#!/usr/bin/env bash
set -euo pipefail

WORDPRESS_IMAGE="wordpress:7.1.0-php8.2-apache"
WPCLI_IMAGE="wordpress:cli-2.12.0-php8.2"
MARIADB_IMAGE="mariadb:11.8.9"
SUFFIX="${GITHUB_RUN_ID:-local}-${GITHUB_RUN_ATTEMPT:-1}-$$"
NETWORK="seo-geo-permalink-${SUFFIX}"
DB_CONTAINER="seo-geo-permalink-db-${SUFFIX}"
WP_CONTAINER="seo-geo-permalink-wp-${SUFFIX}"
WP_VOLUME="seo-geo-permalink-wp-${SUFFIX}"
DB_NAME="wordpress"
DB_USER="wordpress"
DB_PASSWORD="permalink-acceptance-password"
DB_ROOT_PASSWORD="permalink-acceptance-root"
TMP_DIR="$(mktemp -d)"
RUNTIME_LOG="${TMP_DIR}/permalink-runtime.log"

cleanup() {
  docker rm -f "$WP_CONTAINER" >/dev/null 2>&1 || true
  docker rm -f "$DB_CONTAINER" >/dev/null 2>&1 || true
  docker volume rm "$WP_VOLUME" >/dev/null 2>&1 || true
  docker network rm "$NETWORK" >/dev/null 2>&1 || true
  rm -rf "$TMP_DIR"
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
    "$WPCLI_IMAGE" wp "$@" --path=/var/www/html
}

docker network create "$NETWORK" >/dev/null
docker volume create "$WP_VOLUME" >/dev/null

docker run -d \
  --name "$DB_CONTAINER" \
  --network "$NETWORK" \
  -e "MARIADB_DATABASE=${DB_NAME}" \
  -e "MARIADB_USER=${DB_USER}" \
  -e "MARIADB_PASSWORD=${DB_PASSWORD}" \
  -e "MARIADB_ROOT_PASSWORD=${DB_ROOT_PASSWORD}" \
  "$MARIADB_IMAGE" >/dev/null

for _ in $(seq 1 60); do
  if docker exec "$DB_CONTAINER" mariadb-admin ping -h 127.0.0.1 -uroot "-p${DB_ROOT_PASSWORD}" --silent >/dev/null 2>&1; then
    break
  fi
  sleep 2
done
docker exec "$DB_CONTAINER" mariadb-admin ping -h 127.0.0.1 -uroot "-p${DB_ROOT_PASSWORD}" --silent >/dev/null

docker run -d \
  --name "$WP_CONTAINER" \
  --network "$NETWORK" \
  -v "${WP_VOLUME}:/var/www/html" \
  -e "WORDPRESS_DB_HOST=${DB_CONTAINER}:3306" \
  -e "WORDPRESS_DB_USER=${DB_USER}" \
  -e "WORDPRESS_DB_PASSWORD=${DB_PASSWORD}" \
  -e "WORDPRESS_DB_NAME=${DB_NAME}" \
  -e WORDPRESS_DEBUG=1 \
  -e "WORDPRESS_CONFIG_EXTRA=define( 'WP_DEBUG_LOG', true ); define( 'WP_DEBUG_DISPLAY', false ); @ini_set( 'display_errors', '0' );" \
  "$WORDPRESS_IMAGE" >/dev/null

for _ in $(seq 1 60); do
  if docker exec "$WP_CONTAINER" test -f /var/www/html/wp-settings.php >/dev/null 2>&1; then
    break
  fi
  sleep 2
done
docker exec "$WP_CONTAINER" test -f /var/www/html/wp-settings.php >/dev/null

docker exec "$WP_CONTAINER" mkdir -p /var/www/html/wp-content/plugins/seo-geo-manager
docker cp packages/seo-geo-manager/. "$WP_CONTAINER":/var/www/html/wp-content/plugins/seo-geo-manager/
docker cp scripts/ci/seo-geo-manager-permalink-acceptance.php "$WP_CONTAINER":/var/www/html/seo-geo-manager-permalink-acceptance.php
docker exec "$WP_CONTAINER" chown -R www-data:www-data \
  /var/www/html/wp-content/plugins/seo-geo-manager \
  /var/www/html/seo-geo-manager-permalink-acceptance.php

wp_cli core install \
  --url="http://seo-geo-permalink.test" \
  --title="SEO GEO Permalink Acceptance" \
  --admin_user=admin \
  --admin_password=permalink-acceptance-admin \
  --admin_email=admin@example.test \
  --skip-email >/dev/null
wp_cli plugin activate seo-geo-manager >/dev/null

if ! wp_cli eval-file /var/www/html/seo-geo-manager-permalink-acceptance.php >"$RUNTIME_LOG" 2>&1; then
  cat "$RUNTIME_LOG"
  exit 1
fi
cat "$RUNTIME_LOG"
grep -q '"ok":true' "$RUNTIME_LOG"
grep -q '"collision_guard":true' "$RUNTIME_LOG"

DEBUG_LOG="$(wp_cli eval 'echo WP_CONTENT_DIR . "/debug.log";' 2>/dev/null | tr -d '\r\n')"
if [[ -n "$DEBUG_LOG" ]]; then
  docker exec "$WP_CONTAINER" sh -lc "test ! -s '$DEBUG_LOG'" || {
    docker exec "$WP_CONTAINER" sh -lc "tail -n 80 '$DEBUG_LOG'" || true
    exit 1
  }
fi

printf '[manager] Permalink redirect-plan acceptance OK.\n'
