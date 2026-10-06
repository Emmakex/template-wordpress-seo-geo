<?php
/**
 * Validate the final SaaS inner-page visual/product contract.
 */

declare(strict_types=1);

const SAAS_INNER_THEME_DIR  = 'packages/seo-geo-theme';
const SAAS_INNER_PRESET_DIR = 'presets/saas-digital-product';

/**
 * Fail with one actionable diagnostic.
 *
 * @param mixed $expected Expected value.
 * @param mixed $received Actual value.
 */
function fail_saas_final_inner( string $code, string $message, string $path, mixed $expected, mixed $received ): never {
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
 * Read one required file.
 */
function saas_inner_file( string $path ): string {
	if ( ! is_file( $path ) ) {
		fail_saas_final_inner( 'missing-file', 'Required SaaS inner-page file is missing.', $path, 'existing file', 'missing' );
	}

	$content = file_get_contents( $path );
	if ( false === $content || '' === trim( $content ) ) {
		fail_saas_final_inner( 'empty-file', 'Required SaaS inner-page file is empty.', $path, 'non-empty file', 'empty' );
	}

	return $content;
}

/**
 * Decode one required JSON object.
 *
 * @return array<string,mixed>
 */
function saas_inner_json( string $path ): array {
	try {
		$value = json_decode( saas_inner_file( $path ), true, 512, JSON_THROW_ON_ERROR );
	} catch ( JsonException $exception ) {
		fail_saas_final_inner( 'json-invalid', 'SaaS inner-page contract is invalid JSON.', $path, 'valid JSON object', $exception->getMessage() );
	}

	if ( ! is_array( $value ) ) {
		fail_saas_final_inner( 'json-object', 'SaaS inner-page contract must decode to an object.', $path, 'JSON object', gettype( $value ) );
	}

	return $value;
}

$page_keys = array( 'product', 'features', 'solutions', 'integrations', 'pricing', 'resources', 'contact-demo' );

$mockup_path = SAAS_INNER_PRESET_DIR . '/mockup.json';
$mockup      = saas_inner_json( $mockup_path );
$stage       = $mockup['current_stage'] ?? null;

if ( ! is_string( $stage ) || ! in_array( $stage, array( 'inner-pages-candidate', 'system-surfaces-candidate' ), true ) ) {
	fail_saas_final_inner( 'stage-contract', 'SaaS inner-page contract must remain valid while the preset advances through its final system-surface stage.', $mockup_path . '#current_stage', 'inner-pages-candidate or system-surfaces-candidate', $stage );
}

foreach ( $page_keys as $page_key ) {
	$page = $mockup['pages'][ $page_key ] ?? null;
	if (
		! is_array( $page )
		|| true !== ( $page['required'] ?? null )
		|| 'saas-inner-final-v1' !== ( $page['composition'] ?? null )
		|| 'candidate' !== ( $page['status'] ?? null )
		|| false !== ( $page['owns_h1'] ?? null )
	) {
		fail_saas_final_inner( 'page-contract', 'SaaS inner page lost its required final-composition contract.', $mockup_path . '#pages.' . $page_key, 'required candidate using saas-inner-final-v1 with template-owned H1', $page );
	}
}

if ( 'inner-pages-candidate' === $stage ) {
	foreach ( array( 'single', 'archive', '404' ) as $system_page ) {
		$status = $mockup['pages'][ $system_page ]['status'] ?? null;
		if ( 'next-microphase' !== $status ) {
			fail_saas_final_inner( 'system-surface-honesty', 'SaaS must not claim system surfaces complete before their dedicated microphase.', $mockup_path . '#pages.' . $system_page, 'next-microphase', $status );
		}
	}
}

$copy_path = SAAS_INNER_PRESET_DIR . '/mockup-inner-copy.json';
$copy_raw  = saas_inner_file( $copy_path );
$copy_doc  = saas_inner_json( $copy_path );

if (
	'placeholder' !== ( $copy_doc['provenance'] ?? null )
	|| false !== ( $copy_doc['publication_ready'] ?? null )
	|| 'final-layout-first-then-content-hydration' !== ( $copy_doc['page_contract'] ?? null )
) {
	fail_saas_final_inner( 'copy-provenance', 'SaaS inner copy must stay explicitly provisional and non-publishable.', $copy_path, 'placeholder + publication_ready=false + hydration contract', array( $copy_doc['provenance'] ?? null, $copy_doc['publication_ready'] ?? null, $copy_doc['page_contract'] ?? null ) );
}

$copy_es = $copy_doc['es_ES'] ?? null;
$copy_en = $copy_doc['en_US'] ?? null;
if ( ! is_array( $copy_es ) || ! is_array( $copy_en ) ) {
	fail_saas_final_inner( 'copy-locales', 'SaaS inner copy requires ES and EN page maps.', $copy_path, 'es_ES + en_US', array_keys( $copy_doc ) );
}

$keys_es = array_keys( $copy_es );
$keys_en = array_keys( $copy_en );
sort( $keys_es );
sort( $keys_en );
$expected_keys = $page_keys;
sort( $expected_keys );
if ( $keys_es !== $expected_keys || $keys_en !== $expected_keys ) {
	fail_saas_final_inner( 'copy-page-parity', 'SaaS inner locales must expose the same seven final pages.', $copy_path, $expected_keys, array( 'es' => $keys_es, 'en' => $keys_en ) );
}

foreach ( array( 'es_ES' => $copy_es, 'en_US' => $copy_en ) as $locale => $localized_pages ) {
	foreach ( $page_keys as $page_key ) {
		$page = $localized_pages[ $page_key ] ?? null;
		if ( ! is_array( $page ) || ! is_string( $page['eyebrow'] ?? null ) || ! is_string( $page['lead'] ?? null ) ) {
			fail_saas_final_inner( 'copy-page-shape', 'SaaS inner page requires eyebrow and lead copy.', $copy_path . '#' . $locale . '.' . $page_key, 'eyebrow + lead strings', $page );
		}

		$sections = $page['sections'] ?? null;
		if ( ! is_array( $sections ) || 2 !== count( $sections ) ) {
			fail_saas_final_inner( 'copy-sections', 'Each SaaS inner page must ship two finished content sections.', $copy_path . '#' . $locale . '.' . $page_key . '.sections', 2, is_array( $sections ) ? count( $sections ) : gettype( $sections ) );
		}

		foreach ( $sections as $index => $section ) {
			$cards = is_array( $section ) ? ( $section['cards'] ?? null ) : null;
			if ( ! is_array( $section ) || ! is_string( $section['label'] ?? null ) || ! is_string( $section['title'] ?? null ) || ! is_string( $section['body'] ?? null ) || ! is_array( $cards ) || 3 !== count( $cards ) ) {
				fail_saas_final_inner( 'copy-section-shape', 'Each SaaS inner section requires label, title, body and three cards.', $copy_path . '#' . $locale . '.' . $page_key . '.sections.' . $index, 'label + title + body + 3 cards', $section );
			}

			foreach ( $cards as $card ) {
				if ( ! is_array( $card ) || ! is_string( $card['title'] ?? null ) || ! is_string( $card['body'] ?? null ) ) {
					fail_saas_final_inner( 'copy-card-shape', 'Each SaaS inner card requires title and body.', $copy_path . '#' . $locale . '.' . $page_key, 'card title + body', $card );
				}
			}
		}

		$cta = $page['cta'] ?? null;
		if ( ! is_array( $cta ) || ! is_string( $cta['title'] ?? null ) || ! is_string( $cta['body'] ?? null ) || ! is_string( $cta['button'] ?? null ) ) {
			fail_saas_final_inner( 'copy-cta-shape', 'Each SaaS inner page requires a complete CTA slot.', $copy_path . '#' . $locale . '.' . $page_key . '.cta', 'title + body + button', $cta );
		}
	}
}

if ( 1 === preg_match( '/https?:\/\//i', $copy_raw, $match ) ) {
	fail_saas_final_inner( 'copy-remote-url', 'SaaS inner provisional copy must not contain remote evidence or dependencies.', $copy_path, 'no remote URL', $match[0] );
}

if ( 1 === preg_match( '/(?:€|\$|£)\s*[0-9]|[0-9]\s*(?:€|\$|£)/', $copy_raw, $match ) ) {
	fail_saas_final_inner( 'copy-invented-pricing', 'SaaS pricing structure must not contain numeric pricing before authoritative hydration.', $copy_path, 'no numeric currency values', $match[0] );
}

$pattern_path = SAAS_INNER_THEME_DIR . '/preset-patterns/saas-inner-final.php';
$pattern      = saas_inner_file( $pattern_path );

if ( str_contains( $pattern, '"level":1' ) || 1 === preg_match( '/<h1\b/i', $pattern ) ) {
	fail_saas_final_inner( 'duplicate-h1', 'SaaS inner patterns must leave the document H1 to the page template.', $pattern_path, 'no H1', 'H1 found' );
}

foreach ( array( 'mockup-inner-copy.json', 'seo-geo-saas-inner-hero', 'seo-geo-saas-product-frame', 'seo-geo-saas-use-cases', 'seo-geo-saas-final-cta', 'seo-geo-placeholder--copy', 'seo-geo-placeholder--media', 'esc_html(' ) as $fragment ) {
	if ( ! str_contains( $pattern, $fragment ) ) {
		fail_saas_final_inner( 'pattern-contract', 'SaaS inner renderer lost a required visual or hydration contract.', $pattern_path, $fragment, 'fragment missing' );
	}
}

if ( 1 === preg_match( '/https?:\/\/|<!--\s*wp:html\b|<\s*script\b|<\s*style\b|application\/ld\+json|schema\.org/i', $pattern, $match ) ) {
	fail_saas_final_inner( 'unsafe-pattern', 'SaaS inner renderer must stay local, native and non-authoritative for Schema.', $pattern_path, 'no remote/html/script/style/schema payload', $match[0] );
}

$registry_path = SAAS_INNER_THEME_DIR . '/inc/presets.php';
$registry      = saas_inner_file( $registry_path );
foreach ( $page_keys as $page_key ) {
	$slug = "'seo-geo-theme/saas-" . $page_key . "-final'";
	if ( ! str_contains( $registry, $slug ) ) {
		fail_saas_final_inner( 'pattern-registry', 'SaaS final inner page is missing from the preset-owned registry.', $registry_path, $slug, 'fragment missing' );
	}
}

if ( ! str_contains( $registry, "'saas-inner-final.php'" ) ) {
	fail_saas_final_inner( 'renderer-registry', 'SaaS final inner renderer is not referenced by the registry.', $registry_path, "'saas-inner-final.php'", 'fragment missing' );
}

echo "SaaS final inner-page contract: OK\n";
