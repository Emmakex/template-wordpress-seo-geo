<?php
/**
 * Portable Clone local private payload extraction and checksum verification.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge\Clone;

/**
 * Reuses Portable Import payload verification for one verified local-clone child import.
 */
final class LocalClonePayloadVerification {
	/**
	 * Parent payload state.
	 *
	 * @var LocalClonePayloadVerificationStateStore
	 */
	private LocalClonePayloadVerificationStateStore $store;

	/**
	 * Verified target preflight authority.
	 *
	 * @var LocalCloneTargetPreflight
	 */
	private LocalCloneTargetPreflight $target_preflight;

	/**
	 * Existing Portable Import payload verifier.
	 *
	 * @var ImportPayloadVerifier
	 */
	private ImportPayloadVerifier $payload_verifier;

	/**
	 * Child payload state.
	 *
	 * @var ImportPayloadStateStore
	 */
	private ImportPayloadStateStore $payload_state;

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
	 * Private workspace.
	 *
	 * @var ExportWorkspace
	 */
	private ExportWorkspace $workspace;

	/**
	 * Construct local payload verification.
	 *
	 * @param LocalClonePayloadVerificationStateStore|null $store            Optional parent state store.
	 * @param LocalCloneTargetPreflight|null               $target_preflight Optional target-preflight authority.
	 * @param ImportPayloadVerifier|null                   $payload_verifier Optional existing Portable Import verifier.
	 * @param ImportPayloadStateStore|null                 $payload_state    Optional child payload-state store.
	 * @param ImportStateStore|null                        $import_state     Optional child import-state store.
	 * @param CloneJobStore|null                           $jobs             Optional clone-job store.
	 * @param ExportWorkspace|null                         $workspace        Optional private workspace.
	 */
	public function __construct(
		?LocalClonePayloadVerificationStateStore $store = null,
		?LocalCloneTargetPreflight $target_preflight = null,
		?ImportPayloadVerifier $payload_verifier = null,
		?ImportPayloadStateStore $payload_state = null,
		?ImportStateStore $import_state = null,
		?CloneJobStore $jobs = null,
		?ExportWorkspace $workspace = null
	) {
		$this->store            = $store ?? new LocalClonePayloadVerificationStateStore();
		$this->target_preflight = $target_preflight ?? new LocalCloneTargetPreflight();
		$this->payload_state    = $payload_state ?? new ImportPayloadStateStore();
		$this->import_state     = $import_state ?? new ImportStateStore();
		$this->jobs             = $jobs ?? new CloneJobStore();
		$this->workspace        = $workspace ?? new ExportWorkspace();
		$this->payload_verifier = $payload_verifier ?? new ImportPayloadVerifier(
			$this->payload_state,
			$this->import_state,
			$this->jobs,
			null,
			$this->workspace
		);
	}

	/**
	 * Return one parent payload-verification state.
	 *
	 * @param string $job_id Parent local-clone job identifier.
	 * @return array<string,mixed>|null
	 */
	public function snapshot( string $job_id ): ?array {
		return $this->store->get( $job_id );
	}

	/**
	 * Return ready state only while the target and child payload authority remain valid.
	 *
	 * @param string $job_id Parent local-clone job identifier.
	 * @return array<string,mixed>|null
	 */
	public function verified_snapshot( string $job_id ): ?array {
		$state     = $this->store->get( $job_id );
		$authority = $this->target_preflight->verified_authority_snapshot( $job_id );
		if (
			! is_array( $state )
			|| 'ready' !== ( $state['status'] ?? null )
			|| true !== ( $state['payload_verified'] ?? false )
			|| true !== ( $state['restore_eligible'] ?? false )
			|| ! is_array( $authority )
			|| ! $this->state_matches_authority( $state, $authority )
		) {
			return null;
		}

		$child_id = (string) $state['child_import_job_id'];
		$payload  = $this->payload_state->get( $child_id );
		$import   = $this->import_state->get( $child_id );
		$archive  = $this->workspace->import_archive_info( $child_id );
		if (
			! is_array( $payload )
			|| 'complete' !== ( $payload['status'] ?? null )
			|| 'complete' !== ( $payload['stage'] ?? null )
			|| array() !== ( $payload['blockers'] ?? array() )
			|| ! is_array( $import )
			|| 'payload-verified' !== ( $import['status'] ?? null )
			|| true !== ( $import['full_payload_verified'] ?? false )
			|| true !== ( $import['restore_allowed'] ?? false )
			|| array() !== ( $import['blockers'] ?? array() )
			|| ! is_array( $archive )
			|| ! $this->same_hash( $state['import_archive_sha256'] ?? '', $archive['sha256'] ?? '' )
			|| ! $this->same_hash( $state['verification_checksum'] ?? '', $import['package_checksum'] ?? '' )
		) {
			return null;
		}

		return $state;
	}

	/**
	 * Advance one bounded private extraction/checksum batch.
	 *
	 * This only mutates private import workspace state. Destination tables/uploads/themes stay absent.
	 *
	 * @param string $job_id      Parent local-clone job identifier.
	 * @param int    $batch_files Maximum files processed by the existing verifier.
	 * @param int    $batch_bytes Soft byte budget for one request.
	 * @return array<string,mixed>|null
	 */
	public function advance(
		string $job_id,
		int $batch_files = ImportPayloadVerifier::DEFAULT_BATCH_FILES,
		int $batch_bytes = ImportPayloadVerifier::DEFAULT_BATCH_BYTES
	): ?array {
		$verified = $this->verified_snapshot( $job_id );
		if ( is_array( $verified ) ) {
			return $verified;
		}

		$authority = $this->target_preflight->verified_authority_snapshot( $job_id );
		if ( ! is_array( $authority ) ) {
			return $this->block( $job_id, 'local-payload-target-authority-unavailable', true );
		}

		$child_id = (string) ( $authority['child_import_job_id'] ?? '' );
		$job      = $this->jobs->get( $child_id );
		$archive  = $this->workspace->import_archive_info( $child_id );
		if (
			! is_array( $job )
			|| 'import' !== ( $job['operation'] ?? null )
			|| ! is_array( $archive )
			|| ! $this->same_hash( $authority['import_archive_sha256'] ?? '', $archive['sha256'] ?? '' )
			|| (int) ( $authority['import_archive_bytes'] ?? -1 ) !== (int) ( $archive['bytes'] ?? -2 )
		) {
			return $this->block( $job_id, 'local-payload-child-authority-invalid', true );
		}

		$payload = $this->payload_state->get( $child_id );
		if ( ! is_array( $payload ) && ! is_array( $this->target_preflight->verified_snapshot( $job_id ) ) ) {
			return $this->block( $job_id, 'local-payload-preflight-not-ready', true );
		}

		$payload = $this->payload_verifier->advance( $child_id, $batch_files, $batch_bytes );
		if ( ! is_array( $payload ) ) {
			return $this->block( $job_id, 'local-payload-verifier-unavailable', true );
		}

		$fresh_authority = $this->target_preflight->verified_authority_snapshot( $job_id );
		if ( ! is_array( $fresh_authority ) || ! $this->authority_equal( $authority, $fresh_authority ) ) {
			return $this->block( $job_id, 'local-payload-target-authority-changed', true );
		}

		if ( 'blocked' === ( $payload['status'] ?? null ) ) {
			$blockers  = is_array( $payload['blockers'] ?? null ) ? $payload['blockers'] : array();
			$code      = is_string( $blockers[0] ?? null ) ? (string) $blockers[0] : 'local-payload-verification-blocked';
			$child     = $this->jobs->get( $child_id );
			$retryable = ! is_array( $child ) || 'failed-terminal' !== ( $child['status'] ?? null );

			return $this->block( $job_id, $code, $retryable );
		}

		$import = $this->import_state->get( $child_id );
		if ( ! is_array( $import ) ) {
			return $this->block( $job_id, 'local-payload-import-state-missing', true );
		}

		if ( 'complete' === ( $payload['status'] ?? null ) ) {
			if (
				'payload-verified' !== ( $import['status'] ?? null )
				|| true !== ( $import['full_payload_verified'] ?? false )
				|| true !== ( $import['restore_allowed'] ?? false )
				|| array() !== ( $import['blockers'] ?? array() )
				|| ! $this->same_hash( $payload['verification_checksum'] ?? '', $authority['package_checksum'] ?? '' )
				|| ! $this->same_hash( $import['package_checksum'] ?? '', $authority['package_checksum'] ?? '' )
			) {
				return $this->block( $job_id, 'local-payload-final-verification-invalid', false );
			}

			return $this->persist( $job_id, $authority, $payload, $import, true );
		}

		return $this->persist( $job_id, $authority, $payload, $import, false );
	}

	/**
	 * Persist synchronized parent progress.
	 *
	 * @param string              $job_id    Parent job identifier.
	 * @param array<string,mixed> $authority Verified target authority.
	 * @param array<string,mixed> $payload   Child payload state.
	 * @param array<string,mixed> $import    Child import state.
	 * @param bool                $ready     Whether checksum verification is complete.
	 * @return array<string,mixed>|null
	 */
	private function persist( string $job_id, array $authority, array $payload, array $import, bool $ready ): ?array {
		$existing = $this->store->get( $job_id );
		$now      = gmdate( DATE_ATOM );
		$state    = array(
			'schema_version'               => LocalClonePayloadVerificationStateStore::SCHEMA_VERSION,
			'job_id'                       => $job_id,
			'status'                       => $ready ? 'ready' : 'running',
			'child_import_job_id'          => (string) $authority['child_import_job_id'],
			'destination_authority_sha256' => (string) $authority['destination_authority_sha256'],
			'import_archive_sha256'        => (string) $authority['import_archive_sha256'],
			'import_archive_bytes'         => (int) $authority['import_archive_bytes'],
			'package_manifest_sha256'      => (string) $authority['package_manifest_sha256'],
			'package_checksum'             => (string) $authority['package_checksum'],
			'payload_status'               => (string) ( $payload['status'] ?? '' ),
			'payload_stage'                => (string) ( $payload['stage'] ?? '' ),
			'expected_file_count'          => (int) ( $payload['expected_file_count'] ?? 0 ),
			'expected_byte_count'          => (int) ( $payload['expected_byte_count'] ?? 0 ),
			'extract_file_count'           => (int) ( $payload['extract_file_count'] ?? 0 ),
			'extract_byte_count'           => (int) ( $payload['extract_byte_count'] ?? 0 ),
			'verify_file_count'            => (int) ( $payload['verify_file_count'] ?? 0 ),
			'verify_byte_count'            => (int) ( $payload['verify_byte_count'] ?? 0 ),
			'verification_checksum'        => (string) ( $payload['verification_checksum'] ?? '' ),
			'payload_verified'             => $ready && true === ( $import['full_payload_verified'] ?? false ),
			'restore_eligible'             => $ready && true === ( $import['restore_allowed'] ?? false ),
			'database_untouched'           => true,
			'client_content_untouched'     => true,
			'payload_next'                 => $ready ? 'staging-restore' : 'payload-verification',
			'blockers'                     => array(),
			'started_at'                   => is_array( $existing ) ? (string) ( $existing['started_at'] ?? $now ) : $now,
			'updated_at'                   => $now,
			'ready_at'                     => $ready ? $now : '',
		);

		if ( ! $this->store->save( $job_id, $state ) ) {
			return null;
		}

		$this->jobs->transition( $job_id, 'active', null );
		$stage     = (string) ( $payload['stage'] ?? 'extract' );
		$completed = 'verify' === $stage || $ready
			? (int) ( $payload['verify_file_count'] ?? 0 )
			: (int) ( $payload['archive_cursor'] ?? 0 );
		$total     = 'verify' === $stage || $ready
			? (int) ( $payload['expected_file_count'] ?? 0 )
			: (int) ( $payload['archive_entry_count'] ?? 0 );
		$this->jobs->update_progress(
			$job_id,
			$ready ? 'verify' : 'validate',
			$ready ? 'local-payload-verified' : 'local-payload-' . $stage,
			array(
				'completed' => $completed,
				'total'     => $total,
			)
		);

		return $this->store->get( $job_id );
	}

	/**
	 * Persist one parent blocker.
	 *
	 * @param string $job_id    Parent job identifier.
	 * @param string $code      Stable blocker.
	 * @param bool   $retryable Whether the current local-clone job may retry.
	 * @return array<string,mixed>|null
	 */
	private function block( string $job_id, string $code, bool $retryable ): ?array {
		$existing = $this->store->get( $job_id ) ?? array();
		$now      = gmdate( DATE_ATOM );
		$blockers = is_array( $existing['blockers'] ?? null ) ? $existing['blockers'] : array();
		$blockers[] = $code;

		$existing['schema_version']   = LocalClonePayloadVerificationStateStore::SCHEMA_VERSION;
		$existing['job_id']           = $job_id;
		$existing['status']           = 'blocked';
		$existing['payload_verified'] = false;
		$existing['restore_eligible'] = false;
		$existing['blockers']         = array_values( array_unique( array_filter( $blockers, 'is_string' ) ) );
		$existing['started_at']       = (string) ( $existing['started_at'] ?? $now );
		$existing['updated_at']       = $now;
		if ( ! $this->store->save( $job_id, $existing ) ) {
			return null;
		}

		$this->jobs->transition( $job_id, $retryable ? 'failed-retryable' : 'failed-terminal', $code );

		return $this->store->get( $job_id );
	}

	/**
	 * Confirm parent authority fields are unchanged across one child verifier batch.
	 *
	 * @param array<string,mixed> $left  Authority before the batch.
	 * @param array<string,mixed> $right Authority after the batch.
	 */
	private function authority_equal( array $left, array $right ): bool {
		foreach (
			array(
				'child_import_job_id',
				'destination_authority_sha256',
				'import_archive_sha256',
				'package_manifest_sha256',
				'package_checksum',
				'target_path',
				'target_url',
				'target_table_prefix',
			) as $key
		) {
			if ( ! hash_equals( (string) ( $left[ $key ] ?? '' ), (string) ( $right[ $key ] ?? '' ) ) ) {
				return false;
			}
		}

		return (int) ( $left['import_archive_bytes'] ?? -1 ) === (int) ( $right['import_archive_bytes'] ?? -2 );
	}

	/**
	 * Confirm parent state still matches the verified target-preflight authority.
	 *
	 * @param array<string,mixed> $state     Parent payload state.
	 * @param array<string,mixed> $authority Target-preflight authority.
	 */
	private function state_matches_authority( array $state, array $authority ): bool {
		return hash_equals( (string) ( $state['child_import_job_id'] ?? '' ), (string) ( $authority['child_import_job_id'] ?? '' ) )
			&& $this->same_hash( $state['destination_authority_sha256'] ?? '', $authority['destination_authority_sha256'] ?? '' )
			&& $this->same_hash( $state['import_archive_sha256'] ?? '', $authority['import_archive_sha256'] ?? '' )
			&& (int) ( $state['import_archive_bytes'] ?? -1 ) === (int) ( $authority['import_archive_bytes'] ?? -2 )
			&& $this->same_hash( $state['package_manifest_sha256'] ?? '', $authority['package_manifest_sha256'] ?? '' )
			&& $this->same_hash( $state['package_checksum'] ?? '', $authority['package_checksum'] ?? '' );
	}

	/**
	 * Constant-time compare validated SHA-256 strings.
	 *
	 * @param mixed $left  First value.
	 * @param mixed $right Second value.
	 */
	private function same_hash( mixed $left, mixed $right ): bool {
		return is_string( $left )
			&& is_string( $right )
			&& 1 === preg_match( '/^[a-f0-9]{64}$/', $left )
			&& 1 === preg_match( '/^[a-f0-9]{64}$/', $right )
			&& hash_equals( $left, $right );
	}
}
