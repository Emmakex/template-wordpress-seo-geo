#!/usr/bin/env bash
set -euo pipefail

WORDPRESS_IMAGE="wordpress:7.1.0-php8.2-apache"
WPCLI_IMAGE="wordpress:cli-2.12.0-php8.2"
MARIADB_IMAGE="mariadb:11.8.9"

SUFFIX="${GITHUB_RUN_ID:-local}-${GITHUB_RUN_ATTEMPT:-1}-$$"
NETWORK="seo-geo-manager-remote-seo-${SUFFIX}"
DB_CONTAINER="seo-geo-manager-remote-seo-db-${SUFFIX}"
WP_CONTAINER="seo-geo-manager-remote-seo-wp-${SUFFIX}"
WP_VOLUME="seo-geo-manager-remote-seo-wp-${SUFFIX}"

DB_NAME="wordpress"
DB_USER="wordpress"
DB_PASSWORD="remote-seo-acceptance-password"
DB_ROOT_PASSWORD="remote-seo-acceptance-root"
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
FIXTURE="$ROOT/scripts/ci/seo-geo-manager-remote-seo-wordpress-acceptance.php"
RUNTIME_LOG="$(mktemp)"
THEME_BUILD_DIR="$(mktemp -d)"

cleanup() {
  docker rm -f "$WP_CONTAINER" >/dev/null 2>&1 || true
  docker rm -f "$DB_CONTAINER" >/dev/null 2>&1 || true
  docker volume rm "$WP_VOLUME" >/dev/null 2>&1 || true
  docker network rm "$NETWORK" >/dev/null 2>&1 || true
  rm -f "$RUNTIME_LOG"
  rm -rf "$THEME_BUILD_DIR"
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

printf '[remote-seo] Creating pinned fixture network and volume.\n'
docker network create "$NETWORK" >/dev/null
docker volume create "$WP_VOLUME" >/dev/null

printf '[remote-seo] Starting MariaDB fixture.\n'
docker run -d --name "$DB_CONTAINER" \
  --network "$NETWORK" \
  -e "MARIADB_DATABASE=${DB_NAME}" \
  -e "MARIADB_USER=${DB_USER}" \
  -e "MARIADB_PASSWORD=${DB_PASSWORD}" \
  -e "MARIADB_ROOT_PASSWORD=${DB_ROOT_PASSWORD}" \
  "$MARIADB_IMAGE" >/dev/null
wait_for_db || { echo '[remote-seo] MariaDB fixture did not become ready.' >&2; exit 1; }

printf '[remote-seo] Starting WordPress fixture with current Manager package mounted.\n'
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
wait_for_wordpress || { echo '[remote-seo] WordPress files did not become ready.' >&2; exit 1; }

printf '[remote-seo] Building self-contained SEO/GEO Theme.\n'
bash "$ROOT/scripts/build-theme-package.sh" "$THEME_BUILD_DIR/seo-geo-theme"
docker exec "$WP_CONTAINER" mkdir -p /var/www/html/wp-content/themes/seo-geo-theme
docker cp "$THEME_BUILD_DIR/seo-geo-theme/." "$WP_CONTAINER:/var/www/html/wp-content/themes/seo-geo-theme/"
docker exec "$WP_CONTAINER" chown -R www-data:www-data /var/www/html/wp-content/themes/seo-geo-theme

printf '[remote-seo] Installing WordPress fixture.\n'
wp_cli core install \
  --url='https://manager.test' \
  --title='SEO GEO Manager R3.3 Native SEO Acceptance' \
  --admin_user='manageradmin' \
  --admin_password='manager-seo-acceptance-local-only' \
  --admin_email='manager-seo@example.test' \
  --skip-email >/dev/null
wp_cli plugin activate seo-geo-manager >/dev/null
wp_cli theme activate seo-geo-theme >/dev/null
wp_cli option update seo_geo_active_preset corporate >/dev/null

docker cp "$FIXTURE" "$WP_CONTAINER:/var/www/html/seo-geo-manager-remote-seo-acceptance.php"
docker exec "$WP_CONTAINER" chown www-data:www-data /var/www/html/seo-geo-manager-remote-seo-acceptance.php

printf '[remote-seo] Running R3.3 Theme-native SEO Plan -> Apply -> Verify -> Rollback acceptance.\n'
if ! wp_cli eval-file /var/www/html/seo-geo-manager-remote-seo-acceptance.php >"$RUNTIME_LOG" 2>&1; then
  cat "$RUNTIME_LOG"
  exit 1
fi
cat "$RUNTIME_LOG"

grep -q '"ok":true' "$RUNTIME_LOG" || { echo '[remote-seo] R3.3 Theme-native acceptance did not emit success marker.' >&2; exit 1; }
grep -q '"plugin_version":"0.3.53"' "$RUNTIME_LOG" || { echo '[remote-seo] R3.3 Theme-native acceptance did not run current Manager version.' >&2; exit 1; }
grep -q '"operation":"seo-authority-change"' "$RUNTIME_LOG" || { echo '[remote-seo] SEO authority operation proof missing.' >&2; exit 1; }
grep -q '"authority":"seo-geo-theme"' "$RUNTIME_LOG" || { echo '[remote-seo] Theme-native authority proof missing.' >&2; exit 1; }
grep -q '"adapter":"seo-geo-theme-native"' "$RUNTIME_LOG" || { echo '[remote-seo] Theme-native adapter proof missing.' >&2; exit 1; }
grep -q '"authority_auto_resolved":true' "$RUNTIME_LOG" || { echo '[remote-seo] Authority auto-resolution proof missing.' >&2; exit 1; }
grep -q '"verify_exact":true' "$RUNTIME_LOG" || { echo '[remote-seo] Exact native SEO verification proof missing.' >&2; exit 1; }
grep -q '"rollback_exact":true' "$RUNTIME_LOG" || { echo '[remote-seo] Exact native SEO rollback proof missing.' >&2; exit 1; }
grep -q '"generic_proxy":false' "$RUNTIME_LOG" || { echo '[remote-seo] Generic proxy guard proof missing.' >&2; exit 1; }

printf '[remote-seo] SEO/GEO Manager R3.3 Theme-native SEO acceptance OK.\n'
