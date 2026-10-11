<?php
/**
 * Offline contract for the permanent SEO/GEO Manager control-plane dashboard.
 */

declare(strict_types=1);

$root = dirname( __DIR__, 2 );
$dashboard_path = $root . '/packages/seo-geo-manager/src/Admin/WorkspaceDashboard.php';
$plugin_path = $root . '/packages/seo-geo-manager/src/Plugin.php';
$style_path = $root . '/packages/seo-geo-manager/assets/admin/workspace-dashboard.css';

foreach ( array( $dashboard_path, $plugin_path, $style_path ) as $path ) {
	if ( ! is_readable( $path ) ) {
		fwrite( STDERR, 'Missing workspace dashboard contract file: ' . basename( $path ) . PHP_EOL );
		exit( 1 );
	}
}

$dashboard = file_get_contents( $dashboard_path );
$plugin = file_get_contents( $plugin_path );
$style = file_get_contents( $style_path );

if ( false === $dashboard || false === $plugin || false === $style ) {
	fwrite( STDERR, "Could not read workspace dashboard contract sources.\n" );
	exit( 1 );
}

$required_dashboard_markers = array(
	'Control plane · SEO/GEO',
	'Build / Finish',
	'Optimize',
	'Grow',
	'Estado actual y prioridades',
	'data-seo-geo-action="analyze"',
	'data-seo-geo-field-slot',
	'data-seo-geo-finalization-actions',
	'seo-geo-manager-admin__technical-body',
	'data-seo-geo-raw',
	'La migración es solo una fase del ciclo de vida.',
);

foreach ( $required_dashboard_markers as $marker ) {
	if ( false === strpos( $dashboard, $marker ) ) {
		fwrite( STDERR, 'Workspace dashboard contract missing marker: ' . $marker . PHP_EOL );
		exit( 1 );
	}
}

if ( false !== strpos( $dashboard, 'Finalizar migración SEO/GEO' ) ) {
	fwrite( STDERR, "Permanent Manager dashboard regressed to migration-only identity.\n" );
	exit( 1 );
}

if ( false === strpos( $plugin, 'WorkspaceDashboard::register();' ) ) {
	fwrite( STDERR, "Plugin does not register the permanent workspace dashboard.\n" );
	exit( 1 );
}

if ( false !== strpos( $plugin, 'Dashboard::register();' ) ) {
	fwrite( STDERR, "Legacy migration-focused Dashboard is still registered.\n" );
	exit( 1 );
}

foreach ( array( 'seo-geo-manager-workspaces', 'seo-geo-manager-workspace-card', 'seo-geo-manager-workspace__build-finish' ) as $selector ) {
	if ( false === strpos( $style, $selector ) ) {
		fwrite( STDERR, 'Workspace dashboard stylesheet missing selector: ' . $selector . PHP_EOL );
		exit( 1 );
	}
}

echo json_encode(
	array(
		'ok' => true,
		'permanent_control_plane' => true,
		'workspaces' => array( 'build-finish', 'optimize', 'grow' ),
		'migration_is_bounded_tool' => true,
		'field_gate_mount_preserved' => true,
		'operation_history_mount_preserved' => true,
	),
	JSON_UNESCAPED_SLASHES
) . PHP_EOL;
