#!/usr/bin/env bash
# Clean Home native SEO handoff acceptance.

printf '[smoke] Checking clean Home native SEO/GEO handoff.\n'

SEO_HANDOFF_RUNNER="$TMP_DIR/home-native-seo-handoff-runner.php"
cat >"$SEO_HANDOFF_RUNNER" <<'PHP'
<?php

use SeoGeo\Core\Seo\IndexabilityResolver;
use SeoGeo\Core\Seo\NativeSeoMetadata;
use SeoGeo\MigrationBridge\Plugin;
use SeoGeo\MigrationBridge\Reset\CleanHomeRebuilder;
use SeoGeo\MigrationBridge\Reset\CorporateHomeContentKit;
use SeoGeo\MigrationBridge\Reset\HomeSeoHandoff;
use SeoGeo\MigrationBridge\Reset\NativeHomeHydrator;
use SeoGeo\MigrationBridge\Reset\RescueManifest;

wp_set_current_user( 1 );

$manifest = Plugin::rescue_manifest();
$builder  = Plugin::clean_home_rebuilder();
$kit      = Plugin::corporate_home_content_kit();
$hydrator = Plugin::native_home_hydrator();
$handoff  = Plugin::home_seo_handoff();

if (
	! $manifest instanceof RescueManifest
	|| ! $builder instanceof CleanHomeRebuilder
	|| ! $kit instanceof CorporateHomeContentKit
	|| ! $hydrator instanceof NativeHomeHydrator
	|| ! $handoff instanceof HomeSeoHandoff
) {
	throw new RuntimeException( 'Native Home SEO handoff services did not initialize.' );
}

$current_plan = $builder->plan();
$current_id   = (int) ( $current_plan['existing_draft'] ?? 0 );
$source_id    = 0 < $current_id ? (int) get_post_meta( $current_id, CleanHomeRebuilder::SOURCE_ID_META, true ) : 0;
if ( 0 >= $source_id ) {
	throw new RuntimeException( 'Rescued Home source is unavailable.' );
}

$source_before  = (string) get_post_field( 'post_content', $source_id );
$front_before   = (int) get_option( 'page_on_front', 0 );
$plugins_before = array_values( array_map( 'strval', get_option( 'active_plugins', array() ) ) );
sort( $plugins_before );

update_post_meta( $source_id, '_yoast_wpseo_title', 'EMMAKE | Estrategia digital y datos' );
update_post_meta( $source_id, '_yoast_wpseo_metadesc', 'Marketing digital, investigación, análisis de datos e inteligencia de negocio para transformar retos en decisiones medibles.' );
update_post_meta( $source_id, '_yoast_wpseo_canonical', get_permalink( $source_id ) );
delete_post_meta( $source_id, '_yoast_wpseo_meta-robots-noindex' );
delete_post_meta( $source_id, '_yoast_wpseo_meta-robots-nofollow' );

$manifest->capture();

$plan = $builder->plan();
if ( true !== ( $plan['ready'] ?? false ) ) {
	throw new RuntimeException( 'Clean Home plan did not remain ready after SEO recapture.' );
}

$created = $builder->create_draft();
if ( $created instanceof WP_Error ) {
	throw new RuntimeException( $created->get_error_code() . ': ' . $created->get_error_message() );
}
$draft_id = (int) ( $created['draft_id'] ?? 0 );
if ( 0 >= $draft_id ) {
	throw new RuntimeException( 'Clean Home draft for SEO handoff was not created.' );
}

$saved_kit = $kit->saved();
if ( ! is_array( $saved_kit ) || ! is_array( $saved_kit['values'] ?? null ) ) {
	throw new RuntimeException( 'Reviewed Content Kit is unavailable for SEO handoff acceptance.' );
}

$kit_result = $kit->save(
	array(
		'draft_id'        => $draft_id,
		'plan_sha256'     => (string) ( $plan['plan_sha256'] ?? '' ),
		'values'          => $saved_kit['values'],
		'verified_groups' => is_array( $saved_kit['verified_groups'] ?? null ) ? $saved_kit['verified_groups'] : array(),
	)
);
if ( $kit_result instanceof WP_Error ) {
	throw new RuntimeException( $kit_result->get_error_code() . ': ' . $kit_result->get_error_message() );
}

$hydrated = $hydrator->apply();
if ( $hydrated instanceof WP_Error ) {
	throw new RuntimeException( $hydrated->get_error_code() . ': ' . $hydrated->get_error_message() );
}

$handoff_plan = $handoff->plan();
$applied      = $handoff->apply();
$replayed     = $handoff->apply();
if ( $applied instanceof WP_Error ) {
	throw new RuntimeException( $applied->get_error_code() . ': ' . $applied->get_error_message() );
}
if ( $replayed instanceof WP_Error ) {
	throw new RuntimeException( $replayed->get_error_code() . ': ' . $replayed->get_error_message() );
}

$plugins_after = array_values( array_map( 'strval', get_option( 'active_plugins', array() ) ) );
sort( $plugins_after );

echo wp_json_encode(
	array(
		'plan'     => $handoff_plan,
		'applied'  => $applied,
		'replayed' => $replayed,
		'native'   => array(
			'title'        => (string) get_post_meta( $draft_id, NativeSeoMetadata::META_TITLE, true ),
			'description'  => (string) get_post_meta( $draft_id, NativeSeoMetadata::META_DESCRIPTION, true ),
			'canonical'    => (string) get_post_meta( $draft_id, NativeSeoMetadata::META_CANONICAL, true ),
			'indexability' => (string) get_post_meta( $draft_id, NativeSeoMetadata::META_INDEXABILITY, true ),
		),
		'safety'   => array(
			'source_unchanged'  => hash_equals( hash( 'sha256', $source_before ), hash( 'sha256', (string) get_post_field( 'post_content', $source_id ) ) ),
			'front_unchanged'   => $front_before === (int) get_option( 'page_on_front', 0 ),
			'plugins_unchanged' => $plugins_before === $plugins_after,
			'draft_only'        => 'draft' === get_post_status( $draft_id ),
		),
	),
	JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
);
PHP

docker cp "$SEO_HANDOFF_RUNNER" "$WP_CONTAINER":/var/www/html/wp-content/home-native-seo-handoff-runner.php \
  || fail_smoke "home-seo-handoff-runner-copy" "Could not copy native Home SEO handoff runner" "runner copied" "docker cp failed"

if ! SEO_HANDOFF_REPORT="$(wp_cli eval-file /var/www/html/wp-content/home-native-seo-handoff-runner.php 2>"$TMP_DIR/home-native-seo-handoff.stderr")"; then
  SEO_HANDOFF_ERROR="$(tr -d '\r' <"$TMP_DIR/home-native-seo-handoff.stderr" | head -c 1800)"
  fail_smoke "home-seo-handoff-runner" "Native Home SEO handoff runner failed" "JSON SEO handoff report" "${SEO_HANDOFF_ERROR:-wp eval-file failed}"
fi

printf '%s' "$SEO_HANDOFF_REPORT" >"$TMP_DIR/home-native-seo-handoff-report.json"

if ! python3 - "$TMP_DIR/home-native-seo-handoff-report.json" <<'PY'
import json
import sys

with open(sys.argv[1], "r", encoding="utf-8") as handle:
    report = json.load(handle)

plan = report["plan"]
applied = report["applied"]
replayed = report["replayed"]
native = report["native"]
safety = report["safety"]

assert plan["ready"] is True
assert plan["provider"] == "yoast"
assert plan["review_required"] is False
assert plan["review_items"] == []
assert plan["canonical_strategy"] == "native-self-canonical"
assert plan["overrides"]["title"] == "EMMAKE | Estrategia digital y datos"
assert plan["overrides"]["description"].startswith("Marketing digital, investigación")
assert plan["overrides"]["indexability"] == "indexable"

assert applied["status"] == "applied"
assert applied["cutover_seo_ready"] is True
assert replayed["status"] == "existing"
assert applied["report_sha256"] == replayed["report_sha256"]

assert native == {
    "title": "EMMAKE | Estrategia digital y datos",
    "description": "Marketing digital, investigación, análisis de datos e inteligencia de negocio para transformar retos en decisiones medibles.",
    "canonical": "",
    "indexability": "indexable",
}

assert safety == {
    "source_unchanged": True,
    "front_unchanged": True,
    "plugins_unchanged": True,
    "draft_only": True,
}
PY
then
  fail_smoke "home-seo-handoff-assertions" "Native Home SEO handoff violated carryover, canonical-safety, idempotency or source/front/plugin invariants" "all assertions pass" "assertion failure"
fi

printf '[smoke] Clean Home native SEO/GEO handoff OK: safe rescued title/description/indexability translated to Core metadata, self-canonical remains native, replay idempotent, source/front/plugins unchanged.\n'
