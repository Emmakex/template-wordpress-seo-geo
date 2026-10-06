<?php
/**
 * Validate the final Publisher / Editorial Home visual/product contract.
 */

declare(strict_types=1);

const PUBLISHER_FINAL_THEME_DIR  = 'packages/seo-geo-theme';
const PUBLISHER_FINAL_PRESET_DIR = 'presets/publisher';

/**
 * Fail with one actionable diagnostic.
 *
 * @param mixed $expected Expected value.
 * @param mixed $received Actual value.
 */
function fail_publisher_final_home( string $code, string $message, string $path, mixed $expected, mixed $received ): never {
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
function publisher_final_file( string $path ): string {
	if ( ! is_file( $path ) ) {
		fail_publisher_final_home( 'missing-file', 'Required Publisher final Home file is missing.', $path, 'existing file', 'missing' );
	}

	$content = file_get_contents( $path );
	if ( false === $content || '' === trim( $content ) ) {
		fail_publisher_final_home( 'empty-file', 'Required Publisher final Home file is empty.', $path, 'non-empty file', 'empty' );
	}

	return $content;
}

/**
 * Decode one required JSON object.
 *
 * @return array<string,mixed>
 */
function publisher_final_json( string $path ): array {
	try {
		$value = json_decode( publisher_final_file( $path ), true, 512, JSON_THROW_ON_ERROR );
	} catch ( JsonException $exception ) {
		fail_publisher_final_home( 'json-invalid', 'Publisher final Home contract is invalid JSON.', $path, 'valid JSON object', $exception->getMessage() );
	}

	if ( ! is_array( $value ) ) {
		fail_publisher_final_home( 'json-object', 'Publisher final Home contract must decode to an object.', $path, 'JSON object', gettype( $value ) );
	}

	return $value;
}

$mockup_path = PUBLISHER_FINAL_PRESET_DIR . '/mockup.json';
$mockup      = publisher_final_json( $mockup_path );

$expected_contract = array(
	'preset'               => 'publisher',
	'target_state'         => '99-percent-finished-before-client-content',
	'current_stage'        => 'home-candidate',
	'design_rule'          => 'final-layout-first-then-content-hydration',
	'legacy_layout_policy' => 'never-a-design-source',
);
foreach ( $expected_contract as $key => $expected ) {
	if ( $expected !== ( $mockup[ $key ] ?? null ) ) {
		fail_publisher_final_home( 'mockup-contract', 'Publisher final product rule changed unexpectedly.', $mockup_path . '#' . $key, $expected, $mockup[ $key ] ?? null );
	}
}

$home = $mockup['pages']['home'] ?? null;
if (
	! is_array( $home )
	|| true !== ( $home['required'] ?? null )
	|| 'publisher-home-final-v1' !== ( $home['composition'] ?? null )
	|| 'candidate' !== ( $home['status'] ?? null )
	|| false !== ( $home['owns_h1'] ?? null )
) {
	fail_publisher_final_home( 'home-contract', 'Publisher Home candidate contract is incomplete.', $mockup_path . '#pages.home', 'required publisher-home-final-v1 with template-owned H1', $home );
}

foreach ( array( 'articles', 'topics', 'authors', 'about', 'editorial-policy', 'contact', 'single', 'archive', '404' ) as $pending_page ) {
	$status = $mockup['pages'][ $pending_page ]['status'] ?? null;
	if ( 'next-microphase' !== $status ) {
		fail_publisher_final_home( 'roadmap-honesty', 'DS-5A must not claim later Publisher surfaces complete.', $mockup_path . '#pages.' . $pending_page, 'next-microphase', $status );
	}
}

if (
	false !== ( $mockup['visual_runtime']['required_frontend_javascript'] ?? null )
	|| false !== ( $mockup['visual_runtime']['remote_fonts'] ?? null )
	|| false !== ( $mockup['visual_runtime']['remote_visual_dependencies'] ?? null )
) {
	fail_publisher_final_home( 'visual-runtime', 'Publisher final Home must remain zero-JS and free of remote visual/font dependencies.', $mockup_path . '#visual_runtime', 'all remote/JS requirements false', $mockup['visual_runtime'] ?? null );
}

if (
	true !== ( $mockup['placeholder_policy']['blocks_publication_until_resolved'] ?? null )
	|| true !== ( $mockup['acceptance']['performance_budgets_must_not_be_relaxed'] ?? null )
	|| true !== ( $mockup['acceptance']['evidence_must_not_be_fabricated'] ?? null )
	|| true !== ( $mockup['acceptance']['real_author_source_required'] ?? null )
	|| true !== ( $mockup['acceptance']['real_reference_source_required'] ?? null )
	|| true !== ( $mockup['acceptance']['real_post_dates_required'] ?? null )
) {
	fail_publisher_final_home( 'editorial-safety', 'Publisher final Home lost publication, evidence or authority safeguards.', $mockup_path, 'all editorial safeguards true', $mockup['acceptance'] ?? null );
}

$copy_path = PUBLISHER_FINAL_PRESET_DIR . '/mockup-copy.json';
$copy_raw  = publisher_final_file( $copy_path );
$copy_doc  = publisher_final_json( $copy_path );

if (
	'publisher' !== ( $copy_doc['preset'] ?? null )
	|| 'placeholder' !== ( $copy_doc['provenance'] ?? null )
	|| false !== ( $copy_doc['publication_ready'] ?? null )
	|| 'final-layout-first-then-content-hydration' !== ( $copy_doc['page_contract'] ?? null )
) {
	fail_publisher_final_home( 'copy-provenance', 'Publisher provisional copy must remain explicit placeholder data and non-publishable.', $copy_path, 'publisher + placeholder + publication_ready=false', $copy_doc );
}

$copy_en = $copy_doc['en_US'] ?? null;
$copy_es = $copy_doc['es_ES'] ?? null;
if ( ! is_array( $copy_en ) || ! is_array( $copy_es ) ) {
	fail_publisher_final_home( 'copy-locales', 'Publisher provisional copy requires complete EN and ES locale maps.', $copy_path, 'en_US + es_ES objects', array_keys( $copy_doc ) );
}

$keys_en = array_keys( $copy_en );
$keys_es = array_keys( $copy_es );
sort( $keys_en );
sort( $keys_es );
if ( $keys_en !== $keys_es || count( $keys_en ) < 45 ) {
	fail_publisher_final_home( 'copy-parity', 'Publisher provisional copy locales must expose the same complete hydration-slot contract.', $copy_path, 'matching EN/ES keys with at least 45 slots', array( 'en' => count( $keys_en ), 'es' => count( $keys_es ) ) );
}

foreach ( array( 'lead_title', 'story_1_title', 'topics_title', 'standards_title', 'resources_title', 'authors_title', 'follow_title' ) as $required_key ) {
	foreach ( array( 'en_US' => $copy_en, 'es_ES' => $copy_es ) as $locale => $localized_copy ) {
		$value = $localized_copy[ $required_key ] ?? null;
		if ( ! is_string( $value ) || '' === trim( $value ) ) {
			fail_publisher_final_home( 'copy-required', 'Publisher provisional copy lost a required hydration slot.', $copy_path . '#' . $locale . '.' . $required_key, 'non-empty string', $value );
		}
	}
}

if ( 1 === preg_match( '/https?:\/\//i', $copy_raw, $match ) ) {
	fail_publisher_final_home( 'copy-remote-evidence', 'Publisher provisional copy must not contain remote source/evidence URLs.', $copy_path, 'no remote URL', $match[0] );
}

$pattern_path = PUBLISHER_FINAL_THEME_DIR . '/preset-patterns/publisher-home-final.php';
$pattern      = publisher_final_file( $pattern_path );

if ( str_contains( $pattern, '"level":1' ) || 1 === preg_match( '/<h1\b/i', $pattern ) ) {
	fail_publisher_final_home( 'duplicate-h1', 'Publisher final Home must leave the page H1 to front-page.html.', $pattern_path, 'no H1', 'H1 found' );
}

$required_pattern_fragments = array(
	'mockup-copy.json',
	'wp_json_file_decode',
	'seo-geo-publisher-hero',
	'seo-geo-publisher-lead-story',
	'seo-geo-editorial-grid',
	'seo-geo-publisher-topics',
	'seo-geo-publisher-standards',
	'seo-geo-publisher-resource-list',
	'seo-geo-publisher-authors',
	'seo-geo-publisher-follow',
	'seo-geo-placeholder--copy',
	'seo-geo-placeholder--media',
	'esc_html(',
);
foreach ( $required_pattern_fragments as $fragment ) {
	if ( ! str_contains( $pattern, $fragment ) ) {
		fail_publisher_final_home( 'pattern-section', 'Publisher final Home lost a required editorial or safety surface.', $pattern_path, $fragment, 'fragment missing' );
	}
}

if ( substr_count( $pattern, 'seo-geo-placeholder--copy' ) < 24 || substr_count( $pattern, 'seo-geo-placeholder--media' ) < 3 ) {
	fail_publisher_final_home(
		'placeholder-provenance',
		'Publisher provisional content/media must stay visibly marked for hydration safety.',
		$pattern_path,
		'at least 24 copy and 3 media placeholder markers',
		array( 'copy' => substr_count( $pattern, 'seo-geo-placeholder--copy' ), 'media' => substr_count( $pattern, 'seo-geo-placeholder--media' ) )
	);
}

if ( 1 === preg_match( '/https?:\/\/|<!--\s*wp:html\b|<\s*script\b|<\s*style\b|application\/ld\+json|schema\.org/i', $pattern, $match ) ) {
	fail_publisher_final_home( 'unsafe-pattern', 'Publisher final Home must stay local, native and non-authoritative for Schema.', $pattern_path, 'no remote/html/script/style/schema payload', $match[0] );
}

$publisher_css_path = PUBLISHER_FINAL_THEME_DIR . '/assets/css/presets/publisher.css';
$design_css_path    = PUBLISHER_FINAL_THEME_DIR . '/assets/css/design-system.css';
$publisher_css      = publisher_final_file( $publisher_css_path );
$design_css         = publisher_final_file( $design_css_path );
foreach ( array( $publisher_css_path => $publisher_css, $design_css_path => $design_css ) as $path => $css ) {
	if ( 1 === preg_match( '/@import\b|url\(\s*["\']?https?:\/\//i', $css, $match ) ) {
		fail_publisher_final_home( 'remote-css', 'Publisher visual system must not add remote CSS/font/media dependencies.', $path, 'no @import or remote url()', $match[0] );
	}
}

foreach ( array( 'seo-geo-publisher-lead-story', 'seo-geo-publisher-story-card', 'seo-geo-publisher-standards', 'prefers-reduced-motion', '@media (max-width: 900px)' ) as $fragment ) {
	if ( ! str_contains( $publisher_css, $fragment ) ) {
		fail_publisher_final_home( 'visual-contract', 'Publisher CSS lost a required editorial/responsive contract.', $publisher_css_path, $fragment, 'fragment missing' );
	}
}

$functions_path = PUBLISHER_FINAL_THEME_DIR . '/functions.php';
$functions      = publisher_final_file( $functions_path );
foreach ( array( '/inc/Publisher/FinalHomePattern.php', "'publisher' === \$preset_id", '/assets/css/design-system.css' ) as $fragment ) {
	if ( ! str_contains( $functions, $fragment ) ) {
		fail_publisher_final_home( 'style-runtime', 'Theme runtime lost Publisher registration or Design System opt-in.', $functions_path, $fragment, 'fragment missing' );
	}
}

$registration_path = PUBLISHER_FINAL_THEME_DIR . '/inc/Publisher/FinalHomePattern.php';
$registration      = publisher_final_file( $registration_path );
foreach ( array( "'publisher' !== seo_geo_theme_active_preset_id()", "'seo-geo-theme/publisher-home-final'", "array( 'seo-geo-publisher' )", 'publisher-home-final.php' ) as $fragment ) {
	if ( ! str_contains( $registration, $fragment ) ) {
		fail_publisher_final_home( 'pattern-registration', 'Publisher final Home registration lost active-preset isolation or category ownership.', $registration_path, $fragment, 'fragment missing' );
	}
}

$manifest = publisher_final_json( PUBLISHER_FINAL_PRESET_DIR . '/preset.json' );
if (
	true !== ( $manifest['editorial_model']['source_links_require_real_references'] ?? null )
	|| false !== ( $manifest['editorial_model']['infer_author_expertise'] ?? null )
	|| false !== ( $manifest['editorial_model']['infer_sources'] ?? null )
	|| 'wordpress-user' !== ( $manifest['schema']['author_source'] ?? null )
	|| 'wordpress-post-dates' !== ( $manifest['schema']['dates_source'] ?? null )
) {
	fail_publisher_final_home( 'semantic-regression', 'Publisher final visual layer must preserve the existing real-author/source/date authorities.', PUBLISHER_FINAL_PRESET_DIR . '/preset.json', 'real references + no inference + WordPress author/dates', $manifest['editorial_model'] ?? null );
}

echo "Publisher final Home contract: OK\n";
