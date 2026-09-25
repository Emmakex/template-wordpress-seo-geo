<?php
/**
 * Portable Clone resumable package manifest and integrity builder.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge\Clone;

/**
 * Builds and verifies one deterministic private package checksum in bounded passes.
 */
final class PackageBuilder {
	public const MIN_BATCH_FILES     = 1;
	public const MAX_BATCH_FILES     = 500;
	public const DEFAULT_BATCH_FILES = 100;
	public const MIN_BATCH_BYTES     = 1048576;
	public const MAX_BATCH_BYTES     = 134217728;
	public const DEFAULT_BATCH_BYTES = 16777216;

	private const CHECKSUM_SEED          = 'seo-geo-portable-clone-package-v1';
	private const MAX_PENDING_DIRECTORIES = 50000;
	private const MAX_ENTRY_OPERATIONS    = 8000;

	/**
	 * Package state store.
	 *
	 * @var PackageStateStore
	 */
	private PackageStateStore $store;

	/**
	 * Source inventory store.
	 *
	 * @var CloneInventoryStore
	 */
	private CloneInventoryStore $inventory;

	/**
	 * Database-export state store.
	 *
	 * @var ExportStateStore
	 */
	private ExportStateStore $database_export;

	/**
	 * File-export state store.
	 *
	 * @var FileExportStateStore
	 */
	private FileExportStateStore $file_export;

	/**
	 * Clone job store.
	 *
	 * @var CloneJobStore
	 */
	private CloneJobStore $jobs;

	/**
	 * Private export workspace.
	 *
	 * @var ExportWorkspace
	 */
	private ExportWorkspace $workspace;

	/**
	 * Construct the package builder.
	 *
	 * @param PackageStateStore|null    $store           Optional package state store.
	 * @param CloneInventoryStore|null  $inventory       Optional source inventory store.
	 * @param ExportStateStore|null     $database_export Optional database-export state store.
	 * @param FileExportStateStore|null $file_export     Optional file-export state store.
	 * @param CloneJobStore|null        $jobs            Optional clone job store.
	 * @param ExportWorkspace|null      $workspace       Optional private export workspace.
	 */
	public function __construct(
		?PackageStateStore $store = null,
		?CloneInventoryStore $inventory = null,
		?ExportStateStore $database_export = null,
		?FileExportStateStore $file_export = null,
		?CloneJobStore $jobs = null,
		?ExportWorkspace $workspace = null
	) {
		$this->store           = $store ?? new PackageStateStore();
		$this->inventory       = $inventory ?? new CloneInventoryStore();
		$this->database_export = $database_export ?? new ExportStateStore();
		$this->file_export     = $file_export ?? new FileExportStateStore();
		$this->jobs            = $jobs ?? new CloneJobStore();
		$this->workspace       = $workspace ?? new ExportWorkspace();
	}

	/**
	 * Return one package build snapshot.
	 *
	 * @param string $job_id Clone job identifier.
	 * @return array<string,mixed>|null
	 */
	public function snapshot( string $job_id ): ?array {
		return $this->store->get( $job_id );
	}

	/**
	 * Start package integrity only after both payload exporters complete.
	 *
	 * @param string $job_id Clone job identifier.
	 * @return array<string,mixed>|null
	 */
	public function start( string $job_id ): ?array {
		$job       = $this->jobs->get( $job_id );
		$inventory = $this->inventory->get( $job_id );
		$database  = $this->database_export->get( $job_id );
		$files     = $this->file_export->get( $job_id );

		if (
			! is_array( $job )
			|| ! in_array( $job['operation'] ?? null, array( 'local-clone', 'export' ), true )
			|| ! is_array( $inventory )
			|| 'complete' !== ( $inventory['status'] ?? null )
			|| ! is_array( $database )
			|| 'complete' !== ( $database['status'] ?? null )
			|| ! is_array( $files )
			|| 'complete' !== ( $files['status'] ?? null )
		) {
			return null;
		}

		$existing = $this->store->get( $job_id );
		if ( is_array( $existing ) ) {
			return $existing;
		}

		$source_fingerprint = (string) ( $inventory['fingerprint'] ?? '' );
		if (
			1 !== preg_match( '/^[a-f0-9]{64}$/', $source_fingerprint )
			|| ! hash_equals( $source_fingerprint, (string) ( $files['export_fingerprint'] ?? '' ) )
			|| ! $this->child_manifest_matches( $job_id, 'database/manifest.json', (string) ( $database['database_manifest_hash'] ?? '' ) )
			|| ! $this->child_manifest_matches( $job_id, 'files/manifest.json', (string) ( $files['files_manifest_hash'] ?? '' ) )
			|| null === $this->workspace_root( $job_id )
		) {
			return null;
		}

		$seed = hash( 'sha256', self::CHECKSUM_SEED );
		$now  = gmdate( DATE_ATOM );
		$state = array(
			'schema_version'         => PackageStateStore::SCHEMA_VERSION,
			'job_id'                 => $job_id,
			'status'                 => 'running',
			'stage'                  => 'build',
			'directory_active'       => false,
			'pending_dirs'           => array( '' ),
			'current_dir'            => '',
			'after_name'             => '',
			'payload_file_count'     => 0,
			'payload_byte_count'     => 0,
			'verify_file_count'      => 0,
			'verify_byte_count'      => 0,
			'package_checksum'       => $seed,
			'verification_checksum'  => $seed,
			'source_fingerprint'     => $source_fingerprint,
			'database_manifest_hash' => (string) $database['database_manifest_hash'],
			'files_manifest_hash'    => (string) $files['files_manifest_hash'],
			'package_manifest_hash'  => '',
			'blockers'               => array(),
			'started_at'             => $now,
			'updated_at'             => $now,
			'completed_at'           => '',
		);

		if ( ! $this->store->save( $job_id, $state ) ) {
			return null;
		}

		$this->jobs->update_progress(
			$job_id,
			'manifest',
			'build:0',
			array(
				'completed' => 0,
				'total'     => null,
			)
		);

		return $this->store->get( $job_id );
	}

	/**
	 * Advance one bounded package checksum or verification batch.
	 *
	 * @param string $job_id      Clone job identifier.
	 * @param int    $batch_files Maximum files hashed this request.
	 * @param int    $batch_bytes Soft maximum bytes hashed this request.
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

		$root = $this->workspace_root( $job_id );
		if ( null === $root ) {
			return $this->block( $job_id, $state, 'package-workspace-unavailable', false );
		}

		$processed_files = 0;
		$processed_bytes = 0;
		$operations      = 0;

		while ( $processed_files < $batch_files && $operations < self::MAX_ENTRY_OPERATIONS ) {
			$pending = is_array( $state['pending_dirs'] ?? null ) ? $state['pending_dirs'] : array();
			if ( true !== ( $state['directory_active'] ?? false ) ) {
				if ( array() === $pending ) {
					return 'build' === ( $state['stage'] ?? null )
						? $this->start_verification_pass( $job_id, $state )
						: $this->complete_package( $job_id, $state );
				}

				$state['current_dir']      = (string) array_shift( $pending );
				$state['pending_dirs']     = $pending;
				$state['after_name']       = '';
				$state['directory_active'] = true;
			}

			$current_dir = (string) ( $state['current_dir'] ?? '' );
			$absolute    = $this->join_path( $root, $current_dir );
			$entries     = scandir( $absolute, SCANDIR_SORT_ASCENDING );
			if ( false === $entries ) {
				return $this->block( $job_id, $state, 'package-directory-read-failed', true );
			}

			$after_name   = (string) ( $state['after_name'] ?? '' );
			$dir_finished = true;

			foreach ( $entries as $entry ) {
				if ( '.' === $entry || '..' === $entry || ( '' !== $after_name && strcmp( $entry, $after_name ) <= 0 ) ) {
					continue;
				}

				++$operations;
				$relative = '' === $current_dir ? $entry : $current_dir . '/' . $entry;
				$path     = $this->join_path( $root, $relative );

				if ( 'package' === $relative || str_starts_with( $relative, 'package/' ) ) {
					$state['after_name'] = $entry;
					continue;
				}

				if ( is_link( $path ) ) {
					return $this->block( $job_id, $state, 'package-workspace-symlink', false );
				}

				if ( is_dir( $path ) ) {
					$pending = is_array( $state['pending_dirs'] ?? null ) ? $state['pending_dirs'] : array();
					if ( count( $pending ) >= self::MAX_PENDING_DIRECTORIES ) {
						return $this->block( $job_id, $state, 'package-directory-queue-limit', false );
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
					return $this->block( $job_id, $state, 'package-payload-unreadable', true );
				}

				$bytes = filesize( $path );
				$hash  = hash_file( 'sha256', $path );
				if ( false === $bytes || false === $hash ) {
					return $this->block( $job_id, $state, 'package-payload-hash-failed', true );
				}

				if ( ! $this->payload_record_valid( $job_id, $relative, (int) $bytes, $hash ) ) {
					return $this->block( $job_id, $state, 'package-exported-payload-mismatch', false );
				}

				$record = 'payload|' . wp_normalize_path( $relative ) . '|' . (string) (int) $bytes . '|' . $hash;
				if ( 'build' === ( $state['stage'] ?? null ) ) {
					$state['package_checksum']   = $this->chain_hash( (string) $state['package_checksum'], $record );
					$state['payload_file_count'] = (int) ( $state['payload_file_count'] ?? 0 ) + 1;
					$state['payload_byte_count'] = (int) ( $state['payload_byte_count'] ?? 0 ) + (int) $bytes;
				} else {
					$state['verification_checksum'] = $this->chain_hash( (string) $state['verification_checksum'], $record );
					$state['verify_file_count']     = (int) ( $state['verify_file_count'] ?? 0 ) + 1;
					$state['verify_byte_count']     = (int) ( $state['verify_byte_count'] ?? 0 ) + (int) $bytes;
				}

				$state['after_name'] = $entry;
				++$processed_files;
				$processed_bytes += (int) $bytes;

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
				$state['directory_active'] = false;
				$state['current_dir']      = '';
				$state['after_name']       = '';
			}

			if ( 0 < $processed_files && $processed_bytes >= $batch_bytes ) {
				break;
			}
		}

		return $this->persist_progress( $job_id, $state );
	}

	/**
	 * Begin a second bounded pass that proves checksum reproducibility.
	 *
	 * @param string              $job_id Clone job identifier.
	 * @param array<string,mixed> $state  Package state.
	 * @return array<string,mixed>|null
	 */
	private function start_verification_pass( string $job_id, array $state ): ?array {
		$state['stage']                 = 'verify';
		$state['directory_active']      = false;
		$state['pending_dirs']          = array( '' );
		$state['current_dir']           = '';
		$state['after_name']            = '';
		$state['verify_file_count']     = 0;
		$state['verify_byte_count']     = 0;
		$state['verification_checksum'] = hash( 'sha256', self::CHECKSUM_SEED );
		$state['updated_at']            = gmdate( DATE_ATOM );

		if ( ! $this->store->save( $job_id, $state ) ) {
			return null;
		}

		$this->jobs->update_progress(
			$job_id,
			'integrity',
			'verify:0',
			array(
				'completed' => 0,
				'total'     => (int) ( $state['payload_file_count'] ?? 0 ),
			)
		);

		return $this->store->get( $job_id );
	}

	/**
	 * Complete integrity verification and write the package manifest.
	 *
	 * @param string              $job_id Clone job identifier.
	 * @param array<string,mixed> $state  Package state.
	 * @return array<string,mixed>|null
	 */
	private function complete_package( string $job_id, array $state ): ?array {
		if (
			(int) ( $state['payload_file_count'] ?? 0 ) !== (int) ( $state['verify_file_count'] ?? -1 )
			|| (int) ( $state['payload_byte_count'] ?? 0 ) !== (int) ( $state['verify_byte_count'] ?? -1 )
			|| ! hash_equals( (string) ( $state['package_checksum'] ?? '' ), (string) ( $state['verification_checksum'] ?? '' ) )
			|| ! $this->child_manifest_matches( $job_id, 'database/manifest.json', (string) ( $state['database_manifest_hash'] ?? '' ) )
			|| ! $this->child_manifest_matches( $job_id, 'files/manifest.json', (string) ( $state['files_manifest_hash'] ?? '' ) )
		) {
			return $this->block( $job_id, $state, 'package-integrity-verification-failed', false );
		}

		$job      = $this->jobs->get( $job_id );
		$database = $this->database_export->get( $job_id );
		$files    = $this->file_export->get( $job_id );
		if ( ! is_array( $job ) || ! is_array( $database ) || ! is_array( $files ) ) {
			return $this->block( $job_id, $state, 'package-state-dependency-missing', false );
		}

		$manifest = array(
			'schema_version' => 1,
			'mode'           => 'portable-clone-package',
			'package_id'     => $job_id,
			'operation'      => (string) ( $job['operation'] ?? '' ),
			'source'         => array(
				'home_url'           => home_url( '/' ),
				'site_url'           => site_url( '/' ),
				'wordpress_version'  => get_bloginfo( 'version' ),
				'php_version'        => PHP_VERSION,
				'source_fingerprint' => (string) $state['source_fingerprint'],
			),
			'payload'        => array(
				'database' => array(
					'manifest_path'   => 'database/manifest.json',
					'manifest_sha256' => (string) $state['database_manifest_hash'],
					'row_count'       => (int) ( $database['row_count'] ?? 0 ),
					'chunk_count'     => (int) ( $database['chunk_count'] ?? 0 ),
					'payload_bytes'   => (int) ( $database['byte_count'] ?? 0 ),
				),
				'files'    => array(
					'manifest_path'   => 'files/manifest.json',
					'manifest_sha256' => (string) $state['files_manifest_hash'],
					'file_count'      => (int) ( $files['file_count'] ?? 0 ),
					'payload_bytes'   => (int) ( $files['byte_count'] ?? 0 ),
				),
			),
			'integrity'      => array(
				'algorithm'          => 'sha256',
				'checksum_contract'  => 'lexicographic-bfs-path+bytes+sha256-v1',
				'checksum_scope'     => 'workspace-excluding-package-metadata',
				'payload_file_count' => (int) $state['payload_file_count'],
				'payload_bytes'      => (int) $state['payload_byte_count'],
				'package_checksum'   => (string) $state['package_checksum'],
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

		$json = wp_json_encode( $manifest, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT );
		if ( ! is_string( $json ) ) {
			return $this->block( $job_id, $state, 'package-manifest-encode-failed', true );
		}

		$written = $this->workspace->write( $job_id, 'package/manifest.json', $json . "\n" );
		if ( null === $written ) {
			return $this->block( $job_id, $state, 'package-manifest-write-failed', true );
		}

		$state['status']                = 'complete';
		$state['stage']                 = 'complete';
		$state['package_manifest_hash'] = (string) $written['sha256'];
		$state['updated_at']            = gmdate( DATE_ATOM );
		$state['completed_at']          = $state['updated_at'];

		if ( ! $this->store->save( $job_id, $state ) ) {
			return null;
		}

		$this->jobs->update_progress(
			$job_id,
			'integrity',
			'complete',
			array(
				'completed' => (int) $state['payload_file_count'],
				'total'     => (int) $state['payload_file_count'],
			)
		);

		return $this->store->get( $job_id );
	}

	/**
	 * Persist bounded package progress.
	 *
	 * @param string              $job_id Clone job identifier.
	 * @param array<string,mixed> $state  Package state.
	 * @return array<string,mixed>|null
	 */
	private function persist_progress( string $job_id, array $state ): ?array {
		$state['updated_at'] = gmdate( DATE_ATOM );
		if ( ! $this->store->save( $job_id, $state ) ) {
			return null;
		}

		$stage     = (string) ( $state['stage'] ?? 'build' );
		$completed = 'build' === $stage
			? (int) ( $state['payload_file_count'] ?? 0 )
			: (int) ( $state['verify_file_count'] ?? 0 );
		$total     = 'verify' === $stage ? (int) ( $state['payload_file_count'] ?? 0 ) : null;
		$cursor    = $stage . ':' . (string) ( $state['current_dir'] ?? '' ) . ':' . (string) ( $state['after_name'] ?? '' );

		$this->jobs->update_progress(
			$job_id,
			'build' === $stage ? 'manifest' : 'integrity',
			substr( $cursor, 0, 160 ),
			array(
				'completed' => $completed,
				'total'     => $total,
			)
		);

		return $this->store->get( $job_id );
	}

	/**
	 * Validate one exported payload file against its child-manifest evidence.
	 *
	 * @param string $job_id   Clone job identifier.
	 * @param string $relative Workspace-relative path.
	 * @param int    $bytes    Current file bytes.
	 * @param string $hash     Current SHA-256.
	 */
	private function payload_record_valid( string $job_id, string $relative, int $bytes, string $hash ): bool {
		if ( 'database/manifest.json' === $relative ) {
			$state = $this->database_export->get( $job_id );

			return is_array( $state ) && hash_equals( (string) ( $state['database_manifest_hash'] ?? '' ), $hash );
		}

		if ( 'files/manifest.json' === $relative ) {
			$state = $this->file_export->get( $job_id );

			return is_array( $state ) && hash_equals( (string) ( $state['files_manifest_hash'] ?? '' ), $hash );
		}

		if ( str_starts_with( $relative, 'files/' ) ) {
			$parts = explode( '/', $relative, 3 );
			if ( 3 !== count( $parts ) ) {
				return $this->workspace_guard_valid( $relative, $bytes, $hash );
			}

			$root          = $parts[1];
			$relative_file = $parts[2];
			$record_path   = 'files-meta/' . $root . '/' . hash( 'sha256', wp_normalize_path( $relative_file ) ) . '.json';
			$record_json   = $this->workspace->read( $job_id, $record_path );
			if ( null === $record_json ) {
				return $this->workspace_guard_valid( $relative, $bytes, $hash );
			}

			$record = json_decode( $record_json, true );
			return is_array( $record )
				&& $relative === (string) ( $record['payload_path'] ?? '' )
				&& $bytes === (int) ( $record['byte_count'] ?? -1 )
				&& hash_equals( (string) ( $record['sha256'] ?? '' ), $hash );
		}

		if ( str_starts_with( $relative, 'database/' ) && ( str_ends_with( $relative, '/schema.sql' ) || str_contains( $relative, '/chunks/' ) ) ) {
			$expected = $this->database_expected_payloads( $job_id );

			return isset( $expected[ $relative ] )
				&& $bytes === $expected[ $relative ]['bytes']
				&& hash_equals( $expected[ $relative ]['sha256'], $hash );
		}

		return true;
	}

	/**
	 * Build expected database schema/chunk hashes from the signed child manifest.
	 *
	 * @param string $job_id Clone job identifier.
	 * @return array<string,array{bytes:int,sha256:string}>
	 */
	private function database_expected_payloads( string $job_id ): array {
		$json = $this->workspace->read( $job_id, 'database/manifest.json' );
		if ( null === $json ) {
			return array();
		}

		$manifest = json_decode( $json, true );
		$tables   = is_array( $manifest['tables'] ?? null ) ? $manifest['tables'] : array();
		$expected = array();

		foreach ( $tables as $table ) {
			if ( ! is_array( $table ) ) {
				continue;
			}

			$schema = is_array( $table['schema'] ?? null ) ? $table['schema'] : array();
			$this->add_expected_record(
				$expected,
				$schema['path'] ?? null,
				$schema['bytes'] ?? null,
				$schema['sha256'] ?? null
			);

			$chunks = is_array( $table['chunks'] ?? null ) ? $table['chunks'] : array();
			foreach ( $chunks as $chunk ) {
				if ( ! is_array( $chunk ) ) {
					continue;
				}

				$this->add_expected_record(
					$expected,
					$chunk['path'] ?? null,
					$chunk['byte_count'] ?? null,
					$chunk['sha256'] ?? null
				);
			}
		}

		return $expected;
	}

	/**
	 * Add one validated expected database payload record.
	 *
	 * @param array<string,array{bytes:int,sha256:string}> $expected Expected records.
	 * @param mixed                                         $path     Raw relative path.
	 * @param mixed                                         $bytes    Raw bytes.
	 * @param mixed                                         $hash     Raw SHA-256.
	 */
	private function add_expected_record( array &$expected, mixed $path, mixed $bytes, mixed $hash ): void {
		if (
			! is_string( $path )
			|| ! is_numeric( $bytes )
			|| ! is_string( $hash )
			|| 1 !== preg_match( '/^[a-f0-9]{64}$/', $hash )
		) {
			return;
		}

		$expected[ wp_normalize_path( $path ) ] = array(
			'bytes'  => max( 0, (int) $bytes ),
			'sha256' => $hash,
		);
	}

	/**
	 * Allow deterministic defense-in-depth files created by ExportWorkspace.
	 *
	 * @param string $relative Workspace-relative path.
	 * @param int    $bytes    Current bytes.
	 * @param string $hash     Current SHA-256.
	 */
	private function workspace_guard_valid( string $relative, int $bytes, string $hash ): bool {
		$basename = basename( $relative );
		$content  = match ( $basename ) {
			'.htaccess' => "Deny from all\n",
			'index.php' => "<?php\n// Silence is golden.\n",
			default     => null,
		};

		return is_string( $content )
			&& strlen( $content ) === $bytes
			&& hash_equals( hash( 'sha256', $content ), $hash );
	}

	/**
	 * Confirm one child manifest still matches its completed exporter state.
	 *
	 * @param string $job_id   Clone job identifier.
	 * @param string $relative Child manifest path.
	 * @param string $expected Expected SHA-256.
	 */
	private function child_manifest_matches( string $job_id, string $relative, string $expected ): bool {
		if ( 1 !== preg_match( '/^[a-f0-9]{64}$/', $expected ) ) {
			return false;
		}

		$root = $this->workspace_root( $job_id );
		if ( null === $root ) {
			return false;
		}

		$path = $this->join_path( $root, $relative );
		$hash = is_file( $path ) && is_readable( $path ) ? hash_file( 'sha256', $path ) : false;

		return is_string( $hash ) && hash_equals( $expected, $hash );
	}

	/**
	 * Return an existing bounded job workspace path.
	 *
	 * @param string $job_id Clone job identifier.
	 */
	private function workspace_root( string $job_id ): ?string {
		if ( 1 !== preg_match( '/^[A-Za-z0-9][A-Za-z0-9._:-]{7,127}$/', $job_id ) ) {
			return null;
		}

		$base = trailingslashit( wp_normalize_path( $this->workspace->base_path() ) );
		$root = $base . $job_id . '/';

		return is_dir( $root ) && str_starts_with( $root, $base ) ? $root : null;
	}

	/**
	 * Persist one package blocker.
	 *
	 * @param string              $job_id    Clone job identifier.
	 * @param array<string,mixed> $state     Package state.
	 * @param string              $code      Blocker code.
	 * @param bool                $retryable Retry allowed.
	 * @return array<string,mixed>|null
	 */
	private function block( string $job_id, array $state, string $code, bool $retryable ): ?array {
		$blockers            = is_array( $state['blockers'] ?? null ) ? $state['blockers'] : array();
		$blockers[]          = $code;
		$state['blockers']   = array_values( array_unique( $blockers ) );
		$state['status']     = 'blocked';
		$state['updated_at'] = gmdate( DATE_ATOM );

		$this->store->save( $job_id, $state );
		$this->jobs->transition( $job_id, $retryable ? 'failed-retryable' : 'failed-terminal', $code );

		return $this->store->get( $job_id );
	}

	/**
	 * Chain one deterministic package record.
	 *
	 * @param string $previous Previous chain hash.
	 * @param string $record   Canonical payload record.
	 */
	private function chain_hash( string $previous, string $record ): string {
		return hash( 'sha256', $previous . "\n" . $record );
	}

	/**
	 * Join normalized filesystem paths.
	 *
	 * @param string $base     Absolute base path.
	 * @param string $relative Relative path.
	 */
	private function join_path( string $base, string $relative ): string {
		return rtrim( wp_normalize_path( $base ), '/' )
			. ( '' === $relative ? '' : '/' . ltrim( wp_normalize_path( $relative ), '/' ) );
	}
}
