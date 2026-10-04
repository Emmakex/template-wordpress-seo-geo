#!/usr/bin/env bash
# Reset/Rebuild clean Corporate Home acceptance.

printf '[smoke] Checking clean Corporate Home rebuild.\n'

CLEAN_HOME_RUNNER="$TMP_DIR/clean-home-rebuild-runner.php"
cat >"$CLEAN_HOME_RUNNER" <<'PHP'
<?php

use SeoGeo\MigrationBridge\Plugin;
use SeoGeo\MigrationBridge\Reset\CleanHomeRebuilder;

wp_set_current_user( 1 );

$builder  = Plugin::clean_home_rebuilder();
$manifest = Plugin::rescue_manifest()?->saved();

if ( ! $builder instanceof CleanHomeRebuilder || ! is_array( $manifest ) ) {
	throw new RuntimeException( 'Clean Home builder or Rescue Manifest service is unavailable.' );
}

$source_id = (int) ( $manifest['site']['front_page_id'] ?? 0 );
$source    = 0 < $source_id ? get_post( $source_id ) : null;
if ( ! $source instanceof WP_Post ) {
	throw new RuntimeException( 'Rescued front page is unavailable.' );
}

$source_sha_before = hash( 'sha256', (string) $source->post_content );
$front_before      = (int) get_option( 'page_on_front', 0 );
$plugins_before    = array_values( array_map( 'strval', get_option( 'active_plugins', array() ) ) );
sort( $plugins_before );

$drafts_before = get_posts(
	array(
		'post_type'      => 'page',
		'post_status'    => array( 'draft', 'pending', 'private' ),
		'posts_per_page' => -1,
		'fields'         => 'ids',
	)
);

$plan    = $builder->plan();
$created = $builder->create_draft();
$replay  = $builder->create_draft();

if ( $created instanceof WP_Error ) {
	throw new RuntimeException( $created->get_error_code() . ': ' . $created->get_error_message() );
}
if ( $replay instanceof WP_Error ) {
	throw new RuntimeException( $replay->get_error_code() . ': ' . $replay->get_error_message() );
}

$draft_id = (int) ( $created['draft_id'] ?? 0 );
$draft    = get_post( $draft_id );
if ( ! $draft instanceof WP_Post ) {
	throw new RuntimeException( 'Clean Home draft could not be loaded.' );
}

$drafts_after = get_posts(
	array(
		'post_type'      => 'page',
		'post_status'    => array( 'draft', 'pending', 'private' ),
		'posts_per_page' => -1,
		'fields'         => 'ids',
	)
);

$plugins_after = array_values( array_map( 'strval', get_option( 'active_plugins', array() ) ) );
sort( $plugins_after );

$source_after = get_post( $source_id );
$draft_content = (string) $draft->post_content;

echo wp_json_encode(
	array(
		'plan'    => $plan,
		'created' => $created,
		'replay'  => $replay,
		'draft'   => array(
			'id'             => $draft_id,
			'status'         => (string) $draft->post_status,
			'title'          => get_the_title( $draft ),
			'content_sha256' => hash( 'sha256', $draft_content ),
			'content'        => $draft_content,
			'source_id_meta' => (int) get_post_meta( $draft_id, CleanHomeRebuilder::SOURCE_ID_META, true ),
			'source_sha_meta' => (string) get_post_meta( $draft_id, CleanHomeRebuilder::SOURCE_SHA_META, true ),
			'manifest_sha_meta' => (string) get_post_meta( $draft_id, CleanHomeRebuilder::MANIFEST_SHA_META, true ),
			'plan_sha_meta'  => (string) get_post_meta( $draft_id, CleanHomeRebuilder::PLAN_SHA_META, true ),
			'patterns_meta'  => get_post_meta( $draft_id, CleanHomeRebuilder::PATTERNS_META, true ),
			'content_state'  => (string) get_post_meta( $draft_id, CleanHomeRebuilder::CONTENT_STATE_META, true ),
		),
		'state' => array(
			'front_before'            => $front_before,
			'front_after'             => (int) get_option( 'page_on_front', 0 ),
			'source_title'            => get_the_title( $source ),
			'source_sha_before'       => $source_sha_before,
			'source_sha_after'        => $source_after instanceof WP_Post ? hash( 'sha256', (string) $source_after->post_content ) : '',
			'plugins_before'          => $plugins_before,
			'plugins_after'           => $plugins_after,
			'private_pages_before'    => count( $drafts_before ),
			'private_pages_after'     => count( $drafts_after ),
			'active_preset'           => seo_geo_theme_active_preset_id(),
			'stylesheet'              => get_stylesheet(),
		),
	),
	JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
);
PHP

docker cp "$CLEAN_HOME_RUNNER" "$WP_CONTAINER":/var/www/html/wp-content/clean-home-rebuild-runner.php \
  || fail_smoke "clean-home-runner-copy" "Could not copy clean Home runner" "runner copied" "docker cp failed"

if ! CLEAN_HOME_REPORT="$(wp_cli eval-file /var/www/html/wp-content/clean-home-rebuild-runner.php 2>"$TMP_DIR/clean-home-rebuild.stderr")"; then
  CLEAN_HOME_ERROR="$(tr -d '\r' <"$TMP_DIR/clean-home-rebuild.stderr" | head -c 1600)"
  fail_smoke "clean-home-runner" "Clean Corporate Home runner failed" "JSON clean Home report" "${CLEAN_HOME_ERROR:-wp eval-file failed}"
fi

printf '%s' "$CLEAN_HOME_REPORT" >"$TMP_DIR/clean-home-rebuild-report.json"

if ! python3 - "$TMP_DIR/clean-home-rebuild-report.json" <<'PY'
import json
import sys

with open(sys.argv[1], "r", encoding="utf-8") as handle:
    report = json.load(handle)

plan = report["plan"]
created = report["created"]
replay = report["replay"]
draft = report["draft"]
state = report["state"]

expected_patterns = [
    "seo-geo-theme/corporate-native-hero",
    "seo-geo-theme/corporate-native-capabilities",
    "seo-geo-theme/corporate-native-proof",
    "seo-geo-theme/corporate-case-study",
    "seo-geo-theme/corporate-native-process",
    "seo-geo-theme/corporate-native-insights",
    "seo-geo-theme/cta",
]

assert plan["ready"] is True
assert plan["mode"] == "reset-rebuild-clean-home-plan"
assert plan["preset"] == "corporate"
assert plan["page_key"] == "home"
assert plan["patterns"] == expected_patterns
assert plan["content_state"] == "preset-scaffold"
assert plan["safety"] == {
    "legacy_layout_reused": False,
    "content_remap_required": False,
    "source_post_mutation": False,
    "front_page_assignment_change": False,
    "draft_only": True,
}
assert len(plan["plan_sha256"]) == 64

assert created["status"] == "created"
assert replay["status"] == "existing"
assert created["draft_id"] == replay["draft_id"]
assert created["source_id"] == plan["source"]["id"]
assert created["safety"]["source_unchanged"] is True
assert created["safety"]["front_page_id_unchanged"] is True
assert created["safety"]["draft_only"] is True

assert draft["status"] == "draft"
assert draft["title"] == state["source_title"]
assert draft["source_id_meta"] == plan["source"]["id"]
assert draft["source_sha_meta"] == plan["source"]["content_sha256"]
assert draft["manifest_sha_meta"] == plan["manifest_sha256"]
assert draft["plan_sha_meta"] == plan["plan_sha256"]
assert draft["patterns_meta"] == expected_patterns
assert draft["content_state"] == "preset-scaffold"

content_lower = draft["content"].lower()
for forbidden in ("[et_pb_", "et_pb_", "elementor-", "[vc_", "fusion-builder", "seo-geo-remap-slot:"):
    assert forbidden not in content_lower

for marker in (
    "seo-geo-corporate-native-hero",
    "seo-geo-corporate-native-capabilities",
    "seo-geo-corporate-native-process",
):
    assert marker in draft["content"]

assert state["front_before"] == state["front_after"] == plan["source"]["id"]
assert state["source_sha_before"] == state["source_sha_after"] == plan["source"]["content_sha256"]
assert state["plugins_before"] == state["plugins_after"]
assert state["private_pages_after"] == state["private_pages_before"] + 1
assert state["active_preset"] == "corporate"
assert state["stylesheet"] == "seo-geo-theme"
PY
then
  fail_smoke "clean-home-assertions" "Clean Corporate Home violated reset-first native-draft invariants" "all assertions pass" "assertion failure"
fi

printf '[smoke] Clean Corporate Home OK: one idempotent private draft composed only from Corporate Theme patterns; source/front-page assignment/plugins unchanged; no legacy builder/remap tokens.\n'
