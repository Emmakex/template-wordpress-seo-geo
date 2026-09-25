<?php
/**
 * Portable Clone import intake and read-only destination preflight.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge\Clone;

use SeoGeo\MigrationBridge\Sandbox\SandboxGuard;
use wpdb;

/**
 * Validates a staged Portable Clone ZIP before any restore mutation is allowed.
 */
final class ImportPreflight {
	public const TARGET_AUTHORIZED_MARKER = 'SEO_GEO_MIGRATION_IMPORT_TARGET_AUTHORIZED';

	private const MAX_ARCHIVE_ENTRIES       = 200000;
	private const MAX_MANIFEST_BYTES        = 16777216;
	private const MIN_FREE_HEADROOM_BYTES   = 67108864;
	private const PACKAGE_CHECKSUM_CONTRACT = 'lexicographic-bfs-path+bytes+sha256-v1';

	/**
	 * Import state store.
	 *
	 * @var ImportStateStore
	 */
	private ImportStateStore $store;

	/**
	 * Clone job store.
	 *
	 * @var CloneJobStore
	 */
	private CloneJobStore $jobs;

	/**
	 * Private filesystem authority.
	 *
	 * @var ExportWorkspace
	 */
	private ExportWorkspace $workspace;

	/**
	 * Construct import preflight.
	 *
	 * @param ImportStateStore|null $store     Optional import-state store.
	 * @param CloneJobStore|null    $jobs      Optional clone-job store.
	 * @param ExportWorkspace|null  $workspace Optional private workspace.
	 */
	public function __construct(
		?ImportStateStore $store = null,
		?CloneJobStore $jobs = null,
		?ExportWorkspace $workspace = null
	) {
		$this->store     = $store ?? new ImportStateStore();
		$this->jobs      = $jobs ?? new CloneJobStore();
		$this->workspace = $workspace ?? new ExportWorkspace();
	}

	/**
	 * Return one import-preflight state.
	 *
	 * @param string $job_id Clone job identifier.
	 * @return array<string,mixed>|null
	 */
	public function snapshot( string $job_id ): ?array {
		return $this->store->get( $job_id );
	}

	/**
	 * Stage one package inside the private job workspace without touching destination content.
	 *
	 * @param string $job_id Clone job identifier.
	 * @param string $source Absolute readable source ZIP path.
	 * @return array<string,mixed>|null
	 */
	public function stage( string $job_id, string $source ): ?array {
		$job = $this->jobs->get( $job_id );
		if ( is_array( ( new ImportPayloadStateStore() )->get( $job_id ) ) ) {
			return null;
		}
		if ( ! is_array( $job ) || 'import' !== ( $job['operation'] ?? null ) ) {
			return null;
		}

		$archive = $this->workspace->stage_import_archive( $job_id, $source );
		if ( null === $archive ) {
			return $this->block(
				$job_id,
				$this->store->get( $job_id ) ?? array(),
				'import-archive-stage-failed',
				true
			);
		}

		$now   = gmdate( DATE_ATOM );
		$state = array(
			'schema_version'               => ImportStateStore::SCHEMA_VERSION,
			'job_id'                       => $job_id,
			'status'                       => 'staged',
			'archive_sha256'               => (string) $archive['sha256'],
			'archive_bytes'                => (int) $archive['bytes'],
			'package_id'                   => '',
			'package_manifest_sha256'      => '',
			'package_checksum'             => '',
			'payload_file_count'           => 0,
			'payload_bytes'                => 0,
			'archive_entry_count'          => 0,
			'archive_uncompressed_bytes'   => 0,
			'source_home_url'              => '',
			'source_site_url'              => '',
			'source_table_prefix'          => '',
			'destination_home_url'         => home_url( '/' ),
			'destination_site_url'         => site_url( '/' ),
			'destination_table_prefix'     => '',
			'destination_mode'             => '',
			'destination_storage_isolated' => false,
			'search_visibility_disabled'   => false,
			'outbound_safe'                => false,
			'backups_ready'                => false,
			'target_authorized'            => false,
			'disk_free_bytes'              => null,
			'disk_required_bytes'          => 0,
			'manifest_contract_valid'      => false,
			'child_manifest_hashes_valid'  => false,
			'full_payload_verified'        => false,
			'restore_allowed'              => false,
			'blockers'                     => array(),
			'advisories'                   => array( 'full-payload-checksum-pending' ),
			'staged_at'                    => $now,
			'validated_at'                 => '',
			'updated_at'                   => $now,
		);

		if ( ! $this->store->save( $job_id, $state ) ) {
			return null;
		}

		$this->jobs->transition( $job_id, 'active' );
		$this->jobs->update_progress(
			$job_id,
			'validate',
			'staged',
			array(
				'completed' => 0,
				'total'     => null,
			)
		);

		return $this->store->get( $job_id );
	}

	/**
	 * Validate archive topology, manifests and destination isolation without restoring payload.
	 *
	 * This microphase deliberately keeps restore_allowed=false. Full payload checksum replay
	 * and extraction remain the next resumable import step.
	 *
	 * @param string $job_id Clone job identifier.
	 * @return array<string,mixed>|null
	 */
	public function validate( string $job_id ): ?array {
		$job   = $this->jobs->get( $job_id );
		$state = $this->store->get( $job_id );
		if (
			! is_array( $job )
			|| 'import' !== ( $job['operation'] ?? null )
			|| ! is_array( $state )
			|| ! in_array( $state['status'] ?? null, array( 'staged', 'blocked', 'preflight-ready', 'payload-verified' ), true )
		) {
			return null;
		}

		$archive_info = $this->workspace->import_archive_info( $job_id );
		if (
			null === $archive_info
			|| (int) ( $state['archive_bytes'] ?? 0 ) !== (int) $archive_info['bytes']
			|| ! hash_equals( (string) ( $state['archive_sha256'] ?? '' ), (string) $archive_info['sha256'] )
		) {
			return $this->block( $job_id, $state, 'import-archive-identity-changed', false );
		}

		if ( ! $this->load_pclzip() ) {
			return $this->block( $job_id, $state, 'import-archive-reader-unavailable', true );
		}

		// phpcs:ignore PHPCompatibility.Classes.NewClasses.pclzipFound -- WordPress Core PclZip is loaded explicitly above.
		$archive = new \PclZip( $archive_info['path'] );
		$list    = $archive->listContent();
		if ( ! is_array( $list ) || array() === $list ) {
			return $this->block( $job_id, $state, 'import-archive-invalid', false );
		}

		$inspection = $this->inspect_archive_entries( $list );
		$blockers   = $inspection['blockers'];
		$advisories = array( 'full-payload-checksum-pending' );

		$package_json  = $this->read_archive_entry( $archive, 'package/manifest.json' );
		$database_json = $this->read_archive_entry( $archive, 'database/manifest.json' );
		$files_json    = $this->read_archive_entry( $archive, 'files/manifest.json' );

		if ( null === $package_json ) {
			$blockers[] = 'import-package-manifest-unreadable';
		}
		if ( null === $database_json ) {
			$blockers[] = 'import-database-manifest-unreadable';
		}
		if ( null === $files_json ) {
			$blockers[] = 'import-files-manifest-unreadable';
		}

		$package  = is_string( $package_json ) ? json_decode( $package_json, true ) : null;
		$database = is_string( $database_json ) ? json_decode( $database_json, true ) : null;
		$files    = is_string( $files_json ) ? json_decode( $files_json, true ) : null;

		if ( ! is_array( $package ) ) {
			$blockers[] = 'import-package-manifest-json-invalid';
			$package    = array();
		}
		if ( ! is_array( $database ) ) {
			$blockers[] = 'import-database-manifest-json-invalid';
			$database   = array();
		}
		if ( ! is_array( $files ) ) {
			$blockers[] = 'import-files-manifest-json-invalid';
			$files      = array();
		}

		$contract_blockers = $this->manifest_contract_blockers(
			$package,
			$database,
			$files,
			$package_json,
			$database_json,
			$files_json
		);
		$blockers          = array_merge( $blockers, $contract_blockers );
		$destination       = $this->destination_report( $package, $database, (int) $archive_info['bytes'] );
		$blockers          = array_merge( $blockers, $destination['blockers'] );
		$advisories        = array_merge( $advisories, $destination['advisories'] );

		$blockers   = array_values( array_unique( $blockers ) );
		$advisories = array_values( array_unique( $advisories ) );

		$integrity = is_array( $package['integrity'] ?? null ) ? $package['integrity'] : array();
		$source    = is_array( $package['source'] ?? null ) ? $package['source'] : array();
		$db_source = is_array( $database['source'] ?? null ) ? $database['source'] : array();
		$now       = gmdate( DATE_ATOM );

		$state['package_id']                   = is_string( $package['package_id'] ?? null ) ? $package['package_id'] : '';
		$state['package_manifest_sha256']      = is_string( $package_json ) ? hash( 'sha256', $package_json ) : '';
		$state['package_checksum']             = is_string( $integrity['package_checksum'] ?? null ) ? $integrity['package_checksum'] : '';
		$state['payload_file_count']           = max( 0, (int) ( $integrity['payload_file_count'] ?? 0 ) );
		$state['payload_bytes']                = max( 0, (int) ( $integrity['payload_bytes'] ?? 0 ) );
		$state['archive_entry_count']          = (int) $inspection['entry_count'];
		$state['archive_uncompressed_bytes']   = (int) $inspection['uncompressed_bytes'];
		$state['source_home_url']              = is_string( $source['home_url'] ?? null ) ? $source['home_url'] : '';
		$state['source_site_url']              = is_string( $source['site_url'] ?? null ) ? $source['site_url'] : '';
		$state['source_table_prefix']          = is_string( $db_source['table_prefix'] ?? null ) ? $db_source['table_prefix'] : '';
		$state['destination_home_url']         = (string) $destination['home_url'];
		$state['destination_site_url']         = (string) $destination['site_url'];
		$state['destination_table_prefix']     = (string) $destination['table_prefix'];
		$state['destination_mode']             = (string) $destination['mode'];
		$state['destination_storage_isolated'] = true === $destination['storage_isolated'];
		$state['search_visibility_disabled']   = true === $destination['search_visibility_disabled'];
		$state['outbound_safe']                = true === $destination['outbound_safe'];
		$state['backups_ready']                = true === $destination['backups_ready'];
		$state['target_authorized']            = true === $destination['target_authorized'];
		$state['disk_free_bytes']              = $destination['disk_free_bytes'];
		$state['disk_required_bytes']          = (int) $destination['disk_required_bytes'];
		$state['manifest_contract_valid']      = array() === $contract_blockers;
		$state['child_manifest_hashes_valid']  = ! in_array( 'import-database-manifest-hash-mismatch', $contract_blockers, true )
			&& ! in_array( 'import-files-manifest-hash-mismatch', $contract_blockers, true );
		$payload_state                         = ( new ImportPayloadStateStore() )->get( $job_id );
		$payload_valid                         = array() === $blockers
			&& is_array( $payload_state )
			&& 'complete' === ( $payload_state['status'] ?? null )
			&& hash_equals(
				(string) ( $state['archive_sha256'] ?? '' ),
				(string) ( $payload_state['archive_sha256'] ?? '' )
			)
			&& hash_equals(
				(string) $state['package_manifest_sha256'],
				(string) ( $payload_state['package_manifest_sha256'] ?? '' )
			)
			&& hash_equals(
				(string) $state['package_checksum'],
				(string) ( $payload_state['expected_checksum'] ?? '' )
			);

		if ( $payload_valid ) {
			$advisories   = array_values(
				array_filter(
					$advisories,
					static fn( mixed $code ): bool => is_string( $code ) && 'full-payload-checksum-pending' !== $code
				)
			);
			$advisories[] = 'restore-runtime-guard-required';
		}

		$state['status']                = $payload_valid ? 'payload-verified' : ( array() === $blockers ? 'preflight-ready' : 'blocked' );
		$state['full_payload_verified'] = $payload_valid;
		$state['restore_allowed']       = $payload_valid;
		$state['blockers']              = $blockers;
		$state['advisories']            = array_values( array_unique( $advisories ) );
		$state['validated_at']          = $now;
		$state['updated_at']            = $now;

		if ( ! $this->store->save( $job_id, $state ) ) {
			return null;
		}

		if ( array() === $blockers ) {
			$this->jobs->transition( $job_id, 'active' );
			$this->jobs->update_progress(
				$job_id,
				$payload_valid ? 'verify' : 'validate',
				$payload_valid ? 'payload-verified' : 'preflight-ready',
				array(
					'completed' => (int) $inspection['entry_count'],
					'total'     => (int) $inspection['entry_count'],
				)
			);
		} else {
			$this->jobs->transition( $job_id, 'failed-retryable', (string) $blockers[0] );
		}

		return $this->store->get( $job_id );
	}

	/**
	 * Inspect archive entry names and bounded metadata without extracting payload.
	 *
	 * @param array<int,mixed> $entries PclZip listContent result.
	 * @return array{entry_count:int,uncompressed_bytes:int,blockers:list<string>}
	 */
	private function inspect_archive_entries( array $entries ): array {
		$seen               = array();
		$entry_count        = 0;
		$uncompressed_bytes = 0;
		$blockers           = array();

		if ( count( $entries ) > self::MAX_ARCHIVE_ENTRIES ) {
			$blockers[] = 'import-archive-entry-limit';
		}

		foreach ( array_slice( $entries, 0, self::MAX_ARCHIVE_ENTRIES + 1 ) as $entry ) {
			if ( ! is_array( $entry ) || ! is_string( $entry['filename'] ?? null ) ) {
				$blockers[] = 'import-archive-entry-invalid';
				continue;
			}

			$name      = $entry['filename'];
			$canonical = rtrim( $name, '/' );
			if ( ! $this->safe_archive_path( $name ) || ! $this->allowed_archive_path( $canonical ) ) {
				$blockers[] = 'import-archive-path-unsafe';
				continue;
			}
			if ( isset( $seen[ $canonical ] ) ) {
				$blockers[] = 'import-archive-path-duplicate';
				continue;
			}

			$seen[ $canonical ] = true;
			++$entry_count;
			$uncompressed_bytes += max( 0, (int) ( $entry['size'] ?? 0 ) );
		}

		foreach ( array( 'package/manifest.json', 'database/manifest.json', 'files/manifest.json' ) as $required ) {
			if ( ! isset( $seen[ $required ] ) ) {
				$blockers[] = 'import-required-entry-missing';
			}
		}

		return array(
			'entry_count'        => $entry_count,
			'uncompressed_bytes' => $uncompressed_bytes,
			'blockers'           => array_values( array_unique( $blockers ) ),
		);
	}

	/**
	 * Validate package/child-manifest contract without restoring payload.
	 *
	 * @param array<string,mixed> $package       Package manifest.
	 * @param array<string,mixed> $database      Database manifest.
	 * @param array<string,mixed> $files         Files manifest.
	 * @param string|null         $package_json  Raw package manifest.
	 * @param string|null         $database_json Raw database manifest.
	 * @param string|null         $files_json    Raw files manifest.
	 * @return list<string>
	 */
	private function manifest_contract_blockers(
		array $package,
		array $database,
		array $files,
		?string $package_json,
		?string $database_json,
		?string $files_json
	): array {
		$blockers  = array();
		$source    = is_array( $package['source'] ?? null ) ? $package['source'] : array();
		$payload   = is_array( $package['payload'] ?? null ) ? $package['payload'] : array();
		$db_ref    = is_array( $payload['database'] ?? null ) ? $payload['database'] : array();
		$file_ref  = is_array( $payload['files'] ?? null ) ? $payload['files'] : array();
		$integrity = is_array( $package['integrity'] ?? null ) ? $package['integrity'] : array();
		$safety    = is_array( $package['safety'] ?? null ) ? $package['safety'] : array();
		$delivery  = is_array( $package['delivery'] ?? null ) ? $package['delivery'] : array();

		if ( 1 !== ( $package['schema_version'] ?? null ) ) {
			$blockers[] = 'import-package-schema-unsupported';
		}
		if ( 'portable-clone-package' !== ( $package['mode'] ?? null ) ) {
			$blockers[] = 'import-package-mode-invalid';
		}
		if ( ! in_array( $package['operation'] ?? null, array( 'export', 'local-clone' ), true ) ) {
			$blockers[] = 'import-package-operation-invalid';
		}
		if ( ! $this->valid_package_id( $package['package_id'] ?? null ) ) {
			$blockers[] = 'import-package-id-invalid';
		}
		if ( ! $this->valid_http_url( $source['home_url'] ?? null ) || ! $this->valid_http_url( $source['site_url'] ?? null ) ) {
			$blockers[] = 'import-package-source-url-invalid';
		}

		if (
			'database/manifest.json' !== ( $db_ref['manifest_path'] ?? null )
			|| ! $this->valid_hash( $db_ref['manifest_sha256'] ?? null )
		) {
			$blockers[] = 'import-database-manifest-reference-invalid';
		}
		if (
			'files/manifest.json' !== ( $file_ref['manifest_path'] ?? null )
			|| ! $this->valid_hash( $file_ref['manifest_sha256'] ?? null )
		) {
			$blockers[] = 'import-files-manifest-reference-invalid';
		}

		if (
			'sha256' !== ( $integrity['algorithm'] ?? null )
			|| self::PACKAGE_CHECKSUM_CONTRACT !== ( $integrity['checksum_contract'] ?? null )
			|| 'workspace-excluding-package-metadata' !== ( $integrity['checksum_scope'] ?? null )
			|| true !== ( $integrity['verification_pass'] ?? false )
			|| true !== ( $integrity['verified'] ?? false )
			|| ! $this->valid_hash( $integrity['package_checksum'] ?? null )
		) {
			$blockers[] = 'import-package-integrity-contract-invalid';
		}

		if (
			true !== ( $safety['production_source_read_only'] ?? false )
			|| false !== ( $safety['credentials_in_manifest'] ?? true )
			|| true !== ( $safety['contains_private_site_data'] ?? false )
			|| false !== ( $safety['repository_safe'] ?? true )
			|| true !== ( $safety['delivery_ready'] ?? false )
		) {
			$blockers[] = 'import-package-safety-contract-invalid';
		}

		if (
			'zip' !== ( $delivery['format'] ?? null )
			|| true !== ( $delivery['authenticated_only'] ?? false )
			|| false !== ( $delivery['public_url'] ?? true )
			|| 24 !== (int) ( $delivery['retention_hours'] ?? 0 )
		) {
			$blockers[] = 'import-package-delivery-contract-invalid';
		}

		if (
			! is_string( $database_json )
			|| ! $this->valid_hash( $db_ref['manifest_sha256'] ?? null )
			|| ! hash_equals( (string) $db_ref['manifest_sha256'], hash( 'sha256', $database_json ) )
		) {
			$blockers[] = 'import-database-manifest-hash-mismatch';
		}
		if (
			! is_string( $files_json )
			|| ! $this->valid_hash( $file_ref['manifest_sha256'] ?? null )
			|| ! hash_equals( (string) $file_ref['manifest_sha256'], hash( 'sha256', $files_json ) )
		) {
			$blockers[] = 'import-files-manifest-hash-mismatch';
		}

		if (
			1 !== ( $database['schema_version'] ?? null )
			|| 'database' !== ( $database['payload_class'] ?? null )
			|| true !== ( $database['production_source_read_only'] ?? false )
			|| false !== ( $database['credentials_in_payload'] ?? true )
		) {
			$blockers[] = 'import-database-manifest-contract-invalid';
		}
		if (
			1 !== ( $files['schema_version'] ?? null )
			|| 'files' !== ( $files['payload_class'] ?? null )
			|| true !== ( $files['production_source_read_only'] ?? false )
			|| false !== ( $files['credentials_in_payload'] ?? true )
		) {
			$blockers[] = 'import-files-manifest-contract-invalid';
		}

		$db_source = is_array( $database['source'] ?? null ) ? $database['source'] : array();
		if ( 1 !== preg_match( '/^[A-Za-z0-9_]+$/', (string) ( $db_source['table_prefix'] ?? '' ) ) ) {
			$blockers[] = 'import-source-table-prefix-invalid';
		}

		if ( ! is_string( $package_json ) || self::MAX_MANIFEST_BYTES < strlen( $package_json ) ) {
			$blockers[] = 'import-package-manifest-size-invalid';
		}

		return array_values( array_unique( $blockers ) );
	}

	/**
	 * Build read-only destination safety report.
	 *
	 * @param array<string,mixed> $package       Package manifest.
	 * @param array<string,mixed> $database      Database manifest.
	 * @param int                 $archive_bytes Staged ZIP bytes.
	 * @return array<string,mixed>
	 */
	private function destination_report( array $package, array $database, int $archive_bytes ): array {
		global $wpdb;

		$source        = is_array( $package['source'] ?? null ) ? $package['source'] : array();
		$integrity     = is_array( $package['integrity'] ?? null ) ? $package['integrity'] : array();
		$db_source     = is_array( $database['source'] ?? null ) ? $database['source'] : array();
		$home_url      = home_url( '/' );
		$site_url      = site_url( '/' );
		$mode          = SandboxGuard::mode();
		$storage       = SandboxGuard::storage_isolated();
		$search_safe   = '0' === (string) get_option( 'blog_public', '1' );
		$outbound_safe = SandboxGuard::outbound_safe();
		$backups_ready = SandboxGuard::backups_ready();
		$authorized    = defined( self::TARGET_AUTHORIZED_MARKER ) && true === constant( self::TARGET_AUTHORIZED_MARKER );
		$table_prefix  = $wpdb instanceof wpdb ? $wpdb->prefix : '';
		$payload_bytes = max( 0, (int) ( $integrity['payload_bytes'] ?? 0 ) );
		$required      = $payload_bytes + max( self::MIN_FREE_HEADROOM_BYTES, $archive_bytes );
		$free          = disk_free_space( wp_normalize_path( ABSPATH ) );
		$blockers      = array();
		$advisories    = array();

		if ( ! SandboxGuard::enabled() ) {
			$blockers[] = 'import-sandbox-marker-missing';
		}
		if ( 'invalid' === $mode ) {
			$blockers[] = 'import-sandbox-mode-invalid';
		}
		if ( ! $search_safe ) {
			$blockers[] = 'import-search-visibility-not-disabled';
		}
		if ( ! $outbound_safe ) {
			$blockers[] = 'import-outbound-safety-not-confirmed';
		}
		if ( ! $backups_ready ) {
			$blockers[] = 'import-backups-not-confirmed';
		}
		if ( ! $authorized ) {
			$blockers[] = 'import-target-authorization-missing';
		}
		if ( ! $this->valid_http_url( $home_url ) || ! $this->valid_http_url( $site_url ) ) {
			$blockers[] = 'import-destination-url-invalid';
		}
		if ( 1 !== preg_match( '/^[A-Za-z0-9_]+$/', $table_prefix ) ) {
			$blockers[] = 'import-destination-table-prefix-invalid';
		}
		if ( false === $free ) {
			$blockers[] = 'import-destination-free-space-unknown';
		} elseif ( (int) $free < $required ) {
			$blockers[] = 'import-destination-free-space-insufficient';
		}

		$source_home = is_string( $source['home_url'] ?? null ) ? $source['home_url'] : '';
		$source_site = is_string( $source['site_url'] ?? null ) ? $source['site_url'] : '';
		if (
			'' !== $source_home
			&& untrailingslashit( $source_home ) === untrailingslashit( $home_url )
		) {
			$blockers[] = 'import-destination-home-is-source';
		}
		if (
			'' !== $source_site
			&& untrailingslashit( $source_site ) === untrailingslashit( $site_url )
		) {
			$blockers[] = 'import-destination-site-is-source';
		}

		$source_origin      = $this->origin_key( $source_home );
		$destination_origin = $this->origin_key( $home_url );
		if ( 'origin' === $mode && '' !== $source_origin && $source_origin === $destination_origin ) {
			$blockers[] = 'import-destination-origin-not-isolated';
		}
		if ( 'subdirectory' === $mode ) {
			if ( '' === $source_origin || $source_origin !== $destination_origin ) {
				$blockers[] = 'import-subdirectory-origin-mismatch';
			}
			if ( $this->base_path( $source_home ) === $this->base_path( $home_url ) ) {
				$blockers[] = 'import-subdirectory-path-not-distinct';
			}
			if ( ! $storage ) {
				$blockers[] = 'import-storage-isolation-not-confirmed';
			}
		}

		$source_prefix = is_string( $db_source['table_prefix'] ?? null ) ? $db_source['table_prefix'] : '';
		if ( '' !== $source_prefix && $source_prefix === $table_prefix ) {
			$advisories[] = 'source-target-table-prefix-equal-confirm-database-isolation';
		}

		return array(
			'home_url'                   => $home_url,
			'site_url'                   => $site_url,
			'table_prefix'               => $table_prefix,
			'mode'                       => $mode,
			'storage_isolated'           => $storage,
			'search_visibility_disabled' => $search_safe,
			'outbound_safe'              => $outbound_safe,
			'backups_ready'              => $backups_ready,
			'target_authorized'          => $authorized,
			'disk_free_bytes'            => false === $free ? null : (int) $free,
			'disk_required_bytes'        => $required,
			'blockers'                   => array_values( array_unique( $blockers ) ),
			'advisories'                 => array_values( array_unique( $advisories ) ),
		);
	}

	/**
	 * Read one bounded manifest entry from PclZip without extracting to disk.
	 *
	 * @param \PclZip $archive Archive instance.
	 * @param string  $name    Exact archive path.
	 */
	private function read_archive_entry( \PclZip $archive, string $name ): ?string {
		if (
			! defined( 'PCLZIP_OPT_BY_NAME' )
			|| ! defined( 'PCLZIP_OPT_EXTRACT_AS_STRING' )
		) {
			return null;
		}

		$by_name   = constant( 'PCLZIP_OPT_BY_NAME' );
		$as_string = constant( 'PCLZIP_OPT_EXTRACT_AS_STRING' );
		if ( ! is_int( $by_name ) || ! is_int( $as_string ) ) {
			return null;
		}

		// WordPress Core PclZip exposes variadic extraction options not represented by the static stub.
		$result = $archive->extract( $by_name, $name, $as_string ); // @phpstan-ignore arguments.count
		if ( ! is_array( $result ) ) {
			return null;
		}

		foreach ( $result as $entry ) {
			if (
				! is_array( $entry )
				|| ( $entry['filename'] ?? null ) !== $name
				|| ! is_string( $entry['content'] ?? null )
			) {
				continue;
			}
			if ( self::MAX_MANIFEST_BYTES < strlen( $entry['content'] ) ) {
				return null;
			}

			return $entry['content'];
		}

		return null;
	}

	/**
	 * Whether one archive entry path is traversal/absolute-path safe.
	 *
	 * @param string $name Raw ZIP entry name.
	 */
	private function safe_archive_path( string $name ): bool {
		if (
			'' === $name
			|| str_contains( $name, "\0" )
			|| str_contains( $name, '\\' )
			|| str_starts_with( $name, '/' )
			|| 1 === preg_match( '/^[A-Za-z]:\//', $name )
			|| str_contains( $name, '//' )
		) {
			return false;
		}

		$segments = explode( '/', trim( $name, '/' ) );
		foreach ( $segments as $segment ) {
			if ( '' === $segment || '.' === $segment || '..' === $segment ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Limit package entries to known Portable Clone workspace roots/guards.
	 *
	 * @param string $path Canonical entry path.
	 */
	private function allowed_archive_path( string $path ): bool {
		if ( in_array( $path, array( '.htaccess', 'index.php' ), true ) ) {
			return true;
		}

		foreach ( array( 'database', 'database-meta', 'files', 'files-meta', 'package' ) as $root ) {
			if ( $root === $path || str_starts_with( $path, $root . '/' ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Load WordPress Core PclZip.
	 */
	private function load_pclzip(): bool {
		if ( class_exists( '\\PclZip' ) ) {
			return true;
		}

		$path = trailingslashit( ABSPATH ) . 'wp-admin/includes/class-pclzip.php';
		if ( ! is_file( $path ) || ! is_readable( $path ) ) {
			return false;
		}

		require_once $path;

		return class_exists( '\\PclZip' );
	}

	/**
	 * Validate one package identifier.
	 *
	 * @param mixed $package_id Raw package ID.
	 */
	private function valid_package_id( mixed $package_id ): bool {
		return is_string( $package_id )
			&& 1 === preg_match( '/^[A-Za-z0-9][A-Za-z0-9._:-]{7,127}$/', $package_id );
	}

	/**
	 * Validate one SHA-256 value.
	 *
	 * @param mixed $hash Raw hash.
	 */
	private function valid_hash( mixed $hash ): bool {
		return is_string( $hash ) && 1 === preg_match( '/^[a-f0-9]{64}$/', $hash );
	}

	/**
	 * Validate one HTTP(S) URL.
	 *
	 * @param mixed $url Raw URL.
	 */
	private function valid_http_url( mixed $url ): bool {
		if ( ! is_string( $url ) ) {
			return false;
		}

		return in_array( wp_parse_url( $url, PHP_URL_SCHEME ), array( 'http', 'https' ), true )
			&& is_string( wp_parse_url( $url, PHP_URL_HOST ) );
	}

	/**
	 * Normalize an HTTP(S) URL to scheme + host + effective port.
	 *
	 * @param string $url URL to normalize.
	 */
	private function origin_key( string $url ): string {
		$scheme = wp_parse_url( $url, PHP_URL_SCHEME );
		$host   = wp_parse_url( $url, PHP_URL_HOST );
		$port   = wp_parse_url( $url, PHP_URL_PORT );
		if ( ! is_string( $scheme ) || ! is_string( $host ) ) {
			return '';
		}

		$scheme = strtolower( $scheme );
		$host   = strtolower( $host );
		if ( ! in_array( $scheme, array( 'http', 'https' ), true ) ) {
			return '';
		}

		$effective_port = is_int( $port ) ? $port : ( 'https' === $scheme ? 443 : 80 );

		return $scheme . '://' . $host . ':' . (string) $effective_port;
	}

	/**
	 * Return normalized URL base path.
	 *
	 * @param string $url URL to inspect.
	 */
	private function base_path( string $url ): string {
		$path = wp_parse_url( $url, PHP_URL_PATH );
		if ( ! is_string( $path ) || '' === $path ) {
			return '/';
		}

		$path = '/' . trim( $path, '/' );

		return '/' === $path ? '/' : trailingslashit( $path );
	}

	/**
	 * Persist one bounded import-preflight blocker.
	 *
	 * @param string              $job_id    Clone job identifier.
	 * @param array<string,mixed> $state     Current state.
	 * @param string              $code      Machine-readable blocker.
	 * @param bool                $retryable Whether retry is allowed.
	 * @return array<string,mixed>|null
	 */
	private function block( string $job_id, array $state, string $code, bool $retryable ): ?array {
		$blockers                       = is_array( $state['blockers'] ?? null ) ? $state['blockers'] : array();
		$blockers[]                     = $code;
		$state['schema_version']        = ImportStateStore::SCHEMA_VERSION;
		$state['job_id']                = $job_id;
		$state['status']                = 'blocked';
		$state['blockers']              = array_values( array_unique( $blockers ) );
		$state['restore_allowed']       = false;
		$state['full_payload_verified'] = false;
		$state['updated_at']            = gmdate( DATE_ATOM );

		if ( ! $this->store->save( $job_id, $state ) ) {
			return null;
		}

		$this->jobs->transition( $job_id, $retryable ? 'failed-retryable' : 'failed-terminal', $code );

		return $this->store->get( $job_id );
	}
}
