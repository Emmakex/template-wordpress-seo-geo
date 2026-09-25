<?php
/**
 * Portable Clone import payload extraction/integrity administration endpoint.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge\Clone;

use SeoGeo\MigrationBridge\Operator\AdminOperatorScreen;

/**
 * Advances one bounded private extraction or checksum-verification batch.
 */
final class AdminCloneImportPayloadController {
	public const ACTION       = 'seo_geo_migration_clone_import_payload';
	public const NONCE_ACTION = 'seo_geo_migration_clone_import_payload';

	/**
	 * Payload verifier.
	 *
	 * @var ImportPayloadVerifier
	 */
	private ImportPayloadVerifier $verifier;

	/**
	 * Construct controller.
	 *
	 * @param ImportPayloadVerifier|null $verifier Optional payload verifier.
	 */
	public function __construct( ?ImportPayloadVerifier $verifier = null ) {
		$this->verifier = $verifier ?? new ImportPayloadVerifier();
	}

	/**
	 * Register authenticated endpoint.
	 */
	public function boot(): void {
		add_action( 'admin_post_' . self::ACTION, array( $this, 'handle' ) );
	}

	/**
	 * Advance one bounded extraction/checksum batch.
	 */
	public function handle(): never {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die(
				esc_html__( 'Administrator capability is required to verify Portable Clone import payload.', 'seo-geo-migration-bridge' ),
				'',
				array( 'response' => 403 )
			);
		}

		$job_id = isset( $_POST['clone_job_id'] )
			? sanitize_text_field( wp_unslash( $_POST['clone_job_id'] ) )
			: '';
		check_admin_referer( self::NONCE_ACTION . ':' . $job_id );

		$batch_files = isset( $_POST['import_payload_batch_size'] )
			? absint( sanitize_text_field( wp_unslash( $_POST['import_payload_batch_size'] ) ) )
			: ImportPayloadVerifier::DEFAULT_BATCH_FILES;
		$batch_megabytes = isset( $_POST['import_payload_batch_megabytes'] )
			? absint( sanitize_text_field( wp_unslash( $_POST['import_payload_batch_megabytes'] ) ) )
			: 8;
		$batch_bytes = $batch_megabytes * 1024 * 1024;

		$result = $this->verifier->advance( $job_id, $batch_files, $batch_bytes );
		if ( ! is_array( $result ) ) {
			wp_die(
				esc_html__( 'Portable Clone import payload verification could not be advanced.', 'seo-geo-migration-bridge' ),
				'',
				array( 'response' => 400 )
			);
		}

		$status = (string) ( $result['status'] ?? 'running' );
		$redirect = add_query_arg(
			array(
				'seo_geo_clone_import_payload' => sanitize_key( $status ),
				'clone_job_id'                 => $job_id,
			),
			admin_url( 'tools.php?page=' . AdminOperatorScreen::PAGE_SLUG )
		);

		wp_safe_redirect( $redirect );
		exit;
	}
}
