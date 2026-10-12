<?php
/**
 * Provider-neutral SEO authority workspace assets.
 *
 * @package SeoGeoManager
 */

declare(strict_types=1);

namespace SeoGeo\Manager\Admin;

final class SeoAuthorityWorkspace {
	private const SCREEN = 'toplevel_page_seo-geo-manager';

	public static function register(): void {
		add_action( 'admin_enqueue_scripts', array( self::class, 'enqueue_assets' ) );
	}

	public static function enqueue_assets( string $hook_suffix ): void {
		if ( self::SCREEN !== $hook_suffix ) {
			return;
		}

		wp_enqueue_style(
			'seo-geo-manager-seo-authority-workspace',
			self::asset_url( 'assets/admin/seo-authority-workspace.css' ),
			array( 'seo-geo-manager-content-workspace' ),
			SEO_GEO_MANAGER_VERSION
		);

		wp_enqueue_script(
			'seo-geo-manager-seo-authority-workspace',
			self::asset_url( 'assets/admin/seo-authority-workspace.js' ),
			array( 'seo-geo-manager-content-workspace', 'wp-api-fetch' ),
			SEO_GEO_MANAGER_VERSION,
			true
		);

		wp_add_inline_script(
			'seo-geo-manager-seo-authority-workspace',
			'window.SeoGeoManagerSeoAuthorityWorkspace = ' . wp_json_encode(
				array(
					'contentPath' => '/seo-geo-manager/v1/content',
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
