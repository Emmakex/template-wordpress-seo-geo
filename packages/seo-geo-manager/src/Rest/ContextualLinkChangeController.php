<?php
/**
 * REST endpoints for guarded contextual-link href corrections.
 *
 * @package SeoGeoManager
 */

declare(strict_types=1);

namespace SeoGeo\Manager\Rest;

use SeoGeo\Manager\Changes\ContextualLinkChangeAdapter;
use SeoGeo\Manager\Changes\OperationStore;
use SeoGeo\Manager\Support\EnvironmentPolicy;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

final class ContextualLinkChangeController {
	private const NAMESPACE = 'seo-geo-manager/v1';
	private const ADAPTER   = 'contextual-link';

	public static function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/links/contextual/changes/preview',
			array(
				'methods'             => 'POST',
				'callback'            => array( self::class, 'preview' ),
				'permission_callback' => array( self::class, 'can_write' ),
			)
		);
		register_rest_route(
			self::NAMESPACE,
			'/links/contextual/changes/apply',
			array(
				'methods'             => 'POST',
				'callback'            => array( self::class, 'apply' ),
				'permission_callback' => array( self::class, 'can_write' ),
			)
		);
		register_rest_route(
			self::NAMESPACE,
			'/links/contextual/changes/(?P<operation_id>[a-f0-9\-]{36})/rollback',
			array(
				'methods'             => 'POST',
				'callback'            => array( self::class, 'rollback' ),
				'permission_callback' => array( self::class, 'can_write' ),
				'args'                => array( 'operation_id' => array( 'sanitize_callback' => 'sanitize_text_field' ) ),
			)
		);
	}

	public static function can_write(): bool {
		return current_user_can( 'edit_posts' ) || current_user_can( 'edit_pages' );
	}

	/** @return WP_REST_Response|WP_Error */
	public static function preview( WP_REST_Request $request ) {
		$payload = $request->get_json_params();
		$result  = ContextualLinkChangeAdapter::preview( is_array( $payload ) ? $payload : array() );
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

		$result = ContextualLinkChangeAdapter::apply( $payload );
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

		return new WP_REST_Response( self::public_operation( $result ), 200 );
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
			return new WP_Error( 'seo_geo_manager_contextual_link_operation_not_found', 'Contextual-link operation not found.', array( 'status' => 404 ) );
		}
		$operation_guard = EnvironmentPolicy::validate_operation_environment( $operation );
		if ( is_wp_error( $operation_guard ) ) {
			return $operation_guard;
		}

		$result = ContextualLinkChangeAdapter::rollback( $operation_id );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$result['environment'] = EnvironmentPolicy::snapshot();

		return new WP_REST_Response( self::public_operation( $result ), 200 );
	}

	/**
	 * Remove private rollback snapshots before returning an operation over REST.
	 *
	 * Exact content snapshots stay only in OperationStore so rollback remains
	 * deterministic without exposing full page/post content to remote callers.
	 *
	 * @param array<string, mixed> $operation Stored or replayed operation.
	 * @return array<string, mixed>
	 */
	private static function public_operation( array $operation ): array {
		unset(
			$operation['before_content'],
			$operation['after_content'],
			$operation['post_content'],
			$operation['raw_content']
		);

		return $operation;
	}
}
