<?php
/**
 * Administrator download endpoint for the bounded sandbox handoff manifest.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge\Operator;

use SeoGeo\MigrationBridge\Sandbox\SandboxHandoffManifest;

/**
 * Streams one authenticated JSON planning manifest.
 */
final class AdminSandboxHandoffController {
	/**
	 * Admin-post action name.
	 */
	public const ACTION = 'seo_geo_migration_sandbox_handoff';

	/**
	 * Nonce action.
	 */
	public const NONCE_ACTION = 'seo_geo_migration_sandbox_handoff';

	/**
	 * Register the authenticated endpoint.
	 */
	public function boot(): void {
		add_action( 'admin_post_' . self::ACTION, array( $this, 'handle' ) );
	}

	/**
	 * Generate and download the bounded JSON manifest.
	 */
	public function handle(): never {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die(
				esc_html__( 'Administrator capability is required to export the sandbox handoff manifest.', 'seo-geo-migration-bridge' ),
				'',
				array( 'response' => 403 )
			);
		}

		check_admin_referer( self::NONCE_ACTION );

		$manifest = ( new SandboxHandoffManifest() )->build();

		if ( true !== ( $manifest['baseline']['available'] ?? false ) ) {
			wp_die(
				esc_html__( 'A completed SEO/GEO baseline is required before sandbox handoff.', 'seo-geo-migration-bridge' ),
				'',
				array( 'response' => 409 )
			);
		}

		$encoded = wp_json_encode( $manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		if ( ! is_string( $encoded ) ) {
			wp_die(
				esc_html__( 'The sandbox handoff manifest could not be encoded.', 'seo-geo-migration-bridge' ),
				'',
				array( 'response' => 500 )
			);
		}

		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="seo-geo-sandbox-handoff-' . gmdate( 'Ymd-His' ) . '.json"' );
		header( 'X-Content-Type-Options: nosniff' );

		echo $encoded; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Intentional JSON download after wp_json_encode().
		exit;
	}
}
