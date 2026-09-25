<?php
/**
 * Portable Clone full payload verification + resumable private extraction.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge\Clone;

/**
 * Extracts a staged ZIP privately and replays the exact package checksum contract
 * before opening the restore gate.
 */
final class ImportPayloadVerifier {
	public const MIN_BATCH_FILES     = 1;
	public const MAX_BATCH_FILES     = 250;
	public const DEFAULT_BATCH_FILES = 25;
	public const MIN_BATCH_BYTES     = 1048576;
	public const MAX_BATCH_BYTES     = 67108864;
	public const DEFAULT_BATCH_BYTES = 8388608;

	private const CHECKSUM_SEED           = 'seo-geo-portable-clone-package-v1';
	private const MAX_ARCHIVE_ENTRIES     = 200000;
	private const MAX_PENDING_DIRECTORIES = 50000;
	private const MAX_ENTRY_OPERATIONS    = 8000;

	/**
	 * Payload state store.
	 *
	 * @var ImportPayloadStateStore
	 */
	private ImportPayloadStateStore $store;

	/**
	 * Import preflight state store.
	 *
	 * @var ImportStateStore
	 */
	private ImportStateStore $import_store;

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
	 * Read-only import preflight service.
	 *
	 * @var ImportPreflight
	 */
	private ImportPreflight $preflight;

	/**
	 * Construct verifier.
	 *
	 * @param ImportPayloadStateStore|null $store        Optional payload state store.
	 * @param ImportStateStore|null        $import_store Optional preflight state store.
	 * @param CloneJobStore|null           $jobs         Optional clone job store.
	 * @param ExportWorkspace|null         $workspace    Optional private workspace.
	 * @param ImportPreflight|null         $preflight    Optional preflight service.
	 */
	public function __construct(
		?ImportPayloadStateStore $store = null,
		?ImportStateStore $import_store = null,
		?CloneJobStore $jobs = null,
		?ExportWorkspace $workspace = null,
		?ImportPreflight $preflight = null
	) {
		$this->store        = $store ?? new ImportPayloadStateStore();
		$this->import_store = $import_store ?? new ImportStateStore();
		$this->jobs         = $jobs ?? new CloneJobStore();
		$this->workspace    = $workspace ?? new ExportWorkspace();
		$this->preflight    = $preflight ?? new ImportPreflight( $this->import_store, $this->jobs, $this->workspace );
	}

	/**
	 * Return one payload verification snapshot.
	 *
	 * @param string $job_id Clone job identifier.
	 * @return array<string,mixed>|null
	 */
	public function snapshot( string $job_id ): ?array {
		return $this->store->get( $job_id );
	}

	/**
	 * Start private extraction only after a clean 10E.2A.4.1 preflight.
	 *
	 * @param string $job_id Clone job identifier.
	 * @return array<string,mixed>|null
	 */
	public function start( string $job_id ): ?array {
		$existing = $this->store->get( $job_id );
		if ( is_array( $existing ) ) {
			return $existing;
		}

		$job      = $this->jobs->get( $job_id );
		$preflight = $this->import_store->get( $job_id );
		$archive  = $this->workspace->import_archive_info( $job_id );
		if (
			! is_array( $job )
			|| 'import' !== ( $job['operation'] ?? null )
			|| ! is_array( $preflight )
			|| 'preflight-ready' !== ( $preflight['status'] ?? null )
			|| array() !== ( $preflight['blockers'] ?? array() )
			|| true === ( $preflight['restore_allowed'] ?? false )
			|| true === ( $preflight['full_payload_verified'] ?? false )
			|| ! is_array( $archive )
			|| ! hash_equals( (string) ( $preflight['archive_sha256'] ?? '' ), (string) $archive['sha256'] )
			|| (int) ( $preflight['archive_bytes'] ?? -1 ) !== (int) $archive['bytes']
		) {
			return null;
		}

		$entries = $this->archive_file_entries( (string) $archive['path'] );
		if ( null === $entries || array() === $entries ) {
			return null;
		}

		$payload_root = $this->workspace->import_payload_root( $job_id, true );
		if ( null === $payload_root || ! $this->directory_empty( $payload_root ) ) {
			return null;
		}

		$now   = gmdate( DATE_ATOM );
		$state = array(
			'schema_version'         => ImportPayloadStateStore::SCHEMA_VERSION,
			'job_id'                 => $job_id,
			'status'                 => 'running',
			'stage'                  => 'extract',
			'archive_sha256'         => (string) $archive['sha256'],
			'package_checksum'       => (string) ( $preflight['package_checksum'] ?? '' ),
			'package_manifest_hash'  => (string) ( $preflight['package_manifest_sha256'] ?? '' ),
			'expected_file_count'    => (int) ( $preflight['payload_file_count'] ?? 0 ),
			'expected_bytes'         => (int) ( $preflight['payload_bytes'] ?? 0 ),
			'archive_file_count'     => count( $entries ),
			'extract_after_name'     => '',
			'extracted_file_count'   => 0,
			'extracted_bytes'        => 0,
			'directory_active'       => false,
			'pending_dirs'           => array(),
			'current_dir'            => '',
			'after_name'             => '',
			'verified_file_count'    => 0,
			'verified_bytes'         => 0,
			'verification_checksum'  => hash( 'sha256', self::CHECKSUM_SEED ),
			'blockers'               => array(),
			'started_at'             => $now,
			'updated_at'             => $now,
			'completed_at'           => '',
		);

		if (
			! $this->valid_hash( $state['package_checksum'] )
			|| ! $this->valid_hash( $state['package_manifest_hash'] )
			|| 0 >= $state['expected_file_count']
			|| 0 >= $state['expected_bytes']
			|| ! $this->store->save( $job_id, $state )
		) {
			return null;
		}

		$this->jobs->transition( $job_id, 'active' );
		$this->jobs->update_progress(
			$job_id,
			'validate',
			'extract:0',
			array(
				'completed' => 0,
				'total'     => count( $entries ),
			)
		);

		return $this->store->get( $job_id );
	}

	/**
	 * Advance one bounded private extraction or checksum verification batch.
	 *
	 * @param string $job_id      Clone job identifier.
	 * @param int    $batch_files Maximum files processed this request.
	 * @param int    $batch_bytes Soft maximum uncompressed bytes this request.
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

		if ( ! is_array( $state ) || in_array( $state['status'] ?? null, array( 'verified', 'blocked' ), true ) ) {
			return $state;
		}

		if ( ! $this->archive_identity_matches( $job_id, $state ) ) {
			return $this->block( $job_id, $state, 'import-payload-archive-identity-changed', false );
		}

		return 'extract' === ( $state['stage'] ?? null )
			? $this->advance_extraction( $job_id, $state, $batch_files, $batch_bytes )
			: $this->advance_verification( $job_id, $state, $batch_files, $batch_bytes );
	}

	/**
	 * Advance one bounded extraction batch.
	 *
	 * @param string              $job_id      Clone job identifier.
	 * @param array<string,mixed> $state       Payload state.
	 * @param int                 $batch_files Maximum files.
	 * @param int                 $batch_bytes Soft bytes limit.
	 * @return array<string,mixed>|null
	 */
	private function advance_extraction(
		string $job_id,
		array $state,
		int $batch_files,
		int $batch_bytes
	): ?array {
		$archive = $this->workspace->import_archive_info( $job_id );
		if ( ! is_array( $archive ) ) {
			return $this->block( $job_id, $state, 'import-payload-archive-unavailable', true );
		}

		$entries = $this->archive_file_entries( (string) $archive['path'] );
		if ( null === $entries || count( $entries ) !== (int) ( $state['archive_file_count'] ?? -1 ) ) {
			return $this->block( $job_id, $state, 'import-payload-archive-entry-drift', false );
		}

		$after   = (string) ( $state['extract_after_name'] ?? '' );
		$batch   = array();
		$bytes   = 0;
		$has_more = false;

		foreach ( $entries as $name => $entry ) {
			if ( '' !== $after && strcmp( $name, $after ) <= 0 ) {
				continue;
			}

			if ( count( $batch ) >= $batch_files || ( array() !== $batch && $bytes >= $batch_bytes ) ) {
				$has_more = true;
				break;
			}

			$batch[ $name ] = $entry;
			$bytes         += (int) $entry['size'];
		}

		if ( array() === $batch ) {
			return $this->start_verification_pass( $job_id, $state );
		}

		$extracted = $this->workspace->extract_import_archive_files( $job_id, array_keys( $batch ) );
		if ( null === $extracted ) {
			return $this->block( $job_id, $state, 'import-payload-extraction-failed', true );
		}

		foreach ( $batch as $name => $entry ) {
			$record = $extracted[ $name ] ?? null;
			if ( ! is_array( $record ) || (int) $record['bytes'] !== (int) $entry['size'] ) {
				return $this->block( $job_id, $state, 'import-payload-extracted-size-mismatch', false );
			}

			$state['extract_after_name']    = $name;
			$state['extracted_file_count'] = (int) ( $state['extracted_file_count'] ?? 0 ) + 1;
			$state['extracted_bytes']      = (int) ( $state['extracted_bytes'] ?? 0 ) + (int) $record['bytes'];
		}

		if ( ! $has_more && (int) $state['extracted_file_count'] >= count( $entries ) ) {
			return $this->start_verification_pass( $job_id, $state );
		}

		return $this->persist_progress( $job_id, $state );
	}

	/**
	 * Start the deterministic checksum replay after extraction completes.
	 *
	 * @param string              $job_id Clone job identifier.
	 * @param array<string,mixed> $state  Payload state.
	 * @return array<string,mixed>|null
	 */
	private function start_verification_pass( string $job_id, array $state ): ?array {
		if ( (int) ( $state['archive_file_count'] ?? 0 ) !== (int) ( $state['extracted_file_count'] ?? -1 ) ) {
			return $this->block( $job_id, $state, 'import-payload-extraction-count-mismatch', false );
		}

		$payload_root = $this->workspace->import_payload_root( $job_id, false );
		if ( null === $payload_root ) {
			return $this->block( $job_id, $state, 'import-payload-root-unavailable', true );
		}

		$manifest = $payload_root . 'package/manifest.json';
		$hash     = is_file( $manifest ) && is_readable( $manifest ) ? hash_file( 'sha256', $manifest ) : false;
		if (
			! is_string( $hash )
			|| ! hash_equals( (string) ( $state['package_manifest_hash'] ?? '' ), $hash )
		) {
			return $this->block( $job_id, $state, 'import-package-manifest-extraction-mismatch', false );
		}

		$state['stage']                 = 'verify';
		$state['directory_active']      = false;
		$state['pending_dirs']          = array( '' );
		$state['current_dir']           = '';
		$state['after_name']            = '';
		$state['verified_file_count']   = 0;
		$state['verified_bytes']        = 0;
		$state['verification_checksum'] = hash( 'sha256', self::CHECKSUM_SEED );
		$state['updated_at']            = gmdate( DATE_ATOM );

		if ( ! $this->store->save( $job_id, $state ) ) {
			return null;
		}

		$this->jobs->update_progress(
			$job_id,
			'validate',
			'verify:0',
			array(
				'completed' => 0,
				'total'     => (int) ( $state['expected_file_count'] ?? 0 ),
			)
		);

		return $this->store->get( $job_id );
	}

	/**
	 * Advance one bounded checksum replay batch over the extracted private payload.
	 *
	 * @param string              $job_id      Clone job identifier.
	 * @param array<string,mixed> $state       Payload state.
	 * @param int                 $batch_files Maximum files.
	 * @param int                 $batch_bytes Soft bytes limit.
	 * @return array<string,mixed>|null
	 */
	private function advance_verification(
		string $job_id,
		array $state,
		int $batch_files,
		int $batch_bytes
	): ?array {
		if ( 'verify' !== ( $state['stage'] ?? null ) ) {
			return $state;
		}

		$root = $this->workspace->import_payload_root( $job_id, false );
		if ( null === $root ) {
			return $this->block( $job_id, $state, 'import-payload-root-unavailable', true );
		}

		$processed_files = 0;
		$processed_bytes = 0;
		$operations      = 0;

		while ( $processed_files < $batch_files && $operations < self::MAX_ENTRY_OPERATIONS ) {
			$pending = is_array( $state['pending_dirs'] ?? null ) ? $state['pending_dirs'] : array();
			if ( true !== ( $state['directory_active'] ?? false ) ) {
				if ( array() === $pending ) {
					return $this->complete_verification( $job_id, $state );
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
				return $this->block( $job_id, $state, 'import-payload-directory-read-failed', true );
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
					return $this->block( $job_id, $state, 'import-payload-symlink', false );
				}

				if ( is_dir( $path ) ) {
					$pending = is_array( $state['pending_dirs'] ?? null ) ? $state['pending_dirs'] : array();
					if ( count( $pending ) >= self::MAX_PENDING_DIRECTORIES ) {
						return $this->block( $job_id, $state, 'import-payload-directory-queue-limit', false );
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
					return $this->block( $job_id, $state, 'import-payload-unreadable', true );
				}

				$bytes = filesize( $path );
				$hash  = hash_file( 'sha256', $path );
				if ( false === $bytes || false === $hash ) {
					return $this->block( $job_id, $state, 'import-payload-hash-failed', true );
				}

				$canonical                      = 'payload|' . wp_normalize_path( $relative )
					. '|' . (string) (int) $bytes
					. '|' . $hash;
				$state['verification_checksum'] = $this->chain_hash( (string) $state['verification_checksum'], $canonical );
				$state['verified_file_count']   = (int) ( $state['verified_file_count'] ?? 0 ) + 1;
				$state['verified_bytes']        = (int) ( $state['verified_bytes'] ?? 0 ) + (int) $bytes;
				$state['after_name']            = $entry;
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
	 * Complete checksum replay and open the restore gate only after destination
	 * preflight is still clean.
	 *
	 * @param string              $job_id Clone job identifier.
	 * @param array<string,mixed> $state  Payload state.
	 * @return array<string,mixed>|null
	 */
	private function complete_verification( string $job_id, array $state ): ?array {
		if (
			(int) ( $state['expected_file_count'] ?? 0 ) !== (int) ( $state['verified_file_count'] ?? -1 )
			|| (int) ( $state['expected_bytes'] ?? 0 ) !== (int) ( $state['verified_bytes'] ?? -1 )
			|| ! hash_equals( (string) ( $state['package_checksum'] ?? '' ), (string) ( $state['verification_checksum'] ?? '' ) )
		) {
			return $this->block( $job_id, $state, 'import-payload-integrity-mismatch', false );
		}

		$preflight = $this->preflight->validate( $job_id );
		if (
			! is_array( $preflight )
			|| 'preflight-ready' !== ( $preflight['status'] ?? null )
			|| array() !== ( $preflight['blockers'] ?? array() )
		) {
			return $this->block( $job_id, $state, 'import-destination-preflight-drift', true );
		}

		$preflight['full_payload_verified'] = true;
		$preflight['restore_allowed']       = true;
		$preflight['advisories']            = array_values(
			array_filter(
				is_array( $preflight['advisories'] ?? null ) ? $preflight['advisories'] : array(),
				static fn( mixed $code ): bool => 'full-payload-checksum-pending' !== $code
			)
		);
		$preflight['updated_at'] = gmdate( DATE_ATOM );
		if ( ! $this->import_store->save( $job_id, $preflight ) ) {
			return null;
		}

		$state['status']       = 'verified';
		$state['stage']        = 'complete';
		$state['updated_at']   = gmdate( DATE_ATOM );
		$state['completed_at'] = $state['updated_at'];

		if ( ! $this->store->save( $job_id, $state ) ) {
			return null;
		}

		$this->jobs->transition( $job_id, 'active' );
		$this->jobs->update_progress(
			$job_id,
			'prepare-target',
			'payload-verified',
			array(
				'completed' => (int) ( $state['verified_file_count'] ?? 0 ),
				'total'     => (int) ( $state['expected_file_count'] ?? 0 ),
			)
		);

		return $this->store->get( $job_id );
	}

	/**
	 * Persist resumable progress.
	 *
	 * @param string              $job_id Clone job identifier.
	 * @param array<string,mixed> $state  Payload state.
	 * @return array<string,mixed>|null
	 */
	private function persist_progress( string $job_id, array $state ): ?array {
		$state['updated_at'] = gmdate( DATE_ATOM );
		if ( ! $this->store->save( $job_id, $state ) ) {
			return null;
		}

		$extracting = 'extract' === ( $state['stage'] ?? null );
		$cursor     = $extracting
			? 'extract:' . (string) ( $state['extract_after_name'] ?? '' )
			: 'verify:' . (string) ( $state['current_dir'] ?? '' ) . ':' . (string) ( $state['after_name'] ?? '' );
		$completed  = $extracting
			? (int) ( $state['extracted_file_count'] ?? 0 )
			: (int) ( $state['verified_file_count'] ?? 0 );
		$total      = $extracting
			? (int) ( $state['archive_file_count'] ?? 0 )
			: (int) ( $state['expected_file_count'] ?? 0 );

		$this->jobs->update_progress(
			$job_id,
			'validate',
			substr( $cursor, 0, 160 ),
			array(
				'completed' => $completed,
				'total'     => $total,
			)
		);

		return $this->store->get( $job_id );
	}

	/**
	 * Return sorted file entries from the unchanged staged ZIP.
	 *
	 * @param string $archive_path Absolute private ZIP path.
	 * @return array<string,array{size:int}>|null
	 */
	private function archive_file_entries( string $archive_path ): ?array {
		if ( ! $this->load_pclzip() ) {
			return null;
		}

		// phpcs:ignore PHPCompatibility.Classes.NewClasses.pclzipFound -- WordPress Core PclZip loaded explicitly.
		$archive = new \PclZip( $archive_path );
		$list    = $archive->listContent();
		if ( ! is_array( $list ) || array() === $list || count( $list ) > self::MAX_ARCHIVE_ENTRIES ) {
			return null;
		}

		$entries = array();
		foreach ( $list as $entry ) {
			if ( ! is_array( $entry ) || ! is_string( $entry['filename'] ?? null ) ) {
				return null;
			}

			$name = $entry['filename'];
			if ( true === ( $entry['folder'] ?? false ) || str_ends_with( $name, '/' ) ) {
				continue;
			}
			if ( ! $this->safe_archive_file_path( $name ) ) {
				return null;
			}

			$entries[ $name ] = array(
				'size' => max( 0, (int) ( $entry['size'] ?? 0 ) ),
			);
		}

		ksort( $entries, SORT_STRING );

		return $entries;
	}

	/**
	 * Validate file path against the already accepted package roots.
	 *
	 * @param string $name Archive file path.
	 */
	private function safe_archive_file_path( string $name ): bool {
		if (
			'' === $name
			|| str_contains( $name, "\0" )
			|| str_contains( $name, '\\' )
			|| str_starts_with( $name, '/' )
			|| str_contains( $name, '../' )
			|| str_contains( $name, '/..' )
		) {
			return false;
		}
		if ( in_array( $name, array( '.htaccess', 'index.php' ), true ) ) {
			return true;
		}

		foreach ( array( 'database/', 'database-meta/', 'files/', 'files-meta/', 'package/' ) as $prefix ) {
			if ( str_starts_with( $name, $prefix ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Confirm staged ZIP identity is unchanged since preflight.
	 *
	 * @param string              $job_id Clone job identifier.
	 * @param array<string,mixed> $state  Payload state.
	 */
	private function archive_identity_matches( string $job_id, array $state ): bool {
		$archive = $this->workspace->import_archive_info( $job_id );
		return is_array( $archive )
			&& hash_equals( (string) ( $state['archive_sha256'] ?? '' ), (string) $archive['sha256'] );
	}

	/**
	 * Return whether a private directory is empty.
	 *
	 * @param string $directory Absolute private directory.
	 */
	private function directory_empty( string $directory ): bool {
		$iterator = new \FilesystemIterator( $directory, \FilesystemIterator::SKIP_DOTS );
		return ! $iterator->valid();
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
	 * Persist one blocker and keep restore disabled.
	 *
	 * @param string              $job_id    Clone job identifier.
	 * @param array<string,mixed> $state     Payload state.
	 * @param string              $code      Blocker code.
	 * @param bool                $retryable Retry allowed.
	 * @return array<string,mixed>|null
	 */
	private function block( string $job_id, array $state, string $code, bool $retryable ): ?array {
		$blockers            = is_array( $state['blockers'] ?? null ) ? $state['blockers'] : array();
		$blockers[]          = $code;
		$state['status']     = 'blocked';
		$state['blockers']   = array_values( array_unique( $blockers ) );
		$state['updated_at'] = gmdate( DATE_ATOM );

		$preflight = $this->import_store->get( $job_id );
		if ( is_array( $preflight ) ) {
			$preflight['full_payload_verified'] = false;
			$preflight['restore_allowed']       = false;
			$preflight['updated_at']            = gmdate( DATE_ATOM );
			$this->import_store->save( $job_id, $preflight );
		}

		$this->store->save( $job_id, $state );
		$this->jobs->transition( $job_id, $retryable ? 'failed-retryable' : 'failed-terminal', $code );

		return $this->store->get( $job_id );
	}

	/**
	 * Validate SHA-256 value.
	 *
	 * @param mixed $hash Raw hash.
	 */
	private function valid_hash( mixed $hash ): bool {
		return is_string( $hash ) && 1 === preg_match( '/^[a-f0-9]{64}$/', $hash );
	}

	/**
	 * Chain one canonical payload record.
	 *
	 * @param string $previous Previous chain hash.
	 * @param string $record   Canonical record.
	 */
	private function chain_hash( string $previous, string $record ): string {
		return hash( 'sha256', $previous . "\n" . $record );
	}

	/**
	 * Join normalized paths.
	 *
	 * @param string $base     Absolute base.
	 * @param string $relative Relative path.
	 */
	private function join_path( string $base, string $relative ): string {
		return rtrim( wp_normalize_path( $base ), '/' )
			. ( '' === $relative ? '' : '/' . ltrim( wp_normalize_path( $relative ), '/' ) );
	}
}
