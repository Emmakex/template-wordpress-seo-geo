<?php
/**
 * Administrator-initiated SEO/GEO baseline capture.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge\Operator;

use SeoGeo\MigrationBridge\BaselineSnapshotter;
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
	 * Baseline snapshot service.
	 *
	 * @var BaselineSnapshotter
	 */
	private BaselineSnapshotter $snapshotter;

	/**
	 * Construct the controller.
	 *
	 * @param BaselineSnapshotter $snapshotter Baseline service.
	 */
	public function __construct( BaselineSnapshotter $snapshotter ) {
		$this->snapshotter = $snapshotter;
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

		$existing = $this->snapshotter->latest();
		if ( is_array( $existing ) ) {
			$this->redirect( 'exists' );
		}

		try {
			$snapshot = $this->snapshotter->capture();
			$storage  = $this->snapshotter->persist( $snapshot );
		} catch ( Throwable ) {
			$this->redirect( 'error' );
		}

		if ( ! $storage['saved'] ) {
			$status = 'baseline-exists' === $storage['reason'] ? 'exists' : 'error';
			$this->redirect( $status );
		}

		$this->redirect( 'success' );
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
