<?php
/**
 * Administrator-initiated SEO/GEO baseline capture.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge\Operator;

use SeoGeo\MigrationBridge\IncrementalBaselineCapture;
use Throwable;

/**
 * Handles one explicit, capability-gated public baseline capture.
 */
final class AdminBaselineCaptureController {
	/**
	 * Admin-post action name.
	 */
	public const ACTION = 'seo_geo_migration_capture_baseline';

	/**
	 * Nonce action.
	 */
	public const NONCE_ACTION = 'seo_geo_migration_capture_baseline';

	/**
	 * Incremental baseline capture service.
	 *
	 * @var IncrementalBaselineCapture
	 */
	private IncrementalBaselineCapture $capture;

	/**
	 * Construct the controller.
	 *
	 * @param IncrementalBaselineCapture $capture Incremental capture service.
	 */
	public function __construct( IncrementalBaselineCapture $capture ) {
		$this->capture = $capture;
	}

	/**
	 * Register the authenticated admin-post endpoint.
	 */
	public function boot(): void {
		add_action( 'admin_post_' . self::ACTION, array( $this, 'handle' ) );
	}

	/**
	 * Capture and persist one same-origin public baseline.
	 */
	public function handle(): never {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die(
				esc_html__( 'Administrator capability is required to capture the SEO/GEO baseline.', 'seo-geo-migration-bridge' ),
				'',
				array( 'response' => 403 )
			);
		}

		check_admin_referer( self::NONCE_ACTION );

		try {
			$result = $this->capture->advance();
		} catch ( Throwable ) {
			$this->redirect( 'error' );
		}

		$status = $result['status'];

		if ( 'complete' === $status ) {
			$this->redirect( 'success' );
		}

		if ( 'error' === $status ) {
			$this->redirect( 'error' );
		}

		$this->redirect( 'progress' );
	}

	/**
	 * Redirect back to the bounded operator screen.
	 *
	 * @param string $status Result key.
	 */
	private function redirect( string $status ): never {
		$url = add_query_arg(
			array(
				'page'             => AdminOperatorScreen::PAGE_SLUG,
				'seo_geo_baseline' => sanitize_key( $status ),
			),
			admin_url( 'tools.php' )
		);

		wp_safe_redirect( $url );
		exit;
	}
}
