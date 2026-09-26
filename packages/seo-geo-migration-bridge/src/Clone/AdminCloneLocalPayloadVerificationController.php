<?php
/**
 * Portable Clone local payload-verification administration endpoint.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge\Clone;

use SeoGeo\MigrationBridge\Operator\AdminOperatorScreen;

/**
 * Capability/nonce-gated private payload extraction/checksum batches.
 */
final class AdminCloneLocalPayloadVerificationController {
	public const ACTION       = 'seo_geo_migration_clone_local_payload_verify';
	public const NONCE_ACTION = 'seo_geo_migration_clone_local_payload_verify';

	/**
	 * Local payload-verification service.
	 *
	 * @var LocalClonePayloadVerification
	 */
	private LocalClonePayloadVerification $verification;

	/**
	 * Construct controller.
	 *
	 * @param LocalClonePayloadVerification|null $verification Optional service.
	 */
	public function __construct( ?LocalClonePayloadVerification $verification = null ) {
		$this->verification = $verification ?? new LocalClonePayloadVerification();
	}

	/**
	 * Register authenticated-only endpoint.
	 */
	public function boot(): void {
		add_action( 'admin_post_' . self::ACTION, array( $this, 'advance' ) );
	}

	/**
	 * Advance one bounded private extraction/checksum batch.
	 */
	public function advance(): never {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die(
				esc_html__( 'Administrator capability is required to verify the local clone payload.', 'seo-geo-migration-bridge' ),
				'',
				array( 'response' => 403 )
			);
		}

		$job_id = isset( $_POST['clone_job_id'] )
			? sanitize_text_field( wp_unslash( $_POST['clone_job_id'] ) )
			: '';
		check_admin_referer( self::NONCE_ACTION . ':' . $job_id );

		$existing = $this->verification->snapshot( $job_id );
		if ( ! is_array( $existing ) ) {
			$confirmed = isset( $_POST['local_clone_payload_verify_confirm'] )
				&& '1' === sanitize_text_field( wp_unslash( $_POST['local_clone_payload_verify_confirm'] ) );
			if ( ! $confirmed ) {
				wp_die(
					esc_html__( 'Explicit confirmation is required before extracting the local clone payload into private import workspace storage.', 'seo-geo-migration-bridge' ),
					'',
					array( 'response' => 400 )
				);
			}
		}

		$batch_files = isset( $_POST['payload_batch_files'] )
			? absint( wp_unslash( $_POST['payload_batch_files'] ) )
			: ImportPayloadVerifier::DEFAULT_BATCH_FILES;
		$batch_mb    = isset( $_POST['payload_batch_megabytes'] )
			? absint( wp_unslash( $_POST['payload_batch_megabytes'] ) )
			: 8;

		$result = $this->verification->advance( $job_id, $batch_files, $batch_mb * 1024 * 1024 );
		if ( ! is_array( $result ) ) {
			wp_die(
				esc_html__( 'The local clone payload verifier could not advance from the verified target preflight.', 'seo-geo-migration-bridge' ),
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
					'seo_geo_clone_local_payload_verify' => $status,
					'clone_job_id'                       => $job_id,
				),
				admin_url( 'tools.php?page=' . AdminOperatorScreen::PAGE_SLUG )
			)
		);
		exit;
	}
}
