<?php
/**
 * Portable Clone local target intake/preflight administration endpoint.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge\Clone;

use SeoGeo\MigrationBridge\Operator\AdminOperatorScreen;

/**
 * Capability/nonce-gated local target intake/preflight.
 */
final class AdminCloneLocalTargetPreflightController {
	public const ACTION       = 'seo_geo_migration_clone_local_target_preflight';
	public const NONCE_ACTION = 'seo_geo_migration_clone_local_target_preflight';

	/**
	 * Target preflight service.
	 *
	 * @var LocalCloneTargetPreflight
	 */
	private LocalCloneTargetPreflight $preflight;

	/**
	 * Construct controller.
	 *
	 * @param LocalCloneTargetPreflight|null $preflight Optional service.
	 */
	public function __construct( ?LocalCloneTargetPreflight $preflight = null ) {
		$this->preflight = $preflight ?? new LocalCloneTargetPreflight();
	}

	/**
	 * Register authenticated-only endpoint.
	 */
	public function boot(): void {
		add_action( 'admin_post_' . self::ACTION, array( $this, 'advance' ) );
	}

	/**
	 * Execute the read-only target intake/preflight.
	 */
	public function advance(): never {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die(
				esc_html__( 'Administrator capability is required to validate the local clone target.', 'seo-geo-migration-bridge' ),
				'',
				array( 'response' => 403 )
			);
		}

		$job_id = isset( $_POST['clone_job_id'] )
			? sanitize_text_field( wp_unslash( $_POST['clone_job_id'] ) )
			: '';
		check_admin_referer( self::NONCE_ACTION . ':' . $job_id );

		$existing = $this->preflight->snapshot( $job_id );
		if ( ! is_array( $existing ) ) {
			$confirmed = isset( $_POST['local_clone_target_preflight_confirm'] )
				&& '1' === sanitize_text_field( wp_unslash( $_POST['local_clone_target_preflight_confirm'] ) );
			if ( ! $confirmed ) {
				wp_die(
					esc_html__( 'Explicit confirmation is required before validating the private local clone package against the isolated target.', 'seo-geo-migration-bridge' ),
					'',
					array( 'response' => 400 )
				);
			}
		}

		$result = $this->preflight->advance( $job_id );
		if ( ! is_array( $result ) ) {
			wp_die(
				esc_html__( 'The local clone target preflight could not be completed from the verified private handoff.', 'seo-geo-migration-bridge' ),
				'',
				array( 'response' => 400 )
			);
		}

		$status = (string) ( $result['status'] ?? 'blocked' );
		if ( ! in_array( $status, array( 'running', 'ready', 'blocked' ), true ) ) {
			$status = 'blocked';
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'seo_geo_clone_local_target_preflight' => $status,
					'clone_job_id'                         => $job_id,
				),
				admin_url( 'tools.php?page=' . AdminOperatorScreen::PAGE_SLUG )
			)
		);
		exit;
	}
}
