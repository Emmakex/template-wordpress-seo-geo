<?php
/**
 * Portable Clone file-promotion recovery journal.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge\Clone;

/**
 * Persists file-promotion state outside the destination WordPress roots.
 */
final class ImportFilePromotionStateStore {
	public const SCHEMA_VERSION = 1;
	public const RELATIVE_PATH  = 'import/activation/file-promotion-state.json';

	/**
	 * Private job workspace.
	 *
	 * @var ExportWorkspace
	 */
	private ExportWorkspace $workspace;

	/**
	 * Construct the external file-promotion state store.
	 *
	 * @param ExportWorkspace|null $workspace Optional private workspace.
	 */
	public function __construct( ?ExportWorkspace $workspace = null ) {
		$this->workspace = $workspace ?? new ExportWorkspace();
	}

	/**
	 * Return one normalized file-promotion state.
	 *
	 * @param string $job_id Clone job identifier.
	 * @return array<string,mixed>|null
	 */
	public function get( string $job_id ): ?array {
		$json = $this->workspace->read( $job_id, self::RELATIVE_PATH );
		if ( ! is_string( $json ) ) {
			return null;
		}

		$state = json_decode( $json, true );

		return is_array( $state ) ? $this->normalize( $job_id, $state ) : null;
	}

	/**
	 * Persist one normalized file-promotion state atomically in the private workspace.
	 *
	 * @param string              $job_id Clone job identifier.
	 * @param array<string,mixed> $state  State.
	 */
	public function save( string $job_id, array $state ): bool {
		$normalized = $this->normalize( $job_id, $state );
		if ( null === $normalized ) {
			return false;
		}

		$json = wp_json_encode(
			$normalized,
			JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
		);
		if ( ! is_string( $json ) ) {
			return false;
		}

		return is_array( $this->workspace->write( $job_id, self::RELATIVE_PATH, $json . "\n" ) );
	}

	/**
	 * Normalize one external file-promotion journal.
	 *
	 * @param string              $job_id Clone job identifier.
	 * @param array<string,mixed> $state  Raw state.
	 * @return array<string,mixed>|null
	 */
	private function normalize( string $job_id, array $state ): ?array {
		if ( 1 !== preg_match( '/^[A-Za-z0-9][A-Za-z0-9._-]{7,127}$/', $job_id ) ) {
			return null;
		}

		$status = is_string( $state['status'] ?? null ) ? $state['status'] : 'prepared';
		if ( ! in_array( $status, array( 'prepared', 'copying', 'candidate-ready', 'promoting', 'verifying', 'verified', 'rolling-back', 'rolled-back', 'blocked' ), true ) ) {
			return null;
		}

		$roots = array();
		foreach ( is_array( $state['roots'] ?? null ) ? array_slice( $state['roots'], 0, 3 ) : array() as $root ) {
			if ( ! is_array( $root ) || ! is_string( $root['id'] ?? null ) ) {
				continue;
			}

			$id = $root['id'];
			if ( ! in_array( $id, array( 'uploads', 'plugins', 'themes' ), true ) ) {
				continue;
			}

			$roots[] = array(
				'id'              => $id,
				'staging_path'    => $this->path( $root['staging_path'] ?? '' ),
				'active_path'     => $this->path( $root['active_path'] ?? '' ),
				'candidate_path'  => $this->path( $root['candidate_path'] ?? '' ),
				'rollback_path'   => $this->path( $root['rollback_path'] ?? '' ),
				'file_count'      => max( 0, (int) ( $root['file_count'] ?? 0 ) ),
				'byte_count'      => max( 0, (int) ( $root['byte_count'] ?? 0 ) ),
				'status'          => $this->root_status( $root['status'] ?? '' ),
				'active_existed'  => true === ( $root['active_existed'] ?? false ),
				'rollback_ready'  => true === ( $root['rollback_ready'] ?? false ),
				'candidate_ready' => true === ( $root['candidate_ready'] ?? false ),
			);
		}

		return array(
			'schema_version'      => self::SCHEMA_VERSION,
			'job_id'              => $job_id,
			'status'              => $status,
			'activation_plan_hash'=> $this->hash( $state['activation_plan_hash'] ?? '' ),
			'file_fingerprint'    => $this->hash( $state['file_fingerprint'] ?? '' ),
			'roots'               => $roots,
			'root_index'          => max( 0, min( 3, (int) ( $state['root_index'] ?? 0 ) ) ),
			'pending_dirs'        => $this->paths( $state['pending_dirs'] ?? array() ),
			'current_dir'         => $this->relative( $state['current_dir'] ?? '' ),
			'after_name'          => $this->name( $state['after_name'] ?? '' ),
			'file_count'          => max( 0, (int) ( $state['file_count'] ?? 0 ) ),
			'byte_count'          => max( 0, (int) ( $state['byte_count'] ?? 0 ) ),
			'verify_file_count'   => max( 0, (int) ( $state['verify_file_count'] ?? 0 ) ),
			'verify_byte_count'   => max( 0, (int) ( $state['verify_byte_count'] ?? 0 ) ),
			'database_activated'  => true === ( $state['database_activated'] ?? false ),
			'rollback_available'  => true === ( $state['rollback_available'] ?? false ),
			'handoff_ready'       => true === ( $state['handoff_ready'] ?? false ),
			'blockers'            => $this->codes( $state['blockers'] ?? array() ),
			'prepared_at'         => $this->timestamp( $state['prepared_at'] ?? '' ),
			'promoted_at'         => $this->timestamp( $state['promoted_at'] ?? '' ),
			'verified_at'         => $this->timestamp( $state['verified_at'] ?? '' ),
			'rolled_back_at'      => $this->timestamp( $state['rolled_back_at'] ?? '' ),
			'updated_at'          => $this->timestamp( $state['updated_at'] ?? '' ),
		);
	}

	/**
	 * Normalize one absolute path.
	 *
	 * @param mixed $path Path candidate.
	 */
	private function path( mixed $path ): string {
		if ( ! is_string( $path ) || '' === trim( $path ) || 4096 < strlen( $path ) ) {
			return '';
		}

		return wp_normalize_path( $path );
	}

	/**
	 * Normalize one root status.
	 *
	 * @param mixed $status Status candidate.
	 */
	/**
	 * Normalize bounded relative directory queue entries.
	 *
	 * @param mixed $paths Path candidates.
	 * @return list<string>
	 */
	private function paths( mixed $paths ): array {
		if ( ! is_array( $paths ) ) {
			return array();
		}

		$out = array();
		foreach ( array_slice( $paths, 0, 50000 ) as $path ) {
			$normalized = $this->relative( $path );
			if ( '' === $normalized && '' !== $path ) {
				continue;
			}
			$out[] = $normalized;
		}

		return $out;
	}

	/**
	 * Normalize one root-relative path.
	 *
	 * @param mixed $path Path candidate.
	 */
	private function relative( mixed $path ): string {
		if ( ! is_string( $path ) ) {
			return '';
		}

		$path = ltrim( wp_normalize_path( $path ), '/' );
		return 2048 >= strlen( $path ) && ! str_contains( $path, '../' ) && ! str_contains( $path, '/..' )
			? $path
			: '';
	}

	/**
	 * Normalize one directory entry name.
	 *
	 * @param mixed $name Name candidate.
	 */
	private function name( mixed $name ): string {
		return is_string( $name ) && 255 >= strlen( $name ) && ! str_contains( $name, '/' ) ? $name : '';
	}

	private function root_status( mixed $status ): string {
		return is_string( $status )
			&& in_array( $status, array( 'pending', 'copying', 'candidate-ready', 'promoted', 'verified', 'rolled-back', 'blocked' ), true )
			? $status
			: 'pending';
	}

	/**
	 * Normalize one SHA-256 candidate.
	 *
	 * @param mixed $hash Hash candidate.
	 */
	private function hash( mixed $hash ): string {
		return is_string( $hash ) && 1 === preg_match( '/^[a-f0-9]{64}$/', $hash ) ? $hash : '';
	}

	/**
	 * Normalize bounded blocker codes.
	 *
	 * @param mixed $codes Blocker-code candidates.
	 * @return list<string>
	 */
	private function codes( mixed $codes ): array {
		if ( ! is_array( $codes ) ) {
			return array();
		}

		$out = array();
		foreach ( array_slice( $codes, 0, 40 ) as $code ) {
			if ( is_string( $code ) && 1 === preg_match( '/^[a-z0-9][a-z0-9._-]{0,119}$/', $code ) ) {
				$out[] = $code;
			}
		}

		return array_values( array_unique( $out ) );
	}

	/**
	 * Normalize one bounded timestamp string.
	 *
	 * @param mixed $value Timestamp candidate.
	 */
	private function timestamp( mixed $value ): string {
		return is_string( $value ) ? substr( $value, 0, 40 ) : '';
	}
}
