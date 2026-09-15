#!/usr/bin/env bash
set -u -o pipefail

PIPELINE="${GITHUB_WORKFLOW:-provider-interoperability}"
RUN_ID="${GITHUB_RUN_ID:-local}"
RUN_ATTEMPT="${GITHUB_RUN_ATTEMPT:-1}"
JOB="${GITHUB_JOB:-provider-interoperability}"
STEP="provider-interoperability"
COMMAND="bash scripts/ci/provider-interoperability.sh"
PROVIDER="${PROVIDER:-}"

WORDPRESS_IMAGE="wordpress:7.1.0-php8.2-apache"
WPCLI_IMAGE="wordpress:cli-2.12.0-php8.2"
MARIADB_IMAGE="mariadb:11.8.9"

SUFFIX="${RUN_ID}-${RUN_ATTEMPT}-${PROVIDER//[^a-zA-Z0-9]/-}-$$"
NETWORK="seo-geo-provider-${SUFFIX}"
DB_CONTAINER="seo-geo-provider-db-${SUFFIX}"
WP_CONTAINER="seo-geo-provider-wp-${SUFFIX}"
WP_VOLUME="seo-geo-provider-wp-${SUFFIX}"

DB_NAME="wordpress"
DB_USER="wordpress"
DB_PASSWORD="wordpress-provider-password"
DB_ROOT_PASSWORD="root-provider-password"

TMP_DIR="$(mktemp -d)"
PAGE_BODY="${TMP_DIR}/provider-page.html"
RUNTIME_LOG="${TMP_DIR}/runtime.log"
DEBUG_LOG="${TMP_DIR}/debug.log"

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

fail_provider() {
  local code="$1"
  local primary="$2"
  local expected="$3"
  local received="$4"
  local command_name="${5:-$COMMAND}"
  local sig
  sig="$(signature "${PROVIDER}:${code}:${primary}")"

  cat <<JSON
{
  "schema_version": 1,
  "pipeline": $(json_escape "$PIPELINE"),
  "run_id": $(json_escape "$RUN_ID"),
  "run_attempt": $(json_escape "$RUN_ATTEMPT"),
  "job": $(json_escape "$JOB"),
  "step": $(json_escape "$STEP"),
  "provider": $(json_escape "$PROVIDER"),
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

read_provider_field() {
  local field="$1"
  python3 - "$PROVIDER" "$field" <<'PY'
import json
import sys

provider, field = sys.argv[1], sys.argv[2]
with open('tests/providers/providers.json', encoding='utf-8') as handle:
    data = json.load(handle)
try:
    value = data['providers'][provider][field]
except KeyError:
    raise SystemExit(2)
print(value)
PY
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

if [[ -z "$PROVIDER" ]]; then
  fail_provider "provider-missing" "PROVIDER environment variable is required" "yoast, rank-math or aioseo" "empty"
fi

case "$PROVIDER" in
  yoast|rank-math|aioseo) ;;
  *) fail_provider "provider-unsupported" "Unsupported provider fixture" "yoast, rank-math or aioseo" "$PROVIDER" ;;
esac

PLUGIN_SLUG="$(read_provider_field slug 2>/dev/null || true)"
PLUGIN_VERSION="$(read_provider_field version 2>/dev/null || true)"
[[ -n "$PLUGIN_SLUG" && -n "$PLUGIN_VERSION" ]] \
  || fail_provider "provider-config" "Provider fixture configuration is incomplete" "slug and version" "slug=${PLUGIN_SLUG:-missing}, version=${PLUGIN_VERSION:-missing}" "read tests/providers/providers.json"

printf '[provider:%s] Creating isolated WordPress fixture for %s %s.\n' "$PROVIDER" "$PLUGIN_SLUG" "$PLUGIN_VERSION"
docker network create "$NETWORK" >/dev/null \
  || fail_provider "network-create" "Could not create isolated Docker network" "network created" "docker network create failed" "docker network create"
docker volume create "$WP_VOLUME" >/dev/null \
  || fail_provider "volume-create" "Could not create WordPress data volume" "volume created" "docker volume create failed" "docker volume create"

docker run -d \
  --name "$DB_CONTAINER" \
  --network "$NETWORK" \
  -e "MARIADB_DATABASE=${DB_NAME}" \
  -e "MARIADB_USER=${DB_USER}" \
  -e "MARIADB_PASSWORD=${DB_PASSWORD}" \
  -e "MARIADB_ROOT_PASSWORD=${DB_ROOT_PASSWORD}" \
  "$MARIADB_IMAGE" >/dev/null \
  || fail_provider "database-start" "MariaDB container did not start" "running database" "docker run failed" "docker run mariadb"

wait_for_db \
  || fail_provider "database-ready" "MariaDB did not become ready" "mariadb-admin ping succeeds" "database readiness timeout" "mariadb-admin ping"

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
  || fail_provider "wordpress-start" "WordPress container did not start" "running WordPress" "docker run failed" "docker run wordpress"

wait_for_wordpress_files \
  || fail_provider "wordpress-files" "WordPress files were not initialized" "wp-settings.php exists" "initialization timeout" "WordPress image initialization"

HOST_PORT="$(docker port "$WP_CONTAINER" 80/tcp | awk -F: 'NR == 1 {print $NF}')"
[[ -n "$HOST_PORT" ]] \
  || fail_provider "wordpress-port" "Could not resolve published WordPress port" "non-empty host port" "empty" "docker port"
BASE_URL="http://127.0.0.1:${HOST_PORT}"

printf '[provider:%s] Installing repository packages and provider fixture.\n' "$PROVIDER"
docker exec "$WP_CONTAINER" mkdir -p \
  /var/www/html/wp-content/plugins/seo-geo-core \
  /var/www/html/wp-content/themes/seo-geo-theme \
  /var/www/html/wp-content/mu-plugins \
  || fail_provider "package-dirs" "Could not create package directories" "plugin/theme/mu-plugin directories" "mkdir failed" "docker exec mkdir"

docker cp packages/seo-geo-core/. "$WP_CONTAINER":/var/www/html/wp-content/plugins/seo-geo-core/ \
  || fail_provider "core-copy" "Could not copy SEO GEO Core" "Core copied" "docker cp failed" "docker cp core"
docker cp packages/seo-geo-theme/. "$WP_CONTAINER":/var/www/html/wp-content/themes/seo-geo-theme/ \
  || fail_provider "theme-copy" "Could not copy SEO GEO theme" "theme copied" "docker cp failed" "docker cp theme"
docker cp tests/providers/provider-output-fixture.php "$WP_CONTAINER":/var/www/html/wp-content/mu-plugins/seo-geo-provider-fixture.php \
  || fail_provider "fixture-copy" "Could not copy provider interoperability fixture" "MU plugin copied" "docker cp failed" "docker cp provider fixture"
docker exec "$WP_CONTAINER" chown -R www-data:www-data \
  /var/www/html/wp-content/plugins/seo-geo-core \
  /var/www/html/wp-content/themes/seo-geo-theme \
  /var/www/html/wp-content/mu-plugins/seo-geo-provider-fixture.php \
  || fail_provider "package-permissions" "Could not set fixture permissions" "www-data owns fixture files" "chown failed" "docker exec chown"

printf '[provider:%s] Installing WordPress and exact provider version.\n' "$PROVIDER"
wp_cli core install \
  --url="$BASE_URL" \
  --title="SEO Provider Interoperability" \
  --admin_user=admin \
  --admin_password=provider-smoke-admin \
  --admin_email=admin@example.test \
  --skip-email >/dev/null \
  || fail_provider "core-install" "WP-CLI could not install WordPress" "core install succeeds" "wp core install failed" "wp core install"

wp_cli rewrite structure '/%postname%/' --hard >/dev/null \
  || fail_provider "rewrite-structure" "Could not configure pretty permalinks" "/%postname%/" "rewrite failed" "wp rewrite structure"

wp_cli plugin activate seo-geo-core >/dev/null \
  || fail_provider "core-activate" "SEO GEO Core could not be activated" "Core active" "activation failed" "wp plugin activate seo-geo-core"
wp_cli theme activate seo-geo-theme >/dev/null \
  || fail_provider "theme-activate" "SEO GEO theme could not be activated" "theme active" "activation failed" "wp theme activate seo-geo-theme"

INSTALL_OUTPUT="${TMP_DIR}/provider-install.txt"
if ! wp_cli plugin install "$PLUGIN_SLUG" --version="$PLUGIN_VERSION" --activate >"$INSTALL_OUTPUT" 2>&1; then
  PRIMARY="$(grep -m1 -v '^[[:space:]]*$' "$INSTALL_OUTPUT" | head -c 300)"
  cat "$INSTALL_OUTPUT"
  fail_provider "provider-install" "${PRIMARY:-Provider plugin install/activation failed}" "${PLUGIN_SLUG} ${PLUGIN_VERSION} active" "install failed" "wp plugin install ${PLUGIN_SLUG} --version=${PLUGIN_VERSION} --activate"
fi
cat "$INSTALL_OUTPUT"

ACTUAL_VERSION="$(wp_cli plugin get "$PLUGIN_SLUG" --field=version 2>/dev/null | tr -d '\r\n')"
[[ "$ACTUAL_VERSION" == "$PLUGIN_VERSION" ]] \
  || fail_provider "provider-version" "Installed provider version does not match pinned fixture" "$PLUGIN_VERSION" "$ACTUAL_VERSION" "wp plugin get ${PLUGIN_SLUG} --field=version"

wp_cli option update seo_geo_provider_fixture "$PROVIDER" >/dev/null \
  || fail_provider "fixture-option" "Could not configure provider fixture option" "$PROVIDER" "option update failed" "wp option update seo_geo_provider_fixture"

DETECTED_PROVIDER="$(wp_cli eval 'echo \SeoGeo\Core\Plugin::integrations()?->seo_provider() ?? "missing";' 2>/dev/null | tr -d '\r\n')"
[[ "$DETECTED_PROVIDER" == "$PROVIDER" ]] \
  || fail_provider "provider-detection" "Runtime integration detector resolved the wrong SEO provider" "$PROVIDER" "$DETECTED_PROVIDER" "wp eval SEO provider"

AUTHORITY_PROVIDER="$(wp_cli eval 'echo \SeoGeo\Core\Plugin::seo_authority()?->provider() ?? "missing";' 2>/dev/null | tr -d '\r\n')"
[[ "$AUTHORITY_PROVIDER" == "$PROVIDER" ]] \
  || fail_provider "provider-authority" "SEO output authority resolved the wrong provider" "$PROVIDER" "$AUTHORITY_PROVIDER" "wp eval SEO authority"

NATIVE_OWNERSHIP="$(wp_cli eval '
$authority = \SeoGeo\Core\Plugin::seo_authority();
$signals = array(
    \SeoGeo\Core\Seo\SeoOutputAuthority::SIGNAL_CANONICAL,
    \SeoGeo\Core\Seo\SeoOutputAuthority::SIGNAL_META_DESCRIPTION,
    \SeoGeo\Core\Seo\SeoOutputAuthority::SIGNAL_ROBOTS,
);
$owned = array();
foreach ( $signals as $signal ) {
    if ( $authority && $authority->native_owns( $signal ) ) {
        $owned[] = $signal;
    }
}
echo empty( $owned ) ? "delegated:3" : "native:" . implode( ",", $owned );
' 2>/dev/null | tr -d '\r\n')"
[[ "$NATIVE_OWNERSHIP" == "delegated:3" ]] \
  || fail_provider "native-ownership" "Native Core retained an overlapping SEO signal while an external provider is authoritative" "delegated:3" "$NATIVE_OWNERSHIP" "wp eval native ownership"

POST_ID="$(wp_cli post create \
  --post_type=post \
  --post_status=publish \
  --post_title='Provider SEO Fixture' \
  --post_name='provider-seo-fixture' \
  --post_excerpt='Native Core description must not be emitted while provider authority is active.' \
  --post_content='Provider interoperability fixture body.' \
  --porcelain 2>/dev/null | tr -d '\r\n')"
[[ "$POST_ID" =~ ^[0-9]+$ ]] \
  || fail_provider "fixture-post" "Could not create provider SEO fixture post" "numeric post ID" "$POST_ID" "wp post create"

printf '[provider:%s] Requesting provider-owned public head output.\n' "$PROVIDER"
curl -fsS "${BASE_URL}/provider-seo-fixture/" -o "$PAGE_BODY" \
  || fail_provider "fixture-request" "Provider fixture request failed" "HTTP 2xx" "curl failure" "curl provider fixture"

CANONICAL_COUNT="$(grep -Eio '<link[^>]+rel=["'\'' ]canonical["'\'' ]?[^>]*>' "$PAGE_BODY" | wc -l | tr -d ' ')"
if [[ "$CANONICAL_COUNT" == "0" ]]; then
  CANONICAL_COUNT="$(grep -Eio 'rel=["'\'']canonical["'\'']' "$PAGE_BODY" | wc -l | tr -d ' ')"
fi
[[ "$CANONICAL_COUNT" == "1" ]] \
  || fail_provider "canonical-count" "Provider fixture must expose exactly one canonical" "1" "$CANONICAL_COUNT" "inspect provider canonical tags"

EXPECTED_CANONICAL="${BASE_URL}/provider-seo-fixture/"
grep -Fq "$EXPECTED_CANONICAL" "$PAGE_BODY" \
  || fail_provider "canonical-value" "Provider canonical did not use the deterministic public filter value" "$EXPECTED_CANONICAL" "expected URL not found" "inspect provider canonical value"

DESCRIPTION_COUNT="$(grep -Eio '<meta[^>]+name=["'\'']description["'\''][^>]*>' "$PAGE_BODY" | wc -l | tr -d ' ')"
[[ "$DESCRIPTION_COUNT" == "1" ]] \
  || fail_provider "description-count" "Provider fixture must expose exactly one meta description" "1" "$DESCRIPTION_COUNT" "inspect provider description tags"

grep -Fq 'SEO GEO provider interoperability description.' "$PAGE_BODY" \
  || fail_provider "description-value" "Provider description did not use the documented public filter" "SEO GEO provider interoperability description." "expected description not found" "inspect provider description value"

ROBOTS_COUNT="$(grep -Eio '<meta[^>]+name=["'\'']robots["'\''][^>]*>' "$PAGE_BODY" | wc -l | tr -d ' ')"
[[ "$ROBOTS_COUNT" == "1" ]] \
  || fail_provider "robots-count" "Provider fixture must expose exactly one robots meta element" "1" "$ROBOTS_COUNT" "inspect provider robots tags"

printf '[provider:%s] Checking runtime diagnostics.\n' "$PROVIDER"
docker logs "$WP_CONTAINER" >"$RUNTIME_LOG" 2>&1 || true
docker exec "$WP_CONTAINER" sh -c 'test ! -f /var/www/html/wp-content/debug.log || cat /var/www/html/wp-content/debug.log' >"$DEBUG_LOG" 2>&1 || true

if grep -Eqi 'PHP (Fatal error|Warning|Notice)|Fatal error|Uncaught (Error|Exception)' "$RUNTIME_LOG" "$DEBUG_LOG"; then
  MATCH="$(grep -Eim1 'PHP (Fatal error|Warning|Notice)|Fatal error|Uncaught (Error|Exception)' "$RUNTIME_LOG" "$DEBUG_LOG" | tr -d '\r' | head -c 240)"
  fail_provider "runtime-php" "PHP runtime emitted a fatal, warning, notice or uncaught error" "no PHP runtime diagnostics" "$MATCH" "inspect provider runtime/debug logs"
fi

printf 'Provider interoperability OK: provider=%s version=%s; authority delegated; canonical=1; description=1; robots=1; no runtime PHP diagnostics.\n' "$PROVIDER" "$PLUGIN_VERSION"
