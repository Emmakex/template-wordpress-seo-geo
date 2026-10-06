<?php
/**
 * Validate preset-owned Corporate Single, Archive and 404 surfaces.
 */

declare(strict_types=1);

const CORPORATE_SURFACE_THEME_DIR = 'packages/seo-geo-theme';
const CORPORATE_SURFACE_PRESET_DIR = 'presets/corporate';

/**
 * Stop with an actionable CI message.
 */
function fail_corporate_surface( string $code, string $message, string $path, string $expected, string $received ): never {
	fwrite(
		STDERR,
		sprintf(
			"[%s] %s\nFile: %s\nExpected: %s\nReceived: %s\n",
			$code,
			$message,
			$path,
			$expected,
			$received
		)
	);
	exit( 1 );
}

/**
 * Read a required UTF-8 source file.
 */
function corporate_surface_file( string $path ): string {
	if ( ! is_file( $path ) ) {
		fail_corporate_surface( 'missing-file', 'Required Corporate system surface is missing.', $path, 'existing file', 'missing' );
	}

	$content = file_get_contents( $path );
	if ( false === $content || '' === trim( $content ) ) {
		fail_corporate_surface( 'empty-file', 'Corporate system surface is empty.', $path, 'non-empty source', 'empty' );
	}

	return $content;
}

$runtime_path = CORPORATE_SURFACE_THEME_DIR . '/inc/Templates/PresetTemplateRuntime.php';
$runtime      = corporate_surface_file( $runtime_path );

foreach (
	array(
		"private const OWNED_TEMPLATES = array( 'single', 'archive', '404' );",
		"'corporate'            => 'corporate'",
		"'theme' !== \$template->source",
		"! isset( self::PRESET_TEMPLATE_PREFIXES[ \$preset_id ] )",
		'clone $template',
		"'/preset-templates/' . \$prefix . '-' . \$slug . '.php'",
	) as $required_runtime_fragment
) {
	if ( ! str_contains( $runtime, $required_runtime_fragment ) ) {
		fail_corporate_surface(
			'runtime-contract',
			'Preset template runtime no longer preserves the Corporate untouched-theme replacement and safe-fallback contract.',
			$runtime_path,
			$required_runtime_fragment,
			'fragment missing'
		);
	}
}

$mockup_path = CORPORATE_SURFACE_PRESET_DIR . '/mockup.json';
$mockup_raw  = corporate_surface_file( $mockup_path );
try {
	$mockup = json_decode( $mockup_raw, true, 512, JSON_THROW_ON_ERROR );
} catch ( JsonException $exception ) {
	fail_corporate_surface( 'mockup-json', 'Corporate mockup contract is not valid JSON.', $mockup_path, 'valid JSON', $exception->getMessage() );
}

if ( ! is_array( $mockup ) ) {
	fail_corporate_surface( 'mockup-object', 'Corporate mockup contract must decode to an object.', $mockup_path, 'JSON object', gettype( $mockup ) );
}

foreach ( array( 'single', 'archive', '404' ) as $slug ) {
	if ( true !== ( $mockup['pages'][ $slug ]['required'] ?? null ) ) {
		fail_corporate_surface( 'mockup-required', 'Corporate final system surface must remain required.', $mockup_path . '#pages.' . $slug, 'required=true', 'missing or false' );
	}
}

$surface_paths = array(
	'single'  => CORPORATE_SURFACE_THEME_DIR . '/preset-templates/corporate-single.php',
	'archive' => CORPORATE_SURFACE_THEME_DIR . '/preset-templates/corporate-archive.php',
	'404'     => CORPORATE_SURFACE_THEME_DIR . '/preset-templates/corporate-404.php',
);

foreach ( $surface_paths as $path ) {
	$source = corporate_surface_file( $path );

	foreach ( array( 'wp:template-part {"slug":"header"', 'seo-geo-corporate-system-surface', 'wp:template-part {"slug":"footer"' ) as $fragment ) {
		if ( ! str_contains( $source, $fragment ) ) {
			fail_corporate_surface( 'surface-shell', 'Corporate system surface lost its required final-site shell.', $path, $fragment, 'fragment missing' );
		}
	}

	if ( str_contains( $source, 'seo-geo-placeholder--' ) ) {
		fail_corporate_surface( 'surface-placeholder', 'System surfaces must not rely on client-content placeholders.', $path, 'no placeholder marker classes', 'placeholder marker found' );
	}

	if ( 1 === preg_match( '/https?:\/\/|<\s*script\b|<\s*style\b|application\/ld\+json|schema\.org/i', $source, $match ) ) {
		fail_corporate_surface( 'surface-remote-or-schema', 'System surfaces must remain local, native and non-authoritative for Schema.', $path, 'no remote/script/style/schema payload', $match[0] );
	}
}

$single = corporate_surface_file( $surface_paths['single'] );
if (
	! str_contains( $single, 'wp:post-title {"level":1' )
	|| ! str_contains( $single, 'wp:post-content' )
	|| ! str_contains( $single, 'wp:post-author-name' )
	|| ! str_contains( $single, 'wp:post-navigation-link' )
) {
	fail_corporate_surface( 'single-contract', 'Corporate Single must remain a complete article surface with H1, body, provenance and next/previous navigation.', $surface_paths['single'], 'post-title level=1 + post-content + author + post navigation', 'one or more fragments missing' );
}

$archive = corporate_surface_file( $surface_paths['archive'] );
if (
	! str_contains( $archive, 'wp:query-title {"type":"archive"' )
	|| ! str_contains( $archive, 'wp:post-template' )
	|| ! str_contains( $archive, 'wp:query-pagination' )
) {
	fail_corporate_surface( 'archive-contract', 'Corporate Archive must remain a crawlable archive with title, post grid and pagination.', $surface_paths['archive'], 'archive query title + post template + pagination', 'one or more fragments missing' );
}

$not_found = corporate_surface_file( $surface_paths['404'] );
if (
	! str_contains( $not_found, 'wp:heading {"level":1' )
	|| ! str_contains( $not_found, 'wp:search' )
	|| ! str_contains( $not_found, "home_url( '/' )" )
	|| ! str_contains( $not_found, 'determine_locale' )
) {
	fail_corporate_surface( '404-contract', 'Corporate 404 must remain a useful localized recovery surface.', $surface_paths['404'], 'one H1 + site search + safe home URL + locale-aware copy', 'one or more fragments missing' );
}

if ( 1 !== substr_count( $not_found, 'wp:heading {"level":1' ) ) {
	fail_corporate_surface( '404-h1', 'Corporate 404 must keep exactly one explicit page-level H1.', $surface_paths['404'], '1 H1 block', (string) substr_count( $not_found, 'wp:heading {"level":1' ) );
}

echo "Corporate final system surfaces: OK\n";
