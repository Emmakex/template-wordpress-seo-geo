<?php
/**
 * Portable Clone resumable import extraction and full-payload verification.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge\Clone;

/**
 * Extracts one staged package privately and replays the accepted checksum contract.
 */
final class ImportPayloadVerifier {
	public const MIN_BATCH_FILES     = 1;
	public const MAX_BATCH_FILES     = 500;
	public const DEFAULT_BATCH_FILES = 50;
	public const MIN_BATCH_BYTES     = 1048576;
	public const MAX_BATCH_BYTES     = 134217728;
	public const DEFAULT_BATCH_BYTES = 8388608;

	private const CHECKSUM_SEED           = 'seo-geo-portable-clone-package-v1';
	private const MAX_PENDING_DIRECTORIES = 50000;
	private const MAX_ENTRY_OPERATIONS    = 8000;

	/**
	 * Payload-verification state.
	 *
	 * @var ImportPayloadStateStore
	 */
	private ImportPayloadStateStore $store;

	/**
	 * Import intake/preflight state.
	 *
	 * @var ImportStateStore
	 */
	private ImportStateStore $import_state;

	/**
	 * Clone jobs.
	 *
	 * @var CloneJobStore
	 */
	private CloneJobStore $jobs;

	/**
	 * Fresh destination/package preflight.
	 *
	 * @var ImportPreflight
	 */
	private ImportPreflight $preflight;

	/**
	 * Private workspace authority.
	 *
	 * @var ExportWorkspace
	 */
	private ExportWorkspace $workspace;

	/**
	 * Construct verifier.
	 *
	 * @param ImportPayloadStateStore|null $store        Optional payload-state store.
	 * @param ImportStateStore|null        $import_state Optional import state store.
	 * @param CloneJobStore|null           $jobs         Optional clone-job store.
	 * @param ImportPreflight|null          $preflight    Optional fresh preflight service.
	 * @param ExportWorkspace|null         $workspace    Optional private workspace.
	 */
	public function __construct(
		?ImportPayloadStateStore $store = null,
		?ImportStateStore $import_state = null,
		?CloneJobStore $jobs = null,
		?ImportPreflight $preflight = null,
		?ExportWorkspace $workspace = null
	) {
		$this->store        = $store ?? new ImportPayloadStateStore();
		$this->import_state = $import_state ?? new ImportStateStore();
		$this->jobs         = $jobs ?? new CloneJobStore();
		$this->workspace    = $workspace ?? new ExportWorkspace();
		$this->preflight    = $preflight ?? new ImportPreflight( $this->import_state, $this->jobs, $this->workspace );
	}

	/**
	 * Return one payload-verification snapshot.
	 *
	 * @param string $job_id Clone job identifier.
	 * @return array<string,mixed>|null
	 */
	public function snapshot( string $job_id ): ?array {
		return $this->store->get( $job_id );
	}

	/**
	 * Initialize extraction only after a clean successful import preflight.
	 *
	 * @param string $job_id Clone job identifier.
	 * @return array<string,mixed>|null
	 */
	public function start( string $job_id ): ?array {
		$existing = $this->store->get( $job_id );
		if ( is_array( $existing ) ) {
			return $existing;
		}

		$job     = $this->jobs->get( $job_id );
		$import  = $this->import_state->get( $job_id );
		$archive = $this->workspace->import_archive_info( $job_id );
		$entries = $this->workspace->import_archive_entries( $job_id );

		if (
			! is_array( $job )
			|| 'import' !== ( $job['operation'] ?? null )
			|| ! is_array( $import )
			|| 'preflight-ready' !== ( $import['status'] ?? null )
			|| array() !== ( $import['blockers'] ?? array() )
			|| true !== ( $import['manifest_contract_valid'] ?? false )
			|| true !== ( $import['child_manifest_hashes_valid'] ?? false )
			|| true === ( $import['full_payload_verified'] ?? false )
			|| ! is_array( $archive )
			|| array() === $entries
			|| (int) ( $import['archive_bytes'] ?? -1 ) !== (int) $archive['bytes']
			|| ! $this->same_hash( $import['archive_sha256'] ?? '', $archive['sha256'] )
			|| 1 !== preg_match( '/^[a-f0-9]{64}$/', (string) ( $import['package_checksum'] ?? '' ) )
			|| 1 !== preg_match( '/^[a-f0-9]{64}$/', (string) ( $import['package_manifest_sha256'] ?? '' ) )
			|| (int) ( $import['archive_entry_count'] ?? -1 ) !== count( $entries )
		) {
			return null;
		}

		$root = $this->workspace->prepare_import_extraction( $job_id );
		if ( null === $root ) {
			return null;
		}

		$now   = gmdate( DATE_ATOM );
		$state = array(
			'schema_version'          => ImportPayloadStateStore::SCHEMA_VERSION,
			'job_id'                  => $job_id,
			'status'                  => 'running',
			'stage'                   => 'extract',
			'archive_sha256'          => (string) $archive['sha256'],
			'package_manifest_sha256' => (string) $import['package_manifest_sha256'],
			'expected_checksum'       => (string) $import['package_checksum'],
			'expected_file_count'     => (int) ( $import['payload_file_count'] ?? 0 ),
			'expected_byte_count'     => (int) ( $import['payload_bytes'] ?? 0 ),
			'archive_cursor'          => 0,
			'archive_entry_count'     => count( $entries ),
			'extract_file_count'      => 0,
			'extract_byte_count'      => 0,
			'directory_active'        => false,
			'pending_dirs'            => array(),
			'current_dir'             => '',
			'after_name'              => '',
			'verify_file_count'       => 0,
			'verify_byte_count'       => 0,
			'verification_checksum'   => hash( 'sha256', self::CHECKSUM_SEED ),
			'blockers'                => array(),
			'started_at'              => $now,
			'updated_at'              => $now,
			'completed_at'            => '',
		);

		if ( ! $this->store->save( $job_id, $state ) ) {
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
	 * Advance one bounded extraction or checksum-verification batch.
	 *
	 * @param string $job_id      Clone job identifier.
	 * @param int    $batch_files Maximum files processed this request.
	 * @param int    $batch_bytes Soft maximum payload bytes processed this request.
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

		if ( ! $this->archive_identity_matches( $job_id, $state ) ) {
			return $this->block( $job_id, $state, 'import-payload-archive-identity-changed', false );
		}

		return 'extract' === ( $state['stage'] ?? null )
			? $this->advance_extraction( $job_id, $state, $batch_files, $batch_bytes )
			: $this->advance_verification( $job_id, $state, $batch_files, $batch_bytes );
	}

	/**
	 * Advance bounded private ZIP extraction.
	 *
	 * @param string              $job_id      Clone job identifier.
	 * @param array<string,mixed> $state       Payload state.
	 * @param int                 $batch_files Maximum files.
	 * @param int                 $batch_bytes Soft byte limit.
	 * @return array<string,mixed>|null
	 */
	private function advance_extraction( string $job_id, array $state, int $batch_files, int $batch_bytes ): ?array {
		$entries = $this->workspace->import_archive_entries( $job_id );
		if ( count( $entries ) !== (int) ( $state['archive_entry_count'] ?? -1 ) ) {
			return $this->block( $job_id, $state, 'import-payload-archive-entry-count-changed', false );
		}

		$cursor          = max( 0, (int) ( $state['archive_cursor'] ?? 0 ) );
		$entry_count     = count( $entries );
		$processed_files = 0;
		$processed_bytes = 0;

		while ( $cursor < $entry_count && $processed_files < $batch_files ) {
			$entry = $entries[ $cursor ];
			$name  = (string) $entry['name'];

			if ( true === $entry['folder'] ) {
				++$cursor;
				continue;
			}

			$declared_bytes = max( 0, (int) $entry['size'] );
			if ( 0 < $processed_files && $processed_bytes + $declared_bytes > $batch_bytes ) {
				break;
			}

			$extracted = $this->workspace->extract_import_archive_entry( $job_id, $name );
			if ( ! is_array( $extracted ) || $declared_bytes !== (int) $extracted['bytes'] ) {
				return $this->block( $job_id, $state, 'import-payload-extract-failed', true );
			}

			if (
				'package/manifest.json' === rtrim( $name, '/' )
				&& ! $this->same_hash( $state['package_manifest_sha256'] ?? '', $extracted['sha256'] )
			) {
				return $this->block( $job_id, $state, 'import-payload-package-manifest-changed', false );
			}

			++$cursor;
			++$processed_files;
			$processed_bytes            += (int) $extracted['bytes'];
			$state['extract_file_count'] = (int) ( $state['extract_file_count'] ?? 0 ) + 1;
			$state['extract_byte_count'] = (int) ( $state['extract_byte_count'] ?? 0 ) + (int) $extracted['bytes'];
		}

		$state['archive_cursor'] = $cursor;

		if ( $cursor >= $entry_count ) {
			$manifest = $this->workspace->import_extracted_file_info( $job_id, 'package/manifest.json' );
			if (
				! is_array( $manifest )
				|| ! $this->same_hash( $state['package_manifest_sha256'] ?? '', $manifest['sha256'] )
			) {
				return $this->block( $job_id, $state, 'import-payload-package-manifest-missing', false );
			}

			$state['stage']                 = 'verify';
			$state['directory_active']      = false;
			$state['pending_dirs']          = array( '' );
			$state['current_dir']           = '';
			$state['after_name']            = '';
			$state['verify_file_count']     = 0;
			$state['verify_byte_count']     = 0;
			$state['verification_checksum'] = hash( 'sha256', self::CHECKSUM_SEED );
		}

		return $this->persist_progress( $job_id, $state );
	}

	/**
	 * Advance the exact lexicographic BFS checksum replay over extracted payload.
	 *
	 * @param string              $job_id      Clone job identifier.
	 * @param array<string,mixed> $state       Payload state.
	 * @param int                 $batch_files Maximum files.
	 * @param int                 $batch_bytes Soft byte limit.
	 * @return array<string,mixed>|null
	 */
	private function advance_verification( string $job_id, array $state, int $batch_files, int $batch_bytes ): ?array {
		$root = $this->workspace->import_extraction_root( $job_id );
		if ( null === $root ) {
			return $this->block( $job_id, $state, 'import-payload-extraction-root-missing', false );
		}

		$processed_files = 0;
		$processed_bytes = 0;
		$operations      = 0;

		while ( $processed_files < $batch_files && $operations < self::MAX_ENTRY_OPERATIONS ) {
			$pending = is_array( $state['pending_dirs'] ?? null ) ? $state['pending_dirs'] : array();

			if ( true !== ( $state['directory_active'] ?? false ) ) {
				if ( array() === $pending ) {
					return $this->complete( $job_id, $state );
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
					return $this->block( $job_id, $state, 'import-payload-extracted-symlink', false );
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
					return $this->block( $job_id, $state, 'import-payload-file-unreadable', true );
				}

				$bytes = filesize( $path );
				$hash  = hash_file( 'sha256', $path );
				if ( false === $bytes || false === $hash ) {
					return $this->block( $job_id, $state, 'import-payload-file-hash-failed', true );
				}

				if ( 0 < $processed_files && $processed_bytes + (int) $bytes > $batch_bytes ) {
					$dir_finished = false;
					break;
				}

				$record                         = 'payload|' . wp_normalize_path( $relative ) . '|' . (string) (int) $bytes . '|' . $hash;
				$state['verification_checksum'] = $this->chain_hash( (string) $state['verification_checksum'], $record );
				$state['verify_file_count']     = (int) ( $state['verify_file_count'] ?? 0 ) + 1;
				$state['verify_byte_count']     = (int) ( $state['verify_byte_count'] ?? 0 ) + (int) $bytes;
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
	 * Complete full payload verification and unlock the next guarded restore phase.
	 *
	 * @param string              $job_id Clone job identifier.
	 * @param array<string,mixed> $state  Payload state.
	 * @return array<string,mixed>|null
	 */
	private function complete( string $job_id, array $state ): ?array {
		$manifest = $this->workspace->import_extracted_file_info( $job_id, 'package/manifest.json' );
		if (
			(int) ( $state['expected_file_count'] ?? -1 ) !== (int) ( $state['verify_file_count'] ?? -2 )
			|| (int) ( $state['expected_byte_count'] ?? -1 ) !== (int) ( $state['verify_byte_count'] ?? -2 )
			|| ! $this->same_hash( $state['expected_checksum'] ?? '', $state['verification_checksum'] ?? '' )
			|| ! is_array( $manifest )
			|| ! $this->same_hash( $state['package_manifest_sha256'] ?? '', $manifest['sha256'] )
		) {
			return $this->block( $job_id, $state, 'import-payload-checksum-mismatch', false );
		}

		$import = $this->import_state->get( $job_id );
		if (
			! is_array( $import )
			|| 'preflight-ready' !== ( $import['status'] ?? null )
			|| array() !== ( $import['blockers'] ?? array() )
			|| ! $this->same_hash( $import['archive_sha256'] ?? '', $state['archive_sha256'] ?? '' )
			|| ! $this->same_hash( $import['package_manifest_sha256'] ?? '', $state['package_manifest_sha256'] ?? '' )
			|| ! $this->same_hash( $import['package_checksum'] ?? '', $state['expected_checksum'] ?? '' )
		) {
			return $this->block( $job_id, $state, 'import-payload-preflight-state-changed', false );
		}

		$now                   = gmdate( DATE_ATOM );
		$state['status']       = 'complete';
		$state['stage']        = 'complete';
		$state['updated_at']   = $now;
		$state['completed_at'] = $now;
		if ( ! $this->store->save( $job_id, $state ) ) {
			return null;
		}

		$fresh = $this->preflight->validate( $job_id );
		if (
			! is_array( $fresh )
			|| 'payload-verified' !== ( $fresh['status'] ?? null )
			|| true !== ( $fresh['full_payload_verified'] ?? false )
			|| true !== ( $fresh['restore_allowed'] ?? false )
		) {
			return $this->store->get( $job_id );
		}

		$this->jobs->transition( $job_id, 'active' );
		$this->jobs->update_progress(
			$job_id,
			'verify',
			'payload-verified',
			array(
				'completed' => (int) $state['verify_file_count'],
				'total'     => (int) $state['expected_file_count'],
			)
		);

		return $this->store->get( $job_id );
	}

	/**
	 * Persist resumable extraction/checksum progress.
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

		$stage = (string) ( $state['stage'] ?? 'extract' );
		if ( 'extract' === $stage ) {
			$completed = (int) ( $state['archive_cursor'] ?? 0 );
			$total     = (int) ( $state['archive_entry_count'] ?? 0 );
			$cursor    = 'extract:' . (string) $completed;
			$phase     = 'validate';
		} else {
			$completed = (int) ( $state['verify_file_count'] ?? 0 );
			$total     = (int) ( $state['expected_file_count'] ?? 0 );
			$cursor    = 'verify:' . (string) ( $state['current_dir'] ?? '' ) . ':' . (string) ( $state['after_name'] ?? '' );
			$phase     = 'verify';
		}

		$this->jobs->update_progress(
			$job_id,
			$phase,
			substr( $cursor, 0, 160 ),
			array(
				'completed' => $completed,
				'total'     => $total,
			)
		);

		return $this->store->get( $job_id );
	}

	/**
	 * Persist one payload-verification blocker and keep restore locked.
	 *
	 * @param string              $job_id    Clone job identifier.
	 * @param array<string,mixed> $state     Payload state.
	 * @param string              $code      Machine-readable blocker.
	 * @param bool                $retryable Whether retry is allowed.
	 * @return array<string,mixed>|null
	 */
	private function block( string $job_id, array $state, string $code, bool $retryable ): ?array {
		$blockers            = is_array( $state['blockers'] ?? null ) ? $state['blockers'] : array();
		$blockers[]          = $code;
		$state['status']     = 'blocked';
		$state['blockers']   = array_values( array_unique( $blockers ) );
		$state['updated_at'] = gmdate( DATE_ATOM );
		$this->store->save( $job_id, $state );

		$import = $this->import_state->get( $job_id );
		if ( is_array( $import ) ) {
			$import_blockers                 = is_array( $import['blockers'] ?? null ) ? $import['blockers'] : array();
			$import_blockers[]               = $code;
			$import['status']                = 'blocked';
			$import['blockers']              = array_values( array_unique( $import_blockers ) );
			$import['full_payload_verified'] = false;
			$import['restore_allowed']       = false;
			$import['updated_at']            = gmdate( DATE_ATOM );
			$this->import_state->save( $job_id, $import );
		}

		$this->jobs->transition( $job_id, $retryable ? 'failed-retryable' : 'failed-terminal', $code );

		return $this->store->get( $job_id );
	}

	/**
	 * Confirm the staged ZIP identity is unchanged.
	 *
	 * @param string              $job_id Clone job identifier.
	 * @param array<string,mixed> $state  Payload state.
	 */
	private function archive_identity_matches( string $job_id, array $state ): bool {
		$archive = $this->workspace->import_archive_info( $job_id );

		return is_array( $archive )
			&& $this->same_hash( $state['archive_sha256'] ?? '', $archive['sha256'] );
	}

	/**
	 * Constant-time compare two validated SHA-256 values.
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
