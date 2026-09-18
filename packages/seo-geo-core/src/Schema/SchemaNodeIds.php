<?php
/**
 * Stable native Schema node identifiers.
 *
 * @package SeoGeoCore
 */

declare(strict_types=1);

namespace SeoGeo\Core\Schema;

/**
 * Generates deterministic graph node IDs from authoritative public URLs.
 */
final class SchemaNodeIds {
	/**
	 * Return the stable WebSite entity ID.
	 */
	public function website(): string {
		return $this->with_fragment( home_url( '/' ), 'website' );
	}

	/**
	 * Return the stable WebPage entity ID for one canonical URL.
	 *
	 * @param string $canonical_url Authoritative page canonical URL.
	 */
	public function web_page( string $canonical_url ): string {
		return $this->with_fragment( $canonical_url, 'webpage' );
	}

	/**
	 * Replace any existing fragment with the requested stable graph fragment.
	 *
	 * @param string $url      Absolute authoritative URL.
	 * @param string $fragment Stable graph fragment.
	 */
	private function with_fragment( string $url, string $fragment ): string {
		$position = strpos( $url, '#' );

		if ( false !== $position ) {
			$url = substr( $url, 0, $position );
		}

		return $url . '#' . $fragment;
	}
}
