<?php
/**
 * Controlled production cutover and rollback engine.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge\Cutover;

use SeoGeo\MigrationBridge\BaselineSnapshotStore;
use SeoGeo\MigrationBridge\DependencyGraphBuilder;
use SeoGeo\MigrationBridge\Migration\MigrationEngine;
use SeoGeo\MigrationBridge\Parity\ParityAllowlist;
use SeoGeo\MigrationBridge\Parity\SeoParityEngine;
use SeoGeo\MigrationBridge\SiteAnalyzer;
use Throwable;
use WP_Error;

/**
 * Executes explicitly authorized, reversible production cutovers.
 */
final class CutoverEngine {
	/**
	 * Destination theme.
	 */
	public const TARGET_THEME = 'seo-geo-theme';

	/**
	 * Migration Bridge plugin basename that must survive until acceptance.
	 */
	private const BRIDGE_PLUGIN = 'seo-geo-migration-bridge/seo-geo-migration-bridge.php';

	/**
	 * Site analyzer.
	 *
	 * @var SiteAnalyzer
	 */
	private SiteAnalyzer $analyzer;

	/**
	 * Dependency graph.
	 *
	 * @var DependencyGraphBuilder
	 */
	private DependencyGraphBuilder $graph;

	/**
	 * Persisted baseline store.
	 *
	 * @var BaselineSnapshotStore
	 */
	private BaselineSnapshotStore $baseline_store;

	/**
	 * SEO/GEO parity engine.
	 *
	 * @var SeoParityEngine
	 */
	private SeoParityEngine $parity;

	/**
	 * Fresh public-output snapshot provider.
	 *
	 * @var PublicSnapshotProviderInterface
	 */
	private PublicSnapshotProviderInterface $snapshot_provider;

	/**
	 * External backup evidence validator.
	 *
	 * @var BackupEvidenceValidator
	 */
	private BackupEvidenceValidator $backup_validator;

	/**
	 * Migration quality evidence validator.
	 *
	 * @var QualityEvidenceValidator
	 */
	private QualityEvidenceValidator $quality_validator;

	/**
	 * Cutover history store.
	 *
	 * @var CutoverSnapshotStore
	 */
	private CutoverSnapshotStore $store;

	/**
	 * Construct the cutover engine.
	 *
	 * @param SiteAnalyzer|null                    $analyzer          Optional analyzer override.
	 * @param DependencyGraphBuilder|null          $graph             Optional graph override.
	 * @param BaselineSnapshotStore|null           $baseline_store    Optional baseline-store override.
	 * @param SeoParityEngine|null                 $parity            Optional parity-engine override.
	 * @param PublicSnapshotProviderInterface|null $snapshot_provider Optional public snapshot provider.
	 * @param BackupEvidenceValidator|null         $backup_validator  Optional backup validator.
	 * @param QualityEvidenceValidator|null        $quality_validator Optional quality validator.
	 * @param CutoverSnapshotStore|null            $store             Optional history store.
	 */
	public function __construct(
		?SiteAnalyzer $analyzer = null,
		?DependencyGraphBuilder $graph = null,
		?BaselineSnapshotStore $baseline_store = null,
		?SeoParityEngine $parity = null,
		?PublicSnapshotProviderInterface $snapshot_provider = null,
		?BackupEvidenceValidator $backup_validator = null,
		?QualityEvidenceValidator $quality_validator = null,
		?CutoverSnapshotStore $store = null
	) {
		$this->analyzer          = $analyzer ?? new SiteAnalyzer();
		$this->graph             = $graph ?? new DependencyGraphBuilder();
		$this->baseline_store    = $baseline_store ?? new BaselineSnapshotStore();
		$this->parity            = $parity ?? new SeoParityEngine();
		$this->snapshot_provider = $snapshot_provider ?? new BaselinePublicSnapshotProvider();
		$this->backup_validator  = $backup_validator ?? new BackupEvidenceValidator();
		$this->quality_validator = $quality_validator ?? new QualityEvidenceValidator();
		$this->store             = $store ?? new CutoverSnapshotStore();
	}

	/**
	 * Build a non-mutating cutover plan.
	 *
	 * @param array<string,mixed> $backup_evidence       External recovery evidence.
	 * @param list<string>        $plugins_to_deactivate Explicit plugin basenames.
	 * @param array<int,mixed>    $allowlist_rules       Phase 8F exact-difference approvals.
	 * @param array<string,mixed> $quality_evidence      Accessibility/performance evidence.
	 * @param array<string,mixed> $maintenance           Optional maintenance flags.
	 * @return array<string,mixed>
	 */
	public function plan(
		array $backup_evidence,
		array $plugins_to_deactivate = array(),
		array $allowlist_rules = array(),
		array $quality_evidence = array(),
		array $maintenance = array()
	): array {
		$blockers = array();
		$warnings = array();

		if ( defined( 'SEO_GEO_MIGRATION_SANDBOX' ) && true === SEO_GEO_MIGRATION_SANDBOX ) {
			$blockers[] = 'sandbox-marker-still-enabled';
		}
		if ( is_multisite() ) {
			$blockers[] = 'multisite-cutover-not-supported';
		}
		if ( 1 !== (int) get_option( 'blog_public', 1 ) ) {
			$blockers[] = 'production-search-visibility-disabled';
		}

		$theme = wp_get_theme( self::TARGET_THEME );
		if ( ! $theme->exists() ) {
			$blockers[] = 'destination-theme-not-installed';
		}

		$baseline = $this->baseline_store->latest();
		if ( ! is_array( $baseline ) || ! isset( $baseline['snapshot'] ) || ! is_array( $baseline['snapshot'] ) ) {
			$blockers[] = 'baseline-unavailable';
		}

		$backup = $this->backup_validator->validate( $backup_evidence );
		foreach ( $backup['errors'] as $error ) {
			$blockers[] = 'backup:' . $error;
		}

		$quality = $this->quality_validator->validate( $quality_evidence );
		foreach ( $quality['errors'] as $error ) {
			$blockers[] = 'quality:' . $error;
		}

		$analysis = $this->analyzer->analyze();
		$graph    = $this->graph->build(
			$analysis,
			is_array( $baseline ) && isset( $baseline['snapshot'] ) && is_array( $baseline['snapshot'] )
				? $baseline['snapshot']
				: null
		);

		$plugins = $this->normalize_plugins( $plugins_to_deactivate );
		foreach ( $plugins_to_deactivate as $requested_plugin ) {
			if ( ! is_string( $requested_plugin ) || array() === $this->normalize_plugins( array( $requested_plugin ) ) ) {
				$blockers[] = 'plugin-basename-invalid';
			}
		}

		foreach ( $plugins as $plugin ) {
			if ( self::BRIDGE_PLUGIN === $plugin ) {
				$blockers[] = 'plugin-deactivation-forbidden:migration-bridge';
				continue;
			}

			if ( ! $this->plugin_is_active( $plugin ) ) {
				$blockers[] = 'plugin-not-active:' . $plugin;
				continue;
			}

			$classifications = $this->plugin_classifications( $plugin, $analysis, $graph );
			if ( array() === $classifications ) {
				$blockers[] = 'plugin-classification-unknown:' . $plugin;
				continue;
			}

			$unsafe = array_diff( $classifications, array( 'REPLACE', 'REMOVE-CANDIDATE', 'OPTIONAL' ) );
			if ( array() !== $unsafe ) {
				$blockers[] = 'plugin-deactivation-not-approved:' . $plugin . ':' . implode( '+', $classifications );
			}
		}

		$parity_report = null;
		if ( is_array( $baseline ) && isset( $baseline['snapshot'] ) && is_array( $baseline['snapshot'] ) ) {
			try {
				$candidate     = $this->snapshot_provider->capture();
				$parity_report = $this->parity->compare( $baseline['snapshot'], $candidate, $allowlist_rules );
				if ( true !== ( $parity_report['accepted'] ?? false ) ) {
					$blockers[] = 'pre-cutover-parity-not-accepted';
				}
			} catch ( Throwable $exception ) {
				$blockers[] = 'pre-cutover-snapshot-failed';
				$warnings[] = 'snapshot-error:' . sanitize_key( get_class( $exception ) );
			}
		}

		$latest = $this->store->latest();
		if ( is_array( $latest ) && in_array( $latest['status'] ?? null, array( 'prepared', 'cutover-active' ), true ) ) {
			$blockers[] = 'active-cutover-record-exists';
		}

		$runtime     = $this->capture_runtime_state();
		$maintenance = $this->normalize_maintenance( $maintenance, $runtime, $plugins );

		if ( $this->active_cache_provider_exists( $analysis ) && true === $maintenance['flush_object_cache'] ) {
			$warnings[] = 'external-cache-provider-may-require-provider-specific-purge';
		}

		$blockers = array_values( array_unique( $blockers ) );
		$warnings = array_values( array_unique( $warnings ) );
		sort( $blockers );
		sort( $warnings );

		$plan = array(
			'schema_version' => 1,
			'mode'           => 'production-cutover-plan',
			'ready'          => array() === $blockers,
			'target_theme'   => self::TARGET_THEME,
			'current_theme'  => $runtime['theme']['stylesheet'],
			'plugins'        => array(
				'deactivate' => $plugins,
				'delete'     => array(),
			),
			'backup'         => array(
				'valid'    => $backup['valid'],
				'evidence' => $backup['evidence'],
			),
			'quality'        => array(
				'valid'    => $quality['valid'],
				'evidence' => $quality['evidence'],
			),
			'parity'         => is_array( $parity_report )
				? array(
					'accepted'    => true === ( $parity_report['accepted'] ?? false ),
					'summary'     => $parity_report['summary'] ?? array(),
					'report_hash' => $this->fingerprint( $parity_report ),
				)
				: null,
			'maintenance'    => $maintenance,
			'blockers'       => $blockers,
			'warnings'       => $warnings,
			'authorization'  => array(
				'requires_manage_options' => true,
				'requires_nonce'          => true,
				'requires_confirmation'   => true,
			),
			'safety'         => array(
				'plugin_deletion_allowed'      => false,
				'theme_deletion_allowed'       => false,
				'database_reset_allowed'       => false,
				'uploads_reset_allowed'        => false,
				'bridge_deactivation_allowed'  => false,
				'rollback_required_until_acceptance' => true,
			),
		);

		$plan['plan_sha256'] = $this->fingerprint( $plan );
		return $plan;
	}

	/**
	 * Execute an explicitly confirmed production cutover.
	 *
	 * @param array<string,mixed> $backup_evidence       External recovery evidence.
	 * @param list<string>        $plugins_to_deactivate Explicit plugin basenames.
	 * @param array<int,mixed>    $allowlist_rules       Exact parity approvals.
	 * @param array<string,mixed> $quality_evidence      Accessibility/performance evidence.
	 * @param array<string,mixed> $maintenance           Maintenance flags.
	 * @param string              $nonce                 Cutover nonce.
	 * @param bool                $confirmed             Explicit confirmation.
	 * @return array<string,mixed>|WP_Error
	 */
	public function execute(
		array $backup_evidence,
		array $plugins_to_deactivate,
		array $allowlist_rules,
		array $quality_evidence,
		array $maintenance,
		string $nonce,
		bool $confirmed
	): array|WP_Error {
		$auth = $this->authorize( $nonce, self::execute_nonce_action(), $confirmed, 'cutover' );
		if ( $auth instanceof WP_Error ) {
			return $auth;
		}

		$plan = $this->plan( $backup_evidence, $plugins_to_deactivate, $allowlist_rules, $quality_evidence, $maintenance );
		if ( true !== $plan['ready'] ) {
			return new WP_Error(
				'seo_geo_cutover_not_ready',
				'Cutover plan is blocked: ' . implode( ', ', $plan['blockers'] )
			);
		}

		$baseline = $this->baseline_store->latest();
		if ( ! is_array( $baseline ) || ! isset( $baseline['snapshot'] ) || ! is_array( $baseline['snapshot'] ) ) {
			return new WP_Error( 'seo_geo_cutover_baseline_unavailable', 'Persisted migration baseline is unavailable.' );
		}

		$before   = $this->capture_runtime_state();
		$snapshot = array(
			'plan_sha256'    => $plan['plan_sha256'],
			'backup'         => $plan['backup']['evidence'],
			'quality'        => $plan['quality']['evidence'],
			'baseline'       => array(
				'id'                  => is_string( $baseline['id'] ?? null ) ? $baseline['id'] : null,
				'sha256'              => is_string( $baseline['sha256'] ?? null ) ? $baseline['sha256'] : $this->fingerprint( $baseline['snapshot'] ),
				'redirect_map_sha256' => $this->fingerprint( $baseline['snapshot']['redirects']['entries'] ?? array() ),
			),
			'runtime_before' => $before,
			'migration'      => $this->migration_recovery_index(),
			'actions'        => array(
				'target_theme'          => self::TARGET_THEME,
				'plugins_deactivated'   => $plan['plugins']['deactivate'],
				'maintenance'           => $plan['maintenance'],
				'parity_allowlist_hash' => $this->fingerprint( $allowlist_rules ),
			),
		);

		$stored = $this->store->create( $snapshot );
		if ( ! $stored['saved'] || ! is_string( $stored['id'] ) ) {
			return new WP_Error( 'seo_geo_cutover_snapshot_failed', 'Could not persist the mandatory pre-cutover recovery snapshot.' );
		}
		$record_id = $stored['id'];

		try {
			$this->apply_cutover( $plan['plugins']['deactivate'], $plan['maintenance'] );
			$post_health = $this->cutover_health( $before, $plan['plugins']['deactivate'] );
			$post_parity = $this->fresh_parity( $baseline['snapshot'], $allowlist_rules );

			if ( ! $post_health['healthy'] || true !== ( $post_parity['accepted'] ?? false ) ) {
				$rollback = $this->restore_runtime( $before, $plan['plugins']['deactivate'], $plan['maintenance'] );
				$this->store->transition(
					$record_id,
					array( 'prepared' ),
					'rolled-back-auto',
					array(
						'health_ok'   => $post_health['healthy'],
						'parity_ok'   => true === ( $post_parity['accepted'] ?? false ),
						'rollback_ok' => $rollback,
					)
				);

				return new WP_Error( 'seo_geo_cutover_postcheck_failed', 'Post-cutover health or SEO/GEO parity failed; runtime rollback was attempted automatically.' );
			}

			if (
				! $this->store->transition(
					$record_id,
					array( 'prepared' ),
					'cutover-active',
					array(
						'health_sha256' => $this->fingerprint( $post_health ),
						'parity_sha256' => $this->fingerprint( $post_parity ),
					)
				)
			) {
				$this->restore_runtime( $before, $plan['plugins']['deactivate'], $plan['maintenance'] );
				return new WP_Error( 'seo_geo_cutover_state_write_failed', 'Cutover state could not be committed; runtime rollback was attempted.' );
			}
		} catch ( Throwable $exception ) {
			$rollback = $this->restore_runtime( $before, $plan['plugins']['deactivate'], $plan['maintenance'] );
			$this->store->transition(
				$record_id,
				array( 'prepared' ),
				'rolled-back-auto',
				array(
					'exception'   => sanitize_key( get_class( $exception ) ),
					'rollback_ok' => $rollback,
				)
			);

			return new WP_Error( 'seo_geo_cutover_execution_failed', 'Cutover execution failed; runtime rollback was attempted.' );
		}

		return array(
			'schema_version'    => 1,
			'mode'              => 'production-cutover',
			'status'            => 'cutover-active',
			'record_id'         => $record_id,
			'target_theme'      => self::TARGET_THEME,
			'plugins_deactivated' => $plan['plugins']['deactivate'],
			'rollback_available' => true,
			'accepted'          => false,
			'safety'            => array(
				'backup_verified_before_mutation' => true,
				'post_cutover_health_passed'      => true,
				'post_cutover_parity_passed'      => true,
				'plugin_deletion_performed'       => false,
				'theme_deletion_performed'        => false,
			),
		);
	}

	/**
	 * Roll back the latest active cutover.
	 *
	 * @param array<int,mixed> $allowlist_rules Exact parity approvals used for rollback verification.
	 * @param string           $nonce           Rollback nonce.
	 * @param bool             $confirmed       Explicit confirmation.
	 * @return array<string,mixed>|WP_Error
	 */
	public function rollback( array $allowlist_rules, string $nonce, bool $confirmed ): array|WP_Error {
		$auth = $this->authorize( $nonce, self::rollback_nonce_action(), $confirmed, 'rollback' );
		if ( $auth instanceof WP_Error ) {
			return $auth;
		}

		$record = $this->store->latest();
		if ( ! is_array( $record ) || 'cutover-active' !== ( $record['status'] ?? null ) ) {
			return new WP_Error( 'seo_geo_cutover_rollback_unavailable', 'No active unaccepted cutover is available for rollback.' );
		}

		$before = $record['runtime_before'] ?? null;
		$actions = $record['actions'] ?? null;
		if ( ! is_array( $before ) || ! is_array( $actions ) ) {
			return new WP_Error( 'seo_geo_cutover_snapshot_invalid', 'Cutover recovery snapshot is incomplete.' );
		}

		$plugins = isset( $actions['plugins_deactivated'] ) && is_array( $actions['plugins_deactivated'] )
			? array_values( array_filter( $actions['plugins_deactivated'], 'is_string' ) )
			: array();
		$maintenance = isset( $actions['maintenance'] ) && is_array( $actions['maintenance'] ) ? $actions['maintenance'] : array();

		if ( ! $this->active_cutover_matches_snapshot( $before, $plugins ) ) {
			return new WP_Error( 'seo_geo_cutover_rollback_state_drift', 'Runtime state changed outside the cutover plan; refusing to overwrite unrelated changes.' );
		}

		$restored = $this->restore_runtime( $before, $plugins, $maintenance );
		if ( ! $restored ) {
			return new WP_Error( 'seo_geo_cutover_rollback_failed', 'Runtime rollback did not restore the recorded theme/plugin state.' );
		}

		$baseline = $this->baseline_store->latest();
		$parity_ok = false;
		if ( is_array( $baseline ) && isset( $baseline['snapshot'] ) && is_array( $baseline['snapshot'] ) ) {
			try {
				$parity_ok = true === ( $this->fresh_parity( $baseline['snapshot'], $allowlist_rules )['accepted'] ?? false );
			} catch ( Throwable ) {
				$parity_ok = false;
			}
		}

		$id = is_string( $record['id'] ?? null ) ? $record['id'] : '';
		$next_status = $parity_ok ? 'rolled-back' : 'rolled-back-review-required';
		$this->store->transition(
			$id,
			array( 'cutover-active' ),
			$next_status,
			array(
				'runtime_restored' => true,
				'parity_ok'        => $parity_ok,
			)
		);

		if ( ! $parity_ok ) {
			return new WP_Error( 'seo_geo_cutover_rollback_parity_failed', 'Runtime state was restored, but the public-output parity check still requires review or external backup restoration.' );
		}

		return array(
			'schema_version'     => 1,
			'mode'               => 'production-cutover-rollback',
			'status'             => 'rolled-back',
			'record_id'          => $id,
			'runtime_restored'   => true,
			'parity_restored'    => true,
			'rollback_available' => false,
		);
	}

	/**
	 * Explicitly accept the latest active cutover and close runtime rollback.
	 *
	 * @param array<int,mixed> $allowlist_rules Exact parity approvals.
	 * @param string           $nonce           Acceptance nonce.
	 * @param bool             $confirmed       Explicit confirmation.
	 * @return array<string,mixed>|WP_Error
	 */
	public function accept( array $allowlist_rules, string $nonce, bool $confirmed ): array|WP_Error {
		$auth = $this->authorize( $nonce, self::accept_nonce_action(), $confirmed, 'acceptance' );
		if ( $auth instanceof WP_Error ) {
			return $auth;
		}

		$record = $this->store->latest();
		if ( ! is_array( $record ) || 'cutover-active' !== ( $record['status'] ?? null ) ) {
			return new WP_Error( 'seo_geo_cutover_acceptance_unavailable', 'No active cutover is available for acceptance.' );
		}

		$before  = $record['runtime_before'] ?? null;
		$actions = $record['actions'] ?? null;
		if ( ! is_array( $before ) || ! is_array( $actions ) ) {
			return new WP_Error( 'seo_geo_cutover_snapshot_invalid', 'Cutover recovery snapshot is incomplete.' );
		}

		$plugins = isset( $actions['plugins_deactivated'] ) && is_array( $actions['plugins_deactivated'] )
			? array_values( array_filter( $actions['plugins_deactivated'], 'is_string' ) )
			: array();

		$health = $this->cutover_health( $before, $plugins );
		if ( ! $health['healthy'] ) {
			return new WP_Error( 'seo_geo_cutover_acceptance_health_failed', 'Current production runtime no longer matches the accepted cutover plan.' );
		}

		$baseline = $this->baseline_store->latest();
		if ( ! is_array( $baseline ) || ! isset( $baseline['snapshot'] ) || ! is_array( $baseline['snapshot'] ) ) {
			return new WP_Error( 'seo_geo_cutover_baseline_unavailable', 'Persisted migration baseline is unavailable.' );
		}

		try {
			$parity = $this->fresh_parity( $baseline['snapshot'], $allowlist_rules );
		} catch ( Throwable ) {
			return new WP_Error( 'seo_geo_cutover_acceptance_parity_failed', 'Fresh public-output parity could not be captured.' );
		}

		if ( true !== ( $parity['accepted'] ?? false ) ) {
			return new WP_Error( 'seo_geo_cutover_acceptance_parity_failed', 'Fresh SEO/GEO parity is not accepted.' );
		}

		$id = is_string( $record['id'] ?? null ) ? $record['id'] : '';
		if (
			! $this->store->transition(
				$id,
				array( 'cutover-active' ),
				'accepted',
				array(
					'health_sha256' => $this->fingerprint( $health ),
					'parity_sha256' => $this->fingerprint( $parity ),
				)
			)
		) {
			return new WP_Error( 'seo_geo_cutover_acceptance_write_failed', 'Could not persist cutover acceptance.' );
		}

		return array(
			'schema_version'     => 1,
			'mode'               => 'production-cutover-acceptance',
			'status'             => 'accepted',
			'record_id'          => $id,
			'rollback_available' => false,
			'recovery_evidence_retained' => true,
		);
	}

	/**
	 * Return cutover execution nonce action.
	 */
	public static function execute_nonce_action(): string {
		return 'seo_geo_cutover_execute';
	}

	/**
	 * Return rollback nonce action.
	 */
	public static function rollback_nonce_action(): string {
		return 'seo_geo_cutover_rollback';
	}

	/**
	 * Return acceptance nonce action.
	 */
	public static function accept_nonce_action(): string {
		return 'seo_geo_cutover_accept';
	}

	/**
	 * Apply controlled theme/plugin changes.
	 *
	 * @param list<string>        $plugins     Plugins to deactivate.
	 * @param array<string,mixed> $maintenance Maintenance flags.
	 */
	private function apply_cutover( array $plugins, array $maintenance ): void {
		$this->load_plugin_functions();

		if ( get_stylesheet() !== self::TARGET_THEME ) {
			switch_theme( self::TARGET_THEME );
		}

		if ( array() !== $plugins ) {
			deactivate_plugins( $plugins, false, false );
		}

		$this->run_maintenance( $maintenance );
	}

	/**
	 * Restore the exact runtime state controlled by this cutover.
	 *
	 * @param array<string,mixed> $before      Pre-cutover runtime state.
	 * @param list<string>        $plugins     Plugins deactivated by cutover.
	 * @param array<string,mixed> $maintenance Maintenance flags.
	 */
	private function restore_runtime( array $before, array $plugins, array $maintenance ): bool {
		$this->load_plugin_functions();

		$stylesheet = isset( $before['theme']['stylesheet'] ) && is_string( $before['theme']['stylesheet'] )
			? $before['theme']['stylesheet']
			: '';
		if ( '' === $stylesheet ) {
			return false;
		}

		if ( get_stylesheet() !== $stylesheet ) {
			switch_theme( $stylesheet );
		}

		foreach ( $plugins as $plugin ) {
			if ( ! $this->plugin_is_active( $plugin ) ) {
				$result = activate_plugin( $plugin, '', false, false );
				if ( is_wp_error( $result ) ) {
					return false;
				}
			}
		}

		$this->run_maintenance( $maintenance );
		return $this->runtime_matches_snapshot( $before );
	}

	/**
	 * Run maintenance actions only when the plan requires them.
	 *
	 * @param array<string,mixed> $maintenance Maintenance flags.
	 */
	private function run_maintenance( array $maintenance ): void {
		if ( true === ( $maintenance['flush_rewrites'] ?? false ) ) {
			flush_rewrite_rules( false );
		}
		if ( true === ( $maintenance['flush_object_cache'] ?? false ) ) {
			wp_cache_flush();
		}
		if ( true === ( $maintenance['refresh_sitemaps'] ?? false ) && function_exists( 'wp_clean_sitemaps_cache' ) ) {
			wp_clean_sitemaps_cache();
		}
	}

	/**
	 * Validate cutover state immediately after mutations.
	 *
	 * @param array<string,mixed> $before  Pre-cutover runtime state.
	 * @param list<string>        $plugins Plugins intentionally deactivated.
	 * @return array{healthy:bool,checks:array<string,bool>}
	 */
	private function cutover_health( array $before, array $plugins ): array {
		$current_active  = $this->active_plugins();
		$expected_active = array_values( array_diff( $before['active_plugins'] ?? array(), $plugins ) );
		sort( $expected_active );

		$checks = array(
			'target_theme_active'       => self::TARGET_THEME === get_stylesheet(),
			'expected_plugins_active'   => $expected_active === $current_active,
			'bridge_still_active'       => in_array( self::BRIDGE_PLUGIN, $current_active, true ),
			'protected_options_unchanged' => $this->protected_options() === ( $before['protected_options'] ?? array() ),
		);

		return array(
			'healthy' => ! in_array( false, $checks, true ),
			'checks'  => $checks,
		);
	}

	/**
	 * Verify current active cutover state has not drifted before manual rollback.
	 *
	 * @param array<string,mixed> $before  Pre-cutover runtime state.
	 * @param list<string>        $plugins Plugins deactivated by cutover.
	 */
	private function active_cutover_matches_snapshot( array $before, array $plugins ): bool {
		return $this->cutover_health( $before, $plugins )['healthy'];
	}

	/**
	 * Verify exact restoration of controlled state.
	 *
	 * @param array<string,mixed> $before Pre-cutover runtime state.
	 */
	private function runtime_matches_snapshot( array $before ): bool {
		return ( $before['theme']['stylesheet'] ?? null ) === get_stylesheet()
			&& ( $before['active_plugins'] ?? array() ) === $this->active_plugins()
			&& ( $before['protected_options'] ?? array() ) === $this->protected_options();
	}

	/**
	 * Capture current controlled runtime state.
	 *
	 * @return array<string,mixed>
	 */
	private function capture_runtime_state(): array {
		$analysis = $this->analyzer->analyze();
		$themes   = isset( $analysis['themes'] ) && is_array( $analysis['themes'] ) ? $analysis['themes'] : array();
		$plugins  = isset( $analysis['plugins'] ) && is_array( $analysis['plugins'] ) ? $analysis['plugins'] : array();

		$theme_versions = array();
		foreach ( $themes as $theme ) {
			if ( is_array( $theme ) && is_string( $theme['stylesheet'] ?? null ) ) {
				$theme_versions[ $theme['stylesheet'] ] = is_string( $theme['version'] ?? null ) ? $theme['version'] : '';
			}
		}
		ksort( $theme_versions );

		$plugin_versions = array();
		foreach ( $plugins as $plugin ) {
			if ( is_array( $plugin ) && is_string( $plugin['basename'] ?? null ) ) {
				$plugin_versions[ $plugin['basename'] ] = is_string( $plugin['version'] ?? null ) ? $plugin['version'] : '';
			}
		}
		ksort( $plugin_versions );

		$stylesheet = get_stylesheet();
		$template   = get_template();

		return array(
			'theme'             => array(
				'stylesheet' => $stylesheet,
				'template'   => $template,
				'version'    => $theme_versions[ $stylesheet ] ?? '',
			),
			'active_plugins'    => $this->active_plugins(),
			'plugin_versions'   => $plugin_versions,
			'theme_versions'    => $theme_versions,
			'protected_options' => $this->protected_options(),
		);
	}

	/**
	 * Return protected options that cutover must not silently change.
	 *
	 * @return array<string,mixed>
	 */
	private function protected_options(): array {
		return array(
			'home'                  => get_option( 'home' ),
			'siteurl'               => get_option( 'siteurl' ),
			'permalink_structure'   => get_option( 'permalink_structure' ),
			'blog_public'           => (int) get_option( 'blog_public', 1 ),
			'seo_geo_active_preset' => get_option( 'seo_geo_active_preset', null ),
		);
	}

	/**
	 * Return sorted active plugin basenames.
	 *
	 * @return list<string>
	 */
	private function active_plugins(): array {
		$plugins = get_option( 'active_plugins', array() );
		$plugins = is_array( $plugins ) ? array_values( array_filter( $plugins, 'is_string' ) ) : array();
		sort( $plugins );
		return $plugins;
	}

	/**
	 * Return whether a plugin is currently active.
	 *
	 * @param string $plugin Plugin basename.
	 */
	private function plugin_is_active( string $plugin ): bool {
		$this->load_plugin_functions();
		return is_plugin_active( $plugin );
	}

	/**
	 * Map one plugin basename to conservative Phase 8C classifications.
	 *
	 * @param string              $plugin   Plugin basename.
	 * @param array<string,mixed> $analysis Phase 8A report.
	 * @param array<string,mixed> $graph    Phase 8C graph.
	 * @return list<string>
	 */
	private function plugin_classifications( string $plugin, array $analysis, array $graph ): array {
		$component_map = array();
		$components    = isset( $graph['components'] ) && is_array( $graph['components'] ) ? $graph['components'] : array();

		foreach ( $components as $component ) {
			if (
				is_array( $component )
				&& is_string( $component['component_id'] ?? null )
				&& is_string( $component['classification'] ?? null )
			) {
				$component_map[ $component['component_id'] ] = $component['classification'];
			}
		}

		$classifications = array();
		$providers       = isset( $analysis['providers'] ) && is_array( $analysis['providers'] ) ? $analysis['providers'] : array();

		foreach ( $providers as $category => $rows ) {
			if ( ! is_string( $category ) || ! is_array( $rows ) ) {
				continue;
			}
			foreach ( $rows as $provider ) {
				if (
					! is_array( $provider )
					|| ! is_string( $provider['id'] ?? null )
					|| ! is_array( $provider['matched_plugins'] ?? null )
					|| ! in_array( $plugin, $provider['matched_plugins'], true )
				) {
					continue;
				}

				$component_id = 'provider:' . $category . ':' . $provider['id'];
				if ( isset( $component_map[ $component_id ] ) ) {
					$classifications[] = $component_map[ $component_id ];
				}
			}
		}

		$builders = isset( $analysis['builders'] ) && is_array( $analysis['builders'] ) ? $analysis['builders'] : array();
		foreach ( $builders as $builder ) {
			if ( ! is_array( $builder ) || ! is_string( $builder['id'] ?? null ) || ! is_array( $builder['evidence'] ?? null ) ) {
				continue;
			}

			$matches = array_map(
				static fn( mixed $item ): string => is_string( $item ) ? preg_replace( '/^plugin:/', '', $item ) ?? '' : '',
				$builder['evidence']
			);
			if ( ! in_array( $plugin, $matches, true ) ) {
				continue;
			}

			$component_id = 'builder:' . $builder['id'];
			if ( isset( $component_map[ $component_id ] ) ) {
				$classifications[] = $component_map[ $component_id ];
			}
		}

		$plugin_component = 'plugin:' . $plugin;
		if ( isset( $component_map[ $plugin_component ] ) ) {
			$classifications[] = $component_map[ $plugin_component ];
		}

		$classifications = array_values( array_unique( $classifications ) );
		sort( $classifications );
		return $classifications;
	}

	/**
	 * Normalize explicit plugin basenames.
	 *
	 * @param list<string> $plugins Candidate basenames.
	 * @return list<string>
	 */
	private function normalize_plugins( array $plugins ): array {
		$normalized = array();

		foreach ( $plugins as $plugin ) {
			if ( ! is_string( $plugin ) ) {
				continue;
			}

			$plugin = ltrim( str_replace( '\\', '/', trim( $plugin ) ), '/' );
			if (
				'' === $plugin
				|| str_contains( $plugin, '..' )
				|| 1 !== preg_match( '#^[A-Za-z0-9._-]+/[A-Za-z0-9._/-]+\.php$#', $plugin )
			) {
				continue;
			}
			$normalized[] = $plugin;
		}

		$normalized = array_values( array_unique( $normalized ) );
		sort( $normalized );
		return $normalized;
	}

	/**
	 * Normalize maintenance actions based on actual cutover changes.
	 *
	 * @param array<string,mixed> $requested Requested flags.
	 * @param array<string,mixed> $runtime   Current runtime.
	 * @param list<string>        $plugins   Plugins to deactivate.
	 * @return array{flush_rewrites:bool,flush_object_cache:bool,refresh_sitemaps:bool}
	 */
	private function normalize_maintenance( array $requested, array $runtime, array $plugins ): array {
		$theme_change = self::TARGET_THEME !== ( $runtime['theme']['stylesheet'] ?? null );
		$state_change = $theme_change || array() !== $plugins;

		return array(
			'flush_rewrites'     => $theme_change || true === ( $requested['flush_rewrites'] ?? false ),
			'flush_object_cache' => $state_change || true === ( $requested['flush_object_cache'] ?? false ),
			'refresh_sitemaps'   => $state_change || true === ( $requested['refresh_sitemaps'] ?? false ),
		);
	}

	/**
	 * Return whether an active known external cache provider exists.
	 *
	 * @param array<string,mixed> $analysis Phase 8A report.
	 */
	private function active_cache_provider_exists( array $analysis ): bool {
		$providers = isset( $analysis['providers']['cache'] ) && is_array( $analysis['providers']['cache'] )
			? $analysis['providers']['cache']
			: array();

		foreach ( $providers as $provider ) {
			if ( is_array( $provider ) && true === ( $provider['active'] ?? false ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Capture migration backup/state fingerprints without duplicating private bodies.
	 *
	 * @return list<array<string,mixed>>
	 */
	private function migration_recovery_index(): array {
		$ids = get_posts(
			array(
				'post_type'      => 'any',
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_key'       => MigrationEngine::STATE_META,
				'orderby'        => 'ID',
				'order'          => 'ASC',
			)
		);

		$rows = array();
		foreach ( $ids as $id ) {
			$object_id = (int) $id;
			$backup    = get_post_meta( $object_id, MigrationEngine::BACKUP_META, true );
			$state     = get_post_meta( $object_id, MigrationEngine::STATE_META, true );

			$rows[] = array(
				'object_id'     => $object_id,
				'backup_exists' => is_array( $backup ),
				'backup_sha256' => is_array( $backup ) && is_string( $backup['sha256'] ?? null ) ? $backup['sha256'] : null,
				'before_sha256' => is_array( $state ) && is_string( $state['before_sha256'] ?? null ) ? $state['before_sha256'] : null,
				'after_sha256'  => is_array( $state ) && is_string( $state['after_sha256'] ?? null ) ? $state['after_sha256'] : null,
			);
		}

		return $rows;
	}

	/**
	 * Capture and compare fresh public output against the persisted baseline.
	 *
	 * @param array<string,mixed> $baseline        Persisted baseline snapshot.
	 * @param array<int,mixed>    $allowlist_rules Exact parity approvals.
	 * @return array<string,mixed>
	 */
	private function fresh_parity( array $baseline, array $allowlist_rules ): array {
		return $this->parity->compare( $baseline, $this->snapshot_provider->capture(), $allowlist_rules );
	}

	/**
	 * Authorize one explicit administrator action.
	 *
	 * @param string $nonce       Submitted nonce.
	 * @param string $nonce_action Expected nonce action.
	 * @param bool   $confirmed   Explicit confirmation.
	 * @param string $operation   Operation label.
	 */
	private function authorize( string $nonce, string $nonce_action, bool $confirmed, string $operation ): true|WP_Error {
		if ( ! $confirmed ) {
			return new WP_Error( 'seo_geo_cutover_confirmation_required', 'Explicit ' . $operation . ' confirmation is required.' );
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error( 'seo_geo_cutover_forbidden', 'Administrator cutover capability is required.' );
		}
		if ( ! wp_verify_nonce( $nonce, $nonce_action ) ) {
			return new WP_Error( 'seo_geo_cutover_invalid_nonce', 'Cutover nonce is invalid or expired.' );
		}
		return true;
	}

	/**
	 * Load WordPress plugin administration helpers.
	 */
	private function load_plugin_functions(): void {
		if ( ! function_exists( 'is_plugin_active' ) || ! function_exists( 'deactivate_plugins' ) || ! function_exists( 'activate_plugin' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
	}

	/**
	 * Fingerprint a bounded report/state value.
	 *
	 * @param mixed $value Value to fingerprint.
	 */
	private function fingerprint( mixed $value ): string {
		$encoded = wp_json_encode( $value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		if ( false === $encoded ) {
			$encoded = '[unencodable:' . get_debug_type( $value ) . ']';
		}
		return hash( 'sha256', $encoded );
	}
}
