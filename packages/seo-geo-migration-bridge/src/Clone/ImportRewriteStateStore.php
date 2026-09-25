<?php
/**
 * Portable Clone serialization-safe environment rewrite state.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge\Clone;

/**
 * Stores bounded non-autoloaded rewrite progress.
 */
final class ImportRewriteStateStore {
	public const OPTION_NAME    = 'seo_geo_migration_clone_import_rewrite_state_v1';
	public const SCHEMA_VERSION = 1;

	private const MAX_STATES = 20;

	/**
	 * Return one normalized rewrite state.
	 *
	 * @param string $job_id Clone job identifier.
	 * @return array<string,mixed>|null
	 */
	public function get( string $job_id ): ?array {
		$states = $this->all();

		return $states[ $job_id ] ?? null;
	}

	/**
	 * Persist one bounded rewrite state.
	 *
	 * @param string              $job_id Clone job identifier.
	 * @param array<string,mixed> $state  Rewrite state.
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
	 * Delete one rewrite state.
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
	 * Return all normalized rewrite states.
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
	 * Normalize one stored state.
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
		$stage  = is_string( $state['stage'] ?? null ) ? $state['stage'] : 'rewrite';
		if (
			! in_array( $status, array( 'running', 'complete', 'blocked' ), true )
			|| ! in_array( $stage, array( 'rewrite', 'verify', 'complete' ), true )
		) {
			return null;
		}

		return array(
			'schema_version'          => self::SCHEMA_VERSION,
			'job_id'                  => $job_id,
			'status'                  => $status,
			'stage'                   => $stage,
			'table_index'             => max( 0, (int) ( $state['table_index'] ?? 0 ) ),
			'table_count'             => max( 0, (int) ( $state['table_count'] ?? 0 ) ),
			'cursor_id'               => max( 0, (int) ( $state['cursor_id'] ?? 0 ) ),
			'rows_scanned'            => max( 0, (int) ( $state['rows_scanned'] ?? 0 ) ),
			'rows_changed'            => max( 0, (int) ( $state['rows_changed'] ?? 0 ) ),
			'values_changed'          => max( 0, (int) ( $state['values_changed'] ?? 0 ) ),
			'home_rewrites'           => max( 0, (int) ( $state['home_rewrites'] ?? 0 ) ),
			'siteurl_rewrites'        => max( 0, (int) ( $state['siteurl_rewrites'] ?? 0 ) ),
			'same_origin_rewrites'    => max( 0, (int) ( $state['same_origin_rewrites'] ?? 0 ) ),
			'upload_url_rewrites'     => max( 0, (int) ( $state['upload_url_rewrites'] ?? 0 ) ),
			'serialized_values'       => max( 0, (int) ( $state['serialized_values'] ?? 0 ) ),
			'json_values'             => max( 0, (int) ( $state['json_values'] ?? 0 ) ),
			'credential_skips'        => max( 0, (int) ( $state['credential_skips'] ?? 0 ) ),
			'opaque_serialized_skips' => max( 0, (int) ( $state['opaque_serialized_skips'] ?? 0 ) ),
			'verify_rows_scanned'     => max( 0, (int) ( $state['verify_rows_scanned'] ?? 0 ) ),
			'verify_source_urls'      => max( 0, (int) ( $state['verify_source_urls'] ?? 0 ) ),
			'source_home_url'         => $this->bounded_url( $state['source_home_url'] ?? '' ),
			'source_site_url'         => $this->bounded_url( $state['source_site_url'] ?? '' ),
			'destination_home_url'    => $this->bounded_url( $state['destination_home_url'] ?? '' ),
			'destination_site_url'    => $this->bounded_url( $state['destination_site_url'] ?? '' ),
			'database_manifest_sha256'=> $this->normalize_hash( $state['database_manifest_sha256'] ?? '' ),
			'file_manifest_sha256'    => $this->normalize_hash( $state['file_manifest_sha256'] ?? '' ),
			'active_tables_untouched' => true === ( $state['active_tables_untouched'] ?? false ),
			'active_roots_untouched'  => true === ( $state['active_roots_untouched'] ?? false ),
			'blockers'                => $this->normalize_codes( $state['blockers'] ?? array() ),
			'advisories'              => $this->normalize_codes( $state['advisories'] ?? array() ),
			'started_at'              => $this->bounded_timestamp( $state['started_at'] ?? '' ),
			'updated_at'              => $this->bounded_timestamp( $state['updated_at'] ?? '' ),
			'completed_at'            => $this->bounded_timestamp( $state['completed_at'] ?? '' ),
		);
	}

	/**
	 * Normalize one HTTP(S) URL.
	 *
	 * @param mixed $url Raw URL.
	 */
	private function bounded_url( mixed $url ): string {
		if ( ! is_string( $url ) ) {
			return '';
		}

		$url = substr( $url, 0, 2048 );

		return in_array( wp_parse_url( $url, PHP_URL_SCHEME ), array( 'http', 'https' ), true ) ? $url : '';
	}

	/**
	 * Normalize one SHA-256.
	 *
	 * @param mixed $hash Raw hash.
	 */
	private function normalize_hash( mixed $hash ): string {
		return is_string( $hash ) && 1 === preg_match( '/^[a-f0-9]{64}$/', $hash ) ? $hash : '';
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
