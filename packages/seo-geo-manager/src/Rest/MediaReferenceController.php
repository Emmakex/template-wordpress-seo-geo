<?php
/**
 * REST endpoints for bounded image-media reference discovery.
 *
 * @package SeoGeoManager
 */
declare(strict_types=1);

namespace SeoGeo\Manager\Rest;

use SeoGeo\Manager\Intelligence\MediaReferenceReader;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

final class MediaReferenceController {
	private const NAMESPACE = 'seo-geo-manager/v1';

	public static function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/media',
			array(
				'methods'             => 'GET',
				'callback'            => array( self::class, 'index' ),
				'permission_callback' => array( self::class, 'can_read' ),
			)
		);
		register_rest_route(
			self::NAMESPACE,
			'/media/(?P<media_id>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( self::class, 'show' ),
				'permission_callback' => array( self::class, 'can_read' ),
				'args'                => array(
					'media_id' => array( 'sanitize_callback' => 'absint' ),
				),
			)
		);
	}

	public static function can_read(): bool {
		return current_user_can( 'upload_files' );
	}

	public static function index(): WP_REST_Response {
		return new WP_REST_Response( MediaReferenceReader::list_media(), 200 );
	}

	/** @return WP_REST_Response|WP_Error */
	public static function show( WP_REST_Request $request ) {
		$result = MediaReferenceReader::read_media( absint( $request->get_param( 'media_id' ) ) );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return new WP_REST_Response( $result, 200 );
	}
}
