<?php
/**
 * Portable Clone local guarded finalization-plan administration endpoint.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge\Clone;

use SeoGeo\MigrationBridge\Operator\AdminOperatorScreen;

/**
 * Capability/nonce-gated local finalization-plan controller.
 */
final class AdminCloneLocalFinalizationController {
	public const ACTION       = 'seo_geo_migration_clone_local_finalization_plan';
	public const NONCE_ACTION = 'seo_geo_migration_clone_local_finalization_plan';

	/**
	 * Local finalization planner.
	 *
	 * @var LocalCloneFinalizationPlanner
	 */
	private LocalCloneFinalizationPlanner $planner;

	/**
	 * Construct controller.
	 *
	 * @param LocalCloneFinalizationPlanner|null $planner Optional local finalization planner.
	 */
	public function __construct( ?LocalCloneFinalizationPlanner $planner = null ) {
		$this->planner = $planner ?? new LocalCloneFinalizationPlanner();
	}

	/**
	 * Register authenticated endpoint.
	 */
	public function boot(): void {
		add_action( 'admin_post_' . self::ACTION, array( $this, 'handle' ) );
	}

	/**
	 * Advance one bounded read-only finalization-plan batch.
	 */
	public function handle(): never {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die(
				esc_html__( 'Administrator capability is required to build the local clone finalization plan.', 'seo-geo-migration-bridge' ),
				'',
				array( 'response' => 403 )
			);
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Job ID scopes the nonce checked immediately below.
		$job_id = isset( $_POST['clone_job_id'] )
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Job ID scopes the nonce checked immediately below.
			? sanitize_text_field( wp_unslash( $_POST['clone_job_id'] ) )
			: '';
		check_admin_referer( self::NONCE_ACTION . ':' . $job_id );

		if ( null === $this->planner->snapshot( $job_id ) ) {
			$confirmed = isset( $_POST['local_clone_finalization_confirm'] )
				&& '1' === sanitize_text_field( wp_unslash( $_POST['local_clone_finalization_confirm'] ) );
			if ( ! $confirmed ) {
				wp_die(
					esc_html__( 'Explicit confirmation is required before hashing staging and freezing the activation/rollback plan.', 'seo-geo-migration-bridge' ),
					'',
					array( 'response' => 400 )
				);
			}
		}

		$batch_rows      = isset( $_POST['local_clone_finalization_batch_rows'] )
			? absint( sanitize_text_field( wp_unslash( $_POST['local_clone_finalization_batch_rows'] ) ) )
			: ImportFinalizationPlanner::DEFAULT_BATCH_ROWS;
		$batch_files     = isset( $_POST['local_clone_finalization_batch_files'] )
			? absint( sanitize_text_field( wp_unslash( $_POST['local_clone_finalization_batch_files'] ) ) )
			: ImportFinalizationPlanner::DEFAULT_BATCH_FILES;
		$batch_megabytes = isset( $_POST['local_clone_finalization_batch_megabytes'] )
			? absint( sanitize_text_field( wp_unslash( $_POST['local_clone_finalization_batch_megabytes'] ) ) )
			: 16;

		$result = $this->planner->advance(
			$job_id,
			$batch_rows,
			$batch_files,
			$batch_megabytes * 1024 * 1024
		);
		if ( ! is_array( $result ) ) {
			wp_die(
				esc_html__( 'Local clone finalization planning could not be advanced.', 'seo-geo-migration-bridge' ),
				'',
				array( 'response' => 400 )
			);
		}

		$status = (string) ( $result['status'] ?? 'running' );
		if ( ! in_array( $status, array( 'running', 'ready', 'blocked' ), true ) ) {
			$status = 'blocked';
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'seo_geo_clone_local_finalization' => $status,
					'clone_job_id'                     => $job_id,
				),
				admin_url( 'tools.php?page=' . AdminOperatorScreen::PAGE_SLUG )
			)
		);
		exit;
	}
}
