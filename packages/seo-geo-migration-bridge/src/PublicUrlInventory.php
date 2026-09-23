<?php
/**
 * Public URL inventory for migration baselines.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge;

/**
 * Enumerates public WordPress resources plus same-origin sitemap discoveries.
 */
final class PublicUrlInventory {
	/**
	 * Build the URL inventory.
	 *
	 * @param array<int, string> $sitemap_urls Same-origin URLs discovered from sitemaps.
	 * @param int                $limit        Maximum number of URLs to return.
	 * @return array{discovered:int,truncated:bool,urls:list<array<string,mixed>>}
	 */
	public function build( array $sitemap_urls = array(), int $limit = 500 ): array {
		$limit = max( 1, min( 5000, $limit ) );
		$rows  = array();

		$this->add_row( $rows, home_url( '/' ), 'home', 'home', null );

		$post_type_objects = get_post_types(
			array(
				'public'             => true,
				'publicly_queryable' => true,
			),
			'objects'
		);
		$post_types        = array_keys( $post_type_objects );

		if ( array() !== $post_types ) {
			$post_ids = get_posts(
				array(
					'post_type'        => $post_types,
					'post_status'      => 'publish',
					'numberposts'      => -1,
					'fields'           => 'ids',
					'orderby'          => 'ID',
					'order'            => 'ASC',
					'suppress_filters' => false,
				)
			);

			foreach ( $post_ids as $post_id ) {
				$post_id = (int) $post_id;
				$url     = get_permalink( $post_id );
				if ( false === $url ) {
					continue;
				}

				$this->add_row( $rows, $url, 'post', (string) get_post_type( $post_id ), $post_id );
			}
		}

		foreach ( $post_type_objects as $post_type ) {
			if ( false === $post_type->has_archive ) {
				continue;
			}

			$archive_url = get_post_type_archive_link( $post_type->name );
			if ( false !== $archive_url ) {
				$this->add_row( $rows, $archive_url, 'post-type-archive', $post_type->name . '-archive', null );
			}
		}

		foreach ( get_taxonomies( array( 'public' => true ), 'objects' ) as $taxonomy ) {
			$terms = get_terms(
				array(
					'taxonomy'   => $taxonomy->name,
					'hide_empty' => true,
				)
			);
			if ( is_wp_error( $terms ) ) {
				continue;
			}

			foreach ( $terms as $term ) {
				$term_url = get_term_link( $term );
				if ( is_wp_error( $term_url ) ) {
					continue;
				}

				$this->add_row( $rows, $term_url, 'term', 'taxonomy:' . $taxonomy->name, (int) $term->term_id );
			}
		}

		$user_ids = get_users( array( 'fields' => 'ID' ) );
		foreach ( $user_ids as $user_id ) {
			$user_id = (int) $user_id;
			if ( count_user_posts( $user_id, $post_types, true ) < 1 ) {
				continue;
			}

			$this->add_row( $rows, get_author_posts_url( $user_id ), 'author', 'author-archive', $user_id );
		}

		foreach ( $sitemap_urls as $url ) {
			$this->add_row( $rows, $url, 'sitemap', 'sitemap-discovered', null );
		}

		ksort( $rows, SORT_STRING );
		$all_rows = array_values( $rows );
		$count    = count( $all_rows );

		return array(
			'discovered' => $count,
			'truncated'  => $count > $limit,
			'urls'       => array_slice( $all_rows, 0, $limit ),
		);
	}

	/**
	 * Add or merge one URL row.
	 *
	 * @param array<string, array<string,mixed>> $rows         Existing rows by URL.
	 * @param string                             $url          Candidate public URL.
	 * @param string                             $source       Discovery source.
	 * @param string                             $content_type Resource/content classification.
	 * @param int|null                           $object_id    Related WordPress object ID, when known.
	 */
	private function add_row( array &$rows, string $url, string $source, string $content_type, ?int $object_id ): void {
		$normalized = $this->normalize_same_origin_url( $url );
		if ( null === $normalized ) {
			return;
		}

		if ( isset( $rows[ $normalized ] ) ) {
			$sources = $rows[ $normalized ]['sources'] ?? array();
			if ( is_array( $sources ) && ! in_array( $source, $sources, true ) ) {
				$sources[] = $source;
				sort( $sources );
				$rows[ $normalized ]['sources'] = $sources;
			}

			if ( 'sitemap-discovered' === ( $rows[ $normalized ]['content_type'] ?? '' ) && 'sitemap-discovered' !== $content_type ) {
				$rows[ $normalized ]['content_type'] = $content_type;
				$rows[ $normalized ]['object_id']    = $object_id;
			}
			return;
		}

		$rows[ $normalized ] = array(
			'url'          => $normalized,
			'sources'      => array( $source ),
			'content_type' => $content_type,
			'object_id'    => $object_id,
		);
	}

	/**
	 * Normalize a URL and reject anything outside the configured home origin.
	 *
	 * @param string $url Candidate URL.
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

		$port      = isset( $parts['port'] ) ? (int) $parts['port'] : $this->default_port( $scheme );
		$home_port = is_array( $home ) && isset( $home['port'] )
			? (int) $home['port']
			: $this->default_port( is_array( $home ) && isset( $home['scheme'] ) ? strtolower( (string) $home['scheme'] ) : 'http' );

		if ( $port !== $home_port ) {
			return null;
		}

		$path          = isset( $parts['path'] ) && '' !== (string) $parts['path'] ? (string) $parts['path'] : '/';
		$query         = isset( $parts['query'] ) && '' !== (string) $parts['query'] ? '?' . (string) $parts['query'] : '';
		$port_fragment = in_array(
			$port,
			array(
				80,
				443,
			),
			true
		) ? '' : ':' . $port;

		return $scheme . '://' . $host . $port_fragment . $path . $query;
	}

	/**
	 * Return the default port for a scheme.
	 *
	 * @param string $scheme URL scheme.
	 */
	private function default_port( string $scheme ): int {
		return 'https' === $scheme ? 443 : 80;
	}
}
