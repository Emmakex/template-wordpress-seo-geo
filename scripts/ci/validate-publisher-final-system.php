<?php
/**
 * Validate final Publisher / Editorial system surfaces.
 */

declare(strict_types=1);

const PUBLISHER_SYSTEM_THEME_DIR  = 'packages/seo-geo-theme';
const PUBLISHER_SYSTEM_PRESET_DIR = 'presets/publisher';

/**
 * Fail with one actionable diagnostic.
 *
 * @param mixed $expected Expected value.
 * @param mixed $received Actual value.
 */
function fail_publisher_final_system( string $code, string $message, string $path, mixed $expected, mixed $received ): never {
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
function publisher_system_file( string $path ): string {
	$content = is_file( $path ) ? file_get_contents( $path ) : false;
	if ( false === $content || '' === trim( $content ) ) {
		fail_publisher_final_system( 'missing-file', 'Required Publisher system-surface file is missing or empty.', $path, 'non-empty file', 'missing/empty' );
	}
	return $content;
}

/** @return array<string,mixed> */
function publisher_system_json( string $path ): array {
	try {
		$value = json_decode( publisher_system_file( $path ), true, 512, JSON_THROW_ON_ERROR );
	} catch ( JsonException $exception ) {
		fail_publisher_final_system( 'json-invalid', 'Publisher system contract is invalid JSON.', $path, 'valid JSON object', $exception->getMessage() );
	}
	if ( ! is_array( $value ) ) {
		fail_publisher_final_system( 'json-object', 'Publisher system contract must decode to an object.', $path, 'JSON object', gettype( $value ) );
	}
	return $value;
}

$mockup_path = PUBLISHER_SYSTEM_PRESET_DIR . '/mockup.json';
$mockup      = publisher_system_json( $mockup_path );
if ( 'system-surfaces-candidate' !== ( $mockup['current_stage'] ?? null ) ) {
	fail_publisher_final_system( 'stage-contract', 'DS-5C must explicitly identify the Publisher system-surfaces candidate.', $mockup_path . '#current_stage', 'system-surfaces-candidate', $mockup['current_stage'] ?? null );
}

$expected_compositions = array(
	'single'  => 'publisher-single-final-v1',
	'archive' => 'publisher-archive-final-v1',
	'404'     => 'publisher-404-final-v1',
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
		fail_publisher_final_system( 'surface-contract', 'Publisher system surface lost final composition, H1 or Site Editor precedence.', $mockup_path . '#pages.' . $slug, $composition . ' candidate with H1 and custom-template precedence', $page );
	}
}
if (
	true !== ( $mockup['pages']['single']['real_wordpress_author_date_required'] ?? null )
	|| true !== ( $mockup['pages']['archive']['real_wordpress_posts_required'] ?? null )
	|| true !== ( $mockup['pages']['404']['localized_copy'] ?? null )
) {
	fail_publisher_final_system( 'editorial-authority', 'Publisher system surfaces must keep real WordPress authors/dates/posts and localized 404 copy.', $mockup_path . '#pages', 'real WordPress content authority + localized 404', $mockup['pages'] ?? null );
}
if (
	true !== ( $mockup['acceptance']['site_editor_custom_templates_are_authoritative'] ?? null )
	|| true !== ( $mockup['acceptance']['missing_preset_template_falls_back_to_neutral_theme'] ?? null )
	|| true !== ( $mockup['acceptance']['system_templates_use_wordpress_content_authority'] ?? null )
	|| true !== ( $mockup['acceptance']['evidence_must_not_be_fabricated'] ?? null )
) {
	fail_publisher_final_system( 'runtime-safeguards', 'Publisher lost Site Editor, fallback or editorial-authority safeguards.', $mockup_path, 'all runtime/editorial safeguards true', $mockup['acceptance'] ?? null );
}

$runtime_path = PUBLISHER_SYSTEM_THEME_DIR . '/inc/Templates/PresetTemplateRuntime.php';
$runtime      = publisher_system_file( $runtime_path );
foreach ( array( "'publisher'            => 'publisher'", "'corporate'", "'local-business'", "'saas-digital-product'", "'theme' !== \$template->source", 'OWNED_TEMPLATES', "get_template_directory() . '/preset-templates/'" ) as $fragment ) {
	if ( ! str_contains( $runtime, $fragment ) ) {
		fail_publisher_final_system( 'runtime-contract', 'Preset template runtime lost Publisher registration or shared safety behavior.', $runtime_path, $fragment, 'missing' );
	}
}

$template_fragments = array(
	'single'  => array( 'seo-geo-publisher-system-surface', 'wp:post-title', '"level":1', 'wp:post-date', 'wp:post-author-name', 'wp:post-content', 'wp:post-navigation-link' ),
	'archive' => array( 'seo-geo-publisher-system-surface', 'wp:query-title', 'wp:post-template', 'wp:post-date', 'wp:post-author-name', 'wp:query-pagination', 'wp:query-no-results' ),
	'404'     => array( 'seo-geo-publisher-system-surface', 'determine_locale', '<h1', 'home_url(', 'wp:search' ),
);
foreach ( $template_fragments as $slug => $fragments ) {
	$path    = PUBLISHER_SYSTEM_THEME_DIR . '/preset-templates/publisher-' . $slug . '.php';
	$content = publisher_system_file( $path );
	foreach ( $fragments as $fragment ) {
		if ( ! str_contains( $content, $fragment ) ) {
			fail_publisher_final_system( 'template-contract', 'Publisher system template lost required semantic/UX structure.', $path, $fragment, 'missing' );
		}
	}

	$evidence_content = preg_replace( '/\/\*.*?\*\//s', '', $content );
	if ( ! is_string( $evidence_content ) ) {
		$evidence_content = $content;
	}
	if ( 1 === preg_match( '/https?:\/\/|mailto:|tel:|application\/ld\+json|schema\.org|AggregateRating|Review|subscriber|read-count|view-count|breaking-news|fact-checker|reviewer/i', $evidence_content, $match ) ) {
		fail_publisher_final_system( 'invented-editorial-evidence', 'Publisher system templates must not hard-code remote or fabricated editorial evidence.', $path, 'no remote/contact/schema/popularity/reviewer evidence', $match[0] );
	}
}

$single = publisher_system_file( PUBLISHER_SYSTEM_THEME_DIR . '/preset-templates/publisher-single.php' );
if ( ! str_contains( $single, 'wp:post-author-biography' ) || ! str_contains( $single, 'wp:post-featured-image' ) ) {
	fail_publisher_final_system( 'single-authority', 'Publisher Single must keep native author context and authored media.', PUBLISHER_SYSTEM_THEME_DIR . '/preset-templates/publisher-single.php', 'native author biography + featured image', 'missing' );
}

$archive = publisher_system_file( PUBLISHER_SYSTEM_THEME_DIR . '/preset-templates/publisher-archive.php' );
if ( ! str_contains( $archive, '"inherit":true' ) || ! str_contains( $archive, '"orderBy":"date"' ) ) {
	fail_publisher_final_system( 'archive-authority', 'Publisher Archive must inherit the real WordPress archive context and use native dates.', PUBLISHER_SYSTEM_THEME_DIR . '/preset-templates/publisher-archive.php', 'inherit=true + orderBy=date', 'missing' );
}

$runtime_test_path = 'scripts/ci/test-preset-template-runtime.php';
$runtime_test      = publisher_system_file( $runtime_test_path );
foreach ( array( "'publisher'", 'seo-geo-publisher-system-surface', "'ecommerce'", 'site-editor-custom', 'missing-file-neutral' ) as $fragment ) {
	if ( ! str_contains( $runtime_test, $fragment ) ) {
		fail_publisher_final_system( 'runtime-test-contract', 'Executable runtime test lost Publisher, neutral, custom or missing-file coverage.', $runtime_test_path, $fragment, 'missing' );
	}
}

echo "Publisher final system-surface contract: OK\n";
