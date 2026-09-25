<?php
/**
 * Portable Clone serialization-safe staging environment rewrite.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge\Clone;

use wpdb;

/**
 * Rewrites supported WordPress environment values only inside verified staging tables.
 */
final class ImportEnvironmentRewriter {
	public const MIN_BATCH_ROWS     = 10;
	public const MAX_BATCH_ROWS     = 500;
	public const DEFAULT_BATCH_ROWS = 100;

	private const MAX_NESTING_DEPTH = 64;

	/**
	 * Rewrite state.
	 *
	 * @var ImportRewriteStateStore
	 */
	private ImportRewriteStateStore $store;

	/**
	 * Import state.
	 *
	 * @var ImportStateStore
	 */
	private ImportStateStore $import_state;

	/**
	 * Database staging state.
	 *
	 * @var ImportDatabaseStateStore
	 */
	private ImportDatabaseStateStore $database_state;

	/**
	 * File staging state.
	 *
	 * @var ImportFileStateStore
	 */
	private ImportFileStateStore $file_state;

	/**
	 * Database staging mapper/guard.
	 *
	 * @var ImportDatabaseRestorer
	 */
	private ImportDatabaseRestorer $database_restorer;

	/**
	 * Clone jobs.
	 *
	 * @var CloneJobStore
	 */
	private CloneJobStore $jobs;

	/**
	 * Construct environment rewriter.
	 *
	 * @param ImportRewriteStateStore|null  $store             Optional rewrite-state store.
	 * @param ImportStateStore|null         $import_state      Optional import-state store.
	 * @param ImportDatabaseStateStore|null $database_state    Optional database staging state.
	 * @param ImportFileStateStore|null     $file_state        Optional file staging state.
	 * @param ImportDatabaseRestorer|null   $database_restorer Optional database staging mapper.
	 * @param CloneJobStore|null            $jobs              Optional clone jobs.
	 */
	public function __construct(
		?ImportRewriteStateStore $store = null,
		?ImportStateStore $import_state = null,
		?ImportDatabaseStateStore $database_state = null,
		?ImportFileStateStore $file_state = null,
		?ImportDatabaseRestorer $database_restorer = null,
		?CloneJobStore $jobs = null
	) {
		$this->store             = $store ?? new ImportRewriteStateStore();
		$this->import_state      = $import_state ?? new ImportStateStore();
		$this->database_state    = $database_state ?? new ImportDatabaseStateStore();
		$this->file_state        = $file_state ?? new ImportFileStateStore();
		$this->database_restorer = $database_restorer ?? new ImportDatabaseRestorer();
		$this->jobs              = $jobs ?? new CloneJobStore();
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
	 * Start serialization-safe environment rewrite after database/files staging complete.
	 *
	 * @param string $job_id Clone job identifier.
	 * @return array<string,mixed>|null
	 */
	public function start( string $job_id ): ?array {
		$existing = $this->store->get( $job_id );
		if ( is_array( $existing ) ) {
			return $existing;
		}

		$gate = $this->runtime_gate( $job_id );
		if ( null === $gate || array() === $gate['supported'] ) {
			return null;
		}

		$import = $this->import_state->get( $job_id );
		$db     = $this->database_state->get( $job_id );
		$files  = $this->file_state->get( $job_id );
		if ( ! is_array( $import ) || ! is_array( $db ) || ! is_array( $files ) ) {
			return null;
		}

		$source_home      = (string) ( $import['source_home_url'] ?? '' );
		$source_site      = (string) ( $import['source_site_url'] ?? '' );
		$destination_home = (string) ( $import['destination_home_url'] ?? '' );
		$destination_site = (string) ( $import['destination_site_url'] ?? '' );
		if (
			! $this->valid_http_url( $source_home )
			|| ! $this->valid_http_url( $source_site )
			|| ! $this->valid_http_url( $destination_home )
			|| ! $this->valid_http_url( $destination_site )
			|| $this->same_url( $source_home, $destination_home )
			|| $this->same_url( $source_site, $destination_site )
		) {
			return null;
		}

		$now   = gmdate( DATE_ATOM );
		$state = array(
			'schema_version'           => ImportRewriteStateStore::SCHEMA_VERSION,
			'job_id'                   => $job_id,
			'status'                   => 'running',
			'stage'                    => 'rewrite',
			'table_index'              => 0,
			'table_count'              => count( $gate['supported'] ),
			'cursor_id'                => 0,
			'rows_scanned'             => 0,
			'rows_changed'             => 0,
			'values_changed'           => 0,
			'home_rewrites'            => 0,
			'siteurl_rewrites'         => 0,
			'same_origin_rewrites'     => 0,
			'upload_url_rewrites'      => 0,
			'serialized_values'        => 0,
			'json_values'              => 0,
			'credential_skips'         => 0,
			'opaque_serialized_skips'  => 0,
			'verify_rows_scanned'      => 0,
			'verify_source_urls'       => 0,
			'source_home_url'          => $source_home,
			'source_site_url'          => $source_site,
			'destination_home_url'     => $destination_home,
			'destination_site_url'     => $destination_site,
			'database_manifest_sha256' => (string) ( $db['database_manifest_sha256'] ?? '' ),
			'file_manifest_sha256'     => (string) ( $files['files_manifest_sha256'] ?? '' ),
			'active_tables_untouched'  => true,
			'active_roots_untouched'   => true,
			'blockers'                 => array(),
			'advisories'               => array(),
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
	 * @param int    $batch_rows Maximum rows inspected this request.
	 * @return array<string,mixed>|null
	 */
	public function advance( string $job_id, int $batch_rows = self::DEFAULT_BATCH_ROWS ): ?array {
		$batch_rows = max( self::MIN_BATCH_ROWS, min( self::MAX_BATCH_ROWS, $batch_rows ) );
		$state      = $this->store->get( $job_id ) ?? $this->start( $job_id );

		if ( ! is_array( $state ) || in_array( $state['status'] ?? null, array( 'complete', 'blocked' ), true ) ) {
			return $state;
		}

		$gate = $this->runtime_gate( $job_id, $state );
		if ( null === $gate ) {
			return $this->block( $job_id, $state, 'import-rewrite-runtime-guard-failed', false );
		}

		$tables = $gate['supported'];
		$index  = max( 0, (int) ( $state['table_index'] ?? 0 ) );
		if ( $index >= count( $tables ) ) {
			return 'rewrite' === ( $state['stage'] ?? null )
				? $this->start_verification( $job_id, $state )
				: $this->complete( $job_id, $state );
		}

		$spec = $tables[ $index ];
		if ( 'rewrite' === ( $state['stage'] ?? null ) ) {
			return $this->rewrite_batch( $job_id, $state, $spec, $batch_rows );
		}

		return $this->verify_batch( $job_id, $state, $spec, $batch_rows );
	}

	/**
	 * Rewrite one bounded staging-table batch transactionally.
	 *
	 * @param string              $job_id    Clone job identifier.
	 * @param array<string,mixed> $state     Rewrite state.
	 * @param array<string,mixed> $spec      Supported staging-table spec.
	 * @param int                 $batch_rows Maximum rows.
	 * @return array<string,mixed>|null
	 */
	private function rewrite_batch( string $job_id, array $state, array $spec, int $batch_rows ): ?array {
		$rows = $this->fetch_rows( $spec, (int) ( $state['cursor_id'] ?? 0 ), $batch_rows );
		if ( null === $rows ) {
			return $this->block( $job_id, $state, 'import-rewrite-row-read-failed', true );
		}
		if ( array() === $rows ) {
			return $this->advance_table( $job_id, $state );
		}

		global $wpdb;
		if ( ! $wpdb instanceof wpdb ) {
			return $this->block( $job_id, $state, 'import-rewrite-database-unavailable', true );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Transaction spans only deterministic job-owned staging-table rewrites + state.
		if ( false === $wpdb->query( 'START TRANSACTION' ) ) {
			return $this->block( $job_id, $state, 'import-rewrite-transaction-start-failed', true );
		}

		$next = $state;
		foreach ( $rows as $row ) {
			$id_column = (string) $spec['id_column'];
			$row_id    = isset( $row[ $id_column ] ) && is_numeric( $row[ $id_column ] ) ? (int) $row[ $id_column ] : 0;
			if ( 0 >= $row_id ) {
				$wpdb->query( 'ROLLBACK' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
				return $this->block( $job_id, $state, 'import-rewrite-row-id-invalid', false );
			}

			$result = $this->rewrite_row( $row, $spec, $next );
			if ( null === $result ) {
				$wpdb->query( 'ROLLBACK' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
				return $this->block( $job_id, $state, 'import-rewrite-value-transform-failed', false );
			}

			$next = $result['state'];
			++$next['rows_scanned'];
			$next['cursor_id'] = $row_id;

			if ( array() === $result['changes'] ) {
				continue;
			}

			$formats = array_fill( 0, count( $result['changes'] ), '%s' );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Updates only the validated deterministic job-owned staging table.
			$updated = $wpdb->update(
				(string) $spec['staging_table'],
				$result['changes'],
				array( $id_column => $row_id ),
				$formats,
				array( '%d' )
			);
			if ( false === $updated ) {
				$wpdb->query( 'ROLLBACK' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
				return $this->block( $job_id, $state, 'import-rewrite-row-update-failed', true );
			}
			++$next['rows_changed'];
			$next['values_changed'] += count( $result['changes'] );
		}

		$next['updated_at'] = gmdate( DATE_ATOM );
		if ( ! $this->store->save( $job_id, $next ) ) {
			$wpdb->query( 'ROLLBACK' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			wp_cache_delete( ImportRewriteStateStore::OPTION_NAME, 'options' );

			return $this->block( $job_id, $state, 'import-rewrite-state-transaction-failed', true );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		if ( false === $wpdb->query( 'COMMIT' ) ) {
			$wpdb->query( 'ROLLBACK' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			wp_cache_delete( ImportRewriteStateStore::OPTION_NAME, 'options' );

			return $this->block( $job_id, $state, 'import-rewrite-transaction-commit-failed', true );
		}

		return $this->persist_progress( $job_id, $next );
	}

	/**
	 * Verify no supported staging value still needs a source-environment rewrite.
	 *
	 * @param string              $job_id    Clone job identifier.
	 * @param array<string,mixed> $state     Rewrite state.
	 * @param array<string,mixed> $spec      Supported staging-table spec.
	 * @param int                 $batch_rows Maximum rows.
	 * @return array<string,mixed>|null
	 */
	private function verify_batch( string $job_id, array $state, array $spec, int $batch_rows ): ?array {
		$rows = $this->fetch_rows( $spec, (int) ( $state['cursor_id'] ?? 0 ), $batch_rows );
		if ( null === $rows ) {
			return $this->block( $job_id, $state, 'import-rewrite-verify-read-failed', true );
		}
		if ( array() === $rows ) {
			return $this->advance_table( $job_id, $state );
		}

		$next = $state;
		foreach ( $rows as $row ) {
			$id_column = (string) $spec['id_column'];
			$row_id    = isset( $row[ $id_column ] ) && is_numeric( $row[ $id_column ] ) ? (int) $row[ $id_column ] : 0;
			if ( 0 >= $row_id ) {
				return $this->block( $job_id, $state, 'import-rewrite-verify-row-id-invalid', false );
			}

			$probe = $this->rewrite_row( $row, $spec, $next, true );
			if ( null === $probe ) {
				return $this->block( $job_id, $state, 'import-rewrite-verify-transform-failed', false );
			}

			$next['verify_rows_scanned'] = (int) ( $next['verify_rows_scanned'] ?? 0 ) + 1;
			$next['cursor_id']           = $row_id;
			if ( array() !== $probe['changes'] ) {
				$next['verify_source_urls'] = (int) ( $next['verify_source_urls'] ?? 0 ) + count( $probe['changes'] );
				return $this->block( $job_id, $next, 'import-rewrite-source-environment-remains', false );
			}
		}

		return $this->persist_progress( $job_id, $next );
	}

	/**
	 * Transform one supported row without touching unsupported columns.
	 *
	 * @param array<string,mixed> $row    Database row.
	 * @param array<string,mixed> $spec   Table rewrite spec.
	 * @param array<string,mixed> $state  Rewrite state.
	 * @param bool                $verify Verification-only pass.
	 * @return array{changes:array<string,string>,state:array<string,mixed>}|null
	 */
	private function rewrite_row( array $row, array $spec, array $state, bool $verify = false ): ?array {
		$key = '';
		if ( is_string( $spec['key_column'] ?? null ) && '' !== $spec['key_column'] ) {
			$key_column = $spec['key_column'];
			$key        = is_string( $row[ $key_column ] ?? null ) ? $row[ $key_column ] : '';
		}

		if ( '' !== $key && $this->sensitive_key( $key ) ) {
			if ( ! $verify ) {
				++$state['credential_skips'];
			}
			return array(
				'changes' => array(),
				'state'   => $state,
			);
		}

		$changes = array();
		foreach ( $spec['value_columns'] as $column ) {
			if ( ! is_string( $column ) || ! array_key_exists( $column, $row ) || null === $row[ $column ] ) {
				continue;
			}

			$value = (string) $row[ $column ];
			if ( 'options' === ( $spec['suffix'] ?? null ) && 'option_value' === $column ) {
				if ( 'home' === $key ) {
					$transformed = $this->rewrite_environment_option( $value, (string) $state['source_home_url'], (string) $state['destination_home_url'] );
					if ( null === $transformed ) {
						return null;
					}
					if ( $transformed !== $value ) {
						$changes[ $column ] = $transformed;
						if ( ! $verify ) {
							++$state['home_rewrites'];
						}
					}
					continue;
				}
				if ( 'siteurl' === $key ) {
					$transformed = $this->rewrite_environment_option( $value, (string) $state['source_site_url'], (string) $state['destination_site_url'] );
					if ( null === $transformed ) {
						return null;
					}
					if ( $transformed !== $value ) {
						$changes[ $column ] = $transformed;
						if ( ! $verify ) {
							++$state['siteurl_rewrites'];
						}
					}
					continue;
				}
			}

			$result = $this->transform_value( $value, $state, 0, $verify );
			if ( null === $result ) {
				return null;
			}
			$state = $result['state'];
			if ( $result['value'] !== $value ) {
				$changes[ $column ] = $result['value'];
			}
		}

		return array(
			'changes' => $changes,
			'state'   => $state,
		);
	}

	/**
	 * Transform one database string, preserving serialization/JSON structure.
	 *
	 * @param string              $value  Raw staging value.
	 * @param array<string,mixed> $state  Rewrite state.
	 * @param int                 $depth  Recursion depth.
	 * @param bool                $verify Verification-only pass.
	 * @return array{value:string,state:array<string,mixed>}|null
	 */
	private function transform_value( string $value, array $state, int $depth, bool $verify ): ?array {
		if ( self::MAX_NESTING_DEPTH < $depth ) {
			return null;
		}

		if ( is_serialized( $value, false ) ) {
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize -- Import requires PHP serialization parsing with classes explicitly forbidden.
			$decoded = @unserialize( trim( $value ), array( 'allowed_classes' => false ) );
			if ( false === $decoded && 'b:0;' !== trim( $value ) ) {
				if ( $this->opaque_contains_source_environment( $value, $state ) ) {
					return null;
				}
				if ( ! $verify ) {
					++$state['opaque_serialized_skips'];
				}
				return array(
					'value' => $value,
					'state' => $state,
				);
			}

			$nested = $this->transform_nested( $decoded, $state, $depth + 1, $verify );
			if ( null === $nested ) {
				if ( $this->opaque_contains_source_environment( $value, $state ) ) {
					return null;
				}
				if ( ! $verify ) {
					++$state['opaque_serialized_skips'];
				}
				return array(
					'value' => $value,
					'state' => $state,
				);
			}

			if ( ! $verify ) {
				++$nested['state']['serialized_values'];
			}
			if ( $nested['value'] === $decoded ) {
				return array(
					'value' => $value,
					'state' => $nested['state'],
				);
			}

			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- Re-serializes only a safely decoded payload whose nested URL values actually changed.
			$encoded = serialize( $nested['value'] );

			return array(
				'value' => $encoded,
				'state' => $nested['state'],
			);
		}

		if ( $this->looks_serialized( $value ) ) {
			if ( $this->opaque_contains_source_environment( $value, $state ) ) {
				return null;
			}
			if ( ! $verify ) {
				++$state['opaque_serialized_skips'];
			}
			return array(
				'value' => $value,
				'state' => $state,
			);
		}

		$json = $this->decode_json_container( $value );
		if ( null !== $json ) {
			$nested = $this->transform_nested( $json, $state, $depth + 1, $verify );
			if ( null === $nested ) {
				return null;
			}
			if ( ! $verify ) {
				++$nested['state']['json_values'];
			}
			if ( $nested['value'] === $json ) {
				return array(
					'value' => $value,
					'state' => $nested['state'],
				);
			}
			$encoded = wp_json_encode( $nested['value'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
			if ( ! is_string( $encoded ) ) {
				return null;
			}

			return array(
				'value' => $encoded,
				'state' => $nested['state'],
			);
		}

		return $this->transform_plain_string( $value, $state, $verify );
	}

	/**
	 * Recursively transform scalar/array containers without instantiating objects.
	 *
	 * @param mixed               $value  Decoded value.
	 * @param array<string,mixed> $state  Rewrite state.
	 * @param int                 $depth  Recursion depth.
	 * @param bool                $verify Verification-only pass.
	 * @return array{value:mixed,state:array<string,mixed>}|null
	 */
	private function transform_nested( mixed $value, array $state, int $depth, bool $verify ): ?array {
		if ( self::MAX_NESTING_DEPTH < $depth || is_object( $value ) || is_resource( $value ) ) {
			return null;
		}

		if ( is_string( $value ) ) {
			$result = $this->transform_plain_string( $value, $state, $verify );

			return array(
				'value' => $result['value'],
				'state' => $result['state'],
			);
		}

		if ( ! is_array( $value ) ) {
			return array(
				'value' => $value,
				'state' => $state,
			);
		}

		$transformed = array();
		foreach ( $value as $key => $item ) {
			$result = $this->transform_nested( $item, $state, $depth + 1, $verify );
			if ( null === $result ) {
				return null;
			}
			$state               = $result['state'];
			$transformed[ $key ] = $result['value'];
		}

		return array(
			'value' => $transformed,
			'state' => $state,
		);
	}

	/**
	 * Rewrite absolute HTTP(S) URL tokens in one plain string.
	 *
	 * @param string              $value  Plain value.
	 * @param array<string,mixed> $state  Rewrite state.
	 * @param bool                $verify Verification-only pass.
	 * @return array{value:string,state:array<string,mixed>}
	 */
	private function transform_plain_string( string $value, array $state, bool $verify ): array {
		$rewritten = preg_replace_callback(
			'~https?://[^\s\x00-\x1F"\'<>]+~i',
			function ( array $match ) use ( &$state, $verify ): string {
				$url = (string) ( $match[0] ?? '' );
				$end = '';
				while ( '' !== $url && str_contains( '.,;:!?)]}', substr( $url, -1 ) ) ) {
					$end = substr( $url, -1 ) . $end;
					$url = substr( $url, 0, -1 );
				}

				$result = $this->rewrite_absolute_url( $url, $state, $verify );
				$state  = $result['state'];

				return $result['url'] . $end;
			},
			$value
		);

		return array(
			'value' => is_string( $rewritten ) ? $rewritten : $value,
			'state' => $state,
		);
	}

	/**
	 * Rewrite one same-origin URL to the matching destination environment.
	 *
	 * @param string              $url    Absolute URL.
	 * @param array<string,mixed> $state  Rewrite state.
	 * @param bool                $verify Verification-only pass.
	 * @return array{url:string,state:array<string,mixed>}
	 */
	private function rewrite_absolute_url( string $url, array $state, bool $verify ): array {
		if ( '' === $url || ! $this->valid_http_url( $url ) || null !== wp_parse_url( $url, PHP_URL_USER ) ) {
			return array(
				'url'   => $url,
				'state' => $state,
			);
		}

		$maps = array(
			array( (string) $state['source_home_url'], (string) $state['destination_home_url'] ),
			array( (string) $state['source_site_url'], (string) $state['destination_site_url'] ),
		);
		usort(
			$maps,
			static fn( array $left, array $right ): int => strlen( (string) wp_parse_url( $right[0], PHP_URL_PATH ) )
				<=> strlen( (string) wp_parse_url( $left[0], PHP_URL_PATH ) )
		);

		foreach ( $maps as $map ) {
			$source      = (string) $map[0];
			$destination = (string) $map[1];
			if ( ! $this->same_origin( $url, $source ) ) {
				continue;
			}

			$source_path = $this->base_path( $source );
			$url_path    = $this->url_path( $url );
			if ( ! $this->path_under_base( $url_path, $source_path ) ) {
				continue;
			}

			$relative         = ltrim( substr( $url_path, strlen( rtrim( $source_path, '/' ) ) ), '/' );
			$destination_path = $this->join_url_path( $this->base_path( $destination ), $relative );
			$rebuilt          = $this->rebuild_url( $url, $destination, $destination_path );
			if ( $rebuilt === $url ) {
				return array(
					'url'   => $url,
					'state' => $state,
				);
			}

			if ( ! $verify ) {
				if ( str_contains( strtolower( $url_path ), '/wp-content/uploads/' ) ) {
					++$state['upload_url_rewrites'];
				} else {
					++$state['same_origin_rewrites'];
				}
			}

			return array(
				'url'   => $rebuilt,
				'state' => $state,
			);
		}

		return array(
			'url'   => $url,
			'state' => $state,
		);
	}

	/**
	 * Rewrite protected home/siteurl options only when their source value matches package evidence.
	 *
	 * @param string $current     Current option value.
	 * @param string $source      Accepted source environment URL.
	 * @param string $destination Current destination environment URL.
	 */
	private function rewrite_environment_option( string $current, string $source, string $destination ): ?string {
		if ( $this->same_url( $current, $destination ) ) {
			return $current;
		}
		if ( ! $this->same_url( $current, $source ) ) {
			return null;
		}

		return $destination;
	}

	/**
	 * Start a second read-only pass proving rewrite idempotence.
	 *
	 * @param string              $job_id Clone job identifier.
	 * @param array<string,mixed> $state  Rewrite state.
	 * @return array<string,mixed>|null
	 */
	private function start_verification( string $job_id, array $state ): ?array {
		if ( 1 > (int) ( $state['home_rewrites'] ?? 0 ) || 1 > (int) ( $state['siteurl_rewrites'] ?? 0 ) ) {
			return $this->block( $job_id, $state, 'import-rewrite-core-options-missing', false );
		}

		$state['stage']                = 'verify';
		$state['table_index']          = 0;
		$state['cursor_id']            = 0;
		$state['verify_rows_scanned']  = 0;
		$state['verify_source_urls']   = 0;
		$state['updated_at']           = gmdate( DATE_ATOM );
		if ( ! $this->store->save( $job_id, $state ) ) {
			return null;
		}

		$this->jobs->update_progress(
			$job_id,
			'rewrite-environment',
			'verify:0:0',
			array(
				'completed' => 0,
				'total'     => null,
			)
		);

		return $this->store->get( $job_id );
	}

	/**
	 * Complete rewrite only when the second pass is idempotent and staging remains isolated.
	 *
	 * @param string              $job_id Clone job identifier.
	 * @param array<string,mixed> $state  Rewrite state.
	 * @return array<string,mixed>|null
	 */
	private function complete( string $job_id, array $state ): ?array {
		if ( 0 !== (int) ( $state['verify_source_urls'] ?? 0 ) || null === $this->runtime_gate( $job_id, $state ) ) {
			return $this->block( $job_id, $state, 'import-rewrite-final-verification-failed', false );
		}

		$state['status']       = 'complete';
		$state['stage']        = 'complete';
		$state['updated_at']   = gmdate( DATE_ATOM );
		$state['completed_at'] = $state['updated_at'];
		if ( ! $this->store->save( $job_id, $state ) ) {
			return null;
		}

		$this->jobs->update_progress(
			$job_id,
			'rewrite-environment',
			'complete',
			array(
				'completed' => (int) ( $state['rows_scanned'] ?? 0 ),
				'total'     => (int) ( $state['rows_scanned'] ?? 0 ),
			)
		);

		return $this->store->get( $job_id );
	}

	/**
	 * Advance to the next supported staging table.
	 *
	 * @param string              $job_id Clone job identifier.
	 * @param array<string,mixed> $state  Rewrite state.
	 * @return array<string,mixed>|null
	 */
	private function advance_table( string $job_id, array $state ): ?array {
		$state['table_index'] = (int) ( $state['table_index'] ?? 0 ) + 1;
		$state['cursor_id']   = 0;
		$state['updated_at']  = gmdate( DATE_ATOM );

		if ( ! $this->store->save( $job_id, $state ) ) {
			return null;
		}

		return $this->store->get( $job_id );
	}

	/**
	 * Persist resumable progress.
	 *
	 * @param string              $job_id Clone job identifier.
	 * @param array<string,mixed> $state  Rewrite state.
	 * @return array<string,mixed>|null
	 */
	private function persist_progress( string $job_id, array $state ): ?array {
		$state['updated_at'] = gmdate( DATE_ATOM );
		if ( ! $this->store->save( $job_id, $state ) ) {
			return null;
		}

		$cursor = (string) $state['stage'] . ':' . (string) (int) $state['table_index'] . ':' . (string) (int) $state['cursor_id'];
		$this->jobs->update_progress(
			$job_id,
			'rewrite-environment',
			$cursor,
			array(
				'completed' => 'verify' === $state['stage']
					? (int) ( $state['verify_rows_scanned'] ?? 0 )
					: (int) ( $state['rows_scanned'] ?? 0 ),
				'total'     => null,
			)
		);

		return $this->store->get( $job_id );
	}

	/**
	 * Build the fresh guarded supported staging-table plan.
	 *
	 * @param string                   $job_id Clone job identifier.
	 * @param array<string,mixed>|null $state  Optional existing rewrite state.
	 * @return array{supported:list<array<string,mixed>>,plan:array<string,mixed>}|null
	 */
	private function runtime_gate( string $job_id, ?array $state = null ): ?array {
		$job   = $this->jobs->get( $job_id );
		$db    = $this->database_state->get( $job_id );
		$files = $this->file_state->get( $job_id );
		$plan  = $this->database_restorer->staging_plan( $job_id );
		if (
			! is_array( $job )
			|| 'import' !== ( $job['operation'] ?? null )
			|| ! is_array( $db )
			|| 'complete' !== ( $db['status'] ?? null )
			|| true !== ( $db['active_tables_untouched'] ?? false )
			|| ! is_array( $files )
			|| 'complete' !== ( $files['status'] ?? null )
			|| true !== ( $files['active_roots_untouched'] ?? false )
			|| ! is_array( $plan )
		) {
			return null;
		}

		if (
			is_array( $state )
			&& (
				! hash_equals( (string) ( $state['database_manifest_sha256'] ?? '' ), (string) ( $db['database_manifest_sha256'] ?? '' ) )
				|| ! hash_equals( (string) ( $state['file_manifest_sha256'] ?? '' ), (string) ( $files['files_manifest_sha256'] ?? '' ) )
				|| true !== ( $state['active_tables_untouched'] ?? false )
				|| true !== ( $state['active_roots_untouched'] ?? false )
			)
		) {
			return null;
		}

		$supported = $this->supported_tables( $plan );
		if ( null === $supported ) {
			return null;
		}

		return array(
			'supported' => $supported,
			'plan'      => $plan,
		);
	}

	/**
	 * Reduce full staging map to supported WordPress environment rewrite surfaces.
	 *
	 * @param array<string,mixed> $plan Validated database staging plan.
	 * @return list<array<string,mixed>>|null
	 */
	private function supported_tables( array $plan ): ?array {
		$source_prefix = is_string( $plan['source_prefix'] ?? null ) ? $plan['source_prefix'] : '';
		$tables        = is_array( $plan['tables'] ?? null ) ? $plan['tables'] : array();
		$definitions   = array(
			'options'       => array( 'id' => 'option_id', 'key' => 'option_name', 'values' => array( 'option_value' ) ),
			'posts'         => array( 'id' => 'ID', 'key' => '', 'values' => array( 'post_content', 'post_excerpt', 'post_content_filtered' ) ),
			'postmeta'      => array( 'id' => 'meta_id', 'key' => 'meta_key', 'values' => array( 'meta_value' ) ),
			'usermeta'      => array( 'id' => 'umeta_id', 'key' => 'meta_key', 'values' => array( 'meta_value' ) ),
			'termmeta'      => array( 'id' => 'meta_id', 'key' => 'meta_key', 'values' => array( 'meta_value' ) ),
			'commentmeta'   => array( 'id' => 'meta_id', 'key' => 'meta_key', 'values' => array( 'meta_value' ) ),
			'comments'      => array( 'id' => 'comment_ID', 'key' => '', 'values' => array( 'comment_content' ) ),
			'term_taxonomy' => array( 'id' => 'term_taxonomy_id', 'key' => '', 'values' => array( 'description' ) ),
		);

		$supported = array();
		foreach ( $tables as $table ) {
			if ( ! is_array( $table ) || ! is_string( $table['source_table'] ?? null ) ) {
				return null;
			}
			$source = $table['source_table'];
			if ( ! str_starts_with( $source, $source_prefix ) ) {
				return null;
			}
			$suffix = substr( $source, strlen( $source_prefix ) );
			if ( ! isset( $definitions[ $suffix ] ) ) {
				continue;
			}

			$definition = $definitions[ $suffix ];
			$columns    = is_array( $table['columns'] ?? null ) ? array_values( array_filter( $table['columns'], 'is_string' ) ) : array();
			$required   = array_merge(
				array( (string) $definition['id'] ),
				'' === (string) $definition['key'] ? array() : array( (string) $definition['key'] ),
				$definition['values']
			);
			if ( array_diff( $required, $columns ) ) {
				return null;
			}

			$supported[] = array(
				'suffix'        => $suffix,
				'staging_table' => (string) $table['staging_table'],
				'target_table'  => (string) $table['target_table'],
				'id_column'     => (string) $definition['id'],
				'key_column'    => (string) $definition['key'],
				'value_columns' => $definition['values'],
				'row_count'     => (int) ( $table['row_count'] ?? 0 ),
			);
		}

		$has_options = false;
		$has_posts   = false;
		foreach ( $supported as $table ) {
			$has_options = $has_options || 'options' === $table['suffix'];
			$has_posts   = $has_posts || 'posts' === $table['suffix'];
		}

		return $has_options && $has_posts ? $supported : null;
	}

	/**
	 * Fetch one stable ID-ordered staging batch.
	 *
	 * @param array<string,mixed> $spec       Supported table spec.
	 * @param int                 $cursor_id  Last processed ID.
	 * @param int                 $batch_rows Maximum rows.
	 * @return list<array<string,mixed>>|null
	 */
	private function fetch_rows( array $spec, int $cursor_id, int $batch_rows ): ?array {
		global $wpdb;
		if ( ! $wpdb instanceof wpdb ) {
			return null;
		}

		$table     = (string) ( $spec['staging_table'] ?? '' );
		$id_column = (string) ( $spec['id_column'] ?? '' );
		$columns   = array_merge(
			array( $id_column ),
			'' === (string) ( $spec['key_column'] ?? '' ) ? array() : array( (string) $spec['key_column'] ),
			is_array( $spec['value_columns'] ?? null ) ? $spec['value_columns'] : array()
		);
		if (
			1 !== preg_match( '/^[A-Za-z0-9_]{1,64}$/', $table )
			|| 1 !== preg_match( '/^[A-Za-z0-9_]{1,64}$/', $id_column )
			|| array() === $columns
		) {
			return null;
		}

		foreach ( $columns as $column ) {
			if ( ! is_string( $column ) || 1 !== preg_match( '/^[A-Za-z0-9_]{1,64}$/', $column ) ) {
				return null;
			}
		}

		$quoted_table   = $this->quote_identifier( $table );
		$quoted_id      = $this->quote_identifier( $id_column );
		$quoted_columns = implode( ', ', array_map( array( $this, 'quote_identifier' ), array_values( array_unique( $columns ) ) ) );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared -- Identifiers come only from validated staging plan/core definitions.
		$sql = $wpdb->prepare(
			"SELECT {$quoted_columns} FROM {$quoted_table} WHERE {$quoted_id} > %d ORDER BY {$quoted_id} ASC LIMIT %d",
			$cursor_id,
			$batch_rows
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- Reads only deterministic job-owned staging tables.
		$rows = $wpdb->get_results( $sql, ARRAY_A );

		return is_array( $rows ) ? $rows : null;
	}

	/**
	 * Whether an option/meta key is credential/token-like and must remain opaque.
	 *
	 * @param string $key Option/meta key.
	 */
	private function sensitive_key( string $key ): bool {
		return 1 === preg_match(
			'/(?:password|passwd|secret|token|api[_-]?key|auth[_-]?key|private[_-]?key|license[_-]?key|client[_-]?secret|access[_-]?token|refresh[_-]?token|salt)/i',
			$key
		);
	}

	/**
	 * Decode only JSON object/array containers.
	 *
	 * @param string $value Raw value.
	 */
	private function decode_json_container( string $value ): mixed {
		$trimmed = trim( $value );
		if ( '' === $trimmed || ! in_array( $trimmed[0], array( '{', '[' ), true ) ) {
			return null;
		}

		$decoded = json_decode( $trimmed, true );

		return JSON_ERROR_NONE === json_last_error() && is_array( $decoded ) ? $decoded : null;
	}

	/**
	 * Detect source-environment URLs inside an opaque serialized-looking byte string.
	 *
	 * This is detection only: opaque payload bytes are never search/replaced because
	 * doing so could corrupt serialized length metadata or unsupported object state.
	 *
	 * @param string              $value Opaque serialized-looking bytes.
	 * @param array<string,mixed> $state Rewrite state.
	 */
	private function opaque_contains_source_environment( string $value, array $state ): bool {
		$needles = array();
		foreach ( array( 'source_home_url', 'source_site_url' ) as $key ) {
			$source = is_string( $state[ $key ] ?? null ) ? untrailingslashit( $state[ $key ] ) : '';
			if ( '' === $source ) {
				continue;
			}

			$needles[] = $source;
			$needles[] = str_replace( '/', '\\/', $source );
		}

		foreach ( array_values( array_unique( $needles ) ) as $needle ) {
			if ( '' !== $needle && str_contains( $value, $needle ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Whether a value resembles serialized PHP but failed strict WordPress recognition.
	 *
	 * @param string $value Raw value.
	 */
	private function looks_serialized( string $value ): bool {
		return 1 === preg_match( '/^(?:a|O|C|s|i|b|d|N):/', trim( $value ) );
	}

	/**
	 * Compare URLs ignoring only trailing slash.
	 *
	 * @param string $left  Left URL.
	 * @param string $right Right URL.
	 */
	private function same_url( string $left, string $right ): bool {
		return untrailingslashit( $left ) === untrailingslashit( $right );
	}

	/**
	 * Whether two URLs have equal scheme/host/effective port.
	 *
	 * @param string $left  Left URL.
	 * @param string $right Right URL.
	 */
	private function same_origin( string $left, string $right ): bool {
		return '' !== $this->origin_key( $left ) && $this->origin_key( $left ) === $this->origin_key( $right );
	}

	/**
	 * Return normalized origin key.
	 *
	 * @param string $url Absolute URL.
	 */
	private function origin_key( string $url ): string {
		$scheme = wp_parse_url( $url, PHP_URL_SCHEME );
		$host   = wp_parse_url( $url, PHP_URL_HOST );
		$port   = wp_parse_url( $url, PHP_URL_PORT );
		if ( ! is_string( $scheme ) || ! is_string( $host ) ) {
			return '';
		}

		$scheme = strtolower( $scheme );
		$host   = strtolower( $host );
		if ( ! in_array( $scheme, array( 'http', 'https' ), true ) ) {
			return '';
		}

		$effective_port = is_int( $port ) ? $port : ( 'https' === $scheme ? 443 : 80 );

		return $scheme . '://' . $host . ':' . (string) $effective_port;
	}

	/**
	 * Return normalized URL path.
	 *
	 * @param string $url URL.
	 */
	private function url_path( string $url ): string {
		$path = wp_parse_url( $url, PHP_URL_PATH );

		return is_string( $path ) && '' !== $path ? '/' . ltrim( $path, '/' ) : '/';
	}

	/**
	 * Return normalized base path with trailing slash.
	 *
	 * @param string $url URL.
	 */
	private function base_path( string $url ): string {
		return trailingslashit( rtrim( $this->url_path( $url ), '/' ) );
	}

	/**
	 * Whether URL path belongs to source base path.
	 *
	 * @param string $path URL path.
	 * @param string $base Base path.
	 */
	private function path_under_base( string $path, string $base ): bool {
		$base_no_slash = rtrim( $base, '/' );

		return $path === $base_no_slash || str_starts_with( $path, $base );
	}

	/**
	 * Join destination base path and source-relative path.
	 *
	 * @param string $base     Destination base.
	 * @param string $relative Relative path.
	 */
	private function join_url_path( string $base, string $relative ): string {
		$base = '/' . trim( $base, '/' );

		return '/' === $base
			? '/' . ltrim( $relative, '/' )
			: rtrim( $base, '/' ) . ( '' === $relative ? '' : '/' . ltrim( $relative, '/' ) );
	}

	/**
	 * Rebuild mapped URL preserving query/fragment.
	 *
	 * @param string $original    Original absolute URL.
	 * @param string $destination Destination environment URL.
	 * @param string $path        Rewritten path.
	 */
	private function rebuild_url( string $original, string $destination, string $path ): string {
		$scheme = wp_parse_url( $destination, PHP_URL_SCHEME );
		$host   = wp_parse_url( $destination, PHP_URL_HOST );
		$port   = wp_parse_url( $destination, PHP_URL_PORT );
		if ( ! is_string( $scheme ) || ! is_string( $host ) ) {
			return $original;
		}

		$url = strtolower( $scheme ) . '://' . strtolower( $host );
		if ( is_int( $port ) && ! ( 80 === $port && 'http' === strtolower( $scheme ) ) && ! ( 443 === $port && 'https' === strtolower( $scheme ) ) ) {
			$url .= ':' . (string) $port;
		}
		$url .= $path;

		$query = wp_parse_url( $original, PHP_URL_QUERY );
		if ( is_string( $query ) && '' !== $query ) {
			$url .= '?' . $query;
		}
		$fragment = wp_parse_url( $original, PHP_URL_FRAGMENT );
		if ( is_string( $fragment ) && '' !== $fragment ) {
			$url .= '#' . $fragment;
		}

		return $url;
	}

	/**
	 * Validate one HTTP(S) URL.
	 *
	 * @param string $url URL.
	 */
	private function valid_http_url( string $url ): bool {
		return in_array( wp_parse_url( $url, PHP_URL_SCHEME ), array( 'http', 'https' ), true )
			&& is_string( wp_parse_url( $url, PHP_URL_HOST ) );
	}

	/**
	 * Quote one validated MySQL identifier.
	 *
	 * @param string $identifier Identifier.
	 */
	private function quote_identifier( string $identifier ): string {
		return '`' . str_replace( '`', '``', $identifier ) . '`';
	}

	/**
	 * Persist blocker and job failure state.
	 *
	 * @param string              $job_id    Clone job identifier.
	 * @param array<string,mixed> $state     Rewrite state.
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
}
