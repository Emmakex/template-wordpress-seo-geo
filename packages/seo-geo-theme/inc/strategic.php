<?php
/**
 * Theme-owned strategic frontend bootstrap.
 *
 * @package SeoGeoTheme
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/Strategic/CorporateHomeModelResolver.php';
require_once __DIR__ . '/Strategic/CorporateHomeRenderer.php';
require_once __DIR__ . '/Strategic/ResearchHomeModelResolver.php';
require_once __DIR__ . '/Strategic/ResearchHomeRenderer.php';
require_once __DIR__ . '/Strategic/StrategicSurfaceRuntime.php';

/** Return the strategic-surface runtime singleton. */
function seo_geo_theme_strategic_surface_runtime(): \SeoGeo\Theme\Strategic\StrategicSurfaceRuntime {
	static $runtime = null;

	if ( ! $runtime instanceof \SeoGeo\Theme\Strategic\StrategicSurfaceRuntime ) {
		$corporate_resolver = new \SeoGeo\Theme\Strategic\CorporateHomeModelResolver();
		$corporate_renderer = new \SeoGeo\Theme\Strategic\CorporateHomeRenderer( $corporate_resolver );
		$research_resolver  = new \SeoGeo\Theme\Strategic\ResearchHomeModelResolver();
		$research_renderer  = new \SeoGeo\Theme\Strategic\ResearchHomeRenderer( $research_resolver );
		$runtime            = new \SeoGeo\Theme\Strategic\StrategicSurfaceRuntime( $corporate_renderer, $research_renderer );
	}

	return $runtime;
}

seo_geo_theme_strategic_surface_runtime()->register();
