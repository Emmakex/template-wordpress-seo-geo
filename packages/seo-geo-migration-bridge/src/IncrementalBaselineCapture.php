<?php
/**
 * Resumable SEO/GEO public baseline capture.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge;

use SeoGeo\MigrationBridge\Http\HttpClientInterface;
use SeoGeo\MigrationBridge\Http\WordPressHttpClient;

/**
 * Captures one public baseline in small durable steps.
 */
final class IncrementalBaselineCapture {
	/**
	 * Maximum public resources included in one baseline.
	 */
	private const DEFAULT_LIMIT = 500;

	/**
	 * Public pages fetched during one request.
	 */
	private const PAGE_BATCH_SIZE = 2;

	/**
	 * URL inventory service.
	 *
	 * @var PublicUrlInventory
	 */
	private PublicUrlInventory $url_inventory;

	/**
	 * HTML extractor.
	 *
	 * @var HtmlSnapshotExtractor
	 */
	private HtmlSnapshotExtractor $extractor;

	/**
	 * Final baseline store.
	 *
	 * @var BaselineSnapshotStore
	 */
	private BaselineSnapshotStore $baseline_store;

	/**
	 * Progress store.
	 *
	 * @var IncrementalBaselineStore
	 */
	private IncrementalBaselineStore $progress_store;

	/**
	 * Anonymous public HTTP transport.
	 *
	 * @var HttpClientInterface
	 */
	private HttpClientInterface $http;

	/**
	 * Construct the incremental capture service.
	 *
	 * @param PublicUrlInventory|null       $url_inventory  Optional URL inventory.
	 * @param HtmlSnapshotExtractor|null    $extractor      Optional HTML extractor.
	 * @param BaselineSnapshotStore|null    $baseline_store Optional final baseline store.
	 * @param IncrementalBaselineStore|null $progress_store Optional progress store.
	 * @param HttpClientInterface|null      $http           Optional HTTP transport.
	 */
	public function __construct(
		?PublicUrlInventory $url_inventory = null,
		?HtmlSnapshotExtractor $extractor = null,
		?BaselineSnapshotStore $baseline_store = null,
		?IncrementalBaselineStore $progress_store = null,
		?HttpClientInterface $http = null
	) {
		$this->url_inventory  = $url_inventory ?? new PublicUrlInventory();
		$this->extractor      = $extractor ?? new HtmlSnapshotExtractor();
		$this->baseline_store = $baseline_store ?? new BaselineSnapshotStore();
		$this->progress_store = $progress_store ?? new IncrementalBaselineStore();
		$this->http           = $http ?? new WordPressHttpClient();
	}

	/**
	 * Advance the capture by one bounded unit of work.
	 *
	 * @return array{status:string,phase:string,processed:int,total:int,percent:int,error:string|null}
	 */
	public function advance(): array {
		if ( is_array( $this->baseline_store->latest() ) ) {
			return $this->result( 'complete', 'complete', 1, 1, null );
		}

		$state = $this->progress_store->latest();
		if ( ! is_array( $state ) || 'complete' === ( $state['status'] ?? null ) ) {
			$state = $this->initial_state();
		}

		if ( 'error' === ( $state['status'] ?? null ) ) {
			$state['status'] = 'running';
			$state['error']  = null;
		}

		try {
			$phase = is_string( $state['phase'] ?? null ) ? $state['phase'] : 'robots';

			if ( 'robots' === $phase ) {
				$state = $this->advance_robots( $state );
			} elseif ( 'sitemaps' === $phase ) {
				$state = $this->advance_sitemap( $state );
			} elseif ( 'inventory' === $phase ) {
				$state = $this->prepare_inventory( $state );
			} elseif ( 'pages' === $phase ) {
				$state = $this->advance_pages( $state );
			} elseif ( 'finalize' === $phase ) {
				$state = $this->finalize( $state );
			}
		} catch ( \Throwable $throwable ) {
			$state['status'] = 'error';
			$state['error']  = sanitize_key( $throwable->getMessage() );
		}

		$this->progress_store->save( $state );

		return $this->status_from_state( $state );
	}

	/**
	 * Return bounded progress without advancing work.
	 *
	 * @return array{status:string,phase:string,processed:int,total:int,percent:int,error:string|null}
	 */
	public function status(): array {
		if ( is_array( $this->baseline_store->latest() ) ) {
			return $this->result( 'complete', 'complete', 1, 1, null );
		}

		$state = $this->progress_store->latest();
		if ( ! is_array( $state ) ) {
			return $this->result( 'idle', 'idle', 0, 0, null );
		}

		return $this->status_from_state( $state );
	}

	/**
	 * Initial durable capture state.
	 *
	 * @return array<string,mixed>
	 */
	private function initial_state(): array {
		return array(
			'schema_version'   => 1,
			'status'           => 'running',
			'phase'            => 'robots',
			'started_at'       => gmdate( DATE_ATOM ),
			'updated_at'       => gmdate( DATE_ATOM ),
			'limit'            => self::DEFAULT_LIMIT,
			'robots_txt'       => array(
				'url'          => home_url( '/robots.txt' ),
				'status'       => 0,
				'sha256'       => null,
				'sitemap_urls' => array(),
			),
			'sitemap_queue'    => array(),
			'sitemap_seen'     => array(),
			'sitemaps'         => array(),
			'page_urls'        => array(),
			'inventory'        => null,
			'cursor'           => 0,
			'pages'            => array(),
			'redirects'        => array(),
			'status_counts'    => array(),
			'indexable_count'  => 0,
			'noindex_count'    => 0,
			'request_failures' => 0,
			'error'            => null,
		);
	}

	/**
	 * Capture robots.txt only, then seed the sitemap queue.
	 *
	 * @param array<string,mixed> $state Current state.
	 * @return array<string,mixed>
	 */
	private function advance_robots( array $state ): array {
		$robots_url = home_url( '/robots.txt' );
		$response   = $this->http->get( $robots_url );
		$declared   = array();

		if ( 200 === $response['status'] ) {
			$lines = preg_split( '/\r?\n/', $response['body'] );
			foreach ( is_array( $lines ) ? $lines : array() as $line ) {
				if ( 1 !== preg_match( '/^\s*Sitemap\s*:\s*(\S+)\s*$/i', $line, $match ) ) {
					continue;
				}

				$normalized = $this->normalize_same_origin_url( $match[1] );
				if ( null !== $normalized ) {
					$declared[] = $normalized;
				}
			}
		}

		$declared = array_values( array_unique( $declared ) );
		sort( $declared );

		$state['robots_txt'] = array(
			'url'          => $robots_url,
			'status'       => $response['status'],
			'sha256'       => '' !== $response['body'] ? hash( 'sha256', $response['body'] ) : null,
			'sitemap_urls' => $declared,
		);

		$candidates = array_merge(
			$declared,
			array(
				home_url( '/wp-sitemap.xml' ),
				home_url( '/sitemap.xml' ),
				home_url( '/sitemap_index.xml' ),
			)
		);

		foreach ( $candidates as $candidate ) {
			$this->enqueue_sitemap( $state, $candidate, 0 );
		}

		$state['phase'] = 'sitemaps';

		return $state;
	}

	/**
	 * Fetch at most one sitemap resource.
	 *
	 * @param array<string,mixed> $state Current state.
	 * @return array<string,mixed>
	 */
	private function advance_sitemap( array $state ): array {
		$queue = is_array( $state['sitemap_queue'] ?? null ) ? $state['sitemap_queue'] : array();

		if ( array() === $queue ) {
			$state['phase'] = 'inventory';
			return $state;
		}

		$item = array_shift( $queue );

		$state['sitemap_queue'] = $queue;

		if ( ! is_array( $item ) || ! is_string( $item['url'] ?? null ) ) {
			return $state;
		}

		$url = $item['url'];

		$depth = isset( $item['depth'] ) ? (int) $item['depth'] : 0;

		$seen = is_array( $state['sitemap_seen'] ?? null ) ? $state['sitemap_seen'] : array();
		if ( true === ( $seen[ $url ] ?? false ) ) {
			return $state;
		}
		$seen[ $url ] = true;

		$state['sitemap_seen'] = $seen;

		$response = $this->http->get( $url );

		$locations = 200 === $response['status'] ? $this->extract_xml_locations( $response['body'] ) : array();

		$sitemaps = is_array( $state['sitemaps'] ?? null ) ? $state['sitemaps'] : array();

		$sitemaps[] = array(
			'url'            => $url,
			'status'         => $response['status'],
			'sha256'         => '' !== $response['body'] ? hash( 'sha256', $response['body'] ) : null,
			'location_count' => count( $locations ),
			'error'          => $response['error'],
		);
		$state['sitemaps'] = $sitemaps;

		foreach ( $locations as $location ) {
			$normalized = $this->normalize_same_origin_url( $location );
			if ( null === $normalized ) {
				continue;
			}

			if ( $this->looks_like_sitemap( $normalized ) && $depth < 2 ) {
				$this->enqueue_sitemap( $state, $normalized, $depth + 1 );
				continue;
			}

			$page_urls                = is_array( $state['page_urls'] ?? null ) ? $state['page_urls'] : array();
			$page_urls[ $normalized ] = true;
			$state['page_urls']       = $page_urls;
		}

		if ( array() === ( $state['sitemap_queue'] ?? array() ) ) {
			$state['phase'] = 'inventory';
		}

		return $state;
	}

	/**
	 * Build the local/public inventory after discovery.
	 *
	 * @param array<string,mixed> $state Current state.
	 * @return array<string,mixed>
	 */
	private function prepare_inventory( array $state ): array {
		$page_urls = is_array( $state['page_urls'] ?? null ) ? array_keys( $state['page_urls'] ) : array();

		$limit = isset( $state['limit'] ) ? (int) $state['limit'] : self::DEFAULT_LIMIT;

		$state['inventory'] = $this->url_inventory->build( $page_urls, $limit );
		$state['cursor']    = 0;
		$state['phase']     = 'pages';

		return $state;
	}

	/**
	 * Fetch a very small page batch and persist progress afterwards.
	 *
	 * @param array<string,mixed> $state Current state.
	 * @return array<string,mixed>
	 */
	private function advance_pages( array $state ): array {
		$inventory = is_array( $state['inventory'] ?? null ) ? $state['inventory'] : array();

		$rows = is_array( $inventory['urls'] ?? null ) ? $inventory['urls'] : array();

		$cursor = isset( $state['cursor'] ) ? (int) $state['cursor'] : 0;

		$end = min( count( $rows ), $cursor + self::PAGE_BATCH_SIZE );

		for ( $index = $cursor; $index < $end; ++$index ) {
			$row = is_array( $rows[ $index ] ?? null ) ? $rows[ $index ] : array();

			$state = $this->capture_resource( $state, $row );
		}

		$state['cursor'] = $end;

		if ( $end >= count( $rows ) ) {
			$state['phase'] = 'finalize';
		}

		return $state;
	}

	/**
	 * Capture one public resource without retaining its raw body.
	 *
	 * @param array<string,mixed> $state Current state.
	 * @param array<string,mixed> $row   Resource row.
	 * @return array<string,mixed>
	 */
	private function capture_resource( array $state, array $row ): array {
		$url = is_string( $row['url'] ?? null ) ? $row['url'] : '';

		$response = $this->http->get( $url );

		$signals = $this->extractor->extract(
			$url,
			$response['status'],
			$response['body'],
			$response['headers']
		);

		$status_counts = is_array( $state['status_counts'] ?? null ) ? $state['status_counts'] : array();

		$status_key = (string) $response['status'];
		$status_counts[ $status_key ] = ( $status_counts[ $status_key ] ?? 0 ) + 1;
		$state['status_counts']        = $status_counts;

		$indexability = $signals['indexability'] ?? null;
		if ( is_array( $indexability ) ) {
			if ( true === ( $indexability['indexable'] ?? null ) ) {
				$state['indexable_count'] = (int) ( $state['indexable_count'] ?? 0 ) + 1;
			} elseif ( 'noindex' === ( $indexability['state'] ?? null ) ) {
				$state['noindex_count'] = (int) ( $state['noindex_count'] ?? 0 ) + 1;
			}
		}

		if ( null !== $response['error'] ) {
			$state['request_failures'] = (int) ( $state['request_failures'] ?? 0 ) + 1;
		}

		if (
			$response['status'] >= 300
			&& $response['status'] < 400
			&& '' !== $response['headers']['location']
		) {
			$redirects = is_array( $state['redirects'] ?? null ) ? $state['redirects'] : array();

			$redirects[] = array(
				'from'   => $url,
				'to'     => $response['headers']['location'],
				'status' => $response['status'],
			);
			$state['redirects'] = $redirects;
		}

		$pages = is_array( $state['pages'] ?? null ) ? $state['pages'] : array();

		$pages[] = array_merge(
			$row,
			$signals,
			array(
				'request_error' => $response['error'],
			)
		);
		$state['pages'] = $pages;

		return $state;
	}

	/**
	 * Build and persist the final baseline.
	 *
	 * @param array<string,mixed> $state Current state.
	 * @return array<string,mixed>
	 */
	private function finalize( array $state ): array {
		$inventory = is_array( $state['inventory'] ?? null ) ? $state['inventory'] : array();

		$status_counts = is_array( $state['status_counts'] ?? null ) ? $state['status_counts'] : array();

		$redirects = is_array( $state['redirects'] ?? null ) ? $state['redirects'] : array();

		$sitemaps = is_array( $state['sitemaps'] ?? null ) ? $state['sitemaps'] : array();

		ksort( $status_counts, SORT_NATURAL );
		usort(
			$redirects,
			static fn( array $left, array $right ): int => strcmp( (string) $left['from'], (string) $right['from'] )
		);
		usort(
			$sitemaps,
			static fn( array $left, array $right ): int => strcmp( (string) $left['url'], (string) $right['url'] )
		);

		$snapshot = array(
			'schema_version' => 1,
			'kind'           => 'seo-geo-public-baseline',
			'generated_at'   => gmdate( DATE_ATOM ),
			'site'           => array(
				'home_url' => home_url( '/' ),
				'locale'   => get_locale(),
			),
			'crawl'          => array(
				'limit'            => isset( $state['limit'] ) ? (int) $state['limit'] : self::DEFAULT_LIMIT,
				'discovered'       => isset( $inventory['discovered'] ) ? (int) $inventory['discovered'] : 0,
				'captured'         => count( is_array( $state['pages'] ?? null ) ? $state['pages'] : array() ),
				'truncated'        => true === ( $inventory['truncated'] ?? false ),
				'indexable_count'  => (int) ( $state['indexable_count'] ?? 0 ),
				'noindex_count'    => (int) ( $state['noindex_count'] ?? 0 ),
				'request_failures' => (int) ( $state['request_failures'] ?? 0 ),
				'status_counts'    => $status_counts,
				'pages'            => is_array( $state['pages'] ?? null ) ? $state['pages'] : array(),
			),
			'sitemaps'       => array(
				'robots_txt'   => is_array( $state['robots_txt'] ?? null ) ? $state['robots_txt'] : array(),
				'entries'      => $sitemaps,
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
				'incremental_capture'        => true,
				'resumable_progress'         => true,
			),
		);

		$storage = $this->baseline_store->save( $snapshot, false );
		if ( ! $storage['saved'] && 'baseline-exists' !== $storage['reason'] ) {
			$state['status'] = 'error';
			$state['error']  = 'storage-write-failed';
			return $state;
		}

		$state['status'] = 'complete';
		$state['phase']  = 'complete';
		$state['error']  = null;

		return $state;
	}

	/**
	 * Add one same-origin sitemap to the queue unless already queued/seen.
	 *
	 * @param array<string,mixed> $state Current state.
	 * @param string              $url   Candidate sitemap URL.
	 * @param int                 $depth Discovery depth.
	 */
	private function enqueue_sitemap( array &$state, string $url, int $depth ): void {
		$normalized = $this->normalize_same_origin_url( $url );
		if ( null === $normalized ) {
			return;
		}

		$seen = is_array( $state['sitemap_seen'] ?? null ) ? $state['sitemap_seen'] : array();
		if ( true === ( $seen[ $normalized ] ?? false ) ) {
			return;
		}

		$queue = is_array( $state['sitemap_queue'] ?? null ) ? $state['sitemap_queue'] : array();
		foreach ( $queue as $queued ) {
			if ( is_array( $queued ) && ( $queued['url'] ?? null ) === $normalized ) {
				return;
			}
		}

		$queue[] = array(
			'url'   => $normalized,
			'depth' => $depth,
		);
		$state['sitemap_queue'] = $queue;
	}

	/**
	 * Convert persistent state to bounded operator progress.
	 *
	 * @param array<string,mixed> $state Current state.
	 * @return array{status:string,phase:string,processed:int,total:int,percent:int,error:string|null}
	 */
	private function status_from_state( array $state ): array {
		$status = is_string( $state['status'] ?? null ) ? $state['status'] : 'running';

		$phase = is_string( $state['phase'] ?? null ) ? $state['phase'] : 'robots';

		if ( 'pages' === $phase || 'finalize' === $phase || 'complete' === $phase ) {
			$inventory = is_array( $state['inventory'] ?? null ) ? $state['inventory'] : array();

			$rows = is_array( $inventory['urls'] ?? null ) ? $inventory['urls'] : array();

			$total = count( $rows );

			$processed = min( $total, isset( $state['cursor'] ) ? (int) $state['cursor'] : 0 );
		} else {
			$queue = is_array( $state['sitemap_queue'] ?? null ) ? $state['sitemap_queue'] : array();

			$seen = is_array( $state['sitemap_seen'] ?? null ) ? $state['sitemap_seen'] : array();

			$processed = count( array_filter( $seen ) );

			$total = $processed + count( $queue );
		}

		$error = is_string( $state['error'] ?? null ) && '' !== $state['error'] ? $state['error'] : null;

		return $this->result( $status, $phase, $processed, $total, $error );
	}

	/**
	 * Build one normalized result.
	 *
	 * @param string      $status    Capture status.
	 * @param string      $phase     Current capture phase.
	 * @param int         $processed Processed units.
	 * @param int         $total     Total known units.
	 * @param string|null $error     Bounded error code.
	 * @return array{status:string,phase:string,processed:int,total:int,percent:int,error:string|null}
	 */
	private function result( string $status, string $phase, int $processed, int $total, ?string $error ): array {
		$percent = 0 < $total ? (int) floor( ( $processed / $total ) * 100 ) : 0;
		if ( 'complete' === $status ) {
			$percent = 100;
		}

		return array(
			'status'    => $status,
			'phase'     => $phase,
			'processed' => max( 0, $processed ),
			'total'     => max( 0, $total ),
			'percent'   => max( 0, min( 100, $percent ) ),
			'error'     => $error,
		);
	}

	/**
	 * Extract XML sitemap <loc> values.
	 *
	 * @param string $xml Sitemap body.
	 * @return list<string>
	 */
	private function extract_xml_locations( string $xml ): array {
		if ( '' === trim( $xml ) ) {
			return array();
		}

		if ( false === preg_match_all( '/<loc\b[^>]*>(.*?)<\/loc>/is', $xml, $matches ) ) {
			return array();
		}

		$locations = array();
		foreach ( $matches[1] as $location ) {
			$value = trim( wp_strip_all_tags( html_entity_decode( $location, ENT_QUOTES | ENT_XML1, 'UTF-8' ) ) );
			if ( '' !== $value ) {
				$locations[] = $value;
			}
		}

		return array_values( array_unique( $locations ) );
	}

	/**
	 * Heuristic used only to follow sitemap indexes.
	 *
	 * @param string $url Candidate URL.
	 */
	private function looks_like_sitemap( string $url ): bool {
		$path = strtolower( (string) ( wp_parse_url( $url, PHP_URL_PATH ) ?? '' ) );

		return str_contains( $path, 'sitemap' ) && str_ends_with( $path, '.xml' );
	}

	/**
	 * Normalize one URL and enforce the WordPress home origin.
	 *
	 * @param string $url Candidate URL.
	 */
	private function normalize_same_origin_url( string $url ): ?string {
		$url              = html_entity_decode( trim( $url ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$home_url         = home_url( '/' );
		$scheme_value     = wp_parse_url( $url, PHP_URL_SCHEME );
		$host_value       = wp_parse_url( $url, PHP_URL_HOST );
		$port_value       = wp_parse_url( $url, PHP_URL_PORT );
		$path_value       = wp_parse_url( $url, PHP_URL_PATH );
		$query_value      = wp_parse_url( $url, PHP_URL_QUERY );
		$home_scheme      = wp_parse_url( $home_url, PHP_URL_SCHEME );
		$home_host_value  = wp_parse_url( $home_url, PHP_URL_HOST );
		$home_port_value  = wp_parse_url( $home_url, PHP_URL_PORT );
		$scheme           = is_string( $scheme_value ) ? strtolower( $scheme_value ) : '';
		$host             = is_string( $host_value ) ? strtolower( $host_value ) : '';
		$home_host        = is_string( $home_host_value ) ? strtolower( $home_host_value ) : '';
		$home_scheme_name = is_string( $home_scheme ) ? strtolower( $home_scheme ) : 'http';

		if ( ! in_array( $scheme, array( 'http', 'https' ), true ) || '' === $host || $host !== $home_host ) {
			return null;
		}

		$port      = is_int( $port_value ) ? $port_value : ( 'https' === $scheme ? 443 : 80 );
		$home_port = is_int( $home_port_value ) ? $home_port_value : ( 'https' === $home_scheme_name ? 443 : 80 );
		if ( $port !== $home_port ) {
			return null;
		}

		$path          = is_string( $path_value ) && '' !== $path_value ? $path_value : '/';
		$query         = is_string( $query_value ) && '' !== $query_value ? '?' . $query_value : '';
		$port_fragment = in_array( $port, array( 80, 443 ), true ) ? '' : ':' . $port;

		return $scheme . '://' . $host . $port_fragment . $path . $query;
	}
}
