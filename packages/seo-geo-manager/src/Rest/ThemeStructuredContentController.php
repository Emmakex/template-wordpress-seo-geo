<?php
/**
 * REST endpoints for Theme-owned structured content slots.
 *
 * @package SeoGeoManager
 */

declare(strict_types=1);

namespace SeoGeo\Manager\Rest;

use SeoGeo\Manager\Changes\OperationStore;
use SeoGeo\Manager\Changes\ThemeStructuredContentAdapter;
use SeoGeo\Manager\Support\EnvironmentPolicy;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

final class ThemeStructuredContentController {
	private const NAMESPACE = 'seo-geo-manager/v1';

	public static function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/theme/structured/preview',
			array(
				'methods'             => 'POST',
				'callback'            => array( self::class, 'preview' ),
				'permission_callback' => array( self::class, 'can_write' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/theme/structured/apply',
			array(
				'methods'             => 'POST',
				'callback'            => array( self::class, 'apply' ),
				'permission_callback' => array( self::class, 'can_write' ),
			)
		);
	}

	public static function can_write(): bool {
		return current_user_can( 'edit_posts' );
	}

	/**
	 * @return WP_REST_Response|WP_Error
	 */
	public static function preview( WP_REST_Request $request ) {
		$payload = $request->get_json_params();
		$result  = ThemeStructuredContentAdapter::preview( is_array( $payload ) ? $payload : array() );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$result['environment'] = EnvironmentPolicy::snapshot();

		return new WP_REST_Response( $result, 200 );
	}

	/**
	 * @return WP_REST_Response|WP_Error
	 */
	public static function apply( WP_REST_Request $request ) {
		$payload = $request->get_json_params();
		$payload = is_array( $payload ) ? $payload : array();
		$guard   = EnvironmentPolicy::validate_payload( $payload );
		if ( is_wp_error( $guard ) ) {
			return $guard;
		}

		$result = ThemeStructuredContentAdapter::apply( $payload );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$result['environment'] = EnvironmentPolicy::snapshot();
		$operation_id          = isset( $result['operation_id'] ) && is_string( $result['operation_id'] ) ? $result['operation_id'] : '';
		if ( '' !== $operation_id ) {
			$stored = OperationStore::get( $operation_id );
			if ( is_array( $stored ) ) {
				$stored['structured_model'] = $result['structured_model'] ?? array();
				$stored                     = EnvironmentPolicy::bind_operation( $stored );
				OperationStore::save( $operation_id, $stored );
			}
		}

		return new WP_REST_Response( $result, 200 );
	}
}
