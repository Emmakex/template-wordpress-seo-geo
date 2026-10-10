<?php
/**
 * Diagnostic JSON copy/download actions for the Manager dashboard.
 *
 * @package SeoGeoManager
 */

declare(strict_types=1);

namespace SeoGeo\Manager\Admin;

final class JsonExportActions {
	/**
	 * Register dashboard-only assets.
	 */
	public static function register(): void {
		add_action( 'admin_enqueue_scripts', array( self::class, 'enqueue_assets' ) );
	}

	/**
	 * Load JSON actions only on the SEO/GEO Manager screen.
	 */
	public static function enqueue_assets( string $hook_suffix ): void {
		if ( 'toplevel_page_seo-geo-manager' !== $hook_suffix ) {
			return;
		}

		wp_enqueue_style(
			'seo-geo-manager-json-export-actions',
			self::asset_url( 'assets/admin/json-export-actions.css' ),
			array( 'seo-geo-manager-dashboard' ),
			SEO_GEO_MANAGER_VERSION
		);

		wp_enqueue_script(
			'seo-geo-manager-json-export-actions',
			self::asset_url( 'assets/admin/json-export-actions.js' ),
			array( 'seo-geo-manager-dashboard' ),
			SEO_GEO_MANAGER_VERSION,
			true
		);
	}

	/**
	 * Build a plugin-relative asset URL.
	 */
	private static function asset_url( string $relative_path ): string {
		$plugin_file = dirname( __DIR__, 2 ) . '/seo-geo-manager.php';

		return plugins_url( ltrim( $relative_path, '/' ), $plugin_file );
	}
}
