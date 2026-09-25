<?php
/**
 * Portable Clone resumable file-restore state.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge\Clone;

/**
 * Stores bounded non-autoloaded destination file-staging state.
 */
final class ImportFileStateStore {
	public const OPTION_NAME    = 'seo_geo_migration_clone_import_file_state_v1';
	public const SCHEMA_VERSION = 1;

	private const MAX_STATES = 20;
	private const MAX_PENDING_DIRECTORIES = 50000;

	/**
	 * Return all normalized states.
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
	 * Return one state.
	 *
	 * @param string $job_id Clone job identifier.
	 * @return array<string,mixed>|null
	 */
	public function get( string $job_id ): ?array {
		$states = $this->all();

		return $states[ $job_id ] ?? null;
	}

	/**
	 * Persist one state.
	 *
	 * @param string              $job_id Clone job identifier.
	 * @param array<string,mixed> $state  Restore state.
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
	 * Delete one restore state.
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
	 * Normalize one state.
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
		if ( ! in_array( $status, array( 'pending', 'running', 'complete', 'blocked' ), true ) ) {
			return null;
		}

		return array(
			'schema_version'         => self::SCHEMA_VERSION,
			'job_id'                 => $job_id,
			'status'                 => $status,
			'files_manifest_sha256'  => $this->normalize_hash( $state['files_manifest_sha256'] ?? '' ),
			'payload_archive_sha256' => $this->normalize_hash( $state['payload_archive_sha256'] ?? '' ),
			'staging_root_key'       => $this->normalize_key( $state['staging_root_key'] ?? '' ),
			'root_index'             => max( 0, (int) ( $state['root_index'] ?? 0 ) ),
			'root_count'             => max( 0, (int) ( $state['root_count'] ?? 0 ) ),
			'pending_dirs'           => $this->normalize_paths( $state['pending_dirs'] ?? array() ),
			'current_dir'            => $this->normalize_path( $state['current_dir'] ?? '' ),
			'after_name'             => $this->normalize_name( $state['after_name'] ?? '' ),
			'file_count'             => max( 0, (int) ( $state['file_count'] ?? 0 ) ),
			'byte_count'             => max( 0, (int) ( $state['byte_count'] ?? 0 ) ),
			'expected_file_count'    => max( 0, (int) ( $state['expected_file_count'] ?? 0 ) ),
			'expected_byte_count'    => max( 0, (int) ( $state['expected_byte_count'] ?? 0 ) ),
			'roots_completed'        => max( 0, (int) ( $state['roots_completed'] ?? 0 ) ),
			'active_files_untouched' => true === ( $state['active_files_untouched'] ?? true ),
			'blockers'               => $this->normalize_codes( $state['blockers'] ?? array() ),
			'started_at'             => $this->bounded_timestamp( $state['started_at'] ?? '' ),
			'updated_at'             => $this->bounded_timestamp( $state['updated_at'] ?? '' ),
			'completed_at'           => $this->bounded_timestamp( $state['completed_at'] ?? '' ),
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
	 * Normalize one bounded filesystem key.
	 *
	 * @param mixed $value Raw key.
	 */
	private function normalize_key( mixed $value ): string {
		return is_string( $value ) && 1 === preg_match( '/^[a-f0-9]{16,64}$/', $value ) ? $value : '';
	}

	/**
	 * Normalize one bounded relative path.
	 *
	 * @param mixed $value Raw path.
	 */
	private function normalize_path( mixed $value ): string {
		if ( ! is_string( $value ) || 2048 < strlen( $value ) ) {
			return '';
		}

		$value = ltrim( wp_normalize_path( $value ), '/' );
		if ( str_contains( $value, '../' ) || str_contains( $value, '/..' ) || '..' === $value ) {
			return '';
		}

		return $value;
	}

	/**
	 * Normalize bounded pending directories.
	 *
	 * @param mixed $paths Raw paths.
	 * @return list<string>
	 */
	private function normalize_paths( mixed $paths ): array {
		if ( ! is_array( $paths ) ) {
			return array();
		}

		$normalized = array();
		foreach ( array_slice( $paths, 0, self::MAX_PENDING_DIRECTORIES ) as $path ) {
			$value = $this->normalize_path( $path );
			if ( is_string( $path ) && ( '' === $path || '' !== $value ) ) {
				$normalized[] = $value;
			}
		}

		return $normalized;
	}

	/**
	 * Normalize one bounded directory entry name.
	 *
	 * @param mixed $value Raw name.
	 */
	private function normalize_name( mixed $value ): string {
		if ( ! is_string( $value ) || 255 < strlen( $value ) || str_contains( $value, '/' ) || str_contains( $value, '\\' ) ) {
			return '';
		}

		return $value;
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

		$normalized = array();
		foreach ( array_slice( $codes, 0, 80 ) as $code ) {
			if ( is_string( $code ) && 1 === preg_match( '/^[a-z0-9][a-z0-9._-]{0,119}$/', $code ) ) {
				$normalized[] = $code;
			}
		}

		return array_values( array_unique( $normalized ) );
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
	 * Keep only the newest bounded states.
	 *
	 * @param array<string,array<string,mixed>> $states Restore states.
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
