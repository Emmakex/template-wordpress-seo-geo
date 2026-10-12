#!/usr/bin/env bash
set -euo pipefail

WORDPRESS_IMAGE="wordpress:7.1.0-php8.2-apache"
WPCLI_IMAGE="wordpress:cli-2.12.0-php8.2"
MARIADB_IMAGE="mariadb:11.8.9"
YOAST_VERSION="28.6"

SUFFIX="${GITHUB_RUN_ID:-local}-${GITHUB_RUN_ATTEMPT:-1}-$$"
NETWORK="seo-geo-manager-remote-yoast-${SUFFIX}"
DB_CONTAINER="seo-geo-manager-remote-yoast-db-${SUFFIX}"
WP_CONTAINER="seo-geo-manager-remote-yoast-wp-${SUFFIX}"
WP_VOLUME="seo-geo-manager-remote-yoast-wp-${SUFFIX}"

DB_NAME="wordpress"
DB_USER="wordpress"
DB_PASSWORD="remote-yoast-acceptance-password"
DB_ROOT_PASSWORD="remote-yoast-acceptance-root"
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
FIXTURE="$ROOT/scripts/ci/seo-geo-manager-remote-yoast-wordpress-acceptance.php"
RUNTIME_LOG="$(mktemp)"

cleanup() {
  docker rm -f "$WP_CONTAINER" >/dev/null 2>&1 || true
  docker rm -f "$DB_CONTAINER" >/dev/null 2>&1 || true
  docker volume rm "$WP_VOLUME" >/dev/null 2>&1 || true
  docker network rm "$NETWORK" >/dev/null 2>&1 || true
  rm -f "$RUNTIME_LOG"
}
trap cleanup EXIT

wp_cli() {
  docker run --rm \
    --network "$NETWORK" \
    --volumes-from "$WP_CONTAINER" \
    --user 33:33 \
    -e HOME=/tmp \
    -e "WORDPRESS_DB_HOST=${DB_CONTAINER}:3306" \
    -e "WORDPRESS_DB_USER=${DB_USER}" \
    -e "WORDPRESS_DB_PASSWORD=${DB_PASSWORD}" \
    -e "WORDPRESS_DB_NAME=${DB_NAME}" \
    "$WPCLI_IMAGE" wp --path=/var/www/html "$@"
}

wait_for_db() {
  for _ in $(seq 1 60); do
    if docker exec "$DB_CONTAINER" mariadb-admin ping -h 127.0.0.1 -uroot "-p${DB_ROOT_PASSWORD}" --silent >/dev/null 2>&1; then
      return 0
    fi
    sleep 2
  done
  return 1
}

wait_for_wordpress() {
  for _ in $(seq 1 60); do
    if docker exec "$WP_CONTAINER" test -f /var/www/html/wp-settings.php >/dev/null 2>&1; then
      return 0
    fi
    sleep 2
  done
  return 1
}

if grep -R --line-number --fixed-strings '_yoast_wpseo_' "$ROOT/packages/seo-geo-manager" >/dev/null 2>&1; then
  grep -R --line-number --fixed-strings '_yoast_wpseo_' "$ROOT/packages/seo-geo-manager" >&2 || true
  echo '[remote-yoast] Manager contains a forbidden direct Yoast private-meta dependency.' >&2
  exit 1
fi

printf '[remote-yoast] Creating pinned fixture network and volume.\n'
docker network create "$NETWORK" >/dev/null
docker volume create "$WP_VOLUME" >/dev/null

printf '[remote-yoast] Starting MariaDB fixture.\n'
docker run -d --name "$DB_CONTAINER" \
  --network "$NETWORK" \
  -e "MARIADB_DATABASE=${DB_NAME}" \
  -e "MARIADB_USER=${DB_USER}" \
  -e "MARIADB_PASSWORD=${DB_PASSWORD}" \
  -e "MARIADB_ROOT_PASSWORD=${DB_ROOT_PASSWORD}" \
  "$MARIADB_IMAGE" >/dev/null
wait_for_db || { echo '[remote-yoast] MariaDB fixture did not become ready.' >&2; exit 1; }

printf '[remote-yoast] Starting WordPress fixture with current Manager package mounted.\n'
docker run -d --name "$WP_CONTAINER" \
  --network "$NETWORK" \
  -v "$WP_VOLUME:/var/www/html" \
  -v "$ROOT/packages/seo-geo-manager:/var/www/html/wp-content/plugins/seo-geo-manager:ro" \
  -e "WORDPRESS_DB_HOST=${DB_CONTAINER}:3306" \
  -e "WORDPRESS_DB_USER=${DB_USER}" \
  -e "WORDPRESS_DB_PASSWORD=${DB_PASSWORD}" \
  -e "WORDPRESS_DB_NAME=${DB_NAME}" \
  -e WORDPRESS_DEBUG=1 \
  "$WORDPRESS_IMAGE" >/dev/null
wait_for_wordpress || { echo '[remote-yoast] WordPress files did not become ready.' >&2; exit 1; }

printf '[remote-yoast] Installing WordPress and pinned Yoast SEO %s.\n' "$YOAST_VERSION"
wp_cli core install \
  --url='https://manager-yoast.test' \
  --title='SEO GEO Manager Remote Yoast Acceptance' \
  --admin_user='manageradmin' \
  --admin_password='manager-yoast-acceptance-local-only' \
  --admin_email='manager-yoast@example.test' \
  --skip-email >/dev/null
wp_cli plugin activate seo-geo-manager >/dev/null
wp_cli plugin install wordpress-seo --version="$YOAST_VERSION" --activate >/dev/null

INSTALLED_YOAST="$(wp_cli plugin get wordpress-seo --field=version 2>/dev/null | tr -d '\r')"
[[ "$INSTALLED_YOAST" == "$YOAST_VERSION" ]] || {
  echo "[remote-yoast] Expected Yoast SEO ${YOAST_VERSION}, received ${INSTALLED_YOAST:-unknown}." >&2
  exit 1
}

# A normal non-Theme WordPress fixture must leave Yoast as the sole resolved SEO authority.
wp_cli theme is-active seo-geo-theme >/dev/null 2>&1 && {
  echo '[remote-yoast] SEO/GEO Theme must not be active in the Yoast authority fixture.' >&2
  exit 1
}

docker cp "$FIXTURE" "$WP_CONTAINER:/var/www/html/seo-geo-manager-remote-yoast-acceptance.php"
docker exec "$WP_CONTAINER" chown www-data:www-data /var/www/html/seo-geo-manager-remote-yoast-acceptance.php

printf '[remote-yoast] Running R3.3 authority-aware Yoast Plan -> Apply -> Verify -> Rollback acceptance.\n'
if ! wp_cli eval-file /var/www/html/seo-geo-manager-remote-yoast-acceptance.php >"$RUNTIME_LOG" 2>&1; then
  cat "$RUNTIME_LOG"
  exit 1
fi
cat "$RUNTIME_LOG"

grep -q '"ok":true' "$RUNTIME_LOG" || { echo '[remote-yoast] R3.3 Yoast acceptance did not emit success marker.' >&2; exit 1; }
grep -q '"plugin_version":"0.3.53"' "$RUNTIME_LOG" || { echo '[remote-yoast] R3.3 Yoast acceptance did not run current Manager version.' >&2; exit 1; }
grep -q '"operation":"seo-authority-change"' "$RUNTIME_LOG" || { echo '[remote-yoast] SEO authority operation proof missing.' >&2; exit 1; }
grep -q '"authority":"yoast"' "$RUNTIME_LOG" || { echo '[remote-yoast] Yoast authority proof missing.' >&2; exit 1; }
grep -q '"adapter":"yoast-abilities"' "$RUNTIME_LOG" || { echo '[remote-yoast] Yoast public abilities adapter proof missing.' >&2; exit 1; }
grep -q '"provider_selected_by_caller":false' "$RUNTIME_LOG" || { echo '[remote-yoast] Authority auto-resolution proof missing.' >&2; exit 1; }
grep -q '"public_abilities_only":true' "$RUNTIME_LOG" || { echo '[remote-yoast] Public abilities-only proof missing.' >&2; exit 1; }
grep -q '"private_yoast_meta":false' "$RUNTIME_LOG" || { echo '[remote-yoast] Private Yoast metadata guard proof missing.' >&2; exit 1; }
grep -q '"verify_exact":true' "$RUNTIME_LOG" || { echo '[remote-yoast] Exact provider fingerprint verification proof missing.' >&2; exit 1; }
grep -q '"rollback_exact":true' "$RUNTIME_LOG" || { echo '[remote-yoast] Exact provider rollback proof missing.' >&2; exit 1; }
grep -q '"generic_proxy":false' "$RUNTIME_LOG" || { echo '[remote-yoast] Generic proxy guard proof missing.' >&2; exit 1; }

printf '[remote-yoast] SEO/GEO Manager R3.3 Yoast 28.6 acceptance OK.\n'
