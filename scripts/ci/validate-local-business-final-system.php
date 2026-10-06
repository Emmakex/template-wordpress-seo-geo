<?php
/**
 * Validate final Local Business / Local Pro system surfaces.
 */

declare(strict_types=1);

const LOCAL_SYSTEM_THEME_DIR  = 'packages/seo-geo-theme';
const LOCAL_SYSTEM_PRESET_DIR = 'presets/local-business';

/**
 * Fail with one actionable diagnostic.
 *
 * @param mixed $expected Expected value.
 * @param mixed $received Actual value.
 */
function fail_local_final_system( string $code, string $message, string $path, mixed $expected, mixed $received ): never {
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

/** Read one required UTF-8 file. */
function local_system_file( string $path ): string {
	$content = is_file( $path ) ? file_get_contents( $path ) : false;
	if ( false === $content || '' === trim( $content ) ) {
		fail_local_final_system( 'missing-file', 'Required Local Pro system-surface file is missing or empty.', $path, 'non-empty file', 'missing/empty' );
	}
	return $content;
}

/** @return array<string,mixed> */
function local_system_json( string $path ): array {
	try {
		$value = json_decode( local_system_file( $path ), true, 512, JSON_THROW_ON_ERROR );
	} catch ( JsonException $exception ) {
		fail_local_final_system( 'json-invalid', 'Local Pro system contract is invalid JSON.', $path, 'valid JSON object', $exception->getMessage() );
	}
	if ( ! is_array( $value ) ) {
		fail_local_final_system( 'json-object', 'Local Pro system contract must decode to an object.', $path, 'JSON object', gettype( $value ) );
	}
	return $value;
}

$mockup_path = LOCAL_SYSTEM_PRESET_DIR . '/mockup.json';
$mockup      = local_system_json( $mockup_path );
if ( 'system-surfaces-candidate' !== ( $mockup['current_stage'] ?? null ) ) {
	fail_local_final_system( 'stage-contract', 'DS-4C must explicitly identify the Local Pro system-surfaces candidate.', $mockup_path . '#current_stage', 'system-surfaces-candidate', $mockup['current_stage'] ?? null );
}

$expected_compositions = array(
	'single'  => 'local-business-single-final-v1',
	'archive' => 'local-business-archive-final-v1',
	'404'     => 'local-business-404-final-v1',
);
foreach ( $expected_compositions as $slug => $composition ) {
	$page = $mockup['pages'][ $slug ] ?? null;
	if (
		! is_array( $page )
		|| true !== ( $page['required'] ?? null )
		|| 'candidate' !== ( $page['status'] ?? null )
		|| $composition !== ( $page['composition'] ?? null )
		|| true !== ( $page['owns_h1'] ?? null )
		|| true !== ( $page['site_editor_custom_template_wins'] ?? null )
	) {
		fail_local_final_system( 'surface-contract', 'Local Pro system surface lost final composition, H1 or Site Editor precedence.', $mockup_path . '#pages.' . $slug, $composition . ' candidate with H1 and custom-template precedence', $page );
	}
}
if ( true !== ( $mockup['pages']['404']['localized_copy'] ?? null ) ) {
	fail_local_final_system( '404-localization', 'Local Pro 404 must remain localized.', $mockup_path . '#pages.404', true, $mockup['pages']['404']['localized_copy'] ?? null );
}
if (
	true !== ( $mockup['acceptance']['site_editor_custom_templates_are_authoritative'] ?? null )
	|| true !== ( $mockup['acceptance']['missing_preset_template_falls_back_to_neutral_theme'] ?? null )
	|| true !== ( $mockup['acceptance']['doorway_pages_forbidden'] ?? null )
	|| true !== ( $mockup['hydration_contract']['local_business_schema_requires_confirmed_visible_facts'] ?? null )
) {
	fail_local_final_system( 'runtime-safeguards', 'Local Pro lost Site Editor, fallback, doorway or Schema safeguards.', $mockup_path, 'all runtime/local safeguards true', $mockup );
}

$runtime_path = LOCAL_SYSTEM_THEME_DIR . '/inc/Templates/PresetTemplateRuntime.php';
$runtime      = local_system_file( $runtime_path );
foreach ( array( "'local-business'       => 'local-business'", "'corporate'", "'saas-digital-product'", "'theme' !== \$template->source", 'OWNED_TEMPLATES', "get_template_directory() . '/preset-templates/'" ) as $fragment ) {
	if ( ! str_contains( $runtime, $fragment ) ) {
		fail_local_final_system( 'runtime-contract', 'Preset template runtime lost Local Pro registration or shared safety behavior.', $runtime_path, $fragment, 'missing' );
	}
}

$template_fragments = array(
	'single'  => array( 'seo-geo-local-system-surface', 'wp:post-title', '"level":1', 'wp:post-content', 'wp:post-navigation-link' ),
	'archive' => array( 'seo-geo-local-system-surface', 'wp:query-title', 'wp:post-template', 'wp:query-pagination', 'wp:query-no-results' ),
	'404'     => array( 'seo-geo-local-system-surface', 'determine_locale', '<h1', 'home_url(', 'wp:search' ),
);
foreach ( $template_fragments as $slug => $fragments ) {
	$path    = LOCAL_SYSTEM_THEME_DIR . '/preset-templates/local-business-' . $slug . '.php';
	$content = local_system_file( $path );
	foreach ( $fragments as $fragment ) {
		if ( ! str_contains( $content, $fragment ) ) {
			fail_local_final_system( 'template-contract', 'Local Pro system template lost required semantic/UX structure.', $path, $fragment, 'missing' );
		}
	}
	if ( 1 === preg_match( '/https?:\/\/|tel:|mailto:|maps\.google|application\/ld\+json|schema\.org|LocalBusiness|AggregateRating|Review|streetAddress|openingHours|geoCoordinates/i', $content, $match ) ) {
		fail_local_final_system( 'invented-local-fact', 'Local Pro system templates must not hard-code remote or authoritative local evidence.', $path, 'no remote/contact/local-fact/schema payload', $match[0] );
	}
}

$runtime_test_path = 'scripts/ci/test-preset-template-runtime.php';
$runtime_test      = local_system_file( $runtime_test_path );
foreach ( array( "'local-business'", 'seo-geo-local-system-surface', "'publisher'", 'site-editor-custom', 'missing-file-neutral' ) as $fragment ) {
	if ( ! str_contains( $runtime_test, $fragment ) ) {
		fail_local_final_system( 'runtime-test-contract', 'Executable runtime test lost Local Pro, neutral, custom or missing-file coverage.', $runtime_test_path, $fragment, 'missing' );
	}
}

echo "Local Pro final system-surface contract: OK\n";
