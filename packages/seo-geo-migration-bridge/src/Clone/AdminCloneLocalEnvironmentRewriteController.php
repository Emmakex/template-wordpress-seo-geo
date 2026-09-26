<?php
/**
 * Portable Clone local environment rewrite administration endpoint.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge\Clone;

use SeoGeo\MigrationBridge\Operator\AdminOperatorScreen;

/**
 * Capability/nonce-gated local-clone staging rewrite controller.
 */
final class AdminCloneLocalEnvironmentRewriteController {
	public const ACTION       = 'seo_geo_migration_clone_local_environment_rewrite';
	public const NONCE_ACTION = 'seo_geo_migration_clone_local_environment_rewrite';

	/**
	 * Local environment rewriter.
	 *
	 * @var LocalCloneEnvironmentRewriter
	 */
	private LocalCloneEnvironmentRewriter $rewriter;

	/**
	 * Construct controller.
	 *
	 * @param LocalCloneEnvironmentRewriter|null $rewriter Optional local environment rewriter.
	 */
	public function __construct( ?LocalCloneEnvironmentRewriter $rewriter = null ) {
		$this->rewriter = $rewriter ?? new LocalCloneEnvironmentRewriter();
	}

	/**
	 * Register authenticated endpoint.
	 */
	public function boot(): void {
		add_action( 'admin_post_' . self::ACTION, array( $this, 'handle' ) );
	}

	/**
	 * Advance one bounded staging rewrite/verification batch.
	 */
	public function handle(): never {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die(
				esc_html__( 'Administrator capability is required to rewrite the local clone staging environment.', 'seo-geo-migration-bridge' ),
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

		$existing = $this->rewriter->snapshot( $job_id );
		if ( ! is_array( $existing ) ) {
			$confirmed = isset( $_POST['local_clone_rewrite_confirm'] )
				&& '1' === sanitize_text_field( wp_unslash( $_POST['local_clone_rewrite_confirm'] ) );
			if ( ! $confirmed ) {
				wp_die(
					esc_html__( 'Explicit confirmation is required before rewriting environment values inside isolated staging tables.', 'seo-geo-migration-bridge' ),
					'',
					array( 'response' => 400 )
				);
			}
		}

		$batch_rows = isset( $_POST['local_clone_rewrite_batch_rows'] )
			? absint( sanitize_text_field( wp_unslash( $_POST['local_clone_rewrite_batch_rows'] ) ) )
			: ImportEnvironmentRewriter::DEFAULT_BATCH_ROWS;

		$result = $this->rewriter->advance( $job_id, $batch_rows );
		if ( ! is_array( $result ) ) {
			wp_die(
				esc_html__( 'Local clone staging environment rewrite could not be advanced.', 'seo-geo-migration-bridge' ),
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
					'seo_geo_clone_local_rewrite' => $status,
					'clone_job_id'                => $job_id,
				),
				admin_url( 'tools.php?page=' . AdminOperatorScreen::PAGE_SLUG )
			)
		);
		exit;
	}
}
