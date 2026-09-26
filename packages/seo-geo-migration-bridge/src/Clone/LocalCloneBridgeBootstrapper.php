<?php
/**
 * Portable Clone Migration Bridge control-runtime bootstrap.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge\Clone;

/**
 * Copies and independently verifies only the Migration Bridge control plugin.
 */
final class LocalCloneBridgeBootstrapper {
	public const MIN_BATCH_FILES     = 1;
	public const MAX_BATCH_FILES     = 200;
	public const DEFAULT_BATCH_FILES = 25;
	public const MIN_BATCH_BYTES     = 1048576;
	public const MAX_BATCH_BYTES     = 33554432;
	public const DEFAULT_BATCH_BYTES = 4194304;

	private const MAX_PENDING_DIRECTORIES = 10000;
	private const MAX_ENTRY_OPERATIONS    = 2000;
	private const FINGERPRINT_SEED        = 'seo-geo-local-clone-bridge-runtime-v1';
	private const TARGET_PLUGIN_RELATIVE  = 'wp-content/plugins/seo-geo-migration-bridge';
	private const MAIN_FILE               = 'seo-geo-migration-bridge.php';

	/**
	 * Bridge runtime state persistence.
	 *
	 * @var LocalCloneBridgeStateStore
	 */
	private LocalCloneBridgeStateStore $store;

	/**
	 * Verified target ownership service.
	 *
	 * @var LocalCloneBootstrapper
	 */
	private LocalCloneBootstrapper $ownership;

	/**
	 * Accepted WordPress core runtime state.
	 *
	 * @var LocalCloneRuntimeStateStore
	 */
	private LocalCloneRuntimeStateStore $runtime;

	/**
	 * Portable Clone job state.
	 *
	 * @var CloneJobStore
	 */
	private CloneJobStore $jobs;

	/**
	 * Centralized Portable Clone filesystem mutation authority.
	 *
	 * @var ExportWorkspace
	 */
	private ExportWorkspace $workspace;

	/**
	 * Construct the Bridge runtime bootstrapper.
	 *
	 * @param LocalCloneBridgeStateStore|null  $store     Optional Bridge state store.
	 * @param LocalCloneBootstrapper|null      $ownership Optional target ownership service.
	 * @param LocalCloneRuntimeStateStore|null $runtime   Optional accepted core runtime state.
	 * @param CloneJobStore|null               $jobs      Optional clone job store.
	 * @param ExportWorkspace|null             $workspace Optional filesystem mutation authority.
	 */
	public function __construct(
		?LocalCloneBridgeStateStore $store = null,
		?LocalCloneBootstrapper $ownership = null,
		?LocalCloneRuntimeStateStore $runtime = null,
		?CloneJobStore $jobs = null,
		?ExportWorkspace $workspace = null
	) {
		$this->store     = $store ?? new LocalCloneBridgeStateStore();
		$this->ownership = $ownership ?? new LocalCloneBootstrapper();
		$this->runtime   = $runtime ?? new LocalCloneRuntimeStateStore();
		$this->jobs      = $jobs ?? new CloneJobStore();
		$this->workspace = $workspace ?? new ExportWorkspace();
	}

	/**
	 * Return one Bridge runtime state.
	 *
	 * @param string $job_id Clone job identifier.
	 * @return array<string,mixed>|null
	 */
	public function snapshot( string $job_id ): ?array {
		return $this->store->get( $job_id );
	}

	/**
	 * Start the bounded Bridge control-runtime copy.
	 *
	 * @param string $job_id Clone job identifier.
	 * @return array<string,mixed>|null
	 */
	public function start( string $job_id ): ?array {
		$existing = $this->store->get( $job_id );
		if ( is_array( $existing ) ) {
			return $existing;
		}

		$authority = $this->authority( $job_id );
		if ( null === $authority ) {
			return null;
		}

		$ownership = $authority['ownership'];
		$source    = untrailingslashit( wp_normalize_path( dirname( __DIR__, 2 ) ) );
		$target    = untrailingslashit( wp_normalize_path( (string) $ownership['target_path'] ) );
		$main      = $this->join_path( $source, self::MAIN_FILE );
		if (
			'' === $source
			|| '' === $target
			|| ! is_dir( $source )
			|| ! is_readable( $source )
			|| is_link( $source )
			|| ! is_file( $main )
			|| ! is_readable( $main )
			|| is_link( $main )
			|| $this->target_plugin_overlaps_source( $source, $target )
		) {
			return null;
		}

		$main_hash = hash_file( 'sha256', $main );
		if ( false === $main_hash ) {
			return null;
		}

		foreach ( array( 'wp-content', 'wp-content/plugins', self::TARGET_PLUGIN_RELATIVE ) as $relative ) {
			if ( ! $this->workspace->ensure_local_clone_directory( $target, $relative ) ) {
				return null;
			}
		}

		$now   = gmdate( DATE_ATOM );
		$seed  = hash( 'sha256', self::FINGERPRINT_SEED );
		$state = array(
			'schema_version'           => LocalCloneBridgeStateStore::SCHEMA_VERSION,
			'job_id'                   => $job_id,
			'status'                   => 'running',
			'stage'                    => 'bridge-copy',
			'plan_hash'                => (string) $ownership['plan_hash'],
			'ownership_marker_sha256'  => (string) $ownership['marker_sha256'],
			'target_path'              => $target,
			'source_root'              => $source,
			'source_main_sha256'       => $main_hash,
			'pending_dirs'             => array( '' ),
			'current_dir'              => '',
			'after_name'               => '',
			'copy_file_count'          => 0,
			'copy_byte_count'          => 0,
			'copy_fingerprint'         => $seed,
			'verify_file_count'        => 0,
			'verify_byte_count'        => 0,
			'verify_fingerprint'       => $seed,
			'client_content_untouched' => true,
			'database_untouched'       => true,
			'bridge_runtime_ready'     => false,
			'bootstrap_next'           => 'bridge-copy',
			'blockers'                 => array(),
			'started_at'               => $now,
			'updated_at'               => $now,
			'completed_at'             => '',
		);

		if ( ! $this->store->save( $job_id, $state ) ) {
			return null;
		}

		$this->jobs->transition( $job_id, 'active', null );
		$this->jobs->update_progress(
			$job_id,
			'bootstrap-bridge',
			'bridge-copy',
			array(
				'completed' => 0,
				'total'     => 0,
			)
		);

		return $this->store->get( $job_id );
	}

	/**
	 * Advance one bounded Bridge copy or verification batch.
	 *
	 * @param string $job_id      Clone job identifier.
	 * @param int    $batch_files Maximum files processed this request.
	 * @param int    $batch_bytes Soft byte budget; one larger file may complete.
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
		if ( ! $this->state_authorized( $job_id, $state ) ) {
			return $this->block( $job_id, $state, 'bridge-authority-drift' );
		}
		if ( ! $this->source_main_matches( $state ) ) {
			return $this->block( $job_id, $state, 'bridge-source-main-changed' );
		}

		return 'bridge-verify' === ( $state['stage'] ?? null )
			? $this->advance_verify( $job_id, $state, $batch_files, $batch_bytes )
			: $this->advance_bridge_files( $job_id, $state, $batch_files, $batch_bytes );
	}

	/**
	 * Copy one bounded Bridge source batch.
	 *
	 * @param string              $job_id      Clone job identifier.
	 * @param array<string,mixed> $state       Bridge state.
	 * @param int                 $batch_files Maximum files.
	 * @param int                 $batch_bytes Maximum bytes.
	 * @return array<string,mixed>|null
	 */
	private function advance_bridge_files( string $job_id, array $state, int $batch_files, int $batch_bytes ): ?array {
		if ( 'bridge-copy' !== ( $state['stage'] ?? null ) ) {
			return $this->block( $job_id, $state, 'bridge-copy-stage-invalid' );
		}

		$source         = (string) $state['source_root'];
		$target         = (string) $state['target_path'];
		$accepted_files = 0;
		$accepted_bytes = 0;
		$operations     = 0;

		while ( $accepted_files < $batch_files && $operations < self::MAX_ENTRY_OPERATIONS ) {
			$pending = is_array( $state['pending_dirs'] ?? null ) ? $state['pending_dirs'] : array();
			if ( '' === (string) ( $state['current_dir'] ?? '' ) && '' === (string) ( $state['after_name'] ?? '' ) ) {
				if ( array() === $pending ) {
					return $this->begin_verify( $job_id, $state );
				}

				$state['current_dir']  = (string) array_shift( $pending );
				$state['pending_dirs'] = $pending;
			}

			$current_dir = (string) $state['current_dir'];
			$source_dir  = '' === $current_dir ? $source : $this->join_path( $source, $current_dir );
			$entries     = $this->entries( $source_dir );
			if ( null === $entries ) {
				return $this->block( $job_id, $state, 'bridge-source-directory-read-failed' );
			}

			$after_name   = (string) $state['after_name'];
			$dir_finished = true;

			foreach ( $entries as $entry ) {
				if ( '' !== $after_name && strcmp( $entry, $after_name ) <= 0 ) {
					continue;
				}

				++$operations;
				$relative = '' === $current_dir ? $entry : $current_dir . '/' . $entry;
				$path     = $this->join_path( $source, $relative );

				if ( is_link( $path ) ) {
					return $this->block( $job_id, $state, 'bridge-source-symlink' );
				}

				if ( is_dir( $path ) ) {
					$target_relative = self::TARGET_PLUGIN_RELATIVE . '/' . $relative;
					if ( ! $this->workspace->ensure_local_clone_directory( $target, $target_relative ) ) {
						return $this->block( $job_id, $state, 'bridge-target-directory-create-failed' );
					}

					$pending = is_array( $state['pending_dirs'] ?? null ) ? $state['pending_dirs'] : array();
					if ( count( $pending ) >= self::MAX_PENDING_DIRECTORIES ) {
						return $this->block( $job_id, $state, 'bridge-directory-queue-limit' );
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
					return $this->block( $job_id, $state, 'bridge-source-file-unreadable' );
				}

				$target_relative = self::TARGET_PLUGIN_RELATIVE . '/' . $relative;
				$copied          = $this->workspace->copy_file_to_local_clone_target( $path, $target, $target_relative );
				if ( ! is_array( $copied ) ) {
					return $this->block( $job_id, $state, 'bridge-file-copy-failed' );
				}

				$bytes = (int) $copied['bytes'];
				$hash  = (string) $copied['sha256'];

				$state['copy_file_count']  = (int) $state['copy_file_count'] + 1;
				$state['copy_byte_count']  = (int) $state['copy_byte_count'] + $bytes;
				$state['copy_fingerprint'] = $this->fingerprint_add(
					(string) $state['copy_fingerprint'],
					$relative,
					$bytes,
					$hash
				);
				$state['after_name'] = $entry;
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

			if ( $accepted_files >= $batch_files || $accepted_bytes >= $batch_bytes || $operations >= self::MAX_ENTRY_OPERATIONS ) {
				break;
			}
		}

		$state['updated_at']     = gmdate( DATE_ATOM );
		$state['bootstrap_next'] = 'bridge-copy';
		if ( ! $this->store->save( $job_id, $state ) ) {
			return null;
		}

		$this->jobs->update_progress(
			$job_id,
			'bootstrap-bridge',
			'bridge-copy',
			array(
				'completed' => (int) $state['copy_file_count'],
				'total'     => 0,
			)
		);

		return $this->store->get( $job_id );
	}

	/**
	 * Begin the independent Bridge verification pass.
	 *
	 * @param string              $job_id Clone job identifier.
	 * @param array<string,mixed> $state  Bridge state.
	 * @return array<string,mixed>|null
	 */
	private function begin_verify( string $job_id, array $state ): ?array {
		$state['stage']              = 'bridge-verify';
		$state['pending_dirs']       = array( '' );
		$state['current_dir']        = '';
		$state['after_name']         = '';
		$state['verify_file_count']  = 0;
		$state['verify_byte_count']  = 0;
		$state['verify_fingerprint'] = hash( 'sha256', self::FINGERPRINT_SEED );
		$state['bootstrap_next']     = 'bridge-verify';
		$state['updated_at']         = gmdate( DATE_ATOM );

		if ( ! $this->store->save( $job_id, $state ) ) {
			return null;
		}

		$this->jobs->update_progress(
			$job_id,
			'bootstrap-bridge',
			'bridge-verify',
			array(
				'completed' => 0,
				'total'     => (int) $state['copy_file_count'],
			)
		);

		return $this->store->get( $job_id );
	}

	/**
	 * Verify one bounded Bridge batch against the current source tree.
	 *
	 * @param string              $job_id      Clone job identifier.
	 * @param array<string,mixed> $state       Bridge state.
	 * @param int                 $batch_files Maximum files.
	 * @param int                 $batch_bytes Maximum bytes.
	 * @return array<string,mixed>|null
	 */
	private function advance_verify( string $job_id, array $state, int $batch_files, int $batch_bytes ): ?array {
		$source         = (string) $state['source_root'];
		$target_root    = $this->join_path( (string) $state['target_path'], self::TARGET_PLUGIN_RELATIVE );
		$accepted_files = 0;
		$accepted_bytes = 0;
		$operations     = 0;

		while ( $accepted_files < $batch_files && $operations < self::MAX_ENTRY_OPERATIONS ) {
			$pending = is_array( $state['pending_dirs'] ?? null ) ? $state['pending_dirs'] : array();
			if ( '' === (string) ( $state['current_dir'] ?? '' ) && '' === (string) ( $state['after_name'] ?? '' ) ) {
				if ( array() === $pending ) {
					return $this->complete_verify( $job_id, $state );
				}

				$state['current_dir']  = (string) array_shift( $pending );
				$state['pending_dirs'] = $pending;
			}

			$current_dir = (string) $state['current_dir'];
			$source_dir  = '' === $current_dir ? $source : $this->join_path( $source, $current_dir );
			$target_dir  = '' === $current_dir ? $target_root : $this->join_path( $target_root, $current_dir );
			$source_list = $this->entries( $source_dir );
			$target_list = $this->entries( $target_dir );
			if ( null === $source_list || null === $target_list ) {
				return $this->block( $job_id, $state, 'bridge-verify-directory-read-failed' );
			}
			if ( $source_list !== $target_list ) {
				return $this->block( $job_id, $state, 'bridge-target-entry-drift' );
			}

			$after_name   = (string) $state['after_name'];
			$dir_finished = true;

			foreach ( $source_list as $entry ) {
				if ( '' !== $after_name && strcmp( $entry, $after_name ) <= 0 ) {
					continue;
				}

				++$operations;
				$relative    = '' === $current_dir ? $entry : $current_dir . '/' . $entry;
				$source_path = $this->join_path( $source, $relative );
				$target_path = $this->join_path( $target_root, $relative );

				if ( is_link( $source_path ) || is_link( $target_path ) ) {
					return $this->block( $job_id, $state, 'bridge-verify-symlink' );
				}

				if ( is_dir( $source_path ) ) {
					if ( ! is_dir( $target_path ) ) {
						return $this->block( $job_id, $state, 'bridge-target-directory-missing' );
					}

					$pending = is_array( $state['pending_dirs'] ?? null ) ? $state['pending_dirs'] : array();
					if ( count( $pending ) >= self::MAX_PENDING_DIRECTORIES ) {
						return $this->block( $job_id, $state, 'bridge-verify-directory-queue-limit' );
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

				if (
					! is_file( $source_path )
					|| ! is_readable( $source_path )
					|| ! is_file( $target_path )
					|| ! is_readable( $target_path )
				) {
					return $this->block( $job_id, $state, 'bridge-verify-file-missing' );
				}

				$source_bytes = filesize( $source_path );
				$target_bytes = filesize( $target_path );
				$source_hash  = hash_file( 'sha256', $source_path );
				$target_hash  = hash_file( 'sha256', $target_path );
				if (
					false === $source_bytes
					|| false === $target_bytes
					|| false === $source_hash
					|| false === $target_hash
					|| (int) $source_bytes !== (int) $target_bytes
					|| ! hash_equals( $source_hash, $target_hash )
				) {
					return $this->block( $job_id, $state, 'bridge-integrity-mismatch' );
				}

				$bytes = (int) $target_bytes;

				$state['verify_file_count']  = (int) $state['verify_file_count'] + 1;
				$state['verify_byte_count']  = (int) $state['verify_byte_count'] + $bytes;
				$state['verify_fingerprint'] = $this->fingerprint_add(
					(string) $state['verify_fingerprint'],
					$relative,
					$bytes,
					$target_hash
				);

				$state['after_name'] = $entry;
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

			if ( $accepted_files >= $batch_files || $accepted_bytes >= $batch_bytes || $operations >= self::MAX_ENTRY_OPERATIONS ) {
				break;
			}
		}

		$state['updated_at']     = gmdate( DATE_ATOM );
		$state['bootstrap_next'] = 'bridge-verify';
		if ( ! $this->store->save( $job_id, $state ) ) {
			return null;
		}

		$this->jobs->update_progress(
			$job_id,
			'bootstrap-bridge',
			'bridge-verify',
			array(
				'completed' => (int) $state['verify_file_count'],
				'total'     => (int) $state['copy_file_count'],
			)
		);

		return $this->store->get( $job_id );
	}

	/**
	 * Complete only after exact second-pass reconciliation.
	 *
	 * @param string              $job_id Clone job identifier.
	 * @param array<string,mixed> $state  Bridge state.
	 * @return array<string,mixed>|null
	 */
	private function complete_verify( string $job_id, array $state ): ?array {
		if (
			(int) $state['copy_file_count'] !== (int) $state['verify_file_count']
			|| (int) $state['copy_byte_count'] !== (int) $state['verify_byte_count']
			|| ! hash_equals( (string) $state['copy_fingerprint'], (string) $state['verify_fingerprint'] )
			|| ! $this->source_main_matches( $state )
		) {
			return $this->block( $job_id, $state, 'bridge-final-reconciliation-failed' );
		}

		$state['status']               = 'complete';
		$state['stage']                = 'bridge-complete';
		$state['pending_dirs']         = array();
		$state['current_dir']          = '';
		$state['after_name']           = '';
		$state['bridge_runtime_ready'] = true;
		$state['bootstrap_next']       = 'isolated-config-hardening';
		$state['completed_at']         = gmdate( DATE_ATOM );
		$state['updated_at']           = $state['completed_at'];
		$state['blockers']             = array();

		if ( ! $this->store->save( $job_id, $state ) ) {
			return null;
		}

		$this->jobs->update_progress(
			$job_id,
			'bootstrap-bridge',
			'bridge-complete',
			array(
				'completed' => (int) $state['verify_file_count'],
				'total'     => (int) $state['copy_file_count'],
			)
		);

		return $this->store->get( $job_id );
	}

	/**
	 * Resolve accepted ownership + core runtime authority.
	 *
	 * @param string $job_id Clone job identifier.
	 * @return array{ownership:array<string,mixed>,runtime:array<string,mixed>}|null
	 */
	private function authority( string $job_id ): ?array {
		$job       = $this->jobs->get( $job_id );
		$ownership = $this->ownership->verified_snapshot( $job_id );
		$runtime   = $this->runtime->get( $job_id );

		if (
			! is_array( $job )
			|| 'local-clone' !== ( $job['operation'] ?? null )
			|| ! is_array( $ownership )
			|| ! is_array( $runtime )
			|| 'complete' !== ( $runtime['status'] ?? null )
			|| 'core-complete' !== ( $runtime['stage'] ?? null )
			|| true !== ( $runtime['runtime_core_ready'] ?? false )
		) {
			return null;
		}

		$ownership_target = untrailingslashit( wp_normalize_path( (string) $ownership['target_path'] ) );
		$runtime_target   = untrailingslashit( wp_normalize_path( (string) $runtime['target_path'] ) );
		if (
			! hash_equals( (string) $ownership['plan_hash'], (string) ( $runtime['plan_hash'] ?? '' ) )
			|| ! hash_equals( (string) $ownership['marker_sha256'], (string) ( $runtime['ownership_marker_sha256'] ?? '' ) )
			|| ! hash_equals( $ownership_target, $runtime_target )
		) {
			return null;
		}

		return array(
			'ownership' => $ownership,
			'runtime'   => $runtime,
		);
	}

	/**
	 * Confirm one stored Bridge state still has the same authority.
	 *
	 * @param string              $job_id Clone job identifier.
	 * @param array<string,mixed> $state  Bridge state.
	 */
	private function state_authorized( string $job_id, array $state ): bool {
		$authority = $this->authority( $job_id );
		if ( null === $authority ) {
			return false;
		}

		$ownership = $authority['ownership'];
		$target    = untrailingslashit( wp_normalize_path( (string) $state['target_path'] ) );
		$owned     = untrailingslashit( wp_normalize_path( (string) $ownership['target_path'] ) );

		return hash_equals( (string) $state['plan_hash'], (string) $ownership['plan_hash'] )
			&& hash_equals( (string) $state['ownership_marker_sha256'], (string) $ownership['marker_sha256'] )
			&& hash_equals( $target, $owned );
	}

	/**
	 * Verify the currently running Bridge main-file identity.
	 *
	 * @param array<string,mixed> $state Bridge state.
	 */
	private function source_main_matches( array $state ): bool {
		$main = $this->join_path( (string) $state['source_root'], self::MAIN_FILE );
		$hash = is_file( $main ) && is_readable( $main ) && ! is_link( $main )
			? hash_file( 'sha256', $main )
			: false;

		return false !== $hash && hash_equals( (string) $state['source_main_sha256'], $hash );
	}

	/**
	 * Return sorted safe directory entries.
	 *
	 * @param string $directory Absolute directory.
	 * @return list<string>|null
	 */
	private function entries( string $directory ): ?array {
		if ( ! is_dir( $directory ) || ! is_readable( $directory ) || is_link( $directory ) ) {
			return null;
		}

		$entries = scandir( $directory, SCANDIR_SORT_ASCENDING );
		if ( false === $entries ) {
			return null;
		}

		return array_values(
			array_filter(
				$entries,
				static fn ( string $entry ): bool => ! in_array( $entry, array( '.', '..' ), true )
			)
		);
	}

	/**
	 * Reject a target control-plugin path that overlaps the running Bridge source.
	 *
	 * @param string $source Bridge source root.
	 * @param string $target Clone target root.
	 */
	private function target_plugin_overlaps_source( string $source, string $target ): bool {
		$source = trailingslashit( untrailingslashit( wp_normalize_path( $source ) ) );
		$target = trailingslashit(
			untrailingslashit(
				wp_normalize_path( $this->join_path( $target, self::TARGET_PLUGIN_RELATIVE ) )
			)
		);

		return str_starts_with( $target, $source ) || str_starts_with( $source, $target );
	}

	/**
	 * Add one file record to the deterministic Bridge fingerprint.
	 *
	 * @param string $fingerprint Current fingerprint.
	 * @param string $relative    Relative path.
	 * @param int    $bytes       File bytes.
	 * @param string $sha256      File SHA-256.
	 */
	private function fingerprint_add( string $fingerprint, string $relative, int $bytes, string $sha256 ): string {
		return hash(
			'sha256',
			$fingerprint . "\0" . wp_normalize_path( $relative ) . "\0" . (string) $bytes . "\0" . $sha256
		);
	}

	/**
	 * Persist one blocker without further target mutation.
	 *
	 * @param string              $job_id Clone job identifier.
	 * @param array<string,mixed> $state  Bridge state.
	 * @param string              $code   Stable blocker code.
	 * @return array<string,mixed>|null
	 */
	private function block( string $job_id, array $state, string $code ): ?array {
		$blockers   = is_array( $state['blockers'] ?? null ) ? $state['blockers'] : array();
		$blockers[] = $code;

		$state['status']     = 'blocked';
		$state['blockers']   = array_values( array_unique( array_filter( $blockers, 'is_string' ) ) );
		$state['updated_at'] = gmdate( DATE_ATOM );
		if ( ! $this->store->save( $job_id, $state ) ) {
			return null;
		}

		$this->jobs->transition( $job_id, 'failed-retryable', $code );
		$this->jobs->update_progress(
			$job_id,
			'bootstrap-bridge',
			'blocked',
			array(
				'completed' => (int) ( $state['verify_file_count'] ?? $state['copy_file_count'] ?? 0 ),
				'total'     => (int) ( $state['copy_file_count'] ?? 0 ),
			)
		);

		return $this->store->get( $job_id );
	}

	/**
	 * Join one absolute base and relative path.
	 *
	 * @param string $base     Absolute base.
	 * @param string $relative Relative path.
	 */
	private function join_path( string $base, string $relative ): string {
		return trailingslashit( wp_normalize_path( $base ) ) . ltrim( wp_normalize_path( $relative ), '/' );
	}
}
