<?php
/**
 * Portable Clone local file-promotion state.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge\Clone;

/**
 * Stores bounded parent authority/evidence for reversible local file promotion.
 */
final class LocalCloneFilePromotionStateStore {
	public const OPTION_NAME    = 'seo_geo_migration_local_clone_file_promotion_v1';
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
	 * Persist one normalized state.
	 *
	 * @param string              $job_id Parent local-clone job identifier.
	 * @param array<string,mixed> $state  Raw state.
	 */
	public function save( string $job_id, array $state ): bool {
		$normalized = $this->normalize( $job_id, $state );
		if ( null === $normalized ) {
			return false;
		}

		$states            = $this->all();
		$states[ $job_id ] = $normalized;
		$states            = $this->bounded( $states );

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

		$out = array();
		foreach ( $value as $job_id => $state ) {
			if ( ! is_string( $job_id ) || ! is_array( $state ) ) {
				continue;
			}
			$normalized = $this->normalize( $job_id, $state );
			if ( null !== $normalized ) {
				$out[ $job_id ] = $normalized;
			}
		}

		return $out;
	}

	/**
	 * Normalize one parent promotion state.
	 *
	 * @param string              $job_id Parent job identifier.
	 * @param array<string,mixed> $state  Raw state.
	 * @return array<string,mixed>|null
	 */
	private function normalize( string $job_id, array $state ): ?array {
		if ( 1 !== preg_match( '/^[A-Za-z0-9][A-Za-z0-9._:-]{7,127}$/', $job_id ) ) {
			return null;
		}

		$status = is_string( $state['status'] ?? null ) ? $state['status'] : 'prepared';
		if ( ! in_array( $status, array( 'prepared', 'copying', 'candidate-ready', 'promoting', 'verifying', 'verified', 'rolled-back', 'blocked' ), true ) ) {
			return null;
		}

		return array(
			'schema_version'               => self::SCHEMA_VERSION,
			'job_id'                       => $job_id,
			'status'                       => $status,
			'child_import_job_id'          => $this->job_id( $state['child_import_job_id'] ?? '' ),
			'destination_authority_sha256' => $this->hash( $state['destination_authority_sha256'] ?? '' ),
			'activation_plan_hash'         => $this->hash( $state['activation_plan_hash'] ?? '' ),
			'file_fingerprint'             => $this->hash( $state['file_fingerprint'] ?? '' ),
			'copy_fingerprint'             => $this->hash( $state['copy_fingerprint'] ?? '' ),
			'active_fingerprint'           => $this->hash( $state['active_fingerprint'] ?? '' ),
			'target_root_sha256'           => $this->hash( $state['target_root_sha256'] ?? '' ),
			'file_count'                   => max( 0, (int) ( $state['file_count'] ?? 0 ) ),
			'byte_count'                   => max( 0, (int) ( $state['byte_count'] ?? 0 ) ),
			'verify_file_count'            => max( 0, (int) ( $state['verify_file_count'] ?? 0 ) ),
			'verify_byte_count'            => max( 0, (int) ( $state['verify_byte_count'] ?? 0 ) ),
			'database_activated'           => true === ( $state['database_activated'] ?? false ),
			'rollback_available'           => true === ( $state['rollback_available'] ?? false ),
			'handoff_ready'                => true === ( $state['handoff_ready'] ?? false ),
			'source_untouched'             => true === ( $state['source_untouched'] ?? false ),
			'promotion_next'               => $this->code( $state['promotion_next'] ?? '' ),
			'blockers'                     => $this->codes( $state['blockers'] ?? array() ),
			'prepared_at'                  => $this->timestamp( $state['prepared_at'] ?? '' ),
			'promoted_at'                  => $this->timestamp( $state['promoted_at'] ?? '' ),
			'verified_at'                  => $this->timestamp( $state['verified_at'] ?? '' ),
			'rolled_back_at'               => $this->timestamp( $state['rolled_back_at'] ?? '' ),
			'updated_at'                   => $this->timestamp( $state['updated_at'] ?? '' ),
		);
	}

	/**
	 * Normalize SHA-256.
	 *
	 * @param mixed $value Raw hash.
	 */
	private function hash( mixed $value ): string {
		return is_string( $value ) && 1 === preg_match( '/^[a-f0-9]{64}$/', $value ) ? $value : '';
	}

	/**
	 * Normalize job identifier.
	 *
	 * @param mixed $value Raw job identifier.
	 */
	private function job_id( mixed $value ): string {
		return is_string( $value ) && 1 === preg_match( '/^[A-Za-z0-9][A-Za-z0-9._:-]{7,127}$/', $value ) ? $value : '';
	}

	/**
	 * Normalize one machine code.
	 *
	 * @param mixed $value Raw code.
	 */
	private function code( mixed $value ): string {
		return is_string( $value ) && 1 === preg_match( '/^[a-z0-9][a-z0-9._-]{0,119}$/', $value ) ? $value : '';
	}

	/**
	 * Normalize bounded blocker codes.
	 *
	 * @param mixed $values Raw codes.
	 * @return list<string>
	 */
	private function codes( mixed $values ): array {
		if ( ! is_array( $values ) ) {
			return array();
		}

		$out = array();
		foreach ( array_slice( $values, 0, 40 ) as $value ) {
			$code = $this->code( $value );
			if ( '' !== $code ) {
				$out[] = $code;
			}
		}

		return array_values( array_unique( $out ) );
	}

	/**
	 * Normalize timestamp.
	 *
	 * @param mixed $value Raw timestamp.
	 */
	private function timestamp( mixed $value ): string {
		return is_string( $value ) ? substr( $value, 0, 40 ) : '';
	}

	/**
	 * Keep newest states.
	 *
	 * @param array<string,array<string,mixed>> $states States.
	 * @return array<string,array<string,mixed>>
	 */
	private function bounded( array $states ): array {
		if ( count( $states ) <= self::MAX_STATES ) {
			return $states;
		}

		uasort(
			$states,
			static fn( array $left, array $right ): int => strcmp(
				(string) ( $right['updated_at'] ?? '' ),
				(string) ( $left['updated_at'] ?? '' )
			)
		);

		return array_slice( $states, 0, self::MAX_STATES, true );
	}
}
