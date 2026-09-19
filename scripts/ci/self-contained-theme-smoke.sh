#!/usr/bin/env bash
set -u -o pipefail

PIPELINE="${GITHUB_WORKFLOW:-self-contained-theme}"
RUN_ID="${GITHUB_RUN_ID:-local}"
RUN_ATTEMPT="${GITHUB_RUN_ATTEMPT:-1}"
JOB="${GITHUB_JOB:-self-contained-theme}"

WORDPRESS_IMAGE="wordpress:7.1.0-php8.2-apache"
WPCLI_IMAGE="wordpress:cli-2.12.0-php8.2"
MARIADB_IMAGE="mariadb:11.8.9"

SUFFIX="${RUN_ID}-${RUN_ATTEMPT}-$$"
NETWORK="seo-geo-self-contained-${SUFFIX}"
DB_CONTAINER="seo-geo-self-contained-db-${SUFFIX}"
WP_CONTAINER="seo-geo-self-contained-wp-${SUFFIX}"
WP_VOLUME="seo-geo-self-contained-wp-${SUFFIX}"

DB_NAME="wordpress"
DB_USER="wordpress"
DB_PASSWORD="wordpress-theme-password"
DB_ROOT_PASSWORD="root-theme-password"

TMP_DIR="$(mktemp -d)"
BUILT_THEME="${TMP_DIR}/seo-geo-theme"
PAGE_BODY="${TMP_DIR}/page.html"
HOME_BODY="${TMP_DIR}/home.html"
AUTHOR_BODY="${TMP_DIR}/author.html"
AUTHOR_ARTICLE_BODY="${TMP_DIR}/author-article.html"
PROVENANCE_MARKDOWN_BODY="${TMP_DIR}/provenance-article.md"
SEARCH_BODY="${TMP_DIR}/search.html"
ROBOTS_BODY="${TMP_DIR}/robots.txt"
LLMS_BODY="${TMP_DIR}/llms.txt"
LLMS_HEADERS="${TMP_DIR}/llms.headers"
MARKDOWN_BODY="${TMP_DIR}/alternate.md"
MARKDOWN_HEADERS="${TMP_DIR}/alternate.headers"
RUNTIME_LOG="${TMP_DIR}/runtime.log"
DEBUG_LOG="${TMP_DIR}/debug.log"
RUNTIME_EVAL_ERROR="${TMP_DIR}/runtime-eval.stderr"
AUTHORITY_EVAL_ERROR="${TMP_DIR}/authority-eval.stderr"
CRAWLER_ADMIN_EVAL_ERROR="${TMP_DIR}/crawler-admin-eval.stderr"
BREADCRUMB_EVAL_ERROR="${TMP_DIR}/breadcrumb-eval.stderr"

signature() {
  printf '%s' "$1" | sha256sum | cut -c1-12
}

json_escape() {
  python3 -c 'import json,sys; print(json.dumps(sys.argv[1]))' "$1"
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
  local command_name="${5:-bash scripts/ci/self-contained-theme-smoke.sh}"
  local sig
  sig="$(signature "${code}:${primary}")"

  cat <<JSON
{
  "schema_version": 1,
  "pipeline": $(json_escape "$PIPELINE"),
  "run_id": $(json_escape "$RUN_ID"),
  "run_attempt": $(json_escape "$RUN_ATTEMPT"),
  "job": $(json_escape "$JOB"),
  "step": "self-contained-theme-smoke",
  "command": $(json_escape "$command_name"),
  "exit_code": 1,
  "primary_error": $(json_escape "$primary"),
  "file_line": null,
  "expected": $(json_escape "$expected"),
  "received": $(json_escape "$received"),
  "error_signature": $(json_escape "$sig"),
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
    wp "$@" --path=/var/www/html
}

printf '[self-contained] Building installable theme with embedded Core.\n'
bash scripts/build-theme-package.sh "$BUILT_THEME" \
  || fail_smoke "theme-build" "Could not assemble self-contained theme" "build succeeds" "build failed" "bash scripts/build-theme-package.sh"

for required_file in \
  "${BUILT_THEME}/inc/seo-geo-core/src/Runtime.php" \
  "${BUILT_THEME}/inc/seo-geo-core/src/Seo/OpenGraphResolver.php" \
  "${BUILT_THEME}/inc/seo-geo-core/src/Seo/BreadcrumbResolver.php" \
  "${BUILT_THEME}/inc/seo-geo-core/src/Schema/SchemaNodeIds.php" \
  "${BUILT_THEME}/inc/seo-geo-core/src/Schema/SchemaIdentityResolver.php" \
  "${BUILT_THEME}/inc/seo-geo-core/src/Schema/SchemaLocalBusinessResolver.php" \
  "${BUILT_THEME}/inc/seo-geo-core/src/Schema/SchemaVisibleContentResolver.php" \
  "${BUILT_THEME}/inc/seo-geo-core/src/Schema/SchemaArticleResolver.php" \
  "${BUILT_THEME}/inc/seo-geo-core/src/Schema/SchemaGraphBuilder.php" \
  "${BUILT_THEME}/inc/seo-geo-core/src/Schema/SchemaPresenter.php" \
  "${BUILT_THEME}/inc/seo-geo-core/src/Geo/CrawlerPolicyResolver.php" \
  "${BUILT_THEME}/inc/seo-geo-core/src/Geo/CrawlerPolicyPresenter.php" \
  "${BUILT_THEME}/inc/seo-geo-core/src/Geo/CrawlerPolicyAdmin.php" \
  "${BUILT_THEME}/inc/seo-geo-core/src/Geo/DiscoveryCacheRevision.php" \
  "${BUILT_THEME}/inc/seo-geo-core/src/Geo/DiscoveryCachePolicy.php" \
  "${BUILT_THEME}/inc/seo-geo-core/src/Geo/DiscoveryCacheInvalidator.php" \
  "${BUILT_THEME}/languages/seo-geo-core-es_ES.po" \
  "${BUILT_THEME}/languages/seo-geo-core-es_ES.mo" \
  "${BUILT_THEME}/inc/seo-geo-core/src/Geo/LlmsTxtResolver.php" \
  "${BUILT_THEME}/inc/seo-geo-core/src/Geo/LlmsTxtPresenter.php" \
  "${BUILT_THEME}/inc/seo-geo-core/src/Geo/MarkdownAlternateResolver.php" \
  "${BUILT_THEME}/inc/seo-geo-core/src/Geo/MarkdownAlternatePresenter.php" \
  "${BUILT_THEME}/inc/presets.php" \
  "${BUILT_THEME}/presets/corporate/preset.json" \
  "${BUILT_THEME}/presets/corporate/content-map.json" \
  "${BUILT_THEME}/presets/corporate/patterns.json"; do
  [[ -f "$required_file" ]] \
    || fail_smoke "embedded-runtime-file" "Built theme is missing embedded SEO/GEO runtime source" "${required_file}" "missing"
done

printf '[self-contained] Starting isolated WordPress fixture.\n'
docker network create "$NETWORK" >/dev/null \
  || fail_smoke "network-create" "Could not create Docker network" "network created" "failed"
docker volume create "$WP_VOLUME" >/dev/null \
  || fail_smoke "volume-create" "Could not create WordPress volume" "volume created" "failed"

docker run -d \
  --name "$DB_CONTAINER" \
  --network "$NETWORK" \
  -e "MARIADB_DATABASE=${DB_NAME}" \
  -e "MARIADB_USER=${DB_USER}" \
  -e "MARIADB_PASSWORD=${DB_PASSWORD}" \
  -e "MARIADB_ROOT_PASSWORD=${DB_ROOT_PASSWORD}" \
  "$MARIADB_IMAGE" >/dev/null \
  || fail_smoke "database-start" "MariaDB container did not start" "running database" "failed"

wait_for_db \
  || fail_smoke "database-ready" "MariaDB did not become ready" "database ready" "timeout"

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
  || fail_smoke "wordpress-start" "WordPress container did not start" "running WordPress" "failed"

wait_for_wordpress_files \
  || fail_smoke "wordpress-files" "WordPress files were not initialized" "wp-settings.php exists" "timeout"

HOST_PORT="$(docker port "$WP_CONTAINER" 80/tcp | awk -F: 'NR == 1 {print $NF}')"
[[ -n "$HOST_PORT" ]] \
  || fail_smoke "wordpress-port" "Could not resolve WordPress host port" "non-empty port" "empty"
BASE_URL="http://127.0.0.1:${HOST_PORT}"

printf '[self-contained] Installing only the built theme.\n'
docker exec "$WP_CONTAINER" mkdir -p /var/www/html/wp-content/themes/seo-geo-theme \
  || fail_smoke "theme-dir" "Could not create theme directory" "directory created" "failed"
docker cp "${BUILT_THEME}/." "$WP_CONTAINER":/var/www/html/wp-content/themes/seo-geo-theme/ \
  || fail_smoke "theme-copy" "Could not copy built theme" "theme copied" "failed"
docker exec "$WP_CONTAINER" chown -R www-data:www-data /var/www/html/wp-content/themes/seo-geo-theme \
  || fail_smoke "theme-permissions" "Could not set theme permissions" "www-data owns theme" "failed"

wp_cli core install \
  --url="$BASE_URL" \
  --title="Self-contained SEO GEO" \
  --admin_user=admin \
  --admin_password=self-contained-admin \
  --admin_email=admin@example.test \
  --skip-email >/dev/null \
  || fail_smoke "core-install" "Could not install WordPress" "core install succeeds" "failed"

wp_cli rewrite structure '/%postname%/' --hard >/dev/null \
  || fail_smoke "rewrite" "Could not configure permalinks" "/%postname%/" "failed"
wp_cli theme activate seo-geo-theme >/dev/null \
  || fail_smoke "theme-activate" "Could not activate self-contained theme" "theme active" "failed"

ACTIVE_PLUGINS="$(wp_cli plugin list --status=active --field=name 2>/dev/null | tr -d '\r')"
[[ -z "$ACTIVE_PLUGINS" ]] \
  || fail_smoke "zero-plugins" "Self-contained acceptance must run with zero active plugins" "empty active-plugin list" "$ACTIVE_PLUGINS" "wp plugin list --status=active"

if docker exec "$WP_CONTAINER" test -d /var/www/html/wp-content/plugins/seo-geo-core; then
  fail_smoke "plugin-absent" "SEO GEO Core plugin directory must not be installed" "plugin directory absent" "directory exists"
fi

if ! RUNTIME_FILE="$(wp_cli eval '$r = new ReflectionClass( \SeoGeo\Core\Runtime::class ); echo (string) $r->getFileName();' 2>"$RUNTIME_EVAL_ERROR" | tr -d '\r\n')"; then
  ERROR_TEXT="$(tr -d '\r' <"$RUNTIME_EVAL_ERROR" | head -c 240)"
  fail_smoke "runtime-eval" "Could not resolve embedded runtime class" "Runtime class available from theme" "${ERROR_TEXT:-wp eval failed}" "wp eval ReflectionClass Runtime"
fi

case "$RUNTIME_FILE" in
  */wp-content/themes/seo-geo-theme/inc/seo-geo-core/src/Runtime.php) ;;
  *) fail_smoke "runtime-origin" "SEO/GEO runtime was not loaded from the theme bundle" "theme/inc/seo-geo-core/src/Runtime.php" "$RUNTIME_FILE" "ReflectionClass Runtime" ;;
esac

if ! AUTHORITY="$(wp_cli eval 'echo \SeoGeo\Core\Runtime::seo_authority()?->provider() ?? "missing";' 2>"$AUTHORITY_EVAL_ERROR" | tr -d '\r\n')"; then
  ERROR_TEXT="$(tr -d '\r' <"$AUTHORITY_EVAL_ERROR" | head -c 240)"
  fail_smoke "authority-eval" "Could not resolve native SEO authority" "native authority available" "${ERROR_TEXT:-wp eval failed}" "wp eval Runtime::seo_authority"
fi
[[ "$AUTHORITY" == "native" ]] \
  || fail_smoke "native-authority" "Theme-only runtime must own native SEO output" "native" "$AUTHORITY" "Runtime::seo_authority"

printf '[self-contained] Checking crawler policy administration contract.\n'
if ! CRAWLER_SANITIZED="$(wp_cli eval '$resolver = \SeoGeo\Core\Runtime::crawler_policy(); if ( ! $resolver ) { exit( 1 ); } echo wp_json_encode( $resolver->sanitize_configuration( array( "oai_searchbot" => "allow", "gptbot" => "inherit", "unknown_bot" => "disallow" ) ) );' 2>"$CRAWLER_ADMIN_EVAL_ERROR" | tr -d '\r\n')"; then
  ERROR_TEXT="$(tr -d '\r' <"$CRAWLER_ADMIN_EVAL_ERROR" | head -c 240)"
  fail_smoke "crawler-admin-sanitize-eval" "Could not evaluate crawler policy sanitizer" "normalized supported policy" "${ERROR_TEXT:-wp eval failed}" "wp eval CrawlerPolicyResolver::sanitize_configuration"
fi
[[ "$CRAWLER_SANITIZED" == '{"oai_searchbot":"allow"}' ]] \
  || fail_smoke "crawler-admin-sanitize" "Crawler policy sanitizer kept inherited, malformed, or unknown entries" '{"oai_searchbot":"allow"}' "$CRAWLER_SANITIZED" "CrawlerPolicyResolver::sanitize_configuration"

wp_cli eval 'update_option( "seo_geo_crawler_policy", array( "oai_searchbot" => "allow", "gptbot" => "disallow" ), false ); update_option( "blog_public", "0" );' >/dev/null \
  || fail_smoke "crawler-admin-fixture" "Could not configure crawler admin reporting fixture" "option updates succeed" "failed"

if ! CRAWLER_ADMIN_HTML="$(wp_cli eval 'wp_set_current_user( 1 ); if ( ! function_exists( "submit_button" ) ) { require_once ABSPATH . "wp-admin/includes/admin.php"; } $resolver = \SeoGeo\Core\Runtime::crawler_policy(); if ( ! $resolver ) { exit( 1 ); } $admin = new \SeoGeo\Core\Geo\CrawlerPolicyAdmin( $resolver ); ob_start(); $admin->render_page(); echo ob_get_clean();' 2>"$CRAWLER_ADMIN_EVAL_ERROR")"; then
  ERROR_TEXT="$(tr -d '\r' <"$CRAWLER_ADMIN_EVAL_ERROR" | head -c 240)"
  fail_smoke "crawler-admin-render-eval" "Could not render crawler policy administration screen" "render succeeds for manage_options user" "${ERROR_TEXT:-wp eval failed}" "wp eval CrawlerPolicyAdmin::render_page"
fi

for expected_admin_fragment in \
  'name="seo_geo_crawler_policy[oai_searchbot]"' \
  'name="seo_geo_crawler_policy[gptbot]"' \
  'Current report' \
  'Controlled by WordPress site visibility'; do
  [[ "$CRAWLER_ADMIN_HTML" == *"$expected_admin_fragment"* ]] \
    || fail_smoke "crawler-admin-markup" "Crawler policy administration screen is missing required reporting markup" "$expected_admin_fragment" "fragment absent" "CrawlerPolicyAdmin::render_page"
done

if ! CRAWLER_ADMIN_ES="$(wp_cli eval 'switch_to_locale( "es_ES" ); unload_textdomain( "seo-geo-core", true ); load_textdomain( "seo-geo-core", get_template_directory() . "/languages/seo-geo-core-es_ES.mo" ); echo __( "SEO/GEO crawler policy", "seo-geo-core" );' 2>"$CRAWLER_ADMIN_EVAL_ERROR" | tr -d '\r\n')"; then
  ERROR_TEXT="$(tr -d '\r' <"$CRAWLER_ADMIN_EVAL_ERROR" | head -c 240)"
  fail_smoke "crawler-admin-es-eval" "Could not resolve bundled Spanish crawler-policy translation" "Política de rastreadores SEO/GEO" "${ERROR_TEXT:-wp eval failed}" "switch_to_locale es_ES"
fi
[[ "$CRAWLER_ADMIN_ES" == 'Política de rastreadores SEO/GEO' ]] \
  || fail_smoke "crawler-admin-es" "Crawler policy administration did not ship its Spanish translation" "Política de rastreadores SEO/GEO" "$CRAWLER_ADMIN_ES" "gettext seo-geo-core es_ES"

wp_cli eval 'delete_option( "seo_geo_crawler_policy" ); update_option( "blog_public", "1" );' >/dev/null \
  || fail_smoke "crawler-admin-reset" "Could not restore crawler admin fixture state" "crawler option removed and blog_public=1" "failed"

printf '[self-contained] Checking independent OpenAI crawler policy.\n'
curl -fsS "${BASE_URL}/robots.txt" -o "$ROBOTS_BODY" \
  || fail_smoke "crawler-policy-baseline-request" "Could not request baseline robots.txt" "HTTP 2xx" "curl failed"

if grep -Fq 'User-agent: OAI-SearchBot' "$ROBOTS_BODY" || grep -Fq 'User-agent: GPTBot' "$ROBOTS_BODY"; then
  fail_smoke "crawler-policy-baseline" "Unset crawler policy must inherit existing WordPress robots behavior" "no OpenAI-specific groups" "crawler group emitted"
fi

wp_cli eval 'update_option( "seo_geo_crawler_policy", array( "oai_searchbot" => "allow", "gptbot" => "disallow" ), false );' >/dev/null \
  || fail_smoke "crawler-policy-option" "Could not configure independent crawler policy" "option update succeeds" "failed"
curl -fsS "${BASE_URL}/robots.txt" -o "$ROBOTS_BODY" \
  || fail_smoke "crawler-policy-request" "Could not request configured robots.txt" "HTTP 2xx" "curl failed"

if ! CRAWLER_POLICY_RESULT="$(python3 - "$ROBOTS_BODY" <<'PY'
import sys

with open(sys.argv[1], "r", encoding="utf-8") as handle:
    body = handle.read().replace("\r\n", "\n")

assert body.count("# BEGIN SEO GEO crawler policy") == 1
assert body.count("# END SEO GEO crawler policy") == 1
assert body.count("User-agent: OAI-SearchBot") == 1
assert body.count("User-agent: GPTBot") == 1
assert "User-agent: OAI-SearchBot\nAllow: /" in body
assert "User-agent: GPTBot\nDisallow: /" in body
assert "User-agent: OAI-SearchBot\nDisallow: /" not in body
assert "User-agent: GPTBot\nAllow: /" not in body
print("ok")
PY
)"; then
  fail_smoke "crawler-policy-independent" "OAI-SearchBot and GPTBot directives are not independent" "OAI-SearchBot allow + GPTBot disallow" "${CRAWLER_POLICY_RESULT:-python assertion failed}" "parse robots.txt crawler groups"
fi

printf '[self-contained] Checking malformed crawler policy falls back to inherit.\n'
wp_cli eval 'update_option( "seo_geo_crawler_policy", array( "oai_searchbot" => "allow-everything", "gptbot" => "inherit", "unknown_bot" => "disallow" ), false );' >/dev/null \
  || fail_smoke "crawler-policy-invalid-option" "Could not configure malformed crawler policy fixture" "option update succeeds" "failed"
curl -fsS "${BASE_URL}/robots.txt" -o "$ROBOTS_BODY" \
  || fail_smoke "crawler-policy-invalid-request" "Could not request malformed-policy robots.txt" "HTTP 2xx" "curl failed"
if grep -Fq 'User-agent: OAI-SearchBot' "$ROBOTS_BODY" || grep -Fq 'User-agent: GPTBot' "$ROBOTS_BODY" || grep -Fq 'unknown_bot' "$ROBOTS_BODY"; then
  fail_smoke "crawler-policy-invalid" "Malformed or inherited crawler settings must not emit native groups" "no OpenAI-specific groups" "crawler group emitted"
fi

printf '[self-contained] Checking WordPress privacy overrides explicit crawler allows.\n'
wp_cli eval 'update_option( "seo_geo_crawler_policy", array( "oai_searchbot" => "allow", "gptbot" => "allow" ), false ); update_option( "blog_public", "0" );' >/dev/null \
  || fail_smoke "crawler-policy-private-option" "Could not configure private-site crawler fixture" "option updates succeed" "failed"
curl -fsS "${BASE_URL}/robots.txt" -o "$ROBOTS_BODY" \
  || fail_smoke "crawler-policy-private-request" "Could not request private-site robots.txt" "HTTP 2xx" "curl failed"
grep -Fq 'Disallow: /' "$ROBOTS_BODY" \
  || fail_smoke "crawler-policy-private-global" "Private WordPress site must preserve global crawl blocking" "Disallow: /" "global disallow absent"
if grep -Fq 'User-agent: OAI-SearchBot' "$ROBOTS_BODY" || grep -Fq 'User-agent: GPTBot' "$ROBOTS_BODY"; then
  fail_smoke "crawler-policy-private-override" "Explicit crawler allows must not override WordPress site privacy" "no OpenAI-specific allow groups" "crawler group emitted"
fi

wp_cli eval 'delete_option( "seo_geo_crawler_policy" ); update_option( "blog_public", "1" );' >/dev/null \
  || fail_smoke "crawler-policy-reset" "Could not restore crawler-policy fixture state" "crawler option removed and blog_public=1" "failed"

POST_ID="$(wp_cli post create \
  --post_type=post \
  --post_status=publish \
  --post_title='Self-contained SEO Fixture' \
  --post_name='self-contained-seo-fixture' \
  --post_excerpt='Self-contained SEO GEO native description.' \
  --post_content='Theme-only SEO GEO fixture body.' \
  --post_author=1 \
  --porcelain 2>/dev/null | tr -d '\r\n')"
[[ "$POST_ID" =~ ^[0-9]+$ ]] \
  || fail_smoke "fixture-post" "Could not create fixture post" "numeric post ID" "$POST_ID"

printf '[self-contained] Checking optional llms.txt endpoint.\n'
LLMS_DISABLED_STATUS="$(curl -sS -o "$LLMS_BODY" -w '%{http_code}' "${BASE_URL}/llms.txt")"
[[ "$LLMS_DISABLED_STATUS" == "404" ]] \
  || fail_smoke "llms-disabled" "llms.txt must be absent until explicitly enabled" "HTTP 404" "$LLMS_DISABLED_STATUS"

LLMS_PUBLIC_ID="$(wp_cli post create --post_type=page --post_status=publish --post_title='Public llms resource' --post_name='public-llms-resource' --post_content='Public llms body.' --porcelain 2>/dev/null | tr -d '\r\n')"
LLMS_DRAFT_ID="$(wp_cli post create --post_type=page --post_status=draft --post_title='Draft llms secret' --post_name='draft-llms-secret' --post_content='Draft secret.' --porcelain 2>/dev/null | tr -d '\r\n')"
LLMS_PRIVATE_ID="$(wp_cli post create --post_type=page --post_status=private --post_title='Private llms secret' --post_name='private-llms-secret' --post_content='Private secret.' --porcelain 2>/dev/null | tr -d '\r\n')"
LLMS_PASSWORD_ID="$(wp_cli post create --post_type=page --post_status=publish --post_title='Protected llms secret' --post_name='protected-llms-secret' --post_password='secret-pass' --post_content='Protected secret.' --porcelain 2>/dev/null | tr -d '\r\n')"

for llms_id in "$LLMS_PUBLIC_ID" "$LLMS_DRAFT_ID" "$LLMS_PRIVATE_ID" "$LLMS_PASSWORD_ID"; do
  [[ "$llms_id" =~ ^[0-9]+$ ]] \
    || fail_smoke "llms-fixture-id" "Could not create llms.txt resource fixture" "numeric post ID" "$llms_id"
done

wp_cli eval "update_option( 'seo_geo_llms_txt', array( 'enabled' => true, 'summary' => 'Curated agent index.', 'sections' => array( array( 'title' => 'Primary resources', 'post_ids' => array( $POST_ID, $LLMS_PUBLIC_ID, $LLMS_DRAFT_ID, $LLMS_PRIVATE_ID, $LLMS_PASSWORD_ID, $POST_ID, 0, -1 ) ), array( 'title' => '', 'post_ids' => array( $LLMS_PUBLIC_ID ) ), array( 'title' => 'Malformed', 'post_ids' => 'not-an-array' ) ) ), false );" >/dev/null \
  || fail_smoke "llms-option" "Could not configure llms.txt fixture" "option update succeeds" "failed"

LLMS_STATUS="$(curl -sS -D "$LLMS_HEADERS" -o "$LLMS_BODY" -w '%{http_code}' "${BASE_URL}/llms.txt")"
[[ "$LLMS_STATUS" == "200" ]] \
  || fail_smoke "llms-enabled-status" "Enabled llms.txt did not resolve" "HTTP 200" "$LLMS_STATUS"
grep -Eiq '^content-type: text/plain; charset=' "$LLMS_HEADERS" \
  || fail_smoke "llms-content-type" "llms.txt must use a plain-text content type" "text/plain charset header" "header absent"

if ! LLMS_RESULT="$(python3 - "$LLMS_BODY" "$BASE_URL" <<'PY'
import sys

with open(sys.argv[1], "r", encoding="utf-8") as handle:
    body = handle.read()

base = sys.argv[2].rstrip("/") + "/"
assert body.startswith("# Self-contained SEO GEO\n")
assert "> Curated agent index." in body
assert "## Primary resources" in body
post_line = f"- [Self-contained SEO Fixture]({base}self-contained-seo-fixture/)"
page_line = f"- [Public llms resource]({base}public-llms-resource/)"
assert body.count(post_line) == 1
assert body.count(page_line) == 1
assert "Draft llms secret" not in body
assert "Private llms secret" not in body
assert "Protected llms secret" not in body
assert "## Malformed" not in body
print("ok")
PY
)"; then
  fail_smoke "llms-contract" "Generated llms.txt contract is invalid" "curated public Markdown resources only" "${LLMS_RESULT:-python assertion failed}" "parse llms.txt"
fi

LLMS_HEAD_STATUS="$(curl -sS -I -o /dev/null -w '%{http_code}' "${BASE_URL}/llms.txt")"
[[ "$LLMS_HEAD_STATUS" == "200" ]] \
  || fail_smoke "llms-head" "HEAD llms.txt request did not resolve" "HTTP 200" "$LLMS_HEAD_STATUS"

printf '[self-contained] Checking optional Markdown alternate discovery and output.\n'
MARKDOWN_URL="${BASE_URL}/self-contained-seo-fixture/index.md"
MARKDOWN_DISABLED_STATUS="$(curl -sS -o "$MARKDOWN_BODY" -w '%{http_code}' "$MARKDOWN_URL")"
[[ "$MARKDOWN_DISABLED_STATUS" == "404" ]] \
  || fail_smoke "markdown-disabled" "Markdown alternate must be absent until explicitly enabled" "HTTP 404" "$MARKDOWN_DISABLED_STATUS"

wp_cli eval 'update_option( "seo_geo_markdown_alternates", array( "enabled" => true ), false );' >/dev/null \
  || fail_smoke "markdown-option" "Could not enable Markdown alternates" "option update succeeds" "failed"

curl -fsS "${BASE_URL}/self-contained-seo-fixture/" -o "$PAGE_BODY" \
  || fail_smoke "markdown-html-request" "Could not request HTML source after enabling Markdown" "HTTP 2xx" "curl failed"
grep -Fq '<link rel="alternate" type="text/markdown" href="'"$MARKDOWN_URL"'" />' "$PAGE_BODY" \
  || fail_smoke "markdown-head-alternate" "HTML source did not advertise its Markdown alternate" "$MARKDOWN_URL" "alternate absent"
grep -Fq '<link rel="describedby" href="'"${BASE_URL}/llms.txt"'" />' "$PAGE_BODY" \
  || fail_smoke "markdown-head-describedby" "HTML source did not advertise llms.txt" "${BASE_URL}/llms.txt" "describedby absent"

MARKDOWN_STATUS="$(curl -sS -D "$MARKDOWN_HEADERS" -o "$MARKDOWN_BODY" -w '%{http_code}' "$MARKDOWN_URL")"
[[ "$MARKDOWN_STATUS" == "200" ]] \
  || fail_smoke "markdown-status" "Enabled Markdown alternate did not resolve" "HTTP 200" "$MARKDOWN_STATUS"
grep -Eiq '^content-type: text/markdown; charset=' "$MARKDOWN_HEADERS" \
  || fail_smoke "markdown-content-type" "Markdown alternate must use text/markdown" "text/markdown charset header" "header absent"
grep -Fq 'rel="alternate"; type="text/html"' "$MARKDOWN_HEADERS" \
  || fail_smoke "markdown-link-header" "Markdown response did not link back to HTML" "alternate text/html Link header" "header absent"
grep -Fq 'rel="describedby"' "$MARKDOWN_HEADERS" \
  || fail_smoke "markdown-describedby-header" "Markdown response did not link to llms.txt" "describedby Link header" "header absent"

if ! MARKDOWN_RESULT="$(python3 - "$MARKDOWN_BODY" "${BASE_URL}/self-contained-seo-fixture/" <<'PY'
import sys
with open(sys.argv[1], "r", encoding="utf-8") as handle:
    body = handle.read()
source = sys.argv[2]
assert body.startswith("# Self-contained SEO Fixture\n")
assert f"Source: [{source}]({source})" in body
assert "Theme-only SEO GEO fixture body." in body
assert "<script" not in body.lower()
print("ok")
PY
)"; then
  fail_smoke "markdown-contract" "Generated Markdown alternate is invalid" "title + HTML source + authored body" "${MARKDOWN_RESULT:-python assertion failed}" "parse Markdown alternate"
fi

for blocked_md in \
  "${BASE_URL}/draft-llms-secret/index.md" \
  "${BASE_URL}/private-llms-secret/index.md" \
  "${BASE_URL}/protected-llms-secret/index.md"; do
  BLOCKED_STATUS="$(curl -sS -o "$MARKDOWN_BODY" -w '%{http_code}' "$blocked_md")"
  [[ "$BLOCKED_STATUS" == "404" ]] \
    || fail_smoke "markdown-private-leak" "Non-public resource exposed a Markdown alternate" "HTTP 404" "$BLOCKED_STATUS" "curl $blocked_md"
done

curl -fsS "${BASE_URL}/llms.txt" -o "$LLMS_BODY" \
  || fail_smoke "llms-markdown-request" "Could not request llms.txt after enabling Markdown" "HTTP 2xx" "curl failed"
grep -Fq '[Self-contained SEO Fixture]('"$MARKDOWN_URL"')' "$LLMS_BODY" \
  || fail_smoke "llms-markdown-link" "llms.txt did not prefer enabled Markdown alternate" "$MARKDOWN_URL" "Markdown link absent"
if grep -Fq '[Self-contained SEO Fixture]('"${BASE_URL}/self-contained-seo-fixture/"')' "$LLMS_BODY"; then
  fail_smoke "llms-html-fallback" "llms.txt kept HTML link after Markdown alternate was enabled" "Markdown URL only" "HTML URL still present"
fi

MARKDOWN_HEAD_STATUS="$(curl -sS -I -o /dev/null -w '%{http_code}' "$MARKDOWN_URL")"
[[ "$MARKDOWN_HEAD_STATUS" == "200" ]] \
  || fail_smoke "markdown-head" "HEAD Markdown request did not resolve" "HTTP 200" "$MARKDOWN_HEAD_STATUS"

wp_cli option update blog_public 0 >/dev/null \
  || fail_smoke "llms-private-site-option" "Could not make WordPress fixture private" "blog_public=0" "failed"
LLMS_PRIVATE_STATUS="$(curl -sS -o "$LLMS_BODY" -w '%{http_code}' "${BASE_URL}/llms.txt")"
[[ "$LLMS_PRIVATE_STATUS" == "404" ]] \
  || fail_smoke "llms-private-site" "Private WordPress site exposed llms.txt" "HTTP 404" "$LLMS_PRIVATE_STATUS"

MARKDOWN_PRIVATE_STATUS="$(curl -sS -o "$MARKDOWN_BODY" -w '%{http_code}' "$MARKDOWN_URL")"
[[ "$MARKDOWN_PRIVATE_STATUS" == "404" ]] \
  || fail_smoke "markdown-private-site" "Private WordPress site exposed Markdown alternate" "HTTP 404" "$MARKDOWN_PRIVATE_STATUS"

wp_cli eval 'delete_option( "seo_geo_llms_txt" ); delete_option( "seo_geo_markdown_alternates" ); update_option( "blog_public", "1" );' >/dev/null \
  || fail_smoke "llms-reset" "Could not reset GEO document fixtures" "options removed and blog_public=1" "failed"

# Reuse this same disposable WordPress fixture for the Phase 6F cross-surface
# non-public discovery regression matrix. Sourcing keeps one runner/Docker setup.
source scripts/ci/discovery-privacy-acceptance.sh

# Reuse the same fixture for Phase 6G cache/revalidation acceptance.
source scripts/ci/discovery-cache-acceptance.sh

# Reuse the same fixture for Phase 7A Corporate preset activation acceptance.
source scripts/ci/corporate-preset-acceptance.sh

# Reuse the same fixture for Phase 7B Local Business preset activation acceptance.
source scripts/ci/local-business-preset-acceptance.sh

# Reuse the same fixture for Phase 7C Publisher preset activation acceptance.
source scripts/ci/publisher-preset-acceptance.sh

# Reuse the same fixture for Phase 7D Ecommerce preset activation acceptance.
source scripts/ci/ecommerce-preset-acceptance.sh

curl -fsS "${BASE_URL}/self-contained-seo-fixture/" -o "$PAGE_BODY" \
  || fail_smoke "fixture-request" "Could not request fixture post" "HTTP 2xx" "curl failed"

CANONICAL_COUNT="$(grep -Eio 'rel=["'\'']canonical["'\'']' "$PAGE_BODY" | wc -l | tr -d ' ')"
[[ "$CANONICAL_COUNT" == "1" ]] \
  || fail_smoke "canonical-count" "Theme-only fixture must expose exactly one canonical" "1" "$CANONICAL_COUNT"
grep -Fq "${BASE_URL}/self-contained-seo-fixture/" "$PAGE_BODY" \
  || fail_smoke "canonical-value" "Canonical URL is incorrect" "${BASE_URL}/self-contained-seo-fixture/" "expected URL absent"

DESCRIPTION_COUNT="$(grep -Eio '<meta[^>]+name=["'\'']description["'\''][^>]*>' "$PAGE_BODY" | wc -l | tr -d ' ')"
[[ "$DESCRIPTION_COUNT" == "1" ]] \
  || fail_smoke "description-count" "Theme-only fixture must expose exactly one meta description" "1" "$DESCRIPTION_COUNT"
grep -Fq 'Self-contained SEO GEO native description.' "$PAGE_BODY" \
  || fail_smoke "description-value" "Native meta description is incorrect" "fixture excerpt" "expected description absent"

printf '[self-contained] Checking native Open Graph metadata.\n'
for property in title type url site_name description locale; do
  OG_COUNT="$(grep -Eio "<meta[^>]+property=[\"']og:${property}[\"'][^>]*>" "$PAGE_BODY" | wc -l | tr -d ' ')"
  [[ "$OG_COUNT" == "1" ]] \
    || fail_smoke "open-graph-${property}-count" "Open Graph property must be emitted exactly once" "1" "$OG_COUNT" "grep og:${property}"
done

grep -Eq 'property=["'\'']og:type["'\''][^>]+content=["'\'']article["'\'']' "$PAGE_BODY" \
  || fail_smoke "open-graph-type" "Singular post must use article Open Graph type" "article" "expected value absent"
grep -Fq 'Self-contained SEO Fixture' "$PAGE_BODY" \
  || fail_smoke "open-graph-title" "Open Graph title must derive from document title" "fixture title present" "expected title absent"
grep -Eq "property=[\"']og:url[\"'][^>]+content=[\"']${BASE_URL}/self-contained-seo-fixture/[\"']" "$PAGE_BODY" \
  || fail_smoke "open-graph-url" "Open Graph URL must equal canonical" "${BASE_URL}/self-contained-seo-fixture/" "expected value absent"
grep -Eq 'property=["'\'']og:site_name["'\''][^>]+content=["'\'']Self-contained SEO GEO["'\'']' "$PAGE_BODY" \
  || fail_smoke "open-graph-site-name" "Open Graph site name is incorrect" "Self-contained SEO GEO" "expected value absent"
grep -Eq 'property=["'\'']og:description["'\''][^>]+content=["'\'']Self-contained SEO GEO native description\.["'\'']' "$PAGE_BODY" \
  || fail_smoke "open-graph-description" "Open Graph description must reuse native description" "fixture excerpt" "expected value absent"

OG_IMAGE_COUNT="$(grep -Eio '<meta[^>]+property=["'\'']og:image["'\''][^>]*>' "$PAGE_BODY" | wc -l | tr -d ' ')"
[[ "$OG_IMAGE_COUNT" == "0" ]] \
  || fail_smoke "open-graph-image" "Fixture without featured image or site icon must not fabricate og:image" "0" "$OG_IMAGE_COUNT"

printf '[self-contained] Checking native Schema graph.\n'
if ! SCHEMA_RESULT="$(python3 - "$PAGE_BODY" "$BASE_URL" <<'PY'
import json
import sys
from html.parser import HTMLParser

class SchemaParser(HTMLParser):
    def __init__(self):
        super().__init__()
        self.capture = False
        self.count = 0
        self.parts = []

    def handle_starttag(self, tag, attrs):
        attributes = dict(attrs)
        if tag == "script" and attributes.get("id") == "seo-geo-schema-graph":
            assert attributes.get("type") == "application/ld+json"
            self.capture = True
            self.count += 1

    def handle_data(self, data):
        if self.capture:
            self.parts.append(data)

    def handle_endtag(self, tag):
        if tag == "script" and self.capture:
            self.capture = False

body_path = sys.argv[1]
base = sys.argv[2].rstrip("/") + "/"
parser = SchemaParser()
with open(body_path, "r", encoding="utf-8") as handle:
    parser.feed(handle.read())

assert parser.count == 1, f"schema_script_count={parser.count}"
payload = json.loads("".join(parser.parts))
assert payload.get("@context") == "https://schema.org"
nodes = payload.get("@graph")
assert isinstance(nodes, list) and len(nodes) == 5, f"nodes={nodes!r}"

by_type = {node.get("@type"): node for node in nodes if isinstance(node, dict)}
website = by_type.get("WebSite")
webpage = by_type.get("WebPage")
article = by_type.get("BlogPosting")
person = by_type.get("Person")
breadcrumb = by_type.get("BreadcrumbList")
assert isinstance(website, dict)
assert isinstance(webpage, dict)
assert isinstance(article, dict)
assert isinstance(person, dict)
assert isinstance(breadcrumb, dict)

canonical = base + "self-contained-seo-fixture/"
website_id = base + "#website"
webpage_id = canonical + "#webpage"
article_id = canonical + "#article"
profile_url = base + "author/admin/"
profile_page_id = profile_url + "#webpage"
person_id = profile_url + "#person"
breadcrumb_id = canonical + "#breadcrumb"

assert website.get("@id") == website_id
assert website.get("url") == base
assert website.get("name") == "Self-contained SEO GEO"
assert webpage.get("@id") == webpage_id
assert webpage.get("url") == canonical
assert webpage.get("isPartOf") == {"@id": website_id}
assert webpage.get("inLanguage") == "en-US"
assert webpage.get("mainEntity") == {"@id": article_id}
assert webpage.get("breadcrumb") == {"@id": breadcrumb_id}
assert isinstance(webpage.get("name"), str) and webpage["name"]

assert breadcrumb.get("@id") == breadcrumb_id
items = breadcrumb.get("itemListElement")
assert isinstance(items, list) and len(items) == 2, f"breadcrumb_items={items!r}"
assert items[0].get("@type") == "ListItem"
assert items[0].get("position") == 1
assert items[0].get("name") == "Self-contained SEO GEO"
assert items[0].get("item") == base
assert items[1].get("@type") == "ListItem"
assert items[1].get("position") == 2
assert items[1].get("name") == "Self-contained SEO Fixture"
assert items[1].get("item") == canonical

assert article.get("@id") == article_id
assert article.get("url") == canonical
assert article.get("mainEntityOfPage") == {"@id": webpage_id}
assert article.get("headline") == "Self-contained SEO Fixture"
assert article.get("inLanguage") == "en-US"
assert article.get("author") == {"@id": person_id}
assert "publisher" not in article
assert "image" not in article
assert isinstance(article.get("datePublished"), str) and article["datePublished"]
assert isinstance(article.get("dateModified"), str) and article["dateModified"]

assert person.get("@id") == person_id
assert person.get("name") == "admin"
assert person.get("url") == profile_url
assert person.get("mainEntityOfPage") == {"@id": profile_page_id}

print(json.dumps({
    "website_id": website_id,
    "webpage_id": webpage_id,
    "article_id": article_id,
    "person_id": person_id,
    "breadcrumb_id": breadcrumb_id,
    "inLanguage": article["inLanguage"],
}))
PY
)"; then
  fail_smoke "schema-breadcrumb-graph-contract" "Native Schema graph contract is invalid" "one parseable WebSite + WebPage + BreadcrumbList + BlogPosting + Person graph with stable IDs" "${SCHEMA_RESULT:-python assertion failed}" "parse native JSON-LD graph"
fi

printf '[self-contained] Checking explicit Organization identity on the home page.\n'
wp_cli eval 'update_option( \SeoGeo\Core\Schema\SchemaIdentityResolver::OPTION_NAME, array( "site_entity_type" => "organization" ), false );' >/dev/null \
  || fail_smoke "schema-organization-option" "Could not enable explicit Organization identity" "option update succeeds" "failed"
curl -fsS "${BASE_URL}/" -o "$HOME_BODY" \
  || fail_smoke "schema-organization-home-request" "Could not request Organization home fixture" "HTTP 2xx" "curl failed"

if ! ORGANIZATION_SCHEMA_RESULT="$(python3 - "$HOME_BODY" "$BASE_URL" <<'PY'
import json
import sys
from html.parser import HTMLParser

class SchemaParser(HTMLParser):
    def __init__(self):
        super().__init__()
        self.capture = False
        self.parts = []

    def handle_starttag(self, tag, attrs):
        attributes = dict(attrs)
        if tag == "script" and attributes.get("id") == "seo-geo-schema-graph":
            self.capture = True

    def handle_data(self, data):
        if self.capture:
            self.parts.append(data)

    def handle_endtag(self, tag):
        if tag == "script" and self.capture:
            self.capture = False

parser = SchemaParser()
with open(sys.argv[1], "r", encoding="utf-8") as handle:
    parser.feed(handle.read())

base = sys.argv[2].rstrip("/") + "/"
payload = json.loads("".join(parser.parts))
nodes = payload.get("@graph")
assert isinstance(nodes, list) and len(nodes) == 3, f"nodes={nodes!r}"
by_type = {node.get("@type"): node for node in nodes if isinstance(node, dict)}
website = by_type.get("WebSite")
webpage = by_type.get("WebPage")
organization = by_type.get("Organization")
assert isinstance(website, dict)
assert isinstance(webpage, dict)
assert isinstance(organization, dict)
organization_id = base + "#organization"
assert organization.get("@id") == organization_id
assert organization.get("name") == "Self-contained SEO GEO"
assert organization.get("url") == base
assert website.get("publisher") == {"@id": organization_id}
assert webpage.get("@type") == "WebPage"
assert by_type.get("BreadcrumbList") is None
assert "breadcrumb" not in webpage
print(json.dumps({"organization_id": organization_id, "node_count": len(nodes)}))
PY
)"; then
  fail_smoke "schema-organization-contract" "Explicit Organization graph contract is invalid" "home graph with WebSite + WebPage + Organization and publisher link" "${ORGANIZATION_SCHEMA_RESULT:-python assertion failed}" "parse Organization JSON-LD graph"
fi

printf '[self-contained] Checking visible LocalBusiness identity.\n'
LOCAL_BUSINESS_PAGE_ID="$(wp_cli post create --post_type=page --post_status=publish --post_title='Local Business Home' --post_name='local-business-home' --post_content='Carrer de la Prova 10 08001 Barcelona Catalunya +34 930 000 000 €€ Monday to Friday 09:00-18:00' --porcelain 2>/dev/null | tr -d '\r\n')"
[[ "$LOCAL_BUSINESS_PAGE_ID" =~ ^[0-9]+$ ]] \
  || fail_smoke "schema-local-business-page" "Could not create visible LocalBusiness front-page fixture" "numeric page ID" "$LOCAL_BUSINESS_PAGE_ID"
wp_cli option update show_on_front page >/dev/null \
  || fail_smoke "schema-local-business-front-mode" "Could not switch fixture to a static front page" "show_on_front=page" "failed"
wp_cli option update page_on_front "$LOCAL_BUSINESS_PAGE_ID" >/dev/null \
  || fail_smoke "schema-local-business-front-page" "Could not select LocalBusiness front page" "$LOCAL_BUSINESS_PAGE_ID" "failed"

wp_cli eval 'update_option( \SeoGeo\Core\Schema\SchemaIdentityResolver::OPTION_NAME, array( "site_entity_type" => "local_business" ), false ); update_option( \SeoGeo\Core\Schema\SchemaLocalBusinessResolver::OPTION_NAME, array( "type" => "Plumber", "street_address" => "Carrer de la Prova 10", "address_locality" => "Barcelona", "address_region" => "Catalunya", "postal_code" => "08001", "address_country" => "ES", "telephone" => "+34 930 000 000", "price_range" => "€€", "latitude" => "41.38740", "longitude" => "2.16860", "opening_hours" => array( array( "days" => array( "Monday", "Tuesday", "Wednesday", "Thursday", "Friday" ), "opens" => "09:00", "closes" => "18:00", "visible_text" => "Monday to Friday 09:00-18:00" ) ) ), false );' >/dev/null \
  || fail_smoke "schema-local-business-option" "Could not configure explicit LocalBusiness identity" "option updates succeed" "failed"
curl -fsS "${BASE_URL}/" -o "$HOME_BODY" \
  || fail_smoke "schema-local-business-home-request" "Could not request LocalBusiness home fixture" "HTTP 2xx" "curl failed"

for visible_fact in 'Carrer de la Prova 10' '08001' 'Barcelona' 'Catalunya' '+34 930 000 000' '€€' 'Monday to Friday 09:00-18:00'; do
  grep -Fq "$visible_fact" "$HOME_BODY" \
    || fail_smoke "schema-local-business-visible-fact" "LocalBusiness Schema fixture fact is not visible in rendered HTML" "$visible_fact" "missing"
done

if ! LOCAL_BUSINESS_SCHEMA_RESULT="$(python3 - "$HOME_BODY" "$BASE_URL" <<'PY'
import json
import sys
from html.parser import HTMLParser

class SchemaParser(HTMLParser):
    def __init__(self):
        super().__init__()
        self.capture = False
        self.parts = []

    def handle_starttag(self, tag, attrs):
        attributes = dict(attrs)
        if tag == "script" and attributes.get("id") == "seo-geo-schema-graph":
            self.capture = True

    def handle_data(self, data):
        if self.capture:
            self.parts.append(data)

    def handle_endtag(self, tag):
        if tag == "script" and self.capture:
            self.capture = False

parser = SchemaParser()
with open(sys.argv[1], "r", encoding="utf-8") as handle:
    parser.feed(handle.read())

base = sys.argv[2].rstrip("/") + "/"
payload = json.loads("".join(parser.parts))
nodes = payload.get("@graph")
assert isinstance(nodes, list) and len(nodes) == 3, f"nodes={nodes!r}"
by_type = {node.get("@type"): node for node in nodes if isinstance(node, dict)}
website = by_type.get("WebSite")
webpage = by_type.get("WebPage")
business = by_type.get("Plumber")
assert isinstance(website, dict)
assert isinstance(webpage, dict)
assert isinstance(business, dict)
business_id = base + "#localbusiness"
assert business.get("@id") == business_id
assert business.get("name") == "Self-contained SEO GEO"
assert business.get("url") == base
assert website.get("publisher") == {"@id": business_id}
assert webpage.get("mainEntity") == {"@id": business_id}
assert by_type.get("Organization") is None

address = business.get("address")
assert isinstance(address, dict)
assert address.get("@type") == "PostalAddress"
assert address.get("streetAddress") == "Carrer de la Prova 10"
assert address.get("addressLocality") == "Barcelona"
assert address.get("addressRegion") == "Catalunya"
assert address.get("postalCode") == "08001"
assert address.get("addressCountry") == "ES"

assert business.get("telephone") == "+34 930 000 000"
assert business.get("priceRange") == "€€"
geo = business.get("geo")
assert isinstance(geo, dict)
assert geo.get("@type") == "GeoCoordinates"
assert geo.get("latitude") == 41.3874
assert geo.get("longitude") == 2.1686

hours = business.get("openingHoursSpecification")
assert isinstance(hours, list) and len(hours) == 1
assert hours[0].get("@type") == "OpeningHoursSpecification"
assert hours[0].get("dayOfWeek") == [
    "https://schema.org/Monday",
    "https://schema.org/Tuesday",
    "https://schema.org/Wednesday",
    "https://schema.org/Thursday",
    "https://schema.org/Friday",
]
assert hours[0].get("opens") == "09:00:00"
assert hours[0].get("closes") == "18:00:00"

print(json.dumps({"business_id": business_id, "node_count": len(nodes)}))
PY
)"; then
  fail_smoke "schema-local-business-visible-contract" "Visible LocalBusiness graph contract is invalid" "visible front-page facts mapped to one typed LocalBusiness" "${LOCAL_BUSINESS_SCHEMA_RESULT:-python assertion failed}" "parse visible LocalBusiness JSON-LD graph"
fi

printf '[self-contained] Checking LocalBusiness does not leak onto an article.\n'
curl -fsS "${BASE_URL}/self-contained-seo-fixture/" -o "$PAGE_BODY" \
  || fail_smoke "schema-local-business-article-request" "Could not request article while LocalBusiness identity is active" "HTTP 2xx" "curl failed"
if ! LOCAL_BUSINESS_ARTICLE_RESULT="$(python3 - "$PAGE_BODY" <<'PY'
import json
import sys
from html.parser import HTMLParser

class SchemaParser(HTMLParser):
    def __init__(self):
        super().__init__()
        self.capture = False
        self.parts = []

    def handle_starttag(self, tag, attrs):
        attributes = dict(attrs)
        if tag == "script" and attributes.get("id") == "seo-geo-schema-graph":
            self.capture = True

    def handle_data(self, data):
        if self.capture:
            self.parts.append(data)

    def handle_endtag(self, tag):
        if tag == "script" and self.capture:
            self.capture = False

parser = SchemaParser()
with open(sys.argv[1], "r", encoding="utf-8") as handle:
    parser.feed(handle.read())
payload = json.loads("".join(parser.parts))
nodes = payload.get("@graph")
assert isinstance(nodes, list)
assert all(node.get("@id", "").endswith("#localbusiness") is False for node in nodes if isinstance(node, dict))
article = next(node for node in nodes if node.get("@type") == "BlogPosting")
assert "publisher" not in article
print("ok")
PY
)"; then
  fail_smoke "schema-local-business-article-leak" "LocalBusiness data must not be emitted on an article where its physical facts are not visible" "no LocalBusiness node or publisher reference" "${LOCAL_BUSINESS_ARTICLE_RESULT:-python assertion failed}" "parse article JSON-LD graph"
fi

printf '[self-contained] Checking hidden optional LocalBusiness fields are omitted.\n'
wp_cli post update "$LOCAL_BUSINESS_PAGE_ID" --post_content='Carrer de la Prova 10 08001 Barcelona Catalunya' >/dev/null \
  || fail_smoke "schema-local-business-hidden-content" "Could not update visible LocalBusiness fixture content" "page update succeeds" "failed"
wp_cli eval 'update_option( \SeoGeo\Core\Schema\SchemaLocalBusinessResolver::OPTION_NAME, array( "type" => "NotARealBusinessType", "street_address" => "Carrer de la Prova 10", "address_locality" => "Barcelona", "address_region" => "Catalunya", "postal_code" => "08001", "address_country" => "ES", "telephone" => "+34 930 111 111", "price_range" => "$$$", "latitude" => "91.00000", "longitude" => "2.16860", "opening_hours" => array( array( "days" => array( "Monday" ), "opens" => "09:00", "closes" => "18:00", "visible_text" => "Hidden Monday hours" ) ) ), false );' >/dev/null \
  || fail_smoke "schema-local-business-hidden-option" "Could not configure hidden optional LocalBusiness fields" "option update succeeds" "failed"
curl -fsS "${BASE_URL}/" -o "$HOME_BODY" \
  || fail_smoke "schema-local-business-hidden-request" "Could not request hidden optional LocalBusiness fixture" "HTTP 2xx" "curl failed"

if ! LOCAL_BUSINESS_OPTIONAL_RESULT="$(python3 - "$HOME_BODY" <<'PY'
import json
import sys
from html.parser import HTMLParser

class SchemaParser(HTMLParser):
    def __init__(self):
        super().__init__()
        self.capture = False
        self.parts = []

    def handle_starttag(self, tag, attrs):
        attributes = dict(attrs)
        if tag == "script" and attributes.get("id") == "seo-geo-schema-graph":
            self.capture = True

    def handle_data(self, data):
        if self.capture:
            self.parts.append(data)

    def handle_endtag(self, tag):
        if tag == "script" and self.capture:
            self.capture = False

parser = SchemaParser()
with open(sys.argv[1], "r", encoding="utf-8") as handle:
    parser.feed(handle.read())
payload = json.loads("".join(parser.parts))
nodes = payload.get("@graph")
assert isinstance(nodes, list) and len(nodes) == 3, f"nodes={nodes!r}"
business = next(node for node in nodes if node.get("@type") == "LocalBusiness")
assert "telephone" not in business
assert "priceRange" not in business
assert "geo" not in business
assert "openingHoursSpecification" not in business
print("ok")
PY
)"; then
  fail_smoke "schema-local-business-hidden-optional" "Hidden or invalid optional LocalBusiness fields must be omitted" "generic LocalBusiness with visible address only" "${LOCAL_BUSINESS_OPTIONAL_RESULT:-python assertion failed}" "parse hidden optional LocalBusiness JSON-LD graph"
fi

printf '[self-contained] Checking hidden required LocalBusiness address suppresses the entity.\n'
wp_cli post update "$LOCAL_BUSINESS_PAGE_ID" --post_content='Carrer de la Prova 10 08001 Catalunya' >/dev/null \
  || fail_smoke "schema-local-business-hidden-address-content" "Could not remove locality from visible fixture content" "page update succeeds" "failed"
wp_cli eval 'update_option( \SeoGeo\Core\Schema\SchemaLocalBusinessResolver::OPTION_NAME, array( "type" => "Plumber", "street_address" => "Carrer de la Prova 10", "address_locality" => "Barcelona", "address_region" => "Catalunya", "postal_code" => "08001", "address_country" => "ES" ), false );' >/dev/null \
  || fail_smoke "schema-local-business-hidden-address-option" "Could not configure required LocalBusiness address fixture" "option update succeeds" "failed"
curl -fsS "${BASE_URL}/" -o "$HOME_BODY" \
  || fail_smoke "schema-local-business-hidden-address-request" "Could not request hidden address LocalBusiness fixture" "HTTP 2xx" "curl failed"

if ! LOCAL_BUSINESS_NEGATIVE_RESULT="$(python3 - "$HOME_BODY" <<'PY'
import json
import sys
from html.parser import HTMLParser

class SchemaParser(HTMLParser):
    def __init__(self):
        super().__init__()
        self.capture = False
        self.parts = []

    def handle_starttag(self, tag, attrs):
        attributes = dict(attrs)
        if tag == "script" and attributes.get("id") == "seo-geo-schema-graph":
            self.capture = True

    def handle_data(self, data):
        if self.capture:
            self.parts.append(data)

    def handle_endtag(self, tag):
        if tag == "script" and self.capture:
            self.capture = False

parser = SchemaParser()
with open(sys.argv[1], "r", encoding="utf-8") as handle:
    parser.feed(handle.read())
payload = json.loads("".join(parser.parts))
nodes = payload.get("@graph")
assert isinstance(nodes, list) and len(nodes) == 2, f"nodes={nodes!r}"
website = next(node for node in nodes if node.get("@type") == "WebSite")
webpage = next(node for node in nodes if node.get("@type") == "WebPage")
assert "publisher" not in website
assert "mainEntity" not in webpage
assert all(node.get("@id", "").endswith("#localbusiness") is False for node in nodes if isinstance(node, dict))
print("ok")
PY
)"; then
  fail_smoke "schema-local-business-hidden-required" "LocalBusiness with hidden required address data must not emit an entity" "baseline WebSite + WebPage only" "${LOCAL_BUSINESS_NEGATIVE_RESULT:-python assertion failed}" "parse hidden required LocalBusiness JSON-LD graph"
fi

wp_cli option update show_on_front posts >/dev/null \
  || fail_smoke "schema-local-business-front-reset" "Could not restore posts front page" "show_on_front=posts" "failed"
wp_cli option update page_on_front 0 >/dev/null \
  || fail_smoke "schema-local-business-page-reset" "Could not clear static front-page selection" "page_on_front=0" "failed"
wp_cli eval 'update_option( \SeoGeo\Core\Schema\SchemaIdentityResolver::OPTION_NAME, array( "site_entity_type" => "organization" ), false ); delete_option( \SeoGeo\Core\Schema\SchemaLocalBusinessResolver::OPTION_NAME );' >/dev/null \
  || fail_smoke "schema-local-business-reset" "Could not restore Organization fixture after LocalBusiness checks" "identity reset succeeds" "failed"

printf '[self-contained] Checking native author ProfilePage + Person identity.\n'
AUTHOR_ID="$(wp_cli user create schema-author schema-author@example.test --role=author --user_pass=schema-author-pass --display_name='Schema Author' --description='Visible schema author biography.' --porcelain 2>/dev/null | tr -d '\r\n')"
[[ "$AUTHOR_ID" =~ ^[0-9]+$ ]] \
  || fail_smoke "schema-author-user" "Could not create Schema author fixture" "numeric user ID" "$AUTHOR_ID"

AUTHOR_POST_ID="$(wp_cli post create --post_type=post --post_status=publish --post_title='Schema Author Article' --post_name='schema-author-article' --post_content='Visible article content by the Schema author.' --post_author="$AUTHOR_ID" --porcelain 2>/dev/null | tr -d '\r\n')"
[[ "$AUTHOR_POST_ID" =~ ^[0-9]+$ ]] \
  || fail_smoke "schema-author-post" "Could not create author-owned fixture post" "numeric post ID" "$AUTHOR_POST_ID"

curl -fsS "${BASE_URL}/author/schema-author/" -o "$AUTHOR_BODY" \
  || fail_smoke "schema-author-request" "Could not request author profile fixture" "HTTP 2xx" "curl failed"

if ! AUTHOR_SCHEMA_RESULT="$(python3 - "$AUTHOR_BODY" "$BASE_URL" <<'PY'
import json
import sys
from html.parser import HTMLParser

class SchemaParser(HTMLParser):
    def __init__(self):
        super().__init__()
        self.capture = False
        self.parts = []

    def handle_starttag(self, tag, attrs):
        attributes = dict(attrs)
        if tag == "script" and attributes.get("id") == "seo-geo-schema-graph":
            self.capture = True

    def handle_data(self, data):
        if self.capture:
            self.parts.append(data)

    def handle_endtag(self, tag):
        if tag == "script" and self.capture:
            self.capture = False

parser = SchemaParser()
with open(sys.argv[1], "r", encoding="utf-8") as handle:
    parser.feed(handle.read())

base = sys.argv[2].rstrip("/") + "/"
payload = json.loads("".join(parser.parts))
nodes = payload.get("@graph")
assert isinstance(nodes, list) and len(nodes) == 4, f"nodes={nodes!r}"
by_type = {node.get("@type"): node for node in nodes if isinstance(node, dict)}
website = by_type.get("WebSite")
profile = by_type.get("ProfilePage")
person = by_type.get("Person")
assert isinstance(website, dict)
assert isinstance(profile, dict)
assert isinstance(person, dict)
profile_url = base + "author/schema-author/"
profile_id = profile_url + "#webpage"
person_id = profile_url + "#person"
assert profile.get("@id") == profile_id
assert profile.get("url") == profile_url
assert profile.get("mainEntity") == {"@id": person_id}
assert person.get("@id") == person_id
assert person.get("name") == "Schema Author"
assert person.get("url") == profile_url
assert "description" not in person
assert person.get("mainEntityOfPage") == {"@id": profile_id}
assert "publisher" not in website
print(json.dumps({"profile_id": profile_id, "person_id": person_id, "node_count": len(nodes)}))
PY
)"; then
  fail_smoke "schema-profile-contract" "Author ProfilePage/Person graph contract is invalid" "WebSite + ProfilePage + Person with reciprocal mainEntity links" "${AUTHOR_SCHEMA_RESULT:-python assertion failed}" "parse author ProfilePage JSON-LD graph"
fi

if grep -Fq '<meta name="author"' "$AUTHOR_BODY" || grep -Fq 'property="article:author"' "$AUTHOR_BODY"; then
  fail_smoke "provenance-author-archive" "Author archive incorrectly emitted article provenance metadata" "no article author metadata" "article provenance present"
fi

printf '[self-contained] Checking BlogPosting author + publisher identity linkage.\n'
curl -fsS "${BASE_URL}/schema-author-article/" -o "$AUTHOR_ARTICLE_BODY" \
  || fail_smoke "schema-article-request" "Could not request authored BlogPosting fixture" "HTTP 2xx" "curl failed"

if ! ARTICLE_SCHEMA_RESULT="$(python3 - "$AUTHOR_ARTICLE_BODY" "$BASE_URL" <<'PY'
import json
import sys
from html.parser import HTMLParser

class SchemaParser(HTMLParser):
    def __init__(self):
        super().__init__()
        self.capture = False
        self.parts = []

    def handle_starttag(self, tag, attrs):
        attributes = dict(attrs)
        if tag == "script" and attributes.get("id") == "seo-geo-schema-graph":
            self.capture = True

    def handle_data(self, data):
        if self.capture:
            self.parts.append(data)

    def handle_endtag(self, tag):
        if tag == "script" and self.capture:
            self.capture = False

parser = SchemaParser()
with open(sys.argv[1], "r", encoding="utf-8") as handle:
    parser.feed(handle.read())

base = sys.argv[2].rstrip("/") + "/"
payload = json.loads("".join(parser.parts))
nodes = payload.get("@graph")
assert isinstance(nodes, list) and len(nodes) == 6, f"nodes={nodes!r}"
by_type = {node.get("@type"): node for node in nodes if isinstance(node, dict)}
website = by_type.get("WebSite")
webpage = by_type.get("WebPage")
article = by_type.get("BlogPosting")
person = by_type.get("Person")
organization = by_type.get("Organization")
assert isinstance(website, dict)
assert isinstance(webpage, dict)
assert isinstance(article, dict)
assert isinstance(person, dict)
assert isinstance(organization, dict)

canonical = base + "schema-author-article/"
webpage_id = canonical + "#webpage"
article_id = canonical + "#article"
profile_url = base + "author/schema-author/"
profile_id = profile_url + "#webpage"
person_id = profile_url + "#person"
organization_id = base + "#organization"

assert webpage.get("mainEntity") == {"@id": article_id}
assert article.get("@id") == article_id
assert article.get("url") == canonical
assert article.get("headline") == "Schema Author Article"
assert article.get("mainEntityOfPage") == {"@id": webpage_id}
assert article.get("author") == {"@id": person_id}
assert article.get("publisher") == {"@id": organization_id}
assert article.get("inLanguage") == "en-US"
assert "image" not in article

assert person.get("@id") == person_id
assert person.get("name") == "Schema Author"
assert person.get("url") == profile_url
assert "description" not in person
assert person.get("mainEntityOfPage") == {"@id": profile_id}

assert organization.get("@id") == organization_id
assert organization.get("name") == "Self-contained SEO GEO"
assert organization.get("url") == base

print(json.dumps({
    "article_id": article_id,
    "person_id": person_id,
    "organization_id": organization_id,
    "node_count": len(nodes),
}))
PY
)"; then
  fail_smoke "schema-article-contract" "BlogPosting author/publisher graph contract is invalid" "WebSite + WebPage + BreadcrumbList + BlogPosting + Person + Organization with stable references" "${ARTICLE_SCHEMA_RESULT:-python assertion failed}" "parse authored BlogPosting JSON-LD graph"
fi

AUTHOR_PROFILE_URL="${BASE_URL}/author/schema-author/"
grep -Fq '<meta name="author" content="Schema Author" />' "$AUTHOR_ARTICLE_BODY" \
  || fail_smoke "provenance-meta-author" "Article missed standard HTML author metadata" "Schema Author" "meta author absent"
grep -Fq '<link rel="author" href="'"$AUTHOR_PROFILE_URL"'" />' "$AUTHOR_ARTICLE_BODY" \
  || fail_smoke "provenance-rel-author" "Article missed rel=author profile link" "$AUTHOR_PROFILE_URL" "rel author absent"
grep -Fq 'href="'"$AUTHOR_PROFILE_URL"'"' "$AUTHOR_ARTICLE_BODY" \
  || fail_smoke "provenance-visible-author-link" "Visible post byline did not link to the public author archive" "$AUTHOR_PROFILE_URL" "author link absent"

if ! PROVENANCE_RESULT="$(python3 - "$AUTHOR_ARTICLE_BODY" "$AUTHOR_PROFILE_URL" <<'PY'
import json
import re
import sys
from html.parser import HTMLParser

class SchemaParser(HTMLParser):
    def __init__(self):
        super().__init__()
        self.capture = False
        self.parts = []
    def handle_starttag(self, tag, attrs):
        attributes = dict(attrs)
        if tag == "script" and attributes.get("id") == "seo-geo-schema-graph":
            self.capture = True
    def handle_data(self, data):
        if self.capture:
            self.parts.append(data)
    def handle_endtag(self, tag):
        if tag == "script" and self.capture:
            self.capture = False

with open(sys.argv[1], "r", encoding="utf-8") as handle:
    html = handle.read()

parser = SchemaParser()
parser.feed(html)
payload = json.loads("".join(parser.parts))
article = next(node for node in payload["@graph"] if node.get("@type") == "BlogPosting")

def og(name):
    match = re.search(r'<meta property="' + re.escape(name) + r'" content="([^"]+)"\s*/?>', html)
    assert match, name
    return match.group(1)

assert og("article:published_time") == article["datePublished"]
assert og("article:modified_time") == article["dateModified"]
assert og("article:author") == sys.argv[2]
print(json.dumps({
    "published": article["datePublished"],
    "modified": article["dateModified"],
    "author": sys.argv[2],
}))
PY
)"; then
  fail_smoke "provenance-cross-surface" "HTML/Open Graph/Schema provenance diverged" "same author + publication/modification dates" "${PROVENANCE_RESULT:-python assertion failed}" "parse article provenance"
fi

wp_cli eval 'update_option( "seo_geo_markdown_alternates", array( "enabled" => true ), false );' >/dev/null \
  || fail_smoke "provenance-markdown-option" "Could not enable Markdown for provenance fixture" "option update succeeds" "failed"
PROVENANCE_MD_URL="${BASE_URL}/schema-author-article/index.md"
PROVENANCE_MD_STATUS="$(curl -sS -o "$PROVENANCE_MARKDOWN_BODY" -w '%{http_code}' "$PROVENANCE_MD_URL")"
[[ "$PROVENANCE_MD_STATUS" == "200" ]] \
  || fail_smoke "provenance-markdown-http" "Authored article Markdown did not resolve" "HTTP 200" "$PROVENANCE_MD_STATUS"

if ! PROVENANCE_MD_RESULT="$(python3 - "$PROVENANCE_MARKDOWN_BODY" "$AUTHOR_PROFILE_URL" "${BASE_URL}/schema-author-article/" "$PROVENANCE_RESULT" <<'PY'
import json
import sys
with open(sys.argv[1], "r", encoding="utf-8") as handle:
    body = handle.read()
profile, source = sys.argv[2:4]
provenance = json.loads(sys.argv[4])
assert body.startswith("# Schema Author Article\n")
assert f"Source: [{source}]({source})" in body
assert f"Author: [Schema Author]({profile})" in body
assert "Publisher: [Self-contained SEO GEO](" in body
assert f"Published: {provenance['published']}" in body
assert f"Updated: {provenance['modified']}" in body
print("ok")
PY
)"; then
  fail_smoke "provenance-markdown-contract" "Markdown provenance diverged from HTML/Schema" "same author/source/dates/publisher" "${PROVENANCE_MD_RESULT:-python assertion failed}" "parse provenance Markdown"
fi

wp_cli delete option seo_geo_markdown_alternates >/dev/null 2>&1 || true

if ! BREADCRUMBS_JSON="$(wp_cli eval "global \$wp_query; \$wp_query = new WP_Query( array( 'p' => ${POST_ID} ) ); if ( \$wp_query->have_posts() ) { \$wp_query->the_post(); } echo wp_json_encode( \SeoGeo\Core\\Runtime::breadcrumbs()?->resolve() ?? array() );" 2>"$BREADCRUMB_EVAL_ERROR" | tr -d '\r\n')"; then
  ERROR_TEXT="$(tr -d '\r' <"$BREADCRUMB_EVAL_ERROR" | head -c 240)"
  fail_smoke "breadcrumb-eval" "Could not resolve breadcrumb data contract" "root and current post items" "${ERROR_TEXT:-wp eval failed}" "wp eval Runtime::breadcrumbs"
fi

if ! BREADCRUMB_RESULT="$(python3 -c 'import json,sys; items=json.loads(sys.argv[1]); base=sys.argv[2].rstrip("/")+"/"; target=base+"self-contained-seo-fixture/"; assert len(items) >= 2; assert items[0].get("url") == base and items[0].get("current") is False; assert items[-1].get("label") == "Self-contained SEO Fixture"; assert items[-1].get("url") == target and items[-1].get("current") is True; assert sum(1 for item in items if item.get("current") is True) == 1; print("ok")' "$BREADCRUMBS_JSON" "$BASE_URL" 2>&1)"; then
  fail_smoke "breadcrumb-contract" "Breadcrumb data contract is incorrect" "root plus one current post item" "${BREADCRUMB_RESULT}; json=${BREADCRUMBS_JSON}" "Runtime::breadcrumbs()->resolve()"
fi

curl -fsS "${BASE_URL}/?s=self-contained" -o "$SEARCH_BODY" \
  || fail_smoke "search-request" "Could not request search fixture" "HTTP 2xx" "curl failed"
ROBOTS_COUNT="$(grep -Eio '<meta[^>]+name=["'\'']robots["'\''][^>]*>' "$SEARCH_BODY" | wc -l | tr -d ' ')"
[[ "$ROBOTS_COUNT" == "1" ]] \
  || fail_smoke "robots-count" "Search fixture must expose exactly one robots meta element" "1" "$ROBOTS_COUNT"
grep -Eiq 'content=["'\''][^"'\'']*noindex' "$SEARCH_BODY" \
  || fail_smoke "robots-value" "Search fixture must be noindex" "robots contains noindex" "noindex absent"

SEARCH_OG_COUNT="$(grep -Eio '<meta[^>]+property=["'\'']og:[a-z_:.-]+["'\''][^>]*>' "$SEARCH_BODY" | wc -l | tr -d ' ')"
[[ "$SEARCH_OG_COUNT" == "0" ]] \
  || fail_smoke "search-open-graph" "Noindex search fixture must not expose native Open Graph metadata" "0" "$SEARCH_OG_COUNT"

SEARCH_SCHEMA_COUNT="$(grep -Eio '<script[^>]+id=["'\'']seo-geo-schema-graph["'\''][^>]*>' "$SEARCH_BODY" | wc -l | tr -d ' ')"
[[ "$SEARCH_SCHEMA_COUNT" == "0" ]] \
  || fail_smoke "search-schema" "Noindex search fixture must not expose native Schema graph" "0" "$SEARCH_SCHEMA_COUNT"

printf '[self-contained] Checking PHP runtime diagnostics.\n'
docker logs "$WP_CONTAINER" >"$RUNTIME_LOG" 2>&1 || true
docker exec "$WP_CONTAINER" sh -c 'test ! -f /var/www/html/wp-content/debug.log || cat /var/www/html/wp-content/debug.log' >"$DEBUG_LOG" 2>&1 || true

if grep -Eqi 'PHP (Fatal error|Warning|Notice)|Fatal error|Uncaught (Error|Exception)' "$RUNTIME_LOG" "$DEBUG_LOG"; then
  MATCH="$(grep -Eim1 'PHP (Fatal error|Warning|Notice)|Fatal error|Uncaught (Error|Exception)' "$RUNTIME_LOG" "$DEBUG_LOG" | tr -d '\r' | head -c 240)"
  fail_smoke "runtime-php" "PHP runtime emitted diagnostics" "no fatal/warning/notice/uncaught error" "$MATCH"
fi

printf 'Self-contained theme OK: zero plugins; native SEO + Open Graph + Schema BlogPosting identities; breadcrumbs contract; no PHP diagnostics.\n'
