<?php
/**
 * Portable Clone Engine planning-job administration endpoint.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge\Clone;

use SeoGeo\MigrationBridge\Operator\AdminOperatorScreen;

/**
 * Creates bounded resumable job state without copying database/files.
 */
final class AdminCloneController {
	/**
	 * Admin-post action.
	 */
	public const ACTION = 'seo_geo_migration_clone_create_job';

	/**
	 * Nonce action.
	 */
	public const NONCE_ACTION = 'seo_geo_migration_clone_create_job';

	/**
	 * Job persistence.
	 *
	 * @var CloneJobStore
	 */
	private CloneJobStore $store;

	/**
	 * Construct the controller.
	 *
	 * @param CloneJobStore|null $store Optional job store.
	 */
	public function __construct( ?CloneJobStore $store = null ) {
		$this->store = $store ?? new CloneJobStore();
	}

	/**
	 * Register authenticated endpoint.
	 */
	public function boot(): void {
		add_action( 'admin_post_' . self::ACTION, array( $this, 'handle' ) );
	}

	/**
	 * Create one planning-only resumable job.
	 */
	public function handle(): never {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die(
				esc_html__( 'Administrator capability is required to create Portable Clone jobs.', 'seo-geo-migration-bridge' ),
				'',
				array( 'response' => 403 )
			);
		}

		check_admin_referer( self::NONCE_ACTION );

		$operation = isset( $_POST['clone_operation'] )
			? sanitize_key( wp_unslash( $_POST['clone_operation'] ) )
			: '';

		if ( ! in_array( $operation, CloneJobStore::allowed_operations(), true ) ) {
			wp_die(
				esc_html__( 'Unsupported Portable Clone operation.', 'seo-geo-migration-bridge' ),
				'',
				array( 'response' => 400 )
			);
		}

		$job = $this->store->create( $operation );
		if ( null === $job ) {
			wp_die(
				esc_html__( 'Portable Clone planning job could not be created.', 'seo-geo-migration-bridge' ),
				'',
				array( 'response' => 500 )
			);
		}

		$redirect = add_query_arg(
			array(
				'seo_geo_clone_job' => 'created',
				'clone_job_id'      => (string) $job['job_id'],
			),
			admin_url( 'tools.php?page=' . AdminOperatorScreen::PAGE_SLUG )
		);

		wp_safe_redirect( $redirect );
		exit;
	}
}
