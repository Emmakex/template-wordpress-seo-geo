#!/usr/bin/env bash
# Reset/Rebuild Corporate Home Content Kit + native hydrator acceptance.

printf '[smoke] Checking Corporate Home Content Kit + native hydrator.\n'

HOME_CONTENT_RUNNER="$TMP_DIR/corporate-home-content-kit-runner.php"
cat >"$HOME_CONTENT_RUNNER" <<'PHP'
<?php

use SeoGeo\MigrationBridge\Plugin;
use SeoGeo\MigrationBridge\Reset\CleanHomeRebuilder;
use SeoGeo\MigrationBridge\Reset\CorporateHomeContentKit;
use SeoGeo\MigrationBridge\Reset\NativeHomeHydrator;

wp_set_current_user( 1 );

$builder  = Plugin::clean_home_rebuilder();
$kit      = Plugin::corporate_home_content_kit();
$hydrator = Plugin::native_home_hydrator();

if (
	! $builder instanceof CleanHomeRebuilder
	|| ! $kit instanceof CorporateHomeContentKit
	|| ! $hydrator instanceof NativeHomeHydrator
) {
	throw new RuntimeException( 'Corporate Home Content Kit services did not initialize.' );
}

$home_plan = $builder->plan();
$draft_id  = (int) ( $home_plan['existing_draft'] ?? 0 );
$draft     = 0 < $draft_id ? get_post( $draft_id ) : null;
if ( ! $draft instanceof WP_Post ) {
	throw new RuntimeException( 'Clean Home draft from the previous acceptance phase is unavailable.' );
}

delete_option( CorporateHomeContentKit::OPTION );
delete_post_meta( $draft_id, NativeHomeHydrator::BACKUP_META );
delete_post_meta( $draft_id, NativeHomeHydrator::BACKUP_SHA_META );
delete_post_meta( $draft_id, NativeHomeHydrator::KIT_SHA_META );
delete_post_meta( $draft_id, NativeHomeHydrator::APPLIED_SHA_META );
delete_post_meta( $draft_id, NativeHomeHydrator::APPLIED_AT_META );
update_post_meta( $draft_id, CleanHomeRebuilder::CONTENT_STATE_META, NativeHomeHydrator::SCAFFOLD_STATE );

$scaffold         = (string) $draft->post_content;
$scaffold_sha     = hash( 'sha256', $scaffold );
$source_id        = (int) get_post_meta( $draft_id, CleanHomeRebuilder::SOURCE_ID_META, true );
$source_before    = (string) get_post_field( 'post_content', $source_id );
$source_sha       = hash( 'sha256', $source_before );
$front_before     = (int) get_option( 'page_on_front', 0 );
$plugins_before   = array_values( array_map( 'strval', get_option( 'active_plugins', array() ) ) );
sort( $plugins_before );

$values = array(
	'hero-eyebrow'       => 'Digital growth systems',
	'hero-lead'          => 'We build fast, measurable digital platforms that connect strategy, automation and useful content.',
	'hero-primary-cta'   => array( 'label' => 'Talk to our team', 'url' => '/contact/' ),
	'capabilities-heading' => 'Three capabilities, one operating system',
	'capabilities-intro' => 'Start with the business problem and connect the right web, automation and growth capabilities.',
	'capability-1-title'  => 'Digital platforms',
	'capability-1-body'   => 'Modern WordPress and product experiences designed for speed, discoverability and maintainability.',
	'capability-1-link'   => array( 'label' => 'Explore digital platforms', 'url' => '/services/' ),
	'capability-2-title'  => 'Automation',
	'capability-2-body'   => 'Connect repetitive workflows with practical automation that reduces manual work.',
	'capability-2-link'   => array( 'label' => 'Explore automation', 'url' => '/services/' ),
	'capability-3-title'  => 'SEO/GEO growth',
	'capability-3-body'   => 'Structure content, entities and internal links so people and answer engines can understand the offer.',
	'capability-3-link'   => array( 'label' => 'Explore SEO/GEO', 'url' => '/services/' ),
	'process-heading'     => 'A clear path from problem to improvement',
	'process-intro'       => 'Every engagement starts with context, moves through a controlled build and continues with measurable iteration.',
	'process-1-title'     => 'Understand',
	'process-1-body'      => 'Define the audience, constraints, evidence and business outcome before choosing tools.',
	'process-2-title'     => 'Build',
	'process-2-body'      => 'Ship the smallest complete solution with clear ownership, quality gates and reusable components.',
	'process-3-title'     => 'Improve',
	'process-3-body'      => 'Use search, analytics and operational feedback to prioritize the next useful change.',
	'insights-heading'    => 'Useful insights from the work',
	'insights-intro'      => 'Recent articles connect practical questions with the services, methods and decisions behind the work.',
	'final-cta-heading'   => 'Turn the next digital bottleneck into a working system',
	'final-cta-body'      => 'Tell us what needs to improve and we will start from the business requirement, not from a predefined stack.',
	'final-cta-button'    => array( 'label' => 'Start a conversation', 'url' => '/contact/' ),
);

$incomplete_verified = $kit->validate(
	array(
		'draft_id'        => $draft_id,
		'plan_sha256'     => (string) $home_plan['plan_sha256'],
		'values'          => $values,
		'verified_groups' => array(
			'hero-proof' => false,
			'proof'      => true,
			'case-study' => false,
		),
	)
);

if ( ! $incomplete_verified instanceof WP_Error || 'seo_geo_home_content_incomplete' !== $incomplete_verified->get_error_code() ) {
	throw new RuntimeException( 'Evidence-sensitive Content Kit did not reject incomplete verified proof.' );
}

$saved = $kit->save(
	array(
		'draft_id'        => $draft_id,
		'plan_sha256'     => (string) $home_plan['plan_sha256'],
		'values'          => $values,
		'verified_groups' => array(
			'hero-proof' => false,
			'proof'      => false,
			'case-study' => false,
		),
	)
);
if ( $saved instanceof WP_Error ) {
	throw new RuntimeException( $saved->get_error_code() . ': ' . $saved->get_error_message() );
}

$plan_before = $hydrator->plan();
$applied     = $hydrator->apply();
$replayed    = $hydrator->apply();
if ( $applied instanceof WP_Error ) {
	throw new RuntimeException( $applied->get_error_code() . ': ' . $applied->get_error_message() );
}
if ( $replayed instanceof WP_Error ) {
	throw new RuntimeException( $replayed->get_error_code() . ': ' . $replayed->get_error_message() );
}

$hydrated_post    = get_post( $draft_id );
$hydrated_content = $hydrated_post instanceof WP_Post ? (string) $hydrated_post->post_content : '';
$hydrated_sha     = hash( 'sha256', $hydrated_content );

wp_update_post(
	array(
		'ID'           => $draft_id,
		'post_content' => $hydrated_content . "\n<!-- ci-manual-drift -->",
	)
);
$drift_plan = $hydrator->plan();

wp_update_post(
	array(
		'ID'           => $draft_id,
		'post_content' => $hydrated_content,
	)
);
$rollback = $hydrator->rollback();
if ( $rollback instanceof WP_Error ) {
	throw new RuntimeException( $rollback->get_error_code() . ': ' . $rollback->get_error_message() );
}

$rolled_back_post = get_post( $draft_id );
$rolled_back      = $rolled_back_post instanceof WP_Post ? (string) $rolled_back_post->post_content : '';

$reapplied = $hydrator->apply();
if ( $reapplied instanceof WP_Error ) {
	throw new RuntimeException( $reapplied->get_error_code() . ': ' . $reapplied->get_error_message() );
}

$final_post    = get_post( $draft_id );
$final_content = $final_post instanceof WP_Post ? (string) $final_post->post_content : '';
$plugins_after = array_values( array_map( 'strval', get_option( 'active_plugins', array() ) ) );
sort( $plugins_after );

echo wp_json_encode(
	array(
		'saved'       => $saved,
		'plan_before' => $plan_before,
		'applied'     => $applied,
		'replayed'    => $replayed,
		'drift_plan'  => $drift_plan,
		'rollback'    => $rollback,
		'reapplied'   => $reapplied,
		'content'     => array(
			'scaffold_sha256'     => $scaffold_sha,
			'hydrated_sha256'     => $hydrated_sha,
			'rolled_back_sha256'  => hash( 'sha256', $rolled_back ),
			'final_sha256'        => hash( 'sha256', $final_content ),
			'hydrated'            => $hydrated_content,
			'final'               => $final_content,
		),
		'state'       => array(
			'content_state'        => (string) get_post_meta( $draft_id, CleanHomeRebuilder::CONTENT_STATE_META, true ),
			'backup_available'     => '' !== (string) get_post_meta( $draft_id, NativeHomeHydrator::BACKUP_META, true ),
			'kit_sha_meta'         => (string) get_post_meta( $draft_id, NativeHomeHydrator::KIT_SHA_META, true ),
			'applied_sha_meta'     => (string) get_post_meta( $draft_id, NativeHomeHydrator::APPLIED_SHA_META, true ),
			'source_sha_before'    => $source_sha,
			'source_sha_after'     => hash( 'sha256', (string) get_post_field( 'post_content', $source_id ) ),
			'front_before'         => $front_before,
			'front_after'          => (int) get_option( 'page_on_front', 0 ),
			'plugins_before'       => $plugins_before,
			'plugins_after'        => $plugins_after,
			'draft_status'         => (string) get_post_status( $draft_id ),
		),
	),
	JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
);
PHP

docker cp "$HOME_CONTENT_RUNNER" "$WP_CONTAINER":/var/www/html/wp-content/corporate-home-content-kit-runner.php \
  || fail_smoke "home-content-kit-runner-copy" "Could not copy Home Content Kit runner" "runner copied" "docker cp failed"

if ! HOME_CONTENT_REPORT="$(wp_cli eval-file /var/www/html/wp-content/corporate-home-content-kit-runner.php 2>"$TMP_DIR/corporate-home-content-kit.stderr")"; then
  HOME_CONTENT_ERROR="$(tr -d '\r' <"$TMP_DIR/corporate-home-content-kit.stderr" | head -c 1800)"
  fail_smoke "home-content-kit-runner" "Corporate Home Content Kit runner failed" "JSON Home Content Kit report" "${HOME_CONTENT_ERROR:-wp eval-file failed}"
fi

printf '%s' "$HOME_CONTENT_REPORT" >"$TMP_DIR/corporate-home-content-kit-report.json"

if ! python3 - "$TMP_DIR/corporate-home-content-kit-report.json" <<'PY'
import json
import sys

with open(sys.argv[1], "r", encoding="utf-8") as handle:
    report = json.load(handle)

saved = report["saved"]
plan = report["plan_before"]
applied = report["applied"]
replayed = report["replayed"]
drift = report["drift_plan"]
rollback = report["rollback"]
reapplied = report["reapplied"]
content = report["content"]
state = report["state"]

assert saved["schema_version"] == 1
assert saved["mode"] == "corporate-home-content-kit"
assert saved["model"] == "corporate-home-v1"
assert len(saved["kit_sha256"]) == 64
assert saved["verified_groups"] == {
    "hero-proof": False,
    "proof": False,
    "case-study": False,
}

assert plan["ready"] is True
assert plan["mode"] == "corporate-home-native-hydration-plan"
assert plan["safety"] == {
    "source_post_mutation": False,
    "front_page_assignment_change": False,
    "legacy_layout_input": False,
    "rollback_available": True,
    "draft_only": True,
}

assert applied["status"] == "applied"
assert replayed["status"] == "existing"
assert applied["draft_id"] == replayed["draft_id"]
assert applied["kit_sha256"] == saved["kit_sha256"]
assert applied["safety"]["draft_only"] is True
assert applied["safety"]["front_page_id_unchanged"] is True
assert applied["safety"]["source_content_unchanged"] is True
assert applied["safety"]["rollback_available"] is True

hydrated = content["hydrated"]
expected_fragments = (
    "Digital growth systems",
    "Three capabilities, one operating system",
    "Digital platforms",
    "A clear path from problem to improvement",
    "Useful insights from the work",
    "Turn the next digital bottleneck into a working system",
    "Tell us what needs to improve and we will start from the business requirement, not from a predefined stack.",
    "Start a conversation",
    "Talk to our team",
    'href="/contact/"',
)
missing_fragments = [fragment for fragment in expected_fragments if fragment not in hydrated]
assert not missing_fragments, f"missing hydrated fragments: {missing_fragments}"

for forbidden in (
    "seo-geo-corporate-native-hero__proof",
    "seo-geo-corporate-native-proof",
    "seo-geo-corporate-case-study",
    "Acción principal",
    "Resultado verificado",
    "Proyectos seleccionados",
    "State the next useful step clearly",
    "Add the minimum supporting context a visitor needs before taking action.",
    "Take the next step",
):
    assert forbidden not in hydrated

assert "seo-geo-content-slot--hero-primary-cta" in hydrated
assert "seo-geo-content-slot--capability-1-title" in hydrated
assert "seo-geo-content-slot--final-cta-button" in hydrated

assert drift["ready"] is False
assert "hydrated-draft-drift" in drift["blockers"]

assert rollback["status"] == "rolled-back"
assert content["rolled_back_sha256"] == content["scaffold_sha256"]
assert reapplied["status"] == "applied"
assert content["final_sha256"] == content["hydrated_sha256"]

assert state["content_state"] == "content-hydrated-v1"
assert state["backup_available"] is True
assert state["kit_sha_meta"] == saved["kit_sha256"]
assert state["applied_sha_meta"] == content["final_sha256"]
assert state["source_sha_before"] == state["source_sha_after"]
assert state["front_before"] == state["front_after"]
assert state["plugins_before"] == state["plugins_after"]
assert state["draft_status"] == "draft"
PY
then
  fail_smoke "home-content-kit-assertions" "Corporate Home Content Kit/hydrator violated native-content, evidence, drift, idempotency or rollback invariants" "all assertions pass" "assertion failure"
fi

printf '[smoke] Corporate Home Content Kit + native hydrator OK: typed reviewed content applied by semantic slots, unverified evidence omitted, replay idempotent, manual drift blocked, rollback exact, source/front/plugins unchanged.\n'
