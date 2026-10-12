<?php
/**
 * Read-only REST endpoints for Theme semantic model discovery.
 *
 * @package SeoGeoManager
 */

declare(strict_types=1);

namespace SeoGeo\Manager\Rest;

use SeoGeo\Manager\Intelligence\ThemeModelReader;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

final class ThemeModelController {
	private const NAMESPACE = 'seo-geo-manager/v1';

	public static function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/theme/models',
			array(
				'methods'             => 'GET',
				'callback'            => array( self::class, 'index' ),
				'permission_callback' => array( self::class, 'can_read' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/theme/models/(?P<model_id>[a-z0-9-]+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( self::class, 'show' ),
				'permission_callback' => array( self::class, 'can_read' ),
				'args'                => array(
					'model_id' => array(
						'sanitize_callback' => 'sanitize_key',
					),
				),
			)
		);
	}

	public static function can_read(): bool {
		return current_user_can( 'edit_pages' ) || current_user_can( 'edit_posts' );
	}

	/**
	 * @return WP_REST_Response|WP_Error
	 */
	public static function index(): object {
		$result = ThemeModelReader::list_models();
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return new WP_REST_Response( $result, 200 );
	}

	/**
	 * @return WP_REST_Response|WP_Error
	 */
	public static function show( WP_REST_Request $request ): object {
		$model_id = $request->get_param( 'model_id' );
		$model_id = is_string( $model_id ) ? $model_id : '';
		$result   = ThemeModelReader::read_model( $model_id );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return new WP_REST_Response( $result, 200 );
	}
}
