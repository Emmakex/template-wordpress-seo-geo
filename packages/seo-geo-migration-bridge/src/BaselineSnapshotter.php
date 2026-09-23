<?php
/**
 * SEO/GEO public-output baseline snapshot service.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge;

use SeoGeo\MigrationBridge\Http\HttpClientInterface;
use SeoGeo\MigrationBridge\Http\WordPressHttpClient;

/**
 * Captures a same-origin, anonymous, comparison-oriented migration baseline.
 */
final class BaselineSnapshotter {
	/**
	 * Public URL inventory service.
	 *
	 * @var PublicUrlInventory
	 */
	private PublicUrlInventory $url_inventory;

	/**
	 * HTML signal extractor.
	 *
	 * @var HtmlSnapshotExtractor
	 */
	private HtmlSnapshotExtractor $extractor;

	/**
	 * Baseline persistence service.
	 *
	 * @var BaselineSnapshotStore
	 */
	private BaselineSnapshotStore $store;

	/**
	 * Anonymous HTTP client.
	 *
	 * @var HttpClientInterface
	 */
	private HttpClientInterface $http;

	/**
	 * Construct the snapshot service.
	 */
	public function __construct(
		?PublicUrlInventory $url_inventory = null,
		?HtmlSnapshotExtractor $extractor = null,
		?BaselineSnapshotStore $store = null,
		?HttpClientInterface $http = null
	) {
		$this->url_inventory = $url_inventory ?? new PublicUrlInventory();
		$this->extractor     = $extractor ?? new HtmlSnapshotExtractor();
		$this->store         = $store ?? new BaselineSnapshotStore();
		$this->http          = $http ?? new WordPressHttpClient();
	}

	/**
	 * Capture the current public SEO/GEO baseline.
	 *
	 * @param list<string> $seed_urls Optional explicit same-origin public URLs.
	 * @return array<string,mixed>
	 */
	public function capture( array $seed_urls = array(), int $limit = 500 ): array {
		$discovery = $this->discover_sitemaps();
		$inventory = $this->url_inventory->build(
			array_merge( $discovery['page_urls'], $seed_urls ),
			$limit
		);

		$pages            = array();
		$redirects        = array();
		$status_counts    = array();
		$indexable_count  = 0;
		$noindex_count    = 0;
		$request_failures = 0;

		foreach ( $inventory['urls'] as $resource ) {
			$url      = isset( $resource['url'] ) ? (string) $resource['url'] : '';
			$response = $this->http->get( $url );
			$signals  = $this->extractor->extract(
				$url,
				$response['status'],
				$response['body'],
				$response['headers']
			);

			$status_key = (string) $response['status'];
			$status_counts[ $status_key ] = ( $status_counts[ $status_key ] ?? 0 ) + 1;

			$indexability = $signals['indexability'] ?? null;
			if ( is_array( $indexability ) ) {
				if ( true === ( $indexability['indexable'] ?? null ) ) {
					++$indexable_count;
				} elseif ( 'noindex' === ( $indexability['state'] ?? null ) ) {
					++$noindex_count;
				}
			}

			if ( null !== $response['error'] ) {
				++$request_failures;
			}

			if (
				$response['status'] >= 300
				&& $response['status'] < 400
				&& '' !== $response['headers']['location']
			) {
				$redirects[] = array(
					'from'   => $url,
					'to'     => $response['headers']['location'],
					'status' => $response['status'],
				);
			}

			$pages[] = array_merge(
				$resource,
				$signals,
				array(
					'request_error' => $response['error'],
				)
			);
		}

		ksort( $status_counts, SORT_NATURAL );
		usort(
			$redirects,
			static fn( array $left, array $right ): int => strcmp( (string) $left['from'], (string) $right['from'] )
		);

		return array(
			'schema_version' => 1,
			'kind'           => 'seo-geo-public-baseline',
			'generated_at'   => gmdate( DATE_ATOM ),
			'site'           => array(
				'home_url' => home_url( '/' ),
				'locale'   => get_locale(),
			),
			'crawl'          => array(
				'limit'            => max( 1, min( 5000, $limit ) ),
				'discovered'       => $inventory['discovered'],
				'captured'         => count( $pages ),
				'truncated'        => $inventory['truncated'],
				'indexable_count'  => $indexable_count,
				'noindex_count'    => $noindex_count,
				'request_failures' => $request_failures,
				'status_counts'    => $status_counts,
				'pages'            => $pages,
			),
			'sitemaps'       => array(
				'robots_txt'   => $discovery['robots_txt'],
				'entries'      => $discovery['sitemaps'],
				'completeness' => 'same-origin-discovered',
			),
			'redirects'      => array(
				'mode'    => 'observed-during-baseline',
				'entries' => $redirects,
			),
			'safety'         => array(
				'same_origin_only'           => true,
				'authenticated_requests'     => false,
				'private_content_collected'  => false,
				'body_content_persisted'     => false,
				'legacy_output_is_authority' => false,
			),
		);
	}

	/**
	 * Persist an already captured baseline.
	 *
	 * @param array<string,mixed> $snapshot Baseline snapshot.
	 * @return array{saved:bool,id:string|null,saved_at:string|null,sha256:string|null,replaced:bool,reason:string|null}
	 */
	public function persist( array $snapshot, bool $replace = false ): array {
		return $this->store->save( $snapshot, $replace );
	}

	/**
	 * Return the persisted baseline envelope.
	 *
	 * @return array<string,mixed>|null
	 */
	public function latest(): ?array {
		return $this->store->latest();
	}

	/**
	 * Discover same-origin sitemap resources and page URLs.
	 *
	 * @return array{
	 *     robots_txt:array{url:string,status:int,sha256:string|null,sitemap_urls:list<string>},
	 *     sitemaps:list<array{url:string,status:int,sha256:string|null,location_count:int,error:string|null}>,
	 *     page_urls:list<string>
	 * }
	 */
	private function discover_sitemaps(): array {
		$robots_url      = home_url( '/robots.txt' );
		$robots_response = $this->http->get( $robots_url );
		$robots_sitemaps = array();

		if ( 200 === $robots_response['status'] ) {
			foreach ( preg_split( '/\r?\n/', $robots_response['body'] ) ?: array() as $line ) {
				if ( 1 !== preg_match( '/^\s*Sitemap\s*:\s*(\S+)\s*$/i', $line, $match ) ) {
					continue;
				}

				$normalized = $this->normalize_same_origin_url( $match[1] );
				if ( null !== $normalized ) {
					$robots_sitemaps[] = $normalized;
				}
			}
		}

		$candidates = array_merge(
			$robots_sitemaps,
			array(
				home_url( '/wp-sitemap.xml' ),
				home_url( '/sitemap.xml' ),
				home_url( '/sitemap_index.xml' ),
			)
		);

		$queue = array();
		foreach ( $candidates as $candidate ) {
			$normalized = $this->normalize_same_origin_url( $candidate );
			if ( null !== $normalized ) {
				$queue[ $normalized ] = 0;
			}
		}

		$sitemaps = array();
		$page_urls = array();
		$visited = array();

		while ( array() !== $queue && count( $visited ) < 50 ) {
			$url   = (string) array_key_first( $queue );
			$depth = (int) $queue[ $url ];
			unset( $queue[ $url ] );

			if ( isset( $visited[ $url ] ) ) {
				continue;
			}
			$visited[ $url ] = true;

			$response  = $this->http->get( $url );
			$locations = 200 === $response['status'] ? $this->extract_xml_locations( $response['body'] ) : array();

			$sitemaps[] = array(
				'url'            => $url,
				'status'         => $response['status'],
				'sha256'         => '' !== $response['body'] ? hash( 'sha256', $response['body'] ) : null,
				'location_count' => count( $locations ),
				'error'          => $response['error'],
			);

			foreach ( $locations as $location ) {
				$normalized = $this->normalize_same_origin_url( $location );
				if ( null === $normalized ) {
					continue;
				}

				if ( $this->looks_like_sitemap( $normalized ) && $depth < 2 ) {
					if ( ! isset( $visited[ $normalized ] ) ) {
						$queue[ $normalized ] = $depth + 1;
					}
					continue;
				}

				$page_urls[] = $normalized;
			}
		}

		$robots_sitemaps = array_values( array_unique( $robots_sitemaps ) );
		sort( $robots_sitemaps );
		$page_urls = array_values( array_unique( $page_urls ) );
		sort( $page_urls );
		usort(
			$sitemaps,
			static fn( array $left, array $right ): int => strcmp( $left['url'], $right['url'] )
		);

		return array(
			'robots_txt' => array(
				'url'          => $robots_url,
				'status'       => $robots_response['status'],
				'sha256'       => '' !== $robots_response['body'] ? hash( 'sha256', $robots_response['body'] ) : null,
				'sitemap_urls' => $robots_sitemaps,
			),
			'sitemaps'    => $sitemaps,
			'page_urls'   => $page_urls,
		);
	}

	/**
	 * Extract XML <loc> values without requiring a sitemap provider adapter.
	 *
	 * @return list<string>
	 */
	private function extract_xml_locations( string $xml ): array {
		if ( '' === trim( $xml ) ) {
			return array();
		}

		if ( false === preg_match_all( '/<loc\b[^>]*>(.*?)<\/loc>/is', $xml, $matches ) || ! isset( $matches[1] ) ) {
			return array();
		}

		$locations = array();
		foreach ( $matches[1] as $location ) {
			if ( ! is_string( $location ) ) {
				continue;
			}

			$value = trim( wp_strip_all_tags( html_entity_decode( $location, ENT_QUOTES | ENT_XML1, 'UTF-8' ) ) );
			if ( '' !== $value ) {
				$locations[] = $value;
			}
		}

		return array_values( array_unique( $locations ) );
	}

	/**
	 * Heuristic used only to follow sitemap indexes, never to classify content.
	 */
	private function looks_like_sitemap( string $url ): bool {
		$path = strtolower( (string) ( wp_parse_url( $url, PHP_URL_PATH ) ?? '' ) );
		return str_contains( $path, 'sitemap' ) && str_ends_with( $path, '.xml' );
	}

	/**
	 * Normalize and enforce the configured WordPress home origin.
	 */
	private function normalize_same_origin_url( string $url ): ?string {
		$url       = html_entity_decode( trim( $url ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$parts     = wp_parse_url( $url );
		$home      = wp_parse_url( home_url( '/' ) );
		$scheme    = is_array( $parts ) && isset( $parts['scheme'] ) ? strtolower( (string) $parts['scheme'] ) : '';
		$host      = is_array( $parts ) && isset( $parts['host'] ) ? strtolower( (string) $parts['host'] ) : '';
		$home_host = is_array( $home ) && isset( $home['host'] ) ? strtolower( (string) $home['host'] ) : '';

		if ( ! in_array( $scheme, array( 'http', 'https' ), true ) || '' === $host || $host !== $home_host ) {
			return null;
		}

		$port      = isset( $parts['port'] ) ? (int) $parts['port'] : ( 'https' === $scheme ? 443 : 80 );
		$home_port = is_array( $home ) && isset( $home['port'] )
			? (int) $home['port']
			: ( is_array( $home ) && isset( $home['scheme'] ) && 'https' === strtolower( (string) $home['scheme'] ) ? 443 : 80 );
		if ( $port !== $home_port ) {
			return null;
		}

		$path          = isset( $parts['path'] ) && '' !== (string) $parts['path'] ? (string) $parts['path'] : '/';
		$query         = isset( $parts['query'] ) && '' !== (string) $parts['query'] ? '?' . (string) $parts['query'] : '';
		$port_fragment = in_array( $port, array( 80, 443 ), true ) ? '' : ':' . $port;

		return $scheme . '://' . $host . $port_fragment . $path . $query;
	}
}
