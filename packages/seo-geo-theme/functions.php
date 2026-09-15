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

/**
 * Load project-owned translations.
 */
function seo_geo_theme_setup(): void {
	load_theme_textdomain( 'seo-geo-theme', get_template_directory() . '/languages' );
}
add_action( 'after_setup_theme', 'seo_geo_theme_setup' );

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
