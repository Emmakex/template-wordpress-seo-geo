<?php
/**
 * Validate the final SaaS Home visual/product contract.
 */

declare(strict_types=1);

const SAAS_FINAL_THEME_DIR  = 'packages/seo-geo-theme';
const SAAS_FINAL_PRESET_DIR = 'presets/saas-digital-product';

/**
 * Fail with an actionable message.
 *
 * @param mixed $expected Expected value.
 * @param mixed $received Actual value.
 */
function fail_saas_final_home( string $code, string $message, string $path, mixed $expected, mixed $received ): never {
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
function saas_final_file( string $path ): string {
	if ( ! is_file( $path ) ) {
		fail_saas_final_home( 'missing-file', 'Required SaaS final Home file is missing.', $path, 'existing file', 'missing' );
	}

	$content = file_get_contents( $path );
	if ( false === $content || '' === trim( $content ) ) {
		fail_saas_final_home( 'empty-file', 'Required SaaS final Home file is empty.', $path, 'non-empty file', 'empty' );
	}

	return $content;
}

/**
 * Decode one required JSON object.
 *
 * @return array<string,mixed>
 */
function saas_final_json( string $path ): array {
	$raw = saas_final_file( $path );

	try {
		$value = json_decode( $raw, true, 512, JSON_THROW_ON_ERROR );
	} catch ( JsonException $exception ) {
		fail_saas_final_home( 'json-invalid', 'Required SaaS contract is invalid JSON.', $path, 'valid JSON object', $exception->getMessage() );
	}

	if ( ! is_array( $value ) ) {
		fail_saas_final_home( 'json-object', 'Required SaaS contract must decode to an object.', $path, 'JSON object', gettype( $value ) );
	}

	return $value;
}

$mockup_path = SAAS_FINAL_PRESET_DIR . '/mockup.json';
$mockup      = saas_final_json( $mockup_path );

$expected_contract = array(
	'preset'               => 'saas-digital-product',
	'target_state'         => '99-percent-finished-before-client-content',
	'design_rule'          => 'final-layout-first-then-content-hydration',
	'legacy_layout_policy' => 'never-a-design-source',
);
foreach ( $expected_contract as $key => $expected ) {
	if ( $expected !== ( $mockup[ $key ] ?? null ) ) {
		fail_saas_final_home( 'mockup-contract', 'SaaS final mockup product rule changed unexpectedly.', $mockup_path . '#' . $key, $expected, $mockup[ $key ] ?? null );
	}
}

$home = $mockup['pages']['home'] ?? null;
if (
	! is_array( $home )
	|| true !== ( $home['required'] ?? null )
	|| 'saas-home-final-v1' !== ( $home['composition'] ?? null )
	|| 'candidate' !== ( $home['status'] ?? null )
	|| false !== ( $home['owns_h1'] ?? null )
) {
	fail_saas_final_home( 'home-contract', 'SaaS Home candidate contract is incomplete.', $mockup_path . '#pages.home', 'required final composition with template-owned H1', $home );
}

if ( false !== ( $mockup['visual_runtime']['required_frontend_javascript'] ?? null ) ) {
	fail_saas_final_home( 'javascript-policy', 'SaaS final Home must not require project JavaScript.', $mockup_path . '#visual_runtime.required_frontend_javascript', false, $mockup['visual_runtime']['required_frontend_javascript'] ?? null );
}

if ( true !== ( $mockup['acceptance']['performance_budgets_must_not_be_relaxed'] ?? null ) ) {
	fail_saas_final_home( 'budget-policy', 'SaaS final Home must preserve existing performance budgets.', $mockup_path . '#acceptance.performance_budgets_must_not_be_relaxed', true, $mockup['acceptance']['performance_budgets_must_not_be_relaxed'] ?? null );
}

$copy_path = SAAS_FINAL_PRESET_DIR . '/mockup-copy.json';
$copy_raw  = saas_final_file( $copy_path );
$copy_doc  = saas_final_json( $copy_path );

if ( 'placeholder' !== ( $copy_doc['provenance'] ?? null ) || false !== ( $copy_doc['publication_ready'] ?? null ) ) {
	fail_saas_final_home(
		'copy-provenance',
		'SaaS provisional mockup copy must remain explicitly placeholder and non-publishable.',
		$copy_path,
		array( 'provenance' => 'placeholder', 'publication_ready' => false ),
		array( 'provenance' => $copy_doc['provenance'] ?? null, 'publication_ready' => $copy_doc['publication_ready'] ?? null )
	);
}

$copy_es = $copy_doc['es_ES'] ?? null;
$copy_en = $copy_doc['en_US'] ?? null;
if ( ! is_array( $copy_es ) || ! is_array( $copy_en ) ) {
	fail_saas_final_home( 'copy-locales', 'SaaS provisional mockup copy requires complete ES and EN locale maps.', $copy_path, 'es_ES + en_US objects', array_keys( $copy_doc ) );
}

$keys_es = array_keys( $copy_es );
$keys_en = array_keys( $copy_en );
sort( $keys_es );
sort( $keys_en );
if ( $keys_es !== $keys_en || count( $keys_en ) < 40 ) {
	fail_saas_final_home( 'copy-parity', 'SaaS provisional copy locales must expose the same complete slot contract.', $copy_path, 'matching ES/EN keys with at least 40 slots', array( 'es' => count( $keys_es ), 'en' => count( $keys_en ) ) );
}

foreach ( array( 'kicker', 'lead', 'primary', 'secondary', 'preview_note', 'value_title', 'integration_empty', 'plans_title', 'faq_title', 'cta_title', 'cta_button' ) as $required_key ) {
	foreach ( array( 'es_ES' => $copy_es, 'en_US' => $copy_en ) as $locale => $localized_copy ) {
		$value = $localized_copy[ $required_key ] ?? null;
		if ( ! is_string( $value ) || '' === trim( $value ) ) {
			fail_saas_final_home( 'copy-required', 'SaaS provisional copy lost a required hydration slot.', $copy_path . '#' . $locale . '.' . $required_key, 'non-empty string', $value );
		}
	}
}

if ( 1 === preg_match( '/https?:\/\//i', $copy_raw, $match ) ) {
	fail_saas_final_home( 'copy-remote-url', 'Provisional SaaS copy must not smuggle remote evidence or dependencies into the mockup.', $copy_path, 'no remote URL', $match[0] );
}

if ( 1 === preg_match( '/(?:€|\$|£)\s*[0-9]|[0-9]\s*(?:€|\$|£)/', $copy_raw, $match ) ) {
	fail_saas_final_home( 'copy-invented-pricing', 'SaaS provisional copy must not contain numeric pricing before verified commercial hydration.', $copy_path, 'no numeric currency values', $match[0] );
}

$pattern_path = SAAS_FINAL_THEME_DIR . '/preset-patterns/saas-home-final.php';
$pattern      = saas_final_file( $pattern_path );

if ( str_contains( $pattern, '"level":1' ) || 1 === preg_match( '/<h1\b/i', $pattern ) ) {
	fail_saas_final_home( 'duplicate-h1', 'SaaS final Home pattern must leave the page H1 to front-page.html.', $pattern_path, 'no H1', 'H1 found' );
}

$required_pattern_fragments = array(
	'mockup-copy.json',
	'wp_json_file_decode',
	'seo-geo-saas-hero',
	'seo-geo-saas-product-frame',
	'seo-geo-bento',
	'seo-geo-process-sequence',
	'seo-geo-saas-proof-placeholder',
	'seo-geo-saas-use-cases',
	'seo-geo-saas-faq',
	'seo-geo-saas-final-cta',
	'seo-geo-placeholder--copy',
	'seo-geo-placeholder--media',
	'esc_html(',
);
foreach ( $required_pattern_fragments as $fragment ) {
	if ( ! str_contains( $pattern, $fragment ) ) {
		fail_saas_final_home( 'pattern-section', 'SaaS final Home lost a required visual or safety contract.', $pattern_path, $fragment, 'fragment missing' );
	}
}

if ( substr_count( $pattern, 'seo-geo-placeholder--copy' ) < 12 ) {
	fail_saas_final_home( 'placeholder-provenance', 'Provisional SaaS copy must remain explicitly marked for hydration/publication safety.', $pattern_path, 'at least 12 placeholder copy markers', substr_count( $pattern, 'seo-geo-placeholder--copy' ) );
}

if ( 1 === preg_match( '/https?:\/\/|<!--\s*wp:html\b|<\s*script\b|<\s*style\b|application\/ld\+json|schema\.org/i', $pattern, $match ) ) {
	fail_saas_final_home( 'unsafe-pattern', 'SaaS final Home must stay local, native and non-authoritative for Schema.', $pattern_path, 'no remote/html/script/style/schema payload', $match[0] );
}

$design_css_path = SAAS_FINAL_THEME_DIR . '/assets/css/design-system.css';
$saas_css_path   = SAAS_FINAL_THEME_DIR . '/assets/css/presets/saas-digital-product.css';
$design_css      = saas_final_file( $design_css_path );
$saas_css        = saas_final_file( $saas_css_path );

foreach ( array( $design_css_path => $design_css, $saas_css_path => $saas_css ) as $path => $css ) {
	if ( 1 === preg_match( '/@import\b|url\(\s*["\']?https?:\/\//i', $css, $match ) ) {
		fail_saas_final_home( 'remote-css', 'SaaS visual system must not add remote CSS/font/media dependencies.', $path, 'no @import or remote url()', $match[0] );
	}
}

$functions_path = SAAS_FINAL_THEME_DIR . '/functions.php';
$functions      = saas_final_file( $functions_path );
foreach (
	array(
		"\$design_system_presets = array( 'saas-digital-product' );",
		'/assets/css/design-system.css',
		'corporate-v2.css',
	) as $fragment
) {
	if ( ! str_contains( $functions, $fragment ) ) {
		fail_saas_final_home( 'style-runtime', 'Theme style runtime lost SaaS opt-in or Corporate isolation.', $functions_path, $fragment, 'fragment missing' );
	}
}

$registry_path = SAAS_FINAL_THEME_DIR . '/inc/presets.php';
$registry      = saas_final_file( $registry_path );
foreach ( array( "'seo-geo-theme/saas-home-final'", "'saas-home-final.php'", "'saas-digital-product' === \$preset_id" ) as $fragment ) {
	if ( ! str_contains( $registry, $fragment ) ) {
		fail_saas_final_home( 'pattern-registry', 'SaaS final Home is not registered as a preset-owned mockup.', $registry_path, $fragment, 'fragment missing' );
	}
}

echo "SaaS final Home contract: OK\n";
