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

require_once __DIR__ . '/Strategic/StrategicMediaContract.php';
require_once __DIR__ . '/Strategic/StrategicModelMediaState.php';
require_once __DIR__ . '/Strategic/CorporateHomeModelResolver.php';
require_once __DIR__ . '/Strategic/CorporateHomeRenderer.php';
require_once __DIR__ . '/Strategic/StrategicSurfaceRuntime.php';

\SeoGeo\Theme\Strategic\StrategicModelMediaState::register();

/** Return one effective image attachment ID for a declared strategic media slot. */
function seo_geo_theme_strategic_media_slot( int $post_id, string $model_id, string $slot_id ): int {
	return \SeoGeo\Theme\Strategic\StrategicModelMediaState::slot( $post_id, $model_id, $slot_id );
}

/** Return a stable fingerprint for one strategic media binding. */
function seo_geo_theme_strategic_media_fingerprint( int $post_id, string $model_id, string $slot_id ): string {
	return \SeoGeo\Theme\Strategic\StrategicModelMediaState::fingerprint( $post_id, $model_id, $slot_id );
}

/**
 * Bind one declared strategic media slot to a WordPress image attachment.
 *
 * @return true|WP_Error
 */
function seo_geo_theme_set_strategic_media_slot( int $post_id, string $model_id, string $slot_id, int $attachment_id ) {
	return \SeoGeo\Theme\Strategic\StrategicModelMediaState::set_slot( $post_id, $model_id, $slot_id, $attachment_id );
}

/**
 * Remove one declared strategic media binding.
 *
 * @return true|WP_Error
 */
function seo_geo_theme_remove_strategic_media_slot( int $post_id, string $model_id, string $slot_id ) {
	return \SeoGeo\Theme\Strategic\StrategicModelMediaState::remove_slot( $post_id, $model_id, $slot_id );
}

/**
 * Return the declared media slots for one active-preset semantic model.
 *
 * @return array<string, array{type:string,required:bool,accept:string}>
 */
function seo_geo_theme_strategic_media_contract( string $model_id ): array {
	return \SeoGeo\Theme\Strategic\StrategicMediaContract::model_slots( $model_id );
}

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
