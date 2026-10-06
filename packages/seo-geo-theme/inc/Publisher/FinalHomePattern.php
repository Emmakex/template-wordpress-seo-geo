<?php
/**
 * Publisher final pattern registration.
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

/**
 * Register finished Publisher inner-page compositions only for Publisher.
 */
function seo_geo_theme_register_publisher_final_inner_patterns(): void {
	if (
		! function_exists( 'seo_geo_theme_active_preset_id' )
		|| 'publisher' !== seo_geo_theme_active_preset_id()
		|| ! function_exists( 'register_block_pattern' )
	) {
		return;
	}

	$definitions = array(
		'articles'         => array(
			'slug'     => 'seo-geo-theme/publisher-articles-final',
			'title_es' => 'Editorial / Publisher — Artículos final',
			'title_en' => 'Editorial / Publisher — final Articles',
			'desc_es'  => 'Índice de artículos terminado para trabajo publicado real, prioridades y recorridos de lectura mantenidos.',
			'desc_en'  => 'Finished Articles index for real published work, editorial priorities and maintained reading paths.',
		),
		'topics'           => array(
			'slug'     => 'seo-geo-theme/publisher-topics-final',
			'title_es' => 'Editorial / Publisher — Temas final',
			'title_en' => 'Editorial / Publisher — final Topics',
			'desc_es'  => 'Página temática terminada para taxonomía real y hubs con valor editorial distinto, sin archivos finos.',
			'desc_en'  => 'Finished Topics page for real taxonomy and distinct editorial hubs without thin archives.',
		),
		'authors'          => array(
			'slug'     => 'seo-geo-theme/publisher-authors-final',
			'title_es' => 'Editorial / Publisher — Autores final',
			'title_en' => 'Editorial / Publisher — final Authors',
			'desc_es'  => 'Página de autores terminada para perfiles públicos reales y trabajo publicado verificable.',
			'desc_en'  => 'Finished Authors page for real public profiles and verifiable published work.',
		),
		'about'            => array(
			'slug'     => 'seo-geo-theme/publisher-about-final',
			'title_es' => 'Editorial / Publisher — Publicación final',
			'title_en' => 'Editorial / Publisher — final About',
			'desc_es'  => 'Página Sobre la publicación terminada para propósito, alcance, gobernanza y responsabilidad con procedencia real.',
			'desc_en'  => 'Finished About page for sourced purpose, scope, governance and accountability.',
		),
		'editorial-policy' => array(
			'slug'     => 'seo-geo-theme/publisher-editorial-policy-final',
			'title_es' => 'Editorial / Publisher — Política editorial final',
			'title_en' => 'Editorial / Publisher — final Editorial Policy',
			'desc_es'  => 'Política editorial visualmente terminada para estándares operativos, fuentes, correcciones y conflictos reales.',
			'desc_en'  => 'Visually finished Editorial Policy for real operating standards, sourcing, corrections and conflicts.',
		),
		'contact'          => array(
			'slug'     => 'seo-geo-theme/publisher-contact-final',
			'title_es' => 'Editorial / Publisher — Contacto final',
			'title_en' => 'Editorial / Publisher — final Contact',
			'desc_es'  => 'Página de contacto terminada para canales editoriales, colaboradores y negocio realmente mantenidos.',
			'desc_en'  => 'Finished Contact page for genuinely maintained editorial, contributor and business routes.',
		),
	);

	$renderer = get_template_directory() . '/preset-patterns/publisher-inner-final.php';
	if ( ! is_readable( $renderer ) ) {
		return;
	}

	$is_es = 'es_ES' === seo_geo_theme_preset_locale();

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
				'categories'    => array( 'seo-geo-publisher' ),
				'content'       => $content,
				'viewportWidth' => 1440,
			)
		);
	}
}
add_action( 'init', 'seo_geo_theme_register_publisher_final_inner_patterns', 25 );
