<?php
/**
 * Plugin Name: SEO/GEO Migration Bridge
 * Plugin URI: https://github.com/Emmakex/template-wordpress-seo-geo
 * Description: Safe existing-site analysis and SEO/GEO baseline tooling for WordPress migrations.
 * Version: 0.8.15
 * Requires at least: 7.1
 * Requires PHP: 8.2
 * Author: Eduardo Yauri
 * Text Domain: seo-geo-migration-bridge
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SEO_GEO_MIGRATION_BRIDGE_VERSION', '0.8.15' );
define( 'SEO_GEO_MIGRATION_BRIDGE_DIR', plugin_dir_path( __FILE__ ) );

spl_autoload_register(
	static function ( string $class_name ): void {
		$prefix = 'SeoGeo\\MigrationBridge\\';

		if ( 0 !== strncmp( $class_name, $prefix, strlen( $prefix ) ) ) {
			return;
		}

		$relative = substr( $class_name, strlen( $prefix ) );
		$path     = SEO_GEO_MIGRATION_BRIDGE_DIR . 'src/' . str_replace( '\\', '/', $relative ) . '.php';

		if ( is_readable( $path ) ) {
			require_once $path;
		}
	}
);

SeoGeo\MigrationBridge\Plugin::boot();
