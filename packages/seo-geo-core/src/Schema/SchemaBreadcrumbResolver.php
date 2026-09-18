<?php
/**
 * Native BreadcrumbList Schema resolver.
 *
 * @package SeoGeoCore
 */

declare(strict_types=1);

namespace SeoGeo\Core\Schema;

use SeoGeo\Core\Seo\BreadcrumbResolver;

/**
 * Converts the reusable breadcrumb data authority into one Schema graph node.
 */
final class SchemaBreadcrumbResolver {
	/**
	 * Reusable breadcrumb data authority.
	 *
	 * @var BreadcrumbResolver
	 */
	private BreadcrumbResolver $breadcrumbs;

	/**
	 * Stable Schema node-ID generator.
	 *
	 * @var SchemaNodeIds
	 */
	private SchemaNodeIds $ids;

	/**
	 * Create the resolver.
	 *
	 * @param BreadcrumbResolver $breadcrumbs Reusable breadcrumb data authority.
	 * @param SchemaNodeIds      $ids         Stable Schema node-ID generator.
	 */
	public function __construct( BreadcrumbResolver $breadcrumbs, SchemaNodeIds $ids ) {
		$this->breadcrumbs = $breadcrumbs;
		$this->ids         = $ids;
	}

	/**
	 * Resolve a BreadcrumbList for the current public page.
	 *
	 * At least two items are required. Every non-final item must expose one
	 * absolute HTTP(S) URL. The final item may omit its URL, matching Google's
	 * documented BreadcrumbList contract for the containing page.
	 *
	 * @param string $canonical_url Authoritative current-page canonical URL.
	 * @return array{'@type': string, '@id': string, 'itemListElement': array<int, array<string, mixed>>}|null
	 */
	public function current( string $canonical_url ): ?array {
		$items = $this->breadcrumbs->resolve();

		if ( count( $items ) < 2 ) {
			return null;
		}

		$last_index = count( $items ) - 1;
		$elements   = array();

		foreach ( $items as $index => $item ) {
			$name = trim( $item['label'] );

			if ( '' === $name || ( $item['current'] && $index !== $last_index ) ) {
				return null;
			}

			if ( $index === $last_index && ! $item['current'] ) {
				return null;
			}

			$list_item = array(
				'@type'    => 'ListItem',
				'position' => $index + 1,
				'name'     => $name,
			);

			$url = $item['url'];
			if ( null !== $url ) {
				$url = trim( $url );

				if ( ! $this->is_absolute_http_url( $url ) ) {
					return null;
				}

				$list_item['item'] = $url;
			} elseif ( $index !== $last_index ) {
				return null;
			}

			$elements[] = $list_item;
		}

		return array(
			'@type'           => 'BreadcrumbList',
			'@id'             => $this->ids->breadcrumb_list( $canonical_url ),
			'itemListElement' => $elements,
		);
	}

	/**
	 * Accept only absolute HTTP(S) URLs for breadcrumb graph items.
	 *
	 * @param string $url Candidate breadcrumb URL.
	 */
	private function is_absolute_http_url( string $url ): bool {
		if ( '' === $url ) {
			return false;
		}

		$scheme = wp_parse_url( $url, PHP_URL_SCHEME );
		$host   = wp_parse_url( $url, PHP_URL_HOST );

		return is_string( $scheme )
			&& is_string( $host )
			&& '' !== $host
			&& in_array( strtolower( $scheme ), array( 'http', 'https' ), true );
	}
}
