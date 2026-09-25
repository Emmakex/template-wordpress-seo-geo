<?php
/**
 * Portable Clone private ZIP delivery archive.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge\Clone;

/**
 * Owns all delivery-archive filesystem mutations outside the export workspace.
 */
final class PackageArchive {
	public const DIRECTORY_NAME = 'seo-geo-migration-bridge-delivery';

	/**
	 * Reset one job-owned partial/final archive and prepare the private directory.
	 *
	 * @param string $job_id Clone job identifier.
	 */
	public function start( string $job_id ): bool {
		if ( ! $this->valid_job_id( $job_id ) || ! $this->ensure_base() ) {
			return false;
		}

		foreach ( array( $this->partial_path( $job_id ), $this->final_path( $job_id ) ) as $path ) {
			if ( is_file( $path ) ) {
				// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged,WordPress.WP.AlternativeFunctions.unlink_unlink -- Removes only this job-owned private delivery archive.
				@unlink( $path );
			}
		}

		return ! is_file( $this->partial_path( $job_id ) ) && ! is_file( $this->final_path( $job_id ) );
	}

	/**
	 * Append one bounded list of workspace files to the private partial ZIP.
	 *
	 * @param string       $job_id        Clone job identifier.
	 * @param string       $workspace_root Absolute workspace root.
	 * @param list<string> $relative_paths Workspace-relative files.
	 */
	public function append_files( string $job_id, string $workspace_root, array $relative_paths ): bool {
		if ( ! $this->valid_job_id( $job_id ) || array() === $relative_paths || ! $this->ensure_base() ) {
			return false;
		}

		$workspace_root = trailingslashit( wp_normalize_path( $workspace_root ) );
		if ( ! is_dir( $workspace_root ) ) {
			return false;
		}

		$sources = array();
		foreach ( array_values( array_unique( $relative_paths ) ) as $relative ) {
			$relative = $this->normalize_relative( $relative );
			if ( '' === $relative ) {
				return false;
			}

			$absolute = $workspace_root . $relative;
			if (
				! str_starts_with( $absolute, $workspace_root )
				|| ! is_file( $absolute )
				|| ! is_readable( $absolute )
				|| is_link( $absolute )
			) {
				return false;
			}

			$sources[] = $absolute;
		}

		if ( ! $this->load_pclzip() ) {
			return false;
		}

		$partial = $this->partial_path( $job_id );
		// phpcs:ignore PHPCompatibility.Classes.NewClasses.pclzipFound -- PclZip is bundled by WordPress Core and loaded explicitly above.
		$archive = new \PclZip( $partial );
		$options = array(
			PCLZIP_OPT_REMOVE_PATH,
			untrailingslashit( $workspace_root ),
		);

		$result = is_file( $partial ) && 0 < (int) filesize( $partial )
			// phpcs:ignore PHPCompatibility.Classes.NewClasses.pclzipFound -- WordPress Core PclZip instance.
			? $archive->add( $sources, ...$options )
			// phpcs:ignore PHPCompatibility.Classes.NewClasses.pclzipFound -- WordPress Core PclZip instance.
			: $archive->create( $sources, ...$options );

		if ( ! is_array( $result ) || count( $result ) !== count( $sources ) ) {
			return false;
		}

		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged,WordPress.WP.AlternativeFunctions.file_system_operations_chmod -- Best-effort restrictive private archive permissions.
		@chmod( $partial, 0600 );

		return is_file( $partial ) && is_readable( $partial );
	}

	/**
	 * Finalize the private archive atomically and return its immutable identity.
	 *
	 * @param string $job_id Clone job identifier.
	 * @return array{path:string,bytes:int,sha256:string}|null
	 */
	public function finalize( string $job_id ): ?array {
		if ( ! $this->valid_job_id( $job_id ) ) {
			return null;
		}

		$partial = $this->partial_path( $job_id );
		$final   = $this->final_path( $job_id );
		if ( ! is_file( $partial ) || ! is_readable( $partial ) ) {
			return null;
		}

		if ( is_file( $final ) ) {
			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged,WordPress.WP.AlternativeFunctions.unlink_unlink -- Removes only a stale job-owned final archive.
			@unlink( $final );
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename -- Atomic rename remains inside the private delivery directory.
		if ( ! rename( $partial, $final ) ) {
			return null;
		}

		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged,WordPress.WP.AlternativeFunctions.file_system_operations_chmod -- Best-effort restrictive private archive permissions.
		@chmod( $final, 0600 );

		return $this->info( $job_id );
	}

	/**
	 * Return the current finalized archive identity.
	 *
	 * @param string $job_id Clone job identifier.
	 * @return array{path:string,bytes:int,sha256:string}|null
	 */
	public function info( string $job_id ): ?array {
		if ( ! $this->valid_job_id( $job_id ) ) {
			return null;
		}

		$path = $this->final_path( $job_id );
		if ( ! is_file( $path ) || ! is_readable( $path ) || is_link( $path ) ) {
			return null;
		}

		$bytes = filesize( $path );
		$hash  = hash_file( 'sha256', $path );
		if ( false === $bytes || false === $hash ) {
			return null;
		}

		return array(
			'path'   => $path,
			'bytes'  => (int) $bytes,
			'sha256' => $hash,
		);
	}

	/**
	 * Remove this job's partial and final delivery archives.
	 *
	 * @param string $job_id Clone job identifier.
	 */
	public function delete( string $job_id ): bool {
		if ( ! $this->valid_job_id( $job_id ) ) {
			return false;
		}

		$ok = true;
		foreach ( array( $this->partial_path( $job_id ), $this->final_path( $job_id ) ) as $path ) {
			if ( is_file( $path ) ) {
				// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged,WordPress.WP.AlternativeFunctions.unlink_unlink -- Removes only this job-owned private archive.
				$ok = @unlink( $path ) && $ok;
			}
		}

		return $ok;
	}

	/**
	 * Return the public download filename without exposing the private storage path.
	 *
	 * @param string $job_id Clone job identifier.
	 */
	public function download_name( string $job_id ): string {
		$name = sanitize_file_name( $job_id );
		if ( '' === $name ) {
			$name = substr( hash( 'sha256', $job_id ), 0, 24 );
		}

		return 'seo-geo-portable-clone-' . $name . '.zip';
	}

	/**
	 * Return the private delivery directory.
	 */
	public function base_path(): string {
		return trailingslashit( wp_normalize_path( get_temp_dir() ) ) . self::DIRECTORY_NAME;
	}

	/**
	 * Ensure WordPress Core PclZip is available.
	 */
	private function load_pclzip(): bool {
		if ( class_exists( '\\PclZip' ) ) {
			return true;
		}

		$path = trailingslashit( ABSPATH ) . 'wp-admin/includes/class-pclzip.php';
		if ( ! is_file( $path ) || ! is_readable( $path ) ) {
			return false;
		}

		require_once $path;

		return class_exists( '\\PclZip' );
	}

	/**
	 * Ensure and protect the private delivery directory.
	 */
	private function ensure_base(): bool {
		$base = $this->base_path();
		if ( ! is_dir( $base ) && ! wp_mkdir_p( $base ) ) {
			return false;
		}

		$htaccess = trailingslashit( $base ) . '.htaccess';
		$index    = trailingslashit( $base ) . 'index.php';
		if ( ! is_file( $htaccess ) ) {
			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged,WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Defense-in-depth private archive guard.
			@file_put_contents( $htaccess, "Deny from all\n" );
		}
		if ( ! is_file( $index ) ) {
			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged,WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Defense-in-depth private archive guard.
			@file_put_contents( $index, "<?php\n// Silence is golden.\n" );
		}

		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged,WordPress.WP.AlternativeFunctions.file_system_operations_chmod -- Best-effort restrictive private delivery directory permissions.
		@chmod( $base, 0700 );

		return is_dir( $base );
	}

	/**
	 * Return one deterministic private partial archive path.
	 *
	 * @param string $job_id Clone job identifier.
	 */
	private function partial_path( string $job_id ): string {
		return trailingslashit( $this->base_path() ) . $this->archive_key( $job_id ) . '.zip.part';
	}

	/**
	 * Return one deterministic private final archive path.
	 *
	 * @param string $job_id Clone job identifier.
	 */
	private function final_path( string $job_id ): string {
		return trailingslashit( $this->base_path() ) . $this->archive_key( $job_id ) . '.zip';
	}

	/**
	 * Build a filesystem-safe archive key.
	 *
	 * @param string $job_id Clone job identifier.
	 */
	private function archive_key( string $job_id ): string {
		return substr( hash( 'sha256', $job_id ), 0, 32 );
	}

	/**
	 * Normalize one archive-relative file path.
	 *
	 * @param string $relative Relative path.
	 */
	private function normalize_relative( string $relative ): string {
		$relative = ltrim( wp_normalize_path( trim( $relative ) ), '/' );
		if (
			'' === $relative
			|| 2048 < strlen( $relative )
			|| str_contains( $relative, '../' )
			|| str_contains( $relative, '/..' )
			|| str_starts_with( $relative, '.' )
		) {
			return '';
		}

		return $relative;
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
