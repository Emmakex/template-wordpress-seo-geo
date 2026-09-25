<?php
/**
 * Portable Clone destination file-staging workspace.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge\Clone;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Writes verified files only into a deterministic job-owned staging tree.
 */
final class ImportFileStagingWorkspace {
	private const DIRECTORY_NAME = 'seo-geo-migration-stage';
	private const ROOT_IDS       = array( 'uploads', 'plugins', 'themes' );

	/**
	 * Ensure one protected job-owned staging root.
	 *
	 * @param string $job_id Clone job identifier.
	 */
	public function ensure( string $job_id ): ?string {
		if ( ! $this->valid_job_id( $job_id ) ) {
			return null;
		}

		$base = $this->base_path();
		if ( ! is_dir( $base ) && ! wp_mkdir_p( $base ) ) {
			return null;
		}
		$this->protect_directory( $base );

		$root = trailingslashit( $base ) . $this->root_key( $job_id ) . '/';
		if ( ! is_dir( $root ) && ! wp_mkdir_p( $root ) ) {
			return null;
		}
		$this->protect_directory( $root );

		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged,WordPress.WP.AlternativeFunctions.file_system_operations_chmod -- Best-effort restrictive staging directory permissions.
		@chmod( $root, 0700 );

		return trailingslashit( wp_normalize_path( $root ) );
	}

	/**
	 * Return one existing job-owned staging root.
	 *
	 * @param string $job_id Clone job identifier.
	 */
	public function root_path( string $job_id ): ?string {
		if ( ! $this->valid_job_id( $job_id ) ) {
			return null;
		}

		$root = trailingslashit( $this->base_path() ) . $this->root_key( $job_id ) . '/';

		return is_dir( $root ) ? trailingslashit( wp_normalize_path( $root ) ) : null;
	}

	/**
	 * Return deterministic filesystem-safe staging key.
	 *
	 * @param string $job_id Clone job identifier.
	 */
	public function root_key( string $job_id ): string {
		return $this->valid_job_id( $job_id ) ? substr( hash( 'sha256', $job_id ), 0, 32 ) : '';
	}

	/**
	 * Stage one already-verified payload file atomically.
	 *
	 * Existing matching files are accepted for crash-safe idempotency. Existing mismatched
	 * files are never replaced silently.
	 *
	 * @param string $job_id         Clone job identifier.
	 * @param string $root_id        uploads/plugins/themes.
	 * @param string $relative       Root-relative payload path.
	 * @param string $source         Absolute verified extracted source path.
	 * @param int    $expected_bytes Expected bytes.
	 * @param string $expected_hash  Expected SHA-256.
	 * @return array{path:string,bytes:int,sha256:string}|null
	 */
	public function stage_file(
		string $job_id,
		string $root_id,
		string $relative,
		string $source,
		int $expected_bytes,
		string $expected_hash
	): ?array {
		$relative = $this->normalize_relative( $relative );
		$root     = $this->ensure( $job_id );
		if (
			null === $root
			|| ! $this->valid_root_id( $root_id )
			|| '' === $relative
			|| ! is_file( $source )
			|| ! is_readable( $source )
			|| is_link( $source )
			|| 1 !== preg_match( '/^[a-f0-9]{64}$/', $expected_hash )
			|| 0 > $expected_bytes
		) {
			return null;
		}

		$destination = wp_normalize_path( trailingslashit( $root ) . $root_id . '/' . $relative );
		$root_base   = trailingslashit( wp_normalize_path( trailingslashit( $root ) . $root_id ) );
		if ( ! str_starts_with( $destination, $root_base ) ) {
			return null;
		}

		if ( is_file( $destination ) ) {
			$existing = $this->file_info( $job_id, $root_id, $relative );
			if (
				is_array( $existing )
				&& $expected_bytes === (int) $existing['bytes']
				&& hash_equals( $expected_hash, (string) $existing['sha256'] )
			) {
				return $existing;
			}

			return null;
		}

		$directory = dirname( $destination );
		if ( ! is_dir( $directory ) && ! wp_mkdir_p( $directory ) ) {
			return null;
		}

		$temp = $destination . '.seo-geo-part';
		if ( is_file( $temp ) ) {
			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged,WordPress.WP.AlternativeFunctions.unlink_unlink -- Removes only deterministic job-owned partial staging file.
			@unlink( $temp );
		}

		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged,WordPress.WP.AlternativeFunctions.file_system_operations_copy -- Copies only verified private payload into isolated job-owned staging.
		if ( ! @copy( $source, $temp ) ) {
			return null;
		}

		$bytes = filesize( $temp );
		$hash  = hash_file( 'sha256', $temp );
		if (
			false === $bytes
			|| false === $hash
			|| $expected_bytes !== (int) $bytes
			|| ! hash_equals( $expected_hash, $hash )
		) {
			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged,WordPress.WP.AlternativeFunctions.unlink_unlink -- Removes only failed job-owned partial staging file.
			@unlink( $temp );
			return null;
		}

		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged,WordPress.WP.AlternativeFunctions.file_system_operations_rename -- Atomic promotion remains inside the same job-owned staging directory.
		if ( ! @rename( $temp, $destination ) ) {
			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged,WordPress.WP.AlternativeFunctions.unlink_unlink -- Removes only failed job-owned partial staging file.
			@unlink( $temp );
			return null;
		}

		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged,WordPress.WP.AlternativeFunctions.file_system_operations_chmod -- Best-effort restrictive staging file permissions.
		@chmod( $destination, 0600 );

		return $this->file_info( $job_id, $root_id, $relative );
	}

	/**
	 * Return one staged file identity.
	 *
	 * @param string $job_id   Clone job identifier.
	 * @param string $root_id  uploads/plugins/themes.
	 * @param string $relative Root-relative file path.
	 * @return array{path:string,bytes:int,sha256:string}|null
	 */
	public function file_info( string $job_id, string $root_id, string $relative ): ?array {
		$relative = $this->normalize_relative( $relative );
		$root     = $this->root_path( $job_id );
		if ( null === $root || ! $this->valid_root_id( $root_id ) || '' === $relative ) {
			return null;
		}

		$absolute  = wp_normalize_path( trailingslashit( $root ) . $root_id . '/' . $relative );
		$root_base = trailingslashit( wp_normalize_path( trailingslashit( $root ) . $root_id ) );
		if (
			! str_starts_with( $absolute, $root_base )
			|| ! is_file( $absolute )
			|| ! is_readable( $absolute )
			|| is_link( $absolute )
		) {
			return null;
		}

		$bytes = filesize( $absolute );
		$hash  = hash_file( 'sha256', $absolute );
		if ( false === $bytes || false === $hash ) {
			return null;
		}

		return array(
			'path'   => $absolute,
			'bytes'  => (int) $bytes,
			'sha256' => $hash,
		);
	}

	/**
	 * Confirm a new job has no staged payload files yet.
	 *
	 * @param string $job_id Clone job identifier.
	 */
	public function payload_empty( string $job_id ): bool {
		$root = $this->ensure( $job_id );
		if ( null === $root ) {
			return false;
		}

		foreach ( self::ROOT_IDS as $root_id ) {
			$path = trailingslashit( $root ) . $root_id;
			if ( ! is_dir( $path ) ) {
				continue;
			}

			$iterator = new RecursiveIteratorIterator(
				new RecursiveDirectoryIterator( $path, FilesystemIterator::SKIP_DOTS )
			);
			foreach ( $iterator as $item ) {
				if ( $item->isFile() || $item->isLink() ) {
					return false;
				}
			}
		}

		return true;
	}

	/**
	 * Summarize all staged payload files and reject symlinks/unexpected roots.
	 *
	 * @param string $job_id Clone job identifier.
	 * @return array{files:int,bytes:int}|null
	 */
	public function payload_summary( string $job_id ): ?array {
		$root = $this->root_path( $job_id );
		if ( null === $root ) {
			return null;
		}

		$files = 0;
		$bytes = 0;
		foreach ( self::ROOT_IDS as $root_id ) {
			$path = trailingslashit( $root ) . $root_id;
			if ( ! is_dir( $path ) ) {
				continue;
			}

			$iterator = new RecursiveIteratorIterator(
				new RecursiveDirectoryIterator( $path, FilesystemIterator::SKIP_DOTS )
			);
			foreach ( $iterator as $item ) {
				if ( $item->isLink() || ! $item->isFile() ) {
					return null;
				}
				$size = $item->getSize();
				if ( 0 > $size ) {
					return null;
				}
				++$files;
				$bytes += $size;
			}
		}

		$entries = scandir( $root, SCANDIR_SORT_ASCENDING );
		if ( false === $entries ) {
			return null;
		}
		foreach ( $entries as $entry ) {
			if ( in_array( $entry, array( '.', '..', '.htaccess', 'index.php' ), true ) || in_array( $entry, self::ROOT_IDS, true ) ) {
				continue;
			}

			return null;
		}

		return array(
			'files' => $files,
			'bytes' => $bytes,
		);
	}

	/**
	 * Delete one job-owned staging tree.
	 *
	 * @param string $job_id Clone job identifier.
	 */
	public function cleanup( string $job_id ): bool {
		$root = $this->root_path( $job_id );
		$base = trailingslashit( $this->base_path() );
		if ( null === $root || ! str_starts_with( $root, $base ) ) {
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
				// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged,WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- Removes only job-owned destination staging directories.
				@rmdir( $path );
			} else {
				// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged,WordPress.WP.AlternativeFunctions.unlink_unlink -- Removes only job-owned destination staging files.
				@unlink( $path );
			}
		}

		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged,WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- Removes only job-owned destination staging root.
		return @rmdir( untrailingslashit( $root ) );
	}

	/**
	 * Return normalized staging base path.
	 */
	public function base_path(): string {
		return trailingslashit( wp_normalize_path( WP_CONTENT_DIR ) ) . self::DIRECTORY_NAME . '/';
	}

	/**
	 * Normalize one root-relative payload path.
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
			|| '..' === $relative
			|| str_contains( $relative, "\0" )
		) {
			return '';
		}

		$segments = explode( '/', $relative );
		foreach ( $segments as $segment ) {
			if ( '' === $segment || '.' === $segment || '..' === $segment ) {
				return '';
			}
		}

		return $relative;
	}

	/**
	 * Protect one web-adjacent staging directory.
	 *
	 * @param string $directory Directory to protect.
	 */
	private function protect_directory( string $directory ): void {
		$htaccess = trailingslashit( $directory ) . '.htaccess';
		$index    = trailingslashit( $directory ) . 'index.php';

		if ( ! is_file( $htaccess ) ) {
			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged,WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Defense-in-depth deny guard for isolated destination staging.
			@file_put_contents( $htaccess, "Deny from all\n" );
		}
		if ( ! is_file( $index ) ) {
			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged,WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Defense-in-depth index guard for isolated destination staging.
			@file_put_contents( $index, "<?php\n// Silence is golden.\n" );
		}
	}

	/**
	 * Validate root identifier.
	 *
	 * @param string $root_id Root identifier.
	 */
	private function valid_root_id( string $root_id ): bool {
		return in_array( $root_id, self::ROOT_IDS, true );
	}

	/**
	 * Validate clone job identifier.
	 *
	 * @param string $job_id Clone job identifier.
	 */
	private function valid_job_id( string $job_id ): bool {
		return 1 === preg_match( '/^[A-Za-z0-9][A-Za-z0-9._:-]{7,127}$/', $job_id );
	}
}
