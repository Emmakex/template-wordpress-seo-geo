#!/usr/bin/env bash
set -euo pipefail

WORDPRESS_IMAGE="wordpress:7.1.0-php8.2-apache"
WPCLI_IMAGE="wordpress:cli-2.12.0-php8.2"
MARIADB_IMAGE="mariadb:11.8.9"
SUFFIX="${GITHUB_RUN_ID:-local}-${GITHUB_RUN_ATTEMPT:-1}-$$"
NETWORK="sgm-r33-native-${SUFFIX}"
DB_CONTAINER="sgm-r33-native-db-${SUFFIX}"
WP_CONTAINER="sgm-r33-native-wp-${SUFFIX}"
WP_VOLUME="sgm-r33-native-wp-${SUFFIX}"
DB_NAME="wordpress"
DB_USER="wordpress"
DB_PASSWORD="r33-native-password"
DB_ROOT_PASSWORD="r33-native-root"
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
FIXTURE="$ROOT/scripts/ci/seo-geo-manager-remote-seo-r33-acceptance.php"
LOG="$(mktemp)"
THEME_BUILD_DIR="$(mktemp -d)"

cleanup() {
  docker rm -f "$WP_CONTAINER" >/dev/null 2>&1 || true
  docker rm -f "$DB_CONTAINER" >/dev/null 2>&1 || true
  docker volume rm "$WP_VOLUME" >/dev/null 2>&1 || true
  docker network rm "$NETWORK" >/dev/null 2>&1 || true
  rm -f "$LOG"
  rm -rf "$THEME_BUILD_DIR"
}
trap cleanup EXIT

wp_cli() {
  docker run --rm --network "$NETWORK" --volumes-from "$WP_CONTAINER" --user 33:33 \
    -e HOME=/tmp -e "WORDPRESS_DB_HOST=${DB_CONTAINER}:3306" -e "WORDPRESS_DB_USER=${DB_USER}" \
    -e "WORDPRESS_DB_PASSWORD=${DB_PASSWORD}" -e "WORDPRESS_DB_NAME=${DB_NAME}" \
    "$WPCLI_IMAGE" wp --path=/var/www/html "$@"
}

for _ in $(seq 1 1); do
  docker network create "$NETWORK" >/dev/null
  docker volume create "$WP_VOLUME" >/dev/null
  docker run -d --name "$DB_CONTAINER" --network "$NETWORK" \
    -e "MARIADB_DATABASE=${DB_NAME}" -e "MARIADB_USER=${DB_USER}" -e "MARIADB_PASSWORD=${DB_PASSWORD}" \
    -e "MARIADB_ROOT_PASSWORD=${DB_ROOT_PASSWORD}" "$MARIADB_IMAGE" >/dev/null
  for i in $(seq 1 60); do
    docker exec "$DB_CONTAINER" mariadb-admin ping -h 127.0.0.1 -uroot "-p${DB_ROOT_PASSWORD}" --silent >/dev/null 2>&1 && break
    [[ "$i" == 60 ]] && { echo '[r33-native] database timeout' >&2; exit 1; }
    sleep 2
  done
  docker run -d --name "$WP_CONTAINER" --network "$NETWORK" -v "$WP_VOLUME:/var/www/html" \
    -v "$ROOT/packages/seo-geo-manager:/var/www/html/wp-content/plugins/seo-geo-manager:ro" \
    -e "WORDPRESS_DB_HOST=${DB_CONTAINER}:3306" -e "WORDPRESS_DB_USER=${DB_USER}" \
    -e "WORDPRESS_DB_PASSWORD=${DB_PASSWORD}" -e "WORDPRESS_DB_NAME=${DB_NAME}" "$WORDPRESS_IMAGE" >/dev/null
  for i in $(seq 1 60); do
    docker exec "$WP_CONTAINER" test -f /var/www/html/wp-settings.php >/dev/null 2>&1 && break
    [[ "$i" == 60 ]] && { echo '[r33-native] WordPress files timeout' >&2; exit 1; }
    sleep 2
  done
done

bash "$ROOT/scripts/build-theme-package.sh" "$THEME_BUILD_DIR/seo-geo-theme"
docker exec "$WP_CONTAINER" mkdir -p /var/www/html/wp-content/themes/seo-geo-theme
docker cp "$THEME_BUILD_DIR/seo-geo-theme/." "$WP_CONTAINER:/var/www/html/wp-content/themes/seo-geo-theme/"
docker exec "$WP_CONTAINER" chown -R www-data:www-data /var/www/html/wp-content/themes/seo-geo-theme

wp_cli core install --url='https://manager.test' --title='R3.3 Native SEO' --admin_user='manageradmin' \
  --admin_password='r33-native-local-only' --admin_email='native@example.test' --skip-email >/dev/null
wp_cli plugin activate seo-geo-manager >/dev/null
wp_cli theme activate seo-geo-theme >/dev/null
wp_cli option update seo_geo_active_preset corporate >/dev/null

docker cp "$FIXTURE" "$WP_CONTAINER:/var/www/html/r33-native-seo.php"
docker exec "$WP_CONTAINER" chown www-data:www-data /var/www/html/r33-native-seo.php
wp_cli eval-file /var/www/html/r33-native-seo.php >"$LOG" 2>&1 || { cat "$LOG"; exit 1; }
cat "$LOG"

grep -q '"ok":true' "$LOG"
grep -q '"plugin_version":"0.3.53"' "$LOG"
grep -q '"operation":"seo-authority-change"' "$LOG"
grep -q '"authority":"seo-geo-theme"' "$LOG"
grep -q '"authority_auto_resolved":true' "$LOG"
grep -q '"apply_exact":true' "$LOG"
grep -q '"verify_exact":true' "$LOG"
grep -q '"rollback_exact":true' "$LOG"
grep -q '"generic_proxy":false' "$LOG"
printf '[r33-native] Theme-native SEO authority acceptance OK.\n'
