<?php
/**
 * Portable Clone import finalization-preflight state persistence.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge\Clone;

/**
 * Stores bounded non-autoloaded finalization-preflight progress.
 */
final class ImportFinalizeStateStore {
	public const OPTION_NAME    = 'seo_geo_migration_clone_import_finalize_state_v1';
	public const SCHEMA_VERSION = 1;

	private const MAX_STATES = 20;

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
	 * Persist one bounded state.
	 *
	 * @param string              $job_id Clone job identifier.
	 * @param array<string,mixed> $state  Finalization state.
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
	 * Return normalized states.
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

		$status = is_string( $state['status'] ?? null ) ? $state['status'] : 'running';
		if ( ! in_array( $status, array( 'running', 'ready', 'blocked' ), true ) ) {
			return null;
		}

		$stage = is_string( $state['stage'] ?? null ) ? $state['stage'] : 'database-fingerprint';
		if ( ! in_array( $stage, array( 'database-fingerprint', 'file-fingerprint', 'ready' ), true ) ) {
			return null;
		}

		return array(
			'schema_version'             => self::SCHEMA_VERSION,
			'job_id'                     => $job_id,
			'status'                     => $status,
			'stage'                      => $stage,
			'database_table_index'       => max( 0, (int) ( $state['database_table_index'] ?? 0 ) ),
			'database_row_offset'        => max( 0, (int) ( $state['database_row_offset'] ?? 0 ) ),
			'database_rows_hashed'       => max( 0, (int) ( $state['database_rows_hashed'] ?? 0 ) ),
			'database_table_count'       => max( 0, (int) ( $state['database_table_count'] ?? 0 ) ),
			'database_fingerprint'       => $this->normalize_hash( $state['database_fingerprint'] ?? '' ),
			'file_root_index'            => max( 0, (int) ( $state['file_root_index'] ?? 0 ) ),
			'file_pending_dirs'          => $this->normalize_paths( $state['file_pending_dirs'] ?? array() ),
			'file_current_dir'           => is_string( $state['file_current_dir'] ?? null ) ? $this->bounded_path( $state['file_current_dir'] ) : '',
			'file_after_name'            => is_string( $state['file_after_name'] ?? null ) ? $this->bounded_name( $state['file_after_name'] ) : '',
			'files_hashed'               => max( 0, (int) ( $state['files_hashed'] ?? 0 ) ),
			'file_bytes_hashed'          => max( 0, (int) ( $state['file_bytes_hashed'] ?? 0 ) ),
			'expected_file_count'        => max( 0, (int) ( $state['expected_file_count'] ?? 0 ) ),
			'expected_file_bytes'        => max( 0, (int) ( $state['expected_file_bytes'] ?? 0 ) ),
			'file_fingerprint'           => $this->normalize_hash( $state['file_fingerprint'] ?? '' ),
			'activation_plan_hash'       => $this->normalize_hash( $state['activation_plan_hash'] ?? '' ),
			'database_manifest_sha256'   => $this->normalize_hash( $state['database_manifest_sha256'] ?? '' ),
			'files_manifest_sha256'      => $this->normalize_hash( $state['files_manifest_sha256'] ?? '' ),
			'payload_archive_sha256'     => $this->normalize_hash( $state['payload_archive_sha256'] ?? '' ),
			'sandbox_hardening_ready'    => true === ( $state['sandbox_hardening_ready'] ?? false ),
			'rollback_plan_ready'        => true === ( $state['rollback_plan_ready'] ?? false ),
			'activation_allowed'         => true === ( $state['activation_allowed'] ?? false ),
			'handoff_ready'              => true === ( $state['handoff_ready'] ?? false ),
			'active_tables_untouched'    => true === ( $state['active_tables_untouched'] ?? false ),
			'active_roots_untouched'     => true === ( $state['active_roots_untouched'] ?? false ),
			'blockers'                   => $this->normalize_codes( $state['blockers'] ?? array() ),
			'advisories'                 => $this->normalize_codes( $state['advisories'] ?? array() ),
			'started_at'                 => $this->bounded_timestamp( $state['started_at'] ?? '' ),
			'updated_at'                 => $this->bounded_timestamp( $state['updated_at'] ?? '' ),
			'completed_at'               => $this->bounded_timestamp( $state['completed_at'] ?? '' ),
		);
	}

	/**
	 * Normalize one hash.
	 *
	 * @param mixed $hash Raw hash.
	 */
	private function normalize_hash( mixed $hash ): string {
		return is_string( $hash ) && 1 === preg_match( '/^[a-f0-9]{64}$/', $hash ) ? $hash : '';
	}

	/**
	 * Normalize bounded traversal paths.
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
			if ( ! is_string( $path ) ) {
				continue;
			}
			$bounded = $this->bounded_path( $path );
			if ( '' === $bounded && '' !== $path ) {
				continue;
			}
			$normalized[] = $bounded;
		}

		return array_values( array_unique( $normalized ) );
	}

	/**
	 * Normalize one bounded path.
	 *
	 * @param string $path Relative path.
	 */
	private function bounded_path( string $path ): string {
		$path = ltrim( wp_normalize_path( $path ), '/' );
		if ( 2048 < strlen( $path ) || str_contains( $path, '../' ) ) {
			return '';
		}

		return $path;
	}

	/**
	 * Normalize one directory cursor.
	 *
	 * @param string $name Directory entry name.
	 */
	private function bounded_name( string $name ): string {
		if ( 255 < strlen( $name ) || str_contains( $name, '/' ) || str_contains( $name, '\\' ) ) {
			return '';
		}

		return $name;
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
	 * Normalize one timestamp.
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
