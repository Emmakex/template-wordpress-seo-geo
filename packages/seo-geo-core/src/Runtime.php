<?php
/**
 * Context-neutral SEO/GEO runtime.
 *
 * @package SeoGeoCore
 */

declare(strict_types=1);

namespace SeoGeo\Core;

use SeoGeo\Core\Integrations\RuntimeIntegrationDetector;
use SeoGeo\Core\Language\LanguageManager;
use SeoGeo\Core\Language\NativeLanguageConfiguration;
use SeoGeo\Core\Language\NativeLanguageRouter;
use SeoGeo\Core\Language\NativeWordPressAdapter;
use SeoGeo\Core\Seo\BreadcrumbResolver;
use SeoGeo\Core\Seo\CanonicalResolver;
use SeoGeo\Core\Seo\IndexabilityResolver;
use SeoGeo\Core\Seo\MetaDescriptionResolver;
use SeoGeo\Core\Seo\NativeSeoPresenter;
use SeoGeo\Core\Seo\OpenGraphResolver;
use SeoGeo\Core\Seo\SeoOutputAuthority;

/**
 * Boots SEO/GEO services independently from plugin or theme packaging.
 */
final class Runtime {
	/**
	 * Normalized language service.
	 *
	 * @var LanguageManager|null
	 */
	private static ?LanguageManager $language_manager = null;

	/**
	 * Native language router.
	 *
	 * @var NativeLanguageRouter|null
	 */
	private static ?NativeLanguageRouter $language_router = null;

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
	 * Reusable breadcrumb data resolver.
	 *
	 * @var BreadcrumbResolver|null
	 */
	private static ?BreadcrumbResolver $breadcrumbs = null;

	/**
	 * Initialize shared services once.
	 */
	public static function initialize(): void {
		if ( null !== self::$integration_detector ) {
			return;
		}

		self::$integration_detector = new RuntimeIntegrationDetector();

		$language_configuration  = NativeLanguageConfiguration::from_wordpress();
		self::$language_manager  = new LanguageManager( new NativeWordPressAdapter( $language_configuration ) );
		self::$language_router   = new NativeLanguageRouter( $language_configuration );
		self::$seo_authority     = new SeoOutputAuthority( self::$integration_detector->seo_provider() );

		self::$language_router->register();
		add_filter( 'seo_geo_indexability_state', array( self::class, 'protect_localized_route_indexability' ), 20 );

		$indexability = new IndexabilityResolver();
		$canonical    = new CanonicalResolver();
		$description  = new MetaDescriptionResolver();
		$open_graph   = new OpenGraphResolver( $indexability, $canonical, $description );

		self::$breadcrumbs = new BreadcrumbResolver();
		self::$native_seo  = new NativeSeoPresenter(
			self::$seo_authority,
			$indexability,
			$canonical,
			$description,
			$open_graph
		);
		self::$native_seo->register();

		/**
		 * Fires after shared SEO/GEO services are ready.
		 *
		 * @param LanguageManager            $language_manager     Normalized language service.
		 * @param RuntimeIntegrationDetector $integration_detector Runtime integration detector.
		 * @param SeoOutputAuthority          $seo_authority        SEO output authority.
		 */
		do_action( 'seo_geo_core_ready', self::$language_manager, self::$integration_detector, self::$seo_authority );
	}

	/**
	 * Keep newly routed localized URLs out of the index until translation
	 * relationships, localized canonicals and hreflang are authoritative.
	 *
	 * @param string $state Native indexability state.
	 */
	public static function protect_localized_route_indexability( string $state ): string {
		if (
			IndexabilityResolver::INDEXABLE === $state
			&& null !== self::$language_router
			&& self::$language_router->is_localized_request()
		) {
			return IndexabilityResolver::NOINDEX_FOLLOW;
		}

		return $state;
	}

	/**
	 * Get the normalized language service when initialized.
	 */
	public static function language(): ?LanguageManager {
		return self::$language_manager;
	}

	/**
	 * Get the native language router when initialized.
	 */
	public static function language_router(): ?NativeLanguageRouter {
		return self::$language_router;
	}

	/**
	 * Get the runtime integration detector when initialized.
	 */
	public static function integrations(): ?RuntimeIntegrationDetector {
		return self::$integration_detector;
	}

	/**
	 * Get the SEO output authority when initialized.
	 */
	public static function seo_authority(): ?SeoOutputAuthority {
		return self::$seo_authority;
	}

	/**
	 * Get the reusable breadcrumb resolver when initialized.
	 */
	public static function breadcrumbs(): ?BreadcrumbResolver {
		return self::$breadcrumbs;
	}
}
