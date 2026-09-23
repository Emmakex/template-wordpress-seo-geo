#!/usr/bin/env bash
set -u -o pipefail

PIPELINE="${GITHUB_WORKFLOW:-accessibility-responsive}"
RUN_ID="${GITHUB_RUN_ID:-local}"
RUN_ATTEMPT="${GITHUB_RUN_ATTEMPT:-1}"
JOB="${GITHUB_JOB:-browser-acceptance}"
STEP="browser-accessibility-responsive"
COMMAND="bash scripts/ci/browser-acceptance.sh"

WORDPRESS_IMAGE="wordpress:7.1.0-php8.2-apache"
WPCLI_IMAGE="wordpress:cli-2.12.0-php8.2"
MARIADB_IMAGE="mariadb:11.8.9"

SUFFIX="${RUN_ID}-${RUN_ATTEMPT}-$$"
NETWORK="seo-geo-browser-${SUFFIX}"
DB_CONTAINER="seo-geo-browser-db-${SUFFIX}"
WP_CONTAINER="seo-geo-browser-wp-${SUFFIX}"
WP_VOLUME="seo-geo-browser-wp-${SUFFIX}"

DB_NAME="wordpress"
DB_USER="wordpress"
DB_PASSWORD="wordpress-browser-password"
DB_ROOT_PASSWORD="root-browser-password"

TMP_DIR="$(mktemp -d)"
RUNTIME_LOG="${TMP_DIR}/runtime.log"
DEBUG_LOG="${TMP_DIR}/debug.log"
PLAYWRIGHT_LOG="${TMP_DIR}/playwright.log"

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

printf '[browser] Creating isolated Docker resources.\n'
docker network create "$NETWORK" >/dev/null \
  || fail_acceptance "network-create" "Could not create isolated Docker network" "network created" "docker network create failed" "docker network create"
docker volume create "$WP_VOLUME" >/dev/null \
  || fail_acceptance "volume-create" "Could not create WordPress data volume" "volume created" "docker volume create failed" "docker volume create"

printf '[browser] Starting MariaDB %s.\n' "$MARIADB_IMAGE"
docker run -d \
  --name "$DB_CONTAINER" \
  --network "$NETWORK" \
  -e "MARIADB_DATABASE=${DB_NAME}" \
  -e "MARIADB_USER=${DB_USER}" \
  -e "MARIADB_PASSWORD=${DB_PASSWORD}" \
  -e "MARIADB_ROOT_PASSWORD=${DB_ROOT_PASSWORD}" \
  "$MARIADB_IMAGE" >/dev/null \
  || fail_acceptance "database-start" "MariaDB container did not start" "running database container" "docker run failed" "docker run mariadb"

wait_for_db \
  || fail_acceptance "database-ready" "MariaDB did not become ready" "mariadb-admin ping succeeds" "database readiness timeout" "mariadb-admin ping"

printf '[browser] Starting WordPress %s.\n' "$WORDPRESS_IMAGE"
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
  || fail_acceptance "wordpress-start" "WordPress container did not start" "running WordPress container" "docker run failed" "docker run wordpress"

wait_for_wordpress_files \
  || fail_acceptance "wordpress-files" "WordPress files were not initialized" "wp-settings.php exists" "initialization timeout" "WordPress image initialization"

HOST_PORT="$(docker port "$WP_CONTAINER" 80/tcp | awk -F: 'NR == 1 {print $NF}')"
[[ -n "$HOST_PORT" ]] \
  || fail_acceptance "wordpress-port" "Could not resolve published WordPress port" "non-empty host port" "empty" "docker port"
BASE_URL="http://127.0.0.1:${HOST_PORT}"

printf '[browser] Installing repository packages and acceptance fixtures.\n'
docker exec "$WP_CONTAINER" mkdir -p \
  /var/www/html/wp-content/plugins/seo-geo-core \
  /var/www/html/wp-content/themes/seo-geo-theme \
  /var/www/html/wp-content/mu-plugins \
  || fail_acceptance "package-dirs" "Could not create WordPress package directories" "package directories created" "mkdir failed" "docker exec mkdir"

docker cp packages/seo-geo-core/. "$WP_CONTAINER":/var/www/html/wp-content/plugins/seo-geo-core/ \
  || fail_acceptance "plugin-copy" "Could not copy SEO GEO Core into WordPress" "plugin copied" "docker cp failed" "docker cp plugin"
docker cp packages/seo-geo-theme/. "$WP_CONTAINER":/var/www/html/wp-content/themes/seo-geo-theme/ \
  || fail_acceptance "theme-copy" "Could not copy SEO GEO Starter into WordPress" "theme copied" "docker cp failed" "docker cp theme"
docker cp tests/fixtures/acceptance-language.php "$WP_CONTAINER":/var/www/html/wp-content/mu-plugins/seo-geo-acceptance-language.php \
  || fail_acceptance "locale-fixture-copy" "Could not copy acceptance locale MU-plugin" "locale fixture copied" "docker cp failed" "docker cp locale fixture"
docker cp tests/fixtures/seed-acceptance.php "$WP_CONTAINER":/var/www/html/wp-content/seed-acceptance.php \
  || fail_acceptance "seed-copy" "Could not copy acceptance seed script" "seed fixture copied" "docker cp failed" "docker cp seed fixture"

docker exec "$WP_CONTAINER" chown -R www-data:www-data \
  /var/www/html/wp-content/plugins/seo-geo-core \
  /var/www/html/wp-content/themes/seo-geo-theme \
  /var/www/html/wp-content/mu-plugins \
  /var/www/html/wp-content/seed-acceptance.php \
  || fail_acceptance "package-permissions" "Could not set fixture package permissions" "www-data owns fixture files" "chown failed" "docker exec chown"

printf '[browser] Installing and configuring WordPress.\n'
wp_cli core install \
  --url="$BASE_URL" \
  --title="SEO GEO Browser Acceptance" \
  --admin_user=admin \
  --admin_password=wordpress-browser-admin \
  --admin_email=admin@example.test \
  --skip-email >/dev/null \
  || fail_acceptance "core-install" "WP-CLI could not install WordPress" "core install succeeds" "wp core install failed" "wp core install"

wp_cli plugin activate seo-geo-core >/dev/null \
  || fail_acceptance "plugin-activate" "SEO GEO Core could not be activated" "plugin active" "activation failed" "wp plugin activate seo-geo-core"
wp_cli theme activate seo-geo-theme >/dev/null \
  || fail_acceptance "theme-activate" "SEO GEO Starter could not be activated" "theme active" "activation failed" "wp theme activate seo-geo-theme"

wp_cli rewrite structure '/%postname%/' --hard >/dev/null \
  || fail_acceptance "permalink-structure" "Could not configure pretty permalinks" "post-name permalinks enabled" "rewrite structure failed" "wp rewrite structure"
wp_cli rewrite flush --hard >/dev/null \
  || fail_acceptance "permalink-flush" "Could not flush rewrite rules" "rewrite rules flushed" "rewrite flush failed" "wp rewrite flush"

printf '[browser] Seeding representative EN/ES pages from registered theme patterns.\n'
wp_cli eval-file /var/www/html/wp-content/seed-acceptance.php \
  || fail_acceptance "fixture-seed" "Representative browser fixtures could not be seeded" "EN and ES pages published" "wp eval-file failed" "wp eval-file seed-acceptance.php"

wait_for_fixture "${BASE_URL}/acceptance-en/?fixture_lang=en" \
  || fail_acceptance "fixture-en-http" "English acceptance fixture did not become reachable" "HTTP 2xx" "fixture request timeout" "curl acceptance-en"
wait_for_fixture "${BASE_URL}/acceptance-es/?fixture_lang=es" \
  || fail_acceptance "fixture-es-http" "Spanish acceptance fixture did not become reachable" "HTTP 2xx" "fixture request timeout" "curl acceptance-es"

printf '[browser] Running Playwright + axe accessibility/responsive acceptance.\n'
set +e
SEO_GEO_BASE_URL="$BASE_URL" npm run test:browser 2>&1 | tee "$PLAYWRIGHT_LOG"
PLAYWRIGHT_STATUS=${PIPESTATUS[0]}
set -e

if [[ $PLAYWRIGHT_STATUS -ne 0 ]]; then
  fail_acceptance "playwright" "Browser accessibility/responsive acceptance failed" "all Playwright projects pass" "npm test:browser exited non-zero" "npm run test:browser"
fi

printf '[browser] Checking WordPress runtime diagnostics.\n'
docker logs "$WP_CONTAINER" >"$RUNTIME_LOG" 2>&1 || true
docker exec "$WP_CONTAINER" sh -c 'test ! -f /var/www/html/wp-content/debug.log || cat /var/www/html/wp-content/debug.log' >"$DEBUG_LOG" 2>&1 || true

if grep -Eqi 'PHP (Fatal error|Warning|Notice)|Fatal error|Uncaught (Error|Exception)' "$RUNTIME_LOG" "$DEBUG_LOG"; then
  fail_acceptance "runtime-php" "WordPress emitted a PHP runtime diagnostic during browser acceptance" "no PHP fatal/warning/notice/uncaught error" "runtime diagnostics detected" "inspect WordPress runtime/debug logs"
fi

printf 'Browser acceptance OK: EN/ES representative pages plus the Phase 9D admin wizard passed Chromium at 320/768/1440 with axe WCAG A/AA, responsive reflow and keyboard/focus assertions.\n'
