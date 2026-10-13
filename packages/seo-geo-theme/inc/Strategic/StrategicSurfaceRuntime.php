<?php
/**
 * Theme-owned strategic surface runtime.
 *
 * @package SeoGeoTheme
 */

declare(strict_types=1);

namespace SeoGeo\Theme\Strategic;

use WP_Post;

/**
 * Select server-rendered strategic surfaces without using Gutenberg as the
 * public master-layout authority.
 */
final class StrategicSurfaceRuntime {
	/** Active strategic page for the current request. */
	private ?WP_Post $active_post = null;

	/**
	 * Create the strategic runtime.
	 *
	 * @param CorporateHomeRenderer $corporate_home_renderer Corporate Home renderer.
	 */
	public function __construct( private readonly CorporateHomeRenderer $corporate_home_renderer ) {}

	/** Register strategic Theme hooks. */
	public function register(): void {
		add_filter( 'template_include', array( $this, 'filter_template' ), 99 );
		add_filter( 'body_class', array( $this, 'body_classes' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'replace_legacy_layout_runtime' ), 30 );
	}

	/**
	 * Select the Theme-owned Corporate Home template when its semantic model is available.
	 *
	 * @param string $template Template selected by WordPress.
	 * @return string Strategic template or the original template.
	 */
	public function filter_template( string $template ): string {
		$this->active_post = null;

		if ( is_admin() || ! is_singular( 'page' ) || ! function_exists( 'seo_geo_theme_active_preset_id' ) || 'corporate' !== seo_geo_theme_active_preset_id() ) {
			return $template;
		}

		$post = get_queried_object();
		if ( ! $post instanceof WP_Post || ! $this->corporate_home_renderer->supports( $post ) ) {
			return $template;
		}

		$strategic_template = get_template_directory() . '/strategic-templates/corporate-home.php';
		if ( ! is_readable( $strategic_template ) ) {
			return $template;
		}

		$this->active_post = $post;
		return $strategic_template;
	}

	/** Return whether a strategic surface owns the current request. */
	public function is_active(): bool {
		return $this->active_post instanceof WP_Post;
	}

	/** Return rendered HTML for the active strategic surface. */
	public function render_current(): string {
		if ( ! $this->active_post instanceof WP_Post ) {
			return '';
		}
		return $this->corporate_home_renderer->render( $this->active_post );
	}

	/**
	 * Replace legacy Corporate presentation layers with the v5 strategic runtime.
	 *
	 * Development installs load the runtime plus bounded closure files.
	 * Installable releases collapse foundation, runtime and closures into one CSS
	 * request and write the bundle marker.
	 */
	public function replace_legacy_layout_runtime(): void {
		if ( ! $this->is_active() ) {
			return;
		}

		wp_dequeue_style( 'seo-geo-theme-preset-corporate-v2' );
		wp_dequeue_style( 'seo-geo-theme-preset-corporate-v3-runtime' );

		$path          = get_stylesheet_directory() . '/assets/css/presets/corporate-v5-runtime.css';
		$a31_path      = get_stylesheet_directory() . '/assets/css/presets/corporate-v5-a3-1.css';
		$a32_path      = get_stylesheet_directory() . '/assets/css/presets/corporate-v5-a3-2.css';
		$bundle_marker = get_stylesheet_directory() . '/assets/css/presets/corporate-v5-bundled.marker';
		if ( ! is_readable( $path ) ) {
			return;
		}

		$is_bundled   = is_readable( $bundle_marker );
		$dependencies = array( 'seo-geo-theme' );
		if ( $is_bundled ) {
			wp_dequeue_style( 'seo-geo-theme' );
			$dependencies = array();
		}

		$version = function_exists( 'seo_geo_theme_asset_version' ) ? seo_geo_theme_asset_version( $path ) : '0.1.2';
		wp_enqueue_style(
			'seo-geo-theme-preset-corporate-v5-runtime',
			get_stylesheet_directory_uri() . '/assets/css/presets/corporate-v5-runtime.css',
			$dependencies,
			$version
		);

		if ( $is_bundled ) {
			return;
		}

		$parent = 'seo-geo-theme-preset-corporate-v5-runtime';
		if ( is_readable( $a31_path ) ) {
			wp_enqueue_style(
				'seo-geo-theme-preset-corporate-v5-a3-1',
				get_stylesheet_directory_uri() . '/assets/css/presets/corporate-v5-a3-1.css',
				array( $parent ),
				function_exists( 'seo_geo_theme_asset_version' ) ? seo_geo_theme_asset_version( $a31_path ) : '0.1.2'
			);
			$parent = 'seo-geo-theme-preset-corporate-v5-a3-1';
		}

		if ( is_readable( $a32_path ) ) {
			wp_enqueue_style(
				'seo-geo-theme-preset-corporate-v5-a3-2',
				get_stylesheet_directory_uri() . '/assets/css/presets/corporate-v5-a3-2.css',
				array( $parent ),
				function_exists( 'seo_geo_theme_asset_version' ) ? seo_geo_theme_asset_version( $a32_path ) : '0.1.2'
			);
		}
	}

	/**
	 * Add body classes for the active Corporate strategic surface.
	 *
	 * @param array $classes Existing body classes.
	 * @phpstan-param list<string> $classes
	 * @return list<string>
	 */
	public function body_classes( array $classes ): array {
		if ( $this->is_active() ) {
			$classes[] = 'seo-geo-strategic-surface';
			$classes[] = 'seo-geo-corporate-home-v5';
			$classes[] = 'seo-geo-corporate-home-a3-2';
		}
		return array_values( array_unique( $classes ) );
	}
}
