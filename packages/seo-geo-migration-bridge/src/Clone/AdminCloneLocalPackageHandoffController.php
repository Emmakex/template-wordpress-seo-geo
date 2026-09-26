<?php
/**
 * Portable Clone private local package handoff administration endpoint.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge\Clone;

use SeoGeo\MigrationBridge\Operator\AdminOperatorScreen;

/**
 * Capability/nonce-gated same-server package handoff batches.
 */
final class AdminCloneLocalPackageHandoffController {
	public const ACTION       = 'seo_geo_migration_clone_local_package_handoff_advance';
	public const NONCE_ACTION = 'seo_geo_migration_clone_local_package_handoff';

	/**
	 * Private local package handoff service.
	 *
	 * @var LocalClonePackageHandoff
	 */
	private LocalClonePackageHandoff $handoff;

	/**
	 * Construct controller.
	 *
	 * @param LocalClonePackageHandoff|null $handoff Optional handoff service.
	 */
	public function __construct( ?LocalClonePackageHandoff $handoff = null ) {
		$this->handoff = $handoff ?? new LocalClonePackageHandoff();
	}

	/**
	 * Register authenticated-only endpoint.
	 */
	public function boot(): void {
		add_action( 'admin_post_' . self::ACTION, array( $this, 'advance' ) );
	}

	/**
	 * Advance one bounded private archive handoff batch.
	 */
	public function advance(): never {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die(
				esc_html__( 'Administrator capability is required to prepare the local clone package handoff.', 'seo-geo-migration-bridge' ),
				'',
				array( 'response' => 403 )
			);
		}

		$job_id = isset( $_POST['clone_job_id'] )
			? sanitize_text_field( wp_unslash( $_POST['clone_job_id'] ) )
			: '';
		check_admin_referer( self::NONCE_ACTION . ':' . $job_id );

		$existing = $this->handoff->snapshot( $job_id );
		if ( ! is_array( $existing ) ) {
			$confirmed = isset( $_POST['local_clone_package_handoff_confirm'] )
				&& '1' === sanitize_text_field( wp_unslash( $_POST['local_clone_package_handoff_confirm'] ) );
			if ( ! $confirmed ) {
				wp_die(
					esc_html__( 'Explicit confirmation is required before building the private same-server handoff archive.', 'seo-geo-migration-bridge' ),
					'',
					array( 'response' => 400 )
				);
			}
		}

		$batch_files = isset( $_POST['handoff_batch_files'] )
			? absint( wp_unslash( $_POST['handoff_batch_files'] ) )
			: LocalClonePackageHandoff::DEFAULT_BATCH_FILES;
		$batch_mb    = isset( $_POST['handoff_batch_megabytes'] )
			? absint( wp_unslash( $_POST['handoff_batch_megabytes'] ) )
			: 16;

		$result = $this->handoff->advance( $job_id, $batch_files, $batch_mb * 1024 * 1024 );
		if ( ! is_array( $result ) ) {
			wp_die(
				esc_html__( 'The private local clone package handoff could not be started from the verified sandbox runtime.', 'seo-geo-migration-bridge' ),
				'',
				array( 'response' => 400 )
			);
		}

		$status = (string) ( $result['status'] ?? 'blocked' );
		if ( ! in_array( $status, array( 'building', 'ready', 'blocked' ), true ) ) {
			$status = 'blocked';
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'seo_geo_clone_local_package_handoff' => $status,
					'clone_job_id'                        => $job_id,
				),
				admin_url( 'tools.php?page=' . AdminOperatorScreen::PAGE_SLUG )
			)
		);
		exit;
	}
}
