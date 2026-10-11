<?php
/**
 * REST endpoint for bounded Theme strategic-media binding discovery.
 *
 * @package SeoGeoManager
 */

declare(strict_types=1);

namespace SeoGeo\Manager\Rest;

use SeoGeo\Manager\Intelligence\ThemeStrategicMediaReader;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/** Expose one Theme-declared strategic media binding to external orchestration. */
final class ThemeStrategicMediaController {
	private const NAMESPACE = 'seo-geo-manager/v1';

	public static function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/theme/media-bindings/(?P<resource_id>\d+)/(?P<model_id>[a-z0-9_-]+)/(?P<slot_id>[a-z0-9_-]+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( self::class, 'show' ),
				'permission_callback' => array( self::class, 'can_read' ),
				'args'                => array(
					'resource_id' => array( 'sanitize_callback' => 'absint' ),
					'model_id'    => array( 'sanitize_callback' => 'sanitize_key' ),
					'slot_id'     => array( 'sanitize_callback' => 'sanitize_key' ),
				),
			)
		);
	}

	public static function can_read(): bool {
		return current_user_can( 'upload_files' ) && current_user_can( 'edit_pages' );
	}

	/** @return WP_REST_Response|WP_Error */
	public static function show( WP_REST_Request $request ) {
		$result = ThemeStrategicMediaReader::read(
			absint( $request->get_param( 'resource_id' ) ),
			(string) $request->get_param( 'model_id' ),
			(string) $request->get_param( 'slot_id' )
		);
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return new WP_REST_Response( $result, 200 );
	}
}
