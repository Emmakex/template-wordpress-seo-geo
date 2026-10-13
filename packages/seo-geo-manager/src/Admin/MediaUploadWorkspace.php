<?php
/**
 * SEO/GEO Manager guarded media-upload admin surface.
 *
 * @package SeoGeoManager
 */

declare(strict_types=1);

namespace SeoGeo\Manager\Admin;

final class MediaUploadWorkspace {
	private const DASHBOARD_HOOK = 'toplevel_page_seo-geo-manager';

	public static function register(): void {
		add_action( 'admin_enqueue_scripts', array( self::class, 'enqueue_assets' ) );
	}

	public static function enqueue_assets( string $hook_suffix ): void {
		if ( self::DASHBOARD_HOOK !== $hook_suffix ) {
			return;
		}

		wp_enqueue_style(
			'seo-geo-manager-media-upload-workspace',
			self::asset_url( 'assets/admin/media-upload-workspace.css' ),
			array( 'seo-geo-manager-theme-media-workspace' ),
			SEO_GEO_MANAGER_VERSION
		);

		wp_enqueue_script(
			'seo-geo-manager-media-upload-workspace',
			self::asset_url( 'assets/admin/media-upload-workspace.js' ),
			array( 'wp-api-fetch', 'seo-geo-manager-theme-media-workspace' ),
			SEO_GEO_MANAGER_VERSION,
			true
		);

		wp_add_inline_script(
			'seo-geo-manager-media-upload-workspace',
			'window.SeoGeoManagerMediaUploadWorkspace = ' . wp_json_encode(
				array(
					'previewPath' => '/seo-geo-manager/v1/media/uploads/preview',
					'applyPath'   => '/seo-geo-manager/v1/media/uploads/apply',
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
