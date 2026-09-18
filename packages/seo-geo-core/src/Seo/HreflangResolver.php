<?php
/**
 * Native hreflang resolver.
 *
 * @package SeoGeoCore
 */

declare(strict_types=1);

namespace SeoGeo\Core\Seo;

/**
 * Resolves reciprocal localized alternates from one localized SEO authority.
 */
final class HreflangResolver {
	/**
	 * Native indexability resolver.
	 *
	 * @var IndexabilityResolver
	 */
	private IndexabilityResolver $indexability;

	/**
	 * Localized SEO authority.
	 *
	 * @var LocalizedSeoResolver
	 */
	private LocalizedSeoResolver $localized;

	/**
	 * Create the resolver.
	 *
	 * @param IndexabilityResolver $indexability Native indexability resolver.
	 * @param LocalizedSeoResolver $localized    Localized SEO authority resolver.
	 */
	public function __construct( IndexabilityResolver $indexability, LocalizedSeoResolver $localized ) {
		$this->indexability = $indexability;
		$this->localized    = $localized;
	}

	/**
	 * Resolve hreflang language-to-URL pairs for the current request.
	 *
	 * @return array<string, string>
	 */
	public function resolve(): array {
		if ( IndexabilityResolver::INDEXABLE !== $this->indexability->resolve() ) {
			return array();
		}

		$alternates = $this->localized->alternate_urls();
		$x_default  = $this->localized->x_default_url();

		if ( null !== $x_default ) {
			$alternates['x-default'] = $x_default;
		}

		/**
		 * Filters native hreflang alternates.
		 *
		 * x-default is added only from explicit native configuration. Consumers must preserve
		 * language-code to absolute-URL values; invalid entries are discarded.
		 *
		 * @param array<string, string> $alternates Reciprocal localized URLs.
		 */
		$filtered = apply_filters( 'seo_geo_hreflang_alternates', $alternates );
		$output   = array();

		foreach ( $filtered as $language_code => $url ) {
			$language_code = strtolower( trim( $language_code ) );
			$url           = trim( $url );

			if (
				( 'x-default' === $language_code || 1 === preg_match( '/^[a-z]{2,3}(?:-[a-z0-9]{2,8})*$/', $language_code ) )
				&& '' !== $url
			) {
				$output[ $language_code ] = $url;
			}
		}

		return $output;
	}
}
