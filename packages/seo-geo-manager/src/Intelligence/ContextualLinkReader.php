<?php
/**
 * Bounded contextual-link discovery for supported WordPress content.
 *
 * @package SeoGeoManager
 */

declare(strict_types=1);

namespace SeoGeo\Manager\Intelligence;

use SeoGeo\Manager\Support\ContentFingerprint;
use WP_Error;
use WP_Post;
use WP_Query;

final class ContextualLinkReader {
	private const MAX_RESOURCES = 100;
	private const MAX_LINKS     = 250;

	/**
	 * Read bounded contextual links without returning raw post bodies.
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	public static function read( int $source_id = 0 ) {
		$sources = self::sources( $source_id );
		if ( is_wp_error( $sources ) ) {
			return $sources;
		}

		$items     = array();
		$truncated = false;
		foreach ( $sources['items'] as $post ) {
			if ( ! $post instanceof WP_Post || ! current_user_can( 'edit_post', $post->ID ) ) {
				continue;
			}

			$source = self::source_summary( $post );
			$links  = self::extract_links( (string) $post->post_content );
			foreach ( $links as $ordinal => $link ) {
				if ( self::MAX_LINKS <= count( $items ) ) {
					$truncated = true;
					break 2;
				}

				$href    = $link['href'];
				$text    = $link['text'];
				$items[] = array_merge(
					array(
						'edge_id'     => hash( 'sha256', $post->ID . '|' . $ordinal . '|' . $href . '|' . $text ),
						'ordinal'     => $ordinal,
						'source'      => $source,
						'anchor_text' => $text,
						'href'        => $href,
					),
					self::classify_target( $href, (string) $source['permalink'] )
				);
			}
		}

		return array(
			'schema_version' => 1,
			'bounded'        => true,
			'source_filter'  => 0 < $source_id ? $source_id : null,
			'resource_count' => count( $sources['items'] ),
			'link_count'     => count( $items ),
			'truncated'      => $truncated || $sources['truncated'],
			'limits'         => array(
				'max_resources' => self::MAX_RESOURCES,
				'max_links'     => self::MAX_LINKS,
			),
			'items'          => $items,
			'policy'         => array(
				'raw_post_content_returned'   => false,
				'mutation_supported'          => false,
				'targets_verified_current'    => true,
				'environment_leakage_flagged' => true,
			),
		);
	}

	/**
	 * @return array{items:list<WP_Post>,truncated:bool}|WP_Error
	 */
	private static function sources( int $source_id ) {
		if ( 0 < $source_id ) {
			$post = get_post( $source_id );
			if ( ! $post instanceof WP_Post || ! in_array( $post->post_type, array( 'page', 'post' ), true ) ) {
				return new WP_Error(
					'seo_geo_manager_contextual_source_not_found',
					'The contextual-link source page/post was not found.',
					array( 'status' => 404 )
				);
			}
			if ( ! current_user_can( 'edit_post', $post->ID ) ) {
				return new WP_Error(
					'seo_geo_manager_contextual_source_forbidden',
					'You cannot inspect contextual links for this resource.',
					array( 'status' => 403 )
				);
			}

			return array(
				'items'     => array( $post ),
				'truncated' => false,
			);
		}

		$query = new WP_Query(
			array(
				'post_type'              => array( 'page', 'post' ),
				'post_status'            => array( 'publish', 'future', 'draft', 'pending', 'private' ),
				'posts_per_page'         => self::MAX_RESOURCES,
				'orderby'                => 'ID',
				'order'                  => 'ASC',
				'no_found_rows'          => false,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);

		$items = array_values(
			array_filter(
				$query->posts,
				static fn ( $post ): bool => $post instanceof WP_Post && current_user_can( 'edit_post', $post->ID )
			)
		);

		return array(
			'items'     => $items,
			'truncated' => (int) $query->found_posts > self::MAX_RESOURCES,
		);
	}

	/**
	 * @return list<array{href:string,text:string}>
	 */
	private static function extract_links( string $html ): array {
		if ( '' === trim( $html ) ) {
			return array();
		}

		$matches = array();
		$count   = preg_match_all( '~<a\b(?P<attrs>[^>]*)>(?P<inner>.*?)</a\s*>~is', $html, $matches, PREG_SET_ORDER );
		if ( ! is_int( $count ) || 1 > $count ) {
			return array();
		}

		$result = array();
		foreach ( $matches as $match ) {
			$attrs = is_string( $match['attrs'] ) ? $match['attrs'] : '';
			$href  = self::attribute_value( $attrs, 'href' );
			if ( self::skip_href( $href ) ) {
				continue;
			}
			$inner    = is_string( $match['inner'] ) ? $match['inner'] : '';
			$result[] = array(
				'href' => $href,
				'text' => self::clean_text( $inner ),
			);
		}

		return $result;
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function classify_target( string $href, string $source_permalink ): array {
		$lower = strtolower( $href );
		if ( str_starts_with( $lower, 'mailto:' ) || str_starts_with( $lower, 'tel:' ) ) {
			return array(
				'absolute_url'    => $href,
				'classification'  => 'system',
				'target_verified' => false,
				'target'          => null,
			);
		}

		$home_url   = home_url( '/' );
		$home_host  = strtolower( (string) wp_parse_url( $home_url, PHP_URL_HOST ) );
		$home_path  = self::normalize_path( (string) wp_parse_url( $home_url, PHP_URL_PATH ) );
		$absolute   = \WP_Http::make_absolute_url( $href, '' !== $source_permalink ? $source_permalink : $home_url );
		if ( '' === $absolute ) {
			return array(
				'absolute_url'    => '',
				'classification'  => 'invalid',
				'target_verified' => false,
				'target'          => null,
			);
		}

		$host           = strtolower( (string) wp_parse_url( $absolute, PHP_URL_HOST ) );
		$path           = self::normalize_path( (string) wp_parse_url( $absolute, PHP_URL_PATH ) );
		$is_same_host   = '' === $host || $host === $home_host;
		$is_within_home = self::is_within_home_path( $path, $home_path );

		if ( $is_same_host && $is_within_home ) {
			$target = self::target_post( url_to_postid( $absolute ) );
			if ( $target instanceof WP_Post ) {
				$summary = self::target_summary( $target );
				return array(
					'absolute_url'    => $absolute,
					'classification'  => 'publish' === $target->post_status ? 'internal-current' : 'internal-nonpublic',
					'target_verified' => true === $summary['current_public'],
					'target'          => $summary,
				);
			}

			return array(
				'absolute_url'    => $absolute,
				'classification'  => 'internal-unresolved',
				'target_verified' => false,
				'target'          => null,
			);
		}

		$logical      = self::logical_path( $path, $home_path );
		$local_url    = home_url( $logical );
		$local_target = self::target_post( url_to_postid( $local_url ) );
		if ( $local_target instanceof WP_Post ) {
			$summary = self::target_summary( $local_target );
			return array(
				'absolute_url'    => $absolute,
				'classification'  => 'environment-leakage-candidate',
				'target_verified' => true === $summary['current_public'],
				'target'          => $summary,
			);
		}

		return array(
			'absolute_url'    => $absolute,
			'classification'  => 'external',
			'target_verified' => false,
			'target'          => null,
		);
	}

	private static function target_post( int $post_id ): ?WP_Post {
		if ( 1 > $post_id ) {
			return null;
		}
		$post = get_post( $post_id );

		return $post instanceof WP_Post && in_array( $post->post_type, array( 'page', 'post' ), true ) ? $post : null;
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function source_summary( WP_Post $post ): array {
		$permalink = get_permalink( $post );

		return array(
			'id'          => (int) $post->ID,
			'type'        => (string) $post->post_type,
			'status'      => (string) $post->post_status,
			'permalink'   => is_string( $permalink ) ? $permalink : '',
			'fingerprint' => ContentFingerprint::for_post( $post ),
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function target_summary( WP_Post $post ): array {
		$permalink = get_permalink( $post );
		$permalink = is_string( $permalink ) ? $permalink : '';

		return array(
			'id'             => (int) $post->ID,
			'type'           => (string) $post->post_type,
			'status'         => (string) $post->post_status,
			'permalink'      => $permalink,
			'current_public' => 'publish' === $post->post_status && '' !== $permalink,
		);
	}

	private static function attribute_value( string $attributes, string $attribute ): string {
		$attribute = preg_quote( $attribute, '~' );
		if ( 1 === preg_match( '~\b' . $attribute . '\s*=\s*(["\'])(.*?)\1~is', $attributes, $match ) && isset( $match[2] ) && is_string( $match[2] ) ) {
			return trim( html_entity_decode( $match[2], ENT_QUOTES | ENT_HTML5, get_bloginfo( 'charset' ) ) );
		}

		return '';
	}

	private static function clean_text( string $value ): string {
		$text = html_entity_decode( wp_strip_all_tags( $value ), ENT_QUOTES | ENT_HTML5, get_bloginfo( 'charset' ) );
		$text = preg_replace( '/\s+/u', ' ', $text );

		return is_string( $text ) ? trim( $text ) : '';
	}

	private static function skip_href( string $href ): bool {
		if ( '' === $href || str_starts_with( $href, '#' ) ) {
			return true;
		}
		$lower = strtolower( $href );

		return str_starts_with( $lower, 'javascript:' ) || str_starts_with( $lower, 'data:' );
	}

	private static function normalize_path( string $path ): string {
		$path = '/' . ltrim( $path, '/' );

		return '/' === $path ? '/' : trailingslashit( $path );
	}

	private static function is_within_home_path( string $path, string $home_path ): bool {
		if ( '/' === $home_path ) {
			return true;
		}
		$base = rtrim( $home_path, '/' );

		return $path === $base . '/' || str_starts_with( $path, $base . '/' );
	}

	private static function logical_path( string $path, string $home_path ): string {
		if ( '/' === $home_path ) {
			return $path;
		}
		$base = rtrim( $home_path, '/' );
		if ( $path === $base . '/' ) {
			return '/';
		}
		if ( str_starts_with( $path, $base . '/' ) ) {
			return '/' . ltrim( substr( $path, strlen( $base ) ), '/' );
		}

		return $path;
	}
}
