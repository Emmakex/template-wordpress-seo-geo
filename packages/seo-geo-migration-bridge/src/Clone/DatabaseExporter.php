<?php
/**
 * Portable Clone resumable read-only database exporter.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge\Clone;

use wpdb;

/**
 * Exports selected WordPress-prefix tables into private deterministic chunks.
 */
final class DatabaseExporter {
	public const MIN_BATCH_ROWS     = 10;
	public const MAX_BATCH_ROWS     = 500;
	public const DEFAULT_BATCH_ROWS = 100;

	/**
	 * Export-state persistence.
	 *
	 * @var ExportStateStore
	 */
	private ExportStateStore $store;

	/**
	 * Source inventory persistence.
	 *
	 * @var CloneInventoryStore
	 */
	private CloneInventoryStore $inventory;

	/**
	 * Clone job persistence.
	 *
	 * @var CloneJobStore
	 */
	private CloneJobStore $jobs;

	/**
	 * Private export workspace.
	 *
	 * @var ExportWorkspace
	 */
	private ExportWorkspace $workspace;

	/**
	 * Construct the resumable database exporter.
	 *
	 * @param ExportStateStore|null    $store     Optional export-state store.
	 * @param CloneInventoryStore|null $inventory Optional source inventory store.
	 * @param CloneJobStore|null       $jobs      Optional clone job store.
	 * @param ExportWorkspace|null     $workspace Optional private workspace.
	 */
	public function __construct(
		?ExportStateStore $store = null,
		?CloneInventoryStore $inventory = null,
		?CloneJobStore $jobs = null,
		?ExportWorkspace $workspace = null
	) {
		$this->store     = $store ?? new ExportStateStore();
		$this->inventory = $inventory ?? new CloneInventoryStore();
		$this->jobs      = $jobs ?? new CloneJobStore();
		$this->workspace = $workspace ?? new ExportWorkspace();
	}

	/**
	 * Return one normalized database export state.
	 *
	 * @param string $job_id Clone job identifier.
	 * @return array<string,mixed>|null
	 */
	public function snapshot( string $job_id ): ?array {
		return $this->store->get( $job_id );
	}

	/**
	 * Initialize database export after a completed source inventory.
	 *
	 * @param string $job_id Clone job identifier.
	 * @return array<string,mixed>|null
	 */
	public function start( string $job_id ): ?array {
		$job       = $this->jobs->get( $job_id );
		$inventory = $this->inventory->get( $job_id );
		if (
			! is_array( $job )
			|| ! in_array( $job['operation'] ?? null, array( 'local-clone', 'export' ), true )
			|| ! is_array( $inventory )
			|| 'complete' !== ( $inventory['status'] ?? null )
		) {
			return null;
		}

		$existing = $this->store->get( $job_id );
		if ( is_array( $existing ) ) {
			return $existing;
		}

		$database = is_array( $inventory['database'] ?? null ) ? $inventory['database'] : array();
		$tables   = is_array( $database['tables'] ?? null ) ? $database['tables'] : array();
		if ( true !== ( $database['available'] ?? false ) || null === $this->workspace->ensure( $job_id ) ) {
			return null;
		}

		$now   = gmdate( DATE_ATOM );
		$state = array(
			'schema_version'         => ExportStateStore::SCHEMA_VERSION,
			'job_id'                 => $job_id,
			'status'                 => 'running',
			'table_index'            => 0,
			'table_count'            => count( $tables ),
			'current_table'          => '',
			'strategy'               => '',
			'cursor_value_b64'       => '',
			'offset'                 => 0,
			'chunk_index'            => 0,
			'row_count'              => 0,
			'byte_count'             => 0,
			'chunk_count'            => 0,
			'tables_completed'       => 0,
			'database_manifest_hash' => '',
			'blockers'               => array(),
			'started_at'             => $now,
			'updated_at'             => $now,
			'completed_at'           => '',
		);

		if ( ! $this->store->save( $job_id, $state ) ) {
			return null;
		}

		$this->jobs->update_progress(
			$job_id,
			'database',
			'0:0',
			array(
				'completed' => 0,
				'total'     => null,
			)
		);

		return $this->store->get( $job_id );
	}

	/**
	 * Advance one bounded database export batch.
	 *
	 * @param string $job_id    Clone job identifier.
	 * @param int    $batch_rows Maximum rows exported this request.
	 * @return array<string,mixed>|null
	 */
	public function advance( string $job_id, int $batch_rows = self::DEFAULT_BATCH_ROWS ): ?array {
		$batch_rows = max( self::MIN_BATCH_ROWS, min( self::MAX_BATCH_ROWS, $batch_rows ) );
		$state      = $this->store->get( $job_id ) ?? $this->start( $job_id );
		if ( ! is_array( $state ) || in_array( $state['status'] ?? null, array( 'complete', 'blocked' ), true ) ) {
			return $state;
		}

		$inventory = $this->inventory->get( $job_id );
		$database  = is_array( $inventory['database'] ?? null ) ? $inventory['database'] : array();
		$tables    = is_array( $database['tables'] ?? null ) ? array_values( $database['tables'] ) : array();
		$index     = (int) ( $state['table_index'] ?? 0 );

		if ( $index >= count( $tables ) ) {
			return $this->complete_database( $job_id, $state, $database, $tables );
		}

		$entry = is_array( $tables[ $index ] ?? null ) ? $tables[ $index ] : array();
		$table = is_string( $entry['name'] ?? null ) ? $entry['name'] : '';
		if ( '' === $table || ! $this->accepted_source_table( $table, (string) ( $database['table_prefix'] ?? '' ) ) ) {
			return $this->block( $job_id, $state, 'database-table-outside-inventory-prefix', false );
		}

		$meta = $this->load_table_metadata( $job_id, $table ) ?? $this->initialize_table( $job_id, $table );
		if ( null === $meta ) {
			return $this->block( $job_id, $state, 'database-table-metadata-failed', true );
		}

		$state['current_table'] = $table;
		$state['strategy']      = (string) ( $meta['strategy'] ?? '' );
		$rows                   = $this->fetch_rows( $table, $meta, $state, $batch_rows );
		if ( null === $rows ) {
			return $this->block( $job_id, $state, 'database-row-read-failed', true );
		}

		if ( array() === $rows ) {
			$state = $this->complete_table( $job_id, $state, $meta );
			return (int) $state['table_index'] >= count( $tables )
				? $this->complete_database( $job_id, $state, $database, $tables )
				: $this->persist_progress( $job_id, $state );
		}

		$chunk_index = (int) ( $state['chunk_index'] ?? 0 );
		$chunk       = $this->build_chunk_payload( $table, $meta, $state, $rows, $chunk_index );
		$json        = wp_json_encode( $chunk, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		if ( ! is_string( $json ) ) {
			return $this->block( $job_id, $state, 'database-chunk-encode-failed', true );
		}

		$chunk_path = $this->table_directory( $table ) . '/chunks/' . sprintf( '%06d.json', $chunk_index );
		$written    = $this->workspace->write( $job_id, $chunk_path, $json );
		if ( null === $written ) {
			return $this->block( $job_id, $state, 'database-chunk-write-failed', true );
		}

		$chunks   = is_array( $meta['chunks'] ?? null ) ? $meta['chunks'] : array();
		$chunks[] = array(
			'index'      => $chunk_index,
			'path'       => $chunk_path,
			'row_count'  => count( $rows ),
			'byte_count' => (int) $written['bytes'],
			'sha256'     => (string) $written['sha256'],
		);

		$meta['chunks']     = $chunks;
		$meta['row_count']  = (int) ( $meta['row_count'] ?? 0 ) + count( $rows );
		$meta['byte_count'] = (int) ( $meta['byte_count'] ?? 0 ) + (int) $written['bytes'];

		$state['row_count']   = (int) ( $state['row_count'] ?? 0 ) + count( $rows );
		$state['byte_count']  = (int) ( $state['byte_count'] ?? 0 ) + (int) $written['bytes'];
		$state['chunk_count'] = (int) ( $state['chunk_count'] ?? 0 ) + 1;
		$state['chunk_index'] = $chunk_index + 1;

		if ( 'primary-key' === ( $meta['strategy'] ?? null ) ) {
			$column = is_string( $meta['cursor_column'] ?? null ) ? $meta['cursor_column'] : '';
			$last   = end( $rows );
			if ( '' === $column || ! array_key_exists( $column, $last ) || null === $last[ $column ] ) {
				return $this->block( $job_id, $state, 'database-primary-cursor-missing', false );
			}
			$state['cursor_value_b64'] = $this->encode_cursor( (string) $last[ $column ] );
		} else {
			$state['offset'] = (int) ( $state['offset'] ?? 0 ) + count( $rows );
		}

		if ( ! $this->save_table_metadata( $job_id, $table, $meta ) ) {
			return $this->block( $job_id, $state, 'database-table-manifest-write-failed', true );
		}

		if ( count( $rows ) < $batch_rows ) {
			$state = $this->complete_table( $job_id, $state, $meta );
			if ( (int) $state['table_index'] >= count( $tables ) ) {
				return $this->complete_database( $job_id, $state, $database, $tables );
			}
		}

		return $this->persist_progress( $job_id, $state );
	}

	/**
	 * Delete this job's private export workspace and export state.
	 *
	 * @param string $job_id Clone job identifier.
	 */
	public function cleanup( string $job_id ): bool {
		return $this->workspace->cleanup( $job_id ) && $this->store->delete( $job_id );
	}

	/**
	 * Initialize schema and cursor metadata for one inventoried table.
	 *
	 * @param string $job_id Clone job identifier.
	 * @param string $table  Source table.
	 * @return array<string,mixed>|null
	 */
	private function initialize_table( string $job_id, string $table ): ?array {
		global $wpdb;
		if ( ! $wpdb instanceof wpdb ) {
			return null;
		}

		$quoted = $this->quote_identifier( $table );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Read-only SHOW CREATE against inventoried table.
		$create_row = $wpdb->get_row( "SHOW CREATE TABLE {$quoted}", ARRAY_A );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Read-only SHOW against inventoried table.
		$column_rows = $wpdb->get_results( "SHOW COLUMNS FROM {$quoted}", ARRAY_A );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Read-only SHOW against inventoried table.
		$key_rows = $wpdb->get_results( "SHOW KEYS FROM {$quoted} WHERE Key_name = 'PRIMARY'", ARRAY_A );

		$create_sql = is_string( $create_row['Create Table'] ?? null ) ? $create_row['Create Table'] : '';
		if ( '' === $create_sql ) {
			foreach ( $create_row as $value ) {
				if ( is_string( $value ) && str_starts_with( strtoupper( ltrim( $value ) ), 'CREATE TABLE' ) ) {
					$create_sql = $value;
					break;
				}
			}
		}
		if ( '' === $create_sql ) {
			return null;
		}

		$columns = array();
		$types   = array();
		foreach ( $column_rows as $row ) {
			$field = is_string( $row['Field'] ?? null ) ? $row['Field'] : '';
			$type  = is_string( $row['Type'] ?? null ) ? strtolower( $row['Type'] ) : '';
			if ( '' !== $field ) {
				$columns[]       = $field;
				$types[ $field ] = $type;
			}
		}
		if ( array() === $columns ) {
			return null;
		}

		$primary = array();
		foreach ( $key_rows as $row ) {
			$column = is_string( $row['Column_name'] ?? null ) ? $row['Column_name'] : '';
			if ( '' !== $column ) {
				$primary[] = $column;
			}
		}

		$strategy      = 1 === count( $primary ) ? 'primary-key' : 'offset-fallback';
		$cursor_column = 'primary-key' === $strategy ? $primary[0] : '';
		$order_columns = array();

		if ( 'offset-fallback' === $strategy ) {
			foreach ( $columns as $column ) {
				$type = $types[ $column ] ?? '';
				if ( 1 === preg_match( '/(?:blob|text|json|geometry)/i', $type ) ) {
					continue;
				}
				$order_columns[] = $column;
				if ( 3 <= count( $order_columns ) ) {
					break;
				}
			}
			if ( array() === $order_columns ) {
				$order_columns[] = $columns[0];
			}
		}

		$schema_path = $this->table_directory( $table ) . '/schema.sql';
		$schema      = $this->workspace->write( $job_id, $schema_path, $create_sql . ";\n" );
		if ( null === $schema ) {
			return null;
		}

		$meta = array(
			'schema_version' => 1,
			'name'           => $table,
			'slug'           => $this->table_slug( $table ),
			'schema'         => array(
				'path'   => $schema_path,
				'bytes'  => (int) $schema['bytes'],
				'sha256' => (string) $schema['sha256'],
			),
			'strategy'       => $strategy,
			'cursor_column'  => $cursor_column,
			'order_columns'  => $order_columns,
			'columns'        => $columns,
			'chunks'         => array(),
			'row_count'      => 0,
			'byte_count'     => 0,
			'complete'       => false,
		);

		return $this->save_table_metadata( $job_id, $table, $meta ) ? $meta : null;
	}

	/**
	 * Fetch one deterministic bounded row batch.
	 *
	 * @param string              $table      Source table.
	 * @param array<string,mixed> $meta       Table export metadata.
	 * @param array<string,mixed> $state      Export state.
	 * @param int                 $batch_rows Row batch size.
	 * @return list<array<string,mixed>>|null
	 */
	private function fetch_rows( string $table, array $meta, array $state, int $batch_rows ): ?array {
		global $wpdb;
		if ( ! $wpdb instanceof wpdb ) {
			return null;
		}

		$quoted = $this->quote_identifier( $table );
		if ( 'primary-key' === ( $meta['strategy'] ?? null ) ) {
			$column = is_string( $meta['cursor_column'] ?? null ) ? $meta['cursor_column'] : '';
			if ( '' === $column ) {
				return null;
			}
			$quoted_column = $this->quote_identifier( $column );
			$cursor        = $this->decode_cursor( (string) ( $state['cursor_value_b64'] ?? '' ) );
			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Identifiers are quoted and come only from the accepted inventory.
			$sql = null === $cursor
				? $wpdb->prepare( "SELECT * FROM {$quoted} ORDER BY {$quoted_column} ASC LIMIT %d", $batch_rows )
				: $wpdb->prepare( "SELECT * FROM {$quoted} WHERE {$quoted_column} > %s ORDER BY {$quoted_column} ASC LIMIT %d", $cursor, $batch_rows );
			// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		} else {
			$order_columns = is_array( $meta['order_columns'] ?? null ) ? $meta['order_columns'] : array();
			$order_parts   = array();
			foreach ( $order_columns as $column ) {
				if ( is_string( $column ) && '' !== $column ) {
					$order_parts[] = $this->quote_identifier( $column ) . ' ASC';
				}
			}
			if ( array() === $order_parts ) {
				return null;
			}
			$offset = max( 0, (int) ( $state['offset'] ?? 0 ) );
			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared -- ORDER identifiers are quoted and derived only from inventoried table metadata.
			$sql = $wpdb->prepare(
				"SELECT * FROM {$quoted} ORDER BY " . implode( ', ', $order_parts ) . ' LIMIT %d, %d',
				$offset,
				$batch_rows
			);
			// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- Source exporter is SHOW/SELECT-only with prepared values and inventoried identifiers.
		$rows = $wpdb->get_results( $sql, ARRAY_A );
		return $rows;
	}

	/**
	 * Build one binary-safe private row-chunk payload.
	 *
	 * @param string                    $table       Source table.
	 * @param array<string,mixed>       $meta        Table metadata.
	 * @param array<string,mixed>       $state       Export state.
	 * @param list<array<string,mixed>> $rows        Rows.
	 * @param int                       $chunk_index Chunk index.
	 * @return array<string,mixed>
	 */
	private function build_chunk_payload( string $table, array $meta, array $state, array $rows, int $chunk_index ): array {
		$columns = is_array( $meta['columns'] ?? null ) ? array_values( array_filter( $meta['columns'], 'is_string' ) ) : array();
		$encoded = array();
		foreach ( $rows as $row ) {
			$encoded_row = array();
			foreach ( $columns as $column ) {
				if ( ! array_key_exists( $column, $row ) || null === $row[ $column ] ) {
					$encoded_row[] = null;
					continue;
				}

				// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Binary-safe migration transport encoding, not code obfuscation.
				$encoded_row[] = base64_encode( (string) $row[ $column ] );
			}
			$encoded[] = $encoded_row;
		}

		return array(
			'schema_version'   => 1,
			'table'            => $table,
			'strategy'         => (string) ( $meta['strategy'] ?? '' ),
			'cursor_column'    => (string) ( $meta['cursor_column'] ?? '' ),
			'cursor_start_b64' => (string) ( $state['cursor_value_b64'] ?? '' ),
			'offset_start'     => (int) ( $state['offset'] ?? 0 ),
			'chunk_index'      => $chunk_index,
			'row_count'        => count( $rows ),
			'columns'          => $columns,
			'value_encoding'   => 'base64-or-null',
			'rows'             => $encoded,
		);
	}

	/**
	 * Mark one table export complete and advance the cursor.
	 *
	 * @param string              $job_id Clone job identifier.
	 * @param array<string,mixed> $state  Export state.
	 * @param array<string,mixed> $meta   Table metadata.
	 * @return array<string,mixed>
	 */
	private function complete_table( string $job_id, array $state, array $meta ): array {
		$table = is_string( $meta['name'] ?? null ) ? $meta['name'] : '';
		if ( '' !== $table ) {
			$meta['complete']     = true;
			$meta['completed_at'] = gmdate( DATE_ATOM );
			$this->save_table_metadata( $job_id, $table, $meta );
		}
		$state['table_index']      = (int) ( $state['table_index'] ?? 0 ) + 1;
		$state['tables_completed'] = (int) ( $state['tables_completed'] ?? 0 ) + 1;
		$state['current_table']    = '';
		$state['strategy']         = '';
		$state['cursor_value_b64'] = '';
		$state['offset']           = 0;
		$state['chunk_index']      = 0;
		return $state;
	}

	/**
	 * Write the final private database manifest and complete export state.
	 *
	 * @param string                    $job_id   Clone job identifier.
	 * @param array<string,mixed>       $state    Export state.
	 * @param array<string,mixed>       $database Inventory database metadata.
	 * @param list<array<string,mixed>> $tables   Inventory table rows.
	 * @return array<string,mixed>|null
	 */
	private function complete_database( string $job_id, array $state, array $database, array $tables ): ?array {
		$table_manifests = array();
		foreach ( $tables as $entry ) {
			if ( ! is_string( $entry['name'] ?? null ) ) {
				continue;
			}
			$meta = $this->load_table_metadata( $job_id, $entry['name'] );
			if ( ! is_array( $meta ) || true !== ( $meta['complete'] ?? false ) ) {
				return $this->block( $job_id, $state, 'database-table-export-incomplete', true );
			}
			$table_manifests[] = $meta;
		}

		$manifest = array(
			'schema_version'              => 1,
			'package_id'                  => $job_id,
			'payload_class'               => 'database',
			'source'                      => array(
				'home_url'          => home_url( '/' ),
				'site_url'          => site_url( '/' ),
				'wordpress_version' => get_bloginfo( 'version' ),
				'php_version'       => PHP_VERSION,
				'table_prefix'      => (string) ( $database['table_prefix'] ?? '' ),
			),
			'tables'                      => $table_manifests,
			'table_count'                 => count( $table_manifests ),
			'row_count'                   => (int) ( $state['row_count'] ?? 0 ),
			'payload_bytes'               => (int) ( $state['byte_count'] ?? 0 ),
			'chunk_count'                 => (int) ( $state['chunk_count'] ?? 0 ),
			'production_source_read_only' => true,
			'credentials_in_payload'      => false,
			'contains_private_site_data'  => true,
			'repository_safe'             => false,
			'generated_at'                => gmdate( DATE_ATOM ),
		);

		$json = wp_json_encode( $manifest, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT );
		if ( ! is_string( $json ) ) {
			return $this->block( $job_id, $state, 'database-manifest-encode-failed', true );
		}
		$written = $this->workspace->write( $job_id, 'database/manifest.json', $json . "\n" );
		if ( null === $written ) {
			return $this->block( $job_id, $state, 'database-manifest-write-failed', true );
		}

		$state['status']                 = 'complete';
		$state['database_manifest_hash'] = (string) $written['sha256'];
		$state['updated_at']             = gmdate( DATE_ATOM );
		$state['completed_at']           = $state['updated_at'];

		if ( ! $this->store->save( $job_id, $state ) ) {
			return null;
		}
		$this->jobs->update_progress(
			$job_id,
			'database',
			'complete',
			array(
				'completed' => (int) ( $state['row_count'] ?? 0 ),
				'total'     => (int) ( $state['row_count'] ?? 0 ),
			)
		);
		return $this->store->get( $job_id );
	}

	/**
	 * Persist bounded export progress and the resumable job cursor.
	 *
	 * @param string              $job_id Clone job identifier.
	 * @param array<string,mixed> $state  Export state.
	 * @return array<string,mixed>|null
	 */
	private function persist_progress( string $job_id, array $state ): ?array {
		$state['updated_at'] = gmdate( DATE_ATOM );
		if ( ! $this->store->save( $job_id, $state ) ) {
			return null;
		}
		$cursor = (string) (int) ( $state['table_index'] ?? 0 ) . ':' . (string) (int) ( $state['chunk_index'] ?? 0 );
		$this->jobs->update_progress(
			$job_id,
			'database',
			$cursor,
			array(
				'completed' => (int) ( $state['row_count'] ?? 0 ),
				'total'     => null,
			)
		);
		return $this->store->get( $job_id );
	}

	/**
	 * Persist one bounded export blocker and job failure state.
	 *
	 * @param string              $job_id   Clone job identifier.
	 * @param array<string,mixed> $state    Export state.
	 * @param string              $code     Blocker code.
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
	 * Load one private table metadata file.
	 *
	 * @param string $job_id Clone job identifier.
	 * @param string $table  Source table.
	 * @return array<string,mixed>|null
	 */
	private function load_table_metadata( string $job_id, string $table ): ?array {
		$json = $this->workspace->read( $job_id, $this->table_directory( $table ) . '/table.json' );
		if ( null === $json ) {
			return null;
		}
		$decoded = json_decode( $json, true );
		return is_array( $decoded ) ? $decoded : null;
	}

	/**
	 * Persist one private table metadata file.
	 *
	 * @param string              $job_id Clone job identifier.
	 * @param string              $table  Source table.
	 * @param array<string,mixed> $meta   Metadata.
	 */
	private function save_table_metadata( string $job_id, string $table, array $meta ): bool {
		$json = wp_json_encode( $meta, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT );
		return is_string( $json )
			&& null !== $this->workspace->write( $job_id, $this->table_directory( $table ) . '/table.json', $json . "\n" );
	}

	/**
	 * Build one deterministic private table directory.
	 *
	 * @param string $table Source table.
	 */
	private function table_directory( string $table ): string {
		return 'database/tables/' . $this->table_slug( $table );
	}

	/**
	 * Build one deterministic table slug.
	 *
	 * @param string $table Source table.
	 */
	private function table_slug( string $table ): string {
		return substr( hash( 'sha256', $table ), 0, 20 );
	}

	/**
	 * Quote one already-inventoried MySQL identifier.
	 *
	 * @param string $identifier Identifier.
	 */
	private function quote_identifier( string $identifier ): string {
		$tick = chr( 96 );

		return $tick . str_replace( $tick, $tick . $tick, $identifier ) . $tick;
	}

	/**
	 * Validate that one table remains inside the inventoried WordPress prefix.
	 *
	 * @param string $table  Table name.
	 * @param string $prefix Inventoried prefix.
	 */
	private function accepted_source_table( string $table, string $prefix ): bool {
		return '' !== $prefix
			&& str_starts_with( $table, $prefix )
			&& 192 >= strlen( $table )
			&& 1 !== preg_match( '/[\x00-\x1F\x7F]/', $table );
	}

	/**
	 * Encode a primary-key cursor for bounded persistence.
	 *
	 * @param string $value Raw cursor.
	 */
	private function encode_cursor( string $value ): string {
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Cursor transport encoding, not code obfuscation.
		return rtrim( strtr( base64_encode( $value ), '+/', '-_' ), '=' );
	}

	/**
	 * Decode one bounded primary-key cursor.
	 *
	 * @param string $value Base64url cursor.
	 */
	private function decode_cursor( string $value ): ?string {
		if ( '' === $value ) {
			return null;
		}
		$padding = strlen( $value ) % 4;
		if ( 0 !== $padding ) {
			$value .= str_repeat( '=', 4 - $padding );
		}
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Cursor transport decoding, not code obfuscation.
		$decoded = base64_decode( strtr( $value, '-_', '+/' ), true );
		return false === $decoded ? null : $decoded;
	}
}
