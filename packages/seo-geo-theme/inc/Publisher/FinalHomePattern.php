<?php
/**
 * Publisher final Home pattern registration.
 *
 * @package SeoGeoTheme
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register the finished Publisher Home only when Publisher is active.
 */
function seo_geo_theme_register_publisher_final_home_pattern(): void {
	if (
		! function_exists( 'seo_geo_theme_active_preset_id' )
		|| 'publisher' !== seo_geo_theme_active_preset_id()
		|| ! function_exists( 'register_block_pattern' )
	) {
		return;
	}

	$renderer = get_template_directory() . '/preset-patterns/publisher-home-final.php';
	if ( ! is_readable( $renderer ) ) {
		return;
	}

	ob_start();
	include $renderer;
	$content = ob_get_clean();

	if ( ! is_string( $content ) || '' === trim( $content ) ) {
		return;
	}

	$is_es = 'es_ES' === seo_geo_theme_preset_locale();

	register_block_pattern(
		'seo-geo-theme/publisher-home-final',
		array(
			'title'         => $is_es ? 'Editorial / Publisher — Home final' : 'Editorial / Publisher — final Home',
			'description'   => $is_es
				? 'Home editorial visualmente terminada con jerarquía de lectura, temas, estándares y contenido provisional seguro.'
				: 'Visually finished editorial Home with reading hierarchy, topics, standards and safe provisional content.',
			'categories'    => array( 'seo-geo-publisher' ),
			'content'       => $content,
			'viewportWidth' => 1440,
		)
	);
}
add_action( 'init', 'seo_geo_theme_register_publisher_final_home_pattern', 25 );
