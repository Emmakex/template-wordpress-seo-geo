<?php
/**
 * Validate the final Local Business / Local Pro inner-page contract.
 */

declare(strict_types=1);

const LOCAL_INNER_THEME_DIR  = 'packages/seo-geo-theme';
const LOCAL_INNER_PRESET_DIR = 'presets/local-business';

/**
 * Fail with one actionable diagnostic.
 *
 * @param mixed $expected Expected value.
 * @param mixed $received Actual value.
 */
function fail_local_final_inner( string $code, string $message, string $path, mixed $expected, mixed $received ): never {
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
function local_inner_file( string $path ): string {
	if ( ! is_file( $path ) ) {
		fail_local_final_inner( 'missing-file', 'Required Local Pro inner-page file is missing.', $path, 'existing file', 'missing' );
	}

	$content = file_get_contents( $path );
	if ( false === $content || '' === trim( $content ) ) {
		fail_local_final_inner( 'empty-file', 'Required Local Pro inner-page file is empty.', $path, 'non-empty file', 'empty' );
	}

	return $content;
}

/**
 * Decode one required JSON object.
 *
 * @return array<string,mixed>
 */
function local_inner_json( string $path ): array {
	try {
		$value = json_decode( local_inner_file( $path ), true, 512, JSON_THROW_ON_ERROR );
	} catch ( JsonException $exception ) {
		fail_local_final_inner( 'json-invalid', 'Local Pro inner-page contract is invalid JSON.', $path, 'valid JSON object', $exception->getMessage() );
	}

	if ( ! is_array( $value ) ) {
		fail_local_final_inner( 'json-object', 'Local Pro inner-page contract must decode to an object.', $path, 'JSON object', gettype( $value ) );
	}

	return $value;
}

$page_keys   = array( 'services', 'locations', 'about', 'faq', 'contact' );
$mockup_path = LOCAL_INNER_PRESET_DIR . '/mockup.json';
$mockup      = local_inner_json( $mockup_path );

if ( 'inner-pages-candidate' !== ( $mockup['current_stage'] ?? null ) ) {
	fail_local_final_inner( 'stage-contract', 'Local Pro roadmap stage must explicitly identify the inner-page candidate.', $mockup_path . '#current_stage', 'inner-pages-candidate', $mockup['current_stage'] ?? null );
}

foreach ( $page_keys as $page_key ) {
	$page = $mockup['pages'][ $page_key ] ?? null;
	if (
		! is_array( $page )
		|| true !== ( $page['required'] ?? null )
		|| 'local-business-inner-final-v1' !== ( $page['composition'] ?? null )
		|| 'candidate' !== ( $page['status'] ?? null )
		|| false !== ( $page['owns_h1'] ?? null )
	) {
		fail_local_final_inner( 'page-contract', 'Local Pro inner page lost its final-composition or H1 ownership contract.', $mockup_path . '#pages.' . $page_key, 'required candidate using local-business-inner-final-v1 with template-owned H1', $page );
	}
}

$locations = $mockup['pages']['locations'] ?? array();
if (
	true !== ( $locations['verified_local_facts_required'] ?? null )
	|| true !== ( $locations['doorway_pages_forbidden'] ?? null )
	|| true !== ( $locations['distinct_local_value_required'] ?? null )
) {
	fail_local_final_inner( 'locations-fact-gate', 'Locations must require verified local facts, reject doorway pages and require distinct local value.', $mockup_path . '#pages.locations', 'all location safeguards true', $locations );
}

$contact = $mockup['pages']['contact'] ?? array();
if (
	true !== ( $contact['verified_contact_facts_required'] ?? null )
	|| true !== ( $contact['verified_location_facts_required'] ?? null )
	|| true !== ( $contact['verified_hours_required'] ?? null )
) {
	fail_local_final_inner( 'contact-fact-gate', 'Contact must keep public channels, location and hours behind verified facts.', $mockup_path . '#pages.contact', 'all contact safeguards true', $contact );
}

foreach ( array( 'single', 'archive', '404' ) as $system_page ) {
	$status = $mockup['pages'][ $system_page ]['status'] ?? null;
	if ( 'next-microphase' !== $status ) {
		fail_local_final_inner( 'system-surface-honesty', 'Local Pro must not claim system surfaces complete before DS-4C.', $mockup_path . '#pages.' . $system_page, 'next-microphase', $status );
	}
}

if (
	true !== ( $mockup['placeholder_policy']['blocks_publication_until_resolved'] ?? null )
	|| true !== ( $mockup['hydration_contract']['local_business_schema_requires_confirmed_visible_facts'] ?? null )
	|| true !== ( $mockup['acceptance']['doorway_pages_forbidden'] ?? null )
	|| true !== ( $mockup['acceptance']['location_pages_require_distinct_local_value'] ?? null )
) {
	fail_local_final_inner( 'global-local-safeguards', 'Local Pro lost publication, Schema, doorway or distinct-local-value safeguards.', $mockup_path, 'all local safeguards true', $mockup['acceptance'] ?? null );
}

$copy_path = LOCAL_INNER_PRESET_DIR . '/mockup-inner-copy.json';
$copy_raw  = local_inner_file( $copy_path );
$copy      = local_inner_json( $copy_path );

if (
	'placeholder' !== ( $copy['provenance'] ?? null )
	|| false !== ( $copy['publication_ready'] ?? null )
	|| 'final-layout-first-then-content-hydration' !== ( $copy['page_contract'] ?? null )
) {
	fail_local_final_inner( 'copy-provenance', 'Local Pro inner copy must remain provisional and non-publishable.', $copy_path, 'placeholder + publication_ready=false + hydration contract', array( $copy['provenance'] ?? null, $copy['publication_ready'] ?? null, $copy['page_contract'] ?? null ) );
}

$copy_en = $copy['en_US'] ?? null;
$copy_es = $copy['es_ES'] ?? null;
if ( ! is_array( $copy_en ) || ! is_array( $copy_es ) ) {
	fail_local_final_inner( 'copy-locales', 'Local Pro inner copy requires EN and ES maps.', $copy_path, 'en_US + es_ES', array_keys( $copy ) );
}

$keys_en = array_keys( $copy_en );
$keys_es = array_keys( $copy_es );
$expected_keys = $page_keys;
sort( $keys_en );
sort( $keys_es );
sort( $expected_keys );
if ( $keys_en !== $expected_keys || $keys_es !== $expected_keys ) {
	fail_local_final_inner( 'copy-page-parity', 'Local Pro inner locales must expose the same five pages.', $copy_path, $expected_keys, array( 'en' => $keys_en, 'es' => $keys_es ) );
}

foreach ( array( 'en_US' => $copy_en, 'es_ES' => $copy_es ) as $locale => $localized_pages ) {
	foreach ( $page_keys as $page_key ) {
		$page = $localized_pages[ $page_key ] ?? null;
		if ( ! is_array( $page ) || ! is_string( $page['eyebrow'] ?? null ) || ! is_string( $page['lead'] ?? null ) ) {
			fail_local_final_inner( 'copy-page-shape', 'Each Local Pro inner page requires eyebrow and lead copy.', $copy_path . '#' . $locale . '.' . $page_key, 'eyebrow + lead strings', $page );
		}

		$sections = $page['sections'] ?? null;
		if ( ! is_array( $sections ) || array() === $sections ) {
			fail_local_final_inner( 'copy-sections', 'Each Local Pro inner page requires at least one complete content section.', $copy_path . '#' . $locale . '.' . $page_key . '.sections', 'one or more sections', $sections );
		}

		foreach ( $sections as $index => $section ) {
			$cards = is_array( $section ) ? ( $section['cards'] ?? null ) : null;
			if (
				! is_array( $section )
				|| ! is_string( $section['label'] ?? null )
				|| ! is_string( $section['title'] ?? null )
				|| ! is_string( $section['body'] ?? null )
				|| ! is_bool( $section['fact_gated'] ?? null )
				|| ! is_array( $cards )
				|| 3 !== count( $cards )
			) {
				fail_local_final_inner( 'copy-section-shape', 'Each Local Pro section requires label, title, body, fact gate and three cards.', $copy_path . '#' . $locale . '.' . $page_key . '.sections.' . $index, 'label + title + body + fact_gated + 3 cards', $section );
			}
		}

		$cta = $page['cta'] ?? null;
		if ( ! is_array( $cta ) || ! is_string( $cta['title'] ?? null ) || ! is_string( $cta['body'] ?? null ) || ! is_string( $cta['button'] ?? null ) ) {
			fail_local_final_inner( 'copy-cta-shape', 'Each Local Pro inner page requires a complete CTA hydration slot.', $copy_path . '#' . $locale . '.' . $page_key . '.cta', 'title + body + button', $cta );
		}
	}
}

foreach ( array( 'en_US', 'es_ES' ) as $locale ) {
	if (
		true !== ( $copy[ $locale ]['locations']['sections'][0]['fact_gated'] ?? null )
		|| true !== ( $copy[ $locale ]['contact']['sections'][0]['fact_gated'] ?? null )
	) {
		fail_local_final_inner( 'localized-fact-gates', 'Locations and Contact first sections must remain fact-gated in every locale.', $copy_path . '#' . $locale, 'locations/contact first section fact_gated=true', array( $copy[ $locale ]['locations']['sections'][0]['fact_gated'] ?? null, $copy[ $locale ]['contact']['sections'][0]['fact_gated'] ?? null ) );
	}
}

if ( 1 === preg_match( '/https?:\/\/|tel:|mailto:|maps\.google|google\.[^\s\"]*\/maps|schema\.org|LocalBusiness|AggregateRating|Review/i', $copy_raw, $match ) ) {
	fail_local_final_inner( 'invented-local-fact', 'Provisional Local Pro inner copy must not embed remote maps, public contact routes or structured/review evidence.', $copy_path, 'no remote/contact/schema/review payload', $match[0] );
}

if ( ! str_contains( strtolower( $copy_raw ), 'doorway' ) || ! str_contains( strtolower( $copy_raw ), 'clones por ciudad' ) ) {
	fail_local_final_inner( 'anti-doorway-copy', 'Locations copy must explicitly preserve the no-doorway/no-city-clone editorial rule.', $copy_path, 'doorway + clones por ciudad safeguards', 'required language missing' );
}

$renderer_path = LOCAL_INNER_THEME_DIR . '/preset-patterns/local-business-inner-final.php';
$renderer      = local_inner_file( $renderer_path );

if ( str_contains( $renderer, '"level":1' ) || 1 === preg_match( '/<h1\b/i', $renderer ) ) {
	fail_local_final_inner( 'duplicate-h1', 'Local Pro inner renderer must leave the document H1 to the page template.', $renderer_path, 'no H1', 'H1 found' );
}

foreach (
	array(
		'mockup-inner-copy.json',
		'seo-geo-local-inner-hero',
		'seo-geo-local-service-grid',
		'seo-geo-local-area-visual',
		'seo-geo-local-faq',
		'seo-geo-placeholder--copy',
		'seo-geo-placeholder--fact',
		'esc_html(',
	) as $fragment
) {
	if ( ! str_contains( $renderer, $fragment ) ) {
		fail_local_final_inner( 'renderer-contract', 'Local Pro inner renderer lost a required visual, fact or escaping contract.', $renderer_path, $fragment, 'fragment missing' );
	}
}

if ( 1 === preg_match( '/https?:\/\/|tel:|mailto:|<!--\s*wp:html\b|<\s*script\b|<\s*style\b|application\/ld\+json|schema\.org|AggregateRating/i', $renderer, $match ) ) {
	fail_local_final_inner( 'unsafe-renderer', 'Local Pro inner renderer must stay local, native and non-authoritative for Schema/contact facts.', $renderer_path, 'no remote/html/script/style/schema/contact payload', $match[0] );
}

$registrar_path = LOCAL_INNER_THEME_DIR . '/inc/LocalBusiness/FinalInnerPatterns.php';
$registrar      = local_inner_file( $registrar_path );
foreach (
	array(
		"'local-business' !== seo_geo_theme_active_preset_id()",
		"'seo-geo-theme/local-business-services-final'",
		"'seo-geo-theme/local-business-locations-final'",
		"'seo-geo-theme/local-business-about-final'",
		"'seo-geo-theme/local-business-faq-final'",
		"'seo-geo-theme/local-business-contact-final'",
		"'/preset-patterns/local-business-inner-final.php'",
		"add_action( 'init', 'seo_geo_theme_register_local_business_final_inner_patterns', 25 )",
	) as $fragment
) {
	if ( ! str_contains( $registrar, $fragment ) ) {
		fail_local_final_inner( 'registrar-contract', 'Local Pro inner patterns lost active-preset isolation or one required registration.', $registrar_path, $fragment, 'fragment missing' );
	}
}

$functions_path = LOCAL_INNER_THEME_DIR . '/functions.php';
$functions      = local_inner_file( $functions_path );
if ( ! str_contains( $functions, "'/inc/LocalBusiness/FinalInnerPatterns.php'" ) ) {
	fail_local_final_inner( 'bootstrap-contract', 'Local Pro final inner registrar is not loaded by the Theme bootstrap.', $functions_path, "FinalInnerPatterns.php require_once", 'fragment missing' );
}

foreach ( array( 'seo_geo_theme_normalize_migrated_text', 'seo_geo_theme_guard_singular_content_h1' ) as $guard ) {
	if ( ! str_contains( $functions, $guard ) ) {
		fail_local_final_inner( 'shared-guard-regression', 'Local Pro bootstrap must not remove an already accepted shared migration/H1 guard.', $functions_path, $guard, 'fragment missing' );
	}
}

echo "Local Pro final inner-page contract: OK\n";
