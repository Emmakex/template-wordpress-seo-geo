<?php
/**
 * REST endpoint for bounded featured-media state discovery.
 *
 * @package SeoGeoManager
 */

declare(strict_types=1);

namespace SeoGeo\Manager\Rest;

use SeoGeo\Manager\Intelligence\FeaturedMediaReader;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

final class FeaturedMediaController {
	private const NAMESPACE = 'seo-geo-manager/v1';

	public static function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/content/(?P<resource_id>\d+)/featured-media',
			array(
				'methods'             => 'GET',
				'callback'            => array( self::class, 'show' ),
				'permission_callback' => array( self::class, 'can_read' ),
				'args'                => array(
					'resource_id' => array( 'sanitize_callback' => 'absint' ),
				),
			)
		);
	}

	public static function can_read(): bool {
		return current_user_can( 'upload_files' ) && ( current_user_can( 'edit_posts' ) || current_user_can( 'edit_pages' ) );
	}

	/** @return WP_REST_Response|WP_Error */
	public static function show( WP_REST_Request $request ) {
		$result = FeaturedMediaReader::read( absint( $request->get_param( 'resource_id' ) ) );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return new WP_REST_Response( $result, 200 );
	}
}
