#!/usr/bin/env bash
# Phase 6F discovery privacy acceptance.
#
# This file is sourced by self-contained-theme-smoke.sh and intentionally
# reuses its disposable WordPress fixture, wp_cli helper and structured
# fail_smoke diagnostic instead of starting a second runner environment.

printf '[self-contained] Checking private/draft discovery leakage matrix.\n'

DISCOVERY_BODY="${TMP_DIR}/discovery-privacy-body"
DISCOVERY_HEADERS="${TMP_DIR}/discovery-privacy-headers"
DISCOVERY_PUBLIC_TITLE='Phase 6F Public Control'
DISCOVERY_DRAFT_TITLE='PHASE6F_DRAFT_TITLE_SECRET_7F31'
DISCOVERY_PRIVATE_TITLE='PHASE6F_PRIVATE_TITLE_SECRET_8A42'
DISCOVERY_DRAFT_CONTENT='PHASE6F_DRAFT_CONTENT_SECRET_1C53'
DISCOVERY_PRIVATE_CONTENT='PHASE6F_PRIVATE_CONTENT_SECRET_2D64'
DISCOVERY_DRAFT_SLUG='phase6f-draft-resource'
DISCOVERY_PRIVATE_SLUG='phase6f-private-resource'
DISCOVERY_PUBLIC_SLUG='phase6f-public-control'

DISCOVERY_PUBLIC_ID="$(wp_cli post create \
  --post_type=post \
  --post_status=publish \
  --post_title="$DISCOVERY_PUBLIC_TITLE" \
  --post_name="$DISCOVERY_PUBLIC_SLUG" \
  --post_content='Phase 6F public control body.' \
  --post_author=1 \
  --porcelain 2>/dev/null | tr -d '\r\n')"
DISCOVERY_DRAFT_ID="$(wp_cli post create \
  --post_type=post \
  --post_status=draft \
  --post_title="$DISCOVERY_DRAFT_TITLE" \
  --post_name="$DISCOVERY_DRAFT_SLUG" \
  --post_content="$DISCOVERY_DRAFT_CONTENT" \
  --post_excerpt="$DISCOVERY_DRAFT_CONTENT" \
  --post_author=1 \
  --porcelain 2>/dev/null | tr -d '\r\n')"
DISCOVERY_PRIVATE_ID="$(wp_cli post create \
  --post_type=post \
  --post_status=private \
  --post_title="$DISCOVERY_PRIVATE_TITLE" \
  --post_name="$DISCOVERY_PRIVATE_SLUG" \
  --post_content="$DISCOVERY_PRIVATE_CONTENT" \
  --post_excerpt="$DISCOVERY_PRIVATE_CONTENT" \
  --post_author=1 \
  --porcelain 2>/dev/null | tr -d '\r\n')"

for discovery_id in "$DISCOVERY_PUBLIC_ID" "$DISCOVERY_DRAFT_ID" "$DISCOVERY_PRIVATE_ID"; do
  [[ "$discovery_id" =~ ^[0-9]+$ ]] \
    || fail_smoke "discovery-privacy-fixture" "Could not create Phase 6F discovery fixture" "numeric post ID" "$discovery_id"
done

assert_no_discovery_secrets() {
  local file="$1"
  local surface="$2"

  for secret in \
    "$DISCOVERY_DRAFT_TITLE" \
    "$DISCOVERY_PRIVATE_TITLE" \
    "$DISCOVERY_DRAFT_CONTENT" \
    "$DISCOVERY_PRIVATE_CONTENT"; do
    if grep -Fq "$secret" "$file"; then
      fail_smoke \
        "discovery-privacy-${surface}" \
        "Non-public content leaked into a public discovery surface" \
        "no draft/private title or content marker" \
        "$secret" \
        "scan ${surface}"
    fi
  done
}

printf '[self-contained] Phase 6F: direct non-public HTML routes.\n'
for fixture in \
  "draft:${DISCOVERY_DRAFT_SLUG}" \
  "private:${DISCOVERY_PRIVATE_SLUG}"; do
  fixture_kind="${fixture%%:*}"
  fixture_slug="${fixture#*:}"
  DISCOVERY_STATUS="$(curl -sS -D "$DISCOVERY_HEADERS" -o "$DISCOVERY_BODY" -w '%{http_code}' "${BASE_URL}/${fixture_slug}/")"
  [[ "$DISCOVERY_STATUS" == "404" ]] \
    || fail_smoke "discovery-privacy-html-${fixture_kind}" "Non-public post resolved on a public HTML route" "HTTP 404" "$DISCOVERY_STATUS" "curl /${fixture_slug}/"

  assert_no_discovery_secrets "$DISCOVERY_BODY" "html-${fixture_kind}"

  if grep -Eiq '<script[^>]+id=["'\'']seo-geo-schema-graph["'\'']' "$DISCOVERY_BODY"; then
    fail_smoke "discovery-privacy-schema-${fixture_kind}" "Non-public HTML route emitted native Schema" "no seo-geo-schema-graph" "Schema graph present"
  fi

  if grep -Eiq '<meta[^>]+name=["'\'']author["'\'']|property=["'\'']article:(author|published_time|modified_time)["'\'']|rel=["'\'']author["'\'']' "$DISCOVERY_BODY"; then
    fail_smoke "discovery-privacy-provenance-${fixture_kind}" "Non-public HTML route emitted article provenance" "no article provenance" "provenance metadata present"
  fi

  if grep -Eiq 'type=["'\'']text/markdown["'\'']' "$DISCOVERY_BODY"; then
    fail_smoke "discovery-privacy-markdown-link-${fixture_kind}" "Non-public HTML route advertised a Markdown alternate" "no text/markdown alternate" "Markdown alternate present"
  fi
done

printf '[self-contained] Phase 6F: native sitemap.\n'
DISCOVERY_SITEMAP_STATUS="$(curl -sS -o "$DISCOVERY_BODY" -w '%{http_code}' "${BASE_URL}/wp-sitemap-posts-post-1.xml")"
[[ "$DISCOVERY_SITEMAP_STATUS" == "200" ]] \
  || fail_smoke "discovery-privacy-sitemap-request" "Could not request native post sitemap" "HTTP 200" "$DISCOVERY_SITEMAP_STATUS"

grep -Fq "${BASE_URL}/${DISCOVERY_PUBLIC_SLUG}/" "$DISCOVERY_BODY" \
  || fail_smoke "discovery-privacy-sitemap-control" "Public control post is missing from native sitemap" "${DISCOVERY_PUBLIC_SLUG}" "public control absent"

if grep -Fq "$DISCOVERY_DRAFT_SLUG" "$DISCOVERY_BODY" || grep -Fq "$DISCOVERY_PRIVATE_SLUG" "$DISCOVERY_BODY"; then
  fail_smoke "discovery-privacy-sitemap-url" "Draft/private URL leaked into native sitemap" "no non-public slugs" "non-public slug present"
fi
assert_no_discovery_secrets "$DISCOVERY_BODY" "sitemap"

printf '[self-contained] Phase 6F: feed, search and author archive.\n'
for surface in \
  "feed:${BASE_URL}/feed/" \
  "search:${BASE_URL}/?s=PHASE6F_" \
  "author:${BASE_URL}/author/admin/"; do
  surface_name="${surface%%:*}"
  surface_url="${surface#*:}"
  DISCOVERY_STATUS="$(curl -sS -o "$DISCOVERY_BODY" -w '%{http_code}' "$surface_url")"
  [[ "$DISCOVERY_STATUS" == "200" ]] \
    || fail_smoke "discovery-privacy-${surface_name}-request" "Could not request public discovery surface" "HTTP 200" "$DISCOVERY_STATUS" "curl ${surface_name}"
  assert_no_discovery_secrets "$DISCOVERY_BODY" "$surface_name"
done

printf '[self-contained] Phase 6F: REST API unauthenticated access.\n'
for fixture in \
  "draft:${DISCOVERY_DRAFT_ID}" \
  "private:${DISCOVERY_PRIVATE_ID}"; do
  fixture_kind="${fixture%%:*}"
  fixture_id="${fixture#*:}"
  DISCOVERY_STATUS="$(curl -sS -o "$DISCOVERY_BODY" -w '%{http_code}' "${BASE_URL}/wp-json/wp/v2/posts/${fixture_id}")"
  case "$DISCOVERY_STATUS" in
    401|403|404) ;;
    *) fail_smoke "discovery-privacy-rest-${fixture_kind}" "REST API exposed a non-public post to an unauthenticated request" "HTTP 401, 403 or 404" "$DISCOVERY_STATUS" "GET wp-json/wp/v2/posts/${fixture_id}" ;;
  esac
  assert_no_discovery_secrets "$DISCOVERY_BODY" "rest-${fixture_kind}"
done

printf '[self-contained] Phase 6F: llms.txt and Markdown.\n'
wp_cli eval "update_option( 'seo_geo_llms_txt', array( 'enabled' => true, 'summary' => 'Phase 6F privacy matrix.', 'sections' => array( array( 'title' => 'Privacy controls', 'post_ids' => array( $DISCOVERY_PUBLIC_ID, $DISCOVERY_DRAFT_ID, $DISCOVERY_PRIVATE_ID ) ) ) ), false ); update_option( 'seo_geo_markdown_alternates', array( 'enabled' => true ), false );" >/dev/null \
  || fail_smoke "discovery-privacy-geo-options" "Could not enable Phase 6F GEO discovery fixtures" "llms.txt and Markdown enabled" "option update failed"

DISCOVERY_STATUS="$(curl -sS -o "$DISCOVERY_BODY" -w '%{http_code}' "${BASE_URL}/llms.txt")"
[[ "$DISCOVERY_STATUS" == "200" ]] \
  || fail_smoke "discovery-privacy-llms-request" "Could not request enabled llms.txt" "HTTP 200" "$DISCOVERY_STATUS"

grep -Fq "$DISCOVERY_PUBLIC_TITLE" "$DISCOVERY_BODY" \
  || fail_smoke "discovery-privacy-llms-control" "Public control resource is missing from llms.txt" "$DISCOVERY_PUBLIC_TITLE" "public control absent"
assert_no_discovery_secrets "$DISCOVERY_BODY" "llms"

DISCOVERY_PUBLIC_MD="${BASE_URL}/${DISCOVERY_PUBLIC_SLUG}/index.md"
DISCOVERY_STATUS="$(curl -sS -o "$DISCOVERY_BODY" -w '%{http_code}' "$DISCOVERY_PUBLIC_MD")"
[[ "$DISCOVERY_STATUS" == "200" ]] \
  || fail_smoke "discovery-privacy-markdown-control" "Public control Markdown alternate did not resolve" "HTTP 200" "$DISCOVERY_STATUS"

for fixture in \
  "draft:${DISCOVERY_DRAFT_SLUG}" \
  "private:${DISCOVERY_PRIVATE_SLUG}"; do
  fixture_kind="${fixture%%:*}"
  fixture_slug="${fixture#*:}"
  DISCOVERY_STATUS="$(curl -sS -o "$DISCOVERY_BODY" -w '%{http_code}' "${BASE_URL}/${fixture_slug}/index.md")"
  [[ "$DISCOVERY_STATUS" == "404" ]] \
    || fail_smoke "discovery-privacy-markdown-${fixture_kind}" "Non-public resource exposed a Markdown alternate" "HTTP 404" "$DISCOVERY_STATUS" "curl /${fixture_slug}/index.md"
  assert_no_discovery_secrets "$DISCOVERY_BODY" "markdown-${fixture_kind}"
done

printf '[self-contained] Phase 6F: resolver-level provenance guard.\n'
if ! DISCOVERY_RESOLVER_RESULT="$(wp_cli eval "$provenance = \\SeoGeo\\Core\\Runtime::content_provenance(); $markdown = \\SeoGeo\\Core\\Runtime::markdown_alternates(); if ( ! $provenance || ! $markdown ) { exit( 2 ); } echo wp_json_encode( array( 'draft_provenance' => $provenance->for_post( $DISCOVERY_DRAFT_ID ), 'private_provenance' => $provenance->for_post( $DISCOVERY_PRIVATE_ID ), 'draft_markdown' => $markdown->url_for_post( $DISCOVERY_DRAFT_ID, null ), 'private_markdown' => $markdown->url_for_post( $DISCOVERY_PRIVATE_ID, null ) ) );" 2>"${TMP_DIR}/discovery-privacy-eval.stderr" | tr -d '\r\n')"; then
  DISCOVERY_EVAL_ERROR="$(tr -d '\r' <"${TMP_DIR}/discovery-privacy-eval.stderr" | head -c 240)"
  fail_smoke "discovery-privacy-resolver-eval" "Could not evaluate discovery privacy resolvers" "resolver evaluation succeeds" "${DISCOVERY_EVAL_ERROR:-wp eval failed}" "wp eval discovery privacy resolvers"
fi

[[ "$DISCOVERY_RESOLVER_RESULT" == '{"draft_provenance":null,"private_provenance":null,"draft_markdown":null,"private_markdown":null}' ]] \
  || fail_smoke "discovery-privacy-resolver" "Resolver-level guards exposed non-public provenance or Markdown" "all null" "$DISCOVERY_RESOLVER_RESULT"

wp_cli eval 'delete_option( "seo_geo_llms_txt" ); delete_option( "seo_geo_markdown_alternates" );' >/dev/null \
  || fail_smoke "discovery-privacy-reset" "Could not reset Phase 6F GEO options" "options removed" "reset failed"

printf '[self-contained] Phase 6F discovery privacy matrix OK.\n'
