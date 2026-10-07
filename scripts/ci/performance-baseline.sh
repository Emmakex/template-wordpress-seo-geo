#!/usr/bin/env bash
set -u -o pipefail

PIPELINE="${GITHUB_WORKFLOW:-performance-baseline}"
RUN_ID="${GITHUB_RUN_ID:-local}"
RUN_ATTEMPT="${GITHUB_RUN_ATTEMPT:-1}"
JOB="${GITHUB_JOB:-performance-baseline}"
STEP="wordpress-lighthouse-baseline"
COMMAND="bash scripts/ci/performance-baseline.sh"

WORDPRESS_IMAGE="wordpress:7.1.0-php8.2-apache"
WPCLI_IMAGE="wordpress:cli-2.12.0-php8.2"
MARIADB_IMAGE="mariadb:11.8.9"
LIGHTHOUSE_VERSION="13.4.1"

SUFFIX="${RUN_ID}-${RUN_ATTEMPT}-$$"
NETWORK="seo-geo-performance-${SUFFIX}"
DB_CONTAINER="seo-geo-performance-db-${SUFFIX}"
WP_CONTAINER="seo-geo-performance-wp-${SUFFIX}"
WP_VOLUME="seo-geo-performance-wp-${SUFFIX}"

DB_NAME="wordpress"
DB_USER="wordpress"
DB_PASSWORD="wordpress-performance-password"
DB_ROOT_PASSWORD="root-performance-password"
RESULTS_DIR="performance-results"
THEME_BUILD_DIR="${RESULTS_DIR}/seo-geo-theme-build"
RUNTIME_LOG="${RESULTS_DIR}/wordpress-runtime.log"
DEBUG_LOG="${RESULTS_DIR}/wordpress-debug.log"

signature() {
  printf '%s' "$1" | sha256sum | cut -c1-12
}

cleanup() {
  docker rm -f "$WP_CONTAINER" >/dev/null 2>&1 || true
  docker rm -f "$DB_CONTAINER" >/dev/null 2>&1 || true
  docker volume rm "$WP_VOLUME" >/dev/null 2>&1 || true
  docker network rm "$NETWORK" >/dev/null 2>&1 || true
}
trap cleanup EXIT

fail_performance() {
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

wait_for_fixture() {
  local url="$1"
  local attempt
  for attempt in $(seq 1 30); do
    if curl -fsS "$url" >/dev/null 2>&1; then
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

run_lighthouse() {
  local page="$1"
  local url="$2"
  local index="$3"
  local output="${RESULTS_DIR}/${page}-${index}.json"

  printf '[performance] Lighthouse %s run %s/3 for %s.\n' "$LIGHTHOUSE_VERSION" "$index" "$page"
  CHROME_PATH="$CHROME_PATH" npx --yes "lighthouse@${LIGHTHOUSE_VERSION}" "$url" \
    --only-categories=performance \
    --output=json \
    --output-path="$output" \
    --quiet \
    --chrome-flags="--headless --no-sandbox --disable-dev-shm-usage" \
    || fail_performance "lighthouse-${page}-${index}" "Lighthouse execution failed for ${page} sample ${index}" "JSON report generated" "lighthouse exited non-zero" "lighthouse ${url}"
}

rm -rf "$RESULTS_DIR"
mkdir -p "$RESULTS_DIR"

printf '[performance] Creating isolated Docker resources.\n'
docker network create "$NETWORK" >/dev/null \
  || fail_performance "network-create" "Could not create isolated Docker network" "network created" "docker network create failed" "docker network create"
docker volume create "$WP_VOLUME" >/dev/null \
  || fail_performance "volume-create" "Could not create WordPress data volume" "volume created" "docker volume create failed" "docker volume create"

printf '[performance] Starting MariaDB %s.\n' "$MARIADB_IMAGE"
docker run -d \
  --name "$DB_CONTAINER" \
  --network "$NETWORK" \
  -e "MARIADB_DATABASE=${DB_NAME}" \
  -e "MARIADB_USER=${DB_USER}" \
  -e "MARIADB_PASSWORD=${DB_PASSWORD}" \
  -e "MARIADB_ROOT_PASSWORD=${DB_ROOT_PASSWORD}" \
  "$MARIADB_IMAGE" >/dev/null \
  || fail_performance "database-start" "MariaDB container did not start" "running database container" "docker run failed" "docker run mariadb"

wait_for_db \
  || fail_performance "database-ready" "MariaDB did not become ready" "mariadb-admin ping succeeds" "database readiness timeout" "mariadb-admin ping"

printf '[performance] Starting WordPress %s.\n' "$WORDPRESS_IMAGE"
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
  -e "WORDPRESS_CONFIG_EXTRA=define( 'WP_DEBUG_LOG', true ); define( 'WP_DEBUG_DISPLAY', false ); define( 'SEO_GEO_ACCEPTANCE_FIXTURE', true ); @ini_set( 'display_errors', '0' );" \
  "$WORDPRESS_IMAGE" >/dev/null \
  || fail_performance "wordpress-start" "WordPress container did not start" "running WordPress container" "docker run failed" "docker run wordpress"

wait_for_wordpress_files \
  || fail_performance "wordpress-files" "WordPress files were not initialized" "wp-settings.php exists" "initialization timeout" "WordPress image initialization"

HOST_PORT="$(docker port "$WP_CONTAINER" 80/tcp | awk -F: 'NR == 1 {print $NF}')"
[[ -n "$HOST_PORT" ]] \
  || fail_performance "wordpress-port" "Could not resolve published WordPress port" "non-empty host port" "empty" "docker port"
BASE_URL="http://127.0.0.1:${HOST_PORT}"

printf '[performance] Building the exact self-contained Theme artifact used by clients.\n'
bash scripts/build-theme-package.sh "$THEME_BUILD_DIR" \
  || fail_performance "theme-build" "Could not build self-contained SEO/GEO Theme" "theme build succeeds" "build-theme-package failed" "bash scripts/build-theme-package.sh"

printf '[performance] Installing self-contained Theme and fixture support with zero plugins.\n'
docker exec "$WP_CONTAINER" mkdir -p \
  /var/www/html/wp-content/themes/seo-geo-theme \
  /var/www/html/wp-content/mu-plugins \
  || fail_performance "package-dirs" "Could not create WordPress package directories" "package directories created" "mkdir failed" "docker exec mkdir"

docker cp "$THEME_BUILD_DIR/." "$WP_CONTAINER":/var/www/html/wp-content/themes/seo-geo-theme/ \
  || fail_performance "theme-copy" "Could not copy built self-contained SEO/GEO Theme into WordPress" "built theme copied" "docker cp failed" "docker cp built theme"
docker cp tests/fixtures/acceptance-language.php "$WP_CONTAINER":/var/www/html/wp-content/mu-plugins/seo-geo-acceptance-language.php \
  || fail_performance "locale-fixture-copy" "Could not copy acceptance locale/preset MU-plugin" "fixture copied" "docker cp failed" "docker cp acceptance fixture"
docker cp tests/fixtures/seed-acceptance.php "$WP_CONTAINER":/var/www/html/wp-content/seed-acceptance.php \
  || fail_performance "seed-copy" "Could not copy acceptance seed script" "seed fixture copied" "docker cp failed" "docker cp seed fixture"

docker exec "$WP_CONTAINER" chown -R www-data:www-data \
  /var/www/html/wp-content/themes/seo-geo-theme \
  /var/www/html/wp-content/mu-plugins \
  /var/www/html/wp-content/seed-acceptance.php \
  || fail_performance "package-permissions" "Could not set fixture package permissions" "www-data owns fixture files" "chown failed" "docker exec chown"

printf '[performance] Installing and configuring WordPress.\n'
wp_cli core install \
  --url="$BASE_URL" \
  --title="SEO GEO Performance Baseline" \
  --admin_user=admin \
  --admin_password=wordpress-performance-admin \
  --admin_email=admin@example.test \
  --skip-email >/dev/null \
  || fail_performance "core-install" "WP-CLI could not install WordPress" "core install succeeds" "wp core install failed" "wp core install"

wp_cli theme activate seo-geo-theme >/dev/null \
  || fail_performance "theme-activate" "SEO/GEO Theme could not be activated" "theme active" "activation failed" "wp theme activate seo-geo-theme"

ACTIVE_PLUGINS="$(wp_cli plugin list --status=active --field=name 2>/dev/null | tr -d '\r')"
[[ -z "$ACTIVE_PLUGINS" ]] \
  || fail_performance "zero-plugins" "Performance acceptance must measure the self-contained Theme with zero active plugins" "empty active-plugin list" "$ACTIVE_PLUGINS" "wp plugin list --status=active"

RUNTIME_FILE="$(wp_cli eval '$reflection = new ReflectionClass( \SeoGeo\Core\Runtime::class ); echo (string) $reflection->getFileName();' 2>/dev/null | tr -d '\r\n')"
case "$RUNTIME_FILE" in
  */wp-content/themes/seo-geo-theme/inc/seo-geo-core/src/Runtime.php) ;;
  *) fail_performance "embedded-runtime" "Performance acceptance did not load SEO/GEO Core from the built Theme" "theme/inc/seo-geo-core/src/Runtime.php" "$RUNTIME_FILE" "ReflectionClass Runtime" ;;
esac

wp_cli eval 'if ( ! is_array( seo_geo_theme_preset_document( "corporate", "content-map.json" ) ) ) { exit( 1 ); }' >/dev/null \
  || fail_performance "embedded-presets" "Built Theme cannot resolve the embedded Corporate content model" "embedded corporate content-map available" "preset document unavailable" "wp eval preset document"

wp_cli rewrite structure '/%postname%/' --hard >/dev/null \
  || fail_performance "permalink-structure" "Could not configure pretty permalinks" "post-name permalinks enabled" "rewrite structure failed" "wp rewrite structure"
wp_cli rewrite flush --hard >/dev/null \
  || fail_performance "permalink-flush" "Could not flush rewrite rules" "rewrite rules flushed" "wp rewrite flush failed" "wp rewrite flush"
wp_cli eval-file /var/www/html/wp-content/seed-acceptance.php \
  || fail_performance "fixture-seed" "Representative performance fixtures could not be seeded" "EN, ES, migration and Corporate v5 pages published" "wp eval-file failed" "wp eval-file seed-acceptance.php"

EN_URL="${BASE_URL}/acceptance-en/?fixture_lang=en"
ES_URL="${BASE_URL}/acceptance-es/?fixture_lang=es"
MIGRATION_URL="${BASE_URL}/migration-parity-fixture/?fixture_lang=en"
CORPORATE_URL="${BASE_URL}/corporate-v5-fixture/?fixture_lang=en&fixture_preset=corporate"
wait_for_fixture "$EN_URL" \
  || fail_performance "fixture-en-http" "English performance fixture did not become reachable" "HTTP 2xx" "fixture request timeout" "curl acceptance-en"
wait_for_fixture "$ES_URL" \
  || fail_performance "fixture-es-http" "Spanish performance fixture did not become reachable" "HTTP 2xx" "fixture request timeout" "curl acceptance-es"
wait_for_fixture "$MIGRATION_URL" \
  || fail_performance "fixture-migration-http" "Post-migration performance fixture did not become reachable" "HTTP 2xx" "fixture request timeout" "curl migration-parity-fixture"
wait_for_fixture "$CORPORATE_URL" \
  || fail_performance "fixture-corporate-v5-http" "Corporate v5 strategic performance fixture did not become reachable" "HTTP 2xx through Theme-owned renderer" "fixture request timeout" "curl corporate-v5-fixture"

CORPORATE_HTML="$(curl -fsS "$CORPORATE_URL")"
grep -Fq 'seo-geo-corporate-v5-home' <<<"$CORPORATE_HTML" \
  || fail_performance "fixture-corporate-v5-renderer" "Corporate performance fixture fell back to Gutenberg instead of v5 renderer" "seo-geo-corporate-v5-home marker" "v5 marker absent" "curl corporate-v5-fixture"
grep -Fq 'corporate-v5-runtime.css' <<<"$CORPORATE_HTML" \
  || fail_performance "fixture-corporate-v5-css" "Corporate v5 runtime stylesheet is missing from measured HTML" "corporate-v5-runtime.css present" "v5 CSS absent" "curl corporate-v5-fixture"
if grep -Fq 'corporate-v3-runtime.css' <<<"$CORPORATE_HTML"; then
  fail_performance "fixture-corporate-v3-css" "Legacy Gutenberg escape CSS is still loaded on Corporate v5" "corporate-v3-runtime.css absent" "legacy CSS present" "curl corporate-v5-fixture"
fi

CHROME_PATH="$(node -e "const { chromium } = require('@playwright/test'); process.stdout.write(chromium.executablePath())")"
[[ -x "$CHROME_PATH" ]] \
  || fail_performance "chromium-path" "Playwright Chromium executable was not found" "executable CHROME_PATH" "$CHROME_PATH" "resolve chromium executablePath"
export CHROME_PATH

for sample in 1 2 3; do
  run_lighthouse en "$EN_URL" "$sample"
  run_lighthouse es "$ES_URL" "$sample"
  run_lighthouse migration "$MIGRATION_URL" "$sample"
  run_lighthouse corporate "$CORPORATE_URL" "$sample"
done

printf '[performance] Evaluating median Lighthouse/resource metrics.\n'
node scripts/ci/performance-report.mjs "$RESULTS_DIR" tests/performance/budgets.json \
  || fail_performance "budget-evaluation" "Performance baseline/budget evaluation failed" "observed metrics satisfy contract" "performance-report exited non-zero" "node scripts/ci/performance-report.mjs"

printf '[performance] Checking WordPress runtime diagnostics.\n'
docker logs "$WP_CONTAINER" >"$RUNTIME_LOG" 2>&1 || true
docker exec "$WP_CONTAINER" sh -c 'test ! -f /var/www/html/wp-content/debug.log || cat /var/www/html/wp-content/debug.log' >"$DEBUG_LOG" 2>&1 || true
if grep -Eqi 'PHP (Fatal error|Warning|Notice)|Fatal error|Uncaught (Error|Exception)' "$RUNTIME_LOG" "$DEBUG_LOG"; then
  fail_performance "runtime-php" "WordPress emitted a PHP runtime diagnostic during performance measurement" "no PHP fatal/warning/notice/uncaught error" "runtime diagnostics detected" "inspect WordPress runtime/debug logs"
fi

ACTIVE_PLUGINS_AFTER="$(wp_cli plugin list --status=active --field=name 2>/dev/null | tr -d '\r')"
[[ -z "$ACTIVE_PLUGINS_AFTER" ]] \
  || fail_performance "zero-plugins-after" "Performance acceptance must finish with zero active plugins" "empty active-plugin list" "$ACTIVE_PLUGINS_AFTER" "wp plugin list --status=active"

printf 'Performance baseline OK: Lighthouse %s measured the same built zero-plugin Theme used by browser acceptance, including the real Corporate v5 renderer.\n' "$LIGHTHOUSE_VERSION"
