<?php
/**
 * Site intelligence endpoint for Build / Finish workflows.
 *
 * @package SeoGeoManager
 */

declare(strict_types=1);

namespace SeoGeo\Manager\Rest;

use SeoGeo\Manager\Intelligence\BuildFinishReadiness;
use SeoGeo\Manager\Intelligence\MediaIntelligenceScanner;
use SeoGeo\Manager\Intelligence\SeoAuthorityScanner;
use SeoGeo\Manager\Intelligence\SiteIntelligenceScanner;
use SeoGeo\Manager\Intelligence\ThemeContractScanner;
use WP_REST_Request;
use WP_REST_Response;

final class SiteIntelligenceController {
	private const NAMESPACE = 'seo-geo-manager/v1';

	public static function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/site/intelligence',
			array(
				'methods'             => 'GET',
				'callback'            => array( self::class, 'show' ),
				'permission_callback' => array( self::class, 'can_read' ),
				'args'                => array(
					'include_rendered' => array(
						'default'           => false,
						'sanitize_callback' => 'rest_sanitize_boolean',
					),
				),
			)
		);
	}

	public static function can_read(): bool {
		return current_user_can( 'edit_posts' );
	}

	public static function show( WP_REST_Request $request ): WP_REST_Response {
		$include_rendered = true === filter_var( $request->get_param( 'include_rendered' ), FILTER_VALIDATE_BOOLEAN );
		$site             = SiteIntelligenceScanner::scan( $include_rendered );
		$theme            = ThemeContractScanner::scan();
		$seo              = SeoAuthorityScanner::scan();
		$media            = MediaIntelligenceScanner::scan();

		$site['theme_contract']         = $theme;
		$site['seo_authority']          = $seo;
		$site['media_intelligence']     = $media;
		$site['build_finish_readiness'] = BuildFinishReadiness::aggregate( $site, $theme, $seo, $media );

		return new WP_REST_Response( $site, 200 );
	}
}
