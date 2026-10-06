<?php
/**
 * Validate the final Local Business / Local Pro Home contract.
 */

declare(strict_types=1);

const LOCAL_FINAL_THEME_DIR  = 'packages/seo-geo-theme';
const LOCAL_FINAL_PRESET_DIR = 'presets/local-business';

/**
 * Fail with one actionable diagnostic.
 *
 * @param mixed $expected Expected value.
 * @param mixed $received Actual value.
 */
function fail_local_final_home( string $code, string $message, string $path, mixed $expected, mixed $received ): never {
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
 * Read one required UTF-8 file.
 */
function local_final_file( string $path ): string {
	if ( ! is_file( $path ) ) {
		fail_local_final_home( 'missing-file', 'Required Local Pro final Home file is missing.', $path, 'existing file', 'missing' );
	}

	$content = file_get_contents( $path );
	if ( false === $content || '' === trim( $content ) ) {
		fail_local_final_home( 'empty-file', 'Required Local Pro final Home file is empty.', $path, 'non-empty file', 'empty' );
	}

	return $content;
}

/**
 * Decode one required JSON object.
 *
 * @return array<string,mixed>
 */
function local_final_json( string $path ): array {
	try {
		$value = json_decode( local_final_file( $path ), true, 512, JSON_THROW_ON_ERROR );
	} catch ( JsonException $exception ) {
		fail_local_final_home( 'json-invalid', 'Local Pro contract is invalid JSON.', $path, 'valid JSON object', $exception->getMessage() );
	}

	if ( ! is_array( $value ) ) {
		fail_local_final_home( 'json-object', 'Local Pro contract must decode to an object.', $path, 'JSON object', gettype( $value ) );
	}

	return $value;
}

$mockup_path = LOCAL_FINAL_PRESET_DIR . '/mockup.json';
$mockup      = local_final_json( $mockup_path );
$stage       = $mockup['current_stage'] ?? null;

if ( ! in_array( $stage, array( 'home-candidate', 'inner-pages-candidate' ), true ) ) {
	fail_local_final_home( 'stage-contract', 'Local Pro roadmap stage must preserve the accepted Home while DS-4 advances.', $mockup_path . '#current_stage', 'home-candidate or inner-pages-candidate', $stage );
}

$home = $mockup['pages']['home'] ?? null;
if (
	! is_array( $home )
	|| true !== ( $home['required'] ?? null )
	|| 'local-business-home-final-v1' !== ( $home['composition'] ?? null )
	|| 'candidate' !== ( $home['status'] ?? null )
	|| false !== ( $home['owns_h1'] ?? null )
) {
	fail_local_final_home( 'home-contract', 'Local Pro Home lost its final-composition or H1 ownership contract.', $mockup_path . '#pages.home', 'required candidate with template-owned H1', $home );
}

if ( 'home-candidate' === $stage ) {
	foreach ( array( 'services', 'locations', 'about', 'faq', 'contact', 'single', 'archive', '404' ) as $pending_page ) {
		$status = $mockup['pages'][ $pending_page ]['status'] ?? null;
		if ( 'next-microphase' !== $status ) {
			fail_local_final_home( 'roadmap-honesty', 'Local Pro Home stage must not claim later pages complete.', $mockup_path . '#pages.' . $pending_page, 'next-microphase', $status );
		}
	}
}

if ( 'inner-pages-candidate' === $stage ) {
	foreach ( array( 'services', 'locations', 'about', 'faq', 'contact' ) as $inner_page ) {
		$status = $mockup['pages'][ $inner_page ]['status'] ?? null;
		if ( 'candidate' !== $status ) {
			fail_local_final_home( 'inner-stage-honesty', 'When DS-4B is active, all five inner pages must be explicit candidates.', $mockup_path . '#pages.' . $inner_page, 'candidate', $status );
		}
	}

	foreach ( array( 'single', 'archive', '404' ) as $system_page ) {
		$status = $mockup['pages'][ $system_page ]['status'] ?? null;
		if ( 'next-microphase' !== $status ) {
			fail_local_final_home( 'system-stage-honesty', 'DS-4B must leave system surfaces for their dedicated microphase.', $mockup_path . '#pages.' . $system_page, 'next-microphase', $status );
		}
	}
}

if (
	false !== ( $mockup['visual_runtime']['required_frontend_javascript'] ?? null )
	|| false !== ( $mockup['visual_runtime']['remote_visual_dependencies'] ?? null )
	|| 'no-remote-map-required-for-visual-completion' !== ( $mockup['visual_runtime']['map_policy'] ?? null )
) {
	fail_local_final_home( 'visual-runtime', 'Local Pro must remain zero-JS and independent of remote maps or visuals.', $mockup_path . '#visual_runtime', 'zero JS + zero remote visual dependencies + local map visual', $mockup['visual_runtime'] ?? null );
}

if (
	true !== ( $mockup['placeholder_policy']['blocks_publication_until_resolved'] ?? null )
	|| true !== ( $mockup['hydration_contract']['local_business_schema_requires_confirmed_visible_facts'] ?? null )
	|| true !== ( $mockup['acceptance']['doorway_pages_forbidden'] ?? null )
) {
	fail_local_final_home( 'fact-gates', 'Local Pro lost publication, Schema or doorway-page safeguards.', $mockup_path, 'publication block + visible-fact Schema gate + doorway ban', $mockup );
}

$copy_path = LOCAL_FINAL_PRESET_DIR . '/mockup-copy.json';
$copy_raw  = local_final_file( $copy_path );
$copy      = local_final_json( $copy_path );

if (
	'placeholder' !== ( $copy['provenance'] ?? null )
	|| false !== ( $copy['publication_ready'] ?? null )
	|| 'final-layout-first-then-content-hydration' !== ( $copy['page_contract'] ?? null )
	|| ! is_array( $copy['en_US'] ?? null )
	|| ! is_array( $copy['es_ES'] ?? null )
) {
	fail_local_final_home( 'copy-provenance', 'Local Pro copy must remain bilingual, provisional and non-publishable.', $copy_path, 'placeholder + publication_ready=false + EN/ES', array( $copy['provenance'] ?? null, $copy['publication_ready'] ?? null ) );
}

if ( 1 === preg_match( '/https?:\/\/|tel:|mailto:|google\.[^\s\"]*\/maps|maps\.google|schema\.org|LocalBusiness|AggregateRating|Review/i', $copy_raw, $match ) ) {
	fail_local_final_home( 'invented-local-fact', 'Provisional Local Pro copy must not embed remote maps, contact routes or Schema/review evidence.', $copy_path, 'no remote/contact/schema/review payload', $match[0] );
}

$pattern_path = LOCAL_FINAL_THEME_DIR . '/preset-patterns/local-business-home-final.php';
$pattern      = local_final_file( $pattern_path );

if ( str_contains( $pattern, '"level":1' ) || 1 === preg_match( '/<h1\b/i', $pattern ) ) {
	fail_local_final_home( 'duplicate-h1', 'Local Pro Home pattern must leave the document H1 to the page template.', $pattern_path, 'no H1', 'H1 found' );
}

foreach (
	array(
		'mockup-copy.json',
		'seo-geo-local-hero',
		'seo-geo-local-action-grid',
		'seo-geo-local-service-grid',
		'seo-geo-local-area-visual',
		'seo-geo-local-process',
		'seo-geo-local-facts',
		'seo-geo-local-faq',
		'seo-geo-local-final-cta',
		'seo-geo-placeholder--copy',
		'seo-geo-placeholder--fact',
		'esc_html(',
	) as $fragment
) {
	if ( ! str_contains( $pattern, $fragment ) ) {
		fail_local_final_home( 'pattern-contract', 'Local Pro Home lost a required visual, hydration or escaping contract.', $pattern_path, $fragment, 'fragment missing' );
	}
}

if ( 1 === preg_match( '/https?:\/\/|tel:|mailto:|<!--\s*wp:html\b|<\s*script\b|<\s*style\b|application\/ld\+json|schema\.org|AggregateRating/i', $pattern, $match ) ) {
	fail_local_final_home( 'unsafe-pattern', 'Local Pro Home must stay local, native and non-authoritative for Schema or contact facts.', $pattern_path, 'no remote/html/script/style/schema/contact payload', $match[0] );
}

$css_path = LOCAL_FINAL_THEME_DIR . '/assets/css/presets/local-business.css';
$css      = local_final_file( $css_path );
if ( 1 === preg_match( '/@import|url\s*\(|https?:\/\//i', $css, $match ) ) {
	fail_local_final_home( 'remote-css', 'Local Pro CSS must not load remote fonts, maps or visual dependencies.', $css_path, 'no import/url/remote request', $match[0] );
}

foreach ( array( '.seo-geo-local-hero', '.seo-geo-local-service-grid', '.seo-geo-local-area-visual', '.seo-geo-local-final-cta', '@media (max-width: 900px)', '@media (prefers-reduced-motion: reduce)' ) as $fragment ) {
	if ( ! str_contains( $css, $fragment ) ) {
		fail_local_final_home( 'css-contract', 'Local Pro CSS lost a required responsive or visual layer.', $css_path, $fragment, 'fragment missing' );
	}
}

$registry_path = LOCAL_FINAL_THEME_DIR . '/inc/presets.php';
$registry      = local_final_file( $registry_path );
foreach ( array( "'local-business' === \$preset_id", "'seo-geo-theme/local-business-home-final'", "'local-business-home-final.php'" ) as $fragment ) {
	if ( ! str_contains( $registry, $fragment ) ) {
		fail_local_final_home( 'registry-contract', 'Local Pro final Home is not isolated in the preset-owned registry.', $registry_path, $fragment, 'fragment missing' );
	}
}

echo "Local Pro final Home contract: OK\n";
