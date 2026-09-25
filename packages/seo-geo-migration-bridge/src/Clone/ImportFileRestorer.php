<?php
/**
 * Portable Clone resumable staging-file restore.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge\Clone;

/**
 * Restores verified uploads/plugins/themes only into job-owned staging storage.
 */
final class ImportFileRestorer {
	public const MIN_BATCH_FILES     = 1;
	public const MAX_BATCH_FILES     = 500;
	public const DEFAULT_BATCH_FILES = 10;
	public const MIN_BATCH_BYTES     = 1048576;
	public const MAX_BATCH_BYTES     = 134217728;
	public const DEFAULT_BATCH_BYTES = 8388608;

	private const MAX_PENDING_DIRECTORIES = 50000;
	private const MAX_ENTRY_OPERATIONS    = 8000;

	private ImportFileStateStore $store;
	private ImportStateStore $import_state;
	private ImportPayloadStateStore $payload_state;
	private ImportDatabaseStateStore $database_state;
	private CloneJobStore $jobs;
	private ImportPreflight $preflight;
	private ExportWorkspace $workspace;

	public function __construct(
		?ImportFileStateStore $store = null,
		?ImportStateStore $import_state = null,
		?ImportPayloadStateStore $payload_state = null,
		?ImportDatabaseStateStore $database_state = null,
		?CloneJobStore $jobs = null,
		?ImportPreflight $preflight = null,
		?ExportWorkspace $workspace = null
	) {
		$this->store          = $store ?? new ImportFileStateStore();
		$this->import_state   = $import_state ?? new ImportStateStore();
		$this->payload_state  = $payload_state ?? new ImportPayloadStateStore();
		$this->database_state = $database_state ?? new ImportDatabaseStateStore();
		$this->jobs           = $jobs ?? new CloneJobStore();
		$this->workspace      = $workspace ?? new ExportWorkspace();
		$this->preflight      = $preflight ?? new ImportPreflight( $this->import_state, $this->jobs, $this->workspace );
	}

	/**
	 * Return one restore snapshot.
	 *
	 * @param string $job_id Clone job identifier.
	 * @return array<string,mixed>|null
	 */
	public function snapshot( string $job_id ): ?array {
		return $this->store->get( $job_id );
	}

	/**
	 * Start staging file restore after database staging has completed.
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
		$db    = $this->database_state->get( $job_id );
		if (
			null === $fresh
			|| ! is_array( $db )
			|| 'complete' !== ( $db['status'] ?? null )
			|| true !== ( $db['active_tables_untouched'] ?? false )
		) {
			return null;
		}

		$bundle = $this->files_manifest( $job_id );
		if ( null === $bundle ) {
			return null;
		}

		$manifest = $bundle['manifest'];
		$roots    = $this->manifest_roots( $manifest );
		$payload  = $this->payload_state->get( $job_id );
		$staging  = $this->workspace->prepare_import_file_staging( $job_id );
		if (
			null === $roots
			|| ! is_array( $payload )
			|| null === $staging
			|| ! $this->directory_empty( $staging )
		) {
			return null;
		}

		$now   = gmdate( DATE_ATOM );
		$state = array(
			'schema_version'         => ImportFileStateStore::SCHEMA_VERSION,
			'job_id'                 => $job_id,
			'status'                 => 'running',
			'stage'                  => 'copy',
			'root_index'             => 0,
			'root_count'             => count( $roots ),
			'pending_dirs'           => array( '' ),
			'current_dir'            => '',
			'after_name'             => '',
			'file_count'             => 0,
			'byte_count'             => 0,
			'verify_file_count'      => 0,
			'verify_byte_count'      => 0,
			'expected_file_count'    => (int) ( $manifest['file_count'] ?? 0 ),
			'expected_byte_count'    => (int) ( $manifest['payload_bytes'] ?? 0 ),
			'files_manifest_sha256'  => $bundle['sha256'],
			'payload_archive_sha256' => (string) ( $payload['archive_sha256'] ?? '' ),
			'active_roots_untouched' => true,
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
			'copy:0:',
			array(
				'completed' => 0,
				'total'     => (int) $state['expected_file_count'],
			)
		);

		return $this->store->get( $job_id );
	}

	/**
	 * Advance one bounded copy or verification batch.
	 *
	 * @param string $job_id      Clone job identifier.
	 * @param int    $batch_files Maximum files this request.
	 * @param int    $batch_bytes Soft maximum bytes this request.
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
			|| ! hash_equals( (string) ( $state['files_manifest_sha256'] ?? '' ), $bundle['sha256'] )
		) {
			return $this->block( $job_id, $state, 'import-files-manifest-changed', false );
		}

		$roots = $this->manifest_roots( $bundle['manifest'] );
		if ( null === $roots ) {
			return $this->block( $job_id, $state, 'import-files-root-contract-invalid', false );
		}

		$processed_files = 0;
		$processed_bytes = 0;
		$operations      = 0;

		while ( $processed_files < $batch_files && $operations < self::MAX_ENTRY_OPERATIONS ) {
			$root_index = (int) ( $state['root_index'] ?? 0 );
			if ( $root_index >= count( $roots ) ) {
				return 'copy' === ( $state['stage'] ?? null )
					? $this->start_verify_pass( $job_id, $state )
					: $this->complete_files( $job_id, $state );
			}

			$root      = $roots[ $root_index ];
			$root_id   = (string) $root['id'];
			$root_base = 'copy' === ( $state['stage'] ?? null )
				? $this->extracted_root( $job_id, $root_id )
				: $this->staging_root( $job_id, $root_id );

			if ( null === $root_base ) {
				if ( 0 === (int) $root['file_count'] ) {
					$state = $this->advance_root( $state );
					continue;
				}

				return $this->block( $job_id, $state, 'import-files-root-unavailable', false );
			}

			$pending = is_array( $state['pending_dirs'] ?? null ) ? $state['pending_dirs'] : array();
			if ( '' === (string) ( $state['current_dir'] ?? '' ) && '' === (string) ( $state['after_name'] ?? '' ) ) {
				if ( array() === $pending ) {
					$state = $this->advance_root( $state );
					continue;
				}
				$state['current_dir']  = (string) array_shift( $pending );
				$state['pending_dirs'] = $pending;
			}

			$current_dir = (string) ( $state['current_dir'] ?? '' );
			$absolute    = $this->join_path( $root_base, $current_dir );
			$entries     = scandir( $absolute, SCANDIR_SORT_ASCENDING );
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
				$path     = $this->join_path( $root_base, $relative );

				if ( is_link( $path ) ) {
					return $this->block( $job_id, $state, 'import-files-staging-symlink', false );
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
					if ( 'copy' === ( $state['stage'] ?? null ) && $this->deterministic_guard_file( $path, $entry ) ) {
						$state['after_name'] = $entry;
						continue;
					}

					return $this->block( $job_id, $state, 'import-files-record-missing', false );
				}

				if ( 'copy' === ( $state['stage'] ?? null ) ) {
					$result = $this->workspace->stage_import_payload_file( $job_id, $root_id, $relative );
					if (
						null === $result
						|| (int) $record['byte_count'] !== (int) $result['bytes']
						|| ! hash_equals( (string) $record['sha256'], (string) $result['sha256'] )
					) {
						return $this->block( $job_id, $state, 'import-files-copy-integrity-failed', false );
					}

					$state['file_count'] = (int) ( $state['file_count'] ?? 0 ) + 1;
					$state['byte_count'] = (int) ( $state['byte_count'] ?? 0 ) + (int) $result['bytes'];
					$bytes               = (int) $result['bytes'];
				} else {
					$result = $this->workspace->import_staged_file_info( $job_id, $root_id, $relative );
					if (
						null === $result
						|| (int) $record['byte_count'] !== (int) $result['bytes']
						|| ! hash_equals( (string) $record['sha256'], (string) $result['sha256'] )
					) {
						return $this->block( $job_id, $state, 'import-files-verification-failed', false );
					}

					$state['verify_file_count'] = (int) ( $state['verify_file_count'] ?? 0 ) + 1;
					$state['verify_byte_count'] = (int) ( $state['verify_byte_count'] ?? 0 ) + (int) $result['bytes'];
					$bytes                      = (int) $result['bytes'];
				}

				$state['after_name'] = $entry;
				++$processed_files;
				$processed_bytes += $bytes;

				if (
					$processed_files >= $batch_files
					|| $processed_bytes >= $batch_bytes
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

			if ( 0 < $processed_files && $processed_bytes >= $batch_bytes ) {
				break;
			}
		}

		return $this->persist_progress( $job_id, $state );
	}

	/**
	 * Begin the independent staged-file verification pass.
	 *
	 * @param string              $job_id Clone job identifier.
	 * @param array<string,mixed> $state  Restore state.
	 * @return array<string,mixed>|null
	 */
	private function start_verify_pass( string $job_id, array $state ): ?array {
		if (
			(int) ( $state['file_count'] ?? 0 ) !== (int) ( $state['expected_file_count'] ?? -1 )
			|| (int) ( $state['byte_count'] ?? 0 ) !== (int) ( $state['expected_byte_count'] ?? -1 )
		) {
			return $this->block( $job_id, $state, 'import-files-copy-count-mismatch', false );
		}

		$state['stage']             = 'verify';
		$state['root_index']        = 0;
		$state['pending_dirs']      = array( '' );
		$state['current_dir']       = '';
		$state['after_name']        = '';
		$state['verify_file_count'] = 0;
		$state['verify_byte_count'] = 0;
		$state['updated_at']        = gmdate( DATE_ATOM );

		if ( ! $this->store->save( $job_id, $state ) ) {
			return null;
		}

		return $this->store->get( $job_id );
	}

	/**
	 * Complete restore after exact independent reconciliation.
	 *
	 * @param string              $job_id Clone job identifier.
	 * @param array<string,mixed> $state  Restore state.
	 * @return array<string,mixed>|null
	 */
	private function complete_files( string $job_id, array $state ): ?array {
		if (
			(int) ( $state['verify_file_count'] ?? 0 ) !== (int) ( $state['expected_file_count'] ?? -1 )
			|| (int) ( $state['verify_byte_count'] ?? 0 ) !== (int) ( $state['expected_byte_count'] ?? -1 )
			|| true !== ( $state['active_roots_untouched'] ?? false )
		) {
			return $this->block( $job_id, $state, 'import-files-final-count-mismatch', false );
		}

		$now                   = gmdate( DATE_ATOM );
		$state['status']       = 'complete';
		$state['stage']        = 'complete';
		$state['updated_at']   = $now;
		$state['completed_at'] = $now;

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
	 * Revalidate destination, payload, database staging and manifest identity.
	 *
	 * @param string              $job_id Clone job identifier.
	 * @param array<string,mixed> $state  Restore state.
	 * @return array<string,mixed>|null
	 */
	private function runtime_guard( string $job_id, array $state ): ?array {
		$fresh = $this->fresh_restore_gate( $job_id );
		$db    = $this->database_state->get( $job_id );
		$pay   = $this->payload_state->get( $job_id );
		if (
			null === $fresh
			|| ! is_array( $db )
			|| 'complete' !== ( $db['status'] ?? null )
			|| true !== ( $db['active_tables_untouched'] ?? false )
			|| ! is_array( $pay )
			|| 'complete' !== ( $pay['status'] ?? null )
			|| ! hash_equals(
				(string) ( $state['payload_archive_sha256'] ?? '' ),
				(string) ( $pay['archive_sha256'] ?? '' )
			)
		) {
			return null;
		}

		return $fresh;
	}

	/**
	 * Require current verified-payload restore eligibility.
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
	 * Load and bind files manifest to the verified package.
	 *
	 * @param string $job_id Clone job identifier.
	 * @return array{manifest:array<string,mixed>,sha256:string}|null
	 */
	private function files_manifest( string $job_id ): ?array {
		$payload = $this->payload_state->get( $job_id );
		if ( ! is_array( $payload ) || 'complete' !== ( $payload['status'] ?? null ) ) {
			return null;
		}

		$package_info = $this->workspace->import_extracted_file_info( $job_id, 'package/manifest.json' );
		$package_json = $this->workspace->read_import_extracted_file( $job_id, 'package/manifest.json' );
		$files_info   = $this->workspace->import_extracted_file_info( $job_id, 'files/manifest.json' );
		$files_json   = $this->workspace->read_import_extracted_file( $job_id, 'files/manifest.json' );
		if (
			! is_array( $package_info )
			|| ! is_string( $package_json )
			|| ! is_array( $files_info )
			|| ! is_string( $files_json )
			|| ! hash_equals(
				(string) ( $payload['package_manifest_sha256'] ?? '' ),
				(string) $package_info['sha256']
			)
		) {
			return null;
		}

		$package  = json_decode( $package_json, true );
		$manifest = json_decode( $files_json, true );
		$ref      = is_array( $package['payload']['files'] ?? null ) ? $package['payload']['files'] : array();
		if (
			! is_array( $package )
			|| ! is_array( $manifest )
			|| 'files/manifest.json' !== ( $ref['manifest_path'] ?? null )
			|| ! is_string( $ref['manifest_sha256'] ?? null )
			|| ! hash_equals( (string) $ref['manifest_sha256'], (string) $files_info['sha256'] )
		) {
			return null;
		}

		return array(
			'manifest' => $manifest,
			'sha256'   => (string) $files_info['sha256'],
		);
	}

	/**
	 * Validate and normalize root summaries.
	 *
	 * @param array<string,mixed> $manifest Files manifest.
	 * @return list<array{id:string,file_count:int,byte_count:int}>|null
	 */
	private function manifest_roots( array $manifest ): ?array {
		$roots = is_array( $manifest['roots'] ?? null ) ? array_values( $manifest['roots'] ) : array();
		$record = is_array( $manifest['file_records'] ?? null ) ? $manifest['file_records'] : array();
		if (
			1 !== ( $manifest['schema_version'] ?? null )
			|| 'files' !== ( $manifest['payload_class'] ?? null )
			|| true !== ( $manifest['production_source_read_only'] ?? false )
			|| false !== ( $manifest['credentials_in_payload'] ?? true )
			|| 'one-json-record-per-file' !== ( $record['format'] ?? null )
			|| 'files-meta/' !== ( $record['directory'] ?? null )
		) {
			return null;
		}

		$normalized = array();
		$seen       = array();
		$file_sum   = 0;
		$byte_sum   = 0;
		foreach ( $roots as $root ) {
			if ( ! is_array( $root ) || ! is_string( $root['id'] ?? null ) ) {
				return null;
			}
			$id = $root['id'];
			if ( ! in_array( $id, array( 'uploads', 'plugins', 'themes' ), true ) || isset( $seen[ $id ] ) ) {
				return null;
			}
			$files = max( 0, (int) ( $root['file_count'] ?? 0 ) );
			$bytes = max( 0, (int) ( $root['byte_count'] ?? 0 ) );
			$normalized[] = array(
				'id'         => $id,
				'file_count' => $files,
				'byte_count' => $bytes,
			);
			$seen[ $id ] = true;
			$file_sum   += $files;
			$byte_sum   += $bytes;
		}

		if (
			$file_sum !== (int) ( $manifest['file_count'] ?? -1 )
			|| $byte_sum !== (int) ( $manifest['payload_bytes'] ?? -1 )
		) {
			return null;
		}

		return $normalized;
	}

	/**
	 * Load one per-file record.
	 *
	 * @param string $job_id  Clone job identifier.
	 * @param string $root_id Root identifier.
	 * @param string $relative Root-relative path.
	 * @return array{byte_count:int,sha256:string}|null
	 */
	private function file_record( string $job_id, string $root_id, string $relative ): ?array {
		$relative = ltrim( wp_normalize_path( $relative ), '/' );
		if ( '' === $relative || str_contains( $relative, '../' ) ) {
			return null;
		}

		$record_path = 'files-meta/' . $root_id . '/' . hash( 'sha256', $relative ) . '.json';
		$json        = $this->workspace->read_import_extracted_file( $job_id, $record_path );
		$record      = is_string( $json ) ? json_decode( $json, true ) : null;
		$hash        = is_array( $record ) && is_string( $record['sha256'] ?? null ) ? $record['sha256'] : '';
		if (
			! is_array( $record )
			|| $root_id !== ( $record['root'] ?? null )
			|| $relative !== ( $record['relative_path'] ?? null )
			|| 'files/' . $root_id . '/' . $relative !== ( $record['payload_path'] ?? null )
			|| 'copied' !== ( $record['export_status'] ?? null )
			|| 0 > (int) ( $record['byte_count'] ?? -1 )
			|| 1 !== preg_match( '/^[a-f0-9]{64}$/', $hash )
		) {
			return null;
		}

		return array(
			'byte_count' => (int) $record['byte_count'],
			'sha256'     => $hash,
		);
	}

	/**
	 * Return extracted source root.
	 *
	 * @param string $job_id  Clone job identifier.
	 * @param string $root_id Root identifier.
	 */
	private function extracted_root( string $job_id, string $root_id ): ?string {
		$root = $this->workspace->import_extraction_root( $job_id );
		if ( null === $root ) {
			return null;
		}

		$path = wp_normalize_path( trailingslashit( $root ) . 'files/' . $root_id . '/' );

		return is_dir( $path ) && ! is_link( $path ) ? $path : null;
	}

	/**
	 * Return staging destination root.
	 *
	 * @param string $job_id  Clone job identifier.
	 * @param string $root_id Root identifier.
	 */
	private function staging_root( string $job_id, string $root_id ): ?string {
		$root = $this->workspace->import_file_staging_root( $job_id );
		if ( null === $root ) {
			return null;
		}

		$path = wp_normalize_path( trailingslashit( $root ) . $root_id . '/' );

		return is_dir( $path ) && ! is_link( $path ) ? $path : null;
	}

	/**
	 * Recognize deterministic ExportWorkspace guard files that are not site payload records.
	 *
	 * @param string $path Absolute file path.
	 * @param string $name Basename.
	 */
	private function deterministic_guard_file( string $path, string $name ): bool {
		$expected = match ( $name ) {
			'.htaccess' => "Deny from all\n",
			'index.php' => "<?php\n// Silence is golden.\n",
			default     => null,
		};
		if ( null === $expected ) {
			return false;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reads only verified private extraction guard candidate.
		$content = file_get_contents( $path );

		return is_string( $content ) && hash_equals( hash( 'sha256', $expected ), hash( 'sha256', $content ) );
	}

	/**
	 * Move traversal to next manifest root.
	 *
	 * @param array<string,mixed> $state State.
	 * @return array<string,mixed>
	 */
	private function advance_root( array $state ): array {
		$state['root_index']   = (int) ( $state['root_index'] ?? 0 ) + 1;
		$state['pending_dirs'] = array( '' );
		$state['current_dir']  = '';
		$state['after_name']   = '';

		return $state;
	}

	/**
	 * Persist bounded progress.
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

		$stage     = (string) ( $state['stage'] ?? 'copy' );
		$completed = 'copy' === $stage
			? (int) ( $state['file_count'] ?? 0 )
			: (int) ( $state['verify_file_count'] ?? 0 );
		$cursor = $stage . ':' . (string) (int) ( $state['root_index'] ?? 0 )
			. ':' . (string) ( $state['current_dir'] ?? '' )
			. ':' . (string) ( $state['after_name'] ?? '' );

		$this->jobs->transition( $job_id, 'active' );
		$this->jobs->update_progress(
			$job_id,
			'restore-files',
			substr( $cursor, 0, 512 ),
			array(
				'completed' => $completed,
				'total'     => (int) ( $state['expected_file_count'] ?? 0 ),
			)
		);

		return $this->store->get( $job_id );
	}

	/**
	 * Persist terminal blocker and lock restore eligibility.
	 *
	 * @param string              $job_id Clone job identifier.
	 * @param array<string,mixed> $state  State.
	 * @param string              $code   Blocker code.
	 * @param bool                $retryable Retryable marker.
	 * @return array<string,mixed>|null
	 */
	private function block( string $job_id, array $state, string $code, bool $retryable ): ?array {
		$blockers            = is_array( $state['blockers'] ?? null ) ? $state['blockers'] : array();
		$blockers[]          = $code;
		$state['status']     = $retryable ? 'running' : 'blocked';
		$state['blockers']   = array_values( array_unique( $blockers ) );
		$state['updated_at'] = gmdate( DATE_ATOM );
		$this->store->save( $job_id, $state );

		if ( ! $retryable ) {
			$import = $this->import_state->get( $job_id );
			if ( is_array( $import ) ) {
				$import_blockers           = is_array( $import['blockers'] ?? null ) ? $import['blockers'] : array();
				$import_blockers[]         = $code;
				$import['status']          = 'blocked';
				$import['blockers']        = array_values( array_unique( $import_blockers ) );
				$import['restore_allowed'] = false;
				$import['updated_at']      = gmdate( DATE_ATOM );
				$this->import_state->save( $job_id, $import );
			}
		}

		$this->jobs->transition( $job_id, $retryable ? 'failed-retryable' : 'failed-terminal', $code );

		return $this->store->get( $job_id );
	}

	/**
	 * Return whether directory has no entries.
	 *
	 * @param string $path Directory.
	 */
	private function directory_empty( string $path ): bool {
		$entries = scandir( $path );

		return is_array( $entries ) && array() === array_values( array_diff( $entries, array( '.', '..' ) ) );
	}

	/**
	 * Join normalized paths.
	 *
	 * @param string $base Base path.
	 * @param string $relative Relative path.
	 */
	private function join_path( string $base, string $relative ): string {
		return rtrim( wp_normalize_path( $base ), '/' )
			. ( '' === $relative ? '' : '/' . ltrim( wp_normalize_path( $relative ), '/' ) );
	}
}
