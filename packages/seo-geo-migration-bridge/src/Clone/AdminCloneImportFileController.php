<?php
/**
 * Portable Clone staging-file restore admin endpoint.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge\Clone;

use SeoGeo\MigrationBridge\Operator\AdminOperatorScreen;

/**
 * Advances one bounded staging-file restore batch.
 */
final class AdminCloneImportFileController {
	public const ACTION       = 'seo_geo_migration_clone_import_file_restore';
	public const NONCE_ACTION = 'seo_geo_migration_clone_import_file_restore';

	/**
	 * Staging-file restore service.
	 *
	 * @var ImportFileRestorer
	 */
	private ImportFileRestorer $restorer;

	/**
	 * Construct the controller.
	 *
	 * @param ImportFileRestorer|null $restorer Optional staging-file restorer.
	 */
	public function __construct( ?ImportFileRestorer $restorer = null ) {
		$this->restorer = $restorer ?? new ImportFileRestorer();
	}

	/**
	 * Register the authenticated staging-file restore endpoint.
	 */
	public function boot(): void {
		add_action( 'admin_post_' . self::ACTION, array( $this, 'handle' ) );
	}

	/**
	 * Advance one bounded staging-file restore batch.
	 */
	public function handle(): never {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die(
				esc_html__( 'Administrator capability is required for Portable Clone file restore.', 'seo-geo-migration-bridge' ),
				'',
				array( 'response' => 403 )
			);
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Job ID scopes the nonce checked immediately below.
		$job_id = isset( $_POST['clone_job_id'] )
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Job ID scopes the nonce checked immediately below.
			? sanitize_text_field( wp_unslash( $_POST['clone_job_id'] ) )
			: '';

		check_admin_referer( self::NONCE_ACTION . ':' . $job_id );

		$batch_files     = isset( $_POST['file_restore_batch_files'] )
			? absint( sanitize_text_field( wp_unslash( $_POST['file_restore_batch_files'] ) ) )
			: ImportFileRestorer::DEFAULT_BATCH_FILES;
		$batch_megabytes = isset( $_POST['file_restore_batch_megabytes'] )
			? absint( sanitize_text_field( wp_unslash( $_POST['file_restore_batch_megabytes'] ) ) )
			: 8;

		$result = $this->restorer->advance( $job_id, $batch_files, $batch_megabytes * 1024 * 1024 );
		if ( ! is_array( $result ) ) {
			wp_die(
				esc_html__( 'Portable Clone staging-file restore could not be advanced.', 'seo-geo-migration-bridge' ),
				'',
				array( 'response' => 400 )
			);
		}

		$redirect = add_query_arg(
			array(
				'seo_geo_clone_file_restore' => sanitize_key( (string) ( $result['status'] ?? 'running' ) ),
				'clone_job_id'               => $job_id,
			),
			admin_url( 'tools.php?page=' . AdminOperatorScreen::PAGE_SLUG )
		);

		wp_safe_redirect( $redirect );
		exit;
	}
}
