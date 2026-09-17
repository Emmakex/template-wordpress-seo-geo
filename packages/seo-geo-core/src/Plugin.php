<?php
/**
 * Plugin wrapper for the context-neutral SEO/GEO runtime.
 *
 * @package SeoGeoCore
 */

declare(strict_types=1);

namespace SeoGeo\Core;

use SeoGeo\Core\Integrations\RuntimeIntegrationDetector;
use SeoGeo\Core\Language\LanguageManager;
use SeoGeo\Core\Seo\SeoOutputAuthority;

/**
 * Preserves the optional standalone plugin entry point.
 */
final class Plugin {
	/**
	 * Register plugin lifecycle hooks.
	 *
	 * @param string $plugin_file Absolute path to the plugin bootstrap file.
	 */
	public static function boot( string $plugin_file ): void {
		register_activation_hook( $plugin_file, array( self::class, 'activate' ) );
		register_deactivation_hook( $plugin_file, array( self::class, 'deactivate' ) );
		add_action( 'plugins_loaded', array( self::class, 'initialize' ), 20 );
	}

	/**
	 * Initialize the shared runtime from plugin packaging.
	 */
	public static function initialize(): void {
		if ( defined( 'SEO_GEO_CORE_FILE' ) ) {
			load_plugin_textdomain( 'seo-geo-core', false, dirname( plugin_basename( SEO_GEO_CORE_FILE ) ) . '/languages' );
		}

		Runtime::initialize();
	}

	/**
	 * Persist only the installed code version.
	 */
	public static function activate(): void {
		if ( defined( 'SEO_GEO_CORE_VERSION' ) ) {
			update_option( 'seo_geo_core_version', SEO_GEO_CORE_VERSION, false );
		}
	}

	/**
	 * Deactivation intentionally preserves configuration and data.
	 */
	public static function deactivate(): void {
		// No destructive work on deactivation.
	}

	/**
	 * Get the normalized language service when initialized.
	 */
	public static function language(): ?LanguageManager {
		return Runtime::language();
	}

	/**
	 * Get the runtime integration detector when initialized.
	 */
	public static function integrations(): ?RuntimeIntegrationDetector {
		return Runtime::integrations();
	}

	/**
	 * Get the SEO output authority when initialized.
	 */
	public static function seo_authority(): ?SeoOutputAuthority {
		return Runtime::seo_authority();
	}
}
