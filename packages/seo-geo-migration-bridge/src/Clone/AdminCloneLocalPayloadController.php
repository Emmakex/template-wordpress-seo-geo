<?php
/**
 * Portable Clone local payload verification administration endpoint.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge\Clone;

use SeoGeo\MigrationBridge\Operator\AdminOperatorScreen;

/**
 * Capability/nonce-gated local-clone payload verification.
 */
final class AdminCloneLocalPayloadController {
	public const ACTION       = 'seo_geo_migration_clone_local_payload';
	public const NONCE_ACTION = 'seo_geo_migration_clone_local_payload';

	/**
	 * Local payload verifier.
	 *
	 * @var LocalClonePayloadVerifier
	 */
	private LocalClonePayloadVerifier $verifier;

	/**
	 * Construct controller.
	 *
	 * @param LocalClonePayloadVerifier|null $verifier Optional local payload verifier.
	 */
	public function __construct( ?LocalClonePayloadVerifier $verifier = null ) {
		$this->verifier = $verifier ?? new LocalClonePayloadVerifier();
	}

	/**
	 * Register authenticated endpoint.
	 */
	public function boot(): void {
		add_action( 'admin_post_' . self::ACTION, array( $this, 'handle' ) );
	}

	/**
	 * Advance one bounded private extraction/checksum batch.
	 */
	public function handle(): never {
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

		$existing = $this->verifier->snapshot( $job_id );
		if ( ! is_array( $existing ) ) {
			$confirmed = isset( $_POST['local_clone_payload_confirm'] )
				&& '1' === sanitize_text_field( wp_unslash( $_POST['local_clone_payload_confirm'] ) );
			if ( ! $confirmed ) {
				wp_die(
					esc_html__( 'Explicit confirmation is required before extracting the verified local clone payload into private job storage.', 'seo-geo-migration-bridge' ),
					'',
					array( 'response' => 400 )
				);
			}
		}

		$batch_files     = isset( $_POST['local_clone_payload_batch_size'] )
			? absint( sanitize_text_field( wp_unslash( $_POST['local_clone_payload_batch_size'] ) ) )
			: ImportPayloadVerifier::DEFAULT_BATCH_FILES;
		$batch_megabytes = isset( $_POST['local_clone_payload_batch_megabytes'] )
			? absint( sanitize_text_field( wp_unslash( $_POST['local_clone_payload_batch_megabytes'] ) ) )
			: 8;
		$batch_bytes     = $batch_megabytes * 1024 * 1024;

		$result = $this->verifier->advance( $job_id, $batch_files, $batch_bytes );
		if ( ! is_array( $result ) ) {
			wp_die(
				esc_html__( 'Local clone payload verification could not be advanced.', 'seo-geo-migration-bridge' ),
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
					'seo_geo_clone_local_payload' => $status,
					'clone_job_id'                => $job_id,
				),
				admin_url( 'tools.php?page=' . AdminOperatorScreen::PAGE_SLUG )
			)
		);
		exit;
	}
}
