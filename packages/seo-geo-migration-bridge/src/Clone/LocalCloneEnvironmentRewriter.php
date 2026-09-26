<?php
/**
 * Portable Clone local serialization-safe environment rewrite.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge\Clone;

/**
 * Reuses Portable Import environment rewrite under local-clone parent authority.
 */
final class LocalCloneEnvironmentRewriter {
	/**
	 * Parent rewrite state.
	 *
	 * @var LocalCloneEnvironmentRewriteStateStore
	 */
	private LocalCloneEnvironmentRewriteStateStore $store;

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
	 * Verified local file staging.
	 *
	 * @var LocalCloneFileRestorer
	 */
	private LocalCloneFileRestorer $files;

	/**
	 * Existing Portable Import environment rewriter.
	 *
	 * @var ImportEnvironmentRewriter
	 */
	private ImportEnvironmentRewriter $rewriter;

	/**
	 * Existing child rewrite state.
	 *
	 * @var ImportRewriteStateStore
	 */
	private ImportRewriteStateStore $rewrite_state;

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
	 * Construct local environment rewriter.
	 *
	 * @param LocalCloneEnvironmentRewriteStateStore|null $store         Optional parent state store.
	 * @param LocalClonePackageHandoff|null               $handoff       Optional verified handoff.
	 * @param LocalClonePayloadVerifier|null              $payload       Optional verified payload.
	 * @param LocalCloneDatabaseRestorer|null             $database      Optional verified database staging.
	 * @param LocalCloneFileRestorer|null                 $files         Optional verified file staging.
	 * @param ImportEnvironmentRewriter|null              $rewriter      Optional existing environment rewriter.
	 * @param ImportRewriteStateStore|null                $rewrite_state Optional child rewrite state.
	 * @param ImportStateStore|null                       $import_state  Optional child import state.
	 * @param CloneJobStore|null                          $jobs          Optional clone jobs.
	 */
	public function __construct(
		?LocalCloneEnvironmentRewriteStateStore $store = null,
		?LocalClonePackageHandoff $handoff = null,
		?LocalClonePayloadVerifier $payload = null,
		?LocalCloneDatabaseRestorer $database = null,
		?LocalCloneFileRestorer $files = null,
		?ImportEnvironmentRewriter $rewriter = null,
		?ImportRewriteStateStore $rewrite_state = null,
		?ImportStateStore $import_state = null,
		?CloneJobStore $jobs = null
	) {
		$this->store         = $store ?? new LocalCloneEnvironmentRewriteStateStore();
		$this->handoff       = $handoff ?? new LocalClonePackageHandoff();
		$this->payload       = $payload ?? new LocalClonePayloadVerifier();
		$this->database      = $database ?? new LocalCloneDatabaseRestorer();
		$this->files         = $files ?? new LocalCloneFileRestorer();
		$this->rewriter      = $rewriter ?? new ImportEnvironmentRewriter();
		$this->rewrite_state = $rewrite_state ?? new ImportRewriteStateStore();
		$this->import_state  = $import_state ?? new ImportStateStore();
		$this->jobs          = $jobs ?? new CloneJobStore();
	}

	/**
	 * Return one parent rewrite snapshot.
	 *
	 * @param string $job_id Parent local-clone job identifier.
	 * @return array<string,mixed>|null
	 */
	public function snapshot( string $job_id ): ?array {
		return $this->store->get( $job_id );
	}

	/**
	 * Return completed rewrite only while parent/child authority remains current.
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
			|| true !== ( $state['active_roots_untouched'] ?? false )
			|| true !== ( $state['target_unactivated'] ?? false )
			|| 0 !== (int) ( $state['verify_source_urls'] ?? -1 )
			|| array() !== ( $state['blockers'] ?? array() )
		) {
			return null;
		}

		$authority = $this->authority( $job_id, false );
		if ( null === $authority || ! $this->state_matches_authority( $state, $authority ) ) {
			return null;
		}

		$child_id = (string) $state['child_import_job_id'];
		$rewrite  = $this->rewrite_state->get( $child_id );
		if (
			! is_array( $rewrite )
			|| 'complete' !== ( $rewrite['status'] ?? null )
			|| 'complete' !== ( $rewrite['stage'] ?? null )
			|| true !== ( $rewrite['active_tables_untouched'] ?? false )
			|| true !== ( $rewrite['active_roots_untouched'] ?? false )
			|| 0 !== (int) ( $rewrite['verify_source_urls'] ?? -1 )
			|| ! hash_equals( (string) $state['database_manifest_sha256'], (string) ( $rewrite['database_manifest_sha256'] ?? '' ) )
			|| ! hash_equals( (string) $state['file_manifest_sha256'], (string) ( $rewrite['file_manifest_sha256'] ?? '' ) )
			|| ! $this->same_url( (string) $state['source_home_url'], (string) ( $rewrite['source_home_url'] ?? '' ) )
			|| ! $this->same_url( (string) $state['source_site_url'], (string) ( $rewrite['source_site_url'] ?? '' ) )
			|| ! $this->same_url( (string) $state['destination_home_url'], (string) ( $rewrite['destination_home_url'] ?? '' ) )
			|| ! $this->same_url( (string) $state['destination_site_url'], (string) ( $rewrite['destination_site_url'] ?? '' ) )
		) {
			return null;
		}

		return $state;
	}

	/**
	 * Return completed child rewrite state after local authority verification.
	 *
	 * @param string $job_id Parent local-clone job identifier.
	 * @return array<string,mixed>|null
	 */
	public function child_snapshot( string $job_id ): ?array {
		$state = $this->verified_snapshot( $job_id );
		if ( ! is_array( $state ) ) {
			return null;
		}

		return $this->rewrite_state->get( (string) $state['child_import_job_id'] );
	}

	/**
	 * Advance one bounded serialization-safe rewrite/verification batch.
	 *
	 * @param string $job_id     Parent local-clone job identifier.
	 * @param int    $batch_rows Maximum rows inspected this request.
	 * @return array<string,mixed>|null
	 */
	public function advance( string $job_id, int $batch_rows = ImportEnvironmentRewriter::DEFAULT_BATCH_ROWS ): ?array {
		$ready = $this->verified_snapshot( $job_id );
		if ( is_array( $ready ) ) {
			$this->refresh_parent_progress( $job_id, $ready );

			return $ready;
		}

		$existing  = $this->store->get( $job_id );
		$authority = $this->authority( $job_id, true );
		if ( null === $authority ) {
			$this->lock_child_from_parent_state( $existing, 'local-rewrite-parent-authority-unavailable' );

			return $this->block(
				$job_id,
				$existing ?? array(),
				'local-rewrite-parent-authority-unavailable'
			);
		}

		$child_id = (string) $authority['payload']['child_import_job_id'];
		$rewrite  = $this->rewriter->advance( $child_id, $batch_rows );
		if ( ! is_array( $rewrite ) ) {
			return $this->block(
				$job_id,
				$existing ?? array(),
				'local-rewrite-service-unavailable'
			);
		}

		if ( 'blocked' === ( $rewrite['status'] ?? null ) ) {
			$blockers = is_array( $rewrite['blockers'] ?? null ) ? $rewrite['blockers'] : array();
			$code     = is_string( $blockers[0] ?? null )
				? (string) $blockers[0]
				: 'local-rewrite-child-blocked';

			return $this->block( $job_id, $existing ?? array(), $code );
		}

		$fresh = $this->authority( $job_id, false );
		if ( null === $fresh ) {
			$this->lock_child( $child_id, 'local-rewrite-parent-authority-changed' );

			return $this->block(
				$job_id,
				$existing ?? array(),
				'local-rewrite-parent-authority-changed'
			);
		}

		if (
			true !== ( $rewrite['active_tables_untouched'] ?? false )
			|| true !== ( $rewrite['active_roots_untouched'] ?? false )
			|| ! hash_equals(
				(string) $fresh['database_plan']['database_manifest_sha256'],
				(string) ( $rewrite['database_manifest_sha256'] ?? '' )
			)
			|| ! hash_equals(
				(string) $fresh['files']['files_manifest_sha256'],
				(string) ( $rewrite['file_manifest_sha256'] ?? '' )
			)
			|| ! $this->same_url( (string) $fresh['import']['source_home_url'], (string) ( $rewrite['source_home_url'] ?? '' ) )
			|| ! $this->same_url( (string) $fresh['import']['source_site_url'], (string) ( $rewrite['source_site_url'] ?? '' ) )
			|| ! $this->same_url( (string) $fresh['import']['destination_home_url'], (string) ( $rewrite['destination_home_url'] ?? '' ) )
			|| ! $this->same_url( (string) $fresh['import']['destination_site_url'], (string) ( $rewrite['destination_site_url'] ?? '' ) )
		) {
			$this->lock_child( $child_id, 'local-rewrite-isolation-guard-failed' );

			return $this->block(
				$job_id,
				$existing ?? array(),
				'local-rewrite-isolation-guard-failed'
			);
		}

		$complete = 'complete' === ( $rewrite['status'] ?? null )
			&& 'complete' === ( $rewrite['stage'] ?? null )
			&& 0 === (int) ( $rewrite['verify_source_urls'] ?? -1 );

		if ( 'complete' === ( $rewrite['status'] ?? null ) && ! $complete ) {
			$this->lock_child( $child_id, 'local-rewrite-final-verification-failed' );

			return $this->block(
				$job_id,
				$existing ?? array(),
				'local-rewrite-final-verification-failed'
			);
		}

		$now   = gmdate( DATE_ATOM );
		$state = array(
			'schema_version'               => LocalCloneEnvironmentRewriteStateStore::SCHEMA_VERSION,
			'job_id'                       => $job_id,
			'status'                       => $complete ? 'ready' : 'running',
			'stage'                        => $complete ? 'complete' : (string) ( $rewrite['stage'] ?? 'rewrite' ),
			'child_import_job_id'          => $child_id,
			'destination_authority_sha256' => (string) $fresh['payload']['destination_authority_sha256'],
			'archive_sha256'               => (string) $fresh['handoff']['archive_sha256'],
			'package_manifest_sha256'      => (string) $fresh['handoff']['package_manifest_hash'],
			'package_checksum'             => (string) $fresh['handoff']['package_checksum'],
			'database_manifest_sha256'     => (string) $fresh['database_plan']['database_manifest_sha256'],
			'file_manifest_sha256'         => (string) $fresh['files']['files_manifest_sha256'],
			'source_home_url'              => (string) $fresh['import']['source_home_url'],
			'source_site_url'              => (string) $fresh['import']['source_site_url'],
			'destination_home_url'         => (string) $fresh['import']['destination_home_url'],
			'destination_site_url'         => (string) $fresh['import']['destination_site_url'],
			'rows_scanned'                 => (int) ( $rewrite['rows_scanned'] ?? 0 ),
			'rows_changed'                 => (int) ( $rewrite['rows_changed'] ?? 0 ),
			'values_changed'               => (int) ( $rewrite['values_changed'] ?? 0 ),
			'home_rewrites'                => (int) ( $rewrite['home_rewrites'] ?? 0 ),
			'siteurl_rewrites'             => (int) ( $rewrite['siteurl_rewrites'] ?? 0 ),
			'same_origin_rewrites'         => (int) ( $rewrite['same_origin_rewrites'] ?? 0 ),
			'upload_url_rewrites'          => (int) ( $rewrite['upload_url_rewrites'] ?? 0 ),
			'serialized_values'            => (int) ( $rewrite['serialized_values'] ?? 0 ),
			'json_values'                  => (int) ( $rewrite['json_values'] ?? 0 ),
			'credential_skips'             => (int) ( $rewrite['credential_skips'] ?? 0 ),
			'verify_rows_scanned'          => (int) ( $rewrite['verify_rows_scanned'] ?? 0 ),
			'verify_source_urls'           => (int) ( $rewrite['verify_source_urls'] ?? 0 ),
			'active_tables_untouched'      => true,
			'active_roots_untouched'       => true,
			'target_unactivated'           => true,
			'rewrite_next'                 => $complete ? 'finalization-plan' : 'environment-rewrite',
			'blockers'                     => array(),
			'advisories'                   => is_array( $rewrite['advisories'] ?? null ) ? $rewrite['advisories'] : array(),
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
	 * Resolve current handoff/payload/database/files/import authority.
	 *
	 * @param string $job_id          Parent local-clone job identifier.
	 * @param bool   $attempt_recover Whether to retry lower-level local authority.
	 * @return array<string,array<string,mixed>>|null
	 */
	private function authority( string $job_id, bool $attempt_recover ): ?array {
		$handoff  = $this->handoff->verified_snapshot( $job_id );
		$payload  = $this->payload->verified_snapshot( $job_id );
		$database = $this->database->verified_snapshot( $job_id );
		$files    = $this->files->verified_snapshot( $job_id );

		if ( ! is_array( $files ) && $attempt_recover ) {
			$this->files->advance( $job_id );
			$handoff  = $this->handoff->verified_snapshot( $job_id );
			$payload  = $this->payload->verified_snapshot( $job_id );
			$database = $this->database->verified_snapshot( $job_id );
			$files    = $this->files->verified_snapshot( $job_id );
		}

		if ( ! is_array( $handoff ) || ! is_array( $payload ) || ! is_array( $database ) || ! is_array( $files ) ) {
			return null;
		}

		$database_plan = $this->database->staging_plan( $job_id );
		if ( ! is_array( $database_plan ) ) {
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
			|| (string) ( $files['child_import_job_id'] ?? '' ) !== $child_id
			|| ! hash_equals( (string) $handoff['archive_sha256'], (string) ( $import['archive_sha256'] ?? '' ) )
			|| ! hash_equals( (string) $handoff['package_manifest_hash'], (string) ( $import['package_manifest_sha256'] ?? '' ) )
			|| ! hash_equals( (string) $handoff['package_checksum'], (string) ( $import['package_checksum'] ?? '' ) )
			|| ! $this->valid_http_url( $import['source_home_url'] ?? null )
			|| ! $this->valid_http_url( $import['source_site_url'] ?? null )
			|| ! $this->valid_http_url( $import['destination_home_url'] ?? null )
			|| ! $this->valid_http_url( $import['destination_site_url'] ?? null )
			|| $this->same_url( (string) $import['source_home_url'], (string) $import['destination_home_url'] )
			|| $this->same_url( (string) $import['source_site_url'], (string) $import['destination_site_url'] )
		) {
			return null;
		}

		return array(
			'handoff'  => $handoff,
			'payload'  => $payload,
			'database'      => $database,
			'database_plan' => $database_plan,
			'files'         => $files,
			'import'   => $import,
		);
	}

	/**
	 * Confirm one saved parent state remains bound to current authority.
	 *
	 * @param array<string,mixed>               $state     Parent rewrite state.
	 * @param array<string,array<string,mixed>> $authority Current authority.
	 */
	private function state_matches_authority( array $state, array $authority ): bool {
		return hash_equals( (string) $state['child_import_job_id'], (string) $authority['payload']['child_import_job_id'] )
			&& hash_equals( (string) $state['destination_authority_sha256'], (string) $authority['payload']['destination_authority_sha256'] )
			&& hash_equals( (string) $state['archive_sha256'], (string) $authority['handoff']['archive_sha256'] )
			&& hash_equals( (string) $state['package_manifest_sha256'], (string) $authority['handoff']['package_manifest_hash'] )
			&& hash_equals( (string) $state['package_checksum'], (string) $authority['handoff']['package_checksum'] )
			&& hash_equals( (string) $state['database_manifest_sha256'], (string) $authority['database_plan']['database_manifest_sha256'] )
			&& hash_equals( (string) $state['file_manifest_sha256'], (string) $authority['files']['files_manifest_sha256'] )
			&& $this->same_url( (string) $state['source_home_url'], (string) $authority['import']['source_home_url'] )
			&& $this->same_url( (string) $state['source_site_url'], (string) $authority['import']['source_site_url'] )
			&& $this->same_url( (string) $state['destination_home_url'], (string) $authority['import']['destination_home_url'] )
			&& $this->same_url( (string) $state['destination_site_url'], (string) $authority['import']['destination_site_url'] );
	}

	/**
	 * Refresh parent progress after running or recovered completion.
	 *
	 * @param string              $job_id Parent local-clone job identifier.
	 * @param array<string,mixed> $state  Parent rewrite state.
	 */
	private function refresh_parent_progress( string $job_id, array $state ): void {
		$complete = 'ready' === ( $state['status'] ?? null ) && 'complete' === ( $state['stage'] ?? null );

		$this->jobs->transition( $job_id, 'active', null );
		$this->jobs->update_progress(
			$job_id,
			'rewrite-environment',
			$complete ? 'local-environment-rewrite-complete' : 'local-environment-' . (string) ( $state['stage'] ?? 'rewrite' ),
			array(
				'completed' => 'verify' === ( $state['stage'] ?? null )
					? (int) ( $state['verify_rows_scanned'] ?? 0 )
					: (int) ( $state['rows_scanned'] ?? 0 ),
				'total'     => null,
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
	 * Revoke child eligibility from saved parent state.
	 *
	 * @param array<string,mixed>|null $state Saved parent rewrite state.
	 * @param string                   $code  Stable blocker.
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

		$state['schema_version']          = LocalCloneEnvironmentRewriteStateStore::SCHEMA_VERSION;
		$state['job_id']                  = $job_id;
		$state['status']                  = 'blocked';
		$state['stage']                   = is_string( $state['stage'] ?? null ) ? $state['stage'] : 'rewrite';
		$state['active_tables_untouched'] = true === ( $state['active_tables_untouched'] ?? false );
		$state['active_roots_untouched']  = true === ( $state['active_roots_untouched'] ?? false );
		$state['target_unactivated']      = true === ( $state['target_unactivated'] ?? false );
		$state['blockers']                = array_values( array_unique( array_filter( $blockers, 'is_string' ) ) );
		$state['started_at']              = (string) ( $state['started_at'] ?? $now );
		$state['updated_at']              = $now;

		if ( ! $this->store->save( $job_id, $state ) ) {
			return null;
		}

		$this->jobs->transition( $job_id, 'failed-retryable', $code );

		return $this->store->get( $job_id );
	}

	/**
	 * Compare HTTP(S) URLs after canonical trailing-slash normalization.
	 *
	 * @param string $left  First URL.
	 * @param string $right Second URL.
	 */
	private function same_url( string $left, string $right ): bool {
		return trailingslashit( untrailingslashit( $left ) ) === trailingslashit( untrailingslashit( $right ) );
	}

	/**
	 * Validate one HTTP(S) URL.
	 *
	 * @param mixed $url Raw URL.
	 */
	private function valid_http_url( mixed $url ): bool {
		return is_string( $url )
			&& '' !== $url
			&& in_array( wp_parse_url( $url, PHP_URL_SCHEME ), array( 'http', 'https' ), true );
	}
}
