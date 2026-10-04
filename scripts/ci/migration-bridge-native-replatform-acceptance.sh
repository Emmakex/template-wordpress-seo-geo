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
use SeoGeo\MigrationBridge\Replatform\ReviewedRemapApplier;

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

$applier = Plugin::native_replatform_remap_applier();
if ( ! $applier instanceof ReviewedRemapApplier ) {
	throw new RuntimeException( 'Reviewed Remap applier did not initialize.' );
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

$remap_assets = is_array( $plan['content_remap']['assets'] ?? null ) ? $plan['content_remap']['assets'] : array();
$value_unit_id = '';
foreach ( is_array( $remap_assets['units'] ?? null ) ? $remap_assets['units'] : array() as $unit ) {
	if ( is_array( $unit ) && isset( $unit['id'], $unit['text'] ) && is_string( $unit['id'] ) && is_string( $unit['text'] ) && str_contains( $unit['text'], 'Preserved source copy' ) ) {
		$value_unit_id = $unit['id'];
		break;
	}
}

$internal_link_id = '';
$evidence_link_id = '';
foreach ( is_array( $remap_assets['links'] ?? null ) ? $remap_assets['links'] : array() as $link ) {
	if ( ! is_array( $link ) || ! isset( $link['id'], $link['url'] ) || ! is_string( $link['id'] ) || ! is_string( $link['url'] ) ) {
		continue;
	}
	if ( '/services/' === $link['url'] ) {
		$internal_link_id = $link['id'];
	}
	if ( 'https://example.org/evidence' === $link['url'] ) {
		$evidence_link_id = $link['id'];
	}
}

if ( '' === $value_unit_id || '' === $internal_link_id || '' === $evidence_link_id ) {
	throw new RuntimeException( 'Reviewed Remap source fixture assets were not discovered.' );
}

$reviewed_selections = array(
	'value-proposition' => array(
		'units' => array( $value_unit_id ),
	),
	'verified-proof' => array(
		'links' => array( $evidence_link_id ),
	),
	'primary-cta' => array(
		'links' => array( $internal_link_id ),
	),
);

$blocked_sensitive = $applier->plan( $draft_id, $reviewed_selections, array() );
$invalid_candidate = $applier->plan(
	$draft_id,
	array(
		'value-proposition' => array(
			'units' => array( 'u-not-a-candidate' ),
		),
	),
	array()
);

$source_drift_update = wp_update_post(
	array(
		'ID'           => $home_id,
		'post_content' => (string) $source_before?->post_content . "\n<!-- wp:paragraph --><p>Temporary CI drift sentinel.</p><!-- /wp:paragraph -->",
	),
	true
);
if ( is_wp_error( $source_drift_update ) ) {
	throw new RuntimeException( $source_drift_update->get_error_message() );
}
$blocked_source_drift = $applier->plan( $draft_id, $reviewed_selections, array( 'verified-proof' ) );

$source_restore = wp_update_post(
	array(
		'ID'           => $home_id,
		'post_content' => (string) $source_before?->post_content,
	),
	true
);
if ( is_wp_error( $source_restore ) ) {
	throw new RuntimeException( $source_restore->get_error_message() );
}

$draft_before_drift = get_post( $draft_id );
if ( ! $draft_before_drift instanceof WP_Post ) {
	throw new RuntimeException( 'Native draft disappeared before marker drift acceptance.' );
}

$value_marker          = NativeCompositionService::slot_marker( 'value-proposition' );
$draft_drifted_content = str_replace( $value_marker, '', (string) $draft_before_drift->post_content, $removed_markers );
if ( 1 !== $removed_markers ) {
	throw new RuntimeException( 'Expected exactly one value-proposition marker before marker drift acceptance.' );
}

$draft_drift_update = wp_update_post(
	array(
		'ID'           => $draft_id,
		'post_content' => $draft_drifted_content,
	),
	true
);
if ( is_wp_error( $draft_drift_update ) ) {
	throw new RuntimeException( $draft_drift_update->get_error_message() );
}
$blocked_slot_drift = $applier->plan( $draft_id, $reviewed_selections, array( 'verified-proof' ) );

$draft_restore = wp_update_post(
	array(
		'ID'           => $draft_id,
		'post_content' => (string) $draft_before_drift->post_content,
	),
	true
);
if ( is_wp_error( $draft_restore ) ) {
	throw new RuntimeException( $draft_restore->get_error_message() );
}

$apply_plan = $applier->plan( $draft_id, $reviewed_selections, array( 'verified-proof' ) );
$apply_first = $applier->apply( $draft_id, $reviewed_selections, array( 'verified-proof' ) );
if ( is_wp_error( $apply_first ) ) {
	throw new RuntimeException( $apply_first->get_error_code() . ': ' . $apply_first->get_error_message() );
}
$apply_second = $applier->apply( $draft_id, $reviewed_selections, array( 'verified-proof' ) );
if ( is_wp_error( $apply_second ) ) {
	throw new RuntimeException( $apply_second->get_error_code() . ': ' . $apply_second->get_error_message() );
}

$draft        = get_post( $draft_id );
$source_after = get_post( $home_id );
$ledger       = get_post_meta( $draft_id, ReviewedRemapApplier::LEDGER_META, true );
$backup       = get_post_meta( $draft_id, ReviewedRemapApplier::BACKUP_META, true );

echo wp_json_encode(
	array(
		'plan' => array(
			'ready'          => $plan['ready'] ?? null,
			'mode'           => $plan['mode'] ?? null,
			'preset'         => $plan['preset'] ?? null,
			'page_key'       => $plan['page_key'] ?? null,
			'source'         => $plan['source'] ?? null,
			'patterns'       => $plan['patterns'] ?? null,
			'remap_slots'    => $plan['remap_slots'] ?? null,
			'content_remap'  => $plan['content_remap'] ?? null,
			'plan_sha256'    => $plan['plan_sha256'] ?? null,
			'existing_draft' => $plan['existing_draft'] ?? null,
			'blockers'       => $plan['blockers'] ?? null,
			'safety'         => $plan['safety'] ?? null,
		),
		'first' => $first,
		'second' => $second,
		'blocked_sensitive' => $blocked_sensitive,
		'invalid_candidate' => $invalid_candidate,
		'blocked_source_drift' => $blocked_source_drift,
		'blocked_slot_drift' => $blocked_slot_drift,
		'apply_plan' => $apply_plan,
		'apply_first' => $apply_first,
		'apply_second' => $apply_second,
		'ledger' => $ledger,
		'backup' => array(
			'schema_version' => is_array( $backup ) ? ( $backup['schema_version'] ?? null ) : null,
			'sha256'         => is_array( $backup ) ? ( $backup['sha256'] ?? null ) : null,
			'has_content'    => is_array( $backup ) && isset( $backup['content'] ) && is_string( $backup['content'] ) && '' !== $backup['content'],
		),
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
expected_remap_slots = [
    {"section": "value-proposition", "after_pattern": "seo-geo-theme/corporate-native-hero"},
    {"section": "service-overview", "after_pattern": "seo-geo-theme/corporate-native-capabilities"},
    {"section": "organization-context", "after_pattern": "seo-geo-theme/corporate-case-study"},
    {"section": "verified-proof", "after_pattern": "seo-geo-theme/corporate-native-proof"},
    {"section": "primary-cta", "after_pattern": "seo-geo-theme/cta"},
]
assert plan["remap_slots"] == expected_remap_slots
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

blocked = report["blocked_sensitive"]
assert blocked["ready"] is False
assert "section-verification-required:verified-proof" in blocked["blockers"]

invalid = report["invalid_candidate"]
assert invalid["ready"] is False
assert "asset-not-candidate:value-proposition:u-not-a-candidate" in invalid["blockers"]

source_drift = report["blocked_source_drift"]
assert source_drift["ready"] is False
assert "source-content-drift" in source_drift["blockers"]
assert "native-plan-drift" in source_drift["blockers"]
assert "content-remap-plan-drift" in source_drift["blockers"]

slot_drift = report["blocked_slot_drift"]
assert slot_drift["ready"] is False
assert "native-slot-marker-count:value-proposition:0" in slot_drift["blockers"]

apply_plan = report["apply_plan"]
assert apply_plan["ready"] is True
assert apply_plan["mode"] == "reviewed-remap-apply-plan"
assert apply_plan["existing"] is False
assert apply_plan["verified_sections"] == ["verified-proof"]
assert apply_plan["safety"] == {
    "sandbox_only": True,
    "draft_only": True,
    "source_post_mutation": False,
    "candidate_only": True,
    "sensitive_requires_verification": True,
    "public_post_creation": False,
}

applied = report["apply_first"]
replayed = report["apply_second"]
assert applied["status"] == "applied"
assert replayed["status"] == "existing"
assert applied["draft_id"] == replayed["draft_id"]
assert applied["selection_sha256"] == replayed["selection_sha256"]
assert applied["safety"]["source_unchanged"] is True
assert applied["safety"]["draft_only"] is True
assert applied["safety"]["public_mutation"] is False

ledger = report["ledger"]
assert ledger["selection_sha256"] == applied["selection_sha256"]
assert ledger["source_id"] == report["source"]["id"]
assert ledger["verified_sections"] == ["verified-proof"]
assert len(ledger["before_sha256"]) == 64
assert len(ledger["after_sha256"]) == 64
assert report["backup"]["schema_version"] == 1
assert len(report["backup"]["sha256"]) == 64
assert report["backup"]["has_content"] is True

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
assert "Preserved source copy" in draft["content"]
assert "https://example.org/evidence" in draft["content"]
assert "/services/" in draft["content"]
assert "seo-geo-remap-slot:value-proposition" not in draft["content"]
assert "seo-geo-remap-slot:verified-proof" not in draft["content"]
assert "seo-geo-remap-slot:primary-cta" not in draft["content"]
assert "seo-geo-remap-slot:service-overview" in draft["content"]
assert "seo-geo-remap-slot:organization-context" in draft["content"]

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

printf '[smoke] Native Replatform Composer OK: native draft created once, reviewed candidate-only remap rejected source/slot drift, applied idempotently to deterministic slots, evidence required explicit verification, source remained untouched and public mutation stayed locked.\n'
