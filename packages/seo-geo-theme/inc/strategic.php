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
require_once __DIR__ . '/Strategic/StrategicSurfaceRuntime.php';

/** Return the strategic-surface runtime singleton. */
function seo_geo_theme_strategic_surface_runtime(): \SeoGeo\Theme\Strategic\StrategicSurfaceRuntime {
	static $runtime = null;

	if ( ! $runtime instanceof \SeoGeo\Theme\Strategic\StrategicSurfaceRuntime ) {
		$resolver = new \SeoGeo\Theme\Strategic\CorporateHomeModelResolver();
		$renderer = new \SeoGeo\Theme\Strategic\CorporateHomeRenderer( $resolver );
		$runtime  = new \SeoGeo\Theme\Strategic\StrategicSurfaceRuntime( $renderer );
	}

	return $runtime;
}

seo_geo_theme_strategic_surface_runtime()->register();
