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
SEARCH_BODY="${TMP_DIR}/search.html"
RUNTIME_LOG="${TMP_DIR}/runtime.log"
DEBUG_LOG="${TMP_DIR}/debug.log"
RUNTIME_EVAL_ERROR="${TMP_DIR}/runtime-eval.stderr"
AUTHORITY_EVAL_ERROR="${TMP_DIR}/authority-eval.stderr"
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

[[ -f "${BUILT_THEME}/inc/seo-geo-core/src/Runtime.php" ]] \
  || fail_smoke "embedded-runtime" "Built theme does not contain embedded SEO/GEO runtime" "Runtime.php bundled" "missing"
[[ -f "${BUILT_THEME}/inc/seo-geo-core/src/Seo/OpenGraphResolver.php" ]] \
  || fail_smoke "embedded-open-graph" "Built theme does not contain Open Graph resolver" "OpenGraphResolver.php bundled" "missing"
[[ -f "${BUILT_THEME}/inc/seo-geo-core/src/Seo/BreadcrumbResolver.php" ]] \
  || fail_smoke "embedded-breadcrumbs" "Built theme does not contain breadcrumb resolver" "BreadcrumbResolver.php bundled" "missing"

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

POST_ID="$(wp_cli post create \
  --post_type=post \
  --post_status=publish \
  --post_title='Self-contained SEO Fixture' \
  --post_name='self-contained-seo-fixture' \
  --post_excerpt='Self-contained SEO GEO native description.' \
  --post_content='Theme-only SEO GEO fixture body.' \
  --porcelain 2>/dev/null | tr -d '\r\n')"
[[ "$POST_ID" =~ ^[0-9]+$ ]] \
  || fail_smoke "fixture-post" "Could not create fixture post" "numeric post ID" "$POST_ID"

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

if ! BREADCRUMBS_JSON="$(wp_cli eval "\$wp_query = new WP_Query( array( 'p' => ${POST_ID} ) ); if ( \$wp_query->have_posts() ) { \$wp_query->the_post(); } echo wp_json_encode( \\SeoGeo\\Core\\Runtime::breadcrumbs()?->resolve() ?? array() );" 2>"$BREADCRUMB_EVAL_ERROR" | tr -d '\r\n')"; then
  ERROR_TEXT="$(tr -d '\r' <"$BREADCRUMB_EVAL_ERROR" | head -c 240)"
  fail_smoke "breadcrumb-eval" "Could not resolve breadcrumb data contract" "root and current post items" "${ERROR_TEXT:-wp eval failed}" "wp eval Runtime::breadcrumbs"
fi

if ! BREADCRUMB_RESULT="$(python3 -c 'import json,sys; items=json.loads(sys.argv[1]); base=sys.argv[2].rstrip("/")+"/"; target=base+"self-contained-seo-fixture/"; assert len(items) >= 2; assert items[0].get("url") == base and items[0].get("current") is False; assert items[-1].get("label") == "Self-contained SEO Fixture"; assert items[-1].get("url") == target and items[-1].get("current") is True; assert sum(1 for item in items if item.get("current") is True) == 1; print("ok")' "$BREADCRUMBS_JSON" "$BASE_URL" 2>&1)"; then
  fail_smoke "breadcrumb-contract" "Breadcrumb data contract is incorrect" "root plus one current post item" "$BREADCRUMB_RESULT" "Runtime::breadcrumbs()->resolve()"
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

printf '[self-contained] Checking PHP runtime diagnostics.\n'
docker logs "$WP_CONTAINER" >"$RUNTIME_LOG" 2>&1 || true
docker exec "$WP_CONTAINER" sh -c 'test ! -f /var/www/html/wp-content/debug.log || cat /var/www/html/wp-content/debug.log' >"$DEBUG_LOG" 2>&1 || true

if grep -Eqi 'PHP (Fatal error|Warning|Notice)|Fatal error|Uncaught (Error|Exception)' "$RUNTIME_LOG" "$DEBUG_LOG"; then
  MATCH="$(grep -Eim1 'PHP (Fatal error|Warning|Notice)|Fatal error|Uncaught (Error|Exception)' "$RUNTIME_LOG" "$DEBUG_LOG" | tr -d '\r' | head -c 240)"
  fail_smoke "runtime-php" "PHP runtime emitted diagnostics" "no fatal/warning/notice/uncaught error" "$MATCH"
fi

printf 'Self-contained theme OK: zero plugins; native SEO + Open Graph; breadcrumbs contract; no PHP diagnostics.\n'
