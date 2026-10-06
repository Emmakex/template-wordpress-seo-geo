<?php
/**
 * Local Pro final inner-page pattern registration.
 *
 * @package SeoGeoTheme
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register finished Local Pro inner-page compositions only for the active
 * Local Business preset.
 */
function seo_geo_theme_register_local_business_final_inner_patterns(): void {
	if (
		! function_exists( 'seo_geo_theme_active_preset_id' )
		|| 'local-business' !== seo_geo_theme_active_preset_id()
		|| ! function_exists( 'register_block_pattern' )
	) {
		return;
	}

	$definitions = array(
		'services'  => array(
			'slug'     => 'seo-geo-theme/local-business-services-final',
			'title_es' => 'Local Pro — Servicios final',
			'title_en' => 'Local Pro — final Services',
			'desc_es'  => 'Página de servicios terminada y preparada para hidratar alcance, límites y proceso reales.',
			'desc_en'  => 'Finished Services page prepared to hydrate verified scope, boundaries and real process.',
		),
		'locations' => array(
			'slug'     => 'seo-geo-theme/local-business-locations-final',
			'title_es' => 'Local Pro — Ubicaciones final',
			'title_en' => 'Local Pro — final Locations',
			'desc_es'  => 'Página de ubicaciones y cobertura con datos verificados y protección anti-doorway.',
			'desc_en'  => 'Finished Locations and coverage page with verified-fact and anti-doorway safeguards.',
		),
		'about'     => array(
			'slug'     => 'seo-geo-theme/local-business-about-final',
			'title_es' => 'Local Pro — Empresa final',
			'title_en' => 'Local Pro — final About',
			'desc_es'  => 'Página de empresa preparada para historia, método, equipo y autoridad con procedencia real.',
			'desc_en'  => 'Finished About page for sourced history, method, people and authority.',
		),
		'faq'       => array(
			'slug'     => 'seo-geo-theme/local-business-faq-final',
			'title_es' => 'Local Pro — FAQ final',
			'title_en' => 'Local Pro — final FAQ',
			'desc_es'  => 'FAQ local terminada para respuestas visibles, mantenibles y alineadas con datos reales.',
			'desc_en'  => 'Finished local FAQ for visible, maintainable answers aligned with verified operations.',
		),
		'contact'   => array(
			'slug'     => 'seo-geo-theme/local-business-contact-final',
			'title_es' => 'Local Pro — Contacto final',
			'title_en' => 'Local Pro — final Contact',
			'desc_es'  => 'Página de contacto terminada con canales, ubicación y disponibilidad sujetos a verificación.',
			'desc_en'  => 'Finished Contact page with contact routes, location and availability gated by verification.',
		),
	);

	$is_es    = 'es_ES' === seo_geo_theme_preset_locale();
	$renderer = get_template_directory() . '/preset-patterns/local-business-inner-final.php';
	if ( ! is_readable( $renderer ) ) {
		return;
	}

	foreach ( $definitions as $key => $definition ) {
		$seo_geo_pattern_key = $key;
		ob_start();
		include $renderer;
		$content = ob_get_clean();
		unset( $seo_geo_pattern_key );

		if ( ! is_string( $content ) || '' === trim( $content ) ) {
			continue;
		}

		register_block_pattern(
			$definition['slug'],
			array(
				'title'         => $is_es ? $definition['title_es'] : $definition['title_en'],
				'description'   => $is_es ? $definition['desc_es'] : $definition['desc_en'],
				'categories'    => array( 'seo-geo-local-business' ),
				'content'       => $content,
				'viewportWidth' => 1440,
			)
		);
	}
}
add_action( 'init', 'seo_geo_theme_register_local_business_final_inner_patterns', 25 );
