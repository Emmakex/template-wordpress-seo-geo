<?php
/**
 * Portable Clone Engine read-only inventory persistence.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge\Clone;

/**
 * Stores bounded resumable inventory state in one non-autoloaded option.
 */
final class CloneInventoryStore {
	/**
	 * Inventory storage option.
	 */
	public const OPTION_NAME = 'seo_geo_migration_clone_inventory_v1';

	/**
	 * Inventory schema version.
	 */
	public const SCHEMA_VERSION = 1;

	/**
	 * Maximum retained inventory states.
	 */
	private const MAX_STATES = 20;

	/**
	 * Return one normalized inventory state.
	 *
	 * @param string $job_id Clone job identifier.
	 * @return array<string,mixed>|null
	 */
	public function get( string $job_id ): ?array {
		$states = $this->all();

		return $states[ $job_id ] ?? null;
	}

	/**
	 * Persist one inventory state.
	 *
	 * @param string              $job_id Clone job identifier.
	 * @param array<string,mixed> $state  Inventory state.
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
	 * Delete one inventory state.
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
	 * Normalize one inventory state without accepting arbitrary private payload.
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

		$roots = is_array( $state['roots'] ?? null ) ? $state['roots'] : array();

		return array(
			'schema_version'   => self::SCHEMA_VERSION,
			'job_id'           => $job_id,
			'status'           => $status,
			'database'         => is_array( $state['database'] ?? null ) ? $state['database'] : array(),
			'roots'            => $roots,
			'root_index'       => max( 0, (int) ( $state['root_index'] ?? 0 ) ),
			'pending_dirs'     => $this->normalize_paths( $state['pending_dirs'] ?? array() ),
			'current_dir'      => is_string( $state['current_dir'] ?? null ) ? $this->bounded_path( $state['current_dir'] ) : '',
			'after_name'       => is_string( $state['after_name'] ?? null ) ? $this->bounded_name( $state['after_name'] ) : '',
			'file_count'       => max( 0, (int) ( $state['file_count'] ?? 0 ) ),
			'byte_count'       => max( 0, (int) ( $state['byte_count'] ?? 0 ) ),
			'excluded_count'   => max( 0, (int) ( $state['excluded_count'] ?? 0 ) ),
			'symlink_count'    => max( 0, (int) ( $state['symlink_count'] ?? 0 ) ),
			'unreadable_count' => max( 0, (int) ( $state['unreadable_count'] ?? 0 ) ),
			'fingerprint'      => is_string( $state['fingerprint'] ?? null )
				&& 1 === preg_match( '/^[a-f0-9]{64}$/', $state['fingerprint'] )
				? $state['fingerprint']
				: hash( 'sha256', 'seo-geo-portable-clone-inventory-v1' ),
			'fingerprint_scope' => 'database-structure-estimates+file-content',
			'blockers'          => $this->normalize_codes( $state['blockers'] ?? array() ),
			'started_at'        => is_string( $state['started_at'] ?? null ) ? substr( $state['started_at'], 0, 40 ) : '',
			'updated_at'        => is_string( $state['updated_at'] ?? null ) ? substr( $state['updated_at'], 0, 40 ) : '',
			'completed_at'      => is_string( $state['completed_at'] ?? null ) ? substr( $state['completed_at'], 0, 40 ) : '',
		);
	}

	/**
	 * Keep only bounded relative directory paths.
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
	 * Keep bounded machine-readable codes.
	 *
	 * @param mixed $codes Raw codes.
	 * @return list<string>
	 */
	private function normalize_codes( mixed $codes ): array {
		if ( ! is_array( $codes ) ) {
			return array();
		}

		$normalized = array();
		foreach ( array_slice( $codes, 0, 50 ) as $code ) {
			if ( ! is_string( $code ) || 1 !== preg_match( '/^[a-z0-9][a-z0-9._-]{0,119}$/', $code ) ) {
				continue;
			}

			$normalized[] = $code;
		}

		return array_values( array_unique( $normalized ) );
	}

	/**
	 * Bound one relative directory path.
	 *
	 * @param string $path Relative directory path.
	 */
	private function bounded_path( string $path ): string {
		$path = wp_normalize_path( $path );
		$path = ltrim( $path, '/' );
		if ( 1024 < strlen( $path ) || str_contains( $path, '../' ) ) {
			return '';
		}

		return $path;
	}

	/**
	 * Bound one directory entry name.
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
	 * Keep only the newest bounded set of states.
	 *
	 * @param array<string,array<string,mixed>> $states Inventory states.
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
