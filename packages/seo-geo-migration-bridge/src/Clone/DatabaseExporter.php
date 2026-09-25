<?php
/**
 * Portable Clone Engine resumable database exporter.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge\Clone;

use wpdb;

/**
 * Exports WordPress-prefix database rows into deterministic private chunks.
 */
final class DatabaseExporter {
	public const MIN_ROW_BATCH = 25;
	public const MAX_ROW_BATCH = 1000;
	public const DEFAULT_ROW_BATCH = 250;

	private CloneWorkspace $workspace;

	public function __construct( ?CloneWorkspace $workspace = null ) {
		$this->workspace = $workspace ?? new CloneWorkspace();
	}

	/**
	 * Advance one bounded database export chunk.
	 *
	 * @param array<string,mixed> $state     Export state.
	 * @param array<string,mixed> $inventory Completed source inventory.
	 * @param int                 $row_batch Maximum rows in this request.
	 * @return array<string,mixed>
	 */
	public function advance( array $state, array $inventory, int $row_batch = self::DEFAULT_ROW_BATCH ): array {
		$job_id = is_string( $state['job_id'] ?? null ) ? $state['job_id'] : '';
		$tables = is_array( $inventory['database']['tables'] ?? null ) ? $inventory['database']['tables'] : array();
		if ( '' === $job_id || ! $this->workspace->prepare( $job_id ) ) {
			return $this->block( $state, 'private-workspace-unavailable' );
		}

		$row_batch   = max( self::MIN_ROW_BATCH, min( self::MAX_ROW_BATCH, $row_batch ) );
		$table_index = max( 0, (int) ( $state['db_table_index'] ?? 0 ) );

		if ( $table_index >= count( $tables ) ) {
			$state['phase']             = 'files';
			$state['file_root_index']   = 0;
			$state['file_pending_dirs'] = array( '' );
			$state['file_current_dir']  = '';
			$state['file_after_name']   = '';
			return $state;
		}

		$table = $tables[ $table_index ] ?? null;
		if ( ! is_array( $table ) || ! is_string( $table['name'] ?? null ) || '' === $table['name'] ) {
			return $this->block( $state, 'database-table-metadata-invalid' );
		}

		global $wpdb;
		if ( ! $wpdb instanceof wpdb ) {
			return $this->block( $state, 'database-runtime-unavailable' );
		}

		$table_name = $table['name'];
		$table_dir  = $this->workspace->table_dir( $job_id, $table_name );
		if ( ! is_dir( $table_dir ) && ! wp_mkdir_p( $table_dir ) ) {
			return $this->block( $state, 'database-workspace-unavailable' );
		}

		$offset      = max( 0, (int) ( $state['db_offset'] ?? 0 ) );
		$chunk_index = max( 0, (int) ( $state['db_chunk_index'] ?? 0 ) );

		if ( 0 === $offset && ! is_file( $table_dir . 'schema.json' ) ) {
			$schema = $this->schema( $wpdb, $table_name );
			if ( null === $schema || ! $this->write_json_atomic( $table_dir . 'schema.json', $schema ) ) {
				return $this->block( $state, 'database-schema-export-failed' );
			}
		}

		$quoted = $this->quote_identifier( $table_name );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Read-only export from a DB-derived escaped table identifier; LIMIT/OFFSET are prepared.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$quoted} LIMIT %d OFFSET %d",
				$row_batch,
				$offset
			),
			ARRAY_A
		);

		if ( ! is_array( $rows ) ) {
			return $this->block( $state, 'database-row-export-failed' );
		}

		if ( array() === $rows ) {
			$state['db_table_index'] = $table_index + 1;
			$state['db_offset']      = 0;
			$state['db_chunk_index'] = 0;
			return $state;
		}

		$payload = '';
		foreach ( $rows as $row ) {
			$payload .= base64_encode( serialize( $row ) ) . "\n";
		}

		$chunk_name = sprintf( 'chunk-%08d.rows', $chunk_index );
		$chunk_path = $table_dir . $chunk_name;
		if ( ! $this->write_atomic( $chunk_path, $payload ) ) {
			return $this->block( $state, 'database-chunk-write-failed' );
		}

		$sha = hash_file( 'sha256', $chunk_path );
		if ( false === $sha ) {
			return $this->block( $state, 'database-chunk-hash-failed' );
		}

		$metadata = array(
			'table'        => $table_name,
			'chunk'        => $chunk_index,
			'offset'       => $offset,
			'rows'         => count( $rows ),
			'bytes'        => strlen( $payload ),
			'sha256'       => $sha,
			'encoding'     => 'php-serialize-base64-lines',
			'query_method' => 'limit-offset-read-only',
		);
		$metadata_path = $this->workspace->database_metadata_dir( $job_id )
			. hash( 'sha256', $table_name . '|' . (string) $chunk_index ) . '.json';
		if ( ! $this->write_json_atomic( $metadata_path, $metadata ) ) {
			return $this->block( $state, 'database-chunk-metadata-write-failed' );
		}

		$exported                    = count( $rows );
		$state['db_offset']          = $offset + $exported;
		$state['db_chunk_index']     = $chunk_index + 1;
		$state['db_rows_exported']   = (int) ( $state['db_rows_exported'] ?? 0 ) + $exported;
		$state['db_chunks_exported'] = (int) ( $state['db_chunks_exported'] ?? 0 ) + 1;

		if ( $exported < $row_batch ) {
			$state['db_table_index'] = $table_index + 1;
			$state['db_offset']      = 0;
			$state['db_chunk_index'] = 0;
		}

		return $state;
	}

	/**
	 * Capture one table schema without exporting credentials.
	 *
	 * @param wpdb   $wpdb       WordPress database connection.
	 * @param string $table_name Table name.
	 * @return array<string,mixed>|null
	 */
	private function schema( wpdb $wpdb, string $table_name ): ?array {
		$quoted = $this->quote_identifier( $table_name );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Read-only schema query using a DB-derived escaped identifier.
		$row = $wpdb->get_row( "SHOW CREATE TABLE {$quoted}", ARRAY_A );
		if ( ! is_array( $row ) ) {
			return null;
		}

		$create_sql = null;
		foreach ( $row as $key => $value ) {
			if ( is_string( $key ) && str_starts_with( strtolower( $key ), 'create ' ) && is_string( $value ) ) {
				$create_sql = $value;
				break;
			}
		}

		if ( null === $create_sql ) {
			return null;
		}

		return array(
			'table'      => $table_name,
			'create_sql' => $create_sql,
			'sha256'     => hash( 'sha256', $create_sql ),
		);
	}

	/**
	 * Escape one DB-derived table identifier for quoting.
	 */
	private function quote_identifier( string $identifier ): string {
		$quote = chr( 96 );
		return $quote . str_replace( $quote, $quote . $quote, $identifier ) . $quote;
	}

	/**
	 * Write JSON atomically inside the private workspace.
	 *
	 * @param string              $path    Destination path.
	 * @param array<string,mixed> $payload JSON payload.
	 */
	private function write_json_atomic( string $path, array $payload ): bool {
		$json = wp_json_encode( $payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		return is_string( $json ) && $this->write_atomic( $path, $json . "\n" );
	}

	/**
	 * Write one deterministic private payload file atomically.
	 */
	private function write_atomic( string $path, string $contents ): bool {
		$tmp = $path . '.tmp';
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Private local migration payload requires resumable atomic writes.
		$bytes = file_put_contents( $tmp, $contents, LOCK_EX );
		if ( false === $bytes || $bytes !== strlen( $contents ) ) {
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
