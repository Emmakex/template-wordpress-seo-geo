#!/usr/bin/env bash
# Native Replatform Composer acceptance.
#
# Requires the sandbox marker from the preceding Migration Bridge sandbox lab
# and the Corporate preset active in the disposable WordPress fixture.

printf '[smoke] Checking non-destructive Native Replatform Composer.\n'

REPLATFORM_RUNNER="$TMP_DIR/native-replatform-runner.php"
cat >"$REPLATFORM_RUNNER" <<'PHP'
<?php

use SeoGeo\MigrationBridge\Plugin;
use SeoGeo\MigrationBridge\Replatform\NativeCompositionService;

wp_set_current_user( 1 );
update_option( 'seo_geo_active_preset', 'corporate', false );

$home_id = wp_insert_post(
	array(
		'post_type'    => 'page',
		'post_status'  => 'publish',
		'post_title'   => 'Preserved Source Home',
		'post_name'    => 'preserved-source-home',
		'post_content' => '<!-- wp:heading {"level":2} --><h2 class="wp-block-heading">Digital services</h2><!-- /wp:heading -->
<!-- wp:paragraph --><p>Preserved source copy for businesses that need SEO, GEO and automation.</p><!-- /wp:paragraph -->
<!-- wp:list --><ul><li>SEO audits</li><li>Automation delivery</li></ul><!-- /wp:list -->
<!-- wp:paragraph --><p>Explore <a href="/services/">our services</a> and review <a href="https://example.org/evidence">an external evidence source</a>.</p><!-- /wp:paragraph -->',
	),
	true
);
if ( is_wp_error( $home_id ) ) {
	throw new RuntimeException( $home_id->get_error_message() );
}

update_option( 'show_on_front', 'page', false );
update_option( 'page_on_front', $home_id, false );

$source_before      = get_post( $home_id );
$source_before_hash = hash( 'sha256', (string) $source_before?->post_content );
$source_before_slug = (string) $source_before?->post_name;
$source_before_path = (string) wp_parse_url( (string) get_permalink( $home_id ), PHP_URL_PATH );

$composer = Plugin::native_replatform_composer();
if ( ! $composer instanceof NativeCompositionService ) {
	throw new RuntimeException( 'Native Replatform composer did not initialize.' );
}

$plan   = $composer->plan( 'home', 'corporate' );
$first  = $composer->create_draft( 'home', 'corporate' );
$second = $composer->create_draft( 'home', 'corporate' );

if ( is_wp_error( $first ) ) {
	throw new RuntimeException( $first->get_error_code() . ': ' . $first->get_error_message() );
}
if ( is_wp_error( $second ) ) {
	throw new RuntimeException( $second->get_error_code() . ': ' . $second->get_error_message() );
}

$draft_id = (int) ( $first['draft_id'] ?? 0 );
$draft    = get_post( $draft_id );
$source_after = get_post( $home_id );

echo wp_json_encode(
	array(
		'plan' => array(
			'ready'          => $plan['ready'] ?? null,
			'mode'           => $plan['mode'] ?? null,
			'preset'         => $plan['preset'] ?? null,
			'page_key'       => $plan['page_key'] ?? null,
			'source'         => $plan['source'] ?? null,
			'patterns'       => $plan['patterns'] ?? null,
			'content_remap'  => $plan['content_remap'] ?? null,
			'plan_sha256'    => $plan['plan_sha256'] ?? null,
			'existing_draft' => $plan['existing_draft'] ?? null,
			'blockers'       => $plan['blockers'] ?? null,
			'safety'         => $plan['safety'] ?? null,
		),
		'first' => $first,
		'second' => $second,
		'draft' => array(
			'id'             => $draft?->ID,
			'status'         => $draft?->post_status,
			'post_name'      => $draft?->post_name,
			'title'          => $draft?->post_title,
			'content'        => $draft?->post_content,
			'source_id'      => (int) get_post_meta( $draft_id, NativeCompositionService::SOURCE_ID_META, true ),
			'source_sha256'  => (string) get_post_meta( $draft_id, NativeCompositionService::SOURCE_SHA_META, true ),
			'source_path'    => (string) get_post_meta( $draft_id, NativeCompositionService::SOURCE_PATH_META, true ),
			'preset'         => (string) get_post_meta( $draft_id, NativeCompositionService::PRESET_META, true ),
			'page_key'       => (string) get_post_meta( $draft_id, NativeCompositionService::PAGE_KEY_META, true ),
			'plan_sha256'    => (string) get_post_meta( $draft_id, NativeCompositionService::PLAN_SHA_META, true ),
			'composition'    => get_post_meta( $draft_id, NativeCompositionService::COMPOSITION_META, true ),
			'remap_plan_sha256' => (string) get_post_meta( $draft_id, NativeCompositionService::REMAP_PLAN_SHA_META, true ),
			'remap_summary'  => get_post_meta( $draft_id, NativeCompositionService::REMAP_SUMMARY_META, true ),
		),
		'source' => array(
			'id'           => $source_after?->ID,
			'status'       => $source_after?->post_status,
			'post_name'    => $source_after?->post_name,
			'path'         => (string) wp_parse_url( (string) get_permalink( $home_id ), PHP_URL_PATH ),
			'content'      => $source_after?->post_content,
			'before_hash'  => $source_before_hash,
			'after_hash'   => hash( 'sha256', (string) $source_after?->post_content ),
			'before_slug'  => $source_before_slug,
			'before_path'  => $source_before_path,
		),
	),
	JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
);
PHP

docker cp "$REPLATFORM_RUNNER" "$WP_CONTAINER":/var/www/html/wp-content/native-replatform-runner.php \
  || fail_smoke "native-replatform-runner-copy" "Could not copy Native Replatform runner" "runner copied" "docker cp failed"

if ! REPLATFORM_REPORT="$(wp_cli eval-file /var/www/html/wp-content/native-replatform-runner.php 2>"$TMP_DIR/native-replatform.stderr")"; then
  REPLATFORM_ERROR="$(tr -d '\r' <"$TMP_DIR/native-replatform.stderr" | head -c 900)"
  fail_smoke "native-replatform-runner" "Native Replatform runner failed" "JSON replatform report" "${REPLATFORM_ERROR:-wp eval-file failed}"
fi

printf '%s' "$REPLATFORM_REPORT" >"$TMP_DIR/native-replatform-report.json"

if ! REPLATFORM_ASSERTION="$(python3 - "$TMP_DIR/native-replatform-report.json" <<'PY'
import json
import sys

with open(sys.argv[1], "r", encoding="utf-8") as handle:
    report = json.load(handle)

plan = report["plan"]
assert plan["ready"] is True
assert plan["mode"] == "native-replatform-plan"
assert plan["preset"] == "corporate"
assert plan["page_key"] == "home"
assert plan["existing_draft"] == 0
assert plan["blockers"] == []
assert plan["safety"] == {
    "sandbox_only": True,
    "source_post_mutation": False,
    "public_post_creation": False,
    "legacy_visual_parity": False,
    "native_draft_only": True,
    "canonical_change_allowed": False,
}

expected_patterns = [
    "seo-geo-theme/corporate-native-hero",
    "seo-geo-theme/corporate-native-capabilities",
    "seo-geo-theme/corporate-native-proof",
    "seo-geo-theme/corporate-case-study",
    "seo-geo-theme/corporate-native-process",
    "seo-geo-theme/corporate-native-insights",
    "seo-geo-theme/cta",
]
assert plan["patterns"] == expected_patterns
assert len(plan["plan_sha256"]) == 64

remap = plan["content_remap"]
assert remap["mode"] == "content-remap-plan"
assert remap["status"] == "review-required"
assert len(remap["source_sha256"]) == 64
assert len(remap["assets_sha256"]) == 64
assert len(remap["plan_sha256"]) == 64
assert remap["counts"]["units"] >= 4
assert remap["counts"]["links"] == 2
assert remap["counts"]["required_sections"] == 5
assert remap["counts"]["manual_review_sections"] == 1
assert remap["manual_review"] == ["verified-proof"]
assert remap["safety"] == {
    "read_only": True,
    "source_post_mutation": False,
    "generated_factual_copy": False,
    "legacy_layout_preservation": False,
    "evidence_requires_review": True,
    "uncertain_mapping_auto_apply": False,
}
slot_by_section = {slot["section"]: slot for slot in remap["slots"]}
assert slot_by_section["verified-proof"]["status"] == "manual-review"
assert slot_by_section["verified-proof"]["requires_verification"] is True
assert slot_by_section["verified-proof"]["auto_apply"] is False
assert all(slot["auto_apply"] is False for slot in remap["slots"])
assert any("Preserved source copy" in unit["text"] for unit in remap["assets"]["units"])
links = {item["url"]: item for item in remap["assets"]["links"]}
assert links["/services/"]["internal"] is True
assert links["/services/"]["kind"] == "internal"
assert links["https://example.org/evidence"]["internal"] is False
assert links["https://example.org/evidence"]["kind"] == "external"

first = report["first"]
second = report["second"]
assert first["status"] == "created"
assert second["status"] == "existing"
assert first["draft_id"] == second["draft_id"]
assert first["source_id"] == report["source"]["id"]
assert first["page_key"] == "home"
assert first["preset"] == "corporate"
assert first["plan_sha256"] == plan["plan_sha256"]
assert first["content_remap_plan_sha256"] == remap["plan_sha256"]
assert second["content_remap_plan_sha256"] == remap["plan_sha256"]
assert first["safety"]["source_unchanged"] is True
assert first["safety"]["draft_only"] is True

draft = report["draft"]
assert draft["status"] == "draft"
assert draft["source_id"] == report["source"]["id"]
assert draft["source_sha256"] == report["source"]["before_hash"]
assert draft["source_path"] == report["source"]["before_path"]
assert draft["preset"] == "corporate"
assert draft["page_key"] == "home"
assert draft["plan_sha256"] == plan["plan_sha256"]
assert draft["remap_plan_sha256"] == remap["plan_sha256"]
assert draft["remap_summary"]["counts"] == remap["counts"]
assert draft["remap_summary"]["manual_review"] == ["verified-proof"]
assert draft["composition"] == expected_patterns
assert "seo-geo-corporate-native-hero" in draft["content"]
assert "seo-geo-corporate-native-capabilities" in draft["content"]
assert "seo-geo-corporate-native-proof" in draft["content"]
assert "seo-geo-corporate-native-process" in draft["content"]
assert "seo-geo-corporate-native-insights" in draft["content"]
assert "Preserved source copy" not in draft["content"]

source = report["source"]
assert source["status"] == "publish"
assert source["before_hash"] == source["after_hash"]
assert source["before_slug"] == source["post_name"]
assert source["before_path"] == source["path"]
assert "Preserved source copy" in source["content"]
assert "https://example.org/evidence" in source["content"]

print("ok")
PY
)"; then
  fail_smoke "native-replatform-assertions" "Native Replatform composer violated draft/source/composition invariants" "all assertions pass" "assertion failure"
fi

printf '[smoke] Native Replatform Composer OK: native draft created once, source URL/content untouched, Content Remap inventory/candidates are provenance-bound, evidence remains manual-review and public mutation remains locked.\n'
