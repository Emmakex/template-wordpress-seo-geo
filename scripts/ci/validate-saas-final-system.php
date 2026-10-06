<?php
/**
 * Validate SaaS final Single, Archive and 404 system surfaces.
 */

declare(strict_types=1);

const SAAS_SYSTEM_THEME_DIR  = 'packages/seo-geo-theme';
const SAAS_SYSTEM_PRESET_DIR = 'presets/saas-digital-product';

/**
 * Fail with one actionable diagnostic.
 *
 * @param mixed $expected Expected value.
 * @param mixed $received Actual value.
 */
function fail_saas_final_system( string $code, string $message, string $path, mixed $expected, mixed $received ): never {
	fwrite(
		STDERR,
		sprintf(
			"[%s] %s\nFile: %s\nExpected: %s\nReceived: %s\n",
			$code,
			$message,
			$path,
			is_scalar( $expected ) || null === $expected ? (string) $expected : (string) json_encode( $expected ),
			is_scalar( $received ) || null === $received ? (string) $received : (string) json_encode( $received )
		)
	);
	exit( 1 );
}

/**
 * Read one required file.
 */
function saas_system_file( string $path ): string {
	if ( ! is_file( $path ) ) {
		fail_saas_final_system( 'missing-file', 'Required SaaS system-surface file is missing.', $path, 'existing file', 'missing' );
	}

	$content = file_get_contents( $path );
	if ( false === $content || '' === trim( $content ) ) {
		fail_saas_final_system( 'empty-file', 'Required SaaS system-surface file is empty.', $path, 'non-empty file', 'empty' );
	}

	return $content;
}

$mockup_path = SAAS_SYSTEM_PRESET_DIR . '/mockup.json';
$mockup_raw  = saas_system_file( $mockup_path );

try {
	$mockup = json_decode( $mockup_raw, true, 512, JSON_THROW_ON_ERROR );
} catch ( JsonException $exception ) {
	fail_saas_final_system( 'json-invalid', 'SaaS mockup contract is invalid JSON.', $mockup_path, 'valid JSON', $exception->getMessage() );
}

if ( ! is_array( $mockup ) || 'system-surfaces-candidate' !== ( $mockup['current_stage'] ?? null ) ) {
	fail_saas_final_system( 'stage-contract', 'SaaS roadmap stage must identify system-surface acceptance.', $mockup_path . '#current_stage', 'system-surfaces-candidate', $mockup['current_stage'] ?? null );
}

$expected_compositions = array(
	'single'  => 'saas-system-single-v1',
	'archive' => 'saas-system-archive-v1',
	'404'     => 'saas-system-404-v1',
);

foreach ( $expected_compositions as $slug => $composition ) {
	$page = $mockup['pages'][ $slug ] ?? null;
	if (
		! is_array( $page )
		|| true !== ( $page['required'] ?? null )
		|| $composition !== ( $page['composition'] ?? null )
		|| 'candidate' !== ( $page['status'] ?? null )
		|| true !== ( $page['site_editor_custom_template_wins'] ?? null )
	) {
		fail_saas_final_system( 'page-contract', 'SaaS system surface lost its final runtime contract.', $mockup_path . '#pages.' . $slug, array( 'composition' => $composition, 'status' => 'candidate', 'site_editor_custom_template_wins' => true ), $page );
	}
}

if (
	true !== ( $mockup['acceptance']['system_templates_preserve_site_editor_customizations'] ?? null )
	|| true !== ( $mockup['acceptance']['system_templates_fail_safe_to_neutral_theme'] ?? null )
) {
	fail_saas_final_system( 'runtime-acceptance', 'SaaS system templates must preserve custom templates and fail safe to neutral Theme surfaces.', $mockup_path . '#acceptance', 'both runtime safeguards true', $mockup['acceptance'] ?? null );
}

$runtime_path = SAAS_SYSTEM_THEME_DIR . '/inc/Templates/PresetTemplateRuntime.php';
$runtime      = saas_system_file( $runtime_path );

foreach (
	array(
		"'corporate'            => 'corporate'",
		"'saas-digital-product' => 'saas-digital-product'",
		"array( 'single', 'archive', '404' )",
		"'theme' !== \$template->source",
		'clone $template',
		'preset-templates/',
	) as $fragment
) {
	if ( ! str_contains( $runtime, $fragment ) ) {
		fail_saas_final_system( 'runtime-contract', 'Preset template runtime lost an isolation or fallback safeguard.', $runtime_path, $fragment, 'fragment missing' );
	}
}

$templates = array(
	'single'  => SAAS_SYSTEM_THEME_DIR . '/preset-templates/saas-digital-product-single.php',
	'archive' => SAAS_SYSTEM_THEME_DIR . '/preset-templates/saas-digital-product-archive.php',
	'404'     => SAAS_SYSTEM_THEME_DIR . '/preset-templates/saas-digital-product-404.php',
);

foreach ( $templates as $path ) {
	$content = saas_system_file( $path );
	if ( ! str_contains( $content, 'seo-geo-saas-system-surface' ) || ! str_contains( $content, 'tagName":"main' ) ) {
		fail_saas_final_system( 'template-main', 'SaaS system surface must expose its preset class and main landmark.', $path, 'SaaS system class + main landmark', 'required fragment missing' );
	}

	if ( 1 === preg_match( '/https?:\/\/|<!--\s*wp:html\b|<\s*script\b|<\s*style\b|application\/ld\+json|schema\.org/i', $content, $match ) ) {
		fail_saas_final_system( 'unsafe-template', 'SaaS system surfaces must stay local, native and non-authoritative for Schema.', $path, 'no remote/html/script/style/schema payload', $match[0] );
	}
}

$single = saas_system_file( $templates['single'] );
if ( 1 !== substr_count( $single, 'wp:post-title {"level":1' ) || 0 !== preg_match_all( '/<h1\b/i', $single ) ) {
	fail_saas_final_system( 'single-h1', 'SaaS Single must expose exactly one dynamic page-owned H1.', $templates['single'], 'one post-title level 1 and no literal H1', 'H1 contract mismatch' );
}
foreach ( array( 'wp:post-content', 'wp:post-author-name', 'wp:post-navigation-link' ) as $fragment ) {
	if ( ! str_contains( $single, $fragment ) ) {
		fail_saas_final_system( 'single-contract', 'SaaS Single lost a required authored-content or navigation surface.', $templates['single'], $fragment, 'fragment missing' );
	}
}

$archive = saas_system_file( $templates['archive'] );
if ( 1 !== substr_count( $archive, 'wp:query-title {"type":"archive"' ) || 0 !== preg_match_all( '/<h1\b/i', $archive ) ) {
	fail_saas_final_system( 'archive-h1', 'SaaS Archive must leave document-title rendering to one archive query-title block.', $templates['archive'], 'one archive query-title and no literal H1', 'H1 contract mismatch' );
}
foreach ( array( 'wp:query', 'wp:post-template', 'wp:query-pagination', 'wp:search' ) as $fragment ) {
	if ( ! str_contains( $archive, $fragment ) ) {
		fail_saas_final_system( 'archive-contract', 'SaaS Archive lost a required discovery surface.', $templates['archive'], $fragment, 'fragment missing' );
	}
}

$not_found = saas_system_file( $templates['404'] );
if ( 1 !== substr_count( $not_found, 'wp:heading {"level":1' ) || 1 !== preg_match_all( '/<h1\b/i', $not_found ) ) {
	fail_saas_final_system( '404-h1', 'SaaS 404 must expose exactly one localized literal H1.', $templates['404'], 'one level-1 heading block with one literal H1', 'H1 contract mismatch' );
}
foreach ( array( 'determine_locale', 'home_url( \'/\' )', 'wp:search', 'seo-geo-saas-kicker' ) as $fragment ) {
	if ( ! str_contains( $not_found, $fragment ) ) {
		fail_saas_final_system( '404-contract', 'SaaS 404 lost localized recovery navigation.', $templates['404'], $fragment, 'fragment missing' );
	}
}

echo "SaaS final system-surface contract: OK\n";
