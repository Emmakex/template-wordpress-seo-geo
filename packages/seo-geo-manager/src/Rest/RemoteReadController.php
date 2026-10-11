<?php
/**
 * Authenticated read-only remote surface for SEO/GEO Manager.
 *
 * @package SeoGeoManager
 */

declare(strict_types=1);

namespace SeoGeo\Manager\Rest;

use SeoGeo\Manager\Intelligence\SeoAuthorityScanner;
use SeoGeo\Manager\Intelligence\SiteIntelligenceScanner;
use SeoGeo\Manager\Remote\Audit;
use SeoGeo\Manager\Remote\Authenticator;
use SeoGeo\Manager\Remote\Credentials;
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
		self::register_read_route( '/remote/audit', 'site.diagnostics', 'audit' );
	}

	public static function status( WP_REST_Request $request ): WP_REST_Response {
		$connection = self::credentials()->state();
		$seo = SeoAuthorityScanner::scan();

		return self::response(
			$request,
			array(
				'manager' => array(
					'version' => SEO_GEO_MANAGER_VERSION,
					'package' => PackageIdentity::snapshot(),
					'api_namespace' => self::NAMESPACE,
					'remote_milestone' => 'R2',
				),
				'environment' => EnvironmentPolicy::snapshot(),
				'seo_authority' => array(
					'state' => (string) ( $seo['state'] ?? '' ),
					'conflict' => true === ( $seo['conflict'] ?? false ),
					'authority' => is_array( $seo['authority'] ?? null ) ? $seo['authority'] : array(),
				),
				'remote' => array(
					'connection_id' => (string) ( $connection['connection_id'] ?? '' ),
					'enabled' => ! empty( $connection['enabled'] ),
					'generation' => (int) ( $connection['generation'] ?? 0 ),
					'last_used_at' => (string) ( $connection['last_used_at'] ?? '' ),
				),
			)
		);
	}

	public static function capabilities( WP_REST_Request $request ): WP_REST_Response {
		$context = self::authenticator()->context( $request );
		$connection = is_array( $context['connection'] ?? null ) ? $context['connection'] : array();

		return self::response(
			$request,
			array(
				'connection_id' => (string) ( $connection['connection_id'] ?? '' ),
				'granted_scopes' => is_array( $connection['scopes'] ?? null ) ? array_values( $connection['scopes'] ) : array(),
				'supported_scopes' => Credentials::supported_scopes(),
				'writes_granted_by_scopes' => ! empty( $connection['writes_enabled'] ),
				'read_surface' => array(
					'/remote/status' => 'site.read',
					'/remote/capabilities' => 'site.read',
					'/remote/site-intelligence' => 'site.diagnostics',
					'/remote/audit' => 'site.diagnostics',
				),
				'mutation_transport' => array(
					'available' => false,
					'planned_milestone' => 'R3',
					'planned_lifecycle' => array( 'preview', 'apply', 'verify', 'rollback' ),
					'generic_proxy' => false,
				),
				'safety' => array(
					'https_outside_local_development' => true,
					'request_id_required' => true,
					'nonce_required' => true,
					'timestamp_required' => true,
					'replay_protection' => true,
					'rate_limit' => true,
					'bounded_audit' => true,
				),
			)
		);
	}

	public static function site_intelligence( WP_REST_Request $request ): WP_REST_Response {
		$rendered = in_array( strtolower( (string) $request->get_param( 'rendered' ) ), array( '1', 'true', 'yes' ), true );

		return self::response( $request, SiteIntelligenceScanner::scan( $rendered ) );
	}

	public static function audit( WP_REST_Request $request ): WP_REST_Response {
		$limit = max( 1, min( 100, absint( $request->get_param( 'limit' ) ?: 50 ) ) );

		return self::response(
			$request,
			array(
				'bounded' => true,
				'items' => self::audit_store()->recent( $limit ),
			)
		);
	}

	private static function register_read_route( string $route, string $scope, string $method ): void {
		register_rest_route(
			self::NAMESPACE,
			$route,
			array(
				'methods' => WP_REST_Server::READABLE,
				'callback' => array( self::class, $method ),
				'permission_callback' => static fn ( WP_REST_Request $request ): bool|WP_Error => self::authenticator()->authorize( $request, $scope ),
			)
		);
	}

	/**
	 * @param array<string, mixed> $data Bounded response data.
	 */
	private static function response( WP_REST_Request $request, array $data ): WP_REST_Response {
		$context = self::authenticator()->context( $request );
		$metadata = is_array( $context['metadata'] ?? null ) ? $context['metadata'] : array();
		self::authenticator()->record_success( $request );

		return new WP_REST_Response(
			array(
				'ok' => true,
				'request_id' => (string) ( $metadata['request_id'] ?? '' ),
				'data' => $data,
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
			$credentials = self::credentials();
			self::$authenticator = new Authenticator(
				$credentials,
				new TokenService( $credentials ),
				new RequestGuard(),
				self::audit_store()
			);
		}

		return self::$authenticator;
	}
}
