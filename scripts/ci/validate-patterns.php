<?php
/**
 * Validate the Phase 2B reusable-pattern contract.
 */

declare(strict_types=1);

const PATTERN_DIR = 'packages/seo-geo-theme/patterns';
const THEME_JSON  = 'packages/seo-geo-theme/theme.json';

/**
 * Emit one structured actionable failure and stop.
 *
 * @param mixed $received Actual value.
 */
function fail_pattern_contract( string $code, string $message, string $file_line, string $expected, mixed $received ): never {
	$pipeline    = getenv( 'GITHUB_WORKFLOW' ) ?: 'patterns';
	$run_id      = getenv( 'GITHUB_RUN_ID' ) ?: 'local';
	$run_attempt = getenv( 'GITHUB_RUN_ATTEMPT' ) ?: '1';
	$job         = getenv( 'GITHUB_JOB' ) ?: 'patterns';
	$signature   = substr( hash( 'sha256', $code . ':' . $file_line . ':' . $message ), 0, 12 );

	$payload = array(
		'schema_version'    => 1,
		'pipeline'          => $pipeline,
		'run_id'            => $run_id,
		'run_attempt'       => $run_attempt,
		'job'               => $job,
		'step'              => 'pattern-contract',
		'command'           => 'php scripts/ci/validate-patterns.php',
		'exit_code'         => 1,
		'primary_error'     => $message,
		'file_line'         => $file_line,
		'expected'          => $expected,
		'received'          => is_scalar( $received ) || null === $received ? $received : json_encode( $received, JSON_UNESCAPED_SLASHES ),
		'error_signature'   => $signature,
		'root_cause_status' => 'unknown',
	);

	echo json_encode( $payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . PHP_EOL;
	exit( 1 );
}

/**
 * Read a pattern file header value.
 */
function pattern_header( string $content, string $name ): ?string {
	$matched = preg_match( '/^\s*\*\s*' . preg_quote( $name, '/' ) . ':\s*(.+?)\s*$/mi', $content, $matches );
	return 1 === $matched ? trim( $matches[1] ) : null;
}

/**
 * Return a preset slug set from theme.json.
 *
 * @param array<string, mixed> $theme Parsed theme.json.
 * @param list<string>         $path  Nested path.
 * @return array<string, true>
 */
function preset_slugs( array $theme, array $path ): array {
	$value = $theme;
	foreach ( $path as $key ) {
		if ( ! is_array( $value ) || ! array_key_exists( $key, $value ) ) {
			return array();
		}
		$value = $value[ $key ];
	}

	if ( ! is_array( $value ) ) {
		return array();
	}

	$slugs = array();
	foreach ( $value as $preset ) {
		if ( is_array( $preset ) && isset( $preset['slug'] ) && is_string( $preset['slug'] ) ) {
			$slugs[ $preset['slug'] ] = true;
		}
	}

	return $slugs;
}

$expected = array(
	'author-profile.php'    => array( 'seo-geo-theme/author-profile', 'about, text' ),
	'contact.php'           => array( 'seo-geo-theme/contact', 'text' ),
	'cta.php'               => array( 'seo-geo-theme/cta', 'call-to-action' ),
	'faq.php'               => array( 'seo-geo-theme/faq', 'text' ),
	'hero.php'              => array( 'seo-geo-theme/hero', 'banner, featured' ),
	'services-features.php' => array( 'seo-geo-theme/services-features', 'featured' ),
	'trust-proof.php'       => array( 'seo-geo-theme/trust-proof', 'featured' ),
);

if ( ! is_dir( PATTERN_DIR ) ) {
	fail_pattern_contract( 'missing-pattern-dir', 'Theme patterns directory is missing.', PATTERN_DIR, 'patterns directory exists', 'missing' );
}

$files = glob( PATTERN_DIR . '/*.php' );
if ( false === $files ) {
	$files = array();
}
$actual_names = array_map( 'basename', $files );
sort( $actual_names );
$expected_names = array_keys( $expected );
sort( $expected_names );

if ( $actual_names !== $expected_names ) {
	fail_pattern_contract( 'pattern-set', 'Core pattern file set does not match the Phase 2B contract.', PATTERN_DIR, json_encode( $expected_names ), $actual_names );
}

try {
	/** @var array<string, mixed> $theme */
	$theme = json_decode( (string) file_get_contents( THEME_JSON ), true, 512, JSON_THROW_ON_ERROR );
} catch ( JsonException $exception ) {
	fail_pattern_contract( 'theme-json', 'theme.json could not be parsed while validating pattern presets.', THEME_JSON, 'valid theme.json', $exception->getMessage() );
}

$allowed_presets = array(
	'color'         => preset_slugs( $theme, array( 'settings', 'color', 'palette' ) ),
	'spacing'       => preset_slugs( $theme, array( 'settings', 'spacing', 'spacingSizes' ) ),
	'font-size'     => preset_slugs( $theme, array( 'settings', 'typography', 'fontSizes' ) ),
	'font-family'   => preset_slugs( $theme, array( 'settings', 'typography', 'fontFamilies' ) ),
	'border-radius' => preset_slugs( $theme, array( 'settings', 'border', 'radiusSizes' ) ),
);

$seen_slugs = array();
foreach ( $expected as $filename => array( $expected_slug, $expected_categories ) ) {
	$path    = PATTERN_DIR . '/' . $filename;
	$content = (string) file_get_contents( $path );

	$title       = pattern_header( $content, 'Title' );
	$slug        = pattern_header( $content, 'Slug' );
	$categories  = pattern_header( $content, 'Categories' );
	$description = pattern_header( $content, 'Description' );
	$viewport    = pattern_header( $content, 'Viewport Width' );

	if ( null === $title || '' === $title ) {
		fail_pattern_contract( 'missing-title', 'Pattern Title header is required.', $path, 'non-empty Title header', $title );
	}
	if ( $slug !== $expected_slug ) {
		fail_pattern_contract( 'slug', 'Pattern Slug header does not match the file contract.', $path, $expected_slug, $slug );
	}
	if ( $categories !== $expected_categories ) {
		fail_pattern_contract( 'categories', 'Pattern Categories header does not match the file contract.', $path, $expected_categories, $categories );
	}
	if ( null === $description || '' === $description ) {
		fail_pattern_contract( 'description', 'Pattern Description header is required.', $path, 'non-empty Description header', $description );
	}
	if ( null === $viewport || 1 !== preg_match( '/^[0-9]+$/', $viewport ) ) {
		fail_pattern_contract( 'viewport', 'Pattern Viewport Width must be an integer header value.', $path, 'integer Viewport Width', $viewport );
	}
	if ( isset( $seen_slugs[ $expected_slug ] ) ) {
		fail_pattern_contract( 'duplicate-slug', 'Pattern slug is duplicated.', $path, 'unique pattern slug', $expected_slug );
	}
	$seen_slugs[ $expected_slug ] = true;

	if ( ! str_contains( $content, "'seo-geo-theme'" ) || ! str_contains( $content, 'esc_html_e(' ) ) {
		fail_pattern_contract( 'i18n', 'Visible project-owned pattern copy must use escaped theme translations.', $path, "esc_html_e(..., 'seo-geo-theme')", 'translation call missing' );
	}

	if ( 1 === preg_match( '/<\s*(script|style)\b/i', $content, $matches ) ) {
		fail_pattern_contract( 'embedded-assets', 'Patterns must not embed custom script or style elements.', $path, 'no script/style elements', $matches[0] );
	}
	if ( 1 === preg_match( '/<!--\s*wp:html\b/i', $content ) ) {
		fail_pattern_contract( 'html-block', 'Core patterns must use native structured blocks instead of Custom HTML.', $path, 'native WordPress blocks only', 'wp:html block found' );
	}
	if ( 1 === preg_match( '/https?:\/\//i', $content, $matches ) ) {
		fail_pattern_contract( 'remote-url', 'Core patterns must not ship remote frontend URLs or dependencies.', $path, 'no http/https URL', $matches[0] );
	}
	if ( 1 === preg_match( '/#[0-9A-Fa-f]{3,8}\b/', $content, $matches ) ) {
		fail_pattern_contract( 'raw-color', 'Core patterns must consume semantic color presets instead of raw hex values.', $path, 'theme color preset', $matches[0] );
	}
	if ( 1 === preg_match( '/\b[0-9]+(?:\.[0-9]+)?(?:px|rem|em|vw|vh)\b/i', $content, $matches ) ) {
		fail_pattern_contract( 'raw-size', 'Core patterns must consume design tokens instead of raw CSS size values.', $path, 'theme preset variable', $matches[0] );
	}
	if ( 1 === preg_match( '/<h1\b/i', $content ) || str_contains( $content, '"level":1' ) ) {
		fail_pattern_contract( 'h1-ownership', 'Reusable patterns must not claim page-level H1 ownership.', $path, 'no H1 in reusable pattern', 'H1 found' );
	}
	if ( 1 === preg_match( '/application\/ld\+json|schema\.org/i', $content, $matches ) ) {
		fail_pattern_contract( 'schema-ownership', 'Theme patterns must not emit Schema markup; Core owns structured data.', $path, 'no Schema output', $matches[0] );
	}

	preg_match_all( '/var:preset\|([a-z-]+)\|([a-z0-9-]+)/i', $content, $preset_matches, PREG_SET_ORDER );
	foreach ( $preset_matches as $preset_match ) {
		$type = strtolower( $preset_match[1] );
		$slug_value = strtolower( $preset_match[2] );
		if ( ! isset( $allowed_presets[ $type ][ $slug_value ] ) ) {
			fail_pattern_contract( 'unknown-preset', 'Pattern references a preset that is not defined by theme.json.', $path, 'defined semantic preset', $preset_match[0] );
		}
	}
}

printf(
	"Pattern contract OK: %d core patterns; headers/slugs/i18n/native blocks/design tokens/H1/Schema/dependency rules pass.\n",
	count( $expected )
);
