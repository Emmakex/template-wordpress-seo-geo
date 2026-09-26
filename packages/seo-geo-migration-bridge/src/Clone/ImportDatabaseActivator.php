<?php
/**
 * Portable Clone reversible staging-database activation.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge\Clone;

use SeoGeo\MigrationBridge\Sandbox\SandboxGuard;
use wpdb;

/**
 * Atomically promotes verified staging tables while keeping a deterministic rollback.
 *
 * Database activation is intentionally separated from file promotion. The activation
 * journal lives in the private workspace so replacing wp_options cannot erase the
 * control plane needed to verify or roll back the swap.
 */
final class ImportDatabaseActivator {
	private const MAX_CONTROL_PLANE_BYTES = 2097152;

	/**
	 * Workspace-backed activation journal.
	 *
	 * @var ImportDatabaseActivationStateStore
	 */
	private ImportDatabaseActivationStateStore $store;

	/**
	 * Accepted finalization-plan authority.
	 *
	 * @var ImportFinalizationPlanner
	 */
	private ImportFinalizationPlanner $finalizer;

	/**
	 * External file-promotion recovery journal.
	 *
	 * @var ImportFilePromotionStateStore
	 */
	private ImportFilePromotionStateStore $file_promotion;

	/**
	 * Child import/destination authority.
	 *
	 * @var ImportStateStore
	 */
	private ImportStateStore $import_state;

	/**
	 * Fresh private same-server target authority.
	 *
	 * @var LocalCloneTargetPreflight
	 */
	private LocalCloneTargetPreflight $local_target_preflight;

	/**
	 * Construct the reversible database activator.
	 *
	 * @param ImportDatabaseActivationStateStore|null $store                  Optional workspace journal.
	 * @param ImportFinalizationPlanner|null          $finalizer              Optional finalization-plan authority.
	 * @param ImportFilePromotionStateStore|null      $file_promotion         Optional file-promotion journal.
	 * @param ImportStateStore|null                   $import_state           Optional child import state.
	 * @param LocalCloneTargetPreflight|null          $local_target_preflight Optional local target authority.
	 */
	public function __construct(
		?ImportDatabaseActivationStateStore $store = null,
		?ImportFinalizationPlanner $finalizer = null,
		?ImportFilePromotionStateStore $file_promotion = null,
		?ImportStateStore $import_state = null,
		?LocalCloneTargetPreflight $local_target_preflight = null
	) {
		$this->store                  = $store ?? new ImportDatabaseActivationStateStore();
		$this->finalizer              = $finalizer ?? new ImportFinalizationPlanner();
		$this->file_promotion         = $file_promotion ?? new ImportFilePromotionStateStore();
		$this->import_state           = $import_state ?? new ImportStateStore();
		$this->local_target_preflight = $local_target_preflight ?? new LocalCloneTargetPreflight();
	}

	/**
	 * Return the workspace-backed activation journal.
	 *
	 * @param string $job_id Clone job identifier.
	 * @return array<string,mixed>|null
	 */
	public function snapshot( string $job_id ): ?array {
		return $this->store->get( $job_id );
	}

	/**
	 * Return an activated database only while exact layout/control guards still hold.
	 *
	 * @param string $job_id Child import job identifier.
	 * @return array<string,mixed>|null
	 */
	public function verified_snapshot( string $job_id ): ?array {
		$state = $this->store->get( $job_id );
		if (
			! is_array( $state )
			|| 'activated' !== ( $state['status'] ?? null )
			|| true !== ( $state['database_swapped'] ?? false )
			|| true !== ( $state['rollback_available'] ?? false )
			|| true !== ( $state['active_files_untouched'] ?? false )
			|| true === ( $state['handoff_ready'] ?? true )
			|| array() !== ( $state['blockers'] ?? array() )
			|| ! $this->sandbox_ready( $job_id, true )
			|| ! $this->verify_activated( $state )
		) {
			return null;
		}

		return $state;
	}

	/**
	 * Freeze an exact reversible table map without mutating staging.
	 *
	 * The finalization plan is rebound through a fresh read-only gate here. The
	 * protected wp_options overlay is intentionally deferred until activate(), so
	 * the accepted finalization plan remains valid while the operator reviews the
	 * prepared recovery journal.
	 *
	 * @param string $job_id Clone job identifier.
	 * @return array<string,mixed>|null
	 */
	public function prepare( string $job_id ): ?array {
		$existing = $this->store->get( $job_id );
		if ( is_array( $existing ) ) {
			return $existing;
		}

		if ( ! $this->sandbox_ready( $job_id ) ) {
			return null;
		}

		$snapshot = $this->finalizer->activation_plan_snapshot( $job_id );
		if ( null === $snapshot ) {
			return null;
		}

		$database = is_array( $snapshot['plan']['database'] ?? null )
			? $snapshot['plan']['database']
			: array();
		$tables   = is_array( $database['tables'] ?? null )
			? array_values( $database['tables'] )
			: array();
		if ( array() === $tables ) {
			return null;
		}

		global $wpdb;
		if ( ! $wpdb instanceof wpdb ) {
			return null;
		}

		$normalized = array();
		$options    = null;
		foreach ( $tables as $table ) {
			if ( ! is_array( $table ) ) {
				return null;
			}

			$staging  = $this->table_name( $table['staging_table'] ?? '' );
			$target   = $this->table_name( $table['target_table'] ?? '' );
			$rollback = $this->table_name( $table['rollback_table'] ?? '' );
			if (
				'' === $staging
				|| '' === $target
				|| '' === $rollback
				|| ! $this->table_exists( $staging )
				|| $this->table_exists( $rollback )
				|| ( true === ( $table['target_exists'] ?? false ) ) !== $this->table_exists( $target )
			) {
				return null;
			}

			$count = $this->row_count( $staging );
			if ( null === $count || max( 0, (int) ( $table['row_count'] ?? 0 ) ) !== $count ) {
				return null;
			}

			$entry        = array(
				'staging_table'  => $staging,
				'target_table'   => $target,
				'rollback_table' => $rollback,
				'target_exists'  => true === ( $table['target_exists'] ?? false ),
				'row_count'      => $count,
			);
			$normalized[] = $entry;

			if ( $target === $this->expected_options_target( $job_id ) ) {
				$options = $entry;
			}
		}

		if ( ! is_array( $options ) ) {
			return null;
		}

		$now   = gmdate( DATE_ATOM );
		$state = array(
			'schema_version'         => ImportDatabaseActivationStateStore::SCHEMA_VERSION,
			'job_id'                 => $job_id,
			'status'                 => 'prepared',
			'activation_plan_hash'   => $snapshot['hash'],
			'finalize_db_hash'       => $snapshot['database_fingerprint'],
			'tables'                 => $normalized,
			'control_options'        => array(),
			'options_target'         => (string) $options['target_table'],
			'options_staging'        => (string) $options['staging_table'],
			'database_swapped'       => false,
			'rollback_available'     => true,
			'active_files_untouched' => true,
			'handoff_ready'          => false,
			'blockers'               => array(),
			'prepared_at'            => $now,
			'activated_at'           => '',
			'verified_at'            => '',
			'rolled_back_at'         => '',
			'updated_at'             => $now,
		);

		return $this->store->save( $job_id, $state ) ? $this->store->get( $job_id ) : null;
	}

	/**
	 * Atomically activate every staging table and verify the new active database.
	 *
	 * Any post-rename verification failure triggers an immediate atomic rollback.
	 * The last fresh finalization gate runs before the controlled options overlay;
	 * after that gate the journal switches to activating so an interrupted request
	 * can safely replay the idempotent overlay and complete the atomic swap.
	 *
	 * @param string $job_id Clone job identifier.
	 * @return array<string,mixed>|null
	 */
	public function activate( string $job_id ): ?array {
		$state = $this->store->get( $job_id ) ?? $this->prepare( $job_id );
		if ( ! is_array( $state ) || ! in_array( $state['status'] ?? null, array( 'prepared', 'activating' ), true ) ) {
			return $state;
		}
		$layout = $this->layout( $state );
		if ( ! $this->sandbox_ready( $job_id, 'activated' === $layout ) ) {
			return $this->block( $job_id, $state, 'database-activation-sandbox-guard-failed', 'activated' === $layout );
		}

		if ( 'activated' === $layout ) {
			return $this->finish_activation_verification( $job_id, $state );
		}
		if ( 'prepared' !== $layout ) {
			return $this->block( $job_id, $state, 'database-activation-layout-drift', false );
		}

		if ( 'prepared' === $state['status'] ) {
			if ( ! $this->staging_counts_match( $state ) ) {
				return $this->block( $job_id, $state, 'database-activation-layout-drift', false );
			}

			$snapshot = $this->finalizer->activation_plan_snapshot( $job_id );
			if (
				null === $snapshot
				|| ! $this->same_hash( $state['activation_plan_hash'] ?? '', $snapshot['hash'] )
			) {
				return $this->block( $job_id, $state, 'database-activation-plan-drift', false );
			}

			$state['status']     = 'activating';
			$state['updated_at'] = gmdate( DATE_ATOM );
			if ( ! $this->store->save( $job_id, $state ) ) {
				return null;
			}
		}

		$controls = $this->prepare_options_overlay(
			$job_id,
			(string) ( $state['options_staging'] ?? '' ),
			(string) ( $state['options_target'] ?? '' )
		);
		if ( null === $controls ) {
			return $this->block( $job_id, $state, 'database-activation-options-overlay-failed', false );
		}

		$tables = is_array( $state['tables'] ?? null ) ? $state['tables'] : array();
		foreach ( $tables as $index => $table ) {
			if ( ! is_array( $table ) ) {
				return $this->block( $job_id, $state, 'database-activation-table-map-invalid', false );
			}
			$count = $this->row_count( (string) ( $table['staging_table'] ?? '' ) );
			if ( null === $count ) {
				return $this->block( $job_id, $state, 'database-activation-staging-count-failed', false );
			}
			$tables[ $index ]['row_count'] = $count;
		}

		$state['tables']          = $tables;
		$state['control_options'] = $controls;
		$state['updated_at']      = gmdate( DATE_ATOM );
		if ( ! $this->store->save( $job_id, $state ) ) {
			return null;
		}

		if ( ! $this->rename_forward( $state ) ) {
			$fresh = $this->store->get( $job_id ) ?? $state;
			return $this->block( $job_id, $fresh, 'database-activation-atomic-rename-failed', false );
		}

		return $this->finish_activation_verification( $job_id, $state );
	}

	/**
	 * Explicitly roll an activated database back to the pre-activation layout.
	 *
	 * @param string $job_id Clone job identifier.
	 * @return array<string,mixed>|null
	 */
	public function rollback( string $job_id ): ?array {
		$state = $this->store->get( $job_id );
		if ( ! is_array( $state ) || ! in_array( $state['status'] ?? null, array( 'activated', 'activating' ), true ) ) {
			return $state;
		}
		$promotion = $this->file_promotion->get( $job_id );
		if (
			is_array( $promotion )
			&& in_array(
				$promotion['status'] ?? null,
				array( 'prepared', 'copying', 'candidate-ready', 'promoting', 'verifying', 'verified' ),
				true
			)
		) {
			return $state;
		}

		if ( ! $this->sandbox_ready( $job_id, true ) ) {
			return $this->block( $job_id, $state, 'database-rollback-sandbox-guard-failed', true );
		}

		return $this->rollback_internal( $job_id, $state, 'operator-database-rollback' );
	}

	/**
	 * Apply the wp_options control-plane overlay transactionally to staging.
	 *
	 * @param string $job_id  Child import job identifier.
	 * @param string $staging Staging options table.
	 * @param string $active  Active destination options table.
	 * @return list<array{name:string,sha256:string,byte_count:int}>|null
	 */
	private function prepare_options_overlay( string $job_id, string $staging, string $active ): ?array {
		$import = $this->private_same_server_import( $job_id );
		if ( is_array( $import ) ) {
			return $this->prepare_local_options_overlay( $staging, $import );
		}

		global $wpdb;
		if ( ! $wpdb instanceof wpdb ) {
			return null;
		}

		$staging_columns = $this->table_columns( $staging );
		$active_columns  = $this->table_columns( $active );
		if (
			! in_array( 'option_id', $staging_columns, true )
			|| ! in_array( 'option_name', $staging_columns, true )
			|| ! in_array( 'option_value', $staging_columns, true )
			|| ! in_array( 'option_name', $active_columns, true )
			|| ! in_array( 'option_value', $active_columns, true )
		) {
			return null;
		}

		$control_names = array(
			CloneJobStore::OPTION_NAME,
			ImportStateStore::OPTION_NAME,
			ImportPayloadStateStore::OPTION_NAME,
			ImportDatabaseStateStore::OPTION_NAME,
			ImportFileStateStore::OPTION_NAME,
			ImportRewriteStateStore::OPTION_NAME,
			ImportFinalizeStateStore::OPTION_NAME,
		);
		$runtime_names = array(
			'active_plugins',
			'template',
			'stylesheet',
		);

		$preserved = array();
		$total     = 0;
		foreach ( array_merge( $control_names, $runtime_names ) as $name ) {
			$row = $this->option_row( $active, $name, $active_columns );
			if ( null === $row ) {
				return null;
			}
			$total += strlen( $row['option_value'] );
			if ( $total > self::MAX_CONTROL_PLANE_BYTES ) {
				return null;
			}
			$preserved[ $name ] = $row;
		}

		// File promotion is intentionally deferred to 10E.2A.4.6.3. Keep the
		// destination's currently runnable plugin/theme state until those files
		// are promoted, while guaranteeing that Migration Bridge remains active.
		$plugin_list = $this->plugin_list( $preserved['active_plugins']['option_value'] );
		if ( null === $plugin_list ) {
			return null;
		}

		$bridge = defined( 'SEO_GEO_MIGRATION_BRIDGE_DIR' )
			? plugin_basename( SEO_GEO_MIGRATION_BRIDGE_DIR . 'seo-geo-migration-bridge.php' )
			: 'seo-geo-migration-bridge/seo-geo-migration-bridge.php';
		if ( ! in_array( $bridge, $plugin_list, true ) ) {
			$plugin_list[] = $bridge;
		}
		$plugin_list = array_values( array_unique( array_filter( $plugin_list, 'is_string' ) ) );

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- WordPress active_plugins is a serialized list by contract.
		$preserved['active_plugins']['option_value'] = serialize( $plugin_list );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Transaction is limited to the verified job-owned staging options table.
		if ( false === $wpdb->query( 'START TRANSACTION' ) ) {
			return null;
		}

		$ok = true;
		foreach ( $preserved as $name => $row ) {
			$ok = $ok && $this->upsert_option(
				$staging,
				$staging_columns,
				$name,
				$row['option_value'],
				$row['autoload']
			);
		}
		$ok = $ok && $this->upsert_option( $staging, $staging_columns, 'blog_public', '0', 'no' );

		if ( ! $ok ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Rolls back only the staging-options overlay transaction.
			$wpdb->query( 'ROLLBACK' );
			return null;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Commits only the staging-options overlay transaction.
		if ( false === $wpdb->query( 'COMMIT' ) ) {
			$wpdb->query( 'ROLLBACK' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			return null;
		}

		$controls = array();
		foreach ( array_merge( $control_names, $runtime_names, array( 'blog_public' ) ) as $name ) {
			$row = $this->option_row( $staging, $name, $staging_columns );
			if ( null === $row ) {
				return null;
			}
			$controls[] = array(
				'name'       => $name,
				'sha256'     => hash( 'sha256', $row['option_value'] ),
				'byte_count' => strlen( $row['option_value'] ),
			);
		}

		return $controls;
	}


	/**
	 * Apply the isolated local-clone control overlay only to staging.
	 *
	 * Client plugins remain disabled until the later file-promotion phase. The
	 * always-on MU sandbox loader keeps Migration Bridge available independently.
	 *
	 * @param string              $staging Staging options table.
	 * @param array<string,mixed> $import  Child import state.
	 * @return list<array{name:string,sha256:string,byte_count:int}>|null
	 */
	private function prepare_local_options_overlay( string $staging, array $import ): ?array {
		global $wpdb;
		if ( ! $wpdb instanceof wpdb ) {
			return null;
		}

		$columns = $this->table_columns( $staging );
		if (
			! in_array( 'option_id', $columns, true )
			|| ! in_array( 'option_name', $columns, true )
			|| ! in_array( 'option_value', $columns, true )
		) {
			return null;
		}

		$home = $this->option_row( $staging, 'home', $columns );
		$site = $this->option_row( $staging, 'siteurl', $columns );
		if (
			null === $home
			|| null === $site
			|| untrailingslashit( $home['option_value'] ) !== untrailingslashit( (string) ( $import['destination_home_url'] ?? '' ) )
			|| untrailingslashit( $site['option_value'] ) !== untrailingslashit( (string) ( $import['destination_site_url'] ?? '' ) )
		) {
			return null;
		}

		$bridge = defined( 'SEO_GEO_MIGRATION_BRIDGE_DIR' )
			? plugin_basename( SEO_GEO_MIGRATION_BRIDGE_DIR . 'seo-geo-migration-bridge.php' )
			: 'seo-geo-migration-bridge/seo-geo-migration-bridge.php';
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- WordPress active_plugins is a serialized list by contract.
		$plugins = serialize( array( $bridge ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Transaction mutates only the verified job-owned staging options table.
		if ( false === $wpdb->query( 'START TRANSACTION' ) ) {
			return null;
		}

		$ok = $this->upsert_option( $staging, $columns, 'active_plugins', $plugins, 'yes' )
			&& $this->upsert_option( $staging, $columns, 'blog_public', '0', 'no' );
		if ( ! $ok ) {
			$wpdb->query( 'ROLLBACK' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			return null;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Commits only the verified staging control overlay.
		if ( false === $wpdb->query( 'COMMIT' ) ) {
			$wpdb->query( 'ROLLBACK' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			return null;
		}

		$controls = array();
		foreach ( array( 'home', 'siteurl', 'active_plugins', 'blog_public' ) as $name ) {
			$row = $this->option_row( $staging, $name, $columns );
			if ( null === $row ) {
				return null;
			}
			$controls[] = array(
				'name'       => $name,
				'sha256'     => hash( 'sha256', $row['option_value'] ),
				'byte_count' => strlen( $row['option_value'] ),
			);
		}

		return $controls;
	}

	/**
	 * Verify the activated database, or automatically reverse the atomic rename.
	 *
	 * @param string              $job_id Clone job identifier.
	 * @param array<string,mixed> $state  State.
	 * @return array<string,mixed>|null
	 */
	private function finish_activation_verification( string $job_id, array $state ): ?array {
		if ( ! $this->verify_activated( $state ) ) {
			return $this->rollback_internal( $job_id, $state, 'database-activation-verification-failed' );
		}

		$now                             = gmdate( DATE_ATOM );
		$state['status']                 = 'activated';
		$state['database_swapped']       = true;
		$state['rollback_available']     = true;
		$state['active_files_untouched'] = true;
		$state['handoff_ready']          = false;
		$state['activated_at']           = '' !== (string) ( $state['activated_at'] ?? '' )
			? (string) $state['activated_at']
			: $now;
		$state['verified_at']            = $now;
		$state['updated_at']             = $now;
		$state['blockers']               = array();

		return $this->store->save( $job_id, $state ) ? $this->store->get( $job_id ) : null;
	}

	/**
	 * Reverse the table swap atomically and prove the pre-activation layout returned.
	 *
	 * @param string              $job_id Clone job identifier.
	 * @param array<string,mixed> $state  State.
	 * @param string              $reason Rollback reason code.
	 * @return array<string,mixed>|null
	 */
	private function rollback_internal( string $job_id, array $state, string $reason ): ?array {
		$layout = $this->layout( $state );
		if ( 'prepared' === $layout ) {
			$state['status']             = 'rolled-back';
			$state['database_swapped']   = false;
			$state['rollback_available'] = false;
			$state['blockers']           = array( $reason );
			$state['rolled_back_at']     = gmdate( DATE_ATOM );
			$state['updated_at']         = $state['rolled_back_at'];
			return $this->store->save( $job_id, $state ) ? $this->store->get( $job_id ) : null;
		}
		if ( 'activated' !== $layout ) {
			return $this->block( $job_id, $state, 'database-rollback-layout-ambiguous', true );
		}

		$state['status']     = 'rolling-back';
		$state['updated_at'] = gmdate( DATE_ATOM );
		if ( ! $this->store->save( $job_id, $state ) || ! $this->rename_reverse( $state ) ) {
			return $this->block( $job_id, $state, 'database-rollback-atomic-rename-failed', true );
		}

		if ( 'prepared' !== $this->layout( $state ) || ! $this->staging_counts_match( $state ) ) {
			return $this->block( $job_id, $state, 'database-rollback-verification-failed', true );
		}

		$now                             = gmdate( DATE_ATOM );
		$state['status']                 = 'rolled-back';
		$state['database_swapped']       = false;
		$state['rollback_available']     = false;
		$state['active_files_untouched'] = true;
		$state['handoff_ready']          = false;
		$state['blockers']               = array( $reason );
		$state['rolled_back_at']         = $now;
		$state['updated_at']             = $now;

		return $this->store->save( $job_id, $state ) ? $this->store->get( $job_id ) : null;
	}

	/**
	 * Verify table layout, counts and control-plane options after activation.
	 *
	 * @param array<string,mixed> $state State.
	 */
	private function verify_activated( array $state ): bool {
		if ( 'activated' !== $this->layout( $state ) ) {
			return false;
		}

		foreach ( $state['tables'] as $table ) {
			$count = $this->row_count( (string) $table['target_table'] );
			if ( null === $count || $count !== (int) $table['row_count'] ) {
				return false;
			}
		}

		$options = (string) ( $state['options_target'] ?? '' );
		$columns = $this->table_columns( $options );
		if ( array() === $columns ) {
			return false;
		}

		foreach ( $state['control_options'] as $control ) {
			$row = $this->option_row( $options, (string) $control['name'], $columns );
			if (
				null === $row
				|| strlen( $row['option_value'] ) !== (int) $control['byte_count']
				|| ! hash_equals( (string) $control['sha256'], hash( 'sha256', $row['option_value'] ) )
			) {
				return false;
			}
		}

		$blog_public = $this->option_row( $options, 'blog_public', $columns );
		$plugins     = $this->option_row( $options, 'active_plugins', $columns );
		if ( null === $blog_public || '0' !== $blog_public['option_value'] || null === $plugins ) {
			return false;
		}

		$list   = $this->plugin_list( $plugins['option_value'] );
		$bridge = defined( 'SEO_GEO_MIGRATION_BRIDGE_DIR' )
			? plugin_basename( SEO_GEO_MIGRATION_BRIDGE_DIR . 'seo-geo-migration-bridge.php' )
			: 'seo-geo-migration-bridge/seo-geo-migration-bridge.php';

		return is_array( $list ) && in_array( $bridge, $list, true );
	}

	/**
	 * Return prepared / activated / ambiguous based only on exact table existence.
	 *
	 * @param array<string,mixed> $state State.
	 */
	private function layout( array $state ): string {
		$prepared  = true;
		$activated = true;

		foreach ( is_array( $state['tables'] ?? null ) ? $state['tables'] : array() as $table ) {
			if ( ! is_array( $table ) ) {
				return 'ambiguous';
			}

			$staging       = (string) $table['staging_table'];
			$target        = (string) $table['target_table'];
			$rollback      = (string) $table['rollback_table'];
			$target_before = true === ( $table['target_exists'] ?? false );

			$staging_exists  = $this->table_exists( $staging );
			$target_exists   = $this->table_exists( $target );
			$rollback_exists = $this->table_exists( $rollback );

			$prepared = $prepared
				&& $staging_exists
				&& ( $target_before === $target_exists )
				&& ! $rollback_exists;

			$activated = $activated
				&& ! $staging_exists
				&& $target_exists
				&& ( $target_before === $rollback_exists );
		}

		if ( $prepared ) {
			return 'prepared';
		}
		if ( $activated ) {
			return 'activated';
		}

		return 'ambiguous';
	}

	/**
	 * Verify every staging-table row count against the frozen journal.
	 *
	 * @param array<string,mixed> $state State.
	 */
	private function staging_counts_match( array $state ): bool {
		foreach ( is_array( $state['tables'] ?? null ) ? $state['tables'] : array() as $table ) {
			$count = is_array( $table ) ? $this->row_count( (string) $table['staging_table'] ) : null;
			if ( null === $count || (int) ( $table['row_count'] ?? -1 ) !== $count ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Execute one atomic multi-table forward rename.
	 *
	 * @param array<string,mixed> $state State.
	 */
	private function rename_forward( array $state ): bool {
		$pairs = array();
		foreach ( $state['tables'] as $table ) {
			if ( true === $table['target_exists'] ) {
				$pairs[] = $this->quote_identifier( (string) $table['target_table'] )
					. ' TO ' . $this->quote_identifier( (string) $table['rollback_table'] );
			}
			$pairs[] = $this->quote_identifier( (string) $table['staging_table'] )
				. ' TO ' . $this->quote_identifier( (string) $table['target_table'] );
		}

		return $this->rename_tables( $pairs );
	}

	/**
	 * Execute one atomic multi-table reverse rename.
	 *
	 * @param array<string,mixed> $state State.
	 */
	private function rename_reverse( array $state ): bool {
		$pairs = array();
		foreach ( $state['tables'] as $table ) {
			$pairs[] = $this->quote_identifier( (string) $table['target_table'] )
				. ' TO ' . $this->quote_identifier( (string) $table['staging_table'] );
			if ( true === $table['target_exists'] ) {
				$pairs[] = $this->quote_identifier( (string) $table['rollback_table'] )
					. ' TO ' . $this->quote_identifier( (string) $table['target_table'] );
			}
		}

		return $this->rename_tables( $pairs );
	}

	/**
	 * Execute one validated atomic multi-table rename statement.
	 *
	 * @param array $pairs Fully quoted rename pairs.
	 * @phpstan-param list<string> $pairs
	 */
	private function rename_tables( array $pairs ): bool {
		global $wpdb;
		if ( ! $wpdb instanceof wpdb || array() === $pairs ) {
			return false;
		}

		$sql = 'RENAME TABLE ' . implode( ', ', $pairs );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.PreparedSQL.NotPrepared -- Exact validated/quoted activation map; one atomic MySQL/MariaDB RENAME TABLE statement.
		if ( false === $wpdb->query( $sql ) ) {
			return false;
		}

		// Swapping wp_options invalidates alloptions/individual option cache keys.
		// Flush immediately so the rest of this request cannot observe the
		// pre-swap option table through a persistent object cache.
		wp_cache_flush();

		return true;
	}

	/**
	 * Upsert one option in the staging options table only.
	 *
	 * @param string $table    Staging options table.
	 * @param array  $columns  Table columns.
	 * @param string $name     Option name.
	 * @param string $value    Option value.
	 * @param string $autoload Autoload value.
	 * @phpstan-param list<string> $columns
	 */
	private function upsert_option(
		string $table,
		array $columns,
		string $name,
		string $value,
		string $autoload
	): bool {
		global $wpdb;
		if ( ! $wpdb instanceof wpdb || ! $this->valid_table_name( $table ) ) {
			return false;
		}

		$current = $this->option_row( $table, $name, $columns );
		if ( is_array( $current ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Writes only the verified staging options table.
			return false !== $wpdb->update(
				$table,
				array( 'option_value' => $value ),
				array( 'option_name' => $name ),
				array( '%s' ),
				array( '%s' )
			);
		}

		$next_id = $this->next_option_id( $table );
		if ( null === $next_id ) {
			return false;
		}

		$data    = array(
			'option_id'    => $next_id,
			'option_name'  => $name,
			'option_value' => $value,
		);
		$formats = array( '%d', '%s', '%s' );
		if ( in_array( 'autoload', $columns, true ) ) {
			$data['autoload'] = '' !== $autoload ? $autoload : 'no';
			$formats[]        = '%s';
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Inserts only into the verified staging options table.
		return false !== $wpdb->insert( $table, $data, $formats );
	}

	/**
	 * Return the next deterministic option_id for a staging options table.
	 *
	 * @param string $table Staging options table.
	 */
	private function next_option_id( string $table ): ?int {
		global $wpdb;
		if ( ! $wpdb instanceof wpdb || ! $this->valid_table_name( $table ) ) {
			return null;
		}

		$quoted = $this->quote_identifier( $table );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Validated staging table identifier.
		$max = $wpdb->get_var( "SELECT MAX(option_id) FROM {$quoted}" );

		return null === $max ? 1 : max( 1, (int) $max + 1 );
	}

	/**
	 * Return one exact option row without using the WordPress object cache.
	 *
	 * @param string $table   Options table.
	 * @param string $name    Option name.
	 * @param array  $columns Table columns.
	 * @phpstan-param list<string> $columns
	 * @return array{option_value:string,autoload:string}|null
	 */
	private function option_row( string $table, string $name, array $columns ): ?array {
		global $wpdb;
		if (
			! $wpdb instanceof wpdb
			|| ! $this->valid_table_name( $table )
			|| ! in_array( 'option_name', $columns, true )
			|| ! in_array( 'option_value', $columns, true )
		) {
			return null;
		}

		$quoted = $this->quote_identifier( $table );
		$select = in_array( 'autoload', $columns, true )
			? 'option_value, autoload'
			: "option_value, '' AS autoload";
		$sql    = $wpdb->prepare(
			"SELECT {$select} FROM {$quoted} WHERE option_name = %s LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table identifier and select list are locally validated.
			$name
		);
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- Direct exact option lookup avoids object-cache ambiguity across table swaps.
		$row = $wpdb->get_row( $sql, ARRAY_A );
		if ( ! is_array( $row ) || ! is_string( $row['option_value'] ?? null ) ) {
			return null;
		}

		return array(
			'option_value' => $row['option_value'],
			'autoload'     => is_string( $row['autoload'] ?? null ) ? $row['autoload'] : '',
		);
	}

	/**
	 * Return validated column names for one activation table.
	 *
	 * @param string $table Table name.
	 * @return list<string>
	 */
	private function table_columns( string $table ): array {
		global $wpdb;
		if ( ! $wpdb instanceof wpdb || ! $this->valid_table_name( $table ) ) {
			return array();
		}

		$quoted = $this->quote_identifier( $table );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Read-only schema inspection of an exact validated table.
		$rows = $wpdb->get_results( "SHOW COLUMNS FROM {$quoted}", ARRAY_A );
		$out  = array();
		foreach ( $rows as $row ) {
			if ( is_string( $row['Field'] ?? null ) ) {
				$out[] = $row['Field'];
			}
		}

		return $out;
	}

	/**
	 * Return the exact row count for one activation table.
	 *
	 * @param string $table Table name.
	 */
	private function row_count( string $table ): ?int {
		global $wpdb;
		if ( ! $wpdb instanceof wpdb || ! $this->valid_table_name( $table ) || ! $this->table_exists( $table ) ) {
			return null;
		}

		$quoted = $this->quote_identifier( $table );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Read-only exact count over validated activation table.
		$count = $wpdb->get_var( "SELECT COUNT(*) FROM {$quoted}" );

		return null === $count ? null : max( 0, (int) $count );
	}

	/**
	 * Whether one activation table exists exactly.
	 *
	 * @param string $table Table name.
	 */
	private function table_exists( string $table ): bool {
		global $wpdb;
		if ( ! $wpdb instanceof wpdb || ! $this->valid_table_name( $table ) ) {
			return false;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Exact read-only table existence guard.
		$found = $wpdb->get_var(
			$wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) )
		);

		return is_string( $found ) && $found === $table;
	}

	/**
	 * Decode a WordPress active_plugins value with classes disabled.
	 *
	 * @param string $raw Serialized plugin list.
	 * @return list<string>|null
	 */
	private function plugin_list( string $raw ): ?array {
		if ( '' === $raw ) {
			return array();
		}
		if ( ! is_serialized( $raw, false ) ) {
			return null;
		}

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize,WordPress.PHP.NoSilencedErrors.Discouraged -- Plugin list is decoded with class instantiation disabled.
		$value = @unserialize( $raw, array( 'allowed_classes' => false ) );
		if ( ! is_array( $value ) ) {
			return null;
		}

		$out = array();
		foreach ( $value as $plugin ) {
			if ( is_string( $plugin ) && 255 >= strlen( $plugin ) ) {
				$out[] = $plugin;
			}
		}

		return array_values( array_unique( $out ) );
	}

	/**
	 * Revalidate the sandbox safety boundary against the currently active DB.
	 */
	private function sandbox_ready( string $job_id, bool $allow_activated = false ): bool {
		global $wpdb;
		if ( ! $wpdb instanceof wpdb ) {
			return false;
		}

		$import = $this->private_same_server_import( $job_id );
		if ( is_array( $import ) ) {
			$root   = $this->private_same_server_root( $import );
			$prefix = is_string( $import['destination_table_prefix'] ?? null )
				? $import['destination_table_prefix']
				: '';
			if (
				null === $root
				|| 1 !== preg_match( '/^[A-Za-z0-9_]+$/', $prefix )
				|| $prefix === $wpdb->prefix
				|| 'subdirectory' !== ( $import['destination_mode'] ?? null )
				|| true !== ( $import['destination_storage_isolated'] ?? false )
				|| true !== ( $import['search_visibility_disabled'] ?? false )
				|| true !== ( $import['outbound_safe'] ?? false )
				|| true !== ( $import['backups_ready'] ?? false )
				|| true !== ( $import['target_authorized'] ?? false )
				|| true !== ( $import['full_payload_verified'] ?? false )
				|| true !== ( $import['restore_allowed'] ?? false )
				|| array() !== ( $import['blockers'] ?? array() )
			) {
				return false;
			}

			foreach (
				array(
					'wp-config.php',
					'wp-content',
					'wp-content/plugins/seo-geo-migration-bridge/seo-geo-migration-bridge.php',
					'wp-content/mu-plugins/seo-geo-migration-sandbox-bootstrap.php',
				) as $relative
			) {
				$path = $this->join_path( $root, $relative );
				if ( is_link( $path ) || ( 'wp-content' === $relative ? ! is_dir( $path ) : ! is_file( $path ) ) ) {
					return false;
				}
			}

			if ( $allow_activated ) {
				return true;
			}

			$parent_id = is_string( $import['local_handoff_parent_job_id'] ?? null )
				? $import['local_handoff_parent_job_id']
				: '';
			$target = '' !== $parent_id ? $this->local_target_preflight->verified_snapshot( $parent_id ) : null;

			return is_array( $target )
				&& (string) ( $target['child_import_job_id'] ?? '' ) === $job_id
				&& hash_equals(
					(string) ( $target['destination_authority_sha256'] ?? '' ),
					(string) ( $import['destination_authority_sha256'] ?? '' )
				);
		}

		$authorized = defined( ImportPreflight::TARGET_AUTHORIZED_MARKER )
			&& true === constant( ImportPreflight::TARGET_AUTHORIZED_MARKER );

		if (
			! SandboxGuard::enabled()
			|| 'invalid' === SandboxGuard::mode()
			|| ! SandboxGuard::outbound_safe()
			|| ! SandboxGuard::backups_ready()
			|| ! $authorized
			|| ( 'subdirectory' === SandboxGuard::mode() && ! SandboxGuard::storage_isolated() )
		) {
			return false;
		}

		$columns = $this->table_columns( $wpdb->options );
		$row     = $this->option_row( $wpdb->options, 'blog_public', $columns );

		return is_array( $row ) && '0' === $row['option_value'];
	}

	/**
	 * Return a verified private same-server child import, when applicable.
	 *
	 * @param string $job_id Child import job identifier.
	 * @return array<string,mixed>|null
	 */
	private function private_same_server_import( string $job_id ): ?array {
		$import = $this->import_state->get( $job_id );
		if (
			! is_array( $import )
			|| 'payload-verified' !== ( $import['status'] ?? null )
			|| 'private-same-server' !== ( $import['transport'] ?? null )
			|| ! is_string( $import['local_handoff_parent_job_id'] ?? null )
			|| '' === $import['local_handoff_parent_job_id']
		) {
			return null;
		}

		return $import;
	}

	/**
	 * Resolve the isolated local-clone target root.
	 *
	 * @param array<string,mixed> $import Child import state.
	 */
	private function private_same_server_root( array $import ): ?string {
		$root = is_string( $import['destination_root_path'] ?? null )
			? untrailingslashit( wp_normalize_path( $import['destination_root_path'] ) )
			: '';
		if (
			'' === $root
			|| ! is_dir( $root )
			|| is_link( $root )
			|| untrailingslashit( wp_normalize_path( ABSPATH ) ) === $root
		) {
			return null;
		}

		return $root;
	}

	/**
	 * Return the options target for normal or private same-server activation.
	 *
	 * @param string $job_id Child import job identifier.
	 */
	private function expected_options_target( string $job_id ): string {
		$import = $this->private_same_server_import( $job_id );
		if ( is_array( $import ) ) {
			$prefix = is_string( $import['destination_table_prefix'] ?? null )
				? $import['destination_table_prefix']
				: '';

			return 1 === preg_match( '/^[A-Za-z0-9_]+$/', $prefix ) ? $prefix . 'options' : '';
		}

		global $wpdb;

		return $wpdb instanceof wpdb ? (string) $wpdb->options : '';
	}

	/**
	 * Join one absolute base and relative path.
	 *
	 * @param string $base     Absolute base.
	 * @param string $relative Relative path.
	 */
	private function join_path( string $base, string $relative ): string {
		return rtrim( wp_normalize_path( $base ), '/' ) . '/' . ltrim( wp_normalize_path( $relative ), '/' );
	}


	/**
	 * Persist one blocker in the external journal.
	 *
	 * @param string              $job_id           Clone job identifier.
	 * @param array<string,mixed> $state            State.
	 * @param string              $code             Blocker code.
	 * @param bool                $database_swapped Whether the database is currently swapped.
	 * @return array<string,mixed>|null
	 */
	private function block( string $job_id, array $state, string $code, bool $database_swapped ): ?array {
		$blockers                        = is_array( $state['blockers'] ?? null ) ? $state['blockers'] : array();
		$blockers[]                      = $code;
		$state['status']                 = 'blocked';
		$state['database_swapped']       = $database_swapped;
		$state['handoff_ready']          = false;
		$state['active_files_untouched'] = true;
		$state['blockers']               = array_values( array_unique( $blockers ) );
		$state['updated_at']             = gmdate( DATE_ATOM );

		return $this->store->save( $job_id, $state ) ? $this->store->get( $job_id ) : null;
	}

	/**
	 * Normalize one activation table-name candidate.
	 *
	 * @param mixed $name Table-name candidate.
	 */
	private function table_name( mixed $name ): string {
		return is_string( $name ) && $this->valid_table_name( $name ) ? $name : '';
	}

	/**
	 * Validate one activation table identifier.
	 *
	 * @param string $table Table name.
	 */
	private function valid_table_name( string $table ): bool {
		return '' !== $table
			&& 64 >= strlen( $table )
			&& 1 === preg_match( '/^[A-Za-z0-9_$-]+$/', $table );
	}

	/**
	 * Quote one already validated SQL identifier.
	 *
	 * @param string $identifier Validated identifier.
	 */
	private function quote_identifier( string $identifier ): string {
		$tick = chr( 96 );

		return $tick . str_replace( $tick, $tick . $tick, $identifier ) . $tick;
	}

	/**
	 * Constant-time compare two SHA-256 values.
	 *
	 * @param mixed $left  First candidate hash.
	 * @param mixed $right Second candidate hash.
	 */
	private function same_hash( mixed $left, mixed $right ): bool {
		return is_string( $left )
			&& is_string( $right )
			&& 1 === preg_match( '/^[a-f0-9]{64}$/', $left )
			&& 1 === preg_match( '/^[a-f0-9]{64}$/', $right )
			&& hash_equals( $left, $right );
	}
}
