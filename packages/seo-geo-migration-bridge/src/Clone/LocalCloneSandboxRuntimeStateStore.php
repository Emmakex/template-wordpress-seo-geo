<?php
/**
 * Portable Clone isolated sandbox control runtime state.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge\Clone;

/**
 * Stores bounded resumable state for Migration Bridge + isolated sandbox configuration.
 */
final class LocalCloneSandboxRuntimeStateStore {
	public const OPTION_NAME    = 'seo_geo_migration_local_clone_sandbox_runtime_v1';
	public const SCHEMA_VERSION = 1;

	private const MAX_STATES = 20;

	/**
	 * Return one normalized sandbox runtime state.
	 *
	 * @param string $job_id Clone job identifier.
	 * @return array<string,mixed>|null
	 */
	public function get( string $job_id ): ?array {
		$states = $this->all();

		return $states[ $job_id ] ?? null;
	}

	/**
	 * Persist one sandbox runtime state.
	 *
	 * @param string              $job_id Clone job identifier.
	 * @param array<string,mixed> $state  Sandbox runtime state.
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
	 * Delete one sandbox runtime state.
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
	 * Return every valid stored state.
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
		if ( ! in_array( $status, array( 'running', 'complete', 'blocked' ), true ) ) {
			return null;
		}

		$stage = is_string( $state['stage'] ?? null ) ? $state['stage'] : 'bridge-copy';
		if ( ! in_array( $stage, array( 'bridge-copy', 'bridge-verify', 'configure', 'complete' ), true ) ) {
			return null;
		}

		return array(
			'schema_version'            => self::SCHEMA_VERSION,
			'job_id'                    => $job_id,
			'status'                    => $status,
			'stage'                     => $stage,
			'plan_hash'                 => $this->normalize_hash( $state['plan_hash'] ?? '' ),
			'ownership_marker_sha256'   => $this->normalize_hash( $state['ownership_marker_sha256'] ?? '' ),
			'core_fingerprint'          => $this->normalize_hash( $state['core_fingerprint'] ?? '' ),
			'target_path'               => $this->bounded_absolute_path( $state['target_path'] ?? '' ),
			'target_url'                => $this->bounded_url( $state['target_url'] ?? '' ),
			'target_table_prefix'       => $this->bounded_prefix( $state['target_table_prefix'] ?? '' ),
			'bridge_source_root'        => $this->bounded_absolute_path( $state['bridge_source_root'] ?? '' ),
			'pending_dirs'              => $this->normalize_paths( $state['pending_dirs'] ?? array() ),
			'current_dir'               => $this->bounded_relative_path( $state['current_dir'] ?? '' ),
			'after_name'                => $this->bounded_name( $state['after_name'] ?? '' ),
			'bridge_copy_files'         => max( 0, (int) ( $state['bridge_copy_files'] ?? 0 ) ),
			'bridge_copy_bytes'         => max( 0, (int) ( $state['bridge_copy_bytes'] ?? 0 ) ),
			'bridge_copy_fingerprint'   => $this->normalize_hash( $state['bridge_copy_fingerprint'] ?? '' ),
			'bridge_verify_files'       => max( 0, (int) ( $state['bridge_verify_files'] ?? 0 ) ),
			'bridge_verify_bytes'       => max( 0, (int) ( $state['bridge_verify_bytes'] ?? 0 ) ),
			'bridge_verify_fingerprint' => $this->normalize_hash( $state['bridge_verify_fingerprint'] ?? '' ),
			'wp_config_sha256'          => $this->normalize_hash( $state['wp_config_sha256'] ?? '' ),
			'mu_plugin_sha256'          => $this->normalize_hash( $state['mu_plugin_sha256'] ?? '' ),
			'sandbox_marker_enabled'    => true === ( $state['sandbox_marker_enabled'] ?? false ),
			'storage_isolated'          => true === ( $state['storage_isolated'] ?? false ),
			'outbound_blocked'          => true === ( $state['outbound_blocked'] ?? false ),
			'search_blocked'            => true === ( $state['search_blocked'] ?? false ),
			'target_authorized'         => true === ( $state['target_authorized'] ?? false ),
			'backups_ready'             => true === ( $state['backups_ready'] ?? false ),
			'database_untouched'        => true === ( $state['database_untouched'] ?? false ),
			'client_content_untouched'  => true === ( $state['client_content_untouched'] ?? false ),
			'sandbox_runtime_ready'     => true === ( $state['sandbox_runtime_ready'] ?? false ),
			'bootstrap_next'            => $this->bounded_code( $state['bootstrap_next'] ?? '' ),
			'blockers'                  => $this->normalize_codes( $state['blockers'] ?? array() ),
			'started_at'                => $this->bounded_timestamp( $state['started_at'] ?? '' ),
			'updated_at'                => $this->bounded_timestamp( $state['updated_at'] ?? '' ),
			'completed_at'              => $this->bounded_timestamp( $state['completed_at'] ?? '' ),
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
	 * Normalize one bounded absolute path.
	 *
	 * @param mixed $path Raw path.
	 */
	private function bounded_absolute_path( mixed $path ): string {
		if ( ! is_string( $path ) ) {
			return '';
		}

		$path = untrailingslashit( wp_normalize_path( trim( $path ) ) );

		return 4096 >= strlen( $path ) ? $path : '';
	}

	/**
	 * Normalize one bounded HTTP(S) URL.
	 *
	 * @param mixed $url Raw URL.
	 */
	private function bounded_url( mixed $url ): string {
		return is_string( $url ) && 2048 >= strlen( $url ) ? esc_url_raw( $url ) : '';
	}

	/**
	 * Normalize one WordPress table prefix.
	 *
	 * @param mixed $prefix Raw prefix.
	 */
	private function bounded_prefix( mixed $prefix ): string {
		return is_string( $prefix )
			&& 64 >= strlen( $prefix )
			&& 1 === preg_match( '/^[A-Za-z0-9_]+$/', $prefix )
			? $prefix
			: '';
	}

	/**
	 * Normalize traversal paths.
	 *
	 * @param mixed $paths Raw paths.
	 * @return list<string>
	 */
	private function normalize_paths( mixed $paths ): array {
		if ( ! is_array( $paths ) ) {
			return array();
		}

		$out = array();
		foreach ( array_slice( $paths, 0, 50000 ) as $path ) {
			if ( ! is_string( $path ) ) {
				continue;
			}

			$bounded = $this->bounded_relative_path( $path );
			if ( '' === $bounded && '' !== $path ) {
				continue;
			}

			$out[] = $bounded;
		}

		return array_values( array_unique( $out ) );
	}

	/**
	 * Normalize one bounded relative traversal path.
	 *
	 * @param mixed $path Raw relative path.
	 */
	private function bounded_relative_path( mixed $path ): string {
		if ( ! is_string( $path ) ) {
			return '';
		}

		$path = ltrim( wp_normalize_path( $path ), '/' );
		if ( 2048 < strlen( $path ) || str_contains( $path, '../' ) ) {
			return '';
		}

		return $path;
	}

	/**
	 * Normalize one bounded directory cursor name.
	 *
	 * @param mixed $name Raw name.
	 */
	private function bounded_name( mixed $name ): string {
		if ( ! is_string( $name ) || 255 < strlen( $name ) || str_contains( $name, '/' ) || str_contains( $name, '\\' ) ) {
			return '';
		}

		return $name;
	}

	/**
	 * Normalize one machine-readable code.
	 *
	 * @param mixed $code Raw code.
	 */
	private function bounded_code( mixed $code ): string {
		return is_string( $code ) && 1 === preg_match( '/^[a-z0-9][a-z0-9._-]{0,119}$/', $code ) ? $code : '';
	}

	/**
	 * Normalize blockers.
	 *
	 * @param mixed $codes Raw codes.
	 * @return list<string>
	 */
	private function normalize_codes( mixed $codes ): array {
		if ( ! is_array( $codes ) ) {
			return array();
		}

		$out = array();
		foreach ( array_slice( $codes, 0, 80 ) as $code ) {
			$bounded = $this->bounded_code( $code );
			if ( '' !== $bounded ) {
				$out[] = $bounded;
			}
		}

		return array_values( array_unique( $out ) );
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
			static fn ( array $left, array $right ): int => strcmp(
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
