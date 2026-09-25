<?php
/**
 * Portable Clone resumable database-restore state.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge\Clone;

/**
 * Stores bounded non-autoloaded database staging-restore state.
 */
final class ImportDatabaseStateStore {
	public const OPTION_NAME    = 'seo_geo_migration_clone_import_database_state_v1';
	public const SCHEMA_VERSION = 1;

	private const MAX_STATES = 20;

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
		$stage  = is_string( $state['stage'] ?? null ) ? $state['stage'] : 'prepare';
		if (
			! in_array( $status, array( 'pending', 'running', 'complete', 'blocked' ), true )
			|| ! in_array( $stage, array( 'prepare', 'schema', 'rows', 'table-verify', 'complete' ), true )
		) {
			return null;
		}

		return array(
			'schema_version'           => self::SCHEMA_VERSION,
			'job_id'                   => $job_id,
			'status'                   => $status,
			'stage'                    => $stage,
			'database_manifest_sha256' => $this->normalize_hash( $state['database_manifest_sha256'] ?? '' ),
			'payload_archive_sha256'   => $this->normalize_hash( $state['payload_archive_sha256'] ?? '' ),
			'source_prefix'            => $this->normalize_identifier( $state['source_prefix'] ?? '' ),
			'destination_prefix'       => $this->normalize_identifier( $state['destination_prefix'] ?? '' ),
			'staging_namespace'        => $this->normalize_identifier( $state['staging_namespace'] ?? '' ),
			'table_index'              => max( 0, (int) ( $state['table_index'] ?? 0 ) ),
			'table_count'              => max( 0, (int) ( $state['table_count'] ?? 0 ) ),
			'current_source_table'     => $this->normalize_identifier( $state['current_source_table'] ?? '' ),
			'current_target_table'     => $this->normalize_identifier( $state['current_target_table'] ?? '' ),
			'current_staging_table'    => $this->normalize_identifier( $state['current_staging_table'] ?? '' ),
			'chunk_index'              => max( 0, (int) ( $state['chunk_index'] ?? 0 ) ),
			'chunk_count'              => max( 0, (int) ( $state['chunk_count'] ?? 0 ) ),
			'chunks_completed'         => max( 0, (int) ( $state['chunks_completed'] ?? 0 ) ),
			'rows_restored'            => max( 0, (int) ( $state['rows_restored'] ?? 0 ) ),
			'table_rows_restored'      => max( 0, (int) ( $state['table_rows_restored'] ?? 0 ) ),
			'tables_completed'         => max( 0, (int) ( $state['tables_completed'] ?? 0 ) ),
			'active_tables_untouched'  => true === ( $state['active_tables_untouched'] ?? true ),
			'blockers'                 => $this->normalize_codes( $state['blockers'] ?? array() ),
			'started_at'               => $this->bounded_timestamp( $state['started_at'] ?? '' ),
			'updated_at'               => $this->bounded_timestamp( $state['updated_at'] ?? '' ),
			'completed_at'             => $this->bounded_timestamp( $state['completed_at'] ?? '' ),
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
	 * Normalize one MySQL identifier.
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
