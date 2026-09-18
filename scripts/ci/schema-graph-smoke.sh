#!/usr/bin/env bash
set -u -o pipefail

PIPELINE="${GITHUB_WORKFLOW:-schema-graph}"
RUN_ID="${GITHUB_RUN_ID:-local}"
RUN_ATTEMPT="${GITHUB_RUN_ATTEMPT:-1}"
JOB="${GITHUB_JOB:-schema-graph}"

WORDPRESS_IMAGE="wordpress:7.1.0-php8.2-apache"
WPCLI_IMAGE="wordpress:cli-2.12.0-php8.2"
MARIADB_IMAGE="mariadb:11.8.9"
SUFFIX="${RUN_ID}-${RUN_ATTEMPT}-schema-$$"
NETWORK="seo-geo-schema-${SUFFIX}"
DB_CONTAINER="seo-geo-schema-db-${SUFFIX}"
WP_CONTAINER="seo-geo-schema-wp-${SUFFIX}"
WP_VOLUME="seo-geo-schema-wp-${SUFFIX}"
DB_NAME="wordpress"
DB_USER="wordpress"
DB_PASSWORD="wordpress-schema-password"
DB_ROOT_PASSWORD="root-schema-password"

TMP_DIR="$(mktemp -d)"
BUILT_THEME="${TMP_DIR}/seo-geo-theme"
DEFAULT_BODY="${TMP_DIR}/default.html"
SEARCH_BODY="${TMP_DIR}/search.html"
ES_BODY="${TMP_DIR}/es.html"
EN_BODY="${TMP_DIR}/en.html"

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
  local command_name="${5:-bash scripts/ci/schema-graph-smoke.sh}"
  local sig
  sig="$(signature "${code}:${primary}")"

  cat <<JSON
{
  "schema_version": 1,
  "pipeline": $(json_escape "$PIPELINE"),
  "run_id": $(json_escape "$RUN_ID"),
  "run_attempt": $(json_escape "$RUN_ATTEMPT"),
  "job": $(json_escape "$JOB"),
  "step": "schema-graph-smoke",
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
  docker run --rm     --network "$NETWORK"     --volumes-from "$WP_CONTAINER"     --user 33:33     -e HOME=/tmp     -e "WORDPRESS_DB_HOST=${DB_CONTAINER}:3306"     -e "WORDPRESS_DB_USER=${DB_USER}"     -e "WORDPRESS_DB_PASSWORD=${DB_PASSWORD}"     -e "WORDPRESS_DB_NAME=${DB_NAME}"     "$WPCLI_IMAGE"     wp "$@" --path=/var/www/html
}

create_page() {
  local title="$1"
  local slug="$2"
  local parent="${3:-0}"

  wp_cli post create     --post_type=page     --post_status=publish     --post_title="$title"     --post_name="$slug"     --post_parent="$parent"     --post_content="$title body."     --porcelain 2>/dev/null | tr -d '\r\n'
}

assign_relation_meta() {
  local post_id="$1"
  local group_id="$2"
  local language="$3"

  wp_cli post meta update "$post_id" _seo_geo_translation_group "$group_id" >/dev/null     || fail_smoke "meta-group" "Could not assign translation group" "meta update succeeds" "failed"
  wp_cli post meta update "$post_id" _seo_geo_language "$language" >/dev/null     || fail_smoke "meta-language" "Could not assign translation language" "meta update succeeds" "failed"
}

assign_translation_map() {
  local post_id="$1"
  local map_php="$2"

  wp_cli eval "update_post_meta( ${post_id}, \\SeoGeo\\Core\\Language\\NativeTranslationRegistry::META_TRANSLATIONS, ${map_php} );" >/dev/null     || fail_smoke "meta-translations" "Could not assign reciprocal translation map" "meta update succeeds" "failed"
}

validate_graph() {
  local html_file="$1"
  local canonical="$2"
  local language="$3"
  local website_id="$4"
  local website_url="$5"
  local breadcrumb_urls_json="$6"

  local result
  if ! result="$(python3 - "$html_file" "$canonical" "$language" "$website_id" "$website_url" "$breadcrumb_urls_json" <<'PY'
import json
import re
import sys
from pathlib import Path

html_path, canonical, language, website_id, website_url, breadcrumb_urls_json = sys.argv[1:]
html = Path(html_path).read_text(encoding="utf-8")
matches = re.findall(
    r'<script\b[^>]*\btype=["\']application/ld\+json["\'][^>]*>(.*?)</script>',
    html,
    flags=re.IGNORECASE | re.DOTALL,
)
assert len(matches) == 1, f"jsonld_count={len(matches)}"
data = json.loads(matches[0])
assert data.get("@context") == "https://schema.org", data.get("@context")
graph = data.get("@graph")
assert isinstance(graph, list), type(graph).__name__

def nodes(kind):
    return [node for node in graph if isinstance(node, dict) and node.get("@type") == kind]

website = nodes("WebSite")
webpage = nodes("WebPage")
breadcrumb = nodes("BreadcrumbList")
assert len(website) == 1, f"website_count={len(website)}"
assert len(webpage) == 1, f"webpage_count={len(webpage)}"
assert len(breadcrumb) == 1, f"breadcrumb_count={len(breadcrumb)}"

website = website[0]
webpage = webpage[0]
breadcrumb = breadcrumb[0]

assert website.get("@id") == website_id, website.get("@id")
assert website.get("url") == website_url, website.get("url")
assert webpage.get("@id") == canonical + "#webpage", webpage.get("@id")
assert webpage.get("url") == canonical, webpage.get("url")
assert webpage.get("inLanguage") == language, webpage.get("inLanguage")
assert webpage.get("isPartOf", {}).get("@id") == website_id, webpage.get("isPartOf")
assert webpage.get("breadcrumb", {}).get("@id") == canonical + "#breadcrumb", webpage.get("breadcrumb")
assert breadcrumb.get("@id") == canonical + "#breadcrumb", breadcrumb.get("@id")

expected_urls = json.loads(breadcrumb_urls_json)
elements = breadcrumb.get("itemListElement")
assert isinstance(elements, list), type(elements).__name__
assert [item.get("position") for item in elements] == list(range(1, len(expected_urls) + 1)), elements
actual_urls = [item.get("item", {}).get("@id") for item in elements]
assert actual_urls == expected_urls, {"expected": expected_urls, "actual": actual_urls}
print("ok")
PY
)"; then
    fail_smoke "schema-contract" "Native Schema graph did not match the authoritative SEO contract" "one valid WebSite/WebPage/BreadcrumbList graph" "$result"
  fi
}

printf '[schema] Building self-contained theme.\n'
bash scripts/build-theme-package.sh "$BUILT_THEME"   || fail_smoke "theme-build" "Could not assemble self-contained theme" "build succeeds" "build failed"
[[ -f "${BUILT_THEME}/inc/seo-geo-core/src/Seo/SchemaGraphBuilder.php" ]]   || fail_smoke "embedded-schema" "Built theme is missing SchemaGraphBuilder" "schema source bundled" "missing"

printf '[schema] Starting isolated WordPress.\n'
docker network create "$NETWORK" >/dev/null   || fail_smoke "network-create" "Could not create Docker network" "network created" "failed"
docker volume create "$WP_VOLUME" >/dev/null   || fail_smoke "volume-create" "Could not create WordPress volume" "volume created" "failed"
docker run -d   --name "$DB_CONTAINER"   --network "$NETWORK"   -e "MARIADB_DATABASE=${DB_NAME}"   -e "MARIADB_USER=${DB_USER}"   -e "MARIADB_PASSWORD=${DB_PASSWORD}"   -e "MARIADB_ROOT_PASSWORD=${DB_ROOT_PASSWORD}"   "$MARIADB_IMAGE" >/dev/null   || fail_smoke "database-start" "MariaDB container did not start" "running database" "failed"
wait_for_db   || fail_smoke "database-ready" "MariaDB did not become ready" "database ready" "timeout"

docker run -d   --name "$WP_CONTAINER"   --network "$NETWORK"   -p 127.0.0.1::80   -v "${WP_VOLUME}:/var/www/html"   -e "WORDPRESS_DB_HOST=${DB_CONTAINER}:3306"   -e "WORDPRESS_DB_USER=${DB_USER}"   -e "WORDPRESS_DB_PASSWORD=${DB_PASSWORD}"   -e "WORDPRESS_DB_NAME=${DB_NAME}"   -e WORDPRESS_DEBUG=1   -e "WORDPRESS_CONFIG_EXTRA=define( 'WP_DEBUG_LOG', true ); define( 'WP_DEBUG_DISPLAY', false ); @ini_set( 'display_errors', '0' );"   "$WORDPRESS_IMAGE" >/dev/null   || fail_smoke "wordpress-start" "WordPress container did not start" "running WordPress" "failed"
wait_for_wordpress_files   || fail_smoke "wordpress-files" "WordPress files were not initialized" "wp-settings.php exists" "timeout"

HOST_PORT="$(docker port "$WP_CONTAINER" 80/tcp | awk -F: 'NR == 1 {print $NF}')"
[[ -n "$HOST_PORT" ]]   || fail_smoke "wordpress-port" "Could not resolve WordPress host port" "non-empty port" "empty"
BASE_URL="http://127.0.0.1:${HOST_PORT}"

docker exec "$WP_CONTAINER" mkdir -p /var/www/html/wp-content/themes/seo-geo-theme   || fail_smoke "theme-dir" "Could not create theme directory" "directory created" "failed"
docker cp "${BUILT_THEME}/." "$WP_CONTAINER":/var/www/html/wp-content/themes/seo-geo-theme/   || fail_smoke "theme-copy" "Could not copy built theme" "theme copied" "failed"
docker exec "$WP_CONTAINER" chown -R www-data:www-data /var/www/html/wp-content/themes/seo-geo-theme   || fail_smoke "theme-permissions" "Could not set theme permissions" "www-data owns theme" "failed"

wp_cli core install   --url="$BASE_URL"   --title="Native Schema Contract"   --admin_user=admin   --admin_password=native-schema-admin   --admin_email=admin@example.test   --skip-email >/dev/null   || fail_smoke "core-install" "Could not install WordPress" "core install succeeds" "failed"
wp_cli rewrite structure '/%postname%/' --hard >/dev/null   || fail_smoke "rewrite-structure" "Could not configure pretty permalinks" "/%postname%/" "failed"
wp_cli theme activate seo-geo-theme >/dev/null   || fail_smoke "theme-activate" "Could not activate self-contained theme" "theme active" "failed"

ACTIVE_PLUGINS="$(wp_cli plugin list --status=active --field=name 2>/dev/null | tr -d '\r')"
[[ -z "$ACTIVE_PLUGINS" ]]   || fail_smoke "zero-plugins" "Schema acceptance must run with zero active plugins" "empty active-plugin list" "$ACTIVE_PLUGINS"

printf '[schema] Checking default hierarchical WebPage graph.\n'
PARENT_ID="$(create_page 'Schema Parent' 'schema-parent')"
CHILD_ID="$(create_page 'Schema Child' 'schema-child' "$PARENT_ID")"
[[ "$PARENT_ID" =~ ^[0-9]+$ && "$CHILD_ID" =~ ^[0-9]+$ ]]   || fail_smoke "default-pages" "Could not create hierarchical Schema fixtures" "numeric page IDs" "parent=${PARENT_ID}; child=${CHILD_ID}"

PARENT_URL="${BASE_URL}/schema-parent/"
CHILD_URL="${BASE_URL}/schema-parent/schema-child/"
curl -fsS "$CHILD_URL" -o "$DEFAULT_BODY"   || fail_smoke "default-request" "Could not request hierarchical Schema fixture" "HTTP 2xx" "curl failed"

DEFAULT_BREADCRUMBS="$(python3 -c 'import json,sys; print(json.dumps(sys.argv[1:]))' "${BASE_URL}/" "$PARENT_URL" "$CHILD_URL")"
validate_graph "$DEFAULT_BODY" "$CHILD_URL" "en-US" "${BASE_URL}/#website" "${BASE_URL}/" "$DEFAULT_BREADCRUMBS"

printf '[schema] Checking noindex request emits no native graph.\n'
curl -fsS "${BASE_URL}/?s=schema" -o "$SEARCH_BODY"   || fail_smoke "search-request" "Could not request search fixture" "HTTP 2xx" "curl failed"
SEARCH_SCHEMA_COUNT="$(grep -Eio '<script[^>]+type=["'"'"']application/ld\+json["'"'"'][^>]*>' "$SEARCH_BODY" | wc -l | tr -d ' ')"
[[ "$SEARCH_SCHEMA_COUNT" == "0" ]]   || fail_smoke "search-schema" "Noindex search emitted native Schema" "0 JSON-LD scripts" "$SEARCH_SCHEMA_COUNT"

printf '[schema] Checking localized ES/EN graph authority.\n'
wp_cli language core install es_ES >/dev/null   || fail_smoke "language-pack" "Could not install Spanish WordPress language pack" "es_ES installed" "failed"
wp_cli eval 'update_option( \SeoGeo\Core\Language\NativeLanguageConfiguration::OPTION_NAME, array( "default" => "es", "routing" => "prefix", "languages" => array( "es" => "es_ES", "en" => "en_US" ) ), false );' >/dev/null   || fail_smoke "language-option" "Could not enable native ES/EN routing" "option update succeeds" "failed"
wp_cli rewrite flush --hard >/dev/null   || fail_smoke "rewrite-flush" "Could not regenerate localized rewrites" "rewrite flush succeeds" "failed"

ES_ID="$(create_page 'Esquema nativo' 'esquema-nativo')"
EN_ID="$(create_page 'Native schema' 'native-schema')"
[[ "$ES_ID" =~ ^[0-9]+$ && "$EN_ID" =~ ^[0-9]+$ ]]   || fail_smoke "localized-pages" "Could not create localized Schema fixtures" "numeric page IDs" "es=${ES_ID}; en=${EN_ID}"

assign_relation_meta "$ES_ID" "schema-localized" "es"
assign_relation_meta "$EN_ID" "schema-localized" "en"
RELATION_MAP="array( 'es' => ${ES_ID}, 'en' => ${EN_ID} )"
assign_translation_map "$ES_ID" "$RELATION_MAP"
assign_translation_map "$EN_ID" "$RELATION_MAP"

ES_URL="${BASE_URL}/es/esquema-nativo/"
EN_URL="${BASE_URL}/en/native-schema/"
curl -fsS "$ES_URL" -o "$ES_BODY"   || fail_smoke "es-request" "Could not request Spanish Schema fixture" "HTTP 2xx" "curl failed"
curl -fsS "$EN_URL" -o "$EN_BODY"   || fail_smoke "en-request" "Could not request English Schema fixture" "HTTP 2xx" "curl failed"

ES_BREADCRUMBS="$(python3 -c 'import json,sys; print(json.dumps(sys.argv[1:]))' "${BASE_URL}/es/" "$ES_URL")"
EN_BREADCRUMBS="$(python3 -c 'import json,sys; print(json.dumps(sys.argv[1:]))' "${BASE_URL}/en/" "$EN_URL")"
validate_graph "$ES_BODY" "$ES_URL" "es-ES" "${BASE_URL}/#website" "${BASE_URL}/" "$ES_BREADCRUMBS"
validate_graph "$EN_BODY" "$EN_URL" "en-US" "${BASE_URL}/#website" "${BASE_URL}/" "$EN_BREADCRUMBS"

RUNTIME_LOG="$(docker logs "$WP_CONTAINER" 2>&1 || true)"
DEBUG_LOG="$(docker exec "$WP_CONTAINER" sh -c 'test ! -f /var/www/html/wp-content/debug.log || cat /var/www/html/wp-content/debug.log' 2>&1 || true)"
if printf '%s\n%s' "$RUNTIME_LOG" "$DEBUG_LOG" | grep -Eqi 'PHP (Fatal error|Warning|Notice)|Fatal error|Uncaught (Error|Exception)'; then
  MATCH="$(printf '%s\n%s' "$RUNTIME_LOG" "$DEBUG_LOG" | grep -Eim1 'PHP (Fatal error|Warning|Notice)|Fatal error|Uncaught (Error|Exception)' | tr -d '\r' | head -c 240)"
  fail_smoke "runtime-php" "PHP runtime emitted diagnostics" "no fatal/warning/notice/uncaught error" "$MATCH"
fi

printf 'Native Schema graph OK: zero plugins; WebSite/WebPage/BreadcrumbList; noindex suppression; localized inLanguage and URLs.\n'
