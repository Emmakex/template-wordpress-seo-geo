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
	return array( 'corporate', 'local-business', 'publisher', 'ecommerce', 'saas-digital-product' );
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
 * Register one preset-owned PHP pattern outside the neutral Theme pattern set.
 *
 * Keeping these patterns outside /patterns preserves the generic Theme contract
 * while allowing each preset to ship complete final mockups.
 */
function seo_geo_theme_register_final_mockup_pattern( string $preset_id, string $category_slug ): void {
	if ( 'corporate' !== $preset_id || ! function_exists( 'register_block_pattern' ) ) {
		return;
	}

	$path = get_template_directory() . '/preset-patterns/corporate-home-final.php';
	if ( ! is_readable( $path ) ) {
		return;
	}

	ob_start();
	include $path;
	$content = ob_get_clean();

	if ( ! is_string( $content ) || '' === trim( $content ) ) {
		return;
	}

	$is_es = 'es_ES' === seo_geo_theme_preset_locale();
	register_block_pattern(
		'seo-geo-theme/corporate-home-final',
		array(
			'title'         => $is_es ? 'Corporate — Home final' : 'Corporate — final Home',
			'description'   => $is_es
				? 'Maquetación Home Corporate completa con copy e imágenes provisionales sustituibles durante la hidratación.'
				: 'Complete Corporate Home mockup with replaceable provisional copy and media for later hydration.',
			'categories'    => array( $category_slug ),
			'content'       => $content,
			'viewportWidth' => 1440,
		)
	);
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

	seo_geo_theme_register_final_mockup_pattern( $preset_id, $category_slug );
}
add_action( 'init', 'seo_geo_theme_register_active_preset_patterns', 20 );
