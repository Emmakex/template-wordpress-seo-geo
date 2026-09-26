<?php
/**
 * Portable Clone local reversible file promotion.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge\Clone;

/**
 * Binds the accepted file promoter to the verified local-clone parent authority.
 */
final class LocalCloneFilePromoter {
	/**
	 * Parent promotion state.
	 *
	 * @var LocalCloneFilePromotionStateStore
	 */
	private LocalCloneFilePromotionStateStore $store;

	/**
	 * Pre-promotion local DB authority.
	 *
	 * @var LocalCloneDatabaseActivator
	 */
	private LocalCloneDatabaseActivator $database;

	/**
	 * Persistent parent DB state.
	 *
	 * @var LocalCloneDatabaseActivationStateStore
	 */
	private LocalCloneDatabaseActivationStateStore $database_parent_state;

	/**
	 * Existing child file promoter.
	 *
	 * @var ImportFilePromoter
	 */
	private ImportFilePromoter $promoter;

	/**
	 * Child file-promotion journal.
	 *
	 * @var ImportFilePromotionStateStore
	 */
	private ImportFilePromotionStateStore $promotion_state;

	/**
	 * Child DB activation journal.
	 *
	 * @var ImportDatabaseActivationStateStore
	 */
	private ImportDatabaseActivationStateStore $database_state;

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
	 * Construct local file promoter.
	 *
	 * @param LocalCloneFilePromotionStateStore|null      $store                 Optional parent promotion state.
	 * @param LocalCloneDatabaseActivator|null            $database              Optional pre-promotion DB authority.
	 * @param LocalCloneDatabaseActivationStateStore|null $database_parent_state Optional persistent parent DB state.
	 * @param ImportFilePromoter|null                     $promoter              Optional child promoter.
	 * @param ImportFilePromotionStateStore|null          $promotion_state       Optional child promotion journal.
	 * @param ImportDatabaseActivationStateStore|null     $database_state        Optional child DB journal.
	 * @param ImportStateStore|null                       $import_state          Optional child import state.
	 * @param CloneJobStore|null                          $jobs                  Optional parent clone jobs.
	 */
	public function __construct(
		?LocalCloneFilePromotionStateStore $store = null,
		?LocalCloneDatabaseActivator $database = null,
		?LocalCloneDatabaseActivationStateStore $database_parent_state = null,
		?ImportFilePromoter $promoter = null,
		?ImportFilePromotionStateStore $promotion_state = null,
		?ImportDatabaseActivationStateStore $database_state = null,
		?ImportStateStore $import_state = null,
		?CloneJobStore $jobs = null
	) {
		$this->store                 = $store ?? new LocalCloneFilePromotionStateStore();
		$this->database              = $database ?? new LocalCloneDatabaseActivator();
		$this->database_parent_state = $database_parent_state ?? new LocalCloneDatabaseActivationStateStore();
		$this->promoter              = $promoter ?? new ImportFilePromoter();
		$this->promotion_state       = $promotion_state ?? new ImportFilePromotionStateStore();
		$this->database_state        = $database_state ?? new ImportDatabaseActivationStateStore();
		$this->import_state          = $import_state ?? new ImportStateStore();
		$this->jobs                  = $jobs ?? new CloneJobStore();
	}

	/**
	 * Return one parent promotion snapshot.
	 *
	 * @param string $job_id Parent local-clone job identifier.
	 * @return array<string,mixed>|null
	 */
	public function snapshot( string $job_id ): ?array {
		return $this->store->get( $job_id );
	}

	/**
	 * Return verified final local file-promotion evidence.
	 *
	 * @param string $job_id Parent local-clone job identifier.
	 * @return array<string,mixed>|null
	 */
	public function verified_snapshot( string $job_id ): ?array {
		$state = $this->store->get( $job_id );
		if (
			! is_array( $state )
			|| 'verified' !== ( $state['status'] ?? null )
			|| true !== ( $state['database_activated'] ?? false )
			|| true !== ( $state['rollback_available'] ?? false )
			|| true !== ( $state['handoff_ready'] ?? false )
			|| true !== ( $state['source_untouched'] ?? false )
			|| array() !== ( $state['blockers'] ?? array() )
		) {
			return null;
		}

		$authority = $this->post_promotion_authority( $job_id, $state );
		if ( null === $authority ) {
			return null;
		}

		$child = $this->promotion_state->get( (string) $state['child_import_job_id'] );
		if (
			! is_array( $child )
			|| 'verified' !== ( $child['status'] ?? null )
			|| true !== ( $child['database_activated'] ?? false )
			|| true !== ( $child['rollback_available'] ?? false )
			|| true !== ( $child['handoff_ready'] ?? false )
			|| ! hash_equals( (string) $state['activation_plan_hash'], (string) ( $child['activation_plan_hash'] ?? '' ) )
			|| ! hash_equals( (string) $state['file_fingerprint'], (string) ( $child['file_fingerprint'] ?? '' ) )
			|| ! hash_equals( (string) $state['active_fingerprint'], (string) ( $child['active_fingerprint'] ?? '' ) )
			|| ! hash_equals( (string) $state['file_fingerprint'], (string) ( $child['active_fingerprint'] ?? '' ) )
			|| ! $this->roots_match_local_target( $child, $authority['import'] )
		) {
			return null;
		}

		return $state;
	}

	/**
	 * Freeze local file promotion journal without copying client files.
	 *
	 * @param string $job_id Parent local-clone job identifier.
	 * @return array<string,mixed>|null
	 */
	public function prepare( string $job_id ): ?array {
		$existing = $this->store->get( $job_id );
		if ( is_array( $existing ) ) {
			return $existing;
		}

		$authority = $this->pre_promotion_authority( $job_id );
		if ( null === $authority ) {
			return null;
		}

		$child_id = (string) $authority['database']['child_import_job_id'];
		$child    = $this->promoter->prepare( $child_id );
		if (
			! is_array( $child )
			|| 'prepared' !== ( $child['status'] ?? null )
			|| true !== ( $child['database_activated'] ?? false )
			|| true !== ( $child['rollback_available'] ?? false )
			|| true === ( $child['handoff_ready'] ?? true )
			|| array() !== ( $child['blockers'] ?? array() )
			|| ! $this->child_matches_database( $child, $authority['database'] )
			|| ! $this->roots_match_local_target( $child, $authority['import'] )
		) {
			return null;
		}

		$state = $this->parent_state( $job_id, $child, $authority['database'], $authority['import'] );
		if ( ! $this->store->save( $job_id, $state ) ) {
			return null;
		}

		$this->refresh_parent_progress( $job_id, $state );

		return $this->store->get( $job_id );
	}

	/**
	 * Copy verified staging files into same-filesystem candidate roots.
	 *
	 * @param string $job_id      Parent local-clone job identifier.
	 * @param int    $batch_files Maximum files copied.
	 * @param int    $batch_bytes Soft maximum bytes copied.
	 * @return array<string,mixed>|null
	 */
	public function advance_candidates(
		string $job_id,
		int $batch_files = ImportFilePromoter::DEFAULT_BATCH_FILES,
		int $batch_bytes = ImportFilePromoter::DEFAULT_BATCH_BYTES
	): ?array {
		$state = $this->store->get( $job_id ) ?? $this->prepare( $job_id );
		if (
			! is_array( $state )
			|| ! in_array( $state['status'] ?? null, array( 'prepared', 'copying', 'candidate-ready' ), true )
		) {
			return $state;
		}

		$authority = $this->pre_promotion_authority( $job_id );
		if ( null === $authority || ! $this->parent_matches_database( $state, $authority['database'] ) ) {
			return $this->block( $job_id, $state, 'local-file-promotion-authority-changed' );
		}

		$child = $this->promoter->advance_candidates(
			(string) $state['child_import_job_id'],
			$batch_files,
			$batch_bytes
		);
		if (
			! is_array( $child )
			|| ! in_array( $child['status'] ?? null, array( 'copying', 'candidate-ready' ), true )
			|| ! $this->child_matches_database( $child, $authority['database'] )
			|| ! $this->roots_match_local_target( $child, $authority['import'] )
		) {
			return $this->block( $job_id, $state, 'local-file-promotion-candidate-verify-failed' );
		}

		return $this->persist_child( $job_id, $child, $authority['database'], $authority['import'] );
	}

	/**
	 * Swap verified candidates into isolated target roots.
	 *
	 * @param string $job_id Parent local-clone job identifier.
	 * @return array<string,mixed>|null
	 */
	public function promote( string $job_id ): ?array {
		$state = $this->store->get( $job_id );
		if ( ! is_array( $state ) || 'candidate-ready' !== ( $state['status'] ?? null ) ) {
			return $state;
		}

		$authority = $this->pre_promotion_authority( $job_id );
		if ( null === $authority || ! $this->parent_matches_database( $state, $authority['database'] ) ) {
			return $this->block( $job_id, $state, 'local-file-promotion-authority-changed' );
		}

		$child = $this->promoter->promote( (string) $state['child_import_job_id'] );
		if (
			! is_array( $child )
			|| 'verifying' !== ( $child['status'] ?? null )
			|| ! $this->child_matches_database( $child, $authority['database'] )
			|| ! $this->roots_match_local_target( $child, $authority['import'] )
		) {
			return $this->block( $job_id, $state, 'local-file-promotion-swap-failed' );
		}

		return $this->persist_child( $job_id, $child, $authority['database'], $authority['import'] );
	}

	/**
	 * Verify promoted roots in bounded batches and complete local handoff.
	 *
	 * @param string $job_id      Parent local-clone job identifier.
	 * @param int    $batch_files Maximum files verified.
	 * @param int    $batch_bytes Soft maximum bytes verified.
	 * @return array<string,mixed>|null
	 */
	public function advance_verification(
		string $job_id,
		int $batch_files = ImportFilePromoter::DEFAULT_BATCH_FILES,
		int $batch_bytes = ImportFilePromoter::DEFAULT_BATCH_BYTES
	): ?array {
		$state = $this->store->get( $job_id );
		if ( ! is_array( $state ) || ! in_array( $state['status'] ?? null, array( 'verifying', 'verified' ), true ) ) {
			return $state;
		}
		if ( 'verified' === $state['status'] ) {
			return $this->verified_snapshot( $job_id ) ?? $state;
		}

		$authority = $this->post_promotion_authority( $job_id, $state );
		if ( null === $authority ) {
			return $this->block( $job_id, $state, 'local-file-promotion-post-swap-authority-lost' );
		}

		$child = $this->promoter->advance_verification(
			(string) $state['child_import_job_id'],
			$batch_files,
			$batch_bytes
		);
		if (
			! is_array( $child )
			|| ! in_array( $child['status'] ?? null, array( 'verifying', 'verified' ), true )
			|| ! $this->roots_match_local_target( $child, $authority['import'] )
		) {
			return $this->block( $job_id, $state, 'local-file-promotion-final-verify-failed' );
		}

		$database = $this->database_parent_state->get( $job_id );
		if ( ! is_array( $database ) ) {
			return $this->block( $job_id, $state, 'local-file-promotion-database-state-missing' );
		}

		$result = $this->persist_child( $job_id, $child, $database, $authority['import'] );
		if ( is_array( $result ) && 'verified' === ( $result['status'] ?? null ) ) {
			$this->jobs->transition( $job_id, 'complete', null );
			$this->jobs->update_progress(
				$job_id,
				'promote-files',
				'local-file-promotion-verified',
				array(
					'completed' => (int) ( $result['verify_file_count'] ?? 0 ),
					'total'     => (int) ( $result['verify_file_count'] ?? 0 ),
				)
			);
		}

		return $result;
	}

	/**
	 * Roll promoted local files back to the pre-promotion target layout.
	 *
	 * @param string $job_id Parent local-clone job identifier.
	 * @return array<string,mixed>|null
	 */
	public function rollback( string $job_id ): ?array {
		$state = $this->store->get( $job_id );
		if (
			! is_array( $state )
			|| ! in_array( $state['status'] ?? null, array( 'promoting', 'verifying', 'verified' ), true )
		) {
			return $state;
		}

		$authority = $this->post_promotion_authority( $job_id, $state );
		if ( null === $authority ) {
			return $this->block( $job_id, $state, 'local-file-promotion-rollback-authority-lost' );
		}

		$child = $this->promoter->rollback( (string) $state['child_import_job_id'] );
		if ( ! is_array( $child ) || 'rolled-back' !== ( $child['status'] ?? null ) ) {
			return $this->block( $job_id, $state, 'local-file-promotion-rollback-failed' );
		}

		$database = $this->database_parent_state->get( $job_id );
		if ( ! is_array( $database ) ) {
			return $this->block( $job_id, $state, 'local-file-promotion-database-state-missing' );
		}

		$result = $this->persist_child( $job_id, $child, $database, $authority['import'] );
		$this->jobs->transition( $job_id, 'failed-retryable', 'operator-file-promotion-rollback' );

		return $result;
	}

	/**
	 * Resolve mutation-free authority before active client roots move.
	 *
	 * @param string $job_id Parent local-clone job identifier.
	 * @return array{database:array<string,mixed>,import:array<string,mixed>}|null
	 */
	private function pre_promotion_authority( string $job_id ): ?array {
		$database = $this->database->verified_snapshot( $job_id );
		if ( ! is_array( $database ) ) {
			return null;
		}

		$child_id = (string) ( $database['child_import_job_id'] ?? '' );
		$import   = '' !== $child_id ? $this->import_state->get( $child_id ) : null;
		if ( ! $this->import_matches_database( $job_id, $database, $import ) ) {
			return null;
		}

		return array(
			'database' => $database,
			'import'   => $import,
		);
	}

	/**
	 * Resolve frozen authority after client roots have moved.
	 *
	 * @param string              $job_id Parent local-clone job identifier.
	 * @param array<string,mixed> $state  Parent promotion state.
	 * @return array{database:array<string,mixed>,child_database:array<string,mixed>,import:array<string,mixed>}|null
	 */
	private function post_promotion_authority( string $job_id, array $state ): ?array {
		$database = $this->database_parent_state->get( $job_id );
		if (
			! is_array( $database )
			|| 'activated' !== ( $database['status'] ?? null )
			|| true !== ( $database['database_swapped'] ?? false )
			|| true !== ( $database['rollback_available'] ?? false )
			|| true !== ( $database['target_database_active'] ?? false )
			|| array() !== ( $database['blockers'] ?? array() )
			|| ! $this->parent_matches_database( $state, $database )
		) {
			return null;
		}

		$child_id       = (string) ( $database['child_import_job_id'] ?? '' );
		$child_database = '' !== $child_id ? $this->database_state->get( $child_id ) : null;
		$import         = '' !== $child_id ? $this->import_state->get( $child_id ) : null;
		if (
			! is_array( $child_database )
			|| 'activated' !== ( $child_database['status'] ?? null )
			|| true !== ( $child_database['database_swapped'] ?? false )
			|| true !== ( $child_database['rollback_available'] ?? false )
			|| array() !== ( $child_database['blockers'] ?? array() )
			|| ! hash_equals(
				(string) $database['activation_plan_hash'],
				(string) ( $child_database['activation_plan_hash'] ?? '' )
			)
			|| ! $this->import_matches_database( $job_id, $database, $import )
		) {
			return null;
		}

		return array(
			'database'       => $database,
			'child_database' => $child_database,
			'import'         => $import,
		);
	}

	/**
	 * Build parent state from child promotion evidence.
	 *
	 * @param string              $job_id   Parent job identifier.
	 * @param array<string,mixed> $child    Child promotion state.
	 * @param array<string,mixed> $database Parent DB activation state.
	 * @param array<string,mixed> $import   Child import state.
	 * @return array<string,mixed>
	 */
	private function parent_state( string $job_id, array $child, array $database, array $import ): array {
		$status = (string) ( $child['status'] ?? 'prepared' );
		$root   = untrailingslashit( wp_normalize_path( (string) ( $import['destination_root_path'] ?? '' ) ) );
		$now    = gmdate( DATE_ATOM );

		return array(
			'schema_version'               => LocalCloneFilePromotionStateStore::SCHEMA_VERSION,
			'job_id'                       => $job_id,
			'status'                       => $status,
			'child_import_job_id'          => (string) $database['child_import_job_id'],
			'destination_authority_sha256' => (string) $database['destination_authority_sha256'],
			'activation_plan_hash'         => (string) $database['activation_plan_hash'],
			'file_fingerprint'             => (string) $database['file_fingerprint'],
			'copy_fingerprint'             => (string) ( $child['copy_fingerprint'] ?? '' ),
			'active_fingerprint'           => (string) ( $child['active_fingerprint'] ?? '' ),
			'target_root_sha256'           => hash( 'sha256', $root ),
			'file_count'                   => (int) ( $child['file_count'] ?? 0 ),
			'byte_count'                   => (int) ( $child['byte_count'] ?? 0 ),
			'verify_file_count'            => (int) ( $child['verify_file_count'] ?? 0 ),
			'verify_byte_count'            => (int) ( $child['verify_byte_count'] ?? 0 ),
			'database_activated'           => true,
			'rollback_available'           => true === ( $child['rollback_available'] ?? false ),
			'handoff_ready'                => true === ( $child['handoff_ready'] ?? false ),
			'source_untouched'             => $this->roots_match_local_target( $child, $import ),
			'promotion_next'               => $this->next_step( $status ),
			'blockers'                     => is_array( $child['blockers'] ?? null ) ? $child['blockers'] : array(),
			'prepared_at'                  => (string) ( $child['prepared_at'] ?? $now ),
			'promoted_at'                  => (string) ( $child['promoted_at'] ?? '' ),
			'verified_at'                  => (string) ( $child['verified_at'] ?? '' ),
			'rolled_back_at'               => (string) ( $child['rolled_back_at'] ?? '' ),
			'updated_at'                   => $now,
		);
	}

	/**
	 * Persist current child promotion state through parent evidence.
	 *
	 * @param string              $job_id   Parent job identifier.
	 * @param array<string,mixed> $child    Child promotion state.
	 * @param array<string,mixed> $database Parent DB activation state.
	 * @param array<string,mixed> $import   Child import state.
	 * @return array<string,mixed>|null
	 */
	private function persist_child( string $job_id, array $child, array $database, array $import ): ?array {
		$state = $this->parent_state( $job_id, $child, $database, $import );
		if ( ! $this->store->save( $job_id, $state ) ) {
			return null;
		}

		$this->refresh_parent_progress( $job_id, $state );

		return $this->store->get( $job_id );
	}

	/**
	 * Validate child promotion identity against frozen DB activation.
	 *
	 * @param array<string,mixed> $child    Child promotion state.
	 * @param array<string,mixed> $database Parent DB state.
	 */
	private function child_matches_database( array $child, array $database ): bool {
		return true === ( $child['database_activated'] ?? false )
			&& true === ( $child['rollback_available'] ?? false )
			&& hash_equals(
				(string) ( $database['activation_plan_hash'] ?? '' ),
				(string) ( $child['activation_plan_hash'] ?? '' )
			)
			&& hash_equals(
				(string) ( $database['file_fingerprint'] ?? '' ),
				(string) ( $child['file_fingerprint'] ?? '' )
			);
	}

	/**
	 * Validate parent promotion identity against DB activation.
	 *
	 * @param array<string,mixed> $state    Parent promotion state.
	 * @param array<string,mixed> $database Parent DB state.
	 */
	private function parent_matches_database( array $state, array $database ): bool {
		return (string) ( $state['child_import_job_id'] ?? '' ) === (string) ( $database['child_import_job_id'] ?? '' )
			&& hash_equals(
				(string) ( $state['destination_authority_sha256'] ?? '' ),
				(string) ( $database['destination_authority_sha256'] ?? '' )
			)
			&& hash_equals(
				(string) ( $state['activation_plan_hash'] ?? '' ),
				(string) ( $database['activation_plan_hash'] ?? '' )
			)
			&& hash_equals(
				(string) ( $state['file_fingerprint'] ?? '' ),
				(string) ( $database['file_fingerprint'] ?? '' )
			);
	}

	/**
	 * Validate child import against frozen DB authority.
	 *
	 * @param string                   $job_id   Parent job identifier.
	 * @param array<string,mixed>      $database Parent DB state.
	 * @param array<string,mixed>|null $import   Child import state.
	 */
	private function import_matches_database( string $job_id, array $database, ?array $import ): bool {
		return is_array( $import )
			&& 'payload-verified' === ( $import['status'] ?? null )
			&& 'private-same-server' === ( $import['transport'] ?? null )
			&& (string) ( $import['local_handoff_parent_job_id'] ?? '' ) === $job_id
			&& (string) ( $import['job_id'] ?? '' ) === (string) ( $database['child_import_job_id'] ?? '' )
			&& (string) ( $import['destination_table_prefix'] ?? '' ) === (string) ( $database['target_table_prefix'] ?? '' )
			&& true === ( $import['full_payload_verified'] ?? false )
			&& true === ( $import['restore_allowed'] ?? false )
			&& array() === ( $import['blockers'] ?? array() )
			&& hash_equals(
				(string) ( $import['destination_authority_sha256'] ?? '' ),
				(string) ( $database['destination_authority_sha256'] ?? '' )
			);
	}

	/**
	 * Prove every promotion path is scoped to the isolated target or private staging.
	 *
	 * @param array<string,mixed> $child  Child promotion state.
	 * @param array<string,mixed> $import Child import state.
	 */
	private function roots_match_local_target( array $child, array $import ): bool {
		$target = is_string( $import['destination_root_path'] ?? null )
			? untrailingslashit( wp_normalize_path( $import['destination_root_path'] ) )
			: '';
		if (
			'' === $target
			|| ! is_dir( $target )
			|| is_link( $target )
			|| untrailingslashit( wp_normalize_path( ABSPATH ) ) === $target
		) {
			return false;
		}

		$content = trailingslashit( wp_normalize_path( $target . '/wp-content' ) );
		$roots   = is_array( $child['roots'] ?? null ) ? array_values( $child['roots'] ) : array();
		if ( 3 !== count( $roots ) ) {
			return false;
		}

		$seen = array();
		foreach ( $roots as $root ) {
			if ( ! is_array( $root ) || ! is_string( $root['id'] ?? null ) ) {
				return false;
			}
			$id = $root['id'];
			if ( ! in_array( $id, array( 'uploads', 'plugins', 'themes' ), true ) || isset( $seen[ $id ] ) ) {
				return false;
			}
			$seen[ $id ] = true;

			$active    = trailingslashit( wp_normalize_path( (string) ( $root['active_path'] ?? '' ) ) );
			$candidate = wp_normalize_path( (string) ( $root['candidate_path'] ?? '' ) );
			$rollback  = wp_normalize_path( (string) ( $root['rollback_path'] ?? '' ) );
			$staging   = wp_normalize_path( (string) ( $root['staging_path'] ?? '' ) );
			if (
				$content . $id . '/' !== $active
				|| ! str_starts_with( $candidate, $content )
				|| ! str_starts_with( $rollback, $content )
				|| str_starts_with( $staging, $content )
			) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Map child status to the next local-clone action.
	 *
	 * @param string $status Child promotion status.
	 */
	private function next_step( string $status ): string {
		return match ( $status ) {
			'prepared', 'copying' => 'build-file-candidates',
			'candidate-ready'     => 'promote-files',
			'promoting', 'verifying' => 'verify-promoted-files',
			'verified'            => 'local-clone-handoff-ready',
			'rolled-back'         => 'database-rollback-or-cleanup',
			default               => 'review-file-promotion',
		};
	}

	/**
	 * Refresh parent job progress.
	 *
	 * @param string              $job_id Parent job identifier.
	 * @param array<string,mixed> $state  Parent state.
	 */
	private function refresh_parent_progress( string $job_id, array $state ): void {
		$status = (string) ( $state['status'] ?? 'prepared' );
		if ( 'verified' !== $status ) {
			$this->jobs->transition( $job_id, 'active', null );
		}
		$this->jobs->update_progress(
			$job_id,
			'promote-files',
			'local-file-' . $status,
			array(
				'completed' => in_array( $status, array( 'verifying', 'verified' ), true )
					? (int) ( $state['verify_file_count'] ?? 0 )
					: (int) ( $state['file_count'] ?? 0 ),
				'total'     => max(
					(int) ( $state['file_count'] ?? 0 ),
					(int) ( $state['verify_file_count'] ?? 0 )
				),
			)
		);
	}

	/**
	 * Persist one parent blocker.
	 *
	 * @param string              $job_id Parent job identifier.
	 * @param array<string,mixed> $state  Current parent state.
	 * @param string              $code   Stable blocker.
	 * @return array<string,mixed>|null
	 */
	private function block( string $job_id, array $state, string $code ): ?array {
		$now        = gmdate( DATE_ATOM );
		$blockers   = is_array( $state['blockers'] ?? null ) ? $state['blockers'] : array();
		$blockers[] = $code;

		$state['schema_version']   = LocalCloneFilePromotionStateStore::SCHEMA_VERSION;
		$state['job_id']           = $job_id;
		$state['status']           = 'blocked';
		$state['handoff_ready']    = false;
		$state['source_untouched'] = true === ( $state['source_untouched'] ?? false );
		$state['promotion_next']   = 'review-file-promotion';
		$state['blockers']         = array_values( array_unique( array_filter( $blockers, 'is_string' ) ) );
		$state['updated_at']       = $now;
		if ( ! $this->store->save( $job_id, $state ) ) {
			return null;
		}

		$this->jobs->transition( $job_id, 'failed-retryable', $code );

		return $this->store->get( $job_id );
	}
}
