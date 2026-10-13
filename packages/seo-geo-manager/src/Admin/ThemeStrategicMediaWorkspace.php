<?php
/**
 * SEO/GEO Manager Theme strategic-media admin surface.
 *
 * @package SeoGeoManager
 */

declare(strict_types=1);

namespace SeoGeo\Manager\Admin;

final class ThemeStrategicMediaWorkspace {
	private const DASHBOARD_HOOK = 'toplevel_page_seo-geo-manager';

	public static function register(): void {
		add_action( 'admin_enqueue_scripts', array( self::class, 'enqueue_assets' ) );
	}

	public static function enqueue_assets( string $hook_suffix ): void {
		if ( self::DASHBOARD_HOOK !== $hook_suffix ) {
			return;
		}

		wp_enqueue_style(
			'seo-geo-manager-theme-media-workspace',
			self::asset_url( 'assets/admin/theme-strategic-media-workspace.css' ),
			array( 'seo-geo-manager-dashboard' ),
			SEO_GEO_MANAGER_VERSION
		);

		wp_enqueue_script(
			'seo-geo-manager-theme-media-workspace',
			self::asset_url( 'assets/admin/theme-strategic-media-workspace.js' ),
			array( 'wp-api-fetch', 'seo-geo-manager-build-workspace' ),
			SEO_GEO_MANAGER_VERSION,
			true
		);

		wp_add_inline_script(
			'seo-geo-manager-theme-media-workspace',
			'window.SeoGeoManagerThemeMediaWorkspace = ' . wp_json_encode(
				array(
					'catalogPath' => '/seo-geo-manager/v1/theme/media-bindings',
					'bindingBase' => '/seo-geo-manager/v1/theme/media-bindings',
					'mediaPath'   => '/seo-geo-manager/v1/media',
					'version'     => SEO_GEO_MANAGER_VERSION,
				)
			) . ';',
			'before'
		);
	}

	private static function asset_url( string $relative_path ): string {
		$plugin_file = dirname( __DIR__, 2 ) . '/seo-geo-manager.php';

		return plugins_url( ltrim( $relative_path, '/' ), $plugin_file );
	}
}
