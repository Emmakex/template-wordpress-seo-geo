<?php
/**
 * Ecommerce final Home pattern registration.
 *
 * @package SeoGeoTheme
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register the finished Ecommerce Home only when Ecommerce is active.
 */
function seo_geo_theme_register_ecommerce_final_home_pattern(): void {
	if (
		! function_exists( 'seo_geo_theme_active_preset_id' )
		|| 'ecommerce' !== seo_geo_theme_active_preset_id()
		|| ! function_exists( 'register_block_pattern' )
	) {
		return;
	}

	$renderer = get_template_directory() . '/preset-patterns/ecommerce-home-final.php';
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
		'seo-geo-theme/ecommerce-home-final',
		array(
			'title'         => $is_es ? 'Commerce / Ecommerce — Home final' : 'Commerce / Ecommerce — final Home',
			'description'   => $is_es
				? 'Home de comercio visualmente terminada para descubrimiento, catálogo, confianza de compra, guías y políticas con datos comerciales bloqueados hasta proveedor real.'
				: 'Visually finished commerce Home for discovery, catalog, decision confidence, buying guidance and policies with commerce facts blocked until a real provider is connected.',
			'categories'    => array( 'seo-geo-ecommerce' ),
			'content'       => $content,
			'viewportWidth' => 1440,
		)
	);
}
add_action( 'init', 'seo_geo_theme_register_ecommerce_final_home_pattern', 25 );
