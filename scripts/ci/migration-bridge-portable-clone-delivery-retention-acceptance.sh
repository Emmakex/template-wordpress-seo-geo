#!/usr/bin/env bash
# Phase 10E.2A.3.4 authenticated package delivery + retention acceptance.

printf '[smoke] Checking Portable Clone authenticated delivery + retention.\n'

DELIVERY_RUNNER="$TMP_DIR/portable-clone-delivery-retention-runner.php"
cat >"$DELIVERY_RUNNER" <<'PHP'
<?php

use SeoGeo\MigrationBridge\Clone\AdminCloneDeliveryController;
use SeoGeo\MigrationBridge\Clone\CloneInventoryStore;
use SeoGeo\MigrationBridge\Clone\CloneJobStore;
use SeoGeo\MigrationBridge\Clone\DeliveryStateStore;
use SeoGeo\MigrationBridge\Clone\ExportStateStore;
use SeoGeo\MigrationBridge\Clone\ExportWorkspace;
use SeoGeo\MigrationBridge\Clone\FileExportStateStore;
use SeoGeo\MigrationBridge\Clone\PackageArchive;
use SeoGeo\MigrationBridge\Clone\PackageDelivery;
use SeoGeo\MigrationBridge\Clone\PackageStateStore;
use SeoGeo\MigrationBridge\Plugin;

delete_option( CloneJobStore::OPTION_NAME );
delete_option( CloneInventoryStore::OPTION_NAME );
delete_option( ExportStateStore::OPTION_NAME );
delete_option( FileExportStateStore::OPTION_NAME );
delete_option( PackageStateStore::OPTION_NAME );
delete_option( DeliveryStateStore::OPTION_NAME );

$jobs = Plugin::clone_job_store();
$delivery = Plugin::clone_package_delivery();
if ( ! $jobs instanceof CloneJobStore || ! $delivery instanceof PackageDelivery ) {
	throw new RuntimeException( 'Portable Clone delivery services are unavailable.' );
}

$workspace = new ExportWorkspace();
$archive = new PackageArchive();
$job_id = 'clone-delivery-good-0001';
$workspace->cleanup( $job_id );
$archive->delete( $job_id );

$job = $jobs->create( 'export', $job_id );
if ( ! is_array( $job ) ) {
	throw new RuntimeException( 'Could not create delivery fixture job.' );
}

foreach (
	array(
		'database/manifest.json' => '{"fixture":"database"}',
		'files/uploads/a.txt' => 'upload-payload',
		'files/plugins/plugin.php' => "<?php\n// fixture\n",
	) as $relative => $payload
) {
	if ( null === $workspace->write( $job_id, $relative, $payload ) ) {
		throw new RuntimeException( 'Could not write delivery fixture payload: ' . $relative );
	}
}

$package_manifest = array(
	'schema_version' => 1,
	'mode' => 'portable-clone-package',
	'package_id' => $job_id,
	'operation' => 'export',
	'integrity' => array(
		'algorithm' => 'sha256',
		'verified' => true,
	),
	'safety' => array(
		'production_source_read_only' => true,
		'credentials_in_manifest' => false,
		'contains_private_site_data' => true,
		'repository_safe' => false,
		'delivery_ready' => false,
	),
);
$manifest_written = $workspace->write(
	$job_id,
	'package/manifest.json',
	wp_json_encode( $package_manifest, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT ) . "\n"
);
if ( ! is_array( $manifest_written ) ) {
	throw new RuntimeException( 'Could not write package manifest fixture.' );
}

$root = $workspace->root_path( $job_id );
if ( null === $root ) {
	throw new RuntimeException( 'Delivery fixture workspace is unavailable.' );
}

$pending = array( '' );
$checksum = hash( 'sha256', 'seo-geo-portable-clone-package-v1' );
$payload_files = 0;
$payload_bytes = 0;
while ( array() !== $pending ) {
	$current = (string) array_shift( $pending );
	$dir = rtrim( $root, '/' ) . ( '' === $current ? '' : '/' . $current );
	$entries = scandir( $dir, SCANDIR_SORT_ASCENDING );
	if ( false === $entries ) {
		throw new RuntimeException( 'Could not scan package fixture.' );
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
		$hash = hash_file( 'sha256', $path );
		if ( false === $bytes || false === $hash ) {
			throw new RuntimeException( 'Could not hash package fixture.' );
		}
		$checksum = hash( 'sha256', $checksum . "\n" . 'payload|' . wp_normalize_path( $relative ) . '|' . (string) (int) $bytes . '|' . $hash );
		++$payload_files;
		$payload_bytes += (int) $bytes;
	}
}

$now = gmdate( DATE_ATOM );
$package_store = new PackageStateStore();
if (
	! $package_store->save(
		$job_id,
		array(
			'schema_version' => PackageStateStore::SCHEMA_VERSION,
			'job_id' => $job_id,
			'status' => 'complete',
			'stage' => 'complete',
			'directory_active' => false,
			'pending_dirs' => array(),
			'current_dir' => '',
			'after_name' => '',
			'payload_file_count' => $payload_files,
			'payload_byte_count' => $payload_bytes,
			'verify_file_count' => $payload_files,
			'verify_byte_count' => $payload_bytes,
			'package_checksum' => $checksum,
			'verification_checksum' => $checksum,
			'source_fingerprint' => hash( 'sha256', 'delivery-source' ),
			'database_manifest_hash' => '',
			'files_manifest_hash' => '',
			'package_manifest_hash' => $manifest_written['sha256'],
			'blockers' => array(),
			'started_at' => $now,
			'updated_at' => $now,
			'completed_at' => $now,
		)
	)
) {
	throw new RuntimeException( 'Could not persist package fixture state.' );
}

$state = $delivery->start( $job_id );
if ( ! is_array( $state ) || 'building' !== ( $state['status'] ?? null ) ) {
	throw new RuntimeException( 'Could not start authenticated delivery fixture.' );
}

$prepared_manifest_json = $workspace->read( $job_id, 'package/manifest.json' );
$prepared_manifest = is_string( $prepared_manifest_json ) ? json_decode( $prepared_manifest_json, true ) : null;
if (
	! is_array( $prepared_manifest )
	|| true !== ( $prepared_manifest['safety']['delivery_ready'] ?? false )
	|| true !== ( $prepared_manifest['delivery']['authenticated_only'] ?? false )
	|| false !== ( $prepared_manifest['delivery']['public_url'] ?? true )
) {
	throw new RuntimeException( 'Delivery-ready manifest contract was not applied.' );
}

$steps = 0;
while ( is_array( $state ) && 'ready' !== ( $state['status'] ?? null ) && 120 > $steps ) {
	$state = $delivery->advance( $job_id, 2, 1024 * 1024 );
	++$steps;
}
if ( ! is_array( $state ) || 'ready' !== ( $state['status'] ?? null ) ) {
	throw new RuntimeException( 'Delivery ZIP did not reach ready state: ' . wp_json_encode( $state ) );
}

$download = $delivery->download_info( $job_id );
if ( ! is_array( $download ) || ! is_file( $download['path'] ) || ! is_readable( $download['path'] ) ) {
	throw new RuntimeException( 'Verified private archive is not downloadable.' );
}
if ( hash_file( 'sha256', $download['path'] ) !== $download['sha256'] ) {
	throw new RuntimeException( 'Final archive SHA-256 does not match download metadata.' );
}

require_once ABSPATH . 'wp-admin/includes/class-pclzip.php';
$zip = new PclZip( $download['path'] );
$list = $zip->listContent();
if ( ! is_array( $list ) ) {
	throw new RuntimeException( 'Could not list Portable Clone ZIP content.' );
}
$names = array();
foreach ( $list as $entry ) {
	if ( is_array( $entry ) && is_string( $entry['filename'] ?? null ) ) {
		$names[] = $entry['filename'];
	}
}
foreach ( array( 'package/manifest.json', 'database/manifest.json', 'files/uploads/a.txt', 'files/plugins/plugin.php' ) as $required ) {
	if ( ! in_array( $required, $names, true ) ) {
		throw new RuntimeException( 'ZIP is missing required path: ' . $required . ' ; got=' . wp_json_encode( $names ) );
	}
}

if ( ! $delivery->mark_downloaded( $job_id ) ) {
	throw new RuntimeException( 'Authenticated download receipt could not be recorded.' );
}
$state = ( new DeliveryStateStore() )->get( $job_id );
if ( ! is_array( $state ) || 1 !== (int) ( $state['download_count'] ?? 0 ) ) {
	throw new RuntimeException( 'Download count was not persisted.' );
}

$sentinel = trailingslashit( $archive->base_path() ) . 'unrelated-retention-sentinel.txt';
// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Fixture proves cleanup remains job-scoped.
file_put_contents( $sentinel, 'keep' );

$state['expires_at'] = time() - 1;
( new DeliveryStateStore() )->save( $job_id, $state );
if ( null !== $delivery->download_info( $job_id ) ) {
	throw new RuntimeException( 'Expired package remained downloadable.' );
}

$cleanup_steps = 0;
do {
	$delivery->cleanup_expired( 1, 2 );
	$state = ( new DeliveryStateStore() )->get( $job_id );
	++$cleanup_steps;
} while ( is_array( $state ) && 'cleaned' !== ( $state['status'] ?? null ) && 120 > $cleanup_steps );

$autoload = $GLOBALS['wpdb']->get_var(
	$GLOBALS['wpdb']->prepare(
		"SELECT autoload FROM {$GLOBALS['wpdb']->options} WHERE option_name = %s",
		DeliveryStateStore::OPTION_NAME
	)
);

$result = array(
	'state_before_cleanup' => array(
		'archive_sha256' => $download['sha256'],
		'archive_bytes' => $download['bytes'],
		'steps' => $steps,
	),
	'final_state' => $state,
	'cleanup_steps' => $cleanup_steps,
	'workspace_removed' => null === $workspace->root_path( $job_id ),
	'archive_removed' => null === $archive->info( $job_id ),
	'package_state_removed' => null === $package_store->get( $job_id ),
	'sentinel_preserved' => is_file( $sentinel ) && 'keep' === file_get_contents( $sentinel ),
	'delivery_state_autoload' => $autoload,
	'auth_build_registered' => false !== has_action( 'admin_post_' . AdminCloneDeliveryController::BUILD_ACTION ),
	'auth_download_registered' => false !== has_action( 'admin_post_' . AdminCloneDeliveryController::DOWNLOAD_ACTION ),
	'auth_cleanup_registered' => false !== has_action( 'admin_post_' . AdminCloneDeliveryController::CLEANUP_ACTION ),
	'public_download_registered' => false !== has_action( 'admin_post_nopriv_' . AdminCloneDeliveryController::DOWNLOAD_ACTION ),
	'job' => $jobs->get( $job_id ),
);

echo wp_json_encode( $result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );

// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged,WordPress.WP.AlternativeFunctions.unlink_unlink -- Fixture sentinel cleanup.
@unlink( $sentinel );
delete_option( CloneJobStore::OPTION_NAME );
delete_option( CloneInventoryStore::OPTION_NAME );
delete_option( ExportStateStore::OPTION_NAME );
delete_option( FileExportStateStore::OPTION_NAME );
delete_option( PackageStateStore::OPTION_NAME );
delete_option( DeliveryStateStore::OPTION_NAME );
PHP

docker cp "$DELIVERY_RUNNER" "$WP_CONTAINER":/var/www/html/wp-content/portable-clone-delivery-retention-runner.php \
  || fail_smoke "clone-delivery-runner-copy" "Could not copy Portable Clone delivery runner" "runner copied" "docker cp failed"

if ! DELIVERY_JSON="$(wp_cli eval-file /var/www/html/wp-content/portable-clone-delivery-retention-runner.php 2>"$TMP_DIR/portable-clone-delivery-retention.stderr")"; then
  DELIVERY_ERROR="$(tr -d '\r' <"$TMP_DIR/portable-clone-delivery-retention.stderr" | head -c 4000)"
  fail_smoke "clone-delivery-runner" "Portable Clone authenticated delivery runner failed" "JSON delivery report" "${DELIVERY_ERROR:-wp eval-file failed}"
fi

printf '%s' "$DELIVERY_JSON" >"$TMP_DIR/portable-clone-delivery-retention.json"

if ! DELIVERY_ASSERTION="$(python3 - "$TMP_DIR/portable-clone-delivery-retention.json" <<'PY'
import json
import re
import sys

with open(sys.argv[1], "r", encoding="utf-8") as handle:
    payload = json.load(handle)

before = payload["state_before_cleanup"]
final = payload["final_state"]
job = payload["job"]

assert re.fullmatch(r"[a-f0-9]{64}", before["archive_sha256"])
assert before["archive_bytes"] > 0
assert before["steps"] >= 2

assert final["status"] == "cleaned"
assert final["archive_sha256"] == ""
assert final["archive_bytes"] == 0
assert final["cleanup_deleted_count"] > 0
assert payload["cleanup_steps"] >= 1
assert payload["workspace_removed"] is True
assert payload["archive_removed"] is True
assert payload["package_state_removed"] is True
assert payload["sentinel_preserved"] is True
assert payload["delivery_state_autoload"] in ("off", "no", "auto-off")

assert payload["auth_build_registered"] is True
assert payload["auth_download_registered"] is True
assert payload["auth_cleanup_registered"] is True
assert payload["public_download_registered"] is False

assert job["operation"] == "export"
assert job["phase"] == "completed"
assert job["status"] == "completed"

print("ok")
PY
)"; then
  fail_smoke "clone-delivery-contract" "Portable Clone delivery/retention contract is invalid" "private authenticated ZIP + bounded expiry cleanup" "${DELIVERY_ASSERTION:-python assertion failed}"
fi

printf '[smoke] Portable Clone delivery OK: private ZIP built resumably, auth-only hook boundary held, archive hash verified, 24h expiry denied download, bounded cleanup preserved unrelated files.\n'
