<?php
/**
 * Portable Clone import intake/validation state persistence.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge\Clone;

/**
 * Stores bounded pre-restore import evidence in one non-autoloaded option.
 */
final class ImportStateStore {
	public const OPTION_NAME    = 'seo_geo_migration_clone_import_state_v1';
	public const SCHEMA_VERSION = 1;
	private const MAX_STATES    = 20;

	/**
	 * Return one normalized import state.
	 *
	 * @param string $job_id Clone job identifier.
	 * @return array<string,mixed>|null
	 */
	public function get( string $job_id ): ?array {
		$states = $this->all();

		return $states[ $job_id ] ?? null;
	}

	/**
	 * Persist one bounded import state.
	 *
	 * @param string              $job_id Clone job identifier.
	 * @param array<string,mixed> $state  Import state.
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
	 * Delete one import state.
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
	 * Normalize one import state.
	 *
	 * @param string              $job_id Clone job identifier.
	 * @param array<string,mixed> $state  Raw state.
	 * @return array<string,mixed>|null
	 */
	private function normalize_state( string $job_id, array $state ): ?array {
		if ( ! $this->valid_job_id( $job_id ) ) {
			return null;
		}

		$status = is_string( $state['status'] ?? null ) ? $state['status'] : 'pending';
		if ( ! in_array( $status, array( 'pending', 'ready', 'blocked' ), true ) ) {
			return null;
		}

		return array(
			'schema_version'         => self::SCHEMA_VERSION,
			'job_id'                 => $job_id,
			'status'                 => $status,
			'archive_bytes'          => max( 0, (int) ( $state['archive_bytes'] ?? 0 ) ),
			'archive_sha256'         => $this->normalize_hash( $state['archive_sha256'] ?? '' ),
			'entry_count'            => max( 0, (int) ( $state['entry_count'] ?? 0 ) ),
			'uncompressed_bytes'     => max( 0, (int) ( $state['uncompressed_bytes'] ?? 0 ) ),
			'compressed_bytes'       => max( 0, (int) ( $state['compressed_bytes'] ?? 0 ) ),
			'package_id'             => $this->bounded_identifier( $state['package_id'] ?? '' ),
			'package_schema_version' => max( 0, (int) ( $state['package_schema_version'] ?? 0 ) ),
			'package_manifest_hash'  => $this->normalize_hash( $state['package_manifest_hash'] ?? '' ),
			'source_home_url'        => $this->bounded_url( $state['source_home_url'] ?? '' ),
			'source_site_url'        => $this->bounded_url( $state['source_site_url'] ?? '' ),
			'source_fingerprint'     => $this->normalize_hash( $state['source_fingerprint'] ?? '' ),
			'package_checksum'       => $this->normalize_hash( $state['package_checksum'] ?? '' ),
			'database_manifest_hash' => $this->normalize_hash( $state['database_manifest_hash'] ?? '' ),
			'files_manifest_hash'    => $this->normalize_hash( $state['files_manifest_hash'] ?? '' ),
			'target_mode'            => $this->bounded_code( $state['target_mode'] ?? '' ),
			'target_path'            => $this->bounded_path( $state['target_path'] ?? '' ),
			'target_url'             => $this->bounded_url( $state['target_url'] ?? '' ),
			'target_table_prefix'    => $this->bounded_prefix( $state['target_table_prefix'] ?? '' ),
			'same_database'          => true === ( $state['same_database'] ?? false ),
			'target_ready'           => true === ( $state['target_ready'] ?? false ),
			'target_free_bytes'      => $this->nullable_nonnegative_int( $state['target_free_bytes'] ?? null ),
			'required_bytes'         => max( 0, (int) ( $state['required_bytes'] ?? 0 ) ),
			'blockers'               => $this->normalize_codes( $state['blockers'] ?? array() ),
			'advisories'             => $this->normalize_codes( $state['advisories'] ?? array() ),
			'mutations_performed'    => false,
			'started_at'             => $this->bounded_timestamp( $state['started_at'] ?? '' ),
			'updated_at'             => $this->bounded_timestamp( $state['updated_at'] ?? '' ),
			'validated_at'           => $this->bounded_timestamp( $state['validated_at'] ?? '' ),
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
	 * Normalize one bounded identifier.
	 *
	 * @param mixed $value Raw identifier.
	 */
	private function bounded_identifier( mixed $value ): string {
		return is_string( $value ) && 1 === preg_match( '/^[A-Za-z0-9][A-Za-z0-9._:-]{0,127}$/', $value ) ? $value : '';
	}

	/**
	 * Normalize one bounded machine code.
	 *
	 * @param mixed $value Raw code.
	 */
	private function bounded_code( mixed $value ): string {
		return is_string( $value ) && 1 === preg_match( '/^[a-z0-9][a-z0-9._-]{0,119}$/', $value ) ? $value : '';
	}

	/**
	 * Normalize one bounded target prefix.
	 *
	 * @param mixed $value Raw prefix.
	 */
	private function bounded_prefix( mixed $value ): string {
		return is_string( $value ) && 1 === preg_match( '/^[A-Za-z0-9_]{1,64}$/', $value ) ? $value : '';
	}

	/**
	 * Normalize one bounded server path.
	 *
	 * @param mixed $value Raw path.
	 */
	private function bounded_path( mixed $value ): string {
		if ( ! is_string( $value ) ) {
			return '';
		}

		$value = wp_normalize_path( $value );

		return 2048 >= strlen( $value ) ? $value : '';
	}

	/**
	 * Normalize one bounded URL.
	 *
	 * @param mixed $value Raw URL.
	 */
	private function bounded_url( mixed $value ): string {
		if ( ! is_string( $value ) || 2048 < strlen( $value ) ) {
			return '';
		}

		return esc_url_raw( $value );
	}

	/**
	 * Normalize one optional non-negative integer.
	 *
	 * @param mixed $value Raw value.
	 */
	private function nullable_nonnegative_int( mixed $value ): ?int {
		return null === $value ? null : max( 0, (int) $value );
	}

	/**
	 * Normalize bounded blocker/advisory codes.
	 *
	 * @param mixed $codes Raw codes.
	 * @return list<string>
	 */
	private function normalize_codes( mixed $codes ): array {
		if ( ! is_array( $codes ) ) {
			return array();
		}

		$normalized = array();
		foreach ( array_slice( $codes, 0, 100 ) as $code ) {
			$bounded = $this->bounded_code( $code );
			if ( '' !== $bounded ) {
				$normalized[] = $bounded;
			}
		}

		return array_values( array_unique( $normalized ) );
	}

	/**
	 * Bound one stored timestamp.
	 *
	 * @param mixed $value Raw timestamp.
	 */
	private function bounded_timestamp( mixed $value ): string {
		return is_string( $value ) ? substr( $value, 0, 40 ) : '';
	}

	/**
	 * Keep only the newest bounded state set.
	 *
	 * @param array<string,array<string,mixed>> $states Import states.
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
	 * Validate a clone job identifier.
	 *
	 * @param string $job_id Clone job identifier.
	 */
	private function valid_job_id( string $job_id ): bool {
		return 1 === preg_match( '/^[A-Za-z0-9][A-Za-z0-9._:-]{7,127}$/', $job_id );
	}
}
