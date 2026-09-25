<?php
/**
 * Portable Clone import finalization-preflight administration endpoint.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge\Clone;

use SeoGeo\MigrationBridge\Operator\AdminOperatorScreen;

/**
 * Capability/nonce-gated finalization-preflight controller.
 */
final class AdminCloneImportFinalizeController {
	public const ACTION       = 'seo_geo_migration_clone_import_finalize_preflight';
	public const NONCE_ACTION = 'seo_geo_migration_clone_import_finalize_preflight';

	/**
	 * Finalization planner.
	 *
	 * @var ImportFinalizationPlanner
	 */
	private ImportFinalizationPlanner $planner;

	/**
	 * Construct controller.
	 *
	 * @param ImportFinalizationPlanner|null $planner Optional finalization planner.
	 */
	public function __construct( ?ImportFinalizationPlanner $planner = null ) {
		$this->planner = $planner ?? new ImportFinalizationPlanner();
	}

	/**
	 * Register authenticated endpoint.
	 */
	public function boot(): void {
		add_action( 'admin_post_' . self::ACTION, array( $this, 'handle' ) );
	}

	/**
	 * Advance one bounded read-only finalization-preflight batch.
	 */
	public function handle(): never {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die(
				esc_html__( 'Administrator capability is required for Portable Clone finalization preflight.', 'seo-geo-migration-bridge' ),
				'',
				array( 'response' => 403 )
			);
		}

		$job_id = $this->posted_job_id();
		check_admin_referer( self::NONCE_ACTION . ':' . $job_id );

		$batch_rows = isset( $_POST['finalize_batch_rows'] )
			? absint( sanitize_text_field( wp_unslash( $_POST['finalize_batch_rows'] ) ) )
			: ImportFinalizationPlanner::DEFAULT_BATCH_ROWS;
		$batch_files = isset( $_POST['finalize_batch_files'] )
			? absint( sanitize_text_field( wp_unslash( $_POST['finalize_batch_files'] ) ) )
			: ImportFinalizationPlanner::DEFAULT_BATCH_FILES;
		$batch_megabytes = isset( $_POST['finalize_batch_megabytes'] )
			? absint( sanitize_text_field( wp_unslash( $_POST['finalize_batch_megabytes'] ) ) )
			: 16;

		$result = $this->planner->advance(
			$job_id,
			$batch_rows,
			$batch_files,
			$batch_megabytes * 1024 * 1024
		);
		if ( ! is_array( $result ) ) {
			wp_die(
				esc_html__( 'Portable Clone finalization preflight could not be advanced.', 'seo-geo-migration-bridge' ),
				'',
				array( 'response' => 400 )
			);
		}

		$status   = (string) ( $result['status'] ?? 'running' );
		$redirect = add_query_arg(
			array(
				'seo_geo_clone_finalize_preflight' => sanitize_key( $status ),
				'clone_job_id'                     => $job_id,
			),
			admin_url( 'tools.php?page=' . AdminOperatorScreen::PAGE_SLUG )
		);

		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * Return posted clone job identifier.
	 */
	private function posted_job_id(): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Job ID scopes the nonce checked immediately by the calling handler.
		if ( ! isset( $_POST['clone_job_id'] ) ) {
			return '';
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Job ID scopes the nonce checked immediately by the calling handler.
		return sanitize_text_field( wp_unslash( $_POST['clone_job_id'] ) );
	}
}
