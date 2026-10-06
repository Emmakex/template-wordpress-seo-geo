<?php
/**
 * Validate final Ecommerce system surfaces and provider boundaries.
 */

declare(strict_types=1);

const ECOMMERCE_SYSTEM_THEME_DIR  = 'packages/seo-geo-theme';
const ECOMMERCE_SYSTEM_PRESET_DIR = 'presets/ecommerce';

/** Fail with one actionable diagnostic. */
function fail_ecommerce_final_system( string $code, string $message, string $path, mixed $expected, mixed $received ): never {
	fwrite( STDERR, sprintf( "[%s] %s\nFile: %s\nExpected: %s\nReceived: %s\n", $code, $message, $path, is_scalar( $expected ) || null === $expected ? (string) $expected : (string) json_encode( $expected ), is_scalar( $received ) || null === $received ? (string) $received : (string) json_encode( $received ) ) );
	exit( 1 );
}

/** Read one required UTF-8 file. */
function ecommerce_system_file( string $path ): string {
	$content = is_file( $path ) ? file_get_contents( $path ) : false;
	if ( false === $content || '' === trim( $content ) ) {
		fail_ecommerce_final_system( 'missing-file', 'Required Ecommerce system-surface file is missing or empty.', $path, 'non-empty file', 'missing/empty' );
	}
	return $content;
}

/** @return array<string,mixed> */
function ecommerce_system_json( string $path ): array {
	try {
		$value = json_decode( ecommerce_system_file( $path ), true, 512, JSON_THROW_ON_ERROR );
	} catch ( JsonException $exception ) {
		fail_ecommerce_final_system( 'json-invalid', 'Ecommerce system contract is invalid JSON.', $path, 'valid JSON object', $exception->getMessage() );
	}
	if ( ! is_array( $value ) ) {
		fail_ecommerce_final_system( 'json-object', 'Ecommerce system contract must decode to an object.', $path, 'JSON object', gettype( $value ) );
	}
	return $value;
}

$mockup_path = ECOMMERCE_SYSTEM_PRESET_DIR . '/mockup.json';
$mockup      = ecommerce_system_json( $mockup_path );
if ( 'system-surfaces-candidate' !== ( $mockup['current_stage'] ?? null ) ) {
	fail_ecommerce_final_system( 'stage-contract', 'DS-6C must explicitly identify the Ecommerce system-surfaces candidate.', $mockup_path . '#current_stage', 'system-surfaces-candidate', $mockup['current_stage'] ?? null );
}

$expected_compositions = array(
	'single'  => 'ecommerce-single-final-v1',
	'archive' => 'ecommerce-archive-final-v1',
	'404'     => 'ecommerce-404-final-v1',
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
		fail_ecommerce_final_system( 'surface-contract', 'Ecommerce system surface lost final composition, H1 or Site Editor precedence.', $mockup_path . '#pages.' . $slug, $composition . ' candidate with H1 and custom-template precedence', $page );
	}
}
if (
	true !== ( $mockup['pages']['single']['editorial_wordpress_content_only'] ?? null )
	|| true !== ( $mockup['pages']['single']['commerce_provider_templates_untouched'] ?? null )
	|| true !== ( $mockup['pages']['archive']['editorial_wordpress_posts_only'] ?? null )
	|| true !== ( $mockup['pages']['archive']['commerce_provider_templates_untouched'] ?? null )
	|| true !== ( $mockup['pages']['404']['localized_copy'] ?? null )
	|| true !== ( $mockup['pages']['404']['commerce_provider_routes_not_invented'] ?? null )
) {
	fail_ecommerce_final_system( 'commerce-authority', 'Ecommerce system surfaces must stay editorial/recovery-only while commerce routes/templates remain provider-owned.', $mockup_path . '#pages', 'WordPress editorial authority + localized recovery + provider templates untouched', $mockup['pages'] ?? null );
}

if (
	false !== ( $mockup['commerce_authority']['theme_emits_product_offer_schema'] ?? null )
	|| false !== ( $mockup['commerce_authority']['theme_may_infer_commerce_facts'] ?? null )
	|| array( 'single', 'archive', '404' ) !== ( $mockup['commerce_authority']['theme_system_runtime_owned_slugs'] ?? null )
	|| true !== ( $mockup['acceptance']['site_editor_custom_templates_are_authoritative'] ?? null )
	|| true !== ( $mockup['acceptance']['missing_preset_template_falls_back_to_neutral_theme'] ?? null )
	|| true !== ( $mockup['acceptance']['provider_product_templates_are_not_overridden'] ?? null )
) {
	fail_ecommerce_final_system( 'runtime-safeguards', 'Ecommerce lost provider, Site Editor or fallback safeguards.', $mockup_path, 'generic slugs only + provider facts/schema false + custom/fallback/provider safeguards true', $mockup );
}

$excluded_provider_templates = $mockup['commerce_authority']['provider_template_examples_excluded_from_theme_runtime'] ?? null;
if ( ! is_array( $excluded_provider_templates ) ) {
	fail_ecommerce_final_system( 'provider-exclusions', 'Ecommerce must enumerate provider-owned template examples.', $mockup_path, 'provider exclusion list', $excluded_provider_templates );
}
foreach ( array( 'single-product', 'archive-product', 'cart', 'checkout', 'account' ) as $provider_surface ) {
	if ( ! in_array( $provider_surface, $excluded_provider_templates, true ) ) {
		fail_ecommerce_final_system( 'provider-exclusion-missing', 'Ecommerce provider boundary lost a required excluded surface.', $mockup_path, $provider_surface, $excluded_provider_templates );
	}
}

$runtime_path = ECOMMERCE_SYSTEM_THEME_DIR . '/inc/Templates/PresetTemplateRuntime.php';
$runtime      = ecommerce_system_file( $runtime_path );
foreach ( array( "'ecommerce'            => 'ecommerce'", "'corporate'", "'local-business'", "'publisher'", "'saas-digital-product'", "'theme' !== \$template->source", "array( 'single', 'archive', '404' )", "get_template_directory() . '/preset-templates/'" ) as $fragment ) {
	if ( ! str_contains( $runtime, $fragment ) ) {
		fail_ecommerce_final_system( 'runtime-contract', 'Preset template runtime lost Ecommerce registration or shared safety behavior.', $runtime_path, $fragment, 'missing' );
	}
}
if ( 1 === preg_match( '/single-product|archive-product|woocommerce\/|wc-block|checkout|my-account/i', $runtime, $match ) ) {
	fail_ecommerce_final_system( 'provider-runtime-boundary', 'Generic Theme runtime must not claim provider-owned commerce template slugs.', $runtime_path, 'no provider template routing', $match[0] );
}

$template_fragments = array(
	'single'  => array( 'seo-geo-commerce-system-surface', 'wp:post-title', '"level":1', 'wp:post-date', 'wp:post-author-name', 'wp:post-content', 'wp:post-navigation-link' ),
	'archive' => array( 'seo-geo-commerce-system-surface', 'wp:query-title', 'wp:post-template', '"postType":"post"', '"inherit":true', 'wp:query-pagination', 'wp:query-no-results' ),
	'404'     => array( 'seo-geo-commerce-system-surface', 'determine_locale', '<h1', 'home_url(', 'wp:search' ),
);
foreach ( $template_fragments as $slug => $fragments ) {
	$path    = ECOMMERCE_SYSTEM_THEME_DIR . '/preset-templates/ecommerce-' . $slug . '.php';
	$content = ecommerce_system_file( $path );
	foreach ( $fragments as $fragment ) {
		if ( ! str_contains( $content, $fragment ) ) {
			fail_ecommerce_final_system( 'template-contract', 'Ecommerce system template lost required semantic/UX structure.', $path, $fragment, 'missing' );
		}
	}

	$evidence_content = preg_replace( '/\/\*.*?\*\//s', '', $content );
	if ( ! is_string( $evidence_content ) ) {
		$evidence_content = $content;
	}
	if ( 1 === preg_match( '/https?:\/\/|mailto:|tel:|application\/ld\+json|schema\.org|AggregateRating|woocommerce\/|wc-block|single-product|archive-product|add-to-cart|checkout|my-account|\bprice\b|\bstock\b|\bavailability\b|\brating\b|\breview\b|[$€£]\s*\d/i', $evidence_content, $match ) ) {
		fail_ecommerce_final_system( 'invented-commerce-evidence', 'Ecommerce system templates must not hard-code provider-owned commerce evidence, blocks, routes or prices.', $path, 'no remote/contact/schema/price/stock/rating/provider evidence', $match[0] );
	}
}

$runtime_test_path = 'scripts/ci/test-preset-template-runtime.php';
$runtime_test      = ecommerce_system_file( $runtime_test_path );
foreach ( array( "'ecommerce'", 'seo-geo-commerce-system-surface', "'future-neutral-preset'", 'site-editor-custom', 'missing-file-neutral' ) as $fragment ) {
	if ( ! str_contains( $runtime_test, $fragment ) ) {
		fail_ecommerce_final_system( 'runtime-test-contract', 'Executable runtime test lost Ecommerce, neutral, custom or missing-file coverage.', $runtime_test_path, $fragment, 'missing' );
	}
}

echo "Ecommerce final system-surface contract: OK\n";
