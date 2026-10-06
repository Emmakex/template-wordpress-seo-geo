<?php
/**
 * Static acceptance contract for semantic placeholder/content-state handling.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

$root = dirname( __DIR__, 2 );

$state_file = $root . '/packages/seo-geo-migration-bridge/src/Reset/AutomaticHomeContentStateKit.php';
$guard_file = $root . '/packages/seo-geo-migration-bridge/src/Reset/PlaceholderPublishingGuard.php';
$controller = $root . '/packages/seo-geo-migration-bridge/src/Reset/AdminAutomaticHomeContentController.php';
$bootstrap  = $root . '/packages/seo-geo-migration-bridge/seo-geo-migration-bridge.php';
$docs       = $root . '/docs/CONTENT_PLACEHOLDERS.md';

$failures = array();

foreach ( array( $state_file, $guard_file, $controller, $bootstrap, $docs ) as $file ) {
	if ( ! is_file( $file ) ) {
		$failures[] = 'missing-file:' . str_replace( $root . '/', '', $file );
	}
}

/**
 * Assert that a file contains every required contract marker.
 *
 * @param string       $file     File path.
 * @param list<string> $needles  Required markers.
 * @param list<string> $failures Failure accumulator.
 * @param string       $label    Diagnostic label.
 */
function require_markers( string $file, array $needles, array &$failures, string $label ): void {
	$content = is_file( $file ) ? file_get_contents( $file ) : false;
	if ( false === $content ) {
		return;
	}
	foreach ( $needles as $needle ) {
		if ( ! str_contains( $content, $needle ) ) {
			$failures[] = $label . ':missing-marker:' . $needle;
		}
	}
}

require_markers(
	$state_file,
	array(
		"'source'",
		"'derived'",
		"'placeholder'",
		"'publishable'",
		"'placeholder_slots'",
		"'semantic-placeholder-scaffold'",
		'source-summary-not-detected',
		'three-capabilities-not-detected',
		'quality-three-capabilities-not-detected',
		'update_post_meta',
	),
	$failures,
	'content-state'
);

require_markers(
	$guard_file,
	array(
		'wp_insert_post_data',
		'AutomaticHomeContentStateKit::PLACEHOLDER_META',
		"array( 'publish', 'future' )",
		"post_status'] = 'draft'",
	),
	$failures,
	'publish-guard'
);

require_markers(
	$controller,
	array(
		'AutomaticHomeContentStateKit',
		'placeholder_slots',
		'publishing is blocked',
	),
	$failures,
	'controller'
);

require_markers(
	$bootstrap,
	array( 'PlaceholderPublishingGuard::boot();' ),
	$failures,
	'bootstrap'
);

require_markers(
	$docs,
	array(
		'`source`',
		'`derived`',
		'`placeholder`',
		'not publishable',
	),
	$failures,
	'docs'
);

if ( array() !== $failures ) {
	fwrite( STDERR, "Semantic placeholder contract failed:\n- " . implode( "\n- ", $failures ) . "\n" );
	exit( 1 );
}

fwrite( STDOUT, "Semantic placeholder/content-state contract OK.\n" );
