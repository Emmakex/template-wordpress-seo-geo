<?php
/**
 * Portable Clone isolated sandbox control runtime bootstrap.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge\Clone;

/**
 * Installs/verifies Migration Bridge and writes isolated target configuration/hardening.
 */
final class LocalCloneSandboxRuntimeBootstrapper {
	public const MIN_BATCH_FILES     = 1;
	public const MAX_BATCH_FILES     = 200;
	public const DEFAULT_BATCH_FILES = 50;
	public const MIN_BATCH_BYTES     = 1048576;
	public const MAX_BATCH_BYTES     = 67108864;
	public const DEFAULT_BATCH_BYTES = 8388608;

	private const MAX_PENDING_DIRECTORIES = 10000;
	private const MAX_ENTRY_OPERATIONS    = 4000;
	private const FINGERPRINT_SEED        = 'seo-geo-local-clone-sandbox-runtime-v1';
	private const BRIDGE_TARGET_ROOT      = 'wp-content/plugins/seo-geo-migration-bridge';
	private const MU_PLUGIN_RELATIVE      = 'wp-content/mu-plugins/seo-geo-migration-sandbox-bootstrap.php';
	private const WP_CONFIG_RELATIVE      = 'wp-config.php';

	/**
	 * Sandbox runtime state persistence.
	 *
	 * @var LocalCloneSandboxRuntimeStateStore
	 */
	private LocalCloneSandboxRuntimeStateStore $store;

	/**
	 * Verified target ownership service.
	 *
	 * @var LocalCloneBootstrapper
	 */
	private LocalCloneBootstrapper $ownership;

	/**
	 * Verified WordPress core runtime service.
	 *
	 * @var LocalCloneRuntimeBootstrapper
	 */
	private LocalCloneRuntimeBootstrapper $core_runtime;

	/**
	 * Clone job state.
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
	 * Construct sandbox control runtime bootstrapper.
	 *
	 * @param LocalCloneSandboxRuntimeStateStore|null $store        Optional state store.
	 * @param LocalCloneBootstrapper|null             $ownership    Optional ownership service.
	 * @param LocalCloneRuntimeBootstrapper|null      $core_runtime Optional core runtime service.
	 * @param CloneJobStore|null                      $jobs         Optional job store.
	 * @param ExportWorkspace|null                    $workspace    Optional filesystem authority.
	 */
	public function __construct(
		?LocalCloneSandboxRuntimeStateStore $store = null,
		?LocalCloneBootstrapper $ownership = null,
		?LocalCloneRuntimeBootstrapper $core_runtime = null,
		?CloneJobStore $jobs = null,
		?ExportWorkspace $workspace = null
	) {
		$this->store        = $store ?? new LocalCloneSandboxRuntimeStateStore();
		$this->ownership    = $ownership ?? new LocalCloneBootstrapper();
		$this->core_runtime = $core_runtime ?? new LocalCloneRuntimeBootstrapper();
		$this->jobs         = $jobs ?? new CloneJobStore();
		$this->workspace    = $workspace ?? new ExportWorkspace();
	}

	/**
	 * Return one sandbox runtime state.
	 *
	 * @param string $job_id Clone job identifier.
	 * @return array<string,mixed>|null
	 */
	public function snapshot( string $job_id ): ?array {
		return $this->store->get( $job_id );
	}

	/**
	 * Return a completed sandbox runtime only while authority/control hashes still match.
	 *
	 * @param string $job_id Clone job identifier.
	 * @return array<string,mixed>|null
	 */
	public function verified_snapshot( string $job_id ): ?array {
		$state = $this->store->get( $job_id );
		if (
			! is_array( $state )
			|| 'complete' !== ( $state['status'] ?? null )
			|| true !== ( $state['sandbox_runtime_ready'] ?? false )
			|| ! $this->state_authorized( $job_id, $state )
			|| ! $this->control_files_match( $state )
		) {
			return null;
		}

		return $state;
	}

	/**
	 * Start the Bridge copy from accepted ownership/core runtime state.
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

		$bridge_root = defined( 'SEO_GEO_MIGRATION_BRIDGE_DIR' )
			? untrailingslashit( wp_normalize_path( (string) constant( 'SEO_GEO_MIGRATION_BRIDGE_DIR' ) ) )
			: '';
		if ( '' === $bridge_root || ! is_dir( $bridge_root ) || ! is_readable( $bridge_root ) || is_link( $bridge_root ) ) {
			return null;
		}

		$ownership = $authority['ownership'];
		$core      = $authority['core'];
		$now       = gmdate( DATE_ATOM );
		$seed      = hash( 'sha256', self::FINGERPRINT_SEED );
		$state     = array(
			'schema_version'            => LocalCloneSandboxRuntimeStateStore::SCHEMA_VERSION,
			'job_id'                    => $job_id,
			'status'                    => 'running',
			'stage'                     => 'bridge-copy',
			'plan_hash'                 => (string) $ownership['plan_hash'],
			'ownership_marker_sha256'   => (string) $ownership['marker_sha256'],
			'core_fingerprint'          => (string) $core['verify_fingerprint'],
			'target_path'               => (string) $ownership['target_path'],
			'target_url'                => (string) $ownership['target_url'],
			'target_table_prefix'       => (string) $ownership['target_table_prefix'],
			'bridge_source_root'        => $bridge_root,
			'pending_dirs'              => array( '' ),
			'current_dir'               => '',
			'after_name'                => '',
			'bridge_copy_files'         => 0,
			'bridge_copy_bytes'         => 0,
			'bridge_copy_fingerprint'   => $seed,
			'bridge_verify_files'       => 0,
			'bridge_verify_bytes'       => 0,
			'bridge_verify_fingerprint' => $seed,
			'wp_config_sha256'          => '',
			'mu_plugin_sha256'          => '',
			'sandbox_marker_enabled'    => false,
			'storage_isolated'          => false,
			'outbound_blocked'          => false,
			'search_blocked'            => false,
			'target_authorized'         => false,
			'backups_ready'             => false,
			'database_untouched'        => true,
			'client_content_untouched'  => true,
			'sandbox_runtime_ready'     => false,
			'bootstrap_next'            => 'bridge-copy',
			'blockers'                  => array(),
			'started_at'                => $now,
			'updated_at'                => $now,
			'completed_at'              => '',
		);

		if ( ! $this->store->save( $job_id, $state ) ) {
			return null;
		}

		$this->jobs->transition( $job_id, 'active', null );
		$this->jobs->update_progress(
			$job_id,
			'bootstrap-sandbox-runtime',
			'bridge-copy',
			array(
				'completed' => 0,
				'total'     => 0,
			)
		);

		return $this->store->get( $job_id );
	}

	/**
	 * Advance one bounded Bridge copy/verify/configure batch.
	 *
	 * @param string $job_id      Clone job identifier.
	 * @param int    $batch_files File budget.
	 * @param int    $batch_bytes Byte budget.
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
			return $this->block( $job_id, $state, 'sandbox-runtime-authority-drift' );
		}

		return match ( $state['stage'] ?? null ) {
			'bridge-copy'   => $this->advance_bridge_copy( $job_id, $state, $batch_files, $batch_bytes ),
			'bridge-verify' => $this->advance_bridge_verify( $job_id, $state, $batch_files, $batch_bytes ),
			'configure'     => $this->configure_target( $job_id, $state ),
			default         => $this->block( $job_id, $state, 'sandbox-runtime-stage-invalid' ),
		};
	}

	/**
	 * Copy one bounded Bridge batch.
	 *
	 * @param string              $job_id      Clone job identifier.
	 * @param array<string,mixed> $state       State.
	 * @param int                 $batch_files File budget.
	 * @param int                 $batch_bytes Byte budget.
	 * @return array<string,mixed>|null
	 */
	private function advance_bridge_copy( string $job_id, array $state, int $batch_files, int $batch_bytes ): ?array {
		$source         = (string) $state['bridge_source_root'];
		$target         = (string) $state['target_path'];
		$accepted_files = 0;
		$accepted_bytes = 0;
		$operations     = 0;

		if ( ! $this->workspace->ensure_local_clone_directory( $target, self::BRIDGE_TARGET_ROOT ) ) {
			return $this->block( $job_id, $state, 'sandbox-runtime-bridge-root-create-failed' );
		}

		while ( $accepted_files < $batch_files && $operations < self::MAX_ENTRY_OPERATIONS ) {
			$pending = is_array( $state['pending_dirs'] ?? null ) ? $state['pending_dirs'] : array();
			if ( '' === (string) ( $state['current_dir'] ?? '' ) && '' === (string) ( $state['after_name'] ?? '' ) ) {
				if ( array() === $pending ) {
					return $this->begin_bridge_verify( $job_id, $state );
				}
				$state['current_dir']  = (string) array_shift( $pending );
				$state['pending_dirs'] = $pending;
			}

			$current_dir = (string) $state['current_dir'];
			$source_dir  = '' === $current_dir ? $source : $this->join_path( $source, $current_dir );
			$entries     = $this->directory_entries( $source_dir );
			if ( null === $entries ) {
				return $this->block( $job_id, $state, 'sandbox-runtime-bridge-source-read-failed' );
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
					return $this->block( $job_id, $state, 'sandbox-runtime-bridge-source-symlink' );
				}

				$target_relative = self::BRIDGE_TARGET_ROOT . '/' . $relative;
				if ( is_dir( $path ) ) {
					if ( ! $this->workspace->ensure_local_clone_directory( $target, $target_relative ) ) {
						return $this->block( $job_id, $state, 'sandbox-runtime-bridge-directory-create-failed' );
					}
					$pending = is_array( $state['pending_dirs'] ?? null ) ? $state['pending_dirs'] : array();
					if ( count( $pending ) >= self::MAX_PENDING_DIRECTORIES ) {
						return $this->block( $job_id, $state, 'sandbox-runtime-bridge-directory-limit' );
					}
					$pending[]             = $relative;
					$state['pending_dirs'] = $pending;
					$state['after_name']   = $entry;
					continue;
				}

				if ( ! is_file( $path ) || ! is_readable( $path ) ) {
					return $this->block( $job_id, $state, 'sandbox-runtime-bridge-source-file-unreadable' );
				}

				$copied = $this->workspace->copy_file_to_local_clone_target( $path, $target, $target_relative );
				if ( ! is_array( $copied ) ) {
					return $this->block( $job_id, $state, 'sandbox-runtime-bridge-copy-failed' );
				}

				$bytes = (int) $copied['bytes'];
				$hash  = (string) $copied['sha256'];
				$state['bridge_copy_files']       = (int) $state['bridge_copy_files'] + 1;
				$state['bridge_copy_bytes']       = (int) $state['bridge_copy_bytes'] + $bytes;
				$state['bridge_copy_fingerprint'] = $this->fingerprint_add(
					(string) $state['bridge_copy_fingerprint'],
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
			'bootstrap-sandbox-runtime',
			'bridge-copy',
			array(
				'completed' => (int) $state['bridge_copy_files'],
				'total'     => 0,
			)
		);

		return $this->store->get( $job_id );
	}

	/**
	 * Switch to independent Bridge verification.
	 *
	 * @param string              $job_id Clone job identifier.
	 * @param array<string,mixed> $state  State.
	 * @return array<string,mixed>|null
	 */
	private function begin_bridge_verify( string $job_id, array $state ): ?array {
		$state['stage']                     = 'bridge-verify';
		$state['pending_dirs']              = array( '' );
		$state['current_dir']               = '';
		$state['after_name']                = '';
		$state['bridge_verify_files']       = 0;
		$state['bridge_verify_bytes']       = 0;
		$state['bridge_verify_fingerprint'] = hash( 'sha256', self::FINGERPRINT_SEED );
		$state['bootstrap_next']            = 'bridge-verify';
		$state['updated_at']                = gmdate( DATE_ATOM );

		if ( ! $this->store->save( $job_id, $state ) ) {
			return null;
		}

		return $this->store->get( $job_id );
	}

	/**
	 * Verify one bounded Bridge batch against source and target topology.
	 *
	 * @param string              $job_id      Clone job identifier.
	 * @param array<string,mixed> $state       State.
	 * @param int                 $batch_files File budget.
	 * @param int                 $batch_bytes Byte budget.
	 * @return array<string,mixed>|null
	 */
	private function advance_bridge_verify( string $job_id, array $state, int $batch_files, int $batch_bytes ): ?array {
		$source         = (string) $state['bridge_source_root'];
		$target_root    = (string) $state['target_path'];
		$target_bridge  = $this->join_path( $target_root, self::BRIDGE_TARGET_ROOT );
		$accepted_files = 0;
		$accepted_bytes = 0;
		$operations     = 0;

		while ( $accepted_files < $batch_files && $operations < self::MAX_ENTRY_OPERATIONS ) {
			$pending = is_array( $state['pending_dirs'] ?? null ) ? $state['pending_dirs'] : array();
			if ( '' === (string) ( $state['current_dir'] ?? '' ) && '' === (string) ( $state['after_name'] ?? '' ) ) {
				if ( array() === $pending ) {
					return $this->finish_bridge_verify( $job_id, $state );
				}
				$state['current_dir']  = (string) array_shift( $pending );
				$state['pending_dirs'] = $pending;
			}

			$current_dir = (string) $state['current_dir'];
			$source_dir  = '' === $current_dir ? $source : $this->join_path( $source, $current_dir );
			$target_dir  = '' === $current_dir ? $target_bridge : $this->join_path( $target_bridge, $current_dir );
			$source_list = $this->directory_entries( $source_dir );
			$target_list = $this->directory_entries( $target_dir );
			if ( null === $source_list || null === $target_list ) {
				return $this->block( $job_id, $state, 'sandbox-runtime-bridge-verify-read-failed' );
			}
			if ( $source_list !== $target_list ) {
				return $this->block( $job_id, $state, 'sandbox-runtime-bridge-target-drift' );
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
				$target_path = $this->join_path( $target_bridge, $relative );
				if ( is_link( $source_path ) || is_link( $target_path ) ) {
					return $this->block( $job_id, $state, 'sandbox-runtime-bridge-verify-symlink' );
				}

				if ( is_dir( $source_path ) ) {
					if ( ! is_dir( $target_path ) ) {
						return $this->block( $job_id, $state, 'sandbox-runtime-bridge-directory-missing' );
					}
					$pending = is_array( $state['pending_dirs'] ?? null ) ? $state['pending_dirs'] : array();
					if ( count( $pending ) >= self::MAX_PENDING_DIRECTORIES ) {
						return $this->block( $job_id, $state, 'sandbox-runtime-bridge-verify-directory-limit' );
					}
					$pending[]             = $relative;
					$state['pending_dirs'] = $pending;
					$state['after_name']   = $entry;
					continue;
				}

				if (
					! is_file( $source_path )
					|| ! is_readable( $source_path )
					|| ! is_file( $target_path )
					|| ! is_readable( $target_path )
				) {
					return $this->block( $job_id, $state, 'sandbox-runtime-bridge-file-missing' );
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
					return $this->block( $job_id, $state, 'sandbox-runtime-bridge-integrity-mismatch' );
				}

				$bytes = (int) $target_bytes;
				$state['bridge_verify_files']       = (int) $state['bridge_verify_files'] + 1;
				$state['bridge_verify_bytes']       = (int) $state['bridge_verify_bytes'] + $bytes;
				$state['bridge_verify_fingerprint'] = $this->fingerprint_add(
					(string) $state['bridge_verify_fingerprint'],
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

		return $this->store->get( $job_id );
	}

	/**
	 * Reconcile Bridge copy/verify and move to configuration.
	 *
	 * @param string              $job_id Clone job identifier.
	 * @param array<string,mixed> $state  State.
	 * @return array<string,mixed>|null
	 */
	private function finish_bridge_verify( string $job_id, array $state ): ?array {
		if (
			(int) $state['bridge_copy_files'] !== (int) $state['bridge_verify_files']
			|| (int) $state['bridge_copy_bytes'] !== (int) $state['bridge_verify_bytes']
			|| ! hash_equals( (string) $state['bridge_copy_fingerprint'], (string) $state['bridge_verify_fingerprint'] )
		) {
			return $this->block( $job_id, $state, 'sandbox-runtime-bridge-final-reconciliation-failed' );
		}

		$state['stage']          = 'configure';
		$state['pending_dirs']   = array();
		$state['current_dir']    = '';
		$state['after_name']     = '';
		$state['bootstrap_next'] = 'configure';
		$state['updated_at']     = gmdate( DATE_ATOM );
		if ( ! $this->store->save( $job_id, $state ) ) {
			return null;
		}

		return $this->store->get( $job_id );
	}

	/**
	 * Write isolated wp-config.php and sandbox MU-plugin, then verify exact hashes.
	 *
	 * @param string              $job_id Clone job identifier.
	 * @param array<string,mixed> $state  State.
	 * @return array<string,mixed>|null
	 */
	private function configure_target( string $job_id, array $state ): ?array {
		$config = $this->wp_config_content( $state );
		$mu     = $this->mu_plugin_content();
		if ( null === $config ) {
			return $this->block( $job_id, $state, 'sandbox-runtime-config-source-constants-missing' );
		}

		$target = (string) $state['target_path'];
		$config_result = $this->workspace->write_local_clone_control_file(
			$target,
			self::WP_CONFIG_RELATIVE,
			$config,
			0600
		);
		if ( ! is_array( $config_result ) ) {
			return $this->block( $job_id, $state, 'sandbox-runtime-wp-config-write-failed' );
		}

		$mu_result = $this->workspace->write_local_clone_control_file(
			$target,
			self::MU_PLUGIN_RELATIVE,
			$mu,
			0640
		);
		if ( ! is_array( $mu_result ) ) {
			return $this->block( $job_id, $state, 'sandbox-runtime-mu-plugin-write-failed' );
		}

		$config_path = $this->join_path( $target, self::WP_CONFIG_RELATIVE );
		$mu_path     = $this->join_path( $target, self::MU_PLUGIN_RELATIVE );
		$config_hash = hash_file( 'sha256', $config_path );
		$mu_hash     = hash_file( 'sha256', $mu_path );
		if (
			false === $config_hash
			|| false === $mu_hash
			|| ! hash_equals( (string) $config_result['sha256'], $config_hash )
			|| ! hash_equals( (string) $mu_result['sha256'], $mu_hash )
		) {
			return $this->block( $job_id, $state, 'sandbox-runtime-control-file-verification-failed' );
		}

		$state['status']                   = 'complete';
		$state['stage']                    = 'complete';
		$state['wp_config_sha256']         = $config_hash;
		$state['mu_plugin_sha256']         = $mu_hash;
		$state['sandbox_marker_enabled']   = true;
		$state['storage_isolated']         = true;
		$state['outbound_blocked']         = true;
		$state['search_blocked']           = true;
		$state['target_authorized']        = true;
		$state['backups_ready']            = true;
		$state['database_untouched']       = true;
		$state['client_content_untouched'] = true;
		$state['sandbox_runtime_ready']    = true;
		$state['bootstrap_next']           = 'package-handoff';
		$state['completed_at']             = gmdate( DATE_ATOM );
		$state['updated_at']               = $state['completed_at'];
		$state['blockers']                 = array();

		if ( ! $this->store->save( $job_id, $state ) ) {
			return null;
		}

		$this->jobs->update_progress(
			$job_id,
			'bootstrap-sandbox-runtime',
			'complete',
			array(
				'completed' => (int) $state['bridge_verify_files'],
				'total'     => (int) $state['bridge_copy_files'],
			)
		);

		return $this->store->get( $job_id );
	}

	/**
	 * Resolve verified ownership + completed core runtime.
	 *
	 * @param string $job_id Clone job identifier.
	 * @return array{ownership:array<string,mixed>,core:array<string,mixed>}|null
	 */
	private function authority( string $job_id ): ?array {
		$job       = $this->jobs->get( $job_id );
		$ownership = $this->ownership->verified_snapshot( $job_id );
		$core      = $this->core_runtime->snapshot( $job_id );
		if (
			! is_array( $job )
			|| 'local-clone' !== ( $job['operation'] ?? null )
			|| ! is_array( $ownership )
			|| ! is_array( $core )
			|| 'complete' !== ( $core['status'] ?? null )
			|| 'core-complete' !== ( $core['stage'] ?? null )
			|| true !== ( $core['runtime_core_ready'] ?? false )
			|| ! hash_equals( (string) $ownership['plan_hash'], (string) ( $core['plan_hash'] ?? '' ) )
			|| ! hash_equals( (string) $ownership['marker_sha256'], (string) ( $core['ownership_marker_sha256'] ?? '' ) )
		) {
			return null;
		}

		$ownership_target = untrailingslashit( wp_normalize_path( (string) $ownership['target_path'] ) );
		$core_target      = untrailingslashit( wp_normalize_path( (string) $core['target_path'] ) );
		if (
			! hash_equals( $ownership_target, $core_target )
			|| ! hash_equals( (string) $ownership['target_url'], (string) $core['target_url'] )
			|| ! hash_equals( (string) $ownership['target_table_prefix'], (string) $core['target_table_prefix'] )
		) {
			return null;
		}

		return array(
			'ownership' => $ownership,
			'core'      => $core,
		);
	}

	/**
	 * Whether stored state remains bound to current ownership/core runtime.
	 *
	 * @param string              $job_id Clone job identifier.
	 * @param array<string,mixed> $state  State.
	 */
	private function state_authorized( string $job_id, array $state ): bool {
		$authority = $this->authority( $job_id );
		if ( null === $authority ) {
			return false;
		}

		$ownership = $authority['ownership'];
		$core      = $authority['core'];
		$target    = untrailingslashit( wp_normalize_path( (string) $state['target_path'] ) );
		$current   = untrailingslashit( wp_normalize_path( (string) $ownership['target_path'] ) );

		return hash_equals( (string) $state['plan_hash'], (string) $ownership['plan_hash'] )
			&& hash_equals( (string) $state['ownership_marker_sha256'], (string) $ownership['marker_sha256'] )
			&& hash_equals( (string) $state['core_fingerprint'], (string) $core['verify_fingerprint'] )
			&& hash_equals( $target, $current )
			&& hash_equals( (string) $state['target_url'], (string) $ownership['target_url'] )
			&& hash_equals( (string) $state['target_table_prefix'], (string) $ownership['target_table_prefix'] );
	}

	/**
	 * Verify generated control files without exposing their contents.
	 *
	 * @param array<string,mixed> $state Runtime state.
	 */
	private function control_files_match( array $state ): bool {
		$target      = (string) $state['target_path'];
		$config_path = $this->join_path( $target, self::WP_CONFIG_RELATIVE );
		$mu_path     = $this->join_path( $target, self::MU_PLUGIN_RELATIVE );
		if (
			! is_file( $config_path )
			|| ! is_readable( $config_path )
			|| is_link( $config_path )
			|| ! is_file( $mu_path )
			|| ! is_readable( $mu_path )
			|| is_link( $mu_path )
		) {
			return false;
		}

		$config_hash = hash_file( 'sha256', $config_path );
		$mu_hash     = hash_file( 'sha256', $mu_path );

		return false !== $config_hash
			&& false !== $mu_hash
			&& hash_equals( (string) $state['wp_config_sha256'], $config_hash )
			&& hash_equals( (string) $state['mu_plugin_sha256'], $mu_hash );
	}

	/**
	 * Build target wp-config.php without persisting/logging database secrets.
	 *
	 * @param array<string,mixed> $state Runtime state.
	 */
	private function wp_config_content( array $state ): ?string {
		foreach ( array( 'DB_NAME', 'DB_USER', 'DB_PASSWORD', 'DB_HOST' ) as $required ) {
			if ( ! defined( $required ) || ! is_string( constant( $required ) ) ) {
				return null;
			}
		}

		$target_url    = untrailingslashit( (string) $state['target_url'] );
		$target_prefix = (string) $state['target_table_prefix'];
		if (
			'' === $target_url
			|| 1 !== preg_match( '/^[A-Za-z0-9_]+$/', $target_prefix )
		) {
			return null;
		}

		$constants = array(
			'DB_NAME'     => (string) constant( 'DB_NAME' ),
			'DB_USER'     => (string) constant( 'DB_USER' ),
			'DB_PASSWORD' => (string) constant( 'DB_PASSWORD' ),
			'DB_HOST'     => (string) constant( 'DB_HOST' ),
			'DB_CHARSET'  => defined( 'DB_CHARSET' ) && is_string( constant( 'DB_CHARSET' ) ) ? (string) constant( 'DB_CHARSET' ) : 'utf8mb4',
			'DB_COLLATE'  => defined( 'DB_COLLATE' ) && is_string( constant( 'DB_COLLATE' ) ) ? (string) constant( 'DB_COLLATE' ) : '',
		);

		$lines = array(
			'<?php',
			'/** Generated by SEO/GEO Migration Bridge for an isolated local-clone sandbox. */',
		'declare(strict_types=1);',
			'',
		);
		foreach ( $constants as $name => $value ) {
			$lines[] = "define( '" . $name . "', " . var_export( $value, true ) . ' );';
		}

		foreach (
			array(
				'AUTH_KEY',
				'SECURE_AUTH_KEY',
				'LOGGED_IN_KEY',
				'NONCE_KEY',
				'AUTH_SALT',
				'SECURE_AUTH_SALT',
				'LOGGED_IN_SALT',
				'NONCE_SALT',
			) as $salt_name
		) {
			$source_secret = defined( $salt_name ) && is_string( constant( $salt_name ) )
				? (string) constant( $salt_name )
				: (string) constant( 'DB_PASSWORD' );
			$derived = hash_hmac(
				'sha512',
				(string) $state['plan_hash'] . '|' . $salt_name . '|' . (string) $state['ownership_marker_sha256'],
				$source_secret
			);
			$lines[] = "define( '" . $salt_name . "', " . var_export( $derived, true ) . ' );';
		}

		$lines[] = '';
		$lines[] = '$table_prefix = ' . var_export( $target_prefix, true ) . ';';
		$lines[] = "define( 'WP_HOME', " . var_export( $target_url, true ) . ' );';
		$lines[] = "define( 'WP_SITEURL', " . var_export( $target_url, true ) . ' );';
		$lines[] = "define( 'WP_ENVIRONMENT_TYPE', 'staging' );";
		$lines[] = "define( 'DISABLE_WP_CRON', true );";
		$lines[] = "define( 'DISALLOW_FILE_EDIT', true );";
		$lines[] = "define( 'AUTOMATIC_UPDATER_DISABLED', true );";
		$lines[] = "define( 'SEO_GEO_MIGRATION_SANDBOX', true );";
		$lines[] = "define( 'SEO_GEO_MIGRATION_SANDBOX_MODE', 'subdirectory' );";
		$lines[] = "define( 'SEO_GEO_MIGRATION_STORAGE_ISOLATED', true );";
		$lines[] = "define( 'SEO_GEO_MIGRATION_OUTBOUND_SAFE', true );";
		$lines[] = "define( 'SEO_GEO_MIGRATION_BACKUPS_READY', true );";
		$lines[] = "define( 'SEO_GEO_MIGRATION_IMPORT_TARGET_AUTHORIZED', true );";
		$lines[] = '';
		$lines[] = "if ( ! defined( 'ABSPATH' ) ) {";
		$lines[] = "	define( 'ABSPATH', __DIR__ . '/' );";
		$lines[] = '}';
		$lines[] = "require_once ABSPATH . 'wp-settings.php';";
		$lines[] = '';

		return implode( "\n", $lines );
	}

	/**
	 * Build the always-on sandbox hardening + Bridge loader MU-plugin.
	 */
	private function mu_plugin_content(): string {
		return <<<'PHP'
<?php
/**
 * SEO/GEO Migration Bridge isolated sandbox bootstrap.
 *
 * Generated file. Do not edit in-place.
 */

declare(strict_types=1);

if ( ! defined( 'SEO_GEO_MIGRATION_SANDBOX' ) || true !== SEO_GEO_MIGRATION_SANDBOX ) {
	return;
}

add_filter(
	'pre_option_blog_public',
	static fn (): string => '0',
	PHP_INT_MAX
);

add_filter(
	'wp_robots',
	static function ( array $robots ): array {
		unset( $robots['index'], $robots['follow'] );
		$robots['noindex']   = true;
		$robots['nofollow']  = true;
		$robots['noarchive'] = true;

		return $robots;
	},
	PHP_INT_MAX
);

add_filter(
	'wp_headers',
	static function ( array $headers ): array {
		$headers['X-Robots-Tag'] = 'noindex, nofollow, noarchive';

		return $headers;
	},
	PHP_INT_MAX
);

add_filter(
	'pre_wp_mail',
	static fn (): bool => false,
	PHP_INT_MAX
);

add_filter(
	'pre_http_request',
	static function ( mixed $preempt, array $parsed_args, string $url ): mixed {
		unset( $parsed_args );

		$host      = wp_parse_url( $url, PHP_URL_HOST );
		$url_path  = wp_parse_url( $url, PHP_URL_PATH );
		$home_url  = home_url( '/' );
		$home_host = wp_parse_url( $home_url, PHP_URL_HOST );
		$home_path = wp_parse_url( $home_url, PHP_URL_PATH );

		$is_loopback = is_string( $host )
			&& in_array( strtolower( $host ), array( 'localhost', '127.0.0.1', '::1' ), true );
		$is_sandbox_path = is_string( $host )
			&& is_string( $home_host )
			&& strtolower( $host ) === strtolower( $home_host )
			&& is_string( $url_path )
			&& is_string( $home_path )
			&& str_starts_with(
				'/' . ltrim( $url_path, '/' ),
				trailingslashit( '/' . trim( $home_path, '/' ) )
			);

		if ( $is_loopback || $is_sandbox_path ) {
			return $preempt;
		}

		return new WP_Error(
			'seo_geo_migration_sandbox_outbound_blocked',
			'Outbound HTTP is disabled in the SEO/GEO migration sandbox.'
		);
	},
	PHP_INT_MAX,
	3
);

$bridge = ABSPATH . 'wp-content/plugins/seo-geo-migration-bridge/seo-geo-migration-bridge.php';
if ( is_readable( $bridge ) ) {
	require_once $bridge;
}
PHP;
	}

	/**
	 * Return sorted entries for one directory.
	 *
	 * @param string $directory Absolute directory.
	 * @return list<string>|null
	 */
	private function directory_entries( string $directory ): ?array {
		if ( ! is_dir( $directory ) || ! is_readable( $directory ) || is_link( $directory ) ) {
			return null;
		}

		$entries = scandir( $directory, SCANDIR_SORT_ASCENDING );
		if ( false === $entries ) {
			return null;
		}

		return array_values( array_diff( $entries, array( '.', '..' ) ) );
	}

	/**
	 * Add one file to deterministic Bridge fingerprint.
	 *
	 * @param string $fingerprint Current fingerprint.
	 * @param string $relative    Relative file.
	 * @param int    $bytes       Bytes.
	 * @param string $sha256      SHA-256.
	 */
	private function fingerprint_add( string $fingerprint, string $relative, int $bytes, string $sha256 ): string {
		return hash(
			'sha256',
			$fingerprint . "\0" . wp_normalize_path( $relative ) . "\0" . (string) $bytes . "\0" . $sha256
		);
	}

	/**
	 * Persist blocker without further target mutation.
	 *
	 * @param string              $job_id Clone job identifier.
	 * @param array<string,mixed> $state  State.
	 * @param string              $code   Stable blocker.
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
			'bootstrap-sandbox-runtime',
			'blocked',
			array(
				'completed' => (int) ( $state['bridge_verify_files'] ?? $state['bridge_copy_files'] ?? 0 ),
				'total'     => (int) ( $state['bridge_copy_files'] ?? 0 ),
			)
		);

		return $this->store->get( $job_id );
	}

	private function join_path( string $base, string $relative ): string {
		return trailingslashit( wp_normalize_path( $base ) ) . ltrim( wp_normalize_path( $relative ), '/' );
	}
}
