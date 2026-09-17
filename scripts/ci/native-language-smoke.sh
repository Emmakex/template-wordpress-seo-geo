#!/usr/bin/env bash
set -u -o pipefail

PIPELINE="${GITHUB_WORKFLOW:-native-multilingual}"
RUN_ID="${GITHUB_RUN_ID:-local}"
RUN_ATTEMPT="${GITHUB_RUN_ATTEMPT:-1}"
JOB="${GITHUB_JOB:-native-language}"

WORDPRESS_IMAGE="wordpress:7.1.0-php8.2-apache"
WPCLI_IMAGE="wordpress:cli-2.12.0-php8.2"
MARIADB_IMAGE="mariadb:11.8.9"

SUFFIX="${RUN_ID}-${RUN_ATTEMPT}-$$"
NETWORK="seo-geo-language-${SUFFIX}"
DB_CONTAINER="seo-geo-language-db-${SUFFIX}"
WP_CONTAINER="seo-geo-language-wp-${SUFFIX}"
WP_VOLUME="seo-geo-language-wp-${SUFFIX}"

DB_NAME="wordpress"
DB_USER="wordpress"
DB_PASSWORD="wordpress-language-password"
DB_ROOT_PASSWORD="root-language-password"

TMP_DIR="$(mktemp -d)"
BUILT_THEME="${TMP_DIR}/seo-geo-theme"
EVAL_ERROR="${TMP_DIR}/language-eval.stderr"

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
  local command_name="${5:-bash scripts/ci/native-language-smoke.sh}"
  local sig
  sig="$(signature "${code}:${primary}")"

  cat <<JSON
{
  "schema_version": 1,
  "pipeline": $(json_escape "$PIPELINE"),
  "run_id": $(json_escape "$RUN_ID"),
  "run_attempt": $(json_escape "$RUN_ATTEMPT"),
  "job": $(json_escape "$JOB"),
  "step": "native-language-smoke",
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

language_state() {
  wp_cli eval '$l = \SeoGeo\Core\Runtime::language(); echo wp_json_encode( array( "provider" => $l?->provider_id(), "current_locale" => $l?->current_locale(), "current_code" => $l?->current_language_code(), "default_locale" => $l?->default_locale(), "default_code" => $l?->default_language_code(), "multilingual" => $l?->is_multilingual(), "languages" => $l?->available_languages(), "locale_es" => $l?->locale_for_language( "es" ), "locale_en" => $l?->locale_for_language( "en" ) ) );'
}

printf '[language] Building installable theme with embedded Core.\n'
bash scripts/build-theme-package.sh "$BUILT_THEME" \
  || fail_smoke "theme-build" "Could not assemble self-contained theme" "build succeeds" "build failed" "bash scripts/build-theme-package.sh"

[[ -f "${BUILT_THEME}/inc/seo-geo-core/src/Language/NativeLanguageConfiguration.php" ]] \
  || fail_smoke "embedded-language-config" "Built theme is missing native language configuration" "NativeLanguageConfiguration.php bundled" "missing"

printf '[language] Starting isolated WordPress fixture.\n'
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

printf '[language] Installing only the built theme.\n'
docker exec "$WP_CONTAINER" mkdir -p /var/www/html/wp-content/themes/seo-geo-theme \
  || fail_smoke "theme-dir" "Could not create theme directory" "directory created" "failed"
docker cp "${BUILT_THEME}/." "$WP_CONTAINER":/var/www/html/wp-content/themes/seo-geo-theme/ \
  || fail_smoke "theme-copy" "Could not copy built theme" "theme copied" "failed"
docker exec "$WP_CONTAINER" chown -R www-data:www-data /var/www/html/wp-content/themes/seo-geo-theme \
  || fail_smoke "theme-permissions" "Could not set theme permissions" "www-data owns theme" "failed"

wp_cli core install \
  --url=http://wordpress.test \
  --title="Native Language Contract" \
  --admin_user=admin \
  --admin_password=native-language-admin \
  --admin_email=admin@example.test \
  --skip-email >/dev/null \
  || fail_smoke "core-install" "Could not install WordPress" "core install succeeds" "failed"
wp_cli theme activate seo-geo-theme >/dev/null \
  || fail_smoke "theme-activate" "Could not activate self-contained theme" "theme active" "failed"

ACTIVE_PLUGINS="$(wp_cli plugin list --status=active --field=name 2>/dev/null | tr -d '\r')"
[[ -z "$ACTIVE_PLUGINS" ]] \
  || fail_smoke "zero-plugins" "Native language acceptance must run with zero active plugins" "empty active-plugin list" "$ACTIVE_PLUGINS" "wp plugin list --status=active"

printf '[language] Checking monolingual fallback.\n'
if ! BASELINE_JSON="$(language_state 2>"$EVAL_ERROR" | tr -d '\r\n')"; then
  ERROR_TEXT="$(tr -d '\r' <"$EVAL_ERROR" | head -c 240)"
  fail_smoke "baseline-eval" "Could not resolve baseline language state" "valid language state" "${ERROR_TEXT:-wp eval failed}" "Runtime::language baseline"
fi

if ! BASELINE_RESULT="$(python3 -c 'import json,sys; s=json.loads(sys.argv[1]); assert s["provider"] == "native"; assert s["multilingual"] is False; assert len(s["languages"]) == 1; assert s["default_locale"] == s["current_locale"]; assert s["default_code"] == s["current_code"]; assert s["languages"].get(s["default_code"]) == s["default_locale"]; print("ok")' "$BASELINE_JSON" 2>&1)"; then
  fail_smoke "baseline-contract" "Native fallback language contract is incorrect" "one language derived from WordPress locale" "${BASELINE_RESULT}; json=${BASELINE_JSON}"
fi

BASELINE_CURRENT_LOCALE="$(python3 -c 'import json,sys; print(json.loads(sys.argv[1])["current_locale"])' "$BASELINE_JSON")"
BASELINE_CURRENT_CODE="$(python3 -c 'import json,sys; print(json.loads(sys.argv[1])["current_code"])' "$BASELINE_JSON")"

printf '[language] Checking configured ES/EN contract.\n'
wp_cli eval 'update_option( \SeoGeo\Core\Language\NativeLanguageConfiguration::OPTION_NAME, array( "default" => "es", "languages" => array( "es" => "es_ES", "en" => "en_US" ) ), false );' >/dev/null \
  || fail_smoke "option-write" "Could not persist native ES/EN language configuration" "option update succeeds" "failed"

if ! CONFIGURED_JSON="$(language_state 2>"$EVAL_ERROR" | tr -d '\r\n')"; then
  ERROR_TEXT="$(tr -d '\r' <"$EVAL_ERROR" | head -c 240)"
  fail_smoke "configured-eval" "Could not resolve configured language state" "valid ES/EN language state" "${ERROR_TEXT:-wp eval failed}" "Runtime::language configured"
fi

if ! CONFIGURED_RESULT="$(python3 -c 'import json,sys; s=json.loads(sys.argv[1]); current_locale=sys.argv[2]; current_code=sys.argv[3]; assert s["provider"] == "native"; assert s["multilingual"] is True; assert s["default_code"] == "es"; assert s["default_locale"] == "es_ES"; assert s["languages"] == {"es":"es_ES","en":"en_US"}; assert s["locale_es"] == "es_ES"; assert s["locale_en"] == "en_US"; assert s["current_locale"] == current_locale; assert s["current_code"] == current_code; print("ok")' "$CONFIGURED_JSON" "$BASELINE_CURRENT_LOCALE" "$BASELINE_CURRENT_CODE" 2>&1)"; then
  fail_smoke "configured-contract" "Configured ES/EN language contract is incorrect" "Spanish default plus English alternate without request switching" "${CONFIGURED_RESULT}; json=${CONFIGURED_JSON}"
fi

printf '[language] Checking atomic invalid-configuration fallback.\n'
wp_cli eval 'update_option( \SeoGeo\Core\Language\NativeLanguageConfiguration::OPTION_NAME, array( "default" => "es", "languages" => array( "es" => "es_ES", "en" => "es_ES" ) ), false );' >/dev/null \
  || fail_smoke "invalid-option-write" "Could not persist invalid language fixture" "option update succeeds" "failed"

if ! INVALID_JSON="$(language_state 2>"$EVAL_ERROR" | tr -d '\r\n')"; then
  ERROR_TEXT="$(tr -d '\r' <"$EVAL_ERROR" | head -c 240)"
  fail_smoke "invalid-eval" "Could not resolve invalid-configuration fallback" "safe fallback language state" "${ERROR_TEXT:-wp eval failed}" "Runtime::language invalid fallback"
fi

if ! INVALID_RESULT="$(python3 -c 'import json,sys; s=json.loads(sys.argv[1]); current_locale=sys.argv[2]; current_code=sys.argv[3]; assert s["provider"] == "native"; assert s["multilingual"] is False; assert len(s["languages"]) == 1; assert s["current_locale"] == current_locale; assert s["current_code"] == current_code; assert s["default_locale"] == current_locale; assert s["default_code"] == current_code; print("ok")' "$INVALID_JSON" "$BASELINE_CURRENT_LOCALE" "$BASELINE_CURRENT_CODE" 2>&1)"; then
  fail_smoke "invalid-fallback-contract" "Malformed native language configuration was partially applied" "atomic single-language fallback" "${INVALID_RESULT}; json=${INVALID_JSON}"
fi

RUNTIME_LOG="$(docker logs "$WP_CONTAINER" 2>&1 || true)"
DEBUG_LOG="$(docker exec "$WP_CONTAINER" sh -c 'test ! -f /var/www/html/wp-content/debug.log || cat /var/www/html/wp-content/debug.log' 2>&1 || true)"
if printf '%s\n%s' "$RUNTIME_LOG" "$DEBUG_LOG" | grep -Eqi 'PHP (Fatal error|Warning|Notice)|Fatal error|Uncaught (Error|Exception)'; then
  MATCH="$(printf '%s\n%s' "$RUNTIME_LOG" "$DEBUG_LOG" | grep -Eim1 'PHP (Fatal error|Warning|Notice)|Fatal error|Uncaught (Error|Exception)' | tr -d '\r' | head -c 240)"
  fail_smoke "runtime-php" "PHP runtime emitted diagnostics" "no fatal/warning/notice/uncaught error" "$MATCH"
fi

printf 'Native language contract OK: zero plugins; monolingual fallback; ES/EN configuration; atomic invalid fallback.\n'
