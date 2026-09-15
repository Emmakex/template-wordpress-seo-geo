<?php
/**
 * Canonical URL resolver.
 *
 * @package SeoGeoCore
 */

declare(strict_types=1);

namespace SeoGeo\Core\Seo;

/**
 * Resolves the canonical URL for the current request.
 */
final class CanonicalResolver {
	/**
	 * Resolve the canonical URL for an indexability state.
	 *
	 * @param string $indexability Resolved indexability state.
	 */
	public function resolve( string $indexability ): ?string {
		if ( IndexabilityResolver::INDEXABLE !== $indexability ) {
			return null;
		}

		$url = null;

		if ( is_singular() ) {
			$object_id = get_queried_object_id();

			if ( 0 < $object_id ) {
				$candidate = wp_get_canonical_url( $object_id );

				if ( is_string( $candidate ) && '' !== $candidate ) {
					$url = $candidate;
				}
			}
		} elseif ( is_front_page() ) {
			$url = home_url( '/' );
		} else {
			$paged     = max( 1, (int) get_query_var( 'paged', 1 ) );
			$candidate = get_pagenum_link( $paged, false );

			if ( '' !== $candidate ) {
				$url = $candidate;
			}
		}

		/**
		 * Filters the resolved canonical URL.
		 *
		 * Returning a non-empty string replaces the native result. Returning an
		 * unsupported value suppresses the canonical for this request.
		 *
		 * @param string|null $url          Canonical URL.
		 * @param string      $indexability Resolved indexability state.
		 */
		$filtered = apply_filters( 'seo_geo_canonical_url', $url, $indexability );

		return is_string( $filtered ) && '' !== $filtered ? $filtered : null;
	}
}
