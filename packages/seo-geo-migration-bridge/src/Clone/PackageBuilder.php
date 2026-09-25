<?php
/**
 * Portable Clone Engine resumable package builder.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge\Clone;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ZipArchive;

/**
 * Builds a deterministic-entry ZIP from an already-complete private workspace.
 */
final class PackageBuilder {
	public const MIN_ENTRY_BATCH = 10;
	public const MAX_ENTRY_BATCH = 500;
	public const DEFAULT_ENTRY_BATCH = 100;

	private CloneWorkspace $workspace;

	public function __construct( ?CloneWorkspace $workspace = null ) {
		$this->workspace = $workspace ?? new CloneWorkspace();
	}

	/**
	 * Advance one bounded archive batch.
	 *
	 * @param array<string,mixed> $state       Export state.
	 * @param int                 $entry_batch Maximum package entries this request.
	 * @return array<string,mixed>
	 */
	public function advance( array $state, int $entry_batch = self::DEFAULT_ENTRY_BATCH ): array {
		$job_id = is_string( $state['job_id'] ?? null ) ? $state['job_id'] : '';
		if ( '' === $job_id || ! $this->workspace->private_root() || ! class_exists( ZipArchive::class ) ) {
			return $this->block( $state, 'ziparchive-unavailable-or-workspace-unsafe' );
		}

		$job_dir = $this->workspace->job_dir( $job_id );
		if ( ! is_dir( $job_dir ) || ! is_file( $this->workspace->manifest_path( $job_id ) ) ) {
			return $this->block( $state, 'package-source-incomplete' );
		}

		$entry_batch = max( self::MIN_ENTRY_BATCH, min( self::MAX_ENTRY_BATCH, $entry_batch ) );
		$entries     = $this->entries( $job_dir );
		$cursor      = is_string( $state['package_cursor'] ?? null ) ? $state['package_cursor'] : '';
		$remaining   = array_values(
			array_filter(
				$entries,
				static fn( string $relative ): bool => '' === $cursor || strcmp( $relative, $cursor ) > 0
			)
		);

		if ( array() === $remaining ) {
			return $this->complete( $state );
		}

		$batch        = array_slice( $remaining, 0, $entry_batch );
		$package_path = $this->workspace->package_path( $job_id );
		$zip          = new ZipArchive();
		$open_flags   = is_file( $package_path ) ? 0 : ZipArchive::CREATE;
		$opened       = $zip->open( $package_path, $open_flags );
		if ( true !== $opened ) {
			return $this->block( $state, 'package-open-failed' );
		}

		foreach ( $batch as $relative ) {
			$source      = $job_dir . $relative;
			$archive_key = 'seo-geo-clone/' . $relative;
			$existing    = $zip->locateName( $archive_key );
			if ( false !== $existing ) {
				$zip->deleteName( $archive_key );
			}

			if ( ! $zip->addFile( $source, $archive_key ) ) {
				$zip->close();
				return $this->block( $state, 'package-entry-add-failed' );
			}
			$state['package_cursor']  = $relative;
			$state['package_entries'] = (int) ( $state['package_entries'] ?? 0 ) + 1;
		}

		if ( ! $zip->close() ) {
			return $this->block( $state, 'package-close-failed' );
		}

		if ( count( $batch ) < $entry_batch || count( $remaining ) === count( $batch ) ) {
			return $this->complete( $state );
		}

		return $state;
	}

	/**
	 * Return sorted relative regular files under the job workspace.
	 *
	 * @return list<string>
	 */
	private function entries( string $job_dir ): array {
		$job_dir  = trailingslashit( wp_normalize_path( $job_dir ) );
		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $job_dir, FilesystemIterator::SKIP_DOTS )
		);
		$entries = array();

		foreach ( $iterator as $item ) {
			if ( ! $item->isFile() || $item->isLink() ) {
				continue;
			}

			$path = wp_normalize_path( $item->getPathname() );
			if ( ! str_starts_with( $path, $job_dir ) ) {
				continue;
			}
			$entries[] = substr( $path, strlen( $job_dir ) );
		}

		sort( $entries, SORT_STRING );
		return $entries;
	}

	/**
	 * Finalize package checksum and completed state.
	 *
	 * @param array<string,mixed> $state Export state.
	 * @return array<string,mixed>
	 */
	private function complete( array $state ): array {
		$job_id       = is_string( $state['job_id'] ?? null ) ? $state['job_id'] : '';
		$package_path = $this->workspace->package_path( $job_id );
		if ( '' === $job_id || ! is_file( $package_path ) ) {
			return $this->block( $state, 'package-file-missing' );
		}

		$sha  = hash_file( 'sha256', $package_path );
		$size = filesize( $package_path );
		if ( false === $sha || false === $size ) {
			return $this->block( $state, 'package-integrity-failed' );
		}

		$checksum = $sha . '  ' . basename( $package_path ) . "\n";
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Private checksum sidecar for authenticated migration package.
		if ( false === file_put_contents( $this->workspace->checksum_path( $job_id ), $checksum, LOCK_EX ) ) {
			return $this->block( $state, 'package-checksum-write-failed' );
		}

		$state['package_sha256'] = $sha;
		$state['package_bytes']  = $size;
		$state['phase']          = 'completed';
		$state['status']         = 'complete';
		$state['completed_at']   = gmdate( DATE_ATOM );
		return $state;
	}

	/**
	 * Add one terminal blocker.
	 *
	 * @param array<string,mixed> $state Export state.
	 * @return array<string,mixed>
	 */
	private function block( array $state, string $code ): array {
		$blockers          = is_array( $state['blockers'] ?? null ) ? $state['blockers'] : array();
		$blockers[]        = $code;
		$state['blockers'] = array_values( array_unique( $blockers ) );
		$state['phase']    = 'blocked';
		$state['status']   = 'blocked';
		return $state;
	}
}
