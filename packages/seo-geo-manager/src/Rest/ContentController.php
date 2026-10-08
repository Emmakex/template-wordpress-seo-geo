<?php
/**
 * Read-only content inventory endpoints used by the Manager control plane.
 *
 * @package SeoGeoManager
 */

declare(strict_types=1);

namespace SeoGeo\Manager\Rest;

use SeoGeo\Manager\Support\ContentFingerprint;
use WP_Error;
use WP_Post;
use WP_Query;
use WP_REST_Request;
use WP_REST_Response;

final class ContentController {
	private const NAMESPACE = 'seo-geo-manager/v1';

	public static function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/content',
			array(
				'methods'             => 'GET',
				'callback'            => array( self::class, 'index' ),
				'permission_callback' => array( self::class, 'can_read' ),
				'args'                => array(
					'type'     => array(
						'default'           => 'page',
						'sanitize_callback' => 'sanitize_key',
					),
					'status'   => array(
						'default'           => 'any',
						'sanitize_callback' => 'sanitize_key',
					),
					'search'   => array(
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'per_page' => array(
						'default'           => 50,
						'sanitize_callback' => 'absint',
					),
					'page'     => array(
						'default'           => 1,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/content/(?P<id>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( self::class, 'show' ),
				'permission_callback' => array( self::class, 'can_read' ),
				'args'                => array(
					'id' => array(
						'sanitize_callback' => 'absint',
					),
				),
			)
		);
	}

	public static function can_read(): bool {
		return current_user_can( 'edit_posts' );
	}

	public static function index( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$type = (string) $request->get_param( 'type' );
		if ( ! post_type_exists( $type ) || ! is_post_type_viewable( $type ) ) {
			return new WP_Error( 'seo_geo_manager_invalid_type', 'Unsupported content type.', array( 'status' => 400 ) );
		}

		$per_page = min( 100, max( 1, (int) $request->get_param( 'per_page' ) ) );
		$page     = max( 1, (int) $request->get_param( 'page' ) );
		$status   = (string) $request->get_param( 'status' );
		$search   = (string) $request->get_param( 'search' );

		$query = new WP_Query(
			array(
				'post_type'      => $type,
				'post_status'    => 'any' === $status ? array( 'publish', 'future', 'draft', 'pending', 'private' ) : $status,
				'posts_per_page' => $per_page,
				'paged'          => $page,
				's'              => $search,
				'orderby'        => 'modified',
				'order'          => 'DESC',
				'no_found_rows'  => false,
			)
		);

		$items = array_map( array( self::class, 'normalize_post' ), $query->posts );

		return new WP_REST_Response(
			array(
				'items'       => $items,
				'page'        => $page,
				'per_page'    => $per_page,
				'total'       => (int) $query->found_posts,
				'total_pages' => (int) $query->max_num_pages,
			),
			200
		);
	}

	public static function show( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$post = get_post( (int) $request->get_param( 'id' ) );
		if ( ! $post instanceof WP_Post ) {
			return new WP_Error( 'seo_geo_manager_not_found', 'Content resource not found.', array( 'status' => 404 ) );
		}

		if ( ! current_user_can( 'edit_post', $post->ID ) ) {
			return new WP_Error( 'seo_geo_manager_forbidden', 'You cannot access this content resource.', array( 'status' => 403 ) );
		}

		$data            = self::normalize_post( $post );
		$data['content'] = (string) $post->post_content;
		$data['excerpt'] = (string) $post->post_excerpt;
		$data['parent']  = (int) $post->post_parent;
		$data['author']  = (int) $post->post_author;

		return new WP_REST_Response( $data, 200 );
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function normalize_post( WP_Post $post ): array {
		return array(
			'id'          => (int) $post->ID,
			'type'        => (string) $post->post_type,
			'status'      => (string) $post->post_status,
			'slug'        => (string) $post->post_name,
			'title'       => html_entity_decode( get_the_title( $post ), ENT_QUOTES | ENT_HTML5, get_bloginfo( 'charset' ) ),
			'permalink'   => (string) get_permalink( $post ),
			'modified'    => (string) $post->post_modified_gmt,
			'fingerprint' => ContentFingerprint::for_post( $post ),
		);
	}
}
