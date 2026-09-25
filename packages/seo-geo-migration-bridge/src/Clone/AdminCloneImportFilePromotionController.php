<?php
/**
 * Portable Clone reversible file-promotion administration endpoint.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge\Clone;

use SeoGeo\MigrationBridge\Operator\AdminOperatorScreen;

/**
 * Capability/nonce/explicit-confirmation gated file-promotion controller.
 */
final class AdminCloneImportFilePromotionController {
	public const ACTION       = 'seo_geo_migration_clone_import_file_promotion';
	public const NONCE_ACTION = 'seo_geo_migration_clone_import_file_promotion';

	/**
	 * Reversible file promoter.
	 *
	 * @var ImportFilePromoter
	 */
	private ImportFilePromoter $promoter;

	/**
	 * Construct the authenticated file-promotion controller.
	 *
	 * @param ImportFilePromoter|null $promoter Optional promoter.
	 */
	public function __construct( ?ImportFilePromoter $promoter = null ) {
		$this->promoter = $promoter ?? new ImportFilePromoter();
	}

	/**
	 * Register the authenticated admin-post endpoint.
	 */
	public function boot(): void {
		add_action( 'admin_post_' . self::ACTION, array( $this, 'handle' ) );
	}

	/**
	 * Handle one prepare, copy, promote, verify or rollback action.
	 */
	public function handle(): never {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die(
				esc_html__( 'Administrator capability is required for Portable Clone file promotion.', 'seo-geo-migration-bridge' ),
				'',
				array( 'response' => 403 )
			);
		}

		$job_id = $this->posted_job_id();
		check_admin_referer( self::NONCE_ACTION . ':' . $job_id );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is verified immediately above.
		$step = isset( $_POST['file_promotion_step'] )
			? sanitize_key( wp_unslash( $_POST['file_promotion_step'] ) )
			: '';

		$batch_files = $this->posted_int( 'file_promotion_batch_files', ImportFilePromoter::DEFAULT_BATCH_FILES );
		$megabytes   = $this->posted_int( 'file_promotion_batch_megabytes', 16 );
		$batch_bytes = max(
			ImportFilePromoter::MIN_BATCH_BYTES,
			min( ImportFilePromoter::MAX_BATCH_BYTES, $megabytes * 1048576 )
		);

		if ( 'prepare' === $step ) {
			$result = $this->promoter->prepare( $job_id );
		} elseif ( 'copy' === $step ) {
			$result = $this->promoter->advance_candidates( $job_id, $batch_files, $batch_bytes );
		} elseif ( 'promote' === $step ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is verified above; explicit confirmation is a second destructive-action guard.
			$confirmation = isset( $_POST['file_promotion_confirmation'] )
				? sanitize_text_field( wp_unslash( $_POST['file_promotion_confirmation'] ) )
				: '';
			$result       = 'PROMOTE_FILES' === $confirmation
				? $this->promoter->promote( $job_id )
				: null;
		} elseif ( 'verify' === $step ) {
			$result = $this->promoter->advance_verification( $job_id, $batch_files, $batch_bytes );
		} elseif ( 'rollback' === $step ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is verified above; explicit confirmation is a second destructive-action guard.
			$confirmation = isset( $_POST['file_promotion_confirmation'] )
				? sanitize_text_field( wp_unslash( $_POST['file_promotion_confirmation'] ) )
				: '';
			$result       = 'ROLLBACK_FILES' === $confirmation
				? $this->promoter->rollback( $job_id )
				: null;
		} else {
			$result = null;
		}

		if ( ! is_array( $result ) ) {
			wp_die(
				esc_html__( 'Portable Clone file promotion request was rejected.', 'seo-geo-migration-bridge' ),
				'',
				array( 'response' => 400 )
			);
		}

		$redirect = add_query_arg(
			array(
				'seo_geo_clone_file_promotion' => sanitize_key( (string) ( $result['status'] ?? 'unknown' ) ),
				'clone_job_id'                 => $job_id,
			),
			admin_url( 'tools.php?page=' . AdminOperatorScreen::PAGE_SLUG )
		);

		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * Return one bounded posted integer.
	 *
	 * @param string $key     POST key.
	 * @param int    $default Default value.
	 */
	private function posted_int( string $key, int $default ): int {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is verified by the calling handler.
		if ( ! isset( $_POST[ $key ] ) ) {
			return $default;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is verified by the calling handler.
		return max( 1, absint( wp_unslash( $_POST[ $key ] ) ) );
	}

	/**
	 * Return the posted clone job identifier.
	 */
	private function posted_job_id(): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Job ID scopes the nonce checked by the calling handler.
		if ( ! isset( $_POST['clone_job_id'] ) ) {
			return '';
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Job ID scopes the nonce checked by the calling handler.
		return sanitize_text_field( wp_unslash( $_POST['clone_job_id'] ) );
	}
}
