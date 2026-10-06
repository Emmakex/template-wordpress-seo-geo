<?php
/**
 * Validate Ecommerce final inner-page compositions and commerce boundaries.
 */

declare(strict_types=1);

const ECOMMERCE_INNER_THEME_DIR  = 'packages/seo-geo-theme';
const ECOMMERCE_INNER_PRESET_DIR = 'presets/ecommerce';

/** Fail with an actionable diagnostic. */
function fail_ecommerce_inner( string $code, string $message, string $path, mixed $expected, mixed $received ): never {
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
	fail_ecommerce_inner( 'provider-authority', 'DS-6B must preserve provider ownership of commerce facts and Product Schema.', $manifest_path, 'provider-owned live commerce', $manifest );
}

$mockup_path = ECOMMERCE_INNER_PRESET_DIR . '/mockup.json';
$mockup      = ecommerce_inner_json( $mockup_path );
if ( 'inner-pages-candidate' !== ( $mockup['current_stage'] ?? null ) ) {
	fail_ecommerce_inner( 'stage', 'DS-6B must declare the inner-pages candidate stage.', $mockup_path . '#current_stage', 'inner-pages-candidate', $mockup['current_stage'] ?? null );
}
if ( 'complete' !== ( $mockup['pages']['home']['status'] ?? null ) ) {
	fail_ecommerce_inner( 'home-regression', 'Accepted Ecommerce Home must stay complete while DS-6B advances.', $mockup_path . '#pages.home.status', 'complete', $mockup['pages']['home']['status'] ?? null );
}
foreach ( $pages as $page ) {
	$page_contract = $mockup['pages'][ $page ] ?? null;
	if (
		! is_array( $page_contract )
		|| true !== ( $page_contract['required'] ?? null )
		|| 'candidate' !== ( $page_contract['status'] ?? null )
		|| false !== ( $page_contract['owns_h1'] ?? null )
		|| ! is_string( $page_contract['composition'] ?? null )
	) {
		fail_ecommerce_inner( 'page-contract', 'Each DS-6B page must be a required candidate with template-owned H1.', $mockup_path . '#pages.' . $page, 'required candidate + composition + owns_h1=false', $page_contract );
	}
}
foreach ( array( 'single', 'archive', '404' ) as $pending_page ) {
	if ( 'next-microphase' !== ( $mockup['pages'][ $pending_page ]['status'] ?? null ) ) {
		fail_ecommerce_inner( 'roadmap-honesty', 'DS-6B must not claim system surfaces complete.', $mockup_path . '#pages.' . $pending_page, 'next-microphase', $mockup['pages'][ $pending_page ]['status'] ?? null );
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
	fail_ecommerce_inner( 'copy-provenance', 'DS-6B copy must remain provisional, non-publishable and provider-aware.', $copy_path, 'placeholder + publication_ready=false + commerce-provider authority', $copy_doc );
}

$copy_en = $copy_doc['en_US'] ?? null;
$copy_es = $copy_doc['es_ES'] ?? null;
if ( ! is_array( $copy_en ) || ! is_array( $copy_es ) ) {
	fail_ecommerce_inner( 'locales', 'DS-6B requires EN and ES maps.', $copy_path, 'en_US + es_ES', array_keys( $copy_doc ) );
}
$en_pages = array_keys( $copy_en );
$es_pages = array_keys( $copy_es );
sort( $en_pages );
sort( $es_pages );
$expected_pages = $pages;
sort( $expected_pages );
if ( $expected_pages !== $en_pages || $expected_pages !== $es_pages ) {
	fail_ecommerce_inner( 'page-parity', 'DS-6B EN/ES locale maps must expose exactly the six candidate pages.', $copy_path, $expected_pages, array( 'en' => $en_pages, 'es' => $es_pages ) );
}

$required_slots = array(
	'kicker', 'lead', 'section_label', 'section_title', 'section_body',
	'card_1_title', 'card_1_body', 'card_2_title', 'card_2_body', 'card_3_title', 'card_3_body',
	'guide_label', 'guide_title', 'guide_body', 'guide_item_1', 'guide_item_2', 'guide_item_3',
	'final_title', 'final_body', 'final_action',
);
foreach ( $pages as $page ) {
	foreach ( array( 'en_US' => $copy_en[ $page ], 'es_ES' => $copy_es[ $page ] ) as $locale => $localized_page ) {
		if ( ! is_array( $localized_page ) ) {
			fail_ecommerce_inner( 'page-copy', 'Each localized DS-6B page must be an object.', $copy_path . '#' . $locale . '.' . $page, 'object', gettype( $localized_page ) );
		}
		$slots = array_keys( $localized_page );
		sort( $slots );
		$expected_slots = $required_slots;
		sort( $expected_slots );
		if ( $slots !== $expected_slots ) {
			fail_ecommerce_inner( 'slot-parity', 'Each DS-6B page must expose the same stable hydration slots.', $copy_path . '#' . $locale . '.' . $page, $expected_slots, $slots );
		}
		foreach ( $localized_page as $slot => $value ) {
			if ( ! is_string( $value ) || '' === trim( $value ) ) {
				fail_ecommerce_inner( 'slot-value', 'DS-6B localized slots must be non-empty strings.', $copy_path . '#' . $locale . '.' . $page . '.' . $slot, 'non-empty string', $value );
			}
		}
	}
}
if ( 1 === preg_match( '/https?:\/\//i', $copy_raw, $match ) ) {
	fail_ecommerce_inner( 'remote-evidence', 'DS-6B provisional copy must not contain remote URLs.', $copy_path, 'no remote URL', $match[0] );
}
if ( 1 === preg_match( '/(?:[$€£]\s*\d|\d[\d.,]*\s*(?:USD|EUR|GBP)\b)/i', $copy_raw, $match ) ) {
	fail_ecommerce_inner( 'numeric-price', 'DS-6B provisional copy must not contain numeric prices.', $copy_path, 'no numeric currency amount', $match[0] );
}

$renderer_path = ECOMMERCE_INNER_THEME_DIR . '/preset-patterns/ecommerce-inner-final.php';
$renderer      = ecommerce_inner_file( $renderer_path );
if ( str_contains( $renderer, '"level":1' ) || 1 === preg_match( '/<h1\b/i', $renderer ) ) {
	fail_ecommerce_inner( 'duplicate-h1', 'DS-6B renderer must leave H1 ownership to the page template.', $renderer_path, 'no H1', 'H1 found' );
}
foreach ( array( 'inner-copy.json', 'seo-geo-commerce-section', 'seo-geo-commerce-category-grid', 'seo-geo-commerce-confidence-grid', 'seo-geo-commerce-final', 'seo-geo-placeholder--copy', 'seo-geo-placeholder--commerce', 'esc_html(' ) as $fragment ) {
	if ( ! str_contains( $renderer, $fragment ) ) {
		fail_ecommerce_inner( 'renderer-contract', 'DS-6B renderer lost a required visual, hydration or provenance surface.', $renderer_path, $fragment, 'missing' );
	}
}
if ( 1 === preg_match( '/https?:\/\/|<!--\s*wp:html\b|<\s*script\b|<\s*style\b|application\/ld\+json|schema\.org|woocommerce\/|wc-block|AggregateRating|"offers"\s*:/i', $renderer, $match ) ) {
	fail_ecommerce_inner( 'unsafe-renderer', 'DS-6B renderer must remain local and non-authoritative for live commerce.', $renderer_path, 'no remote/html/script/style/schema/Woo product blocks', $match[0] );
}

$registration_path = ECOMMERCE_INNER_THEME_DIR . '/inc/Ecommerce/FinalHomePattern.php';
$registration      = ecommerce_inner_file( $registration_path );
foreach ( array(
	'ecommerce-inner-final.php',
	'seo-geo-theme/ecommerce-shop-final',
	'seo-geo-theme/ecommerce-categories-final',
	'seo-geo-theme/ecommerce-buying-guides-final',
	'seo-geo-theme/ecommerce-about-final',
	'seo-geo-theme/ecommerce-support-final',
	'seo-geo-theme/ecommerce-contact-final',
) as $fragment ) {
	if ( ! str_contains( $registration, $fragment ) ) {
		fail_ecommerce_inner( 'registration', 'DS-6B registration lost a required isolated page composition.', $registration_path, $fragment, 'missing' );
	}
}

$css_path = ECOMMERCE_INNER_THEME_DIR . '/assets/css/presets/ecommerce.css';
$css      = ecommerce_inner_file( $css_path );
if ( 1 === preg_match( '/@import\b|url\(\s*["\']?https?:\/\//i', $css, $match ) ) {
	fail_ecommerce_inner( 'remote-css', 'DS-6B must keep the accepted Ecommerce visual system local.', $css_path, 'no remote CSS dependency', $match[0] );
}

echo "Ecommerce final inner-page contract: OK\n";
