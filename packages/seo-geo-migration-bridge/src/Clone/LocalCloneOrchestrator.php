<?php
/**
 * Portable Clone local-clone orchestration.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge\Clone;

/**
 * Freezes a verified same-server destination contract before any target mutation.
 */
final class LocalCloneOrchestrator {
	private const PLAN_SEED = 'seo-geo-local-clone-destination-plan-v1';

	/**
	 * Local-clone destination state persistence.
	 *
	 * @var LocalCloneStateStore
	 */
	private LocalCloneStateStore $store;

	/**
	 * Read-only destination safety planner.
	 *
	 * @var DestinationSafetyPlanner
	 */
	private DestinationSafetyPlanner $planner;

	/**
	 * Verified package state persistence.
	 *
	 * @var PackageStateStore
	 */
	private PackageStateStore $package_state;

	/**
	 * Source inventory state persistence.
	 *
	 * @var CloneInventoryStore
	 */
	private CloneInventoryStore $inventory_state;

	/**
	 * Clone job state persistence.
	 *
	 * @var CloneJobStore
	 */
	private CloneJobStore $jobs;

	/**
	 * Construct the destination-plan orchestrator.
	 *
	 * @param LocalCloneStateStore|null     $store           Optional local-clone state store.
	 * @param DestinationSafetyPlanner|null $planner         Optional read-only destination planner.
	 * @param PackageStateStore|null        $package_state   Optional verified package state store.
	 * @param CloneInventoryStore|null      $inventory_state Optional source inventory state store.
	 * @param CloneJobStore|null            $jobs            Optional clone job store.
	 */
	public function __construct(
		?LocalCloneStateStore $store = null,
		?DestinationSafetyPlanner $planner = null,
		?PackageStateStore $package_state = null,
		?CloneInventoryStore $inventory_state = null,
		?CloneJobStore $jobs = null
	) {
		$this->store           = $store ?? new LocalCloneStateStore();
		$this->planner         = $planner ?? new DestinationSafetyPlanner();
		$this->package_state   = $package_state ?? new PackageStateStore();
		$this->inventory_state = $inventory_state ?? new CloneInventoryStore();
		$this->jobs            = $jobs ?? new CloneJobStore();
	}

	/**
	 * Return one local-clone orchestration snapshot.
	 *
	 * @param string $job_id Clone job identifier.
	 * @return array<string,mixed>|null
	 */
	public function snapshot( string $job_id ): ?array {
		return $this->store->get( $job_id );
	}

	/**
	 * Validate and freeze the destination contract without writing the target.
	 *
	 * A ready plan is immutable. Blocked plans may be corrected and prepared again
	 * because no target mutation has occurred yet.
	 *
	 * @param string $job_id              Clone job identifier.
	 * @param string $target_path         Absolute target WordPress path.
	 * @param string $target_url          Target WordPress URL.
	 * @param string $target_table_prefix Isolated target table prefix.
	 * @param bool   $same_database       Whether source/target use one database.
	 * @return array<string,mixed>|null
	 */
	public function prepare(
		string $job_id,
		string $target_path,
		string $target_url,
		string $target_table_prefix,
		bool $same_database = true
	): ?array {
		$existing = $this->store->get( $job_id );
		if ( is_array( $existing ) && 'ready' === ( $existing['status'] ?? null ) ) {
			return $existing;
		}

		$job       = $this->jobs->get( $job_id );
		$package   = $this->package_state->get( $job_id );
		$inventory = $this->inventory_state->get( $job_id );
		if (
			! is_array( $job )
			|| 'local-clone' !== ( $job['operation'] ?? null )
			|| ! is_array( $package )
			|| 'complete' !== ( $package['status'] ?? null )
			|| 'complete' !== ( $package['stage'] ?? null )
			|| ! $this->valid_hash( $package['package_checksum'] ?? null )
			|| ! $this->valid_hash( $package['package_manifest_hash'] ?? null )
			|| ! $this->valid_hash( $package['source_fingerprint'] ?? null )
			|| ! is_array( $inventory )
			|| 'complete' !== ( $inventory['status'] ?? null )
			|| ! hash_equals( (string) $package['source_fingerprint'], (string) ( $inventory['fingerprint'] ?? '' ) )
		) {
			return null;
		}

		$database       = is_array( $inventory['database'] ?? null ) ? $inventory['database'] : array();
		$required_bytes = max(
			(int) ( $package['payload_byte_count'] ?? 0 ),
			(int) ( $inventory['byte_count'] ?? 0 ) + (int) ( $database['estimated_bytes'] ?? 0 )
		);
		$plan           = $this->planner->plan(
			$target_path,
			$target_url,
			$target_table_prefix,
			$same_database,
			$required_bytes
		);

		$blockers   = is_array( $plan['blockers'] ?? null ) ? array_values( $plan['blockers'] ) : array();
		$advisories = is_array( $plan['advisories'] ?? null ) ? array_values( $plan['advisories'] ) : array();

		if ( true === ( $plan['target_exists'] ?? false ) ) {
			if ( false === ( $plan['target_empty'] ?? null ) ) {
				$blockers[] = 'target-directory-not-empty-unowned';
			} elseif ( null === ( $plan['target_empty'] ?? null ) ) {
				$blockers[] = 'target-directory-state-unknown';
			}
		}

		if ( true !== ( $plan['payload_roots_exclude_target'] ?? false ) ) {
			$blockers[] = 'target-overlaps-source-payload';
		}

		$blockers = array_values( array_unique( array_filter( $blockers, 'is_string' ) ) );
		$ready    = array() === $blockers;
		$now      = gmdate( DATE_ATOM );

		$canonical = implode(
			'|',
			array(
				self::PLAN_SEED,
				$job_id,
				(string) ( $plan['source_path'] ?? '' ),
				(string) ( $plan['target_path'] ?? '' ),
				(string) ( $plan['source_url'] ?? '' ),
				(string) ( $plan['target_url'] ?? '' ),
				(string) ( $plan['source_table_prefix'] ?? '' ),
				(string) ( $plan['target_table_prefix'] ?? '' ),
				$same_database ? 'same-db' : 'separate-db',
				(string) $package['package_checksum'],
				(string) $package['package_manifest_hash'],
				(string) $package['source_fingerprint'],
			)
		);

		$state = array(
			'schema_version'               => LocalCloneStateStore::SCHEMA_VERSION,
			'job_id'                       => $job_id,
			'status'                       => $ready ? 'ready' : 'blocked',
			'stage'                        => 'destination-plan',
			'source_path'                  => (string) ( $plan['source_path'] ?? '' ),
			'target_path'                  => (string) ( $plan['target_path'] ?? '' ),
			'source_url'                   => (string) ( $plan['source_url'] ?? '' ),
			'target_url'                   => (string) ( $plan['target_url'] ?? '' ),
			'same_origin'                  => true === ( $plan['same_origin'] ?? false ),
			'nested_under_wordpress_root'  => true === ( $plan['nested_under_wordpress_root'] ?? false ),
			'payload_roots_exclude_target' => true === ( $plan['payload_roots_exclude_target'] ?? false ),
			'same_database'                => $same_database,
			'source_table_prefix'          => (string) ( $plan['source_table_prefix'] ?? '' ),
			'target_table_prefix'          => (string) ( $plan['target_table_prefix'] ?? '' ),
			'target_exists'                => true === ( $plan['target_exists'] ?? false ),
			'target_empty'                 => is_bool( $plan['target_empty'] ?? null ) ? $plan['target_empty'] : null,
			'free_bytes'                   => is_int( $plan['free_bytes'] ?? null ) ? $plan['free_bytes'] : null,
			'required_bytes'               => $required_bytes,
			'package_checksum'             => (string) $package['package_checksum'],
			'package_manifest_hash'        => (string) $package['package_manifest_hash'],
			'source_fingerprint'           => (string) $package['source_fingerprint'],
			'plan_hash'                    => hash( 'sha256', $canonical ),
			'package_verified'             => true,
			'production_source_read_only'  => true,
			'target_owned'                 => false,
			'bootstrap_allowed'            => $ready,
			'mutations_performed'          => false,
			'blockers'                     => $blockers,
			'advisories'                   => array_values( array_unique( array_filter( $advisories, 'is_string' ) ) ),
			'planned_at'                   => $now,
			'updated_at'                   => $now,
		);

		if ( ! $this->store->save( $job_id, $state ) ) {
			return null;
		}

		if ( $ready ) {
			$this->jobs->transition( $job_id, 'active', null );
			$this->jobs->update_progress(
				$job_id,
				'prepare-target',
				'planned',
				array(
					'completed' => 1,
					'total'     => 1,
				)
			);
		} else {
			$this->jobs->transition(
				$job_id,
				'failed-retryable',
				is_string( $blockers[0] ?? null ) ? $blockers[0] : 'local-clone-destination-blocked'
			);
			$this->jobs->update_progress(
				$job_id,
				'prepare-target',
				'blocked',
				array(
					'completed' => 0,
					'total'     => 1,
				)
			);
		}

		return $this->store->get( $job_id );
	}

	/**
	 * Validate one SHA-256 value.
	 *
	 * @param mixed $hash Raw hash.
	 */
	private function valid_hash( mixed $hash ): bool {
		return is_string( $hash ) && 1 === preg_match( '/^[a-f0-9]{64}$/', $hash );
	}
}
