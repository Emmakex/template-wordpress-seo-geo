<?php
/**
 * Portable Clone local private payload extraction/checksum replay.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge\Clone;

/**
 * Reuses Portable Import payload verification under the verified local-clone parent authority.
 */
final class LocalClonePayloadVerifier {
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
	private ImportPayloadVerifier $verifier;

	/**
	 * Existing child payload state.
	 *
	 * @var ImportPayloadStateStore
	 */
	private ImportPayloadStateStore $payload_state;

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
	 * Construct local payload verifier.
	 *
	 * @param LocalClonePayloadVerificationStateStore|null $store           Optional parent state store.
	 * @param LocalCloneTargetPreflight|null               $target_preflight Optional verified target preflight.
	 * @param ImportPayloadVerifier|null                   $verifier        Optional existing payload verifier.
	 * @param ImportPayloadStateStore|null                 $payload_state   Optional child payload state.
	 * @param ImportStateStore|null                        $import_state    Optional child import state.
	 * @param CloneJobStore|null                           $jobs            Optional clone job store.
	 */
	public function __construct(
		?LocalClonePayloadVerificationStateStore $store = null,
		?LocalCloneTargetPreflight $target_preflight = null,
		?ImportPayloadVerifier $verifier = null,
		?ImportPayloadStateStore $payload_state = null,
		?ImportStateStore $import_state = null,
		?CloneJobStore $jobs = null
	) {
		$this->store            = $store ?? new LocalClonePayloadVerificationStateStore();
		$this->target_preflight = $target_preflight ?? new LocalCloneTargetPreflight();
		$this->payload_state    = $payload_state ?? new ImportPayloadStateStore();
		$this->import_state     = $import_state ?? new ImportStateStore();
		$this->jobs             = $jobs ?? new CloneJobStore();
		$this->verifier         = $verifier ?? new ImportPayloadVerifier(
			$this->payload_state,
			$this->import_state,
			$this->jobs
		);
	}

	/**
	 * Return one parent payload snapshot.
	 *
	 * @param string $job_id Parent local-clone job identifier.
	 * @return array<string,mixed>|null
	 */
	public function snapshot( string $job_id ): ?array {
		return $this->store->get( $job_id );
	}

	/**
	 * Return a ready payload state only while parent and child authority remains valid.
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
			|| true !== ( $state['payload_verified'] ?? false )
			|| true !== ( $state['child_restore_allowed'] ?? false )
			|| true !== ( $state['database_untouched'] ?? false )
			|| true !== ( $state['client_content_untouched'] ?? false )
			|| array() !== ( $state['blockers'] ?? array() )
		) {
			return null;
		}

		$target = $this->target_preflight->verified_snapshot( $job_id );
		if ( ! is_array( $target ) || ! $this->state_matches_target( $state, $target ) ) {
			return null;
		}

		$child_id = (string) $state['child_import_job_id'];
		$payload  = $this->payload_state->get( $child_id );
		$import   = $this->import_state->get( $child_id );
		if (
			! is_array( $payload )
			|| 'complete' !== ( $payload['status'] ?? null )
			|| 'complete' !== ( $payload['stage'] ?? null )
			|| ! is_array( $import )
			|| 'payload-verified' !== ( $import['status'] ?? null )
			|| true !== ( $import['full_payload_verified'] ?? false )
			|| true !== ( $import['restore_allowed'] ?? false )
			|| array() !== ( $import['blockers'] ?? array() )
			|| ! $this->payload_matches_parent( $payload, $state )
		) {
			return null;
		}

		return $state;
	}

	/**
	 * Advance one bounded private extraction/checksum batch through Portable Import.
	 *
	 * @param string $job_id      Parent local-clone job identifier.
	 * @param int    $batch_files Maximum files processed this request.
	 * @param int    $batch_bytes Soft maximum payload bytes processed this request.
	 * @return array<string,mixed>|null
	 */
	public function advance(
		string $job_id,
		int $batch_files = ImportPayloadVerifier::DEFAULT_BATCH_FILES,
		int $batch_bytes = ImportPayloadVerifier::DEFAULT_BATCH_BYTES
	): ?array {
		$ready = $this->verified_snapshot( $job_id );
		if ( is_array( $ready ) ) {
			return $ready;
		}

		$existing = $this->store->get( $job_id );
		$target   = $this->target_preflight->verified_snapshot( $job_id );
		if ( ! is_array( $target ) ) {
			$this->target_preflight->advance( $job_id );
			$target = $this->target_preflight->verified_snapshot( $job_id );
		}
		if ( ! is_array( $target ) ) {
			if ( is_array( $existing ) && is_string( $existing['child_import_job_id'] ?? null ) && '' !== $existing['child_import_job_id'] ) {
				$this->lock_child( (string) $existing['child_import_job_id'], 'local-payload-parent-authority-unavailable' );
			}

			return $this->block(
				$job_id,
				$existing ?? array(),
				'local-payload-parent-authority-unavailable'
			);
		}

		$child_id = (string) ( $target['child_import_job_id'] ?? '' );
		if ( '' === $child_id ) {
			return $this->block(
				$job_id,
				$this->store->get( $job_id ) ?? array(),
				'local-payload-child-job-missing'
			);
		}

		$payload = $this->verifier->advance( $child_id, $batch_files, $batch_bytes );
		if ( ! is_array( $payload ) ) {
			return $this->block(
				$job_id,
				$this->store->get( $job_id ) ?? array(),
				'local-payload-verifier-unavailable'
			);
		}

		$import = $this->import_state->get( $child_id );
		if ( 'blocked' === ( $payload['status'] ?? null ) ) {
			$blockers = is_array( $payload['blockers'] ?? null ) ? $payload['blockers'] : array();
			$code     = is_string( $blockers[0] ?? null )
				? (string) $blockers[0]
				: 'local-payload-verification-blocked';

			return $this->block( $job_id, $this->store->get( $job_id ) ?? array(), $code );
		}

		$fresh_target = $this->target_preflight->verified_snapshot( $job_id );
		if ( ! is_array( $fresh_target ) ) {
			$this->lock_child( $child_id, 'local-payload-parent-authority-changed' );

			return $this->block(
				$job_id,
				$this->store->get( $job_id ) ?? array(),
				'local-payload-parent-authority-changed'
			);
		}

		$now      = gmdate( DATE_ATOM );
		$complete = 'complete' === ( $payload['status'] ?? null );
		$valid    = $complete
			&& is_array( $import )
			&& 'payload-verified' === ( $import['status'] ?? null )
			&& true === ( $import['full_payload_verified'] ?? false )
			&& true === ( $import['restore_allowed'] ?? false )
			&& array() === ( $import['blockers'] ?? array() )
			&& $this->payload_matches_target( $payload, $fresh_target, $import );

		if ( $complete && ! $valid ) {
			$this->lock_child( $child_id, 'local-payload-final-authority-mismatch' );

			return $this->block(
				$job_id,
				$this->store->get( $job_id ) ?? array(),
				'local-payload-final-authority-mismatch'
			);
		}

		$state = array(
			'schema_version'               => LocalClonePayloadVerificationStateStore::SCHEMA_VERSION,
			'job_id'                       => $job_id,
			'status'                       => $valid ? 'ready' : 'running',
			'stage'                        => $valid ? 'complete' : (string) ( $payload['stage'] ?? 'extract' ),
			'child_import_job_id'          => $child_id,
			'destination_authority_sha256' => (string) $fresh_target['destination_authority_sha256'],
			'archive_sha256'               => (string) $fresh_target['import_archive_sha256'],
			'package_manifest_sha256'      => (string) $fresh_target['package_manifest_sha256'],
			'package_checksum'             => (string) $fresh_target['package_checksum'],
			'archive_cursor'               => (int) ( $payload['archive_cursor'] ?? 0 ),
			'archive_entry_count'          => (int) ( $payload['archive_entry_count'] ?? 0 ),
			'extract_file_count'           => (int) ( $payload['extract_file_count'] ?? 0 ),
			'extract_byte_count'           => (int) ( $payload['extract_byte_count'] ?? 0 ),
			'expected_file_count'          => (int) ( $payload['expected_file_count'] ?? 0 ),
			'expected_byte_count'          => (int) ( $payload['expected_byte_count'] ?? 0 ),
			'verify_file_count'            => (int) ( $payload['verify_file_count'] ?? 0 ),
			'verify_byte_count'            => (int) ( $payload['verify_byte_count'] ?? 0 ),
			'verification_checksum'        => (string) ( $payload['verification_checksum'] ?? '' ),
			'payload_verified'             => $valid,
			'child_restore_allowed'        => $valid,
			'database_untouched'           => true,
			'client_content_untouched'     => true,
			'payload_next'                 => $valid ? 'database-staging-restore' : 'payload-verification',
			'blockers'                     => array(),
			'started_at'                   => (string) ( $this->store->get( $job_id )['started_at'] ?? $now ),
			'updated_at'                   => $now,
			'ready_at'                     => $valid ? $now : '',
		);

		if ( ! $this->store->save( $job_id, $state ) ) {
			return null;
		}

		$this->jobs->transition( $job_id, 'active', null );
		$this->jobs->update_progress(
			$job_id,
			'verify',
			$valid ? 'local-payload-verified' : 'local-payload-' . (string) $state['stage'],
			array(
				'completed' => 'verify' === $state['stage']
					? (int) $state['verify_file_count']
					: (int) $state['archive_cursor'],
				'total'     => 'verify' === $state['stage']
					? (int) $state['expected_file_count']
					: (int) $state['archive_entry_count'],
			)
		);

		return $this->store->get( $job_id );
	}

	/**
	 * Confirm parent state remains bound to current target-preflight authority.
	 *
	 * @param array<string,mixed> $state  Parent payload state.
	 * @param array<string,mixed> $target Current target-preflight state.
	 */
	private function state_matches_target( array $state, array $target ): bool {
		return hash_equals( (string) $state['child_import_job_id'], (string) $target['child_import_job_id'] )
			&& hash_equals( (string) $state['destination_authority_sha256'], (string) $target['destination_authority_sha256'] )
			&& hash_equals( (string) $state['archive_sha256'], (string) $target['import_archive_sha256'] )
			&& hash_equals( (string) $state['package_manifest_sha256'], (string) $target['package_manifest_sha256'] )
			&& hash_equals( (string) $state['package_checksum'], (string) $target['package_checksum'] );
	}

	/**
	 * Confirm one payload state matches the frozen parent payload authority.
	 *
	 * @param array<string,mixed> $payload Child payload state.
	 * @param array<string,mixed> $state   Parent payload state.
	 */
	private function payload_matches_parent( array $payload, array $state ): bool {
		return hash_equals( (string) ( $payload['archive_sha256'] ?? '' ), (string) $state['archive_sha256'] )
			&& hash_equals( (string) ( $payload['package_manifest_sha256'] ?? '' ), (string) $state['package_manifest_sha256'] )
			&& hash_equals( (string) ( $payload['expected_checksum'] ?? '' ), (string) $state['package_checksum'] )
			&& hash_equals( (string) ( $payload['verification_checksum'] ?? '' ), (string) $state['package_checksum'] );
	}

	/**
	 * Confirm completed payload/import state matches current parent target authority.
	 *
	 * @param array<string,mixed> $payload Child payload state.
	 * @param array<string,mixed> $target  Current target-preflight state.
	 * @param array<string,mixed> $import  Current child import state.
	 */
	private function payload_matches_target( array $payload, array $target, array $import ): bool {
		return hash_equals( (string) ( $payload['archive_sha256'] ?? '' ), (string) $target['import_archive_sha256'] )
			&& hash_equals( (string) ( $payload['package_manifest_sha256'] ?? '' ), (string) $target['package_manifest_sha256'] )
			&& hash_equals( (string) ( $payload['expected_checksum'] ?? '' ), (string) $target['package_checksum'] )
			&& hash_equals( (string) ( $payload['verification_checksum'] ?? '' ), (string) $target['package_checksum'] )
			&& hash_equals( (string) ( $import['destination_authority_sha256'] ?? '' ), (string) $target['destination_authority_sha256'] )
			&& hash_equals( (string) ( $import['expected_package_manifest_sha256'] ?? '' ), (string) $target['package_manifest_sha256'] )
			&& hash_equals( (string) ( $import['expected_package_checksum'] ?? '' ), (string) $target['package_checksum'] );
	}

	/**
	 * Revoke one child restore gate after parent-authority drift.
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
	 * Persist one retryable parent blocker.
	 *
	 * @param string              $job_id Parent local-clone job identifier.
	 * @param array<string,mixed> $state  Current state.
	 * @param string              $code   Stable blocker.
	 * @return array<string,mixed>|null
	 */
	private function block( string $job_id, array $state, string $code ): ?array {
		$now        = gmdate( DATE_ATOM );
		$blockers   = is_array( $state['blockers'] ?? null ) ? $state['blockers'] : array();
		$blockers[] = $code;

		$state['schema_version']        = LocalClonePayloadVerificationStateStore::SCHEMA_VERSION;
		$state['job_id']                = $job_id;
		$state['status']                = 'blocked';
		$state['stage']                 = is_string( $state['stage'] ?? null ) ? $state['stage'] : 'extract';
		$state['payload_verified']      = false;
		$state['child_restore_allowed'] = false;
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
