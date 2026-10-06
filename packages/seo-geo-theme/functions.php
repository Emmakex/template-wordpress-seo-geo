<?php
/**
 * Theme bootstrap.
 *
 * @package SeoGeoTheme
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once get_template_directory() . '/inc/seo-geo-core/bootstrap.php';
require_once get_template_directory() . '/inc/presets.php';
require_once get_template_directory() . '/inc/setup.php';
require_once get_template_directory() . '/inc/Forms/ContactFormRuntime.php';
require_once get_template_directory() . '/inc/Insights/InsightsIndexRuntime.php';
require_once get_template_directory() . '/inc/Navigation/PresetNavigationRuntime.php';

/**
 * Load project-owned translations.
 */
function seo_geo_theme_setup(): void {
	load_theme_textdomain( 'seo-geo-theme', get_template_directory() . '/languages' );
}
add_action( 'after_setup_theme', 'seo_geo_theme_setup' );

/**
 * Load the neutral Theme foundation and the active preset's visual system.
 *
 * Each preset owns its own stylesheet. This keeps Corporate, Local Business,
 * Publisher, Ecommerce and SaaS/Digital Product visually isolated while they
 * continue to share the same Theme/Core runtime.
 */
function seo_geo_theme_enqueue_styles(): void {
	$stylesheet = get_stylesheet_directory() . '/style.css';
	$version    = is_readable( $stylesheet ) ? (string) filemtime( $stylesheet ) : '0.1.0';

	wp_enqueue_style(
		'seo-geo-theme',
		get_stylesheet_uri(),
		array(),
		$version
	);

	$preset_id = seo_geo_theme_active_preset_id();
	if ( null === $preset_id ) {
		return;
	}

	$preset_stylesheet = get_stylesheet_directory() . '/assets/css/presets/' . $preset_id . '.css';
	if ( ! is_readable( $preset_stylesheet ) ) {
		return;
	}

	$preset_handle = 'seo-geo-theme-preset-' . $preset_id;
	wp_enqueue_style(
		$preset_handle,
		get_stylesheet_directory_uri() . '/assets/css/presets/' . $preset_id . '.css',
		array( 'seo-geo-theme' ),
		(string) filemtime( $preset_stylesheet )
	);

	if ( 'corporate' === $preset_id ) {
		$visual_stylesheet = get_stylesheet_directory() . '/assets/css/presets/corporate-v2.css';
		if ( is_readable( $visual_stylesheet ) ) {
			wp_enqueue_style(
				'seo-geo-theme-preset-corporate-v2',
				get_stylesheet_directory_uri() . '/assets/css/presets/corporate-v2.css',
				array( $preset_handle ),
				(string) filemtime( $visual_stylesheet )
			);
		}
	}
}
add_action( 'wp_enqueue_scripts', 'seo_geo_theme_enqueue_styles' );

/**
 * Expose the active allowlisted preset as a stable frontend body class.
 *
 * @param array $classes Existing body classes.
 * @phpstan-param list<string> $classes
 * @return list<string>
 */
function seo_geo_theme_preset_body_class( array $classes ): array {
	$preset_id = seo_geo_theme_active_preset_id();
	if ( null !== $preset_id ) {
		$classes[] = 'seo-geo-preset-' . $preset_id;
	}

	return array_values( array_unique( $classes ) );
}
add_filter( 'body_class', 'seo_geo_theme_preset_body_class' );

/**
 * Make the page-level main landmark a programmatic focus target.
 *
 * WordPress injects a skip link for block templates and assigns its fragment ID
 * to the first main landmark. A fragment target must also be focusable so
 * keyboard users land in the content reading order after activating that link.
 * The negative tabindex enables programmatic/fragment focus without adding the
 * landmark to normal sequential tab order.
 *
 * @param string               $block_content Rendered Group block HTML.
 * @param array<string, mixed> $block         Parsed block data.
 * @return string
 */
function seo_geo_theme_focusable_main_landmark( string $block_content, array $block ): string {
	$tag_name = $block['attrs']['tagName'] ?? null;

	if ( 'main' !== $tag_name || ! class_exists( 'WP_HTML_Tag_Processor' ) ) {
		return $block_content;
	}

	$processor = new WP_HTML_Tag_Processor( $block_content );

	if ( ! $processor->next_tag( array( 'tag_name' => 'MAIN' ) ) ) {
		return $block_content;
	}

	if ( null === $processor->get_attribute( 'tabindex' ) ) {
		$processor->set_attribute( 'tabindex', '-1' );
	}

	return $processor->get_updated_html();
}
add_filter( 'render_block_core/group', 'seo_geo_theme_focusable_main_landmark', 10, 2 );

/**
 * Register the Theme-owned native contact-form runtime used by migrated pages.
 */
function seo_geo_theme_contact_form_runtime(): \SeoGeo\Theme\Forms\ContactFormRuntime {
	static $runtime = null;

	if ( ! $runtime instanceof \SeoGeo\Theme\Forms\ContactFormRuntime ) {
		$runtime = new \SeoGeo\Theme\Forms\ContactFormRuntime();
	}

	return $runtime;
}

seo_geo_theme_contact_form_runtime()->register();

/**
 * Register the native dynamic Insights/posts-index runtime.
 */
function seo_geo_theme_insights_index_runtime(): \SeoGeo\Theme\Insights\InsightsIndexRuntime {
	static $runtime = null;

	if ( ! $runtime instanceof \SeoGeo\Theme\Insights\InsightsIndexRuntime ) {
		$runtime = new \SeoGeo\Theme\Insights\InsightsIndexRuntime();
	}

	return $runtime;
}

seo_geo_theme_insights_index_runtime()->register();

/**
 * Register the preset-owned navigation runtime.
 */
function seo_geo_theme_preset_navigation_runtime(): \SeoGeo\Theme\Navigation\PresetNavigationRuntime {
	static $runtime = null;

	if ( ! $runtime instanceof \SeoGeo\Theme\Navigation\PresetNavigationRuntime ) {
		$runtime = new \SeoGeo\Theme\Navigation\PresetNavigationRuntime();
	}

	return $runtime;
}

seo_geo_theme_preset_navigation_runtime()->register();

/**
 * Normalize bounded legacy migration placeholders before they reach visitors
 * or downstream SEO/GEO text resolvers.
 *
 * Older Divi exports can contain a brace-wrapped 64-character hexadecimal
 * placeholder where a percent sign was intended. This normalization is
 * deliberately narrow so ordinary authored braces remain untouched.
 *
 * @param string $value Rendered/authored text.
 */
function seo_geo_theme_normalize_migrated_text( string $value ): string {
	if ( '' === $value || ! str_contains( $value, '{' ) ) {
		return $value;
	}

	$normalized = preg_replace( '/\\{[a-f0-9]{64}\\}/i', '%', $value );

	return is_string( $normalized ) ? $normalized : $value;
}
add_filter( 'the_content', 'seo_geo_theme_normalize_migrated_text', 20 );
add_filter( 'seo_geo_normalize_authored_text', 'seo_geo_theme_normalize_migrated_text', 20 );

/**
 * Preserve the shared one-H1 contract on singular public documents.
 *
 * Page/post templates own the document H1 through core/post-title. Migrated
 * builder content may also contain a legacy H1; render it as H2 on the public
 * document so the semantic outline stays deterministic without rewriting the
 * stored migration backup/source.
 *
 * @param string               $block_content Rendered Heading block HTML.
 * @param array<string, mixed> $block         Parsed block data.
 */
function seo_geo_theme_guard_singular_content_h1( string $block_content, array $block ): string {
	if ( is_admin() || ! is_singular() || 1 !== (int) ( $block['attrs']['level'] ?? 2 ) ) {
		return $block_content;
	}

	$block_content = preg_replace( '/<h1\\b/i', '<h2', $block_content, 1 );
	$block_content = preg_replace( '/<\\/h1>/i', '</h2>', $block_content, 1 );

	return is_string( $block_content ) ? $block_content : '';
}
add_filter( 'render_block_core/heading', 'seo_geo_theme_guard_singular_content_h1', 20, 2 );
