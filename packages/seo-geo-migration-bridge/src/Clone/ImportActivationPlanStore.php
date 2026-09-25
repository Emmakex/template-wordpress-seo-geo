<?php
/**
 * Portable Import sandbox activation-plan state.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge\Clone;

/**
 * Stores bounded non-autoloaded activation planning evidence.
 */
final class ImportActivationPlanStore {
	public const OPTION_NAME    = 'seo_geo_migration_clone_import_activation_plan_v1';
	public const SCHEMA_VERSION = 1;

	private const MAX_STATES = 20;

	/**
	 * Return one normalized activation plan.
	 *
	 * @param string $job_id Clone job identifier.
	 * @return array<string,mixed>|null
	 */
	public function get( string $job_id ): ?array {
		$states = $this->all();

		return $states[ $job_id ] ?? null;
	}

	/**
	 * Persist one bounded activation plan.
	 *
	 * @param string              $job_id Clone job identifier.
	 * @param array<string,mixed> $state  Plan state.
	 */
	public function save( string $job_id, array $state ): bool {
		if ( ! $this->valid_job_id( $job_id ) ) {
			return false;
		}

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
	 * Delete one stored activation plan.
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
	 * Return normalized stored plans.
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

			$normalized = $this->normalize( $job_id, $state );
			if ( null !== $normalized ) {
				$states[ $job_id ] = $normalized;
			}
		}

		return $states;
	}

	/**
	 * Normalize one activation plan.
	 *
	 * @param string              $job_id Clone job identifier.
	 * @param array<string,mixed> $state  Raw state.
	 * @return array<string,mixed>|null
	 */
	private function normalize( string $job_id, array $state ): ?array {
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
			'plan_sha256'                 => $this->hash_value( $state['plan_sha256'] ?? '' ),
			'archive_sha256'              => $this->hash_value( $state['archive_sha256'] ?? '' ),
			'database_manifest_sha256'    => $this->hash_value( $state['database_manifest_sha256'] ?? '' ),
			'files_manifest_sha256'       => $this->hash_value( $state['files_manifest_sha256'] ?? '' ),
			'table_count'                 => max( 0, (int) ( $state['table_count'] ?? 0 ) ),
			'staging_file_count'          => max( 0, (int) ( $state['staging_file_count'] ?? 0 ) ),
			'staging_file_bytes'          => max( 0, (int) ( $state['staging_file_bytes'] ?? 0 ) ),
			'destination_home_url'        => $this->bounded_url( $state['destination_home_url'] ?? '' ),
			'destination_site_url'        => $this->bounded_url( $state['destination_site_url'] ?? '' ),
			'destination_table_prefix'    => $this->bounded_text( $state['destination_table_prefix'] ?? '', 128 ),
			'sandbox_mode'                => $this->bounded_text( $state['sandbox_mode'] ?? '', 32 ),
			'target_authorized'           => true === ( $state['target_authorized'] ?? false ),
			'search_visibility_disabled'  => true === ( $state['search_visibility_disabled'] ?? false ),
			'outbound_safe'               => true === ( $state['outbound_safe'] ?? false ),
			'backups_ready'               => true === ( $state['backups_ready'] ?? false ),
			'storage_isolated'            => true === ( $state['storage_isolated'] ?? false ),
			'recovery_valid'              => true === ( $state['recovery_valid'] ?? false ),
			'active_tables_untouched'     => true === ( $state['active_tables_untouched'] ?? false ),
			'active_roots_untouched'      => true === ( $state['active_roots_untouched'] ?? false ),
			'rewrite_verified'            => true === ( $state['rewrite_verified'] ?? false ),
			'activation_ready'            => true === ( $state['activation_ready'] ?? false ),
			'database_activation_allowed' => false,
			'file_activation_allowed'     => false,
			'mutations_performed'         => false,
			'tables'                      => $this->normalize_tables( $state['tables'] ?? array() ),
			'file_roots'                  => $this->normalize_file_roots( $state['file_roots'] ?? array() ),
			'recovery'                    => $this->normalize_recovery( $state['recovery'] ?? array() ),
			'blockers'                    => $this->normalize_codes( $state['blockers'] ?? array() ),
			'advisories'                  => $this->normalize_codes( $state['advisories'] ?? array() ),
			'planned_at'                  => $this->timestamp( $state['planned_at'] ?? '' ),
			'updated_at'                  => $this->timestamp( $state['updated_at'] ?? '' ),
		);
	}

	/**
	 * Normalize bounded table activation rows.
	 *
	 * @param mixed $rows Raw rows.
	 * @return list<array<string,mixed>>
	 */
	private function normalize_tables( mixed $rows ): array {
		if ( ! is_array( $rows ) ) {
			return array();
		}

		$out = array();
		foreach ( array_slice( $rows, 0, 1000 ) as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$source  = $this->table_name( $row['source_table'] ?? '' );
			$target  = $this->table_name( $row['target_table'] ?? '' );
			$staging = $this->table_name( $row['staging_table'] ?? '' );
			$backup  = $this->table_name( $row['rollback_table'] ?? '' );
			if ( '' === $source || '' === $target || '' === $staging || '' === $backup ) {
				continue;
			}

			$out[] = array(
				'source_table'   => $source,
				'target_table'   => $target,
				'staging_table'  => $staging,
				'rollback_table' => $backup,
				'target_exists'  => true === ( $row['target_exists'] ?? false ),
				'target_rows'    => max( 0, (int) ( $row['target_rows'] ?? 0 ) ),
				'staging_rows'   => max( 0, (int) ( $row['staging_rows'] ?? 0 ) ),
			);
		}

		return $out;
	}

	/**
	 * Normalize three staged file-root summaries.
	 *
	 * @param mixed $rows Raw rows.
	 * @return list<array<string,mixed>>
	 */
	private function normalize_file_roots( mixed $rows ): array {
		if ( ! is_array( $rows ) ) {
			return array();
		}

		$out = array();
		foreach ( array_slice( $rows, 0, 3 ) as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$id = is_string( $row['id'] ?? null ) ? $row['id'] : '';
			if ( ! in_array( $id, array( 'uploads', 'plugins', 'themes' ), true ) ) {
				continue;
			}

			$out[] = array(
				'id'                 => $id,
				'staging_file_count' => max( 0, (int) ( $row['staging_file_count'] ?? 0 ) ),
				'staging_bytes'      => max( 0, (int) ( $row['staging_bytes'] ?? 0 ) ),
				'active_exists'      => true === ( $row['active_exists'] ?? false ),
				'active_is_symlink'  => true === ( $row['active_is_symlink'] ?? false ),
			);
		}

		return $out;
	}

	/**
	 * Normalize bounded recovery references without private payload.
	 *
	 * @param mixed $recovery Raw recovery evidence.
	 * @return array<string,array<string,mixed>>
	 */
	private function normalize_recovery( mixed $recovery ): array {
		if ( ! is_array( $recovery ) ) {
			return array();
		}

		$out = array();
		foreach ( array( 'database', 'wp_content' ) as $key ) {
			$row = is_array( $recovery[ $key ] ?? null ) ? $recovery[ $key ] : array();
			if ( array() === $row ) {
				continue;
			}

			$out[ $key ] = array(
				'reference'  => $this->bounded_text( $row['reference'] ?? '', 200 ),
				'sha256'     => $this->hash_value( $row['sha256'] ?? '' ),
				'created_at' => $this->timestamp( $row['created_at'] ?? '' ),
				'scope'      => $this->bounded_text( $row['scope'] ?? '', 40 ),
				'size_bytes' => max( 0, (int) ( $row['size_bytes'] ?? 0 ) ),
			);
		}

		return $out;
	}

	/**
	 * Normalize machine-readable codes.
	 *
	 * @param mixed $codes Raw codes.
	 * @return list<string>
	 */
	private function normalize_codes( mixed $codes ): array {
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
	 * Normalize one table identifier.
	 *
	 * @param mixed $value Raw identifier.
	 */
	private function table_name( mixed $value ): string {
		return is_string( $value ) && 1 === preg_match( '/^[A-Za-z0-9_]{1,64}$/', $value ) ? $value : '';
	}

	/**
	 * Normalize one SHA-256.
	 *
	 * @param mixed $value Raw hash.
	 */
	private function hash_value( mixed $value ): string {
		return is_string( $value ) && 1 === preg_match( '/^[a-f0-9]{64}$/', $value ) ? $value : '';
	}

	/**
	 * Normalize one HTTP(S) URL.
	 *
	 * @param mixed $value Raw URL.
	 */
	private function bounded_url( mixed $value ): string {
		if ( ! is_string( $value ) ) {
			return '';
		}
		$value = substr( $value, 0, 2048 );

		return in_array( wp_parse_url( $value, PHP_URL_SCHEME ), array( 'http', 'https' ), true ) ? $value : '';
	}

	/**
	 * Normalize bounded text.
	 *
	 * @param mixed $value Raw text.
	 * @param int   $limit Byte limit.
	 */
	private function bounded_text( mixed $value, int $limit ): string {
		return is_string( $value ) ? substr( $value, 0, $limit ) : '';
	}

	/**
	 * Normalize timestamp.
	 *
	 * @param mixed $value Raw timestamp.
	 */
	private function timestamp( mixed $value ): string {
		return is_string( $value ) ? substr( $value, 0, 40 ) : '';
	}

	/**
	 * Keep newest bounded states.
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
	 * Validate one job identifier.
	 *
	 * @param string $job_id Clone job identifier.
	 */
	private function valid_job_id( string $job_id ): bool {
		return 1 === preg_match( '/^[A-Za-z0-9][A-Za-z0-9._:-]{7,127}$/', $job_id );
	}
}
