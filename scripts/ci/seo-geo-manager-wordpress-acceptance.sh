#!/usr/bin/env bash
set -u -o pipefail

PIPELINE="${GITHUB_WORKFLOW:-seo-geo-manager-wordpress-acceptance}"
RUN_ID="${GITHUB_RUN_ID:-local}"
RUN_ATTEMPT="${GITHUB_RUN_ATTEMPT:-1}"
JOB="${GITHUB_JOB:-manager-wordpress-acceptance}"
COMMAND="bash scripts/ci/seo-geo-manager-wordpress-acceptance.sh"

WORDPRESS_IMAGE="wordpress:7.1.0-php8.2-apache"
WPCLI_IMAGE="wordpress:cli-2.12.0-php8.2"
MARIADB_IMAGE="mariadb:11.8.9"

SUFFIX="${RUN_ID}-${RUN_ATTEMPT}-$$"
NETWORK="seo-geo-manager-${SUFFIX}"
DB_CONTAINER="seo-geo-manager-db-${SUFFIX}"
WP_CONTAINER="seo-geo-manager-wp-${SUFFIX}"
WP_VOLUME="seo-geo-manager-wp-${SUFFIX}"

DB_NAME="wordpress"
DB_USER="wordpress"
DB_PASSWORD="manager-acceptance-password"
DB_ROOT_PASSWORD="manager-acceptance-root"

TMP_DIR="$(mktemp -d)"
RUNTIME_LOG="${TMP_DIR}/runtime.log"
CAPABILITIES_LOG="${TMP_DIR}/capabilities-runtime.log"
STRUCTURED_LOG="${TMP_DIR}/structured-runtime.log"
AUTHORITY_LOG="${TMP_DIR}/seo-authority-runtime.log"
ACCEPTANCE_FIXTURE="${TMP_DIR}/seo-geo-manager-acceptance.php"
CAPABILITIES_FIXTURE="${TMP_DIR}/seo-geo-manager-capabilities-acceptance.php"
STRUCTURED_FIXTURE="${TMP_DIR}/seo-geo-manager-structured-acceptance.php"
AUTHORITY_FIXTURE="${TMP_DIR}/seo-geo-manager-seo-authority-acceptance.php"

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
  "step": "manager-wordpress-runtime",
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

printf '[manager] Creating isolated Docker resources.\n'
docker network create "$NETWORK" >/dev/null \
  || fail_acceptance "network-create" "Could not create Docker network" "network created" "docker network create failed"
docker volume create "$WP_VOLUME" >/dev/null \
  || fail_acceptance "volume-create" "Could not create WordPress volume" "volume created" "docker volume create failed"

printf '[manager] Starting MariaDB.\n'
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
  || fail_acceptance "database-ready" "MariaDB did not become ready" "mariadb-admin ping succeeds" "timeout"

printf '[manager] Starting WordPress.\n'
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

printf '[manager] Installing Manager and self-contained SEO/GEO Theme.\n'
docker exec "$WP_CONTAINER" mkdir -p \
  /var/www/html/wp-content/plugins/seo-geo-manager \
  /var/www/html/wp-content/themes/seo-geo-theme \
  || fail_acceptance "package-dirs" "Could not create plugin/theme directories" "directories created" "mkdir failed"

docker cp packages/seo-geo-manager/. "$WP_CONTAINER":/var/www/html/wp-content/plugins/seo-geo-manager/ \
  || fail_acceptance "manager-copy" "Could not copy SEO/GEO Manager" "plugin copied" "docker cp failed"

SELF_CONTAINED_THEME="${TMP_DIR}/seo-geo-theme"
bash scripts/build-theme-package.sh "$SELF_CONTAINED_THEME" \
  || fail_acceptance "theme-build" "Could not build self-contained Theme" "theme built" "build-theme-package.sh failed"
docker cp "$SELF_CONTAINED_THEME/." "$WP_CONTAINER":/var/www/html/wp-content/themes/seo-geo-theme/ \
  || fail_acceptance "theme-copy" "Could not copy SEO/GEO Theme" "theme copied" "docker cp failed"

cp scripts/ci/seo-geo-manager-wordpress-acceptance.php "$ACCEPTANCE_FIXTURE" \
  || fail_acceptance "fixture-prepare" "Could not prepare Manager acceptance fixture" "fixture copied" "cp failed"
cp scripts/ci/seo-geo-manager-capabilities-acceptance.php "$CAPABILITIES_FIXTURE" \
  || fail_acceptance "capabilities-fixture-prepare" "Could not prepare capability acceptance fixture" "fixture copied" "cp failed"
cp scripts/ci/seo-geo-manager-structured-acceptance.php "$STRUCTURED_FIXTURE" \
  || fail_acceptance "structured-fixture-prepare" "Could not prepare structured Manager acceptance fixture" "fixture copied" "cp failed"
cp scripts/ci/seo-geo-manager-seo-authority-acceptance.php "$AUTHORITY_FIXTURE" \
  || fail_acceptance "authority-fixture-prepare" "Could not prepare SEO authority acceptance fixture" "fixture copied" "cp failed"

docker cp "$ACCEPTANCE_FIXTURE" "$WP_CONTAINER":/var/www/html/seo-geo-manager-acceptance.php \
  || fail_acceptance "fixture-copy" "Could not copy Manager acceptance fixture" "fixture copied" "docker cp failed"
docker cp "$CAPABILITIES_FIXTURE" "$WP_CONTAINER":/var/www/html/seo-geo-manager-capabilities-acceptance.php \
  || fail_acceptance "capabilities-fixture-copy" "Could not copy capability acceptance fixture" "fixture copied" "docker cp failed"
docker cp "$STRUCTURED_FIXTURE" "$WP_CONTAINER":/var/www/html/seo-geo-manager-structured-acceptance.php \
  || fail_acceptance "structured-fixture-copy" "Could not copy structured Manager acceptance fixture" "fixture copied" "docker cp failed"
docker cp "$AUTHORITY_FIXTURE" "$WP_CONTAINER":/var/www/html/seo-geo-manager-seo-authority-acceptance.php \
  || fail_acceptance "authority-fixture-copy" "Could not copy SEO authority acceptance fixture" "fixture copied" "docker cp failed"
docker exec "$WP_CONTAINER" chown -R www-data:www-data \
  /var/www/html/wp-content/plugins/seo-geo-manager \
  /var/www/html/wp-content/themes/seo-geo-theme \
  /var/www/html/seo-geo-manager-acceptance.php \
  /var/www/html/seo-geo-manager-capabilities-acceptance.php \
  /var/www/html/seo-geo-manager-structured-acceptance.php \
  /var/www/html/seo-geo-manager-seo-authority-acceptance.php \
  || fail_acceptance "package-permissions" "Could not set WordPress package permissions" "www-data owns packages" "chown failed"

printf '[manager] Installing WordPress.\n'
wp_cli core install \
  --url="$BASE_URL" \
  --title="SEO GEO Manager Acceptance" \
  --admin_user=admin \
  --admin_password=manager-acceptance-admin \
  --admin_email=admin@example.test \
  --skip-email >/dev/null \
  || fail_acceptance "core-install" "WP-CLI could not install WordPress" "core install succeeds" "wp core install failed"

# The leakage detector reasons about logical content paths. Use the same pretty
# permalink contract as a real SEO/GEO site instead of WordPress' default ?page_id=.
wp_cli option update permalink_structure '/%postname%/' >/dev/null \
  || fail_acceptance "permalink-structure" "Could not enable pretty permalinks" "/%postname%/" "option update failed"

wp_cli plugin activate seo-geo-manager >/dev/null \
  || fail_acceptance "manager-activate" "SEO/GEO Manager could not be activated" "plugin active" "activation failed"
wp_cli theme activate seo-geo-theme >/dev/null \
  || fail_acceptance "theme-activate" "SEO/GEO Theme could not be activated" "theme active" "activation failed"

wp_cli plugin is-active seo-geo-manager >/dev/null \
  || fail_acceptance "manager-state" "SEO/GEO Manager is not active" "active" "inactive"
wp_cli theme is-active seo-geo-theme >/dev/null \
  || fail_acceptance "theme-state" "SEO/GEO Theme is not active" "active" "inactive"

printf '[manager] Running M1/M2 WordPress acceptance.\n'
if ! wp_cli eval-file /var/www/html/seo-geo-manager-acceptance.php >"$RUNTIME_LOG" 2>&1; then
  cat "$RUNTIME_LOG"
  fail_acceptance "manager-runtime" "Manager M1/M2 acceptance fixture failed" 'JSON with "ok":true' "$(tail -c 500 "$RUNTIME_LOG" | tr '\n' ' ')"
fi

cat "$RUNTIME_LOG"
grep -q '"ok":true' "$RUNTIME_LOG" \
  || fail_acceptance "manager-result" "Manager acceptance did not emit success marker" '"ok":true' "$(tail -c 500 "$RUNTIME_LOG" | tr '\n' ' ')"

printf '[manager] Running capability least-privilege acceptance.\n'
if ! wp_cli eval-file /var/www/html/seo-geo-manager-capabilities-acceptance.php >"$CAPABILITIES_LOG" 2>&1; then
  cat "$CAPABILITIES_LOG"
  fail_acceptance "capabilities-runtime" "Manager capability least-privilege acceptance failed" 'JSON with "ok":true' "$(tail -c 500 "$CAPABILITIES_LOG" | tr '\n' ' ')"
fi

cat "$CAPABILITIES_LOG"
grep -q '"ok":true' "$CAPABILITIES_LOG" \
  || fail_acceptance "capabilities-result" "Capability acceptance did not emit success marker" '"ok":true' "$(tail -c 500 "$CAPABILITIES_LOG" | tr '\n' ' ')"

printf '[manager] Running Theme structured-content acceptance.\n'
if ! wp_cli eval-file /var/www/html/seo-geo-manager-structured-acceptance.php >"$STRUCTURED_LOG" 2>&1; then
  cat "$STRUCTURED_LOG"
  fail_acceptance "structured-runtime" "Manager Theme structured-content acceptance failed" 'JSON with "ok":true' "$(tail -c 500 "$STRUCTURED_LOG" | tr '\n' ' ')"
fi

cat "$STRUCTURED_LOG"
grep -q '"ok":true' "$STRUCTURED_LOG" \
  || fail_acceptance "structured-result" "Structured Manager acceptance did not emit success marker" '"ok":true' "$(tail -c 500 "$STRUCTURED_LOG" | tr '\n' ' ')"

printf '[manager] Running SEO output authority resolver acceptance.\n'
if ! wp_cli eval-file /var/www/html/seo-geo-manager-seo-authority-acceptance.php >"$AUTHORITY_LOG" 2>&1; then
  cat "$AUTHORITY_LOG"
  fail_acceptance "authority-runtime" "Manager SEO output authority acceptance failed" 'JSON with "ok":true' "$(tail -c 500 "$AUTHORITY_LOG" | tr '\n' ' ')"
fi

cat "$AUTHORITY_LOG"
grep -q '"ok":true' "$AUTHORITY_LOG" \
  || fail_acceptance "authority-result" "SEO authority acceptance did not emit success marker" '"ok":true' "$(tail -c 500 "$AUTHORITY_LOG" | tr '\n' ' ')"

DEBUG_LOG="$(wp_cli eval 'echo WP_CONTENT_DIR . "/debug.log";' 2>/dev/null | tr -d '\r\n')"
if [[ -n "$DEBUG_LOG" ]]; then
  docker exec "$WP_CONTAINER" sh -lc "test ! -s '$DEBUG_LOG'" \
    || {
      docker exec "$WP_CONTAINER" sh -lc "tail -n 80 '$DEBUG_LOG'" || true
      fail_acceptance "debug-log" "WordPress debug.log contains runtime output" "empty debug.log" "runtime warnings/notices/fatals found"
    }
fi

printf '[manager] SEO/GEO Manager WordPress acceptance OK.\n'
