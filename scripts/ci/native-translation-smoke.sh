#!/usr/bin/env bash
set -u -o pipefail

PIPELINE="${GITHUB_WORKFLOW:-native-multilingual}"
RUN_ID="${GITHUB_RUN_ID:-local}"
RUN_ATTEMPT="${GITHUB_RUN_ATTEMPT:-1}"
JOB="${GITHUB_JOB:-native-translations}"

WORDPRESS_IMAGE="wordpress:7.1.0-php8.2-apache"
WPCLI_IMAGE="wordpress:cli-2.12.0-php8.2"
MARIADB_IMAGE="mariadb:11.8.9"
SUFFIX="${RUN_ID}-${RUN_ATTEMPT}-translations-$$"
NETWORK="seo-geo-translations-${SUFFIX}"
DB_CONTAINER="seo-geo-translations-db-${SUFFIX}"
WP_CONTAINER="seo-geo-translations-wp-${SUFFIX}"
WP_VOLUME="seo-geo-translations-wp-${SUFFIX}"
DB_NAME="wordpress"
DB_USER="wordpress"
DB_PASSWORD="wordpress-translations-password"
DB_ROOT_PASSWORD="root-translations-password"

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
  local command_name="${5:-bash scripts/ci/native-translation-smoke.sh}"
  local sig
  sig="$(signature "${code}:${primary}")"

  cat <<JSON
{
  "schema_version": 1,
  "pipeline": $(json_escape "$PIPELINE"),
  "run_id": $(json_escape "$RUN_ID"),
  "run_attempt": $(json_escape "$RUN_ATTEMPT"),
  "job": $(json_escape "$JOB"),
  "step": "native-translation-smoke",
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

resolve_relationship() {
  local post_id="$1"
  wp_cli eval '$resolver = \SeoGeo\Core\Runtime::translations(); $relationship = $resolver?->resolve( '"$post_id"' ); echo wp_json_encode( null === $relationship ? null : array( "current_post_id" => $relationship->current_post_id(), "current_language_code" => $relationship->current_language_code(), "members" => $relationship->members() ) );' 2>/dev/null | tr -d '\r\n'
}

printf '[translations] Building self-contained theme.\n'
bash scripts/build-theme-package.sh "$BUILT_THEME" \
  || fail_smoke "theme-build" "Could not assemble self-contained theme" "build succeeds" "build failed"

[[ -f "${BUILT_THEME}/inc/seo-geo-core/src/Language/NativeTranslationRelationshipResolver.php" ]] \
  || fail_smoke "embedded-resolver" "Built theme is missing native translation resolver" "resolver source bundled" "missing"

printf '[translations] Starting isolated WordPress.\n'
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
  --title="Native Translation Contract" \
  --admin_user=admin \
  --admin_password=native-translation-admin \
  --admin_email=admin@example.test \
  --skip-email >/dev/null \
  || fail_smoke "core-install" "Could not install WordPress" "core install succeeds" "failed"

wp_cli theme activate seo-geo-theme >/dev/null \
  || fail_smoke "theme-activate" "Could not activate self-contained theme" "theme active" "failed"

ACTIVE_PLUGINS="$(wp_cli plugin list --status=active --field=name 2>/dev/null | tr -d '\r')"
[[ -z "$ACTIVE_PLUGINS" ]] \
  || fail_smoke "zero-plugins" "Native translation acceptance must run with zero active plugins" "empty active-plugin list" "$ACTIVE_PLUGINS"

printf '[translations] Configuring native ES/EN language authority.\n'
wp_cli eval 'update_option( \SeoGeo\Core\Language\NativeLanguageConfiguration::OPTION_NAME, array( "default" => "es", "routing" => "prefix", "languages" => array( "es" => "es_ES", "en" => "en_US" ) ), false );' >/dev/null \
  || fail_smoke "language-config" "Could not persist native ES/EN configuration" "option update succeeds" "failed"

ES_ID="$(wp_cli post create --post_type=post --post_status=publish --post_title='Relación ES' --post_name='relacion-es' --porcelain 2>/dev/null | tr -d '\r\n')"
EN_ID="$(wp_cli post create --post_type=post --post_status=publish --post_title='English Relationship' --post_name='english-relationship' --porcelain 2>/dev/null | tr -d '\r\n')"

[[ "$ES_ID" =~ ^[0-9]+$ && "$EN_ID" =~ ^[0-9]+$ ]] \
  || fail_smoke "fixture-posts" "Could not create reciprocal translation fixtures" "two numeric post IDs" "es=${ES_ID}; en=${EN_ID}"

printf '[translations] Persisting explicit reciprocal relationship.\n'
wp_cli eval 'update_post_meta( '"$ES_ID"', \SeoGeo\Core\Language\NativeTranslationRelationshipResolver::LANGUAGE_META_KEY, "es" ); update_post_meta( '"$EN_ID"', \SeoGeo\Core\Language\NativeTranslationRelationshipResolver::LANGUAGE_META_KEY, "en" ); $set = array( "es" => '"$ES_ID"', "en" => '"$EN_ID"' ); update_post_meta( '"$ES_ID"', \SeoGeo\Core\Language\NativeTranslationRelationshipResolver::TRANSLATION_SET_META_KEY, $set ); update_post_meta( '"$EN_ID"', \SeoGeo\Core\Language\NativeTranslationRelationshipResolver::TRANSLATION_SET_META_KEY, $set );' >/dev/null \
  || fail_smoke "relationship-meta" "Could not persist reciprocal translation relationship" "meta update succeeds" "failed"

ES_RELATIONSHIP="$(resolve_relationship "$ES_ID")"
EN_RELATIONSHIP="$(resolve_relationship "$EN_ID")"

if ! VALID_RESULT="$(python3 -c 'import json,sys; es=int(sys.argv[2]); en=int(sys.argv[3]); a=json.loads(sys.argv[1]); assert a["current_post_id"]==es; assert a["current_language_code"]=="es"; assert a["members"]=={"en":en,"es":es}; print("ok")' "$ES_RELATIONSHIP" "$ES_ID" "$EN_ID" 2>&1)"; then
  fail_smoke "valid-es" "Valid ES relationship did not resolve" "reciprocal ES/EN relationship" "${VALID_RESULT}; json=${ES_RELATIONSHIP}"
fi

if ! VALID_RESULT="$(python3 -c 'import json,sys; es=int(sys.argv[2]); en=int(sys.argv[3]); a=json.loads(sys.argv[1]); assert a["current_post_id"]==en; assert a["current_language_code"]=="en"; assert a["members"]=={"en":en,"es":es}; print("ok")' "$EN_RELATIONSHIP" "$ES_ID" "$EN_ID" 2>&1)"; then
  fail_smoke "valid-en" "Valid EN relationship did not resolve" "reciprocal ES/EN relationship" "${VALID_RESULT}; json=${EN_RELATIONSHIP}"
fi

printf '[translations] Checking non-reciprocal relationship fails closed.\n'
wp_cli eval 'update_post_meta( '"$EN_ID"', \SeoGeo\Core\Language\NativeTranslationRelationshipResolver::TRANSLATION_SET_META_KEY, array( "en" => '"$EN_ID"', "es" => '"$EN_ID"' ) );' >/dev/null \
  || fail_smoke "break-reciprocity" "Could not mutate reciprocity fixture" "meta update succeeds" "failed"

BROKEN_RECIPROCITY="$(resolve_relationship "$ES_ID")"
[[ "$BROKEN_RECIPROCITY" == "null" ]] \
  || fail_smoke "non-reciprocal" "Non-reciprocal translation set unexpectedly resolved" "null" "$BROKEN_RECIPROCITY"

wp_cli eval '$set = array( "es" => '"$ES_ID"', "en" => '"$EN_ID"' ); update_post_meta( '"$EN_ID"', \SeoGeo\Core\Language\NativeTranslationRelationshipResolver::TRANSLATION_SET_META_KEY, $set );' >/dev/null \
  || fail_smoke "restore-reciprocity" "Could not restore reciprocal fixture" "meta update succeeds" "failed"

printf '[translations] Checking draft translation fails closed.\n'
wp_cli post update "$EN_ID" --post_status=draft >/dev/null \
  || fail_smoke "draft-target" "Could not make EN translation draft" "post update succeeds" "failed"

DRAFT_RESULT="$(resolve_relationship "$ES_ID")"
[[ "$DRAFT_RESULT" == "null" ]] \
  || fail_smoke "draft-rejection" "Relationship containing a draft translation unexpectedly resolved" "null" "$DRAFT_RESULT"

wp_cli post update "$EN_ID" --post_status=publish >/dev/null \
  || fail_smoke "restore-publish" "Could not republish EN translation" "post update succeeds" "failed"

printf '[translations] Checking language metadata mismatch fails closed.\n'
wp_cli eval 'update_post_meta( '"$EN_ID"', \SeoGeo\Core\Language\NativeTranslationRelationshipResolver::LANGUAGE_META_KEY, "es" );' >/dev/null \
  || fail_smoke "language-mismatch" "Could not mutate EN language metadata" "meta update succeeds" "failed"

MISMATCH_RESULT="$(resolve_relationship "$ES_ID")"
[[ "$MISMATCH_RESULT" == "null" ]] \
  || fail_smoke "language-mismatch-rejection" "Language-mismatched translation unexpectedly resolved" "null" "$MISMATCH_RESULT"

wp_cli eval 'update_post_meta( '"$EN_ID"', \SeoGeo\Core\Language\NativeTranslationRelationshipResolver::LANGUAGE_META_KEY, "en" );' >/dev/null \
  || fail_smoke "restore-language" "Could not restore EN language metadata" "meta update succeeds" "failed"

FINAL_RESULT="$(resolve_relationship "$ES_ID")"
[[ "$FINAL_RESULT" != "null" ]] \
  || fail_smoke "final-validity" "Restored reciprocal relationship did not resolve" "validated relationship" "null"

RUNTIME_LOG="$(docker logs "$WP_CONTAINER" 2>&1 || true)"
DEBUG_LOG="$(docker exec "$WP_CONTAINER" sh -c 'test ! -f /var/www/html/wp-content/debug.log || cat /var/www/html/wp-content/debug.log' 2>&1 || true)"
if printf '%s\n%s' "$RUNTIME_LOG" "$DEBUG_LOG" | grep -Eqi 'PHP (Fatal error|Warning|Notice)|Fatal error|Uncaught (Error|Exception)'; then
  MATCH="$(printf '%s\n%s' "$RUNTIME_LOG" "$DEBUG_LOG" | grep -Eim1 'PHP (Fatal error|Warning|Notice)|Fatal error|Uncaught (Error|Exception)' | tr -d '\r' | head -c 240)"
  fail_smoke "runtime-php" "PHP runtime emitted diagnostics" "no fatal/warning/notice/uncaught error" "$MATCH"
fi

printf 'Native translation relationships OK: explicit reciprocal ES/EN set; non-reciprocal, draft and language-mismatched members fail closed.\n'
