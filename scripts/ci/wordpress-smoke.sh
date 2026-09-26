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
PAGE_BODY="${TMP_DIR}/native-seo-page.html"
SEARCH_BODY="${TMP_DIR}/search.html"
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

printf '[smoke] Installing repository theme and migration/source plugins into the WordPress fixture.\n'
docker exec "$WP_CONTAINER" mkdir -p \
  /var/www/html/wp-content/plugins/seo-geo-core \
  /var/www/html/wp-content/plugins/seo-geo-migration-bridge \
  /var/www/html/wp-content/themes/seo-geo-theme \
  || fail_smoke "package-dirs" "Could not create package directories" "plugin/theme directories created" "mkdir failed" "docker exec mkdir"

docker cp packages/seo-geo-core/. "$WP_CONTAINER":/var/www/html/wp-content/plugins/seo-geo-core/ \
  || fail_smoke "plugin-copy" "Could not copy SEO GEO Core into WordPress" "plugin copied" "docker cp failed" "docker cp plugin"
docker cp packages/seo-geo-migration-bridge/. "$WP_CONTAINER":/var/www/html/wp-content/plugins/seo-geo-migration-bridge/ \
  || fail_smoke "migration-bridge-copy" "Could not copy SEO/GEO Migration Bridge into WordPress" "migration bridge copied" "docker cp failed" "docker cp migration bridge"
docker cp packages/seo-geo-theme/. "$WP_CONTAINER":/var/www/html/wp-content/themes/seo-geo-theme/ \
  || fail_smoke "theme-copy" "Could not copy SEO GEO Starter into WordPress" "theme copied" "docker cp failed" "docker cp theme"
docker exec "$WP_CONTAINER" chown -R www-data:www-data \
  /var/www/html/wp-content/plugins/seo-geo-core \
  /var/www/html/wp-content/plugins/seo-geo-migration-bridge \
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

wp_cli plugin activate seo-geo-core seo-geo-migration-bridge >/dev/null \
  || fail_smoke "plugin-activate" "SEO GEO Core/Migration Bridge could not be activated" "plugins active" "activation failed" "wp plugin activate seo-geo-core seo-geo-migration-bridge"
wp_cli theme activate seo-geo-theme >/dev/null \
  || fail_smoke "theme-activate" "SEO GEO Starter could not be activated" "theme active" "activation failed" "wp theme activate seo-geo-theme"

wp_cli plugin is-active seo-geo-core >/dev/null \
  || fail_smoke "plugin-state" "SEO GEO Core is not active after activation" "active" "inactive" "wp plugin is-active seo-geo-core"
wp_cli plugin is-active seo-geo-migration-bridge >/dev/null \
  || fail_smoke "migration-bridge-state" "SEO/GEO Migration Bridge is not active after activation" "active" "inactive" "wp plugin is-active seo-geo-migration-bridge"
wp_cli theme is-active seo-geo-theme >/dev/null \
  || fail_smoke "theme-state" "SEO GEO Starter is not active after activation" "active" "inactive" "wp theme is-active seo-geo-theme"

PROVIDER="$(wp_cli eval 'echo \SeoGeo\Core\Plugin::language()?->provider_id() ?? "missing";' 2>/dev/null | tr -d '\r\n')"
[[ "$PROVIDER" == "native" ]] \
  || fail_smoke "language-service" "Language service did not initialize with the native provider" "native" "$PROVIDER" "wp eval language provider"

SEO_PROVIDER="$(wp_cli eval 'echo \SeoGeo\Core\Plugin::integrations()?->seo_provider() ?? "missing";' 2>/dev/null | tr -d '\r\n')"
[[ "$SEO_PROVIDER" == "native" ]] \
  || fail_smoke "integration-service" "SEO integration detector did not resolve the clean fixture as native" "native" "$SEO_PROVIDER" "wp eval SEO provider"

SEO_AUTHORITY="$(wp_cli eval 'echo \SeoGeo\Core\Plugin::seo_authority()?->provider() ?? "missing";' 2>/dev/null | tr -d '\r\n')"
[[ "$SEO_AUTHORITY" == "native" ]] \
  || fail_smoke "seo-authority" "SEO output authority did not resolve the clean fixture as native" "native" "$SEO_AUTHORITY" "wp eval SEO authority"

CANONICAL_AUTHORITY="$(wp_cli eval 'echo ( \SeoGeo\Core\Plugin::seo_authority()?->native_owns( \SeoGeo\Core\Seo\SeoOutputAuthority::SIGNAL_CANONICAL ) ?? false ) ? "native" : "delegated";' 2>/dev/null | tr -d '\r\n')"
[[ "$CANONICAL_AUTHORITY" == "native" ]] \
  || fail_smoke "canonical-authority" "Native Core does not own canonical output in a clean fixture" "native" "$CANONICAL_AUTHORITY" "wp eval canonical authority"

printf '[smoke] Verifying native theme pattern registration.\n'
PATTERN_STATE="$(wp_cli eval '
$expected = array(
    "seo-geo-theme/hero",
    "seo-geo-theme/cta",
    "seo-geo-theme/services-features",
    "seo-geo-theme/trust-proof",
    "seo-geo-theme/faq",
    "seo-geo-theme/author-profile",
    "seo-geo-theme/contact",
);
$registry = WP_Block_Patterns_Registry::get_instance();
$missing = array();
foreach ( $expected as $slug ) {
    if ( ! $registry->is_registered( $slug ) ) {
        $missing[] = $slug;
    }
}
echo empty( $missing ) ? "ok:7" : "missing:" . implode( ",", $missing );
' 2>/dev/null | tr -d '\r\n')"
[[ "$PATTERN_STATE" == "ok:7" ]] \
  || fail_smoke "theme-pattern-registry" "Expected all Phase 2B theme patterns to be registered in WordPress" "ok:7" "$PATTERN_STATE" "wp eval WP_Block_Patterns_Registry"

printf '[smoke] Creating native SEO fixture content.\n'
wp_cli rewrite structure '/%postname%/' --hard >/dev/null \
  || fail_smoke "rewrite-structure" "Could not configure pretty permalinks for SEO fixture" "/%postname%/" "rewrite command failed" "wp rewrite structure"
wp_cli option update blogdescription 'Native SEO home description.' >/dev/null \
  || fail_smoke "blog-description" "Could not configure native home description fixture" "fixture description saved" "option update failed" "wp option update blogdescription"
POST_ID="$(wp_cli post create \
  --post_type=post \
  --post_status=publish \
  --post_title='Native SEO Fixture' \
  --post_name='native-seo-fixture' \
  --post_excerpt='Native SEO fixture description.' \
  --post_content='Native SEO fixture body used to validate canonical and metadata ownership.' \
  --porcelain 2>/dev/null | tr -d '\r\n')"
[[ "$POST_ID" =~ ^[0-9]+$ ]] \
  || fail_smoke "seo-fixture-post" "Could not create native SEO fixture post" "numeric post ID" "$POST_ID" "wp post create"

printf '[smoke] Requesting frontend, SEO fixture, search and admin routes.\n'
curl -fsS "$BASE_URL/" -o "$HOME_BODY" \
  || fail_smoke "frontend-request" "WordPress frontend request failed" "HTTP 2xx" "curl failure" "curl frontend"
curl -fsS "$BASE_URL/native-seo-fixture/" -o "$PAGE_BODY" \
  || fail_smoke "seo-page-request" "Native SEO fixture request failed" "HTTP 2xx" "curl failure" "curl native SEO fixture"
curl -fsS "${BASE_URL}/?s=unlikely-native-seo-query" -o "$SEARCH_BODY" \
  || fail_smoke "search-request" "Native SEO search fixture request failed" "HTTP 2xx" "curl failure" "curl search fixture"
curl -fsSL "$BASE_URL/wp-admin/" -o "$ADMIN_BODY" \
  || fail_smoke "admin-request" "Admin route did not resolve to a valid response" "HTTP 2xx after redirect" "curl failure" "curl wp-admin"

if ! grep -qi '<body' "$HOME_BODY"; then
  fail_smoke "frontend-body" "Frontend response is not a rendered HTML document" "HTML body" "body element not found" "inspect frontend response"
fi

if ! grep -qi 'wp-login' "$ADMIN_BODY"; then
  fail_smoke "admin-body" "Admin route did not resolve to the expected login flow" "WordPress login response" "login marker not found" "inspect admin response"
fi

printf '[smoke] Verifying native SEO output ownership.\n'
HOME_CANONICAL_COUNT="$(grep -o 'rel="canonical"' "$HOME_BODY" | wc -l | tr -d ' ')"
[[ "$HOME_CANONICAL_COUNT" == "1" ]] \
  || fail_smoke "home-canonical-count" "Home page must expose exactly one canonical" "1" "$HOME_CANONICAL_COUNT" "inspect home canonical tags"

PAGE_CANONICAL_COUNT="$(grep -o 'rel="canonical"' "$PAGE_BODY" | wc -l | tr -d ' ')"
[[ "$PAGE_CANONICAL_COUNT" == "1" ]] \
  || fail_smoke "page-canonical-count" "Native SEO fixture must expose exactly one canonical" "1" "$PAGE_CANONICAL_COUNT" "inspect fixture canonical tags"

EXPECTED_CANONICAL="${BASE_URL}/native-seo-fixture/"
if ! grep -Fq "<link rel=\"canonical\" href=\"${EXPECTED_CANONICAL}\" />" "$PAGE_BODY"; then
  fail_smoke "page-canonical-value" "Native SEO fixture canonical does not match its public permalink" "$EXPECTED_CANONICAL" "canonical href mismatch" "inspect fixture canonical href"
fi

PAGE_DESCRIPTION_COUNT="$(grep -o 'name="description"' "$PAGE_BODY" | wc -l | tr -d ' ')"
[[ "$PAGE_DESCRIPTION_COUNT" == "1" ]] \
  || fail_smoke "page-description-count" "Native SEO fixture must expose exactly one meta description" "1" "$PAGE_DESCRIPTION_COUNT" "inspect fixture meta descriptions"

if ! grep -Fq '<meta name="description" content="Native SEO fixture description." />' "$PAGE_BODY"; then
  fail_smoke "page-description-value" "Native SEO fixture description does not match the resolved excerpt" "Native SEO fixture description." "description mismatch" "inspect fixture meta description"
fi

SEARCH_CANONICAL_COUNT="$(grep -o 'rel="canonical"' "$SEARCH_BODY" | wc -l | tr -d ' ')"
[[ "$SEARCH_CANONICAL_COUNT" == "0" ]] \
  || fail_smoke "search-canonical-count" "Noindex search fixture must not expose a native canonical" "0" "$SEARCH_CANONICAL_COUNT" "inspect search canonical tags"

SEARCH_ROBOTS_COUNT="$(grep -o "name='robots'" "$SEARCH_BODY" | wc -l | tr -d ' ')"
[[ "$SEARCH_ROBOTS_COUNT" == "1" ]] \
  || fail_smoke "search-robots-count" "Search fixture must expose exactly one WordPress robots meta tag" "1" "$SEARCH_ROBOTS_COUNT" "inspect search robots tags"

SEARCH_ROBOTS_LINE="$(grep -i "name='robots'" "$SEARCH_BODY" | head -n 1 | tr -d '\r')"
[[ "$SEARCH_ROBOTS_LINE" == *"noindex"* && "$SEARCH_ROBOTS_LINE" == *"follow"* && "$SEARCH_ROBOTS_LINE" != *"nofollow"* ]] \
  || fail_smoke "search-robots-policy" "Search fixture must resolve to noindex,follow" "robots contains noindex and follow without nofollow" "$SEARCH_ROBOTS_LINE" "inspect search robots policy"

source scripts/ci/migration-bridge-site-analyzer-acceptance.sh
source scripts/ci/migration-bridge-baseline-acceptance.sh
source scripts/ci/migration-bridge-incremental-baseline-acceptance.sh
source scripts/ci/migration-bridge-dependency-graph-acceptance.sh
source scripts/ci/migration-bridge-dependency-review-acceptance.sh
source scripts/ci/migration-bridge-portable-clone-contract-acceptance.sh
source scripts/ci/migration-bridge-portable-clone-inventory-acceptance.sh
source scripts/ci/migration-bridge-portable-clone-db-export-acceptance.sh
source scripts/ci/migration-bridge-portable-clone-file-export-acceptance.sh
source scripts/ci/migration-bridge-portable-clone-package-integrity-acceptance.sh
source scripts/ci/migration-bridge-portable-clone-delivery-retention-acceptance.sh
source scripts/ci/migration-bridge-portable-clone-import-preflight-acceptance.sh
source scripts/ci/migration-bridge-portable-clone-import-payload-acceptance.sh
source scripts/ci/migration-bridge-portable-clone-import-database-acceptance.sh
source scripts/ci/migration-bridge-portable-clone-import-file-acceptance.sh
source scripts/ci/migration-bridge-portable-clone-import-rewrite-acceptance.sh
source scripts/ci/migration-bridge-portable-clone-local-plan-acceptance.sh
source scripts/ci/migration-bridge-portable-clone-local-bootstrap-acceptance.sh
source scripts/ci/migration-bridge-portable-clone-local-runtime-acceptance.sh
source scripts/ci/migration-bridge-sandbox-handoff-acceptance.sh
source scripts/ci/migration-bridge-sandbox-lab-acceptance.sh
source scripts/ci/migration-bridge-migration-engine-acceptance.sh
source scripts/ci/migration-bridge-parity-acceptance.sh
source scripts/ci/migration-bridge-cutover-acceptance.sh
source scripts/ci/migration-bridge-report-acceptance.sh
source scripts/ci/migration-bridge-operator-ui-acceptance.sh

printf '[smoke] Checking runtime diagnostics.\n'
docker logs "$WP_CONTAINER" >"$RUNTIME_LOG" 2>&1 || true
docker exec "$WP_CONTAINER" sh -c 'test ! -f /var/www/html/wp-content/debug.log || cat /var/www/html/wp-content/debug.log' >"$DEBUG_LOG" 2>&1 || true

if grep -Eqi 'PHP (Fatal error|Warning|Notice)|Fatal error|Uncaught (Error|Exception)' "$RUNTIME_LOG" "$DEBUG_LOG"; then
  MATCH="$(grep -Eim1 'PHP (Fatal error|Warning|Notice)|Fatal error|Uncaught (Error|Exception)' "$RUNTIME_LOG" "$DEBUG_LOG" | tr -d '\r' | head -c 240)"
  fail_smoke "runtime-php" "PHP runtime emitted a fatal, warning, notice or uncaught error" "no PHP runtime diagnostics" "$MATCH" "inspect WordPress runtime/debug logs"
fi

printf 'WordPress smoke OK: WordPress 7.1 / PHP 8.2 fixture installed; source plugin + Migration Bridge + theme active; 7/7 theme patterns registered; native SEO authority=%s; canonical/meta/robots contract healthy; Phase 8A analyzer read-only acceptance passed; Phase 8B public baseline capture/persistence passed; Phase 8C dependency graph passed; bounded dependency review planning passed; Portable Clone 10E.2A.1 contract passed; 10E.2A.2 read-only source inventory/destination planning passed; 10E.2A.3.1 resumable private database export passed; 10E.2A.3.2 resumable private file export passed; 10E.2A.3.3 package manifest/integrity passed; 10E.2A.3.4 authenticated delivery/retention passed; 10E.2A.4.1 Portable Import preflight passed; 10E.2A.4.2 private extraction/full payload checksum passed; 10E.2A.4.3 transactional staging database restore passed; 10E.2A.4.4 verified staging-file restore passed; 10E.2A.4.5 serialization-safe environment rewrite passed; 10E.2A.4.6.1 read-only finalization preflight passed; 10E.2A.4.6.2 reversible atomic database activation/rollback passed; Phase 8D sandbox lab passed; Phase 8E Migration Engine passed; Phase 8F SEO/GEO parity engine passed; Phase 8G safe cutover/rollback passed; Phase 8H migration report passed; Phase 8I operator UI passed; frontend/admin requests healthy; language=%s; seo-provider=%s.\n' "$SEO_AUTHORITY" "$PROVIDER" "$SEO_PROVIDER"
