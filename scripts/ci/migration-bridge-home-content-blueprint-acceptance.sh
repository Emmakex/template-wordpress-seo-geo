#!/usr/bin/env bash
# Portable Corporate Home Content Blueprint acceptance.

printf '[smoke] Checking portable Corporate Home Content Blueprint import.\n'

BLUEPRINT_RUNNER="$TMP_DIR/corporate-home-content-blueprint-runner.php"
cat >"$BLUEPRINT_RUNNER" <<'PHP'
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
	throw new RuntimeException( 'Portable Content Blueprint services did not initialize.' );
}

$home_plan = $builder->plan();
$draft_id  = (int) ( $home_plan['existing_draft'] ?? 0 );
$draft     = 0 < $draft_id ? get_post( $draft_id ) : null;
if ( ! $draft instanceof WP_Post ) {
	throw new RuntimeException( 'Clean Home draft is unavailable for Content Blueprint acceptance.' );
}

$existing_kit = $kit->saved();
if ( ! is_array( $existing_kit ) || ! is_array( $existing_kit['values'] ?? null ) ) {
	throw new RuntimeException( 'The accepted Content Kit fixture is unavailable for portable Blueprint acceptance.' );
}

if ( NativeHomeHydrator::CONTENT_STATE === (string) get_post_meta( $draft_id, CleanHomeRebuilder::CONTENT_STATE_META, true ) ) {
	$rollback = $hydrator->rollback();
	if ( $rollback instanceof WP_Error ) {
		throw new RuntimeException( $rollback->get_error_code() . ': ' . $rollback->get_error_message() );
	}
}

$source_id      = (int) get_post_meta( $draft_id, CleanHomeRebuilder::SOURCE_ID_META, true );
$source_before  = (string) get_post_field( 'post_content', $source_id );
$front_before   = (int) get_option( 'page_on_front', 0 );
$plugins_before = array_values( array_map( 'strval', get_option( 'active_plugins', array() ) ) );
sort( $plugins_before );

$locale = function_exists( 'seo_geo_theme_preset_locale' ) ? (string) seo_geo_theme_preset_locale() : get_locale();
$blueprint = array(
	'schema_version'  => CorporateHomeContentKit::BLUEPRINT_SCHEMA_VERSION,
	'mode'            => CorporateHomeContentKit::BLUEPRINT_MODE,
	'model'           => CorporateHomeContentKit::MODEL,
	'locale'          => $locale,
	'values'          => $existing_kit['values'],
	'verified_groups' => is_array( $existing_kit['verified_groups'] ?? null ) ? $existing_kit['verified_groups'] : array(),
);

$runtime_bound             = $blueprint;
$runtime_bound['draft_id'] = $draft_id;
$runtime_bound_result      = $kit->validate_blueprint( $runtime_bound );

$wrong_locale           = $blueprint;
$wrong_locale['locale'] = 'zz_ZZ';
$wrong_locale_result    = $kit->validate_blueprint( $wrong_locale );

$unknown_slot                               = $blueprint;
$unknown_slot['values']['unknown-slot-v1'] = 'must fail';
$unknown_slot_result                        = $kit->validate_blueprint( $unknown_slot );

$incomplete = $blueprint;
unset( $incomplete['values']['final-cta-heading'] );
$incomplete_result = $kit->validate_blueprint( $incomplete );

$validated_blueprint = $kit->validate_blueprint( $blueprint );
if ( $validated_blueprint instanceof WP_Error ) {
	throw new RuntimeException( $validated_blueprint->get_error_code() . ': ' . $validated_blueprint->get_error_message() );
}

$imported = $kit->import_blueprint(
	$blueprint,
	$draft_id,
	(string) ( $home_plan['plan_sha256'] ?? '' )
);
if ( $imported instanceof WP_Error ) {
	throw new RuntimeException( $imported->get_error_code() . ': ' . $imported->get_error_message() );
}

$reimported = $kit->import_blueprint(
	$blueprint,
	$draft_id,
	(string) ( $home_plan['plan_sha256'] ?? '' )
);
if ( $reimported instanceof WP_Error ) {
	throw new RuntimeException( $reimported->get_error_code() . ': ' . $reimported->get_error_message() );
}

$hydrated = $hydrator->apply();
if ( $hydrated instanceof WP_Error ) {
	throw new RuntimeException( $hydrated->get_error_code() . ': ' . $hydrated->get_error_message() );
}

$hydrated_post    = get_post( $draft_id );
$hydrated_content = $hydrated_post instanceof WP_Post ? (string) $hydrated_post->post_content : '';
$plugins_after    = array_values( array_map( 'strval', get_option( 'active_plugins', array() ) ) );
sort( $plugins_after );

echo wp_json_encode(
	array(
		'negative'  => array(
			'runtime_bound' => $runtime_bound_result instanceof WP_Error ? $runtime_bound_result->get_error_code() : 'accepted',
			'wrong_locale'   => $wrong_locale_result instanceof WP_Error ? $wrong_locale_result->get_error_code() : 'accepted',
			'unknown_slot'   => $unknown_slot_result instanceof WP_Error ? $unknown_slot_result->get_error_code() : 'accepted',
			'incomplete'     => $incomplete_result instanceof WP_Error ? $incomplete_result->get_error_code() : 'accepted',
		),
		'blueprint' => $validated_blueprint,
		'imported'  => $imported,
		'reimported'=> $reimported,
		'hydrated'  => $hydrated,
		'content'   => array(
			'sha256' => hash( 'sha256', $hydrated_content ),
			'html'   => $hydrated_content,
		),
		'safety'    => array(
			'source_unchanged' => hash_equals( hash( 'sha256', $source_before ), hash( 'sha256', (string) get_post_field( 'post_content', $source_id ) ) ),
			'front_unchanged'  => $front_before === (int) get_option( 'page_on_front', 0 ),
			'plugins_unchanged'=> $plugins_before === $plugins_after,
			'draft_only'       => 'draft' === get_post_status( $draft_id ),
		),
	),
	JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
);
PHP

docker cp "$BLUEPRINT_RUNNER" "$WP_CONTAINER":/var/www/html/wp-content/corporate-home-content-blueprint-runner.php \
  || fail_smoke "home-content-blueprint-runner-copy" "Could not copy Content Blueprint runner" "runner copied" "docker cp failed"

if ! BLUEPRINT_REPORT="$(wp_cli eval-file /var/www/html/wp-content/corporate-home-content-blueprint-runner.php 2>"$TMP_DIR/corporate-home-content-blueprint.stderr")"; then
  BLUEPRINT_ERROR="$(tr -d '\r' <"$TMP_DIR/corporate-home-content-blueprint.stderr" | head -c 1800)"
  fail_smoke "home-content-blueprint-runner" "Portable Content Blueprint runner failed" "JSON Blueprint report" "${BLUEPRINT_ERROR:-wp eval-file failed}"
fi

printf '%s' "$BLUEPRINT_REPORT" >"$TMP_DIR/corporate-home-content-blueprint-report.json"

if ! python3 - "$TMP_DIR/corporate-home-content-blueprint-report.json" <<'PY'
import json
import sys

with open(sys.argv[1], "r", encoding="utf-8") as handle:
    report = json.load(handle)

negative = report["negative"]
blueprint = report["blueprint"]
imported = report["imported"]
reimported = report["reimported"]
hydrated = report["hydrated"]
content = report["content"]
safety = report["safety"]

assert negative == {
    "runtime_bound": "seo_geo_home_blueprint_runtime_identity",
    "wrong_locale": "seo_geo_home_blueprint_locale_mismatch",
    "unknown_slot": "seo_geo_home_blueprint_unknown_slot",
    "incomplete": "seo_geo_home_blueprint_incomplete",
}

assert blueprint["schema_version"] == 1
assert blueprint["mode"] == "corporate-home-content-blueprint"
assert blueprint["model"] == "corporate-home-v1"
assert len(blueprint["blueprint_sha256"]) == 64
assert all(key not in blueprint for key in ("draft_id", "source_id", "plan_sha256", "kit_sha256", "saved_at"))

assert imported["input_mode"] == "blueprint"
assert reimported["input_mode"] == "blueprint"
assert imported["blueprint_sha256"] == blueprint["blueprint_sha256"]
assert reimported["blueprint_sha256"] == blueprint["blueprint_sha256"]
assert imported["kit_sha256"] == reimported["kit_sha256"]
assert imported["draft_id"] == reimported["draft_id"]
assert len(imported["kit_sha256"]) == 64

assert hydrated["status"] == "applied"
assert hydrated["kit_sha256"] == imported["kit_sha256"]
assert len(content["sha256"]) == 64
assert "Digital growth systems" in content["html"]
assert "Turn the next digital bottleneck into a working system" in content["html"]

assert safety == {
    "source_unchanged": True,
    "front_unchanged": True,
    "plugins_unchanged": True,
    "draft_only": True,
}
PY
then
  fail_smoke "home-content-blueprint-assertions" "Portable Content Blueprint violated portability, validation, determinism or draft-safety invariants" "all assertions pass" "assertion failure"
fi

printf '[smoke] Portable Corporate Home Content Blueprint OK: runtime identity rejected, locale/slot/completeness enforced, deterministic import proven, native draft hydrated with source/front/plugins unchanged.\n'
