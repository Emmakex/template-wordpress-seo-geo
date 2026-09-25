<?php
/**
 * Portable Clone Engine source-inventory administration endpoint.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge\Clone;

use SeoGeo\MigrationBridge\Operator\AdminOperatorScreen;

/**
 * Advances one bounded read-only inventory batch.
 */
final class AdminCloneInventoryController {
	/**
	 * Admin-post action.
	 */
	public const ACTION = 'seo_geo_migration_clone_inventory';

	/**
	 * Nonce action prefix.
	 */
	public const NONCE_ACTION = 'seo_geo_migration_clone_inventory';

	/**
	 * Inventory service.
	 *
	 * @var CloneInventory
	 */
	private CloneInventory $inventory;

	/**
	 * Construct controller.
	 *
	 * @param CloneInventory|null $inventory Optional inventory service.
	 */
	public function __construct( ?CloneInventory $inventory = null ) {
		$this->inventory = $inventory ?? new CloneInventory();
	}

	/**
	 * Register authenticated endpoint.
	 */
	public function boot(): void {
		add_action( 'admin_post_' . self::ACTION, array( $this, 'handle' ) );
	}

	/**
	 * Advance one inventory batch.
	 */
	public function handle(): never {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die(
				esc_html__( 'Administrator capability is required to inventory Portable Clone source data.', 'seo-geo-migration-bridge' ),
				'',
				array( 'response' => 403 )
			);
		}

		$job_id = isset( $_POST['clone_job_id'] )
			? sanitize_text_field( wp_unslash( $_POST['clone_job_id'] ) )
			: '';

		check_admin_referer( self::NONCE_ACTION . ':' . $job_id );

		$batch_size = isset( $_POST['inventory_batch_size'] )
			? (int) wp_unslash( $_POST['inventory_batch_size'] )
			: CloneInventory::DEFAULT_BATCH_SIZE;

		$result = $this->inventory->advance( $job_id, $batch_size );
		if ( ! is_array( $result ) ) {
			wp_die(
				esc_html__( 'Portable Clone source inventory could not be advanced.', 'seo-geo-migration-bridge' ),
				'',
				array( 'response' => 400 )
			);
		}

		$redirect = add_query_arg(
			array(
				'seo_geo_clone_inventory' => (string) ( $result['status'] ?? 'running' ),
				'clone_job_id'            => $job_id,
			),
			admin_url( 'tools.php?page=' . AdminOperatorScreen::PAGE_SLUG )
		);

		wp_safe_redirect( $redirect );
		exit;
	}
}
