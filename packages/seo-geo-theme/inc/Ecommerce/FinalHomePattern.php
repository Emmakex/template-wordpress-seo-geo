<?php
/**
 * Ecommerce final pattern registration.
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

/**
 * Register finished Ecommerce inner-page compositions only for Ecommerce.
 */
function seo_geo_theme_register_ecommerce_final_inner_patterns(): void {
	if (
		! function_exists( 'seo_geo_theme_active_preset_id' )
		|| 'ecommerce' !== seo_geo_theme_active_preset_id()
		|| ! function_exists( 'register_block_pattern' )
	) {
		return;
	}

	$definitions = array(
		'shop'          => array(
			'slug'     => 'seo-geo-theme/ecommerce-shop-final',
			'title_es' => 'Commerce / Ecommerce — Tienda final',
			'title_en' => 'Commerce / Ecommerce — final Shop',
			'desc_es'  => 'Entrada de tienda terminada para descubrimiento del catálogo real sin apropiarse de producto, precio, stock, carrito o checkout.',
			'desc_en'  => 'Finished Shop entry for real catalog discovery without taking ownership of product, price, stock, cart or checkout.',
		),
		'categories'    => array(
			'slug'     => 'seo-geo-theme/ecommerce-categories-final',
			'title_es' => 'Commerce / Ecommerce — Categorías final',
			'title_en' => 'Commerce / Ecommerce — final Categories',
			'desc_es'  => 'Página de categorías terminada para taxonomía mantenida y sin páginas duplicadas por variantes de keywords.',
			'desc_en'  => 'Finished Categories page for maintained taxonomy without keyword-variant duplicate pages.',
		),
		'buying-guides' => array(
			'slug'     => 'seo-geo-theme/ecommerce-buying-guides-final',
			'title_es' => 'Commerce / Ecommerce — Guías de compra final',
			'title_en' => 'Commerce / Ecommerce — final Buying Guides',
			'desc_es'  => 'Superficie editorial terminada para comparativas, criterios y decisiones basadas en conocimiento real.',
			'desc_en'  => 'Finished editorial surface for comparisons, criteria and decisions grounded in real knowledge.',
		),
		'about'         => array(
			'slug'     => 'seo-geo-theme/ecommerce-about-final',
			'title_es' => 'Commerce / Ecommerce — Sobre la tienda final',
			'title_en' => 'Commerce / Ecommerce — final About',
			'desc_es'  => 'Página Sobre la tienda terminada para propósito, selección, identidad y procedencia verificables.',
			'desc_en'  => 'Finished About page for verifiable purpose, selection approach, identity and provenance.',
		),
		'support'       => array(
			'slug'     => 'seo-geo-theme/ecommerce-support-final',
			'title_es' => 'Commerce / Ecommerce — Soporte final',
			'title_en' => 'Commerce / Ecommerce — final Support',
			'desc_es'  => 'Página de soporte terminada para políticas y canales reales sin inventar niveles de servicio.',
			'desc_en'  => 'Finished Support page for real policies and channels without invented service levels.',
		),
		'contact'       => array(
			'slug'     => 'seo-geo-theme/ecommerce-contact-final',
			'title_es' => 'Commerce / Ecommerce — Contacto final',
			'title_en' => 'Commerce / Ecommerce — final Contact',
			'desc_es'  => 'Página de contacto terminada para canales públicos verificados y hechos operativos fact-gated.',
			'desc_en'  => 'Finished Contact page for verified public channels and fact-gated operating facts.',
		),
	);

	$renderer = get_template_directory() . '/preset-patterns/ecommerce-inner-final.php';
	if ( ! is_readable( $renderer ) ) {
		return;
	}

	$is_es = 'es_ES' === seo_geo_theme_preset_locale();

	foreach ( $definitions as $key => $definition ) {
		$seo_geo_ecommerce_pattern_key = $key;
		ob_start();
		include $renderer;
		$content = ob_get_clean();
		unset( $seo_geo_ecommerce_pattern_key );

		if ( ! is_string( $content ) || '' === trim( $content ) ) {
			continue;
		}

		register_block_pattern(
			$definition['slug'],
			array(
				'title'         => $is_es ? $definition['title_es'] : $definition['title_en'],
				'description'   => $is_es ? $definition['desc_es'] : $definition['desc_en'],
				'categories'    => array( 'seo-geo-ecommerce' ),
				'content'       => $content,
				'viewportWidth' => 1440,
			)
		);
	}
}
add_action( 'init', 'seo_geo_theme_register_ecommerce_final_inner_patterns', 25 );
