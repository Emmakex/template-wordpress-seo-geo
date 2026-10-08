<?php
/**
 * Public bounded health endpoint.
 *
 * @package SeoGeoManager
 */

declare(strict_types=1);

namespace SeoGeo\Manager\Rest;

use WP_REST_Request;
use WP_REST_Response;

final class HealthController {
	private const NAMESPACE = 'seo-geo-manager/v1';

	public static function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/health',
			array(
				'methods'             => 'GET',
				'callback'            => array( self::class, 'health' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	public static function health( WP_REST_Request $request ): WP_REST_Response {
		unset( $request );

		return new WP_REST_Response(
			array(
				'ok'      => true,
				'service' => 'seo-geo-manager',
				'version' => SEO_GEO_MANAGER_VERSION,
			),
			200
		);
	}
}
