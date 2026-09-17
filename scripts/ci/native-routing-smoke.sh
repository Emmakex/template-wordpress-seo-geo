#!/usr/bin/env bash
set -u -o pipefail

PIPELINE="${GITHUB_WORKFLOW:-native-multilingual}"
RUN_ID="${GITHUB_RUN_ID:-local}"
RUN_ATTEMPT="${GITHUB_RUN_ATTEMPT:-1}"
JOB="${GITHUB_JOB:-native-routing}"

WORDPRESS_IMAGE="wordpress:7.1.0-php8.2-apache"
WPCLI_IMAGE="wordpress:cli-2.12.0-php8.2"
MARIADB_IMAGE="mariadb:11.8.9"
SUFFIX="${RUN_ID}-${RUN_ATTEMPT}-routing-$$"
NETWORK="seo-geo-routing-${SUFFIX}"
DB_CONTAINER="seo-geo-routing-db-${SUFFIX}"
WP_CONTAINER="seo-geo-routing-wp-${SUFFIX}"
WP_VOLUME="seo-geo-routing-wp-${SUFFIX}"
DB_NAME="wordpress"
DB_USER="wordpress"
DB_PASSWORD="wordpress-routing-password"
DB_ROOT_PASSWORD="root-routing-password"

TMP_DIR="$(mktemp -d)"
BUILT_THEME="${TMP_DIR}/seo-geo-theme"
UNPREFIXED_BODY="${TMP_DIR}/unprefixed.html"
ES_ROOT_BODY="${TMP_DIR}/es-root.html"
ES_BODY="${TMP_DIR}/es.html"
EN_BODY="${TMP_DIR}/en.html"
SELECTOR_BODY="${TMP_DIR}/selector.html"
UNKNOWN_BODY="${TMP_DIR}/unknown.html"

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
  local command_name="${5:-bash scripts/ci/native-routing-smoke.sh}"
  local sig
  sig="$(signature "${code}:${primary}")"
  cat <<JSON
{
  "schema_version": 1,
  "pipeline": $(json_escape "$PIPELINE"),
  "run_id": $(json_escape "$RUN_ID"),
  "run_attempt": $(json_escape "$RUN_ATTEMPT"),
  "job": $(json_escape "$JOB"),
  "step": "native-routing-smoke",
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

canonical_count() {
  grep -Eio 'rel=["'"'"']canonical["'"'"']' "$1" | wc -l | tr -d ' '
}

open_graph_count() {
  grep -Eio '<meta[^>]+property=["'"'"']og:[a-z_:.-]+["'"'"'][^>]*>' "$1" | wc -l | tr -d ' '
}

printf '[routing] Building self-contained theme.\n'
bash scripts/build-theme-package.sh "$BUILT_THEME" \
  || fail_smoke "theme-build" "Could not assemble self-contained theme" "build succeeds" "build failed"
[[ -f "${BUILT_THEME}/inc/seo-geo-core/src/Language/NativeLanguageRouter.php" ]] \
  || fail_smoke "embedded-router" "Built theme is missing NativeLanguageRouter" "router source bundled" "missing"

printf '[routing] Starting isolated WordPress.\n'
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
  --title="Native Routing Contract" \
  --admin_user=admin \
  --admin_password=native-routing-admin \
  --admin_email=admin@example.test \
  --skip-email >/dev/null \
  || fail_smoke "core-install" "Could not install WordPress" "core install succeeds" "failed"
wp_cli rewrite structure '/%postname%/' --hard >/dev/null \
  || fail_smoke "rewrite-structure" "Could not configure pretty permalinks" "/%postname%/" "failed"
wp_cli theme activate seo-geo-theme >/dev/null \
  || fail_smoke "theme-activate" "Could not activate self-contained theme" "theme active" "failed"

ACTIVE_PLUGINS="$(wp_cli plugin list --status=active --field=name 2>/dev/null | tr -d '\r')"
[[ -z "$ACTIVE_PLUGINS" ]] \
  || fail_smoke "zero-plugins" "Native routing acceptance must run with zero active plugins" "empty active-plugin list" "$ACTIVE_PLUGINS"

printf '[routing] Installing Spanish WordPress language pack.\n'
wp_cli language core install es_ES >/dev/null \
  || fail_smoke "language-pack" "Could not install Spanish WordPress language pack" "es_ES language pack installed" "failed"

printf '[routing] Enabling ES/EN prefix routing.\n'
wp_cli eval 'update_option( \SeoGeo\Core\Language\NativeLanguageConfiguration::OPTION_NAME, array( "default" => "es", "routing" => "prefix", "languages" => array( "es" => "es_ES", "en" => "en_US" ) ), false );' >/dev/null \
  || fail_smoke "routing-option" "Could not persist native prefix-routing configuration" "option update succeeds" "failed"
wp_cli rewrite flush --hard >/dev/null \
  || fail_smoke "rewrite-flush" "Could not regenerate prefixed rewrite rules" "rewrite flush succeeds" "failed"

ROUTER_STATE="$(wp_cli eval '$r = \SeoGeo\Core\Runtime::language_router(); echo wp_json_encode( array( "enabled" => $r?->enabled(), "active_code" => $r?->active_language_code(), "active_locale" => $r?->active_locale() ) );' 2>/dev/null | tr -d '\r\n')"
if ! ROUTER_RESULT="$(python3 -c 'import json,sys; s=json.loads(sys.argv[1]); assert s["enabled"] is True; assert s["active_code"] is None; assert s["active_locale"] is None; print("ok")' "$ROUTER_STATE" 2>&1)"; then
  fail_smoke "router-state" "Native prefix router did not enable cleanly" "enabled router with no CLI route activation" "${ROUTER_RESULT}; json=${ROUTER_STATE}"
fi

POST_ID="$(wp_cli post create \
  --post_type=post \
  --post_status=publish \
  --post_title='Native Routing Fixture' \
  --post_name='routing-fixture' \
  --post_excerpt='Native routing fixture description.' \
  --post_content='Native multilingual routing fixture body.' \
  --porcelain 2>/dev/null | tr -d '\r\n')"
[[ "$POST_ID" =~ ^[0-9]+$ ]] \
  || fail_smoke "fixture-post" "Could not create routing fixture post" "numeric post ID" "$POST_ID"

printf '[routing] Checking unprefixed authoritative route.\n'
curl -fsS "${BASE_URL}/routing-fixture/" -o "$UNPREFIXED_BODY" \
  || fail_smoke "unprefixed-request" "Could not request unprefixed fixture" "HTTP 2xx" "curl failed"
grep -Eqi '<html[^>]+lang=["'"'"']en-US["'"'"']' "$UNPREFIXED_BODY" \
  || fail_smoke "unprefixed-lang" "Unprefixed route unexpectedly changed locale" "html lang=en-US" "expected attribute absent"
UNPREFIXED_CANONICAL_COUNT="$(canonical_count "$UNPREFIXED_BODY")"
[[ "$UNPREFIXED_CANONICAL_COUNT" == "1" ]] \
  || fail_smoke "unprefixed-canonical" "Unprefixed route must retain one canonical" "1" "$UNPREFIXED_CANONICAL_COUNT"
if grep -Eiq '<meta[^>]+name=["'"'"']robots["'"'"'][^>]+content=["'"'"'][^"'"'"']*noindex' "$UNPREFIXED_BODY"; then
  fail_smoke "unprefixed-indexability" "Unprefixed route must not be forced noindex" "no forced noindex" "noindex present"
fi

printf '[routing] Checking Spanish language root.\n'
curl -fsS "${BASE_URL}/es/" -o "$ES_ROOT_BODY" \
  || fail_smoke "es-root-request" "Could not request Spanish language root" "HTTP 2xx" "curl failed"
grep -Eqi '<html[^>]+lang=["'"'"']es-ES["'"'"']' "$ES_ROOT_BODY" \
  || fail_smoke "es-root-lang" "Spanish language root did not switch locale" "html lang=es-ES" "expected attribute absent"
grep -Eiq '<meta[^>]+name=["'"'"']robots["'"'"'][^>]+content=["'"'"'][^"'"'"']*noindex' "$ES_ROOT_BODY" \
  || fail_smoke "es-root-indexability" "Spanish root must remain noindex during staged routing" "robots contains noindex" "noindex absent"

printf '[routing] Checking localized post routes.\n'
curl -fsS "${BASE_URL}/es/routing-fixture/" -o "$ES_BODY" \
  || fail_smoke "es-route-request" "Could not request Spanish prefixed fixture" "HTTP 2xx without canonical redirect" "curl failed"
curl -fsS "${BASE_URL}/en/routing-fixture/" -o "$EN_BODY" \
  || fail_smoke "en-route-request" "Could not request English prefixed fixture" "HTTP 2xx without canonical redirect" "curl failed"
grep -Eqi '<html[^>]+lang=["'"'"']es-ES["'"'"']' "$ES_BODY" \
  || fail_smoke "es-route-lang" "Spanish prefixed fixture did not switch locale" "html lang=es-ES" "expected attribute absent"
grep -Eqi '<html[^>]+lang=["'"'"']en-US["'"'"']' "$EN_BODY" \
  || fail_smoke "en-route-lang" "English prefixed fixture did not switch locale" "html lang=en-US" "expected attribute absent"

for localized_body in "$ES_BODY" "$EN_BODY"; do
  grep -Eiq '<meta[^>]+name=["'"'"']robots["'"'"'][^>]+content=["'"'"'][^"'"'"']*noindex' "$localized_body" \
    || fail_smoke "localized-indexability" "Localized staged route must be noindex" "robots contains noindex" "noindex absent"
  LOCALIZED_CANONICAL_COUNT="$(canonical_count "$localized_body")"
  [[ "$LOCALIZED_CANONICAL_COUNT" == "0" ]] \
    || fail_smoke "localized-canonical" "Localized staged route must not emit canonical before translation relationships exist" "0" "$LOCALIZED_CANONICAL_COUNT"
  LOCALIZED_OG_COUNT="$(open_graph_count "$localized_body")"
  [[ "$LOCALIZED_OG_COUNT" == "0" ]] \
    || fail_smoke "localized-open-graph" "Localized staged route must not emit native Open Graph before indexability is established" "0" "$LOCALIZED_OG_COUNT"
done

printf '[routing] Checking query selector cannot activate locale by itself.\n'
curl -fsSL "${BASE_URL}/routing-fixture/?seo_geo_lang=es" -o "$SELECTOR_BODY" \
  || fail_smoke "query-selector-request" "Could not request query-selector guard fixture" "unprefixed HTTP 2xx" "curl failed"
grep -Eqi '<html[^>]+lang=["'"'"']en-US["'"'"']' "$SELECTOR_BODY" \
  || fail_smoke "query-selector-lang" "Query selector changed locale without a prefixed rewrite match" "html lang=en-US" "unexpected locale activation"
SELECTOR_CANONICAL_COUNT="$(canonical_count "$SELECTOR_BODY")"
[[ "$SELECTOR_CANONICAL_COUNT" == "1" ]] \
  || fail_smoke "query-selector-canonical" "Query selector must retain unprefixed SEO authority" "1" "$SELECTOR_CANONICAL_COUNT"

printf '[routing] Checking unknown language prefix.\n'
UNKNOWN_STATUS="$(curl -sS -o "$UNKNOWN_BODY" -w '%{http_code}' "${BASE_URL}/fr/routing-fixture/")"
[[ "$UNKNOWN_STATUS" == "404" ]] \
  || fail_smoke "unknown-language-route" "Unconfigured language prefix unexpectedly resolved" "404" "$UNKNOWN_STATUS"

RUNTIME_LOG="$(docker logs "$WP_CONTAINER" 2>&1 || true)"
DEBUG_LOG="$(docker exec "$WP_CONTAINER" sh -c 'test ! -f /var/www/html/wp-content/debug.log || cat /var/www/html/wp-content/debug.log' 2>&1 || true)"
if printf '%s\n%s' "$RUNTIME_LOG" "$DEBUG_LOG" | grep -Eqi 'PHP (Fatal error|Warning|Notice)|Fatal error|Uncaught (Error|Exception)'; then
  MATCH="$(printf '%s\n%s' "$RUNTIME_LOG" "$DEBUG_LOG" | grep -Eim1 'PHP (Fatal error|Warning|Notice)|Fatal error|Uncaught (Error|Exception)' | tr -d '\r' | head -c 240)"
  fail_smoke "runtime-php" "PHP runtime emitted diagnostics" "no fatal/warning/notice/uncaught error" "$MATCH"
fi

printf 'Native routing OK: zero plugins; ES/EN prefixes; locale switching; staged noindex; query selector guarded.\n'
