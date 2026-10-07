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
 * Selects server-rendered strategic surfaces without using Gutenberg as the
 * public master-layout authority.
 */
final class StrategicSurfaceRuntime {
	/**
	 * Active strategic page for the current request.
	 *
	 * @var WP_Post|null
	 */
	private ?WP_Post $active_post = null;

	/**
	 * Build the strategic runtime.
	 *
	 * @param CorporateHomeRenderer $corporate_home_renderer Corporate Home renderer.
	 */
	public function __construct( private readonly CorporateHomeRenderer $corporate_home_renderer ) {}

	/** Register the strategic-template selector. */
	public function register(): void {
		add_filter( 'template_include', array( $this, 'filter_template' ), 99 );
		add_filter( 'body_class', array( $this, 'body_classes' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'replace_legacy_layout_runtime' ), 30 );
	}

	/**
	 * Replace the normal block-template canvas only for an accepted strategic
	 * surface. Ordinary pages, posts and Gutenberg-managed content are untouched.
	 *
	 * @param string $template WordPress-resolved template path.
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

	/** Whether the current request has been claimed by a strategic renderer. */
	public function is_active(): bool {
		return $this->active_post instanceof WP_Post;
	}

	/** Render the current accepted strategic surface. */
	public function render_current(): string {
		if ( ! $this->active_post instanceof WP_Post ) {
			return '';
		}

		return $this->corporate_home_renderer->render( $this->active_post );
	}

	/**
	 * Replace all legacy Corporate presentation layers with the v5 strategic
	 * runtime. Corporate v5 intentionally does not carry the previous Gutenberg
	 * visual stylesheet: the strategic renderer and its CSS are a clean product
	 * boundary rather than another override layer.
	 *
	 * The installable release prepends the neutral Theme foundation to the v5
	 * runtime and writes a marker. Source installs keep the foundation as a normal
	 * dependency while still avoiding legacy Corporate layout CSS.
	 */
	public function replace_legacy_layout_runtime(): void {
		if ( ! $this->is_active() ) {
			return;
		}

		wp_dequeue_style( 'seo-geo-theme-preset-corporate-v2' );
		wp_dequeue_style( 'seo-geo-theme-preset-corporate-v3-runtime' );

		$path          = get_stylesheet_directory() . '/assets/css/presets/corporate-v5-runtime.css';
		$bundle_marker = get_stylesheet_directory() . '/assets/css/presets/corporate-v5-bundled.marker';
		if ( ! is_readable( $path ) ) {
			return;
		}

		$dependencies = array( 'seo-geo-theme' );
		if ( is_readable( $bundle_marker ) ) {
			wp_dequeue_style( 'seo-geo-theme' );
			$dependencies = array();
		}

		$version = '0.1.1';
		if ( function_exists( 'seo_geo_theme_asset_version' ) ) {
			$version = seo_geo_theme_asset_version( $path );
		}

		wp_enqueue_style(
			'seo-geo-theme-preset-corporate-v5-runtime',
			get_stylesheet_directory_uri() . '/assets/css/presets/corporate-v5-runtime.css',
			$dependencies,
			$version
		);
	}

	/**
	 * Add stable diagnostic/product classes only when v5 owns the public layout.
	 *
	 * @param array $classes Existing body classes.
	 * @phpstan-param list<string> $classes
	 * @return list<string>
	 */
	public function body_classes( array $classes ): array {
		if ( $this->is_active() ) {
			$classes[] = 'seo-geo-strategic-surface';
			$classes[] = 'seo-geo-corporate-home-v5';
		}

		return array_values( array_unique( $classes ) );
	}
}
