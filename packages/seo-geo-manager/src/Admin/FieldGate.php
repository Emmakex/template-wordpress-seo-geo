<?php
/**
 * SEO/GEO Manager field-gate admin surface.
 *
 * @package SeoGeoManager
 */

declare(strict_types=1);

namespace SeoGeo\Manager\Admin;

final class FieldGate {
	private const DASHBOARD_HOOK = 'toplevel_page_seo-geo-manager';

	public static function register(): void {
		add_action( 'admin_enqueue_scripts', array( self::class, 'enqueue_assets' ) );
	}

	public static function enqueue_assets( string $hook_suffix ): void {
		if ( self::DASHBOARD_HOOK !== $hook_suffix || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		wp_enqueue_script(
			'seo-geo-manager-field-gate',
			self::asset_url( 'assets/admin/field-gate.js' ),
			array( 'wp-api-fetch' ),
			SEO_GEO_MANAGER_VERSION,
			true
		);

		wp_enqueue_script(
			'seo-geo-manager-finalization-actions',
			self::asset_url( 'assets/admin/finalization-actions.js' ),
			array( 'seo-geo-manager-field-gate' ),
			SEO_GEO_MANAGER_VERSION,
			true
		);
	}

	private static function asset_url( string $relative_path ): string {
		$plugin_file = dirname( __DIR__, 2 ) . '/seo-geo-manager.php';

		return plugins_url( ltrim( $relative_path, '/' ), $plugin_file );
	}
}
