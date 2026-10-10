<?php
/**
 * REST endpoint for the read-only Build / Finish field gate.
 *
 * @package SeoGeoManager
 */

declare(strict_types=1);

namespace SeoGeo\Manager\Rest;

use SeoGeo\Manager\Field\FieldGatePreflight;
use WP_REST_Request;
use WP_REST_Response;

final class FieldGateController {
	private const NAMESPACE = 'seo-geo-manager/v1';

	public static function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/field-gate/preflight',
			array(
				'methods'             => 'GET',
				'callback'            => array( self::class, 'show' ),
				'permission_callback' => array( self::class, 'can_manage' ),
				'args'                => array(
					'include_rendered' => array(
						'default'           => false,
						'sanitize_callback' => 'rest_sanitize_boolean',
					),
					'legacy_base_url'  => array(
						'default'           => '',
						'sanitize_callback' => 'esc_url_raw',
					),
				),
			)
		);
	}

	public static function can_manage(): bool {
		return current_user_can( 'manage_options' );
	}

	public static function show( WP_REST_Request $request ): WP_REST_Response {
		$include_rendered = true === filter_var( $request->get_param( 'include_rendered' ), FILTER_VALIDATE_BOOLEAN );
		$legacy_base_url  = $request->get_param( 'legacy_base_url' );
		$legacy_base_url  = is_string( $legacy_base_url ) ? $legacy_base_url : '';

		return new WP_REST_Response( FieldGatePreflight::run( $include_rendered, $legacy_base_url ), 200 );
	}
}
