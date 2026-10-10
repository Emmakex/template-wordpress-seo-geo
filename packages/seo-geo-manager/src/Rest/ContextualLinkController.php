<?php
/**
 * REST endpoint for bounded contextual-link discovery.
 *
 * @package SeoGeoManager
 */
declare(strict_types=1);

namespace SeoGeo\Manager\Rest;

use SeoGeo\Manager\Intelligence\ContextualLinkReader;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

final class ContextualLinkController {
	private const NAMESPACE = 'seo-geo-manager/v1';

	public static function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/links/contextual',
			array(
				'methods'             => 'GET',
				'callback'            => array( self::class, 'index' ),
				'permission_callback' => array( self::class, 'can_read' ),
				'args'                => array(
					'source_id' => array( 'sanitize_callback' => 'absint' ),
				),
			)
		);
	}

	public static function can_read(): bool {
		return current_user_can( 'edit_posts' ) || current_user_can( 'edit_pages' );
	}

	/** @return WP_REST_Response|WP_Error */
	public static function index( WP_REST_Request $request ) {
		$source_id = absint( $request->get_param( 'source_id' ) );
		$result    = ContextualLinkReader::read( $source_id );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return new WP_REST_Response( $result, 200 );
	}
}
