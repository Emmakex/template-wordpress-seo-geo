<?php
/**
 * Portable Clone local target ownership bootstrap.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge\Clone;

/**
 * Claims/releases only the frozen local-clone destination before runtime copy.
 */
final class LocalCloneBootstrapper {
	public const OWNER_MARKER = '.seo-geo-migration-local-clone-owner.php';

	private const OWNER_CONTRACT = 'seo-geo-local-clone-owner-v1';

	/**
	 * Bootstrap ownership state persistence.
	 *
	 * @var LocalCloneBootstrapStateStore
	 */
	private LocalCloneBootstrapStateStore $store;

	/**
	 * Accepted local-clone destination plan persistence.
	 *
	 * @var LocalCloneStateStore
	 */
	private LocalCloneStateStore $plans;

	/**
	 * Fresh destination safety planner.
	 *
	 * @var DestinationSafetyPlanner
	 */
	private DestinationSafetyPlanner $planner;

	/**
	 * Verified package state persistence.
	 *
	 * @var PackageStateStore
	 */
	private PackageStateStore $packages;

	/**
	 * Source inventory state persistence.
	 *
	 * @var CloneInventoryStore
	 */
	private CloneInventoryStore $inventory;

	/**
	 * Clone job state persistence.
	 *
	 * @var CloneJobStore
	 */
	private CloneJobStore $jobs;

	/**
	 * Portable Clone filesystem mutation authority.
	 *
	 * @var ExportWorkspace
	 */
	private ExportWorkspace $workspace;

	/**
	 * Construct the ownership bootstrapper.
	 *
	 * @param LocalCloneBootstrapStateStore|null $store     Optional bootstrap state store.
	 * @param LocalCloneStateStore|null          $plans     Optional accepted plan store.
	 * @param DestinationSafetyPlanner|null      $planner   Optional destination planner.
	 * @param PackageStateStore|null             $packages  Optional package state store.
	 * @param CloneInventoryStore|null           $inventory Optional inventory state store.
	 * @param CloneJobStore|null                 $jobs      Optional clone job store.
	 * @param ExportWorkspace|null               $workspace Optional filesystem mutation authority.
	 */
	public function __construct(
		?LocalCloneBootstrapStateStore $store = null,
		?LocalCloneStateStore $plans = null,
		?DestinationSafetyPlanner $planner = null,
		?PackageStateStore $packages = null,
		?CloneInventoryStore $inventory = null,
		?CloneJobStore $jobs = null,
		?ExportWorkspace $workspace = null
	) {
		$this->store     = $store ?? new LocalCloneBootstrapStateStore();
		$this->plans     = $plans ?? new LocalCloneStateStore();
		$this->planner   = $planner ?? new DestinationSafetyPlanner();
		$this->packages  = $packages ?? new PackageStateStore();
		$this->inventory = $inventory ?? new CloneInventoryStore();
		$this->jobs      = $jobs ?? new CloneJobStore();
		$this->workspace = $workspace ?? new ExportWorkspace();
	}

	/**
	 * Return one bootstrap ownership state.
	 *
	 * @param string $job_id Clone job identifier.
	 * @return array<string,mixed>|null
	 */
	public function snapshot( string $job_id ): ?array {
		return $this->store->get( $job_id );
	}

	/**
	 * Claim the exact accepted destination with a deterministic recovery marker.
	 *
	 * This is the only filesystem mutation in 10E.2A.5.2.1. No WordPress runtime
	 * files or database objects are created here.
	 *
	 * @param string $job_id Clone job identifier.
	 * @return array<string,mixed>|null
	 */
	public function claim( string $job_id ): ?array {
		$existing = $this->store->get( $job_id );
		if ( is_array( $existing ) ) {
			if ( 'claimed' === ( $existing['status'] ?? null ) ) {
				return $this->marker_matches_state( $existing )
					? $existing
					: $this->block( $job_id, $existing, 'bootstrap-owner-marker-changed' );
			}
			if ( 'claiming' === ( $existing['status'] ?? null ) ) {
				return $this->finish_claim( $job_id, $existing );
			}
			if ( 'released' === ( $existing['status'] ?? null ) ) {
				return $existing;
			}
			if ( 'blocked' === ( $existing['status'] ?? null ) ) {
				$target = untrailingslashit( wp_normalize_path( (string) ( $existing['target_path'] ?? '' ) ) );
				$marker = $this->join_path( $target, self::OWNER_MARKER );
				if ( true === ( $existing['target_owned'] ?? false ) || is_file( $marker ) ) {
					return $existing;
				}
				if ( true === ( $existing['target_created'] ?? false ) && is_dir( $target ) && $this->directory_empty( $target ) ) {
					$existing['status']     = 'claiming';
					$existing['blockers']   = array();
					$existing['updated_at'] = gmdate( DATE_ATOM );
					if ( ! $this->store->save( $job_id, $existing ) ) {
						return null;
					}
					return $this->finish_claim( $job_id, $existing );
				}
				if ( ! $this->store->delete( $job_id ) ) {
					return $existing;
				}
			}
		}

		$plan = $this->fresh_plan( $job_id );
		if ( null === $plan ) {
			return null;
		}

		$target        = untrailingslashit( wp_normalize_path( (string) $plan['target_path'] ) );
		$target_exists = file_exists( $target );
		if ( is_link( $target ) ) {
			return $this->blocked_from_plan( $job_id, $plan, 'bootstrap-target-symlink' );
		}
		if ( $target_exists && ! is_dir( $target ) ) {
			return $this->blocked_from_plan( $job_id, $plan, 'bootstrap-target-not-directory' );
		}

		$planned_exists = true === ( $plan['target_exists'] ?? false );
		if ( $planned_exists !== $target_exists ) {
			return $this->blocked_from_plan( $job_id, $plan, 'bootstrap-target-existence-drift' );
		}
		if ( $target_exists && ! $this->directory_empty( $target ) ) {
			return $this->blocked_from_plan( $job_id, $plan, 'bootstrap-target-not-empty' );
		}

		if ( ! $target_exists ) {
			$parent = dirname( $target );
			if ( ! is_dir( $parent ) || is_link( $parent ) ) {
				return $this->blocked_from_plan( $job_id, $plan, 'bootstrap-target-parent-not-writable' );
			}
		}

		$now   = gmdate( DATE_ATOM );
		$state = array(
			'schema_version'        => LocalCloneBootstrapStateStore::SCHEMA_VERSION,
			'job_id'                => $job_id,
			'status'                => 'claiming',
			'stage'                 => 'target-ownership',
			'plan_hash'             => (string) $plan['plan_hash'],
			'target_path'           => $target,
			'target_url'            => (string) $plan['target_url'],
			'target_table_prefix'   => (string) $plan['target_table_prefix'],
			'package_checksum'      => (string) $plan['package_checksum'],
			'package_manifest_hash' => (string) $plan['package_manifest_hash'],
			'source_fingerprint'    => (string) $plan['source_fingerprint'],
			'target_created'        => ! $target_exists,
			'target_owned'          => false,
			'marker_relative_path'  => self::OWNER_MARKER,
			'marker_sha256'         => '',
			'production_untouched'  => true,
			'database_untouched'    => true,
			'bootstrap_next'        => 'runtime-copy',
			'blockers'              => array(),
			'claimed_at'            => '',
			'released_at'           => '',
			'updated_at'            => $now,
		);

		if ( ! $this->store->save( $job_id, $state ) ) {
			return null;
		}

		return $this->finish_claim( $job_id, $this->store->get( $job_id ) ?? $state );
	}

	/**
	 * Release only the ownership marker created by this microphase.
	 *
	 * A created target is removed only while the marker is its sole entry. A target
	 * that existed empty before the claim is left as an empty directory.
	 *
	 * @param string $job_id Clone job identifier.
	 * @return array<string,mixed>|null
	 */
	public function release( string $job_id ): ?array {
		$state = $this->store->get( $job_id );
		if ( ! is_array( $state ) || 'claimed' !== ( $state['status'] ?? null ) ) {
			return $state;
		}
		if ( ! $this->marker_matches_state( $state ) ) {
			return $this->block( $job_id, $state, 'bootstrap-release-owner-marker-changed' );
		}

		$target  = untrailingslashit( wp_normalize_path( (string) $state['target_path'] ) );
		$entries = scandir( $target, SCANDIR_SORT_ASCENDING );
		if ( false === $entries ) {
			return $this->block( $job_id, $state, 'bootstrap-release-directory-read-failed' );
		}

		$owned_entries = array_values(
			array_filter(
				$entries,
				static fn ( string $entry ): bool => ! in_array( $entry, array( '.', '..', self::OWNER_MARKER ), true )
			)
		);
		if ( array() !== $owned_entries ) {
			return $this->block( $job_id, $state, 'bootstrap-release-target-has-unowned-entries' );
		}

		if ( ! $this->workspace->delete_local_clone_marker( $target, self::OWNER_MARKER, (string) $state['marker_sha256'] ) ) {
			return $this->block( $job_id, $state, 'bootstrap-release-marker-delete-failed' );
		}

		if ( true === ( $state['target_created'] ?? false ) && ! $this->workspace->remove_empty_local_clone_target( $target ) ) {
			return $this->block( $job_id, $state, 'bootstrap-release-target-delete-failed' );
		}

		$state['status']       = 'released';
		$state['target_owned'] = false;
		$state['released_at']  = gmdate( DATE_ATOM );
		$state['updated_at']   = $state['released_at'];
		if ( ! $this->store->save( $job_id, $state ) ) {
			return null;
		}

		$this->jobs->update_progress(
			$job_id,
			'bootstrap',
			'released',
			array(
				'completed' => 0,
				'total'     => 1,
			)
		);

		return $this->store->get( $job_id );
	}

	/**
	 * Finish/recover the target ownership claim after the journal exists.
	 *
	 * @param string              $job_id Clone job identifier.
	 * @param array<string,mixed> $state  Claiming state.
	 * @return array<string,mixed>|null
	 */
	private function finish_claim( string $job_id, array $state ): ?array {
		$plan = $this->plans->get( $job_id );
		if (
			! is_array( $plan )
			|| 'ready' !== ( $plan['status'] ?? null )
			|| ! hash_equals( (string) ( $state['plan_hash'] ?? '' ), (string) ( $plan['plan_hash'] ?? '' ) )
			|| ! $this->package_identity_matches( $plan )
		) {
			return $this->block( $job_id, $state, 'bootstrap-plan-or-package-changed' );
		}

		$target = untrailingslashit( wp_normalize_path( (string) $state['target_path'] ) );
		if ( true === ( $state['target_created'] ?? false ) && ! file_exists( $target ) ) {
			$prepared = $this->workspace->prepare_local_clone_target( $target );
			if ( ! is_array( $prepared ) || true !== ( $prepared['created'] ?? false ) ) {
				return $this->block( $job_id, $state, 'bootstrap-target-create-failed' );
			}
		}

		if ( ! is_dir( $target ) || is_link( $target ) ) {
			return $this->block( $job_id, $state, 'bootstrap-target-unavailable-after-claim' );
		}

		$marker = $this->join_path( $target, self::OWNER_MARKER );
		if ( is_file( $marker ) ) {
			if ( ! $this->marker_matches_state( $state ) ) {
				return $this->block( $job_id, $state, 'bootstrap-owner-marker-collision' );
			}
		} else {
			if ( ! $this->directory_empty( $target ) ) {
				return $this->block( $job_id, $state, 'bootstrap-target-changed-before-marker' );
			}

			$content = $this->marker_content( $state );
			$written = $this->workspace->write_local_clone_marker( $target, self::OWNER_MARKER, $content );
			if ( ! is_array( $written ) ) {
				return $this->block( $job_id, $state, 'bootstrap-owner-marker-write-failed' );
			}
			$state['marker_sha256'] = (string) $written['sha256'];
		}

		if ( '' === (string) ( $state['marker_sha256'] ?? '' ) ) {
			$content = $this->marker_content( $state );
			$hash    = hash_file( 'sha256', $marker );
			if ( false === $hash || ! hash_equals( hash( 'sha256', $content ), $hash ) ) {
				return $this->block( $job_id, $state, 'bootstrap-owner-marker-verify-failed' );
			}
			$state['marker_sha256'] = $hash;
		}

		$state['status']         = 'claimed';
		$state['target_owned']   = true;
		$state['claimed_at']     = '' === (string) ( $state['claimed_at'] ?? '' ) ? gmdate( DATE_ATOM ) : (string) $state['claimed_at'];
		$state['updated_at']     = gmdate( DATE_ATOM );
		$state['blockers']       = array();
		$state['bootstrap_next'] = 'runtime-copy';
		if ( ! $this->store->save( $job_id, $state ) ) {
			return null;
		}

		$this->jobs->transition( $job_id, 'active', null );
		$this->jobs->update_progress(
			$job_id,
			'bootstrap',
			'claimed',
			array(
				'completed' => 1,
				'total'     => 1,
			)
		);

		return $this->store->get( $job_id );
	}

	/**
	 * Revalidate the immutable plan and current package before any filesystem write.
	 *
	 * @param string $job_id Clone job identifier.
	 * @return array<string,mixed>|null
	 */
	private function fresh_plan( string $job_id ): ?array {
		$job       = $this->jobs->get( $job_id );
		$plan      = $this->plans->get( $job_id );
		$package   = $this->packages->get( $job_id );
		$inventory = $this->inventory->get( $job_id );
		if (
			! is_array( $job )
			|| 'local-clone' !== ( $job['operation'] ?? null )
			|| ! is_array( $plan )
			|| 'ready' !== ( $plan['status'] ?? null )
			|| true !== ( $plan['bootstrap_allowed'] ?? false )
			|| true === ( $plan['mutations_performed'] ?? false )
			|| ! is_array( $package )
			|| 'complete' !== ( $package['status'] ?? null )
			|| 'complete' !== ( $package['stage'] ?? null )
			|| ! is_array( $inventory )
			|| 'complete' !== ( $inventory['status'] ?? null )
			|| ! $this->package_identity_matches( $plan )
		) {
			return null;
		}

		$same_database = true === ( $plan['same_database'] ?? false );
		$fresh         = $this->planner->plan(
			(string) $plan['target_path'],
			(string) $plan['target_url'],
			(string) $plan['target_table_prefix'],
			$same_database,
			max( 0, (int) ( $plan['required_bytes'] ?? 0 ) )
		);
		if (
			array() !== ( $fresh['blockers'] ?? array() )
			|| (string) ( $fresh['source_path'] ?? '' ) !== (string) $plan['source_path']
			|| (string) ( $fresh['target_path'] ?? '' ) !== (string) $plan['target_path']
			|| (string) ( $fresh['source_url'] ?? '' ) !== (string) $plan['source_url']
			|| (string) ( $fresh['target_url'] ?? '' ) !== (string) $plan['target_url']
			|| (string) ( $fresh['source_table_prefix'] ?? '' ) !== (string) $plan['source_table_prefix']
			|| (string) ( $fresh['target_table_prefix'] ?? '' ) !== (string) $plan['target_table_prefix']
			|| true !== ( $fresh['payload_roots_exclude_target'] ?? false )
		) {
			return null;
		}

		return $plan;
	}

	/**
	 * Check package/inventory identity against the frozen plan.
	 *
	 * @param array<string,mixed> $plan Frozen destination plan.
	 */
	private function package_identity_matches( array $plan ): bool {
		$job_id    = is_string( $plan['job_id'] ?? null ) ? $plan['job_id'] : '';
		$package   = $this->packages->get( $job_id );
		$inventory = $this->inventory->get( $job_id );

		return is_array( $package )
			&& 'complete' === ( $package['status'] ?? null )
			&& 'complete' === ( $package['stage'] ?? null )
			&& is_array( $inventory )
			&& 'complete' === ( $inventory['status'] ?? null )
			&& hash_equals( (string) ( $plan['package_checksum'] ?? '' ), (string) ( $package['package_checksum'] ?? '' ) )
			&& hash_equals( (string) ( $plan['package_manifest_hash'] ?? '' ), (string) ( $package['package_manifest_hash'] ?? '' ) )
			&& hash_equals( (string) ( $plan['source_fingerprint'] ?? '' ), (string) ( $package['source_fingerprint'] ?? '' ) )
			&& hash_equals( (string) ( $plan['source_fingerprint'] ?? '' ), (string) ( $inventory['fingerprint'] ?? '' ) );
	}

	/**
	 * Persist one blocker starting from an accepted plan.
	 *
	 * @param string              $job_id Clone job identifier.
	 * @param array<string,mixed> $plan   Accepted plan.
	 * @param string              $code   Stable blocker code.
	 * @return array<string,mixed>|null
	 */
	private function blocked_from_plan( string $job_id, array $plan, string $code ): ?array {
		$now   = gmdate( DATE_ATOM );
		$state = array(
			'schema_version'        => LocalCloneBootstrapStateStore::SCHEMA_VERSION,
			'job_id'                => $job_id,
			'status'                => 'blocked',
			'stage'                 => 'target-ownership',
			'plan_hash'             => (string) ( $plan['plan_hash'] ?? '' ),
			'target_path'           => (string) ( $plan['target_path'] ?? '' ),
			'target_url'            => (string) ( $plan['target_url'] ?? '' ),
			'target_table_prefix'   => (string) ( $plan['target_table_prefix'] ?? '' ),
			'package_checksum'      => (string) ( $plan['package_checksum'] ?? '' ),
			'package_manifest_hash' => (string) ( $plan['package_manifest_hash'] ?? '' ),
			'source_fingerprint'    => (string) ( $plan['source_fingerprint'] ?? '' ),
			'target_created'        => false,
			'target_owned'          => false,
			'marker_relative_path'  => self::OWNER_MARKER,
			'marker_sha256'         => '',
			'production_untouched'  => true,
			'database_untouched'    => true,
			'bootstrap_next'        => 'runtime-copy',
			'blockers'              => array( $code ),
			'claimed_at'            => '',
			'released_at'           => '',
			'updated_at'            => $now,
		);

		return $this->store->save( $job_id, $state ) ? $this->block( $job_id, $state, $code ) : null;
	}

	/**
	 * Block the bootstrap state without mutating the target further.
	 *
	 * @param string              $job_id Clone job identifier.
	 * @param array<string,mixed> $state  Bootstrap state.
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
			'bootstrap',
			'blocked',
			array(
				'completed' => 0,
				'total'     => 1,
			)
		);

		return $this->store->get( $job_id );
	}

	/**
	 * Whether the target directory has no entries.
	 *
	 * @param string $target Absolute target directory.
	 */
	private function directory_empty( string $target ): bool {
		if ( ! is_dir( $target ) || is_link( $target ) ) {
			return false;
		}

		$entries = scandir( $target, SCANDIR_SORT_ASCENDING );

		return is_array( $entries ) && array() === array_values( array_diff( $entries, array( '.', '..' ) ) );
	}

	/**
	 * Verify the exact deterministic ownership marker for a state.
	 *
	 * @param array<string,mixed> $state Bootstrap state.
	 */
	private function marker_matches_state( array $state ): bool {
		$target = untrailingslashit( wp_normalize_path( (string) ( $state['target_path'] ?? '' ) ) );
		$marker = $this->join_path( $target, self::OWNER_MARKER );
		if ( ! is_file( $marker ) || ! is_readable( $marker ) || is_link( $marker ) ) {
			return false;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reads only the exact local ownership marker.
		$content = file_get_contents( $marker );
		if ( false === $content ) {
			return false;
		}

		$expected = $this->marker_content( $state );
		$hash     = hash( 'sha256', $content );
		$stored   = (string) ( $state['marker_sha256'] ?? '' );

		return hash_equals( $expected, $content )
			&& ( '' === $stored || hash_equals( $stored, $hash ) );
	}

	/**
	 * Build deterministic PHP ownership marker content without credentials.
	 *
	 * @param array<string,mixed> $state Bootstrap state.
	 */
	private function marker_content( array $state ): string {
		$payload = array(
			'contract'              => self::OWNER_CONTRACT,
			'schema_version'        => LocalCloneBootstrapStateStore::SCHEMA_VERSION,
			'job_id'                => (string) ( $state['job_id'] ?? '' ),
			'plan_hash'             => (string) ( $state['plan_hash'] ?? '' ),
			'target_url'            => (string) ( $state['target_url'] ?? '' ),
			'target_table_prefix'   => (string) ( $state['target_table_prefix'] ?? '' ),
			'package_checksum'      => (string) ( $state['package_checksum'] ?? '' ),
			'package_manifest_hash' => (string) ( $state['package_manifest_hash'] ?? '' ),
			'source_fingerprint'    => (string) ( $state['source_fingerprint'] ?? '' ),
		);
		$json    = wp_json_encode( $payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		if ( ! is_string( $json ) ) {
			$json = '{}';
		}

		$identity = hash( 'sha256', $json );

		return "<?php\n/** SEO/GEO Migration Bridge local-clone ownership marker. */\n/* contract=" . self::OWNER_CONTRACT . '; job=' . (string) ( $state['job_id'] ?? '' ) . '; identity=' . $identity . " */\n";
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
