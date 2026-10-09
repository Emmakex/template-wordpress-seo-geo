<?php
/**
 * Theme preset registry and activation.
 *
 * @package SeoGeoTheme
 */
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Return the preset IDs bundled and supported by this theme build.
 *
 * @return list<string>
 */
function seo_geo_theme_preset_ids(): array {
	return array( 'corporate', 'local-business', 'publisher', 'ecommerce', 'saas-digital-product', 'research' );
}

/**
 * Resolve the preset source directory in built and monorepo development modes.
 */
function seo_geo_theme_preset_root(): string {
	$embedded = get_template_directory() . '/presets';
	if ( is_dir( $embedded ) ) {
		return $embedded;
	}

	$development = dirname( get_template_directory(), 2 ) . '/presets';

	return is_dir( $development ) ? $development : $embedded;
}

/**
 * Load one trusted preset JSON document.
 *
 * @param string $preset_id Allowlisted preset identifier.
 * @param string $filename  Supported preset document filename.
 * @return array<string, mixed>|null
 */
function seo_geo_theme_preset_document( string $preset_id, string $filename ): ?array {
	$preset_id = sanitize_key( $preset_id );

	if ( ! in_array( $preset_id, seo_geo_theme_preset_ids(), true ) ) {
		return null;
	}

	if ( ! in_array( $filename, array( 'preset.json', 'content-map.json', 'patterns.json', 'page-models.json', 'mockup.json' ), true ) ) {
		return null;
	}

	$path = seo_geo_theme_preset_root() . '/' . $preset_id . '/' . $filename;
	if ( ! is_readable( $path ) || ! function_exists( 'wp_json_file_decode' ) ) {
		return null;
	}

	$value = wp_json_file_decode( $path, array( 'associative' => true ) );

	return is_array( $value ) ? $value : null;
}

/**
 * Resolve the active allowlisted preset.
 */
function seo_geo_theme_active_preset_id(): ?string {
	$value = get_option( 'seo_geo_active_preset', '' );

	if ( ! is_string( $value ) ) {
		return null;
	}

	$value = sanitize_key( $value );

	return in_array( $value, seo_geo_theme_preset_ids(), true ) ? $value : null;
}

/**
 * Resolve the active preset's optional final-mockup contract.
 *
 * @return array<string,mixed>|null
 */
function seo_geo_theme_active_mockup_contract(): ?array {
	$preset_id = seo_geo_theme_active_preset_id();

	return null === $preset_id ? null : seo_geo_theme_preset_document( $preset_id, 'mockup.json' );
}

/**
 * Resolve the bundled locale key used by preset-owned copy.
 */
function seo_geo_theme_preset_locale(): string {
	$locale = get_locale();

	return str_starts_with( strtolower( $locale ), 'es' ) ? 'es_ES' : 'en_US';
}

/**
 * Return one localized preset value with English fallback.
 *
 * @param mixed  $localized Localized map.
 * @param string $key       Localized field key.
 */
function seo_geo_theme_preset_localized_value( $localized, string $key ): ?string {
	if ( ! is_array( $localized ) ) {
		return null;
	}

	$locale   = seo_geo_theme_preset_locale();
	$current  = $localized[ $locale ] ?? null;
	$fallback = $localized['en_US'] ?? null;

	if ( is_array( $current ) && isset( $current[ $key ] ) && is_string( $current[ $key ] ) ) {
		return $current[ $key ];
	}

	if ( is_array( $fallback ) && isset( $fallback[ $key ] ) && is_string( $fallback[ $key ] ) ) {
		return $fallback[ $key ];
	}

	return null;
}

/**
 * Return final mockup definitions owned by one preset.
 *
 * @param string $preset_id Active allowlisted preset identifier.
 * @return list<array<string, string|null>>
 */
function seo_geo_theme_final_mockup_definitions( string $preset_id ): array {
	if ( 'saas-digital-product' === $preset_id ) {
		return array(
			array(
				'slug'           => 'seo-geo-theme/saas-home-final',
				'file'           => 'saas-home-final.php',
				'key'            => null,
				'title_es'       => 'Tech / SaaS — Home final',
				'title_en'       => 'Tech / SaaS — final Home',
				'description_es' => 'Home SaaS visualmente terminada con producto conceptual, Bento funcional y contenido provisional seguro para hidratar después.',
				'description_en' => 'Visually finished SaaS Home with conceptual product preview, functional Bento hierarchy and safe provisional content for later hydration.',
			),
			array(
				'slug'           => 'seo-geo-theme/saas-product-final',
				'file'           => 'saas-inner-final.php',
				'key'            => 'product',
				'title_es'       => 'Tech / SaaS — Producto final',
				'title_en'       => 'Tech / SaaS — final Product',
				'description_es' => 'Página de producto terminada para explicar flujo, estados y límites con evidencia real durante la hidratación.',
				'description_en' => 'Finished Product page for real workflow, states and boundaries hydrated from verified evidence.',
			),
			array(
				'slug'           => 'seo-geo-theme/saas-features-final',
				'file'           => 'saas-inner-final.php',
				'key'            => 'features',
				'title_es'       => 'Tech / SaaS — Funcionalidades final',
				'title_en'       => 'Tech / SaaS — final Features',
				'description_es' => 'Página de funcionalidades agrupada por trabajo, requisitos y límites verificables.',
				'description_en' => 'Finished Features page grouped by work, requirements and verifiable boundaries.',
			),
			array(
				'slug'           => 'seo-geo-theme/saas-solutions-final',
				'file'           => 'saas-inner-final.php',
				'key'            => 'solutions',
				'title_es'       => 'Tech / SaaS — Soluciones final',
				'title_en'       => 'Tech / SaaS — final Solutions',
				'description_es' => 'Página de soluciones preparada para casos y roles reales sin crear verticales ficticios.',
				'description_en' => 'Finished Solutions page prepared for real roles and use cases without fictional verticals.',
			),
			array(
				'slug'           => 'seo-geo-theme/saas-integrations-final',
				'file'           => 'saas-inner-final.php',
				'key'            => 'integrations',
				'title_es'       => 'Tech / SaaS — Integraciones final',
				'title_en'       => 'Tech / SaaS — final Integrations',
				'description_es' => 'Página de integraciones lista para catálogo, requisitos y límites confirmados, sin logos inventados.',
				'description_en' => 'Finished Integrations page ready for verified catalog, requirements and boundaries without invented logos.',
			),
			array(
				'slug'           => 'seo-geo-theme/saas-pricing-final',
				'file'           => 'saas-inner-final.php',
				'key'            => 'pricing',
				'title_es'       => 'Tech / SaaS — Pricing final',
				'title_en'       => 'Tech / SaaS — final Pricing',
				'description_es' => 'Estructura comercial terminada sin precios ni condiciones inventadas antes de la hidratación autorizada.',
				'description_en' => 'Finished commercial structure without invented pricing or terms before authorized hydration.',
			),
			array(
				'slug'           => 'seo-geo-theme/saas-resources-final',
				'file'           => 'saas-inner-final.php',
				'key'            => 'resources',
				'title_es'       => 'Tech / SaaS — Recursos final',
				'title_en'       => 'Tech / SaaS — final Resources',
				'description_es' => 'Centro de recursos preparado para contenido real, clusters temáticos y enlazado interno.',
				'description_en' => 'Finished Resources hub prepared for real content, topical clusters and internal linking.',
			),
			array(
				'slug'           => 'seo-geo-theme/saas-contact-demo-final',
				'file'           => 'saas-inner-final.php',
				'key'            => 'contact-demo',
				'title_es'       => 'Tech / SaaS — Contacto y demo final',
				'title_en'       => 'Tech / SaaS — final Contact and Demo',
				'description_es' => 'Página de conversión terminada para proceso comercial, formulario y canales reales.',
				'description_en' => 'Finished conversion page prepared for the real commercial flow, native form and authorized channels.',
			),
		);
	}

	if ( 'local-business' === $preset_id ) {
		return array(
			array(
				'slug'           => 'seo-geo-theme/local-business-home-final',
				'file'           => 'local-business-home-final.php',
				'key'            => null,
				'title_es'       => 'Local Pro — Home final',
				'title_en'       => 'Local Pro — final Home',
				'description_es' => 'Home local visualmente terminada con servicios, cobertura, datos públicos y contacto sujetos a verificación durante la hidratación.',
				'description_en' => 'Visually finished local-business Home with services, coverage, public facts and contact gated by verified hydration.',
			),
		);
	}

	if ( 'corporate' !== $preset_id ) {
		return array();
	}

	return array(
		array(
			'slug'           => 'seo-geo-theme/corporate-home-final',
			'file'           => 'corporate-home-final.php',
			'key'            => 'home',
			'title_es'       => 'Corporate — Home final',
			'title_en'       => 'Corporate — final Home',
			'description_es' => 'Home Corporate completa con copy e imágenes provisionales sustituibles durante la hidratación.',
			'description_en' => 'Complete Corporate Home with replaceable provisional copy and media for later hydration.',
		),
		array(
			'slug'           => 'seo-geo-theme/corporate-services-final',
			'file'           => 'corporate-inner-final.php',
			'key'            => 'services',
			'title_es'       => 'Corporate — Servicios final',
			'title_en'       => 'Corporate — final Services',
			'description_es' => 'Página de servicios completa, visual y preparada para hidratar la oferta real sin rediseño.',
			'description_en' => 'Complete visual Services page ready to hydrate the real offer without redesigning it.',
		),
		array(
			'slug'           => 'seo-geo-theme/corporate-work-final',
			'file'           => 'corporate-inner-final.php',
			'key'            => 'work',
			'title_es'       => 'Corporate — Proyectos final',
			'title_en'       => 'Corporate — final Work',
			'description_es' => 'Página de proyectos preparada para casos verificables y visuales reales, sin inventar evidencia.',
			'description_en' => 'Work page prepared for verified cases and real visuals without fabricating evidence.',
		),
		array(
			'slug'           => 'seo-geo-theme/corporate-about-final',
			'file'           => 'corporate-inner-final.php',
			'key'            => 'about',
			'title_es'       => 'Corporate — Empresa final',
			'title_en'       => 'Corporate — final About',
			'description_es' => 'Página de empresa completa para historia, autoridad, equipo y método con procedencia real.',
			'description_en' => 'Complete About page for story, authority, team and method with real provenance.',
		),
		array(
			'slug'           => 'seo-geo-theme/corporate-contact-final',
			'file'           => 'corporate-inner-final.php',
			'key'            => 'contact',
			'title_es'       => 'Corporate — Contacto final',
			'title_en'       => 'Corporate — final Contact',
			'description_es' => 'Página de contacto terminada visualmente y preparada para hidratar canales y formulario reales.',
			'description_en' => 'Visually complete Contact page ready to hydrate real channels and form configuration.',
		),
		array(
			'slug'           => 'seo-geo-theme/corporate-insights-final',
			'file'           => 'corporate-inner-final.php',
			'key'            => 'insights',
			'title_es'       => 'Corporate — Insights final',
			'title_en'       => 'Corporate — final Insights',
			'description_es' => 'Índice editorial Corporate preparado para contenido dinámico, autoridad temática y enlazado interno.',
			'description_en' => 'Corporate editorial index prepared for dynamic content, topical authority and internal linking.',
		),
	);
}

/**
 * Register preset-owned final mockups outside the neutral Theme pattern set.
 *
 * Keeping these patterns outside /patterns preserves the generic Theme contract
 * while allowing each preset to ship complete, production-shaped mockups.
 *
 * @param string $preset_id     Active allowlisted preset identifier.
 * @param string $category_slug Registered block-pattern category slug.
 */
function seo_geo_theme_register_final_mockup_patterns( string $preset_id, string $category_slug ): void {
	if ( ! function_exists( 'register_block_pattern' ) ) {
		return;
	}

	$patterns = seo_geo_theme_final_mockup_definitions( $preset_id );
	if ( array() === $patterns ) {
		return;
	}

	$is_es = 'es_ES' === seo_geo_theme_preset_locale();

	foreach ( $patterns as $pattern ) {
		$path = get_template_directory() . '/preset-patterns/' . $pattern['file'];
		if ( ! is_readable( $path ) ) {
			continue;
		}

		$seo_geo_pattern_key = $pattern['key'];
		ob_start();
		include $path;
		$content = ob_get_clean();
		unset( $seo_geo_pattern_key );

		if ( ! is_string( $content ) || '' === trim( $content ) ) {
			continue;
		}

		register_block_pattern(
			$pattern['slug'],
			array(
				'title'         => $is_es ? $pattern['title_es'] : $pattern['title_en'],
				'description'   => $is_es ? $pattern['description_es'] : $pattern['description_en'],
				'categories'    => array( $category_slug ),
				'content'       => $content,
				'viewportWidth' => 1440,
			)
		);
	}
}

/**
 * Register the active preset's editor patterns.
 */
function seo_geo_theme_register_active_preset_patterns(): void {
	$preset_id = seo_geo_theme_active_preset_id();
	if ( null === $preset_id ) {
		return;
	}

	$document = seo_geo_theme_preset_document( $preset_id, 'patterns.json' );
	if ( null === $document || ! isset( $document['patterns'] ) || ! is_array( $document['patterns'] ) ) {
		return;
	}

	$category      = $document['category'] ?? null;
	$category_slug = null;

	if ( is_array( $category ) && isset( $category['slug'] ) && is_string( $category['slug'] ) ) {
		$category_slug = sanitize_key( $category['slug'] );
		$labels        = $category['labels'] ?? array();
		$locale        = seo_geo_theme_preset_locale();
		$candidate     = is_array( $labels ) ? ( $labels[ $locale ] ?? $labels['en_US'] ?? null ) : null;

		if ( '' !== $category_slug && is_string( $candidate ) && function_exists( 'register_block_pattern_category' ) ) {
			register_block_pattern_category(
				$category_slug,
				array( 'label' => $candidate )
			);
		}
	}

	if ( ! function_exists( 'register_block_pattern' ) || null === $category_slug || '' === $category_slug ) {
		return;
	}

	foreach ( $document['patterns'] as $pattern ) {
		if ( ! is_array( $pattern ) || ! isset( $pattern['slug'] ) || ! is_string( $pattern['slug'] ) ) {
			continue;
		}

		$title       = seo_geo_theme_preset_localized_value( $pattern['locales'] ?? null, 'title' );
		$description = seo_geo_theme_preset_localized_value( $pattern['locales'] ?? null, 'description' );
		$content     = seo_geo_theme_preset_localized_value( $pattern['locales'] ?? null, 'content' );

		if ( null === $title || null === $description || null === $content ) {
			continue;
		}

		$args = array(
			'title'       => $title,
			'description' => $description,
			'categories'  => array( $category_slug ),
			'content'     => $content,
		);

		$viewport_width = $pattern['viewport_width'] ?? null;
		if ( is_int( $viewport_width ) && 0 < $viewport_width ) {
			$args['viewportWidth'] = $viewport_width;
		}

		register_block_pattern( $pattern['slug'], $args );
	}

	seo_geo_theme_register_final_mockup_patterns( $preset_id, $category_slug );
}
add_action( 'init', 'seo_geo_theme_register_active_preset_patterns', 20 );
