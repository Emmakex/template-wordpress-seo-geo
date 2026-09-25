<?php
/**
 * Portable Clone local-clone orchestration state persistence.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge\Clone;

/**
 * Stores the bounded destination/ownership contract for local clone jobs.
 */
final class LocalCloneStateStore {
	public const OPTION_NAME    = 'seo_geo_migration_local_clone_state_v1';
	public const SCHEMA_VERSION = 1;

	private const MAX_STATES = 20;

	/**
	 * Return one normalized local-clone state.
	 *
	 * @param string $job_id Clone job identifier.
	 * @return array<string,mixed>|null
	 */
	public function get( string $job_id ): ?array {
		$states = $this->all();

		return $states[ $job_id ] ?? null;
	}

	/**
	 * Persist one bounded local-clone state.
	 *
	 * @param string              $job_id Clone job identifier.
	 * @param array<string,mixed> $state  Local-clone state.
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
	 * Delete one local-clone state.
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
	 * Return every valid stored state.
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
	 * Normalize one persisted state.
	 *
	 * @param string              $job_id Clone job identifier.
	 * @param array<string,mixed> $state  Raw state.
	 * @return array<string,mixed>|null
	 */
	private function normalize_state( string $job_id, array $state ): ?array {
		if ( ! $this->valid_job_id( $job_id ) ) {
			return null;
		}

		$status = is_string( $state['status'] ?? null ) ? $state['status'] : 'blocked';
		if ( ! in_array( $status, array( 'ready', 'blocked' ), true ) ) {
			return null;
		}

		return array(
			'schema_version'               => self::SCHEMA_VERSION,
			'job_id'                       => $job_id,
			'status'                       => $status,
			'stage'                        => 'destination-plan',
			'source_path'                  => $this->bounded_path( $state['source_path'] ?? '' ),
			'target_path'                  => $this->bounded_path( $state['target_path'] ?? '' ),
			'source_url'                   => $this->bounded_url( $state['source_url'] ?? '' ),
			'target_url'                   => $this->bounded_url( $state['target_url'] ?? '' ),
			'same_origin'                  => true === ( $state['same_origin'] ?? false ),
			'nested_under_wordpress_root'  => true === ( $state['nested_under_wordpress_root'] ?? false ),
			'payload_roots_exclude_target' => true === ( $state['payload_roots_exclude_target'] ?? false ),
			'same_database'                => true === ( $state['same_database'] ?? false ),
			'source_table_prefix'          => $this->bounded_prefix( $state['source_table_prefix'] ?? '' ),
			'target_table_prefix'          => $this->bounded_prefix( $state['target_table_prefix'] ?? '' ),
			'target_exists'                => true === ( $state['target_exists'] ?? false ),
			'target_empty'                 => is_bool( $state['target_empty'] ?? null ) ? $state['target_empty'] : null,
			'free_bytes'                   => $this->nullable_nonnegative_int( $state['free_bytes'] ?? null ),
			'required_bytes'               => $this->nullable_nonnegative_int( $state['required_bytes'] ?? null ),
			'package_checksum'             => $this->normalize_hash( $state['package_checksum'] ?? '' ),
			'package_manifest_hash'        => $this->normalize_hash( $state['package_manifest_hash'] ?? '' ),
			'source_fingerprint'           => $this->normalize_hash( $state['source_fingerprint'] ?? '' ),
			'plan_hash'                    => $this->normalize_hash( $state['plan_hash'] ?? '' ),
			'package_verified'             => true === ( $state['package_verified'] ?? false ),
			'production_source_read_only'  => true === ( $state['production_source_read_only'] ?? false ),
			'target_owned'                 => true === ( $state['target_owned'] ?? false ),
			'bootstrap_allowed'            => true === ( $state['bootstrap_allowed'] ?? false ),
			'mutations_performed'          => true === ( $state['mutations_performed'] ?? false ),
			'blockers'                     => $this->normalize_codes( $state['blockers'] ?? array() ),
			'advisories'                   => $this->normalize_codes( $state['advisories'] ?? array() ),
			'planned_at'                   => $this->bounded_timestamp( $state['planned_at'] ?? '' ),
			'updated_at'                   => $this->bounded_timestamp( $state['updated_at'] ?? '' ),
		);
	}

	/**
	 * Normalize one absolute filesystem path.
	 *
	 * @param mixed $path Raw path.
	 */
	private function bounded_path( mixed $path ): string {
		if ( ! is_string( $path ) ) {
			return '';
		}

		$path = wp_normalize_path( trim( $path ) );

		return 4096 >= strlen( $path ) ? $path : '';
	}

	/**
	 * Normalize one HTTP(S) URL.
	 *
	 * @param mixed $url Raw URL.
	 */
	private function bounded_url( mixed $url ): string {
		if ( ! is_string( $url ) || 2048 < strlen( $url ) ) {
			return '';
		}

		return esc_url_raw( $url );
	}

	/**
	 * Normalize one WordPress table prefix.
	 *
	 * @param mixed $prefix Raw prefix.
	 */
	private function bounded_prefix( mixed $prefix ): string {
		if ( ! is_string( $prefix ) || 64 < strlen( $prefix ) ) {
			return '';
		}

		return 1 === preg_match( '/^[A-Za-z0-9_]+$/', $prefix ) ? $prefix : '';
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
	 * Normalize blocker/advisory codes.
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
			if ( is_string( $code ) && 1 === preg_match( '/^[a-z0-9][a-z0-9._-]{0,119}$/', $code ) ) {
				$out[] = $code;
			}
		}

		return array_values( array_unique( $out ) );
	}

	/**
	 * Normalize a nullable non-negative integer.
	 *
	 * @param mixed $value Raw value.
	 */
	private function nullable_nonnegative_int( mixed $value ): ?int {
		return null === $value ? null : max( 0, (int) $value );
	}

	/**
	 * Normalize one timestamp.
	 *
	 * @param mixed $timestamp Raw timestamp.
	 */
	private function bounded_timestamp( mixed $timestamp ): string {
		return is_string( $timestamp ) ? substr( $timestamp, 0, 40 ) : '';
	}

	/**
	 * Keep only the newest bounded state records.
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
	 * @param string $job_id Clone job identifier.
	 */
	private function valid_job_id( string $job_id ): bool {
		return 1 === preg_match( '/^[A-Za-z0-9][A-Za-z0-9._:-]{7,127}$/', $job_id );
	}
}
