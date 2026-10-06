<?php
/**
 * Preset-owned block template runtime.
 *
 * @package SeoGeoTheme
 */

declare(strict_types=1);

namespace SeoGeo\Theme\Templates;

use WP_Block_Template;

/**
 * Replaces neutral Theme templates with preset-owned final surfaces.
 *
 * Site Editor customizations remain authoritative because customized block
 * templates use source=custom. Only untouched Theme-file templates may be
 * replaced by the active preset.
 */
final class PresetTemplateRuntime {
	/**
	 * Template slugs that presets may replace.
	 *
	 * @var list<string>
	 */
	private const OWNED_TEMPLATES = array( 'single', 'archive', '404' );

	/**
	 * Preset IDs mapped to their bundled system-template filename prefix.
	 *
	 * @var array<string, string>
	 */
	private const PRESET_TEMPLATE_PREFIXES = array(
		'corporate'            => 'corporate',
		'local-business'       => 'local-business',
		'publisher'            => 'publisher',
		'saas-digital-product' => 'saas-digital-product',
	);

	/** Register runtime filters. */
	public function register(): void {
		add_filter( 'get_block_template', array( $this, 'filter_template' ), 20, 3 );
	}

	/**
	 * Apply the active preset's final system surface when the site still uses
	 * the uncustomized Theme-file template.
	 *
	 * @param WP_Block_Template|null $template      Resolved block template.
	 * @param string                 $id            Unified template ID.
	 * @param string                 $template_type Template type.
	 */
	public function filter_template( ?WP_Block_Template $template, string $id, string $template_type ): ?WP_Block_Template {
		unset( $id );

		if ( ! $template instanceof WP_Block_Template || 'wp_template' !== $template_type ) {
			return $template;
		}

		if ( 'theme' !== $template->source || ! function_exists( 'seo_geo_theme_active_preset_id' ) ) {
			return $template;
		}

		$preset_id = seo_geo_theme_active_preset_id();
		if ( null === $preset_id || ! isset( self::PRESET_TEMPLATE_PREFIXES[ $preset_id ] ) || ! in_array( $template->slug, self::OWNED_TEMPLATES, true ) ) {
			return $template;
		}

		$content = $this->preset_template_content( $preset_id, $template->slug );
		if ( null === $content ) {
			return $template;
		}

		$replacement          = clone $template;
		$replacement->content = $content;

		return $replacement;
	}

	/**
	 * Render one bundled preset template source.
	 *
	 * Missing or unreadable preset templates fail safe to the neutral Theme
	 * template supplied by WordPress.
	 *
	 * @param string $preset_id Active preset identifier.
	 * @param string $slug      Template slug to resolve.
	 */
	private function preset_template_content( string $preset_id, string $slug ): ?string {
		if ( ! isset( self::PRESET_TEMPLATE_PREFIXES[ $preset_id ] ) || ! in_array( $slug, self::OWNED_TEMPLATES, true ) ) {
			return null;
		}

		$prefix = self::PRESET_TEMPLATE_PREFIXES[ $preset_id ];
		$path   = get_template_directory() . '/preset-templates/' . $prefix . '-' . $slug . '.php';
		if ( ! is_readable( $path ) ) {
			return null;
		}

		ob_start();
		include $path;
		$content = ob_get_clean();

		if ( ! is_string( $content ) || '' === trim( $content ) ) {
			return null;
		}

		return $content;
	}
}
