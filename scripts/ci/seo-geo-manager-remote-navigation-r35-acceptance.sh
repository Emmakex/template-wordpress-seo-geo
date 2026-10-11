#!/usr/bin/env bash
set -euo pipefail

WORDPRESS_IMAGE="wordpress:7.1.0-php8.2-apache"
WPCLI_IMAGE="wordpress:cli-2.12.0-php8.2"
MARIADB_IMAGE="mariadb:11.8.9"
SUFFIX="${GITHUB_RUN_ID:-local}-${GITHUB_RUN_ATTEMPT:-1}-$$"
NETWORK="sgm-r35-navigation-${SUFFIX}"
DB_CONTAINER="sgm-r35-navigation-db-${SUFFIX}"
WP_CONTAINER="sgm-r35-navigation-wp-${SUFFIX}"
WP_VOLUME="sgm-r35-navigation-wp-${SUFFIX}"
DB_NAME="wordpress"
DB_USER="wordpress"
DB_PASSWORD="r35-navigation-password"
DB_ROOT_PASSWORD="r35-navigation-root"
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
FIXTURE="$ROOT/scripts/ci/seo-geo-manager-remote-navigation-r35-acceptance.php"
LOG="$(mktemp)"
EXPECTED_VERSION="$(awk '/^[[:space:]]*\* Version:/ {print $3; exit}' "$ROOT/packages/seo-geo-manager/seo-geo-manager.php")"
EXPECTED_MILESTONE="$(awk -F"'" "/'remote_milestone'[[:space:]]*=>/ {print \$4; exit}" "$ROOT/packages/seo-geo-manager/src/Rest/RemoteReadController.php")"
[[ -n "$EXPECTED_VERSION" ]] || { echo '[r35-navigation] could not resolve current Manager version' >&2; exit 1; }
[[ -n "$EXPECTED_MILESTONE" ]] || { echo '[r35-navigation] could not resolve current remote milestone' >&2; exit 1; }

cleanup() {
  docker rm -f "$WP_CONTAINER" >/dev/null 2>&1 || true
  docker rm -f "$DB_CONTAINER" >/dev/null 2>&1 || true
  docker volume rm "$WP_VOLUME" >/dev/null 2>&1 || true
  docker network rm "$NETWORK" >/dev/null 2>&1 || true
  rm -f "$LOG"
}
trap cleanup EXIT

wp_cli() {
  docker run --rm --network "$NETWORK" --volumes-from "$WP_CONTAINER" --user 33:33 \
    -e HOME=/tmp -e "WORDPRESS_DB_HOST=${DB_CONTAINER}:3306" -e "WORDPRESS_DB_USER=${DB_USER}" \
    -e "WORDPRESS_DB_PASSWORD=${DB_PASSWORD}" -e "WORDPRESS_DB_NAME=${DB_NAME}" \
    "$WPCLI_IMAGE" wp --path=/var/www/html "$@"
}

printf '[r35-navigation] Creating pinned WordPress runtime.\n'
docker network create "$NETWORK" >/dev/null
docker volume create "$WP_VOLUME" >/dev/null
docker run -d --name "$DB_CONTAINER" --network "$NETWORK" \
  -e "MARIADB_DATABASE=${DB_NAME}" -e "MARIADB_USER=${DB_USER}" -e "MARIADB_PASSWORD=${DB_PASSWORD}" \
  -e "MARIADB_ROOT_PASSWORD=${DB_ROOT_PASSWORD}" "$MARIADB_IMAGE" >/dev/null
for i in $(seq 1 60); do
  docker exec "$DB_CONTAINER" mariadb-admin ping -h 127.0.0.1 -uroot "-p${DB_ROOT_PASSWORD}" --silent >/dev/null 2>&1 && break
  [[ "$i" == 60 ]] && { echo '[r35-navigation] database timeout' >&2; exit 1; }
  sleep 2
done

docker run -d --name "$WP_CONTAINER" --network "$NETWORK" -v "$WP_VOLUME:/var/www/html" \
  -v "$ROOT/packages/seo-geo-manager:/var/www/html/wp-content/plugins/seo-geo-manager:ro" \
  -e "WORDPRESS_DB_HOST=${DB_CONTAINER}:3306" -e "WORDPRESS_DB_USER=${DB_USER}" \
  -e "WORDPRESS_DB_PASSWORD=${DB_PASSWORD}" -e "WORDPRESS_DB_NAME=${DB_NAME}" -e WORDPRESS_DEBUG=1 \
  "$WORDPRESS_IMAGE" >/dev/null
for i in $(seq 1 60); do
  docker exec "$WP_CONTAINER" test -f /var/www/html/wp-settings.php >/dev/null 2>&1 && break
  [[ "$i" == 60 ]] && { echo '[r35-navigation] WordPress files timeout' >&2; exit 1; }
  sleep 2
done

wp_cli core install --url='https://manager.test' --title='R3.5 Navigation' --admin_user='manageradmin' \
  --admin_password='r35-navigation-local-only' --admin_email='navigation@example.test' --skip-email >/dev/null
wp_cli option update permalink_structure '/%postname%/' >/dev/null
wp_cli plugin activate seo-geo-manager >/dev/null

docker cp "$FIXTURE" "$WP_CONTAINER:/var/www/html/r35-navigation.php"
docker exec "$WP_CONTAINER" sed -i "s/R3\.5/${EXPECTED_MILESTONE}/g" /var/www/html/r35-navigation.php
docker exec "$WP_CONTAINER" chown www-data:www-data /var/www/html/r35-navigation.php

printf '[r35-navigation] Running Inspect -> Plan -> Approval -> Apply -> Verify -> Rollback acceptance against %s.\n' "$EXPECTED_MILESTONE"
wp_cli eval-file /var/www/html/r35-navigation.php >"$LOG" 2>&1 || { cat "$LOG"; exit 1; }
cat "$LOG"

grep -q '"ok":true' "$LOG"
grep -q "\"plugin_version\":\"${EXPECTED_VERSION}\"" "$LOG"
grep -q '"operation":"navigation-change"' "$LOG"
grep -q '"navigation_inspected_remotely":true' "$LOG"
grep -q '"canonical_fingerprint_shared":true' "$LOG"
grep -q '"preview_no_mutation":true' "$LOG"
grep -q '"public_approval_required":true' "$LOG"
grep -q '"apply_exact":true' "$LOG"
grep -q '"url_only":true' "$LOG"
grep -q '"item_attributes_preserved":true' "$LOG"
grep -q '"unrelated_item_preserved":true' "$LOG"
grep -q '"apply_idempotent_replay":true' "$LOG"
grep -q '"verify_exact":true' "$LOG"
grep -q '"operation_bounded":true' "$LOG"
grep -q '"rollback_exact":true' "$LOG"
grep -q '"rollback_fingerprint_exact":true' "$LOG"
grep -q '"audit_secret_free":true' "$LOG"
grep -q '"generic_proxy":false' "$LOG"
printf '[r35-navigation] SEO/GEO Manager R3.5 navigation regression OK on Manager %s / milestone %s.\n' "$EXPECTED_VERSION" "$EXPECTED_MILESTONE"
