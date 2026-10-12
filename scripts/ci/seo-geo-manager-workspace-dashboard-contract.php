<?php
/**
 * Offline contract for the permanent SEO/GEO Manager control-plane dashboard.
 */

declare(strict_types=1);

$root = dirname( __DIR__, 2 );
$dashboard_path = $root . '/packages/seo-geo-manager/src/Admin/WorkspaceDashboard.php';
$plugin_path = $root . '/packages/seo-geo-manager/src/Plugin.php';
$style_path = $root . '/packages/seo-geo-manager/assets/admin/workspace-dashboard.css';
$build_script_path = $root . '/packages/seo-geo-manager/assets/admin/build-workspace.js';

foreach ( array( $dashboard_path, $plugin_path, $style_path, $build_script_path ) as $path ) {
	if ( ! is_readable( $path ) ) {
		fwrite( STDERR, 'Missing workspace dashboard contract file: ' . basename( $path ) . PHP_EOL );
		exit( 1 );
	}
}

$dashboard = file_get_contents( $dashboard_path );
$plugin = file_get_contents( $plugin_path );
$style = file_get_contents( $style_path );
$build_script = file_get_contents( $build_script_path );

if ( false === $dashboard || false === $plugin || false === $style || false === $build_script ) {
	fwrite( STDERR, "Could not read workspace dashboard contract sources.\n" );
	exit( 1 );
}

$required_dashboard_markers = array(
	'Control plane · SEO/GEO',
	'Build / Finish',
	'Optimize',
	'Grow',
	'Construcción del sitio',
	'data-seo-geo-build-workspace',
	'data-seo-geo-build-models',
	'data-seo-geo-build-editor',
	'data-seo-geo-build-preview',
	'data-seo-geo-build-apply',
	'data-seo-geo-action="analyze"',
	'data-seo-geo-field-slot',
	'data-seo-geo-finalization-actions',
	'seo-geo-manager-admin__technical-body',
	'data-seo-geo-raw',
	'themeStructuredPreviewPath',
	'themeStructuredApplyPath',
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

if ( false !== strpos( $plugin, 'use SeoGeo\\Manager\\Admin\\Dashboard;' ) ) {
	fwrite( STDERR, "Legacy migration-focused Dashboard import is still active.\n" );
	exit( 1 );
}

foreach ( array( 'seo-geo-manager-workspaces', 'seo-geo-manager-workspace-card', 'seo-geo-manager-workspace__build-finish', 'seo-geo-manager-build__layout', 'seo-geo-manager-build-slot' ) as $selector ) {
	if ( false === strpos( $style, $selector ) ) {
		fwrite( STDERR, 'Workspace dashboard stylesheet missing selector: ' . $selector . PHP_EOL );
		exit( 1 );
	}
}

$required_build_markers = array(
	'/seo-geo-manager/v1/theme/models',
	'/seo-geo-manager/v1/theme/structured/preview',
	'/seo-geo-manager/v1/theme/structured/apply',
	'expected_fingerprint',
	'idempotency_key',
	'environment_fingerprint',
	'allow_published_target',
	'verified_groups',
	'verify_rendered',
	'Vista previa',
	'Aplicando y verificando el cambio',
);

foreach ( $required_build_markers as $marker ) {
	if ( false === strpos( $build_script, $marker ) ) {
		fwrite( STDERR, 'Theme build workspace contract missing marker: ' . $marker . PHP_EOL );
		exit( 1 );
	}
}

if ( false !== strpos( $build_script, 'wp_update_post' ) || false !== strpos( $build_script, '_yoast_wpseo_' ) ) {
	fwrite( STDERR, "Build workspace must use accepted Manager semantic endpoints, not direct WordPress/provider writes.\n" );
	exit( 1 );
}

echo json_encode(
	array(
		'ok' => true,
		'permanent_control_plane' => true,
		'workspaces' => array( 'build-finish', 'optimize', 'grow' ),
		'theme_build_workspace' => true,
		'preview_before_apply' => true,
		'optimistic_concurrency' => true,
		'environment_bound_apply' => true,
		'layout_owned_by_theme' => true,
		'migration_is_bounded_tool' => true,
		'field_gate_mount_preserved' => true,
		'operation_history_mount_preserved' => true,
	),
	JSON_UNESCAPED_SLASHES
) . PHP_EOL;
