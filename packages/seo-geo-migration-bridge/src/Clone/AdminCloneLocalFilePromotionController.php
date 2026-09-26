<?php
/**
 * Portable Clone local file-promotion administration endpoint.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge\Clone;

use SeoGeo\MigrationBridge\Operator\AdminOperatorScreen;

/**
 * Capability/nonce-gated local reversible file-promotion controller.
 */
final class AdminCloneLocalFilePromotionController {
	public const ACTION       = 'seo_geo_migration_clone_local_file_promotion';
	public const NONCE_ACTION = 'seo_geo_migration_clone_local_file_promotion';

	/**
	 * Local file promoter.
	 *
	 * @var LocalCloneFilePromoter
	 */
	private LocalCloneFilePromoter $promoter;

	/**
	 * Construct controller.
	 *
	 * @param LocalCloneFilePromoter|null $promoter Optional local promoter.
	 */
	public function __construct( ?LocalCloneFilePromoter $promoter = null ) {
		$this->promoter = $promoter ?? new LocalCloneFilePromoter();
	}

	/**
	 * Register authenticated endpoint.
	 */
	public function boot(): void {
		add_action( 'admin_post_' . self::ACTION, array( $this, 'handle' ) );
	}

	/**
	 * Execute one explicit bounded file-promotion step.
	 */
	public function handle(): never {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die(
				esc_html__( 'Administrator capability is required to promote local clone files.', 'seo-geo-migration-bridge' ),
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

		$step = isset( $_POST['local_clone_file_promotion_step'] )
			? sanitize_key( wp_unslash( $_POST['local_clone_file_promotion_step'] ) )
			: 'prepare';

		$batch_files = isset( $_POST['local_clone_file_promotion_batch_files'] )
			? absint( sanitize_text_field( wp_unslash( $_POST['local_clone_file_promotion_batch_files'] ) ) )
			: ImportFilePromoter::DEFAULT_BATCH_FILES;
		$batch_megabytes = isset( $_POST['local_clone_file_promotion_batch_megabytes'] )
			? absint( sanitize_text_field( wp_unslash( $_POST['local_clone_file_promotion_batch_megabytes'] ) ) )
			: 16;
		$batch_bytes = $batch_megabytes * 1024 * 1024;

		if ( in_array( $step, array( 'promote', 'rollback' ), true ) ) {
			$confirmed = isset( $_POST['local_clone_file_promotion_confirm'] )
				&& '1' === sanitize_text_field( wp_unslash( $_POST['local_clone_file_promotion_confirm'] ) );
			if ( ! $confirmed ) {
				wp_die(
					esc_html__( 'Explicit confirmation is required before swapping or rolling back isolated target file roots.', 'seo-geo-migration-bridge' ),
					'',
					array( 'response' => 400 )
				);
			}
		}

		$result = match ( $step ) {
			'prepare'  => $this->promoter->prepare( $job_id ),
			'copy'     => $this->promoter->advance_candidates( $job_id, $batch_files, $batch_bytes ),
			'promote'  => $this->promoter->promote( $job_id ),
			'verify'   => $this->promoter->advance_verification( $job_id, $batch_files, $batch_bytes ),
			'rollback' => $this->promoter->rollback( $job_id ),
			default    => null,
		};

		if ( ! is_array( $result ) ) {
			wp_die(
				esc_html__( 'Local clone file promotion could not be advanced.', 'seo-geo-migration-bridge' ),
				'',
				array( 'response' => 400 )
			);
		}

		$status = is_string( $result['status'] ?? null ) ? $result['status'] : 'blocked';
		wp_safe_redirect(
			add_query_arg(
				array(
					'seo_geo_clone_local_file_promotion' => sanitize_key( $status ),
					'clone_job_id'                       => $job_id,
				),
				admin_url( 'tools.php?page=' . AdminOperatorScreen::PAGE_SLUG )
			)
		);
		exit;
	}
}
