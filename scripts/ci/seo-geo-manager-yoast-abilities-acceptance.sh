#!/usr/bin/env bash
set -u -o pipefail

WORDPRESS_IMAGE="wordpress:7.1.0-php8.2-apache"
WPCLI_IMAGE="wordpress:cli-2.12.0-php8.2"
MARIADB_IMAGE="mariadb:11.8.9"
YOAST_VERSION="28.6"

SUFFIX="${GITHUB_RUN_ID:-local}-${GITHUB_RUN_ATTEMPT:-1}-$$"
NETWORK="seo-geo-manager-yoast-${SUFFIX}"
DB_CONTAINER="seo-geo-manager-yoast-db-${SUFFIX}"
WP_CONTAINER="seo-geo-manager-yoast-wp-${SUFFIX}"
WP_VOLUME="seo-geo-manager-yoast-wp-${SUFFIX}"
DB_NAME="wordpress"
DB_USER="wordpress"
DB_PASSWORD="yoast-acceptance-password"
DB_ROOT_PASSWORD="yoast-acceptance-root"
TMP_DIR="$(mktemp -d)"
RUNTIME_LOG="${TMP_DIR}/yoast-runtime.log"

cleanup() {
  docker rm -f "$WP_CONTAINER" >/dev/null 2>&1 || true
  docker rm -f "$DB_CONTAINER" >/dev/null 2>&1 || true
  docker volume rm "$WP_VOLUME" >/dev/null 2>&1 || true
  docker network rm "$NETWORK" >/dev/null 2>&1 || true
  rm -rf "$TMP_DIR"
}
trap cleanup EXIT

fail_acceptance() {
  local message="$1"
  printf '[yoast-abilities] ERROR: %s\n' "$message" >&2
  exit 1
}

wait_for_db() {
  local attempt
  for attempt in $(seq 1 60); do
    if docker exec "$DB_CONTAINER" mariadb-admin ping -h 127.0.0.1 -uroot "-p${DB_ROOT_PASSWORD}" --silent >/dev/null 2>&1; then
      return 0
    fi
    sleep 2
  done
  return 1
}

wait_for_wordpress_files() {
  local attempt
  for attempt in $(seq 1 60); do
    if docker exec "$WP_CONTAINER" test -f /var/www/html/wp-settings.php >/dev/null 2>&1; then
      return 0
    fi
    sleep 2
  done
  return 1
}

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
    "$WPCLI_IMAGE" \
    wp "$@" --path=/var/www/html
}

if grep -R --line-number --fixed-strings '_yoast_wpseo_' packages/seo-geo-manager >/dev/null 2>&1; then
  grep -R --line-number --fixed-strings '_yoast_wpseo_' packages/seo-geo-manager >&2 || true
  fail_acceptance "Manager source contains a direct Yoast private-meta dependency."
fi

docker network create "$NETWORK" >/dev/null || fail_acceptance "Could not create Docker network."
docker volume create "$WP_VOLUME" >/dev/null || fail_acceptance "Could not create WordPress volume."

docker run -d \
  --name "$DB_CONTAINER" \
  --network "$NETWORK" \
  -e "MARIADB_DATABASE=${DB_NAME}" \
  -e "MARIADB_USER=${DB_USER}" \
  -e "MARIADB_PASSWORD=${DB_PASSWORD}" \
  -e "MARIADB_ROOT_PASSWORD=${DB_ROOT_PASSWORD}" \
  "$MARIADB_IMAGE" >/dev/null || fail_acceptance "MariaDB did not start."
wait_for_db || fail_acceptance "MariaDB did not become ready."

docker run -d \
  --name "$WP_CONTAINER" \
  --network "$NETWORK" \
  -p 127.0.0.1::80 \
  -v "${WP_VOLUME}:/var/www/html" \
  -e "WORDPRESS_DB_HOST=${DB_CONTAINER}:3306" \
  -e "WORDPRESS_DB_USER=${DB_USER}" \
  -e "WORDPRESS_DB_PASSWORD=${DB_PASSWORD}" \
  -e "WORDPRESS_DB_NAME=${DB_NAME}" \
  -e WORDPRESS_DEBUG=1 \
  "$WORDPRESS_IMAGE" >/dev/null || fail_acceptance "WordPress did not start."
wait_for_wordpress_files || fail_acceptance "WordPress files did not initialize."

HOST_PORT="$(docker port "$WP_CONTAINER" 80/tcp | awk -F: 'NR == 1 {print $NF}')"
[[ -n "$HOST_PORT" ]] || fail_acceptance "Could not resolve WordPress host port."
BASE_URL="http://127.0.0.1:${HOST_PORT}"

docker exec "$WP_CONTAINER" mkdir -p /var/www/html/wp-content/plugins/seo-geo-manager \
  || fail_acceptance "Could not create Manager plugin directory."
docker cp packages/seo-geo-manager/. "$WP_CONTAINER":/var/www/html/wp-content/plugins/seo-geo-manager/ \
  || fail_acceptance "Could not copy Manager plugin."
docker cp scripts/ci/seo-geo-manager-yoast-abilities-acceptance.php "$WP_CONTAINER":/var/www/html/seo-geo-manager-yoast-abilities-acceptance.php \
  || fail_acceptance "Could not copy Yoast acceptance fixture."
docker exec "$WP_CONTAINER" chown -R www-data:www-data \
  /var/www/html/wp-content/plugins/seo-geo-manager \
  /var/www/html/seo-geo-manager-yoast-abilities-acceptance.php \
  || fail_acceptance "Could not set fixture permissions."

wp_cli core install \
  --url="$BASE_URL" \
  --title="SEO GEO Manager Yoast Acceptance" \
  --admin_user=admin \
  --admin_password=manager-yoast-admin \
  --admin_email=admin@example.test \
  --skip-email >/dev/null || fail_acceptance "WP-CLI could not install WordPress."

wp_cli option update permalink_structure '/%postname%/' >/dev/null \
  || fail_acceptance "Could not enable pretty permalinks."
wp_cli plugin activate seo-geo-manager >/dev/null \
  || fail_acceptance "SEO/GEO Manager could not be activated."
wp_cli plugin install wordpress-seo --version="$YOAST_VERSION" --activate >/dev/null \
  || fail_acceptance "Pinned Yoast SEO ${YOAST_VERSION} could not be installed and activated."

INSTALLED_YOAST="$(wp_cli plugin get wordpress-seo --field=version 2>/dev/null | tr -d '\r')"
[[ "$INSTALLED_YOAST" == "$YOAST_VERSION" ]] \
  || fail_acceptance "Expected Yoast SEO ${YOAST_VERSION}, received ${INSTALLED_YOAST:-unknown}."

printf '[yoast-abilities] Running pinned Yoast SEO %s acceptance.\n' "$YOAST_VERSION"
if ! wp_cli eval-file /var/www/html/seo-geo-manager-yoast-abilities-acceptance.php >"$RUNTIME_LOG" 2>&1; then
  cat "$RUNTIME_LOG"
  fail_acceptance "Yoast SEO public abilities acceptance failed."
fi
cat "$RUNTIME_LOG"
grep -q '"ok":true' "$RUNTIME_LOG" || fail_acceptance "Acceptance output did not confirm success."
grep -q '"yoast_version":"28.6"' "$RUNTIME_LOG" || fail_acceptance "Acceptance did not run against Yoast SEO 28.6."
grep -q '"provider_authority_writable":true' "$RUNTIME_LOG" || fail_acceptance "Yoast provider authority was not writable."
grep -q '"schema_write":true' "$RUNTIME_LOG" || fail_acceptance "Yoast Schema write was not proven."
grep -q '"multiple_provider_conflict":true' "$RUNTIME_LOG" || fail_acceptance "Provider conflict guard was not proven."

printf '[yoast-abilities] Acceptance OK: Yoast SEO %s uses only public WordPress Abilities.\n' "$YOAST_VERSION"
