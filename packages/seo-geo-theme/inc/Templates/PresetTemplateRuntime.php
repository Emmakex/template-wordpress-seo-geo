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
	 * Corporate template slugs owned by this runtime.
	 *
	 * @var list<string>
	 */
	private const CORPORATE_TEMPLATES = array( 'single', 'archive', '404' );

	/**
	 * Register runtime filters.
	 */
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

		if ( 'corporate' !== seo_geo_theme_active_preset_id() || ! in_array( $template->slug, self::CORPORATE_TEMPLATES, true ) ) {
			return $template;
		}

		$content = $this->corporate_template_content( $template->slug );
		if ( null === $content ) {
			return $template;
		}

		$replacement          = clone $template;
		$replacement->content = $content;

		return $replacement;
	}

	/**
	 * Render one bundled Corporate template source.
	 *
	 * @param string $slug Template slug to resolve.
	 */
	private function corporate_template_content( string $slug ): ?string {
		if ( ! in_array( $slug, self::CORPORATE_TEMPLATES, true ) ) {
			return null;
		}

		$path = get_template_directory() . '/preset-templates/corporate-' . $slug . '.php';
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
