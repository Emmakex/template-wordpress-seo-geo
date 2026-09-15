<?php
/**
 * Plugin Name: SEO GEO Core
 * Plugin URI: https://github.com/Emmakex/template-wordpress-seo-geo
 * Description: Durable SEO, GEO, multilingual and Schema foundation for the SEO GEO Starter theme.
 * Version: 0.1.0
 * Requires at least: 7.1
 * Requires PHP: 8.2
 * Author: Eduardo Yauri
 * Text Domain: seo-geo-core
 * Domain Path: /languages
 *
 * @package SeoGeoCore
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SEO_GEO_CORE_VERSION', '0.1.0' );
define( 'SEO_GEO_CORE_FILE', __FILE__ );
define( 'SEO_GEO_CORE_DIR', plugin_dir_path( __FILE__ ) );

spl_autoload_register(
	static function ( string $class_name ): void {
		$prefix = 'SeoGeo\\Core\\';

		if ( 0 !== strncmp( $class_name, $prefix, strlen( $prefix ) ) ) {
			return;
		}

		$relative = substr( $class_name, strlen( $prefix ) );
		$path     = SEO_GEO_CORE_DIR . 'src/' . str_replace( '\\', '/', $relative ) . '.php';

		if ( is_readable( $path ) ) {
			require_once $path;
		}
	}
);

SeoGeo\Core\Plugin::boot( SEO_GEO_CORE_FILE );
