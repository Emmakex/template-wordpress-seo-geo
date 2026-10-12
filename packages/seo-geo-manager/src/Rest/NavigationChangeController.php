<?php
/**
 * REST endpoints for controlled WordPress navigation inspection and corrections.
 *
 * @package SeoGeoManager
 */

declare(strict_types=1);

namespace SeoGeo\Manager\Rest;

use SeoGeo\Manager\Changes\NavigationChangeAdapter;
use SeoGeo\Manager\Changes\OperationStore;
use SeoGeo\Manager\Support\EnvironmentPolicy;
use WP_Error;
use WP_Post;
use WP_REST_Request;
use WP_REST_Response;

final class NavigationChangeController {
	private const NAMESPACE = 'seo-geo-manager/v1';
	private const ADAPTER   = 'navigation-menu';

	public static function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/navigation',
			array(
				'methods'             => 'GET',
				'callback'            => array( self::class, 'index' ),
				'permission_callback' => array( self::class, 'can_write' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/navigation/(?P<menu_id>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( self::class, 'inspect' ),
				'permission_callback' => array( self::class, 'can_write' ),
				'args'                => array(
					'menu_id' => array( 'sanitize_callback' => 'absint' ),
				),
			)
		);

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
			'/navigation/changes/(?P<operation_id>[a-f0-9\-]{36})/verify',
			array(
				'methods'             => 'GET',
				'callback'            => array( self::class, 'verify' ),
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

	/** @return WP_REST_Response */
	public static function index() {
		$menus     = wp_get_nav_menus();
		$locations = get_nav_menu_locations();
		$items     = array();

		foreach ( $menus as $menu ) {
			$menu_id        = (int) $menu->term_id;
			$menu_locations = array();
			foreach ( is_array( $locations ) ? $locations : array() as $location => $term_id ) {
				if ( $menu_id === (int) $term_id ) {
					$menu_locations[] = sanitize_key( (string) $location );
				}
			}
			$menu_items = wp_get_nav_menu_items( $menu_id, array( 'post_status' => 'any' ) );
			$items[]    = array(
				'id'         => $menu_id,
				'name'       => (string) $menu->name,
				'slug'       => (string) $menu->slug,
				'item_count' => is_array( $menu_items ) ? count( $menu_items ) : 0,
				'locations'  => $menu_locations,
			);
		}

		return new WP_REST_Response(
			array(
				'items'       => $items,
				'menu_count'  => count( $items ),
				'environment' => EnvironmentPolicy::snapshot(),
			),
			200
		);
	}

	/** @return WP_REST_Response|WP_Error */
	public static function inspect( WP_REST_Request $request ) {
		$menu_id = (int) $request->get_param( 'menu_id' );
		$result  = NavigationChangeAdapter::inspect( $menu_id );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$locations            = get_nav_menu_locations();
		$result['locations']   = array();
		$result['environment'] = EnvironmentPolicy::snapshot();
		foreach ( is_array( $locations ) ? $locations : array() as $location => $term_id ) {
			if ( $menu_id === (int) $term_id ) {
				$result['locations'][] = sanitize_key( (string) $location );
			}
		}

		return new WP_REST_Response( $result, 200 );
	}

	/** @return WP_REST_Response|WP_Error */
	public static function preview( WP_REST_Request $request ) {
		$payload = self::prepare_target_resource( $request->get_json_params() );
		if ( is_wp_error( $payload ) ) {
			return $payload;
		}

		$result = NavigationChangeAdapter::preview( $payload );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$result['environment'] = EnvironmentPolicy::snapshot();
		$result['target']      = self::target_summary( $payload );
		return new WP_REST_Response( $result, 200 );
	}

	/** @return WP_REST_Response|WP_Error */
	public static function apply( WP_REST_Request $request ) {
		$payload = self::prepare_target_resource( $request->get_json_params() );
		if ( is_wp_error( $payload ) ) {
			return $payload;
		}

		$guard = EnvironmentPolicy::validate_payload( $payload );
		if ( is_wp_error( $guard ) ) {
			return $guard;
		}

		$result = NavigationChangeAdapter::apply( $payload );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$environment                  = EnvironmentPolicy::snapshot();
		$result['environment']        = $environment;
		$result['target_resource_id'] = isset( $payload['target_resource_id'] ) ? (int) $payload['target_resource_id'] : 0;
		$operation_id                 = isset( $result['operation_id'] ) && is_string( $result['operation_id'] ) ? $result['operation_id'] : '';
		if ( '' !== $operation_id ) {
			$stored = OperationStore::get( $operation_id );
			if ( is_array( $stored ) ) {
				if ( ! isset( $stored['environment'] ) ) {
					$stored = EnvironmentPolicy::bind_operation( $stored );
				}
				if ( 0 < $result['target_resource_id'] ) {
					$stored['target_resource_id'] = $result['target_resource_id'];
				}
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
	public static function verify( WP_REST_Request $request ) {
		$operation_id = (string) $request->get_param( 'operation_id' );
		$operation    = OperationStore::get( $operation_id );
		if ( ! is_array( $operation ) || self::ADAPTER !== ( $operation['adapter'] ?? '' ) ) {
			return new WP_Error( 'seo_geo_manager_navigation_operation_not_found', 'Navigation operation not found.', array( 'status' => 404 ) );
		}

		$result = NavigationChangeAdapter::verify( $operation );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$result['operation_id'] = $operation_id;
		$result['environment']  = EnvironmentPolicy::snapshot();
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

	/**
	 * Resolve an optional published WordPress target resource into the existing
	 * bounded suggested_url adapter contract. Existing remote callers that
	 * already use suggested_url remain compatible; the wp-admin workspace uses
	 * target_resource_id exclusively.
	 *
	 * @param mixed $raw_payload Request payload.
	 * @return array<string, mixed>|WP_Error
	 */
	private static function prepare_target_resource( $raw_payload ) {
		$payload = is_array( $raw_payload ) ? $raw_payload : array();
		$target  = isset( $payload['target_resource_id'] ) ? absint( $payload['target_resource_id'] ) : 0;
		if ( 1 > $target ) {
			return $payload;
		}

		$post = get_post( $target );
		if ( ! $post instanceof WP_Post || 'publish' !== $post->post_status || ! is_post_type_viewable( $post->post_type ) ) {
			return new WP_Error(
				'seo_geo_manager_navigation_target_invalid',
				'Navigation workspace targets must be published viewable WordPress resources.',
				array( 'status' => 409 )
			);
		}
		$permalink = get_permalink( $post );
		if ( ! is_string( $permalink ) || '' === $permalink ) {
			return new WP_Error( 'seo_geo_manager_navigation_target_permalink_missing', 'Navigation target permalink is unavailable.', array( 'status' => 409 ) );
		}

		$payload['suggested_url'] = $permalink;
		return $payload;
	}

	/**
	 * @param array<string, mixed> $payload Target-resolution payload.
	 * @return array<string, mixed>
	 */
	private static function target_summary( array $payload ): array {
		$target = isset( $payload['target_resource_id'] ) ? absint( $payload['target_resource_id'] ) : 0;
		if ( 1 > $target ) {
			return array();
		}
		$post = get_post( $target );
		if ( ! $post instanceof WP_Post ) {
			return array();
		}
		return array(
			'id'        => $target,
			'type'      => (string) $post->post_type,
			'status'    => (string) $post->post_status,
			'title'     => html_entity_decode( get_the_title( $post ), ENT_QUOTES | ENT_HTML5, get_bloginfo( 'charset' ) ),
			'permalink' => (string) get_permalink( $post ),
		);
	}
}
