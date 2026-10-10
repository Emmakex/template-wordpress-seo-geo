<?php
/**
 * Permalink inspection, planning, apply and rollback endpoints.
 *
 * @package SeoGeoManager
 */

declare(strict_types=1);

namespace SeoGeo\Manager\Rest;

use SeoGeo\Manager\Changes\OperationStore;
use SeoGeo\Manager\Changes\PermalinkChangeEngine;
use SeoGeo\Manager\Changes\PermalinkRedirectChangeEngine;
use SeoGeo\Manager\Changes\SlugRepairChangeEngine;
use SeoGeo\Manager\Support\AuthoritativePermalinkPlanner;
use SeoGeo\Manager\Support\EnvironmentPolicy;
use SeoGeo\Manager\Support\LegacyPermalinkAuthority;
use SeoGeo\Manager\Support\PermalinkInspector;
use SeoGeo\Manager\Support\PermalinkRedirectPlanner;
use SeoGeo\Manager\Support\PermalinkRedirectRuntime;
use WP_REST_Request;
use WP_REST_Response;

final class PermalinkController {
	private const NAMESPACE = 'seo-geo-manager/v1';

	public static function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/permalinks/preview',
			array(
				'methods'             => 'GET',
				'callback'            => array( self::class, 'preview' ),
				'permission_callback' => array( self::class, 'can_manage' ),
			)
		);
		register_rest_route(
			self::NAMESPACE,
			'/permalinks/redirect-plan',
			array(
				'methods'             => 'GET',
				'callback'            => array( self::class, 'redirect_plan' ),
				'permission_callback' => array( self::class, 'can_manage' ),
			)
		);
		register_rest_route(
			self::NAMESPACE,
			'/permalinks/legacy-authority-preview',
			array(
				'methods'             => 'POST',
				'callback'            => array( self::class, 'legacy_authority_preview' ),
				'permission_callback' => array( self::class, 'can_manage' ),
			)
		);
		register_rest_route(
			self::NAMESPACE,
			'/permalinks/authoritative-plan',
			array(
				'methods'             => 'POST',
				'callback'            => array( self::class, 'authoritative_plan' ),
				'permission_callback' => array( self::class, 'can_manage' ),
			)
		);
		register_rest_route(
			self::NAMESPACE,
			'/permalinks/slug-repair/preview',
			array(
				'methods'             => 'POST',
				'callback'            => array( self::class, 'slug_repair_preview' ),
				'permission_callback' => array( self::class, 'can_manage' ),
			)
		);
		register_rest_route(
			self::NAMESPACE,
			'/permalinks/slug-repair/apply',
			array(
				'methods'             => 'POST',
				'callback'            => array( self::class, 'slug_repair_apply' ),
				'permission_callback' => array( self::class, 'can_manage' ),
			)
		);
		register_rest_route(
			self::NAMESPACE,
			'/permalinks/redirect-runtime',
			array(
				'methods'             => 'GET',
				'callback'            => array( self::class, 'redirect_runtime' ),
				'permission_callback' => array( self::class, 'can_manage' ),
			)
		);
		register_rest_route(
			self::NAMESPACE,
			'/permalinks/redirect-runtime/preview',
			array(
				'methods'             => 'POST',
				'callback'            => array( self::class, 'redirect_runtime_preview' ),
				'permission_callback' => array( self::class, 'can_manage' ),
			)
		);
		register_rest_route(
			self::NAMESPACE,
			'/permalinks/apply',
			array(
				'methods'             => 'POST',
				'callback'            => array( self::class, 'apply' ),
				'permission_callback' => array( self::class, 'can_manage' ),
			)
		);
		register_rest_route(
			self::NAMESPACE,
			'/permalinks/redirect-apply',
			array(
				'methods'             => 'POST',
				'callback'            => array( self::class, 'redirect_apply' ),
				'permission_callback' => array( self::class, 'can_manage' ),
			)
		);
		register_rest_route(
			self::NAMESPACE,
			'/permalinks/operations/(?P<operation_id>[a-f0-9\-]{36})/rollback',
			array(
				'methods'             => 'POST',
				'callback'            => array( self::class, 'rollback' ),
				'permission_callback' => array( self::class, 'can_manage' ),
				'args'                => array(
					'operation_id' => array( 'sanitize_callback' => 'sanitize_text_field' ),
				),
			)
		);
	}

	public static function can_manage(): bool {
		return current_user_can( 'manage_options' );
	}

	public static function preview(): WP_REST_Response {
		return new WP_REST_Response( PermalinkInspector::preview(), 200 );
	}

	public static function redirect_plan(): WP_REST_Response {
		return new WP_REST_Response( PermalinkRedirectPlanner::preview(), 200 );
	}

	public static function legacy_authority_preview( WP_REST_Request $request ): WP_REST_Response {
		$payload = self::payload( $request );
		$base    = isset( $payload['legacy_base_url'] ) && is_string( $payload['legacy_base_url'] ) ? $payload['legacy_base_url'] : '';

		return new WP_REST_Response( LegacyPermalinkAuthority::preview( $base ), 200 );
	}

	public static function authoritative_plan( WP_REST_Request $request ): WP_REST_Response {
		$payload = self::payload( $request );
		$base    = isset( $payload['legacy_base_url'] ) && is_string( $payload['legacy_base_url'] ) ? $payload['legacy_base_url'] : '';
		$expected_authority_fingerprint = isset( $payload['authority_fingerprint'] ) && is_string( $payload['authority_fingerprint'] ) ? $payload['authority_fingerprint'] : '';
		$plan = AuthoritativePermalinkPlanner::preview( $base, $expected_authority_fingerprint );
		$atomic_candidate = true === ( $plan['safe_structure_candidate'] ?? false ) && true === ( $plan['requires_redirect_runtime'] ?? false );
		$plan['atomic_apply_candidate'] = $atomic_candidate;
		if ( $atomic_candidate ) {
			$plan['block_reason'] = 'El Apply directo no puede usarse porque cambian rutas históricas. Valida primero el runtime 301 atómico; si supera sus guardas, usa redirect-apply.';
			$plan['next_action'] = 'preview-atomic-redirect-runtime';
		}

		return new WP_REST_Response( $plan, 200 );
	}

	public static function slug_repair_preview( WP_REST_Request $request ): WP_REST_Response {
		$payload = self::payload( $request );
		$base    = isset( $payload['legacy_base_url'] ) && is_string( $payload['legacy_base_url'] ) ? $payload['legacy_base_url'] : '';
		$authority_fingerprint = isset( $payload['authority_fingerprint'] ) && is_string( $payload['authority_fingerprint'] ) ? $payload['authority_fingerprint'] : '';
		$plan_fingerprint      = isset( $payload['plan_fingerprint'] ) && is_string( $payload['plan_fingerprint'] ) ? $payload['plan_fingerprint'] : '';

		return new WP_REST_Response( SlugRepairChangeEngine::preview( $base, $authority_fingerprint, $plan_fingerprint ), 200 );
	}

	/**
	 * @return WP_REST_Response|\WP_Error
	 */
	public static function slug_repair_apply( WP_REST_Request $request ) {
		$payload = self::payload( $request );
		$guard   = EnvironmentPolicy::validate_payload( $payload );
		if ( is_wp_error( $guard ) ) {
			return $guard;
		}
		$result = SlugRepairChangeEngine::apply( $payload );

		return is_wp_error( $result ) ? $result : new WP_REST_Response( $result, 200 );
	}

	public static function redirect_runtime(): WP_REST_Response {
		return new WP_REST_Response( PermalinkRedirectRuntime::snapshot(), 200 );
	}

	public static function redirect_runtime_preview( WP_REST_Request $request ): WP_REST_Response {
		$payload = self::payload( $request );
		$base    = isset( $payload['legacy_base_url'] ) && is_string( $payload['legacy_base_url'] ) ? $payload['legacy_base_url'] : '';
		$expected_authority_fingerprint = isset( $payload['authority_fingerprint'] ) && is_string( $payload['authority_fingerprint'] ) ? $payload['authority_fingerprint'] : '';
		$plan = AuthoritativePermalinkPlanner::preview( $base, $expected_authority_fingerprint );
		$runtime = PermalinkRedirectRuntime::preview(
			isset( $plan['redirects'] ) && is_array( $plan['redirects'] ) ? $plan['redirects'] : array(),
			isset( $plan['plan_fingerprint'] ) && is_string( $plan['plan_fingerprint'] ) ? $plan['plan_fingerprint'] : '',
			isset( $plan['authoritative_structure'] ) && is_string( $plan['authoritative_structure'] ) ? $plan['authoritative_structure'] : ''
		);
		$runtime['authoritative_plan_safe']        = true === ( $plan['safe_structure_candidate'] ?? false );
		$runtime['authoritative_plan_fingerprint'] = isset( $plan['plan_fingerprint'] ) && is_string( $plan['plan_fingerprint'] ) ? $plan['plan_fingerprint'] : '';
		$runtime['authority_fingerprint']          = isset( $plan['authority_fingerprint'] ) && is_string( $plan['authority_fingerprint'] ) ? $plan['authority_fingerprint'] : '';
		$runtime['requires_redirect_runtime']      = true === ( $plan['requires_redirect_runtime'] ?? false );
		$runtime['atomic_apply_available']         = true === ( $plan['safe_structure_candidate'] ?? false ) && true === ( $plan['requires_redirect_runtime'] ?? false ) && true === ( $runtime['safe_to_activate'] ?? false );
		$runtime['apply_blocked']                  = ! $runtime['atomic_apply_available'];
		$runtime['next_action']                    = $runtime['atomic_apply_available'] ? 'atomic-permalink-redirect-apply' : 'resolve-redirect-runtime-blockers';

		return new WP_REST_Response( $runtime, 200 );
	}

	/**
	 * @return WP_REST_Response|\WP_Error
	 */
	public static function apply( WP_REST_Request $request ) {
		$payload = self::payload( $request );
		$guard   = EnvironmentPolicy::validate_payload( $payload );
		if ( is_wp_error( $guard ) ) {
			return $guard;
		}
		$result = PermalinkChangeEngine::apply( $payload );

		return is_wp_error( $result ) ? $result : new WP_REST_Response( $result, 200 );
	}

	/**
	 * @return WP_REST_Response|\WP_Error
	 */
	public static function redirect_apply( WP_REST_Request $request ) {
		$payload = self::payload( $request );
		$guard   = EnvironmentPolicy::validate_payload( $payload );
		if ( is_wp_error( $guard ) ) {
			return $guard;
		}
		$result = PermalinkRedirectChangeEngine::apply( $payload );

		return is_wp_error( $result ) ? $result : new WP_REST_Response( $result, 200 );
	}

	/**
	 * @return WP_REST_Response|\WP_Error
	 */
	public static function rollback( WP_REST_Request $request ) {
		$payload = self::payload( $request );
		$guard   = EnvironmentPolicy::validate_payload( $payload );
		if ( is_wp_error( $guard ) ) {
			return $guard;
		}

		$operation_id = (string) $request->get_param( 'operation_id' );
		$operation    = OperationStore::get( $operation_id );
		if ( is_array( $operation ) ) {
			$operation_guard = EnvironmentPolicy::validate_operation_environment( $operation );
			if ( is_wp_error( $operation_guard ) ) {
				return $operation_guard;
			}
		}

		$operation_type = is_array( $operation ) && isset( $operation['operation_type'] ) && is_string( $operation['operation_type'] )
			? $operation['operation_type']
			: '';
		if ( 'post-slug-repair' === $operation_type ) {
			$result = SlugRepairChangeEngine::rollback( $operation_id );
		} elseif ( 'permalink-structure-with-redirects' === $operation_type ) {
			$result = PermalinkRedirectChangeEngine::rollback( $operation_id );
		} else {
			$result = PermalinkChangeEngine::rollback( $operation_id );
		}

		return is_wp_error( $result ) ? $result : new WP_REST_Response( $result, 200 );
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function payload( WP_REST_Request $request ): array {
		$payload = $request->get_json_params();

		return is_array( $payload ) ? $payload : array();
	}
}
