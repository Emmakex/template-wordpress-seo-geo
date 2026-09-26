<?php
/**
 * Portable Clone local private file staging.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge\Clone;

/**
 * Reuses Portable Import file staging under local-clone parent authority.
 */
final class LocalCloneFileRestorer {
	/**
	 * Parent file staging state.
	 *
	 * @var LocalCloneFileRestoreStateStore
	 */
	private LocalCloneFileRestoreStateStore $store;

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
	 * Verified local database staging.
	 *
	 * @var LocalCloneDatabaseRestorer
	 */
	private LocalCloneDatabaseRestorer $database;

	/**
	 * Existing Portable Import file restorer.
	 *
	 * @var ImportFileRestorer
	 */
	private ImportFileRestorer $restorer;

	/**
	 * Existing child file state.
	 *
	 * @var ImportFileStateStore
	 */
	private ImportFileStateStore $file_state;

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
	 * Private workspace authority.
	 *
	 * @var ExportWorkspace
	 */
	private ExportWorkspace $workspace;

	/**
	 * Construct local file restorer.
	 *
	 * @param LocalCloneFileRestoreStateStore|null $store        Optional parent state store.
	 * @param LocalClonePackageHandoff|null        $handoff      Optional verified handoff.
	 * @param LocalClonePayloadVerifier|null       $payload      Optional verified payload.
	 * @param LocalCloneDatabaseRestorer|null      $database     Optional verified database staging.
	 * @param ImportFileRestorer|null              $restorer     Optional existing file restorer.
	 * @param ImportFileStateStore|null            $file_state   Optional child file state.
	 * @param ImportStateStore|null                $import_state Optional child import state.
	 * @param CloneJobStore|null                   $jobs         Optional clone jobs.
	 * @param ExportWorkspace|null                 $workspace    Optional private workspace.
	 */
	public function __construct(
		?LocalCloneFileRestoreStateStore $store = null,
		?LocalClonePackageHandoff $handoff = null,
		?LocalClonePayloadVerifier $payload = null,
		?LocalCloneDatabaseRestorer $database = null,
		?ImportFileRestorer $restorer = null,
		?ImportFileStateStore $file_state = null,
		?ImportStateStore $import_state = null,
		?CloneJobStore $jobs = null,
		?ExportWorkspace $workspace = null
	) {
		$this->store        = $store ?? new LocalCloneFileRestoreStateStore();
		$this->handoff      = $handoff ?? new LocalClonePackageHandoff();
		$this->payload      = $payload ?? new LocalClonePayloadVerifier();
		$this->database     = $database ?? new LocalCloneDatabaseRestorer();
		$this->restorer     = $restorer ?? new ImportFileRestorer();
		$this->file_state   = $file_state ?? new ImportFileStateStore();
		$this->import_state = $import_state ?? new ImportStateStore();
		$this->jobs         = $jobs ?? new CloneJobStore();
		$this->workspace    = $workspace ?? new ExportWorkspace();
	}

	/**
	 * Return one parent file staging snapshot.
	 *
	 * @param string $job_id Parent local-clone job identifier.
	 * @return array<string,mixed>|null
	 */
	public function snapshot( string $job_id ): ?array {
		return $this->store->get( $job_id );
	}

	/**
	 * Return completed private file staging only while authority/isolation still holds.
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
			|| true !== ( $state['active_roots_untouched'] ?? false )
			|| true !== ( $state['target_client_roots_untouched'] ?? false )
			|| true !== ( $state['private_staging_verified'] ?? false )
			|| array() !== ( $state['blockers'] ?? array() )
		) {
			return null;
		}

		$authority = $this->authority( $job_id, false );
		if ( null === $authority || ! $this->state_matches_authority( $state, $authority ) ) {
			return null;
		}

		$child_id = (string) $state['child_import_job_id'];
		$files    = $this->file_state->get( $child_id );
		$staging  = $this->workspace->import_file_staging_root( $child_id );
		if (
			! is_array( $files )
			|| 'complete' !== ( $files['status'] ?? null )
			|| 'complete' !== ( $files['stage'] ?? null )
			|| true !== ( $files['active_roots_untouched'] ?? false )
			|| (int) ( $files['file_count'] ?? -1 ) !== (int) $state['file_count']
			|| (int) ( $files['byte_count'] ?? -1 ) !== (int) $state['byte_count']
			|| (int) ( $files['verify_file_count'] ?? -1 ) !== (int) $state['verify_file_count']
			|| (int) ( $files['verify_byte_count'] ?? -1 ) !== (int) $state['verify_byte_count']
			|| ! hash_equals( (string) $state['files_manifest_sha256'], (string) ( $files['files_manifest_sha256'] ?? '' ) )
			|| ! is_string( $staging )
			|| ! hash_equals( (string) $state['staging_root_sha256'], hash( 'sha256', wp_normalize_path( $staging ) ) )
			|| ! $this->private_staging_root( $child_id, $staging )
			|| ! $this->target_client_roots_untouched( (string) $authority['handoff']['target_path'] )
		) {
			return null;
		}

		return $state;
	}

	/**
	 * Return verified private staging root after completion.
	 *
	 * @param string $job_id Parent local-clone job identifier.
	 */
	public function staging_root( string $job_id ): ?string {
		$state = $this->verified_snapshot( $job_id );
		if ( ! is_array( $state ) ) {
			return null;
		}

		return $this->workspace->import_file_staging_root( (string) $state['child_import_job_id'] );
	}

	/**
	 * Advance one bounded private file staging batch.
	 *
	 * @param string $job_id      Parent local-clone job identifier.
	 * @param int    $batch_files Maximum files this request.
	 * @param int    $batch_bytes Soft maximum bytes this request.
	 * @return array<string,mixed>|null
	 */
	public function advance(
		string $job_id,
		int $batch_files = ImportFileRestorer::DEFAULT_BATCH_FILES,
		int $batch_bytes = ImportFileRestorer::DEFAULT_BATCH_BYTES
	): ?array {
		$ready = $this->verified_snapshot( $job_id );
		if ( is_array( $ready ) ) {
			$this->jobs->transition( $job_id, 'active', null );
			$this->jobs->update_progress(
				$job_id,
				'restore-files',
				'local-file-staging-complete',
				array(
					'completed' => (int) ( $ready['file_count'] ?? 0 ),
					'total'     => (int) ( $ready['expected_file_count'] ?? 0 ),
				)
			);

			return $ready;
		}

		$existing  = $this->store->get( $job_id );
		$authority = $this->authority( $job_id, true );
		if ( null === $authority ) {
			$this->lock_child_from_parent_state( $existing, 'local-files-parent-authority-unavailable' );

			return $this->block(
				$job_id,
				$existing ?? array(),
				'local-files-parent-authority-unavailable'
			);
		}

		$child_id = (string) $authority['payload']['child_import_job_id'];
		$files    = $this->restorer->advance( $child_id, $batch_files, $batch_bytes );
		if ( ! is_array( $files ) ) {
			return $this->block(
				$job_id,
				$existing ?? array(),
				'local-files-restorer-unavailable'
			);
		}

		if ( 'blocked' === ( $files['status'] ?? null ) ) {
			$blockers = is_array( $files['blockers'] ?? null ) ? $files['blockers'] : array();
			$code     = is_string( $blockers[0] ?? null )
				? (string) $blockers[0]
				: 'local-files-staging-blocked';

			return $this->block( $job_id, $existing ?? array(), $code );
		}

		$fresh = $this->authority( $job_id, false );
		if ( null === $fresh ) {
			$this->lock_child( $child_id, 'local-files-parent-authority-changed' );

			return $this->block(
				$job_id,
				$existing ?? array(),
				'local-files-parent-authority-changed'
			);
		}

		$staging = $this->workspace->import_file_staging_root( $child_id );
		if (
			! is_string( $staging )
			|| ! $this->private_staging_root( $child_id, $staging )
			|| ! $this->target_client_roots_untouched( (string) $fresh['handoff']['target_path'] )
			|| true !== ( $files['active_roots_untouched'] ?? false )
		) {
			$this->lock_child( $child_id, 'local-files-isolation-guard-failed' );

			return $this->block(
				$job_id,
				$existing ?? array(),
				'local-files-isolation-guard-failed'
			);
		}

		$complete = 'complete' === ( $files['status'] ?? null )
			&& 'complete' === ( $files['stage'] ?? null )
			&& (int) ( $files['file_count'] ?? -1 ) === (int) ( $files['expected_file_count'] ?? -2 )
			&& (int) ( $files['byte_count'] ?? -1 ) === (int) ( $files['expected_byte_count'] ?? -2 )
			&& (int) ( $files['verify_file_count'] ?? -1 ) === (int) ( $files['expected_file_count'] ?? -2 )
			&& (int) ( $files['verify_byte_count'] ?? -1 ) === (int) ( $files['expected_byte_count'] ?? -2 );

		if ( 'complete' === ( $files['status'] ?? null ) && ! $complete ) {
			$this->lock_child( $child_id, 'local-files-final-reconciliation-failed' );

			return $this->block(
				$job_id,
				$existing ?? array(),
				'local-files-final-reconciliation-failed'
			);
		}

		$now   = gmdate( DATE_ATOM );
		$state = array(
			'schema_version'                => LocalCloneFileRestoreStateStore::SCHEMA_VERSION,
			'job_id'                        => $job_id,
			'status'                        => $complete ? 'ready' : 'running',
			'stage'                         => $complete ? 'complete' : (string) ( $files['stage'] ?? 'copy' ),
			'child_import_job_id'           => $child_id,
			'destination_authority_sha256'  => (string) $fresh['payload']['destination_authority_sha256'],
			'archive_sha256'                => (string) $fresh['handoff']['archive_sha256'],
			'package_manifest_sha256'       => (string) $fresh['handoff']['package_manifest_hash'],
			'package_checksum'              => (string) $fresh['handoff']['package_checksum'],
			'files_manifest_sha256'         => (string) ( $files['files_manifest_sha256'] ?? '' ),
			'staging_root_sha256'           => hash( 'sha256', wp_normalize_path( $staging ) ),
			'file_count'                    => (int) ( $files['file_count'] ?? 0 ),
			'byte_count'                    => (int) ( $files['byte_count'] ?? 0 ),
			'verify_file_count'             => (int) ( $files['verify_file_count'] ?? 0 ),
			'verify_byte_count'             => (int) ( $files['verify_byte_count'] ?? 0 ),
			'expected_file_count'           => (int) ( $files['expected_file_count'] ?? 0 ),
			'expected_byte_count'           => (int) ( $files['expected_byte_count'] ?? 0 ),
			'active_roots_untouched'        => true,
			'target_client_roots_untouched' => true,
			'private_staging_verified'      => $complete,
			'file_next'                     => $complete ? 'environment-rewrite' : 'file-staging-restore',
			'blockers'                      => array(),
			'started_at'                    => (string) ( $existing['started_at'] ?? $now ),
			'updated_at'                    => $now,
			'ready_at'                      => $complete ? $now : '',
		);

		if ( ! $this->store->save( $job_id, $state ) ) {
			return null;
		}

		$this->jobs->transition( $job_id, 'active', null );
		$this->jobs->update_progress(
			$job_id,
			'restore-files',
			$complete ? 'local-file-staging-complete' : 'local-files-' . (string) $state['stage'],
			array(
				'completed' => 'verify' === $state['stage']
					? (int) $state['verify_file_count']
					: (int) $state['file_count'],
				'total'     => (int) $state['expected_file_count'],
			)
		);

		return $this->store->get( $job_id );
	}

	/**
	 * Resolve current local handoff/payload/database/import authority.
	 *
	 * @param string $job_id          Parent local-clone job identifier.
	 * @param bool   $attempt_recover Whether to retry database/payload authority first.
	 * @return array{handoff:array<string,mixed>,payload:array<string,mixed>,database:array<string,mixed>,import:array<string,mixed>}|null
	 */
	private function authority( string $job_id, bool $attempt_recover ): ?array {
		$handoff  = $this->handoff->verified_snapshot( $job_id );
		$payload  = $this->payload->verified_snapshot( $job_id );
		$database = $this->database->verified_snapshot( $job_id );
		if ( ! is_array( $database ) && $attempt_recover ) {
			$this->database->advance( $job_id );
			$database = $this->database->verified_snapshot( $job_id );
			$payload  = $this->payload->verified_snapshot( $job_id );
			$handoff  = $this->handoff->verified_snapshot( $job_id );
		}
		if ( ! is_array( $handoff ) || ! is_array( $payload ) || ! is_array( $database ) ) {
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
			|| (string) ( $database['child_import_job_id'] ?? '' ) !== $child_id
			|| ! hash_equals( (string) $handoff['archive_sha256'], (string) ( $import['archive_sha256'] ?? '' ) )
			|| ! hash_equals( (string) $handoff['package_manifest_hash'], (string) ( $import['package_manifest_sha256'] ?? '' ) )
			|| ! hash_equals( (string) $handoff['package_checksum'], (string) ( $import['package_checksum'] ?? '' ) )
			|| ! hash_equals( (string) $handoff['target_table_prefix'], (string) ( $import['destination_table_prefix'] ?? '' ) )
		) {
			return null;
		}

		return array(
			'handoff'  => $handoff,
			'payload'  => $payload,
			'database' => $database,
			'import'   => $import,
		);
	}

	/**
	 * Confirm saved parent state still matches current authority.
	 *
	 * @param array<string,mixed> $state     Parent file state.
	 * @param array<string,mixed> $authority Current authority.
	 */
	private function state_matches_authority( array $state, array $authority ): bool {
		return hash_equals( (string) $state['child_import_job_id'], (string) $authority['payload']['child_import_job_id'] )
			&& hash_equals( (string) $state['destination_authority_sha256'], (string) $authority['payload']['destination_authority_sha256'] )
			&& hash_equals( (string) $state['archive_sha256'], (string) $authority['handoff']['archive_sha256'] )
			&& hash_equals( (string) $state['package_manifest_sha256'], (string) $authority['handoff']['package_manifest_hash'] )
			&& hash_equals( (string) $state['package_checksum'], (string) $authority['handoff']['package_checksum'] );
	}

	/**
	 * Confirm staging root remains inside the child private workspace.
	 *
	 * @param string $child_id Child import job identifier.
	 * @param string $staging  Staging root.
	 */
	private function private_staging_root( string $child_id, string $staging ): bool {
		$root = $this->workspace->root_path( $child_id );
		if ( null === $root ) {
			return false;
		}

		$root    = trailingslashit( wp_normalize_path( $root ) );
		$staging = trailingslashit( wp_normalize_path( $staging ) );

		return is_dir( $staging )
			&& ! is_link( untrailingslashit( $staging ) )
			&& str_starts_with( $staging, $root )
			&& str_contains( $staging, '/import/staged-files/' );
	}

	/**
	 * Confirm client file roots have not been promoted into the target.
	 *
	 * Migration Bridge itself is the only allowed normal plugin entry before promotion.
	 *
	 * @param string $target_path Verified isolated target path.
	 */
	private function target_client_roots_untouched( string $target_path ): bool {
		$target = untrailingslashit( wp_normalize_path( $target_path ) );
		if ( '' === $target || ! is_dir( $target ) || is_link( $target ) ) {
			return false;
		}

		if (
			file_exists( $target . '/wp-content/uploads' )
			|| file_exists( $target . '/wp-content/themes' )
		) {
			return false;
		}

		$plugins = $target . '/wp-content/plugins';
		if ( ! is_dir( $plugins ) || is_link( $plugins ) ) {
			return false;
		}

		$entries = scandir( $plugins, SCANDIR_SORT_ASCENDING );
		if ( false === $entries ) {
			return false;
		}

		$entries = array_values( array_diff( $entries, array( '.', '..' ) ) );

		return array() === array_values(
			array_diff(
				$entries,
				array( 'seo-geo-migration-bridge' )
			)
		);
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

		$state['schema_version']                = LocalCloneFileRestoreStateStore::SCHEMA_VERSION;
		$state['job_id']                        = $job_id;
		$state['status']                        = 'blocked';
		$state['stage']                         = is_string( $state['stage'] ?? null ) ? $state['stage'] : 'copy';
		$state['active_roots_untouched']        = true === ( $state['active_roots_untouched'] ?? false );
		$state['target_client_roots_untouched'] = true === ( $state['target_client_roots_untouched'] ?? false );
		$state['private_staging_verified']      = false;
		$state['blockers']                      = array_values( array_unique( array_filter( $blockers, 'is_string' ) ) );
		$state['started_at']                    = (string) ( $state['started_at'] ?? $now );
		$state['updated_at']                    = $now;

		if ( ! $this->store->save( $job_id, $state ) ) {
			return null;
		}

		$this->jobs->transition( $job_id, 'failed-retryable', $code );

		return $this->store->get( $job_id );
	}
}
