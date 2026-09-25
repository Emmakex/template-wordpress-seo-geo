<?php
/**
 * Portable Clone private export workspace.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge\Clone;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Owns all export payload filesystem writes and cleanup.
 */
final class ExportWorkspace {
	public const DIRECTORY_NAME = 'seo-geo-migration-bridge';

	/**
	 * @param string $job_id Clone job identifier.
	 * @return string|null
	 */
	public function ensure( string $job_id ): ?string {
		if ( ! $this->valid_job_id( $job_id ) ) {
			return null;
		}

		$base = $this->base_path();
		$path = trailingslashit( $base ) . $job_id;

		if ( ! is_dir( $base ) && ! wp_mkdir_p( $base ) ) {
			return null;
		}
		if ( ! is_dir( $path ) && ! wp_mkdir_p( $path ) ) {
			return null;
		}

		$this->protect_directory( $base );
		$this->protect_directory( $path );

		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged,WordPress.WP.AlternativeFunctions.file_system_operations_chmod -- Best-effort restrictive private payload permissions.
		@chmod( $path, 0700 );

		return trailingslashit( wp_normalize_path( $path ) );
	}

	/**
	 * @param string $job_id   Clone job identifier.
	 * @param string $relative Workspace-relative path.
	 * @param string $content  Payload bytes.
	 * @return array{path:string,bytes:int,sha256:string}|null
	 */
	public function write( string $job_id, string $relative, string $content ): ?array {
		$absolute = $this->absolute_path( $job_id, $relative, true );
		if ( null === $absolute ) {
			return null;
		}

		$parent = dirname( $absolute );
		if ( ! is_dir( $parent ) && ! wp_mkdir_p( $parent ) ) {
			return null;
		}
		$this->protect_directory( $parent );

		$temp = $absolute . '.tmp-' . wp_generate_password( 12, false, false );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Private export workspace uses bounded atomic local writes.
		$written = file_put_contents( $temp, $content, LOCK_EX );
		if ( false === $written || strlen( $content ) !== $written ) {
			if ( is_file( $temp ) ) {
				// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged,WordPress.WP.AlternativeFunctions.unlink_unlink -- Removes only a failed job-owned temp file.
				@unlink( $temp );
			}
			return null;
		}

		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged,WordPress.WP.AlternativeFunctions.file_system_operations_chmod -- Best-effort restrictive private payload permissions.
		@chmod( $temp, 0600 );

		if ( ! rename( $temp, $absolute ) ) {
			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged,WordPress.WP.AlternativeFunctions.unlink_unlink -- Removes only a failed job-owned temp file.
			@unlink( $temp );
			return null;
		}

		$hash = hash_file( 'sha256', $absolute );
		if ( false === $hash ) {
			return null;
		}

		return array(
			'path'   => $this->normalize_relative( $relative ),
			'bytes'  => $written,
			'sha256' => $hash,
		);
	}

	/**
	 * @param string $job_id   Clone job identifier.
	 * @param string $relative Workspace-relative path.
	 */
	public function read( string $job_id, string $relative ): ?string {
		$absolute = $this->absolute_path( $job_id, $relative, false );
		if ( null === $absolute || ! is_file( $absolute ) || ! is_readable( $absolute ) ) {
			return null;
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Server-local bounded private export workspace.
		$content = file_get_contents( $absolute );
		return false === $content ? null : $content;
	}

	/**
	 * @param string $job_id Clone job identifier.
	 */
	public function cleanup( string $job_id ): bool {
		$root = trailingslashit( $this->base_path() ) . $job_id . '/';
		$base = trailingslashit( $this->base_path() );
		if ( ! $this->valid_job_id( $job_id ) || ! is_dir( $root ) || ! str_starts_with( $root, $base ) ) {
			return false;
		}

		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::CHILD_FIRST
		);

		foreach ( $iterator as $item ) {
			$path = wp_normalize_path( $item->getPathname() );
			if ( ! str_starts_with( $path, $base ) ) {
				return false;
			}
			if ( $item->isDir() && ! $item->isLink() ) {
				// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged,WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- Removes only job-owned private export directories.
				@rmdir( $path );
			} else {
				// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged,WordPress.WP.AlternativeFunctions.unlink_unlink -- Removes only job-owned private export files.
				@unlink( $path );
			}
		}

		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged,WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- Removes only the job-owned workspace root.
		return @rmdir( untrailingslashit( $root ) );
	}

	public function base_path(): string {
		return trailingslashit( wp_normalize_path( get_temp_dir() ) ) . self::DIRECTORY_NAME;
	}

	/**
	 * @param string $job_id      Clone job identifier.
	 * @param string $relative    Workspace-relative path.
	 * @param bool   $create_root Ensure job root first.
	 */
	private function absolute_path( string $job_id, string $relative, bool $create_root ): ?string {
		if ( ! $this->valid_job_id( $job_id ) ) {
			return null;
		}
		$normalized = $this->normalize_relative( $relative );
		if ( '' === $normalized ) {
			return null;
		}
		$root = $create_root ? $this->ensure( $job_id ) : trailingslashit( $this->base_path() ) . $job_id . '/';
		if ( null === $root || ! is_dir( $root ) ) {
			return null;
		}
		$absolute = trailingslashit( wp_normalize_path( $root ) ) . $normalized;
		return str_starts_with( $absolute, trailingslashit( wp_normalize_path( $root ) ) ) ? $absolute : null;
	}

	/**
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
	 * @param string $directory Directory to protect.
	 */
	private function protect_directory( string $directory ): void {
		$htaccess = trailingslashit( $directory ) . '.htaccess';
		$index    = trailingslashit( $directory ) . 'index.php';
		if ( ! is_file( $htaccess ) ) {
			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged,WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Defense-in-depth local deny guard.
			@file_put_contents( $htaccess, "Deny from all\n" );
		}
		if ( ! is_file( $index ) ) {
			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged,WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Defense-in-depth local index guard.
			@file_put_contents( $index, "<?php\n// Silence is golden.\n" );
		}
	}

	/**
	 * @param string $job_id Clone job identifier.
	 */
	private function valid_job_id( string $job_id ): bool {
		return 1 === preg_match( '/^[A-Za-z0-9][A-Za-z0-9._:-]{7,127}$/', $job_id );
	}
}
