<?php
/**
 * Portable Clone resumable export-state persistence.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge\Clone;

/**
 * Stores bounded export progress separately from private payload files.
 */
final class ExportStateStore {
	public const OPTION_NAME = 'seo_geo_migration_clone_export_state_v1';
	public const SCHEMA_VERSION = 1;
	private const MAX_STATES = 20;

	/**
	 * @param string $job_id Clone job identifier.
	 * @return array<string,mixed>|null
	 */
	public function get( string $job_id ): ?array {
		$states = $this->all();
		return $states[ $job_id ] ?? null;
	}

	/**
	 * @param string              $job_id Clone job identifier.
	 * @param array<string,mixed> $state  Export state.
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
	 * @param string              $job_id Clone job identifier.
	 * @param array<string,mixed> $state  Raw state.
	 * @return array<string,mixed>|null
	 */
	private function normalize_state( string $job_id, array $state ): ?array {
		if ( ! $this->valid_job_id( $job_id ) ) {
			return null;
		}

		$status = is_string( $state['status'] ?? null ) ? $state['status'] : 'running';
		if ( ! in_array( $status, array( 'running', 'complete', 'blocked' ), true ) ) {
			return null;
		}

		$strategy = is_string( $state['strategy'] ?? null ) ? $state['strategy'] : '';
		if ( ! in_array( $strategy, array( '', 'primary-key', 'offset-fallback' ), true ) ) {
			$strategy = '';
		}

		$cursor = is_string( $state['cursor_value_b64'] ?? null ) ? $state['cursor_value_b64'] : '';
		if ( 8192 < strlen( $cursor ) || ( '' !== $cursor && 1 !== preg_match( '/^[A-Za-z0-9_-]+$/', $cursor ) ) ) {
			$cursor = '';
		}

		$manifest_hash = is_string( $state['database_manifest_hash'] ?? null ) ? $state['database_manifest_hash'] : '';
		if ( '' !== $manifest_hash && 1 !== preg_match( '/^[a-f0-9]{64}$/', $manifest_hash ) ) {
			$manifest_hash = '';
		}

		return array(
			'schema_version'         => self::SCHEMA_VERSION,
			'job_id'                 => $job_id,
			'status'                 => $status,
			'table_index'            => max( 0, (int) ( $state['table_index'] ?? 0 ) ),
			'table_count'            => max( 0, (int) ( $state['table_count'] ?? 0 ) ),
			'current_table'          => is_string( $state['current_table'] ?? null ) ? substr( $state['current_table'], 0, 192 ) : '',
			'strategy'               => $strategy,
			'cursor_value_b64'       => $cursor,
			'offset'                 => max( 0, (int) ( $state['offset'] ?? 0 ) ),
			'chunk_index'            => max( 0, (int) ( $state['chunk_index'] ?? 0 ) ),
			'row_count'              => max( 0, (int) ( $state['row_count'] ?? 0 ) ),
			'byte_count'             => max( 0, (int) ( $state['byte_count'] ?? 0 ) ),
			'chunk_count'            => max( 0, (int) ( $state['chunk_count'] ?? 0 ) ),
			'tables_completed'       => max( 0, (int) ( $state['tables_completed'] ?? 0 ) ),
			'database_manifest_hash' => $manifest_hash,
			'blockers'               => $this->normalize_codes( $state['blockers'] ?? array() ),
			'started_at'             => is_string( $state['started_at'] ?? null ) ? substr( $state['started_at'], 0, 40 ) : '',
			'updated_at'             => is_string( $state['updated_at'] ?? null ) ? substr( $state['updated_at'], 0, 40 ) : '',
			'completed_at'           => is_string( $state['completed_at'] ?? null ) ? substr( $state['completed_at'], 0, 40 ) : '',
		);
	}

	/**
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
	 * @param array<string,array<string,mixed>> $states Export states.
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
	 * @param string $job_id Clone job identifier.
	 */
	private function valid_job_id( string $job_id ): bool {
		return 1 === preg_match( '/^[A-Za-z0-9][A-Za-z0-9._:-]{7,127}$/', $job_id );
	}
}
