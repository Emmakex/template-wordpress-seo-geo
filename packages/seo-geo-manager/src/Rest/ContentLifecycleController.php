<?php
/**
 * REST endpoints for generic post/page creation and publication lifecycle.
 *
 * @package SeoGeoManager
 */

declare(strict_types=1);

namespace SeoGeo\Manager\Rest;

use SeoGeo\Manager\Changes\ContentLifecycleEngine;
use SeoGeo\Manager\Changes\OperationStore;
use SeoGeo\Manager\Support\EnvironmentPolicy;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

final class ContentLifecycleController {
	private const NAMESPACE = 'seo-geo-manager/v1';

	public static function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/content/resources/preview',
			array(
				'methods'             => 'POST',
				'callback'            => array( self::class, 'preview_create' ),
				'permission_callback' => array( self::class, 'can_operate' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/content/resources/apply',
			array(
				'methods'             => 'POST',
				'callback'            => array( self::class, 'apply_create' ),
				'permission_callback' => array( self::class, 'can_operate' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/content/(?P<id>\d+)/publication/preview',
			array(
				'methods'             => 'POST',
				'callback'            => array( self::class, 'preview_publication' ),
				'permission_callback' => array( self::class, 'can_operate' ),
				'args'                => array(
					'id' => array(
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/content/(?P<id>\d+)/publication/apply',
			array(
				'methods'             => 'POST',
				'callback'            => array( self::class, 'apply_publication' ),
				'permission_callback' => array( self::class, 'can_operate' ),
				'args'                => array(
					'id' => array(
						'sanitize_callback' => 'absint',
					),
				),
			)
		);
	}

	public static function can_operate(): bool {
		return current_user_can( 'edit_posts' ) || current_user_can( 'edit_pages' );
	}

	/**
	 * @return WP_REST_Response|WP_Error
	 */
	public static function preview_create( WP_REST_Request $request ) {
		$payload = self::payload( $request );
		$result  = ContentLifecycleEngine::preview_create( $payload );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$result['environment'] = EnvironmentPolicy::snapshot();

		return new WP_REST_Response( $result, 200 );
	}

	/**
	 * @return WP_REST_Response|WP_Error
	 */
	public static function apply_create( WP_REST_Request $request ) {
		$payload = self::payload( $request );
		$guard   = EnvironmentPolicy::validate_payload( $payload );
		if ( is_wp_error( $guard ) ) {
			return $guard;
		}

		$result = ContentLifecycleEngine::create( $payload );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$result = self::bind_environment( $result );

		return new WP_REST_Response( $result, 201 );
	}

	/**
	 * @return WP_REST_Response|WP_Error
	 */
	public static function preview_publication( WP_REST_Request $request ) {
		$payload = self::publication_payload( $request );
		$result  = ContentLifecycleEngine::preview_publication( $payload );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$result['environment'] = EnvironmentPolicy::snapshot();

		return new WP_REST_Response( $result, 200 );
	}

	/**
	 * @return WP_REST_Response|WP_Error
	 */
	public static function apply_publication( WP_REST_Request $request ) {
		$payload = self::publication_payload( $request );
		$guard   = EnvironmentPolicy::validate_payload( $payload );
		if ( is_wp_error( $guard ) ) {
			return $guard;
		}

		$result = ContentLifecycleEngine::apply_publication( $payload );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$result = self::bind_environment( $result );

		return new WP_REST_Response( $result, 200 );
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function payload( WP_REST_Request $request ): array {
		$payload = $request->get_json_params();

		return is_array( $payload ) ? $payload : array();
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function publication_payload( WP_REST_Request $request ): array {
		$payload           = self::payload( $request );
		$target            = isset( $payload['target'] ) && is_array( $payload['target'] ) ? $payload['target'] : array();
		$target['id']      = absint( $request->get_param( 'id' ) );
		$payload['target'] = $target;

		return $payload;
	}

	/**
	 * Persist environment evidence onto the private operation record and return
	 * the same evidence to the authenticated caller.
	 *
	 * @param array<string, mixed> $result Operation result.
	 * @return array<string, mixed>
	 */
	private static function bind_environment( array $result ): array {
		$environment           = EnvironmentPolicy::snapshot();
		$result['environment'] = $environment;
		$operation_id          = isset( $result['operation_id'] ) && is_string( $result['operation_id'] ) ? $result['operation_id'] : '';

		if ( '' !== $operation_id ) {
			$stored = OperationStore::get( $operation_id );
			if ( is_array( $stored ) && ! isset( $stored['environment'] ) ) {
				OperationStore::save( $operation_id, EnvironmentPolicy::bind_operation( $stored ) );
			}
		}

		return $result;
	}
}
