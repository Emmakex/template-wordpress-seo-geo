<?php
/**
 * Portable Clone Engine resumable export state.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge\Clone;

/**
 * Stores bounded resumable export state in one non-autoloaded option.
 */
final class CloneExportStore {
	public const OPTION_NAME = 'seo_geo_migration_clone_export_v1';
	public const SCHEMA_VERSION = 1;
	private const MAX_STATES = 20;

	/**
	 * Return one normalized export state.
	 *
	 * @param string $job_id Clone job identifier.
	 * @return array<string,mixed>|null
	 */
	public function get( string $job_id ): ?array {
		$states = $this->all();
		return $states[ $job_id ] ?? null;
	}

	/**
	 * Persist one bounded export state.
	 *
	 * @param string              $job_id Clone job identifier.
	 * @param array<string,mixed> $state  Export state.
	 */
	public function save( string $job_id, array $state ): bool {
		$normalized = $this->normalize( $job_id, $state );
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
	 * Delete one export state without deleting its private payload.
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
	 * Return normalized export states.
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

			$normalized = $this->normalize( $job_id, $state );
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
	private function normalize( string $job_id, array $state ): ?array {
		if ( 1 !== preg_match( '/^[A-Za-z0-9][A-Za-z0-9._:-]{7,127}$/', $job_id ) ) {
			return null;
		}

		$phase = is_string( $state['phase'] ?? null ) ? $state['phase'] : 'database';
		if ( ! in_array( $phase, array( 'database', 'files', 'manifest', 'package', 'completed', 'blocked' ), true ) ) {
			return null;
		}

		$status = is_string( $state['status'] ?? null ) ? $state['status'] : 'running';
		if ( ! in_array( $status, array( 'running', 'complete', 'blocked' ), true ) ) {
			return null;
		}

		return array(
			'schema_version'        => self::SCHEMA_VERSION,
			'job_id'                => $job_id,
			'phase'                 => $phase,
			'status'                => $status,
			'db_table_index'        => max( 0, (int) ( $state['db_table_index'] ?? 0 ) ),
			'db_offset'             => max( 0, (int) ( $state['db_offset'] ?? 0 ) ),
			'db_chunk_index'        => max( 0, (int) ( $state['db_chunk_index'] ?? 0 ) ),
			'db_rows_exported'      => max( 0, (int) ( $state['db_rows_exported'] ?? 0 ) ),
			'db_chunks_exported'    => max( 0, (int) ( $state['db_chunks_exported'] ?? 0 ) ),
			'file_root_index'       => max( 0, (int) ( $state['file_root_index'] ?? 0 ) ),
			'file_pending_dirs'     => $this->paths( $state['file_pending_dirs'] ?? array() ),
			'file_current_dir'      => is_string( $state['file_current_dir'] ?? null ) ? $this->path( $state['file_current_dir'] ) : '',
			'file_after_name'       => is_string( $state['file_after_name'] ?? null ) ? $this->name( $state['file_after_name'] ) : '',
			'files_exported'        => max( 0, (int) ( $state['files_exported'] ?? 0 ) ),
			'file_bytes_exported'   => max( 0, (int) ( $state['file_bytes_exported'] ?? 0 ) ),
			'package_cursor'        => is_string( $state['package_cursor'] ?? null ) ? $this->path( $state['package_cursor'] ) : '',
			'package_entries'       => max( 0, (int) ( $state['package_entries'] ?? 0 ) ),
			'package_bytes'         => max( 0, (int) ( $state['package_bytes'] ?? 0 ) ),
			'package_sha256'        => $this->sha( $state['package_sha256'] ?? null ),
			'manifest_sha256'       => $this->sha( $state['manifest_sha256'] ?? null ),
			'blockers'              => $this->codes( $state['blockers'] ?? array() ),
			'advisories'            => $this->codes( $state['advisories'] ?? array() ),
			'created_at'            => is_string( $state['created_at'] ?? null ) ? substr( $state['created_at'], 0, 40 ) : '',
			'updated_at'            => is_string( $state['updated_at'] ?? null ) ? substr( $state['updated_at'], 0, 40 ) : '',
			'completed_at'          => is_string( $state['completed_at'] ?? null ) ? substr( $state['completed_at'], 0, 40 ) : '',
			'expires_at'            => is_string( $state['expires_at'] ?? null ) ? substr( $state['expires_at'], 0, 40 ) : '',
		);
	}

	/**
	 * Normalize relative paths.
	 *
	 * @param mixed $paths Raw paths.
	 * @return list<string>
	 */
	private function paths( mixed $paths ): array {
		if ( ! is_array( $paths ) ) {
			return array();
		}
		$out = array();
		foreach ( array_slice( $paths, 0, 50000 ) as $path ) {
			if ( is_string( $path ) ) {
				$normalized = $this->path( $path );
				if ( '' === $path || '' !== $normalized ) {
					$out[] = $normalized;
				}
			}
		}
		return array_values( array_unique( $out ) );
	}

	private function path( string $path ): string {
		$path = ltrim( wp_normalize_path( $path ), '/' );
		return 1024 >= strlen( $path ) && ! str_contains( $path, '../' ) ? $path : '';
	}

	private function name( string $name ): string {
		return 255 >= strlen( $name ) && ! str_contains( $name, '/' ) && ! str_contains( $name, '\\' ) ? $name : '';
	}

	private function sha( mixed $sha ): ?string {
		return is_string( $sha ) && 1 === preg_match( '/^[a-f0-9]{64}$/', $sha ) ? $sha : null;
	}

	/**
	 * Normalize bounded machine-readable codes.
	 *
	 * @param mixed $codes Raw codes.
	 * @return list<string>
	 */
	private function codes( mixed $codes ): array {
		if ( ! is_array( $codes ) ) {
			return array();
		}
		$out = array();
		foreach ( array_slice( $codes, 0, 100 ) as $code ) {
			if ( is_string( $code ) && 1 === preg_match( '/^[a-z0-9][a-z0-9._:-]{0,159}$/', $code ) ) {
				$out[] = $code;
			}
		}
		return array_values( array_unique( $out ) );
	}

	/**
	 * Keep a bounded number of states.
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
}
