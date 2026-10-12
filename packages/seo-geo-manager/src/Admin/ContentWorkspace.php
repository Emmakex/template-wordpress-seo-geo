<?php
/**
 * Content workspace assets for normal WordPress pages and posts.
 *
 * @package SeoGeoManager
 */

declare(strict_types=1);

namespace SeoGeo\Manager\Admin;

final class ContentWorkspace {
	private const SCREEN = 'toplevel_page_seo-geo-manager';

	public static function register(): void {
		add_action( 'admin_enqueue_scripts', array( self::class, 'enqueue_assets' ) );
	}

	public static function enqueue_assets( string $hook_suffix ): void {
		if ( self::SCREEN !== $hook_suffix ) {
			return;
		}

		wp_enqueue_style(
			'seo-geo-manager-content-workspace',
			self::asset_url( 'assets/admin/content-workspace.css' ),
			array( 'seo-geo-manager-workspace-dashboard' ),
			SEO_GEO_MANAGER_VERSION
		);

		wp_enqueue_script(
			'seo-geo-manager-content-workspace',
			self::asset_url( 'assets/admin/content-workspace.js' ),
			array( 'seo-geo-manager-dashboard', 'wp-api-fetch' ),
			SEO_GEO_MANAGER_VERSION,
			true
		);

		wp_add_inline_script(
			'seo-geo-manager-content-workspace',
			'window.SeoGeoManagerContentWorkspace = ' . wp_json_encode(
				array(
					'contentPath' => '/seo-geo-manager/v1/content',
					'previewPath' => '/seo-geo-manager/v1/changes/preview',
					'applyPath'   => '/seo-geo-manager/v1/changes/apply',
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
