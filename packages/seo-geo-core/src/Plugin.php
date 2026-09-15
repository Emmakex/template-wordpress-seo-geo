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
use SeoGeo\Core\Seo\CanonicalResolver;
use SeoGeo\Core\Seo\IndexabilityResolver;
use SeoGeo\Core\Seo\MetaDescriptionResolver;
use SeoGeo\Core\Seo\NativeSeoPresenter;
use SeoGeo\Core\Seo\SeoOutputAuthority;

/**
 * Coordinates the Core lifecycle and shared services.
 */
final class Plugin {
	/**
	 * Normalized language service.
	 *
	 * @var LanguageManager|null
	 */
	private static ?LanguageManager $language_manager = null;

	/**
	 * Runtime integration detector.
	 *
	 * @var RuntimeIntegrationDetector|null
	 */
	private static ?RuntimeIntegrationDetector $integration_detector = null;

	/**
	 * SEO output authority.
	 *
	 * @var SeoOutputAuthority|null
	 */
	private static ?SeoOutputAuthority $seo_authority = null;

	/**
	 * Native SEO presenter.
	 *
	 * @var NativeSeoPresenter|null
	 */
	private static ?NativeSeoPresenter $native_seo = null;

	/**
	 * Register lifecycle hooks.
	 *
	 * @param string $plugin_file Absolute path to the plugin bootstrap file.
	 */
	public static function boot( string $plugin_file ): void {
		register_activation_hook( $plugin_file, array( self::class, 'activate' ) );
		register_deactivation_hook( $plugin_file, array( self::class, 'deactivate' ) );
		add_action( 'plugins_loaded', array( self::class, 'initialize' ), 20 );
	}

	/**
	 * Initialize shared services and native SEO output ownership.
	 */
	public static function initialize(): void {
		load_plugin_textdomain( 'seo-geo-core', false, dirname( plugin_basename( SEO_GEO_CORE_FILE ) ) . '/languages' );

		self::$integration_detector = new RuntimeIntegrationDetector();
		self::$language_manager     = new LanguageManager( new NativeWordPressAdapter() );

		$detected_provider      = self::$integration_detector->seo_provider();
		$authoritative_provider = self::$integration_detector->seo_provider_ready() ? $detected_provider : 'native';

		self::$seo_authority = new SeoOutputAuthority( $authoritative_provider );
		self::$native_seo    = new NativeSeoPresenter(
			self::$seo_authority,
			new IndexabilityResolver(),
			new CanonicalResolver(),
			new MetaDescriptionResolver()
		);
		self::$native_seo->register();

		/**
		 * Fires after Core shared services are ready.
		 *
		 * @param LanguageManager            $language_manager     Normalized language service.
		 * @param RuntimeIntegrationDetector $integration_detector Runtime integration detector.
		 * @param SeoOutputAuthority          $seo_authority        SEO output authority.
		 */
		do_action( 'seo_geo_core_ready', self::$language_manager, self::$integration_detector, self::$seo_authority );
	}

	/**
	 * Persist only the installed code version.
	 */
	public static function activate(): void {
		update_option( 'seo_geo_core_version', SEO_GEO_CORE_VERSION, false );
	}

	/**
	 * Deactivation intentionally preserves configuration and data.
	 */
	public static function deactivate(): void {
		// No destructive work on deactivation.
	}

	/**
	 * Get the normalized language service when initialized.
	 *
	 * @return LanguageManager|null
	 */
	public static function language(): ?LanguageManager {
		return self::$language_manager;
	}

	/**
	 * Get the runtime integration detector when initialized.
	 *
	 * @return RuntimeIntegrationDetector|null
	 */
	public static function integrations(): ?RuntimeIntegrationDetector {
		return self::$integration_detector;
	}

	/**
	 * Get the SEO output authority when initialized.
	 *
	 * @return SeoOutputAuthority|null
	 */
	public static function seo_authority(): ?SeoOutputAuthority {
		return self::$seo_authority;
	}
}
