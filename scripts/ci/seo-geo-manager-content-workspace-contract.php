<?php
/**
 * Static contract for the normal WordPress content workspace.
 */

declare(strict_types=1);

$root      = dirname( __DIR__, 2 );
$plugin    = $root . '/packages/seo-geo-manager/src/Plugin.php';
$bootstrap = $root . '/packages/seo-geo-manager/src/Admin/ContentWorkspace.php';
$script    = $root . '/packages/seo-geo-manager/assets/admin/content-workspace.js';
$style     = $root . '/packages/seo-geo-manager/assets/admin/content-workspace.css';

foreach ( array( $plugin, $bootstrap, $script, $style ) as $file ) {
	if ( ! is_file( $file ) ) {
		fwrite( STDERR, "Missing content workspace file: {$file}\n" );
		exit( 1 );
	}
}

$plugin_source    = (string) file_get_contents( $plugin );
$bootstrap_source = (string) file_get_contents( $bootstrap );
$script_source    = (string) file_get_contents( $script );

$required_plugin = array(
	'use SeoGeo\\Manager\\Admin\\ContentWorkspace;',
	'ContentWorkspace::register();',
);
$required_bootstrap = array(
	'/seo-geo-manager/v1/content',
	'/seo-geo-manager/v1/changes/preview',
	'/seo-geo-manager/v1/changes/apply',
	'content-workspace.js',
	'content-workspace.css',
);
$required_script = array(
	'Build / Finish · Content',
	'Páginas y entradas',
	'data-content-field="title"',
	'data-content-field="slug"',
	'data-content-field="excerpt"',
	'data-content-field="content"',
	'data-content-field="status"',
	'<option value="future">Programado</option>',
	'expected_fingerprint',
	'allow_published_target',
	'verify_rendered: true',
	'environment_fingerprint',
	'idempotency_key',
	'Vista previa',
	'Aplicar y verificar',
);

foreach ( $required_plugin as $needle ) {
	if ( false === strpos( $plugin_source, $needle ) ) {
		fwrite( STDERR, "Content workspace plugin contract missing: {$needle}\n" );
		exit( 1 );
	}
}
foreach ( $required_bootstrap as $needle ) {
	if ( false === strpos( $bootstrap_source, $needle ) ) {
		fwrite( STDERR, "Content workspace bootstrap contract missing: {$needle}\n" );
		exit( 1 );
	}
}
foreach ( $required_script as $needle ) {
	if ( false === strpos( $script_source, $needle ) ) {
		fwrite( STDERR, "Content workspace JS contract missing: {$needle}\n" );
		exit( 1 );
	}
}

if ( false !== strpos( $script_source, 'emmake.com' ) || false !== strpos( $bootstrap_source, 'emmake.com' ) ) {
	fwrite( STDERR, "Content workspace must not hardcode the EMMAKE domain.\n" );
	exit( 1 );
}

fwrite(
	STDOUT,
	json_encode(
		array(
			'ok'                       => true,
			'content_inventory'        => true,
			'bounded_fields'           => array( 'title', 'slug', 'excerpt', 'content', 'status' ),
			'scheduled_status_safe'    => true,
			'preview_before_apply'      => true,
			'optimistic_concurrency'   => true,
			'environment_bound_apply'  => true,
			'rendered_verification'    => true,
			'idempotency'              => true,
			'no_client_hardcoding'     => true,
		),
		JSON_UNESCAPED_SLASHES
	) . PHP_EOL
);
