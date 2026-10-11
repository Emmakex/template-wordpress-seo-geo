<?php
/**
 * REST endpoints for guarded Yoast SEO metadata changes.
 *
 * @package SeoGeoManager
 */

declare(strict_types=1);

namespace SeoGeo\Manager\Rest;

use SeoGeo\Manager\Changes\OperationStore;
use SeoGeo\Manager\Changes\YoastSeoMetadataChangeAdapter;
use SeoGeo\Manager\Support\EnvironmentPolicy;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/** Expose the bounded Yoast SEO WordPress Abilities adapter. */
final class YoastSeoMetadataController {
	private const NAMESPACE = 'seo-geo-manager/v1';
	private const ADAPTER   = 'yoast-abilities';
	private const BASE_PATH = '/content/(?P<resource_id>\d+)/seo/providers/yoast';

	public static function register_routes(): void {
		$args = array(
			'resource_id' => array( 'sanitize_callback' => 'absint' ),
		);

		register_rest_route(
			self::NAMESPACE,
			self::BASE_PATH,
			array(
				'methods'             => 'GET',
				'callback'            => array( self::class, 'show' ),
				'permission_callback' => array( self::class, 'can_operate' ),
				'args'                => $args,
			)
		);
		register_rest_route(
			self::NAMESPACE,
			self::BASE_PATH . '/changes/preview',
			array(
				'methods'             => 'POST',
				'callback'            => array( self::class, 'preview' ),
				'permission_callback' => array( self::class, 'can_operate' ),
				'args'                => $args,
			)
		);
		register_rest_route(
			self::NAMESPACE,
			self::BASE_PATH . '/changes/apply',
			array(
				'methods'             => 'POST',
				'callback'            => array( self::class, 'apply' ),
				'permission_callback' => array( self::class, 'can_operate' ),
				'args'                => $args,
			)
		);
		register_rest_route(
			self::NAMESPACE,
			'/content/seo/providers/yoast/changes/(?P<operation_id>[a-f0-9\-]{36})/rollback',
			array(
				'methods'             => 'POST',
				'callback'            => array( self::class, 'rollback' ),
				'permission_callback' => array( self::class, 'can_operate' ),
				'args'                => array(
					'operation_id' => array( 'sanitize_callback' => 'sanitize_text_field' ),
				),
			)
		);
	}

	public static function can_operate(): bool {
		return current_user_can( 'edit_posts' ) || current_user_can( 'edit_pages' );
	}

	/** @return WP_REST_Response|WP_Error */
	public static function show( WP_REST_Request $request ) {
		$result = YoastSeoMetadataChangeAdapter::inspect( absint( $request->get_param( 'resource_id' ) ) );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return new WP_REST_Response( $result, 200 );
	}

	/** @return WP_REST_Response|WP_Error */
	public static function preview( WP_REST_Request $request ) {
		$result = YoastSeoMetadataChangeAdapter::preview( self::payload( $request ) );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$result['environment'] = EnvironmentPolicy::snapshot();

		return new WP_REST_Response( $result, 200 );
	}

	/** @return WP_REST_Response|WP_Error */
	public static function apply( WP_REST_Request $request ) {
		$payload = self::payload( $request );
		$guard   = EnvironmentPolicy::validate_payload( $payload );
		if ( is_wp_error( $guard ) ) {
			return $guard;
		}

		$result = YoastSeoMetadataChangeAdapter::apply( $payload );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$operation_id = isset( $result['operation_id'] ) && is_string( $result['operation_id'] ) ? $result['operation_id'] : '';
		if ( '' !== $operation_id ) {
			$stored = OperationStore::get( $operation_id );
			if ( is_array( $stored ) && ! isset( $stored['environment'] ) ) {
				$stored = EnvironmentPolicy::bind_operation( $stored );
				OperationStore::save( $operation_id, $stored );
				$result = $stored;
			}
		}
		$result['environment'] = $result['environment'] ?? EnvironmentPolicy::snapshot();

		return new WP_REST_Response( $result, 200 );
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
			return new WP_Error(
				'seo_geo_manager_yoast_seo_operation_not_found',
				'Yoast SEO metadata operation not found.',
				array( 'status' => 404 )
			);
		}
		$operation_guard = EnvironmentPolicy::validate_operation_environment( $operation );
		if ( is_wp_error( $operation_guard ) ) {
			return $operation_guard;
		}

		$result = YoastSeoMetadataChangeAdapter::rollback( $operation_id );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$result['environment'] = EnvironmentPolicy::snapshot();

		return new WP_REST_Response( $result, 200 );
	}

	/** @return array<string, mixed> */
	private static function payload( WP_REST_Request $request ): array {
		$payload                = $request->get_json_params();
		$payload                = is_array( $payload ) ? $payload : array();
		$payload['resource_id'] = absint( $request->get_param( 'resource_id' ) );

		return $payload;
	}
}
