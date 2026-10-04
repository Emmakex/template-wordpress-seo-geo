<?php
/**
 * Validate the Phase 7A Corporate preset contract.
 */

declare(strict_types=1);

const CORPORATE_PRESET_DIR = 'presets/corporate';
const CORPORATE_THEME_DIR  = 'packages/seo-geo-theme';

/**
 * Emit one structured actionable failure and stop.
 *
 * @param mixed $received Actual value.
 */
function fail_corporate_preset( string $code, string $message, string $file_line, string $expected, mixed $received ): never {
	$pipeline    = getenv( 'GITHUB_WORKFLOW' ) ?: 'foundation';
	$run_id      = getenv( 'GITHUB_RUN_ID' ) ?: 'local';
	$run_attempt = getenv( 'GITHUB_RUN_ATTEMPT' ) ?: '1';
	$job         = getenv( 'GITHUB_JOB' ) ?: 'foundation';
	$signature   = substr( hash( 'sha256', $code . ':' . $file_line . ':' . $message ), 0, 12 );

	echo json_encode(
		array(
			'schema_version'    => 1,
			'pipeline'          => $pipeline,
			'run_id'            => $run_id,
			'run_attempt'       => $run_attempt,
			'job'               => $job,
			'step'              => 'corporate-preset-contract',
			'command'           => 'php scripts/ci/validate-corporate-preset.php',
			'exit_code'         => 1,
			'primary_error'     => $message,
			'file_line'         => $file_line,
			'expected'          => $expected,
			'received'          => is_scalar( $received ) || null === $received ? $received : json_encode( $received, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ),
			'error_signature'   => $signature,
			'root_cause_status' => 'unknown',
		),
		JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
	) . PHP_EOL;

	exit( 1 );
}

/**
 * Decode a required JSON document.
 *
 * @return array<string, mixed>
 */
function corporate_json( string $path ): array {
	if ( ! is_file( $path ) ) {
		fail_corporate_preset( 'missing-json', 'Corporate preset document is missing.', $path, 'file exists', 'missing' );
	}

	try {
		$value = json_decode( (string) file_get_contents( $path ), true, 512, JSON_THROW_ON_ERROR );
	} catch ( JsonException $exception ) {
		fail_corporate_preset( 'invalid-json', 'Corporate preset document is not valid JSON.', $path, 'valid JSON object', $exception->getMessage() );
	}

	if ( ! is_array( $value ) ) {
		fail_corporate_preset( 'json-object', 'Corporate preset document must decode to an object.', $path, 'JSON object', gettype( $value ) );
	}

	return $value;
}

/**
 * Extract project pattern slugs from the neutral base theme.
 *
 * @return array<string, true>
 */
function corporate_base_pattern_slugs(): array {
	$files = glob( CORPORATE_THEME_DIR . '/patterns/*.php' );
	if ( false === $files ) {
		return array();
	}

	$slugs = array();
	foreach ( $files as $path ) {
		$content = (string) file_get_contents( $path );
		if ( 1 === preg_match( '/^\s*\*\s*Slug:\s*(.+?)\s*$/mi', $content, $matches ) ) {
			$slugs[ trim( $matches[1] ) ] = true;
		}
	}

	return $slugs;
}

/**
 * Return theme token slugs used by pattern content.
 *
 * @return array<string, array<string, true>>
 */
function corporate_theme_tokens(): array {
	$theme = corporate_json( CORPORATE_THEME_DIR . '/theme.json' );

	$paths = array(
		'color'         => array( 'settings', 'color', 'palette' ),
		'spacing'       => array( 'settings', 'spacing', 'spacingSizes' ),
		'font-size'     => array( 'settings', 'typography', 'fontSizes' ),
		'font-family'   => array( 'settings', 'typography', 'fontFamilies' ),
		'border-radius' => array( 'settings', 'border', 'radiusSizes' ),
	);

	$result = array();
	foreach ( $paths as $type => $path ) {
		$value = $theme;
		foreach ( $path as $key ) {
			$value = is_array( $value ) && array_key_exists( $key, $value ) ? $value[ $key ] : array();
		}

		$result[ $type ] = array();
		if ( is_array( $value ) ) {
			foreach ( $value as $preset ) {
				if ( is_array( $preset ) && isset( $preset['slug'] ) && is_string( $preset['slug'] ) ) {
					$result[ $type ][ $preset['slug'] ] = true;
				}
			}
		}
	}

	return $result;
}

$manifest = corporate_json( CORPORATE_PRESET_DIR . '/preset.json' );
$content  = corporate_json( CORPORATE_PRESET_DIR . '/content-map.json' );
$patterns = corporate_json( CORPORATE_PRESET_DIR . '/patterns.json' );

if ( 1 !== ( $manifest['schema_version'] ?? null ) || 'corporate' !== ( $manifest['id'] ?? null ) || 'corporate' !== ( $manifest['site_type'] ?? null ) ) {
	fail_corporate_preset( 'manifest-identity', 'Corporate manifest identity/version is invalid.', CORPORATE_PRESET_DIR . '/preset.json', 'schema_version=1, id=corporate, site_type=corporate', $manifest );
}

$baseline_locales = $manifest['multilingual']['baseline_locales'] ?? null;
if ( ! is_array( $baseline_locales ) || array_values( $baseline_locales ) !== array( 'en_US', 'es_ES' ) ) {
	fail_corporate_preset( 'locales', 'Corporate preset must ship EN/ES together.', CORPORATE_PRESET_DIR . '/preset.json', '["en_US","es_ES"]', $baseline_locales );
}

$schema = $manifest['schema'] ?? null;
if ( ! is_array( $schema ) || 'organization' !== ( $schema['site_identity'] ?? null ) || true !== ( $schema['requires_confirmation'] ?? null ) ) {
	fail_corporate_preset( 'schema-confirmation', 'Corporate Organization identity must require explicit confirmation.', CORPORATE_PRESET_DIR . '/preset.json', 'organization + requires_confirmation=true', $schema );
}

$replatforming = $manifest['replatforming'] ?? null;
if (
	! is_array( $replatforming )
	|| 'preserve-content-seo-links-rebuild-presentation' !== ( $replatforming['strategy'] ?? null )
	|| false !== ( $replatforming['legacy_visual_parity'] ?? null )
	|| true !== ( $replatforming['native_composition_required'] ?? null )
	|| true !== ( $replatforming['source_content_may_be_restructured'] ?? null )
	|| true !== ( $replatforming['source_facts_must_not_be_fabricated'] ?? null )
) {
	fail_corporate_preset(
		'replatforming',
		'Corporate must preserve the search/content asset while replacing legacy presentation with native composition.',
		CORPORATE_PRESET_DIR . '/preset.json#replatforming',
		'preserve content/SEO/links + no legacy visual parity + native composition',
		$replatforming
	);
}

$content_semantics = $manifest['content_semantics'] ?? null;
$required_semantic_flags = array(
	'answer_first_where_useful',
	'entity_clarity_required',
	'evidence_for_claims_required',
	'crawlable_internal_links_required',
	'faq_only_when_user_intent',
	'author_provenance_when_applicable',
	'keyword_stuffing_forbidden',
	'doorway_content_forbidden',
	'fabricated_proof_forbidden',
);
if ( ! is_array( $content_semantics ) ) {
	fail_corporate_preset( 'content-semantics', 'Corporate preset must declare its SEO/GEO content semantics.', CORPORATE_PRESET_DIR . '/preset.json', 'content semantics object', $content_semantics );
}
foreach ( $required_semantic_flags as $semantic_flag ) {
	if ( true !== ( $content_semantics[ $semantic_flag ] ?? null ) ) {
		fail_corporate_preset( 'content-semantic-flag', 'Corporate preset weakened a required SEO/GEO content semantic.', CORPORATE_PRESET_DIR . '/preset.json#content_semantics.' . $semantic_flag, true, $content_semantics[ $semantic_flag ] ?? null );
	}
}

$required_templates = $manifest['required_templates'] ?? null;
if ( ! is_array( $required_templates ) ) {
	fail_corporate_preset( 'templates', 'Corporate preset required_templates is missing.', CORPORATE_PRESET_DIR . '/preset.json', 'template slug list', $required_templates );
}
foreach ( $required_templates as $template ) {
	if ( ! is_string( $template ) || ! is_file( CORPORATE_THEME_DIR . '/templates/' . $template . '.html' ) ) {
		fail_corporate_preset( 'template-missing', 'Corporate preset references a missing base template.', CORPORATE_PRESET_DIR . '/preset.json', 'existing theme template', $template );
	}
}

$base_slugs = corporate_base_pattern_slugs();
$recommended = $manifest['recommended_base_patterns'] ?? null;
if ( ! is_array( $recommended ) ) {
	fail_corporate_preset( 'recommended-patterns', 'Corporate recommended base pattern list is missing.', CORPORATE_PRESET_DIR . '/preset.json', 'base pattern slug list', $recommended );
}
foreach ( $recommended as $slug ) {
	if ( ! is_string( $slug ) || ! isset( $base_slugs[ $slug ] ) ) {
		fail_corporate_preset( 'base-pattern', 'Corporate preset references a base pattern outside the neutral contract.', CORPORATE_PRESET_DIR . '/preset.json', 'registered base theme pattern slug', $slug );
	}
}

$expected_preset_slugs = array(
	'seo-geo-theme/corporate-case-study',
	'seo-geo-theme/corporate-stats',
	'seo-geo-theme/corporate-testimonials',
	'seo-geo-theme/corporate-native-hero',
	'seo-geo-theme/corporate-native-capabilities',
	'seo-geo-theme/corporate-native-proof',
	'seo-geo-theme/corporate-native-process',
	'seo-geo-theme/corporate-native-insights',
);
$declared_preset_slugs = $manifest['preset_patterns'] ?? null;
if ( ! is_array( $declared_preset_slugs ) || array_values( $declared_preset_slugs ) !== $expected_preset_slugs ) {
	fail_corporate_preset( 'preset-pattern-list', 'Corporate manifest preset pattern list is not canonical.', CORPORATE_PRESET_DIR . '/preset.json', json_encode( $expected_preset_slugs ), $declared_preset_slugs );
}

$category = $patterns['category'] ?? null;
if ( ! is_array( $category ) || 'seo-geo-corporate' !== ( $category['slug'] ?? null ) ) {
	fail_corporate_preset( 'pattern-category', 'Corporate pattern category is invalid.', CORPORATE_PRESET_DIR . '/patterns.json', 'seo-geo-corporate', $category );
}
$category_labels = $category['labels'] ?? null;
if ( ! is_array( $category_labels ) || ! isset( $category_labels['en_US'], $category_labels['es_ES'] ) ) {
	fail_corporate_preset( 'pattern-category-i18n', 'Corporate pattern category must ship EN/ES labels.', CORPORATE_PRESET_DIR . '/patterns.json', 'en_US + es_ES labels', $category_labels );
}

$pattern_rows = $patterns['patterns'] ?? null;
if ( ! is_array( $pattern_rows ) || 8 !== count( $pattern_rows ) ) {
	fail_corporate_preset( 'pattern-count', 'Corporate Native must ship the complete reusable pattern set.', CORPORATE_PRESET_DIR . '/patterns.json', 8, is_array( $pattern_rows ) ? count( $pattern_rows ) : $pattern_rows );
}

$theme_tokens = corporate_theme_tokens();
$seen = array();
foreach ( $pattern_rows as $index => $pattern ) {
	$file_line = CORPORATE_PRESET_DIR . '/patterns.json#patterns[' . $index . ']';
	if ( ! is_array( $pattern ) || ! isset( $pattern['slug'] ) || ! is_string( $pattern['slug'] ) ) {
		fail_corporate_preset( 'pattern-slug', 'Corporate pattern slug is missing.', $file_line, 'namespaced slug', $pattern );
	}

	$slug = $pattern['slug'];
	if ( ! in_array( $slug, $expected_preset_slugs, true ) || isset( $seen[ $slug ] ) ) {
		fail_corporate_preset( 'pattern-slug-set', 'Corporate pattern slug is unexpected or duplicated.', $file_line, json_encode( $expected_preset_slugs ), $slug );
	}
	$seen[ $slug ] = true;

	foreach ( array( 'en_US', 'es_ES' ) as $locale ) {
		$localized = $pattern['locales'][ $locale ] ?? null;
		if ( ! is_array( $localized ) ) {
			fail_corporate_preset( 'pattern-locale', 'Corporate pattern is missing a required locale.', $file_line, $locale, $localized );
		}

		foreach ( array( 'title', 'description', 'content' ) as $field ) {
			if ( ! isset( $localized[ $field ] ) || ! is_string( $localized[ $field ] ) || '' === trim( $localized[ $field ] ) ) {
				fail_corporate_preset( 'pattern-field', 'Corporate localized pattern field is empty.', $file_line, $locale . '.' . $field, $localized[ $field ] ?? null );
			}
		}

		$markup = $localized['content'];
		if ( 1 === preg_match( '/<\s*(script|style)\b|<!--\s*wp:html\b|https?:\/\/|application\/ld\+json|schema\.org/i', $markup, $matches ) ) {
			fail_corporate_preset( 'unsafe-pattern-content', 'Corporate pattern contains forbidden embedded/remote/Schema content.', $file_line, 'native blocks without scripts/styles/remote URLs/Schema', $matches[0] );
		}
		if ( str_contains( $markup, '"level":1' ) || 1 === preg_match( '/<h1\b/i', $markup ) ) {
			fail_corporate_preset( 'pattern-h1', 'Corporate reusable patterns must not own page-level H1.', $file_line, 'no H1', 'H1 found' );
		}
		if ( 1 === preg_match( '/#[0-9A-Fa-f]{3,8}\b/', $markup, $matches ) ) {
			fail_corporate_preset( 'pattern-color', 'Corporate pattern must use semantic theme colors.', $file_line, 'no raw hex color', $matches[0] );
		}
		if ( 1 === preg_match( '/\b[0-9]+(?:\.[0-9]+)?(?:px|rem|em|vw|vh)\b/i', $markup, $matches ) ) {
			fail_corporate_preset( 'pattern-size', 'Corporate pattern must use design-token sizes.', $file_line, 'no raw CSS size literal', $matches[0] );
		}

		preg_match_all( '/var:preset\|([a-z-]+)\|([a-z0-9-]+)/i', $markup, $preset_matches, PREG_SET_ORDER );
		foreach ( $preset_matches as $preset_match ) {
			$type = strtolower( $preset_match[1] );
			$token = strtolower( $preset_match[2] );
			if ( ! isset( $theme_tokens[ $type ][ $token ] ) ) {
				fail_corporate_preset( 'pattern-token', 'Corporate pattern references an unknown theme token.', $file_line, 'token defined in theme.json', $preset_match[0] );
			}
		}
	}
}

$locales = $content['locales'] ?? null;
if ( ! is_array( $locales ) || ! isset( $locales['en_US'], $locales['es_ES'] ) ) {
	fail_corporate_preset( 'content-locales', 'Corporate content map must ship EN/ES together.', CORPORATE_PRESET_DIR . '/content-map.json', 'en_US + es_ES', $locales );
}

$required_native_home_patterns = array(
	'seo-geo-theme/corporate-native-hero',
	'seo-geo-theme/corporate-native-capabilities',
	'seo-geo-theme/corporate-native-proof',
	'seo-geo-theme/corporate-case-study',
	'seo-geo-theme/corporate-native-process',
	'seo-geo-theme/corporate-native-insights',
	'seo-geo-theme/cta',
);

$required_native_home_remap_slots = array(
	array(
		'section'       => 'value-proposition',
		'after_pattern' => 'seo-geo-theme/corporate-native-hero',
	),
	array(
		'section'       => 'service-overview',
		'after_pattern' => 'seo-geo-theme/corporate-native-capabilities',
	),
	array(
		'section'       => 'organization-context',
		'after_pattern' => 'seo-geo-theme/corporate-case-study',
	),
	array(
		'section'       => 'verified-proof',
		'after_pattern' => 'seo-geo-theme/corporate-native-proof',
	),
	array(
		'section'       => 'primary-cta',
		'after_pattern' => 'seo-geo-theme/cta',
	),
);

$page_keys_by_locale = array();
$allowed_patterns = array_fill_keys( array_merge( array_keys( $base_slugs ), $expected_preset_slugs ), true );
foreach ( array( 'en_US', 'es_ES' ) as $locale ) {
	$pages = $locales[ $locale ]['pages'] ?? null;
	if ( ! is_array( $pages ) || 8 !== count( $pages ) ) {
		fail_corporate_preset( 'page-count', 'Corporate content map must ship eight singleton pages per locale.', CORPORATE_PRESET_DIR . '/content-map.json', 8, is_array( $pages ) ? count( $pages ) : $pages );
	}

	$keys = array();
	$slugs = array();
	$roles = array();
	foreach ( $pages as $page ) {
		if ( ! is_array( $page ) ) {
			fail_corporate_preset( 'page-row', 'Corporate page row is invalid.', CORPORATE_PRESET_DIR . '/content-map.json', 'page object', $page );
		}
		foreach ( array( 'key', 'title', 'slug', 'role', 'template' ) as $field ) {
			if ( ! isset( $page[ $field ] ) || ! is_string( $page[ $field ] ) || '' === trim( $page[ $field ] ) ) {
				fail_corporate_preset( 'page-field', 'Corporate page field is missing.', CORPORATE_PRESET_DIR . '/content-map.json', $locale . '.' . $field, $page[ $field ] ?? null );
			}
		}
		if ( isset( $keys[ $page['key'] ] ) || isset( $slugs[ $page['slug'] ] ) ) {
			fail_corporate_preset( 'page-duplicate', 'Corporate page keys/slugs must be unique per locale.', CORPORATE_PRESET_DIR . '/content-map.json', 'unique page key and slug', $page );
		}
		$keys[ $page['key'] ] = true;
		$slugs[ $page['slug'] ] = true;
		$roles[ $page['role'] ] = ( $roles[ $page['role'] ] ?? 0 ) + 1;

		$content_contract = $page['content_contract'] ?? null;
		if (
			! is_array( $content_contract )
			|| ! is_string( $content_contract['primary_intent'] ?? null )
			|| ! is_array( $content_contract['required_sections'] ?? null )
			|| array() === $content_contract['required_sections']
			|| ! is_array( $content_contract['internal_link_targets'] ?? null )
			|| ! is_array( $content_contract['retrieval_units'] ?? null )
			|| array() === $content_contract['retrieval_units']
		) {
			fail_corporate_preset( 'page-content-contract', 'Corporate page is missing a machine-readable SEO/GEO content contract.', CORPORATE_PRESET_DIR . '/content-map.json#' . $locale . '.' . $page['key'], 'intent + sections + internal links + retrieval units', $content_contract );
		}

		if ( 'home' === $page['key'] && array_values( $page['patterns'] ?? array() ) !== $required_native_home_patterns ) {
			fail_corporate_preset(
				'native-home-composition',
				'Corporate Home must use the canonical native composition rather than legacy visual parity.',
				CORPORATE_PRESET_DIR . '/content-map.json#' . $locale . '.home.patterns',
				json_encode( $required_native_home_patterns ),
				$page['patterns'] ?? null
			);
		}

		if (
			'home' === $page['key']
			&& array_values( is_array( $content_contract['remap_slots'] ?? null ) ? $content_contract['remap_slots'] : array() ) !== $required_native_home_remap_slots
		) {
			fail_corporate_preset(
				'native-home-remap-slots',
				'Corporate Home reviewed remap slots must remain explicit, semantic and bound to the canonical native composition.',
				CORPORATE_PRESET_DIR . '/content-map.json#' . $locale . '.home.content_contract.remap_slots',
				json_encode( $required_native_home_remap_slots ),
				$content_contract['remap_slots'] ?? null
			);
		}

		$page_patterns = $page['patterns'] ?? array();
		if ( ! is_array( $page_patterns ) ) {
			fail_corporate_preset( 'page-patterns', 'Corporate page pattern composition must be a list.', CORPORATE_PRESET_DIR . '/content-map.json', 'pattern list', $page_patterns );
		}
		foreach ( $page_patterns as $slug ) {
			if ( ! is_string( $slug ) || ! isset( $allowed_patterns[ $slug ] ) ) {
				fail_corporate_preset( 'page-pattern-reference', 'Corporate content map references an unknown pattern.', CORPORATE_PRESET_DIR . '/content-map.json', 'base or Corporate preset pattern', $slug );
			}
		}
	}

	if ( 1 !== ( $roles['front-page'] ?? 0 ) || 1 !== ( $roles['posts-page'] ?? 0 ) ) {
		fail_corporate_preset( 'page-authority', 'Corporate content map must define exactly one front page and one posts page per locale.', CORPORATE_PRESET_DIR . '/content-map.json', 'one front-page + one posts-page', $roles );
	}

	$page_keys_by_locale[ $locale ] = array_keys( $keys );
	sort( $page_keys_by_locale[ $locale ] );
}

if ( $page_keys_by_locale['en_US'] !== $page_keys_by_locale['es_ES'] ) {
	fail_corporate_preset( 'page-key-parity', 'Corporate EN/ES page maps must describe the same logical pages.', CORPORATE_PRESET_DIR . '/content-map.json', $page_keys_by_locale['en_US'], $page_keys_by_locale['es_ES'] );
}

foreach ( array( 'en_US', 'es_ES' ) as $locale ) {
	$repeatables = $locales[ $locale ]['repeatable'] ?? null;
	if ( ! is_array( $repeatables ) || 2 !== count( $repeatables ) ) {
		fail_corporate_preset( 'repeatable-count', 'Corporate must define service-detail and case-study repeatable content contracts.', CORPORATE_PRESET_DIR . '/content-map.json', 2, is_array( $repeatables ) ? count( $repeatables ) : $repeatables );
	}

	foreach ( $repeatables as $repeatable ) {
		$contract = is_array( $repeatable ) ? ( $repeatable['content_contract'] ?? null ) : null;
		if (
			! is_array( $contract )
			|| ! is_string( $contract['primary_intent'] ?? null )
			|| ! is_array( $contract['required_sections'] ?? null )
			|| array() === $contract['required_sections']
			|| ! is_array( $contract['internal_link_targets'] ?? null )
			|| ! is_array( $contract['retrieval_units'] ?? null )
			|| array() === $contract['retrieval_units']
		) {
			fail_corporate_preset( 'repeatable-content-contract', 'Corporate repeatable content is missing its SEO/GEO contract.', CORPORATE_PRESET_DIR . '/content-map.json#' . $locale, 'intent + sections + internal links + retrieval units', $contract );
		}
	}
}

$corporate_css_path = CORPORATE_THEME_DIR . '/assets/css/presets/corporate.css';
$corporate_css      = is_file( $corporate_css_path ) ? (string) file_get_contents( $corporate_css_path ) : '';
if ( '' === $corporate_css || str_contains( $corporate_css, 'migrated-divi' ) ) {
	fail_corporate_preset(
		'legacy-visual-css',
		'Corporate Native stylesheet must not preserve legacy-builder presentation selectors.',
		$corporate_css_path,
		'modern native preset CSS without migrated-divi selectors',
		'' === $corporate_css ? 'missing stylesheet' : 'legacy selector found'
	);
}
foreach (
	array(
		'seo-geo-corporate-native-hero',
		'seo-geo-corporate-native-capabilities',
		'seo-geo-corporate-native-proof',
		'seo-geo-corporate-native-process',
		'seo-geo-corporate-native-insights',
	) as $native_css_hook
) {
	if ( ! str_contains( $corporate_css, $native_css_hook ) ) {
		fail_corporate_preset(
			'native-css-hook',
			'Corporate Native stylesheet is missing a required component hook.',
			$corporate_css_path,
			$native_css_hook,
			'missing'
		);
	}
}

printf(
	"Corporate Native contract OK: replatforming semantics, front-page/native components, 8 bilingual preset patterns and EN/ES page/repeatable content contracts pass.\n"
);
