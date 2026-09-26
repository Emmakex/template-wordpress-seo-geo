<?php
/**
 * Portable Clone private same-server target intake and sandbox preflight.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge\Clone;

use wpdb;

/**
 * Binds one verified local-clone handoff to the accepted Portable Import preflight.
 */
final class LocalCloneTargetPreflight {
	/**
	 * Parent preflight state.
	 *
	 * @var LocalCloneTargetPreflightStateStore
	 */
	private LocalCloneTargetPreflightStateStore $store;

	/**
	 * Verified local handoff.
	 *
	 * @var LocalClonePackageHandoff
	 */
	private LocalClonePackageHandoff $handoff;

	/**
	 * Verified sandbox runtime.
	 *
	 * @var LocalCloneSandboxRuntimeBootstrapper
	 */
	private LocalCloneSandboxRuntimeBootstrapper $sandbox_runtime;

	/**
	 * Private archive delivery.
	 *
	 * @var PackageDelivery
	 */
	private PackageDelivery $delivery;

	/**
	 * Existing Portable Import preflight.
	 *
	 * @var ImportPreflight
	 */
	private ImportPreflight $preflight;

	/**
	 * Existing import state.
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
	 * Private workspace.
	 *
	 * @var ExportWorkspace
	 */
	private ExportWorkspace $workspace;

	/**
	 * Construct local target preflight.
	 *
	 * @param LocalCloneTargetPreflightStateStore|null  $store           Optional parent state store.
	 * @param LocalClonePackageHandoff|null             $handoff         Optional verified handoff service.
	 * @param LocalCloneSandboxRuntimeBootstrapper|null $sandbox_runtime Optional sandbox runtime service.
	 * @param PackageDelivery|null                      $delivery        Optional private archive delivery service.
	 * @param ImportPreflight|null                      $preflight       Optional existing Portable Import preflight.
	 * @param ImportStateStore|null                     $import_state    Optional child import state store.
	 * @param CloneJobStore|null                        $jobs            Optional clone job store.
	 * @param ExportWorkspace|null                      $workspace       Optional private workspace.
	 */
	public function __construct(
		?LocalCloneTargetPreflightStateStore $store = null,
		?LocalClonePackageHandoff $handoff = null,
		?LocalCloneSandboxRuntimeBootstrapper $sandbox_runtime = null,
		?PackageDelivery $delivery = null,
		?ImportPreflight $preflight = null,
		?ImportStateStore $import_state = null,
		?CloneJobStore $jobs = null,
		?ExportWorkspace $workspace = null
	) {
		$this->store           = $store ?? new LocalCloneTargetPreflightStateStore();
		$this->handoff         = $handoff ?? new LocalClonePackageHandoff();
		$this->sandbox_runtime = $sandbox_runtime ?? new LocalCloneSandboxRuntimeBootstrapper();
		$this->delivery        = $delivery ?? new PackageDelivery();
		$this->import_state    = $import_state ?? new ImportStateStore();
		$this->jobs            = $jobs ?? new CloneJobStore();
		$this->workspace       = $workspace ?? new ExportWorkspace();
		$this->preflight       = $preflight ?? new ImportPreflight( $this->import_state, $this->jobs, $this->workspace );
	}

	/**
	 * Return one parent preflight state.
	 *
	 * @param string $job_id Parent local-clone job identifier.
	 * @return array<string,mixed>|null
	 */
	public function snapshot( string $job_id ): ?array {
		return $this->store->get( $job_id );
	}

	/**
	 * Return a ready state only while all parent/child authority remains unchanged.
	 *
	 * @param string $job_id Parent local-clone job identifier.
	 * @return array<string,mixed>|null
	 */
	public function verified_snapshot( string $job_id ): ?array {
		$state = $this->store->get( $job_id );
		if (
			! is_array( $state )
			|| 'ready' !== ( $state['status'] ?? null )
			|| true !== ( $state['preflight_ready'] ?? false )
			|| true === ( $state['restore_allowed'] ?? true )
		) {
			return null;
		}

		$authority = $this->authority( $job_id );
		if ( null === $authority || ! $this->state_matches_authority( $state, $authority ) ) {
			return null;
		}

		$child_id         = (string) $state['child_import_job_id'];
		$import           = $this->import_state->get( $child_id );
		$archive          = $this->workspace->import_archive_info( $child_id );
		$child_status     = is_array( $import ) ? (string) ( $import['status'] ?? '' ) : '';
		$preflight_ready  = 'preflight-ready' === $child_status
			&& false === ( $import['full_payload_verified'] ?? false )
			&& false === ( $import['restore_allowed'] ?? false );
		$payload_verified = 'payload-verified' === $child_status
			&& true === ( $import['full_payload_verified'] ?? false )
			&& true === ( $import['restore_allowed'] ?? false );

		if (
			! is_array( $import )
			|| ! $this->child_state_matches_authority( $import, $state, $authority )
			|| ( ! $preflight_ready && ! $payload_verified )
			|| 'private-same-server' !== ( $import['transport'] ?? null )
			|| array() !== ( $import['blockers'] ?? array() )
			|| ! is_array( $archive )
			|| ! hash_equals( (string) $state['import_archive_sha256'], (string) $archive['sha256'] )
			|| (int) $state['import_archive_bytes'] !== (int) $archive['bytes']
			|| ! $this->target_untouched( $state )
		) {
			return null;
		}

		return $state;
	}

	/**
	 * Stage and validate the existing private handoff through Portable Import preflight.
	 *
	 * No database/file restore occurs in this microphase.
	 *
	 * @param string $job_id Parent local-clone job identifier.
	 * @return array<string,mixed>|null
	 */
	public function advance( string $job_id ): ?array {
		$existing = $this->verified_snapshot( $job_id );
		if ( is_array( $existing ) ) {
			return $existing;
		}

		$authority = $this->authority( $job_id );
		if ( null === $authority ) {
			return $this->block( $job_id, $this->store->get( $job_id ) ?? array(), 'local-target-preflight-authority-unavailable' );
		}

		$handoff = $authority['handoff'];
		$sandbox = $authority['sandbox'];
		$archive = $authority['archive'];
		$child   = $this->child_job_id( $job_id );
		$job     = $this->jobs->get( $child );
		if ( ! is_array( $job ) ) {
			$job = $this->jobs->create( 'import', $child );
		}
		if ( ! is_array( $job ) || 'import' !== ( $job['operation'] ?? null ) ) {
			return $this->block( $job_id, $this->store->get( $job_id ) ?? array(), 'local-target-preflight-child-job-invalid' );
		}

		$authority_hash = $this->destination_authority_hash( $handoff, $sandbox );
		$context        = array(
			'parent_job_id'                => $job_id,
			'target_path'                  => (string) $handoff['target_path'],
			'target_url'                   => (string) $handoff['target_url'],
			'target_table_prefix'          => (string) $handoff['target_table_prefix'],
			'archive_sha256'               => (string) $archive['sha256'],
			'archive_bytes'                => (int) $archive['bytes'],
			'package_manifest_sha256'      => (string) $handoff['package_manifest_hash'],
			'package_checksum'             => (string) $handoff['package_checksum'],
			'destination_authority_sha256' => $authority_hash,
		);

		if ( ! $this->target_untouched( $context ) ) {
			return $this->block( $job_id, $this->store->get( $job_id ) ?? array(), 'local-target-preflight-target-mutated' );
		}

		$import = $this->import_state->get( $child );
		if ( is_array( $import ) && ! $this->child_context_matches( $import, $context ) ) {
			return $this->block( $job_id, $this->store->get( $job_id ) ?? array(), 'local-target-preflight-child-authority-drift' );
		}
		if ( ! is_array( $import ) ) {
			$import = $this->preflight->stage_local_handoff( $child, (string) $archive['path'], $context );
		}
		if ( ! is_array( $import ) ) {
			return $this->block( $job_id, $this->store->get( $job_id ) ?? array(), 'local-target-preflight-stage-failed' );
		}

		$import = $this->preflight->validate( $child );
		if ( ! is_array( $import ) ) {
			return $this->block( $job_id, $this->store->get( $job_id ) ?? array(), 'local-target-preflight-validation-failed' );
		}

		$blockers = is_array( $import['blockers'] ?? null ) ? $import['blockers'] : array();
		if (
			'preflight-ready' !== ( $import['status'] ?? null )
			|| array() !== $blockers
			|| true !== ( $import['manifest_contract_valid'] ?? false )
			|| true !== ( $import['child_manifest_hashes_valid'] ?? false )
			|| true === ( $import['restore_allowed'] ?? true )
			|| ! hash_equals( (string) $handoff['archive_sha256'], (string) $import['archive_sha256'] )
			|| ! hash_equals( (string) $handoff['package_manifest_hash'], (string) $import['package_manifest_sha256'] )
			|| ! hash_equals( (string) $handoff['package_checksum'], (string) $import['package_checksum'] )
		) {
			$code = array() !== $blockers && is_string( $blockers[0] ?? null )
				? (string) $blockers[0]
				: 'local-target-preflight-not-ready';

			return $this->block( $job_id, $this->store->get( $job_id ) ?? array(), $code );
		}

		$now   = gmdate( DATE_ATOM );
		$state = array(
			'schema_version'               => LocalCloneTargetPreflightStateStore::SCHEMA_VERSION,
			'job_id'                       => $job_id,
			'status'                       => 'ready',
			'child_import_job_id'          => $child,
			'destination_authority_sha256' => $authority_hash,
			'handoff_archive_sha256'       => (string) $handoff['archive_sha256'],
			'handoff_archive_bytes'        => (int) $handoff['archive_bytes'],
			'package_manifest_sha256'      => (string) $handoff['package_manifest_hash'],
			'package_checksum'             => (string) $handoff['package_checksum'],
			'target_path'                  => (string) $handoff['target_path'],
			'target_url'                   => (string) $handoff['target_url'],
			'target_table_prefix'          => (string) $handoff['target_table_prefix'],
			'import_archive_sha256'        => (string) $import['archive_sha256'],
			'import_archive_bytes'         => (int) $import['archive_bytes'],
			'import_preflight_status'      => (string) $import['status'],
			'preflight_ready'              => true,
			'restore_allowed'              => false,
			'database_untouched'           => true,
			'client_content_untouched'     => true,
			'preflight_next'               => 'payload-extraction',
			'blockers'                     => array(),
			'started_at'                   => (string) ( $this->store->get( $job_id )['started_at'] ?? $now ),
			'updated_at'                   => $now,
			'ready_at'                     => $now,
		);

		if ( ! $this->store->save( $job_id, $state ) ) {
			return null;
		}

		$this->jobs->transition( $job_id, 'active', null );
		$this->jobs->update_progress(
			$job_id,
			'validate',
			'local-target-preflight-ready',
			array(
				'completed' => (int) $import['archive_entry_count'],
				'total'     => (int) $import['archive_entry_count'],
			)
		);

		return $this->store->get( $job_id );
	}

	/**
	 * Resolve current parent authority.
	 *
	 * @param string $job_id Parent job.
	 * @return array{handoff:array<string,mixed>,sandbox:array<string,mixed>,archive:array<string,mixed>}|null
	 */
	private function authority( string $job_id ): ?array {
		$job     = $this->jobs->get( $job_id );
		$handoff = $this->handoff->verified_snapshot( $job_id );
		$sandbox = $this->sandbox_runtime->verified_snapshot( $job_id );
		$archive = $this->delivery->download_info( $job_id );
		if (
			! is_array( $job )
			|| 'local-clone' !== ( $job['operation'] ?? null )
			|| ! is_array( $handoff )
			|| ! is_array( $sandbox )
			|| ! is_array( $archive )
			|| true !== ( $handoff['handoff_ready'] ?? false )
			|| true !== ( $sandbox['sandbox_runtime_ready'] ?? false )
			|| true !== ( $sandbox['sandbox_marker_enabled'] ?? false )
			|| true !== ( $sandbox['storage_isolated'] ?? false )
			|| true !== ( $sandbox['outbound_blocked'] ?? false )
			|| true !== ( $sandbox['search_blocked'] ?? false )
			|| true !== ( $sandbox['target_authorized'] ?? false )
			|| true !== ( $sandbox['backups_ready'] ?? false )
			|| true !== ( $sandbox['database_untouched'] ?? false )
			|| true !== ( $sandbox['client_content_untouched'] ?? false )
			|| (int) $handoff['archive_bytes'] !== (int) $archive['bytes']
			|| ! hash_equals( (string) $handoff['archive_sha256'], (string) $archive['sha256'] )
		) {
			return null;
		}

		return array(
			'handoff' => $handoff,
			'sandbox' => $sandbox,
			'archive' => $archive,
		);
	}

	/**
	 * Confirm stored ready state remains bound to current authority.
	 *
	 * @param array<string,mixed> $state     State.
	 * @param array<string,mixed> $authority Authority.
	 */
	private function state_matches_authority( array $state, array $authority ): bool {
		$handoff = $authority['handoff'];
		$sandbox = $authority['sandbox'];

		return hash_equals( (string) $state['destination_authority_sha256'], $this->destination_authority_hash( $handoff, $sandbox ) )
			&& hash_equals( (string) $state['handoff_archive_sha256'], (string) $handoff['archive_sha256'] )
			&& (int) $state['handoff_archive_bytes'] === (int) $handoff['archive_bytes']
			&& hash_equals( (string) $state['package_manifest_sha256'], (string) $handoff['package_manifest_hash'] )
			&& hash_equals( (string) $state['package_checksum'], (string) $handoff['package_checksum'] )
			&& hash_equals( (string) $state['target_path'], (string) $handoff['target_path'] )
			&& hash_equals( (string) $state['target_url'], (string) $handoff['target_url'] )
			&& hash_equals( (string) $state['target_table_prefix'], (string) $handoff['target_table_prefix'] );
	}

	/**
	 * Hash the exact verified destination authority used by the child import.
	 *
	 * @param array<string,mixed> $handoff Handoff.
	 * @param array<string,mixed> $sandbox Sandbox.
	 */
	private function destination_authority_hash( array $handoff, array $sandbox ): string {
		return hash(
			'sha256',
			implode(
				"\n",
				array(
					(string) $handoff['plan_hash'],
					(string) $handoff['ownership_marker_sha256'],
					(string) $sandbox['wp_config_sha256'],
					(string) $sandbox['mu_plugin_sha256'],
					(string) $handoff['target_path'],
					(string) $handoff['target_url'],
					(string) $handoff['target_table_prefix'],
					(string) $handoff['package_manifest_hash'],
					(string) $handoff['package_checksum'],
					(string) $handoff['archive_sha256'],
				)
			)
		);
	}


	/**
	 * Confirm one persisted child import state still matches its parent-ready state and live authority.
	 *
	 * @param array<string,mixed> $import    Child import state.
	 * @param array<string,mixed> $state     Parent target-preflight state.
	 * @param array<string,mixed> $authority Live parent authority.
	 */
	private function child_state_matches_authority( array $import, array $state, array $authority ): bool {
		$handoff = $authority['handoff'];
		$sandbox = $authority['sandbox'];

		return 'private-same-server' === ( $import['transport'] ?? null )
			&& hash_equals( (string) ( $import['local_handoff_parent_job_id'] ?? '' ), (string) $state['job_id'] )
			&& hash_equals( (string) ( $import['destination_root_path'] ?? '' ), (string) $handoff['target_path'] )
			&& hash_equals( (string) ( $import['destination_home_url'] ?? '' ), (string) $handoff['target_url'] )
			&& hash_equals( (string) ( $import['destination_site_url'] ?? '' ), (string) $handoff['target_url'] )
			&& hash_equals( (string) ( $import['destination_table_prefix'] ?? '' ), (string) $handoff['target_table_prefix'] )
			&& hash_equals(
				(string) ( $import['destination_authority_sha256'] ?? '' ),
				$this->destination_authority_hash( $handoff, $sandbox )
			)
			&& hash_equals( (string) ( $import['expected_package_manifest_sha256'] ?? '' ), (string) $handoff['package_manifest_hash'] )
			&& hash_equals( (string) ( $import['expected_package_checksum'] ?? '' ), (string) $handoff['package_checksum'] );
	}

	/**
	 * Confirm an existing staged child state matches the exact fresh context before revalidation.
	 *
	 * @param array<string,mixed> $import  Child import state.
	 * @param array<string,mixed> $context Fresh verified destination context.
	 */
	private function child_context_matches( array $import, array $context ): bool {
		return 'private-same-server' === ( $import['transport'] ?? null )
			&& hash_equals( (string) ( $import['local_handoff_parent_job_id'] ?? '' ), (string) $context['parent_job_id'] )
			&& hash_equals( (string) ( $import['destination_root_path'] ?? '' ), (string) $context['target_path'] )
			&& hash_equals( (string) ( $import['destination_home_url'] ?? '' ), (string) $context['target_url'] )
			&& hash_equals( (string) ( $import['destination_site_url'] ?? '' ), (string) $context['target_url'] )
			&& hash_equals( (string) ( $import['destination_table_prefix'] ?? '' ), (string) $context['target_table_prefix'] )
			&& hash_equals( (string) ( $import['destination_authority_sha256'] ?? '' ), (string) $context['destination_authority_sha256'] )
			&& hash_equals( (string) ( $import['expected_package_manifest_sha256'] ?? '' ), (string) $context['package_manifest_sha256'] )
			&& hash_equals( (string) ( $import['expected_package_checksum'] ?? '' ), (string) $context['package_checksum'] )
			&& hash_equals( (string) ( $import['archive_sha256'] ?? '' ), (string) $context['archive_sha256'] )
			&& (int) ( $import['archive_bytes'] ?? -1 ) === (int) $context['archive_bytes'];
	}

	/**
	 * Confirm no target table or client-content restore has begun.
	 *
	 * @param array<string,mixed> $state State/context carrying target path/prefix.
	 */
	private function target_untouched( array $state ): bool {
		global $wpdb;

		$prefix = (string) ( $state['target_table_prefix'] ?? '' );
		$target = untrailingslashit( wp_normalize_path( (string) ( $state['target_path'] ?? '' ) ) );
		if (
			! $wpdb instanceof wpdb
			|| 1 !== preg_match( '/^[A-Za-z0-9_]+$/', $prefix )
			|| '' === $target
			|| ! is_dir( $target )
			|| is_link( $target )
		) {
			return false;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Read-only isolation guard must inspect the exact target prefix before restore.
		$tables = $wpdb->get_col(
			$wpdb->prepare(
				'SHOW TABLES LIKE %s',
				$wpdb->esc_like( $prefix ) . '%'
			)
		);
		if ( array() !== $tables ) {
			return false;
		}

		return ! file_exists( $target . '/wp-content/uploads' )
			&& ! file_exists( $target . '/wp-content/themes' );
	}

	/**
	 * Deterministic child import job identifier.
	 *
	 * @param string $job_id Parent job.
	 */
	private function child_job_id( string $job_id ): string {
		return 'local-intake-' . substr( hash( 'sha256', $job_id ), 0, 32 );
	}

	/**
	 * Persist one retryable blocker.
	 *
	 * @param string              $job_id Parent job.
	 * @param array<string,mixed> $state  Current state.
	 * @param string              $code   Stable blocker.
	 * @return array<string,mixed>|null
	 */
	private function block( string $job_id, array $state, string $code ): ?array {
		$now        = gmdate( DATE_ATOM );
		$blockers   = is_array( $state['blockers'] ?? null ) ? $state['blockers'] : array();
		$blockers[] = $code;

		$state['schema_version']  = LocalCloneTargetPreflightStateStore::SCHEMA_VERSION;
		$state['job_id']          = $job_id;
		$state['status']          = 'blocked';
		$state['preflight_ready'] = false;
		$state['restore_allowed'] = false;
		$state['blockers']        = array_values( array_unique( array_filter( $blockers, 'is_string' ) ) );
		$state['started_at']      = (string) ( $state['started_at'] ?? $now );
		$state['updated_at']      = $now;

		if ( ! $this->store->save( $job_id, $state ) ) {
			return null;
		}

		$this->jobs->transition( $job_id, 'failed-retryable', $code );

		return $this->store->get( $job_id );
	}
}
