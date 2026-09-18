#!/usr/bin/env bash
set -u -o pipefail

PIPELINE="${GITHUB_WORKFLOW:-native-multilingual}"
RUN_ID="${GITHUB_RUN_ID:-local}"
RUN_ATTEMPT="${GITHUB_RUN_ATTEMPT:-1}"
JOB="${GITHUB_JOB:-native-translation-relations}"

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
  local command_name="${5:-bash scripts/ci/native-translation-relations-smoke.sh}"
  local sig
  sig="$(signature "${code}:${primary}")"

  cat <<JSON
{
  "schema_version": 1,
  "pipeline": $(json_escape "$PIPELINE"),
  "run_id": $(json_escape "$RUN_ID"),
  "run_attempt": $(json_escape "$RUN_ATTEMPT"),
  "job": $(json_escape "$JOB"),
  "step": "native-translation-relations-smoke",
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

create_post() {
  local status="$1"
  local title="$2"
  local slug="$3"

  wp_cli post create \
    --post_type=post \
    --post_status="$status" \
    --post_title="$title" \
    --post_name="$slug" \
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

printf '[relations] Building self-contained theme.\n'
bash scripts/build-theme-package.sh "$BUILT_THEME" \
  || fail_smoke "theme-build" "Could not assemble self-contained theme" "build succeeds" "build failed"

for embedded in \
  NativeTranslationRelationship.php \
  NativeTranslationRegistry.php; do
  [[ -f "${BUILT_THEME}/inc/seo-geo-core/src/Language/${embedded}" ]] \
    || fail_smoke "embedded-source" "Built theme is missing translation relationship source" "$embedded bundled" "missing"
done

printf '[relations] Starting isolated WordPress.\n'
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
  --title="Native Translation Relations Contract" \
  --admin_user=admin \
  --admin_password=native-relations-admin \
  --admin_email=admin@example.test \
  --skip-email >/dev/null \
  || fail_smoke "core-install" "Could not install WordPress" "core install succeeds" "failed"
wp_cli theme activate seo-geo-theme >/dev/null \
  || fail_smoke "theme-activate" "Could not activate self-contained theme" "theme active" "failed"

ACTIVE_PLUGINS="$(wp_cli plugin list --status=active --field=name 2>/dev/null | tr -d '\r')"
[[ -z "$ACTIVE_PLUGINS" ]] \
  || fail_smoke "zero-plugins" "Translation relationship acceptance must run with zero active plugins" "empty active-plugin list" "$ACTIVE_PLUGINS"

wp_cli eval 'update_option( \SeoGeo\Core\Language\NativeLanguageConfiguration::OPTION_NAME, array( "default" => "es", "routing" => "prefix", "languages" => array( "es" => "es_ES", "en" => "en_US" ) ), false );' >/dev/null \
  || fail_smoke "language-option" "Could not persist native ES/EN configuration" "option update succeeds" "failed"

printf '[relations] Creating valid reciprocal ES/EN group.\n'
ES_ID="$(create_post publish 'Servicios SEO' 'servicios-seo')"
EN_ID="$(create_post publish 'SEO Services' 'seo-services')"
assign_relation_meta "$ES_ID" "services-seo" "es"
assign_relation_meta "$EN_ID" "services-seo" "en"
VALID_MAP="array( 'es' => ${ES_ID}, 'en' => ${EN_ID} )"
assign_translation_map "$ES_ID" "$VALID_MAP"
assign_translation_map "$EN_ID" "$VALID_MAP"

ES_RELATION="$(relation_json "$ES_ID")"
EN_RELATION="$(relation_json "$EN_ID")"

if ! RESULT="$(python3 -c 'import json,sys; s=json.loads(sys.argv[1]); es=int(sys.argv[2]); en=int(sys.argv[3]); assert s["group"]=="services-seo"; assert s["current"]=="es"; assert s["translations"]=={"en":en,"es":es}; print("ok")' "$ES_RELATION" "$ES_ID" "$EN_ID" 2>&1)"; then
  fail_smoke "valid-es-relation" "Spanish resource did not resolve the reciprocal translation group" "group=services-seo,current=es,translations={en,es}" "${RESULT}; json=${ES_RELATION}"
fi

if ! RESULT="$(python3 -c 'import json,sys; s=json.loads(sys.argv[1]); es=int(sys.argv[2]); en=int(sys.argv[3]); assert s["group"]=="services-seo"; assert s["current"]=="en"; assert s["translations"]=={"en":en,"es":es}; print("ok")' "$EN_RELATION" "$ES_ID" "$EN_ID" 2>&1)"; then
  fail_smoke "valid-en-relation" "English resource did not resolve the reciprocal translation group" "group=services-seo,current=en,translations={en,es}" "${RESULT}; json=${EN_RELATION}"
fi

printf '[relations] Checking that reachable content does not imply a translation.\n'
UNRELATED_ID="$(create_post publish 'Unrelated Fixture' 'unrelated-fixture')"
UNRELATED_RELATION="$(relation_json "$UNRELATED_ID")"
[[ "$UNRELATED_RELATION" == "null" ]] \
  || fail_smoke "unrelated-inference" "Content without explicit metadata was inferred as translated" "null relationship" "$UNRELATED_RELATION"

printf '[relations] Checking draft alternate rejection.\n'
DRAFT_ES_ID="$(create_post publish 'Draft Pair ES' 'draft-pair-es')"
DRAFT_EN_ID="$(create_post draft 'Draft Pair EN' 'draft-pair-en')"
assign_relation_meta "$DRAFT_ES_ID" "draft-pair" "es"
assign_relation_meta "$DRAFT_EN_ID" "draft-pair" "en"
DRAFT_MAP="array( 'es' => ${DRAFT_ES_ID}, 'en' => ${DRAFT_EN_ID} )"
assign_translation_map "$DRAFT_ES_ID" "$DRAFT_MAP"
assign_translation_map "$DRAFT_EN_ID" "$DRAFT_MAP"
DRAFT_RELATION="$(relation_json "$DRAFT_ES_ID")"
[[ "$DRAFT_RELATION" == "null" ]] \
  || fail_smoke "draft-alternate" "A group with only one published member became a translation relationship" "null relationship" "$DRAFT_RELATION"

printf '[relations] Checking non-reciprocal map rejection.\n'
NONRECIP_ES_ID="$(create_post publish 'Nonreciprocal ES' 'nonreciprocal-es')"
NONRECIP_EN_ID="$(create_post publish 'Nonreciprocal EN' 'nonreciprocal-en')"
assign_relation_meta "$NONRECIP_ES_ID" "nonreciprocal" "es"
assign_relation_meta "$NONRECIP_EN_ID" "nonreciprocal" "en"
NONRECIP_MAP="array( 'es' => ${NONRECIP_ES_ID}, 'en' => ${NONRECIP_EN_ID} )"
assign_translation_map "$NONRECIP_ES_ID" "$NONRECIP_MAP"
assign_translation_map "$NONRECIP_EN_ID" "array( 'en' => ${NONRECIP_EN_ID} )"
NONRECIP_RELATION="$(relation_json "$NONRECIP_ES_ID")"
[[ "$NONRECIP_RELATION" == "null" ]] \
  || fail_smoke "nonreciprocal-map" "Translation members with different maps were accepted as reciprocal" "null relationship" "$NONRECIP_RELATION"

printf '[relations] Checking duplicate resource rejection.\n'
REUSED_ID="$(create_post publish 'Reused Translation Resource' 'reused-translation-resource')"
assign_relation_meta "$REUSED_ID" "reused-resource" "es"
assign_translation_map "$REUSED_ID" "array( 'es' => ${REUSED_ID}, 'en' => ${REUSED_ID} )"
REUSED_RELATION="$(relation_json "$REUSED_ID")"
[[ "$REUSED_RELATION" == "null" ]] \
  || fail_smoke "duplicate-resource" "One WordPress resource was accepted for multiple translation languages" "null relationship" "$REUSED_RELATION"

printf '[relations] Checking unconfigured language rejection.\n'
INVALID_ES_ID="$(create_post publish 'Invalid Language ES' 'invalid-language-es')"
INVALID_FR_ID="$(create_post publish 'Invalid Language FR' 'invalid-language-fr')"
assign_relation_meta "$INVALID_ES_ID" "invalid-language" "es"
assign_relation_meta "$INVALID_FR_ID" "invalid-language" "fr"
INVALID_MAP="array( 'es' => ${INVALID_ES_ID}, 'fr' => ${INVALID_FR_ID} )"
assign_translation_map "$INVALID_ES_ID" "$INVALID_MAP"
assign_translation_map "$INVALID_FR_ID" "$INVALID_MAP"
INVALID_RELATION="$(relation_json "$INVALID_ES_ID")"
[[ "$INVALID_RELATION" == "null" ]] \
  || fail_smoke "unconfigured-language" "Unconfigured language member was accepted in a translation group" "null relationship" "$INVALID_RELATION"

RUNTIME_LOG="$(docker logs "$WP_CONTAINER" 2>&1 || true)"
DEBUG_LOG="$(docker exec "$WP_CONTAINER" sh -c 'test ! -f /var/www/html/wp-content/debug.log || cat /var/www/html/wp-content/debug.log' 2>&1 || true)"
if printf '%s\n%s' "$RUNTIME_LOG" "$DEBUG_LOG" | grep -Eqi 'PHP (Fatal error|Warning|Notice)|Fatal error|Uncaught (Error|Exception)'; then
  MATCH="$(printf '%s\n%s' "$RUNTIME_LOG" "$DEBUG_LOG" | grep -Eim1 'PHP (Fatal error|Warning|Notice)|Fatal error|Uncaught (Error|Exception)' | tr -d '\r' | head -c 240)"
  fail_smoke "runtime-php" "PHP runtime emitted diagnostics" "no fatal/warning/notice/uncaught error" "$MATCH"
fi

printf 'Native translation relationships OK: zero plugins; explicit reciprocal ES/EN map; invalid relationships rejected.\n'
