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
	public const DIRECTORY_NAME                   = 'seo-geo-migration-bridge';
	public const DELIVERY_DIRECTORY_NAME          = 'seo-geo-migration-bridge-delivery';
	public const DESTINATION_STAGE_DIRECTORY_NAME = 'seo-geo-migration-stage';

	private const DESTINATION_STAGE_ROOT_IDS = array( 'uploads', 'plugins', 'themes' );

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
	 * @param string $job_id        Clone job identifier.
	 * @param array  $relative_paths Workspace-relative files.
	 * @phpstan-param list<string> $relative_paths
	 */
	public function append_delivery_archive_files( string $job_id, array $relative_paths ): bool {
		$root = $this->root_path( $job_id );
		if ( null === $root || array() === $relative_paths || ! $this->ensure_delivery_base() ) {
			return false;
		}

		$sources = array();
		foreach ( array_values( array_unique( $relative_paths ) ) as $relative ) {
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

		$remove_path_option = defined( 'PCLZIP_OPT_REMOVE_PATH' )
			? constant( 'PCLZIP_OPT_REMOVE_PATH' )
			: null;
		if ( ! is_int( $remove_path_option ) ) {
			return false;
		}

		$method = is_file( $partial ) && 0 < (int) filesize( $partial ) ? 'add' : 'create';
		$args   = array(
			$sources,
			$remove_path_option,
			untrailingslashit( $root ),
		);
		$result = call_user_func_array( array( $archive, $method ), $args );

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
	 * Stage one uploaded/imported Portable Clone ZIP inside the job-owned private workspace.
	 *
	 * @param string $job_id Clone job identifier.
	 * @param string $source Absolute readable source ZIP path.
	 * @return array{path:string,bytes:int,sha256:string}|null
	 */
	public function stage_import_archive( string $job_id, string $source ): ?array {
		if ( ! $this->delete_import_archive( $job_id ) ) {
			return null;
		}

		$written = $this->copy_file( $job_id, 'import/package.zip', $source );
		if ( null === $written ) {
			return null;
		}

		$info = $this->import_archive_info( $job_id );
		if (
			null === $info
			|| (int) $written['bytes'] !== (int) $info['bytes']
			|| ! hash_equals( (string) $written['sha256'], (string) $info['sha256'] )
		) {
			$this->delete_import_archive( $job_id );
			return null;
		}

		return $info;
	}

	/**
	 * Return the staged private import archive identity.
	 *
	 * @param string $job_id Clone job identifier.
	 * @return array{path:string,bytes:int,sha256:string}|null
	 */
	public function import_archive_info( string $job_id ): ?array {
		$path = $this->absolute_path( $job_id, 'import/package.zip', false );
		if ( null === $path || ! is_file( $path ) || ! is_readable( $path ) || is_link( $path ) ) {
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
	 * Remove only the staged import ZIP for one clone job.
	 *
	 * @param string $job_id Clone job identifier.
	 */
	public function delete_import_archive( string $job_id ): bool {
		$path = $this->absolute_path( $job_id, 'import/package.zip', false );
		if ( null === $path || ! is_file( $path ) ) {
			return true;
		}

		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged,WordPress.WP.AlternativeFunctions.unlink_unlink -- Removes only the job-owned private staged import ZIP.
		return @unlink( $path );
	}

	/**
	 * Prepare one empty job-owned private extraction root.
	 *
	 * The parent import directory is already protected by ExportWorkspace. This
	 * child intentionally receives no generated guard files because those would
	 * alter the Portable Clone package checksum replay.
	 *
	 * @param string $job_id Clone job identifier.
	 */
	public function prepare_import_extraction( string $job_id ): ?string {
		$root = $this->ensure( $job_id );
		if ( null === $root ) {
			return null;
		}

		$path = trailingslashit( $root ) . 'import/extracted';
		if ( is_dir( $path ) ) {
			$remaining = new FilesystemIterator( $path, FilesystemIterator::SKIP_DOTS );
			if ( $remaining->valid() ) {
				return null;
			}
		} elseif ( ! wp_mkdir_p( $path ) ) {
			return null;
		}

		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged,WordPress.WP.AlternativeFunctions.file_system_operations_chmod -- Best-effort restrictive job-owned extraction directory permissions.
		@chmod( $path, 0700 );

		return trailingslashit( wp_normalize_path( $path ) );
	}

	/**
	 * Return one existing job-owned private extraction root.
	 *
	 * @param string $job_id Clone job identifier.
	 */
	public function import_extraction_root( string $job_id ): ?string {
		$root = $this->root_path( $job_id );
		if ( null === $root ) {
			return null;
		}

		$path = trailingslashit( $root ) . 'import/extracted/';
		return is_dir( $path ) && str_starts_with( $path, trailingslashit( $root ) )
			? wp_normalize_path( $path )
			: null;
	}

	/**
	 * Return bounded archive-entry metadata for the staged Portable Clone ZIP.
	 *
	 * @param string $job_id Clone job identifier.
	 * @return list<array{name:string,size:int,folder:bool}>
	 */
	public function import_archive_entries( string $job_id ): array {
		$info = $this->import_archive_info( $job_id );
		if ( null === $info || ! $this->load_pclzip() ) {
			return array();
		}

		// phpcs:ignore PHPCompatibility.Classes.NewClasses.pclzipFound -- WordPress Core PclZip is loaded explicitly above.
		$archive = new \PclZip( $info['path'] );
		$list    = $archive->listContent();
		if ( ! is_array( $list ) ) {
			return array();
		}

		$entries = array();
		foreach ( $list as $entry ) {
			if ( ! is_array( $entry ) || ! is_string( $entry['filename'] ?? null ) ) {
				return array();
			}

			$name      = (string) $entry['filename'];
			$canonical = rtrim( $name, '/' );
			if ( '' === $canonical || '' === $this->normalize_archive_relative( $canonical ) ) {
				return array();
			}

			$entries[] = array(
				'name'   => $name,
				'size'   => max( 0, (int) ( $entry['size'] ?? 0 ) ),
				'folder' => true === ( $entry['folder'] ?? false ) || str_ends_with( $name, '/' ),
			);
		}

		return $entries;
	}

	/**
	 * Extract one already-preflighted archive file into the private import root.
	 *
	 * @param string $job_id Clone job identifier.
	 * @param string $name   Exact archive-relative file path.
	 * @return array{path:string,bytes:int,sha256:string}|null
	 */
	public function extract_import_archive_entry( string $job_id, string $name ): ?array {
		$relative = $this->normalize_archive_relative( $name );
		$archive  = $this->import_archive_info( $job_id );
		$root     = $this->import_extraction_root( $job_id );
		if (
			'' === $relative
			|| str_ends_with( $relative, '/' )
			|| null === $archive
			|| null === $root
			|| ! $this->load_pclzip()
		) {
			return null;
		}

		$by_name = defined( 'PCLZIP_OPT_BY_NAME' ) ? constant( 'PCLZIP_OPT_BY_NAME' ) : null;
		$path    = defined( 'PCLZIP_OPT_PATH' ) ? constant( 'PCLZIP_OPT_PATH' ) : null;
		if ( ! is_int( $by_name ) || ! is_int( $path ) ) {
			return null;
		}

		// phpcs:ignore PHPCompatibility.Classes.NewClasses.pclzipFound -- WordPress Core PclZip is loaded explicitly above.
		$zip = new \PclZip( $archive['path'] );
		// WordPress Core PclZip exposes variadic extraction options not represented by the static stub.
		$result = $zip->extract( $by_name, $name, $path, untrailingslashit( $root ) ); // @phpstan-ignore arguments.count
		if ( ! is_array( $result ) || array() === $result ) {
			return null;
		}

		$absolute = trailingslashit( $root ) . $relative;
		$absolute = wp_normalize_path( $absolute );
		if (
			! str_starts_with( $absolute, trailingslashit( wp_normalize_path( $root ) ) )
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

		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged,WordPress.WP.AlternativeFunctions.file_system_operations_chmod -- Best-effort restrictive extracted payload permissions.
		@chmod( $absolute, 0600 );

		return array(
			'path'   => $relative,
			'bytes'  => (int) $bytes,
			'sha256' => $hash,
		);
	}

	/**
	 * Return one extracted private payload file identity.
	 *
	 * @param string $job_id   Clone job identifier.
	 * @param string $relative Package-relative file path.
	 * @return array{path:string,bytes:int,sha256:string}|null
	 */
	public function import_extracted_file_info( string $job_id, string $relative ): ?array {
		$relative = $this->normalize_archive_relative( $relative );
		$root     = $this->import_extraction_root( $job_id );
		if ( '' === $relative || null === $root ) {
			return null;
		}

		$absolute = wp_normalize_path( trailingslashit( $root ) . $relative );
		if (
			! str_starts_with( $absolute, trailingslashit( wp_normalize_path( $root ) ) )
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
	 * Read one already-verified extracted import payload file.
	 *
	 * @param string $job_id   Clone job identifier.
	 * @param string $relative Package-relative file path.
	 */
	public function read_import_extracted_file( string $job_id, string $relative ): ?string {
		$info = $this->import_extracted_file_info( $job_id, $relative );
		if ( null === $info ) {
			return null;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reads only a preflighted, job-owned private extracted payload path.
		$content = file_get_contents( $info['path'] );

		return false === $content ? null : $content;
	}

	/**
	 * Ensure one protected job-owned destination staging root.
	 *
	 * @param string $job_id Clone job identifier.
	 */
	public function destination_staging_ensure( string $job_id ): ?string {
		if ( ! $this->valid_job_id( $job_id ) ) {
			return null;
		}

		$base = $this->destination_staging_base_path();
		if ( ! is_dir( $base ) && ! wp_mkdir_p( $base ) ) {
			return null;
		}
		$this->protect_directory( $base );

		$root = trailingslashit( $base ) . $this->destination_staging_root_key( $job_id ) . '/';
		if ( ! is_dir( $root ) && ! wp_mkdir_p( $root ) ) {
			return null;
		}
		$this->protect_directory( $root );

		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged,WordPress.WP.AlternativeFunctions.file_system_operations_chmod -- Best-effort restrictive destination staging permissions.
		@chmod( $root, 0700 );

		return trailingslashit( wp_normalize_path( $root ) );
	}

	/**
	 * Return one existing destination staging root.
	 *
	 * @param string $job_id Clone job identifier.
	 */
	public function destination_staging_root_path( string $job_id ): ?string {
		if ( ! $this->valid_job_id( $job_id ) ) {
			return null;
		}

		$root = trailingslashit( $this->destination_staging_base_path() ) . $this->destination_staging_root_key( $job_id ) . '/';

		return is_dir( $root ) ? trailingslashit( wp_normalize_path( $root ) ) : null;
	}

	/**
	 * Return deterministic destination staging key.
	 *
	 * @param string $job_id Clone job identifier.
	 */
	public function destination_staging_root_key( string $job_id ): string {
		return $this->valid_job_id( $job_id ) ? substr( hash( 'sha256', $job_id ), 0, 32 ) : '';
	}

	/**
	 * Stage one verified import file atomically into destination staging.
	 *
	 * @param string $job_id         Clone job identifier.
	 * @param string $root_id        uploads/plugins/themes.
	 * @param string $relative       Root-relative payload path.
	 * @param string $source         Absolute verified extracted source path.
	 * @param int    $expected_bytes Expected bytes.
	 * @param string $expected_hash  Expected SHA-256.
	 * @return array{path:string,bytes:int,sha256:string}|null
	 */
	public function destination_stage_file(
		string $job_id,
		string $root_id,
		string $relative,
		string $source,
		int $expected_bytes,
		string $expected_hash
	): ?array {
		$relative = $this->normalize_destination_staging_relative( $relative );
		$root     = $this->destination_staging_ensure( $job_id );
		if (
			null === $root
			|| ! $this->valid_destination_stage_root_id( $root_id )
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
			$existing = $this->destination_staged_file_info( $job_id, $root_id, $relative );
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
			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged,WordPress.WP.AlternativeFunctions.unlink_unlink -- Removes only deterministic job-owned partial destination staging file.
			@unlink( $temp );
		}

		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged,WordPress.WP.AlternativeFunctions.file_system_operations_copy -- Copies only verified private payload into isolated job-owned destination staging.
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
			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged,WordPress.WP.AlternativeFunctions.unlink_unlink -- Removes only failed job-owned partial destination staging file.
			@unlink( $temp );
			return null;
		}

		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged,WordPress.WP.AlternativeFunctions.rename_rename -- Atomic promotion remains inside one job-owned destination staging directory.
		if ( ! @rename( $temp, $destination ) ) {
			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged,WordPress.WP.AlternativeFunctions.unlink_unlink -- Removes only failed job-owned partial destination staging file.
			@unlink( $temp );
			return null;
		}

		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged,WordPress.WP.AlternativeFunctions.file_system_operations_chmod -- Best-effort restrictive destination staging file permissions.
		@chmod( $destination, 0600 );

		return $this->destination_staged_file_info( $job_id, $root_id, $relative );
	}

	/**
	 * Return one destination-staged file identity.
	 *
	 * @param string $job_id   Clone job identifier.
	 * @param string $root_id  uploads/plugins/themes.
	 * @param string $relative Root-relative file path.
	 * @return array{path:string,bytes:int,sha256:string}|null
	 */
	public function destination_staged_file_info( string $job_id, string $root_id, string $relative ): ?array {
		$relative = $this->normalize_destination_staging_relative( $relative );
		$root     = $this->destination_staging_root_path( $job_id );
		if ( null === $root || ! $this->valid_destination_stage_root_id( $root_id ) || '' === $relative ) {
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
	 * Confirm one new destination staging job has no staged payload files.
	 *
	 * @param string $job_id Clone job identifier.
	 */
	public function destination_staging_payload_empty( string $job_id ): bool {
		$root = $this->destination_staging_ensure( $job_id );
		if ( null === $root ) {
			return false;
		}

		foreach ( self::DESTINATION_STAGE_ROOT_IDS as $root_id ) {
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
	 * Summarize all destination-staged payload files.
	 *
	 * @param string $job_id Clone job identifier.
	 * @return array{files:int,bytes:int}|null
	 */
	public function destination_staging_payload_summary( string $job_id ): ?array {
		$root = $this->destination_staging_root_path( $job_id );
		if ( null === $root ) {
			return null;
		}

		$files = 0;
		$bytes = 0;
		foreach ( self::DESTINATION_STAGE_ROOT_IDS as $root_id ) {
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
			if (
				in_array( $entry, array( '.', '..', '.htaccess', 'index.php' ), true )
				|| in_array( $entry, self::DESTINATION_STAGE_ROOT_IDS, true )
			) {
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
	 * Delete one job-owned destination staging tree.
	 *
	 * @param string $job_id Clone job identifier.
	 */
	public function destination_staging_cleanup( string $job_id ): bool {
		$root = $this->destination_staging_root_path( $job_id );
		$base = trailingslashit( $this->destination_staging_base_path() );
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
	 * Return normalized destination staging base path.
	 */
	public function destination_staging_base_path(): string {
		return trailingslashit( wp_normalize_path( WP_CONTENT_DIR ) ) . self::DESTINATION_STAGE_DIRECTORY_NAME . '/';
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
	 * Normalize one destination staging root-relative path.
	 *
	 * @param string $relative Relative path.
	 */
	private function normalize_destination_staging_relative( string $relative ): string {
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
	 * Validate one destination staging root identifier.
	 *
	 * @param string $root_id Root identifier.
	 */
	private function valid_destination_stage_root_id( string $root_id ): bool {
		return in_array( $root_id, self::DESTINATION_STAGE_ROOT_IDS, true );
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
