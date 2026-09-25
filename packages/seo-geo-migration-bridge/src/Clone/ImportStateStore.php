<?php
/**
 * Portable Clone import-preflight state persistence.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge\Clone;

/**
 * Stores bounded non-autoloaded import intake/preflight state.
 */
final class ImportStateStore {
	public const OPTION_NAME    = 'seo_geo_migration_clone_import_state_v1';
	public const SCHEMA_VERSION = 1;

	private const MAX_STATES = 20;

	/**
	 * Return all normalized import states keyed by job ID.
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
	 * Return one import state.
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

		return update_option( self::OPTION_NAME, $states, false );
	}

	/**
	 * Normalize one persisted import state.
	 *
	 * @param string              $job_id Clone job identifier.
	 * @param array<string,mixed> $state  Raw import state.
	 * @return array<string,mixed>|null
	 */
	private function normalize_state( string $job_id, array $state ): ?array {
		if ( ! $this->valid_job_id( $job_id ) ) {
			return null;
		}

		$status = is_string( $state['status'] ?? null ) ? $state['status'] : 'pending';
		if ( ! in_array( $status, array( 'pending', 'staged', 'preflight-ready', 'blocked' ), true ) ) {
			return null;
		}

		return array(
			'schema_version'               => self::SCHEMA_VERSION,
			'job_id'                       => $job_id,
			'status'                       => $status,
			'archive_sha256'               => $this->normalize_hash( $state['archive_sha256'] ?? '' ),
			'archive_bytes'                => max( 0, (int) ( $state['archive_bytes'] ?? 0 ) ),
			'package_id'                   => $this->bounded_text( $state['package_id'] ?? '', 160 ),
			'package_manifest_sha256'      => $this->normalize_hash( $state['package_manifest_sha256'] ?? '' ),
			'package_checksum'             => $this->normalize_hash( $state['package_checksum'] ?? '' ),
			'payload_file_count'           => max( 0, (int) ( $state['payload_file_count'] ?? 0 ) ),
			'payload_bytes'                => max( 0, (int) ( $state['payload_bytes'] ?? 0 ) ),
			'archive_entry_count'          => max( 0, (int) ( $state['archive_entry_count'] ?? 0 ) ),
			'archive_uncompressed_bytes'   => max( 0, (int) ( $state['archive_uncompressed_bytes'] ?? 0 ) ),
			'source_home_url'              => $this->bounded_url( $state['source_home_url'] ?? '' ),
			'source_site_url'              => $this->bounded_url( $state['source_site_url'] ?? '' ),
			'source_table_prefix'          => $this->bounded_text( $state['source_table_prefix'] ?? '', 128 ),
			'destination_home_url'         => $this->bounded_url( $state['destination_home_url'] ?? '' ),
			'destination_site_url'         => $this->bounded_url( $state['destination_site_url'] ?? '' ),
			'destination_table_prefix'     => $this->bounded_text( $state['destination_table_prefix'] ?? '', 128 ),
			'destination_mode'             => $this->bounded_text( $state['destination_mode'] ?? '', 32 ),
			'destination_storage_isolated' => true === ( $state['destination_storage_isolated'] ?? false ),
			'search_visibility_disabled'   => true === ( $state['search_visibility_disabled'] ?? false ),
			'outbound_safe'                => true === ( $state['outbound_safe'] ?? false ),
			'backups_ready'                => true === ( $state['backups_ready'] ?? false ),
			'target_authorized'            => true === ( $state['target_authorized'] ?? false ),
			'disk_free_bytes'              => null === ( $state['disk_free_bytes'] ?? null ) ? null : max( 0, (int) $state['disk_free_bytes'] ),
			'disk_required_bytes'          => max( 0, (int) ( $state['disk_required_bytes'] ?? 0 ) ),
			'manifest_contract_valid'      => true === ( $state['manifest_contract_valid'] ?? false ),
			'child_manifest_hashes_valid'  => true === ( $state['child_manifest_hashes_valid'] ?? false ),
			'full_payload_verified'        => true === ( $state['full_payload_verified'] ?? false ),
			'restore_allowed'              => true === ( $state['restore_allowed'] ?? false ),
			'blockers'                     => $this->normalize_codes( $state['blockers'] ?? array() ),
			'advisories'                   => $this->normalize_codes( $state['advisories'] ?? array() ),
			'staged_at'                    => $this->bounded_timestamp( $state['staged_at'] ?? '' ),
			'validated_at'                 => $this->bounded_timestamp( $state['validated_at'] ?? '' ),
			'updated_at'                   => $this->bounded_timestamp( $state['updated_at'] ?? '' ),
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
	 * Normalize one bounded text value.
	 *
	 * @param mixed $value Raw value.
	 * @param int   $limit Maximum bytes.
	 */
	private function bounded_text( mixed $value, int $limit ): string {
		return is_string( $value ) ? substr( $value, 0, $limit ) : '';
	}

	/**
	 * Normalize one bounded HTTP(S) URL.
	 *
	 * @param mixed $url Raw URL.
	 */
	private function bounded_url( mixed $url ): string {
		if ( ! is_string( $url ) ) {
			return '';
		}

		$url    = substr( $url, 0, 2048 );
		$scheme = wp_parse_url( $url, PHP_URL_SCHEME );

		return in_array( $scheme, array( 'http', 'https' ), true ) ? $url : '';
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
		foreach ( array_slice( $codes, 0, 80 ) as $code ) {
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
	 * Keep only the newest bounded import states.
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
	 * Validate one clone job identifier.
	 *
	 * @param string $job_id Clone job identifier.
	 */
	private function valid_job_id( string $job_id ): bool {
		return 1 === preg_match( '/^[A-Za-z0-9][A-Za-z0-9._:-]{7,127}$/', $job_id );
	}
}
