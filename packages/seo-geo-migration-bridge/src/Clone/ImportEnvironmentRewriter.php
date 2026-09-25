<?php
/**
 * Portable Clone serialization-safe environment rewrite on staging tables.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge\Clone;

use wpdb;

/**
 * Rewrites source URLs/prefix-sensitive WordPress keys only inside verified staging tables.
 */
final class ImportEnvironmentRewriter {
	public const MIN_BATCH_ROWS     = 1;
	public const MAX_BATCH_ROWS     = 200;
	public const DEFAULT_BATCH_ROWS = 25;

	private const MAX_TABLES = 1000;

	private ImportEnvironmentStateStore $store;
	private ImportStateStore $import_state;
	private ImportPayloadStateStore $payload_state;
	private ImportDatabaseStateStore $database_state;
	private ImportFileStateStore $file_state;
	private CloneJobStore $jobs;
	private ImportPreflight $preflight;
	private ExportWorkspace $workspace;
	private SerializationSafeRewriter $rewriter;

	/**
	 * Construct environment rewriter.
	 *
	 * @param ImportEnvironmentStateStore|null $store          Optional environment state.
	 * @param ImportStateStore|null            $import_state   Optional import state.
	 * @param ImportPayloadStateStore|null     $payload_state  Optional payload state.
	 * @param ImportDatabaseStateStore|null    $database_state Optional database state.
	 * @param ImportFileStateStore|null        $file_state     Optional file state.
	 * @param CloneJobStore|null               $jobs           Optional clone jobs.
	 * @param ImportPreflight|null             $preflight      Optional fresh preflight.
	 * @param ExportWorkspace|null             $workspace      Optional private workspace.
	 * @param SerializationSafeRewriter|null   $rewriter       Optional value rewriter.
	 */
	public function __construct(
		?ImportEnvironmentStateStore $store = null,
		?ImportStateStore $import_state = null,
		?ImportPayloadStateStore $payload_state = null,
		?ImportDatabaseStateStore $database_state = null,
		?ImportFileStateStore $file_state = null,
		?CloneJobStore $jobs = null,
		?ImportPreflight $preflight = null,
		?ExportWorkspace $workspace = null,
		?SerializationSafeRewriter $rewriter = null
	) {
		$this->store          = $store ?? new ImportEnvironmentStateStore();
		$this->import_state   = $import_state ?? new ImportStateStore();
		$this->payload_state  = $payload_state ?? new ImportPayloadStateStore();
		$this->database_state = $database_state ?? new ImportDatabaseStateStore();
		$this->file_state     = $file_state ?? new ImportFileStateStore();
		$this->jobs           = $jobs ?? new CloneJobStore();
		$this->workspace      = $workspace ?? new ExportWorkspace();
		$this->preflight      = $preflight ?? new ImportPreflight( $this->import_state, $this->jobs, $this->workspace );
		$this->rewriter       = $rewriter ?? new SerializationSafeRewriter();
	}

	/**
	 * Return one rewrite snapshot.
	 *
	 * @param string $job_id Clone job identifier.
	 * @return array<string,mixed>|null
	 */
	public function snapshot( string $job_id ): ?array {
		return $this->store->get( $job_id );
	}

	/**
	 * Initialize staging-only environment rewrite after database/files staging is verified.
	 *
	 * @param string $job_id Clone job identifier.
	 * @return array<string,mixed>|null
	 */
	public function start( string $job_id ): ?array {
		$existing = $this->store->get( $job_id );
		if ( is_array( $existing ) ) {
			return $existing;
		}

		$fresh   = $this->fresh_restore_gate( $job_id );
		$db      = $this->database_state->get( $job_id );
		$files   = $this->file_state->get( $job_id );
		$payload = $this->payload_state->get( $job_id );

		if (
			null === $fresh
			|| ! is_array( $db )
			|| 'complete' !== ( $db['status'] ?? null )
			|| true !== ( $db['active_tables_untouched'] ?? false )
			|| ! is_array( $files )
			|| 'complete' !== ( $files['status'] ?? null )
			|| true !== ( $files['active_roots_untouched'] ?? false )
			|| ! is_array( $payload )
			|| 'complete' !== ( $payload['status'] ?? null )
		) {
			return null;
		}

		$manifest_bundle = $this->database_manifest( $job_id, $payload );
		if (
			null === $manifest_bundle
			|| ! $this->same_hash( $manifest_bundle['sha256'], $db['database_manifest_sha256'] ?? '' )
			|| ! $this->same_hash( $payload['archive_sha256'] ?? '', $db['payload_archive_sha256'] ?? '' )
		) {
			return null;
		}

		$manifest = $manifest_bundle['manifest'];
		$tables   = is_array( $manifest['tables'] ?? null ) ? array_values( $manifest['tables'] ) : array();
		if ( count( $tables ) > self::MAX_TABLES ) {
			return null;
		}

		$source_home = (string) ( $fresh['source_home_url'] ?? '' );
		$source_site = (string) ( $fresh['source_site_url'] ?? '' );
		$dest_home   = (string) ( $fresh['destination_home_url'] ?? '' );
		$dest_site   = (string) ( $fresh['destination_site_url'] ?? '' );

		if (
			! $this->valid_http_url( $source_home )
			|| ! $this->valid_http_url( $source_site )
			|| ! $this->valid_http_url( $dest_home )
			|| ! $this->valid_http_url( $dest_site )
			|| '' === (string) ( $db['source_prefix'] ?? '' )
			|| '' === (string) ( $db['destination_prefix'] ?? '' )
			|| '' === (string) ( $db['staging_namespace'] ?? '' )
		) {
			return null;
		}

		$now   = gmdate( DATE_ATOM );
		$state = array(
			'schema_version'            => ImportEnvironmentStateStore::SCHEMA_VERSION,
			'job_id'                    => $job_id,
			'status'                    => 'running',
			'stage'                     => 'rewrite',
			'source_home_url'           => $source_home,
			'source_site_url'           => $source_site,
			'destination_home_url'      => $dest_home,
			'destination_site_url'      => $dest_site,
			'source_prefix'             => (string) $db['source_prefix'],
			'destination_prefix'        => (string) $db['destination_prefix'],
			'staging_namespace'         => (string) $db['staging_namespace'],
			'payload_archive_sha256'    => (string) $payload['archive_sha256'],
			'database_manifest_sha256'  => (string) $manifest_bundle['sha256'],
			'table_index'               => 0,
			'table_count'               => count( $tables ),
			'row_offset'                => 0,
			'rows_scanned'              => 0,
			'rows_changed'              => 0,
			'values_changed'            => 0,
			'serialized_values_changed' => 0,
			'raw_values_changed'        => 0,
			'prefix_keys_changed'       => 0,
			'verify_rows_scanned'       => 0,
			'remaining_source_values'   => 0,
			'active_tables_untouched'   => true,
			'blockers'                  => array(),
			'advisories'                => array(
				'guid-values-preserved',
				'staged-files-not-rewritten',
				'opaque-custom-serialization-blocks-if-source-url-present',
			),
			'started_at'                => $now,
			'updated_at'                => $now,
			'completed_at'              => '',
		);

		if ( ! $this->expected_staging_set_valid( $manifest, $state ) || ! $this->store->save( $job_id, $state ) ) {
			return null;
		}

		$this->jobs->transition( $job_id, 'active' );
		$this->jobs->update_progress(
			$job_id,
			'rewrite-environment',
			'rewrite:0:0',
			array(
				'completed' => 0,
				'total'     => null,
			)
		);

		return $this->store->get( $job_id );
	}

	/**
	 * Advance one bounded rewrite or verification batch.
	 *
	 * @param string $job_id    Clone job identifier.
	 * @param int    $batch_rows Maximum rows this request.
	 * @return array<string,mixed>|null
	 */
	public function advance( string $job_id, int $batch_rows = self::DEFAULT_BATCH_ROWS ): ?array {
		$batch_rows = max( self::MIN_BATCH_ROWS, min( self::MAX_BATCH_ROWS, $batch_rows ) );
		$state      = $this->store->get( $job_id ) ?? $this->start( $job_id );

		if ( ! is_array( $state ) || in_array( $state['status'] ?? null, array( 'complete', 'blocked' ), true ) ) {
			return $state;
		}

		$guard = $this->runtime_guard( $job_id, $state );
		if ( null === $guard ) {
			return $this->block( $job_id, $state, 'import-environment-runtime-guard-failed', false );
		}

		$manifest_bundle = $this->database_manifest( $job_id, $guard['payload'] );
		if (
			null === $manifest_bundle
			|| ! $this->same_hash( $manifest_bundle['sha256'], $state['database_manifest_sha256'] ?? '' )
		) {
			return $this->block( $job_id, $state, 'import-environment-manifest-changed', false );
		}

		$manifest = $manifest_bundle['manifest'];
		if ( ! $this->expected_staging_set_valid( $manifest, $state ) ) {
			return $this->block( $job_id, $state, 'import-environment-staging-set-changed', false );
		}

		$tables = is_array( $manifest['tables'] ?? null ) ? array_values( $manifest['tables'] ) : array();
		$index  = (int) ( $state['table_index'] ?? 0 );

		if ( $index >= count( $tables ) ) {
			return 'rewrite' === ( $state['stage'] ?? null )
				? $this->start_verify_pass( $job_id, $state )
				: $this->complete_rewrite( $job_id, $state, $manifest );
		}

		$meta = is_array( $tables[ $index ] ?? null ) ? $tables[ $index ] : array();
		if ( ! is_string( $meta['name'] ?? null ) ) {
			return $this->block( $job_id, $state, 'import-environment-table-manifest-invalid', false );
		}

		$source_table  = $meta['name'];
		$staging_table = $this->staging_table( (string) $state['staging_namespace'], $source_table );

		if ( '' === $staging_table || ! $this->table_exists( $staging_table ) ) {
			return $this->block( $job_id, $state, 'import-environment-staging-table-missing', false );
		}

		$columns = $this->table_columns( $staging_table );
		if ( null === $columns ) {
			return $this->block( $job_id, $state, 'import-environment-table-columns-unavailable', true );
		}

		$text_columns = array_values(
			array_map(
				static fn( array $column ): string => $column['name'],
				array_filter(
					$columns,
					static fn( array $column ): bool => true === $column['textual']
				)
			)
		);

		if ( array() === $text_columns ) {
			return $this->advance_table( $job_id, $state );
		}

		$primary = $this->primary_key_columns( $staging_table );
		if ( null === $primary ) {
			return $this->block( $job_id, $state, 'import-environment-primary-key-metadata-unavailable', true );
		}

		$select_columns = array_values( array_unique( array_merge( $primary, $text_columns ) ) );
		$rows           = $this->read_rows(
			$staging_table,
			$select_columns,
			$primary,
			(int) ( $state['row_offset'] ?? 0 ),
			$batch_rows
		);

		if ( null === $rows ) {
			return $this->block( $job_id, $state, 'import-environment-row-read-failed', true );
		}
		if ( array() === $rows ) {
			return $this->advance_table( $job_id, $state );
		}

		return 'rewrite' === ( $state['stage'] ?? null )
			? $this->rewrite_rows( $job_id, $state, $source_table, $staging_table, $text_columns, $primary, $rows )
			: $this->verify_rows( $job_id, $state, $source_table, $text_columns, $rows );
	}

	/**
	 * Rewrite one bounded row batch transactionally.
	 *
	 * @param string                    $job_id       Clone job identifier.
	 * @param array<string,mixed>       $state        Rewrite state.
	 * @param string                    $source_table Source table name.
	 * @param string                    $staging_table Staging table name.
	 * @param list<string>              $text_columns Text columns.
	 * @param list<string>              $primary      Primary-key columns.
	 * @param list<array<string,mixed>> $rows         Selected rows.
	 * @return array<string,mixed>|null
	 */
	private function rewrite_rows(
		string $job_id,
		array $state,
		string $source_table,
		string $staging_table,
		array $text_columns,
		array $primary,
		array $rows
	): ?array {
		global $wpdb;

		if ( ! $wpdb instanceof wpdb ) {
			return $this->block( $job_id, $state, 'import-environment-database-unavailable', true );
		}

		$replacements = $this->url_replacements( $state );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Transaction scopes staging-only environment mutations and state persistence.
		if ( false === $wpdb->query( 'START TRANSACTION' ) ) {
			return $this->block( $job_id, $state, 'import-environment-transaction-start-failed', true );
		}

		$next = $state;

		foreach ( $rows as $row ) {
			$updates            = array();
			$row_values_changed = 0;
			$row_serialized     = 0;
			$row_raw            = 0;
			$row_prefix         = 0;

			foreach ( $text_columns as $column ) {
				$value = $row[ $column ] ?? null;
				if ( ! is_string( $value ) || $this->preserve_column( $source_table, $column, (string) $state['source_prefix'] ) ) {
					continue;
				}

				$prefix_value = $this->rewrite_prefix_key(
					$source_table,
					$column,
					$value,
					(string) $state['source_prefix'],
					(string) $state['destination_prefix']
				);
				$prefix_changed = $prefix_value !== $value;
				$result         = $this->rewriter->rewrite( $prefix_value, $replacements );

				if ( true === $result['unsupported'] ) {
					$wpdb->query( 'ROLLBACK' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
					return $this->block( $job_id, $state, 'import-environment-serialized-value-unsupported', false );
				}

				$new_value = $result['value'];
				if ( $new_value === $value ) {
					continue;
				}
				if ( in_array( $column, $primary, true ) ) {
					$wpdb->query( 'ROLLBACK' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
					return $this->block( $job_id, $state, 'import-environment-primary-key-rewrite-unsupported', false );
				}

				$updates[ $column ] = $new_value;
				++$row_values_changed;
				if ( $prefix_changed ) {
					++$row_prefix;
				}
				if ( true === $result['serialized'] ) {
					++$row_serialized;
				} else {
					++$row_raw;
				}
			}

			if ( array() === $updates ) {
				continue;
			}
			if ( array() === $primary ) {
				$wpdb->query( 'ROLLBACK' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
				return $this->block( $job_id, $state, 'import-environment-table-without-primary-key', false );
			}

			$where = array();
			foreach ( $primary as $column ) {
				if ( ! array_key_exists( $column, $row ) ) {
					$wpdb->query( 'ROLLBACK' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
					return $this->block( $job_id, $state, 'import-environment-primary-key-value-missing', false );
				}
				$where[ $column ] = $row[ $column ];
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Writes only the deterministic job-owned staging table selected by manifest + namespace.
			$updated = $wpdb->update( $staging_table, $updates, $where );

			if ( false === $updated || 1 !== $updated ) {
				$wpdb->query( 'ROLLBACK' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
				return $this->block( $job_id, $state, 'import-environment-row-update-failed', true );
			}

			++$next['rows_changed'];
			$next['values_changed']            = (int) $next['values_changed'] + $row_values_changed;
			$next['serialized_values_changed'] = (int) $next['serialized_values_changed'] + $row_serialized;
			$next['raw_values_changed']        = (int) $next['raw_values_changed'] + $row_raw;
			$next['prefix_keys_changed']       = (int) $next['prefix_keys_changed'] + $row_prefix;
		}

		$next['row_offset']   = (int) $next['row_offset'] + count( $rows );
		$next['rows_scanned'] = (int) $next['rows_scanned'] + count( $rows );
		$next['updated_at']   = gmdate( DATE_ATOM );

		if ( ! $this->store->save( $job_id, $next ) ) {
			$wpdb->query( 'ROLLBACK' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			wp_cache_delete( ImportEnvironmentStateStore::OPTION_NAME, 'options' );

			return $this->block( $job_id, $state, 'import-environment-state-transaction-failed', true );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		if ( false === $wpdb->query( 'COMMIT' ) ) {
			$wpdb->query( 'ROLLBACK' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			wp_cache_delete( ImportEnvironmentStateStore::OPTION_NAME, 'options' );

			return $this->block( $job_id, $state, 'import-environment-transaction-commit-failed', true );
		}

		$this->jobs->update_progress(
			$job_id,
			'rewrite-environment',
			'rewrite:' . (string) $next['table_index'] . ':' . (string) $next['row_offset'],
			array(
				'completed' => (int) $next['rows_scanned'],
				'total'     => null,
			)
		);

		return $this->store->get( $job_id );
	}

	/**
	 * Verify one bounded batch contains no source environment values.
	 *
	 * @param string                    $job_id       Clone job identifier.
	 * @param array<string,mixed>       $state        Rewrite state.
	 * @param string                    $source_table Source table.
	 * @param list<string>              $text_columns Text columns.
	 * @param list<array<string,mixed>> $rows         Selected rows.
	 * @return array<string,mixed>|null
	 */
	private function verify_rows(
		string $job_id,
		array $state,
		string $source_table,
		array $text_columns,
		array $rows
	): ?array {
		$replacements = $this->url_replacements( $state );
		$remaining    = 0;

		foreach ( $rows as $row ) {
			foreach ( $text_columns as $column ) {
				$value = $row[ $column ] ?? null;
				if ( ! is_string( $value ) || $this->preserve_column( $source_table, $column, (string) $state['source_prefix'] ) ) {
					continue;
				}

				if ( $this->rewriter->contains_source( $value, $replacements ) ) {
					++$remaining;
				}
				if (
					$this->prefix_key_needs_rewrite(
						$source_table,
						$column,
						$value,
						(string) $state['source_prefix'],
						(string) $state['destination_prefix']
					)
				) {
					++$remaining;
				}
				if ( $this->core_environment_value_invalid( $source_table, $column, $row, $state ) ) {
					++$remaining;
				}
			}
		}

		if ( 0 < $remaining ) {
			$state['remaining_source_values'] = (int) ( $state['remaining_source_values'] ?? 0 ) + $remaining;

			return $this->block( $job_id, $state, 'import-environment-verification-source-value-remains', false );
		}

		$state['row_offset']           = (int) $state['row_offset'] + count( $rows );
		$state['verify_rows_scanned'] = (int) $state['verify_rows_scanned'] + count( $rows );
		$state['updated_at']           = gmdate( DATE_ATOM );

		if ( ! $this->store->save( $job_id, $state ) ) {
			return null;
		}

		$this->jobs->update_progress(
			$job_id,
			'rewrite-environment',
			'verify:' . (string) $state['table_index'] . ':' . (string) $state['row_offset'],
			array(
				'completed' => (int) $state['verify_rows_scanned'],
				'total'     => null,
			)
		);

		return $this->store->get( $job_id );
	}

	/**
	 * Begin independent verification pass.
	 *
	 * @param string              $job_id Clone job identifier.
	 * @param array<string,mixed> $state  Rewrite state.
	 * @return array<string,mixed>|null
	 */
	private function start_verify_pass( string $job_id, array $state ): ?array {
		$state['stage']                   = 'verify';
		$state['table_index']             = 0;
		$state['row_offset']              = 0;
		$state['verify_rows_scanned']     = 0;
		$state['remaining_source_values'] = 0;
		$state['updated_at']              = gmdate( DATE_ATOM );

		if ( ! $this->store->save( $job_id, $state ) ) {
			return null;
		}

		return $this->store->get( $job_id );
	}

	/**
	 * Complete environment rewrite after verification.
	 *
	 * @param string              $job_id   Clone job identifier.
	 * @param array<string,mixed> $state    Rewrite state.
	 * @param array<string,mixed> $manifest Database manifest.
	 * @return array<string,mixed>|null
	 */
	private function complete_rewrite( string $job_id, array $state, array $manifest ): ?array {
		$tables = is_array( $manifest['tables'] ?? null ) ? $manifest['tables'] : array();

		if (
			(int) ( $state['table_count'] ?? -1 ) !== count( $tables )
			|| 0 !== (int) ( $state['remaining_source_values'] ?? 0 )
			|| true !== ( $state['active_tables_untouched'] ?? false )
			|| ! $this->expected_staging_set_valid( $manifest, $state )
		) {
			return $this->block( $job_id, $state, 'import-environment-final-verification-failed', false );
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
			'rewrite-environment',
			'complete',
			array(
				'completed' => (int) $state['verify_rows_scanned'],
				'total'     => (int) $state['verify_rows_scanned'],
			)
		);

		return $this->store->get( $job_id );
	}

	/**
	 * Advance cursor to next manifest table.
	 *
	 * @param string              $job_id Clone job identifier.
	 * @param array<string,mixed> $state  Rewrite state.
	 * @return array<string,mixed>|null
	 */
	private function advance_table( string $job_id, array $state ): ?array {
		$state['table_index'] = (int) ( $state['table_index'] ?? 0 ) + 1;
		$state['row_offset']  = 0;
		$state['updated_at']  = gmdate( DATE_ATOM );

		if ( ! $this->store->save( $job_id, $state ) ) {
			return null;
		}

		return $this->store->get( $job_id );
	}

	/**
	 * Revalidate all accepted import layers before every batch.
	 *
	 * @param string              $job_id Clone job identifier.
	 * @param array<string,mixed> $state  Rewrite state.
	 * @return array{fresh:array<string,mixed>,payload:array<string,mixed>}|null
	 */
	private function runtime_guard( string $job_id, array $state ): ?array {
		$fresh   = $this->fresh_restore_gate( $job_id );
		$db      = $this->database_state->get( $job_id );
		$files   = $this->file_state->get( $job_id );
		$payload = $this->payload_state->get( $job_id );

		global $wpdb;

		if (
			null === $fresh
			|| ! $wpdb instanceof wpdb
			|| ! is_array( $db )
			|| 'complete' !== ( $db['status'] ?? null )
			|| true !== ( $db['active_tables_untouched'] ?? false )
			|| ! is_array( $files )
			|| 'complete' !== ( $files['status'] ?? null )
			|| true !== ( $files['active_roots_untouched'] ?? false )
			|| ! is_array( $payload )
			|| 'complete' !== ( $payload['status'] ?? null )
			|| ! $this->same_hash( $payload['archive_sha256'] ?? '', $state['payload_archive_sha256'] ?? '' )
			|| ! $this->same_hash( $db['database_manifest_sha256'] ?? '', $state['database_manifest_sha256'] ?? '' )
			|| (string) ( $db['staging_namespace'] ?? '' ) !== (string) ( $state['staging_namespace'] ?? '' )
			|| (string) ( $db['source_prefix'] ?? '' ) !== (string) ( $state['source_prefix'] ?? '' )
			|| (string) ( $db['destination_prefix'] ?? '' ) !== (string) ( $state['destination_prefix'] ?? '' )
			|| $wpdb->prefix !== (string) ( $state['destination_prefix'] ?? '' )
			|| (string) ( $fresh['source_home_url'] ?? '' ) !== (string) ( $state['source_home_url'] ?? '' )
			|| (string) ( $fresh['source_site_url'] ?? '' ) !== (string) ( $state['source_site_url'] ?? '' )
			|| (string) ( $fresh['destination_home_url'] ?? '' ) !== (string) ( $state['destination_home_url'] ?? '' )
			|| (string) ( $fresh['destination_site_url'] ?? '' ) !== (string) ( $state['destination_site_url'] ?? '' )
		) {
			return null;
		}

		return array(
			'fresh'   => $fresh,
			'payload' => $payload,
		);
	}

	/**
	 * Require fresh verified-payload restore eligibility.
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
	 * Load extracted database manifest bound to verified package identity.
	 *
	 * @param string              $job_id  Clone job identifier.
	 * @param array<string,mixed> $payload Verified payload state.
	 * @return array{manifest:array<string,mixed>,sha256:string}|null
	 */
	private function database_manifest( string $job_id, array $payload ): ?array {
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

		$package  = json_decode( $package_json, true );
		$manifest = json_decode( $db_json, true );
		$ref      = is_array( $package['payload']['database'] ?? null ) ? $package['payload']['database'] : array();

		if (
			! is_array( $package )
			|| ! is_array( $manifest )
			|| 'database/manifest.json' !== ( $ref['manifest_path'] ?? null )
			|| ! $this->same_hash( $ref['manifest_sha256'] ?? '', $db_info['sha256'] )
		) {
			return null;
		}

		return array(
			'manifest' => $manifest,
			'sha256'   => (string) $db_info['sha256'],
		);
	}

	/**
	 * Return column metadata.
	 *
	 * @param string $table Staging table.
	 * @return list<array{name:string,textual:bool}>|null
	 */
	private function table_columns( string $table ): ?array {
		global $wpdb;

		if ( ! $wpdb instanceof wpdb || ! $this->valid_identifier( $table ) ) {
			return null;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- Read-only metadata on a validated staging table.
		$rows = $wpdb->get_results( 'SHOW COLUMNS FROM ' . $this->quote_identifier( $table ), ARRAY_A );

		if ( ! is_array( $rows ) ) {
			return null;
		}

		$columns = array();

		foreach ( $rows as $row ) {
			$name = is_string( $row['Field'] ?? null ) ? $row['Field'] : '';
			$type = is_string( $row['Type'] ?? null ) ? strtolower( $row['Type'] ) : '';

			if ( ! $this->valid_identifier( $name ) ) {
				return null;
			}

			$columns[] = array(
				'name'    => $name,
				'textual' => 1 === preg_match( '/^(?:var)?char\b|^(?:tiny|medium|long)?text\b|^enum\b|^set\b|^json\b/', $type ),
			);
		}

		return $columns;
	}

	/**
	 * Return primary-key columns.
	 *
	 * @param string $table Staging table.
	 * @return list<string>|null
	 */
	private function primary_key_columns( string $table ): ?array {
		global $wpdb;

		if ( ! $wpdb instanceof wpdb || ! $this->valid_identifier( $table ) ) {
			return null;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- Read-only index metadata on a validated staging table.
		$rows = $wpdb->get_results( 'SHOW INDEX FROM ' . $this->quote_identifier( $table ) . " WHERE Key_name = 'PRIMARY' ORDER BY Seq_in_index ASC", ARRAY_A );

		if ( ! is_array( $rows ) ) {
			return null;
		}

		$columns = array();

		foreach ( $rows as $row ) {
			$name = is_string( $row['Column_name'] ?? null ) ? $row['Column_name'] : '';
			if ( ! $this->valid_identifier( $name ) ) {
				return null;
			}
			$columns[] = $name;
		}

		return array_values( array_unique( $columns ) );
	}

	/**
	 * Read one stable bounded row page.
	 *
	 * @param string       $table   Staging table.
	 * @param list<string> $columns Selected columns.
	 * @param list<string> $primary Primary columns.
	 * @param int          $offset  Offset.
	 * @param int          $limit   Limit.
	 * @return list<array<string,mixed>>|null
	 */
	private function read_rows( string $table, array $columns, array $primary, int $offset, int $limit ): ?array {
		global $wpdb;

		if ( ! $wpdb instanceof wpdb || ! $this->valid_identifier( $table ) || array() === $columns ) {
			return null;
		}

		foreach ( array_merge( $columns, $primary ) as $column ) {
			if ( ! $this->valid_identifier( $column ) ) {
				return null;
			}
		}

		$select = implode( ', ', array_map( array( $this, 'quote_identifier' ), $columns ) );
		$order  = array() === $primary
			? ''
			: ' ORDER BY ' . implode( ', ', array_map( array( $this, 'quote_identifier' ), $primary ) ) . ' ASC';
		$sql    = 'SELECT ' . $select . ' FROM ' . $this->quote_identifier( $table ) . $order . ' LIMIT %d OFFSET %d';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- Identifiers are validated; pagination values are prepared.
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $limit, max( 0, $offset ) ), ARRAY_A );

		if ( ! is_array( $rows ) ) {
			return null;
		}

		/** @var list<array<string,mixed>> $rows */
		return array_values( $rows );
	}

	/**
	 * Build exact source-to-destination URL replacements.
	 *
	 * @param array<string,mixed> $state Rewrite state.
	 * @return array<string,string>
	 */
	private function url_replacements( array $state ): array {
		$pairs = array();

		foreach (
			array(
				array( (string) $state['source_home_url'], (string) $state['destination_home_url'] ),
				array( (string) $state['source_site_url'], (string) $state['destination_site_url'] ),
			) as $pair
		) {
			$source = $pair[0];
			$dest   = $pair[1];

			foreach (
				array(
					array( $source, $dest ),
					array( untrailingslashit( $source ), untrailingslashit( $dest ) ),
					array( trailingslashit( $source ), trailingslashit( $dest ) ),
					array( str_replace( '/', '\\/', $source ), str_replace( '/', '\\/', $dest ) ),
				) as $variant
			) {
				if ( '' !== $variant[0] && $variant[0] !== $variant[1] ) {
					$pairs[ $variant[0] ] = $variant[1];
				}
			}
		}

		return $pairs;
	}

	/**
	 * Rewrite WordPress role/capability keys that embed the table prefix.
	 */
	private function rewrite_prefix_key(
		string $source_table,
		string $column,
		string $value,
		string $source_prefix,
		string $dest_prefix
	): string {
		if ( 'option_name' === $column ) {
			$site_prefix = $this->site_prefix_for_options_table( $source_table, $source_prefix );

			if ( null !== $site_prefix && $site_prefix . 'user_roles' === $value ) {
				$suffix = substr( $site_prefix, strlen( $source_prefix ) );

				return $dest_prefix . $suffix . 'user_roles';
			}
		}

		if ( 'meta_key' === $column && $source_prefix . 'usermeta' === $source_table ) {
			$pattern = '/^' . preg_quote( $source_prefix, '/' ) . '(\d+_)?(capabilities|user_level)$/';

			if ( 1 === preg_match( $pattern, $value, $match ) ) {
				return $dest_prefix . ( $match[1] ?? '' ) . $match[2];
			}
		}

		return $value;
	}

	/**
	 * Whether a prefix-sensitive key still requires rewrite.
	 */
	private function prefix_key_needs_rewrite(
		string $source_table,
		string $column,
		string $value,
		string $source_prefix,
		string $dest_prefix
	): bool {
		return $this->rewrite_prefix_key( $source_table, $column, $value, $source_prefix, $dest_prefix ) !== $value;
	}

	/**
	 * Verify core home/siteurl rows use exact destination URLs.
	 *
	 * @param array<string,mixed> $row   Selected row.
	 * @param array<string,mixed> $state Rewrite state.
	 */
	private function core_environment_value_invalid(
		string $source_table,
		string $column,
		array $row,
		array $state
	): bool {
		if (
			'option_value' !== $column
			|| null === $this->site_prefix_for_options_table( $source_table, (string) $state['source_prefix'] )
			|| ! is_string( $row['option_name'] ?? null )
			|| ! is_string( $row['option_value'] ?? null )
		) {
			return false;
		}

		if ( 'home' === $row['option_name'] ) {
			return untrailingslashit( $row['option_value'] ) !== untrailingslashit( (string) $state['destination_home_url'] );
		}

		if ( 'siteurl' === $row['option_name'] ) {
			return untrailingslashit( $row['option_value'] ) !== untrailingslashit( (string) $state['destination_site_url'] );
		}

		return false;
	}

	/**
	 * Preserve canonical WordPress GUID columns.
	 */
	private function preserve_column( string $source_table, string $column, string $source_prefix ): bool {
		if ( 'guid' !== $column || ! str_starts_with( $source_table, $source_prefix ) ) {
			return false;
		}

		$suffix = substr( $source_table, strlen( $source_prefix ) );

		return 1 === preg_match( '/^(?:\d+_)?posts$/', $suffix );
	}

	/**
	 * Return a site-specific prefix for an options table.
	 */
	private function site_prefix_for_options_table( string $source_table, string $source_prefix ): ?string {
		if ( ! str_starts_with( $source_table, $source_prefix ) ) {
			return null;
		}

		$suffix = substr( $source_table, strlen( $source_prefix ) );

		if ( 'options' === $suffix ) {
			return $source_prefix;
		}

		if ( 1 === preg_match( '/^(\d+_)options$/', $suffix, $match ) ) {
			return $source_prefix . $match[1];
		}

		return null;
	}

	/**
	 * Check expected staging tables match actual namespace contents exactly.
	 *
	 * @param array<string,mixed> $manifest Database manifest.
	 * @param array<string,mixed> $state    Rewrite state.
	 */
	private function expected_staging_set_valid( array $manifest, array $state ): bool {
		$tables   = is_array( $manifest['tables'] ?? null ) ? array_values( $manifest['tables'] ) : array();
		$expected = array();

		foreach ( $tables as $meta ) {
			if ( ! is_array( $meta ) || ! is_string( $meta['name'] ?? null ) ) {
				return false;
			}

			$table = $this->staging_table( (string) $state['staging_namespace'], $meta['name'] );

			if ( '' === $table ) {
				return false;
			}

			$expected[] = $table;
		}

		$actual = $this->staging_tables( (string) $state['staging_namespace'] );

		sort( $expected, SORT_STRING );
		sort( $actual, SORT_STRING );

		return array_values( array_unique( $expected ) ) === array_values( array_unique( $actual ) );
	}

	/**
	 * Return deterministic staging table name.
	 */
	private function staging_table( string $namespace, string $source_table ): string {
		if ( ! $this->valid_identifier( $namespace ) || ! $this->valid_source_table( $source_table ) ) {
			return '';
		}

		$table = $namespace . substr( hash( 'sha256', $source_table ), 0, 16 );

		return $this->valid_identifier( $table ) ? $table : '';
	}

	/**
	 * Return all tables in one job-owned staging namespace.
	 *
	 * @return list<string>
	 */
	private function staging_tables( string $namespace ): array {
		global $wpdb;

		if ( ! $wpdb instanceof wpdb || ! $this->valid_identifier( $namespace ) || '' === $namespace ) {
			return array();
		}

		$pattern = $wpdb->esc_like( $namespace ) . '%';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Read-only metadata scoped to one deterministic job-owned namespace.
		$tables = $wpdb->get_col( $wpdb->prepare( 'SHOW TABLES LIKE %s', $pattern ) );

		return array_values( array_filter( $tables, 'is_string' ) );
	}

	/**
	 * Whether one exact table exists.
	 */
	private function table_exists( string $table ): bool {
		global $wpdb;

		if ( ! $wpdb instanceof wpdb || ! $this->valid_identifier( $table ) ) {
			return false;
		}

		$pattern = $wpdb->esc_like( $table );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Read-only exact staging-table lookup.
		$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $pattern ) );

		return is_string( $found ) && $found === $table;
	}

	/**
	 * Quote one validated MySQL identifier.
	 */
	private function quote_identifier( string $identifier ): string {
		$quote = chr( 96 );

		return $quote . str_replace( $quote, $quote . $quote, $identifier ) . $quote;
	}

	/**
	 * Validate deterministic staging identifiers.
	 */
	private function valid_identifier( string $identifier ): bool {
		return '' !== $identifier
			&& 64 >= strlen( $identifier )
			&& 1 === preg_match( '/^[A-Za-z0-9_]+$/', $identifier );
	}

	/**
	 * Validate source table names.
	 */
	private function valid_source_table( string $table ): bool {
		return '' !== $table
			&& 64 >= strlen( $table )
			&& 1 !== preg_match( '/[\x00-\x1F\x7F\x60]/', $table );
	}

	/**
	 * Compare SHA-256 values.
	 */
	private function same_hash( mixed $left, mixed $right ): bool {
		return is_string( $left )
			&& is_string( $right )
			&& 1 === preg_match( '/^[a-f0-9]{64}$/', $left )
			&& hash_equals( $left, $right );
	}

	/**
	 * Validate one HTTP(S) URL.
	 */
	private function valid_http_url( string $url ): bool {
		return in_array( wp_parse_url( $url, PHP_URL_SCHEME ), array( 'http', 'https' ), true )
			&& is_string( wp_parse_url( $url, PHP_URL_HOST ) );
	}

	/**
	 * Persist one terminal/retryable rewrite blocker.
	 *
	 * @param string              $job_id    Clone job identifier.
	 * @param array<string,mixed> $state     Current state.
	 * @param string              $code      Blocker.
	 * @param bool                $retryable Retry allowed.
	 * @return array<string,mixed>|null
	 */
	private function block( string $job_id, array $state, string $code, bool $retryable ): ?array {
		$blockers                         = is_array( $state['blockers'] ?? null ) ? $state['blockers'] : array();
		$blockers[]                       = $code;
		$state['schema_version']          = ImportEnvironmentStateStore::SCHEMA_VERSION;
		$state['job_id']                  = $job_id;
		$state['status']                  = 'blocked';
		$state['blockers']                = array_values( array_unique( $blockers ) );
		$state['active_tables_untouched'] = true;
		$state['updated_at']              = gmdate( DATE_ATOM );

		if ( ! $this->store->save( $job_id, $state ) ) {
			return null;
		}

		$this->jobs->transition( $job_id, $retryable ? 'failed-retryable' : 'failed-terminal', $code );

		return $this->store->get( $job_id );
	}
}
