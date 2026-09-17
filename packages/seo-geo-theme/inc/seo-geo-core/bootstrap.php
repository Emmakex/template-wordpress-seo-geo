<?php
/**
 * Theme-owned SEO/GEO runtime bootstrap.
 *
 * The distributable theme bundles the Core source tree under this directory.
 * In the monorepo, development falls back to the sibling source package so the
 * same classes remain the single source of truth.
 *
 * @package SeoGeoTheme
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$seo_geo_embedded_src = __DIR__ . '/src';
$seo_geo_dev_src      = dirname( __DIR__, 3 ) . '/seo-geo-core/src';
$seo_geo_core_src     = is_dir( $seo_geo_embedded_src ) ? $seo_geo_embedded_src : $seo_geo_dev_src;

if ( ! is_dir( $seo_geo_core_src ) ) {
	return;
}

spl_autoload_register(
	static function ( string $class_name ) use ( $seo_geo_core_src ): void {
		$prefix = 'SeoGeo\\Core\\';

		if ( 0 !== strncmp( $class_name, $prefix, strlen( $prefix ) ) ) {
			return;
		}

		$relative = substr( $class_name, strlen( $prefix ) );
		$path     = $seo_geo_core_src . '/' . str_replace( '\\', '/', $relative ) . '.php';

		if ( is_readable( $path ) ) {
			require_once $path;
		}
	}
);

add_action(
	'after_setup_theme',
	static function (): void {
		load_theme_textdomain( 'seo-geo-core', get_template_directory() . '/languages' );
		\SeoGeo\Core\Runtime::initialize();
	},
	20
);
