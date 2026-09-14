<?php
/**
 * Main plugin coordinator.
 *
 * @package SeoGeoCore
 */

declare(strict_types=1);

namespace SeoGeo\Core;

use SeoGeo\Core\Integrations\RuntimeIntegrationDetector;
use SeoGeo\Core\Language\LanguageManager;
use SeoGeo\Core\Language\NativeWordPressAdapter;

final class Plugin {
	private static ?LanguageManager $language_manager = null;
	private static ?RuntimeIntegrationDetector $integration_detector = null;

	/**
	 * Register lifecycle hooks without producing frontend output.
	 */
	public static function boot( string $plugin_file ): void {
		register_activation_hook( $plugin_file, array( self::class, 'activate' ) );
		register_deactivation_hook( $plugin_file, array( self::class, 'deactivate' ) );
		add_action( 'plugins_loaded', array( self::class, 'initialize' ), 20 );
	}

	/**
	 * Initialize the Phase 1 service skeleton.
	 */
	public static function initialize(): void {
		load_plugin_textdomain( 'seo-geo-core', false, dirname( plugin_basename( SEO_GEO_CORE_FILE ) ) . '/languages' );

		self::$integration_detector = new RuntimeIntegrationDetector();
		self::$language_manager     = new LanguageManager( new NativeWordPressAdapter() );

		/**
		 * Fires after the non-output service skeleton is ready.
		 *
		 * @param LanguageManager            $language_manager     Normalized language service.
		 * @param RuntimeIntegrationDetector $integration_detector Runtime integration detector.
		 */
		do_action( 'seo_geo_core_ready', self::$language_manager, self::$integration_detector );
	}

	/**
	 * Persist only the installed code version. No SEO settings are created yet.
	 */
	public static function activate(): void {
		update_option( 'seo_geo_core_version', SEO_GEO_CORE_VERSION, false );
	}

	/**
	 * Deactivation intentionally preserves configuration/data.
	 */
	public static function deactivate(): void {
		// No destructive work on deactivation.
	}

	public static function language(): ?LanguageManager {
		return self::$language_manager;
	}

	public static function integrations(): ?RuntimeIntegrationDetector {
		return self::$integration_detector;
	}
}
