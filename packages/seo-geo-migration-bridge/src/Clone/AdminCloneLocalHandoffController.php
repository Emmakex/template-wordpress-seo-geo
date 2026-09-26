<?php
/**
 * Portable Clone final local handoff administration endpoint.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge\Clone;

use SeoGeo\MigrationBridge\Operator\AdminOperatorScreen;

/**
 * Capability/nonce-gated final local target verification controller.
 */
final class AdminCloneLocalHandoffController {
	public const ACTION       = 'seo_geo_migration_clone_local_handoff';
	public const NONCE_ACTION = 'seo_geo_migration_clone_local_handoff';

	private LocalCloneHandoffReporter $reporter;

	public function __construct( ?LocalCloneHandoffReporter $reporter = null ) {
		$this->reporter = $reporter ?? new LocalCloneHandoffReporter();
	}

	public function boot(): void {
		add_action( 'admin_post_' . self::ACTION, array( $this, 'handle' ) );
	}

	public function handle(): never {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die(
				esc_html__( 'Administrator capability is required to verify the final local clone handoff.', 'seo-geo-migration-bridge' ),
				'',
				array( 'response' => 403 )
			);
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Job ID scopes nonce checked below.
		$job_id = isset( $_POST['clone_job_id'] )
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Job ID scopes nonce checked below.
			? sanitize_text_field( wp_unslash( $_POST['clone_job_id'] ) )
			: '';
		check_admin_referer( self::NONCE_ACTION . ':' . $job_id );

		$result = $this->reporter->prepare( $job_id );
		$status = is_array( $result ) && 'ready' === ( $result['status'] ?? null ) ? 'ready' : 'blocked';

		wp_safe_redirect(
			add_query_arg(
				array(
					'seo_geo_clone_local_handoff' => $status,
					'clone_job_id'                => $job_id,
				),
				admin_url( 'tools.php?page=' . AdminOperatorScreen::PAGE_SLUG )
			)
		);
		exit;
	}
}
