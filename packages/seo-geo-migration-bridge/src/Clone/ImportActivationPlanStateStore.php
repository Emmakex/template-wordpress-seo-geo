<?php
/**
 * Portable Clone sandbox activation-plan state persistence.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge\Clone;

/**
 * Stores bounded non-autoloaded activation planning evidence.
 */
final class ImportActivationPlanStateStore {
	public const OPTION_NAME    = 'seo_geo_migration_clone_import_activation_plan_v1';
	public const SCHEMA_VERSION = 1;

	private const MAX_STATES = 20;

	/**
	 * Return one normalized activation-plan state.
	 *
	 * @param string $job_id Clone job identifier.
	 * @return array<string,mixed>|null
	 */
	public function get( string $job_id ): ?array {
		$states = $this->all();

		return $states[ $job_id ] ?? null;
	}

	/**
	 * Persist one bounded activation-plan state.
	 *
	 * @param string              $job_id Clone job identifier.
	 * @param array<string,mixed> $state  Activation-plan state.
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
	 * Delete one activation-plan state.
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
	 * Return all normalized activation-plan states.
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
	 * Normalize one activation-plan state.
	 *
	 * @param string              $job_id Clone job identifier.
	 * @param array<string,mixed> $state  Raw state.
	 * @return array<string,mixed>|null
	 */
	private function normalize_state( string $job_id, array $state ): ?array {
		if ( ! $this->valid_job_id( $job_id ) ) {
			return null;
		}

		$status = is_string( $state['status'] ?? null ) ? $state['status'] : 'blocked';
		if ( ! in_array( $status, array( 'ready', 'blocked' ), true ) ) {
			return null;
		}

		return array(
			'schema_version'              => self::SCHEMA_VERSION,
			'job_id'                      => $job_id,
			'status'                      => $status,
			'plan_fingerprint'            => $this->normalize_hash( $state['plan_fingerprint'] ?? '' ),
			'archive_sha256'              => $this->normalize_hash( $state['archive_sha256'] ?? '' ),
			'package_manifest_sha256'     => $this->normalize_hash( $state['package_manifest_sha256'] ?? '' ),
			'database_manifest_sha256'    => $this->normalize_hash( $state['database_manifest_sha256'] ?? '' ),
			'files_manifest_sha256'       => $this->normalize_hash( $state['files_manifest_sha256'] ?? '' ),
			'staging_namespace'           => $this->normalize_identifier( $state['staging_namespace'] ?? '' ),
			'table_count'                 => max( 0, (int) ( $state['table_count'] ?? 0 ) ),
			'staged_file_count'           => max( 0, (int) ( $state['staged_file_count'] ?? 0 ) ),
			'staged_file_bytes'           => max( 0, (int) ( $state['staged_file_bytes'] ?? 0 ) ),
			'destination_home_url'        => $this->bounded_url( $state['destination_home_url'] ?? '' ),
			'destination_site_url'        => $this->bounded_url( $state['destination_site_url'] ?? '' ),
			'destination_mode'            => $this->bounded_text( $state['destination_mode'] ?? '', 32 ),
			'storage_isolated'            => true === ( $state['storage_isolated'] ?? false ),
			'search_visibility_disabled'  => true === ( $state['search_visibility_disabled'] ?? false ),
			'outbound_safe'               => true === ( $state['outbound_safe'] ?? false ),
			'backups_ready'               => true === ( $state['backups_ready'] ?? false ),
			'target_authorized'           => true === ( $state['target_authorized'] ?? false ),
			'recovery_valid'              => true === ( $state['recovery_valid'] ?? false ),
			'recovery_database_reference' => $this->bounded_text( $state['recovery_database_reference'] ?? '', 200 ),
			'recovery_database_sha256'    => $this->normalize_hash( $state['recovery_database_sha256'] ?? '' ),
			'recovery_database_created_at'=> $this->bounded_timestamp( $state['recovery_database_created_at'] ?? '' ),
			'recovery_database_size'      => max( 0, (int) ( $state['recovery_database_size'] ?? 0 ) ),
			'recovery_files_reference'    => $this->bounded_text( $state['recovery_files_reference'] ?? '', 200 ),
			'recovery_files_sha256'       => $this->normalize_hash( $state['recovery_files_sha256'] ?? '' ),
			'recovery_files_created_at'   => $this->bounded_timestamp( $state['recovery_files_created_at'] ?? '' ),
			'recovery_files_size'         => max( 0, (int) ( $state['recovery_files_size'] ?? 0 ) ),
			'valid_until'                 => max( 0, (int) ( $state['valid_until'] ?? 0 ) ),
			'active_tables_untouched'     => true === ( $state['active_tables_untouched'] ?? false ),
			'active_roots_untouched'      => true === ( $state['active_roots_untouched'] ?? false ),
			'mutation_performed'          => true === ( $state['mutation_performed'] ?? false ),
			'activation_allowed'          => true === ( $state['activation_allowed'] ?? false ),
			'blockers'                    => $this->normalize_codes( $state['blockers'] ?? array() ),
			'advisories'                  => $this->normalize_codes( $state['advisories'] ?? array() ),
			'planned_at'                  => $this->bounded_timestamp( $state['planned_at'] ?? '' ),
			'updated_at'                  => $this->bounded_timestamp( $state['updated_at'] ?? '' ),
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
	 * Normalize one bounded text value.
	 *
	 * @param mixed $value Raw value.
	 * @param int   $limit Maximum bytes.
	 */
	private function bounded_text( mixed $value, int $limit ): string {
		return is_string( $value ) ? substr( $value, 0, $limit ) : '';
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
	 * @param array<string,array<string,mixed>> $states Activation-plan states.
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
