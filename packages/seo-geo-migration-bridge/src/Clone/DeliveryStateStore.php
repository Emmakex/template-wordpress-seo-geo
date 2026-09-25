<?php
/**
 * Portable Clone authenticated-delivery and retention state persistence.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge\Clone;

/**
 * Stores bounded resumable package-delivery state in one non-autoloaded option.
 */
final class DeliveryStateStore {
	public const OPTION_NAME    = 'seo_geo_migration_clone_delivery_state_v1';
	public const SCHEMA_VERSION = 1;
	private const MAX_STATES    = 20;

	/**
	 * Return all normalized delivery states.
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
	 * Return whether a new delivery may be created without evicting live private-artifact state.
	 *
	 * @param string $job_id Clone job identifier.
	 */
	public function can_create( string $job_id ): bool {
		$states = $this->all();
		if ( isset( $states[ $job_id ] ) ) {
			return true;
		}

		$live = 0;
		foreach ( $states as $state ) {
			if ( 'cleaned' !== ( $state['status'] ?? null ) ) {
				++$live;
			}
		}

		return $live < self::MAX_STATES;
	}

	/**
	 * Return one normalized delivery state.
	 *
	 * @param string $job_id Clone job identifier.
	 * @return array<string,mixed>|null
	 */
	public function get( string $job_id ): ?array {
		$states = $this->all();

		return $states[ $job_id ] ?? null;
	}

	/**
	 * Persist one bounded delivery state.
	 *
	 * @param string              $job_id Clone job identifier.
	 * @param array<string,mixed> $state  Delivery state.
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
	 * Normalize one stored delivery state.
	 *
	 * @param string              $job_id Clone job identifier.
	 * @param array<string,mixed> $state  Raw state.
	 * @return array<string,mixed>|null
	 */
	private function normalize_state( string $job_id, array $state ): ?array {
		if ( ! $this->valid_job_id( $job_id ) ) {
			return null;
		}

		$status = is_string( $state['status'] ?? null ) ? $state['status'] : 'building';
		if ( ! in_array( $status, array( 'building', 'ready', 'blocked', 'expired', 'cleaning', 'cleaned' ), true ) ) {
			return null;
		}

		return array(
			'schema_version'        => self::SCHEMA_VERSION,
			'job_id'                => $job_id,
			'status'                => $status,
			'directory_active'      => true === ( $state['directory_active'] ?? false ),
			'pending_dirs'          => $this->normalize_paths( $state['pending_dirs'] ?? array() ),
			'current_dir'           => is_string( $state['current_dir'] ?? null ) ? $this->bounded_path( $state['current_dir'] ) : '',
			'after_name'            => is_string( $state['after_name'] ?? null ) ? $this->bounded_name( $state['after_name'] ) : '',
			'archive_file_count'    => max( 0, (int) ( $state['archive_file_count'] ?? 0 ) ),
			'archive_source_bytes'  => max( 0, (int) ( $state['archive_source_bytes'] ?? 0 ) ),
			'verified_file_count'   => max( 0, (int) ( $state['verified_file_count'] ?? 0 ) ),
			'verified_byte_count'   => max( 0, (int) ( $state['verified_byte_count'] ?? 0 ) ),
			'verification_checksum'=> $this->normalize_hash( $state['verification_checksum'] ?? '' ),
			'package_checksum'      => $this->normalize_hash( $state['package_checksum'] ?? '' ),
			'package_manifest_hash' => $this->normalize_hash( $state['package_manifest_hash'] ?? '' ),
			'archive_sha256'        => $this->normalize_hash( $state['archive_sha256'] ?? '' ),
			'archive_bytes'         => max( 0, (int) ( $state['archive_bytes'] ?? 0 ) ),
			'retention_hours'       => max( 1, min( 168, (int) ( $state['retention_hours'] ?? 24 ) ) ),
			'expires_at'            => max( 0, (int) ( $state['expires_at'] ?? 0 ) ),
			'download_count'        => max( 0, (int) ( $state['download_count'] ?? 0 ) ),
			'cleanup_deleted_count' => max( 0, (int) ( $state['cleanup_deleted_count'] ?? 0 ) ),
			'blockers'              => $this->normalize_codes( $state['blockers'] ?? array() ),
			'started_at'            => $this->bounded_timestamp( $state['started_at'] ?? '' ),
			'updated_at'            => $this->bounded_timestamp( $state['updated_at'] ?? '' ),
			'ready_at'              => $this->bounded_timestamp( $state['ready_at'] ?? '' ),
			'downloaded_at'         => $this->bounded_timestamp( $state['downloaded_at'] ?? '' ),
			'cleaned_at'            => $this->bounded_timestamp( $state['cleaned_at'] ?? '' ),
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
	 * Normalize bounded traversal paths.
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
		foreach ( array_slice( $codes, 0, 50 ) as $code ) {
			if ( ! is_string( $code ) || 1 !== preg_match( '/^[a-z0-9][a-z0-9._-]{0,119}$/', $code ) ) {
				continue;
			}
			$normalized[] = $code;
		}

		return array_values( array_unique( $normalized ) );
	}

	/**
	 * Normalize one relative directory path.
	 *
	 * @param string $path Relative path.
	 */
	private function bounded_path( string $path ): string {
		$path = ltrim( wp_normalize_path( $path ), '/' );
		if ( 1024 < strlen( $path ) || str_contains( $path, '../' ) ) {
			return '';
		}

		return $path;
	}

	/**
	 * Normalize one directory entry name.
	 *
	 * @param string $name Entry name.
	 */
	private function bounded_name( string $name ): string {
		if ( 255 < strlen( $name ) || str_contains( $name, '/' ) || str_contains( $name, '\\' ) ) {
			return '';
		}

		return $name;
	}

	/**
	 * Bound one persisted timestamp.
	 *
	 * @param mixed $timestamp Raw timestamp.
	 */
	private function bounded_timestamp( mixed $timestamp ): string {
		return is_string( $timestamp ) ? substr( $timestamp, 0, 40 ) : '';
	}

	/**
	 * Keep only the newest bounded delivery states.
	 *
	 * @param array<string,array<string,mixed>> $states Delivery states.
	 * @return array<string,array<string,mixed>>
	 */
	private function bounded_states( array $states ): array {
		if ( count( $states ) <= self::MAX_STATES ) {
			return $states;
		}

		uasort(
			$states,
			static fn( array $left, array $right ): int => strcmp(
				(string) ( $left['updated_at'] ?? '' ),
				(string) ( $right['updated_at'] ?? '' )
			)
		);

		foreach ( array_keys( $states ) as $job_id ) {
			if ( count( $states ) <= self::MAX_STATES ) {
				break;
			}
			if ( 'cleaned' === ( $states[ $job_id ]['status'] ?? null ) ) {
				unset( $states[ $job_id ] );
			}
		}

		if ( count( $states ) <= self::MAX_STATES ) {
			return $states;
		}

		return array_slice( array_reverse( $states, true ), 0, self::MAX_STATES, true );
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
