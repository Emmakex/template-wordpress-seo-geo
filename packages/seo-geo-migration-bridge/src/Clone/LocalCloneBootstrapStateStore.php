<?php
/**
 * Portable Clone local target bootstrap ownership state.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge\Clone;

/**
 * Persists the bounded ownership/recovery journal for a local-clone target.
 */
final class LocalCloneBootstrapStateStore {
	public const OPTION_NAME    = 'seo_geo_migration_local_clone_bootstrap_state_v1';
	public const SCHEMA_VERSION = 1;

	private const MAX_STATES = 20;

	/**
	 * Return one normalized bootstrap state.
	 *
	 * @param string $job_id Clone job identifier.
	 * @return array<string,mixed>|null
	 */
	public function get( string $job_id ): ?array {
		$states = $this->all();

		return $states[ $job_id ] ?? null;
	}

	/**
	 * Persist one bounded bootstrap state.
	 *
	 * @param string              $job_id Clone job identifier.
	 * @param array<string,mixed> $state  Bootstrap state.
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
	 * Delete one bootstrap state.
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
		if ( ! in_array( $status, array( 'claiming', 'claimed', 'blocked', 'released' ), true ) ) {
			return null;
		}

		return array(
			'schema_version'           => self::SCHEMA_VERSION,
			'job_id'                   => $job_id,
			'status'                   => $status,
			'stage'                    => 'target-ownership',
			'plan_hash'                => $this->normalize_hash( $state['plan_hash'] ?? '' ),
			'target_path'              => $this->bounded_path( $state['target_path'] ?? '' ),
			'target_url'               => $this->bounded_url( $state['target_url'] ?? '' ),
			'target_table_prefix'      => $this->bounded_prefix( $state['target_table_prefix'] ?? '' ),
			'package_checksum'         => $this->normalize_hash( $state['package_checksum'] ?? '' ),
			'package_manifest_hash'    => $this->normalize_hash( $state['package_manifest_hash'] ?? '' ),
			'source_fingerprint'       => $this->normalize_hash( $state['source_fingerprint'] ?? '' ),
			'target_created'           => true === ( $state['target_created'] ?? false ),
			'target_owned'             => true === ( $state['target_owned'] ?? false ),
			'marker_relative_path'     => $this->bounded_relative( $state['marker_relative_path'] ?? '' ),
			'marker_sha256'            => $this->normalize_hash( $state['marker_sha256'] ?? '' ),
			'production_untouched'     => true === ( $state['production_untouched'] ?? false ),
			'database_untouched'       => true === ( $state['database_untouched'] ?? false ),
			'bootstrap_next'           => $this->bounded_code( $state['bootstrap_next'] ?? '' ),
			'blockers'                 => $this->normalize_codes( $state['blockers'] ?? array() ),
			'claimed_at'               => $this->bounded_timestamp( $state['claimed_at'] ?? '' ),
			'released_at'              => $this->bounded_timestamp( $state['released_at'] ?? '' ),
			'updated_at'               => $this->bounded_timestamp( $state['updated_at'] ?? '' ),
		);
	}

	/**
	 * Normalize an absolute filesystem path.
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
	 * Normalize one bounded relative path.
	 *
	 * @param mixed $path Raw relative path.
	 */
	private function bounded_relative( mixed $path ): string {
		if ( ! is_string( $path ) || 255 < strlen( $path ) ) {
			return '';
		}

		$path = ltrim( wp_normalize_path( $path ), '/' );
		if ( '' === $path || str_contains( $path, '..' ) || str_contains( $path, ':' ) ) {
			return '';
		}

		return $path;
	}

	/**
	 * Normalize one bounded machine code.
	 *
	 * @param mixed $code Raw code.
	 */
	private function bounded_code( mixed $code ): string {
		return is_string( $code ) && 1 === preg_match( '/^[a-z0-9][a-z0-9._-]{0,119}$/', $code ) ? $code : '';
	}

	/**
	 * Normalize blocker codes.
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
			$normalized = $this->bounded_code( $code );
			if ( '' !== $normalized ) {
				$out[] = $normalized;
			}
		}

		return array_values( array_unique( $out ) );
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
	 * Keep only the newest bounded records.
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
