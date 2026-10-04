#!/usr/bin/env bash
# Reset/Rebuild Corporate Theme bootstrap acceptance.

printf '[smoke] Checking reset-first Corporate Theme bootstrap.\n'

BOOTSTRAP_RUNNER="$TMP_DIR/corporate-theme-bootstrap-runner.php"
cat >"$BOOTSTRAP_RUNNER" <<'PHP'
<?php

use SeoGeo\MigrationBridge\Plugin;
use SeoGeo\MigrationBridge\Reset\CorporateThemeBootstrap;
use WP_Error;

wp_set_current_user( 1 );

$bootstrap = Plugin::corporate_theme_bootstrap();
$manifest  = Plugin::rescue_manifest()?->saved();

if ( ! $bootstrap instanceof CorporateThemeBootstrap || ! is_array( $manifest ) ) {
	throw new RuntimeException( 'Corporate bootstrap or Rescue Manifest service is unavailable.' );
}

$legacy_handoff = array(
	'schema_version' => 1,
	'report'         => array(
		'schema_version'    => 1,
		'mode'              => 'migration-report',
		'ready_for_handoff' => false,
	),
);
update_option( 'seo_geo_migration_report_v1', $legacy_handoff, false );
$legacy_handoff_sha = hash( 'sha256', maybe_serialize( $legacy_handoff ) );

delete_option( CorporateThemeBootstrap::REPORT_OPTION );
delete_option( 'seo_geo_active_preset' );
delete_option( 'seo_geo_native_languages' );
delete_option( 'seo_geo_schema_identity' );
delete_option( 'seo_geo_schema_local_business' );
delete_option( 'seo_geo_crawler_policy' );
delete_option( 'seo_geo_llms_txt' );
delete_option( 'seo_geo_markdown_alternates' );
delete_option( 'seo_geo_theme_setup_v1' );
delete_option( 'seo_geo_theme_setup_report_v1' );

$first_resource = null;
foreach ( is_array( $manifest['resources'] ?? null ) ? $manifest['resources'] : array() as $item ) {
	if ( is_array( $item ) && 0 < (int) ( $item['id'] ?? 0 ) && '' !== (string) ( $item['content_sha256'] ?? '' ) ) {
		$first_resource = $item;
		break;
	}
}
if ( ! is_array( $first_resource ) ) {
	throw new RuntimeException( 'No rescued content fixture is available for Corporate bootstrap verification.' );
}

$page_count_before    = (int) wp_count_posts( 'page' )->publish + (int) wp_count_posts( 'page' )->draft;
$active_plugins_before = array_values( array_map( 'strval', get_option( 'active_plugins', array() ) ) );
sort( $active_plugins_before );

$plan             = $bootstrap->plan();
$setup_plan_before = seo_geo_theme_setup_plan();
$denied           = $bootstrap->apply( false );
$applied          = $bootstrap->apply( true );
$replayed         = $bootstrap->apply( true );
$setup_plan_after = seo_geo_theme_setup_plan();

if ( ! $denied instanceof WP_Error ) {
	throw new RuntimeException( 'Corporate bootstrap must require explicit Organization confirmation.' );
}
if ( $applied instanceof WP_Error ) {
	throw new RuntimeException( $applied->get_error_code() . ': ' . $applied->get_error_message() );
}
if ( $replayed instanceof WP_Error ) {
	throw new RuntimeException( $replayed->get_error_code() . ': ' . $replayed->get_error_message() );
}

$identity  = get_option( 'seo_geo_schema_identity', array() );
$languages = get_option( 'seo_geo_native_languages', array() );
$llms      = get_option( 'seo_geo_llms_txt', array() );
$markdown  = get_option( 'seo_geo_markdown_alternates', array() );
$setup     = seo_geo_theme_setup_report();

$active_plugins_after = array_values( array_map( 'strval', get_option( 'active_plugins', array() ) ) );
sort( $active_plugins_after );

$resource_id      = (int) ( $first_resource['id'] ?? 0 );
$resource_content = (string) get_post_field( 'post_content', $resource_id );
$resource_sha     = hash( 'sha256', $resource_content );
$page_count_after = (int) wp_count_posts( 'page' )->publish + (int) wp_count_posts( 'page' )->draft;
$legacy_after     = get_option( 'seo_geo_migration_report_v1', null );

echo wp_json_encode(
	array(
		'plan'              => $plan,
		'setup_plan_before' => $setup_plan_before,
		'applied'           => $applied,
		'replayed'          => $replayed,
		'setup_plan_after'  => $setup_plan_after,
		'state'             => array(
			'active_preset'           => seo_geo_theme_active_preset_id(),
			'stylesheet'              => get_stylesheet(),
			'languages'               => $languages,
			'identity'                => $identity,
			'llms'                    => $llms,
			'markdown'                => $markdown,
			'setup_report'            => $setup,
			'active_plugins_before'   => $active_plugins_before,
			'active_plugins_after'    => $active_plugins_after,
			'page_count_before'       => $page_count_before,
			'page_count_after'        => $page_count_after,
			'rescued_content_sha256'  => $resource_sha,
			'expected_content_sha256' => (string) ( $first_resource['content_sha256'] ?? '' ),
			'legacy_handoff_unchanged' => is_array( $legacy_after )
				&& hash_equals( $legacy_handoff_sha, hash( 'sha256', maybe_serialize( $legacy_after ) ) ),
		),
	),
	JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
);
PHP

docker cp "$BOOTSTRAP_RUNNER" "$WP_CONTAINER":/var/www/html/wp-content/corporate-theme-bootstrap-runner.php \
  || fail_smoke "corporate-bootstrap-runner-copy" "Could not copy Corporate bootstrap runner" "runner copied" "docker cp failed"

if ! BOOTSTRAP_REPORT="$(wp_cli eval-file /var/www/html/wp-content/corporate-theme-bootstrap-runner.php 2>"$TMP_DIR/corporate-theme-bootstrap.stderr")"; then
  BOOTSTRAP_ERROR="$(tr -d '\r' <"$TMP_DIR/corporate-theme-bootstrap.stderr" | head -c 1400)"
  fail_smoke "corporate-bootstrap-runner" "Corporate Theme bootstrap runner failed" "JSON bootstrap report" "${BOOTSTRAP_ERROR:-wp eval-file failed}"
fi

printf '%s' "$BOOTSTRAP_REPORT" >"$TMP_DIR/corporate-theme-bootstrap-report.json"

if ! python3 - "$TMP_DIR/corporate-theme-bootstrap-report.json" <<'PY'
import json
import sys

with open(sys.argv[1], "r", encoding="utf-8") as handle:
    report = json.load(handle)

plan = report["plan"]
before = report["setup_plan_before"]
applied = report["applied"]
replayed = report["replayed"]
after = report["setup_plan_after"]
state = report["state"]

assert plan["ready"] is True
assert plan["mode"] == "reset-rebuild-corporate-bootstrap-plan"
assert plan["preset"] == "corporate"
assert plan["theme"] == "seo-geo-theme"
assert plan["identity"] == {
    "type": "organization",
    "name_source": "wordpress-site-title",
    "explicit_confirmation": True,
    "local_business_inferred": False,
}
assert plan["geo"] == {
    "crawler_policy": "inherit",
    "llms_txt_enabled": False,
    "markdown_alternates_enabled": False,
}

assert before["site_mode"] == "reset-rebuild"
assert before["migration_handoff"]["source"] == "reset-rebuild-handoff-v1"
assert before["migration_handoff"]["blocking_review_count"] == 0
assert before["migration_handoff"]["runtime_dependency_required"] is False

assert applied["status"] == "completed"
assert applied["preset"] == "corporate"
assert applied["theme"] == "seo-geo-theme"
assert applied["safety"]["organization_confirmed"] is True
assert applied["safety"]["local_business_inferred"] is False
assert applied["safety"]["page_content_mutation"] is False
assert applied["safety"]["plugin_mutation"] is False
assert len(applied["configuration_sha256"]) == 64
assert len(applied["theme_report_sha256"]) == 64
assert len(applied["report_sha256"]) == 64

assert replayed["status"] == "completed"
assert replayed["idempotent"] is True

assert after["site_mode"] == "reset-rebuild"
assert after["current"]["preset"] == "corporate"
assert after["migration_handoff"]["source"] == "reset-rebuild-handoff-v1"

assert state["active_preset"] == "corporate"
assert state["stylesheet"] == "seo-geo-theme"
assert state["identity"] == {"site_entity_type": "organization"}
assert state["languages"]["routing"] == "disabled"
assert len(state["languages"]["languages"]) == 1
assert state["languages"]["default"] in state["languages"]["languages"]
assert state["llms"]["enabled"] is False
assert state["markdown"]["enabled"] is False
assert state["active_plugins_before"] == state["active_plugins_after"]
assert state["page_count_before"] == state["page_count_after"]
assert state["rescued_content_sha256"] == state["expected_content_sha256"]
assert state["legacy_handoff_unchanged"] is True
assert state["setup_report"]["site_mode"] == "reset-rebuild"
assert state["setup_report"]["preset"] == "corporate"
assert state["setup_report"]["migration_handoff"]["source"] == "reset-rebuild-handoff-v1"
PY
then
  fail_smoke "corporate-bootstrap-assertions" "Corporate Theme bootstrap violated reset-first Theme authority invariants" "all assertions pass" "assertion failure"
fi

printf '[smoke] Corporate Theme bootstrap OK: reset-first handoff overrides stale migration state, Theme SetupExecutor owns Corporate configuration, Organization confirmation is explicit, and content/plugins/pages remain unchanged.\n'
