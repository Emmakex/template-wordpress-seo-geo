#!/usr/bin/env bash
set -u -o pipefail

PIPELINE="${GITHUB_WORKFLOW:-seo-geo-manager-featured-media-acceptance}"
RUN_ID="${GITHUB_RUN_ID:-local}"
RUN_ATTEMPT="${GITHUB_RUN_ATTEMPT:-1}"
JOB="${GITHUB_JOB:-manager-featured-media-acceptance}"
COMMAND="bash scripts/ci/seo-geo-manager-featured-media-acceptance.sh"

WORDPRESS_IMAGE="wordpress:7.1.0-php8.2-apache"
WPCLI_IMAGE="wordpress:cli-2.12.0-php8.2"
MARIADB_IMAGE="mariadb:11.8.9"

SUFFIX="${RUN_ID}-${RUN_ATTEMPT}-featured-media-$$"
NETWORK="seo-geo-manager-${SUFFIX}"
DB_CONTAINER="seo-geo-manager-db-${SUFFIX}"
WP_CONTAINER="seo-geo-manager-wp-${SUFFIX}"
WP_VOLUME="seo-geo-manager-wp-${SUFFIX}"

DB_NAME="wordpress"
DB_USER="wordpress"
DB_PASSWORD="manager-featured-media-db"
DB_ROOT_PASSWORD="manager-featured-media-root"

TMP_DIR="$(mktemp -d)"
RUNTIME_LOG="${TMP_DIR}/featured-media.log"
THEME_RUNTIME_LOG="${TMP_DIR}/theme-media.log"
NATIVE_RUNTIME_LOG="${TMP_DIR}/native-translations.log"
THEME_BUILD="${TMP_DIR}/seo-geo-theme"

signature() {
  printf '%s' "$1" | sha256sum | cut -c1-12
}

cleanup() {
  docker rm -f "$WP_CONTAINER" >/dev/null 2>&1 || true
  docker rm -f "$DB_CONTAINER" >/dev/null 2>&1 || true
  docker volume rm "$WP_VOLUME" >/dev/null 2>&1 || true
  docker network rm "$NETWORK" >/dev/null 2>&1 || true
  rm -rf "$TMP_DIR"
}
trap cleanup EXIT

fail_acceptance() {
  local code="$1"
  local primary="$2"
  local expected="$3"
  local received="$4"
  local sig
  sig="$(signature "${code}:${primary}")"

  cat <<JSON
{
  "schema_version": 1,
  "pipeline": "${PIPELINE}",
  "run_id": "${RUN_ID}",
  "run_attempt": "${RUN_ATTEMPT}",
  "job": "${JOB}",
  "step": "manager-featured-media-runtime",
  "command": "${COMMAND}",
  "exit_code": 1,
  "primary_error": "${primary}",
  "expected": "${expected}",
  "received": "${received}",
  "error_signature": "${sig}"
}
JSON
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
    wp \
    "$@" \
    --path=/var/www/html
}

printf '[manager-featured-media] Creating isolated WordPress fixture.\n'
docker network create "$NETWORK" >/dev/null \
  || fail_acceptance "network-create" "Could not create Docker network" "network created" "failed"
docker volume create "$WP_VOLUME" >/dev/null \
  || fail_acceptance "volume-create" "Could not create WordPress volume" "volume created" "failed"

docker run -d \
  --name "$DB_CONTAINER" \
  --network "$NETWORK" \
  -e "MARIADB_DATABASE=${DB_NAME}" \
  -e "MARIADB_USER=${DB_USER}" \
  -e "MARIADB_PASSWORD=${DB_PASSWORD}" \
  -e "MARIADB_ROOT_PASSWORD=${DB_ROOT_PASSWORD}" \
  "$MARIADB_IMAGE" >/dev/null \
  || fail_acceptance "database-start" "MariaDB did not start" "running database" "docker run failed"
wait_for_db \
  || fail_acceptance "database-ready" "MariaDB did not become ready" "ready database" "timeout"

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
  -e "WORDPRESS_CONFIG_EXTRA=define( 'WP_DEBUG_LOG', true ); define( 'WP_DEBUG_DISPLAY', false ); @ini_set( 'display_errors', '0' );" \
  "$WORDPRESS_IMAGE" >/dev/null \
  || fail_acceptance "wordpress-start" "WordPress did not start" "running WordPress" "docker run failed"
wait_for_wordpress_files \
  || fail_acceptance "wordpress-files" "WordPress files did not initialize" "wp-settings.php exists" "timeout"

HOST_PORT="$(docker port "$WP_CONTAINER" 80/tcp | awk -F: 'NR == 1 {print $NF}')"
[[ -n "$HOST_PORT" ]] \
  || fail_acceptance "wordpress-port" "Could not resolve WordPress host port" "non-empty port" "empty"
BASE_URL="http://127.0.0.1:${HOST_PORT}"

printf '[manager-featured-media] Installing WordPress, Manager and self-contained Theme.\n'
docker exec "$WP_CONTAINER" mkdir -p \
  /var/www/html/wp-content/plugins/seo-geo-manager \
  /var/www/html/wp-content/themes/seo-geo-theme \
  || fail_acceptance "package-dirs" "Could not create plugin/theme directories" "directories created" "mkdir failed"
docker cp packages/seo-geo-manager/. "$WP_CONTAINER":/var/www/html/wp-content/plugins/seo-geo-manager/ \
  || fail_acceptance "manager-copy" "Could not copy Manager" "plugin copied" "docker cp failed"
bash scripts/build-theme-package.sh "$THEME_BUILD" \
  || fail_acceptance "theme-build" "Could not build self-contained Theme" "theme built" "build-theme-package.sh failed"
docker cp "$THEME_BUILD/." "$WP_CONTAINER":/var/www/html/wp-content/themes/seo-geo-theme/ \
  || fail_acceptance "theme-copy" "Could not copy SEO/GEO Theme" "theme copied" "docker cp failed"
docker cp scripts/ci/seo-geo-manager-featured-media-acceptance.php "$WP_CONTAINER":/var/www/html/seo-geo-manager-featured-media-acceptance.php \
  || fail_acceptance "fixture-copy" "Could not copy featured-media fixture" "fixture copied" "docker cp failed"
docker cp scripts/ci/seo-geo-manager-theme-media-acceptance.php "$WP_CONTAINER":/var/www/html/seo-geo-manager-theme-media-acceptance.php \
  || fail_acceptance "theme-fixture-copy" "Could not copy Theme-media fixture" "fixture copied" "docker cp failed"
docker cp scripts/ci/seo-geo-manager-native-translations-acceptance.php "$WP_CONTAINER":/var/www/html/seo-geo-manager-native-translations-acceptance.php \
  || fail_acceptance "native-fixture-copy" "Could not copy native-translation fixture" "fixture copied" "docker cp failed"
docker exec "$WP_CONTAINER" chown -R www-data:www-data \
  /var/www/html/wp-content/plugins/seo-geo-manager \
  /var/www/html/wp-content/themes/seo-geo-theme \
  /var/www/html/seo-geo-manager-featured-media-acceptance.php \
  /var/www/html/seo-geo-manager-theme-media-acceptance.php \
  /var/www/html/seo-geo-manager-native-translations-acceptance.php \
  || fail_acceptance "permissions" "Could not set fixture permissions" "www-data owns fixture" "chown failed"

wp_cli core install \
  --url="$BASE_URL" \
  --title="SEO GEO Manager Featured Media Acceptance" \
  --admin_user=admin \
  --admin_password=manager-featured-media-admin \
  --admin_email=admin@example.test \
  --skip-email >/dev/null \
  || fail_acceptance "core-install" "Could not install WordPress" "core install succeeds" "failed"

wp_cli option update permalink_structure '/%postname%/' >/dev/null \
  || fail_acceptance "permalink-structure" "Could not enable pretty permalinks" "/%postname%/" "failed"
wp_cli plugin activate seo-geo-manager >/dev/null \
  || fail_acceptance "manager-activate" "Could not activate Manager" "plugin active" "failed"
wp_cli theme activate seo-geo-theme >/dev/null \
  || fail_acceptance "theme-activate" "Could not activate SEO/GEO Theme" "theme active" "failed"
wp_cli option update seo_geo_active_preset corporate >/dev/null \
  || fail_acceptance "corporate-preset" "Could not activate Corporate Theme preset" "seo_geo_active_preset=corporate" "failed"

printf '[manager-featured-media] Running C6.6A featured-media acceptance.\n'
if ! wp_cli eval-file /var/www/html/seo-geo-manager-featured-media-acceptance.php >"$RUNTIME_LOG" 2>&1; then
  cat "$RUNTIME_LOG"
  fail_acceptance "featured-media-runtime" "Featured-media acceptance failed" 'JSON with "ok":true' "$(tail -c 1200 "$RUNTIME_LOG" | tr '\n' ' ')"
fi
cat "$RUNTIME_LOG"
grep -q '"ok":true' "$RUNTIME_LOG" \
  || fail_acceptance "featured-media-result" "Featured-media acceptance did not emit success marker" '"ok":true' "$(tail -c 1200 "$RUNTIME_LOG" | tr '\n' ' ')"

FEATURED_VERSION="$(sed -n 's/.*"plugin_version":"\([^"]*\)".*/\1/p' "$RUNTIME_LOG" | tail -n 1)"
[[ -n "$FEATURED_VERSION" ]] \
  || fail_acceptance "featured-media-version-missing" "Featured-media fixture did not report the Manager version" ">=0.3.42" "missing"
LOWEST_VERSION="$(printf '%s\n' '0.3.42' "$FEATURED_VERSION" | sort -V | head -n 1)"
[[ "$LOWEST_VERSION" == '0.3.42' ]] \
  || fail_acceptance "featured-media-version" "Featured-media fixture executed a Manager older than the C6.6A contract" ">=0.3.42" "$FEATURED_VERSION"

printf '[manager-featured-media] Running C6.6B Theme strategic-media acceptance.\n'
if ! wp_cli eval-file /var/www/html/seo-geo-manager-theme-media-acceptance.php >"$THEME_RUNTIME_LOG" 2>&1; then
  cat "$THEME_RUNTIME_LOG"
  fail_acceptance "theme-media-runtime" "Theme strategic-media acceptance failed" 'JSON with "ok":true' "$(tail -c 1800 "$THEME_RUNTIME_LOG" | tr '\n' ' ')"
fi
cat "$THEME_RUNTIME_LOG"
grep -q '"ok":true' "$THEME_RUNTIME_LOG" \
  || fail_acceptance "theme-media-result" "Theme strategic-media acceptance did not emit success marker" '"ok":true' "$(tail -c 1800 "$THEME_RUNTIME_LOG" | tr '\n' ' ')"
THEME_MEDIA_VERSION="$(sed -n 's/.*"plugin_version":"\([^"]*\)".*/\1/p' "$THEME_RUNTIME_LOG" | tail -n 1)"
[[ -n "$THEME_MEDIA_VERSION" ]] \
  || fail_acceptance "theme-media-version-missing" "Theme-media fixture did not report the Manager version" ">=0.3.43" "missing"
LOWEST_THEME_VERSION="$(printf '%s\n' '0.3.43' "$THEME_MEDIA_VERSION" | sort -V | head -n 1)"
[[ "$LOWEST_THEME_VERSION" == '0.3.43' ]] \
  || fail_acceptance "theme-media-version" "Theme-media fixture executed a Manager older than the C6.6B contract" ">=0.3.43" "$THEME_MEDIA_VERSION"

printf '[manager-featured-media] Running C4.3 native translation relationship acceptance.\n'
if ! wp_cli eval-file /var/www/html/seo-geo-manager-native-translations-acceptance.php >"$NATIVE_RUNTIME_LOG" 2>&1; then
  cat "$NATIVE_RUNTIME_LOG"
  fail_acceptance "native-translation-runtime" "Native translation relationship acceptance failed" 'JSON with "ok":true' "$(tail -c 2200 "$NATIVE_RUNTIME_LOG" | tr '\n' ' ')"
fi
cat "$NATIVE_RUNTIME_LOG"
grep -q '"ok":true' "$NATIVE_RUNTIME_LOG" \
  || fail_acceptance "native-translation-result" "Native translation acceptance did not emit success marker" '"ok":true' "$(tail -c 2200 "$NATIVE_RUNTIME_LOG" | tr '\n' ' ')"
NATIVE_VERSION="$(sed -n 's/.*"plugin_version":"\([^"]*\)".*/\1/p' "$NATIVE_RUNTIME_LOG" | tail -n 1)"
[[ -n "$NATIVE_VERSION" ]] \
  || fail_acceptance "native-translation-version-missing" "Native translation fixture did not report the Manager version" ">=0.3.46" "missing"
LOWEST_NATIVE_VERSION="$(printf '%s\n' '0.3.46' "$NATIVE_VERSION" | sort -V | head -n 1)"
[[ "$LOWEST_NATIVE_VERSION" == '0.3.46' ]] \
  || fail_acceptance "native-translation-version" "Native translation fixture executed a Manager older than the C4 native relationship contract" ">=0.3.46" "$NATIVE_VERSION"

DEBUG_LOG="$(wp_cli eval 'echo WP_CONTENT_DIR . "/debug.log";' 2>/dev/null | tr -d '\r\n')"
if [[ -n "$DEBUG_LOG" ]]; then
  docker exec "$WP_CONTAINER" sh -lc "test ! -s '$DEBUG_LOG'" \
    || {
      docker exec "$WP_CONTAINER" sh -lc "tail -n 80 '$DEBUG_LOG'" || true
      fail_acceptance "debug-log" "WordPress debug.log contains runtime output" "empty debug.log" "runtime warnings/notices/fatals found"
    }
fi

printf '[manager-featured-media] SEO/GEO Manager featured-media, strategic-media and native-translation acceptance OK.\n'
