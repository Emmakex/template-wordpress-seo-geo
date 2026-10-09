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
	/** Active strategic page for the current request. */
	private ?WP_Post $active_post = null;

	/** Active strategic preset for renderer dispatch. */
	private ?string $active_preset = null;

	/** Build the strategic runtime. */
	public function __construct(
		private readonly CorporateHomeRenderer $corporate_home_renderer,
		private readonly ResearchHomeRenderer $research_home_renderer
	) {}

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
		$this->active_post   = null;
		$this->active_preset = null;

		if ( is_admin() || ! is_singular( 'page' ) || ! function_exists( 'seo_geo_theme_active_preset_id' ) ) {
			return $template;
		}

		$preset_id = seo_geo_theme_active_preset_id();
		$post      = get_queried_object();
		if ( ! $post instanceof WP_Post || null === $preset_id ) {
			return $template;
		}

		$strategic_template = null;
		if ( 'corporate' === $preset_id && $this->corporate_home_renderer->supports( $post ) ) {
			$strategic_template = get_template_directory() . '/strategic-templates/corporate-home.php';
		} elseif ( 'research' === $preset_id && $this->research_home_renderer->supports( $post ) ) {
			$strategic_template = get_template_directory() . '/strategic-templates/research-home.php';
		}

		if ( ! is_string( $strategic_template ) || ! is_readable( $strategic_template ) ) {
			return $template;
		}

		$this->active_post   = $post;
		$this->active_preset = $preset_id;

		return $strategic_template;
	}

	/** Whether the current request has been claimed by a strategic renderer. */
	public function is_active(): bool {
		return $this->active_post instanceof WP_Post && null !== $this->active_preset;
	}

	/** Render the current accepted strategic surface. */
	public function render_current(): string {
		if ( ! $this->active_post instanceof WP_Post ) {
			return '';
		}

		if ( 'corporate' === $this->active_preset ) {
			return $this->corporate_home_renderer->render( $this->active_post );
		}

		if ( 'research' === $this->active_preset ) {
			return $this->research_home_renderer->render( $this->active_post );
		}

		return '';
	}

	/**
	 * Replace legacy Corporate presentation layers with the v5 strategic runtime.
	 * Research uses its normal preset stylesheet and therefore never enters this
	 * Corporate-only compatibility path.
	 */
	public function replace_legacy_layout_runtime(): void {
		if ( ! $this->is_active() || 'corporate' !== $this->active_preset ) {
			return;
		}

		wp_dequeue_style( 'seo-geo-theme-preset-corporate-v2' );
		wp_dequeue_style( 'seo-geo-theme-preset-corporate-v3-runtime' );

		$path          = get_stylesheet_directory() . '/assets/css/presets/corporate-v5-runtime.css';
		$closure_path  = get_stylesheet_directory() . '/assets/css/presets/corporate-v5-a3-1.css';
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

		if ( ! $is_bundled && is_readable( $closure_path ) ) {
			$closure_version = function_exists( 'seo_geo_theme_asset_version' ) ? seo_geo_theme_asset_version( $closure_path ) : '0.1.1';
			wp_enqueue_style(
				'seo-geo-theme-preset-corporate-v5-a3-1',
				get_stylesheet_directory_uri() . '/assets/css/presets/corporate-v5-a3-1.css',
				array( 'seo-geo-theme-preset-corporate-v5-runtime' ),
				$closure_version
			);
		}
	}

	/**
	 * Add stable diagnostic/product classes only when a strategic renderer owns
	 * the public layout.
	 *
	 * @param array $classes Existing body classes.
	 * @phpstan-param list<string> $classes
	 * @return list<string>
	 */
	public function body_classes( array $classes ): array {
		if ( ! $this->is_active() ) {
			return array_values( array_unique( $classes ) );
		}

		$classes[] = 'seo-geo-strategic-surface';
		if ( 'corporate' === $this->active_preset ) {
			$classes[] = 'seo-geo-corporate-home-v5';
		} elseif ( 'research' === $this->active_preset ) {
			$classes[] = 'seo-geo-research-home-v1';
		}

		return array_values( array_unique( $classes ) );
	}
}
