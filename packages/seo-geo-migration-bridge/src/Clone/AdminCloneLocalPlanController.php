<?php
/**
 * Portable Clone local-clone destination-plan administration endpoint.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge\Clone;

use SeoGeo\MigrationBridge\Operator\AdminOperatorScreen;

/**
 * Freezes a nonce/capability/confirmation-gated local clone destination plan.
 */
final class AdminCloneLocalPlanController {
	public const ACTION       = 'seo_geo_migration_clone_local_plan';
	public const NONCE_ACTION = 'seo_geo_migration_clone_local_plan';

	/**
	 * Local-clone destination-plan orchestrator.
	 *
	 * @var LocalCloneOrchestrator
	 */
	private LocalCloneOrchestrator $orchestrator;

	/**
	 * Construct the controller.
	 *
	 * @param LocalCloneOrchestrator|null $orchestrator Optional orchestrator.
	 */
	public function __construct( ?LocalCloneOrchestrator $orchestrator = null ) {
		$this->orchestrator = $orchestrator ?? new LocalCloneOrchestrator();
	}

	/**
	 * Register authenticated endpoint.
	 */
	public function boot(): void {
		add_action( 'admin_post_' . self::ACTION, array( $this, 'handle' ) );
	}

	/**
	 * Validate and persist one destination plan.
	 */
	public function handle(): never {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die(
				esc_html__( 'Administrator capability is required to prepare a local clone destination.', 'seo-geo-migration-bridge' ),
				'',
				array( 'response' => 403 )
			);
		}

		$job_id = isset( $_POST['clone_job_id'] )
			? sanitize_text_field( wp_unslash( $_POST['clone_job_id'] ) )
			: '';
		check_admin_referer( self::NONCE_ACTION . ':' . $job_id );

		$confirmed = isset( $_POST['local_clone_confirm'] )
			&& '1' === sanitize_text_field( wp_unslash( $_POST['local_clone_confirm'] ) );
		if ( ! $confirmed ) {
			wp_die(
				esc_html__( 'Explicit confirmation is required before freezing the local clone destination plan.', 'seo-geo-migration-bridge' ),
				'',
				array( 'response' => 400 )
			);
		}

		$target_path   = isset( $_POST['local_clone_target_path'] )
			? sanitize_text_field( wp_unslash( $_POST['local_clone_target_path'] ) )
			: '';
		$target_url    = isset( $_POST['local_clone_target_url'] )
			? esc_url_raw( wp_unslash( $_POST['local_clone_target_url'] ) )
			: '';
		$target_prefix = isset( $_POST['local_clone_target_prefix'] )
			? sanitize_text_field( wp_unslash( $_POST['local_clone_target_prefix'] ) )
			: '';

		$result = $this->orchestrator->prepare(
			$job_id,
			$target_path,
			$target_url,
			$target_prefix,
			true
		);
		if ( ! is_array( $result ) ) {
			wp_die(
				esc_html__( 'The local clone destination plan could not be prepared from the verified package.', 'seo-geo-migration-bridge' ),
				'',
				array( 'response' => 400 )
			);
		}

		$redirect = add_query_arg(
			array(
				'seo_geo_clone_local_plan' => (string) ( $result['status'] ?? 'blocked' ),
				'clone_job_id'             => $job_id,
			),
			admin_url( 'tools.php?page=' . AdminOperatorScreen::PAGE_SLUG )
		);

		wp_safe_redirect( $redirect );
		exit;
	}
}
