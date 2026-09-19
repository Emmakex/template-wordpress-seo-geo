#!/usr/bin/env bash
# Phase 6G cache/revalidation acceptance.
#
# This file is sourced by self-contained-theme-smoke.sh and reuses its
# disposable WordPress fixture and structured fail_smoke diagnostic.

printf '[self-contained] Checking discovery cache revision and HTTP revalidation.\n'

CACHE_BODY="${TMP_DIR}/discovery-cache-body"
CACHE_HEADERS="${TMP_DIR}/discovery-cache-headers"
CACHE_POST_SLUG='phase6g-cache-control'
CACHE_POST_ID="$(wp_cli post create \
  --post_type=post \
  --post_status=publish \
  --post_title='Phase 6G Cache Control' \
  --post_name="$CACHE_POST_SLUG" \
  --post_content='Phase 6G cache body v1.' \
  --post_author=1 \
  --porcelain 2>/dev/null | tr -d '\r\n')"

[[ "$CACHE_POST_ID" =~ ^[0-9]+$ ]] \
  || fail_smoke "discovery-cache-fixture" "Could not create Phase 6G cache fixture" "numeric post ID" "$CACHE_POST_ID"

cache_revision() {
  wp_cli eval 'echo \\SeoGeo\\Core\\Runtime::discovery_cache_revision()?->current() ?? "missing";' 2>/dev/null | tr -d '\r\n'
}

cache_etag() {
  awk 'BEGIN { IGNORECASE=1 } /^etag:/ { sub(/\r$/, ""); sub(/^[^:]+:[[:space:]]*/, ""); print; exit }' "$1"
}

assert_revalidation_headers() {
  local headers_file="$1"
  local surface="$2"

  grep -Eiq '^cache-control:[[:space:]]*public, no-cache, must-revalidate, max-age=0\r?$' "$headers_file" \
    || fail_smoke "discovery-cache-${surface}-cache-control" "Discovery response missed the conservative public revalidation policy" "public, no-cache, must-revalidate, max-age=0" "header absent or different"

  local etag
  etag="$(cache_etag "$headers_file")"
  [[ "$etag" == '"seo-geo-'*'"' ]] \
    || fail_smoke "discovery-cache-${surface}-etag" "Discovery response missed a strong SEO/GEO ETag" '"seo-geo-..."' "${etag:-missing}"
}

CACHE_REVISION_BASE="$(cache_revision)"
[[ -n "$CACHE_REVISION_BASE" && "$CACHE_REVISION_BASE" != "missing" ]] \
  || fail_smoke "discovery-cache-revision" "Runtime did not expose a discovery cache revision" "non-empty revision" "$CACHE_REVISION_BASE"

printf '[self-contained] Phase 6G: unrelated options do not invalidate.\n'
wp_cli eval 'update_option( "seo_geo_unrelated_cache_probe", "one", false );' >/dev/null \
  || fail_smoke "discovery-cache-unrelated-option" "Could not create unrelated option probe" "option update succeeds" "failed"
CACHE_REVISION_UNRELATED="$(cache_revision)"
[[ "$CACHE_REVISION_UNRELATED" == "$CACHE_REVISION_BASE" ]] \
  || fail_smoke "discovery-cache-unrelated-revision" "Unrelated option change invalidated GEO discovery cache" "$CACHE_REVISION_BASE" "$CACHE_REVISION_UNRELATED"
wp_cli option delete seo_geo_unrelated_cache_probe >/dev/null 2>&1 || true

printf '[self-contained] Phase 6G: llms.txt revalidation and option invalidation.\n'
wp_cli eval "update_option( 'seo_geo_llms_txt', array( 'enabled' => true, 'summary' => 'Phase 6G cache summary v1.', 'sections' => array( array( 'title' => 'Cache resources', 'post_ids' => array( $CACHE_POST_ID ) ) ) ), false );" >/dev/null \
  || fail_smoke "discovery-cache-llms-option" "Could not enable llms.txt cache fixture" "option update succeeds" "failed"

CACHE_REVISION_LLMS="$(cache_revision)"
[[ "$CACHE_REVISION_LLMS" != "$CACHE_REVISION_BASE" ]] \
  || fail_smoke "discovery-cache-llms-revision" "Relevant llms.txt option did not advance discovery revision" "revision changes" "$CACHE_REVISION_LLMS"

CACHE_STATUS="$(curl -sS -D "$CACHE_HEADERS" -o "$CACHE_BODY" -w '%{http_code}' "${BASE_URL}/llms.txt")"
[[ "$CACHE_STATUS" == "200" ]] \
  || fail_smoke "discovery-cache-llms-http" "Could not request llms.txt cache fixture" "HTTP 200" "$CACHE_STATUS"
assert_revalidation_headers "$CACHE_HEADERS" "llms"
LLMS_ETAG_V1="$(cache_etag "$CACHE_HEADERS")"
grep -Fq 'Phase 6G cache summary v1.' "$CACHE_BODY" \
  || fail_smoke "discovery-cache-llms-body" "llms.txt cache fixture returned unexpected body" "v1 summary" "summary absent"

CACHE_STATUS="$(curl -sS -D "$CACHE_HEADERS" -o "$CACHE_BODY" -H "If-None-Match: $LLMS_ETAG_V1" -w '%{http_code}' "${BASE_URL}/llms.txt")"
[[ "$CACHE_STATUS" == "304" ]] \
  || fail_smoke "discovery-cache-llms-304" "Matching llms.txt ETag did not revalidate to 304" "HTTP 304" "$CACHE_STATUS"
assert_revalidation_headers "$CACHE_HEADERS" "llms-304"

wp_cli eval '$configuration = get_option( "seo_geo_llms_txt", array() ); $configuration["summary"] = "Phase 6G cache summary v2."; update_option( "seo_geo_llms_txt", $configuration, false );' >/dev/null \
  || fail_smoke "discovery-cache-llms-update" "Could not mutate llms.txt cache fixture" "option update succeeds" "failed"

CACHE_STATUS="$(curl -sS -D "$CACHE_HEADERS" -o "$CACHE_BODY" -H "If-None-Match: $LLMS_ETAG_V1" -w '%{http_code}' "${BASE_URL}/llms.txt")"
[[ "$CACHE_STATUS" == "200" ]] \
  || fail_smoke "discovery-cache-llms-invalidation" "Stale llms.txt ETag survived relevant option mutation" "HTTP 200 with refreshed representation" "$CACHE_STATUS"
assert_revalidation_headers "$CACHE_HEADERS" "llms-refreshed"
LLMS_ETAG_V2="$(cache_etag "$CACHE_HEADERS")"
[[ "$LLMS_ETAG_V2" != "$LLMS_ETAG_V1" ]] \
  || fail_smoke "discovery-cache-llms-etag-change" "llms.txt ETag did not change after relevant option mutation" "different ETag" "$LLMS_ETAG_V2"
grep -Fq 'Phase 6G cache summary v2.' "$CACHE_BODY" \
  || fail_smoke "discovery-cache-llms-refresh-body" "Refreshed llms.txt did not expose updated public configuration" "v2 summary" "updated summary absent"

printf '[self-contained] Phase 6G: Markdown revalidation and post invalidation.\n'
wp_cli eval 'update_option( "seo_geo_markdown_alternates", array( "enabled" => true ), false );' >/dev/null \
  || fail_smoke "discovery-cache-markdown-option" "Could not enable Markdown cache fixture" "option update succeeds" "failed"

CACHE_MARKDOWN_URL="${BASE_URL}/${CACHE_POST_SLUG}/index.md"
CACHE_STATUS="$(curl -sS -D "$CACHE_HEADERS" -o "$CACHE_BODY" -w '%{http_code}' "$CACHE_MARKDOWN_URL")"
[[ "$CACHE_STATUS" == "200" ]] \
  || fail_smoke "discovery-cache-markdown-http" "Could not request Markdown cache fixture" "HTTP 200" "$CACHE_STATUS"
assert_revalidation_headers "$CACHE_HEADERS" "markdown"
MARKDOWN_ETAG_V1="$(cache_etag "$CACHE_HEADERS")"
grep -Fq 'Phase 6G cache body v1.' "$CACHE_BODY" \
  || fail_smoke "discovery-cache-markdown-body" "Markdown cache fixture returned unexpected body" "v1 body" "body absent"

CACHE_STATUS="$(curl -sS -D "$CACHE_HEADERS" -o "$CACHE_BODY" -H "If-None-Match: $MARKDOWN_ETAG_V1" -w '%{http_code}' "$CACHE_MARKDOWN_URL")"
[[ "$CACHE_STATUS" == "304" ]] \
  || fail_smoke "discovery-cache-markdown-304" "Matching Markdown ETag did not revalidate to 304" "HTTP 304" "$CACHE_STATUS"
assert_revalidation_headers "$CACHE_HEADERS" "markdown-304"

wp_cli post update "$CACHE_POST_ID" --post_content='Phase 6G cache body v2.' >/dev/null \
  || fail_smoke "discovery-cache-post-update" "Could not mutate Markdown source post" "post update succeeds" "failed"

CACHE_STATUS="$(curl -sS -D "$CACHE_HEADERS" -o "$CACHE_BODY" -H "If-None-Match: $MARKDOWN_ETAG_V1" -w '%{http_code}' "$CACHE_MARKDOWN_URL")"
[[ "$CACHE_STATUS" == "200" ]] \
  || fail_smoke "discovery-cache-markdown-invalidation" "Stale Markdown ETag survived post mutation" "HTTP 200 with refreshed representation" "$CACHE_STATUS"
assert_revalidation_headers "$CACHE_HEADERS" "markdown-refreshed"
MARKDOWN_ETAG_V2="$(cache_etag "$CACHE_HEADERS")"
[[ "$MARKDOWN_ETAG_V2" != "$MARKDOWN_ETAG_V1" ]] \
  || fail_smoke "discovery-cache-markdown-etag-change" "Markdown ETag did not change after post mutation" "different ETag" "$MARKDOWN_ETAG_V2"
grep -Fq 'Phase 6G cache body v2.' "$CACHE_BODY" \
  || fail_smoke "discovery-cache-markdown-refresh-body" "Refreshed Markdown did not expose updated public post content" "v2 body" "updated body absent"

printf '[self-contained] Phase 6G: author/profile invalidation.\n'
CACHE_ORIGINAL_DISPLAY_NAME="$(wp_cli user get 1 --field=display_name 2>/dev/null | tr -d '\r\n')"
[[ -n "$CACHE_ORIGINAL_DISPLAY_NAME" ]] \
  || fail_smoke "discovery-cache-user-read" "Could not read cache fixture author display name" "non-empty display name" "empty"

wp_cli user update 1 --display_name='Phase 6G Cache Author' >/dev/null \
  || fail_smoke "discovery-cache-user-update" "Could not mutate author identity" "user update succeeds" "failed"

CACHE_STATUS="$(curl -sS -D "$CACHE_HEADERS" -o "$CACHE_BODY" -H "If-None-Match: $MARKDOWN_ETAG_V2" -w '%{http_code}' "$CACHE_MARKDOWN_URL")"
[[ "$CACHE_STATUS" == "200" ]] \
  || fail_smoke "discovery-cache-user-invalidation" "Stale Markdown ETag survived author identity mutation" "HTTP 200 with refreshed provenance" "$CACHE_STATUS"
MARKDOWN_ETAG_V3="$(cache_etag "$CACHE_HEADERS")"
[[ "$MARKDOWN_ETAG_V3" != "$MARKDOWN_ETAG_V2" ]] \
  || fail_smoke "discovery-cache-user-etag-change" "Markdown ETag did not change after author identity mutation" "different ETag" "$MARKDOWN_ETAG_V3"
grep -Fq 'Author: [Phase 6G Cache Author]' "$CACHE_BODY" \
  || fail_smoke "discovery-cache-user-body" "Refreshed Markdown did not expose updated public author identity" "Phase 6G Cache Author" "updated author absent"

wp_cli user update 1 --display_name="$CACHE_ORIGINAL_DISPLAY_NAME" >/dev/null \
  || fail_smoke "discovery-cache-user-reset" "Could not restore cache fixture author" "$CACHE_ORIGINAL_DISPLAY_NAME" "failed"

printf '[self-contained] Phase 6G: translation metadata invalidation.\n'
CACHE_REVISION_META_BEFORE="$(cache_revision)"
wp_cli post meta update "$CACHE_POST_ID" _seo_geo_language en >/dev/null \
  || fail_smoke "discovery-cache-meta-update" "Could not mutate translation metadata probe" "post meta update succeeds" "failed"
CACHE_REVISION_META_AFTER="$(cache_revision)"
[[ "$CACHE_REVISION_META_AFTER" != "$CACHE_REVISION_META_BEFORE" ]] \
  || fail_smoke "discovery-cache-meta-revision" "Relevant translation metadata did not advance discovery revision" "revision changes" "$CACHE_REVISION_META_AFTER"
wp_cli post meta delete "$CACHE_POST_ID" _seo_geo_language >/dev/null 2>&1 || true

wp_cli eval 'delete_option( "seo_geo_llms_txt" ); delete_option( "seo_geo_markdown_alternates" );' >/dev/null \
  || fail_smoke "discovery-cache-reset" "Could not reset Phase 6G discovery options" "options removed" "reset failed"

printf '[self-contained] Phase 6G cache invalidation/revalidation OK.\n'
