<?php
/**
 * Administrator portable clone planning actions.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge\Portable;

use SeoGeo\MigrationBridge\Operator\AdminOperatorScreen;

/**
 * Creates/cancels bounded resumable clone jobs without copying site data yet.
 */
final class AdminPortableCloneController {
	/**
	 * Prepare action name.
	 */
	public const PREPARE_ACTION = 'seo_geo_migration_prepare_portable_clone';

	/**
	 * Cancel action name.
	 */
	public const CANCEL_ACTION = 'seo_geo_migration_cancel_portable_clone';

	/**
	 * Prepare nonce.
	 */
	public const PREPARE_NONCE_ACTION = 'seo_geo_migration_prepare_portable_clone';

	/**
	 * Cancel nonce.
	 */
	public const CANCEL_NONCE_ACTION = 'seo_geo_migration_cancel_portable_clone';

	/**
	 * Clone planner.
	 *
	 * @var PortableClonePlanner
	 */
	private PortableClonePlanner $planner;

	/**
	 * Job store.
	 *
	 * @var PortableCloneJobStore
	 */
	private PortableCloneJobStore $store;

	/**
	 * Construct controller.
	 *
	 * @param PortableClonePlanner|null  $planner Optional planner.
	 * @param PortableCloneJobStore|null $store   Optional job store.
	 */
	public function __construct( ?PortableClonePlanner $planner = null, ?PortableCloneJobStore $store = null ) {
		$this->planner = $planner ?? new PortableClonePlanner();
		$this->store   = $store ?? new PortableCloneJobStore();
	}

	/**
	 * Register authenticated admin-post endpoints.
	 */
	public function boot(): void {
		add_action( 'admin_post_' . self::PREPARE_ACTION, array( $this, 'prepare' ) );
		add_action( 'admin_post_' . self::CANCEL_ACTION, array( $this, 'cancel' ) );
	}

	/**
	 * Prepare one resumable clone job.
	 */
	public function prepare(): never {
		$this->require_admin();
		check_admin_referer( self::PREPARE_NONCE_ACTION );

		$directory = isset( $_POST['target_directory'] )
			? sanitize_text_field( wp_unslash( $_POST['target_directory'] ) )
			: '';
		$confirmed = isset( $_POST['non_production_confirmed'] )
			&& '1' === sanitize_text_field( wp_unslash( $_POST['non_production_confirmed'] ) );

		if ( ! $confirmed ) {
			$this->redirect( 'confirmation-required' );
		}

		$plan = $this->planner->local_subdirectory( $directory );
		if ( true !== ( $plan['ready'] ?? false ) ) {
			$this->redirect( 'blocked' );
		}

		$job = $this->store->start( $plan );
		$this->redirect( null === $job ? 'busy' : 'planned' );
	}

	/**
	 * Cancel the current planning/running job.
	 */
	public function cancel(): never {
		$this->require_admin();
		check_admin_referer( self::CANCEL_NONCE_ACTION );

		$this->redirect( $this->store->cancel() ? 'cancelled' : 'error' );
	}

	/**
	 * Require administrator capability.
	 */
	private function require_admin(): void {
		if ( current_user_can( 'manage_options' ) ) {
			return;
		}

		wp_die(
			esc_html__( 'Administrator capability is required to manage portable clone jobs.', 'seo-geo-migration-bridge' ),
			'',
			array( 'response' => 403 )
		);
	}

	/**
	 * Redirect to the operator screen.
	 *
	 * @param string $status Result status.
	 */
	private function redirect( string $status ): never {
		$url = add_query_arg(
			array(
				'page'                   => AdminOperatorScreen::PAGE_SLUG,
				'seo_geo_portable_clone' => sanitize_key( $status ),
			),
			admin_url( 'tools.php' )
		);

		wp_safe_redirect( $url );
		exit;
	}
}
