<?php
/**
 * Portable Clone private same-server package handoff.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge\Clone;

/**
 * Builds and freezes a private local handoff ZIP without mutating the package manifest.
 */
final class LocalClonePackageHandoff {
	public const DEFAULT_BATCH_FILES = 100;
	public const DEFAULT_BATCH_BYTES = 16777216;

	/**
	 * Handoff state store.
	 *
	 * @var LocalClonePackageHandoffStateStore
	 */
	private LocalClonePackageHandoffStateStore $store;

	/**
	 * Verified target ownership.
	 *
	 * @var LocalCloneBootstrapper
	 */
	private LocalCloneBootstrapper $ownership;

	/**
	 * Verified isolated sandbox runtime.
	 *
	 * @var LocalCloneSandboxRuntimeBootstrapper
	 */
	private LocalCloneSandboxRuntimeBootstrapper $sandbox_runtime;

	/**
	 * Package state.
	 *
	 * @var PackageStateStore
	 */
	private PackageStateStore $packages;

	/**
	 * Reused private ZIP builder.
	 *
	 * @var PackageDelivery
	 */
	private PackageDelivery $delivery;

	/**
	 * Clone job state.
	 *
	 * @var CloneJobStore
	 */
	private CloneJobStore $jobs;

	/**
	 * Construct local package handoff.
	 *
	 * @param LocalClonePackageHandoffStateStore|null   $store           Optional handoff state.
	 * @param LocalCloneBootstrapper|null               $ownership       Optional ownership service.
	 * @param LocalCloneSandboxRuntimeBootstrapper|null $sandbox_runtime Optional sandbox runtime.
	 * @param PackageStateStore|null                    $packages        Optional package state.
	 * @param PackageDelivery|null                      $delivery        Optional private ZIP builder.
	 * @param CloneJobStore|null                        $jobs            Optional clone job store.
	 */
	public function __construct(
		?LocalClonePackageHandoffStateStore $store = null,
		?LocalCloneBootstrapper $ownership = null,
		?LocalCloneSandboxRuntimeBootstrapper $sandbox_runtime = null,
		?PackageStateStore $packages = null,
		?PackageDelivery $delivery = null,
		?CloneJobStore $jobs = null
	) {
		$this->store           = $store ?? new LocalClonePackageHandoffStateStore();
		$this->ownership       = $ownership ?? new LocalCloneBootstrapper();
		$this->sandbox_runtime = $sandbox_runtime ?? new LocalCloneSandboxRuntimeBootstrapper();
		$this->packages        = $packages ?? new PackageStateStore();
		$this->delivery        = $delivery ?? new PackageDelivery();
		$this->jobs            = $jobs ?? new CloneJobStore();
	}

	/**
	 * Return one handoff state.
	 *
	 * @param string $job_id Clone job identifier.
	 * @return array<string,mixed>|null
	 */
	public function snapshot( string $job_id ): ?array {
		return $this->store->get( $job_id );
	}

	/**
	 * Return a ready handoff only while upstream authority and archive identity still match.
	 *
	 * @param string $job_id Clone job identifier.
	 * @return array<string,mixed>|null
	 */
	public function verified_snapshot( string $job_id ): ?array {
		$state = $this->store->get( $job_id );
		if (
			! is_array( $state )
			|| 'ready' !== ( $state['status'] ?? null )
			|| true !== ( $state['handoff_ready'] ?? false )
			|| ! $this->state_authorized( $job_id, $state )
		) {
			return null;
		}

		$archive = $this->delivery->download_info( $job_id );
		if (
			! is_array( $archive )
			|| (int) $state['archive_bytes'] !== (int) $archive['bytes']
			|| ! hash_equals( (string) $state['archive_sha256'], (string) $archive['sha256'] )
		) {
			return null;
		}

		return $state;
	}

	/**
	 * Start a private same-server handoff archive.
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

		$delivery = $this->delivery->start( $job_id );
		if ( ! is_array( $delivery ) ) {
			return null;
		}

		$package   = $authority['package'];
		$ownership = $authority['ownership'];
		$sandbox   = $authority['sandbox'];
		$now       = gmdate( DATE_ATOM );
		$state     = array(
			'schema_version'          => LocalClonePackageHandoffStateStore::SCHEMA_VERSION,
			'job_id'                  => $job_id,
			'status'                  => 'building',
			'transport'               => 'private-same-server',
			'plan_hash'               => (string) $ownership['plan_hash'],
			'ownership_marker_sha256' => (string) $ownership['marker_sha256'],
			'sandbox_config_sha256'   => (string) $sandbox['wp_config_sha256'],
			'sandbox_mu_sha256'       => (string) $sandbox['mu_plugin_sha256'],
			'package_checksum'        => (string) $package['package_checksum'],
			'package_manifest_hash'   => (string) $package['package_manifest_hash'],
			'target_path'             => (string) $ownership['target_path'],
			'target_url'              => (string) $ownership['target_url'],
			'target_table_prefix'     => (string) $ownership['target_table_prefix'],
			'archive_sha256'          => '',
			'archive_bytes'           => 0,
			'handoff_ready'           => false,
			'handoff_next'            => 'build-private-archive',
			'blockers'                => array(),
			'started_at'              => $now,
			'updated_at'              => $now,
			'ready_at'                => '',
		);

		if ( ! $this->store->save( $job_id, $state ) ) {
			return null;
		}

		$this->jobs->transition( $job_id, 'active', null );
		$this->jobs->update_progress(
			$job_id,
			'integrity',
			'local-handoff-building',
			array(
				'completed' => 0,
				'total'     => null,
			)
		);

		return $this->store->get( $job_id );
	}

	/**
	 * Advance one bounded private handoff ZIP batch.
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
		$state = $this->store->get( $job_id ) ?? $this->start( $job_id );
		if ( ! is_array( $state ) || in_array( $state['status'] ?? null, array( 'ready', 'blocked' ), true ) ) {
			return $state;
		}
		if ( ! $this->state_authorized( $job_id, $state ) ) {
			return $this->block( $job_id, $state, 'local-handoff-authority-drift' );
		}

		$delivery = $this->delivery->advance( $job_id, $batch_files, $batch_bytes );
		if ( ! is_array( $delivery ) ) {
			return $this->block( $job_id, $state, 'local-handoff-archive-build-failed' );
		}

		$status = (string) ( $delivery['status'] ?? '' );
		if ( in_array( $status, array( 'blocked', 'expired', 'cleaning', 'cleaned' ), true ) ) {
			return $this->block( $job_id, $state, 'local-handoff-archive-unavailable' );
		}

		if ( 'ready' !== $status ) {
			$state['updated_at']   = gmdate( DATE_ATOM );
			$state['handoff_next'] = 'build-private-archive';
			if ( ! $this->store->save( $job_id, $state ) ) {
				return null;
			}

			return $this->store->get( $job_id );
		}

		$archive = $this->delivery->download_info( $job_id );
		if ( ! is_array( $archive ) ) {
			return $this->block( $job_id, $state, 'local-handoff-archive-identity-invalid' );
		}

		$state['status']         = 'ready';
		$state['archive_sha256'] = (string) $archive['sha256'];
		$state['archive_bytes']  = (int) $archive['bytes'];
		$state['handoff_ready']  = true;
		$state['handoff_next']   = 'target-intake-preflight';
		$state['ready_at']       = gmdate( DATE_ATOM );
		$state['updated_at']     = $state['ready_at'];
		$state['blockers']       = array();

		if ( ! $this->store->save( $job_id, $state ) ) {
			return null;
		}

		$this->jobs->transition( $job_id, 'active', null );
		$this->jobs->update_progress(
			$job_id,
			'verify',
			'local-handoff-ready',
			array(
				'completed' => (int) $archive['bytes'],
				'total'     => (int) $archive['bytes'],
			)
		);

		return $this->store->get( $job_id );
	}

	/**
	 * Resolve immutable upstream authority for the handoff.
	 *
	 * @param string $job_id Clone job identifier.
	 * @return array{ownership:array<string,mixed>,sandbox:array<string,mixed>,package:array<string,mixed>}|null
	 */
	private function authority( string $job_id ): ?array {
		$job       = $this->jobs->get( $job_id );
		$ownership = $this->ownership->verified_snapshot( $job_id );
		$sandbox   = $this->sandbox_runtime->verified_snapshot( $job_id );
		$package   = $this->packages->get( $job_id );
		if (
			! is_array( $job )
			|| 'local-clone' !== ( $job['operation'] ?? null )
			|| ! is_array( $ownership )
			|| ! is_array( $sandbox )
			|| ! is_array( $package )
			|| 'complete' !== ( $package['status'] ?? null )
			|| 'complete' !== ( $package['stage'] ?? null )
			|| true !== ( $sandbox['sandbox_runtime_ready'] ?? false )
		) {
			return null;
		}

		if (
			! hash_equals( (string) $ownership['package_checksum'], (string) ( $package['package_checksum'] ?? '' ) )
			|| ! hash_equals( (string) $ownership['package_manifest_hash'], (string) ( $package['package_manifest_hash'] ?? '' ) )
			|| ! hash_equals( (string) $ownership['plan_hash'], (string) ( $sandbox['plan_hash'] ?? '' ) )
			|| ! hash_equals( (string) $ownership['marker_sha256'], (string) ( $sandbox['ownership_marker_sha256'] ?? '' ) )
		) {
			return null;
		}

		return array(
			'ownership' => $ownership,
			'sandbox'   => $sandbox,
			'package'   => $package,
		);
	}

	/**
	 * Verify stored state against current upstream authority.
	 *
	 * @param string              $job_id Clone job identifier.
	 * @param array<string,mixed> $state  Handoff state.
	 */
	private function state_authorized( string $job_id, array $state ): bool {
		$authority = $this->authority( $job_id );
		if ( null === $authority ) {
			return false;
		}

		$ownership = $authority['ownership'];
		$sandbox   = $authority['sandbox'];
		$package   = $authority['package'];
		$target    = untrailingslashit( wp_normalize_path( (string) $state['target_path'] ) );
		$current   = untrailingslashit( wp_normalize_path( (string) $ownership['target_path'] ) );

		return hash_equals( (string) $state['plan_hash'], (string) $ownership['plan_hash'] )
			&& hash_equals( (string) $state['ownership_marker_sha256'], (string) $ownership['marker_sha256'] )
			&& hash_equals( (string) $state['sandbox_config_sha256'], (string) $sandbox['wp_config_sha256'] )
			&& hash_equals( (string) $state['sandbox_mu_sha256'], (string) $sandbox['mu_plugin_sha256'] )
			&& hash_equals( (string) $state['package_checksum'], (string) $package['package_checksum'] )
			&& hash_equals( (string) $state['package_manifest_hash'], (string) $package['package_manifest_hash'] )
			&& hash_equals( $target, $current )
			&& hash_equals( (string) $state['target_url'], (string) $ownership['target_url'] )
			&& hash_equals( (string) $state['target_table_prefix'], (string) $ownership['target_table_prefix'] );
	}

	/**
	 * Persist one blocker.
	 *
	 * @param string              $job_id Clone job identifier.
	 * @param array<string,mixed> $state  Handoff state.
	 * @param string              $code   Stable blocker.
	 * @return array<string,mixed>|null
	 */
	private function block( string $job_id, array $state, string $code ): ?array {
		$blockers   = is_array( $state['blockers'] ?? null ) ? $state['blockers'] : array();
		$blockers[] = $code;

		$state['status']        = 'blocked';
		$state['handoff_ready'] = false;
		$state['blockers']      = array_values( array_unique( array_filter( $blockers, 'is_string' ) ) );
		$state['updated_at']    = gmdate( DATE_ATOM );

		if ( ! $this->store->save( $job_id, $state ) ) {
			return null;
		}

		$this->jobs->transition( $job_id, 'failed-retryable', $code );

		return $this->store->get( $job_id );
	}
}
