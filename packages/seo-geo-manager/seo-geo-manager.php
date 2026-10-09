<?php
/**
 * Plugin Name: SEO/GEO Manager
 * Plugin URI: https://github.com/Emmakex/template-wordpress-seo-geo
 * Description: Controlled WordPress content, SEO/GEO optimization and publishing endpoint for agency automation.
 * Version: 0.3.14
 * Requires at least: 6.7
 * Requires PHP: 8.2
 * Author: Eduardo Yauri
 * Text Domain: seo-geo-manager
 *
 * @package SeoGeoManager
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SEO_GEO_MANAGER_VERSION', '0.3.14' );
define( 'SEO_GEO_MANAGER_DIR', plugin_dir_path( __FILE__ ) );
define( 'SEO_GEO_MANAGER_URL', plugin_dir_url( __FILE__ ) );

spl_autoload_register(
	static function ( string $class_name ): void {
		$prefix = 'SeoGeo\\Manager\\';

		if ( 0 !== strncmp( $class_name, $prefix, strlen( $prefix ) ) ) {
			return;
		}

		$relative = substr( $class_name, strlen( $prefix ) );
		$path     = SEO_GEO_MANAGER_DIR . 'src/' . str_replace( '\\', '/', $relative ) . '.php';

		if ( is_readable( $path ) ) {
			require_once $path;
		}
	}
);

SeoGeo\Manager\Plugin::boot();
