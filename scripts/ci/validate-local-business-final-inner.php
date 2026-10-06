<?php
/**
 * Validate the accepted Local Business / Local Pro inner-page contract while
 * DS-4 advances into system surfaces.
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

/** Read one required UTF-8 file. */
function local_inner_file( string $path ): string {
	$content = is_file( $path ) ? file_get_contents( $path ) : false;
	if ( false === $content || '' === trim( $content ) ) {
		fail_local_final_inner( 'missing-file', 'Required Local Pro inner-page file is missing or empty.', $path, 'non-empty file', 'missing/empty' );
	}
	return $content;
}

/** @return array<string,mixed> */
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
$stage       = $mockup['current_stage'] ?? null;

if ( ! in_array( $stage, array( 'inner-pages-candidate', 'system-surfaces-candidate' ), true ) ) {
	fail_local_final_inner( 'stage-contract', 'Local Pro roadmap stage must preserve accepted inner pages while DS-4 advances.', $mockup_path . '#current_stage', 'inner-pages-candidate or system-surfaces-candidate', $stage );
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
	fail_local_final_inner( 'locations-fact-gate', 'Locations must require verified facts, reject doorway pages and require distinct local value.', $mockup_path . '#pages.locations', 'all location safeguards true', $locations );
}

$contact = $mockup['pages']['contact'] ?? array();
if (
	true !== ( $contact['verified_contact_facts_required'] ?? null )
	|| true !== ( $contact['verified_location_facts_required'] ?? null )
	|| true !== ( $contact['verified_hours_required'] ?? null )
) {
	fail_local_final_inner( 'contact-fact-gate', 'Contact must keep channels, location and hours behind verified facts.', $mockup_path . '#pages.contact', 'all contact safeguards true', $contact );
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
	|| ! is_array( $copy['en_US'] ?? null )
	|| ! is_array( $copy['es_ES'] ?? null )
) {
	fail_local_final_inner( 'copy-provenance', 'Local Pro inner copy must remain bilingual, provisional and non-publishable.', $copy_path, 'placeholder + publication_ready=false + EN/ES', $copy );
}
foreach ( $page_keys as $page_key ) {
	if ( ! is_array( $copy['en_US'][ $page_key ] ?? null ) || ! is_array( $copy['es_ES'][ $page_key ] ?? null ) ) {
		fail_local_final_inner( 'copy-parity', 'Every Local Pro inner page requires EN/ES placeholder copy.', $copy_path . '#' . $page_key, 'EN/ES objects', 'missing locale/page' );
	}
}
if ( 1 === preg_match( '/https?:\/\/|tel:|mailto:|maps\.google|schema\.org|LocalBusiness|AggregateRating|Review/i', $copy_raw, $match ) ) {
	fail_local_final_inner( 'invented-local-fact', 'Inner-page placeholder copy must not embed remote/contact/Schema/review evidence.', $copy_path, 'no authoritative payload', $match[0] );
}

$renderer_path = LOCAL_INNER_THEME_DIR . '/preset-patterns/local-business-inner-final.php';
$renderer      = local_inner_file( $renderer_path );
if ( str_contains( $renderer, '"level":1' ) || 1 === preg_match( '/<h1\b/i', $renderer ) ) {
	fail_local_final_inner( 'duplicate-h1', 'Local Pro inner renderer must leave H1 ownership to the page template.', $renderer_path, 'no H1', 'H1 found' );
}
foreach ( array( 'services', 'locations', 'about', 'faq', 'contact', 'seo-geo-placeholder--copy', 'seo-geo-placeholder--fact', 'seo-geo-local-service-grid', 'seo-geo-local-area-visual', 'esc_html(' ) as $fragment ) {
	if ( ! str_contains( $renderer, $fragment ) ) {
		fail_local_final_inner( 'renderer-contract', 'Local Pro inner renderer lost a required page, visual or hydration contract.', $renderer_path, $fragment, 'missing' );
	}
}
if ( 1 === preg_match( '/https?:\/\/|tel:|mailto:|<\s*script\b|<\s*style\b|application\/ld\+json|AggregateRating/i', $renderer, $match ) ) {
	fail_local_final_inner( 'unsafe-renderer', 'Local Pro inner renderer must remain native and non-authoritative for contact/Schema evidence.', $renderer_path, 'safe native renderer', $match[0] );
}

$registrar_path = LOCAL_INNER_THEME_DIR . '/inc/LocalBusiness/FinalInnerPatterns.php';
$registrar      = local_inner_file( $registrar_path );
foreach ( array( 'local-business', 'local-business-inner-final.php', 'services', 'locations', 'about', 'faq', 'contact' ) as $fragment ) {
	if ( ! str_contains( $registrar, $fragment ) ) {
		fail_local_final_inner( 'registrar-contract', 'Local Pro inner patterns lost isolated preset registration.', $registrar_path, $fragment, 'missing' );
	}
}

echo "Local Pro final inner-page contract: OK\n";
