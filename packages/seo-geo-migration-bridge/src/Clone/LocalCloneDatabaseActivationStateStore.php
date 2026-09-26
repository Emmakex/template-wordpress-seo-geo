<?php
/**
 * Portable Clone local database activation state.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge\Clone;

/**
 * Stores bounded parent authority/evidence for isolated local DB activation.
 */
final class LocalCloneDatabaseActivationStateStore {
	public const OPTION_NAME    = 'seo_geo_migration_local_clone_database_activation_v1';
	public const SCHEMA_VERSION = 1;

	private const MAX_STATES = 20;

	/**
	 * Return one state.
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
	 * Return normalized states.
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
	 * Normalize one parent activation state.
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
		if ( ! in_array( $status, array( 'prepared', 'activated', 'rolled-back', 'blocked' ), true ) ) {
			return null;
		}

		return array(
			'schema_version'               => self::SCHEMA_VERSION,
			'job_id'                       => $job_id,
			'status'                       => $status,
			'child_import_job_id'          => $this->job_id( $state['child_import_job_id'] ?? '' ),
			'destination_authority_sha256' => $this->hash( $state['destination_authority_sha256'] ?? '' ),
			'activation_plan_hash'         => $this->hash( $state['activation_plan_hash'] ?? '' ),
			'database_fingerprint'         => $this->hash( $state['database_fingerprint'] ?? '' ),
			'file_fingerprint'             => $this->hash( $state['file_fingerprint'] ?? '' ),
			'target_table_prefix'          => $this->table_prefix( $state['target_table_prefix'] ?? '' ),
			'options_target'               => $this->table_name( $state['options_target'] ?? '' ),
			'table_count'                  => max( 0, (int) ( $state['table_count'] ?? 0 ) ),
			'row_count'                    => max( 0, (int) ( $state['row_count'] ?? 0 ) ),
			'database_swapped'             => true === ( $state['database_swapped'] ?? false ),
			'rollback_available'           => true === ( $state['rollback_available'] ?? false ),
			'active_files_untouched'       => true === ( $state['active_files_untouched'] ?? false ),
			'target_database_active'       => true === ( $state['target_database_active'] ?? false ),
			'activation_next'              => $this->code( $state['activation_next'] ?? '' ),
			'blockers'                     => $this->codes( $state['blockers'] ?? array() ),
			'prepared_at'                  => $this->timestamp( $state['prepared_at'] ?? '' ),
			'activated_at'                 => $this->timestamp( $state['activated_at'] ?? '' ),
			'rolled_back_at'               => $this->timestamp( $state['rolled_back_at'] ?? '' ),
			'updated_at'                   => $this->timestamp( $state['updated_at'] ?? '' ),
		);
	}

	/**
	 * Normalize SHA-256.
	 *
	 * @param mixed $value Raw value.
	 */
	private function hash( mixed $value ): string {
		return is_string( $value ) && 1 === preg_match( '/^[a-f0-9]{64}$/', $value ) ? $value : '';
	}

	/**
	 * Normalize job id.
	 *
	 * @param mixed $value Raw value.
	 */
	private function job_id( mixed $value ): string {
		return is_string( $value ) && 1 === preg_match( '/^[A-Za-z0-9][A-Za-z0-9._:-]{7,127}$/', $value ) ? $value : '';
	}

	/**
	 * Normalize table prefix.
	 *
	 * @param mixed $value Raw value.
	 */
	private function table_prefix( mixed $value ): string {
		return is_string( $value ) && 1 === preg_match( '/^[A-Za-z0-9_]+$/', $value ) ? $value : '';
	}

	/**
	 * Normalize table name.
	 *
	 * @param mixed $value Raw value.
	 */
	private function table_name( mixed $value ): string {
		return is_string( $value )
			&& 64 >= strlen( $value )
			&& 1 === preg_match( '/^[A-Za-z0-9_$-]+$/', $value )
			? $value
			: '';
	}

	/**
	 * Normalize code.
	 *
	 * @param mixed $value Raw value.
	 */
	private function code( mixed $value ): string {
		return is_string( $value ) && 1 === preg_match( '/^[a-z0-9][a-z0-9._-]{0,119}$/', $value ) ? $value : '';
	}

	/**
	 * Normalize codes.
	 *
	 * @param mixed $values Raw values.
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
