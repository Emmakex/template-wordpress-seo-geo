<?php
/**
 * Site intelligence endpoint for Build / Finish workflows.
 *
 * @package SeoGeoManager
 */

declare(strict_types=1);

namespace SeoGeo\Manager\Rest;

use SeoGeo\Manager\Intelligence\SiteIntelligenceScanner;
use WP_REST_Request;
use WP_REST_Response;

final class SiteIntelligenceController {
	private const NAMESPACE = 'seo-geo-manager/v1';

	public static function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/site/intelligence',
			array(
				'methods'             => 'GET',
				'callback'            => array( self::class, 'show' ),
				'permission_callback' => array( self::class, 'can_read' ),
				'args'                => array(
					'include_rendered' => array(
						'default'           => false,
						'sanitize_callback' => 'rest_sanitize_boolean',
					),
				),
			)
		);
	}

	public static function can_read(): bool {
		return current_user_can( 'edit_posts' );
	}

	public static function show( WP_REST_Request $request ): WP_REST_Response {
		$include_rendered = rest_sanitize_boolean( $request->get_param( 'include_rendered' ) );

		return new WP_REST_Response( SiteIntelligenceScanner::scan( $include_rendered ), 200 );
	}
}
