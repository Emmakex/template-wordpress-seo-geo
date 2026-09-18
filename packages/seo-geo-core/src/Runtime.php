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
use SeoGeo\Core\Language\NativeTranslationRegistry;
use SeoGeo\Core\Language\NativeWordPressAdapter;
use SeoGeo\Core\Schema\SchemaArticleResolver;
use SeoGeo\Core\Schema\SchemaBreadcrumbResolver;
use SeoGeo\Core\Schema\SchemaGraphBuilder;
use SeoGeo\Core\Schema\SchemaIdentityResolver;
use SeoGeo\Core\Schema\SchemaLocalBusinessResolver;
use SeoGeo\Core\Schema\SchemaNodeIds;
use SeoGeo\Core\Schema\SchemaPresenter;
use SeoGeo\Core\Schema\SchemaVisibleContentResolver;
use SeoGeo\Core\Seo\BreadcrumbResolver;
use SeoGeo\Core\Seo\CanonicalResolver;
use SeoGeo\Core\Seo\HreflangResolver;
use SeoGeo\Core\Seo\IndexabilityResolver;
use SeoGeo\Core\Seo\LocalizedSeoResolver;
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
	 * Native translation relationship registry.
	 *
	 * @var NativeTranslationRegistry|null
	 */
	private static ?NativeTranslationRegistry $translation_registry = null;

	/**
	 * Localized SEO authority resolver.
	 *
	 * @var LocalizedSeoResolver|null
	 */
	private static ?LocalizedSeoResolver $localized_seo = null;

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
	 * Native Schema graph builder.
	 *
	 * @var SchemaGraphBuilder|null
	 */
	private static ?SchemaGraphBuilder $schema_graph = null;

	/**
	 * Initialize shared services once.
	 */
	public static function initialize(): void {
		if ( null !== self::$integration_detector ) {
			return;
		}

		self::$integration_detector = new RuntimeIntegrationDetector();

		$language_configuration = NativeLanguageConfiguration::from_wordpress();

		self::$language_manager = new LanguageManager( new NativeWordPressAdapter( $language_configuration ) );

		self::$language_router      = new NativeLanguageRouter( $language_configuration );
		self::$translation_registry = new NativeTranslationRegistry( $language_configuration );

		self::$seo_authority = new SeoOutputAuthority( self::$integration_detector->seo_provider() );
		self::$localized_seo = new LocalizedSeoResolver( self::$language_router, self::$translation_registry, $language_configuration );

		self::$language_router->register();
		add_filter( 'seo_geo_indexability_state', array( self::$localized_seo, 'resolve_indexability' ), 20 );
		add_filter( 'seo_geo_canonical_url', array( self::$localized_seo, 'filter_canonical_url' ), 20, 2 );

		$indexability = new IndexabilityResolver();
		$canonical    = new CanonicalResolver();
		$description  = new MetaDescriptionResolver();
		$open_graph   = new OpenGraphResolver( $indexability, $canonical, $description );
		$hreflang     = new HreflangResolver( $indexability, self::$localized_seo );

		self::$breadcrumbs = new BreadcrumbResolver( self::$localized_seo );
		self::$native_seo  = new NativeSeoPresenter(
			self::$seo_authority,
			$indexability,
			$canonical,
			$description,
			$open_graph,
			$hreflang
		);
		self::$native_seo->register();

		$schema_ids            = new SchemaNodeIds();
		$schema_breadcrumb     = new SchemaBreadcrumbResolver( self::$breadcrumbs, $schema_ids );
		$schema_identity        = new SchemaIdentityResolver( $schema_ids );
		$schema_visible_content = new SchemaVisibleContentResolver();
		$schema_local_business  = new SchemaLocalBusinessResolver( $schema_ids, $schema_identity, $schema_visible_content );
		$schema_article         = new SchemaArticleResolver( $schema_identity );
		self::$schema_graph    = new SchemaGraphBuilder(
			$indexability,
			$canonical,
			self::$language_manager,
			$schema_ids,
			$schema_breadcrumb,
			$schema_identity,
			$schema_local_business,
			$schema_article
		);
		$schema_presenter      = new SchemaPresenter( self::$seo_authority, self::$schema_graph );
		$schema_presenter->register();

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
	 * Get the native translation relationship registry when initialized.
	 */
	public static function translations(): ?NativeTranslationRegistry {
		return self::$translation_registry;
	}

	/**
	 * Get the localized SEO authority when initialized.
	 *
	 * Later Schema/GEO layers must reuse this authority instead of reconstructing
	 * language or translation state independently.
	 */
	public static function localized_seo(): ?LocalizedSeoResolver {
		return self::$localized_seo;
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

	/**
	 * Get the native Schema graph builder when initialized.
	 */
	public static function schema_graph(): ?SchemaGraphBuilder {
		return self::$schema_graph;
	}
}
