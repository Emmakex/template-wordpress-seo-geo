<?php
/**
 * Portable Clone resumable read-only file exporter.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge\Clone;

/**
 * Copies accepted uploads/plugins/themes into the private export workspace.
 */
final class FileExporter {
	public const MIN_BATCH_FILES     = 1;
	public const MAX_BATCH_FILES     = 200;
	public const DEFAULT_BATCH_FILES = 25;
	public const MIN_BATCH_BYTES     = 1048576;
	public const MAX_BATCH_BYTES     = 67108864;
	public const DEFAULT_BATCH_BYTES = 8388608;

	private const MAX_PENDING_DIRECTORIES = 50000;
	private const MAX_ENTRY_OPERATIONS    = 4000;

	/**
	 * Resumable file-export state store.
	 *
	 * @var FileExportStateStore
	 */
	private FileExportStateStore $store;

	/**
	 * Completed source inventory store.
	 *
	 * @var CloneInventoryStore
	 */
	private CloneInventoryStore $inventory;

	/**
	 * Database export state dependency.
	 *
	 * @var ExportStateStore
	 */
	private ExportStateStore $database_export;

	/**
	 * Portable Clone job store.
	 *
	 * @var CloneJobStore
	 */
	private CloneJobStore $jobs;

	/**
	 * Private payload workspace.
	 *
	 * @var ExportWorkspace
	 */
	private ExportWorkspace $workspace;

	/**
	 * Construct the resumable file exporter.
	 *
	 * @param FileExportStateStore|null $store           Optional file-export state store.
	 * @param CloneInventoryStore|null  $inventory       Optional source inventory store.
	 * @param ExportStateStore|null     $database_export Optional database-export state store.
	 * @param CloneJobStore|null        $jobs            Optional clone job store.
	 * @param ExportWorkspace|null      $workspace       Optional private export workspace.
	 */
	public function __construct(
		?FileExportStateStore $store = null,
		?CloneInventoryStore $inventory = null,
		?ExportStateStore $database_export = null,
		?CloneJobStore $jobs = null,
		?ExportWorkspace $workspace = null
	) {
		$this->store           = $store ?? new FileExportStateStore();
		$this->inventory       = $inventory ?? new CloneInventoryStore();
		$this->database_export = $database_export ?? new ExportStateStore();
		$this->jobs            = $jobs ?? new CloneJobStore();
		$this->workspace       = $workspace ?? new ExportWorkspace();
	}

	/**
	 * Return one normalized file-export state.
	 *
	 * @param string $job_id Clone job identifier.
	 * @return array<string,mixed>|null
	 */
	public function snapshot( string $job_id ): ?array {
		return $this->store->get( $job_id );
	}

	/**
	 * Start file export only after inventory and database export complete.
	 *
	 * @param string $job_id Clone job identifier.
	 * @return array<string,mixed>|null
	 */
	public function start( string $job_id ): ?array {
		$job       = $this->jobs->get( $job_id );
		$inventory = $this->inventory->get( $job_id );
		$database  = $this->database_export->get( $job_id );
		if (
			! is_array( $job )
			|| ! in_array( $job['operation'] ?? null, array( 'local-clone', 'export' ), true )
			|| ! is_array( $inventory )
			|| 'complete' !== ( $inventory['status'] ?? null )
			|| ! is_array( $database )
			|| 'complete' !== ( $database['status'] ?? null )
		) {
			return null;
		}

		$existing = $this->store->get( $job_id );
		if ( is_array( $existing ) ) {
			return $existing;
		}

		$roots = is_array( $inventory['roots'] ?? null ) ? array_values( $inventory['roots'] ) : array();
		if ( array() === $roots || null === $this->workspace->ensure( $job_id ) ) {
			return null;
		}

		$now   = gmdate( DATE_ATOM );
		$state = array(
			'schema_version'       => FileExportStateStore::SCHEMA_VERSION,
			'job_id'               => $job_id,
			'status'               => 'running',
			'root_index'           => 0,
			'root_count'           => count( $roots ),
			'pending_dirs'         => array( '' ),
			'current_dir'          => '',
			'after_name'           => '',
			'file_count'           => 0,
			'byte_count'           => 0,
			'inventory_file_count' => (int) ( $inventory['file_count'] ?? 0 ),
			'inventory_byte_count' => (int) ( $inventory['byte_count'] ?? 0 ),
			'export_fingerprint'   => $this->database_fingerprint_prefix( $inventory ),
			'files_manifest_hash'  => '',
			'blockers'             => array(),
			'started_at'           => $now,
			'updated_at'           => $now,
			'completed_at'         => '',
		);

		if ( ! $this->store->save( $job_id, $state ) ) {
			return null;
		}
		$this->jobs->update_progress(
			$job_id,
			'files',
			'0:',
			array(
				'completed' => 0,
				'total'     => (int) ( $inventory['file_count'] ?? 0 ),
			)
		);
		return $this->store->get( $job_id );
	}

	/**
	 * Advance one bounded file-export batch.
	 *
	 * @param string $job_id      Clone job identifier.
	 * @param int    $batch_files Maximum accepted files copied this request.
	 * @param int    $batch_bytes Soft maximum copied bytes this request; one larger file may complete.
	 * @return array<string,mixed>|null
	 */
	public function advance(
		string $job_id,
		int $batch_files = self::DEFAULT_BATCH_FILES,
		int $batch_bytes = self::DEFAULT_BATCH_BYTES
	): ?array {
		$batch_files = max( self::MIN_BATCH_FILES, min( self::MAX_BATCH_FILES, $batch_files ) );
		$batch_bytes = max( self::MIN_BATCH_BYTES, min( self::MAX_BATCH_BYTES, $batch_bytes ) );
		$state       = $this->store->get( $job_id ) ?? $this->start( $job_id );
		if ( ! is_array( $state ) || in_array( $state['status'] ?? null, array( 'complete', 'blocked' ), true ) ) {
			return $state;
		}

		$inventory = $this->inventory->get( $job_id );
		$roots     = is_array( $inventory['roots'] ?? null ) ? array_values( $inventory['roots'] ) : array();
		if ( ! is_array( $inventory ) || 'complete' !== ( $inventory['status'] ?? null ) || array() === $roots ) {
			return $this->block( $job_id, $state, 'file-export-inventory-unavailable', false );
		}

		$accepted_files = 0;
		$accepted_bytes = 0;
		$operations     = 0;

		while ( $accepted_files < $batch_files && $operations < self::MAX_ENTRY_OPERATIONS ) {
			$root_index = (int) ( $state['root_index'] ?? 0 );
			if ( $root_index >= count( $roots ) ) {
				return $this->complete_files( $job_id, $state, $inventory, $roots );
			}

			$root    = is_array( $roots[ $root_index ] ?? null ) ? $roots[ $root_index ] : array();
			$root_id = is_string( $root['id'] ?? null ) ? $root['id'] : '';
			$base    = is_string( $root['path'] ?? null ) ? wp_normalize_path( $root['path'] ) : '';
			if ( ! $this->valid_root_id( $root_id ) || '' === $base || ! is_dir( $base ) || ! is_readable( $base ) || is_link( $base ) ) {
				return $this->block( $job_id, $state, 'file-export-root-unavailable', false );
			}

			$pending = is_array( $state['pending_dirs'] ?? null ) ? $state['pending_dirs'] : array();
			if ( '' === (string) ( $state['current_dir'] ?? '' ) && '' === (string) ( $state['after_name'] ?? '' ) ) {
				if ( array() === $pending ) {
					$state = $this->advance_root( $state );
					continue;
				}
				$state['current_dir']  = (string) array_shift( $pending );
				$state['pending_dirs'] = $pending;
			}

			$current_dir = (string) ( $state['current_dir'] ?? '' );
			$absolute    = $this->join_path( $base, $current_dir );
			$entries     = scandir( $absolute, SCANDIR_SORT_ASCENDING );
			if ( false === $entries ) {
				return $this->block( $job_id, $state, 'file-export-directory-read-failed', true );
			}

			$after_name   = (string) ( $state['after_name'] ?? '' );
			$dir_finished = true;

			foreach ( $entries as $entry ) {
				if ( '.' === $entry || '..' === $entry || ( '' !== $after_name && strcmp( $entry, $after_name ) <= 0 ) ) {
					continue;
				}
				++$operations;
				$relative = '' === $current_dir ? $entry : $current_dir . '/' . $entry;
				$path     = $this->join_path( $base, $relative );

				if ( $this->excluded( $relative, is_dir( $path ) ) ) {
					$state['after_name'] = $entry;
					if ( $operations >= self::MAX_ENTRY_OPERATIONS ) {
						$dir_finished = false;
						break;
					}
					continue;
				}

				if ( is_link( $path ) ) {
					$state['after_name'] = $entry;
					continue;
				}

				if ( is_dir( $path ) ) {
					$pending = is_array( $state['pending_dirs'] ?? null ) ? $state['pending_dirs'] : array();
					if ( count( $pending ) >= self::MAX_PENDING_DIRECTORIES ) {
						return $this->block( $job_id, $state, 'file-export-directory-queue-limit', false );
					}
					$pending[]             = $relative;
					$state['pending_dirs'] = $pending;
					$state['after_name']   = $entry;
					if ( $operations >= self::MAX_ENTRY_OPERATIONS ) {
						$dir_finished = false;
						break;
					}
					continue;
				}

				if ( ! is_file( $path ) || ! is_readable( $path ) ) {
					return $this->block( $job_id, $state, 'file-export-source-unreadable', true );
				}

				$payload_path = 'files/' . $root_id . '/' . ltrim( wp_normalize_path( $relative ), '/' );
				$copied       = $this->workspace->copy_file( $job_id, $payload_path, $path );
				if ( null === $copied ) {
					return $this->block( $job_id, $state, 'file-export-copy-failed', true );
				}

				$record      = array(
					'root'          => $root_id,
					'relative_path' => wp_normalize_path( $relative ),
					'payload_path'  => $payload_path,
					'byte_count'    => (int) $copied['bytes'],
					'sha256'        => (string) $copied['sha256'],
					'export_status' => 'copied',
				);
				$record_json = wp_json_encode( $record, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
				if ( ! is_string( $record_json ) ) {
					return $this->block( $job_id, $state, 'file-export-record-encode-failed', true );
				}
				$record_path = 'files-meta/' . $root_id . '/' . hash( 'sha256', wp_normalize_path( $relative ) ) . '.json';
				if ( null === $this->workspace->write( $job_id, $record_path, $record_json . "\n" ) ) {
					return $this->block( $job_id, $state, 'file-export-record-write-failed', true );
				}

				$state['export_fingerprint'] = $this->chain_hash(
					(string) $state['export_fingerprint'],
					'file|' . $root_id . '|' . wp_normalize_path( $relative ) . '|' . (string) (int) $copied['bytes'] . '|' . (string) $copied['sha256']
				);
				$state['file_count']         = (int) ( $state['file_count'] ?? 0 ) + 1;
				$state['byte_count']         = (int) ( $state['byte_count'] ?? 0 ) + (int) $copied['bytes'];
				$state['after_name']         = $entry;
				++$accepted_files;
				$accepted_bytes += (int) $copied['bytes'];

				if (
					$accepted_files >= $batch_files
					|| $accepted_bytes >= $batch_bytes
					|| $operations >= self::MAX_ENTRY_OPERATIONS
				) {
					$dir_finished = false;
					break;
				}
			}

			if ( $dir_finished ) {
				$state['current_dir'] = '';
				$state['after_name']  = '';
			}

			if ( $accepted_files > 0 && $accepted_bytes >= $batch_bytes ) {
				break;
			}
		}

		return $this->persist_progress( $job_id, $state );
	}

	/**
	 * Build the same database-prefix hash used before file hashing in source inventory.
	 *
	 * @param array<string,mixed> $inventory Source inventory.
	 */
	private function database_fingerprint_prefix( array $inventory ): string {
		$fingerprint = hash( 'sha256', 'seo-geo-portable-clone-inventory-v1' );
		$database    = is_array( $inventory['database'] ?? null ) ? $inventory['database'] : array();
		$tables      = is_array( $database['tables'] ?? null ) ? $database['tables'] : array();
		foreach ( $tables as $table ) {
			if ( ! is_array( $table ) ) {
				continue;
			}
			$fingerprint = $this->chain_hash(
				$fingerprint,
				'db|' . (string) ( $table['name'] ?? '' )
					. '|' . (string) (int) ( $table['estimated_rows'] ?? 0 )
					. '|' . (string) (int) ( $table['estimated_bytes'] ?? 0 )
			);
		}
		return $fingerprint;
	}

	/**
	 * Complete and reconcile file export with immutable inventory evidence.
	 *
	 * @param string                    $job_id    Clone job identifier.
	 * @param array<string,mixed>       $state     File-export state.
	 * @param array<string,mixed>       $inventory Source inventory.
	 * @param list<array<string,mixed>> $roots     Inventoried file roots.
	 * @return array<string,mixed>|null
	 */
	private function complete_files( string $job_id, array $state, array $inventory, array $roots ): ?array {
		if (
			(int) ( $state['file_count'] ?? 0 ) !== (int) ( $inventory['file_count'] ?? -1 )
			|| (int) ( $state['byte_count'] ?? 0 ) !== (int) ( $inventory['byte_count'] ?? -1 )
			|| ! hash_equals( (string) ( $inventory['fingerprint'] ?? '' ), (string) ( $state['export_fingerprint'] ?? '' ) )
		) {
			return $this->block( $job_id, $state, 'source-files-changed-since-inventory', false );
		}

		$root_summaries = array();
		foreach ( $roots as $root ) {
			$root_summaries[] = array(
				'id'         => is_string( $root['id'] ?? null ) ? $root['id'] : '',
				'file_count' => max( 0, (int) ( $root['file_count'] ?? 0 ) ),
				'byte_count' => max( 0, (int) ( $root['byte_count'] ?? 0 ) ),
			);
		}

		$manifest = array(
			'schema_version'              => 1,
			'payload_class'               => 'files',
			'file_count'                  => (int) $state['file_count'],
			'payload_bytes'               => (int) $state['byte_count'],
			'roots'                       => $root_summaries,
			'source_fingerprint'          => (string) $state['export_fingerprint'],
			'file_records'                => array(
				'format'    => 'one-json-record-per-file',
				'directory' => 'files-meta/',
			),
			'production_source_read_only' => true,
			'credentials_in_payload'      => false,
			'contains_private_site_data'  => true,
			'repository_safe'             => false,
			'generated_at'                => gmdate( DATE_ATOM ),
		);
		$json     = wp_json_encode( $manifest, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT );
		if ( ! is_string( $json ) ) {
			return $this->block( $job_id, $state, 'files-manifest-encode-failed', true );
		}
		$written = $this->workspace->write( $job_id, 'files/manifest.json', $json . "\n" );
		if ( null === $written ) {
			return $this->block( $job_id, $state, 'files-manifest-write-failed', true );
		}

		$state['status']              = 'complete';
		$state['files_manifest_hash'] = (string) $written['sha256'];
		$state['updated_at']          = gmdate( DATE_ATOM );
		$state['completed_at']        = $state['updated_at'];
		if ( ! $this->store->save( $job_id, $state ) ) {
			return null;
		}
		$this->jobs->update_progress(
			$job_id,
			'files',
			'complete',
			array(
				'completed' => (int) $state['file_count'],
				'total'     => (int) $state['inventory_file_count'],
			)
		);
		return $this->store->get( $job_id );
	}

	/**
	 * Persist bounded file-export progress.
	 *
	 * @param string              $job_id Clone job identifier.
	 * @param array<string,mixed> $state  File-export state.
	 * @return array<string,mixed>|null
	 */
	private function persist_progress( string $job_id, array $state ): ?array {
		$state['updated_at'] = gmdate( DATE_ATOM );
		if ( ! $this->store->save( $job_id, $state ) ) {
			return null;
		}
		$cursor = (string) (int) ( $state['root_index'] ?? 0 ) . ':' . (string) ( $state['current_dir'] ?? '' ) . ':' . (string) ( $state['after_name'] ?? '' );
		$this->jobs->update_progress(
			$job_id,
			'files',
			substr( $cursor, 0, 512 ),
			array(
				'completed' => (int) ( $state['file_count'] ?? 0 ),
				'total'     => (int) ( $state['inventory_file_count'] ?? 0 ),
			)
		);
		return $this->store->get( $job_id );
	}

	/**
	 * Persist one bounded file-export blocker.
	 *
	 * @param string              $job_id    Clone job identifier.
	 * @param array<string,mixed> $state     File-export state.
	 * @param string              $code      Blocker code.
	 * @param bool                $retryable Retry allowed.
	 * @return array<string,mixed>|null
	 */
	private function block( string $job_id, array $state, string $code, bool $retryable ): ?array {
		$blockers            = is_array( $state['blockers'] ?? null ) ? $state['blockers'] : array();
		$blockers[]          = $code;
		$state['blockers']   = array_values( array_unique( $blockers ) );
		$state['status']     = 'blocked';
		$state['updated_at'] = gmdate( DATE_ATOM );
		$this->store->save( $job_id, $state );
		$this->jobs->transition( $job_id, $retryable ? 'failed-retryable' : 'failed-terminal', $code );
		return $this->store->get( $job_id );
	}

	/**
	 * Move traversal to the next payload root.
	 *
	 * @param array<string,mixed> $state File-export state.
	 * @return array<string,mixed>
	 */
	private function advance_root( array $state ): array {
		$state['root_index']   = (int) ( $state['root_index'] ?? 0 ) + 1;
		$state['pending_dirs'] = array( '' );
		$state['current_dir']  = '';
		$state['after_name']   = '';
		return $state;
	}

	/**
	 * Match the source inventory exclusion policy exactly.
	 *
	 * @param string $relative Relative path.
	 * @param bool   $is_dir   Whether the path is a directory.
	 */
	private function excluded( string $relative, bool $is_dir ): bool {
		$normalized    = strtolower( wp_normalize_path( $relative ) );
		$segments      = array_values(
			array_filter(
				explode( '/', $normalized ),
				static fn( string $segment ): bool => '' !== $segment
			)
		);
		$excluded_dirs = array(
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
		);
		if ( $is_dir && array_intersect( $segments, $excluded_dirs ) ) {
			return true;
		}
		$basename = basename( $normalized );
		return ! $is_dir && (
			'.ds_store' === $basename
			|| str_ends_with( $basename, '.log' )
			|| str_ends_with( $basename, '.tmp' )
		);
	}

	/**
	 * Validate one inventoried root identifier.
	 *
	 * @param string $root_id Root identifier.
	 */
	private function valid_root_id( string $root_id ): bool {
		return in_array( $root_id, array( 'uploads', 'plugins', 'themes' ), true );
	}

	/**
	 * Chain one deterministic source fingerprint record.
	 *
	 * @param string $previous Previous chain hash.
	 * @param string $record   Canonical inventory record.
	 */
	private function chain_hash( string $previous, string $record ): string {
		return hash( 'sha256', $previous . "\n" . $record );
	}

	/**
	 * Join normalized filesystem paths.
	 *
	 * @param string $base     Absolute base path.
	 * @param string $relative Relative path.
	 */
	private function join_path( string $base, string $relative ): string {
		return rtrim( wp_normalize_path( $base ), '/' )
			. ( '' === $relative ? '' : '/' . ltrim( wp_normalize_path( $relative ), '/' ) );
	}
}
