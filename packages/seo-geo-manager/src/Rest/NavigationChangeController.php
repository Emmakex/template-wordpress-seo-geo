<?php
/**
 * REST endpoints for controlled WordPress navigation corrections.
 *
 * @package SeoGeoManager
 */

declare(strict_types=1);

namespace SeoGeo\Manager\Rest;

use SeoGeo\Manager\Changes\NavigationChangeAdapter;
use SeoGeo\Manager\Changes\OperationStore;
use SeoGeo\Manager\Support\EnvironmentPolicy;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

final class NavigationChangeController {
	private const NAMESPACE = 'seo-geo-manager/v1';
	private const ADAPTER   = 'navigation-menu';

	public static function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/navigation/changes/preview',
			array(
				'methods'             => 'POST',
				'callback'            => array( self::class, 'preview' ),
				'permission_callback' => array( self::class, 'can_write' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/navigation/changes/apply',
			array(
				'methods'             => 'POST',
				'callback'            => array( self::class, 'apply' ),
				'permission_callback' => array( self::class, 'can_write' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/navigation/changes/(?P<operation_id>[a-f0-9\-]{36})',
			array(
				'methods'             => 'GET',
				'callback'            => array( self::class, 'show' ),
				'permission_callback' => array( self::class, 'can_write' ),
				'args'                => array(
					'operation_id' => array( 'sanitize_callback' => 'sanitize_text_field' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/navigation/changes/(?P<operation_id>[a-f0-9\-]{36})/rollback',
			array(
				'methods'             => 'POST',
				'callback'            => array( self::class, 'rollback' ),
				'permission_callback' => array( self::class, 'can_write' ),
				'args'                => array(
					'operation_id' => array( 'sanitize_callback' => 'sanitize_text_field' ),
				),
			)
		);
	}

	public static function can_write(): bool {
		return current_user_can( 'edit_theme_options' );
	}

	/** @return WP_REST_Response|WP_Error */
	public static function preview( WP_REST_Request $request ) {
		$payload = $request->get_json_params();
		$result  = NavigationChangeAdapter::preview( is_array( $payload ) ? $payload : array() );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$result['environment'] = EnvironmentPolicy::snapshot();
		return new WP_REST_Response( $result, 200 );
	}

	/** @return WP_REST_Response|WP_Error */
	public static function apply( WP_REST_Request $request ) {
		$payload = $request->get_json_params();
		$payload = is_array( $payload ) ? $payload : array();
		$guard   = EnvironmentPolicy::validate_payload( $payload );
		if ( is_wp_error( $guard ) ) {
			return $guard;
		}

		$result = NavigationChangeAdapter::apply( $payload );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$environment           = EnvironmentPolicy::snapshot();
		$result['environment'] = $environment;
		$operation_id          = isset( $result['operation_id'] ) && is_string( $result['operation_id'] ) ? $result['operation_id'] : '';
		if ( '' !== $operation_id ) {
			$stored = OperationStore::get( $operation_id );
			if ( is_array( $stored ) && ! isset( $stored['environment'] ) ) {
				$stored = EnvironmentPolicy::bind_operation( $stored );
				OperationStore::save( $operation_id, $stored );
				$result['environment'] = $stored['environment'];
			}
		}

		return new WP_REST_Response( $result, 200 );
	}

	/** @return WP_REST_Response|WP_Error */
	public static function show( WP_REST_Request $request ) {
		$operation = OperationStore::get( (string) $request->get_param( 'operation_id' ) );
		if ( ! is_array( $operation ) || self::ADAPTER !== ( $operation['adapter'] ?? '' ) ) {
			return new WP_Error( 'seo_geo_manager_navigation_operation_not_found', 'Navigation operation not found.', array( 'status' => 404 ) );
		}
		return new WP_REST_Response( $operation, 200 );
	}

	/** @return WP_REST_Response|WP_Error */
	public static function rollback( WP_REST_Request $request ) {
		$payload = $request->get_json_params();
		$payload = is_array( $payload ) ? $payload : array();
		$guard   = EnvironmentPolicy::validate_payload( $payload );
		if ( is_wp_error( $guard ) ) {
			return $guard;
		}

		$operation_id = (string) $request->get_param( 'operation_id' );
		$operation    = OperationStore::get( $operation_id );
		if ( ! is_array( $operation ) || self::ADAPTER !== ( $operation['adapter'] ?? '' ) ) {
			return new WP_Error( 'seo_geo_manager_navigation_operation_not_found', 'Navigation operation not found.', array( 'status' => 404 ) );
		}
		$operation_guard = EnvironmentPolicy::validate_operation_environment( $operation );
		if ( is_wp_error( $operation_guard ) ) {
			return $operation_guard;
		}

		$result = NavigationChangeAdapter::rollback( $operation_id );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$result['environment'] = EnvironmentPolicy::snapshot();
		return new WP_REST_Response( $result, 200 );
	}
}
