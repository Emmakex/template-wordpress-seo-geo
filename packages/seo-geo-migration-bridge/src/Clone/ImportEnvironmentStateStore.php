<?php
/**
 * Portable Clone environment rewrite state persistence.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge\Clone;

/**
 * Stores bounded non-autoloaded serialization-safe staging rewrite progress.
 */
final class ImportEnvironmentStateStore {
	public const OPTION_NAME    = 'seo_geo_migration_clone_import_environment_state_v1';
	public const SCHEMA_VERSION = 1;

	private const MAX_STATES = 20;

	/**
	 * Return one normalized rewrite state.
	 *
	 * @param string $job_id Clone job identifier.
	 * @return array<string,mixed>|null
	 */
	public function get( string $job_id ): ?array {
		$states = $this->all();

		return $states[ $job_id ] ?? null;
	}

	/**
	 * Persist one bounded rewrite state.
	 *
	 * @param string              $job_id Clone job identifier.
	 * @param array<string,mixed> $state  Rewrite state.
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
	 * Delete one state.
	 *
	 * @param string $job_id Clone job identifier.
	 */
	public function delete( string $job_id ): bool {
		$states = $this->all();
		if ( ! isset( $states[ $job_id ] ) ) {
			return true;
		}

		unset( $states[ $job_id ] );

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
	 * Normalize one stored state.
	 *
	 * @param string              $job_id Clone job identifier.
	 * @param array<string,mixed> $state  Raw state.
	 * @return array<string,mixed>|null
	 */
	private function normalize_state( string $job_id, array $state ): ?array {
		if ( ! $this->valid_job_id( $job_id ) ) {
			return null;
		}

		$status = is_string( $state['status'] ?? null ) ? $state['status'] : 'running';
		$stage  = is_string( $state['stage'] ?? null ) ? $state['stage'] : 'rewrite';
		if (
			! in_array( $status, array( 'running', 'complete', 'blocked' ), true )
			|| ! in_array( $stage, array( 'rewrite', 'verify', 'complete' ), true )
		) {
			return null;
		}

		return array(
			'schema_version'            => self::SCHEMA_VERSION,
			'job_id'                    => $job_id,
			'status'                    => $status,
			'stage'                     => $stage,
			'source_home_url'           => $this->bounded_url( $state['source_home_url'] ?? '' ),
			'source_site_url'           => $this->bounded_url( $state['source_site_url'] ?? '' ),
			'destination_home_url'      => $this->bounded_url( $state['destination_home_url'] ?? '' ),
			'destination_site_url'      => $this->bounded_url( $state['destination_site_url'] ?? '' ),
			'source_prefix'             => $this->normalize_identifier( $state['source_prefix'] ?? '' ),
			'destination_prefix'        => $this->normalize_identifier( $state['destination_prefix'] ?? '' ),
			'staging_namespace'         => $this->normalize_identifier( $state['staging_namespace'] ?? '' ),
			'payload_archive_sha256'    => $this->normalize_hash( $state['payload_archive_sha256'] ?? '' ),
			'database_manifest_sha256'  => $this->normalize_hash( $state['database_manifest_sha256'] ?? '' ),
			'table_index'               => max( 0, (int) ( $state['table_index'] ?? 0 ) ),
			'table_count'               => max( 0, (int) ( $state['table_count'] ?? 0 ) ),
			'row_offset'                => max( 0, (int) ( $state['row_offset'] ?? 0 ) ),
			'rows_scanned'              => max( 0, (int) ( $state['rows_scanned'] ?? 0 ) ),
			'rows_changed'              => max( 0, (int) ( $state['rows_changed'] ?? 0 ) ),
			'values_changed'            => max( 0, (int) ( $state['values_changed'] ?? 0 ) ),
			'serialized_values_changed' => max( 0, (int) ( $state['serialized_values_changed'] ?? 0 ) ),
			'raw_values_changed'        => max( 0, (int) ( $state['raw_values_changed'] ?? 0 ) ),
			'prefix_keys_changed'       => max( 0, (int) ( $state['prefix_keys_changed'] ?? 0 ) ),
			'verify_rows_scanned'       => max( 0, (int) ( $state['verify_rows_scanned'] ?? 0 ) ),
			'remaining_source_values'   => max( 0, (int) ( $state['remaining_source_values'] ?? 0 ) ),
			'active_tables_untouched'   => true === ( $state['active_tables_untouched'] ?? false ),
			'blockers'                  => $this->normalize_codes( $state['blockers'] ?? array() ),
			'advisories'                => $this->normalize_codes( $state['advisories'] ?? array() ),
			'started_at'                => $this->bounded_timestamp( $state['started_at'] ?? '' ),
			'updated_at'                => $this->bounded_timestamp( $state['updated_at'] ?? '' ),
			'completed_at'              => $this->bounded_timestamp( $state['completed_at'] ?? '' ),
		);
	}

	/**
	 * Normalize a bounded HTTP(S) URL.
	 *
	 * @param mixed $value Raw URL.
	 */
	private function bounded_url( mixed $value ): string {
		if ( ! is_string( $value ) || 2048 < strlen( $value ) ) {
			return '';
		}

		$scheme = wp_parse_url( $value, PHP_URL_SCHEME );

		return in_array( $scheme, array( 'http', 'https' ), true ) ? $value : '';
	}

	/**
	 * Normalize a MySQL identifier/prefix.
	 *
	 * @param mixed $value Raw identifier.
	 */
	private function normalize_identifier( mixed $value ): string {
		if ( ! is_string( $value ) || 64 < strlen( $value ) ) {
			return '';
		}

		return 1 === preg_match( '/^[A-Za-z0-9_]*$/', $value ) ? $value : '';
	}

	/**
	 * Normalize SHA-256.
	 *
	 * @param mixed $hash Raw hash.
	 */
	private function normalize_hash( mixed $hash ): string {
		return is_string( $hash ) && 1 === preg_match( '/^[a-f0-9]{64}$/', $hash ) ? $hash : '';
	}

	/**
	 * Normalize blocker/advisory codes.
	 *
	 * @param mixed $codes Raw codes.
	 * @return list<string>
	 */
	private function normalize_codes( mixed $codes ): array {
		if ( ! is_array( $codes ) ) {
			return array();
		}

		$normalized = array();
		foreach ( array_slice( $codes, 0, 80 ) as $code ) {
			if ( is_string( $code ) && 1 === preg_match( '/^[a-z0-9][a-z0-9._-]{0,119}$/', $code ) ) {
				$normalized[] = $code;
			}
		}

		return array_values( array_unique( $normalized ) );
	}

	/**
	 * Normalize timestamp.
	 *
	 * @param mixed $value Raw timestamp.
	 */
	private function bounded_timestamp( mixed $value ): string {
		return is_string( $value ) ? substr( $value, 0, 40 ) : '';
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
			static fn( array $left, array $right ): int => strcmp(
				(string) ( $right['updated_at'] ?? '' ),
				(string) ( $left['updated_at'] ?? '' )
			)
		);

		return array_slice( $states, 0, self::MAX_STATES, true );
	}

	/**
	 * Validate one clone job identifier.
	 *
	 * @param string $job_id Clone job identifier.
	 */
	private function valid_job_id( string $job_id ): bool {
		return 1 === preg_match( '/^[A-Za-z0-9][A-Za-z0-9._:-]{7,127}$/', $job_id );
	}
}
