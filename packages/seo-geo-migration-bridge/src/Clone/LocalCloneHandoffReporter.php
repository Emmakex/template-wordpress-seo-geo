<?php
/**
 * Portable Clone final local target verification and handoff report.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge\Clone;

use wpdb;

/**
 * Produces a bounded, reproducible, read-only handoff report for a promoted local clone.
 */
final class LocalCloneHandoffReporter {
	/**
	 * Report state.
	 *
	 * @var LocalCloneHandoffReportStateStore
	 */
	private LocalCloneHandoffReportStateStore $store;

	/**
	 * Final file-promotion authority.
	 *
	 * @var LocalCloneFilePromoter
	 */
	private LocalCloneFilePromoter $files;

	/**
	 * Parent database activation journal.
	 *
	 * @var LocalCloneDatabaseActivationStateStore
	 */
	private LocalCloneDatabaseActivationStateStore $database;

	/**
	 * Child database activation journal.
	 *
	 * @var ImportDatabaseActivationStateStore
	 */
	private ImportDatabaseActivationStateStore $child_database;

	/**
	 * Child file-promotion journal.
	 *
	 * @var ImportFilePromotionStateStore
	 */
	private ImportFilePromotionStateStore $child_files;

	/**
	 * Child import state.
	 *
	 * @var ImportStateStore
	 */
	private ImportStateStore $imports;

	/**
	 * Parent clone jobs.
	 *
	 * @var CloneJobStore
	 */
	private CloneJobStore $jobs;

	/**
	 * Construct reporter.
	 *
	 * @param LocalCloneHandoffReportStateStore|null    $store          Optional report store.
	 * @param LocalCloneFilePromoter|null               $files          Optional file authority.
	 * @param LocalCloneDatabaseActivationStateStore|null $database       Optional parent DB journal.
	 * @param ImportDatabaseActivationStateStore|null   $child_database Optional child DB journal.
	 * @param ImportFilePromotionStateStore|null        $child_files    Optional child file journal.
	 * @param ImportStateStore|null                     $imports        Optional child import state.
	 * @param CloneJobStore|null                        $jobs           Optional parent clone jobs.
	 */
	public function __construct(
		?LocalCloneHandoffReportStateStore $store = null,
		?LocalCloneFilePromoter $files = null,
		?LocalCloneDatabaseActivationStateStore $database = null,
		?ImportDatabaseActivationStateStore $child_database = null,
		?ImportFilePromotionStateStore $child_files = null,
		?ImportStateStore $imports = null,
		?CloneJobStore $jobs = null
	) {
		$this->store          = $store ?? new LocalCloneHandoffReportStateStore();
		$this->files          = $files ?? new LocalCloneFilePromoter();
		$this->database       = $database ?? new LocalCloneDatabaseActivationStateStore();
		$this->child_database = $child_database ?? new ImportDatabaseActivationStateStore();
		$this->child_files    = $child_files ?? new ImportFilePromotionStateStore();
		$this->imports        = $imports ?? new ImportStateStore();
		$this->jobs           = $jobs ?? new CloneJobStore();
	}

	/**
	 * Return saved report.
	 *
	 * @param string $job_id Parent local-clone job identifier.
	 * @return array<string,mixed>|null
	 */
	public function snapshot( string $job_id ): ?array {
		return $this->store->get( $job_id );
	}

	/**
	 * Rebuild and persist the final read-only handoff report.
	 *
	 * @param string $job_id Parent local-clone job identifier.
	 * @return array<string,mixed>|null
	 */
	public function prepare( string $job_id ): ?array {
		$state = $this->current_evidence( $job_id );
		if ( null === $state || ! $this->store->save( $job_id, $state ) ) {
			return null;
		}

		if ( 'ready' === ( $state['status'] ?? null ) ) {
			$this->jobs->transition( $job_id, 'complete', null );
			$this->jobs->update_progress(
				$job_id,
				'final-handoff',
				'local-clone-handoff-ready',
				array(
					'completed' => 1,
					'total'     => 1,
				)
			);
		}

		return $this->store->get( $job_id );
	}

	/**
	 * Return saved handoff only while fresh target evidence still reproduces its hash.
	 *
	 * @param string $job_id Parent local-clone job identifier.
	 * @return array<string,mixed>|null
	 */
	public function verified_snapshot( string $job_id ): ?array {
		$saved = $this->store->get( $job_id );
		if (
			! is_array( $saved )
			|| 'ready' !== ( $saved['status'] ?? null )
			|| true !== ( $saved['handoff_ready'] ?? false )
			|| array() !== ( $saved['blockers'] ?? array() )
		) {
			return null;
		}

		$fresh = $this->current_evidence( $job_id );
		if (
			! is_array( $fresh )
			|| 'ready' !== ( $fresh['status'] ?? null )
			|| ! hash_equals( (string) $saved['report_sha256'], (string) $fresh['report_sha256'] )
		) {
			return null;
		}

		return $saved;
	}

	/**
	 * Build fresh read-only evidence from the active isolated target.
	 *
	 * @param string $job_id Parent local-clone job identifier.
	 * @return array<string,mixed>|null
	 */
	private function current_evidence( string $job_id ): ?array {
		$promotion = $this->files->verified_snapshot( $job_id );
		if ( ! is_array( $promotion ) ) {
			return $this->blocked( $job_id, 'local-handoff-file-promotion-not-verified' );
		}

		$database = $this->database->get( $job_id );
		$child_id = (string) ( $promotion['child_import_job_id'] ?? '' );
		$import   = '' !== $child_id ? $this->imports->get( $child_id ) : null;
		$child_db = '' !== $child_id ? $this->child_database->get( $child_id ) : null;
		$child_files = '' !== $child_id ? $this->child_files->get( $child_id ) : null;

		if (
			! is_array( $database )
			|| ! is_array( $import )
			|| ! is_array( $child_db )
			|| ! is_array( $child_files )
			|| 'activated' !== ( $database['status'] ?? null )
			|| 'activated' !== ( $child_db['status'] ?? null )
			|| 'verified' !== ( $child_files['status'] ?? null )
			|| true !== ( $database['database_swapped'] ?? false )
			|| true !== ( $database['target_database_active'] ?? false )
			|| true !== ( $database['rollback_available'] ?? false )
			|| true !== ( $child_db['database_swapped'] ?? false )
			|| true !== ( $child_db['rollback_available'] ?? false )
			|| true !== ( $child_db['handoff_ready'] ?? false )
			|| true !== ( $child_files['handoff_ready'] ?? false )
			|| true !== ( $child_files['rollback_available'] ?? false )
			|| array() !== ( $database['blockers'] ?? array() )
			|| array() !== ( $child_db['blockers'] ?? array() )
			|| array() !== ( $child_files['blockers'] ?? array() )
			|| 'private-same-server' !== ( $import['transport'] ?? null )
			|| (string) ( $import['local_handoff_parent_job_id'] ?? '' ) !== $job_id
			|| true !== ( $import['destination_storage_isolated'] ?? false )
			|| true !== ( $import['search_visibility_disabled'] ?? false )
			|| true !== ( $import['outbound_safe'] ?? false )
			|| true !== ( $import['backups_ready'] ?? false )
			|| true !== ( $import['target_authorized'] ?? false )
		) {
			return $this->blocked( $job_id, 'local-handoff-authority-invalid', $promotion );
		}

		if (
			(string) ( $database['child_import_job_id'] ?? '' ) !== $child_id
			|| ! hash_equals( (string) $promotion['destination_authority_sha256'], (string) $database['destination_authority_sha256'] )
			|| ! hash_equals( (string) $promotion['activation_plan_hash'], (string) $database['activation_plan_hash'] )
			|| ! hash_equals( (string) $promotion['file_fingerprint'], (string) $database['file_fingerprint'] )
			|| ! hash_equals( (string) $database['activation_plan_hash'], (string) $child_db['activation_plan_hash'] )
			|| ! hash_equals( (string) $promotion['file_fingerprint'], (string) $child_files['file_fingerprint'] )
			|| ! hash_equals( (string) $promotion['active_fingerprint'], (string) $child_files['active_fingerprint'] )
		) {
			return $this->blocked( $job_id, 'local-handoff-identity-mismatch', $promotion );
		}

		$root = is_string( $import['destination_root_path'] ?? null )
			? untrailingslashit( wp_normalize_path( $import['destination_root_path'] ) )
			: '';
		$url = is_string( $import['destination_home_url'] ?? null ) ? $import['destination_home_url'] : '';
		$prefix = is_string( $import['destination_table_prefix'] ?? null ) ? $import['destination_table_prefix'] : '';
		if (
			'' === $root
			|| ! is_dir( $root )
			|| is_link( $root )
			|| '' === $url
			|| 1 !== preg_match( '/^[A-Za-z0-9_]+$/', $prefix )
			|| (string) ( $database['target_table_prefix'] ?? '' ) !== $prefix
			|| ! hash_equals( (string) $promotion['target_root_sha256'], hash( 'sha256', $root ) )
		) {
			return $this->blocked( $job_id, 'local-handoff-target-identity-invalid', $promotion );
		}

		$database_check = $this->database_ready( $child_db, $prefix );
		$runtime_check  = $this->runtime_ready( $child_files, $database['options_target'] ?? '', $import );
		$bridge_ready   = $this->bridge_control_ready( $root );
		$files_verified = (int) ( $promotion['file_count'] ?? -1 ) === (int) ( $promotion['verify_file_count'] ?? -2 )
			&& (int) ( $promotion['byte_count'] ?? -1 ) === (int) ( $promotion['verify_byte_count'] ?? -2 )
			&& hash_equals( (string) $promotion['file_fingerprint'], (string) $promotion['active_fingerprint'] );

		$blockers = array();
		if ( ! $database_check['ready'] ) {
			$blockers[] = 'local-handoff-database-smoke-failed';
		}
		if ( ! $runtime_check['home_matches'] || ! $runtime_check['siteurl_matches'] ) {
			$blockers[] = 'local-handoff-target-url-mismatch';
		}
		if ( ! $runtime_check['noindex_ready'] ) {
			$blockers[] = 'local-handoff-noindex-missing';
		}
		if ( ! $runtime_check['runtime_matches'] ) {
			$blockers[] = 'local-handoff-runtime-mismatch';
		}
		if ( ! $bridge_ready ) {
			$blockers[] = 'local-handoff-bridge-control-missing';
		}
		if ( ! $files_verified ) {
			$blockers[] = 'local-handoff-file-fingerprint-mismatch';
		}

		$rollback_available = true === ( $promotion['rollback_available'] ?? false )
			&& true === ( $database['rollback_available'] ?? false )
			&& true === ( $child_files['rollback_available'] ?? false )
			&& true === ( $child_db['rollback_available'] ?? false );
		if ( ! $rollback_available ) {
			$blockers[] = 'local-handoff-rollback-unavailable';
		}

		$source_untouched = true === ( $promotion['source_untouched'] ?? false );
		if ( ! $source_untouched ) {
			$blockers[] = 'local-handoff-source-safety-lost';
		}

		$evidence = array(
			'schema_version'               => 1,
			'job_id'                       => $job_id,
			'child_import_job_id'          => $child_id,
			'destination_authority_sha256' => (string) $promotion['destination_authority_sha256'],
			'activation_plan_hash'         => (string) $promotion['activation_plan_hash'],
			'database_fingerprint'         => (string) ( $database['database_fingerprint'] ?? '' ),
			'file_fingerprint'             => (string) $promotion['file_fingerprint'],
			'active_fingerprint'           => (string) $promotion['active_fingerprint'],
			'target_root_sha256'           => hash( 'sha256', $root ),
			'target_url_sha256'            => hash( 'sha256', trailingslashit( $url ) ),
			'runtime_sha256'               => (string) $runtime_check['runtime_sha256'],
			'target_table_prefix'          => $prefix,
			'table_count'                  => (int) $database_check['table_count'],
			'row_count'                    => (int) $database_check['row_count'],
			'file_count'                   => (int) $promotion['file_count'],
			'file_bytes'                   => (int) $promotion['byte_count'],
			'home_matches'                 => (bool) $runtime_check['home_matches'],
			'siteurl_matches'              => (bool) $runtime_check['siteurl_matches'],
			'noindex_ready'                => (bool) $runtime_check['noindex_ready'],
			'runtime_matches'              => (bool) $runtime_check['runtime_matches'],
			'bridge_control_ready'         => $bridge_ready,
			'database_verified'            => (bool) $database_check['ready'],
			'files_verified'               => $files_verified,
			'rollback_available'           => $rollback_available,
			'source_untouched'             => $source_untouched,
		);
		$encoded = wp_json_encode( $evidence, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		if ( ! is_string( $encoded ) ) {
			return null;
		}

		$ready = array() === $blockers;

		return $evidence + array(
			'status'        => $ready ? 'ready' : 'blocked',
			'target_url'    => trailingslashit( $url ),
			'handoff_ready' => $ready,
			'report_sha256' => hash( 'sha256', $encoded ),
			'blockers'      => $blockers,
			'advisories'    => array(
				'sandbox-noindex-preserved',
				'rollback-preserved',
				'production-cutover-not-authorized',
			),
			'prepared_at'   => gmdate( DATE_ATOM ),
			'updated_at'    => gmdate( DATE_ATOM ),
		);
	}

	/**
	 * Verify all activated target tables still exist with exact row counts.
	 *
	 * @param array<string,mixed> $child_db Child DB journal.
	 * @param string              $prefix   Target table prefix.
	 * @return array{ready:bool,table_count:int,row_count:int}
	 */
	private function database_ready( array $child_db, string $prefix ): array {
		global $wpdb;
		if ( ! $wpdb instanceof wpdb ) {
			return array( 'ready' => false, 'table_count' => 0, 'row_count' => 0 );
		}

		$tables = is_array( $child_db['tables'] ?? null ) ? array_values( $child_db['tables'] ) : array();
		$rows   = 0;
		if ( array() === $tables ) {
			return array( 'ready' => false, 'table_count' => 0, 'row_count' => 0 );
		}

		foreach ( $tables as $table ) {
			$name     = is_array( $table ) && is_string( $table['target_table'] ?? null ) ? $table['target_table'] : '';
			$expected = is_array( $table ) ? (int) ( $table['row_count'] ?? -1 ) : -1;
			if (
				'' === $name
				|| ! str_starts_with( $name, $prefix )
				|| 1 !== preg_match( '/^[A-Za-z0-9_$-]+$/', $name )
				|| 0 > $expected
			) {
				return array( 'ready' => false, 'table_count' => count( $tables ), 'row_count' => $rows );
			}

			$quoted = chr( 96 ) . str_replace( chr( 96 ), chr( 96 ) . chr( 96 ), $name ) . chr( 96 );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared -- Read-only final smoke over validated isolated target tables.
			$actual = $wpdb->get_var( "SELECT COUNT(*) FROM {$quoted}" );
			if ( null === $actual || (int) $actual !== $expected ) {
				return array( 'ready' => false, 'table_count' => count( $tables ), 'row_count' => $rows );
			}
			$rows += $expected;
		}

		return array(
			'ready'       => true,
			'table_count' => count( $tables ),
			'row_count'   => $rows,
		);
	}

	/**
	 * Verify target URLs, noindex and exact plugin/theme runtime.
	 *
	 * @param array<string,mixed> $child_files Child promotion journal.
	 * @param mixed               $options_name Target options table.
	 * @param array<string,mixed> $import      Child import state.
	 * @return array{home_matches:bool,siteurl_matches:bool,noindex_ready:bool,runtime_matches:bool,runtime_sha256:string}
	 */
	private function runtime_ready( array $child_files, mixed $options_name, array $import ): array {
		$empty = array(
			'home_matches'   => false,
			'siteurl_matches' => false,
			'noindex_ready'  => false,
			'runtime_matches' => false,
			'runtime_sha256' => '',
		);
		if ( ! is_string( $options_name ) || 1 !== preg_match( '/^[A-Za-z0-9_$-]+$/', $options_name ) ) {
			return $empty;
		}

		$home       = $this->option_value( $options_name, 'home' );
		$siteurl    = $this->option_value( $options_name, 'siteurl' );
		$blog_public = $this->option_value( $options_name, 'blog_public' );
		$plugins_raw = $this->option_value( $options_name, 'active_plugins' );
		$template    = $this->option_value( $options_name, 'template' );
		$stylesheet  = $this->option_value( $options_name, 'stylesheet' );
		$runtime     = is_array( $child_files['runtime_target'] ?? null ) ? $child_files['runtime_target'] : array();
		$expected_plugins = is_array( $runtime['active_plugins'] ?? null ) ? array_values( $runtime['active_plugins'] ) : array();

		$actual_plugins = null;
		if ( is_string( $plugins_raw ) && is_serialized( $plugins_raw, false ) ) {
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize,WordPress.PHP.NoSilencedErrors.Discouraged -- WordPress plugin list with object instantiation disabled.
			$actual_plugins = @unserialize( $plugins_raw, array( 'allowed_classes' => false ) );
		}

		$runtime_evidence = array(
			'active_plugins' => $expected_plugins,
			'template'       => (string) ( $runtime['template'] ?? '' ),
			'stylesheet'     => (string) ( $runtime['stylesheet'] ?? '' ),
		);
		$runtime_json = wp_json_encode( $runtime_evidence, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );

		return array(
			'home_matches'    => is_string( $home ) && untrailingslashit( $home ) === untrailingslashit( (string) ( $import['destination_home_url'] ?? '' ) ),
			'siteurl_matches' => is_string( $siteurl ) && untrailingslashit( $siteurl ) === untrailingslashit( (string) ( $import['destination_site_url'] ?? '' ) ),
			'noindex_ready'   => '0' === $blog_public && true === ( $import['search_visibility_disabled'] ?? false ),
			'runtime_matches' => is_array( $actual_plugins )
				&& array_values( $actual_plugins ) === $expected_plugins
				&& (string) $template === (string) ( $runtime['template'] ?? '' )
				&& (string) $stylesheet === (string) ( $runtime['stylesheet'] ?? '' ),
			'runtime_sha256'  => is_string( $runtime_json ) ? hash( 'sha256', $runtime_json ) : '',
		);
	}

	/**
	 * Confirm target control plane files remain present and non-symlinked.
	 */
	private function bridge_control_ready( string $root ): bool {
		foreach (
			array(
				'wp-config.php',
				'wp-content/mu-plugins/seo-geo-migration-sandbox-bootstrap.php',
				'wp-content/plugins/seo-geo-migration-bridge/seo-geo-migration-bridge.php',
			) as $relative
		) {
			$path = trailingslashit( $root ) . $relative;
			if ( ! is_file( $path ) || ! is_readable( $path ) || is_link( $path ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Read one option from an isolated target options table.
	 *
	 * @param string $table Target options table.
	 * @param string $name  Option name.
	 */
	private function option_value( string $table, string $name ): ?string {
		global $wpdb;
		if ( ! $wpdb instanceof wpdb ) {
			return null;
		}

		$quoted = chr( 96 ) . str_replace( chr( 96 ), chr( 96 ) . chr( 96 ), $table ) . chr( 96 );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared -- Read-only final target option smoke over validated table.
		$value = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT option_value FROM {$quoted} WHERE option_name = %s LIMIT 1",
				$name
			)
		);

		return is_string( $value ) ? $value : null;
	}

	/**
	 * Build a bounded blocked report.
	 *
	 * @param string              $job_id    Parent job.
	 * @param string              $code      Stable blocker.
	 * @param array<string,mixed> $promotion Optional promotion evidence.
	 * @return array<string,mixed>
	 */
	private function blocked( string $job_id, string $code, array $promotion = array() ): array {
		$now = gmdate( DATE_ATOM );

		return array(
			'schema_version'               => LocalCloneHandoffReportStateStore::SCHEMA_VERSION,
			'job_id'                       => $job_id,
			'status'                       => 'blocked',
			'child_import_job_id'          => (string) ( $promotion['child_import_job_id'] ?? '' ),
			'destination_authority_sha256' => (string) ( $promotion['destination_authority_sha256'] ?? '' ),
			'activation_plan_hash'         => (string) ( $promotion['activation_plan_hash'] ?? '' ),
			'database_fingerprint'         => '',
			'file_fingerprint'             => (string) ( $promotion['file_fingerprint'] ?? '' ),
			'active_fingerprint'           => (string) ( $promotion['active_fingerprint'] ?? '' ),
			'target_root_sha256'           => (string) ( $promotion['target_root_sha256'] ?? '' ),
			'target_url'                   => '',
			'target_url_sha256'            => '',
			'runtime_sha256'               => '',
			'target_table_prefix'          => '',
			'table_count'                  => 0,
			'row_count'                    => 0,
			'file_count'                   => (int) ( $promotion['file_count'] ?? 0 ),
			'file_bytes'                   => (int) ( $promotion['byte_count'] ?? 0 ),
			'home_matches'                 => false,
			'siteurl_matches'              => false,
			'noindex_ready'                => false,
			'runtime_matches'              => false,
			'bridge_control_ready'         => false,
			'database_verified'            => false,
			'files_verified'               => false,
			'rollback_available'           => false,
			'source_untouched'             => true === ( $promotion['source_untouched'] ?? false ),
			'handoff_ready'                => false,
			'report_sha256'                => '',
			'blockers'                     => array( $code ),
			'advisories'                   => array(),
			'prepared_at'                  => $now,
			'updated_at'                   => $now,
		);
	}
}
