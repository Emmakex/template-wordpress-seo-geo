<?php
/**
 * REST endpoint for the read-only C9 field-acceptance preflight.
 *
 * @package SeoGeoManager
 */

declare(strict_types=1);

namespace SeoGeo\Manager\Rest;

use SeoGeo\Manager\Field\C9FieldPreflight;
use WP_REST_Request;
use WP_REST_Response;

/** Expose C9 runtime prerequisites without performing field mutations. */
final class C9FieldPreflightController {
	private const NAMESPACE = 'seo-geo-manager/v1';

	public static function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/field/c9/preflight',
			array(
				'methods'             => 'GET',
				'callback'            => array( self::class, 'show' ),
				'permission_callback' => array( self::class, 'can_read' ),
				'args'                => array(
					'expected_stack' => array(
						'required'          => true,
						'sanitize_callback' => 'sanitize_key',
					),
					'expected_manager_version' => array(
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
					'expected_package_fingerprint' => array(
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);
	}

	public static function can_read(): bool {
		return current_user_can( 'edit_posts' );
	}

	public static function show( WP_REST_Request $request ): WP_REST_Response {
		$stack       = $request->get_param( 'expected_stack' );
		$version     = $request->get_param( 'expected_manager_version' );
		$fingerprint = $request->get_param( 'expected_package_fingerprint' );

		return new WP_REST_Response(
			C9FieldPreflight::run(
				is_string( $stack ) ? $stack : '',
				is_string( $version ) ? $version : '',
				is_string( $fingerprint ) ? $fingerprint : ''
			),
			200
		);
	}
}
