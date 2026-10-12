<?php
/**
 * Static contract for Manager 0.3.62 Navigation Workspace.
 *
 * @package SeoGeoManager
 */
declare(strict_types=1);

$root = dirname( __DIR__, 2 );
$files = array(
	'plugin'     => $root . '/packages/seo-geo-manager/seo-geo-manager.php',
	'bootstrap'  => $root . '/packages/seo-geo-manager/src/Plugin.php',
	'workspace'  => $root . '/packages/seo-geo-manager/src/Admin/NavigationWorkspace.php',
	'controller' => $root . '/packages/seo-geo-manager/src/Rest/NavigationChangeController.php',
	'adapter'    => $root . '/packages/seo-geo-manager/src/Changes/NavigationChangeAdapter.php',
	'javascript' => $root . '/packages/seo-geo-manager/assets/admin/navigation-workspace.js',
	'css'        => $root . '/packages/seo-geo-manager/assets/admin/navigation-workspace.css',
);

foreach ( $files as $label => $path ) {
	if ( ! is_readable( $path ) ) {
		fwrite( STDERR, "Navigation Workspace contract missing {$label}: {$path}\n" );
		exit( 1 );
	}
}

$read = static function ( string $path ): string {
	$content = file_get_contents( $path );
	if ( false === $content ) {
		fwrite( STDERR, "Unable to read {$path}\n" );
		exit( 1 );
	}
	return $content;
};

$plugin     = $read( $files['plugin'] );
$bootstrap  = $read( $files['bootstrap'] );
$workspace  = $read( $files['workspace'] );
$controller = $read( $files['controller'] );
$adapter    = $read( $files['adapter'] );
$javascript = $read( $files['javascript'] );

$required = array(
	'0.3.62 version'                           => array( $plugin, 'Version: 0.3.62' ),
	'0.3.62 constant'                          => array( $plugin, "SEO_GEO_MANAGER_VERSION', '0.3.62" ),
	'NavigationWorkspace bootstrap import'      => array( $bootstrap, 'Admin\\NavigationWorkspace' ),
	'NavigationWorkspace registration'          => array( $bootstrap, 'NavigationWorkspace::register()' ),
	'navigation asset handle'                   => array( $workspace, 'seo-geo-manager-navigation-workspace' ),
	'menu inventory route'                      => array( $controller, "'/navigation'" ),
	'menu inspection route'                     => array( $controller, "'/navigation/(?P<menu_id>\\d+)'" ),
	'operation verification route'              => array( $controller, '/verify' ),
	'published resource resolver'                => array( $controller, 'prepare_target_resource' ),
	'published-only target policy'               => array( $controller, "'publish' !== \$post->post_status" ),
	'target resource id in workspace'            => array( $javascript, 'target_resource_id' ),
	'explicit public-navigation acknowledgement' => array( $javascript, 'allow_public_navigation' ),
	'idempotency key'                            => array( $javascript, 'idempotency_key' ),
	'post-apply verify call'                     => array( $javascript, '/verify' ),
	'rollback action'                            => array( $javascript, '/rollback' ),
	'rollback response fingerprint authority'    => array( $javascript, "result.before_fingerprint || ''" ),
	'rollback exact fingerprint readback'         => array( $javascript, 'state.menu?.fingerprint !== expectedBeforeFingerprint' ),
	'rollback remains available after Apply'      => array( $javascript, 'El rollback seguro permanece disponible.' ),
	'custom-link adapter boundary'               => array( $adapter, "'custom' !==" ),
	'stale rollback boundary'                    => array( $adapter, 'navigation_rollback_stale' ),
);

foreach ( $required as $label => $contract ) {
	$haystack = $contract[0];
	$needle   = $contract[1];
	if ( false === strpos( $haystack, $needle ) ) {
		fwrite( STDERR, "Navigation Workspace contract missing {$label}: {$needle}\n" );
		exit( 1 );
	}
}

if ( false !== strpos( $javascript, 'suggested_url' ) ) {
	fwrite( STDERR, "Navigation Workspace UI must not expose an arbitrary suggested_url writer.\n" );
	exit( 1 );
}

if ( false !== strpos( $javascript, '<input type="url"' ) ) {
	fwrite( STDERR, "Navigation Workspace UI must not expose arbitrary URL input.\n" );
	exit( 1 );
}

if ( false !== strpos( $javascript, 'state.beforeFingerprint' ) ) {
	fwrite( STDERR, "Navigation Workspace must verify rollback against the stored operation fingerprint, not mutable UI inspection state.\n" );
	exit( 1 );
}

$apply_start    = strpos( $javascript, 'async function apply()' );
$rollback_start = strpos( $javascript, 'async function rollback()' );
if ( false === $apply_start || false === $rollback_start || $rollback_start <= $apply_start ) {
	fwrite( STDERR, "Navigation Workspace apply/rollback functions are unavailable for contract inspection.\n" );
	exit( 1 );
}
$apply_body = substr( $javascript, $apply_start, $rollback_start - $apply_start );
if ( false !== strpos( $apply_body, 'await inspectMenu()' ) ) {
	fwrite( STDERR, "Navigation Workspace Apply must keep the verified operation editor and rollback action visible until the operator chooses the next step.\n" );
	exit( 1 );
}

fwrite( STDOUT, "SEO/GEO Manager 0.3.62 Navigation Workspace contract OK.\n" );
