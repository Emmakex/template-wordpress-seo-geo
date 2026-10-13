<?php
/**
 * SEO/GEO Manager controlled publication lifecycle admin surface.
 *
 * @package SeoGeoManager
 */

declare(strict_types=1);

namespace SeoGeo\Manager\Admin;

final class PublicationWorkspace {
	private const DASHBOARD_HOOK = 'toplevel_page_seo-geo-manager';

	public static function register(): void {
		add_action( 'admin_enqueue_scripts', array( self::class, 'enqueue_assets' ) );
	}

	public static function enqueue_assets( string $hook_suffix ): void {
		if ( self::DASHBOARD_HOOK !== $hook_suffix ) {
			return;
		}

		wp_enqueue_style(
			'seo-geo-manager-publication-workspace',
			self::asset_url( 'assets/admin/publication-workspace.css' ),
			array( 'seo-geo-manager-content-workspace' ),
			SEO_GEO_MANAGER_VERSION
		);

		wp_enqueue_script(
			'seo-geo-manager-publication-workspace',
			self::asset_url( 'assets/admin/publication-workspace.js' ),
			array( 'wp-api-fetch', 'seo-geo-manager-content-workspace' ),
			SEO_GEO_MANAGER_VERSION,
			true
		);

		wp_add_inline_script(
			'seo-geo-manager-publication-workspace',
			'window.SeoGeoManagerPublicationWorkspace = ' . wp_json_encode(
				array(
					'contentPath' => '/seo-geo-manager/v1/content',
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
