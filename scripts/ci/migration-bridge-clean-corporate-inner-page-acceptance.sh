#!/usr/bin/env bash
# Generic clean Corporate inner-page + Services scaffold acceptance.

printf '[smoke] Checking clean Corporate inner-page rebuilder with Services pilot.\n'

INNER_PAGE_RUNNER="$TMP_DIR/clean-corporate-inner-page-runner.php"
cat >"$INNER_PAGE_RUNNER" <<'PHP'
<?php

use SeoGeo\MigrationBridge\Plugin;
use SeoGeo\MigrationBridge\Reset\CleanCorporatePageRebuilder;

wp_set_current_user( 1 );

$rebuilder = Plugin::clean_corporate_page_rebuilder();
$manifest  = Plugin::rescue_manifest()?->saved();

if ( ! $rebuilder instanceof CleanCorporatePageRebuilder || ! is_array( $manifest ) ) {
	throw new RuntimeException( 'Clean Corporate inner-page rebuilder or Rescue Manifest is unavailable.' );
}

$candidates = $rebuilder->source_candidates();
if ( array() === $candidates ) {
	throw new RuntimeException( 'No rescued published inner-page candidate is available.' );
}

$source_id = (int) $candidates[0]['id'];
$source    = get_post( $source_id );
if ( ! $source instanceof WP_Post ) {
	throw new RuntimeException( 'Selected rescued Services source is unavailable.' );
}

$source_content_before = (string) $source->post_content;
$source_path_before    = (string) $candidates[0]['path'];
$front_before          = (int) get_option( 'page_on_front', 0 );
$plugins_before        = array_values( array_map( 'strval', get_option( 'active_plugins', array() ) ) );
sort( $plugins_before );

$explicit_plan = $rebuilder->plan( 'services', $source_id );
$front_plan    = $rebuilder->plan( 'services', $front_before );
$home_plan     = $rebuilder->plan( 'home', $source_id );
$insights_plan = $rebuilder->plan( 'insights', $source_id );

$created = $rebuilder->create_draft( 'services', $source_id );
$replay  = $rebuilder->create_draft( 'services', $source_id );
if ( $created instanceof WP_Error ) {
	throw new RuntimeException( $created->get_error_code() . ': ' . $created->get_error_message() );
}
if ( $replay instanceof WP_Error ) {
	throw new RuntimeException( $replay->get_error_code() . ': ' . $replay->get_error_message() );
}

$bound_plan      = $rebuilder->plan( 'services' );
$remap_source_id = 1 < count( $candidates ) ? (int) $candidates[1]['id'] : $front_before;
$remap_plan      = $rebuilder->plan( 'services', $remap_source_id );
$draft_id        = (int) ( $created['draft_id'] ?? 0 );
$draft      = 0 < $draft_id ? get_post( $draft_id ) : null;
if ( ! $draft instanceof WP_Post ) {
	throw new RuntimeException( 'Clean Services draft could not be loaded.' );
}

$source_after  = get_post( $source_id );
$plugins_after = array_values( array_map( 'strval', get_option( 'active_plugins', array() ) ) );
sort( $plugins_after );

$source_path_after = '';
if ( $source_after instanceof WP_Post ) {
	$parsed = wp_parse_url( get_permalink( $source_after ), PHP_URL_PATH );
	$source_path_after = is_string( $parsed ) ? $parsed : '';
}

echo wp_json_encode(
	array(
		'candidates'    => $candidates,
		'explicit_plan' => $explicit_plan,
		'bound_plan'    => $bound_plan,
		'negative'      => array(
			'front'    => $front_plan,
			'home'     => $home_plan,
			'insights' => $insights_plan,
			'remap'    => $remap_plan,
		),
		'created'       => $created,
		'replay'        => $replay,
		'draft'         => array(
			'id'            => $draft_id,
			'status'        => (string) $draft->post_status,
			'title'         => get_the_title( $draft ),
			'content'       => (string) $draft->post_content,
			'page_key_meta' => (string) get_post_meta( $draft_id, CleanCorporatePageRebuilder::PAGE_KEY_META, true ),
			'source_id_meta'=> (int) get_post_meta( $draft_id, CleanCorporatePageRebuilder::SOURCE_ID_META, true ),
			'source_path_meta' => (string) get_post_meta( $draft_id, CleanCorporatePageRebuilder::SOURCE_PATH_META, true ),
			'patterns_meta' => get_post_meta( $draft_id, CleanCorporatePageRebuilder::PATTERNS_META, true ),
			'content_state' => (string) get_post_meta( $draft_id, CleanCorporatePageRebuilder::CONTENT_STATE_META, true ),
		),
		'safety'        => array(
			'source_content_unchanged' => $source_after instanceof WP_Post
				&& hash_equals( hash( 'sha256', $source_content_before ), hash( 'sha256', (string) $source_after->post_content ) ),
			'source_path_before'       => $source_path_before,
			'source_path_after'        => $source_path_after,
			'front_before'             => $front_before,
			'front_after'              => (int) get_option( 'page_on_front', 0 ),
			'plugins_before'           => $plugins_before,
			'plugins_after'            => $plugins_after,
			'bound_source_id'          => $rebuilder->bound_source_id( 'services' ),
		),
	),
	JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
);
PHP

docker cp "$INNER_PAGE_RUNNER" "$WP_CONTAINER":/var/www/html/wp-content/clean-corporate-inner-page-runner.php \
  || fail_smoke "clean-inner-page-runner-copy" "Could not copy clean Corporate inner-page runner" "runner copied" "docker cp failed"

if ! INNER_PAGE_REPORT="$(wp_cli eval-file /var/www/html/wp-content/clean-corporate-inner-page-runner.php 2>"$TMP_DIR/clean-corporate-inner-page.stderr")"; then
  INNER_PAGE_ERROR="$(tr -d '\r' <"$TMP_DIR/clean-corporate-inner-page.stderr" | head -c 1800)"
  fail_smoke "clean-inner-page-runner" "Clean Corporate inner-page runner failed" "JSON inner-page report" "${INNER_PAGE_ERROR:-wp eval-file failed}"
fi

printf '%s' "$INNER_PAGE_REPORT" >"$TMP_DIR/clean-corporate-inner-page-report.json"

if ! python3 - "$TMP_DIR/clean-corporate-inner-page-report.json" <<'PY'
import json
import sys

with open(sys.argv[1], "r", encoding="utf-8") as handle:
    report = json.load(handle)

plan = report["explicit_plan"]
bound = report["bound_plan"]
negative = report["negative"]
created = report["created"]
replay = report["replay"]
draft = report["draft"]
safety = report["safety"]

expected_patterns = [
    "seo-geo-theme/corporate-native-capabilities",
    "seo-geo-theme/corporate-native-process",
    "seo-geo-theme/corporate-native-proof",
    "seo-geo-theme/faq",
    "seo-geo-theme/cta",
]

assert plan["ready"] is True
assert plan["mode"] == "reset-rebuild-clean-corporate-page-plan"
assert plan["page_key"] == "services"
assert plan["definition"]["role"] == "services-index"
assert plan["definition"]["slug"] in {"services", "servicios"}
assert plan["patterns"] == expected_patterns
assert plan["content_state"] == "preset-scaffold"
assert plan["source"]["id"] == report["candidates"][0]["id"]
assert plan["source"]["path"] == report["candidates"][0]["path"]
assert plan["safety"] == {
    "legacy_layout_reused": False,
    "content_remap_required": False,
    "source_post_mutation": False,
    "source_url_change": False,
    "front_page_assignment_change": False,
    "draft_only": True,
}
assert len(plan["plan_sha256"]) == 64

assert negative["front"]["ready"] is False
assert "inner-page-source-cannot-be-front-page" in negative["front"]["blockers"]
assert negative["home"]["ready"] is False
assert "home-has-dedicated-rebuilder" in negative["home"]["blockers"]
assert negative["insights"]["ready"] is False
assert "corporate-page-patterns-empty" in negative["insights"]["blockers"]
assert negative["remap"]["ready"] is False
assert "page-source-binding-conflict" in negative["remap"]["blockers"]

assert created["status"] == "created"
assert replay["status"] == "existing"
assert created["draft_id"] == replay["draft_id"]
assert created["source_id"] == plan["source"]["id"]
assert created["safety"] == {
    "source_unchanged": True,
    "source_path_unchanged": True,
    "front_page_id_unchanged": True,
    "plugins_unchanged": True,
    "draft_only": True,
}

assert bound["ready"] is True
assert bound["existing_draft"] == created["draft_id"]
assert bound["source"]["id"] == created["source_id"]

assert draft["status"] == "draft"
assert draft["page_key_meta"] == "services"
assert draft["source_id_meta"] == created["source_id"]
assert draft["source_path_meta"] == plan["source"]["path"]
assert draft["patterns_meta"] == expected_patterns
assert draft["content_state"] == "preset-scaffold"
assert "seo-geo-corporate-native-capabilities" in draft["content"]
assert "seo-geo-corporate-native-process" in draft["content"]
assert "seo-geo-content-slot--capability-1-title" in draft["content"]

content_lower = draft["content"].lower()
for forbidden in ("[et_pb_", "et_pb_", "elementor-", "[vc_", "fusion-builder", "seo-geo-remap-slot:"):
    assert forbidden not in content_lower

assert safety["source_content_unchanged"] is True
assert safety["source_path_before"] == safety["source_path_after"] == plan["source"]["path"]
assert safety["front_before"] == safety["front_after"]
assert safety["plugins_before"] == safety["plugins_after"]
assert safety["bound_source_id"] == created["source_id"]
PY
then
  fail_smoke "clean-inner-page-assertions" "Clean Corporate inner-page rebuilder violated explicit source mapping, URL/content preservation, native composition or idempotency invariants" "all assertions pass" "assertion failure"
fi

printf '[smoke] Clean Corporate inner-page rebuilder OK: explicit rescued source mapping, Services native scaffold, URL/content/plugin/front-page preservation and idempotent replay passed.\n'
