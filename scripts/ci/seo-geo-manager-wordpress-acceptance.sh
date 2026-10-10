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
THEME_MODEL_LOG="${TMP_DIR}/theme-model-runtime.log"
C6_READ_LOG="${TMP_DIR}/c6-read-runtime.log"
C6_WRITE_LOG="${TMP_DIR}/c6-write-runtime.log"
C6_MEDIA_LOG="${TMP_DIR}/c6-media-runtime.log"
C6_UPLOAD_LOG="${TMP_DIR}/c6-upload-runtime.log"
AUTHORITY_LOG="${TMP_DIR}/seo-authority-runtime.log"
ACCEPTANCE_FIXTURE="${TMP_DIR}/seo-geo-manager-acceptance.php"
CAPABILITIES_FIXTURE="${TMP_DIR}/seo-geo-manager-capabilities-acceptance.php"
STRUCTURED_FIXTURE="${TMP_DIR}/seo-geo-manager-structured-acceptance.php"
THEME_MODEL_FIXTURE="${TMP_DIR}/seo-geo-manager-theme-model-read-acceptance.php"
C6_READ_FIXTURE="${TMP_DIR}/seo-geo-manager-link-media-read-acceptance.php"
C6_WRITE_FIXTURE="${TMP_DIR}/seo-geo-manager-contextual-link-write-acceptance.php"
C6_MEDIA_FIXTURE="${TMP_DIR}/seo-geo-manager-media-alt-write-acceptance.php"
C6_UPLOAD_FIXTURE="${TMP_DIR}/seo-geo-manager-media-upload-acceptance.php"
AUTHORITY_FIXTURE="${TMP_DIR}/seo-geo-manager-seo-authority-acceptance.php"
MANAGER_VERSION="$(awk '/^ \* Version:/ {print $3; exit}' packages/seo-geo-manager/seo-geo-manager.php | tr -d '\r')"

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

[[ -n "$MANAGER_VERSION" ]] \
  || fail_acceptance "manager-version" "Could not resolve Manager version" "non-empty plugin version" "empty"

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
cp scripts/ci/seo-geo-manager-theme-model-read-acceptance.php "$THEME_MODEL_FIXTURE" \
  || fail_acceptance "theme-model-fixture-prepare" "Could not prepare Theme model reader fixture" "fixture copied" "cp failed"
cp scripts/ci/seo-geo-manager-link-media-read-acceptance.php "$C6_READ_FIXTURE" \
  || fail_acceptance "c6-read-fixture-prepare" "Could not prepare C6.1 read acceptance fixture" "fixture copied" "cp failed"
cp scripts/ci/seo-geo-manager-contextual-link-write-acceptance.php "$C6_WRITE_FIXTURE" \
  || fail_acceptance "c6-write-fixture-prepare" "Could not prepare C6.2 contextual-link write fixture" "fixture copied" "cp failed"
cp scripts/ci/seo-geo-manager-media-alt-write-acceptance.php "$C6_MEDIA_FIXTURE" \
  || fail_acceptance "c6-media-fixture-prepare" "Could not prepare C6.3/C6.4 media fixture" "fixture copied" "cp failed"
cp scripts/ci/seo-geo-manager-media-upload-acceptance.php "$C6_UPLOAD_FIXTURE" \
  || fail_acceptance "c6-upload-fixture-prepare" "Could not prepare C6.5 media-upload fixture" "fixture copied" "cp failed"
cp scripts/ci/seo-geo-manager-seo-authority-acceptance.php "$AUTHORITY_FIXTURE" \
  || fail_acceptance "authority-fixture-prepare" "Could not prepare SEO authority acceptance fixture" "fixture copied" "cp failed"

docker cp "$ACCEPTANCE_FIXTURE" "$WP_CONTAINER":/var/www/html/seo-geo-manager-acceptance.php \
  || fail_acceptance "fixture-copy" "Could not copy Manager acceptance fixture" "fixture copied" "docker cp failed"
docker cp "$CAPABILITIES_FIXTURE" "$WP_CONTAINER":/var/www/html/seo-geo-manager-capabilities-acceptance.php \
  || fail_acceptance "capabilities-fixture-copy" "Could not copy capability acceptance fixture" "fixture copied" "docker cp failed"
docker cp "$STRUCTURED_FIXTURE" "$WP_CONTAINER":/var/www/html/seo-geo-manager-structured-acceptance.php \
  || fail_acceptance "structured-fixture-copy" "Could not copy structured Manager acceptance fixture" "fixture copied" "docker cp failed"
docker cp "$THEME_MODEL_FIXTURE" "$WP_CONTAINER":/var/www/html/seo-geo-manager-theme-model-read-acceptance.php \
  || fail_acceptance "theme-model-fixture-copy" "Could not copy Theme model reader fixture" "fixture copied" "docker cp failed"
docker cp "$C6_READ_FIXTURE" "$WP_CONTAINER":/var/www/html/seo-geo-manager-link-media-read-acceptance.php \
  || fail_acceptance "c6-read-fixture-copy" "Could not copy C6.1 read acceptance fixture" "fixture copied" "docker cp failed"
docker cp "$C6_WRITE_FIXTURE" "$WP_CONTAINER":/var/www/html/seo-geo-manager-contextual-link-write-acceptance.php \
  || fail_acceptance "c6-write-fixture-copy" "Could not copy C6.2 contextual-link write fixture" "fixture copied" "docker cp failed"
docker cp "$C6_MEDIA_FIXTURE" "$WP_CONTAINER":/var/www/html/seo-geo-manager-media-alt-write-acceptance.php \
  || fail_acceptance "c6-media-fixture-copy" "Could not copy C6.3/C6.4 media fixture" "fixture copied" "docker cp failed"
docker cp "$C6_UPLOAD_FIXTURE" "$WP_CONTAINER":/var/www/html/seo-geo-manager-media-upload-acceptance.php \
  || fail_acceptance "c6-upload-fixture-copy" "Could not copy C6.5 media-upload fixture" "fixture copied" "docker cp failed"
docker cp "$AUTHORITY_FIXTURE" "$WP_CONTAINER":/var/www/html/seo-geo-manager-seo-authority-acceptance.php \
  || fail_acceptance "authority-fixture-copy" "Could not copy SEO authority acceptance fixture" "fixture copied" "docker cp failed"
docker exec "$WP_CONTAINER" chown -R www-data:www-data \
  /var/www/html/wp-content/plugins/seo-geo-manager \
  /var/www/html/wp-content/themes/seo-geo-theme \
  /var/www/html/seo-geo-manager-acceptance.php \
  /var/www/html/seo-geo-manager-capabilities-acceptance.php \
  /var/www/html/seo-geo-manager-structured-acceptance.php \
  /var/www/html/seo-geo-manager-theme-model-read-acceptance.php \
  /var/www/html/seo-geo-manager-link-media-read-acceptance.php \
  /var/www/html/seo-geo-manager-contextual-link-write-acceptance.php \
  /var/www/html/seo-geo-manager-media-alt-write-acceptance.php \
  /var/www/html/seo-geo-manager-media-upload-acceptance.php \
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

printf '[manager] Running Theme semantic-model readback acceptance.\n'
if ! wp_cli eval-file /var/www/html/seo-geo-manager-theme-model-read-acceptance.php >"$THEME_MODEL_LOG" 2>&1; then
  cat "$THEME_MODEL_LOG"
  fail_acceptance "theme-model-runtime" "Manager Theme semantic-model reader acceptance failed" 'JSON with "ok":true' "$(tail -c 800 "$THEME_MODEL_LOG" | tr '\n' ' ')"
fi
cat "$THEME_MODEL_LOG"
grep -q '"ok":true' "$THEME_MODEL_LOG" \
  || fail_acceptance "theme-model-result" "Theme model reader acceptance did not emit success marker" '"ok":true' "$(tail -c 800 "$THEME_MODEL_LOG" | tr '\n' ' ')"
grep -q "\"plugin_version\":\"${MANAGER_VERSION}\"" "$THEME_MODEL_LOG" \
  || fail_acceptance "theme-model-version" "Theme model reader did not execute current Manager version" "\"plugin_version\":\"${MANAGER_VERSION}\"" "$(tail -c 800 "$THEME_MODEL_LOG" | tr '\n' ' ')"

printf '[manager] Running C6.1 contextual-link and media read acceptance.\n'
if ! wp_cli eval-file /var/www/html/seo-geo-manager-link-media-read-acceptance.php >"$C6_READ_LOG" 2>&1; then
  cat "$C6_READ_LOG"
  fail_acceptance "c6-read-runtime" "Manager C6.1 contextual-link/media acceptance failed" 'JSON with "ok":true' "$(tail -c 1000 "$C6_READ_LOG" | tr '\n' ' ')"
fi
cat "$C6_READ_LOG"
grep -q '"ok":true' "$C6_READ_LOG" \
  || fail_acceptance "c6-read-result" "C6.1 read acceptance did not emit success marker" '"ok":true' "$(tail -c 1000 "$C6_READ_LOG" | tr '\n' ' ')"
grep -q "\"plugin_version\":\"${MANAGER_VERSION}\"" "$C6_READ_LOG" \
  || fail_acceptance "c6-read-version" "C6.1 read acceptance did not execute current Manager version" "\"plugin_version\":\"${MANAGER_VERSION}\"" "$(tail -c 1000 "$C6_READ_LOG" | tr '\n' ' ')"

printf '[manager] Running C6.2 guarded contextual-link write acceptance.\n'
if ! wp_cli eval-file /var/www/html/seo-geo-manager-contextual-link-write-acceptance.php >"$C6_WRITE_LOG" 2>&1; then
  cat "$C6_WRITE_LOG"
  fail_acceptance "c6-write-runtime" "Manager C6.2 contextual-link write acceptance failed" 'JSON with "ok":true' "$(tail -c 1200 "$C6_WRITE_LOG" | tr '\n' ' ')"
fi
cat "$C6_WRITE_LOG"
grep -q '"ok":true' "$C6_WRITE_LOG" \
  || fail_acceptance "c6-write-result" "C6.2 write acceptance did not emit success marker" '"ok":true' "$(tail -c 1200 "$C6_WRITE_LOG" | tr '\n' ' ')"
grep -q "\"plugin_version\":\"${MANAGER_VERSION}\"" "$C6_WRITE_LOG" \
  || fail_acceptance "c6-write-version" "C6.2 write acceptance did not execute current Manager version" "\"plugin_version\":\"${MANAGER_VERSION}\"" "$(tail -c 1200 "$C6_WRITE_LOG" | tr '\n' ' ')"

printf '[manager] Running C6.3/C6.4 guarded image metadata write acceptance.\n'
if ! wp_cli eval-file /var/www/html/seo-geo-manager-media-alt-write-acceptance.php >"$C6_MEDIA_LOG" 2>&1; then
  cat "$C6_MEDIA_LOG"
  fail_acceptance "c6-media-runtime" "Manager C6.3/C6.4 media write acceptance failed" 'JSON with "ok":true' "$(tail -c 1600 "$C6_MEDIA_LOG" | tr '\n' ' ')"
fi
cat "$C6_MEDIA_LOG"
grep -q '"ok":true' "$C6_MEDIA_LOG" \
  || fail_acceptance "c6-media-result" "C6.3/C6.4 media acceptance did not emit success marker" '"ok":true' "$(tail -c 1600 "$C6_MEDIA_LOG" | tr '\n' ' ')"
grep -q "\"plugin_version\":\"${MANAGER_VERSION}\"" "$C6_MEDIA_LOG" \
  || fail_acceptance "c6-media-version" "C6.3/C6.4 media acceptance did not execute current Manager version" "\"plugin_version\":\"${MANAGER_VERSION}\"" "$(tail -c 1600 "$C6_MEDIA_LOG" | tr '\n' ' ')"

printf '[manager] Running C6.5 guarded direct image-upload acceptance.\n'
if ! wp_cli eval-file /var/www/html/seo-geo-manager-media-upload-acceptance.php >"$C6_UPLOAD_LOG" 2>&1; then
  cat "$C6_UPLOAD_LOG"
  fail_acceptance "c6-upload-runtime" "Manager C6.5 media-upload acceptance failed" 'JSON with "ok":true' "$(tail -c 1800 "$C6_UPLOAD_LOG" | tr '\n' ' ')"
fi
cat "$C6_UPLOAD_LOG"
grep -q '"ok":true' "$C6_UPLOAD_LOG" \
  || fail_acceptance "c6-upload-result" "C6.5 media-upload acceptance did not emit success marker" '"ok":true' "$(tail -c 1800 "$C6_UPLOAD_LOG" | tr '\n' ' ')"
grep -q "\"plugin_version\":\"${MANAGER_VERSION}\"" "$C6_UPLOAD_LOG" \
  || fail_acceptance "c6-upload-version" "C6.5 media-upload acceptance did not execute current Manager version" "\"plugin_version\":\"${MANAGER_VERSION}\"" "$(tail -c 1800 "$C6_UPLOAD_LOG" | tr '\n' ' ')"

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
