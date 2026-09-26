<?php
/**
 * Portable Clone Migration Bridge control-runtime administration endpoint.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge\Clone;

use SeoGeo\MigrationBridge\Operator\AdminOperatorScreen;

/**
 * Capability/nonce-gated bounded Bridge control-runtime batches.
 */
final class AdminCloneLocalBridgeController {
	public const ACTION       = 'seo_geo_migration_clone_local_bridge_advance';
	public const NONCE_ACTION = 'seo_geo_migration_clone_local_bridge';

	/**
	 * Resumable Bridge control-runtime service.
	 *
	 * @var LocalCloneBridgeBootstrapper
	 */
	private LocalCloneBridgeBootstrapper $bridge;

	/**
	 * Construct the controller.
	 *
	 * @param LocalCloneBridgeBootstrapper|null $bridge Optional Bridge bootstrapper.
	 */
	public function __construct( ?LocalCloneBridgeBootstrapper $bridge = null ) {
		$this->bridge = $bridge ?? new LocalCloneBridgeBootstrapper();
	}

	/**
	 * Register the authenticated-only endpoint.
	 */
	public function boot(): void {
		add_action( 'admin_post_' . self::ACTION, array( $this, 'advance' ) );
	}

	/**
	 * Advance one bounded Bridge copy/verification request.
	 */
	public function advance(): never {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die(
				esc_html__( 'Administrator capability is required to install the local clone Migration Bridge runtime.', 'seo-geo-migration-bridge' ),
				'',
				array( 'response' => 403 )
			);
		}

		$job_id = isset( $_POST['clone_job_id'] )
			? sanitize_text_field( wp_unslash( $_POST['clone_job_id'] ) )
			: '';
		check_admin_referer( self::NONCE_ACTION . ':' . $job_id );

		$existing = $this->bridge->snapshot( $job_id );
		if ( ! is_array( $existing ) ) {
			$confirmed = isset( $_POST['local_clone_bridge_confirm'] )
				&& '1' === sanitize_text_field( wp_unslash( $_POST['local_clone_bridge_confirm'] ) );
			if ( ! $confirmed ) {
				wp_die(
					esc_html__( 'Explicit confirmation is required before the first Migration Bridge control-runtime copy batch.', 'seo-geo-migration-bridge' ),
					'',
					array( 'response' => 400 )
				);
			}
		}

		$batch_files = isset( $_POST['bridge_batch_files'] )
			? absint( wp_unslash( $_POST['bridge_batch_files'] ) )
			: LocalCloneBridgeBootstrapper::DEFAULT_BATCH_FILES;
		$batch_mb    = isset( $_POST['bridge_batch_megabytes'] )
			? absint( wp_unslash( $_POST['bridge_batch_megabytes'] ) )
			: 4;

		$result = $this->bridge->advance( $job_id, $batch_files, $batch_mb * 1024 * 1024 );
		if ( ! is_array( $result ) ) {
			wp_die(
				esc_html__( 'The Migration Bridge control runtime could not start from the accepted local clone core runtime.', 'seo-geo-migration-bridge' ),
				'',
				array( 'response' => 400 )
			);
		}

		$status = (string) ( $result['status'] ?? 'blocked' );
		if ( ! in_array( $status, array( 'running', 'complete', 'blocked' ), true ) ) {
			$status = 'blocked';
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'seo_geo_clone_local_bridge' => $status,
					'clone_job_id'               => $job_id,
				),
				admin_url( 'tools.php?page=' . AdminOperatorScreen::PAGE_SLUG )
			)
		);
		exit;
	}
}
