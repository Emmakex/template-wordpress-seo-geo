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
  "${BUILT_THEME}/inc/setup.php" \
  "${BUILT_THEME}/inc/Setup/SetupConfigurationContract.php" \
  "${BUILT_THEME}/inc/Setup/MigrationHandoffReader.php" \
  "${BUILT_THEME}/inc/Setup/SetupCompatibilityDetector.php" \
  "${BUILT_THEME}/inc/Setup/SetupPlanner.php" \
  "${BUILT_THEME}/inc/Setup/PresetLanguageValidator.php" \
  "${BUILT_THEME}/inc/Setup/EntityGeoValidator.php" \
  "${BUILT_THEME}/inc/Setup/SetupOptionWriterInterface.php" \
  "${BUILT_THEME}/inc/Setup/WordPressSetupOptionWriter.php" \
  "${BUILT_THEME}/inc/Setup/SetupReportStore.php" \
  "${BUILT_THEME}/inc/Setup/SetupRewriteMaintenance.php" \
  "${BUILT_THEME}/inc/Setup/SetupWriteFailure.php" \
  "${BUILT_THEME}/inc/Setup/SetupExecutor.php" \
  "${BUILT_THEME}/inc/Wizard/SetupWizardCopy.php" \
  "${BUILT_THEME}/inc/Wizard/SetupWizardPreview.php" \
  "${BUILT_THEME}/inc/Wizard/AdminSetupWizard.php" \
  "${BUILT_THEME}/presets/corporate/preset.json" \
  "${BUILT_THEME}/presets/corporate/content-map.json" \
  "${BUILT_THEME}/presets/corporate/patterns.json" \
  "${BUILT_THEME}/presets/saas-digital-product/preset.json" \
  "${BUILT_THEME}/presets/saas-digital-product/content-map.json" \
  "${BUILT_THEME}/presets/saas-digital-product/patterns.json"; do
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

printf '[self-contained] Checking Phase 9A read-only setup foundation.\n'
SETUP_CLEAN_JSON="$(wp_cli eval 'echo wp_json_encode( seo_geo_theme_setup_plan(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );' 2>/dev/null | tr -d '\r\n')" \
  || fail_smoke "setup-plan-clean" "Could not generate clean-install setup plan" "JSON setup plan" "wp eval failed"

printf '%s' "$SETUP_CLEAN_JSON" >"$TMP_DIR/setup-clean.json"

if ! SETUP_CLEAN_ASSERTION="$(python3 - "$TMP_DIR/setup-clean.json" <<'PY'
import json, sys
with open(sys.argv[1], encoding="utf-8") as f:
    p=json.load(f)
assert p["schema_version"] == 1
assert p["mode"] == "theme-setup-plan-read-only"
assert p["site_mode"] == "clean"
assert p["migration_handoff"]["available"] is False
assert [x["id"] for x in p["available_presets"]] == ["corporate","local-business","publisher","ecommerce","saas-digital-product"]
assert p["compatibility"]["providers"] == {"seo":"native","language":"native"}
assert p["compatibility"]["warnings"] == []
assert p["next_step"] == "choose-preset"
assert p["configuration_contract"]["option_name"] == "seo_geo_theme_setup_v1"
assert p["configuration_contract"]["ownership"]["seo_geo_plugin_required"] is False
assert p["configuration_contract"]["ownership"]["migration_bridge_required"] is False
assert all(v is False for v in p["safety"].values())
print("ok")
PY
)"; then
  fail_smoke "setup-plan-clean-contract" "Clean-install Phase 9A setup plan is invalid" "read-only five-preset native setup plan" "${SETUP_CLEAN_ASSERTION:-python assertion failed}"
fi

wp_cli eval 'add_option( "seo_geo_migration_report_v1", array(
  "schema_version" => 1,
  "id" => "phase-9a-handoff",
  "saved_at" => gmdate( DATE_ATOM ),
  "sha256" => str_repeat( "a", 64 ),
  "report" => array(
    "schema_version" => 1,
    "mode" => "migration-report",
    "ready_for_handoff" => true,
    "report_sha256" => str_repeat( "b", 64 ),
    "manual_review" => array( "blocking" => array(), "advisory" => array( array( "type" => "cleanup" ) ) ),
    "bridge_disposition" => array( "decision" => "retain-audit-only", "runtime_dependency_required" => false ),
    "safety" => array( "report_is_runtime_dependency" => false )
  )
), "", false );' >/dev/null \
  || fail_smoke "setup-handoff-fixture" "Could not create accepted migration handoff fixture" "non-autoloaded handoff option" "failed"

SETUP_STATE_BEFORE="$(wp_cli eval '$state=array(
"setup"=>get_option("seo_geo_theme_setup_v1",null),
"preset"=>get_option("seo_geo_active_preset",null),
"languages"=>get_option("seo_geo_native_languages",null),
"entity"=>get_option("seo_geo_schema_identity",null),
"crawler"=>get_option("seo_geo_crawler_policy",null),
"plugins"=>get_option("active_plugins",array())
); echo hash("sha256",wp_json_encode($state));' 2>/dev/null | tr -d '\r\n')"

SETUP_MIGRATED_JSON="$(wp_cli eval 'echo wp_json_encode( seo_geo_theme_setup_plan(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );' 2>/dev/null | tr -d '\r\n')" \
  || fail_smoke "setup-plan-migrated" "Could not generate migrated-site setup plan" "JSON setup plan" "wp eval failed"
printf '%s' "$SETUP_MIGRATED_JSON" >"$TMP_DIR/setup-migrated.json"

SETUP_STATE_AFTER="$(wp_cli eval '$state=array(
"setup"=>get_option("seo_geo_theme_setup_v1",null),
"preset"=>get_option("seo_geo_active_preset",null),
"languages"=>get_option("seo_geo_native_languages",null),
"entity"=>get_option("seo_geo_schema_identity",null),
"crawler"=>get_option("seo_geo_crawler_policy",null),
"plugins"=>get_option("active_plugins",array())
); echo hash("sha256",wp_json_encode($state));' 2>/dev/null | tr -d '\r\n')"

[[ "$SETUP_STATE_BEFORE" == "$SETUP_STATE_AFTER" ]] \
  || fail_smoke "setup-plan-mutated-state" "Phase 9A setup planning changed protected setup state" "$SETUP_STATE_BEFORE" "$SETUP_STATE_AFTER"

if ! SETUP_MIGRATED_ASSERTION="$(python3 - "$TMP_DIR/setup-migrated.json" <<'PY'
import json, sys
with open(sys.argv[1], encoding="utf-8") as f:
    p=json.load(f)
assert p["site_mode"] == "migrated"
h=p["migration_handoff"]
assert h["available"] is True and h["valid"] is True
assert h["source"] == "migration-bridge-handoff-v1"
assert h["bridge_disposition"] == "retain-audit-only"
assert h["runtime_dependency_required"] is False
assert h["blocking_review_count"] == 0
assert h["advisory_review_count"] == 1
assert p["next_step"] == "choose-preset"
assert p["safety"]["migration_bridge_loaded"] is False
print("ok")
PY
)"; then
  fail_smoke "setup-plan-migrated-contract" "Migrated-site Phase 9A setup plan is invalid" "handoff-aware plan without Migration Bridge runtime" "${SETUP_MIGRATED_ASSERTION:-python assertion failed}"
fi

wp_cli eval 'if ( class_exists( "\\SeoGeo\\MigrationBridge\\Plugin", false ) ) { exit( 1 ); } delete_option( "seo_geo_migration_report_v1" );' >/dev/null \
  || fail_smoke "setup-plan-bridge-independent" "Phase 9A required Migration Bridge runtime code" "Migration Bridge class absent" "class loaded"

printf '[self-contained] Phase 9A setup foundation OK: clean/migrated modes resolved; five presets available; handoff consumed without Migration Bridge runtime; planning mutation-free.\n'

printf '[self-contained] Checking Phase 9B preset and language validation.\n'
PHASE9B_STATE_BEFORE="$(wp_cli eval '$state=array(
"setup"=>get_option("seo_geo_theme_setup_v1",null),
"preset"=>get_option("seo_geo_active_preset",null),
"languages"=>get_option("seo_geo_native_languages",null)
); echo hash("sha256",wp_json_encode($state));' 2>/dev/null | tr -d '\r\n')"

PHASE9B_JSON="$(wp_cli eval '$cases=array(
"valid"=>seo_geo_theme_validate_preset_language_setup(array(
  "preset"=>"corporate",
  "default_language"=>"en",
  "languages"=>array("en"=>"en_US","es"=>"es_ES"),
  "routing"=>"prefix",
  "x_default"=>"en"
)),
"unsupported"=>seo_geo_theme_validate_preset_language_setup(array(
  "preset"=>"unknown-preset",
  "default_language"=>"en",
  "languages"=>array("en"=>"en_US"),
  "routing"=>"disabled"
)),
"duplicate_locale"=>seo_geo_theme_validate_preset_language_setup(array(
  "preset"=>"corporate",
  "default_language"=>"en",
  "languages"=>array("en"=>"en_US","es"=>"en_US"),
  "routing"=>"prefix"
)),
"single_prefix"=>seo_geo_theme_validate_preset_language_setup(array(
  "preset"=>"corporate",
  "default_language"=>"en",
  "languages"=>array("en"=>"en_US"),
  "routing"=>"prefix"
))
); echo wp_json_encode($cases,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);' 2>/dev/null | tr -d '\r\n')" \
  || fail_smoke "phase9b-validation" "Could not evaluate Phase 9B preset/language validation" "JSON validation cases" "wp eval failed"

printf '%s' "$PHASE9B_JSON" >"$TMP_DIR/phase9b-validation.json"

PHASE9B_STATE_AFTER="$(wp_cli eval '$state=array(
"setup"=>get_option("seo_geo_theme_setup_v1",null),
"preset"=>get_option("seo_geo_active_preset",null),
"languages"=>get_option("seo_geo_native_languages",null)
); echo hash("sha256",wp_json_encode($state));' 2>/dev/null | tr -d '\r\n')"

[[ "$PHASE9B_STATE_BEFORE" == "$PHASE9B_STATE_AFTER" ]] \
  || fail_smoke "phase9b-mutated-state" "Phase 9B validation changed protected setup state" "$PHASE9B_STATE_BEFORE" "$PHASE9B_STATE_AFTER"

if ! PHASE9B_ASSERTION="$(python3 - "$TMP_DIR/phase9b-validation.json" <<'PY'
import json, sys
with open(sys.argv[1], encoding="utf-8") as f:
    p=json.load(f)

valid=p["valid"]
assert valid["valid"] is True
assert valid["errors"] == []
assert valid["normalized"]["preset"] == "corporate"
assert valid["normalized"]["languages"] == {
    "default":"en",
    "languages":{"en":"en_US","es":"es_ES"},
    "routing":"prefix",
    "x_default":"en"
}
assert valid["provider_ownership"]["language"] == "native"
assert all(v is False for v in valid["safety"].values())

unsupported=p["unsupported"]
assert unsupported["valid"] is False
assert "unsupported-preset" in unsupported["errors"]

duplicate=p["duplicate_locale"]
assert duplicate["valid"] is False
assert "invalid-language-configuration" in duplicate["errors"]

single=p["single_prefix"]
assert single["valid"] is False
assert "prefix-routing-requires-multiple-languages" in single["errors"]

print("ok")
PY
)"; then
  fail_smoke "phase9b-contract" "Phase 9B validation contract is invalid" "normalized explicit preset/language validation without mutation" "${PHASE9B_ASSERTION:-python assertion failed}"
fi

printf '[self-contained] Phase 9B validation OK: explicit preset + native language configuration normalized; invalid preset/locale/routing rejected; no setup state mutated.\n'

printf '[self-contained] Checking Phase 9C entity and GEO validation.\n'
PHASE9C_STATE_BEFORE="$(wp_cli eval '$state=array(
"setup"=>get_option("seo_geo_theme_setup_v1",null),
"identity"=>get_option("seo_geo_schema_identity",null),
"local_business"=>get_option("seo_geo_schema_local_business",null),
"crawler"=>get_option("seo_geo_crawler_policy",null),
"llms"=>get_option("seo_geo_llms_txt",null),
"markdown"=>get_option("seo_geo_markdown_alternates",null),
"plugins"=>get_option("active_plugins",array())
); echo hash("sha256",wp_json_encode($state));' 2>/dev/null | tr -d '\r\n')"

PHASE9C_JSON="$(wp_cli eval '$cases=array(
"organization"=>seo_geo_theme_validate_entity_geo_setup(array(
  "preset"=>"corporate",
  "site_entity_type"=>"organization",
  "confirm_identity"=>true,
  "crawler_policy"=>array("oai_searchbot"=>"allow","gptbot"=>"disallow"),
  "llms_txt_enabled"=>true,
  "markdown_alternates_enabled"=>true
)),
"local_business"=>seo_geo_theme_validate_entity_geo_setup(array(
  "preset"=>"local-business",
  "site_entity_type"=>"local_business",
  "confirm_identity"=>true,
  "local_business"=>array(
    "type"=>"ProfessionalService",
    "street_address"=>"123 Main Street",
    "address_locality"=>"Barcelona",
    "address_region"=>"Catalonia",
    "postal_code"=>"08001",
    "address_country"=>"ES",
    "telephone"=>"+34 930 000 000",
    "price_range"=>"€€",
    "latitude"=>"41.38740",
    "longitude"=>"2.16860"
  ),
  "crawler_policy"=>array(),
  "llms_txt_enabled"=>false,
  "markdown_alternates_enabled"=>false
)),
"missing_address"=>seo_geo_theme_validate_entity_geo_setup(array(
  "preset"=>"local-business",
  "site_entity_type"=>"local_business",
  "confirm_identity"=>true,
  "local_business"=>array("type"=>"LocalBusiness"),
  "crawler_policy"=>array(),
  "llms_txt_enabled"=>false,
  "markdown_alternates_enabled"=>false
)),
"bad_coordinates"=>seo_geo_theme_validate_entity_geo_setup(array(
  "preset"=>"local-business",
  "site_entity_type"=>"local_business",
  "confirm_identity"=>true,
  "local_business"=>array(
    "type"=>"LocalBusiness",
    "street_address"=>"123 Main Street",
    "address_locality"=>"Barcelona",
    "postal_code"=>"08001",
    "address_country"=>"ES",
    "latitude"=>"41.38",
    "longitude"=>"2.16"
  ),
  "crawler_policy"=>array(),
  "llms_txt_enabled"=>false,
  "markdown_alternates_enabled"=>false
)),
"unconfirmed"=>seo_geo_theme_validate_entity_geo_setup(array(
  "preset"=>"corporate",
  "site_entity_type"=>"organization",
  "confirm_identity"=>false,
  "crawler_policy"=>array(),
  "llms_txt_enabled"=>false,
  "markdown_alternates_enabled"=>false
)),
"bad_crawler"=>seo_geo_theme_validate_entity_geo_setup(array(
  "preset"=>"corporate",
  "site_entity_type"=>"organization",
  "confirm_identity"=>true,
  "crawler_policy"=>array("gptbot"=>"maybe"),
  "llms_txt_enabled"=>false,
  "markdown_alternates_enabled"=>false
)),
"fabricated_claim"=>seo_geo_theme_validate_entity_geo_setup(array(
  "preset"=>"local-business",
  "site_entity_type"=>"local_business",
  "confirm_identity"=>true,
  "local_business"=>array(
    "type"=>"LocalBusiness",
    "street_address"=>"123 Main Street",
    "address_locality"=>"Barcelona",
    "postal_code"=>"08001",
    "address_country"=>"ES",
    "aggregate_rating"=>"5.0"
  ),
  "crawler_policy"=>array(),
  "llms_txt_enabled"=>false,
  "markdown_alternates_enabled"=>false
))
); echo wp_json_encode($cases,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);' 2>/dev/null | tr -d '\r\n')" \
  || fail_smoke "phase9c-validation" "Could not evaluate Phase 9C entity/GEO validation" "JSON validation cases" "wp eval failed"

printf '%s' "$PHASE9C_JSON" >"$TMP_DIR/phase9c-validation.json"

PHASE9C_STATE_AFTER="$(wp_cli eval '$state=array(
"setup"=>get_option("seo_geo_theme_setup_v1",null),
"identity"=>get_option("seo_geo_schema_identity",null),
"local_business"=>get_option("seo_geo_schema_local_business",null),
"crawler"=>get_option("seo_geo_crawler_policy",null),
"llms"=>get_option("seo_geo_llms_txt",null),
"markdown"=>get_option("seo_geo_markdown_alternates",null),
"plugins"=>get_option("active_plugins",array())
); echo hash("sha256",wp_json_encode($state));' 2>/dev/null | tr -d '\r\n')"

[[ "$PHASE9C_STATE_BEFORE" == "$PHASE9C_STATE_AFTER" ]] \
  || fail_smoke "phase9c-mutated-state" "Phase 9C validation changed protected setup/GEO state" "$PHASE9C_STATE_BEFORE" "$PHASE9C_STATE_AFTER"

if ! PHASE9C_ASSERTION="$(python3 - "$TMP_DIR/phase9c-validation.json" <<'PY'
import json, sys
with open(sys.argv[1], encoding="utf-8") as f:
    p=json.load(f)

org=p["organization"]
assert org["valid"] is True
assert org["errors"] == []
assert org["normalized"]["entity"]["site_entity_type"] == "organization"
assert org["normalized"]["entity"]["confirmed"] is True
assert org["normalized"]["entity"]["local_business"] is None
assert org["normalized"]["entity"]["visible_fact_gate_required"] is False
assert org["normalized"]["entity"]["schema_output_ready"] is False
assert org["normalized"]["geo"]["crawler_policy"]["value"] == {
    "oai_searchbot":"allow",
    "gptbot":"disallow",
}
assert org["normalized"]["geo"]["llms_txt"]["enabled"] is True
assert org["normalized"]["geo"]["markdown"]["enabled"] is True
assert org["normalized"]["geo"]["provenance"] == {
    "mode":"native-eligible-content",
    "configurable":False,
}
assert all(v is False for v in org["safety"].values())

lb=p["local_business"]
assert lb["valid"] is True
entity=lb["normalized"]["entity"]
assert entity["site_entity_type"] == "local_business"
assert entity["visible_fact_gate_required"] is True
assert entity["schema_output_ready"] is False
assert entity["local_business"]["type"] == "ProfessionalService"
assert entity["local_business"]["street_address"] == "123 Main Street"
assert entity["local_business"]["address_locality"] == "Barcelona"
assert entity["local_business"]["postal_code"] == "08001"
assert entity["local_business"]["address_country"] == "ES"
assert entity["local_business"]["latitude"] == 41.3874
assert entity["local_business"]["longitude"] == 2.1686

assert p["missing_address"]["valid"] is False
assert "local-business-physical-address-required" in p["missing_address"]["errors"]

assert p["bad_coordinates"]["valid"] is False
assert "local-business-coordinates-invalid" in p["bad_coordinates"]["errors"]

assert p["unconfirmed"]["valid"] is False
assert "identity-confirmation-required" in p["unconfirmed"]["errors"]

assert p["bad_crawler"]["valid"] is False
assert "invalid-crawler-state:gptbot" in p["bad_crawler"]["errors"]

assert p["fabricated_claim"]["valid"] is False
assert "unsupported-local-business-field:aggregate_rating" in p["fabricated_claim"]["errors"]

print("ok")
PY
)"; then
  fail_smoke "phase9c-contract" "Phase 9C validation contract is invalid" "explicit entity/GEO validation without inferred facts or mutation" "${PHASE9C_ASSERTION:-python assertion failed}"
fi

printf '[self-contained] Phase 9C validation OK: explicit entity/GEO choices normalized; visible-fact authority retained; invalid address/coordinates/crawler/claim input rejected; no setup state mutated.\n'

printf '[self-contained] Checking Phase 9D theme-owned wizard UI.\n'
PHASE9D_RUNNER="$TMP_DIR/phase9d-wizard-runner.php"
cat >"$PHASE9D_RUNNER" <<'PHP'
<?php

use SeoGeo\Theme\Wizard\AdminSetupWizard;
use SeoGeo\Theme\Wizard\SetupWizardCopy;

wp_set_current_user( 1 );

$protected_state = static function (): array {
	return array(
		'setup'          => get_option( 'seo_geo_theme_setup_v1', null ),
		'preset'         => get_option( 'seo_geo_active_preset', null ),
		'languages'      => get_option( 'seo_geo_native_languages', null ),
		'identity'       => get_option( 'seo_geo_schema_identity', null ),
		'local_business' => get_option( 'seo_geo_schema_local_business', null ),
		'crawler'        => get_option( 'seo_geo_crawler_policy', null ),
		'llms'           => get_option( 'seo_geo_llms_txt', null ),
		'markdown'       => get_option( 'seo_geo_markdown_alternates', null ),
		'plugins'        => get_option( 'active_plugins', array() ),
	);
};

$fingerprint = static function ( array $state ): string {
	$encoded = wp_json_encode( $state, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
	return hash( 'sha256', false === $encoded ? '' : $encoded );
};

$before = $fingerprint( $protected_state() );

$catalogs = SetupWizardCopy::catalogs();
$en_keys  = array_keys( $catalogs['en'] ?? array() );
$es_keys  = array_keys( $catalogs['es'] ?? array() );
sort( $en_keys );
sort( $es_keys );

do_action( 'admin_menu' );
global $submenu;
$appearance_rows = isset( $submenu['themes.php'] ) && is_array( $submenu['themes.php'] ) ? $submenu['themes.php'] : array();
$registered      = false;

foreach ( $appearance_rows as $row ) {
	if (
		is_array( $row )
		&& isset( $row[1], $row[2] )
		&& 'manage_options' === $row[1]
		&& AdminSetupWizard::PAGE_SLUG === $row[2]
	) {
		$registered = true;
		break;
	}
}

$_SERVER['REQUEST_METHOD'] = 'GET';
$_POST                     = array();
$_REQUEST                  = array();

$en_screen = new AdminSetupWizard( null, null, new SetupWizardCopy( 'en_US' ) );
ob_start();
$en_screen->render_page();
$english_html = (string) ob_get_clean();

$es_screen = new AdminSetupWizard( null, null, new SetupWizardCopy( 'es_ES' ) );
ob_start();
$es_screen->render_page();
$spanish_html = (string) ob_get_clean();

$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = array(
	'_wpnonce'                         => wp_create_nonce( AdminSetupWizard::NONCE_ACTION ),
	'seo_geo_setup_action'             => 'preview',
	'seo_geo_preset'                   => 'corporate',
	'seo_geo_default_language'         => 'en',
	'seo_geo_languages'                => "en=en_US\nes=es_ES",
	'seo_geo_routing'                  => 'prefix',
	'seo_geo_x_default'                => 'en',
	'seo_geo_entity_type'              => 'organization',
	'seo_geo_confirm_identity'         => '1',
	'seo_geo_crawler_oai_searchbot'    => 'allow',
	'seo_geo_crawler_gptbot'           => 'disallow',
	'seo_geo_llms_txt_enabled'         => '1',
	'seo_geo_markdown_alternates_enabled' => '1',
	'seo_geo_preview_confirm'          => '1',
	'seo_geo_validate'                 => '1',
);
$_REQUEST = $_POST;

$preview_screen = new AdminSetupWizard( null, null, new SetupWizardCopy( 'en_US' ) );
ob_start();
$preview_screen->render_page();
$preview_html = (string) ob_get_clean();

do_action( 'admin_enqueue_scripts', 'appearance_page_' . AdminSetupWizard::PAGE_SLUG );

$after = $fingerprint( $protected_state() );

echo wp_json_encode(
	array(
		'registered'   => $registered,
		'catalog_keys' => array(
			'en' => $en_keys,
			'es' => $es_keys,
		),
		'catalog_empty' => array(
			'en' => array_keys( array_filter( $catalogs['en'] ?? array(), static fn( mixed $value ): bool => ! is_string( $value ) || '' === trim( $value ) ) ),
			'es' => array_keys( array_filter( $catalogs['es'] ?? array(), static fn( mixed $value ): bool => ! is_string( $value ) || '' === trim( $value ) ) ),
		),
		'english_html' => $english_html,
		'spanish_html' => $spanish_html,
		'preview_html' => $preview_html,
		'assets'       => array(
			'style'  => wp_style_is( 'seo-geo-setup-wizard', 'enqueued' ),
			'script' => wp_script_is( 'seo-geo-setup-wizard', 'enqueued' ),
		),
		'state_fingerprint' => array(
			'before' => $before,
			'after'  => $after,
		),
	),
	JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
);
PHP

docker cp "$PHASE9D_RUNNER" "$WP_CONTAINER":/var/www/html/wp-content/phase9d-wizard-runner.php \
  || fail_smoke "phase9d-runner-copy" "Could not copy Phase 9D wizard runner" "runner copied" "docker cp failed"

if ! PHASE9D_JSON="$(wp_cli eval-file /var/www/html/wp-content/phase9d-wizard-runner.php 2>"$TMP_DIR/phase9d-wizard.stderr")"; then
  PHASE9D_ERROR="$(tr -d '\r' <"$TMP_DIR/phase9d-wizard.stderr" | head -c 1000)"
  fail_smoke "phase9d-runner" "Phase 9D wizard runner failed" "JSON wizard acceptance" "${PHASE9D_ERROR:-wp eval-file failed}"
fi

printf '%s' "$PHASE9D_JSON" >"$TMP_DIR/phase9d-wizard.json"

if ! PHASE9D_ASSERTION="$(python3 - "$TMP_DIR/phase9d-wizard.json" <<'PY'
import json, sys
with open(sys.argv[1], encoding="utf-8") as f:
    p=json.load(f)

assert p["registered"] is True
assert p["catalog_keys"]["en"] == p["catalog_keys"]["es"]
assert len(p["catalog_keys"]["en"]) >= 45
assert p["catalog_empty"] == {"en": [], "es": []}
assert p["assets"] == {"style": True, "script": True}
assert p["state_fingerprint"]["before"] == p["state_fingerprint"]["after"]

en=p["english_html"]
es=p["spanish_html"]
preview=p["preview_html"]

for required in (
    "<h1>SEO/GEO Setup</h1>",
    "1. Preset and languages",
    "2. Site identity",
    "3. GEO and discovery",
    "4. Review",
    'name="seo_geo_preset"',
    'name="seo_geo_languages"',
    'name="seo_geo_entity_type"',
    'name="seo_geo_preview_confirm"',
):
    assert required in en

for required in (
    "<h1>Configuración SEO/GEO</h1>",
    "1. Preset e idiomas",
    "2. Identidad del sitio",
    "3. GEO y descubrimiento",
    "4. Revisión",
):
    assert required in es

assert "Setup preview is valid." in preview
assert 'id="seo-geo-setup-results"' in preview
assert 'tabindex="-1"' in preview
assert 'aria-live="polite"' in preview
assert "corporate" in preview
assert "en=en_US" in preview
assert "organization" in preview

assert "Apply setup" in en
assert "Aplicar configuración" in es
assert 'name="seo_geo_apply_confirm"' in en
assert 'value="apply"' in en

for html in (en, es, preview):
    lower=html.lower()
    assert 'type="password"' not in lower
    assert 'name="seo_geo_external_credential' not in lower
    assert 'name="seo_geo_api_key' not in lower

print("ok")
PY
)"; then
  fail_smoke "phase9d-contract" "Phase 9D wizard UI contract is invalid" "EN/ES nonce-gated responsive preview-only wizard without state mutation" "${PHASE9D_ASSERTION:-python assertion failed}"
fi

UNAUTHORIZED_STDOUT="$TMP_DIR/phase9d-unauthorized.out"
UNAUTHORIZED_STDERR="$TMP_DIR/phase9d-unauthorized.err"
if wp_cli eval 'wp_set_current_user( 0 ); $screen = new \SeoGeo\Theme\Wizard\AdminSetupWizard( null, null, new \SeoGeo\Theme\Wizard\SetupWizardCopy( "en_US" ) ); $screen->render_page();' >"$UNAUTHORIZED_STDOUT" 2>"$UNAUTHORIZED_STDERR"; then
  fail_smoke "phase9d-capability" "Unauthorized user could render the Phase 9D wizard" "wp_die / non-zero exit" "render succeeded"
fi

UNAUTHORIZED_TEXT="$(cat "$UNAUTHORIZED_STDOUT" "$UNAUTHORIZED_STDERR" | tr -d '\r' | head -c 1000)"
[[ "$UNAUTHORIZED_TEXT" == *"You do not have permission to use the SEO/GEO setup wizard."* ]] \
  || fail_smoke "phase9d-capability-message" "Unauthorized Phase 9D response did not use bounded EN guidance" "permission message" "${UNAUTHORIZED_TEXT:-empty}"

printf '[self-contained] Phase 9D/9E wizard surface OK: Appearance screen registered; EN/ES preview/apply controls are nonce/capability-gated, focus-managed and credential-free.\n'

printf '[self-contained] Checking Phase 9F clean-install onboarding with zero active plugins.\n'
PHASE9F_CLEAN_RUNNER="$TMP_DIR/phase9f-clean-onboarding-runner.php"
cat >"$PHASE9F_CLEAN_RUNNER" <<'PHP'
<?php

wp_set_current_user( 1 );

$reset_options = array(
	'seo_geo_theme_setup_report_v1',
	'seo_geo_theme_setup_v1',
	'seo_geo_theme_setup_rewrite_flush_v1',
	'seo_geo_active_preset',
	'seo_geo_native_languages',
	'seo_geo_schema_identity',
	'seo_geo_schema_local_business',
	'seo_geo_crawler_policy',
	'seo_geo_llms_txt',
	'seo_geo_markdown_alternates',
	'seo_geo_migration_report_v1',
);

foreach ( $reset_options as $option_name ) {
	delete_option( $option_name );
}

$candidate = array(
	'preset'           => 'corporate',
	'default_language' => 'en',
	'languages'        => array(
		'en' => 'en_US',
		'es' => 'es_ES',
	),
	'routing'          => 'prefix',
	'x_default'        => 'en',
	'site_entity_type' => 'organization',
	'confirm_identity' => true,
	'local_business'   => array(),
	'crawler_policy'   => array(
		'oai_searchbot' => 'allow',
		'gptbot'        => 'disallow',
	),
	'llms_txt_enabled'            => true,
	'markdown_alternates_enabled' => true,
);

$plugins_before = get_option( 'active_plugins', array() );
$pages_before   = (int) wp_count_posts( 'page' )->publish;

$first      = seo_geo_theme_apply_setup( $candidate, true );
$report_one = seo_geo_theme_setup_report();
$second     = seo_geo_theme_apply_setup( $candidate, true );
$report_two = seo_geo_theme_setup_report();

$plugins_after = get_option( 'active_plugins', array() );
$pages_after   = (int) wp_count_posts( 'page' )->publish;

echo wp_json_encode(
	array(
		'first'          => $first,
		'second'         => $second,
		'report_one'     => $report_one,
		'report_two'     => $report_two,
		'plugins_before' => $plugins_before,
		'plugins_after'  => $plugins_after,
		'pages_before'   => $pages_before,
		'pages_after'    => $pages_after,
		'options'        => array(
			'preset'    => get_option( 'seo_geo_active_preset', null ),
			'languages' => get_option( 'seo_geo_native_languages', null ),
			'identity'  => get_option( 'seo_geo_schema_identity', null ),
			'setup'     => get_option( 'seo_geo_theme_setup_v1', null ),
		),
	),
	JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
);
PHP

docker cp "$PHASE9F_CLEAN_RUNNER" "$WP_CONTAINER":/var/www/html/wp-content/phase9f-clean-onboarding-runner.php \
  || fail_smoke "phase9f-clean-runner-copy" "Could not copy Phase 9F clean onboarding runner" "runner copied" "docker cp failed"

if ! PHASE9F_CLEAN_JSON="$(wp_cli eval-file /var/www/html/wp-content/phase9f-clean-onboarding-runner.php 2>"$TMP_DIR/phase9f-clean-onboarding.stderr")"; then
  PHASE9F_CLEAN_ERROR="$(tr -d '\r' <"$TMP_DIR/phase9f-clean-onboarding.stderr" | head -c 1200)"
  fail_smoke "phase9f-clean-runner" "Phase 9F clean onboarding runner failed" "JSON clean-install acceptance" "${PHASE9F_CLEAN_ERROR:-wp eval-file failed}"
fi

printf '%s' "$PHASE9F_CLEAN_JSON" >"$TMP_DIR/phase9f-clean-onboarding.json"

if ! PHASE9F_CLEAN_ASSERTION="$(python3 - "$TMP_DIR/phase9f-clean-onboarding.json" <<'PY'
import json, sys

with open(sys.argv[1], encoding="utf-8") as handle:
    payload = json.load(handle)

assert payload["plugins_before"] == []
assert payload["plugins_after"] == []
assert payload["pages_before"] == payload["pages_after"]

first = payload["first"]
second = payload["second"]
assert first["valid"] is True
assert first["applied"] is True
assert first["idempotent"] is False
assert first["rollback_attempted"] is False
assert second["valid"] is True
assert second["applied"] is True
assert second["idempotent"] is True
assert second["changed_options"] == []

report_one = payload["report_one"]
report_two = payload["report_two"]
assert report_one == report_two
assert report_one["mode"] == "theme-setup-report"
assert report_one["site_mode"] == "clean"
assert report_one["preset"] == "corporate"
assert report_one["entity"]["type"] == "organization"
assert report_one["migration_handoff"] is None
assert report_one["compatibility"]["providers"] == {"seo": "native", "language": "native"}
assert all(value is False for value in report_one["safety"].values())

options = payload["options"]
assert options["preset"] == "corporate"
assert options["languages"] == {
    "default": "en",
    "languages": {"en": "en_US", "es": "es_ES"},
    "routing": "prefix",
    "x_default": "en",
}
assert options["identity"] == {"site_entity_type": "organization"}
assert options["setup"]["mode"] == "theme-setup-applied"
assert options["setup"]["configuration_sha256"] == report_one["configuration_sha256"]

print("ok")
PY
)"; then
  fail_smoke "phase9f-clean-contract" "Phase 9F clean onboarding contract is invalid" "clean atomic/idempotent setup with zero active plugins" "${PHASE9F_CLEAN_ASSERTION:-python assertion failed}"
fi

wp_cli eval '
foreach (
	array(
		"seo_geo_theme_setup_report_v1",
		"seo_geo_theme_setup_v1",
		"seo_geo_theme_setup_rewrite_flush_v1",
		"seo_geo_active_preset",
		"seo_geo_native_languages",
		"seo_geo_schema_identity",
		"seo_geo_schema_local_business",
		"seo_geo_crawler_policy",
		"seo_geo_llms_txt",
		"seo_geo_markdown_alternates",
		"seo_geo_migration_report_v1"
	) as $option_name
) {
	delete_option( $option_name );
}
' >/dev/null \
  || fail_smoke "phase9f-clean-reset" "Could not restore clean state after Phase 9F clean-install acceptance" "Phase 9F setup options removed" "cleanup failed"

printf '[self-contained] Phase 9F clean onboarding OK: first apply persisted native authorities, rerun was idempotent, report mode stayed clean, page count stayed stable and active plugins remained empty.\n'

printf '[self-contained] Checking Phase 9E atomic setup execution and migrated report.\n'
PHASE9E_RUNNER="$TMP_DIR/phase9e-execution-runner.php"
cat >"$PHASE9E_RUNNER" <<'PHP'
<?php

use SeoGeo\Core\Geo\CrawlerPolicyResolver;
use SeoGeo\Theme\Setup\SetupExecutor;
use SeoGeo\Theme\Setup\SetupOptionWriterInterface;
use SeoGeo\Theme\Setup\SetupReportStore;
use SeoGeo\Theme\Setup\WordPressSetupOptionWriter;
use SeoGeo\Theme\Wizard\AdminSetupWizard;
use SeoGeo\Theme\Wizard\SetupWizardCopy;

final class Phase9EFailingWriter implements SetupOptionWriterInterface {
	private SetupOptionWriterInterface $delegate;
	private string $fail_option;
	private bool $failed = false;

	public function __construct( SetupOptionWriterInterface $delegate, string $fail_option ) {
		$this->delegate    = $delegate;
		$this->fail_option = $fail_option;
	}

	public function read( string $option_name ): array {
		return $this->delegate->read( $option_name );
	}

	public function write( string $option_name, mixed $value ): bool {
		if ( ! $this->failed && $this->fail_option === $option_name ) {
			$this->failed = true;
			return false;
		}

		return $this->delegate->write( $option_name, $value );
	}

	public function delete( string $option_name ): bool {
		return $this->delegate->delete( $option_name );
	}
}

wp_set_current_user( 1 );

update_option(
	'seo_geo_migration_report_v1',
	array(
		'schema_version' => 1,
		'id'             => 'phase-9e-handoff',
		'saved_at'       => gmdate( DATE_ATOM ),
		'sha256'         => str_repeat( 'a', 64 ),
		'report'         => array(
			'schema_version'    => 1,
			'mode'              => 'migration-report',
			'ready_for_handoff' => true,
			'report_sha256'     => str_repeat( 'b', 64 ),
			'manual_review'     => array(
				'blocking' => array(),
				'advisory' => array( array( 'type' => 'cleanup' ) ),
			),
			'bridge_disposition' => array(
				'decision'                    => 'retain-audit-only',
				'runtime_dependency_required' => false,
			),
			'safety' => array(
				'report_is_runtime_dependency' => false,
			),
		),
	),
	false
);

$candidate = array(
	'preset'           => 'local-business',
	'default_language' => 'en',
	'languages'        => array(
		'en' => 'en_US',
		'es' => 'es_ES',
	),
	'routing'          => 'prefix',
	'x_default'        => 'en',
	'site_entity_type' => 'local_business',
	'confirm_identity' => true,
	'local_business'   => array(
		'type'             => 'ProfessionalService',
		'street_address'   => '123 Main Street',
		'address_locality' => 'Barcelona',
		'address_region'   => 'Catalonia',
		'postal_code'      => '08001',
		'address_country'  => 'ES',
		'telephone'        => '+34 930 000 000',
		'price_range'      => '€€',
		'latitude'         => '41.38740',
		'longitude'        => '2.16860',
	),
	'crawler_policy' => array(
		'oai_searchbot' => 'allow',
		'gptbot'        => 'disallow',
	),
	'llms_txt_enabled'            => true,
	'markdown_alternates_enabled' => true,
);

$state = static function (): array {
	return array(
		'setup'          => get_option( 'seo_geo_theme_setup_v1', null ),
		'report'         => get_option( 'seo_geo_theme_setup_report_v1', null ),
		'preset'         => get_option( 'seo_geo_active_preset', null ),
		'languages'      => get_option( 'seo_geo_native_languages', null ),
		'identity'       => get_option( 'seo_geo_schema_identity', null ),
		'local_business' => get_option( 'seo_geo_schema_local_business', null ),
		'crawler'        => get_option( 'seo_geo_crawler_policy', null ),
		'llms'           => get_option( 'seo_geo_llms_txt', null ),
		'markdown'       => get_option( 'seo_geo_markdown_alternates', null ),
		'handoff'        => get_option( 'seo_geo_migration_report_v1', null ),
		'plugins'        => get_option( 'active_plugins', array() ),
	);
};

$fingerprint = static function ( array $value ): string {
	$encoded = wp_json_encode( $value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
	return hash( 'sha256', false === $encoded ? '' : $encoded );
};

$page_count_before = (int) wp_count_posts( 'page' )->publish;

$unconfirmed_before = $fingerprint( $state() );
$unconfirmed        = seo_geo_theme_apply_setup( $candidate, false );
$unconfirmed_after  = $fingerprint( $state() );

$first       = seo_geo_theme_apply_setup( $candidate, true );
$report_one  = seo_geo_theme_setup_report();
$after_first = $state();
$first_hash  = $fingerprint( $after_first );

$second       = seo_geo_theme_apply_setup( $candidate, true );
$report_two   = seo_geo_theme_setup_report();
$after_second = $state();
$second_hash  = $fingerprint( $after_second );

$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = array(
	'_wpnonce'                            => wp_create_nonce( AdminSetupWizard::NONCE_ACTION ),
	'seo_geo_setup_action'                => 'apply',
	'seo_geo_preset'                      => 'local-business',
	'seo_geo_default_language'            => 'en',
	'seo_geo_languages'                   => "en=en_US\nes=es_ES",
	'seo_geo_routing'                     => 'prefix',
	'seo_geo_x_default'                   => 'en',
	'seo_geo_entity_type'                 => 'local_business',
	'seo_geo_confirm_identity'            => '1',
	'seo_geo_lb_type'                     => 'ProfessionalService',
	'seo_geo_lb_street_address'           => '123 Main Street',
	'seo_geo_lb_address_locality'         => 'Barcelona',
	'seo_geo_lb_address_region'           => 'Catalonia',
	'seo_geo_lb_postal_code'              => '08001',
	'seo_geo_lb_address_country'          => 'ES',
	'seo_geo_lb_telephone'                => '+34 930 000 000',
	'seo_geo_lb_price_range'              => '€€',
	'seo_geo_lb_latitude'                 => '41.38740',
	'seo_geo_lb_longitude'                => '2.16860',
	'seo_geo_crawler_oai_searchbot'       => 'allow',
	'seo_geo_crawler_gptbot'              => 'disallow',
	'seo_geo_llms_txt_enabled'            => '1',
	'seo_geo_markdown_alternates_enabled' => '1',
	'seo_geo_apply_confirm'               => '1',
);
$_REQUEST = $_POST;

$screen = new AdminSetupWizard( null, null, new SetupWizardCopy( 'en_US' ) );
ob_start();
$screen->render_page();
$apply_html = (string) ob_get_clean();

$failure_candidate = $candidate;
$failure_candidate['preset']           = 'corporate';
$failure_candidate['site_entity_type'] = 'organization';
$failure_candidate['local_business']   = array();
$failure_candidate['crawler_policy']   = array( 'gptbot' => 'allow' );
$failure_candidate['llms_txt_enabled'] = false;

$failure_before = $fingerprint( $state() );
$failing_writer = new Phase9EFailingWriter(
	new WordPressSetupOptionWriter(),
	CrawlerPolicyResolver::OPTION_NAME
);
$failure = ( new SetupExecutor( null, null, $failing_writer ) )->execute( $failure_candidate, true );
$failure_after = $fingerprint( $state() );

$alloptions = wp_load_alloptions();
$page_count_after = (int) wp_count_posts( 'page' )->publish;

echo wp_json_encode(
	array(
		'unconfirmed' => array(
			'result' => $unconfirmed,
			'before' => $unconfirmed_before,
			'after'  => $unconfirmed_after,
		),
		'first'        => $first,
		'second'       => $second,
		'report_one'   => $report_one,
		'report_two'   => $report_two,
		'state_hashes' => array(
			'first'  => $first_hash,
			'second' => $second_hash,
		),
		'apply_html'   => $apply_html,
		'failure'      => array(
			'result' => $failure,
			'before' => $failure_before,
			'after'  => $failure_after,
		),
		'options'      => array(
			'preset'         => get_option( 'seo_geo_active_preset', null ),
			'languages'      => get_option( 'seo_geo_native_languages', null ),
			'identity'       => get_option( 'seo_geo_schema_identity', null ),
			'local_business' => get_option( 'seo_geo_schema_local_business', null ),
			'crawler'        => get_option( 'seo_geo_crawler_policy', null ),
			'llms'           => get_option( 'seo_geo_llms_txt', null ),
			'markdown'       => get_option( 'seo_geo_markdown_alternates', null ),
			'setup'          => get_option( 'seo_geo_theme_setup_v1', null ),
		),
		'autoloaded' => array(
			'setup'  => array_key_exists( 'seo_geo_theme_setup_v1', $alloptions ),
			'report' => array_key_exists( SetupReportStore::OPTION_NAME, $alloptions ),
		),
		'page_counts' => array(
			'before' => $page_count_before,
			'after'  => $page_count_after,
		),
		'bridge_loaded' => class_exists( '\\SeoGeo\\MigrationBridge\\Plugin', false ),
	),
	JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
);
PHP

docker cp "$PHASE9E_RUNNER" "$WP_CONTAINER":/var/www/html/wp-content/phase9e-execution-runner.php \
  || fail_smoke "phase9e-runner-copy" "Could not copy Phase 9E execution runner" "runner copied" "docker cp failed"

if ! PHASE9E_JSON="$(wp_cli eval-file /var/www/html/wp-content/phase9e-execution-runner.php 2>"$TMP_DIR/phase9e-execution.stderr")"; then
  PHASE9E_ERROR="$(tr -d '\r' <"$TMP_DIR/phase9e-execution.stderr" | head -c 1200)"
  fail_smoke "phase9e-runner" "Phase 9E execution runner failed" "JSON atomic setup acceptance" "${PHASE9E_ERROR:-wp eval-file failed}"
fi

printf '%s' "$PHASE9E_JSON" >"$TMP_DIR/phase9e-execution.json"

if ! PHASE9E_ASSERTION="$(python3 - "$TMP_DIR/phase9e-execution.json" <<'PY'
import json, sys
with open(sys.argv[1], encoding="utf-8") as f:
    p=json.load(f)

u=p["unconfirmed"]
assert u["result"]["applied"] is False
assert "apply-confirmation-required" in u["result"]["errors"]
assert u["before"] == u["after"]

first=p["first"]
assert first["valid"] is True
assert first["applied"] is True
assert first["idempotent"] is False
assert first["rollback_attempted"] is False
assert len(first["configuration_sha256"]) == 64
expected_changed={
    "seo_geo_active_preset",
    "seo_geo_native_languages",
    "seo_geo_schema_identity",
    "seo_geo_schema_local_business",
    "seo_geo_crawler_policy",
    "seo_geo_llms_txt",
    "seo_geo_markdown_alternates",
    "seo_geo_theme_setup_v1",
}
assert expected_changed.issubset(set(first["changed_options"]))

second=p["second"]
assert second["valid"] is True
assert second["applied"] is True
assert second["idempotent"] is True
assert second["changed_options"] == []
assert p["state_hashes"]["first"] == p["state_hashes"]["second"]

r1=p["report_one"]
r2=p["report_two"]
assert r1 == r2
assert r1["mode"] == "theme-setup-report"
assert r1["site_mode"] == "migrated"
assert r1["preset"] == "local-business"
assert r1["entity"]["type"] == "local_business"
assert r1["entity"]["local_business_configured"] is True
assert r1["migration_handoff"]["source"] == "migration-bridge-handoff-v1"
assert r1["migration_handoff"]["id"] == "phase-9e-handoff"
assert r1["migration_handoff"]["bridge_disposition"] == "retain-audit-only"
assert r1["compatibility"]["providers"] == {"seo":"native","language":"native"}
assert len(r1["configuration_sha256"]) == 64
assert len(r1["report_sha256"]) == 64
assert all(v is False for v in r1["safety"].values())

serialized_report=json.dumps(r1, ensure_ascii=False)
for private_value in ("123 Main Street","+34 930 000 000","41.3874","2.1686"):
    assert private_value not in serialized_report

opts=p["options"]
assert opts["preset"] == "local-business"
assert opts["languages"] == {
    "default":"en",
    "languages":{"en":"en_US","es":"es_ES"},
    "routing":"prefix",
    "x_default":"en",
}
assert opts["identity"] == {"site_entity_type":"local_business"}
assert opts["local_business"]["street_address"] == "123 Main Street"
assert opts["local_business"]["address_locality"] == "Barcelona"
assert opts["crawler"] == {"oai_searchbot":"allow","gptbot":"disallow"}
assert opts["llms"]["enabled"] is True
assert opts["markdown"]["enabled"] is True
assert opts["setup"]["mode"] == "theme-setup-applied"
assert opts["setup"]["configuration_sha256"] == r1["configuration_sha256"]

assert p["autoloaded"] == {"setup":False,"report":False}
assert p["page_counts"]["before"] == p["page_counts"]["after"]
assert p["bridge_loaded"] is False

html=p["apply_html"]
assert "Setup already matches these validated settings." in html
assert r1["configuration_sha256"] in html
assert r1["report_sha256"] in html

failure=p["failure"]
assert failure["before"] == failure["after"]
result=failure["result"]
assert result["valid"] is False
assert result["applied"] is False
assert result["rollback_attempted"] is True
assert "setup-write-failed:seo_geo_crawler_policy" in result["errors"]
assert not any(x.startswith("setup-rollback-failed:") for x in result["errors"])

print("ok")
PY
)"; then
  fail_smoke "phase9e-contract" "Phase 9E atomic setup execution contract is invalid" "validated atomic/idempotent setup with rollback and privacy-bounded report" "${PHASE9E_ASSERTION:-python assertion failed}"
fi

printf '[self-contained] Phase 9E/9F migrated onboarding OK: validated options applied atomically; rerun idempotent; injected mid-write failure rolled back; migrated handoff/report remained bridge-independent, privacy-bounded and zero-plugin.\n'

wp_cli eval '
foreach (
	array(
		"seo_geo_theme_setup_report_v1",
		"seo_geo_theme_setup_v1",
		"seo_geo_active_preset",
		"seo_geo_native_languages",
		"seo_geo_schema_identity",
		"seo_geo_schema_local_business",
		"seo_geo_crawler_policy",
		"seo_geo_llms_txt",
		"seo_geo_markdown_alternates",
		"seo_geo_migration_report_v1"
	) as $option_name
) {
	delete_option( $option_name );
}
' >/dev/null \
  || fail_smoke "phase9e-fixture-reset" "Could not restore clean setup state after Phase 9E acceptance" "Phase 9E options removed" "cleanup failed"

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

# Reuse the same fixture for Phase 7E SaaS / Digital Product activation acceptance.
source scripts/ci/saas-digital-product-preset-acceptance.sh

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
