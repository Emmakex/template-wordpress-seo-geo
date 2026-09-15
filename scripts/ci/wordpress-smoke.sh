#!/usr/bin/env bash
set -u -o pipefail

PIPELINE="${GITHUB_WORKFLOW:-wordpress-smoke}"
RUN_ID="${GITHUB_RUN_ID:-local}"
RUN_ATTEMPT="${GITHUB_RUN_ATTEMPT:-1}"
JOB="${GITHUB_JOB:-wordpress-smoke}"
STEP="wordpress-runtime-smoke"
COMMAND="bash scripts/ci/wordpress-smoke.sh"

WORDPRESS_IMAGE="wordpress:7.1.0-php8.2-apache"
WPCLI_IMAGE="wordpress:cli-2.12.0-php8.2"
MARIADB_IMAGE="mariadb:11.8.9"

SUFFIX="${RUN_ID}-${RUN_ATTEMPT}-$$"
NETWORK="seo-geo-${SUFFIX}"
DB_CONTAINER="seo-geo-db-${SUFFIX}"
WP_CONTAINER="seo-geo-wp-${SUFFIX}"
WP_VOLUME="seo-geo-wp-${SUFFIX}"

DB_NAME="wordpress"
DB_USER="wordpress"
DB_PASSWORD="wordpress-smoke-password"
DB_ROOT_PASSWORD="root-smoke-password"

TMP_DIR="$(mktemp -d)"
HOME_BODY="${TMP_DIR}/home.html"
ADMIN_BODY="${TMP_DIR}/admin.html"
RUNTIME_LOG="${TMP_DIR}/runtime.log"
DEBUG_LOG="${TMP_DIR}/debug.log"

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

fail_smoke() {
  local code="$1"
  local primary="$2"
  local expected="$3"
  local received="$4"
  local command_name="${5:-$COMMAND}"
  local sig
  sig="$(signature "${code}:${primary}")"

  cat <<JSON
{
  "schema_version": 1,
  "pipeline": "${PIPELINE}",
  "run_id": "${RUN_ID}",
  "run_attempt": "${RUN_ATTEMPT}",
  "job": "${JOB}",
  "step": "${STEP}",
  "command": "${command_name}",
  "exit_code": 1,
  "primary_error": "${primary}",
  "file_line": null,
  "expected": "${expected}",
  "received": "${received}",
  "error_signature": "${sig}",
  "root_cause_status": "unknown"
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

printf '[smoke] Creating isolated Docker resources.\n'
docker network create "$NETWORK" >/dev/null || fail_smoke "network-create" "Could not create isolated Docker network" "network created" "docker network create failed" "docker network create"
docker volume create "$WP_VOLUME" >/dev/null || fail_smoke "volume-create" "Could not create WordPress data volume" "volume created" "docker volume create failed" "docker volume create"

printf '[smoke] Starting MariaDB %s.\n' "$MARIADB_IMAGE"
docker run -d \
  --name "$DB_CONTAINER" \
  --network "$NETWORK" \
  -e "MARIADB_DATABASE=${DB_NAME}" \
  -e "MARIADB_USER=${DB_USER}" \
  -e "MARIADB_PASSWORD=${DB_PASSWORD}" \
  -e "MARIADB_ROOT_PASSWORD=${DB_ROOT_PASSWORD}" \
  "$MARIADB_IMAGE" >/dev/null \
  || fail_smoke "database-start" "MariaDB container did not start" "running database container" "docker run failed" "docker run mariadb"

wait_for_db || fail_smoke "database-ready" "MariaDB did not become ready" "mariadb-admin ping succeeds" "database readiness timeout" "mariadb-admin ping"

printf '[smoke] Starting WordPress %s.\n' "$WORDPRESS_IMAGE"
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
  || fail_smoke "wordpress-start" "WordPress container did not start" "running WordPress container" "docker run failed" "docker run wordpress"

wait_for_wordpress_files || fail_smoke "wordpress-files" "WordPress files were not initialized" "wp-settings.php exists" "initialization timeout" "WordPress image initialization"

HOST_PORT="$(docker port "$WP_CONTAINER" 80/tcp | awk -F: 'NR == 1 {print $NF}')"
[[ -n "$HOST_PORT" ]] || fail_smoke "wordpress-port" "Could not resolve published WordPress port" "non-empty host port" "empty" "docker port"
BASE_URL="http://127.0.0.1:${HOST_PORT}"

printf '[smoke] Installing repository theme and plugin into the WordPress fixture.\n'
docker exec "$WP_CONTAINER" mkdir -p \
  /var/www/html/wp-content/plugins/seo-geo-core \
  /var/www/html/wp-content/themes/seo-geo-theme \
  || fail_smoke "package-dirs" "Could not create package directories" "plugin/theme directories created" "mkdir failed" "docker exec mkdir"

docker cp packages/seo-geo-core/. "$WP_CONTAINER":/var/www/html/wp-content/plugins/seo-geo-core/ \
  || fail_smoke "plugin-copy" "Could not copy SEO GEO Core into WordPress" "plugin copied" "docker cp failed" "docker cp plugin"
docker cp packages/seo-geo-theme/. "$WP_CONTAINER":/var/www/html/wp-content/themes/seo-geo-theme/ \
  || fail_smoke "theme-copy" "Could not copy SEO GEO Starter into WordPress" "theme copied" "docker cp failed" "docker cp theme"
docker exec "$WP_CONTAINER" chown -R www-data:www-data \
  /var/www/html/wp-content/plugins/seo-geo-core \
  /var/www/html/wp-content/themes/seo-geo-theme \
  || fail_smoke "package-permissions" "Could not set WordPress package permissions" "www-data owns test packages" "chown failed" "docker exec chown"

printf '[smoke] Installing WordPress with WP-CLI %s.\n' "$WPCLI_IMAGE"
wp_cli core install \
  --url="$BASE_URL" \
  --title="SEO GEO Smoke" \
  --admin_user=admin \
  --admin_password=wordpress-smoke-admin \
  --admin_email=admin@example.test \
  --skip-email >/dev/null \
  || fail_smoke "core-install" "WP-CLI could not install WordPress" "core install succeeds" "wp core install failed" "wp core install"

wp_cli plugin activate seo-geo-core >/dev/null \
  || fail_smoke "plugin-activate" "SEO GEO Core could not be activated" "plugin active" "activation failed" "wp plugin activate seo-geo-core"
wp_cli theme activate seo-geo-theme >/dev/null \
  || fail_smoke "theme-activate" "SEO GEO Starter could not be activated" "theme active" "activation failed" "wp theme activate seo-geo-theme"

wp_cli plugin is-active seo-geo-core >/dev/null \
  || fail_smoke "plugin-state" "SEO GEO Core is not active after activation" "active" "inactive" "wp plugin is-active seo-geo-core"
wp_cli theme is-active seo-geo-theme >/dev/null \
  || fail_smoke "theme-state" "SEO GEO Starter is not active after activation" "active" "inactive" "wp theme is-active seo-geo-theme"

PROVIDER="$(wp_cli eval 'echo \SeoGeo\Core\Plugin::language()?->provider_id() ?? "missing";' 2>/dev/null | tr -d '\r\n')"
[[ "$PROVIDER" == "native" ]] \
  || fail_smoke "language-service" "Language service did not initialize with the native provider" "native" "$PROVIDER" "wp eval language provider"

SEO_PROVIDER="$(wp_cli eval 'echo \SeoGeo\Core\Plugin::integrations()?->seo_provider() ?? "missing";' 2>/dev/null | tr -d '\r\n')"
[[ "$SEO_PROVIDER" == "native" ]] \
  || fail_smoke "integration-service" "SEO integration detector did not resolve the clean fixture as native" "native" "$SEO_PROVIDER" "wp eval SEO provider"

printf '[smoke] Requesting frontend and admin routes.\n'
curl -fsS "$BASE_URL/" -o "$HOME_BODY" \
  || fail_smoke "frontend-request" "WordPress frontend request failed" "HTTP 2xx" "curl failure" "curl frontend"
curl -fsSL "$BASE_URL/wp-admin/" -o "$ADMIN_BODY" \
  || fail_smoke "admin-request" "WordPress admin route did not resolve to a valid response" "HTTP 2xx after redirect" "curl failure" "curl wp-admin"

if ! grep -qi '<body' "$HOME_BODY"; then
  fail_smoke "frontend-body" "Frontend response is not a rendered HTML document" "HTML body" "body element not found" "inspect frontend response"
fi

if ! grep -qi 'wp-login' "$ADMIN_BODY"; then
  fail_smoke "admin-body" "Admin route did not resolve to the expected login flow" "WordPress login response" "login marker not found" "inspect admin response"
fi

printf '[smoke] Checking runtime diagnostics.\n'
docker logs "$WP_CONTAINER" >"$RUNTIME_LOG" 2>&1 || true
docker exec "$WP_CONTAINER" sh -c 'test ! -f /var/www/html/wp-content/debug.log || cat /var/www/html/wp-content/debug.log' >"$DEBUG_LOG" 2>&1 || true

if grep -Eqi 'PHP (Fatal error|Warning|Notice)|Fatal error|Uncaught (Error|Exception)' "$RUNTIME_LOG" "$DEBUG_LOG"; then
  MATCH="$(grep -Eim1 'PHP (Fatal error|Warning|Notice)|Fatal error|Uncaught (Error|Exception)' "$RUNTIME_LOG" "$DEBUG_LOG" | tr -d '\r' | head -c 240)"
  fail_smoke "runtime-php" "PHP runtime emitted a fatal, warning, notice or uncaught error" "no PHP runtime diagnostics" "$MATCH" "inspect WordPress runtime/debug logs"
fi

printf 'WordPress smoke OK: WordPress 7.1 / PHP 8.2 fixture installed; plugin and theme active; frontend/admin requests healthy; language=%s; seo-provider=%s.\n' "$PROVIDER" "$SEO_PROVIDER"
