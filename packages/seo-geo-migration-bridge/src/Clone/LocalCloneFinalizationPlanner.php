<?php
/**
 * Portable Clone local guarded finalization plan.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge\Clone;

/**
 * Reuses Portable Import finalization fingerprints under local-clone parent authority.
 */
final class LocalCloneFinalizationPlanner {
	/**
	 * Parent finalization state.
	 *
	 * @var LocalCloneFinalizationPlanStateStore
	 */
	private LocalCloneFinalizationPlanStateStore $store;

	/**
	 * Verified local environment rewrite.
	 *
	 * @var LocalCloneEnvironmentRewriter
	 */
	private LocalCloneEnvironmentRewriter $rewrite;

	/**
	 * Existing Portable Import finalization planner.
	 *
	 * @var ImportFinalizationPlanner
	 */
	private ImportFinalizationPlanner $planner;

	/**
	 * Child finalization state.
	 *
	 * @var ImportFinalizeStateStore
	 */
	private ImportFinalizeStateStore $finalize_state;

	/**
	 * Child import state.
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
	 * Construct local finalization planner.
	 *
	 * @param LocalCloneFinalizationPlanStateStore|null $store          Optional parent state store.
	 * @param LocalCloneEnvironmentRewriter|null        $rewrite        Optional verified local rewrite service.
	 * @param ImportFinalizationPlanner|null            $planner        Optional child finalization planner.
	 * @param ImportFinalizeStateStore|null             $finalize_state Optional child finalization state.
	 * @param ImportStateStore|null                     $import_state   Optional child import state.
	 * @param CloneJobStore|null                        $jobs           Optional clone jobs.
	 */
	public function __construct(
		?LocalCloneFinalizationPlanStateStore $store = null,
		?LocalCloneEnvironmentRewriter $rewrite = null,
		?ImportFinalizationPlanner $planner = null,
		?ImportFinalizeStateStore $finalize_state = null,
		?ImportStateStore $import_state = null,
		?CloneJobStore $jobs = null
	) {
		$this->store          = $store ?? new LocalCloneFinalizationPlanStateStore();
		$this->rewrite        = $rewrite ?? new LocalCloneEnvironmentRewriter();
		$this->planner        = $planner ?? new ImportFinalizationPlanner();
		$this->finalize_state = $finalize_state ?? new ImportFinalizeStateStore();
		$this->import_state   = $import_state ?? new ImportStateStore();
		$this->jobs           = $jobs ?? new CloneJobStore();
	}

	/**
	 * Return one parent finalization snapshot.
	 *
	 * @param string $job_id Parent local-clone job identifier.
	 * @return array<string,mixed>|null
	 */
	public function snapshot( string $job_id ): ?array {
		return $this->store->get( $job_id );
	}

	/**
	 * Return completed finalization only while the frozen activation plan remains valid.
	 *
	 * @param string $job_id Parent local-clone job identifier.
	 * @return array<string,mixed>|null
	 */
	public function verified_snapshot( string $job_id ): ?array {
		$state = $this->store->get( $job_id );
		if (
			! is_array( $state )
			|| 'ready' !== ( $state['status'] ?? null )
			|| 'ready' !== ( $state['stage'] ?? null )
			|| true !== ( $state['sandbox_hardening_ready'] ?? false )
			|| true !== ( $state['rollback_plan_ready'] ?? false )
			|| true !== ( $state['activation_plan_ready'] ?? false )
			|| true !== ( $state['target_unactivated'] ?? false )
			|| array() !== ( $state['blockers'] ?? array() )
		) {
			return null;
		}

		$authority = $this->authority( $job_id, false );
		if ( null === $authority || ! $this->state_matches_authority( $state, $authority ) ) {
			return null;
		}

		$child_id = (string) $state['child_import_job_id'];
		$child    = $this->finalize_state->get( $child_id );
		$plan     = $this->planner->activation_plan_snapshot( $child_id );
		if (
			! is_array( $child )
			|| 'ready' !== ( $child['status'] ?? null )
			|| 'ready' !== ( $child['stage'] ?? null )
			|| true !== ( $child['sandbox_hardening_ready'] ?? false )
			|| true !== ( $child['rollback_plan_ready'] ?? false )
			|| true !== ( $child['activation_allowed'] ?? false )
			|| true === ( $child['handoff_ready'] ?? true )
			|| true !== ( $child['active_tables_untouched'] ?? false )
			|| true !== ( $child['active_roots_untouched'] ?? false )
			|| array() !== ( $child['blockers'] ?? array() )
			|| ! is_array( $plan )
			|| ! hash_equals( (string) $state['activation_plan_hash'], (string) ( $plan['hash'] ?? '' ) )
			|| ! hash_equals( (string) $state['database_fingerprint'], (string) ( $plan['database_fingerprint'] ?? '' ) )
			|| ! hash_equals( (string) $state['file_fingerprint'], (string) ( $plan['file_fingerprint'] ?? '' ) )
			|| ! $this->local_plan_valid( $plan['plan'] ?? null, $authority['import'] )
		) {
			return null;
		}

		return $state;
	}

	/**
	 * Return the immutable child activation plan after local parent verification.
	 *
	 * @param string $job_id Parent local-clone job identifier.
	 * @return array{plan:array<string,mixed>,hash:string,database_fingerprint:string,file_fingerprint:string}|null
	 */
	public function activation_plan_snapshot( string $job_id ): ?array {
		$state = $this->verified_snapshot( $job_id );
		if ( ! is_array( $state ) ) {
			return null;
		}

		$plan = $this->planner->activation_plan_snapshot( (string) $state['child_import_job_id'] );
		if ( ! is_array( $plan ) ) {
			return null;
		}

		return $plan;
	}

	/**
	 * Advance one bounded finalization fingerprint batch.
	 *
	 * @param string $job_id      Parent local-clone job identifier.
	 * @param int    $batch_rows  Maximum database rows hashed.
	 * @param int    $batch_files Maximum files hashed.
	 * @param int    $batch_bytes Soft maximum bytes hashed.
	 * @return array<string,mixed>|null
	 */
	public function advance(
		string $job_id,
		int $batch_rows = ImportFinalizationPlanner::DEFAULT_BATCH_ROWS,
		int $batch_files = ImportFinalizationPlanner::DEFAULT_BATCH_FILES,
		int $batch_bytes = ImportFinalizationPlanner::DEFAULT_BATCH_BYTES
	): ?array {
		$ready = $this->verified_snapshot( $job_id );
		if ( is_array( $ready ) ) {
			$this->refresh_parent_progress( $job_id, $ready );

			return $ready;
		}

		$existing  = $this->store->get( $job_id );
		$authority = $this->authority( $job_id, true );
		if ( null === $authority ) {
			$this->lock_child_from_parent_state( $existing, 'local-finalize-parent-authority-unavailable' );

			return $this->block( $job_id, $existing ?? array(), 'local-finalize-parent-authority-unavailable' );
		}

		$child_id = (string) $authority['rewrite']['child_import_job_id'];
		$child    = $this->planner->advance( $child_id, $batch_rows, $batch_files, $batch_bytes );
		if ( ! is_array( $child ) ) {
			return $this->block( $job_id, $existing ?? array(), 'local-finalize-service-unavailable' );
		}

		if ( 'blocked' === ( $child['status'] ?? null ) ) {
			$blockers = is_array( $child['blockers'] ?? null ) ? $child['blockers'] : array();
			$code     = is_string( $blockers[0] ?? null ) ? (string) $blockers[0] : 'local-finalize-child-blocked';

			return $this->block( $job_id, $existing ?? array(), $code );
		}

		$fresh = $this->authority( $job_id, false );
		if ( null === $fresh ) {
			$this->lock_child( $child_id, 'local-finalize-parent-authority-changed' );

			return $this->block( $job_id, $existing ?? array(), 'local-finalize-parent-authority-changed' );
		}

		if (
			true !== ( $child['active_tables_untouched'] ?? false )
			|| true !== ( $child['active_roots_untouched'] ?? false )
			|| ! hash_equals(
				(string) $fresh['rewrite']['database_manifest_sha256'],
				(string) ( $child['database_manifest_sha256'] ?? '' )
			)
			|| ! hash_equals(
				(string) $fresh['rewrite']['file_manifest_sha256'],
				(string) ( $child['files_manifest_sha256'] ?? '' )
			)
			|| ! hash_equals(
				(string) $fresh['rewrite']['archive_sha256'],
				(string) ( $child['payload_archive_sha256'] ?? '' )
			)
		) {
			$this->lock_child( $child_id, 'local-finalize-isolation-guard-failed' );

			return $this->block( $job_id, $existing ?? array(), 'local-finalize-isolation-guard-failed' );
		}

		$complete = 'ready' === ( $child['status'] ?? null ) && 'ready' === ( $child['stage'] ?? null );
		$plan     = $complete ? $this->planner->activation_plan_snapshot( $child_id ) : null;
		if (
			$complete
			&& (
				true !== ( $child['sandbox_hardening_ready'] ?? false )
				|| true !== ( $child['rollback_plan_ready'] ?? false )
				|| true !== ( $child['activation_allowed'] ?? false )
				|| true === ( $child['handoff_ready'] ?? true )
				|| ! is_array( $plan )
				|| ! $this->local_plan_valid( $plan['plan'] ?? null, $fresh['import'] )
			)
		) {
			return $this->block( $job_id, $existing ?? array(), 'local-finalize-plan-verification-failed' );
		}

		$now   = gmdate( DATE_ATOM );
		$state = array(
			'schema_version'               => LocalCloneFinalizationPlanStateStore::SCHEMA_VERSION,
			'job_id'                       => $job_id,
			'status'                       => $complete ? 'ready' : 'running',
			'stage'                        => $complete ? 'ready' : (string) ( $child['stage'] ?? 'database-fingerprint' ),
			'child_import_job_id'          => $child_id,
			'destination_authority_sha256' => (string) $fresh['rewrite']['destination_authority_sha256'],
			'archive_sha256'               => (string) $fresh['rewrite']['archive_sha256'],
			'package_manifest_sha256'      => (string) $fresh['rewrite']['package_manifest_sha256'],
			'package_checksum'             => (string) $fresh['rewrite']['package_checksum'],
			'database_manifest_sha256'     => (string) $fresh['rewrite']['database_manifest_sha256'],
			'file_manifest_sha256'         => (string) $fresh['rewrite']['file_manifest_sha256'],
			'database_fingerprint'         => $complete ? (string) ( $plan['database_fingerprint'] ?? '' ) : (string) ( $child['database_fingerprint'] ?? '' ),
			'file_fingerprint'             => $complete ? (string) ( $plan['file_fingerprint'] ?? '' ) : (string) ( $child['file_fingerprint'] ?? '' ),
			'activation_plan_hash'         => $complete ? (string) ( $plan['hash'] ?? '' ) : (string) ( $child['activation_plan_hash'] ?? '' ),
			'database_rows_hashed'         => (int) ( $child['database_rows_hashed'] ?? 0 ),
			'database_table_count'         => (int) ( $child['database_table_count'] ?? 0 ),
			'files_hashed'                 => (int) ( $child['files_hashed'] ?? 0 ),
			'file_bytes_hashed'            => (int) ( $child['file_bytes_hashed'] ?? 0 ),
			'expected_file_count'          => (int) ( $child['expected_file_count'] ?? 0 ),
			'expected_file_bytes'          => (int) ( $child['expected_file_bytes'] ?? 0 ),
			'sandbox_hardening_ready'      => true === ( $child['sandbox_hardening_ready'] ?? false ),
			'rollback_plan_ready'          => true === ( $child['rollback_plan_ready'] ?? false ),
			'activation_plan_ready'        => $complete,
			'target_unactivated'           => true,
			'finalization_next'            => $complete ? 'database-activation' : 'finalization-plan',
			'blockers'                     => array(),
			'advisories'                   => is_array( $child['advisories'] ?? null ) ? $child['advisories'] : array(),
			'started_at'                   => (string) ( $existing['started_at'] ?? $now ),
			'updated_at'                   => $now,
			'ready_at'                     => $complete ? $now : '',
		);

		if ( ! $this->store->save( $job_id, $state ) ) {
			return null;
		}

		$this->refresh_parent_progress( $job_id, $state );

		return $this->store->get( $job_id );
	}

	/**
	 * Resolve current local rewrite + child import authority.
	 *
	 * @param string $job_id          Parent local-clone job identifier.
	 * @param bool   $attempt_recover Whether to retry lower local authority.
	 * @return array{rewrite:array<string,mixed>,import:array<string,mixed>}|null
	 */
	private function authority( string $job_id, bool $attempt_recover ): ?array {
		$rewrite = $this->rewrite->verified_snapshot( $job_id );
		if ( ! is_array( $rewrite ) && $attempt_recover ) {
			$this->rewrite->advance( $job_id );
			$rewrite = $this->rewrite->verified_snapshot( $job_id );
		}
		if ( ! is_array( $rewrite ) ) {
			return null;
		}

		$child_id = (string) ( $rewrite['child_import_job_id'] ?? '' );
		$import   = '' !== $child_id ? $this->import_state->get( $child_id ) : null;
		if (
			! is_array( $import )
			|| 'payload-verified' !== ( $import['status'] ?? null )
			|| 'private-same-server' !== ( $import['transport'] ?? null )
			|| (string) ( $import['local_handoff_parent_job_id'] ?? '' ) !== $job_id
			|| true !== ( $import['full_payload_verified'] ?? false )
			|| true !== ( $import['restore_allowed'] ?? false )
			|| true !== ( $import['destination_storage_isolated'] ?? false )
			|| true !== ( $import['search_visibility_disabled'] ?? false )
			|| true !== ( $import['outbound_safe'] ?? false )
			|| true !== ( $import['backups_ready'] ?? false )
			|| true !== ( $import['target_authorized'] ?? false )
			|| array() !== ( $import['blockers'] ?? array() )
			|| ! hash_equals( (string) $rewrite['archive_sha256'], (string) ( $import['archive_sha256'] ?? '' ) )
			|| ! hash_equals( (string) $rewrite['package_manifest_sha256'], (string) ( $import['package_manifest_sha256'] ?? '' ) )
			|| ! hash_equals( (string) $rewrite['package_checksum'], (string) ( $import['package_checksum'] ?? '' ) )
		) {
			return null;
		}

		return array(
			'rewrite' => $rewrite,
			'import'  => $import,
		);
	}

	/**
	 * Confirm saved parent state still matches current authority.
	 *
	 * @param array<string,mixed>                                      $state     Parent finalization state.
	 * @param array{rewrite:array<string,mixed>,import:array<string,mixed>} $authority Current authority.
	 */
	private function state_matches_authority( array $state, array $authority ): bool {
		return hash_equals( (string) $state['child_import_job_id'], (string) $authority['rewrite']['child_import_job_id'] )
			&& hash_equals( (string) $state['destination_authority_sha256'], (string) $authority['rewrite']['destination_authority_sha256'] )
			&& hash_equals( (string) $state['archive_sha256'], (string) $authority['rewrite']['archive_sha256'] )
			&& hash_equals( (string) $state['package_manifest_sha256'], (string) $authority['rewrite']['package_manifest_sha256'] )
			&& hash_equals( (string) $state['package_checksum'], (string) $authority['rewrite']['package_checksum'] )
			&& hash_equals( (string) $state['database_manifest_sha256'], (string) $authority['rewrite']['database_manifest_sha256'] )
			&& hash_equals( (string) $state['file_manifest_sha256'], (string) $authority['rewrite']['file_manifest_sha256'] );
	}

	/**
	 * Validate the immutable child activation plan against the isolated local target.
	 *
	 * @param mixed               $plan   Raw activation plan.
	 * @param array<string,mixed> $import Child import state.
	 */
	private function local_plan_valid( mixed $plan, array $import ): bool {
		if (
			! is_array( $plan )
			|| 1 !== ( $plan['schema_version'] ?? null )
			|| ! is_array( $plan['database'] ?? null )
			|| ! is_array( $plan['files'] ?? null )
			|| ! is_array( $plan['policy'] ?? null )
			|| true !== ( $plan['policy']['rollback_required_before_swap'] ?? false )
			|| false !== ( $plan['policy']['active_mutation_in_this_phase'] ?? true )
			|| false !== ( $plan['policy']['final_handoff_ready'] ?? true )
		) {
			return false;
		}

		$prefix = is_string( $import['destination_table_prefix'] ?? null ) ? $import['destination_table_prefix'] : '';
		$root   = is_string( $import['destination_root_path'] ?? null )
			? untrailingslashit( wp_normalize_path( $import['destination_root_path'] ) )
			: '';
		if (
			'' === $prefix
			|| '' === $root
			|| ! is_dir( $root )
			|| is_link( $root )
			|| (string) ( $plan['database']['destination_prefix'] ?? '' ) !== $prefix
		) {
			return false;
		}

		$tables = is_array( $plan['database']['tables'] ?? null ) ? array_values( $plan['database']['tables'] ) : array();
		if ( array() === $tables ) {
			return false;
		}
		foreach ( $tables as $table ) {
			if (
				! is_array( $table )
				|| true === ( $table['target_exists'] ?? true )
				|| ! is_string( $table['target_table'] ?? null )
				|| ! str_starts_with( $table['target_table'], $prefix )
				|| ! is_string( $table['staging_table'] ?? null )
				|| str_starts_with( $table['staging_table'], $prefix )
				|| ! is_string( $table['rollback_table'] ?? null )
				|| ! str_starts_with( $table['rollback_table'], $prefix )
			) {
				return false;
			}
		}

		$roots = is_array( $plan['files']['roots'] ?? null ) ? array_values( $plan['files']['roots'] ) : array();
		if ( 3 !== count( $roots ) ) {
			return false;
		}
		$seen = array();
		foreach ( $roots as $entry ) {
			if ( ! is_array( $entry ) || ! is_string( $entry['id'] ?? null ) ) {
				return false;
			}
			$id = $entry['id'];
			if ( ! in_array( $id, array( 'uploads', 'plugins', 'themes' ), true ) || isset( $seen[ $id ] ) ) {
				return false;
			}
			$seen[ $id ] = true;

			$expected     = trailingslashit( wp_normalize_path( $root . '/wp-content/' . $id ) );
			$active    = is_string( $entry['active_root'] ?? null ) ? trailingslashit( wp_normalize_path( $entry['active_root'] ) ) : '';
			$candidate = is_string( $entry['candidate_root'] ?? null ) ? wp_normalize_path( $entry['candidate_root'] ) : '';
			$rollback  = is_string( $entry['rollback_root'] ?? null ) ? wp_normalize_path( $entry['rollback_root'] ) : '';
			if (
				$expected !== $active
				|| ! str_starts_with( $candidate, trailingslashit( wp_normalize_path( $root . '/wp-content' ) ) )
				|| ! str_starts_with( $rollback, trailingslashit( wp_normalize_path( $root . '/wp-content' ) ) )
				|| file_exists( untrailingslashit( $candidate ) )
				|| file_exists( untrailingslashit( $rollback ) )
			) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Refresh parent progress.
	 *
	 * @param string              $job_id Parent local-clone job identifier.
	 * @param array<string,mixed> $state  Parent state.
	 */
	private function refresh_parent_progress( string $job_id, array $state ): void {
		$ready = 'ready' === ( $state['status'] ?? null ) && 'ready' === ( $state['stage'] ?? null );

		$this->jobs->transition( $job_id, 'active', null );
		$this->jobs->update_progress(
			$job_id,
			'finalize-preflight',
			$ready ? 'local-finalization-plan-ready' : 'local-finalization-' . (string) ( $state['stage'] ?? 'database-fingerprint' ),
			array(
				'completed' => (int) ( $state['database_rows_hashed'] ?? 0 ) + (int) ( $state['files_hashed'] ?? 0 ),
				'total'     => null,
			)
		);
	}

	/**
	 * Revoke child eligibility after parent authority drift.
	 *
	 * @param string $child_id Child import job identifier.
	 * @param string $code     Stable blocker code.
	 */
	private function lock_child( string $child_id, string $code ): void {
		$import = $this->import_state->get( $child_id );
		if ( ! is_array( $import ) ) {
			return;
		}

		$blockers                        = is_array( $import['blockers'] ?? null ) ? $import['blockers'] : array();
		$blockers[]                      = $code;
		$import['status']                = 'blocked';
		$import['blockers']              = array_values( array_unique( $blockers ) );
		$import['full_payload_verified'] = false;
		$import['restore_allowed']       = false;
		$import['updated_at']            = gmdate( DATE_ATOM );
		$this->import_state->save( $child_id, $import );
	}

	/**
	 * Revoke child eligibility from one saved parent state.
	 *
	 * @param array<string,mixed>|null $state Saved parent state.
	 * @param string                   $code  Stable blocker.
	 */
	private function lock_child_from_parent_state( ?array $state, string $code ): void {
		if ( ! is_array( $state ) || ! is_string( $state['child_import_job_id'] ?? null ) || '' === $state['child_import_job_id'] ) {
			return;
		}

		$this->lock_child( (string) $state['child_import_job_id'], $code );
	}

	/**
	 * Persist one conservative parent blocker.
	 *
	 * @param string              $job_id Parent local-clone job identifier.
	 * @param array<string,mixed> $state  Current parent state.
	 * @param string              $code   Stable blocker.
	 * @return array<string,mixed>|null
	 */
	private function block( string $job_id, array $state, string $code ): ?array {
		$now        = gmdate( DATE_ATOM );
		$blockers   = is_array( $state['blockers'] ?? null ) ? $state['blockers'] : array();
		$blockers[] = $code;

		$state['schema_version']          = LocalCloneFinalizationPlanStateStore::SCHEMA_VERSION;
		$state['job_id']                = $job_id;
		$state['status']                = 'blocked';
		$state['stage']                 = is_string( $state['stage'] ?? null ) ? $state['stage'] : 'database-fingerprint';
		$state['activation_plan_ready'] = false;
		$state['target_unactivated']    = false;
		$state['blockers']              = array_values( array_unique( array_filter( $blockers, 'is_string' ) ) );
		$state['started_at']            = (string) ( $state['started_at'] ?? $now );
		$state['updated_at']            = $now;

		if ( ! $this->store->save( $job_id, $state ) ) {
			return null;
		}

		$this->jobs->transition( $job_id, 'failed-retryable', $code );

		return $this->store->get( $job_id );
	}
}
