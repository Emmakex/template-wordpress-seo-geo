#!/usr/bin/env bash
set -u -o pipefail

PIPELINE="${GITHUB_WORKFLOW:-native-multilingual}"
RUN_ID="${GITHUB_RUN_ID:-local}"
RUN_ATTEMPT="${GITHUB_RUN_ATTEMPT:-1}"
JOB="${GITHUB_JOB:-native-localized-seo}"

WORDPRESS_IMAGE="wordpress:7.1.0-php8.2-apache"
WPCLI_IMAGE="wordpress:cli-2.12.0-php8.2"
MARIADB_IMAGE="mariadb:11.8.9"
SUFFIX="${RUN_ID}-${RUN_ATTEMPT}-relations-$$"
NETWORK="seo-geo-relations-${SUFFIX}"
DB_CONTAINER="seo-geo-relations-db-${SUFFIX}"
WP_CONTAINER="seo-geo-relations-wp-${SUFFIX}"
WP_VOLUME="seo-geo-relations-wp-${SUFFIX}"
DB_NAME="wordpress"
DB_USER="wordpress"
DB_PASSWORD="wordpress-relations-password"
DB_ROOT_PASSWORD="root-relations-password"

TMP_DIR="$(mktemp -d)"
BUILT_THEME="${TMP_DIR}/seo-geo-theme"

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
  local command_name="${5:-bash scripts/ci/native-localized-seo-smoke.sh}"
  local sig
  sig="$(signature "${code}:${primary}")"

  cat <<JSON
{
  "schema_version": 1,
  "pipeline": $(json_escape "$PIPELINE"),
  "run_id": $(json_escape "$RUN_ID"),
  "run_attempt": $(json_escape "$RUN_ATTEMPT"),
  "job": $(json_escape "$JOB"),
  "step": "native-localized-seo-smoke",
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

create_page() {
  local status="$1"
  local title="$2"
  local slug="$3"
  local parent="${4:-0}"

  wp_cli post create \
    --post_type=page \
    --post_status="$status" \
    --post_title="$title" \
    --post_name="$slug" \
    --post_parent="$parent" \
    --post_excerpt="$title description." \
    --post_content="$title body." \
    --porcelain 2>/dev/null | tr -d '\r\n'
}

assign_relation_meta() {
  local post_id="$1"
  local group_id="$2"
  local language="$3"

  wp_cli post meta update "$post_id" _seo_geo_translation_group "$group_id" >/dev/null \
    || fail_smoke "meta-group" "Could not assign translation group" "meta update succeeds" "failed"
  wp_cli post meta update "$post_id" _seo_geo_language "$language" >/dev/null \
    || fail_smoke "meta-language" "Could not assign translation language" "meta update succeeds" "failed"
}

assign_translation_map() {
  local post_id="$1"
  local map_php="$2"

  wp_cli eval "update_post_meta( ${post_id}, \\SeoGeo\\Core\\Language\\NativeTranslationRegistry::META_TRANSLATIONS, ${map_php} );" >/dev/null \
    || fail_smoke "meta-translations" "Could not assign reciprocal translation map" "meta update succeeds" "failed"
}

relation_json() {
  local post_id="$1"
  wp_cli eval "\$registry = \\SeoGeo\\Core\\Runtime::translations(); \$relationship = \$registry?->for_post( ${post_id} ); echo wp_json_encode( null === \$relationship ? null : array( 'group' => \$relationship->group_id(), 'current' => \$relationship->current_language_code(), 'translations' => \$relationship->translations() ) );" 2>/dev/null | tr -d '\r\n'
}

printf '[localized-seo] Building self-contained theme.\n'
bash scripts/build-theme-package.sh "$BUILT_THEME" \
  || fail_smoke "theme-build" "Could not assemble self-contained theme" "build succeeds" "build failed"

for embedded in \
  NativeTranslationRelationship.php \
  NativeTranslationRegistry.php; do
  [[ -f "${BUILT_THEME}/inc/seo-geo-core/src/Language/${embedded}" ]] \
    || fail_smoke "embedded-source" "Built theme is missing translation relationship source" "$embedded bundled" "missing"
done

printf '[localized-seo] Starting isolated WordPress.\n'
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

docker exec "$WP_CONTAINER" mkdir -p /var/www/html/wp-content/themes/seo-geo-theme \
  || fail_smoke "theme-dir" "Could not create theme directory" "directory created" "failed"
docker cp "${BUILT_THEME}/." "$WP_CONTAINER":/var/www/html/wp-content/themes/seo-geo-theme/ \
  || fail_smoke "theme-copy" "Could not copy built theme" "theme copied" "failed"
docker exec "$WP_CONTAINER" chown -R www-data:www-data /var/www/html/wp-content/themes/seo-geo-theme \
  || fail_smoke "theme-permissions" "Could not set theme permissions" "www-data owns theme" "failed"

wp_cli core install \
  --url="$BASE_URL" \
  --title="Native Localized SEO Contract" \
  --admin_user=admin \
  --admin_password=native-relations-admin \
  --admin_email=admin@example.test \
  --skip-email >/dev/null \
  || fail_smoke "core-install" "Could not install WordPress" "core install succeeds" "failed"
wp_cli rewrite structure '/%postname%/' --hard >/dev/null \
  || fail_smoke "rewrite-structure" "Could not configure pretty permalinks" "/%postname%/" "failed"
wp_cli theme activate seo-geo-theme >/dev/null \
  || fail_smoke "theme-activate" "Could not activate self-contained theme" "theme active" "failed"

ACTIVE_PLUGINS="$(wp_cli plugin list --status=active --field=name 2>/dev/null | tr -d '\r')"
[[ -z "$ACTIVE_PLUGINS" ]] \
  || fail_smoke "zero-plugins" "Localized SEO acceptance must run with zero active plugins" "empty active-plugin list" "$ACTIVE_PLUGINS"

printf '[localized-seo] Installing Spanish WordPress language pack.\n'
wp_cli language core install es_ES >/dev/null \
  || fail_smoke "language-pack" "Could not install Spanish WordPress language pack" "es_ES language pack installed" "failed"

printf '[localized-seo] Enabling ES/EN prefix routing with explicit x-default.\n'
wp_cli eval 'update_option( \SeoGeo\Core\Language\NativeLanguageConfiguration::OPTION_NAME, array( "default" => "es", "routing" => "prefix", "x_default" => "es", "languages" => array( "es" => "es_ES", "en" => "en_US" ) ), false );' >/dev/null \
  || fail_smoke "language-option" "Could not persist localized SEO language configuration" "option update succeeds" "failed"
wp_cli rewrite flush --hard >/dev/null \
  || fail_smoke "rewrite-flush" "Could not regenerate localized rewrite rules" "rewrite flush succeeds" "failed"

printf '[localized-seo] Creating reciprocal ES/EN parent and child relationships with distinct slugs.\n'
ES_PARENT_ID="$(create_page publish 'Servicios' 'servicios')"
EN_PARENT_ID="$(create_page publish 'Services' 'services')"
ES_CHILD_ID="$(create_page publish 'SEO técnico' 'seo-tecnico' "$ES_PARENT_ID")"
EN_CHILD_ID="$(create_page publish 'Technical SEO' 'technical-seo' "$EN_PARENT_ID")"

assign_relation_meta "$ES_PARENT_ID" "services-root" "es"
assign_relation_meta "$EN_PARENT_ID" "services-root" "en"
PARENT_MAP="array( 'es' => ${ES_PARENT_ID}, 'en' => ${EN_PARENT_ID} )"
assign_translation_map "$ES_PARENT_ID" "$PARENT_MAP"
assign_translation_map "$EN_PARENT_ID" "$PARENT_MAP"

assign_relation_meta "$ES_CHILD_ID" "technical-seo" "es"
assign_relation_meta "$EN_CHILD_ID" "technical-seo" "en"
CHILD_MAP="array( 'es' => ${ES_CHILD_ID}, 'en' => ${EN_CHILD_ID} )"
assign_translation_map "$ES_CHILD_ID" "$CHILD_MAP"
assign_translation_map "$EN_CHILD_ID" "$CHILD_MAP"

ES_URL="${BASE_URL}/es/servicios/seo-tecnico/"
EN_URL="${BASE_URL}/en/services/technical-seo/"
ES_UNPREFIXED="${BASE_URL}/servicios/seo-tecnico/"
WRONG_PREFIX_URL="${BASE_URL}/en/servicios/seo-tecnico/"

ES_BODY="${TMP_DIR}/localized-es.html"
EN_BODY="${TMP_DIR}/localized-en.html"
UNPREFIXED_BODY="${TMP_DIR}/localized-unprefixed.html"
WRONG_BODY="${TMP_DIR}/localized-wrong-prefix.html"
UNRELATED_BODY="${TMP_DIR}/localized-unrelated.html"
INVALID_BODY="${TMP_DIR}/localized-invalid.html"

canonical_count() {
  grep -Eio 'rel=["'"'"']canonical["'"'"']' "$1" | wc -l | tr -d ' '
}

hreflang_count() {
  grep -Eio '<link[^>]+rel=["'"'"']alternate["'"'"'][^>]+hreflang=["'"'"'][^"'"'"']+["'"'"'][^>]*>' "$1" | wc -l | tr -d ' '
}

open_graph_count() {
  grep -Eio '<meta[^>]+property=["'"'"']og:[a-z_:.-]+["'"'"'][^>]*>' "$1" | wc -l | tr -d ' '
}

schema_count() {
  grep -Eio '<script[^>]+id=["'"'"']seo-geo-schema-graph["'"'"'][^>]*>' "$1" | wc -l | tr -d ' '
}

assert_http_200() {
  local url="$1"
  local output="$2"
  local code="$3"
  local status
  status="$(curl -sS -o "$output" -w '%{http_code}' "$url")"
  [[ "$status" == "200" ]] \
    || fail_smoke "$code" "Localized SEO fixture did not resolve" "HTTP 200" "$status" "curl ${url}"
}

printf '[localized-seo] Checking authoritative Spanish localized SEO.\n'
assert_http_200 "$ES_URL" "$ES_BODY" "es-http"
grep -Fq '<link rel="canonical" href="'"$ES_URL"'" />' "$ES_BODY" \
  || fail_smoke "es-canonical" "Spanish localized canonical is incorrect" "$ES_URL" "expected canonical absent"
[[ "$(canonical_count "$ES_BODY")" == "1" ]] \
  || fail_smoke "es-canonical-count" "Spanish localized page must emit exactly one canonical" "1" "$(canonical_count "$ES_BODY")"
for expected in \
  '<link rel="alternate" hreflang="en" href="'"$EN_URL"'" />' \
  '<link rel="alternate" hreflang="es" href="'"$ES_URL"'" />' \
  '<link rel="alternate" hreflang="x-default" href="'"$ES_URL"'" />'; do
  grep -Fq "$expected" "$ES_BODY" \
    || fail_smoke "es-hreflang" "Spanish localized page is missing a reciprocal hreflang" "$expected" "expected link absent"
done
[[ "$(hreflang_count "$ES_BODY")" == "3" ]] \
  || fail_smoke "es-hreflang-count" "Spanish localized page must emit exactly three alternates" "3" "$(hreflang_count "$ES_BODY")"
grep -Fq '<meta property="og:url" content="'"$ES_URL"'" />' "$ES_BODY" \
  || fail_smoke "es-og-url" "Spanish Open Graph URL is not the localized canonical" "$ES_URL" "expected og:url absent"
grep -Fq '<meta property="og:locale" content="es_ES" />' "$ES_BODY" \
  || fail_smoke "es-og-locale" "Spanish Open Graph locale is incorrect" "es_ES" "expected og:locale absent"
if grep -Eiq '<meta[^>]+name=["'"'"']robots["'"'"'][^>]+content=["'"'"'][^"'"'"']*noindex' "$ES_BODY"; then
  fail_smoke "es-indexability" "Valid Spanish translation remained noindex" "indexable localized route" "noindex present"
fi

printf '[localized-seo] Checking authoritative English localized SEO.\n'
assert_http_200 "$EN_URL" "$EN_BODY" "en-http"
grep -Fq '<link rel="canonical" href="'"$EN_URL"'" />' "$EN_BODY" \
  || fail_smoke "en-canonical" "English localized canonical is incorrect" "$EN_URL" "expected canonical absent"
for expected in \
  '<link rel="alternate" hreflang="en" href="'"$EN_URL"'" />' \
  '<link rel="alternate" hreflang="es" href="'"$ES_URL"'" />' \
  '<link rel="alternate" hreflang="x-default" href="'"$ES_URL"'" />'; do
  grep -Fq "$expected" "$EN_BODY" \
    || fail_smoke "en-hreflang" "English localized page is missing a reciprocal hreflang" "$expected" "expected link absent"
done
grep -Fq '<meta property="og:url" content="'"$EN_URL"'" />' "$EN_BODY" \
  || fail_smoke "en-og-url" "English Open Graph URL is not the localized canonical" "$EN_URL" "expected og:url absent"
grep -Fq '<meta property="og:locale" content="en_US" />' "$EN_BODY" \
  || fail_smoke "en-og-locale" "English Open Graph locale is incorrect" "en_US" "expected og:locale absent"
if grep -Eiq '<meta[^>]+name=["'"'"']robots["'"'"'][^>]+content=["'"'"'][^"'"'"']*noindex' "$EN_BODY"; then
  fail_smoke "en-indexability" "Valid English translation remained noindex" "indexable localized route" "noindex present"
fi

printf '[localized-seo] Checking unprefixed and wrong-prefix copies stay non-indexable.\n'
assert_http_200 "$ES_UNPREFIXED" "$UNPREFIXED_BODY" "unprefixed-http"
assert_http_200 "$WRONG_PREFIX_URL" "$WRONG_BODY" "wrong-prefix-http"
for staged_body in "$UNPREFIXED_BODY" "$WRONG_BODY"; do
  grep -Eiq '<meta[^>]+name=["'"'"']robots["'"'"'][^>]+content=["'"'"'][^"'"'"']*noindex' "$staged_body" \
    || fail_smoke "staged-noindex" "Non-authoritative translation route became indexable" "robots contains noindex" "noindex absent"
  [[ "$(canonical_count "$staged_body")" == "0" ]] \
    || fail_smoke "staged-canonical" "Non-authoritative translation route emitted canonical" "0" "$(canonical_count "$staged_body")"
  [[ "$(hreflang_count "$staged_body")" == "0" ]] \
    || fail_smoke "staged-hreflang" "Non-authoritative translation route emitted hreflang" "0" "$(hreflang_count "$staged_body")"
  [[ "$(open_graph_count "$staged_body")" == "0" ]] \
    || fail_smoke "staged-og" "Non-authoritative translation route emitted Open Graph" "0" "$(open_graph_count "$staged_body")"
done

printf '[localized-seo] Checking missing and invalid relationships stay staged.\n'
UNRELATED_ID="$(create_page publish 'Unrelated localized page' 'unrelated-localized')"
UNRELATED_URL="${BASE_URL}/es/unrelated-localized/"
assert_http_200 "$UNRELATED_URL" "$UNRELATED_BODY" "unrelated-http"
grep -Eiq '<meta[^>]+name=["'"'"']robots["'"'"'][^>]+content=["'"'"'][^"'"'"']*noindex' "$UNRELATED_BODY" \
  || fail_smoke "unrelated-noindex" "Localized page without relationship became indexable" "robots contains noindex" "noindex absent"

INVALID_ES_ID="$(create_page publish 'Invalid pair ES' 'invalid-pair-es')"
INVALID_EN_ID="$(create_page draft 'Invalid pair EN' 'invalid-pair-en')"
assign_relation_meta "$INVALID_ES_ID" "invalid-pair" "es"
assign_relation_meta "$INVALID_EN_ID" "invalid-pair" "en"
INVALID_MAP="array( 'es' => ${INVALID_ES_ID}, 'en' => ${INVALID_EN_ID} )"
assign_translation_map "$INVALID_ES_ID" "$INVALID_MAP"
assign_translation_map "$INVALID_EN_ID" "$INVALID_MAP"
INVALID_URL="${BASE_URL}/es/invalid-pair-es/"
assert_http_200 "$INVALID_URL" "$INVALID_BODY" "invalid-http"
grep -Eiq '<meta[^>]+name=["'"'"']robots["'"'"'][^>]+content=["'"'"'][^"'"'"']*noindex' "$INVALID_BODY" \
  || fail_smoke "invalid-noindex" "Localized page with invalid relationship became indexable" "robots contains noindex" "noindex absent"

printf '[localized-seo] Checking safe translation URL helper and localized breadcrumbs.\n'
HELPER_JSON="$(wp_cli eval "echo wp_json_encode( array( 'valid_en' => \\SeoGeo\\Core\\Runtime::localized_seo()?->url_for_translation( ${ES_CHILD_ID}, 'en' ), 'unrelated_es' => \\SeoGeo\\Core\\Runtime::localized_seo()?->url_for_translation( ${UNRELATED_ID}, 'es' ) ) );" 2>/dev/null | tr -d '\r\n')"
if ! HELPER_RESULT="$(python3 -c 'import json,sys; s=json.loads(sys.argv[1]); assert s["valid_en"]==sys.argv[2]; assert s["unrelated_es"] is None; print("ok")' "$HELPER_JSON" "$EN_URL" 2>&1)"; then
  fail_smoke "translation-helper" "Safe localized URL helper violated relationship authority" "valid EN URL plus null unrelated URL" "${HELPER_RESULT}; json=${HELPER_JSON}"
fi

BREADCRUMB_JSON="$(wp_cli eval "\$router = \\SeoGeo\\Core\\Runtime::language_router(); \$wp = new \\WP(); \$wp->query_vars = array( \\SeoGeo\\Core\\Language\\NativeLanguageRouter::QUERY_VAR => 'es' ); \$wp->matched_rule = '^es/(.+?)/?$'; \$router?->activate_request_locale( \$wp ); global \$wp_query; \$wp_query = new \\WP_Query( array( 'page_id' => ${ES_CHILD_ID} ) ); \$wp_query->the_post(); echo wp_json_encode( \\SeoGeo\\Core\\Runtime::breadcrumbs()?->resolve() );" 2>/dev/null | tr -d '\r\n')"
ES_PARENT_URL="${BASE_URL}/es/servicios/"
if ! BREADCRUMB_RESULT="$(python3 -c 'import json,sys; s=json.loads(sys.argv[1]); urls=[x["url"] for x in s]; assert urls==[sys.argv[2],sys.argv[3],sys.argv[4]]; assert s[-1]["current"] is True; print("ok")' "$BREADCRUMB_JSON" "${BASE_URL}/es/" "$ES_PARENT_URL" "$ES_URL" 2>&1)"; then
  fail_smoke "localized-breadcrumbs" "Breadcrumb URLs crossed or lost the active translation language" "ES root, ES parent, ES current URLs" "${BREADCRUMB_RESULT}; json=${BREADCRUMB_JSON}"
fi

RUNTIME_LOG="$(docker logs "$WP_CONTAINER" 2>&1 || true)"
DEBUG_LOG="$(docker exec "$WP_CONTAINER" sh -c 'test ! -f /var/www/html/wp-content/debug.log || cat /var/www/html/wp-content/debug.log' 2>&1 || true)"
if printf '%s\n%s' "$RUNTIME_LOG" "$DEBUG_LOG" | grep -Eqi 'PHP (Fatal error|Warning|Notice)|Fatal error|Uncaught (Error|Exception)'; then
  MATCH="$(printf '%s\n%s' "$RUNTIME_LOG" "$DEBUG_LOG" | grep -Eim1 'PHP (Fatal error|Warning|Notice)|Fatal error|Uncaught (Error|Exception)' | tr -d '\r' | head -c 240)"
  fail_smoke "runtime-php" "PHP runtime emitted diagnostics" "no fatal/warning/notice/uncaught error" "$MATCH"
fi

printf 'Native localized SEO OK: zero plugins; reciprocal ES/EN canonical + hreflang; explicit x-default; OG alignment; guarded breadcrumbs and negative cases.\n'
