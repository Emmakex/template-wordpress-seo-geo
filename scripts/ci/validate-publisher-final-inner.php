<?php
/**
 * Validate final Publisher / Editorial inner-page contracts.
 */

declare(strict_types=1);

const PUBLISHER_INNER_THEME_DIR  = 'packages/seo-geo-theme';
const PUBLISHER_INNER_PRESET_DIR = 'presets/publisher';

/**
 * Fail with one actionable diagnostic.
 *
 * @param mixed $expected Expected value.
 * @param mixed $received Actual value.
 */
function fail_publisher_final_inner( string $code, string $message, string $path, mixed $expected, mixed $received ): never {
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

/** Read one required file. */
function publisher_inner_file( string $path ): string {
	if ( ! is_file( $path ) ) {
		fail_publisher_final_inner( 'missing-file', 'Required Publisher inner-page file is missing.', $path, 'existing file', 'missing' );
	}

	$content = file_get_contents( $path );
	if ( false === $content || '' === trim( $content ) ) {
		fail_publisher_final_inner( 'empty-file', 'Required Publisher inner-page file is empty.', $path, 'non-empty file', 'empty' );
	}

	return $content;
}

/**
 * Decode one required JSON object.
 *
 * @return array<string,mixed>
 */
function publisher_inner_json( string $path ): array {
	try {
		$value = json_decode( publisher_inner_file( $path ), true, 512, JSON_THROW_ON_ERROR );
	} catch ( JsonException $exception ) {
		fail_publisher_final_inner( 'json-invalid', 'Publisher inner-page contract is invalid JSON.', $path, 'valid JSON object', $exception->getMessage() );
	}

	if ( ! is_array( $value ) ) {
		fail_publisher_final_inner( 'json-object', 'Publisher inner-page contract must decode to an object.', $path, 'JSON object', gettype( $value ) );
	}

	return $value;
}

$page_keys   = array( 'articles', 'topics', 'authors', 'about', 'editorial-policy', 'contact' );
$mockup_path = PUBLISHER_INNER_PRESET_DIR . '/mockup.json';
$mockup      = publisher_inner_json( $mockup_path );

if ( 'inner-pages-candidate' !== ( $mockup['current_stage'] ?? null ) ) {
	fail_publisher_final_inner( 'stage-contract', 'Publisher roadmap stage must explicitly identify the inner-page candidate.', $mockup_path . '#current_stage', 'inner-pages-candidate', $mockup['current_stage'] ?? null );
}

foreach ( $page_keys as $page_key ) {
	$page = $mockup['pages'][ $page_key ] ?? null;
	if (
		! is_array( $page )
		|| true !== ( $page['required'] ?? null )
		|| 'publisher-inner-final-v1' !== ( $page['composition'] ?? null )
		|| 'candidate' !== ( $page['status'] ?? null )
		|| false !== ( $page['owns_h1'] ?? null )
	) {
		fail_publisher_final_inner( 'page-contract', 'Publisher inner page lost its final-composition or H1 ownership contract.', $mockup_path . '#pages.' . $page_key, 'required candidate using publisher-inner-final-v1 with template-owned H1', $page );
	}
}

if ( true !== ( $mockup['pages']['authors']['real_public_authors_required'] ?? null ) ) {
	fail_publisher_final_inner( 'author-gate', 'Authors page must require real public authors.', $mockup_path . '#pages.authors', true, $mockup['pages']['authors'] ?? null );
}
if ( true !== ( $mockup['pages']['editorial-policy']['real_policy_required'] ?? null ) ) {
	fail_publisher_final_inner( 'policy-gate', 'Editorial Policy must represent a real maintained policy.', $mockup_path . '#pages.editorial-policy', true, $mockup['pages']['editorial-policy'] ?? null );
}
if ( true !== ( $mockup['pages']['contact']['verified_public_routes_required'] ?? null ) ) {
	fail_publisher_final_inner( 'contact-gate', 'Publisher Contact must require verified public routes.', $mockup_path . '#pages.contact', true, $mockup['pages']['contact'] ?? null );
}

foreach ( array( 'single', 'archive', '404' ) as $system_page ) {
	$status = $mockup['pages'][ $system_page ]['status'] ?? null;
	if ( 'next-microphase' !== $status ) {
		fail_publisher_final_inner( 'system-surface-honesty', 'DS-5B must not claim Publisher system surfaces complete.', $mockup_path . '#pages.' . $system_page, 'next-microphase', $status );
	}
}

if (
	true !== ( $mockup['placeholder_policy']['blocks_publication_until_resolved'] ?? null )
	|| true !== ( $mockup['hydration_contract']['replace_author_slots_from_public_wordpress_users'] ?? null )
	|| true !== ( $mockup['hydration_contract']['replace_source_slots_only_from_editor_added_references'] ?? null )
	|| true !== ( $mockup['hydration_contract']['replace_dates_only_from_wordpress_post_timestamps'] ?? null )
	|| true !== ( $mockup['acceptance']['evidence_must_not_be_fabricated'] ?? null )
) {
	fail_publisher_final_inner( 'editorial-safeguards', 'Publisher inner pages lost publication or provenance safeguards.', $mockup_path, 'publication block + real author/source/date authorities', $mockup );
}

$copy_path = PUBLISHER_INNER_PRESET_DIR . '/mockup-inner-copy.json';
$copy_raw  = publisher_inner_file( $copy_path );
$copy_doc  = publisher_inner_json( $copy_path );

if (
	'placeholder' !== ( $copy_doc['provenance'] ?? null )
	|| false !== ( $copy_doc['publication_ready'] ?? null )
	|| 'final-layout-first-then-content-hydration' !== ( $copy_doc['page_contract'] ?? null )
) {
	fail_publisher_final_inner( 'copy-provenance', 'Publisher inner copy must remain explicit placeholder data and non-publishable.', $copy_path, 'placeholder + publication_ready=false', $copy_doc );
}

$copy_en = $copy_doc['en_US'] ?? null;
$copy_es = $copy_doc['es_ES'] ?? null;
if ( ! is_array( $copy_en ) || ! is_array( $copy_es ) ) {
	fail_publisher_final_inner( 'copy-locales', 'Publisher inner copy requires complete EN and ES locale maps.', $copy_path, 'en_US + es_ES objects', array_keys( $copy_doc ) );
}

$keys_en = array_keys( $copy_en );
$keys_es = array_keys( $copy_es );
sort( $keys_en );
sort( $keys_es );
$expected_keys = $page_keys;
sort( $expected_keys );
if ( $expected_keys !== $keys_en || $expected_keys !== $keys_es ) {
	fail_publisher_final_inner( 'copy-page-parity', 'Publisher inner copy must expose exactly the six accepted pages in EN and ES.', $copy_path, $expected_keys, array( 'en' => $keys_en, 'es' => $keys_es ) );
}

foreach ( array( 'en_US' => $copy_en, 'es_ES' => $copy_es ) as $locale => $localized_pages ) {
	foreach ( $page_keys as $page_key ) {
		$page = $localized_pages[ $page_key ] ?? null;
		if ( ! is_array( $page ) ) {
			fail_publisher_final_inner( 'copy-page', 'Publisher inner copy page must be an object.', $copy_path . '#' . $locale . '.' . $page_key, 'page object', $page );
		}

		$eyebrow  = $page['eyebrow'] ?? null;
		$lead     = $page['lead'] ?? null;
		$sections = $page['sections'] ?? null;
		$cta      = $page['cta'] ?? null;
		if ( ! is_string( $eyebrow ) || '' === trim( $eyebrow ) || ! is_string( $lead ) || '' === trim( $lead ) || ! is_array( $sections ) || 2 !== count( $sections ) || ! is_array( $cta ) ) {
			fail_publisher_final_inner( 'copy-shape', 'Each Publisher inner page requires eyebrow, lead, two sections and CTA.', $copy_path . '#' . $locale . '.' . $page_key, 'complete inner-page shape', $page );
		}

		foreach ( $sections as $section ) {
			$cards = is_array( $section ) ? ( $section['cards'] ?? null ) : null;
			if ( ! is_array( $cards ) || 3 !== count( $cards ) ) {
				fail_publisher_final_inner( 'section-cards', 'Each Publisher inner section requires three provisional cards.', $copy_path . '#' . $locale . '.' . $page_key, '3 cards per section', $section );
			}
		}
	}
}

if ( 1 === preg_match( '/https?:\/\//i', $copy_raw, $match ) ) {
	fail_publisher_final_inner( 'copy-remote-evidence', 'Publisher inner placeholder copy must not contain source/evidence URLs.', $copy_path, 'no remote URL', $match[0] );
}

$renderer_path = PUBLISHER_INNER_THEME_DIR . '/preset-patterns/publisher-inner-final.php';
$renderer      = publisher_inner_file( $renderer_path );
if ( str_contains( $renderer, '"level":1' ) || 1 === preg_match( '/<h1\b/i', $renderer ) ) {
	fail_publisher_final_inner( 'duplicate-h1', 'Publisher inner renderer must leave the document H1 to the page template.', $renderer_path, 'no H1', 'H1 found' );
}

foreach ( array( 'mockup-inner-copy.json', "array( 'articles', 'topics', 'authors', 'about', 'editorial-policy', 'contact' )", 'seo-geo-section-intro', 'seo-geo-bento--balanced', 'seo-geo-publisher-story-card', 'seo-geo-publisher-follow', 'seo-geo-placeholder--copy', 'esc_html(' ) as $fragment ) {
	if ( ! str_contains( $renderer, $fragment ) ) {
		fail_publisher_final_inner( 'renderer-contract', 'Publisher inner renderer lost a required visual or hydration contract.', $renderer_path, $fragment, 'fragment missing' );
	}
}

if ( 1 === preg_match( '/https?:\/\/|<!--\s*wp:html\b|<\s*script\b|<\s*style\b|application\/ld\+json|schema\.org/i', $renderer, $match ) ) {
	fail_publisher_final_inner( 'unsafe-renderer', 'Publisher inner renderer must stay local, native and non-authoritative for Schema.', $renderer_path, 'no remote/html/script/style/schema payload', $match[0] );
}

$registration_path = PUBLISHER_INNER_THEME_DIR . '/inc/Publisher/FinalHomePattern.php';
$registration      = publisher_inner_file( $registration_path );
foreach ( array( 'publisher-inner-final.php', 'seo_geo_theme_register_publisher_final_inner_patterns', "'publisher' !== seo_geo_theme_active_preset_id()", "array( 'seo-geo-publisher' )" ) as $fragment ) {
	if ( ! str_contains( $registration, $fragment ) ) {
		fail_publisher_final_inner( 'registration-contract', 'Publisher inner registration lost renderer, isolation or category ownership.', $registration_path, $fragment, 'fragment missing' );
	}
}

foreach ( array( 'publisher-articles-final', 'publisher-topics-final', 'publisher-authors-final', 'publisher-about-final', 'publisher-editorial-policy-final', 'publisher-contact-final' ) as $slug ) {
	if ( ! str_contains( $registration, $slug ) ) {
		fail_publisher_final_inner( 'registration-slug', 'Publisher inner registration lost a required final pattern slug.', $registration_path, $slug, 'slug missing' );
	}
}

$manifest = publisher_inner_json( PUBLISHER_INNER_PRESET_DIR . '/preset.json' );
if (
	true !== ( $manifest['editorial_model']['source_links_require_real_references'] ?? null )
	|| false !== ( $manifest['editorial_model']['infer_author_expertise'] ?? null )
	|| false !== ( $manifest['editorial_model']['infer_sources'] ?? null )
	|| 'wordpress-user' !== ( $manifest['schema']['author_source'] ?? null )
	|| 'wordpress-post-dates' !== ( $manifest['schema']['dates_source'] ?? null )
) {
	fail_publisher_final_inner( 'semantic-regression', 'Publisher inner pages must preserve real-author/source/date authorities.', PUBLISHER_INNER_PRESET_DIR . '/preset.json', 'real references + no inference + WordPress author/dates', $manifest['editorial_model'] ?? null );
}

echo "Publisher final inner-page contract: OK\n";
