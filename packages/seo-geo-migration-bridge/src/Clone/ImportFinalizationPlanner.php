<?php
/**
 * Portable Clone import finalization preflight.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge\Clone;

use SeoGeo\MigrationBridge\Sandbox\SandboxGuard;
use wpdb;

/**
 * Re-hashes verified staging and builds an immutable sandbox activation/rollback plan.
 *
 * This microphase is deliberately read-only with respect to active destination tables
 * and WordPress file roots. Promotion happens only after this planner reaches ready.
 */
final class ImportFinalizationPlanner {
	public const MIN_BATCH_ROWS      = 1;
	public const MAX_BATCH_ROWS      = 1000;
	public const DEFAULT_BATCH_ROWS  = 200;
	public const MIN_BATCH_FILES     = 1;
	public const MAX_BATCH_FILES     = 500;
	public const DEFAULT_BATCH_FILES = 100;
	public const MIN_BATCH_BYTES     = 1048576;
	public const MAX_BATCH_BYTES     = 134217728;
	public const DEFAULT_BATCH_BYTES = 16777216;

	private const DB_SEED                 = 'seo-geo-import-finalize-db-v1';
	private const FILE_SEED               = 'seo-geo-import-finalize-files-v1';
	private const MAX_PENDING_DIRECTORIES = 50000;
	private const MAX_ENTRY_OPERATIONS    = 8000;

	/**
	 * Finalization state store.
	 *
	 * @var ImportFinalizeStateStore
	 */
	private ImportFinalizeStateStore $store;

	/**
	 * Portable Import state store.
	 *
	 * @var ImportStateStore
	 */
	private ImportStateStore $import_state;

	/**
	 * Verified payload state store.
	 *
	 * @var ImportPayloadStateStore
	 */
	private ImportPayloadStateStore $payload_state;

	/**
	 * Staging database restore state store.
	 *
	 * @var ImportDatabaseStateStore
	 */
	private ImportDatabaseStateStore $database_state;

	/**
	 * Staging file restore state store.
	 *
	 * @var ImportFileStateStore
	 */
	private ImportFileStateStore $file_state;

	/**
	 * Environment rewrite state store.
	 *
	 * @var ImportRewriteStateStore
	 */
	private ImportRewriteStateStore $rewrite_state;

	/**
	 * Database staging-plan dependency.
	 *
	 * @var ImportDatabaseRestorer
	 */
	private ImportDatabaseRestorer $database_restorer;

	/**
	 * Portable Clone job store.
	 *
	 * @var CloneJobStore
	 */
	private CloneJobStore $jobs;

	/**
	 * Private import workspace.
	 *
	 * @var ExportWorkspace
	 */
	private ExportWorkspace $workspace;

	/**
	 * Construct planner.
	 *
	 * @param ImportFinalizeStateStore|null $store             Optional finalization state store.
	 * @param ImportStateStore|null         $import_state      Optional Portable Import state store.
	 * @param ImportPayloadStateStore|null  $payload_state     Optional payload verification state store.
	 * @param ImportDatabaseStateStore|null $database_state    Optional database staging state store.
	 * @param ImportFileStateStore|null     $file_state        Optional file staging state store.
	 * @param ImportRewriteStateStore|null  $rewrite_state     Optional environment rewrite state store.
	 * @param ImportDatabaseRestorer|null   $database_restorer Optional database staging-plan dependency.
	 * @param CloneJobStore|null            $jobs              Optional clone job store.
	 * @param ExportWorkspace|null          $workspace         Optional private import workspace.
	 */
	public function __construct(
		?ImportFinalizeStateStore $store = null,
		?ImportStateStore $import_state = null,
		?ImportPayloadStateStore $payload_state = null,
		?ImportDatabaseStateStore $database_state = null,
		?ImportFileStateStore $file_state = null,
		?ImportRewriteStateStore $rewrite_state = null,
		?ImportDatabaseRestorer $database_restorer = null,
		?CloneJobStore $jobs = null,
		?ExportWorkspace $workspace = null
	) {
		$this->store             = $store ?? new ImportFinalizeStateStore();
		$this->import_state      = $import_state ?? new ImportStateStore();
		$this->payload_state     = $payload_state ?? new ImportPayloadStateStore();
		$this->database_state    = $database_state ?? new ImportDatabaseStateStore();
		$this->file_state        = $file_state ?? new ImportFileStateStore();
		$this->rewrite_state     = $rewrite_state ?? new ImportRewriteStateStore();
		$this->jobs              = $jobs ?? new CloneJobStore();
		$this->workspace         = $workspace ?? new ExportWorkspace();
		$this->database_restorer = $database_restorer ?? new ImportDatabaseRestorer(
			$this->database_state,
			$this->import_state,
			$this->payload_state,
			$this->jobs,
			null,
			$this->workspace
		);
	}

	/**
	 * Return one finalization snapshot.
	 *
	 * @param string $job_id Clone job identifier.
	 * @return array<string,mixed>|null
	 */
	public function snapshot( string $job_id ): ?array {
		return $this->store->get( $job_id );
	}

	/**
	 * Start finalization preflight after rewrite verification completed.
	 *
	 * @param string $job_id Clone job identifier.
	 * @return array<string,mixed>|null
	 */
	public function start( string $job_id ): ?array {
		$existing = $this->store->get( $job_id );
		if ( is_array( $existing ) ) {
			return $existing;
		}

		$gate = $this->runtime_gate( $job_id );
		if ( null === $gate ) {
			return null;
		}

		$database = $gate['database_state'];
		$files    = $gate['file_state'];
		$payload  = $gate['payload_state'];
		$now      = gmdate( DATE_ATOM );

		$state = array(
			'schema_version'           => ImportFinalizeStateStore::SCHEMA_VERSION,
			'job_id'                   => $job_id,
			'status'                   => 'running',
			'stage'                    => 'database-fingerprint',
			'database_table_index'     => 0,
			'database_row_offset'      => 0,
			'database_rows_hashed'     => 0,
			'database_table_count'     => count( $gate['database_plan']['tables'] ),
			'database_fingerprint'     => hash( 'sha256', self::DB_SEED ),
			'file_root_index'          => 0,
			'file_pending_dirs'        => array( '' ),
			'file_current_dir'         => '',
			'file_after_name'          => '',
			'files_hashed'             => 0,
			'file_bytes_hashed'        => 0,
			'expected_file_count'      => (int) ( $files['expected_file_count'] ?? 0 ),
			'expected_file_bytes'      => (int) ( $files['expected_byte_count'] ?? 0 ),
			'file_fingerprint'         => hash( 'sha256', self::FILE_SEED ),
			'activation_plan_hash'     => (string) $gate['activation_plan_hash'],
			'database_manifest_sha256' => (string) ( $database['database_manifest_sha256'] ?? '' ),
			'files_manifest_sha256'    => (string) ( $files['files_manifest_sha256'] ?? '' ),
			'payload_archive_sha256'   => (string) ( $payload['archive_sha256'] ?? '' ),
			'sandbox_hardening_ready'  => true,
			'rollback_plan_ready'      => true,
			'activation_allowed'       => false,
			'handoff_ready'            => false,
			'active_tables_untouched'  => true,
			'active_roots_untouched'   => true,
			'blockers'                 => array(),
			'advisories'               => $gate['advisories'],
			'started_at'               => $now,
			'updated_at'               => $now,
			'completed_at'             => '',
		);

		if ( ! $this->store->save( $job_id, $state ) ) {
			return null;
		}

		$this->jobs->transition( $job_id, 'active' );
		$this->jobs->update_progress(
			$job_id,
			'finalize-preflight',
			'database:0:0',
			array(
				'completed' => 0,
				'total'     => null,
			)
		);

		return $this->store->get( $job_id );
	}

	/**
	 * Advance one bounded database or file fingerprint batch.
	 *
	 * @param string $job_id     Clone job identifier.
	 * @param int    $batch_rows Maximum database rows hashed.
	 * @param int    $batch_files Maximum files hashed.
	 * @param int    $batch_bytes Soft maximum bytes hashed.
	 * @return array<string,mixed>|null
	 */
	public function advance(
		string $job_id,
		int $batch_rows = self::DEFAULT_BATCH_ROWS,
		int $batch_files = self::DEFAULT_BATCH_FILES,
		int $batch_bytes = self::DEFAULT_BATCH_BYTES
	): ?array {
		$batch_rows  = max( self::MIN_BATCH_ROWS, min( self::MAX_BATCH_ROWS, $batch_rows ) );
		$batch_files = max( self::MIN_BATCH_FILES, min( self::MAX_BATCH_FILES, $batch_files ) );
		$batch_bytes = max( self::MIN_BATCH_BYTES, min( self::MAX_BATCH_BYTES, $batch_bytes ) );
		$state       = $this->store->get( $job_id ) ?? $this->start( $job_id );

		if ( ! is_array( $state ) || in_array( $state['status'] ?? null, array( 'ready', 'blocked' ), true ) ) {
			return $state;
		}

		$gate = $this->runtime_gate( $job_id, $state );
		if ( null === $gate ) {
			return $this->block( $job_id, $state, 'import-finalize-runtime-guard-failed', false );
		}

		if ( 'database-fingerprint' === ( $state['stage'] ?? null ) ) {
			return $this->advance_database( $job_id, $state, $gate, $batch_rows );
		}

		return $this->advance_files( $job_id, $state, $gate, $batch_files, $batch_bytes );
	}

	/**
	 * Hash one bounded database batch deterministically.
	 *
	 * @param string              $job_id Clone job identifier.
	 * @param array<string,mixed> $state  Finalization state.
	 * @param array<string,mixed> $gate   Fresh runtime gate.
	 * @param int                 $batch_rows Maximum rows.
	 * @return array<string,mixed>|null
	 */
	private function advance_database( string $job_id, array $state, array $gate, int $batch_rows ): ?array {
		$tables = $gate['database_plan']['tables'];
		$index  = (int) ( $state['database_table_index'] ?? 0 );

		if ( $index >= count( $tables ) ) {
			$state['stage']             = 'file-fingerprint';
			$state['file_root_index']   = 0;
			$state['file_pending_dirs'] = array( '' );
			$state['file_current_dir']  = '';
			$state['file_after_name']   = '';
			$state['updated_at']        = gmdate( DATE_ATOM );

			return $this->persist( $job_id, $state );
		}

		$spec   = $tables[ $index ];
		$offset = (int) ( $state['database_row_offset'] ?? 0 );
		if ( ! is_array( $spec ) ) {
			return $this->block( $job_id, $state, 'import-finalize-database-plan-invalid', false );
		}

		$columns       = is_array( $spec['columns'] ?? null ) ? array_values( array_filter( $spec['columns'], 'is_string' ) ) : array();
		$table         = is_string( $spec['staging_table'] ?? null ) ? $spec['staging_table'] : '';
		$source        = is_string( $spec['source_table'] ?? null ) ? $spec['source_table'] : '';
		$target        = is_string( $spec['target_table'] ?? null ) ? $spec['target_table'] : '';
		$rows_expected = max( 0, (int) ( $spec['row_count'] ?? 0 ) );

		if ( '' === $table || '' === $source || '' === $target || ( 0 < $rows_expected && array() === $columns ) ) {
			return $this->block( $job_id, $state, 'import-finalize-database-plan-invalid', false );
		}

		if ( 0 === $offset ) {
			$marker                        = 'table|' . $source . '|' . $target . '|' . $table . '|' . (string) $rows_expected;
			$state['database_fingerprint'] = $this->chain_hash( (string) $state['database_fingerprint'], $marker );
		}

		if ( 0 === $rows_expected ) {
			$state['database_table_index'] = $index + 1;
			$state['database_row_offset']  = 0;

			return $this->persist( $job_id, $state );
		}

		$rows = $this->database_rows( $spec, $offset, $batch_rows );
		if ( null === $rows ) {
			return $this->block( $job_id, $state, 'import-finalize-database-read-failed', true );
		}

		foreach ( $rows as $row ) {
			$canonical = $this->canonical_database_row( $columns, $row );
			if ( null === $canonical ) {
				return $this->block( $job_id, $state, 'import-finalize-database-row-encode-failed', false );
			}
			$state['database_fingerprint'] = $this->chain_hash(
				(string) $state['database_fingerprint'],
				'row|' . $source . '|' . $canonical
			);
			++$state['database_rows_hashed'];
			++$state['database_row_offset'];
		}

		if ( count( $rows ) < $batch_rows ) {
			if ( (int) $state['database_row_offset'] !== $rows_expected ) {
				return $this->block( $job_id, $state, 'import-finalize-database-row-count-mismatch', false );
			}
			$state['database_table_index'] = $index + 1;
			$state['database_row_offset']  = 0;
		}

		return $this->persist( $job_id, $state );
	}

	/**
	 * Hash one bounded staged-file batch and validate every file-record binding.
	 *
	 * @param string              $job_id Clone job identifier.
	 * @param array<string,mixed> $state  Finalization state.
	 * @param array<string,mixed> $gate   Fresh runtime gate.
	 * @param int                 $batch_files Maximum files.
	 * @param int                 $batch_bytes Soft maximum bytes.
	 * @return array<string,mixed>|null
	 */
	private function advance_files(
		string $job_id,
		array $state,
		array $gate,
		int $batch_files,
		int $batch_bytes
	): ?array {
		$roots           = $gate['file_roots'];
		$processed_files = 0;
		$processed_bytes = 0;
		$operations      = 0;

		while ( $processed_files < $batch_files && $operations < self::MAX_ENTRY_OPERATIONS ) {
			$root_index = (int) ( $state['file_root_index'] ?? 0 );
			if ( $root_index >= count( $roots ) ) {
				return $this->complete( $job_id, $state, $gate );
			}

			$root      = $roots[ $root_index ];
			$root_id   = (string) $root['id'];
			$root_base = $this->staged_root( $job_id, $root_id );
			if ( null === $root_base ) {
				if ( 0 === (int) $root['file_count'] ) {
					$state = $this->advance_file_root( $state );
					continue;
				}

				return $this->block( $job_id, $state, 'import-finalize-staged-root-unavailable', false );
			}

			$pending = is_array( $state['file_pending_dirs'] ?? null ) ? $state['file_pending_dirs'] : array();
			if ( '' === (string) ( $state['file_current_dir'] ?? '' ) && '' === (string) ( $state['file_after_name'] ?? '' ) ) {
				if ( array() === $pending ) {
					$state = $this->advance_file_root( $state );
					continue;
				}
				$state['file_current_dir']  = (string) array_shift( $pending );
				$state['file_pending_dirs'] = $pending;
			}

			$current_dir = (string) ( $state['file_current_dir'] ?? '' );
			$absolute    = $this->join_path( $root_base, $current_dir );
			$entries     = scandir( $absolute, SCANDIR_SORT_ASCENDING );
			if ( false === $entries ) {
				return $this->block( $job_id, $state, 'import-finalize-file-directory-read-failed', true );
			}

			$after_name   = (string) ( $state['file_after_name'] ?? '' );
			$dir_finished = true;
			foreach ( $entries as $entry ) {
				if ( '.' === $entry || '..' === $entry || ( '' !== $after_name && strcmp( $entry, $after_name ) <= 0 ) ) {
					continue;
				}

				++$operations;
				$relative = '' === $current_dir ? $entry : $current_dir . '/' . $entry;
				$path     = $this->join_path( $root_base, $relative );

				if ( is_link( $path ) ) {
					return $this->block( $job_id, $state, 'import-finalize-staged-file-symlink', false );
				}

				if ( is_dir( $path ) ) {
					$pending = is_array( $state['file_pending_dirs'] ?? null ) ? $state['file_pending_dirs'] : array();
					if ( count( $pending ) >= self::MAX_PENDING_DIRECTORIES ) {
						return $this->block( $job_id, $state, 'import-finalize-file-directory-queue-limit', false );
					}
					$pending[]                  = $relative;
					$state['file_pending_dirs'] = $pending;
					$state['file_after_name']   = $entry;

					if ( $operations >= self::MAX_ENTRY_OPERATIONS ) {
						$dir_finished = false;
						break;
					}
					continue;
				}

				if ( ! is_file( $path ) || ! is_readable( $path ) ) {
					return $this->block( $job_id, $state, 'import-finalize-staged-file-unreadable', true );
				}

				$record = $this->file_record( $job_id, $root_id, $relative );
				$bytes  = filesize( $path );
				$hash   = hash_file( 'sha256', $path );
				if (
					null === $record
					|| false === $bytes
					|| false === $hash
					|| (int) $record['byte_count'] !== (int) $bytes
					|| ! hash_equals( (string) $record['sha256'], $hash )
				) {
					return $this->block( $job_id, $state, 'import-finalize-staged-file-integrity-mismatch', false );
				}

				$state['file_fingerprint'] = $this->chain_hash(
					(string) $state['file_fingerprint'],
					'file|' . $root_id . '|' . wp_normalize_path( $relative ) . '|' . (string) (int) $bytes . '|' . $hash
				);
				++$state['files_hashed'];
				$state['file_bytes_hashed'] = (int) $state['file_bytes_hashed'] + (int) $bytes;
				$state['file_after_name']   = $entry;
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
				$state['file_current_dir'] = '';
				$state['file_after_name']  = '';
			}

			if ( 0 < $processed_files && $processed_bytes >= $batch_bytes ) {
				break;
			}
		}

		return $this->persist( $job_id, $state );
	}

	/**
	 * Complete preflight only after exact staging reconciliation and a fresh safety gate.
	 *
	 * @param string              $job_id Clone job identifier.
	 * @param array<string,mixed> $state  State.
	 * @param array<string,mixed> $gate   Fresh runtime gate.
	 * @return array<string,mixed>|null
	 */
	private function complete( string $job_id, array $state, array $gate ): ?array {
		if (
			(int) ( $state['files_hashed'] ?? 0 ) !== (int) ( $state['expected_file_count'] ?? -1 )
			|| (int) ( $state['file_bytes_hashed'] ?? 0 ) !== (int) ( $state['expected_file_bytes'] ?? -1 )
			|| (int) ( $state['database_rows_hashed'] ?? 0 ) !== (int) $gate['database_rows_expected']
			|| ! hash_equals( (string) $state['activation_plan_hash'], (string) $gate['activation_plan_hash'] )
		) {
			return $this->block( $job_id, $state, 'import-finalize-fingerprint-reconciliation-failed', false );
		}

		$fresh = $this->runtime_gate( $job_id, $state );
		if ( null === $fresh ) {
			return $this->block( $job_id, $state, 'import-finalize-final-safety-gate-failed', false );
		}

		$now                              = gmdate( DATE_ATOM );
		$state['status']                  = 'ready';
		$state['stage']                   = 'ready';
		$state['sandbox_hardening_ready'] = true;
		$state['rollback_plan_ready']     = true;
		$state['activation_allowed']      = true;
		$state['handoff_ready']           = false;
		$state['updated_at']              = $now;
		$state['completed_at']            = $now;

		if ( ! $this->store->save( $job_id, $state ) ) {
			return null;
		}

		$this->jobs->transition( $job_id, 'active' );
		$this->jobs->update_progress(
			$job_id,
			'finalize-preflight',
			'ready',
			array(
				'completed' => (int) ( $state['files_hashed'] ?? 0 ) + (int) ( $state['database_rows_hashed'] ?? 0 ),
				'total'     => (int) ( $state['expected_file_count'] ?? 0 ) + (int) $fresh['database_rows_expected'],
			)
		);

		return $this->store->get( $job_id );
	}

	/**
	 * Rebuild every safety/input dependency before each bounded batch.
	 *
	 * @param string                   $job_id Clone job identifier.
	 * @param array<string,mixed>|null $state  Optional current finalization state.
	 * @return array<string,mixed>|null
	 */
	private function runtime_gate( string $job_id, ?array $state = null ): ?array {
		$job      = $this->jobs->get( $job_id );
		$import   = $this->import_state->get( $job_id );
		$payload  = $this->payload_state->get( $job_id );
		$database = $this->database_state->get( $job_id );
		$files    = $this->file_state->get( $job_id );
		$rewrite  = $this->rewrite_state->get( $job_id );

		if (
			! is_array( $job )
			|| 'import' !== ( $job['operation'] ?? null )
			|| ! is_array( $import )
			|| 'payload-verified' !== ( $import['status'] ?? null )
			|| true !== ( $import['full_payload_verified'] ?? false )
			|| true !== ( $import['restore_allowed'] ?? false )
			|| array() !== ( $import['blockers'] ?? array() )
			|| ! is_array( $payload )
			|| 'complete' !== ( $payload['status'] ?? null )
			|| 'complete' !== ( $payload['stage'] ?? null )
			|| ! is_array( $database )
			|| 'complete' !== ( $database['status'] ?? null )
			|| true !== ( $database['active_tables_untouched'] ?? false )
			|| ! is_array( $files )
			|| 'complete' !== ( $files['status'] ?? null )
			|| true !== ( $files['active_roots_untouched'] ?? false )
			|| ! is_array( $rewrite )
			|| 'complete' !== ( $rewrite['status'] ?? null )
			|| 'complete' !== ( $rewrite['stage'] ?? null )
			|| 0 !== (int) ( $rewrite['verify_source_urls'] ?? -1 )
			|| true !== ( $rewrite['active_tables_untouched'] ?? false )
			|| true !== ( $rewrite['active_roots_untouched'] ?? false )
			|| array() !== ( $rewrite['blockers'] ?? array() )
		) {
			return null;
		}

		if (
			! $this->same_hash( $import['archive_sha256'] ?? '', $payload['archive_sha256'] ?? '' )
			|| ! $this->same_hash( $payload['archive_sha256'] ?? '', $database['payload_archive_sha256'] ?? '' )
			|| ! $this->same_hash( $payload['archive_sha256'] ?? '', $files['payload_archive_sha256'] ?? '' )
			|| ! $this->same_hash( $database['database_manifest_sha256'] ?? '', $rewrite['database_manifest_sha256'] ?? '' )
			|| ! $this->same_hash( $files['files_manifest_sha256'] ?? '', $rewrite['file_manifest_sha256'] ?? '' )
		) {
			return null;
		}

		if ( is_array( $state ) ) {
			if (
				! $this->same_hash( $state['payload_archive_sha256'] ?? '', $payload['archive_sha256'] ?? '' )
				|| ! $this->same_hash( $state['database_manifest_sha256'] ?? '', $database['database_manifest_sha256'] ?? '' )
				|| ! $this->same_hash( $state['files_manifest_sha256'] ?? '', $files['files_manifest_sha256'] ?? '' )
			) {
				return null;
			}
		}

		if ( ! $this->sandbox_ready( $import ) ) {
			return null;
		}

		$database_plan = $this->database_restorer->staging_plan( $job_id );
		$file_roots    = $this->file_roots( $job_id, $files );
		if ( null === $database_plan || null === $file_roots ) {
			return null;
		}

		$activation = $this->activation_plan( $job_id, $database_plan, $file_roots );
		if ( null === $activation ) {
			return null;
		}

		$database_rows_expected = 0;
		foreach ( $database_plan['tables'] as $table ) {
			if ( is_array( $table ) ) {
				$database_rows_expected += max( 0, (int) ( $table['row_count'] ?? 0 ) );
			}
		}

		$advisories = is_array( $rewrite['advisories'] ?? null )
			? array_values( array_filter( $rewrite['advisories'], 'is_string' ) )
			: array();

		return array(
			'import_state'           => $import,
			'payload_state'          => $payload,
			'database_state'         => $database,
			'file_state'             => $files,
			'rewrite_state'          => $rewrite,
			'database_plan'          => $database_plan,
			'database_rows_expected' => $database_rows_expected,
			'file_roots'             => $file_roots,
			'activation_plan'        => $activation['plan'],
			'activation_plan_hash'   => $activation['hash'],
			'advisories'             => $advisories,
		);
	}

	/**
	 * Revalidate sandbox hardening and destination identity.
	 *
	 * @param array<string,mixed> $import Import state.
	 */
	private function sandbox_ready( array $import ): bool {
		global $wpdb;
		if ( ! $wpdb instanceof wpdb ) {
			return false;
		}

		$authorized = defined( ImportPreflight::TARGET_AUTHORIZED_MARKER )
			&& true === constant( ImportPreflight::TARGET_AUTHORIZED_MARKER );
		$mode       = SandboxGuard::mode();

		if (
			! SandboxGuard::enabled()
			|| 'invalid' === $mode
			|| '0' !== (string) get_option( 'blog_public', '1' )
			|| ! SandboxGuard::outbound_safe()
			|| ! SandboxGuard::backups_ready()
			|| ! $authorized
			|| ( 'subdirectory' === $mode && ! SandboxGuard::storage_isolated() )
			|| untrailingslashit( (string) ( $import['destination_home_url'] ?? '' ) ) !== untrailingslashit( home_url( '/' ) )
			|| untrailingslashit( (string) ( $import['destination_site_url'] ?? '' ) ) !== untrailingslashit( site_url( '/' ) )
			|| (string) ( $import['destination_table_prefix'] ?? '' ) !== $wpdb->prefix
			|| (string) ( $import['destination_mode'] ?? '' ) !== $mode
		) {
			return false;
		}

		return true;
	}

	/**
	 * Load normalized file-root summaries and bind them to completed file staging.
	 *
	 * @param string              $job_id Clone job identifier.
	 * @param array<string,mixed> $files  File-restore state.
	 * @return list<array{id:string,file_count:int,byte_count:int}>|null
	 */
	private function file_roots( string $job_id, array $files ): ?array {
		$info = $this->workspace->import_extracted_file_info( $job_id, 'files/manifest.json' );
		$json = $this->workspace->read_import_extracted_file( $job_id, 'files/manifest.json' );
		if (
			null === $info
			|| ! is_string( $json )
			|| ! $this->same_hash( $info['sha256'], $files['files_manifest_sha256'] ?? '' )
		) {
			return null;
		}

		$manifest = json_decode( $json, true );
		$roots    = is_array( $manifest['roots'] ?? null ) ? array_values( $manifest['roots'] ) : array();
		if ( ! is_array( $manifest ) ) {
			return null;
		}

		$normalized  = array();
		$total_files = 0;
		$total_bytes = 0;
		foreach ( $roots as $root ) {
			if (
				! is_array( $root )
				|| ! is_string( $root['id'] ?? null )
				|| ! in_array( $root['id'], array( 'uploads', 'plugins', 'themes' ), true )
			) {
				return null;
			}

			$entry        = array(
				'id'         => $root['id'],
				'file_count' => max( 0, (int) ( $root['file_count'] ?? 0 ) ),
				'byte_count' => max( 0, (int) ( $root['byte_count'] ?? 0 ) ),
			);
			$total_files += $entry['file_count'];
			$total_bytes += $entry['byte_count'];
			$normalized[] = $entry;
		}

		if (
			(int) ( $files['expected_file_count'] ?? -1 ) !== $total_files
			|| (int) ( $files['expected_byte_count'] ?? -1 ) !== $total_bytes
		) {
			return null;
		}

		return $normalized;
	}

	/**
	 * Build a deterministic activation + rollback plan without mutating active targets.
	 *
	 * @param string $job_id        Clone job identifier.
	 * @param array  $database_plan Database staging plan.
	 * @param array  $file_roots    File roots.
	 * @phpstan-param array<string,mixed> $database_plan
	 * @phpstan-param list<array{id:string,file_count:int,byte_count:int}> $file_roots
	 * @return array{plan:array<string,mixed>,hash:string}|null
	 */
	private function activation_plan( string $job_id, array $database_plan, array $file_roots ): ?array {
		$token  = substr( hash( 'sha256', $job_id ), 0, 10 );
		$tables = array();

		foreach ( $database_plan['tables'] as $table ) {
			if (
				! is_array( $table )
				|| ! is_string( $table['target_table'] ?? null )
				|| ! is_string( $table['staging_table'] ?? null )
			) {
				return null;
			}

			$target  = $table['target_table'];
			$staging = $table['staging_table'];
			$backup  = $this->rollback_table_name( (string) $database_plan['destination_prefix'], $token, $target );
			if ( '' === $backup || $this->table_exists( $backup ) ) {
				return null;
			}

			$tables[] = array(
				'source_table'   => (string) $table['source_table'],
				'staging_table'  => $staging,
				'target_table'   => $target,
				'target_exists'  => $this->table_exists( $target ),
				'rollback_table' => $backup,
				'row_count'      => max( 0, (int) ( $table['row_count'] ?? 0 ) ),
			);
		}

		$roots = array();
		foreach ( $file_roots as $root ) {
			$root_id = (string) $root['id'];
			$staged  = $this->staged_root( $job_id, $root_id );
			$active  = $this->active_root( $root_id );
			if ( null === $staged || null === $active || is_link( $active ) ) {
				return null;
			}

			$parent = wp_normalize_path( dirname( untrailingslashit( $active ) ) );
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_is_writable -- Read-only local capability check; this phase performs no filesystem mutation.
			if ( ! is_dir( $parent ) || ! is_writable( $parent ) || is_link( $parent ) ) {
				return null;
			}

			$base      = basename( untrailingslashit( $active ) );
			$candidate = $parent . '/.' . $base . '.seo-geo-next-' . $token;
			$rollback  = $parent . '/.' . $base . '.seo-geo-rollback-' . $token;
			if ( file_exists( $candidate ) || file_exists( $rollback ) || is_link( $candidate ) || is_link( $rollback ) ) {
				return null;
			}

			$roots[] = array(
				'id'             => $root_id,
				'staged_root'    => $staged,
				'active_root'    => trailingslashit( wp_normalize_path( $active ) ),
				'candidate_root' => trailingslashit( wp_normalize_path( $candidate ) ),
				'rollback_root'  => trailingslashit( wp_normalize_path( $rollback ) ),
				'file_count'     => (int) $root['file_count'],
				'byte_count'     => (int) $root['byte_count'],
			);
		}

		$plan = array(
			'schema_version' => 1,
			'job_id'         => $job_id,
			'database'       => array(
				'destination_prefix' => (string) $database_plan['destination_prefix'],
				'tables'             => $tables,
			),
			'files'          => array(
				'roots' => $roots,
			),
			'policy'         => array(
				'active_mutation_in_this_phase' => false,
				'rollback_required_before_swap' => true,
				'final_handoff_ready'           => false,
			),
		);

		$json = wp_json_encode( $plan, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		if ( ! is_string( $json ) ) {
			return null;
		}

		return array(
			'plan' => $plan,
			'hash' => hash( 'sha256', $json ),
		);
	}

	/**
	 * Read one deterministic database row batch.
	 *
	 * @param array<string,mixed> $spec Table spec.
	 * @param int                 $offset Row offset.
	 * @param int                 $limit Row limit.
	 * @return list<array<string,mixed>>|null
	 */
	private function database_rows( array $spec, int $offset, int $limit ): ?array {
		global $wpdb;
		if ( ! $wpdb instanceof wpdb ) {
			return null;
		}

		$table   = is_string( $spec['staging_table'] ?? null ) ? $spec['staging_table'] : '';
		$columns = is_array( $spec['columns'] ?? null ) ? array_values( array_filter( $spec['columns'], 'is_string' ) ) : array();
		if ( ! $this->valid_table_name( $table ) || array() === $columns ) {
			return null;
		}
		foreach ( $columns as $column ) {
			if ( ! $this->valid_column_name( $column ) ) {
				return null;
			}
		}

		$order  = $columns;
		$cursor = is_string( $spec['cursor_column'] ?? null ) ? $spec['cursor_column'] : '';
		if ( '' !== $cursor && in_array( $cursor, $columns, true ) ) {
			$order = array_merge( array( $cursor ), array_values( array_diff( $columns, array( $cursor ) ) ) );
		}

		$select = implode( ', ', array_map( array( $this, 'quote_identifier' ), $columns ) );
		$sort   = implode( ', ', array_map( array( $this, 'quote_identifier' ), $order ) );
		$quoted = $this->quote_identifier( $table );
		$sql    = $wpdb->prepare(
			"SELECT {$select} FROM {$quoted} ORDER BY {$sort} LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Identifiers are validated/quoted above; only limits are placeholders.
			$limit,
			$offset
		);
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- Read-only deterministic staging-table fingerprint.
		$rows = $wpdb->get_results( $sql, ARRAY_A );

		return is_array( $rows ) ? $rows : null;
	}

	/**
	 * Encode one DB row in manifest column order using binary-safe base64 cells.
	 *
	 * @param array $columns Manifest column order.
	 * @param array $row     Database row.
	 * @phpstan-param list<string> $columns
	 * @phpstan-param array<string,mixed> $row
	 */
	private function canonical_database_row( array $columns, array $row ): ?string {
		$cells = array();
		foreach ( $columns as $column ) {
			if ( ! array_key_exists( $column, $row ) ) {
				return null;
			}
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Binary-safe deterministic staging fingerprint encoding.
			$cells[] = null === $row[ $column ] ? null : base64_encode( (string) $row[ $column ] );
		}

		$json = wp_json_encode( $cells, JSON_UNESCAPED_SLASHES );

		return is_string( $json ) ? $json : null;
	}

	/**
	 * Return one manifest-backed file record.
	 *
	 * @param string $job_id  Clone job identifier.
	 * @param string $root_id Root ID.
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
			|| ( $record['root'] ?? null ) !== $root_id
			|| ( $record['relative_path'] ?? null ) !== $relative
			|| ( $record['payload_path'] ?? null ) !== 'files/' . $root_id . '/' . $relative
			|| 'copied' !== ( $record['export_status'] ?? null )
			|| 0 > (int) ( $record['byte_count'] ?? -1 )
			|| ! $this->valid_hash( $hash )
		) {
			return null;
		}

		return array(
			'byte_count' => (int) $record['byte_count'],
			'sha256'     => $hash,
		);
	}

	/**
	 * Return one staging root.
	 *
	 * @param string $job_id Clone job identifier.
	 * @param string $root_id Root ID.
	 */
	private function staged_root( string $job_id, string $root_id ): ?string {
		$root = $this->workspace->import_file_staging_root( $job_id );
		if ( null === $root || ! in_array( $root_id, array( 'uploads', 'plugins', 'themes' ), true ) ) {
			return null;
		}

		$path = wp_normalize_path( trailingslashit( $root ) . $root_id . '/' );

		return is_dir( $path ) && ! is_link( $path ) ? $path : null;
	}

	/**
	 * Resolve one active WordPress content root.
	 *
	 * @param string $root_id Root ID.
	 */
	private function active_root( string $root_id ): ?string {
		if ( 'uploads' === $root_id ) {
			$uploads = wp_upload_dir( null, false );
			$basedir = is_string( $uploads['basedir'] ?? null ) ? $uploads['basedir'] : '';

			return '' !== $basedir && is_dir( $basedir ) ? wp_normalize_path( $basedir ) : null;
		}
		if ( 'plugins' === $root_id ) {
			return defined( 'WP_PLUGIN_DIR' ) && is_dir( WP_PLUGIN_DIR ) ? wp_normalize_path( WP_PLUGIN_DIR ) : null;
		}
		if ( 'themes' === $root_id ) {
			$themes = get_theme_root();

			return is_string( $themes ) && is_dir( $themes ) ? wp_normalize_path( $themes ) : null;
		}

		return null;
	}

	/**
	 * Advance to next file root.
	 *
	 * @param array<string,mixed> $state State.
	 * @return array<string,mixed>
	 */
	private function advance_file_root( array $state ): array {
		$state['file_root_index']   = (int) ( $state['file_root_index'] ?? 0 ) + 1;
		$state['file_pending_dirs'] = array( '' );
		$state['file_current_dir']  = '';
		$state['file_after_name']   = '';

		return $state;
	}

	/**
	 * Persist progress and update clone job cursor.
	 *
	 * @param string              $job_id Clone job identifier.
	 * @param array<string,mixed> $state State.
	 * @return array<string,mixed>|null
	 */
	private function persist( string $job_id, array $state ): ?array {
		$state['updated_at'] = gmdate( DATE_ATOM );
		if ( ! $this->store->save( $job_id, $state ) ) {
			return null;
		}

		$cursor = (string) ( $state['stage'] ?? '' ) . ':'
			. (string) (int) ( $state['database_table_index'] ?? 0 ) . ':'
			. (string) (int) ( $state['database_row_offset'] ?? 0 ) . ':'
			. (string) (int) ( $state['file_root_index'] ?? 0 ) . ':'
			. (string) ( $state['file_current_dir'] ?? '' ) . ':'
			. (string) ( $state['file_after_name'] ?? '' );

		$this->jobs->transition( $job_id, 'active' );
		$this->jobs->update_progress(
			$job_id,
			'finalize-preflight',
			substr( $cursor, 0, 512 ),
			array(
				'completed' => (int) ( $state['database_rows_hashed'] ?? 0 ) + (int) ( $state['files_hashed'] ?? 0 ),
				'total'     => null,
			)
		);

		return $this->store->get( $job_id );
	}

	/**
	 * Persist one blocker.
	 *
	 * @param string              $job_id Clone job identifier.
	 * @param array<string,mixed> $state State.
	 * @param string              $code Blocker code.
	 * @param bool                $retryable Retry marker.
	 * @return array<string,mixed>|null
	 */
	private function block( string $job_id, array $state, string $code, bool $retryable ): ?array {
		$blockers                    = is_array( $state['blockers'] ?? null ) ? $state['blockers'] : array();
		$blockers[]                  = $code;
		$state['status']             = 'blocked';
		$state['activation_allowed'] = false;
		$state['handoff_ready']      = false;
		$state['blockers']           = array_values( array_unique( $blockers ) );
		$state['updated_at']         = gmdate( DATE_ATOM );

		$this->store->save( $job_id, $state );
		$this->jobs->transition( $job_id, $retryable ? 'failed-retryable' : 'failed-terminal', $code );

		return $this->store->get( $job_id );
	}

	/**
	 * Build deterministic rollback table name.
	 *
	 * @param string $prefix Destination table prefix.
	 * @param string $token  Job-derived rollback token.
	 * @param string $target Active target table name.
	 */
	private function rollback_table_name( string $prefix, string $token, string $target ): string {
		if ( 1 !== preg_match( '/^[A-Za-z0-9_]+$/', $prefix ) ) {
			return '';
		}

		$name = $prefix . 'sgmrb_' . $token . '_' . substr( hash( 'sha256', $target ), 0, 12 );

		return $this->valid_table_name( $name ) ? $name : '';
	}

	/**
	 * Whether one table exists exactly.
	 *
	 * @param string $table Table name.
	 */
	private function table_exists( string $table ): bool {
		global $wpdb;
		if ( ! $wpdb instanceof wpdb || ! $this->valid_table_name( $table ) ) {
			return false;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Read-only exact table-existence guard.
		$found = $wpdb->get_var(
			$wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) )
		);

		return is_string( $found ) && $found === $table;
	}

	/**
	 * Chain one canonical record.
	 *
	 * @param string $previous Previous chain hash.
	 * @param string $record   Canonical record.
	 */
	private function chain_hash( string $previous, string $record ): string {
		return hash( 'sha256', $previous . "\n" . $record );
	}

	/**
	 * Validate table name.
	 *
	 * @param string $table Table name.
	 */
	private function valid_table_name( string $table ): bool {
		return '' !== $table
			&& 64 >= strlen( $table )
			&& 1 !== preg_match( '/[\x00-\x1F\x7F]/', $table );
	}

	/**
	 * Validate column identifier.
	 *
	 * @param string $column Column name.
	 */
	private function valid_column_name( string $column ): bool {
		return '' !== $column
			&& 64 >= strlen( $column )
			&& 1 === preg_match( '/^[A-Za-z0-9_$]+$/', $column );
	}

	/**
	 * Quote validated identifier.
	 *
	 * @param string $identifier Validated identifier.
	 */
	private function quote_identifier( string $identifier ): string {
		$tick = chr( 96 );

		return $tick . str_replace( $tick, $tick . $tick, $identifier ) . $tick;
	}

	/**
	 * Validate one SHA-256.
	 *
	 * @param mixed $hash Candidate hash.
	 */
	private function valid_hash( mixed $hash ): bool {
		return is_string( $hash ) && 1 === preg_match( '/^[a-f0-9]{64}$/', $hash );
	}

	/**
	 * Constant-time hash compare.
	 *
	 * @param mixed $left  First candidate hash.
	 * @param mixed $right Second candidate hash.
	 */
	private function same_hash( mixed $left, mixed $right ): bool {
		return $this->valid_hash( $left )
			&& $this->valid_hash( $right )
			&& hash_equals( (string) $left, (string) $right );
	}

	/**
	 * Join paths.
	 *
	 * @param string $base     Absolute base path.
	 * @param string $relative Relative path.
	 */
	private function join_path( string $base, string $relative ): string {
		return rtrim( wp_normalize_path( $base ), '/' )
			. ( '' === $relative ? '' : '/' . ltrim( wp_normalize_path( $relative ), '/' ) );
	}
}
