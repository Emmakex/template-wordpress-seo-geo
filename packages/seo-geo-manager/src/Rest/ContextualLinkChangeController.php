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
use SeoGeo\Manager\Intelligence\ContextualLinkReader;
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
			'/links/contextual/changes/(?P<operation_id>[a-f0-9\-]{36})/verify',
			array(
				'methods'             => 'GET',
				'callback'            => array( self::class, 'verify' ),
				'permission_callback' => array( self::class, 'can_write' ),
				'args'                => array( 'operation_id' => array( 'sanitize_callback' => 'sanitize_text_field' ) ),
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
	public static function verify( WP_REST_Request $request ) {
		$operation_id = (string) $request->get_param( 'operation_id' );
		$operation    = OperationStore::get( $operation_id );
		if ( ! is_array( $operation ) || self::ADAPTER !== ( $operation['adapter'] ?? '' ) ) {
			return new WP_Error( 'seo_geo_manager_contextual_link_operation_not_found', 'Contextual-link operation not found.', array( 'status' => 404 ) );
		}
		if ( 'applied' !== ( $operation['status'] ?? '' ) ) {
			return new WP_Error( 'seo_geo_manager_contextual_link_verify_unavailable', 'Only applied contextual-link operations can be verified.', array( 'status' => 409 ) );
		}

		$environment_guard = EnvironmentPolicy::validate_operation_environment( $operation );
		if ( is_wp_error( $environment_guard ) ) {
			return $environment_guard;
		}

		$source_id          = isset( $operation['source_id'] ) ? absint( $operation['source_id'] ) : 0;
		$ordinal            = isset( $operation['ordinal'] ) ? (int) $operation['ordinal'] : -1;
		$after_href         = isset( $operation['after_href'] ) && is_string( $operation['after_href'] ) ? $operation['after_href'] : '';
		$after_fingerprint  = isset( $operation['after_fingerprint'] ) && is_string( $operation['after_fingerprint'] ) ? $operation['after_fingerprint'] : '';
		$target_resource_id = isset( $operation['target_resource_id'] ) ? absint( $operation['target_resource_id'] ) : 0;
		if ( 1 > $source_id || 0 > $ordinal || '' === $after_href || '' === $after_fingerprint || 1 > $target_resource_id ) {
			return new WP_Error( 'seo_geo_manager_contextual_link_verify_state_invalid', 'Contextual-link verification state is incomplete.', array( 'status' => 409 ) );
		}

		$readback = ContextualLinkReader::read( $source_id );
		if ( is_wp_error( $readback ) ) {
			return $readback;
		}

		$matched = null;
		$items   = isset( $readback['items'] ) && is_array( $readback['items'] ) ? $readback['items'] : array();
		foreach ( $items as $item ) {
			if ( ! is_array( $item ) || $ordinal !== (int) ( $item['ordinal'] ?? -1 ) ) {
				continue;
			}
			$source = isset( $item['source'] ) && is_array( $item['source'] ) ? $item['source'] : array();
			if ( $source_id === absint( $source['id'] ?? 0 ) ) {
				$matched = $item;
				break;
			}
		}

		if ( ! is_array( $matched ) ) {
			return new WP_Error( 'seo_geo_manager_contextual_link_verify_missing', 'The applied contextual link could not be found during readback.', array( 'status' => 409 ) );
		}

		$source              = isset( $matched['source'] ) && is_array( $matched['source'] ) ? $matched['source'] : array();
		$target              = isset( $matched['target'] ) && is_array( $matched['target'] ) ? $matched['target'] : array();
		$current_href        = isset( $matched['href'] ) && is_string( $matched['href'] ) ? $matched['href'] : '';
		$current_fingerprint = isset( $source['fingerprint'] ) && is_string( $source['fingerprint'] ) ? $source['fingerprint'] : '';
		$current_target_id   = absint( $target['id'] ?? 0 );
		if (
			! hash_equals( $after_href, $current_href ) ||
			! hash_equals( $after_fingerprint, $current_fingerprint ) ||
			$target_resource_id !== $current_target_id ||
			true !== ( $matched['target_verified'] ?? false )
		) {
			return new WP_Error( 'seo_geo_manager_contextual_link_verify_stale', 'Contextual-link readback does not match the applied operation.', array( 'status' => 409 ) );
		}

		return new WP_REST_Response(
			array(
				'verified'            => true,
				'operation_id'        => $operation_id,
				'source_id'           => $source_id,
				'ordinal'             => $ordinal,
				'href'                => $current_href,
				'target_resource_id'  => $current_target_id,
				'current_fingerprint' => $current_fingerprint,
				'environment'         => EnvironmentPolicy::snapshot(),
			),
			200
		);
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
