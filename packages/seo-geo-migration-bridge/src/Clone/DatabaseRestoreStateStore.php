<?php
/**
 * Portable Clone resumable database-restore state persistence.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge\Clone;

/**
 * Stores bounded non-autoloaded destination database-restore progress.
 */
final class DatabaseRestoreStateStore {
	public const OPTION_NAME    = 'seo_geo_migration_clone_database_restore_state_v1';
	public const SCHEMA_VERSION = 1;

	private const MAX_STATES = 20;

	/**
	 * Return all normalized database-restore states.
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
	 * Return one normalized database-restore state.
	 *
	 * @param string $job_id Clone job identifier.
	 * @return array<string,mixed>|null
	 */
	public function get( string $job_id ): ?array {
		$states = $this->all();

		return $states[ $job_id ] ?? null;
	}

	/**
	 * Persist one bounded database-restore state.
	 *
	 * @param string              $job_id Clone job identifier.
	 * @param array<string,mixed> $state  Database restore state.
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
	 * Delete one database-restore state.
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
	 * Normalize one persisted database-restore state.
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
		if ( ! in_array( $status, array( 'running', 'complete', 'blocked' ), true ) ) {
			return null;
		}

		$stage = is_string( $state['stage'] ?? null ) ? $state['stage'] : 'prepare';
		if ( ! in_array( $stage, array( 'prepare', 'schema', 'rows', 'complete' ), true ) ) {
			return null;
		}

		return array(
			'schema_version'           => self::SCHEMA_VERSION,
			'job_id'                   => $job_id,
			'status'                   => $status,
			'stage'                    => $stage,
			'restore_prefix'           => $this->normalize_prefix( $state['restore_prefix'] ?? '' ),
			'journal_table'            => $this->normalize_identifier( $state['journal_table'] ?? '' ),
			'source_table_prefix'      => $this->normalize_prefix( $state['source_table_prefix'] ?? '' ),
			'database_manifest_sha256' => $this->normalize_hash( $state['database_manifest_sha256'] ?? '' ),
			'table_index'              => max( 0, (int) ( $state['table_index'] ?? 0 ) ),
			'table_count'              => max( 0, (int) ( $state['table_count'] ?? 0 ) ),
			'current_source_table'     => $this->normalize_identifier( $state['current_source_table'] ?? '' ),
			'current_target_table'     => $this->normalize_identifier( $state['current_target_table'] ?? '' ),
			'chunk_index'              => max( 0, (int) ( $state['chunk_index'] ?? 0 ) ),
			'rows_restored'            => max( 0, (int) ( $state['rows_restored'] ?? 0 ) ),
			'chunks_restored'          => max( 0, (int) ( $state['chunks_restored'] ?? 0 ) ),
			'tables_completed'         => max( 0, (int) ( $state['tables_completed'] ?? 0 ) ),
			'blockers'                 => $this->normalize_codes( $state['blockers'] ?? array() ),
			'started_at'               => $this->bounded_timestamp( $state['started_at'] ?? '' ),
			'updated_at'               => $this->bounded_timestamp( $state['updated_at'] ?? '' ),
			'completed_at'             => $this->bounded_timestamp( $state['completed_at'] ?? '' ),
		);
	}

	/**
	 * Normalize a SQL identifier used only for already-validated WordPress clone tables.
	 *
	 * @param mixed $identifier Raw identifier.
	 */
	private function normalize_identifier( mixed $identifier ): string {
		if ( ! is_string( $identifier ) || 64 < strlen( $identifier ) ) {
			return '';
		}

		return 1 === preg_match( '/^[A-Za-z0-9_]+$/', $identifier ) ? $identifier : '';
	}

	/**
	 * Normalize one bounded WordPress table prefix.
	 *
	 * @param mixed $prefix Raw prefix.
	 */
	private function normalize_prefix( mixed $prefix ): string {
		if ( ! is_string( $prefix ) || 32 < strlen( $prefix ) ) {
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
		foreach ( array_slice( $codes, 0, 60 ) as $code ) {
			if ( is_string( $code ) && 1 === preg_match( '/^[a-z0-9][a-z0-9._-]{0,119}$/', $code ) ) {
				$normalized[] = $code;
			}
		}

		return array_values( array_unique( $normalized ) );
	}

	/**
	 * Normalize one bounded timestamp.
	 *
	 * @param mixed $timestamp Raw timestamp.
	 */
	private function bounded_timestamp( mixed $timestamp ): string {
		return is_string( $timestamp ) ? substr( $timestamp, 0, 40 ) : '';
	}

	/**
	 * Keep only the newest bounded restore states.
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
