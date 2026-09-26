#!/usr/bin/env bash
# Phase 10E.2A.5.3.1 private same-server local package handoff acceptance.

printf '[smoke] Checking private same-server local-clone package handoff.\n'

LOCAL_HANDOFF_RUNNER="$TMP_DIR/portable-clone-local-package-handoff-runner.php"
cat >"$LOCAL_HANDOFF_RUNNER" <<'PHP'
<?php

use SeoGeo\MigrationBridge\Clone\AdminCloneLocalPackageHandoffController;
use SeoGeo\MigrationBridge\Clone\CloneInventoryStore;
use SeoGeo\MigrationBridge\Clone\CloneJobStore;
use SeoGeo\MigrationBridge\Clone\DeliveryStateStore;
use SeoGeo\MigrationBridge\Clone\ExportWorkspace;
use SeoGeo\MigrationBridge\Clone\LocalCloneBootstrapStateStore;
use SeoGeo\MigrationBridge\Clone\LocalCloneBootstrapper;
use SeoGeo\MigrationBridge\Clone\LocalCloneOrchestrator;
use SeoGeo\MigrationBridge\Clone\LocalClonePackageHandoff;
use SeoGeo\MigrationBridge\Clone\LocalClonePackageHandoffStateStore;
use SeoGeo\MigrationBridge\Clone\LocalCloneTargetPreflight;
use SeoGeo\MigrationBridge\Clone\LocalCloneTargetPreflightStateStore;
use SeoGeo\MigrationBridge\Clone\ImportStateStore;
use SeoGeo\MigrationBridge\Clone\LocalCloneRuntimeBootstrapper;
use SeoGeo\MigrationBridge\Clone\LocalCloneRuntimeStateStore;
use SeoGeo\MigrationBridge\Clone\LocalCloneSandboxRuntimeBootstrapper;
use SeoGeo\MigrationBridge\Clone\LocalCloneSandboxRuntimeStateStore;
use SeoGeo\MigrationBridge\Clone\LocalCloneStateStore;
use SeoGeo\MigrationBridge\Clone\PackageStateStore;
use SeoGeo\MigrationBridge\Plugin;

$options = array(
	CloneJobStore::OPTION_NAME,
	CloneInventoryStore::OPTION_NAME,
	PackageStateStore::OPTION_NAME,
	DeliveryStateStore::OPTION_NAME,
	LocalCloneStateStore::OPTION_NAME,
	LocalCloneBootstrapStateStore::OPTION_NAME,
	LocalCloneRuntimeStateStore::OPTION_NAME,
	LocalCloneSandboxRuntimeStateStore::OPTION_NAME,
	LocalClonePackageHandoffStateStore::OPTION_NAME,
	LocalCloneTargetPreflightStateStore::OPTION_NAME,
	ImportStateStore::OPTION_NAME,
);
foreach ( $options as $option ) {
	delete_option( $option );
}

$jobs      = Plugin::clone_job_store();
$planner   = Plugin::local_clone_orchestrator();
$ownership = Plugin::local_clone_bootstrapper();
$core      = Plugin::local_clone_runtime_bootstrapper();
$sandbox   = Plugin::local_clone_sandbox_runtime_bootstrapper();
$handoff   = Plugin::local_clone_package_handoff();
$target_preflight = Plugin::local_clone_target_preflight();
if (
	! $jobs instanceof CloneJobStore
	|| ! $planner instanceof LocalCloneOrchestrator
	|| ! $ownership instanceof LocalCloneBootstrapper
	|| ! $core instanceof LocalCloneRuntimeBootstrapper
	|| ! $sandbox instanceof LocalCloneSandboxRuntimeBootstrapper
	|| ! $handoff instanceof LocalClonePackageHandoff
	|| ! $target_preflight instanceof LocalCloneTargetPreflight
) {
	throw new RuntimeException( 'Local clone handoff services are unavailable.' );
}

$workspace = new ExportWorkspace();
$inventory = new CloneInventoryStore();
$packages  = new PackageStateStore();

$remove_tree = static function ( string $path ): void {
	if ( ! is_dir( $path ) ) {
		return;
	}
	$iterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $path, FilesystemIterator::SKIP_DOTS ),
		RecursiveIteratorIterator::CHILD_FIRST
	);
	foreach ( $iterator as $item ) {
		if ( $item->isDir() && ! $item->isLink() ) {
			@rmdir( $item->getPathname() );
		} else {
			@unlink( $item->getPathname() );
		}
	}
	@rmdir( $path );
};

$job_id        = 'local-package-handoff-0001';
$target_path   = trailingslashit( wp_normalize_path( ABSPATH ) ) . 'nuevaweb-package-handoff';
$target_url    = trailingslashit( home_url( '/nuevaweb-package-handoff/' ) );
$source_prefix = $GLOBALS['wpdb']->prefix;
$target_prefix = $source_prefix . 'sghandoff_';

$workspace->cleanup( $job_id );
$workspace->delete_delivery_archive( $job_id );
$remove_tree( $target_path );

$job = $jobs->create( 'local-clone', $job_id );
if ( ! is_array( $job ) ) {
	throw new RuntimeException( 'Could not create local handoff fixture job.' );
}

$source_fingerprint = hash( 'sha256', 'local-handoff-source:' . $job_id );

$schema_written = $workspace->write( $job_id, 'database/schema/users.sql', 'CREATE TABLE fixture_users (id bigint unsigned);' );
$chunk_written  = $workspace->write( $job_id, 'database/chunks/users-1.json', '[{"id":1}]' );
$upload_written = $workspace->write( $job_id, 'files/uploads/a.txt', 'upload-payload' );
$plugin_written = $workspace->write( $job_id, 'files/plugins/demo/demo.php', "<?php\n// handoff fixture\n" );
if ( ! is_array( $schema_written ) || ! is_array( $chunk_written ) || ! is_array( $upload_written ) || ! is_array( $plugin_written ) ) {
	throw new RuntimeException( 'Could not write local handoff payload fixtures.' );
}

$database_manifest = array(
	'schema_version'              => 1,
	'package_id'                  => $job_id,
	'payload_class'               => 'database',
	'source'                      => array(
		'home_url'     => home_url( '/' ),
		'site_url'     => site_url( '/' ),
		'table_prefix' => $source_prefix,
	),
	'tables'                      => array(),
	'table_count'                 => 0,
	'row_count'                   => 1,
	'payload_bytes'               => (int) $schema_written['bytes'] + (int) $chunk_written['bytes'],
	'chunk_count'                 => 1,
	'production_source_read_only' => true,
	'credentials_in_payload'      => false,
	'contains_private_site_data'  => true,
	'repository_safe'             => false,
	'generated_at'                => gmdate( DATE_ATOM ),
);
$database_json = wp_json_encode( $database_manifest, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT ) . "\n";
$database_written = $workspace->write( $job_id, 'database/manifest.json', $database_json );
if ( ! is_array( $database_written ) ) {
	throw new RuntimeException( 'Could not write local handoff database manifest.' );
}

$files_manifest = array(
	'schema_version'              => 1,
	'payload_class'               => 'files',
	'file_count'                  => 2,
	'payload_bytes'               => (int) $upload_written['bytes'] + (int) $plugin_written['bytes'],
	'roots'                       => array(
		array( 'id' => 'uploads', 'file_count' => 1, 'byte_count' => (int) $upload_written['bytes'] ),
		array( 'id' => 'plugins', 'file_count' => 1, 'byte_count' => (int) $plugin_written['bytes'] ),
	),
	'source_fingerprint'          => $source_fingerprint,
	'file_records'                => array(
		'format'    => 'one-json-record-per-file',
		'directory' => 'files-meta/',
	),
	'production_source_read_only' => true,
	'credentials_in_payload'      => false,
	'contains_private_site_data'  => true,
	'repository_safe'             => false,
	'generated_at'                => gmdate( DATE_ATOM ),
);
$files_json = wp_json_encode( $files_manifest, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT ) . "\n";
$files_written = $workspace->write( $job_id, 'files/manifest.json', $files_json );
if ( ! is_array( $files_written ) ) {
	throw new RuntimeException( 'Could not write local handoff files manifest.' );
}

$root = $workspace->root_path( $job_id );
if ( null === $root ) {
	throw new RuntimeException( 'Local handoff workspace unavailable.' );
}

$pending       = array( '' );
$checksum      = hash( 'sha256', 'seo-geo-portable-clone-package-v1' );
$payload_files = 0;
$payload_bytes = 0;
while ( array() !== $pending ) {
	$current = (string) array_shift( $pending );
	$dir     = rtrim( $root, '/' ) . ( '' === $current ? '' : '/' . $current );
	$entries = scandir( $dir, SCANDIR_SORT_ASCENDING );
	if ( false === $entries ) {
		throw new RuntimeException( 'Could not scan local handoff workspace.' );
	}

	foreach ( $entries as $entry ) {
		if ( '.' === $entry || '..' === $entry ) {
			continue;
		}
		$relative = '' === $current ? $entry : $current . '/' . $entry;
		if ( 'package' === $relative || str_starts_with( $relative, 'package/' ) ) {
			continue;
		}
		$path = rtrim( $root, '/' ) . '/' . $relative;
		if ( is_dir( $path ) ) {
			$pending[] = $relative;
			continue;
		}
		$bytes = filesize( $path );
		$hash  = hash_file( 'sha256', $path );
		if ( false === $bytes || false === $hash ) {
			throw new RuntimeException( 'Could not hash local handoff payload.' );
		}
		$checksum = hash(
			'sha256',
			$checksum . "\n" . 'payload|' . wp_normalize_path( $relative ) . '|' . (string) (int) $bytes . '|' . $hash
		);
		++$payload_files;
		$payload_bytes += (int) $bytes;
	}
}


$manifest = array(
	'schema_version' => 1,
	'mode'           => 'portable-clone-package',
	'package_id'     => $job_id,
	'operation'      => 'local-clone',
	'source'         => array(
		'home_url'           => home_url( '/' ),
		'site_url'           => site_url( '/' ),
		'wordpress_version'  => get_bloginfo( 'version' ),
		'php_version'        => PHP_VERSION,
		'source_fingerprint' => $source_fingerprint,
	),
	'payload'        => array(
		'database' => array(
			'manifest_path'   => 'database/manifest.json',
			'manifest_sha256' => (string) $database_written['sha256'],
			'row_count'       => 1,
			'chunk_count'     => 1,
			'payload_bytes'   => (int) $database_manifest['payload_bytes'],
		),
		'files'    => array(
			'manifest_path'   => 'files/manifest.json',
			'manifest_sha256' => (string) $files_written['sha256'],
			'file_count'      => 2,
			'payload_bytes'   => (int) $files_manifest['payload_bytes'],
		),
	),
	'integrity'      => array(
		'algorithm'          => 'sha256',
		'checksum_contract'  => 'lexicographic-bfs-path+bytes+sha256-v1',
		'checksum_scope'     => 'workspace-excluding-package-metadata',
		'payload_file_count' => $payload_files,
		'payload_bytes'      => $payload_bytes,
		'package_checksum'   => $checksum,
		'verification_pass'  => true,
		'verified'           => true,
	),
	'safety'         => array(
		'production_source_read_only' => true,
		'credentials_in_manifest'     => false,
		'contains_private_site_data'  => true,
		'repository_safe'             => false,
		'delivery_ready'              => false,
	),
	'generated_at'   => gmdate( DATE_ATOM ),
);
$manifest_json = wp_json_encode( $manifest, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT ) . "\n";
$manifest_written = $workspace->write( $job_id, 'package/manifest.json', $manifest_json );
if ( ! is_array( $manifest_written ) ) {
	throw new RuntimeException( 'Could not write local handoff package manifest.' );
}

$now = gmdate( DATE_ATOM );
$inventory->save(
	$job_id,
	array(
		'status'       => 'complete',
		'database'     => array(
			'estimated_rows'  => 1,
			'estimated_bytes' => 4096,
		),
		'roots'        => array(),
		'file_count'   => 2,
		'byte_count'   => 4096,
		'fingerprint'  => $source_fingerprint,
		'blockers'     => array(),
		'completed_at' => $now,
		'updated_at'   => $now,
	)
);
$packages->save(
	$job_id,
	array(
		'schema_version'         => PackageStateStore::SCHEMA_VERSION,
		'job_id'                 => $job_id,
		'status'                 => 'complete',
		'stage'                  => 'complete',
		'directory_active'       => false,
		'pending_dirs'           => array(),
		'current_dir'            => '',
		'after_name'             => '',
		'payload_file_count'     => $payload_files,
		'payload_byte_count'     => $payload_bytes,
		'verify_file_count'      => $payload_files,
		'verify_byte_count'      => $payload_bytes,
		'package_checksum'       => $checksum,
		'verification_checksum'  => $checksum,
		'source_fingerprint'     => $source_fingerprint,
		'database_manifest_hash' => hash_file( 'sha256', rtrim( $root, '/' ) . '/database/manifest.json' ),
		'files_manifest_hash'    => hash_file( 'sha256', rtrim( $root, '/' ) . '/files/manifest.json' ),
		'package_manifest_hash'  => (string) $manifest_written['sha256'],
		'blockers'               => array(),
		'started_at'             => $now,
		'updated_at'             => $now,
		'completed_at'           => $now,
	)
);

$frozen_manifest_hash = hash_file( 'sha256', rtrim( $root, '/' ) . '/package/manifest.json' );
$frozen_package_state = $packages->get( $job_id );
if ( false === $frozen_manifest_hash || ! is_array( $frozen_package_state ) ) {
	throw new RuntimeException( 'Frozen local handoff package identity unavailable.' );
}

$plan  = $planner->prepare( $job_id, $target_path, $target_url, $target_prefix, true );
$claim = $ownership->claim( $job_id );

$core_state = null;
for ( $i = 0; $i < 140; ++$i ) {
	$core_state = $core->advance( $job_id, 200, 64 * 1024 * 1024 );
	if ( ! is_array( $core_state ) || in_array( $core_state['status'] ?? null, array( 'complete', 'blocked' ), true ) ) {
		break;
	}
}

$sandbox_state = null;
for ( $i = 0; $i < 140; ++$i ) {
	$sandbox_state = $sandbox->advance( $job_id, 200, 64 * 1024 * 1024 );
	if ( ! is_array( $sandbox_state ) || in_array( $sandbox_state['status'] ?? null, array( 'complete', 'blocked' ), true ) ) {
		break;
	}
}

$handoff_state = null;
for ( $i = 0; $i < 140; ++$i ) {
	$handoff_state = $handoff->advance( $job_id, 200, 64 * 1024 * 1024 );
	if ( ! is_array( $handoff_state ) || in_array( $handoff_state['status'] ?? null, array( 'ready', 'blocked' ), true ) ) {
		break;
	}
}

$verified_before = $handoff->verified_snapshot( $job_id );
$delivery_info   = Plugin::clone_package_delivery()?->download_info( $job_id );
$manifest_after  = $workspace->read( $job_id, 'package/manifest.json' );
$manifest_parsed = is_string( $manifest_after ) ? json_decode( $manifest_after, true ) : null;
$manifest_after_hash = is_string( $manifest_after ) ? hash( 'sha256', $manifest_after ) : '';
$package_after        = $packages->get( $job_id );

$target_preflight_state = $target_preflight->advance( $job_id );
$target_preflight_verified = $target_preflight->verified_snapshot( $job_id );
$target_preflight_child = is_array( $target_preflight_state )
	? ( new ImportStateStore() )->get( (string) ( $target_preflight_state['child_import_job_id'] ?? '' ) )
	: null;

$table_pattern = $GLOBALS['wpdb']->esc_like( $target_prefix ) . '%';
$target_tables = $GLOBALS['wpdb']->get_col(
	$GLOBALS['wpdb']->prepare(
		'SHOW TABLES LIKE %s',
		$table_pattern
	)
);

$target_verified_after_child_drift = null;
if ( is_array( $target_preflight_child ) && is_array( $target_preflight_state ) ) {
	$child_id = (string) $target_preflight_state['child_import_job_id'];
	$child_store = new ImportStateStore();
	$drifted_child = $target_preflight_child;
	$drifted_child['destination_table_prefix'] = $target_prefix . 'drift_';
	$child_store->save( $child_id, $drifted_child );
	$target_verified_after_child_drift = $target_preflight->verified_snapshot( $job_id );
	$child_store->save( $child_id, $target_preflight_child );
}

$target_verified_after_mutation = null;
$created_table = $target_prefix . 'tamper_guard';
$GLOBALS['wpdb']->query( "CREATE TABLE {$created_table} (id bigint unsigned NOT NULL)" );
$target_verified_after_mutation = $target_preflight->verified_snapshot( $job_id );
$GLOBALS['wpdb']->query( "DROP TABLE IF EXISTS {$created_table}" );

$archive_path = is_array( $delivery_info ) ? (string) $delivery_info['path'] : '';
if ( '' !== $archive_path && is_file( $archive_path ) ) {
	file_put_contents( $archive_path, "tamper", FILE_APPEND );
}
$verified_after_tamper = $handoff->verified_snapshot( $job_id );

$autoload = $GLOBALS['wpdb']->get_var(
	$GLOBALS['wpdb']->prepare(
		"SELECT autoload FROM {$GLOBALS['wpdb']->options} WHERE option_name = %s",
		LocalClonePackageHandoffStateStore::OPTION_NAME
	)
);

echo wp_json_encode(
	array(
		'plan'                        => $plan,
		'claim'                       => $claim,
		'core_state'                  => $core_state,
		'sandbox_state'               => $sandbox_state,
		'handoff_state'               => $handoff_state,
		'target_preflight_state'      => $target_preflight_state,
		'target_preflight_verified'   => is_array( $target_preflight_verified ),
		'target_preflight_child'      => $target_preflight_child,
		'target_verified_after_mutation' => is_array( $target_verified_after_mutation ),
		'target_verified_after_child_drift' => is_array( $target_verified_after_child_drift ),
		'verified_before'             => is_array( $verified_before ),
		'verified_after_tamper'       => is_array( $verified_after_tamper ),
		'delivery_info_before_tamper' => $delivery_info,
		'frozen_manifest_hash'        => $frozen_manifest_hash,
		'manifest_after_hash'         => $manifest_after_hash,
		'package_state_manifest_hash_before' => (string) $frozen_package_state['package_manifest_hash'],
		'package_state_manifest_hash_after'  => is_array( $package_after ) ? (string) ( $package_after['package_manifest_hash'] ?? '' ) : '',
		'manifest_operation'          => is_array( $manifest_parsed ) ? (string) ( $manifest_parsed['operation'] ?? '' ) : '',
		'manifest_delivery_ready'     => is_array( $manifest_parsed ) ? (bool) ( $manifest_parsed['safety']['delivery_ready'] ?? true ) : true,
		'manifest_has_delivery'       => is_array( $manifest_parsed ) && array_key_exists( 'delivery', $manifest_parsed ),
		'target_table_count'          => is_array( $target_tables ) ? count( $target_tables ) : -1,
		'uploads_absent'              => ! file_exists( trailingslashit( $target_path ) . 'wp-content/uploads' ),
		'themes_absent'               => ! file_exists( trailingslashit( $target_path ) . 'wp-content/themes' ),
		'sandbox_still_verified'      => is_array( $sandbox->verified_snapshot( $job_id ) ),
		'controller_registered'       => false !== has_action( 'admin_post_' . AdminCloneLocalPackageHandoffController::ACTION ),
		'target_preflight_service_registered' => $target_preflight instanceof LocalCloneTargetPreflight,
		'public_controller_absent'    => false === has_action( 'admin_post_nopriv_' . AdminCloneLocalPackageHandoffController::ACTION ),
		'autoload'                    => $autoload,
		'job'                         => $jobs->get( $job_id ),
	),
	JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
);

$workspace->delete_delivery_archive( $job_id );
$workspace->cleanup( $job_id );
$remove_tree( $target_path );
foreach ( $options as $option ) {
	delete_option( $option );
}
PHP

docker cp "$LOCAL_HANDOFF_RUNNER" "$WP_CONTAINER":/var/www/html/wp-content/portable-clone-local-package-handoff-runner.php \
  || fail_smoke "local-clone-handoff-runner-copy" "Could not copy local clone handoff runner" "runner copied" "docker cp failed"

if ! LOCAL_HANDOFF_JSON="$(wp_cli eval-file /var/www/html/wp-content/portable-clone-local-package-handoff-runner.php 2>"$TMP_DIR/portable-clone-local-package-handoff.stderr")"; then
  LOCAL_HANDOFF_ERROR="$(tr -d '\r' <"$TMP_DIR/portable-clone-local-package-handoff.stderr" | head -c 2200)"
  fail_smoke "local-clone-handoff-runner" "Local clone package handoff runner failed" "JSON contract report" "${LOCAL_HANDOFF_ERROR:-wp eval-file failed}"
fi

printf '%s' "$LOCAL_HANDOFF_JSON" >"$TMP_DIR/portable-clone-local-package-handoff.json"

if ! LOCAL_HANDOFF_ASSERTION="$(python3 - "$TMP_DIR/portable-clone-local-package-handoff.json" <<'PY'
import json
import re
import sys

with open(sys.argv[1], "r", encoding="utf-8") as handle:
    payload = json.load(handle)

assert payload["plan"] is not None, payload
assert payload["plan"]["status"] == "ready", payload
assert payload["claim"] is not None, payload
assert payload["claim"]["status"] == "claimed", payload

core = payload["core_state"]
assert core is not None and core["status"] == "complete", payload
assert core["runtime_core_ready"] is True, payload

sandbox = payload["sandbox_state"]
assert sandbox is not None and sandbox["status"] == "complete", payload
assert sandbox["sandbox_runtime_ready"] is True, payload

handoff = payload["handoff_state"]
assert handoff is not None, payload
assert handoff["status"] == "ready", payload
assert handoff["transport"] == "private-same-server", payload
assert handoff["handoff_ready"] is True, payload
assert handoff["handoff_next"] == "target-intake-preflight", payload
assert handoff["archive_bytes"] > 0, payload
assert re.fullmatch(r"[a-f0-9]{64}", handoff["archive_sha256"]), payload
assert handoff["blockers"] == [], payload

target = payload["target_preflight_state"]
assert target is not None, payload
assert target["status"] == "ready", payload
assert target["preflight_ready"] is True, payload
assert target["restore_allowed"] is False, payload
assert target["database_untouched"] is True, payload
assert target["client_content_untouched"] is True, payload
assert target["preflight_next"] == "payload-extraction", payload
assert target["blockers"] == [], payload
assert re.fullmatch(r"[a-f0-9]{64}", target["destination_authority_sha256"]), payload
assert target["handoff_archive_sha256"] == handoff["archive_sha256"], payload
assert target["handoff_archive_bytes"] == handoff["archive_bytes"], payload
assert target["package_manifest_sha256"] == handoff["package_manifest_hash"], payload
assert target["package_checksum"] == handoff["package_checksum"], payload
assert payload["target_preflight_verified"] is True, payload
assert payload["target_verified_after_mutation"] is False, payload
assert payload["target_verified_after_child_drift"] is False, payload
assert payload["target_preflight_service_registered"] is True, payload

child = payload["target_preflight_child"]
assert child is not None, payload
assert child["status"] == "preflight-ready", payload
assert child["transport"] == "private-same-server", payload
assert child["local_handoff_parent_job_id"] == handoff["job_id"], payload
assert child["destination_home_url"] == handoff["target_url"], payload
assert child["destination_site_url"] == handoff["target_url"], payload
assert child["destination_table_prefix"] == handoff["target_table_prefix"], payload
assert child["destination_mode"] == "subdirectory", payload
assert child["destination_storage_isolated"] is True, payload
assert child["search_visibility_disabled"] is True, payload
assert child["outbound_safe"] is True, payload
assert child["backups_ready"] is True, payload
assert child["target_authorized"] is True, payload
assert child["manifest_contract_valid"] is True, payload
assert child["child_manifest_hashes_valid"] is True, payload
assert child["full_payload_verified"] is False, payload
assert child["restore_allowed"] is False, payload
assert child["blockers"] == [], payload
assert "full-payload-checksum-pending" in child["advisories"], payload

delivery = payload["delivery_info_before_tamper"]
assert delivery is not None, payload
assert delivery["bytes"] == handoff["archive_bytes"], payload
assert delivery["sha256"] == handoff["archive_sha256"], payload
assert re.fullmatch(r"[a-f0-9]{64}", delivery["sha256"]), payload

assert payload["verified_before"] is True, payload
assert payload["verified_after_tamper"] is False, payload
assert payload["frozen_manifest_hash"] == payload["manifest_after_hash"], payload
assert payload["package_state_manifest_hash_before"] == payload["package_state_manifest_hash_after"], payload
assert payload["package_state_manifest_hash_after"] == payload["frozen_manifest_hash"], payload
assert payload["manifest_operation"] == "local-clone", payload
assert payload["manifest_delivery_ready"] is False, payload
assert payload["manifest_has_delivery"] is False, payload

assert payload["target_table_count"] == 0, payload
assert payload["uploads_absent"] is True, payload
assert payload["themes_absent"] is True, payload
assert payload["sandbox_still_verified"] is True, payload
assert payload["controller_registered"] is True, payload
assert payload["public_controller_absent"] is True, payload
assert payload["autoload"] in ("off", "no", "auto-off"), payload

job = payload["job"]
assert job["operation"] == "local-clone", payload
assert job["status"] == "active", payload
assert job["phase"] == "validate", payload
assert job["cursor"] == "local-target-preflight-ready", payload

print("ok")
PY
)"; then
  fail_smoke "local-clone-package-handoff" "Local clone private package handoff contract is invalid" "immutable package manifest + private hash-bound ZIP + no target import mutation" "${LOCAL_HANDOFF_ASSERTION:-python assertion failed}"
fi

printf '[smoke] Local clone handoff + target preflight OK: private ZIP frozen, existing import preflight reused against isolated target, restore locked, target mutation/archive tamper invalidated readiness.\n'
