#!/usr/bin/env bash
set -euo pipefail

WORDPRESS_IMAGE="wordpress:7.1.0-php8.2-apache"
WPCLI_IMAGE="wordpress:cli-2.12.0-php8.2"
MARIADB_IMAGE="mariadb:11.8.9"
SUFFIX="${GITHUB_RUN_ID:-local}-${GITHUB_RUN_ATTEMPT:-1}-$$"
NETWORK="sgm-r34-links-${SUFFIX}"
DB_CONTAINER="sgm-r34-links-db-${SUFFIX}"
WP_CONTAINER="sgm-r34-links-wp-${SUFFIX}"
WP_VOLUME="sgm-r34-links-wp-${SUFFIX}"
DB_NAME="wordpress"
DB_USER="wordpress"
DB_PASSWORD="r34-links-password"
DB_ROOT_PASSWORD="r34-links-root"
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
FIXTURE="$ROOT/scripts/ci/seo-geo-manager-remote-contextual-link-r34-acceptance.php"
LOG="$(mktemp)"

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

printf '[r34-links] Creating pinned WordPress runtime.\n'
docker network create "$NETWORK" >/dev/null
docker volume create "$WP_VOLUME" >/dev/null
docker run -d --name "$DB_CONTAINER" --network "$NETWORK" \
  -e "MARIADB_DATABASE=${DB_NAME}" -e "MARIADB_USER=${DB_USER}" -e "MARIADB_PASSWORD=${DB_PASSWORD}" \
  -e "MARIADB_ROOT_PASSWORD=${DB_ROOT_PASSWORD}" "$MARIADB_IMAGE" >/dev/null
for i in $(seq 1 60); do
  docker exec "$DB_CONTAINER" mariadb-admin ping -h 127.0.0.1 -uroot "-p${DB_ROOT_PASSWORD}" --silent >/dev/null 2>&1 && break
  [[ "$i" == 60 ]] && { echo '[r34-links] database timeout' >&2; exit 1; }
  sleep 2
done

docker run -d --name "$WP_CONTAINER" --network "$NETWORK" -v "$WP_VOLUME:/var/www/html" \
  -v "$ROOT/packages/seo-geo-manager:/var/www/html/wp-content/plugins/seo-geo-manager:ro" \
  -e "WORDPRESS_DB_HOST=${DB_CONTAINER}:3306" -e "WORDPRESS_DB_USER=${DB_USER}" \
  -e "WORDPRESS_DB_PASSWORD=${DB_PASSWORD}" -e "WORDPRESS_DB_NAME=${DB_NAME}" -e WORDPRESS_DEBUG=1 \
  "$WORDPRESS_IMAGE" >/dev/null
for i in $(seq 1 60); do
  docker exec "$WP_CONTAINER" test -f /var/www/html/wp-settings.php >/dev/null 2>&1 && break
  [[ "$i" == 60 ]] && { echo '[r34-links] WordPress files timeout' >&2; exit 1; }
  sleep 2
done

wp_cli core install --url='https://manager.test' --title='R3.4 Contextual Links' --admin_user='manageradmin' \
  --admin_password='r34-links-local-only' --admin_email='links@example.test' --skip-email >/dev/null
wp_cli option update permalink_structure '/%postname%/' >/dev/null
wp_cli plugin activate seo-geo-manager >/dev/null

docker cp "$FIXTURE" "$WP_CONTAINER:/var/www/html/r34-contextual-links.php"
docker exec "$WP_CONTAINER" chown www-data:www-data /var/www/html/r34-contextual-links.php

printf '[r34-links] Running Discover -> Plan -> Apply -> Verify -> Rollback acceptance.\n'
wp_cli eval-file /var/www/html/r34-contextual-links.php >"$LOG" 2>&1 || { cat "$LOG"; exit 1; }
cat "$LOG"

grep -q '"ok":true' "$LOG"
grep -q '"plugin_version":"0.3.54"' "$LOG"
grep -q '"operation":"contextual-link-change"' "$LOG"
grep -q '"edge_discovered_remotely":true' "$LOG"
grep -q '"fabricated_edge_rejected":true' "$LOG"
grep -q '"href_only":true' "$LOG"
grep -q '"anchor_preserved":true' "$LOG"
grep -q '"attrs_preserved":true' "$LOG"
grep -q '"preview_no_mutation":true' "$LOG"
grep -q '"apply_exact":true' "$LOG"
grep -q '"apply_idempotent_replay":true' "$LOG"
grep -q '"verify_exact":true' "$LOG"
grep -q '"operation_bounded":true' "$LOG"
grep -q '"rollback_exact":true' "$LOG"
grep -q '"audit_secret_free":true' "$LOG"
grep -q '"generic_proxy":false' "$LOG"
printf '[r34-links] SEO/GEO Manager R3.4 contextual-link acceptance OK.\n'
