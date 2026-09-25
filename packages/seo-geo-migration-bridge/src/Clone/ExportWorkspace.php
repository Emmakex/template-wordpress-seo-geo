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
	public const DIRECTORY_NAME          = 'seo-geo-migration-bridge';
	public const DELIVERY_DIRECTORY_NAME = 'seo-geo-migration-bridge-delivery';

	/**
	 * Ensure one private job workspace exists.
	 *
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
	 * Atomically write one workspace-relative payload file.
	 *
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

		// phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename -- Atomic move stays inside the bounded private export workspace.
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
	 * Atomically copy one source file into the private workspace while hashing it.
	 *
	 * @param string $job_id   Clone job identifier.
	 * @param string $relative Workspace-relative destination.
	 * @param string $source   Absolute readable source file.
	 * @return array{path:string,bytes:int,sha256:string}|null
	 */
	public function copy_file( string $job_id, string $relative, string $source ): ?array {
		$source = wp_normalize_path( $source );
		if ( ! is_file( $source ) || ! is_readable( $source ) || is_link( $source ) ) {
			return null;
		}

		$absolute = $this->absolute_path( $job_id, $relative, true );
		if ( null === $absolute ) {
			return null;
		}

		$parent = dirname( $absolute );
		if ( ! is_dir( $parent ) && ! wp_mkdir_p( $parent ) ) {
			return null;
		}
		$this->protect_directory( $parent );

		$size_before = filesize( $source );
		if ( false === $size_before ) {
			return null;
		}

		$temp = $absolute . '.tmp-' . wp_generate_password( 12, false, false );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Streams a bounded server-local source into a private workspace.
		$input = fopen( $source, 'rb' );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Writes only to a job-owned private temporary file.
		$output = fopen( $temp, 'wb' );
		if ( false === $input || false === $output ) {
			if ( is_resource( $input ) ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Closes only the bounded source stream.
				fclose( $input );
			}
			if ( is_resource( $output ) ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Closes only the job-owned private stream.
				fclose( $output );
			}
			if ( is_file( $temp ) ) {
				// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged,WordPress.WP.AlternativeFunctions.unlink_unlink -- Removes only this failed job-owned temp file.
				@unlink( $temp );
			}
			return null;
		}

		$hash_context = hash_init( 'sha256' );
		$written      = 0;
		$success      = true;

		while ( ! feof( $input ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fread -- Bounded local stream copy.
			$chunk = fread( $input, 1048576 );
			if ( false === $chunk ) {
				$success = false;
				break;
			}
			if ( '' === $chunk ) {
				continue;
			}

			hash_update( $hash_context, $chunk );
			$chunk_length = strlen( $chunk );
			$offset       = 0;
			while ( $offset < $chunk_length ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- Writes only to a job-owned private temporary file.
				$result = fwrite( $output, substr( $chunk, $offset ) );
				if ( false === $result || 0 === $result ) {
					$success = false;
					break 2;
				}
				$offset  += $result;
				$written += $result;
			}
		}

		fflush( $output );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Closes only the bounded source stream.
		fclose( $input );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Closes only the job-owned private stream.
		fclose( $output );

		$size_after = filesize( $source );
		if ( ! $success || false === $size_after || $size_before !== $size_after || $written !== $size_before ) {
			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged,WordPress.WP.AlternativeFunctions.unlink_unlink -- Removes only this inconsistent job-owned temp file.
			@unlink( $temp );
			return null;
		}

		$stream_hash = hash_final( $hash_context );
		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged,WordPress.WP.AlternativeFunctions.file_system_operations_chmod -- Best-effort restrictive private payload permissions.
		@chmod( $temp, 0600 );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename -- Atomic move stays inside the bounded private export workspace.
		if ( ! rename( $temp, $absolute ) ) {
			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged,WordPress.WP.AlternativeFunctions.unlink_unlink -- Removes only this failed job-owned temp file.
			@unlink( $temp );
			return null;
		}

		$target_hash = hash_file( 'sha256', $absolute );
		if ( false === $target_hash || ! hash_equals( $stream_hash, $target_hash ) ) {
			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged,WordPress.WP.AlternativeFunctions.unlink_unlink -- Removes only a job-owned copy that failed integrity verification.
			@unlink( $absolute );
			return null;
		}

		return array(
			'path'   => $this->normalize_relative( $relative ),
			'bytes'  => $written,
			'sha256' => $target_hash,
		);
	}

	/**
	 * Read one workspace-relative payload file.
	 *
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
	 * Return one existing job-owned workspace root.
	 *
	 * @param string $job_id Clone job identifier.
	 */
	public function root_path( string $job_id ): ?string {
		if ( ! $this->valid_job_id( $job_id ) ) {
			return null;
		}

		$base = trailingslashit( wp_normalize_path( $this->base_path() ) );
		$root = $base . $job_id . '/';

		return is_dir( $root ) && str_starts_with( $root, $base ) ? $root : null;
	}

	/**
	 * Delete a bounded number of job-owned workspace entries.
	 *
	 * @param string $job_id Clone job identifier.
	 * @param int    $limit  Maximum files/directories removed this request.
	 * @return array{complete:bool,deleted:int}
	 */
	public function cleanup_batch( string $job_id, int $limit = 250 ): array {
		$limit = max( 1, min( 1000, $limit ) );
		$root  = $this->root_path( $job_id );
		if ( null === $root ) {
			return array(
				'complete' => true,
				'deleted'  => 0,
			);
		}

		$base     = trailingslashit( wp_normalize_path( $this->base_path() ) );
		$deleted  = 0;
		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::CHILD_FIRST
		);

		foreach ( $iterator as $item ) {
			if ( $deleted >= $limit ) {
				break;
			}

			$path = wp_normalize_path( $item->getPathname() );
			if ( ! str_starts_with( $path, $base ) ) {
				return array(
					'complete' => false,
					'deleted'  => $deleted,
				);
			}

			if ( $item->isDir() && ! $item->isLink() ) {
				// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged,WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- Removes only a job-owned private export directory.
				$removed = @rmdir( $path );
			} else {
				// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged,WordPress.WP.AlternativeFunctions.unlink_unlink -- Removes only a job-owned private export file.
				$removed = @unlink( $path );
			}

			if ( $removed ) {
				++$deleted;
			}
		}

		$remaining = new FilesystemIterator( $root, FilesystemIterator::SKIP_DOTS );
		if ( ! $remaining->valid() ) {
			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged,WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- Removes only the empty job-owned workspace root.
			@rmdir( untrailingslashit( $root ) );
		}

		return array(
			'complete' => ! is_dir( $root ),
			'deleted'  => $deleted,
		);
	}

	/**
	 * Reset one job-owned private delivery archive.
	 *
	 * @param string $job_id Clone job identifier.
	 */
	public function reset_delivery_archive( string $job_id ): bool {
		if ( ! $this->valid_job_id( $job_id ) || ! $this->ensure_delivery_base() ) {
			return false;
		}

		foreach ( array( $this->delivery_partial_path( $job_id ), $this->delivery_final_path( $job_id ) ) as $path ) {
			if ( is_file( $path ) ) {
				// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged,WordPress.WP.AlternativeFunctions.unlink_unlink -- Removes only this job-owned private delivery archive.
				@unlink( $path );
			}
		}

		return ! is_file( $this->delivery_partial_path( $job_id ) ) && ! is_file( $this->delivery_final_path( $job_id ) );
	}

	/**
	 * Append a bounded list of existing workspace files to the private delivery ZIP.
	 *
	 * @param string       $job_id        Clone job identifier.
	 * @param array        $relative_paths Workspace-relative files.
	 */
	public function append_delivery_archive_files( string $job_id, array $relative_paths ): bool {
		$root = $this->root_path( $job_id );
		if ( null === $root || array() === $relative_paths || ! $this->ensure_delivery_base() ) {
			return false;
		}

		$sources = array();
		foreach ( array_values( array_unique( $relative_paths ) ) as $relative ) {
			if ( ! is_string( $relative ) ) {
				return false;
			}

			$relative = $this->normalize_archive_relative( $relative );
			if ( '' === $relative ) {
				return false;
			}

			$absolute = trailingslashit( $root ) . $relative;
			if (
				! str_starts_with( $absolute, trailingslashit( $root ) )
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

		$partial = $this->delivery_partial_path( $job_id );
		// phpcs:ignore PHPCompatibility.Classes.NewClasses.pclzipFound -- PclZip is bundled by WordPress Core and loaded explicitly above.
		$archive = new \PclZip( $partial );
		$options = array(
			PCLZIP_OPT_REMOVE_PATH,
			untrailingslashit( $root ),
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
	 * Finalize the private delivery ZIP atomically.
	 *
	 * @param string $job_id Clone job identifier.
	 * @return array{path:string,bytes:int,sha256:string}|null
	 */
	public function finalize_delivery_archive( string $job_id ): ?array {
		if ( ! $this->valid_job_id( $job_id ) ) {
			return null;
		}

		$partial = $this->delivery_partial_path( $job_id );
		$final   = $this->delivery_final_path( $job_id );
		if ( ! is_file( $partial ) || ! is_readable( $partial ) ) {
			return null;
		}

		if ( is_file( $final ) ) {
			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged,WordPress.WP.AlternativeFunctions.unlink_unlink -- Removes only one stale job-owned final archive.
			@unlink( $final );
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename -- Atomic rename remains inside the private delivery directory.
		if ( ! rename( $partial, $final ) ) {
			return null;
		}

		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged,WordPress.WP.AlternativeFunctions.file_system_operations_chmod -- Best-effort restrictive private archive permissions.
		@chmod( $final, 0600 );

		return $this->delivery_archive_info( $job_id );
	}

	/**
	 * Return the finalized job-owned archive identity.
	 *
	 * @param string $job_id Clone job identifier.
	 * @return array{path:string,bytes:int,sha256:string}|null
	 */
	public function delivery_archive_info( string $job_id ): ?array {
		if ( ! $this->valid_job_id( $job_id ) ) {
			return null;
		}

		$path = $this->delivery_final_path( $job_id );
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
	 * Delete this job's private partial/final delivery archives.
	 *
	 * @param string $job_id Clone job identifier.
	 */
	public function delete_delivery_archive( string $job_id ): bool {
		if ( ! $this->valid_job_id( $job_id ) ) {
			return false;
		}

		$ok = true;
		foreach ( array( $this->delivery_partial_path( $job_id ), $this->delivery_final_path( $job_id ) ) as $path ) {
			if ( is_file( $path ) ) {
				// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged,WordPress.WP.AlternativeFunctions.unlink_unlink -- Removes only this job-owned private delivery archive.
				$ok = @unlink( $path ) && $ok;
			}
		}

		return $ok;
	}

	/**
	 * Return a safe public-facing download filename without exposing private storage.
	 *
	 * @param string $job_id Clone job identifier.
	 */
	public function delivery_download_name( string $job_id ): string {
		$name = sanitize_file_name( $job_id );
		if ( '' === $name ) {
			$name = substr( hash( 'sha256', $job_id ), 0, 24 );
		}

		return 'seo-geo-portable-clone-' . $name . '.zip';
	}

	/**
	 * Return the normalized private delivery directory.
	 */
	public function delivery_base_path(): string {
		return trailingslashit( wp_normalize_path( get_temp_dir() ) ) . self::DELIVERY_DIRECTORY_NAME;
	}

	/**
	 * Delete only the private workspace owned by one clone job.
	 *
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

	/**
	 * Return the normalized private export workspace base path.
	 */
	public function base_path(): string {
		return trailingslashit( wp_normalize_path( get_temp_dir() ) ) . self::DIRECTORY_NAME;
	}

	/**
	 * Resolve one bounded workspace path.
	 *
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
	 * Normalize one archive-relative workspace path.
	 *
	 * @param string $relative Relative path.
	 */
	private function normalize_archive_relative( string $relative ): string {
		$relative = ltrim( wp_normalize_path( trim( $relative ) ), '/' );
		if (
			'' === $relative
			|| 2048 < strlen( $relative )
			|| str_contains( $relative, '../' )
			|| str_contains( $relative, '/..' )
			|| ( str_starts_with( $relative, '.' ) && '.htaccess' !== $relative )
		) {
			return '';
		}

		return $relative;
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
	private function ensure_delivery_base(): bool {
		$base = $this->delivery_base_path();
		if ( ! is_dir( $base ) && ! wp_mkdir_p( $base ) ) {
			return false;
		}

		$this->protect_directory( $base );
		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged,WordPress.WP.AlternativeFunctions.file_system_operations_chmod -- Best-effort restrictive private delivery directory permissions.
		@chmod( $base, 0700 );

		return is_dir( $base );
	}

	/**
	 * Return a filesystem-safe private archive key.
	 *
	 * @param string $job_id Clone job identifier.
	 */
	private function delivery_archive_key( string $job_id ): string {
		return substr( hash( 'sha256', $job_id ), 0, 32 );
	}

	/**
	 * Return one deterministic private partial delivery archive path.
	 *
	 * @param string $job_id Clone job identifier.
	 */
	private function delivery_partial_path( string $job_id ): string {
		return trailingslashit( $this->delivery_base_path() ) . $this->delivery_archive_key( $job_id ) . '.zip.part';
	}

	/**
	 * Return one deterministic private final delivery archive path.
	 *
	 * @param string $job_id Clone job identifier.
	 */
	private function delivery_final_path( string $job_id ): string {
		return trailingslashit( $this->delivery_base_path() ) . $this->delivery_archive_key( $job_id ) . '.zip';
	}

	/**
	 * Normalize one workspace-relative path.
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
	 * Write defense-in-depth access guards for a generated directory.
	 *
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
	 * Validate one clone job identifier.
	 *
	 * @param string $job_id Clone job identifier.
	 */
	private function valid_job_id( string $job_id ): bool {
		return 1 === preg_match( '/^[A-Za-z0-9][A-Za-z0-9._:-]{7,127}$/', $job_id );
	}
}
