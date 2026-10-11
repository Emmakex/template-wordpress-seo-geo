<?php
/**
 * Authenticated read-only remote surface for SEO/GEO Manager.
 *
 * @package SeoGeoManager
 */

declare(strict_types=1);

namespace SeoGeo\Manager\Rest;

use SeoGeo\Manager\Changes\NavigationChangeAdapter;
use SeoGeo\Manager\Intelligence\ContextualLinkReader;
use SeoGeo\Manager\Intelligence\SeoAuthorityScanner;
use SeoGeo\Manager\Intelligence\SiteIntelligenceScanner;
use SeoGeo\Manager\Remote\Audit;
use SeoGeo\Manager\Remote\Authenticator;
use SeoGeo\Manager\Remote\Credentials;
use SeoGeo\Manager\Remote\MutationOperations;
use SeoGeo\Manager\Remote\RequestGuard;
use SeoGeo\Manager\Remote\TokenService;
use SeoGeo\Manager\Support\EnvironmentPolicy;
use SeoGeo\Manager\Support\PackageIdentity;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

final class RemoteReadController {
	private const NAMESPACE = 'seo-geo-manager/v1';

	private static ?Credentials $credentials = null;
	private static ?Audit $audit = null;
	private static ?Authenticator $authenticator = null;

	public static function register_routes(): void {
		self::register_read_route( '/remote/status', 'site.read', 'status' );
		self::register_read_route( '/remote/capabilities', 'site.read', 'capabilities' );
		self::register_read_route( '/remote/site-intelligence', 'site.diagnostics', 'site_intelligence' );
		self::register_read_route( '/remote/contextual-links', 'site.diagnostics', 'contextual_links' );
		self::register_read_route( '/remote/navigation', 'site.diagnostics', 'navigation' );
		self::register_read_route( '/remote/audit', 'site.diagnostics', 'audit' );
	}

	public static function status( WP_REST_Request $request ): WP_REST_Response {
		$connection = self::credentials()->state();
		$seo        = SeoAuthorityScanner::scan();

		return self::response(
			$request,
			array(
				'manager'       => array(
					'version'          => SEO_GEO_MANAGER_VERSION,
					'package'          => PackageIdentity::snapshot(),
					'api_namespace'    => self::NAMESPACE,
					'remote_milestone' => 'R3.5',
				),
				'environment'   => EnvironmentPolicy::snapshot(),
				'seo_authority' => array(
					'state'     => (string) ( $seo['state'] ?? '' ),
					'conflict'  => true === ( $seo['conflict'] ?? false ),
					'authority' => is_array( $seo['authority'] ?? null ) ? $seo['authority'] : array(),
				),
				'remote'        => array(
					'connection_id' => (string) ( $connection['connection_id'] ?? '' ),
					'enabled'       => ! empty( $connection['enabled'] ),
					'generation'    => (int) ( $connection['generation'] ?? 0 ),
					'last_used_at'  => (string) ( $connection['last_used_at'] ?? '' ),
				),
			)
		);
	}

	public static function capabilities( WP_REST_Request $request ): WP_REST_Response {
		$context    = self::authenticator()->context( $request );
		$connection = is_array( $context['connection'] ?? null ) ? $context['connection'] : array();

		return self::response(
			$request,
			array(
				'connection_id'            => (string) ( $connection['connection_id'] ?? '' ),
				'granted_scopes'           => is_array( $connection['scopes'] ?? null ) ? array_values( $connection['scopes'] ) : array(),
				'supported_scopes'         => Credentials::supported_scopes(),
				'writes_granted_by_scopes' => ! empty( $connection['writes_enabled'] ),
				'read_surface'             => array(
					'/remote/status'            => 'site.read',
					'/remote/capabilities'      => 'site.read',
					'/remote/site-intelligence' => 'site.diagnostics',
					'/remote/contextual-links'  => 'site.diagnostics',
					'/remote/navigation'        => 'site.diagnostics',
					'/remote/audit'             => 'site.diagnostics',
				),
				'mutation_transport'       => array(
					'available'            => true,
					'milestone'            => 'R3.5',
					'supported_operations' => MutationOperations::supported(),
					'lifecycle'            => array( 'plan', 'apply', 'verify', 'rollback' ),
					'plan_ttl_seconds'     => 15 * MINUTE_IN_SECONDS,
					'routes'               => array(
						'plan'           => 'POST /remote/plans',
						'plan_read'      => 'GET /remote/plans/{plan_id}',
						'apply'          => 'POST /remote/plans/{plan_id}/apply',
						'operation_read' => 'GET /remote/operations/{operation_id}',
						'verify'         => 'POST /remote/operations/{operation_id}/verify',
						'rollback'       => 'POST /remote/operations/{operation_id}/rollback',
					),
					'required_scopes'      => array(
						'plan'           => 'content.write',
						'apply'          => 'operations.apply',
						'operation_read' => 'operations.read',
						'verify'         => 'operations.read',
						'rollback'       => 'operations.rollback',
					),
					'generic_proxy'        => false,
					'raw_metadata_write'   => false,
				),
				'safety'                   => array(
					'https_outside_local_development' => true,
					'request_id_required'             => true,
					'nonce_required'                  => true,
					'timestamp_required'              => true,
					'replay_protection'               => true,
					'rate_limit'                      => true,
					'idempotency'                     => true,
					'plan_connection_binding'         => true,
					'environment_binding'             => true,
					'target_fingerprint_required'     => true,
					'exact_post_write_verification'   => true,
					'stale_safe_rollback'             => true,
					'bounded_audit'                   => true,
				),
			)
		);
	}

	public static function site_intelligence( WP_REST_Request $request ): WP_REST_Response {
		$rendered = in_array( strtolower( (string) $request->get_param( 'rendered' ) ), array( '1', 'true', 'yes' ), true );

		return self::response( $request, SiteIntelligenceScanner::scan( $rendered ) );
	}

	/**
	 * Return bounded contextual-link edges with stable edge IDs for remote planning.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public static function contextual_links( WP_REST_Request $request ) {
		$source_id = absint( $request->get_param( 'source_id' ) );
		$result    = ContextualLinkReader::read( $source_id );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return self::response( $request, $result );
	}

	/**
	 * Return one bounded navigation menu using the adapter's canonical fingerprint.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public static function navigation( WP_REST_Request $request ) {
		$menu_id = absint( $request->get_param( 'menu_id' ) );
		$result  = NavigationChangeAdapter::inspect( $menu_id );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return self::response( $request, $result );
	}

	public static function audit( WP_REST_Request $request ): WP_REST_Response {
		$limit = max( 1, min( 100, absint( $request->get_param( 'limit' ) ?: 50 ) ) );

		return self::response(
			$request,
			array(
				'bounded' => true,
				'items'   => self::audit_store()->recent( $limit ),
			)
		);
	}

	private static function register_read_route( string $route, string $scope, string $method ): void {
		register_rest_route(
			self::NAMESPACE,
			$route,
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( self::class, $method ),
				'permission_callback' => static fn ( WP_REST_Request $request ): bool|WP_Error => self::authenticator()->authorize( $request, $scope ),
			)
		);
	}

	/**
	 * Build a bounded authenticated remote response.
	 *
	 * @param WP_REST_Request      $request Authenticated remote request.
	 * @param array<string, mixed> $data    Bounded response data.
	 */
	private static function response( WP_REST_Request $request, array $data ): WP_REST_Response {
		$context  = self::authenticator()->context( $request );
		$metadata = is_array( $context['metadata'] ?? null ) ? $context['metadata'] : array();
		self::authenticator()->record_success( $request );

		return new WP_REST_Response(
			array(
				'ok'         => true,
				'request_id' => (string) ( $metadata['request_id'] ?? '' ),
				'data'       => $data,
			),
			200
		);
	}

	private static function credentials(): Credentials {
		if ( null === self::$credentials ) {
			self::$credentials = new Credentials();
		}

		return self::$credentials;
	}

	private static function audit_store(): Audit {
		if ( null === self::$audit ) {
			self::$audit = new Audit();
		}

		return self::$audit;
	}

	private static function authenticator(): Authenticator {
		if ( null === self::$authenticator ) {
			$credentials         = self::credentials();
			self::$authenticator = new Authenticator( $credentials, new TokenService( $credentials ), new RequestGuard(), self::audit_store() );
		}

		return self::$authenticator;
	}
}
