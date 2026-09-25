<?php
/**
 * Portable Clone resumable database restore into job-owned staging tables.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge\Clone;

use wpdb;

/**
 * Restores verified database chunks into isolated staging tables only.
 */
final class ImportDatabaseRestorer {
	public const MIN_BATCH_ROWS     = 10;
	public const MAX_BATCH_ROWS     = 500;
	public const DEFAULT_BATCH_ROWS = 100;

	private const MAX_TABLES = 1000;

	/**
	 * Database restore state.
	 *
	 * @var ImportDatabaseStateStore
	 */
	private ImportDatabaseStateStore $store;

	/**
	 * Import state.
	 *
	 * @var ImportStateStore
	 */
	private ImportStateStore $import_state;

	/**
	 * Verified payload state.
	 *
	 * @var ImportPayloadStateStore
	 */
	private ImportPayloadStateStore $payload_state;

	/**
	 * Clone jobs.
	 *
	 * @var CloneJobStore
	 */
	private CloneJobStore $jobs;

	/**
	 * Fresh destination preflight.
	 *
	 * @var ImportPreflight
	 */
	private ImportPreflight $preflight;

	/**
	 * Private workspace.
	 *
	 * @var ExportWorkspace
	 */
	private ExportWorkspace $workspace;

	/**
	 * Construct restorer.
	 *
	 * @param ImportDatabaseStateStore|null $store         Optional restore-state store.
	 * @param ImportStateStore|null         $import_state  Optional import-state store.
	 * @param ImportPayloadStateStore|null  $payload_state Optional payload-state store.
	 * @param CloneJobStore|null            $jobs          Optional clone-job store.
	 * @param ImportPreflight|null          $preflight     Optional fresh preflight service.
	 * @param ExportWorkspace|null          $workspace     Optional private workspace.
	 */
	public function __construct(
		?ImportDatabaseStateStore $store = null,
		?ImportStateStore $import_state = null,
		?ImportPayloadStateStore $payload_state = null,
		?CloneJobStore $jobs = null,
		?ImportPreflight $preflight = null,
		?ExportWorkspace $workspace = null
	) {
		$this->store         = $store ?? new ImportDatabaseStateStore();
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
	 * Initialize staging restore after full payload verification and fresh preflight.
	 *
	 * @param string $job_id Clone job identifier.
	 * @return array<string,mixed>|null
	 */
	public function start( string $job_id ): ?array {
		$existing = $this->store->get( $job_id );
		if ( is_array( $existing ) ) {
			return $existing;
		}

		$fresh = $this->fresh_restore_gate( $job_id );
		if ( null === $fresh ) {
			return null;
		}

		$manifest_bundle = $this->database_manifest( $job_id );
		if ( null === $manifest_bundle ) {
			return null;
		}

		$manifest = $manifest_bundle['manifest'];
		$source   = is_array( $manifest['source'] ?? null ) ? $manifest['source'] : array();
		$tables   = is_array( $manifest['tables'] ?? null ) ? array_values( $manifest['tables'] ) : array();

		global $wpdb;
		if ( ! $wpdb instanceof wpdb ) {
			return null;
		}

		$source_prefix      = is_string( $source['table_prefix'] ?? null ) ? $source['table_prefix'] : '';
		$destination_prefix = $wpdb->prefix;
		$namespace          = $this->staging_namespace( $job_id, $destination_prefix );
		if (
			'' === $source_prefix
			|| (string) ( $fresh['source_table_prefix'] ?? '' ) !== $source_prefix
			|| (string) ( $fresh['destination_table_prefix'] ?? '' ) !== $destination_prefix
			|| '' === $namespace
			|| ! $this->options_table_transactional()
			|| ! $this->manifest_valid( $manifest, $source_prefix )
			|| ! $this->staging_namespace_empty( $namespace )
		) {
			return null;
		}

		$payload = $this->payload_state->get( $job_id );
		if ( ! is_array( $payload ) ) {
			return null;
		}

		$now   = gmdate( DATE_ATOM );
		$state = array(
			'schema_version'           => ImportDatabaseStateStore::SCHEMA_VERSION,
			'job_id'                   => $job_id,
			'status'                   => 'running',
			'stage'                    => 'schema',
			'database_manifest_sha256' => (string) $manifest_bundle['sha256'],
			'payload_archive_sha256'   => (string) ( $payload['archive_sha256'] ?? '' ),
			'source_prefix'            => $source_prefix,
			'destination_prefix'       => $destination_prefix,
			'staging_namespace'        => $namespace,
			'table_index'              => 0,
			'table_count'              => count( $tables ),
			'current_source_table'     => '',
			'current_target_table'     => '',
			'current_staging_table'    => '',
			'chunk_index'              => 0,
			'chunk_row_offset'         => 0,
			'chunk_count'              => max( 0, (int) ( $manifest['chunk_count'] ?? 0 ) ),
			'chunks_completed'         => 0,
			'rows_restored'            => 0,
			'table_rows_restored'      => 0,
			'tables_completed'         => 0,
			'active_tables_untouched'  => true,
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
			'restore-database',
			'0:0:0',
			array(
				'completed' => 0,
				'total'     => max( 0, (int) ( $manifest['row_count'] ?? 0 ) ),
			)
		);

		return $this->store->get( $job_id );
	}

	/**
	 * Advance one bounded database staging-restore batch.
	 *
	 * @param string $job_id    Clone job identifier.
	 * @param int    $batch_rows Maximum rows inserted this request.
	 * @return array<string,mixed>|null
	 */
	public function advance( string $job_id, int $batch_rows = self::DEFAULT_BATCH_ROWS ): ?array {
		$batch_rows = max( self::MIN_BATCH_ROWS, min( self::MAX_BATCH_ROWS, $batch_rows ) );
		$state      = $this->store->get( $job_id ) ?? $this->start( $job_id );
		if ( ! is_array( $state ) || in_array( $state['status'] ?? null, array( 'complete', 'blocked' ), true ) ) {
			return $state;
		}

		if ( null === $this->runtime_guard( $job_id, $state ) ) {
			return $this->block( $job_id, $state, 'import-database-runtime-guard-failed', false );
		}

		$manifest_bundle = $this->database_manifest( $job_id );
		if (
			null === $manifest_bundle
			|| ! hash_equals(
				(string) ( $state['database_manifest_sha256'] ?? '' ),
				(string) $manifest_bundle['sha256']
			)
		) {
			return $this->block( $job_id, $state, 'import-database-manifest-changed', false );
		}

		$manifest = $manifest_bundle['manifest'];
		$tables   = is_array( $manifest['tables'] ?? null ) ? array_values( $manifest['tables'] ) : array();
		$index    = max( 0, (int) ( $state['table_index'] ?? 0 ) );
		if ( $index >= count( $tables ) ) {
			return $this->complete_database( $job_id, $state, $manifest );
		}

		$meta = is_array( $tables[ $index ] ?? null ) ? $tables[ $index ] : array();
		if ( ! $this->table_manifest_valid( $meta, (string) $state['source_prefix'] ) ) {
			return $this->block( $job_id, $state, 'import-database-table-manifest-invalid', false );
		}

		$source_table  = (string) $meta['name'];
		$target_table  = $this->target_table( $source_table, (string) $state['source_prefix'], (string) $state['destination_prefix'] );
		$staging_table = $this->staging_table( (string) $state['staging_namespace'], $source_table );
		if ( '' === $target_table || '' === $staging_table || $target_table === $staging_table ) {
			return $this->block( $job_id, $state, 'import-database-table-map-invalid', false );
		}

		$stage = (string) ( $state['stage'] ?? 'schema' );
		if ( 'schema' === $stage ) {
			return $this->prepare_table_schema( $job_id, $state, $meta, $source_table, $target_table, $staging_table );
		}
		if ( 'rows' === $stage ) {
			return $this->restore_rows( $job_id, $state, $meta, $staging_table, $batch_rows );
		}
		if ( 'table-verify' === $stage ) {
			return $this->verify_table( $job_id, $state, $meta, $staging_table );
		}

		return $this->block( $job_id, $state, 'import-database-stage-invalid', false );
	}

	/**
	 * Prepare one deterministic job-owned staging table.
	 *
	 * @param string              $job_id       Clone job identifier.
	 * @param array<string,mixed> $state        Restore state.
	 * @param array<string,mixed> $meta         Table manifest.
	 * @param string              $source_table Source table.
	 * @param string              $target_table Future destination table.
	 * @param string              $staging_table Job-owned staging table.
	 * @return array<string,mixed>|null
	 */
	private function prepare_table_schema(
		string $job_id,
		array $state,
		array $meta,
		string $source_table,
		string $target_table,
		string $staging_table
	): ?array {
		$state['current_source_table']  = $source_table;
		$state['current_target_table']  = $target_table;
		$state['current_staging_table'] = $staging_table;
		$state['chunk_index']           = 0;
		$state['chunk_row_offset']      = 0;
		$state['table_rows_restored']   = 0;
		$state['updated_at']            = gmdate( DATE_ATOM );

		if ( ! $this->store->save( $job_id, $state ) ) {
			return null;
		}

		$schema = is_array( $meta['schema'] ?? null ) ? $meta['schema'] : array();
		$path   = is_string( $schema['path'] ?? null ) ? $schema['path'] : '';
		$info   = $this->workspace->import_extracted_file_info( $job_id, $path );
		$sql    = $this->workspace->read_import_extracted_file( $job_id, $path );
		if (
			'' === $path
			|| ! is_array( $info )
			|| ! is_string( $sql )
			|| (int) ( $schema['bytes'] ?? -1 ) !== (int) $info['bytes']
			|| ! $this->same_hash( $schema['sha256'] ?? '', $info['sha256'] )
		) {
			return $this->block( $job_id, $state, 'import-database-schema-integrity-failed', false );
		}

		$create_sql = $this->staging_create_sql( $sql, $staging_table );
		if ( null === $create_sql ) {
			return $this->block( $job_id, $state, 'import-database-schema-unsupported', false );
		}

		$exists = $this->table_exists( $staging_table );
		if ( $exists ) {
			$count = $this->table_row_count( $staging_table );
			if ( 0 !== $count ) {
				return $this->block( $job_id, $state, 'import-database-staging-table-not-empty', false );
			}
		} else {
			global $wpdb;
			if ( ! $wpdb instanceof wpdb ) {
				return $this->block( $job_id, $state, 'import-database-runtime-unavailable', true );
			}

			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.SchemaChange -- Creates only a deterministic job-owned staging table after explicit sandbox authorization.
			$created = $wpdb->query( $create_sql );
			// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.SchemaChange
			if ( false === $created || ! $this->table_exists( $staging_table ) ) {
				return $this->block( $job_id, $state, 'import-database-staging-schema-create-failed', true );
			}
		}

		if ( ! $this->table_transactional( $staging_table ) ) {
			return $this->block( $job_id, $state, 'import-database-staging-engine-not-transactional', false );
		}

		$state['stage']      = 'rows';
		$state['updated_at'] = gmdate( DATE_ATOM );
		if ( ! $this->store->save( $job_id, $state ) ) {
			return null;
		}

		return $this->store->get( $job_id );
	}

	/**
	 * Restore one bounded slice of the current source chunk transactionally.
	 *
	 * @param string              $job_id       Clone job identifier.
	 * @param array<string,mixed> $state        Restore state.
	 * @param array<string,mixed> $meta         Table manifest.
	 * @param string              $staging_table Job-owned staging table.
	 * @param int                 $batch_rows   Maximum rows this request.
	 * @return array<string,mixed>|null
	 */
	private function restore_rows(
		string $job_id,
		array $state,
		array $meta,
		string $staging_table,
		int $batch_rows
	): ?array {
		$chunks      = is_array( $meta['chunks'] ?? null ) ? array_values( $meta['chunks'] ) : array();
		$chunk_index = max( 0, (int) ( $state['chunk_index'] ?? 0 ) );
		if ( $chunk_index >= count( $chunks ) ) {
			$state['stage']      = 'table-verify';
			$state['updated_at'] = gmdate( DATE_ATOM );
			$this->store->save( $job_id, $state );

			return $this->store->get( $job_id );
		}

		$chunk_meta = is_array( $chunks[ $chunk_index ] ?? null ) ? $chunks[ $chunk_index ] : array();
		$chunk      = $this->load_chunk( $job_id, $meta, $chunk_meta, $chunk_index );
		if ( null === $chunk ) {
			return $this->block( $job_id, $state, 'import-database-chunk-invalid', false );
		}

		$rows       = $chunk['rows'];
		$columns    = $chunk['columns'];
		$row_offset = max( 0, (int) ( $state['chunk_row_offset'] ?? 0 ) );
		if ( count( $rows ) < $row_offset ) {
			return $this->block( $job_id, $state, 'import-database-chunk-cursor-invalid', false );
		}

		if ( count( $rows ) === $row_offset ) {
			$state['chunk_index']      = $chunk_index + 1;
			$state['chunk_row_offset'] = 0;
			$state['chunks_completed'] = (int) ( $state['chunks_completed'] ?? 0 ) + 1;
			$state['updated_at']       = gmdate( DATE_ATOM );
			if ( ! $this->store->save( $job_id, $state ) ) {
				return null;
			}

			return $this->store->get( $job_id );
		}

		$end     = min( count( $rows ), $row_offset + $batch_rows );
		$slice   = array_slice( $rows, $row_offset, $end - $row_offset );
		$decoded = $this->decode_rows( $columns, $slice );
		if ( null === $decoded ) {
			return $this->block( $job_id, $state, 'import-database-row-decode-failed', false );
		}

		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Begins a bounded staging-row transaction; active destination tables are not mutation targets.
		if ( ! $wpdb instanceof wpdb || false === $wpdb->query( 'START TRANSACTION' ) ) {
			return $this->block( $job_id, $state, 'import-database-transaction-start-failed', true );
		}

		foreach ( $decoded as $row ) {
			$formats = array_fill( 0, count( $row ), '%s' );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Writes only to deterministic job-owned staging table; target tables remain untouched.
			if ( false === $wpdb->insert( $staging_table, $row, $formats ) ) {
				$wpdb->query( 'ROLLBACK' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
				wp_cache_delete( ImportDatabaseStateStore::OPTION_NAME, 'options' );

				return $this->block( $job_id, $state, 'import-database-row-insert-failed', true );
			}
		}

		$inserted                    = count( $decoded );
		$next                        = $state;
		$next['chunk_row_offset']    = $end;
		$next['rows_restored']       = (int) ( $state['rows_restored'] ?? 0 ) + $inserted;
		$next['table_rows_restored'] = (int) ( $state['table_rows_restored'] ?? 0 ) + $inserted;
		$next['updated_at']          = gmdate( DATE_ATOM );

		if ( $end >= count( $rows ) ) {
			$next['chunk_index']      = $chunk_index + 1;
			$next['chunk_row_offset'] = 0;
			$next['chunks_completed'] = (int) ( $state['chunks_completed'] ?? 0 ) + 1;
		}

		if ( ! $this->store->save( $job_id, $next ) ) {
			$wpdb->query( 'ROLLBACK' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			wp_cache_delete( ImportDatabaseStateStore::OPTION_NAME, 'options' );

			return $this->block( $job_id, $state, 'import-database-state-transaction-failed', true );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		if ( false === $wpdb->query( 'COMMIT' ) ) {
			$wpdb->query( 'ROLLBACK' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			wp_cache_delete( ImportDatabaseStateStore::OPTION_NAME, 'options' );

			return $this->block( $job_id, $state, 'import-database-transaction-commit-failed', true );
		}

		$this->jobs->update_progress(
			$job_id,
			'restore-database',
			(string) (int) $next['table_index'] . ':' . (string) (int) $next['chunk_index'] . ':' . (string) (int) $next['chunk_row_offset'],
			array(
				'completed' => (int) $next['rows_restored'],
				'total'     => null,
			)
		);

		return $this->store->get( $job_id );
	}

	/**
	 * Verify one staging table and advance to the next table.
	 *
	 * @param string              $job_id       Clone job identifier.
	 * @param array<string,mixed> $state        Restore state.
	 * @param array<string,mixed> $meta         Table manifest.
	 * @param string              $staging_table Job-owned staging table.
	 * @return array<string,mixed>|null
	 */
	private function verify_table( string $job_id, array $state, array $meta, string $staging_table ): ?array {
		$expected = max( 0, (int) ( $meta['row_count'] ?? 0 ) );
		$actual   = $this->table_row_count( $staging_table );
		if ( null === $actual || $actual !== $expected || (int) ( $state['table_rows_restored'] ?? -1 ) !== $expected ) {
			return $this->block( $job_id, $state, 'import-database-table-row-count-mismatch', false );
		}

		$state['table_index']           = (int) ( $state['table_index'] ?? 0 ) + 1;
		$state['tables_completed']      = (int) ( $state['tables_completed'] ?? 0 ) + 1;
		$state['current_source_table']  = '';
		$state['current_target_table']  = '';
		$state['current_staging_table'] = '';
		$state['chunk_index']           = 0;
		$state['chunk_row_offset']      = 0;
		$state['table_rows_restored']   = 0;
		$state['stage']                 = 'schema';
		$state['updated_at']            = gmdate( DATE_ATOM );

		if ( ! $this->store->save( $job_id, $state ) ) {
			return null;
		}

		return $this->store->get( $job_id );
	}

	/**
	 * Complete staging database restore without touching active destination tables.
	 *
	 * @param string              $job_id   Clone job identifier.
	 * @param array<string,mixed> $state    Restore state.
	 * @param array<string,mixed> $manifest Database manifest.
	 * @return array<string,mixed>|null
	 */
	private function complete_database( string $job_id, array $state, array $manifest ): ?array {
		if (
			(int) ( $manifest['table_count'] ?? -1 ) !== (int) ( $state['tables_completed'] ?? -2 )
			|| (int) ( $manifest['row_count'] ?? -1 ) !== (int) ( $state['rows_restored'] ?? -2 )
			|| (int) ( $manifest['chunk_count'] ?? -1 ) !== (int) ( $state['chunks_completed'] ?? -2 )
			|| true !== ( $state['active_tables_untouched'] ?? false )
		) {
			return $this->block( $job_id, $state, 'import-database-final-count-mismatch', false );
		}

		$state['status']       = 'complete';
		$state['stage']        = 'complete';
		$state['updated_at']   = gmdate( DATE_ATOM );
		$state['completed_at'] = $state['updated_at'];

		if ( ! $this->store->save( $job_id, $state ) ) {
			return null;
		}

		$this->jobs->transition( $job_id, 'active' );
		$this->jobs->update_progress(
			$job_id,
			'restore-database',
			'staging-complete',
			array(
				'completed' => (int) $state['rows_restored'],
				'total'     => (int) $state['rows_restored'],
			)
		);

		return $this->store->get( $job_id );
	}

	/**
	 * Revalidate package/destination state before every mutating batch.
	 *
	 * @param string              $job_id Clone job identifier.
	 * @param array<string,mixed> $state  Restore state.
	 * @return array<string,mixed>|null
	 */
	private function runtime_guard( string $job_id, array $state ): ?array {
		$fresh = $this->fresh_restore_gate( $job_id );
		if ( null === $fresh ) {
			return null;
		}

		global $wpdb;
		if (
			! $wpdb instanceof wpdb
			|| (string) ( $state['destination_prefix'] ?? '' ) !== $wpdb->prefix
			|| (string) ( $fresh['source_table_prefix'] ?? '' ) !== (string) ( $state['source_prefix'] ?? '' )
			|| (string) ( $fresh['destination_table_prefix'] ?? '' ) !== (string) ( $state['destination_prefix'] ?? '' )
			|| ! $this->options_table_transactional()
		) {
			return null;
		}

		$payload = $this->payload_state->get( $job_id );
		if (
			! is_array( $payload )
			|| 'complete' !== ( $payload['status'] ?? null )
			|| ! $this->same_hash( $payload['archive_sha256'] ?? '', $state['payload_archive_sha256'] ?? '' )
		) {
			return null;
		}

		$manifest = $this->database_manifest( $job_id );
		if (
			null === $manifest
			|| ! $this->same_hash( $manifest['sha256'], $state['database_manifest_sha256'] ?? '' )
		) {
			return null;
		}

		$allowed = $this->expected_staging_tables( $manifest['manifest'], (string) $state['staging_namespace'] );
		foreach ( $this->staging_tables( (string) $state['staging_namespace'] ) as $table ) {
			if ( ! in_array( $table, $allowed, true ) ) {
				return null;
			}
		}

		return $fresh;
	}

	/**
	 * Run a fresh preflight and require verified-payload restore eligibility.
	 *
	 * @param string $job_id Clone job identifier.
	 * @return array<string,mixed>|null
	 */
	private function fresh_restore_gate( string $job_id ): ?array {
		$job = $this->jobs->get( $job_id );
		if ( ! is_array( $job ) || 'import' !== ( $job['operation'] ?? null ) ) {
			return null;
		}

		$fresh = $this->preflight->validate( $job_id );
		if (
			! is_array( $fresh )
			|| 'payload-verified' !== ( $fresh['status'] ?? null )
			|| true !== ( $fresh['full_payload_verified'] ?? false )
			|| true !== ( $fresh['restore_allowed'] ?? false )
			|| array() !== ( $fresh['blockers'] ?? array() )
		) {
			return null;
		}

		return $fresh;
	}

	/**
	 * Load and validate the extracted database manifest through the verified package manifest.
	 *
	 * @param string $job_id Clone job identifier.
	 * @return array{manifest:array<string,mixed>,sha256:string}|null
	 */
	private function database_manifest( string $job_id ): ?array {
		$payload = $this->payload_state->get( $job_id );
		if ( ! is_array( $payload ) || 'complete' !== ( $payload['status'] ?? null ) ) {
			return null;
		}

		$package_info = $this->workspace->import_extracted_file_info( $job_id, 'package/manifest.json' );
		$package_json = $this->workspace->read_import_extracted_file( $job_id, 'package/manifest.json' );
		$db_info      = $this->workspace->import_extracted_file_info( $job_id, 'database/manifest.json' );
		$db_json      = $this->workspace->read_import_extracted_file( $job_id, 'database/manifest.json' );
		if (
			! is_array( $package_info )
			|| ! is_string( $package_json )
			|| ! is_array( $db_info )
			|| ! is_string( $db_json )
			|| ! $this->same_hash( $payload['package_manifest_sha256'] ?? '', $package_info['sha256'] )
		) {
			return null;
		}

		$package     = json_decode( $package_json, true );
		$manifest    = json_decode( $db_json, true );
		$payload_ref = is_array( $package['payload']['database'] ?? null ) ? $package['payload']['database'] : array();
		if (
			! is_array( $package )
			|| ! is_array( $manifest )
			|| 'database/manifest.json' !== ( $payload_ref['manifest_path'] ?? null )
			|| ! $this->same_hash( $payload_ref['manifest_sha256'] ?? '', $db_info['sha256'] )
		) {
			return null;
		}

		return array(
			'manifest' => $manifest,
			'sha256'   => (string) $db_info['sha256'],
		);
	}

	/**
	 * Validate the database manifest contract.
	 *
	 * @param array<string,mixed> $manifest Database manifest.
	 * @param string              $source_prefix Source table prefix.
	 */
	private function manifest_valid( array $manifest, string $source_prefix ): bool {
		$tables = is_array( $manifest['tables'] ?? null ) ? array_values( $manifest['tables'] ) : array();
		if (
			1 !== ( $manifest['schema_version'] ?? null )
			|| 'database' !== ( $manifest['payload_class'] ?? null )
			|| true !== ( $manifest['production_source_read_only'] ?? false )
			|| false !== ( $manifest['credentials_in_payload'] ?? true )
			|| '' === $source_prefix
			|| count( $tables ) !== (int) ( $manifest['table_count'] ?? -1 )
			|| count( $tables ) > self::MAX_TABLES
		) {
			return false;
		}

		foreach ( $tables as $meta ) {
			if ( ! is_array( $meta ) || ! $this->table_manifest_valid( $meta, $source_prefix ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Validate one deterministic table manifest.
	 *
	 * @param array<string,mixed> $meta          Table manifest.
	 * @param string              $source_prefix Source prefix.
	 */
	private function table_manifest_valid( array $meta, string $source_prefix ): bool {
		$table   = is_string( $meta['name'] ?? null ) ? $meta['name'] : '';
		$columns = is_array( $meta['columns'] ?? null ) ? array_values( $meta['columns'] ) : array();
		$schema  = is_array( $meta['schema'] ?? null ) ? $meta['schema'] : array();
		$chunks  = is_array( $meta['chunks'] ?? null ) ? array_values( $meta['chunks'] ) : array();
		$dir     = 'database/tables/' . substr( hash( 'sha256', $table ), 0, 20 );

		if (
			! $this->valid_table_name( $table )
			|| ! str_starts_with( $table, $source_prefix )
			|| array() === $columns
			|| array_values( array_filter( $columns, 'is_string' ) ) !== $columns
			|| ( $schema['path'] ?? null ) !== $dir . '/schema.sql'
			|| ! $this->valid_hash( $schema['sha256'] ?? null )
			|| 0 > (int) ( $schema['bytes'] ?? -1 )
			|| true !== ( $meta['complete'] ?? false )
			|| count( $chunks ) !== (int) ( $meta['chunk_count'] ?? count( $chunks ) )
		) {
			return false;
		}

		foreach ( $chunks as $index => $chunk ) {
			$expected_path = $dir . '/chunks/' . sprintf( '%06d.json', $index );
			if (
				! is_array( $chunk )
				|| (int) ( $chunk['index'] ?? -1 ) !== $index
				|| ( $chunk['path'] ?? null ) !== $expected_path
				|| ! $this->valid_hash( $chunk['sha256'] ?? null )
				|| 0 > (int) ( $chunk['row_count'] ?? -1 )
				|| 0 > (int) ( $chunk['byte_count'] ?? -1 )
			) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Load one chunk and validate it against table-manifest evidence.
	 *
	 * @param string              $job_id     Clone job identifier.
	 * @param array<string,mixed> $meta       Table manifest.
	 * @param array<string,mixed> $chunk_meta Chunk manifest.
	 * @param int                 $index      Expected chunk index.
	 * @return array{columns:list<string>,rows:list<array<int,mixed>>}|null
	 */
	private function load_chunk( string $job_id, array $meta, array $chunk_meta, int $index ): ?array {
		$path = is_string( $chunk_meta['path'] ?? null ) ? $chunk_meta['path'] : '';
		$info = $this->workspace->import_extracted_file_info( $job_id, $path );
		$json = $this->workspace->read_import_extracted_file( $job_id, $path );
		if (
			'' === $path
			|| ! is_array( $info )
			|| ! is_string( $json )
			|| (int) ( $chunk_meta['byte_count'] ?? -1 ) !== (int) $info['bytes']
			|| ! $this->same_hash( $chunk_meta['sha256'] ?? '', $info['sha256'] )
		) {
			return null;
		}

		$chunk            = json_decode( $json, true );
		$columns          = is_array( $chunk['columns'] ?? null ) ? array_values( $chunk['columns'] ) : array();
		$rows             = is_array( $chunk['rows'] ?? null ) ? array_values( $chunk['rows'] ) : array();
		$expected_columns = is_array( $meta['columns'] ?? null ) ? array_values( $meta['columns'] ) : array();
		if (
			! is_array( $chunk )
			|| 1 !== ( $chunk['schema_version'] ?? null )
			|| ( $chunk['table'] ?? null ) !== (string) ( $meta['name'] ?? '' )
			|| (int) ( $chunk['chunk_index'] ?? -1 ) !== $index
			|| 'base64-or-null' !== ( $chunk['value_encoding'] ?? null )
			|| $expected_columns !== $columns
			|| (int) ( $chunk_meta['row_count'] ?? -1 ) !== count( $rows )
			|| (int) ( $chunk['row_count'] ?? -1 ) !== count( $rows )
		) {
			return null;
		}

		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) || count( $row ) !== count( $columns ) ) {
				return null;
			}
		}

		// phpcs:disable Generic.Commenting.DocComment.MissingShort -- Local PHPStan type refinements, not API documentation.
		/** @var list<string> $columns */
		/** @var list<array<int,mixed>> $rows */
		// phpcs:enable Generic.Commenting.DocComment.MissingShort
		return array(
			'columns' => $columns,
			'rows'    => $rows,
		);
	}

	/**
	 * Decode binary-safe chunk rows into associative inserts.
	 *
	 * @param array $columns Columns.
	 * @param array $rows    Encoded rows.
	 * @phpstan-param list<string> $columns
	 * @phpstan-param list<array<int,mixed>> $rows
	 * @return list<array<string,mixed>>|null
	 */
	private function decode_rows( array $columns, array $rows ): ?array {
		$decoded_rows = array();

		foreach ( $rows as $row ) {
			$decoded = array();
			foreach ( $columns as $index => $column ) {
				$value = $row[ $index ] ?? null;
				if ( null === $value ) {
					$decoded[ $column ] = null;
					continue;
				}
				if ( ! is_string( $value ) ) {
					return null;
				}

				// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Binary-safe migration transport decoding, not code obfuscation.
				$raw = base64_decode( $value, true );
				if ( false === $raw ) {
					return null;
				}
				$decoded[ $column ] = $raw;
			}
			$decoded_rows[] = $decoded;
		}

		return $decoded_rows;
	}

	/**
	 * Build a safe CREATE TABLE statement for the job-owned staging table.
	 *
	 * @param string $source_sql   Extracted SHOW CREATE TABLE statement.
	 * @param string $staging_table Job-owned staging table.
	 */
	private function staging_create_sql( string $source_sql, string $staging_table ): ?string {
		$sql = trim( $source_sql );
		if (
			! $this->valid_staging_table( $staging_table )
			|| 1 !== preg_match( '/^CREATE\s+TABLE\s+/i', $sql )
			|| 1 === preg_match( '/\b(?:FOREIGN\s+KEY|REFERENCES)\b/i', $sql )
			|| 1 !== preg_match( '/\bENGINE\s*=\s*InnoDB\b/i', $sql )
		) {
			return null;
		}

		$open = strpos( $sql, '(' );
		if ( false === $open ) {
			return null;
		}

		$tail = rtrim( substr( $sql, $open ), " \t\n\r\0\x0B;" );
		if ( '' === $tail ) {
			return null;
		}

		return 'CREATE TABLE ' . $this->quote_identifier( $staging_table ) . ' ' . $tail;
	}

	/**
	 * Build deterministic future destination table mapping.
	 *
	 * @param string $source_table      Source table.
	 * @param string $source_prefix     Source prefix.
	 * @param string $destination_prefix Destination prefix.
	 */
	private function target_table( string $source_table, string $source_prefix, string $destination_prefix ): string {
		if (
			'' === $source_prefix
			|| ! str_starts_with( $source_table, $source_prefix )
			|| ! $this->valid_table_name( $source_table )
		) {
			return '';
		}

		$target = $destination_prefix . substr( $source_table, strlen( $source_prefix ) );

		return $this->valid_table_name( $target ) ? $target : '';
	}

	/**
	 * Build one job-owned staging namespace.
	 *
	 * @param string $job_id             Clone job identifier.
	 * @param string $destination_prefix Destination prefix.
	 */
	private function staging_namespace( string $job_id, string $destination_prefix ): string {
		if ( 1 !== preg_match( '/^[A-Za-z0-9_]+$/', $destination_prefix ) ) {
			return '';
		}

		$namespace = $destination_prefix . 'sgm_' . substr( hash( 'sha256', $job_id ), 0, 10 ) . '_';

		return 47 >= strlen( $namespace ) ? $namespace : '';
	}

	/**
	 * Build one deterministic job-owned staging table.
	 *
	 * @param string $staging_namespace Staging namespace.
	 * @param string $source_table      Source table.
	 */
	private function staging_table( string $staging_namespace, string $source_table ): string {
		$table = $staging_namespace . substr( hash( 'sha256', $source_table ), 0, 16 );

		return $this->valid_staging_table( $table ) ? $table : '';
	}

	/**
	 * Return all deterministic staging tables expected by this package.
	 *
	 * @param array<string,mixed> $manifest  Database manifest.
	 * @param string              $staging_namespace Staging namespace.
	 * @return list<string>
	 */
	private function expected_staging_tables( array $manifest, string $staging_namespace ): array {
		$tables   = is_array( $manifest['tables'] ?? null ) ? $manifest['tables'] : array();
		$expected = array();

		foreach ( $tables as $meta ) {
			if ( ! is_array( $meta ) || ! is_string( $meta['name'] ?? null ) ) {
				continue;
			}
			$table = $this->staging_table( $staging_namespace, $meta['name'] );
			if ( '' !== $table ) {
				$expected[] = $table;
			}
		}

		return array_values( array_unique( $expected ) );
	}

	/**
	 * Return staging tables currently present in the destination DB.
	 *
	 * @param string $staging_namespace Staging namespace.
	 * @return list<string>
	 */
	private function staging_tables( string $staging_namespace ): array {
		global $wpdb;
		if ( ! $wpdb instanceof wpdb || '' === $staging_namespace ) {
			return array();
		}

		$pattern = $wpdb->esc_like( $staging_namespace ) . '%';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Read-only metadata query scoped to the deterministic job-owned staging namespace.
		$tables = $wpdb->get_col( $wpdb->prepare( 'SHOW TABLES LIKE %s', $pattern ) );

		return array_values( array_filter( $tables, 'is_string' ) );
	}

	/**
	 * Whether no table currently exists in this job-owned staging namespace.
	 *
	 * @param string $staging_namespace Staging namespace.
	 */
	private function staging_namespace_empty( string $staging_namespace ): bool {
		return array() === $this->staging_tables( $staging_namespace );
	}

	/**
	 * Whether one table exists exactly.
	 *
	 * @param string $table Table name.
	 */
	private function table_exists( string $table ): bool {
		global $wpdb;
		if ( ! $wpdb instanceof wpdb || ! $this->valid_table_name( $table ) ) {
			return false;
		}

		$pattern = $wpdb->esc_like( $table );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Read-only exact table metadata query.
		$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $pattern ) );

		return is_string( $found ) && $table === $found;
	}

	/**
	 * Return exact row count for one deterministic staging table.
	 *
	 * @param string $table Staging table.
	 */
	private function table_row_count( string $table ): ?int {
		global $wpdb;
		if ( ! $wpdb instanceof wpdb || ! $this->valid_staging_table( $table ) ) {
			return null;
		}

		$quoted = $this->quote_identifier( $table );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Read-only count against deterministic job-owned staging table.
		$count = $wpdb->get_var( "SELECT COUNT(*) FROM {$quoted}" );

		return is_numeric( $count ) ? max( 0, (int) $count ) : null;
	}

	/**
	 * Whether the WordPress options table supports transactional state persistence.
	 */
	private function options_table_transactional(): bool {
		global $wpdb;
		return $wpdb instanceof wpdb && $this->table_transactional( $wpdb->options );
	}

	/**
	 * Whether one table uses InnoDB.
	 *
	 * @param string $table Table name.
	 */
	private function table_transactional( string $table ): bool {
		global $wpdb;
		if ( ! $wpdb instanceof wpdb || ! $this->valid_table_name( $table ) ) {
			return false;
		}

		$pattern = $wpdb->esc_like( $table );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Read-only table-engine metadata query.
		$row = $wpdb->get_row( $wpdb->prepare( 'SHOW TABLE STATUS LIKE %s', $pattern ), ARRAY_A );

		return is_array( $row ) && 'innodb' === strtolower( (string) ( $row['Engine'] ?? '' ) );
	}

	/**
	 * Validate one source/final table name.
	 *
	 * @param string $table Table name.
	 */
	private function valid_table_name( string $table ): bool {
		return '' !== $table
			&& 64 >= strlen( $table )
			&& 1 !== preg_match( '/[\x00-\x1F\x7F]/', $table );
	}

	/**
	 * Validate one generated staging-table identifier.
	 *
	 * @param string $table Table name.
	 */
	private function valid_staging_table( string $table ): bool {
		return 1 === preg_match( '/^[A-Za-z0-9_]{1,64}$/', $table );
	}

	/**
	 * Validate one SHA-256 value.
	 *
	 * @param mixed $hash Raw hash.
	 */
	private function valid_hash( mixed $hash ): bool {
		return is_string( $hash ) && 1 === preg_match( '/^[a-f0-9]{64}$/', $hash );
	}

	/**
	 * Constant-time compare one pair of SHA-256 values.
	 *
	 * @param mixed $left  First hash.
	 * @param mixed $right Second hash.
	 */
	private function same_hash( mixed $left, mixed $right ): bool {
		return $this->valid_hash( $left )
			&& $this->valid_hash( $right )
			&& hash_equals( (string) $left, (string) $right );
	}

	/**
	 * Quote one MySQL identifier.
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
	 * @param string              $job_id    Clone job identifier.
	 * @param array<string,mixed> $state     Restore state.
	 * @param string              $code      Blocker code.
	 * @param bool                $retryable Whether retry is allowed.
	 * @return array<string,mixed>|null
	 */
	private function block( string $job_id, array $state, string $code, bool $retryable ): ?array {
		$blockers            = is_array( $state['blockers'] ?? null ) ? $state['blockers'] : array();
		$blockers[]          = $code;
		$state['status']     = 'blocked';
		$state['blockers']   = array_values( array_unique( $blockers ) );
		$state['updated_at'] = gmdate( DATE_ATOM );
		$this->store->save( $job_id, $state );
		$this->jobs->transition( $job_id, $retryable ? 'failed-retryable' : 'failed-terminal', $code );

		return $this->store->get( $job_id );
	}
}
