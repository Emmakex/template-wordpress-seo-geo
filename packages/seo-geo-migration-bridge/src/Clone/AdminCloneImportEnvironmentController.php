<?php
/**
 * Portable Clone environment rewrite administration endpoint.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge\Clone;

use SeoGeo\MigrationBridge\Operator\AdminOperatorScreen;

/**
 * Capability/nonce-gated bounded staging environment rewrite controller.
 */
final class AdminCloneImportEnvironmentController {
	public const ACTION       = 'seo_geo_migration_clone_import_environment';
	public const NONCE_ACTION = 'seo_geo_migration_clone_import_environment';

	/**
	 * Staging environment rewrite service.
	 *
	 * @var ImportEnvironmentRewriter
	 */
	private ImportEnvironmentRewriter $rewriter;

	/**
	 * Construct controller.
	 *
	 * @param ImportEnvironmentRewriter|null $rewriter Optional environment rewriter.
	 */
	public function __construct( ?ImportEnvironmentRewriter $rewriter = null ) {
		$this->rewriter = $rewriter ?? new ImportEnvironmentRewriter();
	}

	/**
	 * Register authenticated action.
	 */
	public function boot(): void {
		add_action( 'admin_post_' . self::ACTION, array( $this, 'handle' ) );
	}

	/**
	 * Advance one bounded staging-only environment rewrite batch.
	 */
	public function handle(): never {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die(
				esc_html__( 'Administrator capability is required for Portable Clone environment rewrite.', 'seo-geo-migration-bridge' ),
				'',
				array( 'response' => 403 )
			);
		}

		$job_id = $this->posted_job_id();
		check_admin_referer( self::NONCE_ACTION . ':' . $job_id );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Job-scoped nonce verified immediately above.
		$batch_rows = isset( $_POST['environment_rewrite_batch_rows'] )
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Job-scoped nonce verified immediately above.
			? absint( wp_unslash( $_POST['environment_rewrite_batch_rows'] ) )
			: ImportEnvironmentRewriter::DEFAULT_BATCH_ROWS;

		$result = $this->rewriter->advance( $job_id, $batch_rows );

		if ( ! is_array( $result ) ) {
			wp_die(
				esc_html__( 'Portable Clone environment rewrite could not be advanced.', 'seo-geo-migration-bridge' ),
				'',
				array( 'response' => 400 )
			);
		}

		$status = (string) ( $result['status'] ?? 'running' );
		$this->redirect_to_operator( $job_id, $status );
	}

	/**
	 * Return bounded posted clone job identifier.
	 */
	private function posted_job_id(): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Job ID scopes the nonce checked immediately by the handler.
		if ( ! isset( $_POST['clone_job_id'] ) ) {
			return '';
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Job ID scopes the nonce checked immediately by the handler.
		return sanitize_text_field( wp_unslash( $_POST['clone_job_id'] ) );
	}

	/**
	 * Redirect to operator screen.
	 *
	 * @param string $job_id Clone job identifier.
	 * @param string $status Rewrite status.
	 */
	private function redirect_to_operator( string $job_id, string $status ): never {
		$redirect = add_query_arg(
			array(
				'seo_geo_clone_environment' => sanitize_key( $status ),
				'clone_job_id'              => $job_id,
			),
			admin_url( 'tools.php?page=' . AdminOperatorScreen::PAGE_SLUG )
		);

		wp_safe_redirect( $redirect );
		exit;
	}
}
