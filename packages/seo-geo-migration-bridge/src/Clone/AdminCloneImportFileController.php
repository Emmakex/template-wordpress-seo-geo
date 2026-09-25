<?php
/**
 * Portable Clone destination file-staging administration endpoint.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge\Clone;

use SeoGeo\MigrationBridge\Operator\AdminOperatorScreen;

/**
 * Capability/nonce-gated resumable uploads/plugins/themes staging restore.
 */
final class AdminCloneImportFileController {
	public const ACTION       = 'seo_geo_migration_clone_import_file_restore';
	public const NONCE_ACTION = 'seo_geo_migration_clone_import_file_restore';

	/**
	 * File restorer.
	 *
	 * @var ImportFileRestorer
	 */
	private ImportFileRestorer $restorer;

	/**
	 * Construct controller.
	 *
	 * @param ImportFileRestorer|null $restorer Optional restorer.
	 */
	public function __construct( ?ImportFileRestorer $restorer = null ) {
		$this->restorer = $restorer ?? new ImportFileRestorer();
	}

	/**
	 * Register authenticated action.
	 */
	public function boot(): void {
		add_action( 'admin_post_' . self::ACTION, array( $this, 'handle' ) );
	}

	/**
	 * Advance one bounded destination staging batch.
	 */
	public function handle(): never {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die(
				esc_html__( 'Administrator capability is required for Portable Clone file restore.', 'seo-geo-migration-bridge' ),
				'',
				array( 'response' => 403 )
			);
		}

		$job_id = $this->posted_job_id();
		check_admin_referer( self::NONCE_ACTION . ':' . $job_id );

		$batch_files = isset( $_POST['file_restore_batch_files'] )
			? absint( sanitize_text_field( wp_unslash( $_POST['file_restore_batch_files'] ) ) )
			: ImportFileRestorer::DEFAULT_BATCH_FILES;
		$batch_mb    = isset( $_POST['file_restore_batch_mb'] )
			? absint( sanitize_text_field( wp_unslash( $_POST['file_restore_batch_mb'] ) ) )
			: (int) ( ImportFileRestorer::DEFAULT_BATCH_BYTES / 1048576 );
		$batch_bytes = max( 1, $batch_mb ) * 1048576;

		$result = $this->restorer->advance( $job_id, $batch_files, $batch_bytes );
		if ( ! is_array( $result ) ) {
			wp_die(
				esc_html__( 'Portable Clone destination file staging could not be advanced.', 'seo-geo-migration-bridge' ),
				'',
				array( 'response' => 400 )
			);
		}

		$status = match ( $result['status'] ?? null ) {
			'complete' => 'complete',
			'blocked'  => 'blocked',
			default    => 'running',
		};

		$this->redirect_to_operator( $job_id, $status );
	}

	/**
	 * Return posted clone job identifier.
	 */
	private function posted_job_id(): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Job ID scopes the nonce checked immediately by the caller.
		if ( ! isset( $_POST['clone_job_id'] ) ) {
			return '';
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Job ID scopes the nonce checked immediately by the caller.
		return sanitize_text_field( wp_unslash( $_POST['clone_job_id'] ) );
	}

	/**
	 * Redirect back to operator screen.
	 *
	 * @param string $job_id Clone job identifier.
	 * @param string $status Restore status.
	 */
	private function redirect_to_operator( string $job_id, string $status ): never {
		$redirect = add_query_arg(
			array(
				'seo_geo_clone_import_files' => sanitize_key( $status ),
				'clone_job_id'               => $job_id,
			),
			admin_url( 'tools.php?page=' . AdminOperatorScreen::PAGE_SLUG )
		);

		wp_safe_redirect( $redirect );
		exit;
	}
}
