#!/usr/bin/env bash
# Phase 8E controlled Migration Engine acceptance.
#
# Sourced after Phase 8D: explicit sandbox marker, blog_public=0, destination
# theme, baseline and dependency graph prerequisites are already present.

printf '[smoke] Checking Phase 8E controlled Migration Engine.\n'

MIGRATION_RUNNER="$TMP_DIR/migration-engine-runner.php"
cat >"$MIGRATION_RUNNER" <<'PHP'
<?php

use SeoGeo\MigrationBridge\Migration\MigrationEngine;
use SeoGeo\MigrationBridge\Plugin;

wp_set_current_user( 1 );
update_option( 'seo_geo_active_preset', 'corporate', false );

$attachment_id = wp_insert_attachment(
	array(
		'post_mime_type' => 'image/jpeg',
		'post_title'     => 'Migration fixture image',
		'post_status'    => 'inherit',
		'guid'           => home_url( '/wp-content/uploads/migration-fixture.jpg' ),
	)
);
update_post_meta( $attachment_id, '_wp_attached_file', 'migration-fixture.jpg' );
update_post_meta( $attachment_id, '_wp_attachment_image_alt', 'Migration fixture alt' );

$elementor_id = wp_insert_post(
	array(
		'post_type'    => 'page',
		'post_status'  => 'publish',
		'post_title'   => 'Elementor Migration Fixture',
		'post_name'    => 'elementor-migration-fixture',
		'post_content' => 'Elementor legacy fallback marker.',
	)
);
$elementor_tree = array(
	array(
		'id'       => 'section-1',
		'elType'   => 'section',
		'elements' => array(
			array(
				'id'       => 'column-1',
				'elType'   => 'column',
				'elements' => array(
					array(
						'id'         => 'heading-1',
						'elType'     => 'widget',
						'widgetType' => 'heading',
						'settings'   => array(
							'title'       => 'Migrated Elementor heading',
							'header_size' => 'h2',
						),
					),
					array(
						'id'         => 'text-1',
						'elType'     => 'widget',
						'widgetType' => 'text-editor',
						'settings'   => array(
							'editor' => '<p>Migrated Elementor body.</p>',
						),
					),
					array(
						'id'         => 'image-1',
						'elType'     => 'widget',
						'widgetType' => 'image',
						'settings'   => array(
							'image' => array(
								'id'  => $attachment_id,
								'url' => home_url( '/wp-content/uploads/migration-fixture.jpg' ),
							),
						),
					),
					array(
						'id'         => 'button-1',
						'elType'     => 'widget',
						'widgetType' => 'button',
						'settings'   => array(
							'text' => 'Contact',
							'link' => array( 'url' => home_url( '/contact/' ) ),
						),
					),
				),
			),
		),
	),
);
update_post_meta( $elementor_id, '_elementor_edit_mode', 'builder' );
update_post_meta( $elementor_id, '_elementor_data', wp_json_encode( $elementor_tree ) );
update_post_meta( $elementor_id, '_thumbnail_id', $attachment_id );

$divi_id = wp_insert_post(
	array(
		'post_type'    => 'page',
		'post_status'  => 'publish',
		'post_title'   => 'Divi Migration Fixture',
		'post_name'    => 'divi-migration-fixture',
		'post_content' => '[et_pb_section][et_pb_row][et_pb_column][et_pb_text]<p>Migrated Divi body.</p>[/et_pb_text][et_pb_button button_text="Learn more" button_url="/learn-more/"][/et_pb_button][/et_pb_column][/et_pb_row][/et_pb_section]',
	)
);
update_post_meta( $divi_id, 'et_pb_use_builder', 'on' );

$unsupported_id = wp_insert_post(
	array(
		'post_type'    => 'page',
		'post_status'  => 'publish',
		'post_title'   => 'Unsupported Elementor Fixture',
		'post_name'    => 'unsupported-elementor-fixture',
		'post_content' => 'UNSUPPORTED SOURCE MARKER',
	)
);
$unsupported_tree = array(
	array(
		'id'         => 'form-1',
		'elType'     => 'widget',
		'widgetType' => 'form',
		'settings'   => array(
			'form_name' => 'Private form configuration marker',
		),
	),
);
update_post_meta( $unsupported_id, '_elementor_edit_mode', 'builder' );
update_post_meta( $unsupported_id, '_elementor_data', wp_json_encode( $unsupported_tree ) );

$engine = Plugin::migration_engine();
if ( ! $engine instanceof MigrationEngine ) {
	throw new RuntimeException( 'Migration Engine did not initialize.' );
}

$active_plugins_before = get_option( 'active_plugins', array() );
sort( $active_plugins_before );
$theme_before = array(
	'stylesheet' => get_option( 'stylesheet' ),
	'template'   => get_option( 'template' ),
);

$elementor_permalink_before = get_permalink( $elementor_id );
$divi_permalink_before      = get_permalink( $divi_id );
$unsupported_before         = get_post( $unsupported_id );
$unsupported_meta_before    = (string) get_post_meta( $unsupported_id, '_elementor_data', true );
$unsupported_meta_hash_before = hash( 'sha256', $unsupported_meta_before );

$elementor_plan = $engine->plan( $elementor_id, 'elementor', 'corporate' );
$divi_plan      = $engine->plan( $divi_id, 'divi', 'corporate' );
$blocked_plan   = $engine->plan( $unsupported_id, 'elementor', 'corporate' );
$mismatch_plan  = $engine->plan( $elementor_id, 'elementor', 'publisher' );

$elementor_nonce = wp_create_nonce( MigrationEngine::nonce_action( $elementor_id, 'elementor' ) );
$divi_nonce      = wp_create_nonce( MigrationEngine::nonce_action( $divi_id, 'divi' ) );
$blocked_nonce   = wp_create_nonce( MigrationEngine::nonce_action( $unsupported_id, 'elementor' ) );

$confirmation_error = $engine->execute( $elementor_id, 'elementor', $elementor_nonce, 'corporate', false );
$nonce_error        = $engine->execute( $elementor_id, 'elementor', 'invalid-nonce', 'corporate', true );
$blocked_error      = $engine->execute( $unsupported_id, 'elementor', $blocked_nonce, 'corporate', true );

$elementor_result = $engine->execute( $elementor_id, 'elementor', $elementor_nonce, 'corporate', true );
$divi_result      = $engine->execute( $divi_id, 'divi', $divi_nonce, 'corporate', true );

$elementor_after = get_post( $elementor_id );
$divi_after      = get_post( $divi_id );
$unsupported_after = get_post( $unsupported_id );

$active_plugins_after = get_option( 'active_plugins', array() );
sort( $active_plugins_after );
$theme_after = array(
	'stylesheet' => get_option( 'stylesheet' ),
	'template'   => get_option( 'template' ),
);

$elementor_backup = get_post_meta( $elementor_id, MigrationEngine::BACKUP_META, true );
$divi_backup      = get_post_meta( $divi_id, MigrationEngine::BACKUP_META, true );

echo wp_json_encode(
	array(
		'ids' => array(
			'attachment' => $attachment_id,
			'elementor'  => $elementor_id,
			'divi'       => $divi_id,
			'unsupported'=> $unsupported_id,
		),
		'plans' => array(
			'elementor' => $elementor_plan,
			'divi'      => $divi_plan,
			'blocked'   => $blocked_plan,
			'mismatch'  => $mismatch_plan,
		),
		'errors' => array(
			'confirmation' => is_wp_error( $confirmation_error ) ? $confirmation_error->get_error_code() : null,
			'nonce'        => is_wp_error( $nonce_error ) ? $nonce_error->get_error_code() : null,
			'blocked'      => is_wp_error( $blocked_error ) ? $blocked_error->get_error_code() : null,
		),
		'results' => array(
			'elementor' => $elementor_result,
			'divi'      => $divi_result,
		),
		'after' => array(
			'elementor' => array(
				'id'             => $elementor_after?->ID,
				'post_name'      => $elementor_after?->post_name,
				'permalink'      => get_permalink( $elementor_id ),
				'content'        => $elementor_after?->post_content,
				'edit_mode'      => get_post_meta( $elementor_id, '_elementor_edit_mode', true ),
				'elementor_data_sha256' => hash( 'sha256', (string) get_post_meta( $elementor_id, '_elementor_data', true ) ),
				'thumbnail_id'   => (int) get_post_meta( $elementor_id, '_thumbnail_id', true ),
				'backup_exists'  => is_array( $elementor_backup ),
				'backup_sha256'  => is_array( $elementor_backup ) && is_string( $elementor_backup['sha256'] ?? null ) ? $elementor_backup['sha256'] : null,
				'state_exists'   => metadata_exists( 'post', $elementor_id, MigrationEngine::STATE_META ),
			),
			'divi' => array(
				'id'            => $divi_after?->ID,
				'post_name'     => $divi_after?->post_name,
				'permalink'     => get_permalink( $divi_id ),
				'content'       => $divi_after?->post_content,
				'builder_mode'  => get_post_meta( $divi_id, 'et_pb_use_builder', true ),
				'backup_exists' => is_array( $divi_backup ),
				'backup_sha256' => is_array( $divi_backup ) && is_string( $divi_backup['sha256'] ?? null ) ? $divi_backup['sha256'] : null,
				'state_exists'  => metadata_exists( 'post', $divi_id, MigrationEngine::STATE_META ),
			),
			'unsupported' => array(
				'id'             => $unsupported_after?->ID,
				'post_name'      => $unsupported_after?->post_name,
				'content'        => $unsupported_after?->post_content,
				'elementor_data_sha256' => hash( 'sha256', (string) get_post_meta( $unsupported_id, '_elementor_data', true ) ),
				'backup_exists'         => metadata_exists( 'post', $unsupported_id, MigrationEngine::BACKUP_META ),
			),
		),
		'before' => array(
			'elementor_permalink' => $elementor_permalink_before,
			'divi_permalink'      => $divi_permalink_before,
			'unsupported_content' => $unsupported_before?->post_content,
			'unsupported_meta_sha256' => $unsupported_meta_hash_before,
		),
		'invariants' => array(
			'active_plugins_same' => $active_plugins_before === $active_plugins_after,
			'theme_same'          => $theme_before === $theme_after,
			'active_preset'       => get_option( 'seo_geo_active_preset' ),
		),
	),
	JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
);
PHP

docker cp "$MIGRATION_RUNNER" "$WP_CONTAINER":/var/www/html/wp-content/migration-engine-runner.php   || fail_smoke "migration-engine-runner-copy" "Could not copy Phase 8E runner" "runner copied" "docker cp failed"

if ! MIGRATION_REPORT="$(wp_cli eval-file /var/www/html/wp-content/migration-engine-runner.php 2>"$TMP_DIR/migration-engine.stderr")"; then
  MIGRATION_ENGINE_ERROR="$(tr -d '\r' <"$TMP_DIR/migration-engine.stderr" | head -c 700)"
  fail_smoke "migration-engine-runner" "Phase 8E Migration Engine runner failed" "JSON migration report" "${MIGRATION_ENGINE_ERROR:-wp eval-file failed}"
fi

printf '%s' "$MIGRATION_REPORT" >"$TMP_DIR/migration-engine-report.json"

if grep -Fq 'Private form configuration marker' "$TMP_DIR/migration-engine-report.json"; then
  fail_smoke "migration-plan-private-payload" "Phase 8E plan leaked unsupported builder configuration" "no private form configuration in report" "private marker found"
fi

if ! MIGRATION_ASSERTION="$(python3 - "$TMP_DIR/migration-engine-report.json" <<'PY'
import json
import urllib.parse
import sys

with open(sys.argv[1], "r", encoding="utf-8") as handle:
    report = json.load(handle)

ids = report["ids"]
plans = report["plans"]

assert plans["elementor"]["ready"] is True
assert plans["elementor"]["preset"]["selected"] == "corporate"
assert plans["elementor"]["preset"]["valid"] is True
assert plans["elementor"]["adapter_plan"]["supported"] is True
assert ids["attachment"] in plans["elementor"]["adapter_plan"]["media_ids"]
assert "woocommerce" in plans["elementor"]["business_systems"]
assert plans["elementor"]["authorization"] == {
    "requires_manage_options": True,
    "requires_edit_post": True,
    "requires_nonce": True,
    "requires_confirmation": True,
}
assert plans["elementor"]["safety"]["sandbox_only"] is True
assert plans["elementor"]["safety"]["plugin_mutation_allowed"] is False
assert plans["elementor"]["safety"]["theme_mutation_allowed"] is False
assert plans["elementor"]["safety"]["url_change_allowed"] is False
assert plans["elementor"]["safety"]["object_id_change_allowed"] is False
assert plans["elementor"]["safety"]["unsupported_content_dropped"] is False

assert plans["divi"]["ready"] is True
assert plans["divi"]["adapter_plan"]["supported"] is True

assert plans["blocked"]["ready"] is False
assert plans["blocked"]["adapter_plan"]["supported"] is False
assert "unsupported-elementor-widget:form" in plans["blocked"]["adapter_plan"]["blockers"]
assert any(item == "adapter:unsupported-elementor-widget:form" for item in plans["blocked"]["blockers"])

assert plans["mismatch"]["ready"] is False
assert plans["mismatch"]["preset"]["valid"] is False
assert plans["mismatch"]["preset"]["reason"] == "requested-preset-does-not-match-active-theme-preset"

assert report["errors"]["confirmation"] == "seo_geo_migration_confirmation_required"
assert report["errors"]["nonce"] == "seo_geo_migration_invalid_nonce"
assert report["errors"]["blocked"] == "seo_geo_migration_not_ready"

elementor_result = report["results"]["elementor"]
divi_result = report["results"]["divi"]
assert elementor_result["status"] == "migrated"
assert divi_result["status"] == "migrated"
assert elementor_result["preserved"]["object_id"] is True
assert elementor_result["preserved"]["post_name"] is True
assert elementor_result["preserved"]["permalink_path"] is True
assert ids["attachment"] in elementor_result["preserved"]["media_ids"]
assert "woocommerce" in elementor_result["business_systems"]
assert elementor_result["safety"]["plugins_changed"] is False
assert elementor_result["safety"]["theme_changed"] is False
assert elementor_result["safety"]["unsupported_content_lost"] is False

after = report["after"]
assert after["elementor"]["id"] == ids["elementor"]
assert after["elementor"]["post_name"] == "elementor-migration-fixture"
assert urllib.parse.urlparse(after["elementor"]["permalink"]).path == urllib.parse.urlparse(report["before"]["elementor_permalink"]).path
assert "<!-- wp:heading" in after["elementor"]["content"]
assert "Migrated Elementor heading" in after["elementor"]["content"]
assert "<!-- wp:html -->" in after["elementor"]["content"]
assert "<!-- wp:image" in after["elementor"]["content"]
assert f"wp-image-{ids['attachment']}" in after["elementor"]["content"]
assert "<!-- wp:buttons -->" in after["elementor"]["content"]
assert after["elementor"]["edit_mode"] == ""
assert after["elementor"]["thumbnail_id"] == ids["attachment"]
assert after["elementor"]["backup_exists"] is True
assert after["elementor"]["backup_sha256"]
assert after["elementor"]["state_exists"] is True

assert after["divi"]["id"] == ids["divi"]
assert after["divi"]["post_name"] == "divi-migration-fixture"
assert urllib.parse.urlparse(after["divi"]["permalink"]).path == urllib.parse.urlparse(report["before"]["divi_permalink"]).path
assert "<!-- wp:html -->" in after["divi"]["content"]
assert "Migrated Divi body." in after["divi"]["content"]
assert "<!-- wp:buttons -->" in after["divi"]["content"]
assert after["divi"]["builder_mode"] == "off"
assert after["divi"]["backup_exists"] is True
assert after["divi"]["backup_sha256"]
assert after["divi"]["state_exists"] is True

assert after["unsupported"]["id"] == ids["unsupported"]
assert after["unsupported"]["post_name"] == "unsupported-elementor-fixture"
assert after["unsupported"]["content"] == report["before"]["unsupported_content"] == "UNSUPPORTED SOURCE MARKER"
assert after["unsupported"]["elementor_data_sha256"] == report["before"]["unsupported_meta_sha256"]
assert after["unsupported"]["backup_exists"] is False

assert report["invariants"]["active_plugins_same"] is True
assert report["invariants"]["theme_same"] is True
assert report["invariants"]["active_preset"] == "corporate"

print("ok")
PY
)"; then
  fail_smoke "migration-engine-contract" "Phase 8E Migration Engine contract is invalid" "authorized safe Elementor/Divi migration + unsupported blocker" "${MIGRATION_ASSERTION:-python assertion failed}"
fi

printf '[smoke] Phase 8E Migration Engine OK: authorized Elementor/Divi fixtures migrated to native blocks, IDs/paths/media/business systems preserved, unsupported widget blocked intact.\n'
