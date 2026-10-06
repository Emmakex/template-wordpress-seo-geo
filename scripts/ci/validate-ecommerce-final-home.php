<?php
/**
 * Validate the Ecommerce / Commerce final Home visual and provider contract.
 */

declare(strict_types=1);

const ECOMMERCE_FINAL_THEME_DIR  = 'packages/seo-geo-theme';
const ECOMMERCE_FINAL_PRESET_DIR = 'presets/ecommerce';

/**
 * Fail with one actionable diagnostic.
 *
 * @param mixed $expected Expected value.
 * @param mixed $received Actual value.
 */
function fail_ecommerce_final_home( string $code, string $message, string $path, mixed $expected, mixed $received ): never {
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
function ecommerce_final_file( string $path ): string {
	$content = is_file( $path ) ? file_get_contents( $path ) : false;
	if ( false === $content || '' === trim( $content ) ) {
		fail_ecommerce_final_home( 'missing-file', 'Required Ecommerce final Home file is missing or empty.', $path, 'non-empty file', 'missing/empty' );
	}
	return $content;
}

/** @return array<string,mixed> */
function ecommerce_final_json( string $path ): array {
	try {
		$value = json_decode( ecommerce_final_file( $path ), true, 512, JSON_THROW_ON_ERROR );
	} catch ( JsonException $exception ) {
		fail_ecommerce_final_home( 'json-invalid', 'Ecommerce contract is invalid JSON.', $path, 'valid JSON object', $exception->getMessage() );
	}
	if ( ! is_array( $value ) ) {
		fail_ecommerce_final_home( 'json-object', 'Ecommerce contract must decode to an object.', $path, 'JSON object', gettype( $value ) );
	}
	return $value;
}

$manifest_path = ECOMMERCE_FINAL_PRESET_DIR . '/preset.json';
$manifest      = ecommerce_final_json( $manifest_path );
if (
	'ecommerce' !== ( $manifest['id'] ?? null )
	|| 'woocommerce' !== ( $manifest['commerce_model']['preferred_provider'] ?? null )
	|| true !== ( $manifest['commerce_model']['requires_supported_adapter_for_live_commerce'] ?? null )
	|| true !== ( $manifest['commerce_model']['provider_owns_product_facts'] ?? null )
	|| true !== ( $manifest['commerce_model']['provider_owns_price_availability'] ?? null )
	|| true !== ( $manifest['commerce_model']['provider_owns_reviews_ratings'] ?? null )
	|| false !== ( $manifest['schema']['theme_emits_product_schema'] ?? null )
) {
	fail_ecommerce_final_home( 'provider-authority', 'Ecommerce must preserve provider ownership of live commerce facts and Product Schema.', $manifest_path, 'WooCommerce preferred + adapter required + provider-owned facts + theme Product Schema disabled', $manifest );
}

$mockup_path = ECOMMERCE_FINAL_PRESET_DIR . '/mockup.json';
$mockup      = ecommerce_final_json( $mockup_path );
$expected    = array(
	'preset'               => 'ecommerce',
	'target_state'         => '99-percent-finished-before-client-content',
	'current_stage'        => 'home-candidate',
	'design_rule'          => 'final-layout-first-then-content-hydration',
	'legacy_layout_policy' => 'never-a-design-source',
);
foreach ( $expected as $key => $value ) {
	if ( $value !== ( $mockup[ $key ] ?? null ) ) {
		fail_ecommerce_final_home( 'mockup-contract', 'Ecommerce final product rule changed unexpectedly.', $mockup_path . '#' . $key, $value, $mockup[ $key ] ?? null );
	}
}

$home = $mockup['pages']['home'] ?? null;
if (
	! is_array( $home )
	|| true !== ( $home['required'] ?? null )
	|| 'ecommerce-home-final-v1' !== ( $home['composition'] ?? null )
	|| 'candidate' !== ( $home['status'] ?? null )
	|| false !== ( $home['owns_h1'] ?? null )
) {
	fail_ecommerce_final_home( 'home-contract', 'Ecommerce Home candidate contract is incomplete.', $mockup_path . '#pages.home', 'required ecommerce-home-final-v1 with template-owned H1', $home );
}

foreach ( array( 'shop', 'categories', 'buying-guides', 'about', 'support', 'contact', 'single', 'archive', '404' ) as $pending_page ) {
	$status = $mockup['pages'][ $pending_page ]['status'] ?? null;
	if ( 'next-microphase' !== $status ) {
		fail_ecommerce_final_home( 'roadmap-honesty', 'DS-6A must not claim later Ecommerce surfaces complete.', $mockup_path . '#pages.' . $pending_page, 'next-microphase', $status );
	}
}

if (
	false !== ( $mockup['visual_runtime']['required_frontend_javascript'] ?? null )
	|| false !== ( $mockup['visual_runtime']['remote_fonts'] ?? null )
	|| false !== ( $mockup['visual_runtime']['remote_visual_dependencies'] ?? null )
	|| true !== ( $mockup['placeholder_policy']['blocks_publication_until_resolved'] ?? null )
	|| true !== ( $mockup['commerce_authority']['live_commerce_requires_supported_adapter'] ?? null )
	|| false !== ( $mockup['commerce_authority']['theme_emits_product_offer_schema'] ?? null )
	|| false !== ( $mockup['commerce_authority']['theme_may_infer_commerce_facts'] ?? null )
	|| true !== ( $mockup['acceptance']['performance_budgets_must_not_be_relaxed'] ?? null )
	|| true !== ( $mockup['acceptance']['commerce_evidence_must_not_be_fabricated'] ?? null )
) {
	fail_ecommerce_final_home( 'commerce-safety', 'Ecommerce lost visual, publication, provider or evidence safeguards.', $mockup_path, 'zero remote/JS + publication block + provider authority + strict budgets', $mockup );
}

$forbidden_claims = $mockup['placeholder_policy']['forbidden_claims'] ?? null;
if ( ! is_array( $forbidden_claims ) ) {
	fail_ecommerce_final_home( 'forbidden-claims', 'Ecommerce must explicitly enumerate forbidden provisional commerce claims.', $mockup_path . '#placeholder_policy.forbidden_claims', 'claim list', $forbidden_claims );
}
foreach ( array( 'fabricated-price', 'fabricated-stock', 'fabricated-availability', 'fabricated-rating', 'fabricated-review', 'fabricated-shipping-time', 'fabricated-return-window' ) as $claim ) {
	if ( ! in_array( $claim, $forbidden_claims, true ) ) {
		fail_ecommerce_final_home( 'forbidden-claim-missing', 'Ecommerce provisional safeguards lost a required prohibited claim.', $mockup_path, $claim, $forbidden_claims );
	}
}

$copy_path = ECOMMERCE_FINAL_PRESET_DIR . '/mockup-copy.json';
$copy_raw  = ecommerce_final_file( $copy_path );
$copy_doc  = ecommerce_final_json( $copy_path );
if (
	'ecommerce' !== ( $copy_doc['preset'] ?? null )
	|| 'placeholder' !== ( $copy_doc['provenance'] ?? null )
	|| false !== ( $copy_doc['publication_ready'] ?? null )
	|| 'final-layout-first-then-content-hydration' !== ( $copy_doc['page_contract'] ?? null )
) {
	fail_ecommerce_final_home( 'copy-provenance', 'Ecommerce Home copy must remain explicit placeholder data and non-publishable.', $copy_path, 'ecommerce + placeholder + publication_ready=false', $copy_doc );
}

$copy_en = $copy_doc['en_US'] ?? null;
$copy_es = $copy_doc['es_ES'] ?? null;
if ( ! is_array( $copy_en ) || ! is_array( $copy_es ) ) {
	fail_ecommerce_final_home( 'copy-locales', 'Ecommerce Home requires complete EN and ES locale maps.', $copy_path, 'en_US + es_ES objects', array_keys( $copy_doc ) );
}
$keys_en = array_keys( $copy_en );
$keys_es = array_keys( $copy_es );
sort( $keys_en );
sort( $keys_es );
if ( $keys_en !== $keys_es || count( $keys_en ) < 50 ) {
	fail_ecommerce_final_home( 'copy-parity', 'Ecommerce Home locales must expose the same complete hydration-slot contract.', $copy_path, 'matching EN/ES keys with at least 50 slots', array( 'en' => count( $keys_en ), 'es' => count( $keys_es ) ) );
}
foreach ( array( 'kicker', 'catalog_title', 'provider_note_title', 'confidence_title', 'guide_title', 'brand_title', 'policies_title', 'final_title' ) as $required_key ) {
	foreach ( array( 'en_US' => $copy_en, 'es_ES' => $copy_es ) as $locale => $localized_copy ) {
		$value = $localized_copy[ $required_key ] ?? null;
		if ( ! is_string( $value ) || '' === trim( $value ) ) {
			fail_ecommerce_final_home( 'copy-required', 'Ecommerce Home copy lost a required localized hydration slot.', $copy_path . '#' . $locale . '.' . $required_key, 'non-empty string', $value );
		}
	}
}

if ( 1 === preg_match( '/https?:\/\//i', $copy_raw, $match ) ) {
	fail_ecommerce_final_home( 'copy-remote-evidence', 'Ecommerce provisional copy must not contain remote catalog/evidence URLs.', $copy_path, 'no remote URL', $match[0] );
}
if ( 1 === preg_match( '/(?:[$€£]\s*\d|\d[\d.,]*\s*(?:USD|EUR|GBP)\b)/i', $copy_raw, $match ) ) {
	fail_ecommerce_final_home( 'copy-price', 'Ecommerce provisional copy must not contain a numeric price.', $copy_path, 'no numeric currency amount', $match[0] );
}

$pattern_path = ECOMMERCE_FINAL_THEME_DIR . '/preset-patterns/ecommerce-home-final.php';
$pattern      = ecommerce_final_file( $pattern_path );
if ( str_contains( $pattern, '"level":1' ) || 1 === preg_match( '/<h1\b/i', $pattern ) ) {
	fail_ecommerce_final_home( 'duplicate-h1', 'Ecommerce final Home must leave the document H1 to front-page.html.', $pattern_path, 'no H1', 'H1 found' );
}
foreach ( array( 'mockup-copy.json', 'seo-geo-commerce-hero', 'seo-geo-commerce-category-grid', 'seo-geo-commerce-catalog-grid', 'seo-geo-commerce-confidence-grid', 'seo-geo-commerce-guide', 'seo-geo-commerce-brand', 'seo-geo-commerce-policy-links', 'seo-geo-commerce-final', 'seo-geo-placeholder--commerce', 'seo-geo-placeholder--media', 'esc_html(' ) as $fragment ) {
	if ( ! str_contains( $pattern, $fragment ) ) {
		fail_ecommerce_final_home( 'pattern-section', 'Ecommerce final Home lost a required visual, hydration or safety surface.', $pattern_path, $fragment, 'missing' );
	}
}
if ( substr_count( $pattern, 'seo-geo-placeholder--commerce' ) < 12 || substr_count( $pattern, 'seo-geo-placeholder--copy' ) < 30 || substr_count( $pattern, 'seo-geo-placeholder--media' ) < 5 ) {
	fail_ecommerce_final_home( 'placeholder-provenance', 'Ecommerce provisional copy/media/commerce facts must stay visibly marked.', $pattern_path, '>=12 commerce, >=30 copy, >=5 media markers', array( 'commerce' => substr_count( $pattern, 'seo-geo-placeholder--commerce' ), 'copy' => substr_count( $pattern, 'seo-geo-placeholder--copy' ), 'media' => substr_count( $pattern, 'seo-geo-placeholder--media' ) ) );
}
if ( 1 === preg_match( '/https?:\/\/|<!--\s*wp:html\b|<\s*script\b|<\s*style\b|application\/ld\+json|schema\.org|woocommerce\/|wc-block|AggregateRating|"offers"\s*:/i', $pattern, $match ) ) {
	fail_ecommerce_final_home( 'unsafe-pattern', 'Ecommerce final Home must stay local, provider-neutral and non-authoritative for live commerce Schema.', $pattern_path, 'no remote/html/script/style/schema/Woo product blocks', $match[0] );
}
if ( 1 === preg_match( '/(?:[$€£]\s*\d|\d[\d.,]*\s*(?:USD|EUR|GBP)\b)/i', $pattern, $match ) ) {
	fail_ecommerce_final_home( 'pattern-price', 'Ecommerce final Home must not hard-code numeric prices.', $pattern_path, 'no numeric currency amount', $match[0] );
}

$css_path = ECOMMERCE_FINAL_THEME_DIR . '/assets/css/presets/ecommerce.css';
$css      = ecommerce_final_file( $css_path );
foreach ( array( 'seo-geo-commerce-hero__grid', 'seo-geo-commerce-catalog-grid', 'seo-geo-commerce-confidence-grid', 'seo-geo-commerce-policy-links', '@media (max-width: 900px)', 'prefers-reduced-motion' ) as $fragment ) {
	if ( ! str_contains( $css, $fragment ) ) {
		fail_ecommerce_final_home( 'visual-contract', 'Ecommerce CSS lost a required visual/responsive contract.', $css_path, $fragment, 'missing' );
	}
}
if ( 1 === preg_match( '/@import\b|url\(\s*["\']?https?:\/\//i', $css, $match ) ) {
	fail_ecommerce_final_home( 'remote-css', 'Ecommerce visual system must not add remote CSS/font/media dependencies.', $css_path, 'no @import or remote url()', $match[0] );
}

$registration_path = ECOMMERCE_FINAL_THEME_DIR . '/inc/Ecommerce/FinalHomePattern.php';
$registration      = ecommerce_final_file( $registration_path );
foreach ( array( "'ecommerce' !== seo_geo_theme_active_preset_id()", "'seo-geo-theme/ecommerce-home-final'", "array( 'seo-geo-ecommerce' )", 'ecommerce-home-final.php' ) as $fragment ) {
	if ( ! str_contains( $registration, $fragment ) ) {
		fail_ecommerce_final_home( 'pattern-registration', 'Ecommerce Home registration lost active-preset isolation or category ownership.', $registration_path, $fragment, 'missing' );
	}
}

$functions_path = ECOMMERCE_FINAL_THEME_DIR . '/functions.php';
$functions      = ecommerce_final_file( $functions_path );
foreach ( array( '/inc/Ecommerce/FinalHomePattern.php', "'publisher' === \$preset_id", "'ecommerce' === \$preset_id", '/assets/css/design-system.css' ) as $fragment ) {
	if ( ! str_contains( $functions, $fragment ) ) {
		fail_ecommerce_final_home( 'style-runtime', 'Theme runtime lost Ecommerce registration, Design System opt-in or accepted Publisher behavior.', $functions_path, $fragment, 'missing' );
	}
}

echo "Ecommerce final Home contract: OK\n";
