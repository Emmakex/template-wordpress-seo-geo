#!/usr/bin/env bash
# Phase 8G safe production cutover + rollback acceptance.
#
# Sourced after 8F. This deliberately exits sandbox mode, restores production
# search visibility, activates a legacy presentation theme and a replaceable SEO
# fixture, then proves automatic rollback, manual rollback and final acceptance.

printf '[smoke] Checking Phase 8G safe cutover and rollback.\n'

wp_cli config delete SEO_GEO_MIGRATION_SANDBOX >/dev/null 2>&1   || fail_smoke "cutover-sandbox-marker-delete" "Could not remove the sandbox marker before production cutover acceptance" "sandbox marker absent" "wp config delete failed"

wp_cli option update blog_public 1 >/dev/null   || fail_smoke "cutover-search-visibility" "Could not restore production search-engine visibility" "blog_public=1" "option update failed"

wp_cli theme activate legacy-child >/dev/null   || fail_smoke "cutover-legacy-theme" "Could not activate the legacy production-theme fixture" "legacy-child active" "theme activation failed"

wp_cli plugin activate wordpress-seo >/dev/null   || fail_smoke "cutover-legacy-seo" "Could not activate the replaceable legacy SEO fixture" "wordpress-seo active" "plugin activation failed"

CUTOVER_RUNNER="$TMP_DIR/migration-cutover-runner.php"
cat >"$CUTOVER_RUNNER" <<'PHP'
<?php

use SeoGeo\MigrationBridge\BaselineSnapshotStore;
use SeoGeo\MigrationBridge\Cutover\CutoverEngine;
use SeoGeo\MigrationBridge\Cutover\CutoverSnapshotStore;
use SeoGeo\MigrationBridge\Cutover\PublicSnapshotProviderInterface;

final class Phase8GSequenceSnapshotProvider implements PublicSnapshotProviderInterface {
	/**
	 * @var list<array<string,mixed>>
	 */
	private array $snapshots;

	private int $position = 0;

	/**
	 * @param list<array<string,mixed>> $snapshots Ordered snapshots.
	 */
	public function __construct( array $snapshots ) {
		$this->snapshots = array_values( $snapshots );
	}

	/**
	 * @return array<string,mixed>
	 */
	public function capture(): array {
		if ( array() === $this->snapshots ) {
			throw new RuntimeException( 'No acceptance snapshots configured.' );
		}

		$index = min( $this->position, count( $this->snapshots ) - 1 );
		++$this->position;
		return $this->snapshots[ $index ];
	}
}

wp_set_current_user( 1 );

$baseline_envelope = ( new BaselineSnapshotStore() )->latest();
if ( ! is_array( $baseline_envelope ) || ! isset( $baseline_envelope['snapshot'] ) || ! is_array( $baseline_envelope['snapshot'] ) ) {
	throw new RuntimeException( 'Persisted baseline is unavailable for Phase 8G.' );
}
$baseline = $baseline_envelope['snapshot'];

$changed = $baseline;
foreach ( $changed['crawl']['pages'] as &$page ) {
	if (
		is_array( $page )
		&& isset( $page['url'] )
		&& is_string( $page['url'] )
		&& str_ends_with( $page['url'], '/native-seo-fixture/' )
	) {
		$page['title'] = 'Phase 8G forced regression';
		break;
	}
}
unset( $page );

$now = gmdate( DATE_ATOM );
$backup_evidence = array(
	'database' => array(
		'reference'  => 'ci-db-backup-phase-8g',
		'sha256'     => hash( 'sha256', 'phase-8g-database-backup' ),
		'created_at' => $now,
		'scope'      => 'full-database',
		'size_bytes' => 4096,
	),
	'uploads' => array(
		'reference'  => 'ci-uploads-backup-phase-8g',
		'sha256'     => hash( 'sha256', 'phase-8g-uploads-backup' ),
		'created_at' => $now,
		'scope'      => 'uploads-tree',
		'size_bytes' => 8192,
	),
);

$quality_evidence = array(
	'accessibility' => array(
		'reference'  => 'ci-accessibility-phase-8g',
		'sha256'     => hash( 'sha256', 'phase-8g-accessibility-evidence' ),
		'created_at' => $now,
		'passed'     => true,
	),
	'performance' => array(
		'reference'  => 'ci-performance-phase-8g',
		'sha256'     => hash( 'sha256', 'phase-8g-performance-evidence' ),
		'created_at' => $now,
		'passed'     => true,
	),
);

$stable_provider = new Phase8GSequenceSnapshotProvider( array( $baseline ) );
$plan_engine     = new CutoverEngine( null, null, null, null, $stable_provider );

$missing_backup_plan = $plan_engine->plan( array(), array( 'wordpress-seo/wp-seo.php' ), array(), $quality_evidence );
$missing_quality_plan = $plan_engine->plan( $backup_evidence, array( 'wordpress-seo/wp-seo.php' ), array(), array() );
$keep_plan = $plan_engine->plan( $backup_evidence, array( 'woocommerce/woocommerce.php' ), array(), $quality_evidence );
$migrate_plan = $plan_engine->plan( $backup_evidence, array( 'elementor/elementor.php' ), array(), $quality_evidence );
$bridge_plan = $plan_engine->plan( $backup_evidence, array( 'seo-geo-migration-bridge/seo-geo-migration-bridge.php' ), array(), $quality_evidence );
$safe_plan = $plan_engine->plan( $backup_evidence, array( 'wordpress-seo/wp-seo.php' ), array(), $quality_evidence );

$execute_nonce  = wp_create_nonce( CutoverEngine::execute_nonce_action() );
$rollback_nonce = wp_create_nonce( CutoverEngine::rollback_nonce_action() );
$accept_nonce   = wp_create_nonce( CutoverEngine::accept_nonce_action() );

$confirmation_error = $plan_engine->execute(
	$backup_evidence,
	array( 'wordpress-seo/wp-seo.php' ),
	array(),
	$quality_evidence,
	array(),
	$execute_nonce,
	false
);
$nonce_error = $plan_engine->execute(
	$backup_evidence,
	array( 'wordpress-seo/wp-seo.php' ),
	array(),
	$quality_evidence,
	array(),
	'invalid-nonce',
	true
);

$auto_engine = new CutoverEngine(
	null,
	null,
	null,
	null,
	new Phase8GSequenceSnapshotProvider( array( $baseline, $changed ) )
);
$auto_result = $auto_engine->execute(
	$backup_evidence,
	array( 'wordpress-seo/wp-seo.php' ),
	array(),
	$quality_evidence,
	array(),
	$execute_nonce,
	true
);
$auto_record = ( new CutoverSnapshotStore() )->latest();
$auto_runtime = array(
	'theme'        => get_stylesheet(),
	'yoast_active' => is_plugin_active( 'wordpress-seo/wp-seo.php' ),
);

$manual_engine = new CutoverEngine(
	null,
	null,
	null,
	null,
	new Phase8GSequenceSnapshotProvider( array( $baseline ) )
);
$manual_execute = $manual_engine->execute(
	$backup_evidence,
	array( 'wordpress-seo/wp-seo.php' ),
	array(),
	$quality_evidence,
	array(),
	$execute_nonce,
	true
);
$manual_active_record = ( new CutoverSnapshotStore() )->latest();
$manual_cutover_runtime = array(
	'theme'             => get_stylesheet(),
	'yoast_active'      => is_plugin_active( 'wordpress-seo/wp-seo.php' ),
	'woocommerce_active'=> is_plugin_active( 'woocommerce/woocommerce.php' ),
	'elementor_active'  => is_plugin_active( 'elementor/elementor.php' ),
	'bridge_active'     => is_plugin_active( 'seo-geo-migration-bridge/seo-geo-migration-bridge.php' ),
);

$manual_rollback = $manual_engine->rollback( array(), $rollback_nonce, true );
$manual_rolled_record = ( new CutoverSnapshotStore() )->latest();
$manual_rollback_runtime = array(
	'theme'        => get_stylesheet(),
	'yoast_active' => is_plugin_active( 'wordpress-seo/wp-seo.php' ),
);

$accept_engine = new CutoverEngine(
	null,
	null,
	null,
	null,
	new Phase8GSequenceSnapshotProvider( array( $baseline ) )
);
$accepted_execute = $accept_engine->execute(
	$backup_evidence,
	array( 'wordpress-seo/wp-seo.php' ),
	array(),
	$quality_evidence,
	array(),
	$execute_nonce,
	true
);
$accepted_result = $accept_engine->accept( array(), $accept_nonce, true );
$accepted_record = ( new CutoverSnapshotStore() )->latest();
$rollback_after_accept = $accept_engine->rollback( array(), $rollback_nonce, true );

$history = ( new CutoverSnapshotStore() )->history();
$history_summary = array_map(
	static function ( array $record ): array {
		return array(
			'id'     => $record['id'] ?? null,
			'status' => $record['status'] ?? null,
			'events' => is_array( $record['events'] ?? null )
				? array_values(
					array_filter(
						array_map(
							static fn( mixed $event ): ?string => is_array( $event ) && is_string( $event['status'] ?? null ) ? $event['status'] : null,
							$record['events']
						),
						'is_string'
					)
				)
				: array(),
			'backup_scopes' => is_array( $record['backup'] ?? null )
				? array_values(
					array_filter(
						array_map(
							static fn( mixed $row ): ?string => is_array( $row ) && is_string( $row['scope'] ?? null ) ? $row['scope'] : null,
							$record['backup']
						),
						'is_string'
					)
				)
				: array(),
			'migration_recovery_count' => is_array( $record['migration'] ?? null ) ? count( $record['migration'] ) : 0,
		);
	},
	$history
);

echo wp_json_encode(
	array(
		'plans' => array(
			'missing_backup'  => $missing_backup_plan,
			'missing_quality' => $missing_quality_plan,
			'keep'           => $keep_plan,
			'migrate'        => $migrate_plan,
			'bridge'         => $bridge_plan,
			'safe'           => $safe_plan,
		),
		'auth_errors' => array(
			'confirmation' => is_wp_error( $confirmation_error ) ? $confirmation_error->get_error_code() : null,
			'nonce'        => is_wp_error( $nonce_error ) ? $nonce_error->get_error_code() : null,
		),
		'auto' => array(
			'error'   => is_wp_error( $auto_result ) ? $auto_result->get_error_code() : null,
			'record'  => is_array( $auto_record ) ? array( 'status' => $auto_record['status'] ?? null ) : null,
			'runtime' => $auto_runtime,
		),
		'manual' => array(
			'execute' => $manual_execute,
			'active_record_status' => is_array( $manual_active_record ) ? $manual_active_record['status'] ?? null : null,
			'cutover_runtime' => $manual_cutover_runtime,
			'rollback' => $manual_rollback,
			'rolled_record_status' => is_array( $manual_rolled_record ) ? $manual_rolled_record['status'] ?? null : null,
			'rollback_runtime' => $manual_rollback_runtime,
		),
		'accepted' => array(
			'execute' => $accepted_execute,
			'accept'  => $accepted_result,
			'record_status' => is_array( $accepted_record ) ? $accepted_record['status'] ?? null : null,
			'rollback_error' => is_wp_error( $rollback_after_accept ) ? $rollback_after_accept->get_error_code() : null,
			'runtime' => array(
				'theme'             => get_stylesheet(),
				'yoast_active'      => is_plugin_active( 'wordpress-seo/wp-seo.php' ),
				'woocommerce_active'=> is_plugin_active( 'woocommerce/woocommerce.php' ),
				'elementor_active'  => is_plugin_active( 'elementor/elementor.php' ),
				'bridge_active'     => is_plugin_active( 'seo-geo-migration-bridge/seo-geo-migration-bridge.php' ),
				'blog_public'       => (int) get_option( 'blog_public', 0 ),
			),
		),
		'history' => $history_summary,
	),
	JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
);
PHP

docker cp "$CUTOVER_RUNNER" "$WP_CONTAINER":/var/www/html/wp-content/migration-cutover-runner.php   || fail_smoke "cutover-runner-copy" "Could not copy Phase 8G runner" "runner copied" "docker cp failed"

if ! CUTOVER_REPORT="$(wp_cli eval-file /var/www/html/wp-content/migration-cutover-runner.php 2>"$TMP_DIR/migration-cutover.stderr")"; then
  CUTOVER_ERROR="$(tr -d '\r' <"$TMP_DIR/migration-cutover.stderr" | head -c 900)"
  fail_smoke "cutover-runner" "Phase 8G cutover runner failed" "JSON cutover report" "${CUTOVER_ERROR:-wp eval-file failed}"
fi

printf '%s' "$CUTOVER_REPORT" >"$TMP_DIR/migration-cutover-report.json"

if grep -Fq 'phase-8g-database-backup' "$TMP_DIR/migration-cutover-report.json"   || grep -Fq 'phase-8g-uploads-backup' "$TMP_DIR/migration-cutover-report.json"; then
  fail_smoke "cutover-private-artifact" "Phase 8G report leaked synthetic backup artifact content" "backup metadata only" "raw artifact marker found"
fi

if ! CUTOVER_ASSERTION="$(python3 - "$TMP_DIR/migration-cutover-report.json" <<'PY'
import json
import sys

with open(sys.argv[1], "r", encoding="utf-8") as handle:
    report = json.load(handle)

plans = report["plans"]

assert plans["missing_backup"]["ready"] is False
assert any(item.startswith("backup:backup-evidence-missing:") for item in plans["missing_backup"]["blockers"])

assert plans["missing_quality"]["ready"] is False
assert any(item.startswith("quality:quality-evidence-missing:") for item in plans["missing_quality"]["blockers"])

assert plans["keep"]["ready"] is False
assert any(item.startswith("plugin-deactivation-not-approved:woocommerce/woocommerce.php:") for item in plans["keep"]["blockers"])

assert plans["migrate"]["ready"] is False
assert any(item.startswith("plugin-deactivation-not-approved:elementor/elementor.php:") for item in plans["migrate"]["blockers"])

assert plans["bridge"]["ready"] is False
assert "plugin-deactivation-forbidden:migration-bridge" in plans["bridge"]["blockers"]

safe = plans["safe"]
assert safe["ready"] is True
assert safe["target_theme"] == "seo-geo-theme"
assert safe["current_theme"] == "legacy-child"
assert safe["plugins"]["deactivate"] == ["wordpress-seo/wp-seo.php"]
assert safe["plugins"]["delete"] == []
assert safe["backup"]["valid"] is True
assert safe["quality"]["valid"] is True
assert sorted(safe["quality"]["evidence"]) == ["accessibility", "performance"]
assert all(row["passed"] is True for row in safe["quality"]["evidence"].values())
assert safe["parity"]["accepted"] is True
assert len(safe["parity"]["report_hash"]) == 64
assert len(safe["plan_sha256"]) == 64
assert safe["maintenance"] == {
    "flush_rewrites": True,
    "flush_object_cache": True,
    "refresh_sitemaps": True,
}
assert safe["safety"] == {
    "plugin_deletion_allowed": False,
    "theme_deletion_allowed": False,
    "database_reset_allowed": False,
    "uploads_reset_allowed": False,
    "bridge_deactivation_allowed": False,
    "rollback_required_until_acceptance": True,
}

assert report["auth_errors"]["confirmation"] == "seo_geo_cutover_confirmation_required"
assert report["auth_errors"]["nonce"] == "seo_geo_cutover_invalid_nonce"

auto = report["auto"]
assert auto["error"] == "seo_geo_cutover_postcheck_failed"
assert auto["record"]["status"] == "rolled-back-auto"
assert auto["runtime"] == {
    "theme": "legacy-child",
    "yoast_active": True,
}

manual = report["manual"]
assert manual["execute"]["status"] == "cutover-active"
assert manual["execute"]["rollback_available"] is True
assert manual["execute"]["accepted"] is False
assert manual["active_record_status"] == "cutover-active"
assert manual["cutover_runtime"] == {
    "theme": "seo-geo-theme",
    "yoast_active": False,
    "woocommerce_active": True,
    "elementor_active": True,
    "bridge_active": True,
}
assert manual["rollback"]["status"] == "rolled-back"
assert manual["rollback"]["runtime_restored"] is True
assert manual["rollback"]["parity_restored"] is True
assert manual["rolled_record_status"] == "rolled-back"
assert manual["rollback_runtime"] == {
    "theme": "legacy-child",
    "yoast_active": True,
}

accepted = report["accepted"]
assert accepted["execute"]["status"] == "cutover-active"
assert accepted["accept"]["status"] == "accepted"
assert accepted["accept"]["rollback_available"] is False
assert accepted["accept"]["recovery_evidence_retained"] is True
assert accepted["record_status"] == "accepted"
assert accepted["rollback_error"] == "seo_geo_cutover_rollback_unavailable"
assert accepted["runtime"] == {
    "theme": "seo-geo-theme",
    "yoast_active": False,
    "woocommerce_active": True,
    "elementor_active": True,
    "bridge_active": True,
    "blog_public": 1,
}

history = report["history"]
assert len(history) == 3
assert [row["status"] for row in history] == [
    "rolled-back-auto",
    "rolled-back",
    "accepted",
]
assert history[0]["events"] == ["prepared", "rolled-back-auto"]
assert history[1]["events"] == ["prepared", "cutover-active", "rolled-back"]
assert history[2]["events"] == ["prepared", "cutover-active", "accepted"]
for row in history:
    assert sorted(row["backup_scopes"]) == ["full-database", "uploads-tree"]

assert any(row["migration_recovery_count"] >= 2 for row in history)

print("ok")
PY
)"; then
  fail_smoke "cutover-contract" "Phase 8G cutover/rollback contract is invalid" "backup-gated reversible cutover with hard KEEP/MIGRATE protections" "${CUTOVER_ASSERTION:-python assertion failed}"
fi

printf '[smoke] Phase 8G cutover OK: backup evidence required; KEEP/MIGRATE components protected; failed parity auto-rolls back; manual rollback restores runtime; explicit acceptance closes rollback while retaining evidence.\n'
