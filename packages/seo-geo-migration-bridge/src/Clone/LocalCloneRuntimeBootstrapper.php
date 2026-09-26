<?php
/**
 * Portable Clone isolated WordPress core runtime bootstrap.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge\Clone;

/**
 * Copies and independently verifies only WordPress core into the owned clone target.
 */
final class LocalCloneRuntimeBootstrapper {
	public const MIN_BATCH_FILES     = 1;
	public const MAX_BATCH_FILES     = 200;
	public const DEFAULT_BATCH_FILES = 25;
	public const MIN_BATCH_BYTES     = 1048576;
	public const MAX_BATCH_BYTES     = 67108864;
	public const DEFAULT_BATCH_BYTES = 8388608;

	private const MAX_PENDING_DIRECTORIES = 50000;
	private const MAX_ENTRY_OPERATIONS    = 4000;
	private const FINGERPRINT_SEED        = 'seo-geo-local-clone-runtime-core-v1';

	private const ROOT_DIRECTORIES = array(
		'wp-admin',
		'wp-includes',
	);

	private const ROOT_FILES = array(
		'index.php',
		'license.txt',
		'readme.html',
		'wp-activate.php',
		'wp-blog-header.php',
		'wp-comments-post.php',
		'wp-config-sample.php',
		'wp-cron.php',
		'wp-links-opml.php',
		'wp-load.php',
		'wp-login.php',
		'wp-mail.php',
		'wp-settings.php',
		'wp-signup.php',
		'wp-trackback.php',
		'xmlrpc.php',
	);

	/**
	 * Resumable core runtime state persistence.
	 *
	 * @var LocalCloneRuntimeStateStore
	 */
	private LocalCloneRuntimeStateStore $store;

	/**
	 * Verified local-clone target ownership service.
	 *
	 * @var LocalCloneBootstrapper
	 */
	private LocalCloneBootstrapper $ownership;

	/**
	 * Accepted local-clone destination plans.
	 *
	 * @var LocalCloneStateStore
	 */
	private LocalCloneStateStore $plans;

	/**
	 * Verified package state.
	 *
	 * @var PackageStateStore
	 */
	private PackageStateStore $packages;

	/**
	 * Completed source inventory state.
	 *
	 * @var CloneInventoryStore
	 */
	private CloneInventoryStore $inventory;

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
	 * Construct the core runtime bootstrapper.
	 *
	 * @param LocalCloneRuntimeStateStore|null $store     Optional runtime state store.
	 * @param LocalCloneBootstrapper|null      $ownership Optional target ownership service.
	 * @param LocalCloneStateStore|null        $plans     Optional destination plan store.
	 * @param PackageStateStore|null           $packages  Optional verified package store.
	 * @param CloneInventoryStore|null         $inventory Optional source inventory store.
	 * @param CloneJobStore|null               $jobs      Optional clone job store.
	 * @param ExportWorkspace|null             $workspace Optional filesystem mutation authority.
	 */
	public function __construct(
		?LocalCloneRuntimeStateStore $store = null,
		?LocalCloneBootstrapper $ownership = null,
		?LocalCloneStateStore $plans = null,
		?PackageStateStore $packages = null,
		?CloneInventoryStore $inventory = null,
		?CloneJobStore $jobs = null,
		?ExportWorkspace $workspace = null
	) {
		$this->store     = $store ?? new LocalCloneRuntimeStateStore();
		$this->ownership = $ownership ?? new LocalCloneBootstrapper();
		$this->plans     = $plans ?? new LocalCloneStateStore();
		$this->packages  = $packages ?? new PackageStateStore();
		$this->inventory = $inventory ?? new CloneInventoryStore();
		$this->jobs      = $jobs ?? new CloneJobStore();
		$this->workspace = $workspace ?? new ExportWorkspace();
	}

	/**
	 * Return one runtime bootstrap state.
	 *
	 * @param string $job_id Clone job identifier.
	 * @return array<string,mixed>|null
	 */
	public function snapshot( string $job_id ): ?array {
		return $this->store->get( $job_id );
	}

	/**
	 * Start a resumable WordPress core copy from the verified owned target.
	 *
	 * @param string $job_id Clone job identifier.
	 * @return array<string,mixed>|null
	 */
	public function start( string $job_id ): ?array {
		$existing = $this->store->get( $job_id );
		if ( is_array( $existing ) ) {
			return $existing;
		}

		$authority = $this->runtime_authority( $job_id );
		if ( null === $authority ) {
			return null;
		}

		$ownership = $authority['ownership'];
		$source    = untrailingslashit( wp_normalize_path( ABSPATH ) );
		$target    = untrailingslashit( wp_normalize_path( (string) $ownership['target_path'] ) );
		if (
			'' === $source
			|| '' === $target
			|| $source === $target
			|| ! is_dir( $source )
			|| is_link( $source )
			|| ! is_dir( $target )
			|| is_link( $target )
			|| $this->target_overlaps_core( $source, $target )
		) {
			return null;
		}

		$version_file = $this->join_path( $source, 'wp-includes/version.php' );
		$version_hash = is_file( $version_file ) && is_readable( $version_file ) && ! is_link( $version_file )
			? hash_file( 'sha256', $version_file )
			: false;
		if ( false === $version_hash ) {
			return null;
		}

		$now   = gmdate( DATE_ATOM );
		$seed  = hash( 'sha256', self::FINGERPRINT_SEED );
		$state = array(
			'schema_version'          => LocalCloneRuntimeStateStore::SCHEMA_VERSION,
			'job_id'                  => $job_id,
			'status'                  => 'running',
			'stage'                   => 'core-copy',
			'plan_hash'               => (string) $ownership['plan_hash'],
			'ownership_marker_sha256' => (string) $ownership['marker_sha256'],
			'target_path'             => $target,
			'target_url'              => (string) $ownership['target_url'],
			'target_table_prefix'     => (string) $ownership['target_table_prefix'],
			'source_root'             => $source,
			'source_version_sha256'   => $version_hash,
			'pending_dirs'            => array( '' ),
			'current_dir'             => '',
			'after_name'              => '',
			'copy_file_count'         => 0,
			'copy_byte_count'         => 0,
			'copy_fingerprint'        => $seed,
			'verify_file_count'       => 0,
			'verify_byte_count'       => 0,
			'verify_fingerprint'      => $seed,
			'production_untouched'    => true,
			'database_untouched'      => true,
			'wp_content_untouched'    => true,
			'runtime_core_ready'      => false,
			'bootstrap_next'          => 'core-copy',
			'blockers'                => array(),
			'started_at'              => $now,
			'updated_at'              => $now,
			'completed_at'            => '',
		);

		if ( ! $this->store->save( $job_id, $state ) ) {
			return null;
		}

		$this->jobs->transition( $job_id, 'active', null );
		$this->jobs->update_progress(
			$job_id,
			'bootstrap-runtime',
			'core-copy',
			array(
				'completed' => 0,
				'total'     => 0,
			)
		);

		return $this->store->get( $job_id );
	}

	/**
	 * Advance one bounded core copy or verification batch.
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
			return $this->block( $job_id, $state, 'runtime-authority-drift' );
		}
		if ( ! $this->source_version_matches( $state ) ) {
			return $this->block( $job_id, $state, 'runtime-source-version-changed' );
		}

		return 'core-verify' === ( $state['stage'] ?? null )
			? $this->advance_verify( $job_id, $state, $batch_files, $batch_bytes )
			: $this->advance_core_files( $job_id, $state, $batch_files, $batch_bytes );
	}

	/**
	 * Copy one bounded batch of WordPress core files.
	 *
	 * @param string              $job_id      Clone job identifier.
	 * @param array<string,mixed> $state       Runtime state.
	 * @param int                 $batch_files Maximum files.
	 * @param int                 $batch_bytes Maximum bytes.
	 * @return array<string,mixed>|null
	 */
	private function advance_core_files( string $job_id, array $state, int $batch_files, int $batch_bytes ): ?array {
		if ( 'core-copy' !== ( $state['stage'] ?? null ) ) {
			return $this->block( $job_id, $state, 'runtime-copy-stage-invalid' );
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
			$entries     = $this->source_entries( $source_dir, $current_dir );
			if ( null === $entries ) {
				return $this->block( $job_id, $state, 'runtime-source-directory-read-failed' );
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
					return $this->block( $job_id, $state, 'runtime-source-symlink' );
				}

				if ( is_dir( $path ) ) {
					if ( ! $this->workspace->ensure_local_clone_directory( $target, $relative ) ) {
						return $this->block( $job_id, $state, 'runtime-target-directory-create-failed' );
					}

					$pending = is_array( $state['pending_dirs'] ?? null ) ? $state['pending_dirs'] : array();
					if ( count( $pending ) >= self::MAX_PENDING_DIRECTORIES ) {
						return $this->block( $job_id, $state, 'runtime-directory-queue-limit' );
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
					return $this->block( $job_id, $state, 'runtime-source-file-unreadable' );
				}

				$copied = $this->workspace->copy_file_to_local_clone_target( $path, $target, $relative );
				if ( ! is_array( $copied ) ) {
					return $this->block( $job_id, $state, 'runtime-core-copy-failed' );
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
				$state['after_name']       = $entry;
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
		$state['bootstrap_next'] = 'core-copy';
		if ( ! $this->store->save( $job_id, $state ) ) {
			return null;
		}

		$this->jobs->update_progress(
			$job_id,
			'bootstrap-runtime',
			'core-copy',
			array(
				'completed' => (int) $state['copy_file_count'],
				'total'     => 0,
			)
		);

		return $this->store->get( $job_id );
	}

	/**
	 * Switch from core copy to the independent verification pass.
	 *
	 * @param string              $job_id Clone job identifier.
	 * @param array<string,mixed> $state  Runtime state.
	 * @return array<string,mixed>|null
	 */
	private function begin_verify( string $job_id, array $state ): ?array {
		$state['stage']              = 'core-verify';
		$state['pending_dirs']       = array( '' );
		$state['current_dir']        = '';
		$state['after_name']         = '';
		$state['verify_file_count']  = 0;
		$state['verify_byte_count']  = 0;
		$state['verify_fingerprint'] = hash( 'sha256', self::FINGERPRINT_SEED );
		$state['bootstrap_next']     = 'core-verify';
		$state['updated_at']         = gmdate( DATE_ATOM );

		if ( ! $this->store->save( $job_id, $state ) ) {
			return null;
		}

		$this->jobs->update_progress(
			$job_id,
			'bootstrap-runtime',
			'core-verify',
			array(
				'completed' => 0,
				'total'     => (int) $state['copy_file_count'],
			)
		);

		return $this->store->get( $job_id );
	}

	/**
	 * Verify one bounded batch against source and reject extra target entries.
	 *
	 * @param string              $job_id      Clone job identifier.
	 * @param array<string,mixed> $state       Runtime state.
	 * @param int                 $batch_files Maximum files.
	 * @param int                 $batch_bytes Maximum bytes.
	 * @return array<string,mixed>|null
	 */
	private function advance_verify( string $job_id, array $state, int $batch_files, int $batch_bytes ): ?array {
		$source         = (string) $state['source_root'];
		$target         = (string) $state['target_path'];
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
			$target_dir  = '' === $current_dir ? $target : $this->join_path( $target, $current_dir );
			$entries     = $this->source_entries( $source_dir, $current_dir );
			$target_list = $this->target_entries( $target_dir, $current_dir );
			if ( null === $entries || null === $target_list ) {
				return $this->block( $job_id, $state, 'runtime-verify-directory-read-failed' );
			}
			if ( $entries !== $target_list ) {
				return $this->block( $job_id, $state, 'runtime-target-entry-drift' );
			}

			$after_name   = (string) $state['after_name'];
			$dir_finished = true;

			foreach ( $entries as $entry ) {
				if ( '' !== $after_name && strcmp( $entry, $after_name ) <= 0 ) {
					continue;
				}

				++$operations;
				$relative    = '' === $current_dir ? $entry : $current_dir . '/' . $entry;
				$source_path = $this->join_path( $source, $relative );
				$target_path = $this->join_path( $target, $relative );

				if ( is_link( $source_path ) || is_link( $target_path ) ) {
					return $this->block( $job_id, $state, 'runtime-verify-symlink' );
				}

				if ( is_dir( $source_path ) ) {
					if ( ! is_dir( $target_path ) ) {
						return $this->block( $job_id, $state, 'runtime-target-directory-missing' );
					}
					$pending = is_array( $state['pending_dirs'] ?? null ) ? $state['pending_dirs'] : array();
					if ( count( $pending ) >= self::MAX_PENDING_DIRECTORIES ) {
						return $this->block( $job_id, $state, 'runtime-verify-directory-queue-limit' );
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
					return $this->block( $job_id, $state, 'runtime-verify-file-missing' );
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
					return $this->block( $job_id, $state, 'runtime-core-integrity-mismatch' );
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
		$state['bootstrap_next'] = 'core-verify';
		if ( ! $this->store->save( $job_id, $state ) ) {
			return null;
		}

		$this->jobs->update_progress(
			$job_id,
			'bootstrap-runtime',
			'core-verify',
			array(
				'completed' => (int) $state['verify_file_count'],
				'total'     => (int) $state['copy_file_count'],
			)
		);

		return $this->store->get( $job_id );
	}

	/**
	 * Finish only after independent counts/bytes/fingerprint match.
	 *
	 * @param string              $job_id Clone job identifier.
	 * @param array<string,mixed> $state  Runtime state.
	 * @return array<string,mixed>|null
	 */
	private function complete_verify( string $job_id, array $state ): ?array {
		if (
			(int) $state['copy_file_count'] !== (int) $state['verify_file_count']
			|| (int) $state['copy_byte_count'] !== (int) $state['verify_byte_count']
			|| ! hash_equals( (string) $state['copy_fingerprint'], (string) $state['verify_fingerprint'] )
			|| ! $this->source_version_matches( $state )
		) {
			return $this->block( $job_id, $state, 'runtime-core-final-reconciliation-failed' );
		}

		$state['status']             = 'complete';
		$state['stage']              = 'core-complete';
		$state['pending_dirs']       = array();
		$state['current_dir']        = '';
		$state['after_name']         = '';
		$state['runtime_core_ready'] = true;
		$state['bootstrap_next']     = 'bridge-config-hardening';
		$state['completed_at']       = gmdate( DATE_ATOM );
		$state['updated_at']         = $state['completed_at'];
		$state['blockers']           = array();

		if ( ! $this->store->save( $job_id, $state ) ) {
			return null;
		}

		$this->jobs->update_progress(
			$job_id,
			'bootstrap-runtime',
			'core-complete',
			array(
				'completed' => (int) $state['verify_file_count'],
				'total'     => (int) $state['copy_file_count'],
			)
		);

		return $this->store->get( $job_id );
	}

	/**
	 * Resolve and verify the frozen job/plan/package/ownership authority.
	 *
	 * @param string $job_id Clone job identifier.
	 * @return array{ownership:array<string,mixed>,plan:array<string,mixed>}|null
	 */
	private function runtime_authority( string $job_id ): ?array {
		$job       = $this->jobs->get( $job_id );
		$ownership = $this->ownership->verified_snapshot( $job_id );
		$plan      = $this->plans->get( $job_id );
		$package   = $this->packages->get( $job_id );
		$inventory = $this->inventory->get( $job_id );
		if (
			! is_array( $job )
			|| 'local-clone' !== ( $job['operation'] ?? null )
			|| ! is_array( $ownership )
			|| ! is_array( $plan )
			|| 'ready' !== ( $plan['status'] ?? null )
			|| ! is_array( $package )
			|| 'complete' !== ( $package['status'] ?? null )
			|| 'complete' !== ( $package['stage'] ?? null )
			|| ! is_array( $inventory )
			|| 'complete' !== ( $inventory['status'] ?? null )
		) {
			return null;
		}

		$ownership_target = untrailingslashit( wp_normalize_path( (string) $ownership['target_path'] ) );
		$plan_target      = untrailingslashit( wp_normalize_path( (string) ( $plan['target_path'] ?? '' ) ) );

		if (
			! hash_equals( (string) $ownership['plan_hash'], (string) ( $plan['plan_hash'] ?? '' ) )
			|| ! hash_equals( $ownership_target, $plan_target )
			|| ! hash_equals( (string) $ownership['target_url'], (string) ( $plan['target_url'] ?? '' ) )
			|| ! hash_equals( (string) $ownership['target_table_prefix'], (string) ( $plan['target_table_prefix'] ?? '' ) )
			|| ! hash_equals( (string) ( $ownership['package_checksum'] ?? '' ), (string) ( $package['package_checksum'] ?? '' ) )
			|| ! hash_equals( (string) ( $ownership['package_manifest_hash'] ?? '' ), (string) ( $package['package_manifest_hash'] ?? '' ) )
			|| ! hash_equals( (string) ( $ownership['source_fingerprint'] ?? '' ), (string) ( $package['source_fingerprint'] ?? '' ) )
			|| ! hash_equals( (string) ( $ownership['source_fingerprint'] ?? '' ), (string) ( $inventory['fingerprint'] ?? '' ) )
		) {
			return null;
		}

		return array(
			'ownership' => $ownership,
			'plan'      => $plan,
		);
	}

	/**
	 * Ensure a persisted runtime state still matches current authority.
	 *
	 * @param string              $job_id Clone job identifier.
	 * @param array<string,mixed> $state  Runtime state.
	 */
	private function state_authorized( string $job_id, array $state ): bool {
		$authority = $this->runtime_authority( $job_id );
		if ( null === $authority ) {
			return false;
		}

		$ownership = $authority['ownership'];

		return hash_equals( (string) $state['plan_hash'], (string) $ownership['plan_hash'] )
			&& hash_equals( (string) $state['ownership_marker_sha256'], (string) $ownership['marker_sha256'] )
			&& hash_equals( (string) $state['target_path'], (string) $ownership['target_path'] )
			&& hash_equals( (string) $state['target_url'], (string) $ownership['target_url'] )
			&& hash_equals( (string) $state['target_table_prefix'], (string) $ownership['target_table_prefix'] );
	}

	/**
	 * Check the source WordPress core version file identity.
	 *
	 * @param array<string,mixed> $state Runtime state.
	 */
	private function source_version_matches( array $state ): bool {
		$path = $this->join_path( (string) $state['source_root'], 'wp-includes/version.php' );
		$hash = is_file( $path ) && is_readable( $path ) && ! is_link( $path )
			? hash_file( 'sha256', $path )
			: false;

		return false !== $hash && hash_equals( (string) $state['source_version_sha256'], $hash );
	}

	/**
	 * Return accepted source entries for one runtime directory.
	 *
	 * @param string $source_dir  Absolute source directory.
	 * @param string $current_dir Relative current directory.
	 * @return list<string>|null
	 */
	private function source_entries( string $source_dir, string $current_dir ): ?array {
		if ( ! is_dir( $source_dir ) || ! is_readable( $source_dir ) || is_link( $source_dir ) ) {
			return null;
		}

		$entries = scandir( $source_dir, SCANDIR_SORT_ASCENDING );
		if ( false === $entries ) {
			return null;
		}

		$out = array();
		foreach ( $entries as $entry ) {
			if ( '.' === $entry || '..' === $entry ) {
				continue;
			}
			if ( '' === $current_dir && ! $this->allowed_root_entry( $source_dir, $entry ) ) {
				continue;
			}
			$out[] = $entry;
		}

		return $out;
	}

	/**
	 * Return target entries expected to match one source runtime directory.
	 *
	 * @param string $target_dir  Absolute target directory.
	 * @param string $current_dir Relative current directory.
	 * @return list<string>|null
	 */
	private function target_entries( string $target_dir, string $current_dir ): ?array {
		if ( ! is_dir( $target_dir ) || ! is_readable( $target_dir ) || is_link( $target_dir ) ) {
			return null;
		}

		$entries = scandir( $target_dir, SCANDIR_SORT_ASCENDING );
		if ( false === $entries ) {
			return null;
		}

		$out = array();
		foreach ( $entries as $entry ) {
			if ( '.' === $entry || '..' === $entry ) {
				continue;
			}
			if ( '' === $current_dir && LocalCloneBootstrapper::OWNER_MARKER === $entry ) {
				continue;
			}
			$out[] = $entry;
		}

		return $out;
	}

	/**
	 * Whether one root entry belongs to the WordPress core runtime contract.
	 *
	 * @param string $source_root Absolute WordPress source root.
	 * @param string $entry       Root entry name.
	 */
	private function allowed_root_entry( string $source_root, string $entry ): bool {
		$path = $this->join_path( $source_root, $entry );

		if ( is_dir( $path ) ) {
			return in_array( $entry, self::ROOT_DIRECTORIES, true );
		}

		return is_file( $path ) && in_array( $entry, self::ROOT_FILES, true );
	}

	/**
	 * Reject destinations overlapping source wp-admin/wp-includes.
	 *
	 * @param string $source WordPress source root.
	 * @param string $target Local clone target root.
	 */
	private function target_overlaps_core( string $source, string $target ): bool {
		foreach ( self::ROOT_DIRECTORIES as $directory ) {
			$core = trailingslashit( $this->join_path( $source, $directory ) );
			if ( hash_equals( untrailingslashit( $core ), $target ) || str_starts_with( trailingslashit( $target ), $core ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Add one file record to the deterministic runtime fingerprint.
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
	 * @param array<string,mixed> $state  Runtime state.
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
			'bootstrap-runtime',
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
