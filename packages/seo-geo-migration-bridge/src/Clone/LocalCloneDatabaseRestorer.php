<?php
/**
 * Portable Clone local transactional database staging.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge\Clone;

use wpdb;

/**
 * Reuses Portable Import database staging under local-clone parent authority.
 */
final class LocalCloneDatabaseRestorer {
	/**
	 * Parent database state.
	 *
	 * @var LocalCloneDatabaseRestoreStateStore
	 */
	private LocalCloneDatabaseRestoreStateStore $store;

	/**
	 * Verified local package handoff.
	 *
	 * @var LocalClonePackageHandoff
	 */
	private LocalClonePackageHandoff $handoff;

	/**
	 * Verified local payload.
	 *
	 * @var LocalClonePayloadVerifier
	 */
	private LocalClonePayloadVerifier $payload;

	/**
	 * Existing Portable Import database restorer.
	 *
	 * @var ImportDatabaseRestorer
	 */
	private ImportDatabaseRestorer $restorer;

	/**
	 * Existing child database state.
	 *
	 * @var ImportDatabaseStateStore
	 */
	private ImportDatabaseStateStore $database_state;

	/**
	 * Existing child import state.
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
	 * Construct local database restorer.
	 *
	 * @param LocalCloneDatabaseRestoreStateStore|null $store          Optional parent state store.
	 * @param LocalClonePackageHandoff|null            $handoff        Optional verified handoff service.
	 * @param LocalClonePayloadVerifier|null           $payload        Optional verified payload service.
	 * @param ImportDatabaseRestorer|null              $restorer       Optional existing database restorer.
	 * @param ImportDatabaseStateStore|null            $database_state Optional child database state.
	 * @param ImportStateStore|null                    $import_state   Optional child import state.
	 * @param CloneJobStore|null                       $jobs           Optional clone jobs.
	 */
	public function __construct(
		?LocalCloneDatabaseRestoreStateStore $store = null,
		?LocalClonePackageHandoff $handoff = null,
		?LocalClonePayloadVerifier $payload = null,
		?ImportDatabaseRestorer $restorer = null,
		?ImportDatabaseStateStore $database_state = null,
		?ImportStateStore $import_state = null,
		?CloneJobStore $jobs = null
	) {
		$this->store          = $store ?? new LocalCloneDatabaseRestoreStateStore();
		$this->handoff        = $handoff ?? new LocalClonePackageHandoff();
		$this->payload        = $payload ?? new LocalClonePayloadVerifier();
		$this->restorer       = $restorer ?? new ImportDatabaseRestorer();
		$this->database_state = $database_state ?? new ImportDatabaseStateStore();
		$this->import_state   = $import_state ?? new ImportStateStore();
		$this->jobs           = $jobs ?? new CloneJobStore();
	}

	/**
	 * Return one parent database staging snapshot.
	 *
	 * @param string $job_id Parent local-clone job identifier.
	 * @return array<string,mixed>|null
	 */
	public function snapshot( string $job_id ): ?array {
		return $this->store->get( $job_id );
	}

	/**
	 * Return a completed staging state only while parent/child authority remains valid.
	 *
	 * @param string $job_id Parent local-clone job identifier.
	 * @return array<string,mixed>|null
	 */
	public function verified_snapshot( string $job_id ): ?array {
		$state = $this->store->get( $job_id );
		if (
			! is_array( $state )
			|| 'ready' !== ( $state['status'] ?? null )
			|| 'complete' !== ( $state['stage'] ?? null )
			|| true !== ( $state['active_tables_untouched'] ?? false )
			|| true !== ( $state['target_tables_untouched'] ?? false )
			|| true !== ( $state['client_content_untouched'] ?? false )
			|| array() !== ( $state['blockers'] ?? array() )
		) {
			return null;
		}

		$authority = $this->authority( $job_id, false );
		if ( null === $authority || ! $this->state_matches_authority( $state, $authority ) ) {
			return null;
		}

		$child_id = (string) $state['child_import_job_id'];
		$database = $this->database_state->get( $child_id );
		$plan     = $this->restorer->staging_plan( $child_id );
		if (
			! is_array( $database )
			|| 'complete' !== ( $database['status'] ?? null )
			|| 'complete' !== ( $database['stage'] ?? null )
			|| true !== ( $database['active_tables_untouched'] ?? false )
			|| ! is_array( $plan )
			|| ! $this->database_matches_parent( $database, $state )
			|| ! $this->target_tables_untouched( (string) $state['destination_prefix'] )
			|| ! $this->staging_namespace_isolated(
				(string) $state['staging_namespace'],
				(string) $state['destination_prefix']
			)
		) {
			return null;
		}

		return $state;
	}

	/**
	 * Return the verified child staging plan for a completed local restore.
	 *
	 * @param string $job_id Parent local-clone job identifier.
	 * @return array<string,mixed>|null
	 */
	public function staging_plan( string $job_id ): ?array {
		$state = $this->verified_snapshot( $job_id );
		if ( ! is_array( $state ) ) {
			return null;
		}

		return $this->restorer->staging_plan( (string) $state['child_import_job_id'] );
	}

	/**
	 * Advance one bounded transactional staging batch.
	 *
	 * @param string $job_id     Parent local-clone job identifier.
	 * @param int    $batch_rows Maximum rows inserted this request.
	 * @return array<string,mixed>|null
	 */
	public function advance( string $job_id, int $batch_rows = ImportDatabaseRestorer::DEFAULT_BATCH_ROWS ): ?array {
		$ready = $this->verified_snapshot( $job_id );
		if ( is_array( $ready ) ) {
			$this->jobs->transition( $job_id, 'active', null );
			$this->jobs->update_progress(
				$job_id,
				'restore-database',
				'local-database-staging-complete',
				array(
					'completed' => (int) ( $ready['rows_restored'] ?? 0 ),
					'total'     => (int) ( $ready['rows_restored'] ?? 0 ),
				)
			);

			return $ready;
		}

		$existing  = $this->store->get( $job_id );
		$authority = $this->authority( $job_id, true );
		if ( null === $authority ) {
			$this->lock_child_from_parent_state( $existing, 'local-database-parent-authority-unavailable' );

			return $this->block(
				$job_id,
				$existing ?? array(),
				'local-database-parent-authority-unavailable'
			);
		}

		$child_id = (string) $authority['payload']['child_import_job_id'];
		$database = $this->restorer->advance( $child_id, $batch_rows );
		if ( ! is_array( $database ) ) {
			return $this->block(
				$job_id,
				$existing ?? array(),
				'local-database-restorer-unavailable'
			);
		}

		if ( 'blocked' === ( $database['status'] ?? null ) ) {
			$blockers = is_array( $database['blockers'] ?? null ) ? $database['blockers'] : array();
			$code     = is_string( $blockers[0] ?? null )
				? (string) $blockers[0]
				: 'local-database-staging-blocked';

			return $this->block( $job_id, $existing ?? array(), $code );
		}

		$fresh = $this->authority( $job_id, false );
		if ( null === $fresh ) {
			$this->lock_child( $child_id, 'local-database-parent-authority-changed' );

			return $this->block(
				$job_id,
				$existing ?? array(),
				'local-database-parent-authority-changed'
			);
		}

		$destination_prefix = (string) ( $database['destination_prefix'] ?? '' );
		$staging_namespace  = (string) ( $database['staging_namespace'] ?? '' );
		if (
			$destination_prefix !== (string) $fresh['handoff']['target_table_prefix']
			|| true !== ( $database['active_tables_untouched'] ?? false )
			|| ! $this->target_tables_untouched( $destination_prefix )
			|| ! $this->staging_namespace_isolated( $staging_namespace, $destination_prefix )
		) {
			$this->lock_child( $child_id, 'local-database-isolation-guard-failed' );

			return $this->block(
				$job_id,
				$existing ?? array(),
				'local-database-isolation-guard-failed'
			);
		}

		$complete = 'complete' === ( $database['status'] ?? null )
			&& 'complete' === ( $database['stage'] ?? null )
			&& is_array( $this->restorer->staging_plan( $child_id ) );

		$now   = gmdate( DATE_ATOM );
		$state = array(
			'schema_version'               => LocalCloneDatabaseRestoreStateStore::SCHEMA_VERSION,
			'job_id'                       => $job_id,
			'status'                       => $complete ? 'ready' : 'running',
			'stage'                        => $complete ? 'complete' : (string) ( $database['stage'] ?? 'schema' ),
			'child_import_job_id'          => $child_id,
			'destination_authority_sha256' => (string) $fresh['payload']['destination_authority_sha256'],
			'archive_sha256'               => (string) $fresh['handoff']['archive_sha256'],
			'package_manifest_sha256'      => (string) $fresh['handoff']['package_manifest_hash'],
			'package_checksum'             => (string) $fresh['handoff']['package_checksum'],
			'destination_prefix'           => $destination_prefix,
			'staging_namespace'            => $staging_namespace,
			'table_index'                  => (int) ( $database['table_index'] ?? 0 ),
			'table_count'                  => (int) ( $database['table_count'] ?? 0 ),
			'rows_restored'                => (int) ( $database['rows_restored'] ?? 0 ),
			'tables_completed'             => (int) ( $database['tables_completed'] ?? 0 ),
			'active_tables_untouched'      => true,
			'target_tables_untouched'      => true,
			'client_content_untouched'     => true,
			'database_next'                => $complete ? 'file-staging-restore' : 'database-staging-restore',
			'blockers'                     => array(),
			'started_at'                   => (string) ( $existing['started_at'] ?? $now ),
			'updated_at'                   => $now,
			'ready_at'                     => $complete ? $now : '',
		);

		if ( ! $this->store->save( $job_id, $state ) ) {
			return null;
		}

		$this->jobs->transition( $job_id, 'active', null );
		$this->jobs->update_progress(
			$job_id,
			'restore-database',
			$complete ? 'local-database-staging-complete' : 'local-database-' . (string) $state['stage'],
			array(
				'completed' => (int) $state['rows_restored'],
				'total'     => null,
			)
		);

		return $this->store->get( $job_id );
	}

	/**
	 * Resolve current parent/child authority.
	 *
	 * @param string $job_id          Parent local-clone job identifier.
	 * @param bool   $attempt_recover Whether to retry payload/preflight recovery first.
	 * @return array{handoff:array<string,mixed>,payload:array<string,mixed>,import:array<string,mixed>}|null
	 */
	private function authority( string $job_id, bool $attempt_recover ): ?array {
		$handoff = $this->handoff->verified_snapshot( $job_id );
		$payload = $this->payload->verified_snapshot( $job_id );
		if ( ! is_array( $payload ) && $attempt_recover ) {
			$this->payload->advance( $job_id );
			$payload = $this->payload->verified_snapshot( $job_id );
		}
		if ( ! is_array( $handoff ) || ! is_array( $payload ) ) {
			return null;
		}

		$child_id = (string) ( $payload['child_import_job_id'] ?? '' );
		$import   = '' !== $child_id ? $this->import_state->get( $child_id ) : null;
		if (
			! is_array( $import )
			|| 'payload-verified' !== ( $import['status'] ?? null )
			|| 'private-same-server' !== ( $import['transport'] ?? null )
			|| (string) ( $import['local_handoff_parent_job_id'] ?? '' ) !== $job_id
			|| true !== ( $import['full_payload_verified'] ?? false )
			|| true !== ( $import['restore_allowed'] ?? false )
			|| array() !== ( $import['blockers'] ?? array() )
			|| ! hash_equals( (string) $handoff['archive_sha256'], (string) ( $import['archive_sha256'] ?? '' ) )
			|| ! hash_equals( (string) $handoff['package_manifest_hash'], (string) ( $import['package_manifest_sha256'] ?? '' ) )
			|| ! hash_equals( (string) $handoff['package_checksum'], (string) ( $import['package_checksum'] ?? '' ) )
			|| ! hash_equals( (string) $handoff['target_table_prefix'], (string) ( $import['destination_table_prefix'] ?? '' ) )
		) {
			return null;
		}

		return array(
			'handoff' => $handoff,
			'payload' => $payload,
			'import'  => $import,
		);
	}

	/**
	 * Confirm saved parent state still matches current authority.
	 *
	 * @param array<string,mixed>                                                                       $state     Parent database state.
	 * @param array{handoff:array<string,mixed>,payload:array<string,mixed>,import:array<string,mixed>} $authority Current authority.
	 */
	private function state_matches_authority( array $state, array $authority ): bool {
		return hash_equals( (string) $state['child_import_job_id'], (string) $authority['payload']['child_import_job_id'] )
			&& hash_equals( (string) $state['destination_authority_sha256'], (string) $authority['payload']['destination_authority_sha256'] )
			&& hash_equals( (string) $state['archive_sha256'], (string) $authority['handoff']['archive_sha256'] )
			&& hash_equals( (string) $state['package_manifest_sha256'], (string) $authority['handoff']['package_manifest_hash'] )
			&& hash_equals( (string) $state['package_checksum'], (string) $authority['handoff']['package_checksum'] )
			&& (string) $state['destination_prefix'] === (string) $authority['handoff']['target_table_prefix'];
	}

	/**
	 * Confirm child database state matches saved parent state.
	 *
	 * @param array<string,mixed> $database Child database state.
	 * @param array<string,mixed> $state    Parent database state.
	 */
	private function database_matches_parent( array $database, array $state ): bool {
		return (string) ( $database['destination_prefix'] ?? '' ) === (string) $state['destination_prefix']
			&& (string) ( $database['staging_namespace'] ?? '' ) === (string) $state['staging_namespace']
			&& true === ( $database['active_tables_untouched'] ?? false );
	}

	/**
	 * Confirm no active target table exists yet.
	 *
	 * @param string $target_prefix Future isolated target prefix.
	 */
	private function target_tables_untouched( string $target_prefix ): bool {
		global $wpdb;
		if ( ! $wpdb instanceof wpdb || 1 !== preg_match( '/^[A-Za-z0-9_]+$/', $target_prefix ) ) {
			return false;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Read-only guard over the exact future target prefix.
		$tables = $wpdb->get_col(
			$wpdb->prepare(
				'SHOW TABLES LIKE %s',
				$wpdb->esc_like( $target_prefix ) . '%'
			)
		);

		return array() === $tables;
	}

	/**
	 * Confirm local staging tables cannot match production or future target prefixes.
	 *
	 * @param string $staging_namespace Job-owned staging namespace.
	 * @param string $target_prefix      Future target prefix.
	 */
	private function staging_namespace_isolated( string $staging_namespace, string $target_prefix ): bool {
		global $wpdb;
		return $wpdb instanceof wpdb
			&& 1 === preg_match( '/^[A-Za-z0-9_]+$/', $staging_namespace )
			&& '' !== $staging_namespace
			&& ! str_starts_with( $staging_namespace, $wpdb->prefix )
			&& ! str_starts_with( $staging_namespace, $target_prefix );
	}

	/**
	 * Revoke child restore eligibility after parent authority drift.
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
	 * Revoke child eligibility from a saved parent state when authority cannot resolve.
	 *
	 * @param array<string,mixed>|null $state Saved parent state.
	 * @param string                   $code  Stable blocker code.
	 */
	private function lock_child_from_parent_state( ?array $state, string $code ): void {
		if ( ! is_array( $state ) || ! is_string( $state['child_import_job_id'] ?? null ) || '' === $state['child_import_job_id'] ) {
			return;
		}

		$this->lock_child( (string) $state['child_import_job_id'], $code );
	}

	/**
	 * Persist one retryable parent blocker.
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

		$state['schema_version']           = LocalCloneDatabaseRestoreStateStore::SCHEMA_VERSION;
		$state['job_id']                   = $job_id;
		$state['status']                   = 'blocked';
		$state['stage']                    = is_string( $state['stage'] ?? null ) ? $state['stage'] : 'schema';
		$state['active_tables_untouched']  = true === ( $state['active_tables_untouched'] ?? false );
		$state['target_tables_untouched']  = true === ( $state['target_tables_untouched'] ?? false );
		$state['client_content_untouched'] = true === ( $state['client_content_untouched'] ?? false );
		$state['blockers']                 = array_values( array_unique( array_filter( $blockers, 'is_string' ) ) );
		$state['started_at']               = (string) ( $state['started_at'] ?? $now );
		$state['updated_at']               = $now;

		if ( ! $this->store->save( $job_id, $state ) ) {
			return null;
		}

		$this->jobs->transition( $job_id, 'failed-retryable', $code );

		return $this->store->get( $job_id );
	}
}
