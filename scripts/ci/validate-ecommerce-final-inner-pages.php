<?php
/**
 * Validate Ecommerce final inner-page compositions and commerce boundaries.
 */

declare(strict_types=1);

const ECOMMERCE_INNER_THEME_DIR  = 'packages/seo-geo-theme';
const ECOMMERCE_INNER_PRESET_DIR = 'presets/ecommerce';

/** Fail with an actionable diagnostic. */
function fail_ecommerce_inner( string $code, string $message, string $path, mixed $expected, mixed $received ): never {
	fwrite( STDERR, sprintf( "[%s] %s\nFile: %s\nExpected: %s\nReceived: %s\n", $code, $message, $path, is_scalar( $expected ) || null === $expected ? (string) $expected : (string) json_encode( $expected ), is_scalar( $received ) || null === $received ? (string) $received : (string) json_encode( $received ) ) );
	exit( 1 );
}

/** Read one required UTF-8 file. */
function ecommerce_inner_file( string $path ): string {
	$content = is_file( $path ) ? file_get_contents( $path ) : false;
	if ( false === $content || '' === trim( $content ) ) {
		fail_ecommerce_inner( 'missing-file', 'Required Ecommerce inner-page file is missing or empty.', $path, 'non-empty file', 'missing/empty' );
	}
	return $content;
}

/** @return array<string,mixed> */
function ecommerce_inner_json( string $path ): array {
	try {
		$value = json_decode( ecommerce_inner_file( $path ), true, 512, JSON_THROW_ON_ERROR );
	} catch ( JsonException $exception ) {
		fail_ecommerce_inner( 'json-invalid', 'Ecommerce inner-page contract is invalid JSON.', $path, 'valid JSON object', $exception->getMessage() );
	}
	if ( ! is_array( $value ) ) {
		fail_ecommerce_inner( 'json-object', 'Ecommerce inner-page contract must decode to an object.', $path, 'JSON object', gettype( $value ) );
	}
	return $value;
}

$pages = array( 'shop', 'categories', 'buying-guides', 'about', 'support', 'contact' );

$manifest_path = ECOMMERCE_INNER_PRESET_DIR . '/preset.json';
$manifest      = ecommerce_inner_json( $manifest_path );
if (
	'woocommerce' !== ( $manifest['commerce_model']['preferred_provider'] ?? null )
	|| true !== ( $manifest['commerce_model']['requires_supported_adapter_for_live_commerce'] ?? null )
	|| true !== ( $manifest['commerce_model']['provider_owns_product_facts'] ?? null )
	|| true !== ( $manifest['commerce_model']['provider_owns_price_availability'] ?? null )
	|| true !== ( $manifest['commerce_model']['provider_owns_reviews_ratings'] ?? null )
	|| false !== ( $manifest['schema']['theme_emits_product_schema'] ?? null )
) {
	fail_ecommerce_inner( 'provider-authority', 'Ecommerce inner pages must preserve provider ownership of commerce facts and Product Schema.', $manifest_path, 'provider-owned live commerce', $manifest );
}

$mockup_path  = ECOMMERCE_INNER_PRESET_DIR . '/mockup.json';
$mockup       = ecommerce_inner_json( $mockup_path );
$current_stage = $mockup['current_stage'] ?? null;
$valid_stages = array( 'inner-pages-candidate', 'system-surfaces-candidate', 'complete' );
if ( ! in_array( $current_stage, $valid_stages, true ) ) {
	fail_ecommerce_inner( 'stage', 'Ecommerce inner-page gate only accepts forward DS-6B/DS-6C completion stages.', $mockup_path . '#current_stage', $valid_stages, $current_stage );
}
if ( 'complete' !== ( $mockup['pages']['home']['status'] ?? null ) ) {
	fail_ecommerce_inner( 'home-regression', 'Accepted Ecommerce Home must stay complete.', $mockup_path . '#pages.home.status', 'complete', $mockup['pages']['home']['status'] ?? null );
}
foreach ( $pages as $page ) {
	$page_contract = $mockup['pages'][ $page ] ?? null;
	if (
		! is_array( $page_contract )
		|| true !== ( $page_contract['required'] ?? null )
		|| ! in_array( $page_contract['status'] ?? null, array( 'candidate', 'complete' ), true )
		|| false !== ( $page_contract['owns_h1'] ?? null )
		|| ! is_string( $page_contract['composition'] ?? null )
	) {
		fail_ecommerce_inner( 'page-contract', 'Each Ecommerce inner page must retain its accepted composition and page-template-owned H1.', $mockup_path . '#pages.' . $page, 'required candidate/complete + composition + owns_h1=false', $page_contract );
	}
}
if ( 'inner-pages-candidate' === $current_stage ) {
	foreach ( array( 'single', 'archive', '404' ) as $pending_page ) {
		if ( 'next-microphase' !== ( $mockup['pages'][ $pending_page ]['status'] ?? null ) ) {
			fail_ecommerce_inner( 'roadmap-honesty', 'DS-6B must not claim system surfaces complete before DS-6C.', $mockup_path . '#pages.' . $pending_page, 'next-microphase', $mockup['pages'][ $pending_page ]['status'] ?? null );
		}
	}
}

$copy_path = ECOMMERCE_INNER_PRESET_DIR . '/inner-copy.json';
$copy_raw  = ecommerce_inner_file( $copy_path );
$copy_doc  = ecommerce_inner_json( $copy_path );
if (
	'ecommerce' !== ( $copy_doc['preset'] ?? null )
	|| 'placeholder' !== ( $copy_doc['provenance'] ?? null )
	|| false !== ( $copy_doc['publication_ready'] ?? null )
	|| 'commerce-provider' !== ( $copy_doc['commerce_facts_owner'] ?? null )
) {
	fail_ecommerce_inner( 'copy-provenance', 'Ecommerce inner copy must remain provisional, non-publishable and provider-aware.', $copy_path, 'placeholder + publication_ready=false + commerce-provider authority', $copy_doc );
}

$copy_en = $copy_doc['en_US'] ?? null;
$copy_es = $copy_doc['es_ES'] ?? null;
if ( ! is_array( $copy_en ) || ! is_array( $copy_es ) ) {
	fail_ecommerce_inner( 'locales', 'Ecommerce inner pages require EN and ES maps.', $copy_path, 'en_US + es_ES', array_keys( $copy_doc ) );
}
$en_pages = array_keys( $copy_en );
$es_pages = array_keys( $copy_es );
$expected_pages = $pages;
sort( $en_pages );
sort( $es_pages );
sort( $expected_pages );
if ( $expected_pages !== $en_pages || $expected_pages !== $es_pages ) {
	fail_ecommerce_inner( 'page-parity', 'Ecommerce EN/ES maps must expose exactly the six accepted inner pages.', $copy_path, $expected_pages, array( 'en' => $en_pages, 'es' => $es_pages ) );
}
if ( 1 === preg_match( '/https?:\/\//i', $copy_raw, $match ) || 1 === preg_match( '/(?:[$€£]\s*\d|\d[\d.,]*\s*(?:USD|EUR|GBP)\b)/i', $copy_raw, $match ) ) {
	fail_ecommerce_inner( 'unsafe-copy', 'Ecommerce provisional inner copy must not contain remote evidence or numeric prices.', $copy_path, 'no remote URL or numeric currency amount', $match[0] );
}

$renderer_path = ECOMMERCE_INNER_THEME_DIR . '/preset-patterns/ecommerce-inner-final.php';
$renderer      = ecommerce_inner_file( $renderer_path );
if ( str_contains( $renderer, '"level":1' ) || 1 === preg_match( '/<h1\b/i', $renderer ) ) {
	fail_ecommerce_inner( 'duplicate-h1', 'Ecommerce inner renderer must leave H1 ownership to the page template.', $renderer_path, 'no H1', 'H1 found' );
}
foreach ( array( 'inner-copy.json', 'seo-geo-commerce-section', 'seo-geo-commerce-category-grid', 'seo-geo-commerce-confidence-grid', 'seo-geo-commerce-final', 'seo-geo-placeholder--copy', 'seo-geo-placeholder--commerce', 'esc_html(' ) as $fragment ) {
	if ( ! str_contains( $renderer, $fragment ) ) {
		fail_ecommerce_inner( 'renderer-contract', 'Ecommerce inner renderer lost a required visual, hydration or provenance surface.', $renderer_path, $fragment, 'missing' );
	}
}
if ( 1 === preg_match( '/https?:\/\/|<!--\s*wp:html\b|<\s*script\b|<\s*style\b|application\/ld\+json|schema\.org|woocommerce\/|wc-block|AggregateRating|"offers"\s*:/i', $renderer, $match ) ) {
	fail_ecommerce_inner( 'unsafe-renderer', 'Ecommerce inner renderer must remain local and non-authoritative for live commerce.', $renderer_path, 'no remote/html/script/style/schema/Woo product blocks', $match[0] );
}

$registration_path = ECOMMERCE_INNER_THEME_DIR . '/inc/Ecommerce/FinalHomePattern.php';
$registration      = ecommerce_inner_file( $registration_path );
foreach ( array( 'ecommerce-inner-final.php', 'seo-geo-theme/ecommerce-shop-final', 'seo-geo-theme/ecommerce-categories-final', 'seo-geo-theme/ecommerce-buying-guides-final', 'seo-geo-theme/ecommerce-about-final', 'seo-geo-theme/ecommerce-support-final', 'seo-geo-theme/ecommerce-contact-final' ) as $fragment ) {
	if ( ! str_contains( $registration, $fragment ) ) {
		fail_ecommerce_inner( 'registration', 'Ecommerce registration lost an accepted isolated inner-page composition.', $registration_path, $fragment, 'missing' );
	}
}

$css_path = ECOMMERCE_INNER_THEME_DIR . '/assets/css/presets/ecommerce.css';
$css      = ecommerce_inner_file( $css_path );
if ( 1 === preg_match( '/@import\b|url\(\s*["\']?https?:\/\//i', $css, $match ) ) {
	fail_ecommerce_inner( 'remote-css', 'Ecommerce must keep the accepted visual system local.', $css_path, 'no remote CSS dependency', $match[0] );
}

echo "Ecommerce final inner-page contract: OK\n";
