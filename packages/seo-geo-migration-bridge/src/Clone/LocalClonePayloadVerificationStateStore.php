<?php
/**
 * Portable Clone local payload-verification state.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge\Clone;

/**
 * Stores bounded parent state while the child Portable Import verifier extracts/checks payload.
 */
final class LocalClonePayloadVerificationStateStore {
	public const OPTION_NAME    = 'seo_geo_migration_local_clone_payload_verify_v1';
	public const SCHEMA_VERSION = 1;

	private const MAX_STATES = 20;

	/**
	 * Return one normalized state.
	 *
	 * @param string $job_id Parent local-clone job identifier.
	 * @return array<string,mixed>|null
	 */
	public function get( string $job_id ): ?array {
		$states = $this->all();

		return $states[ $job_id ] ?? null;
	}

	/**
	 * Persist one bounded state.
	 *
	 * @param string              $job_id Parent local-clone job identifier.
	 * @param array<string,mixed> $state  Raw state.
	 */
	public function save( string $job_id, array $state ): bool {
		if ( ! $this->valid_job_id( $job_id ) ) {
			return false;
		}

		$normalized = $this->normalize_state( $job_id, $state );
		if ( null === $normalized ) {
			return false;
		}

		$states            = $this->all();
		$states[ $job_id ] = $normalized;
		$states            = $this->bounded_states( $states );

		if ( false === get_option( self::OPTION_NAME, false ) ) {
			return add_option( self::OPTION_NAME, $states, '', false );
		}

		return update_option( self::OPTION_NAME, $states, false ) || $states === $this->all();
	}

	/**
	 * Return all normalized states.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	private function all(): array {
		$value = get_option( self::OPTION_NAME, array() );
		if ( ! is_array( $value ) ) {
			return array();
		}

		$states = array();
		foreach ( $value as $job_id => $state ) {
			if ( ! is_string( $job_id ) || ! is_array( $state ) ) {
				continue;
			}

			$normalized = $this->normalize_state( $job_id, $state );
			if ( null !== $normalized ) {
				$states[ $job_id ] = $normalized;
			}
		}

		return $states;
	}

	/**
	 * Normalize one parent payload-verification state.
	 *
	 * @param string              $job_id Parent local-clone job identifier.
	 * @param array<string,mixed> $state  Raw state.
	 * @return array<string,mixed>|null
	 */
	private function normalize_state( string $job_id, array $state ): ?array {
		if ( ! $this->valid_job_id( $job_id ) ) {
			return null;
		}

		$status = is_string( $state['status'] ?? null ) ? $state['status'] : 'running';
		if ( ! in_array( $status, array( 'running', 'ready', 'blocked' ), true ) ) {
			return null;
		}

		return array(
			'schema_version'               => self::SCHEMA_VERSION,
			'job_id'                       => $job_id,
			'status'                       => $status,
			'child_import_job_id'          => $this->bounded_job_id( $state['child_import_job_id'] ?? '' ),
			'destination_authority_sha256' => $this->normalize_hash( $state['destination_authority_sha256'] ?? '' ),
			'import_archive_sha256'        => $this->normalize_hash( $state['import_archive_sha256'] ?? '' ),
			'import_archive_bytes'         => max( 0, (int) ( $state['import_archive_bytes'] ?? 0 ) ),
			'package_manifest_sha256'      => $this->normalize_hash( $state['package_manifest_sha256'] ?? '' ),
			'package_checksum'             => $this->normalize_hash( $state['package_checksum'] ?? '' ),
			'payload_status'               => $this->bounded_code( $state['payload_status'] ?? '' ),
			'payload_stage'                => $this->bounded_code( $state['payload_stage'] ?? '' ),
			'expected_file_count'          => max( 0, (int) ( $state['expected_file_count'] ?? 0 ) ),
			'expected_byte_count'          => max( 0, (int) ( $state['expected_byte_count'] ?? 0 ) ),
			'extract_file_count'           => max( 0, (int) ( $state['extract_file_count'] ?? 0 ) ),
			'extract_byte_count'           => max( 0, (int) ( $state['extract_byte_count'] ?? 0 ) ),
			'verify_file_count'            => max( 0, (int) ( $state['verify_file_count'] ?? 0 ) ),
			'verify_byte_count'            => max( 0, (int) ( $state['verify_byte_count'] ?? 0 ) ),
			'verification_checksum'        => $this->normalize_hash( $state['verification_checksum'] ?? '' ),
			'payload_verified'             => true === ( $state['payload_verified'] ?? false ),
			'restore_eligible'             => true === ( $state['restore_eligible'] ?? false ),
			'database_untouched'           => true === ( $state['database_untouched'] ?? false ),
			'client_content_untouched'     => true === ( $state['client_content_untouched'] ?? false ),
			'payload_next'                 => $this->bounded_code( $state['payload_next'] ?? '' ),
			'blockers'                     => $this->normalize_codes( $state['blockers'] ?? array() ),
			'started_at'                   => $this->bounded_timestamp( $state['started_at'] ?? '' ),
			'updated_at'                   => $this->bounded_timestamp( $state['updated_at'] ?? '' ),
			'ready_at'                     => $this->bounded_timestamp( $state['ready_at'] ?? '' ),
		);
	}

	/**
	 * Normalize one SHA-256 value.
	 *
	 * @param mixed $hash Raw hash.
	 */
	private function normalize_hash( mixed $hash ): string {
		return is_string( $hash ) && 1 === preg_match( '/^[a-f0-9]{64}$/', $hash ) ? $hash : '';
	}

	/**
	 * Normalize one bounded machine code.
	 *
	 * @param mixed $code Raw code.
	 */
	private function bounded_code( mixed $code ): string {
		return is_string( $code ) && 1 === preg_match( '/^[a-z0-9][a-z0-9._:-]{0,159}$/', $code ) ? $code : '';
	}

	/**
	 * Normalize one child job identifier.
	 *
	 * @param mixed $job_id Raw identifier.
	 */
	private function bounded_job_id( mixed $job_id ): string {
		return is_string( $job_id ) && $this->valid_job_id( $job_id ) ? $job_id : '';
	}

	/**
	 * Normalize bounded blocker codes.
	 *
	 * @param mixed $codes Raw codes.
	 * @return list<string>
	 */
	private function normalize_codes( mixed $codes ): array {
		if ( ! is_array( $codes ) ) {
			return array();
		}

		$out = array();
		foreach ( array_slice( $codes, 0, 80 ) as $code ) {
			$bounded = $this->bounded_code( $code );
			if ( '' !== $bounded ) {
				$out[] = $bounded;
			}
		}

		return array_values( array_unique( $out ) );
	}

	/**
	 * Normalize one bounded timestamp.
	 *
	 * @param mixed $timestamp Raw timestamp.
	 */
	private function bounded_timestamp( mixed $timestamp ): string {
		return is_string( $timestamp ) ? substr( $timestamp, 0, 40 ) : '';
	}

	/**
	 * Keep only newest bounded states.
	 *
	 * @param array<string,array<string,mixed>> $states States.
	 * @return array<string,array<string,mixed>>
	 */
	private function bounded_states( array $states ): array {
		if ( count( $states ) <= self::MAX_STATES ) {
			return $states;
		}

		uasort(
			$states,
			static fn ( array $left, array $right ): int => strcmp(
				(string) ( $right['updated_at'] ?? '' ),
				(string) ( $left['updated_at'] ?? '' )
			)
		);

		return array_slice( $states, 0, self::MAX_STATES, true );
	}

	/**
	 * Validate one clone job identifier.
	 *
	 * @param string $job_id Job identifier.
	 */
	private function valid_job_id( string $job_id ): bool {
		return 1 === preg_match( '/^[A-Za-z0-9][A-Za-z0-9._:-]{7,127}$/', $job_id );
	}
}
