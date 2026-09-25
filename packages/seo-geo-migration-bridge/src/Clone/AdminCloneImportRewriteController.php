<?php
/**
 * Portable Clone environment rewrite admin endpoint.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge\Clone;

use SeoGeo\MigrationBridge\Operator\AdminOperatorScreen;

/**
 * Advances one bounded serialization-safe staging rewrite batch.
 */
final class AdminCloneImportRewriteController {
	public const ACTION       = 'seo_geo_migration_clone_import_environment_rewrite';
	public const NONCE_ACTION = 'seo_geo_migration_clone_import_environment_rewrite';

	/**
	 * Environment rewrite service.
	 *
	 * @var ImportEnvironmentRewriter
	 */
	private ImportEnvironmentRewriter $rewriter;

	/**
	 * Construct controller.
	 *
	 * @param ImportEnvironmentRewriter|null $rewriter Optional staging environment rewriter.
	 */
	public function __construct( ?ImportEnvironmentRewriter $rewriter = null ) {
		$this->rewriter = $rewriter ?? new ImportEnvironmentRewriter();
	}

	/**
	 * Register authenticated rewrite endpoint.
	 */
	public function boot(): void {
		add_action( 'admin_post_' . self::ACTION, array( $this, 'handle' ) );
	}

	/**
	 * Advance one bounded rewrite/verification batch.
	 */
	public function handle(): never {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die(
				esc_html__( 'Administrator capability is required for Portable Clone environment rewrite.', 'seo-geo-migration-bridge' ),
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

		$batch_rows = isset( $_POST['rewrite_batch_rows'] )
			? absint( sanitize_text_field( wp_unslash( $_POST['rewrite_batch_rows'] ) ) )
			: ImportEnvironmentRewriter::DEFAULT_BATCH_ROWS;

		$result = $this->rewriter->advance( $job_id, $batch_rows );
		if ( ! is_array( $result ) ) {
			wp_die(
				esc_html__( 'Portable Clone staging environment rewrite could not be advanced.', 'seo-geo-migration-bridge' ),
				'',
				array( 'response' => 400 )
			);
		}

		$redirect = add_query_arg(
			array(
				'seo_geo_clone_environment_rewrite' => sanitize_key( (string) ( $result['status'] ?? 'running' ) ),
				'clone_job_id'                      => $job_id,
			),
			admin_url( 'tools.php?page=' . AdminOperatorScreen::PAGE_SLUG )
		);

		wp_safe_redirect( $redirect );
		exit;
	}
}
