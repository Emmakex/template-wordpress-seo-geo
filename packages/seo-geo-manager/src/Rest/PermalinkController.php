<?php
/**
 * Read-only permalink repair preview endpoints.
 *
 * @package SeoGeoManager
 */

declare(strict_types=1);

namespace SeoGeo\Manager\Rest;

use SeoGeo\Manager\Support\PermalinkInspector;
use SeoGeo\Manager\Support\PermalinkRedirectPlanner;
use WP_REST_Response;

final class PermalinkController {
	private const NAMESPACE = 'seo-geo-manager/v1';

	public static function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/permalinks/preview',
			array(
				'methods'             => 'GET',
				'callback'            => array( self::class, 'preview' ),
				'permission_callback' => array( self::class, 'can_manage' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/permalinks/redirect-plan',
			array(
				'methods'             => 'GET',
				'callback'            => array( self::class, 'redirect_plan' ),
				'permission_callback' => array( self::class, 'can_manage' ),
			)
		);
	}

	public static function can_manage(): bool {
		return current_user_can( 'manage_options' );
	}

	public static function preview(): WP_REST_Response {
		return new WP_REST_Response( PermalinkInspector::preview(), 200 );
	}

	public static function redirect_plan(): WP_REST_Response {
		return new WP_REST_Response( PermalinkRedirectPlanner::preview(), 200 );
	}
}
