<?php
/**
 * Standalone parity check for the Manager runtime tree fingerprint.
 *
 * @package SeoGeoManager
 */

declare(strict_types=1);

if ( 2 !== $argc ) {
	fwrite( STDERR, "Usage: php seo-geo-manager-package-identity-contract.php <manager-directory>\n" );
	exit( 2 );
}

$root = realpath( (string) $argv[1] );
if ( false === $root || ! is_dir( $root ) ) {
	fwrite( STDERR, "Manager directory is invalid.\n" );
	exit( 2 );
}

$plugin = $root . DIRECTORY_SEPARATOR . 'seo-geo-manager.php';
$source = is_readable( $plugin ) ? file_get_contents( $plugin ) : false;
if ( false === $source ) {
	fwrite( STDERR, "Manager plugin file is unreadable.\n" );
	exit( 2 );
}
if ( 1 !== preg_match( "/define\\(\\s*'SEO_GEO_MANAGER_VERSION'\\s*,\\s*'([^']+)'\\s*\\)/", $source, $matches ) ) {
	fwrite( STDERR, "Manager version constant was not found.\n" );
	exit( 2 );
}

define( 'SEO_GEO_MANAGER_DIR', rtrim( $root, DIRECTORY_SEPARATOR ) . DIRECTORY_SEPARATOR );
define( 'SEO_GEO_MANAGER_VERSION', (string) $matches[1] );

require_once $root . '/src/Support/PackageIdentity.php';

$identity = SeoGeo\Manager\Support\PackageIdentity::snapshot();
if ( 1 !== preg_match( '/^[a-f0-9]{64}$/', $identity['fingerprint'] ) ) {
	fwrite( STDERR, "Runtime package fingerprint is invalid.\n" );
	exit( 1 );
}
if ( 1 > $identity['file_count'] ) {
	fwrite( STDERR, "Runtime package file count is invalid.\n" );
	exit( 1 );
}

echo json_encode( $identity, JSON_UNESCAPED_SLASHES ) . PHP_EOL;
