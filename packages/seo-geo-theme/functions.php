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
