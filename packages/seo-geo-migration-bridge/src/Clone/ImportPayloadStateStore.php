<?php
/**
 * Portable Clone import payload extraction/verification state persistence.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge\Clone;

/**
 * Stores bounded non-autoloaded resumable payload extraction/verification state.
 */
final class ImportPayloadStateStore {
	public const OPTION_NAME    = 'seo_geo_migration_clone_import_payload_state_v1';
	public const SCHEMA_VERSION = 1;

	private const MAX_STATES = 20;

	/**
	 * Return all normalized states keyed by job ID.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public function all(): array {
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
	 * Return one normalized state.
	 *
	 * @param string $job_id Clone job identifier.
	 * @return array<string,mixed>|null
	 */
	public function get( string $job_id ): ?array {
		$states = $this->all();

		return $states[ $job_id ] ?? null;
	}

	/**
	 * Persist one bounded state.
	 *
	 * @param string              $job_id Clone job identifier.
	 * @param array<string,mixed> $state  Payload verification state.
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

		return update_option( self::OPTION_NAME, $states, false );
	}

	/**
	 * Normalize one persisted payload verification state.
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
		$stage  = is_string( $state['stage'] ?? null ) ? $state['stage'] : 'extract';
		if ( ! in_array( $status, array( 'pending', 'running', 'verified', 'blocked' ), true ) ) {
			return null;
		}
		if ( ! in_array( $stage, array( 'extract', 'verify', 'complete' ), true ) ) {
			return null;
		}

		return array(
			'schema_version'        => self::SCHEMA_VERSION,
			'job_id'                => $job_id,
			'status'                => $status,
			'stage'                 => $stage,
			'archive_sha256'        => $this->normalize_hash( $state['archive_sha256'] ?? '' ),
			'package_checksum'      => $this->normalize_hash( $state['package_checksum'] ?? '' ),
			'package_manifest_hash' => $this->normalize_hash( $state['package_manifest_hash'] ?? '' ),
			'expected_file_count'   => max( 0, (int) ( $state['expected_file_count'] ?? 0 ) ),
			'expected_bytes'        => max( 0, (int) ( $state['expected_bytes'] ?? 0 ) ),
			'archive_file_count'    => max( 0, (int) ( $state['archive_file_count'] ?? 0 ) ),
			'extract_after_name'    => $this->bounded_path( $state['extract_after_name'] ?? '' ),
			'extracted_file_count'  => max( 0, (int) ( $state['extracted_file_count'] ?? 0 ) ),
			'extracted_bytes'       => max( 0, (int) ( $state['extracted_bytes'] ?? 0 ) ),
			'directory_active'      => true === ( $state['directory_active'] ?? false ),
			'pending_dirs'          => $this->normalize_paths( $state['pending_dirs'] ?? array() ),
			'current_dir'           => $this->bounded_path( $state['current_dir'] ?? '' ),
			'after_name'            => $this->bounded_name( $state['after_name'] ?? '' ),
			'verified_file_count'   => max( 0, (int) ( $state['verified_file_count'] ?? 0 ) ),
			'verified_bytes'        => max( 0, (int) ( $state['verified_bytes'] ?? 0 ) ),
			'verification_checksum'=> $this->normalize_hash( $state['verification_checksum'] ?? '' ),
			'blockers'              => $this->normalize_codes( $state['blockers'] ?? array() ),
			'started_at'            => $this->bounded_timestamp( $state['started_at'] ?? '' ),
			'updated_at'            => $this->bounded_timestamp( $state['updated_at'] ?? '' ),
			'completed_at'          => $this->bounded_timestamp( $state['completed_at'] ?? '' ),
		);
	}

	/**
	 * Normalize one SHA-256 hash.
	 *
	 * @param mixed $hash Raw hash.
	 */
	private function normalize_hash( mixed $hash ): string {
		return is_string( $hash ) && 1 === preg_match( '/^[a-f0-9]{64}$/', $hash ) ? $hash : '';
	}

	/**
	 * Normalize bounded path list.
	 *
	 * @param mixed $paths Raw paths.
	 * @return list<string>
	 */
	private function normalize_paths( mixed $paths ): array {
		if ( ! is_array( $paths ) ) {
			return array();
		}

		$normalized = array();
		foreach ( array_slice( $paths, 0, 50000 ) as $path ) {
			$bounded = $this->bounded_path( $path );
			if ( '' === $bounded && '' !== $path ) {
				continue;
			}
			$normalized[] = $bounded;
		}

		return array_values( array_unique( $normalized ) );
	}

	/**
	 * Normalize one relative path.
	 *
	 * @param mixed $path Raw path.
	 */
	private function bounded_path( mixed $path ): string {
		if ( ! is_string( $path ) ) {
			return '';
		}

		$path = ltrim( wp_normalize_path( $path ), '/' );
		if ( 2048 < strlen( $path ) || str_contains( $path, '../' ) ) {
			return '';
		}

		return $path;
	}

	/**
	 * Normalize one directory entry name.
	 *
	 * @param mixed $name Raw name.
	 */
	private function bounded_name( mixed $name ): string {
		if ( ! is_string( $name ) || 255 < strlen( $name ) || str_contains( $name, '/' ) || str_contains( $name, '\\' ) ) {
			return '';
		}

		return $name;
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
	 * @param mixed $timestamp Raw timestamp.
	 */
	private function bounded_timestamp( mixed $timestamp ): string {
		return is_string( $timestamp ) ? substr( $timestamp, 0, 40 ) : '';
	}

	/**
	 * Keep only newest bounded states.
	 *
	 * @param array<string,array<string,mixed>> $states States keyed by ID.
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
