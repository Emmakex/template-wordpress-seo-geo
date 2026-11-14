<?php
/**
 * Emmake staging interior-page editorial art; content remains WordPress-owned.
 *
 * @package SeoGeoTheme
 */

declare(strict_types=1);

namespace SeoGeo\Theme\Emmake;

use WP_Post;

/**
 * Strictly scoped first-party decorative banners, with no content mutation.
 */
final class InnerVisualRuntime {
	/**
	 * Nine real pages accepted by the public Chromium inventory.
	 *
	 * @var list<string>
	 */
	private const SLUGS = array(
		'servicios',
		'sobre-nosotros',
		'contacto',
		'nuestras-marcas',
		'blog',
		'blog-transformacion-digital',
		'politica-de-privacidad',
		'trabaja-con-nosotros',
		'asistentes-virtuales-de-voz',
	);

	/** Attach the visual system without changing stored blocks. */
	public static function register(): void {
		add_filter( 'body_class', array( self::class, 'add_body_class' ), 30 );
		add_action( 'wp_enqueue_scripts', array( self::class, 'enqueue_style' ), 30 );
	}

	/**
	 * Identify only the Emmake staging site and approved Corporate page.
	 * Never decorate Home, arbitrary posts, previews or another client Theme.
	 */
	private static function current_slug(): string {
		if ( is_admin() || is_front_page() || ( ! is_singular( 'page' ) && ! is_home() ) ) {
			return '';
		}

		if ( ! function_exists( 'seo_geo_theme_active_preset_id' ) || 'corporate' !== seo_geo_theme_active_preset_id() ) {
			return '';
		}

		$parts = wp_parse_url( home_url( '/' ) );
		if ( ! is_array( $parts ) || 'emmake.com' !== ( $parts['host'] ?? '' ) || '/nuevaweb' !== rtrim( (string) ( $parts['path'] ?? '' ), '/' ) ) {
			return '';
		}

		$queried = get_queried_object();
		$slug    = $queried instanceof WP_Post ? (string) $queried->post_name : '';

		// An assigned WordPress posts index can be is_home() instead of is_page().
		if ( is_home() && '' === $slug ) {
			$slug = 'blog';
		}

		return in_array( $slug, self::SLUGS, true ) ? $slug : '';
	}

	/**
	 * Scope image selection to one public page without altering its content.
	 *
	 * @param array $classes WordPress body classes.
	 * @phpstan-param list<string> $classes
	 * @return array
	 * @phpstan-return list<string>
	 */
	public static function add_body_class( array $classes ): array {
		$slug = self::current_slug();
		if ( '' !== $slug ) {
			$classes[] = 'seo-geo-emmake-inner-visual-' . $slug;
		}

		return $classes;
	}

	/** Load one small first-party stylesheet on the nine approved pages only. */
	public static function enqueue_style(): void {
		if ( '' === self::current_slug() ) {
			return;
		}

		$relative = '/assets/css/presets/emmake-inner-art-v1.css';
		$path     = get_template_directory() . $relative;
		if ( ! is_readable( $path ) ) {
			return;
		}

		$version = function_exists( 'seo_geo_theme_asset_version' )
			? seo_geo_theme_asset_version( $path )
			: '0.1.1';

		wp_enqueue_style(
			'seo-geo-theme-emmake-inner-art-v1',
			get_template_directory_uri() . $relative,
			array( 'seo-geo-theme-preset-corporate-inner' ),
			$version
		);
	}
}
