<?php
/**
 * Portable Clone resumable isolated database restore.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge\Clone;

use wpdb;

/**
 * Restores verified database chunks into a job-owned isolated table prefix.
 */
final class DatabaseRestorer {
	public const MIN_BATCH_CHUNKS     = 1;
	public const MAX_BATCH_CHUNKS     = 20;
	public const DEFAULT_BATCH_CHUNKS = 5;

	private const MAX_TABLES      = 5000;
	private const MAX_COLUMNS     = 512;
	private const MAX_CHUNK_BYTES = 67108864;

	private DatabaseRestoreStateStore $store;
	private ImportStateStore $import_state;
	private ImportPayloadStateStore $payload_state;
	private CloneJobStore $jobs;
	private ImportPreflight $preflight;
	private ExportWorkspace $workspace;

	public function __construct(
		?DatabaseRestoreStateStore $store = null,
		?ImportStateStore $import_state = null,
		?ImportPayloadStateStore $payload_state = null,
		?CloneJobStore $jobs = null,
		?ImportPreflight $preflight = null,
		?ExportWorkspace $workspace = null
	) {
		$this->store         = $store ?? new DatabaseRestoreStateStore();
		$this->import_state  = $import_state ?? new ImportStateStore();
		$this->payload_state = $payload_state ?? new ImportPayloadStateStore();
		$this->jobs          = $jobs ?? new CloneJobStore();
		$this->workspace     = $workspace ?? new ExportWorkspace();
		$this->preflight     = $preflight ?? new ImportPreflight( $this->import_state, $this->jobs, $this->workspace );
	}

	/**
	 * Return one restore snapshot.
	 *
	 * @param string $job_id Clone job identifier.
	 * @return array<string,mixed>|null
	 */
	public function snapshot( string $job_id ): ?array {
		return $this->store->get( $job_id );
	}

	/**
	 * Initialize an isolated database restore without touching active WordPress tables.
	 *
	 * @param string $job_id Clone job identifier.
	 * @return array<string,mixed>|null
	 */
	public function start( string $job_id ): ?array {
		$existing = $this->store->get( $job_id );
		if ( is_array( $existing ) ) {
			return $existing;
		}

		if ( ! $this->restore_guard_valid( $job_id ) ) {
			return null;
		}

		$record = $this->database_manifest( $job_id );
		if ( null === $record ) {
			return null;
		}

		$manifest      = $record['manifest'];
		$source        = is_array( $manifest['source'] ?? null ) ? $manifest['source'] : array();
		$source_prefix = is_string( $source['table_prefix'] ?? null ) ? $source['table_prefix'] : '';
		$tables        = is_array( $manifest['tables'] ?? null ) ? array_values( $manifest['tables'] ) : array();
		$restore_prefix = $this->restore_prefix( $job_id );
		$journal_table  = $this->journal_table( $job_id );

		if (
			'' === $source_prefix
			|| 1 !== preg_match( '/^[A-Za-z0-9_]+$/', $source_prefix )
			|| count( $tables ) > self::MAX_TABLES
			|| null === $this->table_map( $tables, $source_prefix, $restore_prefix, $journal_table )
			|| ! $this->fresh_prefix_available( $restore_prefix, $journal_table )
		) {
			return null;
		}

		$now   = gmdate( DATE_ATOM );
		$state = array(
			'schema_version'           => DatabaseRestoreStateStore::SCHEMA_VERSION,
			'job_id'                   => $job_id,
			'status'                   => 'running',
			'stage'                    => 'prepare',
			'restore_prefix'           => $restore_prefix,
			'journal_table'            => $journal_table,
			'source_table_prefix'      => $source_prefix,
			'database_manifest_sha256' => $record['sha256'],
			'table_index'              => 0,
			'table_count'              => count( $tables ),
			'current_source_table'     => '',
			'current_target_table'     => '',
			'chunk_index'              => 0,
			'rows_restored'            => 0,
			'chunks_restored'          => 0,
			'tables_completed'         => 0,
			'blockers'                 => array(),
			'started_at'               => $now,
			'updated_at'               => $now,
			'completed_at'             => '',
		);

		if ( ! $this->store->save( $job_id, $state ) ) {
			return null;
		}

		$this->jobs->transition( $job_id, 'active' );
		$this->jobs->update_progress(
			$job_id,
			'prepare-target',
			'database-restore:prepare',
			array(
				'completed' => 0,
				'total'     => max( 0, (int) ( $manifest['row_count'] ?? 0 ) ),
			)
		);

		return $this->store->get( $job_id );
	}

	/**
	 * Advance a bounded number of verified database chunks.
	 *
	 * @param string $job_id       Clone job identifier.
	 * @param int    $batch_chunks Maximum chunks applied this request.
	 * @return array<string,mixed>|null
	 */
	public function advance( string $job_id, int $batch_chunks = self::DEFAULT_BATCH_CHUNKS ): ?array {
		$batch_chunks = max( self::MIN_BATCH_CHUNKS, min( self::MAX_BATCH_CHUNKS, $batch_chunks ) );
		$state        = $this->store->get( $job_id ) ?? $this->start( $job_id );
		if ( ! is_array( $state ) || in_array( $state['status'] ?? null, array( 'complete', 'blocked' ), true ) ) {
			return $state;
		}

		if ( array() !== ( $state['blockers'] ?? array() ) ) {
			$state['blockers']   = array();
			$state['updated_at'] = gmdate( DATE_ATOM );
			if ( ! $this->store->save( $job_id, $state ) ) {
				return null;
			}
		}

		if ( ! $this->restore_guard_valid( $job_id ) ) {
			return $this->block( $job_id, $state, 'database-restore-destination-guard-failed', true );
		}

		$record = $this->database_manifest( $job_id );
		if (
			null === $record
			|| ! hash_equals( (string) ( $state['database_manifest_sha256'] ?? '' ), $record['sha256'] )
		) {
			return $this->block( $job_id, $state, 'database-restore-manifest-changed', false );
		}

		$manifest = $record['manifest'];
		$tables   = is_array( $manifest['tables'] ?? null ) ? array_values( $manifest['tables'] ) : array();
		$map      = $this->table_map(
			$tables,
			(string) ( $state['source_table_prefix'] ?? '' ),
			(string) ( $state['restore_prefix'] ?? '' ),
			(string) ( $state['journal_table'] ?? '' )
		);
		if ( null === $map ) {
			return $this->block( $job_id, $state, 'database-restore-table-map-invalid', false );
		}

		if ( 'prepare' === ( $state['stage'] ?? null ) ) {
			if ( ! $this->ensure_journal( (string) $state['journal_table'] ) ) {
				return $this->block( $job_id, $state, 'database-restore-journal-unavailable', true );
			}
			$state['stage']      = 'schema';
			$state['updated_at'] = gmdate( DATE_ATOM );
			if ( ! $this->store->save( $job_id, $state ) ) {
				return null;
			}
		}

		$applied = 0;
		while ( $applied < $batch_chunks ) {
			$index = (int) ( $state['table_index'] ?? 0 );
			if ( $index >= count( $tables ) ) {
				return $this->complete_database( $job_id, $state, $manifest, $tables, $map );
			}

			$table        = is_array( $tables[ $index ] ?? null ) ? $tables[ $index ] : array();
			$source_table = is_string( $table['name'] ?? null ) ? $table['name'] : '';
			$target_table = $map[ $source_table ] ?? '';
			if ( '' === $source_table || '' === $target_table ) {
				return $this->block( $job_id, $state, 'database-restore-table-contract-invalid', false );
			}

			if (
				$source_table !== ( $state['current_source_table'] ?? '' )
				|| $target_table !== ( $state['current_target_table'] ?? '' )
			) {
				$state['stage']                = 'schema';
				$state['current_source_table'] = $source_table;
				$state['current_target_table'] = $target_table;
				$state['chunk_index']          = 0;
				$state['updated_at']           = gmdate( DATE_ATOM );
				if ( ! $this->store->save( $job_id, $state ) ) {
					return null;
				}
			}

			if ( 'schema' === ( $state['stage'] ?? null ) ) {
				$schema_result = $this->ensure_table_schema(
					$job_id,
					$table,
					$map,
					$target_table,
					(string) $state['journal_table']
				);
				if ( true !== $schema_result ) {
					return $this->block(
						$job_id,
						$state,
						is_string( $schema_result ) ? $schema_result : 'database-restore-schema-failed',
						false
					);
				}
				$state['stage']      = 'rows';
				$state['updated_at'] = gmdate( DATE_ATOM );
				if ( ! $this->store->save( $job_id, $state ) ) {
					return null;
				}
			}

			$chunks      = is_array( $table['chunks'] ?? null ) ? array_values( $table['chunks'] ) : array();
			$chunk_index = (int) ( $state['chunk_index'] ?? 0 );
			if ( $chunk_index >= count( $chunks ) ) {
				$completed = $this->complete_table( $state, $table, $target_table );
				if ( ! is_array( $completed ) ) {
					return $this->block( $job_id, $state, 'database-restore-table-row-count-mismatch', false );
				}
				$state = $completed;
				if ( ! $this->store->save( $job_id, $state ) ) {
					return null;
				}
				continue;
			}

			$chunk  = is_array( $chunks[ $chunk_index ] ?? null ) ? $chunks[ $chunk_index ] : array();
			$result = $this->apply_chunk(
				$job_id,
				(string) $state['journal_table'],
				$source_table,
				$target_table,
				$table,
				$chunk,
				$chunk_index
			);
			if ( ! is_array( $result ) ) {
				return $this->block( $job_id, $state, 'database-restore-chunk-failed', true );
			}
			if ( isset( $result['blocker'] ) && is_string( $result['blocker'] ) ) {
				return $this->block( $job_id, $state, $result['blocker'], false );
			}

			$state['chunk_index']     = $chunk_index + 1;
			$state['rows_restored']   = (int) ( $state['rows_restored'] ?? 0 ) + (int) $result['row_count'];
			$state['chunks_restored'] = (int) ( $state['chunks_restored'] ?? 0 ) + 1;
			$state['updated_at']      = gmdate( DATE_ATOM );
			if ( ! $this->store->save( $job_id, $state ) ) {
				return null;
			}

			++$applied;
		}

		return $this->persist_progress( $job_id, $state, $manifest );
	}

	/**
	 * Ensure destination safety and verified payload are still current.
	 *
	 * @param string $job_id Clone job identifier.
	 */
	private function restore_guard_valid( string $job_id ): bool {
		$job     = $this->jobs->get( $job_id );
		$payload = $this->payload_state->get( $job_id );
		$fresh   = $this->preflight->validate( $job_id );

		return is_array( $job )
			&& 'import' === ( $job['operation'] ?? null )
			&& is_array( $payload )
			&& 'complete' === ( $payload['status'] ?? null )
			&& 'complete' === ( $payload['stage'] ?? null )
			&& is_array( $fresh )
			&& 'payload-verified' === ( $fresh['status'] ?? null )
			&& true === ( $fresh['full_payload_verified'] ?? false )
			&& true === ( $fresh['restore_allowed'] ?? false )
			&& array() === ( $fresh['blockers'] ?? array() );
	}

	/**
	 * Load and bind the extracted database manifest to package metadata.
	 *
	 * @param string $job_id Clone job identifier.
	 * @return array{manifest:array<string,mixed>,sha256:string}|null
	 */
	private function database_manifest( string $job_id ): ?array {
		$db_info      = $this->workspace->import_extracted_file_info( $job_id, 'database/manifest.json' );
		$package_info = $this->workspace->import_extracted_file_info( $job_id, 'package/manifest.json' );
		if ( ! is_array( $db_info ) || ! is_array( $package_info ) ) {
			return null;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reads only verified job-owned private import payload.
		$db_json = file_get_contents( $db_info['path'] );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reads only verified job-owned private package metadata.
		$package_json = file_get_contents( $package_info['path'] );
		$manifest     = is_string( $db_json ) ? json_decode( $db_json, true ) : null;
		$package      = is_string( $package_json ) ? json_decode( $package_json, true ) : null;
		$payload      = is_array( $package['payload'] ?? null ) ? $package['payload'] : array();
		$db_ref       = is_array( $payload['database'] ?? null ) ? $payload['database'] : array();

		if (
			! is_array( $manifest )
			|| ! is_array( $package )
			|| 1 !== ( $manifest['schema_version'] ?? null )
			|| 'database' !== ( $manifest['payload_class'] ?? null )
			|| true !== ( $manifest['production_source_read_only'] ?? false )
			|| false !== ( $manifest['credentials_in_payload'] ?? true )
			|| 'database/manifest.json' !== ( $db_ref['manifest_path'] ?? null )
			|| ! is_string( $db_ref['manifest_sha256'] ?? null )
			|| ! hash_equals( (string) $db_ref['manifest_sha256'], (string) $db_info['sha256'] )
		) {
			return null;
		}

		return array(
			'manifest' => $manifest,
			'sha256'   => (string) $db_info['sha256'],
		);
	}

	/**
	 * Build the isolated source-to-target table map.
	 *
	 * @param list<mixed>          $tables         Database manifest tables.
	 * @param string               $source_prefix  Source table prefix.
	 * @param string               $restore_prefix Isolated restore prefix.
	 * @param string               $journal_table  Restore journal.
	 * @return array<string,string>|null
	 */
	private function table_map( array $tables, string $source_prefix, string $restore_prefix, string $journal_table ): ?array {
		if (
			'' === $source_prefix
			|| '' === $restore_prefix
			|| 1 !== preg_match( '/^[A-Za-z0-9_]+$/', $source_prefix )
			|| 1 !== preg_match( '/^[A-Za-z0-9_]+$/', $restore_prefix )
			|| count( $tables ) > self::MAX_TABLES
		) {
			return null;
		}

		$map     = array();
		$targets = array();
		foreach ( $tables as $table ) {
			if ( ! is_array( $table ) || ! is_string( $table['name'] ?? null ) ) {
				return null;
			}

			$source = $table['name'];
			if (
				1 !== preg_match( '/^[A-Za-z0-9_]+$/', $source )
				|| ! str_starts_with( $source, $source_prefix )
			) {
				return null;
			}

			$suffix = substr( $source, strlen( $source_prefix ) );
			$target = $restore_prefix . $suffix;
			if (
				'' === $suffix
				|| 64 < strlen( $target )
				|| $target === $journal_table
				|| isset( $targets[ $target ] )
			) {
				return null;
			}

			$map[ $source ]      = $target;
			$targets[ $target ] = true;
		}

		return $map;
	}

	/**
	 * Confirm a new isolated prefix cannot overwrite active/existing tables.
	 *
	 * @param string $restore_prefix Restore prefix.
	 * @param string $journal_table  Journal table.
	 */
	private function fresh_prefix_available( string $restore_prefix, string $journal_table ): bool {
		global $wpdb;
		if ( ! $wpdb instanceof wpdb || $wpdb->prefix === $restore_prefix || array() !== $this->tables_with_prefix( $restore_prefix ) ) {
			return false;
		}

		return ! $this->table_exists( $journal_table ) || 0 === $this->journal_count( $journal_table );
	}

	/**
	 * Ensure the idempotency journal exists as InnoDB.
	 *
	 * @param string $journal_table Journal table.
	 */
	private function ensure_journal( string $journal_table ): bool {
		global $wpdb;
		if ( ! $wpdb instanceof wpdb || ! $this->valid_identifier( $journal_table ) ) {
			return false;
		}

		if ( ! $this->table_exists( $journal_table ) ) {
			$quoted  = $this->quote_identifier( $journal_table );
			$collate = $wpdb->get_charset_collate();
			$sql     = "CREATE TABLE {$quoted} (
				id bigint unsigned NOT NULL AUTO_INCREMENT,
				source_table varchar(64) NOT NULL,
				chunk_index int unsigned NOT NULL,
				chunk_sha256 char(64) NOT NULL,
				row_count int unsigned NOT NULL,
				applied_at datetime NOT NULL,
				PRIMARY KEY (id),
				UNIQUE KEY source_chunk (source_table,chunk_index)
			) ENGINE=InnoDB {$collate}";
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.PreparedSQL.NotPrepared -- Creates only a deterministic isolated job journal after destination approval.
			if ( false === $wpdb->query( $sql ) ) {
				return false;
			}
		}

		return 'innodb' === strtolower( $this->table_engine( $journal_table ) )
			&& array( 'id', 'source_table', 'chunk_index', 'chunk_sha256', 'row_count', 'applied_at' ) === $this->table_columns( $journal_table );
	}

	/**
	 * Ensure one mapped target table exists and matches manifest columns.
	 *
	 * @param string               $job_id       Clone job identifier.
	 * @param array<string,mixed>  $table        Table manifest.
	 * @param array<string,string> $map          Source-to-target map.
	 * @param string               $target_table Target table.
	 * @param string               $journal_table Journal table.
	 * @return true|string
	 */
	private function ensure_table_schema(
		string $job_id,
		array $table,
		array $map,
		string $target_table,
		string $journal_table
	): true|string {
		global $wpdb;
		if ( ! $wpdb instanceof wpdb ) {
			return 'database-restore-wpdb-unavailable';
		}

		$columns = $this->manifest_columns( $table );
		$schema  = is_array( $table['schema'] ?? null ) ? $table['schema'] : array();
		$path    = is_string( $schema['path'] ?? null ) ? $schema['path'] : '';
		$hash    = is_string( $schema['sha256'] ?? null ) ? $schema['sha256'] : '';
		$bytes   = max( 0, (int) ( $schema['bytes'] ?? 0 ) );
		$info    = '' === $path ? null : $this->workspace->import_extracted_file_info( $job_id, $path );

		if (
			array() === $columns
			|| count( $columns ) > self::MAX_COLUMNS
			|| ! is_array( $info )
			|| (int) $info['bytes'] !== $bytes
			|| 1 !== preg_match( '/^[a-f0-9]{64}$/', $hash )
			|| ! hash_equals( $hash, (string) $info['sha256'] )
		) {
			return 'database-restore-schema-evidence-invalid';
		}

		if ( ! $this->table_exists( $target_table ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reads only verified private schema payload.
			$source_sql = file_get_contents( $info['path'] );
			if ( ! is_string( $source_sql ) ) {
				return 'database-restore-schema-read-failed';
			}

			$sql = $this->rewrite_schema( $source_sql, $map, $target_table );
			if ( null === $sql ) {
				return 'database-restore-schema-unsupported';
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- Session-only safety for isolated verified DDL.
			$wpdb->query( 'SET FOREIGN_KEY_CHECKS=0' );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.PreparedSQL.NotPrepared -- Executes rewritten verified SHOW CREATE TABLE DDL only into isolated prefix.
			$created = $wpdb->query( $sql );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- Restore session FK checks immediately.
			$wpdb->query( 'SET FOREIGN_KEY_CHECKS=1' );
			if ( false === $created ) {
				return 'database-restore-schema-create-failed';
			}
		}

		if (
			'innodb' !== strtolower( $this->table_engine( $target_table ) )
			|| $columns !== $this->table_columns( $target_table )
		) {
			return 'database-restore-schema-mismatch';
		}

		$source_table = is_string( $table['name'] ?? null ) ? $table['name'] : '';
		if ( 0 === $this->journal_source_count( $journal_table, $source_table ) && 0 !== $this->table_row_count( $target_table ) ) {
			return 'database-restore-unowned-target-rows';
		}

		return true;
	}

	/**
	 * Apply one verified chunk transactionally with a durable journal marker.
	 *
	 * @param string              $job_id        Clone job identifier.
	 * @param string              $journal_table Journal table.
	 * @param string              $source_table  Source table.
	 * @param string              $target_table  Isolated target table.
	 * @param array<string,mixed> $table         Table manifest.
	 * @param array<string,mixed> $chunk         Chunk manifest.
	 * @param int                 $chunk_index   Expected chunk index.
	 * @return array{row_count:int,recovered:bool}|array{blocker:string}|null
	 */
	private function apply_chunk(
		string $job_id,
		string $journal_table,
		string $source_table,
		string $target_table,
		array $table,
		array $chunk,
		int $chunk_index
	): ?array {
		global $wpdb;
		if ( ! $wpdb instanceof wpdb ) {
			return null;
		}

		$path          = is_string( $chunk['path'] ?? null ) ? $chunk['path'] : '';
		$hash          = is_string( $chunk['sha256'] ?? null ) ? $chunk['sha256'] : '';
		$bytes         = max( 0, (int) ( $chunk['byte_count'] ?? 0 ) );
		$declared_rows = max( 0, (int) ( $chunk['row_count'] ?? 0 ) );

		if (
			$chunk_index !== (int) ( $chunk['index'] ?? -1 )
			|| '' === $path
			|| self::MAX_CHUNK_BYTES < $bytes
			|| 1 !== preg_match( '/^[a-f0-9]{64}$/', $hash )
		) {
			return array( 'blocker' => 'database-restore-chunk-contract-invalid' );
		}

		$info = $this->workspace->import_extracted_file_info( $job_id, $path );
		if (
			! is_array( $info )
			|| $bytes !== (int) $info['bytes']
			|| ! hash_equals( $hash, (string) $info['sha256'] )
		) {
			return array( 'blocker' => 'database-restore-chunk-evidence-mismatch' );
		}

		$marker = $this->journal_marker( $journal_table, $source_table, $chunk_index );
		if ( is_array( $marker ) ) {
			if (
				! hash_equals( $hash, (string) ( $marker['chunk_sha256'] ?? '' ) )
				|| $declared_rows !== (int) ( $marker['row_count'] ?? -1 )
			) {
				return array( 'blocker' => 'database-restore-journal-mismatch' );
			}

			return array(
				'row_count' => $declared_rows,
				'recovered' => true,
			);
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reads one verified private database chunk.
		$json    = file_get_contents( $info['path'] );
		$data    = is_string( $json ) ? json_decode( $json, true ) : null;
		$columns = $this->manifest_columns( $table );

		if (
			! is_array( $data )
			|| 1 !== ( $data['schema_version'] ?? null )
			|| $source_table !== ( $data['table'] ?? null )
			|| $chunk_index !== (int) ( $data['chunk_index'] ?? -1 )
			|| 'base64-or-null' !== ( $data['value_encoding'] ?? null )
			|| $columns !== ( is_array( $data['columns'] ?? null ) ? array_values( $data['columns'] ) : array() )
			|| ! is_array( $data['rows'] ?? null )
			|| $declared_rows !== count( $data['rows'] )
			|| $declared_rows !== (int) ( $data['row_count'] ?? -1 )
		) {
			return array( 'blocker' => 'database-restore-chunk-json-invalid' );
		}

		if (
			'innodb' !== strtolower( $this->table_engine( $target_table ) )
			|| 'innodb' !== strtolower( $this->table_engine( $journal_table ) )
		) {
			return array( 'blocker' => 'database-restore-transactional-engine-required' );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- Session-scoped FK suspension for one isolated chunk transaction.
		$wpdb->query( 'SET FOREIGN_KEY_CHECKS=0' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- Begins atomic isolated-table and journal mutation.
		if ( false === $wpdb->query( 'START TRANSACTION' ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- Restore session FK checks after failed transaction start.
			$wpdb->query( 'SET FOREIGN_KEY_CHECKS=1' );
			return null;
		}

		$ok = true;
		foreach ( $data['rows'] as $row ) {
			if ( ! is_array( $row ) || count( $row ) !== count( $columns ) ) {
				$ok = false;
				break;
			}

			$decoded = $this->decode_row( array_values( $row ) );
			if ( null === $decoded || ! $this->insert_row( $target_table, $columns, $decoded ) ) {
				$ok = false;
				break;
			}
		}

		if ( $ok ) {
			$sql = $wpdb->prepare(
				'INSERT INTO ' . $this->quote_identifier( $journal_table )
				. ' (source_table,chunk_index,chunk_sha256,row_count,applied_at) VALUES (%s,%d,%s,%d,%s)',
				$source_table,
				$chunk_index,
				$hash,
				$declared_rows,
				gmdate( 'Y-m-d H:i:s' )
			);
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- Journal marker is committed atomically with restored rows.
			$ok = is_string( $sql ) && false !== $wpdb->query( $sql );
		}

		if ( $ok ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- Commit isolated rows and journal marker.
			$ok = false !== $wpdb->query( 'COMMIT' );
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- Roll back incomplete isolated chunk.
			$wpdb->query( 'ROLLBACK' );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- Restore session FK checks.
		$wpdb->query( 'SET FOREIGN_KEY_CHECKS=1' );

		return $ok
			? array(
				'row_count' => $declared_rows,
				'recovered' => false,
			)
			: null;
	}

	/**
	 * Decode one binary-safe transport row.
	 *
	 * @param list<mixed> $row Encoded values.
	 * @return list<string|null>|null
	 */
	private function decode_row( array $row ): ?array {
		$decoded = array();
		foreach ( $row as $value ) {
			if ( null === $value ) {
				$decoded[] = null;
				continue;
			}
			if ( ! is_string( $value ) ) {
				return null;
			}

			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Migration transport decoding, not code obfuscation.
			$raw = base64_decode( $value, true );
			if ( false === $raw ) {
				return null;
			}
			$decoded[] = $raw;
		}

		return $decoded;
	}

	/**
	 * Insert one decoded row into an isolated target table.
	 *
	 * @param string            $table   Target table.
	 * @param list<string>      $columns Columns.
	 * @param list<string|null> $values  Decoded values.
	 */
	private function insert_row( string $table, array $columns, array $values ): bool {
		global $wpdb;
		if ( ! $wpdb instanceof wpdb || count( $columns ) !== count( $values ) ) {
			return false;
		}

		$quoted_columns = array_map( fn( string $column ): string => $this->quote_identifier( $column ), $columns );
		$placeholders   = array();
		$args           = array();

		foreach ( $values as $value ) {
			if ( null === $value ) {
				$placeholders[] = 'NULL';
			} else {
				$placeholders[] = '%s';
				$args[]         = $value;
			}
		}

		$sql = 'INSERT INTO ' . $this->quote_identifier( $table )
			. ' (' . implode( ',', $quoted_columns ) . ') VALUES (' . implode( ',', $placeholders ) . ')';
		if ( array() !== $args ) {
			$sql = $wpdb->prepare( $sql, ...$args );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- Writes exactly one verified row into the isolated restore prefix.
		return is_string( $sql ) && false !== $wpdb->query( $sql );
	}

	/**
	 * Complete one table after exact row reconciliation.
	 *
	 * @param array<string,mixed> $state        Restore state.
	 * @param array<string,mixed> $table        Table manifest.
	 * @param string              $target_table Target table.
	 * @return array<string,mixed>|null
	 */
	private function complete_table( array $state, array $table, string $target_table ): ?array {
		$expected = max( 0, (int) ( $table['row_count'] ?? 0 ) );
		if ( $expected !== $this->table_row_count( $target_table ) ) {
			return null;
		}

		$state['table_index']          = (int) ( $state['table_index'] ?? 0 ) + 1;
		$state['tables_completed']     = (int) ( $state['tables_completed'] ?? 0 ) + 1;
		$state['current_source_table'] = '';
		$state['current_target_table'] = '';
		$state['chunk_index']          = 0;
		$state['stage']                = 'schema';
		$state['updated_at']           = gmdate( DATE_ATOM );

		return $state;
	}

	/**
	 * Complete database restore after exact reconciliation.
	 *
	 * @param string               $job_id   Clone job identifier.
	 * @param array<string,mixed>  $state    Restore state.
	 * @param array<string,mixed>  $manifest Database manifest.
	 * @param list<mixed>          $tables   Tables.
	 * @param array<string,string> $map      Table map.
	 * @return array<string,mixed>|null
	 */
	private function complete_database(
		string $job_id,
		array $state,
		array $manifest,
		array $tables,
		array $map
	): ?array {
		if (
			count( $tables ) !== (int) ( $manifest['table_count'] ?? -1 )
			|| count( $tables ) !== (int) ( $state['tables_completed'] ?? -2 )
			|| (int) ( $manifest['row_count'] ?? -1 ) !== (int) ( $state['rows_restored'] ?? -2 )
			|| (int) ( $manifest['chunk_count'] ?? -1 ) !== (int) ( $state['chunks_restored'] ?? -2 )
		) {
			return $this->block( $job_id, $state, 'database-restore-final-count-mismatch', false );
		}

		foreach ( $tables as $table ) {
			if ( ! is_array( $table ) || ! is_string( $table['name'] ?? null ) ) {
				return $this->block( $job_id, $state, 'database-restore-final-table-invalid', false );
			}
			$target = $map[ $table['name'] ] ?? '';
			if ( '' === $target || max( 0, (int) ( $table['row_count'] ?? 0 ) ) !== $this->table_row_count( $target ) ) {
				return $this->block( $job_id, $state, 'database-restore-final-table-count-mismatch', false );
			}
		}

		$now                   = gmdate( DATE_ATOM );
		$state['status']       = 'complete';
		$state['stage']        = 'complete';
		$state['updated_at']   = $now;
		$state['completed_at'] = $now;
		if ( ! $this->store->save( $job_id, $state ) ) {
			return null;
		}

		$this->jobs->transition( $job_id, 'active' );
		$this->jobs->update_progress(
			$job_id,
			'restore-database',
			'complete',
			array(
				'completed' => (int) $state['rows_restored'],
				'total'     => (int) ( $manifest['row_count'] ?? 0 ),
			)
		);

		return $this->store->get( $job_id );
	}

	/**
	 * Persist bounded progress.
	 *
	 * @param string              $job_id  Clone job identifier.
	 * @param array<string,mixed> $state   Restore state.
	 * @param array<string,mixed> $manifest Database manifest.
	 * @return array<string,mixed>|null
	 */
	private function persist_progress( string $job_id, array $state, array $manifest ): ?array {
		$state['updated_at'] = gmdate( DATE_ATOM );
		if ( ! $this->store->save( $job_id, $state ) ) {
			return null;
		}

		$cursor = (string) (int) ( $state['table_index'] ?? 0 )
			. ':' . (string) (int) ( $state['chunk_index'] ?? 0 );
		$this->jobs->transition( $job_id, 'active' );
		$this->jobs->update_progress(
			$job_id,
			'restore-database',
			$cursor,
			array(
				'completed' => (int) ( $state['rows_restored'] ?? 0 ),
				'total'     => max( 0, (int) ( $manifest['row_count'] ?? 0 ) ),
			)
		);

		return $this->store->get( $job_id );
	}

	/**
	 * Rewrite verified SHOW CREATE TABLE DDL only to the isolated table map.
	 *
	 * @param string               $source_sql   Source DDL.
	 * @param array<string,string> $map          Table map.
	 * @param string               $target_table Expected target table.
	 */
	private function rewrite_schema( string $source_sql, array $map, string $target_table ): ?string {
		$tick = chr( 96 );
		$sql  = trim( $source_sql );

		foreach ( $map as $source => $target ) {
			$sql = str_replace( $tick . $source . $tick, $tick . $target . $tick, $sql );
		}

		$sql = preg_replace_callback(
			'/\bCONSTRAINT\s+\x60([^\x60]+)\x60/i',
			static function ( array $match ) use ( $target_table, $tick ): string {
				$name = 'sgmc_' . substr( hash( 'sha256', $target_table . '|' . (string) $match[1] ), 0, 20 );
				return 'CONSTRAINT ' . $tick . $name . $tick;
			},
			$sql
		);

		if (
			! is_string( $sql )
			|| 1 !== preg_match( '/^CREATE\s+TABLE\s+\x60' . preg_quote( $target_table, '/' ) . '\x60\s*\(/i', $sql )
			|| 1 !== preg_match( '/\bENGINE\s*=\s*InnoDB\b/i', $sql )
		) {
			return null;
		}

		return rtrim( $sql, "; \t\r\n" );
	}

	/**
	 * Return validated manifest columns.
	 *
	 * @param array<string,mixed> $table Table manifest.
	 * @return list<string>
	 */
	private function manifest_columns( array $table ): array {
		$columns = is_array( $table['columns'] ?? null ) ? array_values( $table['columns'] ) : array();
		if ( array() === $columns || count( $columns ) > self::MAX_COLUMNS ) {
			return array();
		}

		$validated = array();
		foreach ( $columns as $column ) {
			if ( ! is_string( $column ) || '' === $column || 64 < strlen( $column ) || str_contains( $column, "\0" ) ) {
				return array();
			}
			$validated[] = $column;
		}

		return count( array_unique( $validated ) ) === count( $validated ) ? $validated : array();
	}

	/**
	 * Return one durable journal marker.
	 *
	 * @param string $journal_table Journal table.
	 * @param string $source_table  Source table.
	 * @param int    $chunk_index   Chunk index.
	 * @return array<string,mixed>|null
	 */
	private function journal_marker( string $journal_table, string $source_table, int $chunk_index ): ?array {
		global $wpdb;
		if ( ! $wpdb instanceof wpdb ) {
			return null;
		}

		$sql = $wpdb->prepare(
			'SELECT chunk_sha256,row_count FROM ' . $this->quote_identifier( $journal_table )
			. ' WHERE source_table=%s AND chunk_index=%d LIMIT 1',
			$source_table,
			$chunk_index
		);
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- Reads only isolated restore journal.
		$row = is_string( $sql ) ? $wpdb->get_row( $sql, ARRAY_A ) : null;

		return is_array( $row ) ? $row : null;
	}

	/**
	 * Count journal rows for one source table.
	 *
	 * @param string $journal_table Journal table.
	 * @param string $source_table  Source table.
	 */
	private function journal_source_count( string $journal_table, string $source_table ): int {
		global $wpdb;
		if ( ! $wpdb instanceof wpdb || ! $this->table_exists( $journal_table ) ) {
			return 0;
		}

		$sql = $wpdb->prepare(
			'SELECT COUNT(*) FROM ' . $this->quote_identifier( $journal_table ) . ' WHERE source_table=%s',
			$source_table
		);
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- Counts only isolated restore journal rows.
		return is_string( $sql ) ? (int) $wpdb->get_var( $sql ) : 0;
	}

	/**
	 * Return number of journal rows.
	 *
	 * @param string $journal_table Journal table.
	 */
	private function journal_count( string $journal_table ): int {
		global $wpdb;
		if ( ! $wpdb instanceof wpdb || ! $this->table_exists( $journal_table ) ) {
			return 0;
		}

		$sql = 'SELECT COUNT(*) FROM ' . $this->quote_identifier( $journal_table );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- Counts only deterministic job journal.
		return (int) $wpdb->get_var( $sql );
	}

	/**
	 * List current-database tables with one escaped prefix.
	 *
	 * @param string $prefix Prefix.
	 * @return list<string>
	 */
	private function tables_with_prefix( string $prefix ): array {
		global $wpdb;
		if ( ! $wpdb instanceof wpdb ) {
			return array();
		}

		$like = $wpdb->esc_like( $prefix ) . '%';
		$sql  = $wpdb->prepare(
			'SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME LIKE %s ORDER BY TABLE_NAME',
			$like
		);
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- Read-only destination ownership check.
		$rows = is_string( $sql ) ? $wpdb->get_col( $sql ) : array();

		return array_values( array_filter( $rows, 'is_string' ) );
	}

	/**
	 * Return whether one exact table exists.
	 *
	 * @param string $table Table.
	 */
	private function table_exists( string $table ): bool {
		global $wpdb;
		if ( ! $wpdb instanceof wpdb || ! $this->valid_identifier( $table ) ) {
			return false;
		}

		$sql = $wpdb->prepare(
			'SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s LIMIT 1',
			$table
		);
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- Read-only exact destination table check.
		return is_string( $sql ) && $table === $wpdb->get_var( $sql );
	}

	/**
	 * Return table engine.
	 *
	 * @param string $table Table.
	 */
	private function table_engine( string $table ): string {
		global $wpdb;
		if ( ! $wpdb instanceof wpdb || ! $this->valid_identifier( $table ) ) {
			return '';
		}

		$sql = $wpdb->prepare(
			'SELECT ENGINE FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s LIMIT 1',
			$table
		);
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- Read-only engine check.
		$engine = is_string( $sql ) ? $wpdb->get_var( $sql ) : null;

		return is_string( $engine ) ? $engine : '';
	}

	/**
	 * Return exact table columns in ordinal order.
	 *
	 * @param string $table Table.
	 * @return list<string>
	 */
	private function table_columns( string $table ): array {
		global $wpdb;
		if ( ! $wpdb instanceof wpdb || ! $this->valid_identifier( $table ) ) {
			return array();
		}

		$sql = $wpdb->prepare(
			'SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s ORDER BY ORDINAL_POSITION',
			$table
		);
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- Read-only schema verification.
		$columns = is_string( $sql ) ? $wpdb->get_col( $sql ) : array();

		return array_values( array_filter( $columns, 'is_string' ) );
	}

	/**
	 * Return exact row count.
	 *
	 * @param string $table Table.
	 */
	private function table_row_count( string $table ): int {
		global $wpdb;
		if ( ! $wpdb instanceof wpdb || ! $this->valid_identifier( $table ) || ! $this->table_exists( $table ) ) {
			return -1;
		}

		$sql = 'SELECT COUNT(*) FROM ' . $this->quote_identifier( $table );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- Exact count on isolated job restore table.
		return (int) $wpdb->get_var( $sql );
	}

	/**
	 * Derive isolated restore prefix from immutable job ID.
	 *
	 * @param string $job_id Job identifier.
	 */
	private function restore_prefix( string $job_id ): string {
		return 'sgm_' . substr( hash( 'sha256', $job_id ), 0, 10 ) . '_';
	}

	/**
	 * Derive isolated idempotency journal table.
	 *
	 * @param string $job_id Job identifier.
	 */
	private function journal_table( string $job_id ): string {
		return 'sgmj_' . substr( hash( 'sha256', $job_id ), 0, 12 );
	}

	/**
	 * Validate one bounded SQL table identifier.
	 *
	 * @param string $identifier Identifier.
	 */
	private function valid_identifier( string $identifier ): bool {
		return '' !== $identifier
			&& 64 >= strlen( $identifier )
			&& 1 === preg_match( '/^[A-Za-z0-9_]+$/', $identifier );
	}

	/**
	 * Quote one validated MySQL identifier.
	 *
	 * @param string $identifier Identifier.
	 */
	private function quote_identifier( string $identifier ): string {
		$tick = chr( 96 );

		return $tick . str_replace( $tick, $tick . $tick, $identifier ) . $tick;
	}

	/**
	 * Persist one restore blocker.
	 *
	 * Retryable blockers preserve resumable state; terminal blockers lock restore eligibility.
	 *
	 * @param string              $job_id    Clone job identifier.
	 * @param array<string,mixed> $state     Restore state.
	 * @param string              $code      Blocker code.
	 * @param bool                $retryable Retry allowed.
	 * @return array<string,mixed>|null
	 */
	private function block( string $job_id, array $state, string $code, bool $retryable ): ?array {
		$blockers            = is_array( $state['blockers'] ?? null ) ? $state['blockers'] : array();
		$blockers[]          = $code;
		$state['status']     = $retryable ? 'running' : 'blocked';
		$state['blockers']   = array_values( array_unique( $blockers ) );
		$state['updated_at'] = gmdate( DATE_ATOM );
		$this->store->save( $job_id, $state );

		if ( ! $retryable ) {
			$import = $this->import_state->get( $job_id );
			if ( is_array( $import ) ) {
				$import_blockers           = is_array( $import['blockers'] ?? null ) ? $import['blockers'] : array();
				$import_blockers[]         = $code;
				$import['status']          = 'blocked';
				$import['blockers']        = array_values( array_unique( $import_blockers ) );
				$import['restore_allowed'] = false;
				$import['updated_at']      = gmdate( DATE_ATOM );
				$this->import_state->save( $job_id, $import );
			}
		}

		$this->jobs->transition( $job_id, $retryable ? 'failed-retryable' : 'failed-terminal', $code );

		return $this->store->get( $job_id );
	}
}
