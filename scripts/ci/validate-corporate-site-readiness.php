<?php
/**
 * Validate the final Corporate whole-site readiness surface.
 */

declare(strict_types=1);

$required = array(
	'packages/seo-geo-migration-bridge/src/Reset/CorporateSiteReadiness.php',
	'packages/seo-geo-migration-bridge/src/Reset/AdminCorporateSiteReadinessController.php',
);
foreach ( $required as $path ) {
	if ( ! is_file( $path ) ) {
		fwrite( STDERR, 'Missing whole-site readiness file: ' . $path . PHP_EOL );
		exit( 1 );
	}
}

$readiness = (string) file_get_contents( $required[0] );
foreach (
	array(
		'home-not-ready',
		". '-not-ready'",
		'insights-not-ready',
		'duplicate-page-source-binding',
		'duplicate-public-path-binding',
		'front-and-insights-authority-invalid',
		'placeholder-link-detected',
		'ready_for_global_browser_qa',
		'manual_browser_matrix',
	) as $marker
) {
	if ( ! str_contains( $readiness, $marker ) ) {
		fwrite( STDERR, 'Whole-site readiness contract missing marker: ' . $marker . PHP_EOL );
		exit( 1 );
	}
}

foreach ( array( 'services', 'work', 'about', 'contact' ) as $page_key ) {
	if ( ! str_contains( $readiness, "'" . $page_key . "'" ) ) {
		fwrite( STDERR, 'Whole-site readiness contract missing page key: ' . $page_key . PHP_EOL );
		exit( 1 );
	}
}

$bootstrap = (string) file_get_contents( 'packages/seo-geo-migration-bridge/seo-geo-migration-bridge.php' );
if ( ! str_contains( $bootstrap, 'AdminCorporateSiteReadinessController::boot_from_plugin()' ) ) {
	fwrite( STDERR, "Whole-site readiness administrator screen is not bootstrapped.\n" );
	exit( 1 );
}

printf( "Corporate whole-site readiness contract OK.\n" );
