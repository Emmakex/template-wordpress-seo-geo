<?php
/**
 * Portable Clone resumable file restore into job-owned destination staging.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge\Clone;

/**
 * Restores verified uploads/plugins/themes into an isolated staging tree only.
 */
final class ImportFileRestorer {
	public const MIN_BATCH_FILES     = 1;
	public const MAX_BATCH_FILES     = 200;
	public const DEFAULT_BATCH_FILES = 25;
	public const MIN_BATCH_BYTES     = 1048576;
	public const MAX_BATCH_BYTES     = 67108864;
	public const DEFAULT_BATCH_BYTES = 8388608;

	private const MAX_PENDING_DIRECTORIES = 50000;
	private const MAX_ENTRY_OPERATIONS    = 4000;
	private const MAX_RECORD_BYTES        = 1048576;

	/**
	 * File restore state.
	 *
	 * @var ImportFileStateStore
	 */
	private ImportFileStateStore $store;

	/**
	 * Import state.
	 *
	 * @var ImportStateStore
	 */
	private ImportStateStore $import_state;

	/**
	 * Verified payload state.
	 *
	 * @var ImportPayloadStateStore
	 */
	private ImportPayloadStateStore $payload_state;

	/**
	 * Completed database staging state.
	 *
	 * @var ImportDatabaseStateStore
	 */
	private ImportDatabaseStateStore $database_state;

	/**
	 * Clone jobs.
	 *
	 * @var CloneJobStore
	 */
	private CloneJobStore $jobs;

	/**
	 * Fresh destination preflight.
	 *
	 * @var ImportPreflight
	 */
	private ImportPreflight $preflight;

	/**
	 * Verified private extraction workspace.
	 *
	 * @var ExportWorkspace
	 */
	private ExportWorkspace $workspace;

	/**
	 * Destination staging workspace.
	 *
	 * @var ImportFileStagingWorkspace
	 */
	private ImportFileStagingWorkspace $staging;

	/**
	 * Construct file restorer.
	 *
	 * @param ImportFileStateStore|null       $store          Optional file state store.
	 * @param ImportStateStore|null           $import_state   Optional import state.
	 * @param ImportPayloadStateStore|null    $payload_state  Optional payload state.
	 * @param ImportDatabaseStateStore|null   $database_state Optional database restore state.
	 * @param CloneJobStore|null              $jobs           Optional clone jobs.
	 * @param ImportPreflight|null            $preflight      Optional fresh preflight service.
	 * @param ExportWorkspace|null            $workspace      Optional private extraction workspace.
	 * @param ImportFileStagingWorkspace|null $staging        Optional destination staging workspace.
	 */
	public function __construct(
		?ImportFileStateStore $store = null,
		?ImportStateStore $import_state = null,
		?ImportPayloadStateStore $payload_state = null,
		?ImportDatabaseStateStore $database_state = null,
		?CloneJobStore $jobs = null,
		?ImportPreflight $preflight = null,
		?ExportWorkspace $workspace = null,
		?ImportFileStagingWorkspace $staging = null
	) {
		$this->store          = $store ?? new ImportFileStateStore();
		$this->import_state   = $import_state ?? new ImportStateStore();
		$this->payload_state  = $payload_state ?? new ImportPayloadStateStore();
		$this->database_state = $database_state ?? new ImportDatabaseStateStore();
		$this->jobs           = $jobs ?? new CloneJobStore();
		$this->workspace      = $workspace ?? new ExportWorkspace();
		$this->staging        = $staging ?? new ImportFileStagingWorkspace();
		$this->preflight      = $preflight ?? new ImportPreflight( $this->import_state, $this->jobs, $this->workspace );
	}

	/**
	 * Return one file-restore snapshot.
	 *
	 * @param string $job_id Clone job identifier.
	 * @return array<string,mixed>|null
	 */
	public function snapshot( string $job_id ): ?array {
		return $this->store->get( $job_id );
	}

	/**
	 * Initialize destination file staging after verified payload + database staging.
	 *
	 * @param string $job_id Clone job identifier.
	 * @return array<string,mixed>|null
	 */
	public function start( string $job_id ): ?array {
		$existing = $this->store->get( $job_id );
		if ( is_array( $existing ) ) {
			return $existing;
		}

		$fresh = $this->fresh_restore_gate( $job_id );
		if ( null === $fresh ) {
			return null;
		}

		$database = $this->database_state->get( $job_id );
		if (
			! is_array( $database )
			|| 'complete' !== ( $database['status'] ?? null )
			|| true !== ( $database['active_tables_untouched'] ?? false )
		) {
			return null;
		}

		$bundle = $this->files_manifest( $job_id );
		if ( null === $bundle ) {
			return null;
		}

		$payload = $this->payload_state->get( $job_id );
		if (
			! is_array( $payload )
			|| 'complete' !== ( $payload['status'] ?? null )
			|| ! $this->same_hash( $payload['archive_sha256'] ?? '', $fresh['archive_sha256'] ?? '' )
		) {
			return null;
		}

		$root = $this->staging->ensure( $job_id );
		if ( null === $root || ! $this->staging->payload_empty( $job_id ) ) {
			return null;
		}

		$manifest = $bundle['manifest'];
		$roots    = is_array( $manifest['roots'] ?? null ) ? array_values( $manifest['roots'] ) : array();
		$now      = gmdate( DATE_ATOM );
		$state    = array(
			'schema_version'         => ImportFileStateStore::SCHEMA_VERSION,
			'job_id'                 => $job_id,
			'status'                 => 'running',
			'files_manifest_sha256'  => (string) $bundle['sha256'],
			'payload_archive_sha256' => (string) $payload['archive_sha256'],
			'staging_root_key'       => $this->staging->root_key( $job_id ),
			'root_index'             => 0,
			'root_count'             => count( $roots ),
			'pending_dirs'           => array( '' ),
			'current_dir'            => '',
			'after_name'             => '',
			'file_count'             => 0,
			'byte_count'             => 0,
			'expected_file_count'    => max( 0, (int) ( $manifest['file_count'] ?? 0 ) ),
			'expected_byte_count'    => max( 0, (int) ( $manifest['payload_bytes'] ?? 0 ) ),
			'roots_completed'        => 0,
			'current_root_files'      => 0,
			'current_root_bytes'      => 0,
			'active_files_untouched' => true,
			'blockers'               => array(),
			'started_at'             => $now,
			'updated_at'             => $now,
			'completed_at'           => '',
		);

		if ( ! $this->store->save( $job_id, $state ) ) {
			return null;
		}

		$this->jobs->transition( $job_id, 'active' );
		$this->jobs->update_progress(
			$job_id,
			'restore-files',
			'0::',
			array(
				'completed' => 0,
				'total'     => (int) $state['expected_file_count'],
			)
		);

		return $this->store->get( $job_id );
	}

	/**
	 * Advance one bounded destination file-staging batch.
	 *
	 * @param string $job_id      Clone job identifier.
	 * @param int    $batch_files Maximum files staged this request.
	 * @param int    $batch_bytes Soft maximum bytes staged this request.
	 * @return array<string,mixed>|null
	 */
	public function advance(
		string $job_id,
		int $batch_files = self::DEFAULT_BATCH_FILES,
		int $batch_bytes = self::DEFAULT_BATCH_BYTES
	): ?array {
		$batch_files = max( self::MIN_BATCH_FILES, min( self::MAX_BATCH_FILES, $batch_files ) );
		$batch_bytes = max( self::MIN_BATCH_BYTES, min( self::MAX_BATCH_BYTES, $batch_bytes ) );
		$state       = $this->store->get( $job_id ) ?? $this->start( $job_id );
		if ( ! is_array( $state ) || in_array( $state['status'] ?? null, array( 'complete', 'blocked' ), true ) ) {
			return $state;
		}

		if ( null === $this->runtime_guard( $job_id, $state ) ) {
			return $this->block( $job_id, $state, 'import-files-runtime-guard-failed', false );
		}

		$bundle = $this->files_manifest( $job_id );
		if (
			null === $bundle
			|| ! $this->same_hash( $bundle['sha256'], $state['files_manifest_sha256'] ?? '' )
		) {
			return $this->block( $job_id, $state, 'import-files-manifest-changed', false );
		}

		$manifest       = $bundle['manifest'];
		$roots          = is_array( $manifest['roots'] ?? null ) ? array_values( $manifest['roots'] ) : array();
		$accepted_files = 0;
		$accepted_bytes = 0;
		$operations     = 0;

		while ( $accepted_files < $batch_files && $operations < self::MAX_ENTRY_OPERATIONS ) {
			$root_index = max( 0, (int) ( $state['root_index'] ?? 0 ) );
			if ( $root_index >= count( $roots ) ) {
				return $this->complete_files( $job_id, $state, $manifest );
			}

			$root_meta = is_array( $roots[ $root_index ] ?? null ) ? $roots[ $root_index ] : array();
			$root_id   = is_string( $root_meta['id'] ?? null ) ? $root_meta['id'] : '';
			if ( ! $this->root_manifest_valid( $root_meta ) ) {
				return $this->block( $job_id, $state, 'import-files-root-manifest-invalid', false );
			}

			$expected_root_files = max( 0, (int) ( $root_meta['file_count'] ?? 0 ) );
			$expected_root_bytes = max( 0, (int) ( $root_meta['byte_count'] ?? 0 ) );

			$pending = is_array( $state['pending_dirs'] ?? null ) ? $state['pending_dirs'] : array();
			if ( '' === (string) ( $state['current_dir'] ?? '' ) && '' === (string) ( $state['after_name'] ?? '' ) ) {
				if ( array() === $pending ) {
					if (
						(int) ( $state['current_root_files'] ?? 0 ) !== $expected_root_files
						|| (int) ( $state['current_root_bytes'] ?? 0 ) !== $expected_root_bytes
					) {
						return $this->block( $job_id, $state, 'import-files-root-count-mismatch', false );
					}

					$state['root_index']          = $root_index + 1;
					$state['roots_completed']     = (int) ( $state['roots_completed'] ?? 0 ) + 1;
					$state['pending_dirs']        = array( '' );
					$state['current_root_files']  = 0;
					$state['current_root_bytes']  = 0;
					$state['updated_at']          = gmdate( DATE_ATOM );
					continue;
				}

				$state['current_dir']  = (string) array_shift( $pending );
				$state['pending_dirs'] = $pending;
			}

			$source_base = $this->source_root( $job_id, $root_id );
			if ( null === $source_base ) {
				if ( 0 === $expected_root_files && 0 === $expected_root_bytes ) {
					$state['pending_dirs'] = array();
					$state['current_dir']  = '';
					$state['after_name']   = '';
					continue;
				}

				return $this->block( $job_id, $state, 'import-files-source-root-unavailable', false );
			}

			$current_dir = (string) ( $state['current_dir'] ?? '' );
			$absolute    = $this->join_path( $source_base, $current_dir );
			if ( ! str_starts_with( $absolute, trailingslashit( wp_normalize_path( $source_base ) ) ) && $absolute !== untrailingslashit( wp_normalize_path( $source_base ) ) ) {
				return $this->block( $job_id, $state, 'import-files-source-path-unsafe', false );
			}

			$entries = scandir( $absolute, SCANDIR_SORT_ASCENDING );
			if ( false === $entries ) {
				return $this->block( $job_id, $state, 'import-files-directory-read-failed', true );
			}

			$after_name   = (string) ( $state['after_name'] ?? '' );
			$dir_finished = true;

			foreach ( $entries as $entry ) {
				if ( '.' === $entry || '..' === $entry || ( '' !== $after_name && strcmp( $entry, $after_name ) <= 0 ) ) {
					continue;
				}

				++$operations;
				$relative = '' === $current_dir ? $entry : $current_dir . '/' . $entry;
				$path     = $this->join_path( $source_base, $relative );

				if ( is_link( $path ) ) {
					return $this->block( $job_id, $state, 'import-files-source-symlink', false );
				}

				if ( is_dir( $path ) ) {
					$pending = is_array( $state['pending_dirs'] ?? null ) ? $state['pending_dirs'] : array();
					if ( count( $pending ) >= self::MAX_PENDING_DIRECTORIES ) {
						return $this->block( $job_id, $state, 'import-files-directory-queue-limit', false );
					}
					$pending[]             = $relative;
					$state['pending_dirs'] = $pending;
					$state['after_name']   = $entry;
					if ( $operations >= self::MAX_ENTRY_OPERATIONS ) {
						$dir_finished = false;
						break;
					}
					continue;
				}

				if ( ! is_file( $path ) || ! is_readable( $path ) ) {
					return $this->block( $job_id, $state, 'import-files-source-unreadable', true );
				}

				$record = $this->file_record( $job_id, $root_id, $relative );
				if ( null === $record ) {
					return $this->block( $job_id, $state, 'import-files-record-invalid', false );
				}

				$payload_path = 'files/' . $root_id . '/' . $relative;
				$source_info  = $this->workspace->import_extracted_file_info( $job_id, $payload_path );
				if (
					! is_array( $source_info )
					|| (int) $record['byte_count'] !== (int) $source_info['bytes']
					|| ! $this->same_hash( $record['sha256'], $source_info['sha256'] )
				) {
					return $this->block( $job_id, $state, 'import-files-source-integrity-failed', false );
				}

				$staged = $this->staging->stage_file(
					$job_id,
					$root_id,
					$relative,
					(string) $source_info['path'],
					(int) $record['byte_count'],
					(string) $record['sha256']
				);
				if ( ! is_array( $staged ) ) {
					return $this->block( $job_id, $state, 'import-files-stage-write-failed', true );
				}

				$bytes                       = (int) $staged['bytes'];
				$state['file_count']         = (int) ( $state['file_count'] ?? 0 ) + 1;
				$state['byte_count']         = (int) ( $state['byte_count'] ?? 0 ) + $bytes;
				$state['current_root_files'] = (int) ( $state['current_root_files'] ?? 0 ) + 1;
				$state['current_root_bytes'] = (int) ( $state['current_root_bytes'] ?? 0 ) + $bytes;
				$state['after_name']         = $entry;
				++$accepted_files;
				$accepted_bytes += $bytes;

				if (
					$accepted_files >= $batch_files
					|| $accepted_bytes >= $batch_bytes
					|| $operations >= self::MAX_ENTRY_OPERATIONS
				) {
					$dir_finished = false;
					break;
				}
			}

			if ( $dir_finished ) {
				$state['current_dir'] = '';
				$state['after_name']  = '';
			}

			if ( 0 < $accepted_files && $accepted_bytes >= $batch_bytes ) {
				break;
			}
		}

		return $this->persist_progress( $job_id, $state );
	}

	/**
	 * Complete file staging after exact manifest/staging reconciliation.
	 *
	 * @param string              $job_id   Clone job identifier.
	 * @param array<string,mixed> $state    Restore state.
	 * @param array<string,mixed> $manifest Files manifest.
	 * @return array<string,mixed>|null
	 */
	private function complete_files( string $job_id, array $state, array $manifest ): ?array {
		$summary = $this->staging->payload_summary( $job_id );
		if (
			! is_array( $summary )
			|| (int) ( $state['file_count'] ?? -1 ) !== (int) ( $manifest['file_count'] ?? -2 )
			|| (int) ( $state['byte_count'] ?? -1 ) !== (int) ( $manifest['payload_bytes'] ?? -2 )
			|| (int) $summary['files'] !== (int) ( $manifest['file_count'] ?? -2 )
			|| (int) $summary['bytes'] !== (int) ( $manifest['payload_bytes'] ?? -2 )
			|| (int) ( $state['roots_completed'] ?? -1 ) !== (int) ( $state['root_count'] ?? -2 )
		) {
			return $this->block( $job_id, $state, 'import-files-staging-reconciliation-failed', false );
		}

		$state['status']                 = 'complete';
		$state['active_files_untouched'] = true;
		$state['updated_at']             = gmdate( DATE_ATOM );
		$state['completed_at']           = $state['updated_at'];
		if ( ! $this->store->save( $job_id, $state ) ) {
			return null;
		}

		$this->jobs->transition( $job_id, 'active' );
		$this->jobs->update_progress(
			$job_id,
			'restore-files',
			'staging-complete',
			array(
				'completed' => (int) $state['file_count'],
				'total'     => (int) $state['expected_file_count'],
			)
		);

		return $this->store->get( $job_id );
	}

	/**
	 * Persist bounded staging progress.
	 *
	 * @param string              $job_id Clone job identifier.
	 * @param array<string,mixed> $state  Restore state.
	 * @return array<string,mixed>|null
	 */
	private function persist_progress( string $job_id, array $state ): ?array {
		$state['updated_at'] = gmdate( DATE_ATOM );
		if ( ! $this->store->save( $job_id, $state ) ) {
			return null;
		}

		$cursor = (string) (int) ( $state['root_index'] ?? 0 )
			. ':' . (string) ( $state['current_dir'] ?? '' )
			. ':' . (string) ( $state['after_name'] ?? '' );

		$this->jobs->update_progress(
			$job_id,
			'restore-files',
			substr( $cursor, 0, 512 ),
			array(
				'completed' => (int) ( $state['file_count'] ?? 0 ),
				'total'     => (int) ( $state['expected_file_count'] ?? 0 ),
			)
		);

		return $this->store->get( $job_id );
	}

	/**
	 * Revalidate destination/package/database state before every mutating batch.
	 *
	 * @param string              $job_id Clone job identifier.
	 * @param array<string,mixed> $state  Restore state.
	 * @return array<string,mixed>|null
	 */
	private function runtime_guard( string $job_id, array $state ): ?array {
		$fresh = $this->fresh_restore_gate( $job_id );
		if ( null === $fresh || true !== ( $state['active_files_untouched'] ?? false ) ) {
			return null;
		}

		$payload = $this->payload_state->get( $job_id );
		if (
			! is_array( $payload )
			|| 'complete' !== ( $payload['status'] ?? null )
			|| ! $this->same_hash( $payload['archive_sha256'] ?? '', $state['payload_archive_sha256'] ?? '' )
		) {
			return null;
		}

		$database = $this->database_state->get( $job_id );
		if (
			! is_array( $database )
			|| 'complete' !== ( $database['status'] ?? null )
			|| true !== ( $database['active_tables_untouched'] ?? false )
		) {
			return null;
		}

		$bundle = $this->files_manifest( $job_id );
		if (
			null === $bundle
			|| ! $this->same_hash( $bundle['sha256'], $state['files_manifest_sha256'] ?? '' )
			|| $this->staging->root_key( $job_id ) !== (string) ( $state['staging_root_key'] ?? '' )
			|| null === $this->staging->payload_summary( $job_id )
		) {
			return null;
		}

		return $fresh;
	}

	/**
	 * Run a fresh preflight and require verified-payload restore eligibility.
	 *
	 * @param string $job_id Clone job identifier.
	 * @return array<string,mixed>|null
	 */
	private function fresh_restore_gate( string $job_id ): ?array {
		$job = $this->jobs->get( $job_id );
		if ( ! is_array( $job ) || 'import' !== ( $job['operation'] ?? null ) ) {
			return null;
		}

		$fresh = $this->preflight->validate( $job_id );
		if (
			! is_array( $fresh )
			|| 'payload-verified' !== ( $fresh['status'] ?? null )
			|| true !== ( $fresh['full_payload_verified'] ?? false )
			|| true !== ( $fresh['restore_allowed'] ?? false )
			|| array() !== ( $fresh['blockers'] ?? array() )
		) {
			return null;
		}

		return $fresh;
	}

	/**
	 * Load and validate files manifest through the verified package manifest.
	 *
	 * @param string $job_id Clone job identifier.
	 * @return array{manifest:array<string,mixed>,sha256:string,package_sha256:string}|null
	 */
	private function files_manifest( string $job_id ): ?array {
		$package_json = $this->workspace->read_import_extracted_file( $job_id, 'package/manifest.json' );
		$files_json   = $this->workspace->read_import_extracted_file( $job_id, 'files/manifest.json' );
		if ( ! is_string( $package_json ) || ! is_string( $files_json ) ) {
			return null;
		}

		$package = json_decode( $package_json, true );
		$files   = json_decode( $files_json, true );
		if ( ! is_array( $package ) || ! is_array( $files ) ) {
			return null;
		}

		$payload = is_array( $package['payload'] ?? null ) ? $package['payload'] : array();
		$ref     = is_array( $payload['files'] ?? null ) ? $payload['files'] : array();
		$hash    = hash( 'sha256', $files_json );
		if (
			1 !== ( $package['schema_version'] ?? null )
			|| 'portable-clone-package' !== ( $package['mode'] ?? null )
			|| 'files/manifest.json' !== ( $ref['manifest_path'] ?? null )
			|| ! $this->same_hash( $ref['manifest_sha256'] ?? '', $hash )
			|| ! $this->files_manifest_valid( $files )
		) {
			return null;
		}

		$import = $this->import_state->get( $job_id );
		if (
			! is_array( $import )
			|| ! $this->same_hash( $import['package_manifest_sha256'] ?? '', hash( 'sha256', $package_json ) )
		) {
			return null;
		}

		return array(
			'manifest'       => $files,
			'sha256'         => $hash,
			'package_sha256' => hash( 'sha256', $package_json ),
		);
	}

	/**
	 * Validate the files-manifest contract used by the restore stage.
	 *
	 * @param array<string,mixed> $manifest Files manifest.
	 */
	private function files_manifest_valid( array $manifest ): bool {
		if (
			1 !== ( $manifest['schema_version'] ?? null )
			|| 'files' !== ( $manifest['payload_class'] ?? null )
			|| true !== ( $manifest['production_source_read_only'] ?? false )
			|| false !== ( $manifest['credentials_in_payload'] ?? true )
			|| ! is_array( $manifest['roots'] ?? null )
		) {
			return false;
		}

		$records = is_array( $manifest['file_records'] ?? null ) ? $manifest['file_records'] : array();
		if (
			'one-json-record-per-file' !== ( $records['format'] ?? null )
			|| 'files-meta/' !== ( $records['directory'] ?? null )
		) {
			return false;
		}

		$seen  = array();
		$files = 0;
		$bytes = 0;
		foreach ( $manifest['roots'] as $root ) {
			if ( ! is_array( $root ) || ! $this->root_manifest_valid( $root ) ) {
				return false;
			}
			$id = (string) $root['id'];
			if ( isset( $seen[ $id ] ) ) {
				return false;
			}
			$seen[ $id ] = true;
			$files      += max( 0, (int) ( $root['file_count'] ?? 0 ) );
			$bytes      += max( 0, (int) ( $root['byte_count'] ?? 0 ) );
		}

		return $files === max( 0, (int) ( $manifest['file_count'] ?? -1 ) )
			&& $bytes === max( 0, (int) ( $manifest['payload_bytes'] ?? -1 ) );
	}

	/**
	 * Validate one root summary.
	 *
	 * @param array<string,mixed> $root Root summary.
	 */
	private function root_manifest_valid( array $root ): bool {
		return is_string( $root['id'] ?? null )
			&& in_array( $root['id'], array( 'uploads', 'plugins', 'themes' ), true )
			&& 0 <= (int) ( $root['file_count'] ?? -1 )
			&& 0 <= (int) ( $root['byte_count'] ?? -1 );
	}

	/**
	 * Load one verified per-file metadata record.
	 *
	 * @param string $job_id   Clone job identifier.
	 * @param string $root_id  Root identifier.
	 * @param string $relative Root-relative payload path.
	 * @return array{root:string,relative_path:string,payload_path:string,byte_count:int,sha256:string}|null
	 */
	private function file_record( string $job_id, string $root_id, string $relative ): ?array {
		$relative    = $this->normalize_relative( $relative );
		$record_path = 'files-meta/' . $root_id . '/' . hash( 'sha256', $relative ) . '.json';
		$info        = $this->workspace->import_extracted_file_info( $job_id, $record_path );
		$json        = $this->workspace->read_import_extracted_file( $job_id, $record_path );
		if (
			'' === $relative
			|| ! is_array( $info )
			|| self::MAX_RECORD_BYTES < (int) $info['bytes']
			|| ! is_string( $json )
		) {
			return null;
		}

		$record       = json_decode( $json, true );
		$payload_path = 'files/' . $root_id . '/' . $relative;
		if (
			! is_array( $record )
			|| $root_id !== ( $record['root'] ?? null )
			|| $relative !== ( $record['relative_path'] ?? null )
			|| $payload_path !== ( $record['payload_path'] ?? null )
			|| 'copied' !== ( $record['export_status'] ?? null )
			|| 0 > (int) ( $record['byte_count'] ?? -1 )
			|| ! is_string( $record['sha256'] ?? null )
			|| 1 !== preg_match( '/^[a-f0-9]{64}$/', (string) $record['sha256'] )
		) {
			return null;
		}

		return array(
			'root'          => $root_id,
			'relative_path' => $relative,
			'payload_path'  => $payload_path,
			'byte_count'    => (int) $record['byte_count'],
			'sha256'        => (string) $record['sha256'],
		);
	}

	/**
	 * Return one extracted payload root.
	 *
	 * @param string $job_id  Clone job identifier.
	 * @param string $root_id Root identifier.
	 */
	private function source_root( string $job_id, string $root_id ): ?string {
		if ( ! in_array( $root_id, array( 'uploads', 'plugins', 'themes' ), true ) ) {
			return null;
		}

		$root = $this->workspace->import_extraction_root( $job_id );
		if ( null === $root ) {
			return null;
		}

		$path = wp_normalize_path( trailingslashit( $root ) . 'files/' . $root_id );

		return is_dir( $path ) && ! is_link( $path ) ? trailingslashit( $path ) : null;
	}

	/**
	 * Normalize root-relative file path.
	 *
	 * @param string $relative Raw path.
	 */
	private function normalize_relative( string $relative ): string {
		$relative = ltrim( wp_normalize_path( trim( $relative ) ), '/' );
		if (
			'' === $relative
			|| 2048 < strlen( $relative )
			|| str_contains( $relative, '../' )
			|| str_contains( $relative, '/..' )
			|| '..' === $relative
			|| str_contains( $relative, "\0" )
		) {
			return '';
		}

		return $relative;
	}

	/**
	 * Persist one file-restore blocker.
	 *
	 * @param string              $job_id    Clone job identifier.
	 * @param array<string,mixed> $state     Restore state.
	 * @param string              $code      Machine-readable blocker.
	 * @param bool                $retryable Retry allowed.
	 * @return array<string,mixed>|null
	 */
	private function block( string $job_id, array $state, string $code, bool $retryable ): ?array {
		$blockers                        = is_array( $state['blockers'] ?? null ) ? $state['blockers'] : array();
		$blockers[]                      = $code;
		$state['status']                 = 'blocked';
		$state['blockers']               = array_values( array_unique( $blockers ) );
		$state['active_files_untouched'] = true;
		$state['updated_at']             = gmdate( DATE_ATOM );
		$this->store->save( $job_id, $state );
		$this->jobs->transition( $job_id, $retryable ? 'failed-retryable' : 'failed-terminal', $code );

		return $this->store->get( $job_id );
	}

	/**
	 * Constant-time compare validated SHA-256 values.
	 *
	 * @param mixed $left  First hash.
	 * @param mixed $right Second hash.
	 */
	private function same_hash( mixed $left, mixed $right ): bool {
		return is_string( $left )
			&& is_string( $right )
			&& 1 === preg_match( '/^[a-f0-9]{64}$/', $left )
			&& 1 === preg_match( '/^[a-f0-9]{64}$/', $right )
			&& hash_equals( $left, $right );
	}

	/**
	 * Join normalized filesystem paths.
	 *
	 * @param string $base     Absolute base.
	 * @param string $relative Relative path.
	 */
	private function join_path( string $base, string $relative ): string {
		return rtrim( wp_normalize_path( $base ), '/' )
			. ( '' === $relative ? '' : '/' . ltrim( wp_normalize_path( $relative ), '/' ) );
	}
}
