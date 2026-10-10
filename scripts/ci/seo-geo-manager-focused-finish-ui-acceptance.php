<?php
/**
 * Static acceptance for the focused Build / Finish operator surface.
 */

declare(strict_types=1);

$root = dirname( __DIR__, 2 );
$plugin = $root . '/packages/seo-geo-manager/seo-geo-manager.php';
$dashboard = $root . '/packages/seo-geo-manager/src/Admin/Dashboard.php';
$field_gate = $root . '/packages/seo-geo-manager/src/Admin/FieldGate.php';
$actions = $root . '/packages/seo-geo-manager/assets/admin/finalization-actions.js';
$styles = $root . '/packages/seo-geo-manager/assets/admin/focused-finalization.css';
$doc = $root . '/docs/SEO_GEO_MANAGER_0.3.31_FOCUSED_FINISH_UI.md';

foreach ( array( $plugin, $dashboard, $field_gate, $actions, $styles, $doc ) as $file ) {
	if ( ! is_file( $file ) ) {
		fwrite( STDERR, "Missing focused Finish UI file: {$file}\n" );
		exit( 1 );
	}
}

$plugin_source = (string) file_get_contents( $plugin );
$dashboard_source = (string) file_get_contents( $dashboard );
$field_gate_source = (string) file_get_contents( $field_gate );
$actions_source = (string) file_get_contents( $actions );

$required_plugin = array(
	'Version: 0.3.31',
	"SEO_GEO_MANAGER_VERSION', '0.3.31'",
);
$required_dashboard = array(
	'Finalizar migración SEO/GEO',
	'data-seo-geo-field-slot',
	'data-seo-geo-finalization-actions',
	'Detalles técnicos y herramientas avanzadas',
	'focused-finalization.css',
);
$required_field_gate = array(
	'seo-geo-manager-finalization-actions',
	'finalization-actions.js',
	'seo-geo-manager-field-gate',
);
$required_actions = array(
	'/permalinks/redirect-runtime/preview',
	'/permalinks/redirect-apply',
	'confirm_permalink_change: true',
	'confirm_redirect_runtime: true',
	'environment_fingerprint:',
	'Previsualizar operación final',
	'Aplicar estructura + 301',
	'Revertir operación',
	'local_slug_repair_count',
	'collision_count',
);

foreach ( $required_plugin as $needle ) {
	if ( false === strpos( $plugin_source, $needle ) ) {
		fwrite( STDERR, "Focused Finish UI version contract missing: {$needle}\n" );
		exit( 1 );
	}
}
foreach ( $required_dashboard as $needle ) {
	if ( false === strpos( $dashboard_source, $needle ) ) {
		fwrite( STDERR, "Focused dashboard contract missing: {$needle}\n" );
		exit( 1 );
	}
}
foreach ( $required_field_gate as $needle ) {
	if ( false === strpos( $field_gate_source, $needle ) ) {
		fwrite( STDERR, "Focused Field Gate contract missing: {$needle}\n" );
		exit( 1 );
	}
}
foreach ( $required_actions as $needle ) {
	if ( false === strpos( $actions_source, $needle ) ) {
		fwrite( STDERR, "Focused finalization action contract missing: {$needle}\n" );
		exit( 1 );
	}
}

if ( false !== strpos( $actions_source, 'emmake.com' ) ) {
	fwrite( STDERR, "Focused finalization UI must not hardcode the EMMAKE domain.\n" );
	exit( 1 );
}

if ( false === strpos( $dashboard_source, '<details class="seo-geo-manager-admin__technical">' ) ) {
	fwrite( STDERR, "Advanced diagnostics must remain collapsed behind the technical details surface.\n" );
	exit( 1 );
}

fwrite(
	STDOUT,
	json_encode(
		array(
			'ok' => true,
			'version' => '0.3.31',
			'focused_finish_ui' => true,
			'advanced_diagnostics_collapsed' => true,
			'atomic_301_action_present' => true,
			'rollback_present' => true,
			'no_hardcoded_client_domain' => true,
		),
		JSON_UNESCAPED_SLASHES
	) . PHP_EOL
);
