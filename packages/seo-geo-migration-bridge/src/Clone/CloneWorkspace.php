<?php
/**
 * Portable Clone Engine private export workspace.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge\Clone;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Owns private temporary paths used by resumable export jobs.
 */
final class CloneWorkspace {
	/**
	 * Optional absolute private workspace override.
	 */
	public const ROOT_MARKER = 'SEO_GEO_MIGRATION_PACKAGE_DIR';

	/**
	 * Return the configured private workspace root.
	 */
	public function root(): string {
		$configured = defined( self::ROOT_MARKER ) ? constant( self::ROOT_MARKER ) : null;
		$base       = is_string( $configured ) && '' !== trim( $configured )
			? trim( $configured )
			: get_temp_dir() . 'seo-geo-migration-bridge-private';

		return trailingslashit( wp_normalize_path( $base ) );
	}

	/**
	 * Return whether the workspace is outside the public WordPress root.
	 */
	public function private_root(): bool {
		$root    = $this->root();
		$public  = trailingslashit( wp_normalize_path( ABSPATH ) );

		return '' !== $root && ! str_starts_with( $root, $public );
	}

	/**
	 * Prepare a private job workspace.
	 *
	 * @param string $job_id Clone job identifier.
	 */
	public function prepare( string $job_id ): bool {
		if ( ! $this->valid_job_id( $job_id ) || ! $this->private_root() ) {
			return false;
		}

		foreach (
			array(
				$this->job_dir( $job_id ),
				$this->database_dir( $job_id ),
				$this->files_dir( $job_id ),
				$this->database_metadata_dir( $job_id ),
				$this->file_metadata_dir( $job_id ),
				$this->packages_dir(),
			) as $directory
		) {
			if ( ! is_dir( $directory ) && ! wp_mkdir_p( $directory ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Return the private directory for one job.
	 */
	public function job_dir( string $job_id ): string {
		return $this->root() . 'jobs/' . $job_id . '/';
	}

	/**
	 * Return database payload directory.
	 */
	public function database_dir( string $job_id ): string {
		return $this->job_dir( $job_id ) . 'payload/database/';
	}

	/**
	 * Return file payload directory.
	 */
	public function files_dir( string $job_id ): string {
		return $this->job_dir( $job_id ) . 'payload/files/';
	}

	/**
	 * Return database metadata directory.
	 */
	public function database_metadata_dir( string $job_id ): string {
		return $this->job_dir( $job_id ) . 'metadata/database/';
	}

	/**
	 * Return file metadata directory.
	 */
	public function file_metadata_dir( string $job_id ): string {
		return $this->job_dir( $job_id ) . 'metadata/files/';
	}

	/**
	 * Return final manifest path.
	 */
	public function manifest_path( string $job_id ): string {
		return $this->job_dir( $job_id ) . 'manifest.json';
	}

	/**
	 * Return final package directory.
	 */
	public function packages_dir(): string {
		return $this->root() . 'packages/';
	}

	/**
	 * Return final ZIP package path.
	 */
	public function package_path( string $job_id ): string {
		return $this->packages_dir() . 'seo-geo-clone-' . $job_id . '.zip';
	}

	/**
	 * Return final checksum sidecar path.
	 */
	public function checksum_path( string $job_id ): string {
		return $this->package_path( $job_id ) . '.sha256';
	}

	/**
	 * Return a deterministic safe table payload directory.
	 *
	 * @param string $job_id Clone job identifier.
	 * @param string $table  Database table name.
	 */
	public function table_dir( string $job_id, string $table ): string {
		return $this->database_dir( $job_id ) . hash( 'sha256', $table ) . '/';
	}

	/**
	 * Return a deterministic metadata path for one copied file.
	 *
	 * @param string $job_id   Clone job identifier.
	 * @param string $root_id  Payload root identifier.
	 * @param string $relative Relative source path.
	 */
	public function file_metadata_path( string $job_id, string $root_id, string $relative ): string {
		return $this->file_metadata_dir( $job_id ) . hash( 'sha256', $root_id . '|' . $relative ) . '.json';
	}

	/**
	 * Remove private payload/package material owned by one job.
	 *
	 * @param string $job_id Clone job identifier.
	 */
	public function cleanup( string $job_id ): bool {
		if ( ! $this->valid_job_id( $job_id ) || ! $this->private_root() ) {
			return false;
		}

		$ok = $this->delete_tree( $this->job_dir( $job_id ) );
		foreach ( array( $this->package_path( $job_id ), $this->checksum_path( $job_id ) ) as $path ) {
			if ( is_file( $path ) ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Private local migration artifact cleanup requires exact filesystem removal.
				$ok = unlink( $path ) && $ok;
			}
		}

		return $ok;
	}

	/**
	 * Delete one directory tree only under the private workspace root.
	 *
	 * @param string $directory Directory to remove.
	 */
	private function delete_tree( string $directory ): bool {
		$root      = $this->root();
		$directory = trailingslashit( wp_normalize_path( $directory ) );
		if ( ! str_starts_with( $directory, $root ) || $directory === $root || ! is_dir( $directory ) ) {
			return ! is_dir( $directory );
		}

		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $directory, FilesystemIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::CHILD_FIRST
		);

		$ok = true;
		foreach ( $iterator as $item ) {
			$path = wp_normalize_path( $item->getPathname() );
			if ( ! str_starts_with( $path, $root ) ) {
				return false;
			}

			if ( $item->isDir() ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- Private local migration artifact cleanup.
				$ok = rmdir( $path ) && $ok;
			} else {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Private local migration artifact cleanup.
				$ok = unlink( $path ) && $ok;
			}
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- Private local migration artifact cleanup.
		return rmdir( untrailingslashit( $directory ) ) && $ok;
	}

	/**
	 * Validate one bounded clone job identifier.
	 */
	private function valid_job_id( string $job_id ): bool {
		return 1 === preg_match( '/^[A-Za-z0-9][A-Za-z0-9._:-]{7,127}$/', $job_id );
	}
}
