<?php
/**
 * Portable Clone import payload verification administration endpoint.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge\Clone;

use SeoGeo\MigrationBridge\Operator\AdminOperatorScreen;

/**
 * Capability/nonce-gated private extraction + full payload verification controller.
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
	 * @param ImportPayloadVerifier|null $verifier Optional verifier.
	 */
	public function __construct( ?ImportPayloadVerifier $verifier = null ) {
		$this->verifier = $verifier ?? new ImportPayloadVerifier();
	}

	/**
	 * Register authenticated action.
	 */
	public function boot(): void {
		add_action( 'admin_post_' . self::ACTION, array( $this, 'handle' ) );
	}

	/**
	 * Advance one bounded extraction or checksum-verification batch.
	 */
	public function handle(): never {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die(
				esc_html__( 'Administrator capability is required for Portable Clone import payload verification.', 'seo-geo-migration-bridge' ),
				'',
				array( 'response' => 403 )
			);
		}

		$job_id = $this->posted_job_id();
		check_admin_referer( self::NONCE_ACTION . ':' . $job_id );

		$batch_files = isset( $_POST['import_payload_batch_size'] )
			? absint( sanitize_text_field( wp_unslash( $_POST['import_payload_batch_size'] ) ) )
			: ImportPayloadVerifier::DEFAULT_BATCH_FILES;
		$batch_megabytes = isset( $_POST['import_payload_batch_megabytes'] )
			? absint( sanitize_text_field( wp_unslash( $_POST['import_payload_batch_megabytes'] ) ) )
			: 8;

		$result = $this->verifier->advance(
			$job_id,
			$batch_files,
			$batch_megabytes * 1024 * 1024
		);
		if ( ! is_array( $result ) ) {
			wp_die(
				esc_html__( 'Portable Clone import payload verification could not be advanced.', 'seo-geo-migration-bridge' ),
				'',
				array( 'response' => 400 )
			);
		}

		$status = match ( $result['status'] ?? null ) {
			'verified' => 'verified',
			'blocked'  => 'blocked',
			default    => 'running',
		};

		$this->redirect_to_operator( $job_id, $status );
	}

	/**
	 * Return bounded posted job identifier.
	 */
	private function posted_job_id(): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Job ID scopes the nonce checked immediately by the calling handler.
		if ( ! isset( $_POST['clone_job_id'] ) ) {
			return '';
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Job ID scopes the nonce checked immediately by the calling handler.
		return sanitize_text_field( wp_unslash( $_POST['clone_job_id'] ) );
	}

	/**
	 * Redirect back to the operator screen.
	 *
	 * @param string $job_id Clone job identifier.
	 * @param string $status Payload verification status.
	 */
	private function redirect_to_operator( string $job_id, string $status ): never {
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
