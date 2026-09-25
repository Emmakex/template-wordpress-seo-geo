<?php
/**
 * Portable Clone reversible file promotion.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge\Clone;

use SeoGeo\MigrationBridge\Sandbox\SandboxGuard;
use wpdb;

/**
 * Builds same-filesystem candidates, promotes them reversibly and verifies active roots.
 */
final class ImportFilePromoter {
	public const MIN_BATCH_FILES     = 1;
	public const MAX_BATCH_FILES     = 500;
	public const DEFAULT_BATCH_FILES = 20;
	public const MIN_BATCH_BYTES     = 1048576;
	public const MAX_BATCH_BYTES     = 134217728;
	public const DEFAULT_BATCH_BYTES = 16777216;

	private const FILE_SEED               = 'seo-geo-import-finalize-files-v1';
	private const MAX_PENDING_DIRECTORIES = 50000;
	private const MAX_ENTRY_OPERATIONS    = 8000;

	/**
	 * Promotion journal.
	 *
	 * @var ImportFilePromotionStateStore
	 */
	private ImportFilePromotionStateStore $store;

	/**
	 * Read-only promotion planner.
	 *
	 * @var ImportFilePromotionPlanner
	 */
	private ImportFilePromotionPlanner $planner;

	/**
	 * Database activation journal.
	 *
	 * @var ImportDatabaseActivationStateStore
	 */
	private ImportDatabaseActivationStateStore $database_activation;

	/**
	 * Clone job store.
	 *
	 * @var CloneJobStore
	 */
	private CloneJobStore $jobs;

	/**
	 * Centralized filesystem mutation boundary.
	 *
	 * @var ExportWorkspace
	 */
	private ExportWorkspace $workspace;

	/**
	 * Construct the reversible file promoter.
	 *
	 * @param ImportFilePromotionStateStore|null      $store               Optional promotion journal.
	 * @param ImportFilePromotionPlanner|null         $planner             Optional promotion planner.
	 * @param ImportDatabaseActivationStateStore|null $database_activation Optional database activation journal.
	 * @param CloneJobStore|null                      $jobs                Optional clone job store.
	 * @param ExportWorkspace|null                    $workspace           Optional filesystem mutation boundary.
	 */
	public function __construct(
		?ImportFilePromotionStateStore $store = null,
		?ImportFilePromotionPlanner $planner = null,
		?ImportDatabaseActivationStateStore $database_activation = null,
		?CloneJobStore $jobs = null,
		?ExportWorkspace $workspace = null
	) {
		$this->store               = $store ?? new ImportFilePromotionStateStore();
		$this->database_activation = $database_activation ?? new ImportDatabaseActivationStateStore();
		$this->planner             = $planner ?? new ImportFilePromotionPlanner( $this->store, $this->database_activation );
		$this->jobs                = $jobs ?? new CloneJobStore();
		$this->workspace           = $workspace ?? new ExportWorkspace();
	}

	/**
	 * Return the external file-promotion journal.
	 *
	 * @param string $job_id Clone job identifier.
	 * @return array<string,mixed>|null
	 */
	public function snapshot( string $job_id ): ?array {
		return $this->store->get( $job_id );
	}

	/**
	 * Freeze the reversible promotion map without touching active roots.
	 *
	 * @param string $job_id Clone job identifier.
	 * @return array<string,mixed>|null
	 */
	public function prepare( string $job_id ): ?array {
		return $this->planner->prepare( $job_id );
	}

	/**
	 * Copy and verify one bounded staging-to-candidate batch.
	 *
	 * @param string $job_id     Clone job identifier.
	 * @param int    $batch_files Maximum files copied this request.
	 * @param int    $batch_bytes Soft maximum bytes copied this request.
	 * @return array<string,mixed>|null
	 */
	public function advance_candidates(
		string $job_id,
		int $batch_files = self::DEFAULT_BATCH_FILES,
		int $batch_bytes = self::DEFAULT_BATCH_BYTES
	): ?array {
		$batch_files = max( self::MIN_BATCH_FILES, min( self::MAX_BATCH_FILES, $batch_files ) );
		$batch_bytes = max( self::MIN_BATCH_BYTES, min( self::MAX_BATCH_BYTES, $batch_bytes ) );
		$state       = $this->store->get( $job_id ) ?? $this->prepare( $job_id );

		if ( ! is_array( $state ) || in_array( $state['status'] ?? null, array( 'candidate-ready', 'promoting', 'verifying', 'verified', 'rolled-back', 'blocked' ), true ) ) {
			return $state;
		}
		if ( ! $this->runtime_ready( $job_id, $state ) ) {
			return $this->block( $job_id, $state, 'file-promotion-runtime-guard-failed' );
		}

		$state['status'] = 'copying';
		$processed_files = 0;
		$processed_bytes = 0;
		$operations      = 0;

		while ( $processed_files < $batch_files && $operations < self::MAX_ENTRY_OPERATIONS ) {
			$root_index = (int) ( $state['root_index'] ?? 0 );
			$roots      = is_array( $state['roots'] ?? null ) ? $state['roots'] : array();
			if ( $root_index >= count( $roots ) ) {
				return $this->complete_candidates( $job_id, $state );
			}

			$root = $roots[ $root_index ];
			if ( ! is_array( $root ) ) {
				return $this->block( $job_id, $state, 'file-promotion-root-plan-invalid' );
			}

			if ( 'pending' === ( $root['status'] ?? null ) ) {
				if ( ! $this->prepare_candidate_directory( $root ) ) {
					return $this->block( $job_id, $state, 'file-promotion-candidate-create-failed' );
				}
				$root['status']                = 'copying';
				$state['roots'][ $root_index ] = $root;
			}

			$pending = is_array( $state['pending_dirs'] ?? null ) ? $state['pending_dirs'] : array();
			if ( '' === (string) ( $state['current_dir'] ?? '' ) && '' === (string) ( $state['after_name'] ?? '' ) && array() === $pending ) {
				if (
					(int) ( $root['copied_files'] ?? 0 ) !== (int) ( $root['file_count'] ?? -1 )
					|| (int) ( $root['copied_bytes'] ?? 0 ) !== (int) ( $root['byte_count'] ?? -1 )
				) {
					return $this->block( $job_id, $state, 'file-promotion-root-reconciliation-failed' );
				}

				$state['roots'][ $root_index ]['status']          = 'candidate-ready';
				$state['roots'][ $root_index ]['candidate_ready'] = true;
				$state = $this->advance_root_cursor( $state );
				continue;
			}

			if ( '' === (string) ( $state['current_dir'] ?? '' ) && '' === (string) ( $state['after_name'] ?? '' ) ) {
				$state['current_dir']  = (string) array_shift( $pending );
				$state['pending_dirs'] = $pending;
			}

			$current_dir = (string) ( $state['current_dir'] ?? '' );
			$source_dir  = $this->join_path( (string) $root['staging_path'], $current_dir );
			$entries     = scandir( $source_dir, SCANDIR_SORT_ASCENDING );
			if ( false === $entries ) {
				return $this->block( $job_id, $state, 'file-promotion-staging-read-failed' );
			}

			$after_name   = (string) ( $state['after_name'] ?? '' );
			$dir_finished = true;
			foreach ( $entries as $entry ) {
				if ( '.' === $entry || '..' === $entry || ( '' !== $after_name && strcmp( $entry, $after_name ) <= 0 ) ) {
					continue;
				}

				++$operations;
				$relative = '' === $current_dir ? $entry : $current_dir . '/' . $entry;
				$source   = $this->join_path( (string) $root['staging_path'], $relative );
				$target   = $this->join_path( (string) $root['candidate_path'], $relative );

				if ( is_link( $source ) ) {
					return $this->block( $job_id, $state, 'file-promotion-staging-symlink' );
				}
				if ( is_dir( $source ) ) {
					$pending = is_array( $state['pending_dirs'] ?? null ) ? $state['pending_dirs'] : array();
					if ( count( $pending ) >= self::MAX_PENDING_DIRECTORIES ) {
						return $this->block( $job_id, $state, 'file-promotion-directory-queue-limit' );
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
				if ( ! is_file( $source ) || ! is_readable( $source ) ) {
					return $this->block( $job_id, $state, 'file-promotion-staging-file-unreadable' );
				}

				$result = $this->copy_verified_file( $source, $target );
				if ( null === $result ) {
					return $this->block( $job_id, $state, 'file-promotion-candidate-copy-failed' );
				}

				$canonical                 = 'file|' . (string) $root['id'] . '|' . wp_normalize_path( $relative ) . '|'
					. (string) $result['bytes'] . '|' . $result['sha256'];
				$state['copy_fingerprint'] = $this->chain_hash( (string) $state['copy_fingerprint'], $canonical );
				++$state['file_count'];
				$state['byte_count'] = (int) $state['byte_count'] + $result['bytes'];
				++$state['roots'][ $root_index ]['copied_files'];
				$state['roots'][ $root_index ]['copied_bytes'] = (int) $state['roots'][ $root_index ]['copied_bytes'] + $result['bytes'];
				$state['after_name']                           = $entry;
				++$processed_files;
				$processed_bytes += $result['bytes'];

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

		return $this->persist( $job_id, $state, 'copy-candidates' );
	}

	/**
	 * Promote every verified candidate root through sibling rollback paths.
	 *
	 * @param string $job_id Clone job identifier.
	 * @return array<string,mixed>|null
	 */
	public function promote( string $job_id ): ?array {
		$state = $this->store->get( $job_id );
		if ( ! is_array( $state ) || ! in_array( $state['status'] ?? null, array( 'candidate-ready', 'promoting' ), true ) ) {
			return $state;
		}
		if ( ! $this->runtime_ready( $job_id, $state ) || ! $this->runtime_assets_ready( $state ) ) {
			return $this->block( $job_id, $state, 'file-promotion-runtime-assets-not-ready' );
		}

		$state['status'] = 'promoting';
		if ( ! $this->store->save( $job_id, $state ) ) {
			return null;
		}

		foreach ( $state['roots'] as $index => $root ) {
			if ( ! is_array( $root ) ) {
				return $this->rollback_internal( $job_id, $state, 'file-promotion-root-plan-invalid' );
			}

			$layout = $this->root_layout( $root );
			if ( 'promoted' === $layout ) {
				$state['roots'][ $index ]['status']         = 'promoted';
				$state['roots'][ $index ]['rollback_ready'] = true === ( $root['active_existed'] ?? false );
				continue;
			}
			if ( 'mid-swap' === $layout ) {
				if ( ! $this->rename_path( (string) $root['candidate_path'], (string) $root['active_path'] ) ) {
					return $this->rollback_internal( $job_id, $state, 'file-promotion-mid-swap-recovery-failed' );
				}
			} elseif ( 'pending' === $layout ) {
				if ( true === ( $root['active_existed'] ?? false ) ) {
					if ( ! $this->rename_path( (string) $root['active_path'], (string) $root['rollback_path'] ) ) {
						return $this->rollback_internal( $job_id, $state, 'file-promotion-active-backup-failed' );
					}
				}
				if ( ! $this->rename_path( (string) $root['candidate_path'], (string) $root['active_path'] ) ) {
					if ( true === ( $root['active_existed'] ?? false ) ) {
						$this->rename_path( (string) $root['rollback_path'], (string) $root['active_path'] );
					}
					return $this->rollback_internal( $job_id, $state, 'file-promotion-candidate-swap-failed' );
				}
			} else {
				return $this->rollback_internal( $job_id, $state, 'file-promotion-layout-ambiguous' );
			}

			$state['roots'][ $index ]['status']         = 'promoted';
			$state['roots'][ $index ]['rollback_ready'] = true === ( $root['active_existed'] ?? false );
			$state['updated_at']                        = gmdate( DATE_ATOM );
			if ( ! $this->store->save( $job_id, $state ) ) {
				return null;
			}
		}

		if ( ! $this->apply_runtime( $state['runtime_target'] ?? array() ) ) {
			return $this->rollback_internal( $job_id, $state, 'file-promotion-source-runtime-activate-failed' );
		}

		$state['status']             = 'verifying';
		$state['root_index']         = 0;
		$state['pending_dirs']       = array( '' );
		$state['current_dir']        = '';
		$state['after_name']         = '';
		$state['verify_file_count']  = 0;
		$state['verify_byte_count']  = 0;
		$state['active_fingerprint'] = hash( 'sha256', self::FILE_SEED );
		$state['promoted_at']        = gmdate( DATE_ATOM );
		$state['updated_at']         = $state['promoted_at'];

		return $this->store->save( $job_id, $state ) ? $this->store->get( $job_id ) : null;
	}

	/**
	 * Verify one bounded active-root batch after promotion.
	 *
	 * @param string $job_id      Clone job identifier.
	 * @param int    $batch_files Maximum files verified this request.
	 * @param int    $batch_bytes Soft maximum bytes verified this request.
	 * @return array<string,mixed>|null
	 */
	public function advance_verification(
		string $job_id,
		int $batch_files = self::DEFAULT_BATCH_FILES,
		int $batch_bytes = self::DEFAULT_BATCH_BYTES
	): ?array {
		$batch_files = max( self::MIN_BATCH_FILES, min( self::MAX_BATCH_FILES, $batch_files ) );
		$batch_bytes = max( self::MIN_BATCH_BYTES, min( self::MAX_BATCH_BYTES, $batch_bytes ) );
		$state       = $this->store->get( $job_id );
		if ( ! is_array( $state ) || 'verifying' !== ( $state['status'] ?? null ) ) {
			return $state;
		}
		if ( ! $this->runtime_ready( $job_id, $state ) ) {
			return $this->rollback_internal( $job_id, $state, 'file-promotion-final-safety-gate-failed' );
		}

		$processed_files = 0;
		$processed_bytes = 0;
		$operations      = 0;

		while ( $processed_files < $batch_files && $operations < self::MAX_ENTRY_OPERATIONS ) {
			$root_index = (int) $state['root_index'];
			if ( $root_index >= count( $state['roots'] ) ) {
				return $this->complete_verification( $job_id, $state );
			}

			$root = $state['roots'][ $root_index ];
			if ( ! is_array( $root ) || 'promoted' !== ( $root['status'] ?? null ) ) {
				return $this->rollback_internal( $job_id, $state, 'file-promotion-verify-root-invalid' );
			}

			$pending = is_array( $state['pending_dirs'] ?? null ) ? $state['pending_dirs'] : array();
			if ( '' === (string) $state['current_dir'] && '' === (string) $state['after_name'] && array() === $pending ) {
				if (
					(int) $root['verified_files'] !== (int) $root['file_count']
					|| (int) $root['verified_bytes'] !== (int) $root['byte_count']
				) {
					return $this->rollback_internal( $job_id, $state, 'file-promotion-active-root-reconciliation-failed' );
				}
				$state['roots'][ $root_index ]['status'] = 'verified';
				$state                                   = $this->advance_root_cursor( $state );
				continue;
			}
			if ( '' === (string) $state['current_dir'] && '' === (string) $state['after_name'] ) {
				$state['current_dir']  = (string) array_shift( $pending );
				$state['pending_dirs'] = $pending;
			}

			$current_dir = (string) $state['current_dir'];
			$active_dir  = $this->join_path( (string) $root['active_path'], $current_dir );
			$entries     = scandir( $active_dir, SCANDIR_SORT_ASCENDING );
			if ( false === $entries ) {
				return $this->rollback_internal( $job_id, $state, 'file-promotion-active-read-failed' );
			}

			$after_name   = (string) $state['after_name'];
			$dir_finished = true;
			foreach ( $entries as $entry ) {
				if ( '.' === $entry || '..' === $entry || ( '' !== $after_name && strcmp( $entry, $after_name ) <= 0 ) ) {
					continue;
				}
				++$operations;
				$relative = '' === $current_dir ? $entry : $current_dir . '/' . $entry;
				$active   = $this->join_path( (string) $root['active_path'], $relative );
				$staging  = $this->join_path( (string) $root['staging_path'], $relative );

				if ( is_link( $active ) || is_link( $staging ) ) {
					return $this->rollback_internal( $job_id, $state, 'file-promotion-active-symlink' );
				}
				if ( is_dir( $active ) ) {
					if ( ! is_dir( $staging ) ) {
						return $this->rollback_internal( $job_id, $state, 'file-promotion-active-extra-directory' );
					}
					$pending = is_array( $state['pending_dirs'] ?? null ) ? $state['pending_dirs'] : array();
					if ( count( $pending ) >= self::MAX_PENDING_DIRECTORIES ) {
						return $this->rollback_internal( $job_id, $state, 'file-promotion-verify-directory-queue-limit' );
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
				if ( ! is_file( $active ) || ! is_file( $staging ) ) {
					return $this->rollback_internal( $job_id, $state, 'file-promotion-active-file-missing' );
				}

				$active_info  = $this->file_info( $active );
				$staging_info = $this->file_info( $staging );
				if (
					null === $active_info
					|| null === $staging_info
					|| $active_info['bytes'] !== $staging_info['bytes']
					|| ! hash_equals( $active_info['sha256'], $staging_info['sha256'] )
				) {
					return $this->rollback_internal( $job_id, $state, 'file-promotion-active-integrity-mismatch' );
				}

				$canonical                   = 'file|' . (string) $root['id'] . '|' . wp_normalize_path( $relative ) . '|'
					. (string) $active_info['bytes'] . '|' . $active_info['sha256'];
				$state['active_fingerprint'] = $this->chain_hash( (string) $state['active_fingerprint'], $canonical );
				++$state['verify_file_count'];
				$state['verify_byte_count'] = (int) $state['verify_byte_count'] + $active_info['bytes'];
				++$state['roots'][ $root_index ]['verified_files'];
				$state['roots'][ $root_index ]['verified_bytes'] = (int) $state['roots'][ $root_index ]['verified_bytes'] + $active_info['bytes'];
				$state['after_name']                             = $entry;
				++$processed_files;
				$processed_bytes += $active_info['bytes'];

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

		return $this->persist( $job_id, $state, 'verify-active-files' );
	}

	/**
	 * Roll promoted roots back to their previous filesystem layout.
	 *
	 * @param string $job_id Clone job identifier.
	 * @return array<string,mixed>|null
	 */
	public function rollback( string $job_id ): ?array {
		$state = $this->store->get( $job_id );
		if ( ! is_array( $state ) || ! in_array( $state['status'] ?? null, array( 'promoting', 'verifying', 'verified' ), true ) ) {
			return $state;
		}

		return $this->rollback_internal( $job_id, $state, 'operator-file-promotion-rollback' );
	}

	/**
	 * Complete candidate build only after replaying the accepted staging fingerprint.
	 *
	 * @param string              $job_id Clone job identifier.
	 * @param array<string,mixed> $state  Promotion state.
	 * @return array<string,mixed>|null
	 */
	private function complete_candidates( string $job_id, array $state ): ?array {
		$expected_files = 0;
		$expected_bytes = 0;
		foreach ( $state['roots'] as $root ) {
			if ( ! is_array( $root ) || 'candidate-ready' !== ( $root['status'] ?? null ) ) {
				return $this->block( $job_id, $state, 'file-promotion-candidate-root-not-ready' );
			}
			$expected_files += (int) $root['file_count'];
			$expected_bytes += (int) $root['byte_count'];
		}

		if (
			(int) $state['file_count'] !== $expected_files
			|| (int) $state['byte_count'] !== $expected_bytes
			|| ! $this->same_hash( $state['copy_fingerprint'] ?? '', $state['file_fingerprint'] ?? '' )
			|| ! $this->runtime_ready( $job_id, $state )
			|| ! $this->runtime_assets_ready( $state )
		) {
			return $this->block( $job_id, $state, 'file-promotion-candidate-reconciliation-failed' );
		}

		$state['status']       = 'candidate-ready';
		$state['root_index']   = 0;
		$state['pending_dirs'] = array( '' );
		$state['current_dir']  = '';
		$state['after_name']   = '';

		return $this->persist( $job_id, $state, 'candidate-ready' );
	}

	/**
	 * Complete final verification and enable handoff.
	 *
	 * @param string              $job_id Clone job identifier.
	 * @param array<string,mixed> $state  Promotion state.
	 * @return array<string,mixed>|null
	 */
	private function complete_verification( string $job_id, array $state ): ?array {
		$expected_files = 0;
		$expected_bytes = 0;
		foreach ( $state['roots'] as $root ) {
			if ( ! is_array( $root ) || 'verified' !== ( $root['status'] ?? null ) ) {
				return $this->rollback_internal( $job_id, $state, 'file-promotion-root-verification-incomplete' );
			}
			$expected_files += (int) $root['file_count'];
			$expected_bytes += (int) $root['byte_count'];
		}

		if (
			(int) $state['verify_file_count'] !== $expected_files
			|| (int) $state['verify_byte_count'] !== $expected_bytes
			|| ! $this->same_hash( $state['active_fingerprint'] ?? '', $state['file_fingerprint'] ?? '' )
			|| ! $this->runtime_ready( $job_id, $state )
			|| ! $this->runtime_matches( $state['runtime_target'] ?? array() )
		) {
			return $this->rollback_internal( $job_id, $state, 'file-promotion-final-integrity-failed' );
		}

		$database = $this->database_activation->get( $job_id );
		if ( ! is_array( $database ) ) {
			return $this->rollback_internal( $job_id, $state, 'file-promotion-database-journal-missing' );
		}

		$now                         = gmdate( DATE_ATOM );
		$state['status']             = 'verified';
		$state['handoff_ready']      = true;
		$state['rollback_available'] = true;
		$state['verified_at']        = $now;
		$state['updated_at']         = $now;

		$database['handoff_ready']          = true;
		$database['active_files_untouched'] = false;
		$database['updated_at']             = $now;
		if ( ! $this->database_activation->save( $job_id, $database ) || ! $this->store->save( $job_id, $state ) ) {
			return null;
		}

		$this->jobs->transition( $job_id, 'complete' );
		$this->jobs->update_progress(
			$job_id,
			'promote-files',
			'verified',
			array(
				'completed' => $expected_files,
				'total'     => $expected_files,
			)
		);

		return $this->store->get( $job_id );
	}

	/**
	 * Reverse every promoted or half-promoted root in reverse order.
	 *
	 * @param string              $job_id Clone job identifier.
	 * @param array<string,mixed> $state  Promotion state.
	 * @param string              $reason Rollback reason.
	 * @return array<string,mixed>|null
	 */
	private function rollback_internal( string $job_id, array $state, string $reason ): ?array {
		$state['status']     = 'rolling-back';
		$state['updated_at'] = gmdate( DATE_ATOM );
		$this->store->save( $job_id, $state );

		for ( $index = count( $state['roots'] ) - 1; $index >= 0; --$index ) {
			$root = $state['roots'][ $index ];
			if ( ! is_array( $root ) ) {
				return $this->block( $job_id, $state, 'file-promotion-rollback-plan-invalid' );
			}

			$layout = $this->root_layout( $root );
			if ( 'promoted' === $layout ) {
				if ( file_exists( untrailingslashit( (string) $root['candidate_path'] ) ) ) {
					return $this->block( $job_id, $state, 'file-promotion-rollback-candidate-collision' );
				}
				if ( ! $this->rename_path( (string) $root['active_path'], (string) $root['candidate_path'] ) ) {
					return $this->block( $job_id, $state, 'file-promotion-rollback-active-save-failed' );
				}
				if (
					true === ( $root['active_existed'] ?? false )
					&& ! $this->rename_path( (string) $root['rollback_path'], (string) $root['active_path'] )
				) {
					$this->rename_path( (string) $root['candidate_path'], (string) $root['active_path'] );
					return $this->block( $job_id, $state, 'file-promotion-rollback-restore-failed' );
				}
			} elseif ( 'mid-swap' === $layout ) {
				if (
					true === ( $root['active_existed'] ?? false )
					&& ! $this->rename_path( (string) $root['rollback_path'], (string) $root['active_path'] )
				) {
					return $this->block( $job_id, $state, 'file-promotion-mid-swap-rollback-failed' );
				}
			} elseif ( 'pending' !== $layout ) {
				return $this->block( $job_id, $state, 'file-promotion-rollback-layout-ambiguous' );
			}

			$state['roots'][ $index ]['status']         = 'rolled-back';
			$state['roots'][ $index ]['rollback_ready'] = false;
		}

		if ( ! $this->apply_runtime( $state['runtime_before'] ?? array() ) ) {
			return $this->block( $job_id, $state, 'file-promotion-rollback-runtime-restore-failed' );
		}

		$now                         = gmdate( DATE_ATOM );
		$state['status']             = 'rolled-back';
		$state['handoff_ready']      = false;
		$state['rollback_available'] = false;
		$state['blockers']           = array_values( array_unique( array_merge( (array) $state['blockers'], array( $reason ) ) ) );
		$state['rolled_back_at']     = $now;
		$state['updated_at']         = $now;

		$database = $this->database_activation->get( $job_id );
		if ( is_array( $database ) ) {
			$database['handoff_ready']          = false;
			$database['active_files_untouched'] = true;
			$database['updated_at']             = $now;
			$this->database_activation->save( $job_id, $database );
		}

		$this->store->save( $job_id, $state );
		$this->jobs->transition( $job_id, 'failed-retryable', $reason );

		return $this->store->get( $job_id );
	}

	/**
	 * Revalidate database activation and sandbox safety.
	 *
	 * @param string              $job_id Clone job identifier.
	 * @param array<string,mixed> $state  Promotion state.
	 */
	private function runtime_ready( string $job_id, array $state ): bool {
		$database = $this->database_activation->get( $job_id );
		if (
			! is_array( $database )
			|| 'activated' !== ( $database['status'] ?? null )
			|| true !== ( $database['database_swapped'] ?? false )
			|| true !== ( $database['rollback_available'] ?? false )
			|| array() !== ( $database['blockers'] ?? array() )
			|| ! $this->same_hash( $database['activation_plan_hash'] ?? '', $state['activation_plan_hash'] ?? '' )
		) {
			return false;
		}

		$authorized = defined( ImportPreflight::TARGET_AUTHORIZED_MARKER )
			&& true === constant( ImportPreflight::TARGET_AUTHORIZED_MARKER );

		return SandboxGuard::enabled()
			&& 'invalid' !== SandboxGuard::mode()
			&& SandboxGuard::outbound_safe()
			&& SandboxGuard::backups_ready()
			&& $authorized
			&& ( 'subdirectory' !== SandboxGuard::mode() || SandboxGuard::storage_isolated() )
			&& '0' === (string) get_option( 'blog_public', '1' )
			&& $this->options_runtime_schema_ready();
	}

	/**
	 * Require the minimum WordPress options schema needed by update_option().
	 *
	 * Database activation is intentionally source-faithful, but final file promotion
	 * must not enable handoff if the activated options table cannot support the
	 * WordPress runtime that will execute on the next request.
	 */
	private function options_runtime_schema_ready(): bool {
		global $wpdb;
		if ( ! $wpdb instanceof wpdb ) {
			return false;
		}

		$table = str_replace( '`', '``', $wpdb->options );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared -- Read-only schema verification of the trusted active WordPress options table.
		$columns = $wpdb->get_col( "SHOW COLUMNS FROM `{$table}`", 0 );

		return array() === array_diff(
			array( 'option_id', 'option_name', 'option_value', 'autoload' ),
			array_filter( $columns, 'is_string' )
		);
	}

	/**
	 * Ensure the currently active plugin/theme runtime exists in candidates.
	 *
	 * @param array<string,mixed> $state Promotion state.
	 */
	private function runtime_assets_ready( array $state ): bool {
		$roots   = array();
		$runtime = is_array( $state['runtime_target'] ?? null ) ? $state['runtime_target'] : array();
		foreach ( $state['roots'] as $root ) {
			if ( is_array( $root ) && is_string( $root['id'] ?? null ) ) {
				$roots[ $root['id'] ] = $root;
			}
		}
		if ( ! isset( $roots['plugins'], $roots['themes'] ) ) {
			return false;
		}

		$plugins = is_array( $runtime['active_plugins'] ?? null ) ? $runtime['active_plugins'] : array();
		foreach ( $plugins as $plugin ) {
			if ( ! is_string( $plugin ) || '' === $plugin || str_contains( $plugin, '../' ) ) {
				return false;
			}
			$path = $this->join_path( (string) $roots['plugins']['candidate_path'], $plugin );
			if ( ! is_file( $path ) || is_link( $path ) ) {
				return false;
			}
		}

		foreach ( array( 'template', 'stylesheet' ) as $option ) {
			$theme = is_string( $runtime[ $option ] ?? null ) ? $runtime[ $option ] : '';
			if ( '' === $theme || str_contains( $theme, '../' ) || str_contains( $theme, '/' ) ) {
				return false;
			}
			$path = $this->join_path( (string) $roots['themes']['candidate_path'], $theme );
			if ( ! is_dir( $path ) || is_link( $path ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Apply one bounded WordPress plugin/theme runtime after a root swap.
	 *
	 * @param mixed $runtime Runtime snapshot.
	 */
	private function apply_runtime( mixed $runtime ): bool {
		if ( ! is_array( $runtime ) ) {
			return false;
		}

		$plugins    = is_array( $runtime['active_plugins'] ?? null ) ? array_values( $runtime['active_plugins'] ) : array();
		$template   = is_string( $runtime['template'] ?? null ) ? $runtime['template'] : '';
		$stylesheet = is_string( $runtime['stylesheet'] ?? null ) ? $runtime['stylesheet'] : '';
		if ( '' === $template || '' === $stylesheet ) {
			return false;
		}

		update_option( 'active_plugins', $plugins, false );
		update_option( 'template', $template, false );
		update_option( 'stylesheet', $stylesheet, false );
		wp_cache_flush();

		return $this->runtime_matches( $runtime );
	}

	/**
	 * Verify the active WordPress plugin/theme runtime exactly.
	 *
	 * @param mixed $runtime Expected runtime snapshot.
	 */
	private function runtime_matches( mixed $runtime ): bool {
		if ( ! is_array( $runtime ) ) {
			return false;
		}

		$expected_plugins = is_array( $runtime['active_plugins'] ?? null ) ? array_values( $runtime['active_plugins'] ) : array();
		$active_plugins   = get_option( 'active_plugins', array() );

		return is_array( $active_plugins )
			&& array_values( $active_plugins ) === $expected_plugins
			&& (string) ( $runtime['template'] ?? '' ) === (string) get_option( 'template', '' )
			&& (string) ( $runtime['stylesheet'] ?? '' ) === (string) get_option( 'stylesheet', '' );
	}

	/**
	 * Prepare one empty same-filesystem candidate root.
	 *
	 * @param array<string,mixed> $root Root plan.
	 */
	private function prepare_candidate_directory( array $root ): bool {
		$candidate = untrailingslashit( (string) ( $root['candidate_path'] ?? '' ) );

		return '' !== $candidate && $this->workspace->prepare_file_promotion_candidate( $candidate );
	}

	/**
	 * Copy one file and require source/target size + SHA-256 parity.
	 *
	 * @param string $source Source file.
	 * @param string $target Candidate file.
	 * @return array{bytes:int,sha256:string}|null
	 */
	private function copy_verified_file( string $source, string $target ): ?array {
		$source_info = $this->file_info( $source );
		$copied      = $this->workspace->copy_file_to_promotion_candidate( $source, $target );
		if (
			null === $source_info
			|| null === $copied
			|| $source_info['bytes'] !== $copied['bytes']
			|| ! hash_equals( $source_info['sha256'], $copied['sha256'] )
		) {
			return null;
		}

		return $copied;
	}

	/**
	 * Return one regular file identity.
	 *
	 * @param string $path File path.
	 * @return array{bytes:int,sha256:string}|null
	 */
	private function file_info( string $path ): ?array {
		if ( ! is_file( $path ) || ! is_readable( $path ) || is_link( $path ) ) {
			return null;
		}

		$bytes = filesize( $path );
		$hash  = hash_file( 'sha256', $path );
		if ( false === $bytes || false === $hash ) {
			return null;
		}

		return array(
			'bytes'  => (int) $bytes,
			'sha256' => $hash,
		);
	}

	/**
	 * Resolve one root filesystem layout.
	 *
	 * @param array<string,mixed> $root Root plan.
	 */
	private function root_layout( array $root ): string {
		$active     = file_exists( untrailingslashit( (string) $root['active_path'] ) );
		$candidate  = file_exists( untrailingslashit( (string) $root['candidate_path'] ) );
		$rollback   = file_exists( untrailingslashit( (string) $root['rollback_path'] ) );
		$had_active = true === ( $root['active_existed'] ?? false );

		if ( $candidate && $active === $had_active && ! $rollback ) {
			return 'pending';
		}
		if ( $had_active && $candidate && ! $active && $rollback ) {
			return 'mid-swap';
		}
		if ( ! $candidate && $active && $rollback === $had_active ) {
			return 'promoted';
		}

		return 'ambiguous';
	}

	/**
	 * Rename one directory path after normalizing trailing separators.
	 *
	 * @param string $from Source path.
	 * @param string $to   Destination path.
	 */
	private function rename_path( string $from, string $to ): bool {
		return $this->workspace->rename_file_promotion_path( $from, $to );
	}

	/**
	 * Advance traversal to the next planned root.
	 *
	 * @param array<string,mixed> $state Promotion state.
	 * @return array<string,mixed>
	 */
	private function advance_root_cursor( array $state ): array {
		$state['root_index']   = (int) $state['root_index'] + 1;
		$state['pending_dirs'] = array( '' );
		$state['current_dir']  = '';
		$state['after_name']   = '';

		return $state;
	}

	/**
	 * Persist bounded progress.
	 *
	 * @param string              $job_id Clone job identifier.
	 * @param array<string,mixed> $state  Promotion state.
	 * @param string              $cursor Progress cursor.
	 * @return array<string,mixed>|null
	 */
	private function persist( string $job_id, array $state, string $cursor ): ?array {
		$state['updated_at'] = gmdate( DATE_ATOM );
		if ( ! $this->store->save( $job_id, $state ) ) {
			return null;
		}

		$this->jobs->transition( $job_id, 'active' );
		$this->jobs->update_progress(
			$job_id,
			'promote-files',
			$cursor . ':' . (string) $state['root_index'] . ':' . (string) $state['current_dir'] . ':' . (string) $state['after_name'],
			array(
				'completed' => 'verifying' === ( $state['status'] ?? null )
					? (int) $state['verify_file_count']
					: (int) $state['file_count'],
				'total'     => array_sum( array_map( static fn ( array $root ): int => (int) $root['file_count'], $state['roots'] ) ),
			)
		);

		return $this->store->get( $job_id );
	}

	/**
	 * Persist a terminal promotion blocker.
	 *
	 * @param string              $job_id Clone job identifier.
	 * @param array<string,mixed> $state  Promotion state.
	 * @param string              $code   Blocker code.
	 * @return array<string,mixed>|null
	 */
	private function block( string $job_id, array $state, string $code ): ?array {
		$blockers               = is_array( $state['blockers'] ?? null ) ? $state['blockers'] : array();
		$blockers[]             = $code;
		$state['status']        = 'blocked';
		$state['handoff_ready'] = false;
		$state['blockers']      = array_values( array_unique( $blockers ) );
		$state['updated_at']    = gmdate( DATE_ATOM );

		$this->store->save( $job_id, $state );
		$this->jobs->transition( $job_id, 'failed-terminal', $code );

		return $this->store->get( $job_id );
	}

	/**
	 * Chain one canonical file record.
	 *
	 * @param string $previous Previous chain hash.
	 * @param string $record   Canonical record.
	 */
	private function chain_hash( string $previous, string $record ): string {
		return hash( 'sha256', $previous . "\n" . $record );
	}

	/**
	 * Constant-time compare two SHA-256 values.
	 *
	 * @param mixed $left  First candidate hash.
	 * @param mixed $right Second candidate hash.
	 */
	private function same_hash( mixed $left, mixed $right ): bool {
		return is_string( $left )
			&& is_string( $right )
			&& 1 === preg_match( '/^[a-f0-9]{64}$/', $left )
			&& 1 === preg_match( '/^[a-f0-9]{64}$/', $right )
			&& hash_equals( $left, $right );
	}

	/**
	 * Join normalized paths.
	 *
	 * @param string $base     Base path.
	 * @param string $relative Relative path.
	 */
	private function join_path( string $base, string $relative ): string {
		return rtrim( wp_normalize_path( $base ), '/' )
			. ( '' === $relative ? '' : '/' . ltrim( wp_normalize_path( $relative ), '/' ) );
	}
}
