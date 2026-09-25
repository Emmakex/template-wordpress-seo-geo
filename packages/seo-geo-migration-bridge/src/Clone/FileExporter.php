<?php
/**
 * Portable Clone Engine resumable file exporter.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge\Clone;

/**
 * Copies accepted uploads/plugins/themes files into the private export workspace.
 */
final class FileExporter {
	public const MIN_FILE_BATCH = 5;
	public const MAX_FILE_BATCH = 200;
	public const DEFAULT_FILE_BATCH = 25;
	public const MAX_BATCH_BYTES = 33554432;
	private const MAX_PENDING_DIRECTORIES = 50000;
	private const MAX_ENTRY_OPERATIONS = 4000;

	private CloneWorkspace $workspace;

	public function __construct( ?CloneWorkspace $workspace = null ) {
		$this->workspace = $workspace ?? new CloneWorkspace();
	}

	/**
	 * Advance one bounded file-export batch.
	 *
	 * @param array<string,mixed> $state      Export state.
	 * @param array<string,mixed> $inventory  Completed source inventory.
	 * @param int                 $file_batch Maximum copied files this request.
	 * @return array<string,mixed>
	 */
	public function advance( array $state, array $inventory, int $file_batch = self::DEFAULT_FILE_BATCH ): array {
		$job_id = is_string( $state['job_id'] ?? null ) ? $state['job_id'] : '';
		$roots  = is_array( $inventory['roots'] ?? null ) ? $inventory['roots'] : array();
		if ( '' === $job_id || ! $this->workspace->prepare( $job_id ) || array() === $roots ) {
			return $this->block( $state, 'file-export-source-unavailable' );
		}

		$file_batch    = max( self::MIN_FILE_BATCH, min( self::MAX_FILE_BATCH, $file_batch ) );
		$copied_files  = 0;
		$copied_bytes  = 0;
		$operations    = 0;

		while (
			$copied_files < $file_batch
			&& $copied_bytes < self::MAX_BATCH_BYTES
			&& $operations < self::MAX_ENTRY_OPERATIONS
		) {
			$root_index = max( 0, (int) ( $state['file_root_index'] ?? 0 ) );
			if ( $root_index >= count( $roots ) ) {
				$state['phase'] = 'manifest';
				return $state;
			}

			$root = $roots[ $root_index ] ?? null;
			if ( ! is_array( $root ) || ! is_string( $root['id'] ?? null ) || ! is_string( $root['path'] ?? null ) ) {
				return $this->block( $state, 'file-export-root-invalid' );
			}

			$root_id = sanitize_key( $root['id'] );
			$base    = wp_normalize_path( $root['path'] );
			if ( '' === $root_id || '' === $base || ! is_dir( $base ) || ! is_readable( $base ) ) {
				return $this->block( $state, 'file-export-root-unreadable' );
			}

			$pending = is_array( $state['file_pending_dirs'] ?? null ) ? $state['file_pending_dirs'] : array();
			if ( '' === (string) ( $state['file_current_dir'] ?? '' ) && '' === (string) ( $state['file_after_name'] ?? '' ) ) {
				if ( array() === $pending ) {
					$state = $this->advance_root( $state );
					continue;
				}

				$state['file_current_dir']  = (string) array_shift( $pending );
				$state['file_pending_dirs'] = $pending;
			}

			$current_dir = (string) ( $state['file_current_dir'] ?? '' );
			$absolute    = $this->join_path( $base, $current_dir );
			$entries     = scandir( $absolute, SCANDIR_SORT_ASCENDING );
			if ( false === $entries ) {
				return $this->block( $state, 'file-export-directory-unreadable' );
			}

			$after_name   = (string) ( $state['file_after_name'] ?? '' );
			$dir_finished = true;

			foreach ( $entries as $entry ) {
				if ( '.' === $entry || '..' === $entry || ( '' !== $after_name && strcmp( $entry, $after_name ) <= 0 ) ) {
					continue;
				}

				++$operations;
				$relative = '' === $current_dir ? $entry : $current_dir . '/' . $entry;
				$source   = $this->join_path( $base, $relative );

				if ( $this->excluded( $relative, is_dir( $source ) ) || is_link( $source ) ) {
					$state['file_after_name'] = $entry;
					continue;
				}

				if ( is_dir( $source ) ) {
					$pending = is_array( $state['file_pending_dirs'] ?? null ) ? $state['file_pending_dirs'] : array();
					if ( count( $pending ) >= self::MAX_PENDING_DIRECTORIES ) {
						return $this->block( $state, 'file-export-directory-queue-limit' );
					}
					$pending[]                  = $relative;
					$state['file_pending_dirs'] = $pending;
					$state['file_after_name']   = $entry;
					if ( $operations >= self::MAX_ENTRY_OPERATIONS ) {
						$dir_finished = false;
						break;
					}
					continue;
				}

				if ( ! is_file( $source ) || ! is_readable( $source ) ) {
					return $this->block( $state, 'file-export-source-unreadable' );
				}

				$size       = filesize( $source );
				$source_sha = hash_file( 'sha256', $source );
				if ( false === $size || false === $source_sha ) {
					return $this->block( $state, 'file-export-source-hash-failed' );
				}

				$destination = $this->workspace->files_dir( $job_id ) . $root_id . '/' . ltrim( wp_normalize_path( $relative ), '/' );
				$destination_dir = dirname( $destination );
				if ( ! is_dir( $destination_dir ) && ! wp_mkdir_p( $destination_dir ) ) {
					return $this->block( $state, 'file-export-destination-unavailable' );
				}

				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_copy -- Private migration payload requires byte-for-byte local copy.
				if ( ! copy( $source, $destination ) ) {
					return $this->block( $state, 'file-export-copy-failed' );
				}

				$destination_sha = hash_file( 'sha256', $destination );
				if ( false === $destination_sha || $source_sha !== $destination_sha ) {
					return $this->block( $state, 'file-export-integrity-mismatch' );
				}

				$metadata = array(
					'root'          => $root_id,
					'relative_path' => wp_normalize_path( $relative ),
					'bytes'         => $size,
					'sha256'        => $destination_sha,
				);
				if ( ! $this->write_json_atomic( $this->workspace->file_metadata_path( $job_id, $root_id, $relative ), $metadata ) ) {
					return $this->block( $state, 'file-export-metadata-write-failed' );
				}

				$state['files_exported']      = (int) ( $state['files_exported'] ?? 0 ) + 1;
				$state['file_bytes_exported'] = (int) ( $state['file_bytes_exported'] ?? 0 ) + $size;
				$state['file_after_name']     = $entry;
				++$copied_files;
				$copied_bytes += $size;

				if (
					$copied_files >= $file_batch
					|| $copied_bytes >= self::MAX_BATCH_BYTES
					|| $operations >= self::MAX_ENTRY_OPERATIONS
				) {
					$dir_finished = false;
					break;
				}
			}

			if ( $dir_finished ) {
				$state['file_current_dir'] = '';
				$state['file_after_name']  = '';
			}
		}

		return $state;
	}

	/**
	 * Move to the next accepted source root.
	 *
	 * @param array<string,mixed> $state Export state.
	 * @return array<string,mixed>
	 */
	private function advance_root( array $state ): array {
		$state['file_root_index']   = (int) ( $state['file_root_index'] ?? 0 ) + 1;
		$state['file_pending_dirs'] = array( '' );
		$state['file_current_dir']  = '';
		$state['file_after_name']   = '';
		return $state;
	}

	/**
	 * Match the accepted inventory exclusion policy.
	 */
	private function excluded( string $relative, bool $is_dir ): bool {
		$normalized = strtolower( wp_normalize_path( $relative ) );
		$segments   = array_values(
			array_filter(
				explode( '/', $normalized ),
				static fn( string $segment ): bool => '' !== $segment
			)
		);

		if (
			$is_dir
			&& array_intersect(
				$segments,
				array(
					'.git',
					'.svn',
					'cache',
					'caches',
					'tmp',
					'temp',
					'logs',
					'log',
					'updraft',
					'ai1wm-backups',
					'wp-staging',
					'wpo-cache',
					'litespeed',
					'backup',
					'backups',
				)
			)
		) {
			return true;
		}

		$basename = basename( $normalized );
		return ! $is_dir && (
			'.ds_store' === $basename
			|| str_ends_with( $basename, '.log' )
			|| str_ends_with( $basename, '.tmp' )
		);
	}

	private function join_path( string $base, string $relative ): string {
		return rtrim( wp_normalize_path( $base ), '/' )
			. ( '' === $relative ? '' : '/' . ltrim( wp_normalize_path( $relative ), '/' ) );
	}

	/**
	 * Write bounded JSON metadata atomically.
	 *
	 * @param string              $path    Destination path.
	 * @param array<string,mixed> $payload Metadata.
	 */
	private function write_json_atomic( string $path, array $payload ): bool {
		$json = wp_json_encode( $payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		if ( ! is_string( $json ) ) {
			return false;
		}

		$tmp = $path . '.tmp';
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Private local migration metadata requires atomic writes.
		$bytes = file_put_contents( $tmp, $json . "\n", LOCK_EX );
		if ( false === $bytes || $bytes !== strlen( $json ) + 1 ) {
			return false;
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename -- Atomic replacement inside the private migration workspace.
		return rename( $tmp, $path );
	}

	/**
	 * Add one bounded terminal blocker.
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
