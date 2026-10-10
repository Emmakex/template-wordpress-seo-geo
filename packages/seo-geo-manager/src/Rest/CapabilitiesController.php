<?php
/**
 * Authenticated capability-discovery endpoint.
 *
 * @package SeoGeoManager
 */

declare(strict_types=1);

namespace SeoGeo\Manager\Rest;

use SeoGeo\Manager\Support\CapabilityManifest;
use WP_REST_Request;
use WP_REST_Response;

final class CapabilitiesController {
	private const NAMESPACE = 'seo-geo-manager/v1';

	public static function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/capabilities',
			array(
				'methods'             => 'GET',
				'callback'            => array( self::class, 'show' ),
				'permission_callback' => array( self::class, 'can_read' ),
			)
		);
	}

	public static function can_read(): bool {
		return current_user_can( 'edit_posts' ) || current_user_can( 'manage_options' );
	}

	public static function show( WP_REST_Request $request ): WP_REST_Response {
		unset( $request );

		return new WP_REST_Response( CapabilityManifest::build(), 200 );
	}
}
