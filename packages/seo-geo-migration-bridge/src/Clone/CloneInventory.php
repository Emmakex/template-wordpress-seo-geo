<?php
/**
 * Portable Clone Engine read-only source inventory.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge\Clone;

use wpdb;

/**
 * Inventories database/file source state in bounded resumable batches.
 */
final class CloneInventory {
	/**
	 * Minimum accepted file batch size.
	 */
	public const MIN_BATCH_SIZE = 25;

	/**
	 * Maximum accepted file batch size.
	 */
	public const MAX_BATCH_SIZE = 500;

	/**
	 * Default accepted file batch size.
	 */
	public const DEFAULT_BATCH_SIZE = 100;

	/**
	 * Maximum pending directory queue retained in WordPress options.
	 */
	private const MAX_PENDING_DIRECTORIES = 50000;

	/**
	 * Maximum directory entries inspected during one request.
	 */
	private const MAX_ENTRY_OPERATIONS = 4000;

	/**
	 * Inventory persistence.
	 *
	 * @var CloneInventoryStore
	 */
	private CloneInventoryStore $store;

	/**
	 * Clone job persistence.
	 *
	 * @var CloneJobStore
	 */
	private CloneJobStore $jobs;

	/**
	 * Construct the inventory service.
	 *
	 * @param CloneInventoryStore|null $store Optional inventory store.
	 * @param CloneJobStore|null       $jobs  Optional clone job store.
	 */
	public function __construct( ?CloneInventoryStore $store = null, ?CloneJobStore $jobs = null ) {
		$this->store = $store ?? new CloneInventoryStore();
		$this->jobs  = $jobs ?? new CloneJobStore();
	}

	/**
	 * Return one inventory state.
	 *
	 * @param string $job_id Clone job identifier.
	 * @return array<string,mixed>|null
	 */
	public function snapshot( string $job_id ): ?array {
		return $this->store->get( $job_id );
	}

	/**
	 * Start source inventory without copying any payload.
	 *
	 * @param string $job_id Clone job identifier.
	 * @return array<string,mixed>|null
	 */
	public function start( string $job_id ): ?array {
		$job = $this->jobs->get( $job_id );
		if ( ! is_array( $job ) || ! in_array( $job['operation'] ?? null, array( 'local-clone', 'export' ), true ) ) {
			return null;
		}

		$existing = $this->store->get( $job_id );
		if ( is_array( $existing ) ) {
			return $existing;
		}

		$database = $this->database_inventory();
		$roots    = $this->file_roots();

		$fingerprint = hash( 'sha256', 'seo-geo-portable-clone-inventory-v1' );
		foreach ( $database['tables'] as $table ) {
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

		$now   = gmdate( DATE_ATOM );
		$state = array(
			'schema_version'   => CloneInventoryStore::SCHEMA_VERSION,
			'job_id'           => $job_id,
			'status'           => 'running',
			'database'         => $database,
			'roots'            => $roots,
			'root_index'       => 0,
			'pending_dirs'     => array( '' ),
			'current_dir'      => '',
			'after_name'       => '',
			'file_count'       => 0,
			'byte_count'       => 0,
			'excluded_count'   => 0,
			'symlink_count'    => 0,
			'unreadable_count' => 0,
			'fingerprint'      => $fingerprint,
			'blockers'         => array(),
			'started_at'       => $now,
			'updated_at'       => $now,
			'completed_at'     => '',
		);

		if ( ! $this->store->save( $job_id, $state ) ) {
			return null;
		}

		$this->jobs->update_progress(
			$job_id,
			'inventory',
			0,
			array(
				'completed' => 0,
				'total'     => null,
			)
		);

		return $this->store->get( $job_id );
	}

	/**
	 * Advance one bounded inventory batch.
	 *
	 * @param string $job_id    Clone job identifier.
	 * @param int    $batch_size Maximum accepted files hashed this request.
	 * @return array<string,mixed>|null
	 */
	public function advance( string $job_id, int $batch_size = self::DEFAULT_BATCH_SIZE ): ?array {
		$batch_size = max( self::MIN_BATCH_SIZE, min( self::MAX_BATCH_SIZE, $batch_size ) );
		$state      = $this->store->get( $job_id ) ?? $this->start( $job_id );
		if ( ! is_array( $state ) || in_array( $state['status'] ?? null, array( 'complete', 'blocked' ), true ) ) {
			return $state;
		}

		$roots = is_array( $state['roots'] ?? null ) ? $state['roots'] : array();
		if ( array() === $roots ) {
			return $this->block( $state, 'source-file-roots-unavailable' );
		}

		$accepted_files = 0;
		$operations     = 0;

		while ( $accepted_files < $batch_size && $operations < self::MAX_ENTRY_OPERATIONS ) {
			$root_index = (int) ( $state['root_index'] ?? 0 );
			if ( $root_index >= count( $roots ) ) {
				return $this->complete( $state );
			}

			$root = is_array( $roots[ $root_index ] ?? null ) ? $roots[ $root_index ] : array();
			$base = is_string( $root['path'] ?? null ) ? $root['path'] : '';
			if ( '' === $base || ! is_dir( $base ) || ! is_readable( $base ) ) {
				$state['unreadable_count'] = (int) ( $state['unreadable_count'] ?? 0 ) + 1;
				$state                     = $this->advance_root( $state );
				continue;
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
				$state['unreadable_count'] = (int) ( $state['unreadable_count'] ?? 0 ) + 1;
				$state['current_dir']      = '';
				$state['after_name']       = '';
				continue;
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
					$state['excluded_count'] = (int) ( $state['excluded_count'] ?? 0 ) + 1;
					$state['after_name']     = $entry;
					if ( $operations >= self::MAX_ENTRY_OPERATIONS ) {
						$dir_finished = false;
						break;
					}
					continue;
				}

				if ( is_link( $path ) ) {
					$state['symlink_count'] = (int) ( $state['symlink_count'] ?? 0 ) + 1;
					$state['after_name']    = $entry;
					continue;
				}

				if ( is_dir( $path ) ) {
					$pending = is_array( $state['pending_dirs'] ?? null ) ? $state['pending_dirs'] : array();
					if ( count( $pending ) >= self::MAX_PENDING_DIRECTORIES ) {
						return $this->block( $state, 'source-directory-queue-limit' );
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
					$state['unreadable_count'] = (int) ( $state['unreadable_count'] ?? 0 ) + 1;
					$state['after_name']       = $entry;
					continue;
				}

				$size = filesize( $path );
				$hash = hash_file( 'sha256', $path );
				if ( false === $size || false === $hash ) {
					$state['unreadable_count'] = (int) ( $state['unreadable_count'] ?? 0 ) + 1;
					$state['after_name']       = $entry;
					continue;
				}

				$root_id              = (string) ( $root['id'] ?? 'unknown' );
				$state['fingerprint'] = $this->chain_hash(
					(string) $state['fingerprint'],
					'file|' . $root_id . '|' . wp_normalize_path( $relative ) . '|' . (string) $size . '|' . $hash
				);
				$state['file_count']  = (int) ( $state['file_count'] ?? 0 ) + 1;
				$state['byte_count']  = (int) ( $state['byte_count'] ?? 0 ) + $size;

				if ( isset( $roots[ $root_index ] ) && is_array( $roots[ $root_index ] ) ) {
					$roots[ $root_index ]['file_count'] = (int) ( $roots[ $root_index ]['file_count'] ?? 0 ) + 1;
					$roots[ $root_index ]['byte_count'] = (int) ( $roots[ $root_index ]['byte_count'] ?? 0 ) + $size;
				}

				$state['roots']      = $roots;
				$state['after_name'] = $entry;
				++$accepted_files;

				if ( $accepted_files >= $batch_size || $operations >= self::MAX_ENTRY_OPERATIONS ) {
					$dir_finished = false;
					break;
				}
			}

			if ( $dir_finished ) {
				$state['current_dir'] = '';
				$state['after_name']  = '';
			}
		}

		$state['updated_at'] = gmdate( DATE_ATOM );
		if ( ! $this->store->save( $job_id, $state ) ) {
			return null;
		}

		$this->jobs->update_progress(
			$job_id,
			'inventory',
			(int) ( $state['file_count'] ?? 0 ),
			array(
				'completed' => (int) ( $state['file_count'] ?? 0 ),
				'total'     => null,
			)
		);

		return $this->store->get( $job_id );
	}

	/**
	 * Inventory WordPress-prefix database tables using read-only SHOW metadata.
	 *
	 * @return array<string,mixed>
	 */
	private function database_inventory(): array {
		global $wpdb;

		if ( ! $wpdb instanceof wpdb ) {
			return array(
				'table_prefix'    => '',
				'table_count'     => 0,
				'estimated_rows'  => 0,
				'estimated_bytes' => 0,
				'tables'          => array(),
				'read_only'       => true,
				'available'       => false,
			);
		}

		$like = $wpdb->esc_like( $wpdb->prefix ) . '%';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- Read-only table metadata query; LIKE value is prepared.
		$rows = $wpdb->get_results( $wpdb->prepare( 'SHOW TABLE STATUS LIKE %s', $like ), ARRAY_A );
		if ( ! is_array( $rows ) ) {
			$rows = array();
		}

		$tables          = array();
		$estimated_rows  = 0;
		$estimated_bytes = 0;

		foreach ( array_slice( $rows, 0, 1000 ) as $row ) {
			if ( ! is_array( $row ) || ! is_string( $row['Name'] ?? null ) ) {
				continue;
			}

			$table_rows       = max( 0, (int) ( $row['Rows'] ?? 0 ) );
			$table_bytes      = max( 0, (int) ( $row['Data_length'] ?? 0 ) ) + max( 0, (int) ( $row['Index_length'] ?? 0 ) );
			$tables[]         = array(
				'name'            => (string) $row['Name'],
				'engine'          => is_string( $row['Engine'] ?? null ) ? substr( $row['Engine'], 0, 40 ) : '',
				'estimated_rows'  => $table_rows,
				'estimated_bytes' => $table_bytes,
			);
			$estimated_rows  += $table_rows;
			$estimated_bytes += $table_bytes;
		}

		return array(
			'table_prefix'    => $wpdb->prefix,
			'table_count'     => count( $tables ),
			'estimated_rows'  => $estimated_rows,
			'estimated_bytes' => $estimated_bytes,
			'tables'          => $tables,
			'truncated'       => count( $rows ) > 1000,
			'read_only'       => true,
			'available'       => true,
		);
	}

	/**
	 * Return accepted source file roots.
	 *
	 * @return list<array<string,mixed>>
	 */
	private function file_roots(): array {
		$uploads = wp_get_upload_dir();
		$roots   = array(
			array(
				'id'   => 'uploads',
				'path' => is_string( $uploads['basedir'] ?? null ) ? wp_normalize_path( $uploads['basedir'] ) : '',
			),
			array(
				'id'   => 'plugins',
				'path' => defined( 'WP_PLUGIN_DIR' ) ? wp_normalize_path( WP_PLUGIN_DIR ) : '',
			),
			array(
				'id'   => 'themes',
				'path' => wp_normalize_path( get_theme_root() ),
			),
		);

		foreach ( $roots as &$root ) {
			$root['file_count'] = 0;
			$root['byte_count'] = 0;
			$root['read_only']  = true;
		}
		unset( $root );

		return $roots;
	}

	/**
	 * Determine whether a source path is excluded from the clone payload.
	 *
	 * @param string $relative Relative path.
	 * @param bool   $is_dir   Whether the path is a directory.
	 */
	private function excluded( string $relative, bool $is_dir ): bool {
		$normalized    = strtolower( wp_normalize_path( $relative ) );
		$segments      = array_values( array_filter( explode( '/', $normalized ), 'strlen' ) );
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
	 * Move traversal to the next payload root.
	 *
	 * @param array<string,mixed> $state Inventory state.
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
	 * Complete inventory and persist final fingerprint.
	 *
	 * @param array<string,mixed> $state Inventory state.
	 * @return array<string,mixed>|null
	 */
	private function complete( array $state ): ?array {
		$job_id = is_string( $state['job_id'] ?? null ) ? $state['job_id'] : '';
		if ( '' === $job_id ) {
			return null;
		}

		$state['status']       = 'complete';
		$state['updated_at']   = gmdate( DATE_ATOM );
		$state['completed_at'] = $state['updated_at'];

		if ( ! $this->store->save( $job_id, $state ) ) {
			return null;
		}

		$this->jobs->update_progress(
			$job_id,
			'inventory',
			'complete',
			array(
				'completed' => (int) ( $state['file_count'] ?? 0 ),
				'total'     => (int) ( $state['file_count'] ?? 0 ),
			)
		);

		return $this->store->get( $job_id );
	}

	/**
	 * Block inventory on a bounded safety condition.
	 *
	 * @param array<string,mixed> $state Inventory state.
	 * @param string              $code  Blocker code.
	 * @return array<string,mixed>|null
	 */
	private function block( array $state, string $code ): ?array {
		$job_id = is_string( $state['job_id'] ?? null ) ? $state['job_id'] : '';
		if ( '' === $job_id ) {
			return null;
		}

		$blockers            = is_array( $state['blockers'] ?? null ) ? $state['blockers'] : array();
		$blockers[]          = $code;
		$state['blockers']   = array_values( array_unique( $blockers ) );
		$state['status']     = 'blocked';
		$state['updated_at'] = gmdate( DATE_ATOM );

		$this->store->save( $job_id, $state );
		$this->jobs->transition( $job_id, 'failed-terminal', $code );

		return $this->store->get( $job_id );
	}

	/**
	 * Chain one deterministic fingerprint record.
	 *
	 * @param string $previous Previous chain hash.
	 * @param string $record   Deterministic record.
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
