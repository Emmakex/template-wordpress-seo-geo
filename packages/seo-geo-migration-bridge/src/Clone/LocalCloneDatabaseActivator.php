<?php
/**
 * Portable Clone local reversible database activation.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge\Clone;

/**
 * Binds the existing reversible DB activator to verified local-clone parent authority.
 */
final class LocalCloneDatabaseActivator {
	/**
	 * Parent activation state.
	 *
	 * @var LocalCloneDatabaseActivationStateStore
	 */
	private LocalCloneDatabaseActivationStateStore $store;

	/**
	 * Accepted local finalization authority.
	 *
	 * @var LocalCloneFinalizationPlanner
	 */
	private LocalCloneFinalizationPlanner $finalization;

	/**
	 * Existing child reversible DB activator.
	 *
	 * @var ImportDatabaseActivator
	 */
	private ImportDatabaseActivator $activator;

	/**
	 * Child import state.
	 *
	 * @var ImportStateStore
	 */
	private ImportStateStore $import_state;

	/**
	 * Parent clone jobs.
	 *
	 * @var CloneJobStore
	 */
	private CloneJobStore $jobs;

	/**
	 * Construct local database activator.
	 *
	 * @param LocalCloneDatabaseActivationStateStore|null $store        Optional parent state.
	 * @param LocalCloneFinalizationPlanner|null          $finalization Optional finalization authority.
	 * @param ImportDatabaseActivator|null                $activator    Optional child DB activator.
	 * @param ImportStateStore|null                       $import_state Optional child import state.
	 * @param CloneJobStore|null                          $jobs         Optional clone jobs.
	 */
	public function __construct(
		?LocalCloneDatabaseActivationStateStore $store = null,
		?LocalCloneFinalizationPlanner $finalization = null,
		?ImportDatabaseActivator $activator = null,
		?ImportStateStore $import_state = null,
		?CloneJobStore $jobs = null
	) {
		$this->store        = $store ?? new LocalCloneDatabaseActivationStateStore();
		$this->finalization = $finalization ?? new LocalCloneFinalizationPlanner();
		$this->activator    = $activator ?? new ImportDatabaseActivator();
		$this->import_state = $import_state ?? new ImportStateStore();
		$this->jobs         = $jobs ?? new CloneJobStore();
	}

	/**
	 * Return one parent activation snapshot.
	 *
	 * @param string $job_id Parent local-clone job identifier.
	 * @return array<string,mixed>|null
	 */
	public function snapshot( string $job_id ): ?array {
		return $this->store->get( $job_id );
	}

	/**
	 * Return activated state only while DB/control/file-isolation checks still hold.
	 *
	 * @param string $job_id Parent local-clone job identifier.
	 * @return array<string,mixed>|null
	 */
	public function verified_snapshot( string $job_id ): ?array {
		$state = $this->store->get( $job_id );
		if (
			! is_array( $state )
			|| 'activated' !== ( $state['status'] ?? null )
			|| true !== ( $state['database_swapped'] ?? false )
			|| true !== ( $state['rollback_available'] ?? false )
			|| true !== ( $state['active_files_untouched'] ?? false )
			|| true !== ( $state['target_database_active'] ?? false )
			|| array() !== ( $state['blockers'] ?? array() )
		) {
			return null;
		}

		$child_id = (string) ( $state['child_import_job_id'] ?? '' );
		$import   = '' !== $child_id ? $this->import_state->get( $child_id ) : null;
		$child    = '' !== $child_id ? $this->activator->verified_snapshot( $child_id ) : null;
		if (
			! $this->import_matches_parent( $job_id, $state, $import )
			|| ! is_array( $child )
			|| ! hash_equals( (string) $state['activation_plan_hash'], (string) ( $child['activation_plan_hash'] ?? '' ) )
			|| ! hash_equals( (string) $state['database_fingerprint'], (string) ( $child['finalize_db_hash'] ?? '' ) )
			|| (string) ( $child['options_target'] ?? '' ) !== (string) $state['options_target']
			|| count( is_array( $child['tables'] ?? null ) ? $child['tables'] : array() ) !== (int) $state['table_count']
			|| ! $this->target_client_files_untouched( $import )
		) {
			return null;
		}

		return $state;
	}

	/**
	 * Freeze the reversible child DB journal without mutating DB tables.
	 *
	 * @param string $job_id Parent local-clone job identifier.
	 * @return array<string,mixed>|null
	 */
	public function prepare( string $job_id ): ?array {
		$existing = $this->store->get( $job_id );
		if ( is_array( $existing ) ) {
			return $existing;
		}

		$authority = $this->pre_activation_authority( $job_id );
		if ( null === $authority ) {
			return null;
		}

		$child_id = (string) $authority['finalization']['child_import_job_id'];
		$child    = $this->activator->prepare( $child_id );
		if (
			! is_array( $child )
			|| 'prepared' !== ( $child['status'] ?? null )
			|| true === ( $child['database_swapped'] ?? true )
			|| true !== ( $child['rollback_available'] ?? false )
			|| true !== ( $child['active_files_untouched'] ?? false )
			|| array() !== ( $child['blockers'] ?? array() )
			|| ! hash_equals(
				(string) $authority['finalization']['activation_plan_hash'],
				(string) ( $child['activation_plan_hash'] ?? '' )
			)
			|| ! hash_equals(
				(string) $authority['finalization']['database_fingerprint'],
				(string) ( $child['finalize_db_hash'] ?? '' )
			)
		) {
			return null;
		}

		$state = $this->parent_state( $job_id, 'prepared', $authority, $child );
		if ( ! $this->store->save( $job_id, $state ) ) {
			return null;
		}

		$this->refresh_parent_progress( $job_id, $state );

		return $this->store->get( $job_id );
	}

	/**
	 * Atomically activate the isolated target tables.
	 *
	 * @param string $job_id Parent local-clone job identifier.
	 * @return array<string,mixed>|null
	 */
	public function activate( string $job_id ): ?array {
		$verified = $this->verified_snapshot( $job_id );
		if ( is_array( $verified ) ) {
			$this->refresh_parent_progress( $job_id, $verified );

			return $verified;
		}

		$state = $this->store->get( $job_id ) ?? $this->prepare( $job_id );
		if ( ! is_array( $state ) || 'prepared' !== ( $state['status'] ?? null ) ) {
			return $state;
		}

		$authority = $this->pre_activation_authority( $job_id );
		if (
			null === $authority
			|| ! hash_equals(
				(string) $state['activation_plan_hash'],
				(string) $authority['finalization']['activation_plan_hash']
			)
			|| ! hash_equals(
				(string) $state['database_fingerprint'],
				(string) $authority['finalization']['database_fingerprint']
			)
		) {
			return $this->block( $job_id, $state, 'local-database-activation-authority-changed' );
		}

		$child_id = (string) $state['child_import_job_id'];
		$child    = $this->activator->activate( $child_id );
		if ( ! is_array( $child ) ) {
			return $this->block( $job_id, $state, 'local-database-activation-service-unavailable' );
		}

		if ( 'activated' !== ( $child['status'] ?? null ) ) {
			$blockers = is_array( $child['blockers'] ?? null ) ? $child['blockers'] : array();
			$code     = is_string( $blockers[0] ?? null ) ? $blockers[0] : 'local-database-activation-child-not-activated';

			return $this->block( $job_id, $state, $code );
		}

		$verified_child = $this->activator->verified_snapshot( $child_id );
		$import         = $this->import_state->get( $child_id );
		if (
			! is_array( $verified_child )
			|| ! $this->import_matches_parent( $job_id, $state, $import )
			|| ! $this->target_client_files_untouched( $import )
		) {
			return $this->block( $job_id, $state, 'local-database-activation-post-verify-failed' );
		}

		$activated = $this->parent_state( $job_id, 'activated', $authority, $verified_child );
		if ( ! $this->store->save( $job_id, $activated ) ) {
			return null;
		}

		$this->refresh_parent_progress( $job_id, $activated );

		return $this->store->get( $job_id );
	}

	/**
	 * Explicitly roll the activated local target database back to staging.
	 *
	 * @param string $job_id Parent local-clone job identifier.
	 * @return array<string,mixed>|null
	 */
	public function rollback( string $job_id ): ?array {
		$state = $this->store->get( $job_id );
		if ( ! is_array( $state ) || 'activated' !== ( $state['status'] ?? null ) ) {
			return $state;
		}

		$child = $this->activator->rollback( (string) $state['child_import_job_id'] );
		if (
			! is_array( $child )
			|| 'rolled-back' !== ( $child['status'] ?? null )
			|| true === ( $child['database_swapped'] ?? true )
		) {
			return $this->block( $job_id, $state, 'local-database-rollback-failed' );
		}

		$now                             = gmdate( DATE_ATOM );
		$state['status']                 = 'rolled-back';
		$state['database_swapped']       = false;
		$state['rollback_available']     = false;
		$state['active_files_untouched'] = true;
		$state['target_database_active'] = false;
		$state['activation_next']        = 'rebuild-finalization-plan';
		$state['blockers']               = array_values(
			array_unique(
				array_merge(
					is_array( $state['blockers'] ?? null ) ? $state['blockers'] : array(),
					array( 'operator-database-rollback' )
				)
			)
		);
		$state['rolled_back_at']         = $now;
		$state['updated_at']             = $now;
		if ( ! $this->store->save( $job_id, $state ) ) {
			return null;
		}

		$this->jobs->transition( $job_id, 'active', null );
		$this->jobs->update_progress(
			$job_id,
			'activate-database',
			'local-database-rolled-back',
			array(
				'completed' => 0,
				'total'     => (int) ( $state['row_count'] ?? 0 ),
			)
		);

		return $this->store->get( $job_id );
	}

	/**
	 * Resolve the last mutation-free authority gate.
	 *
	 * @param string $job_id Parent local-clone job identifier.
	 * @return array{finalization:array<string,mixed>,plan:array{plan:array<string,mixed>,hash:string,database_fingerprint:string,file_fingerprint:string},import:array<string,mixed>}|null
	 */
	private function pre_activation_authority( string $job_id ): ?array {
		$finalization = $this->finalization->verified_snapshot( $job_id );
		$plan         = $this->finalization->activation_plan_snapshot( $job_id );
		if ( ! is_array( $finalization ) || ! is_array( $plan ) ) {
			return null;
		}

		$child_id = (string) ( $finalization['child_import_job_id'] ?? '' );
		$import   = '' !== $child_id ? $this->import_state->get( $child_id ) : null;
		if ( ! $this->import_matches_finalization( $job_id, $finalization, $import ) ) {
			return null;
		}

		return array(
			'finalization' => $finalization,
			'plan'         => $plan,
			'import'       => $import,
		);
	}

	/**
	 * Build one parent state from child activation evidence.
	 *
	 * @param string              $job_id    Parent local-clone job identifier.
	 * @param string              $status    Parent status.
	 * @param array<string,mixed> $authority Mutation-free authority.
	 * @param array<string,mixed> $child     Child activation journal.
	 * @return array<string,mixed>
	 */
	private function parent_state( string $job_id, string $status, array $authority, array $child ): array {
		$finalization = $authority['finalization'];
		$import       = $authority['import'];
		$tables       = is_array( $child['tables'] ?? null ) ? $child['tables'] : array();
		$rows         = 0;
		foreach ( $tables as $table ) {
			$rows += is_array( $table ) ? max( 0, (int) ( $table['row_count'] ?? 0 ) ) : 0;
		}

		$now = gmdate( DATE_ATOM );

		return array(
			'schema_version'               => LocalCloneDatabaseActivationStateStore::SCHEMA_VERSION,
			'job_id'                       => $job_id,
			'status'                       => $status,
			'child_import_job_id'          => (string) $finalization['child_import_job_id'],
			'destination_authority_sha256' => (string) $finalization['destination_authority_sha256'],
			'activation_plan_hash'         => (string) $finalization['activation_plan_hash'],
			'database_fingerprint'         => (string) $finalization['database_fingerprint'],
			'file_fingerprint'             => (string) $finalization['file_fingerprint'],
			'target_table_prefix'          => (string) $import['destination_table_prefix'],
			'options_target'               => (string) ( $child['options_target'] ?? '' ),
			'table_count'                  => count( $tables ),
			'row_count'                    => $rows,
			'database_swapped'             => true === ( $child['database_swapped'] ?? false ),
			'rollback_available'           => true === ( $child['rollback_available'] ?? false ),
			'active_files_untouched'       => true === ( $child['active_files_untouched'] ?? false ),
			'target_database_active'       => 'activated' === $status,
			'activation_next'              => 'activated' === $status ? 'file-promotion' : 'database-activation',
			'blockers'                     => array(),
			'prepared_at'                  => (string) ( $child['prepared_at'] ?? $now ),
			'activated_at'                 => 'activated' === $status ? (string) ( $child['activated_at'] ?? $now ) : '',
			'rolled_back_at'               => '',
			'updated_at'                   => $now,
		);
	}

	/**
	 * Validate child import against parent finalization.
	 *
	 * @param string                   $job_id       Parent local-clone job identifier.
	 * @param array<string,mixed>      $finalization Parent finalization state.
	 * @param array<string,mixed>|null $import       Child import state.
	 */
	private function import_matches_finalization( string $job_id, array $finalization, ?array $import ): bool {
		return is_array( $import )
			&& 'payload-verified' === ( $import['status'] ?? null )
			&& 'private-same-server' === ( $import['transport'] ?? null )
			&& (string) ( $import['local_handoff_parent_job_id'] ?? '' ) === $job_id
			&& true === ( $import['full_payload_verified'] ?? false )
			&& true === ( $import['restore_allowed'] ?? false )
			&& array() === ( $import['blockers'] ?? array() )
			&& (string) ( $finalization['child_import_job_id'] ?? '' ) === (string) ( $import['job_id'] ?? '' )
			&& hash_equals(
				(string) ( $finalization['destination_authority_sha256'] ?? '' ),
				(string) ( $import['destination_authority_sha256'] ?? '' )
			);
	}

	/**
	 * Validate activated parent state against persistent child import authority.
	 *
	 * @param string                   $job_id Parent local-clone job identifier.
	 * @param array<string,mixed>      $state  Parent activation state.
	 * @param array<string,mixed>|null $import Child import state.
	 */
	private function import_matches_parent( string $job_id, array $state, ?array $import ): bool {
		return is_array( $import )
			&& 'payload-verified' === ( $import['status'] ?? null )
			&& 'private-same-server' === ( $import['transport'] ?? null )
			&& (string) ( $import['local_handoff_parent_job_id'] ?? '' ) === $job_id
			&& (string) ( $import['job_id'] ?? '' ) === (string) ( $state['child_import_job_id'] ?? '' )
			&& (string) ( $import['destination_table_prefix'] ?? '' ) === (string) ( $state['target_table_prefix'] ?? '' )
			&& hash_equals(
				(string) ( $import['destination_authority_sha256'] ?? '' ),
				(string) ( $state['destination_authority_sha256'] ?? '' )
			);
	}

	/**
	 * Prove no client file root has been promoted into the isolated target yet.
	 *
	 * @param array<string,mixed>|null $import Child import state.
	 */
	private function target_client_files_untouched( ?array $import ): bool {
		if ( ! is_array( $import ) || ! is_string( $import['destination_root_path'] ?? null ) ) {
			return false;
		}

		$root = untrailingslashit( wp_normalize_path( $import['destination_root_path'] ) );
		if ( '' === $root || ! is_dir( $root ) || is_link( $root ) ) {
			return false;
		}

		if (
			file_exists( $root . '/wp-content/uploads' )
			|| file_exists( $root . '/wp-content/themes' )
		) {
			return false;
		}

		$plugins = $root . '/wp-content/plugins';
		if ( ! is_dir( $plugins ) || is_link( $plugins ) ) {
			return false;
		}

		$entries = scandir( $plugins, SCANDIR_SORT_ASCENDING );
		if ( false === $entries ) {
			return false;
		}
		$entries = array_values( array_diff( $entries, array( '.', '..' ) ) );

		return array( 'seo-geo-migration-bridge' ) === $entries;
	}

	/**
	 * Refresh parent activation progress.
	 *
	 * @param string              $job_id Parent local-clone job identifier.
	 * @param array<string,mixed> $state  Parent activation state.
	 */
	private function refresh_parent_progress( string $job_id, array $state ): void {
		$activated = 'activated' === ( $state['status'] ?? null );

		$this->jobs->transition( $job_id, 'active', null );
		$this->jobs->update_progress(
			$job_id,
			'activate-database',
			$activated ? 'local-database-activated' : 'local-database-activation-prepared',
			array(
				'completed' => $activated ? (int) ( $state['row_count'] ?? 0 ) : 0,
				'total'     => (int) ( $state['row_count'] ?? 0 ),
			)
		);
	}

	/**
	 * Persist one parent blocker.
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

		$state['schema_version']         = LocalCloneDatabaseActivationStateStore::SCHEMA_VERSION;
		$state['job_id']                 = $job_id;
		$state['status']                 = 'blocked';
		$state['database_swapped']       = true === ( $state['database_swapped'] ?? false );
		$state['rollback_available']     = true === ( $state['rollback_available'] ?? false );
		$state['active_files_untouched'] = true === ( $state['active_files_untouched'] ?? false );
		$state['target_database_active'] = true === ( $state['target_database_active'] ?? false );
		$state['blockers']               = array_values( array_unique( array_filter( $blockers, 'is_string' ) ) );
		$state['updated_at']             = $now;

		if ( ! $this->store->save( $job_id, $state ) ) {
			return null;
		}

		$this->jobs->transition( $job_id, 'failed-retryable', $code );

		return $this->store->get( $job_id );
	}
}
