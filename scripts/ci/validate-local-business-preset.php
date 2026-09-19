<?php
/**
 * Validate the Phase 7B Local Business preset contract.
 */

declare(strict_types=1);

const LOCAL_BUSINESS_PRESET_DIR = 'presets/local-business';
const LOCAL_BUSINESS_THEME_DIR  = 'packages/seo-geo-theme';

/**
 * Emit one structured actionable failure and stop.
 *
 * @param mixed $received Actual value.
 */
function fail_local_business_preset( string $code, string $message, string $file_line, string $expected, mixed $received ): never {
	$signature = substr( hash( 'sha256', $code . ':' . $file_line . ':' . $message ), 0, 12 );

	echo json_encode(
		array(
			'schema_version'    => 1,
			'pipeline'          => getenv( 'GITHUB_WORKFLOW' ) ?: 'foundation',
			'run_id'            => getenv( 'GITHUB_RUN_ID' ) ?: 'local',
			'run_attempt'       => getenv( 'GITHUB_RUN_ATTEMPT' ) ?: '1',
			'job'               => getenv( 'GITHUB_JOB' ) ?: 'foundation',
			'step'              => 'local-business-preset-contract',
			'command'           => 'php scripts/ci/validate-local-business-preset.php',
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
function local_business_json( string $path ): array {
	if ( ! is_file( $path ) ) {
		fail_local_business_preset( 'missing-json', 'Local Business preset document is missing.', $path, 'file exists', 'missing' );
	}

	try {
		$value = json_decode( (string) file_get_contents( $path ), true, 512, JSON_THROW_ON_ERROR );
	} catch ( JsonException $exception ) {
		fail_local_business_preset( 'invalid-json', 'Local Business preset document is invalid JSON.', $path, 'valid JSON object', $exception->getMessage() );
	}

	if ( ! is_array( $value ) ) {
		fail_local_business_preset( 'json-object', 'Local Business preset document must decode to an object.', $path, 'JSON object', gettype( $value ) );
	}

	return $value;
}

/**
 * Extract neutral theme pattern slugs.
 *
 * @return array<string, true>
 */
function local_business_base_patterns(): array {
	$files = glob( LOCAL_BUSINESS_THEME_DIR . '/patterns/*.php' );
	$slugs = array();

	if ( false === $files ) {
		return $slugs;
	}

	foreach ( $files as $path ) {
		$source = (string) file_get_contents( $path );
		if ( 1 === preg_match( '/^\s*\*\s*Slug:\s*(.+?)\s*$/mi', $source, $matches ) ) {
			$slugs[ trim( $matches[1] ) ] = true;
		}
	}

	return $slugs;
}

/**
 * Return defined theme tokens by preset type.
 *
 * @return array<string, array<string, true>>
 */
function local_business_theme_tokens(): array {
	$theme = local_business_json( LOCAL_BUSINESS_THEME_DIR . '/theme.json' );
	$paths = array(
		'color'       => array( 'settings', 'color', 'palette' ),
		'spacing'     => array( 'settings', 'spacing', 'spacingSizes' ),
		'font-size'   => array( 'settings', 'typography', 'fontSizes' ),
		'font-family' => array( 'settings', 'typography', 'fontFamilies' ),
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

$manifest = local_business_json( LOCAL_BUSINESS_PRESET_DIR . '/preset.json' );
$content  = local_business_json( LOCAL_BUSINESS_PRESET_DIR . '/content-map.json' );
$patterns = local_business_json( LOCAL_BUSINESS_PRESET_DIR . '/patterns.json' );

if ( 1 !== ( $manifest['schema_version'] ?? null ) || 'local-business' !== ( $manifest['id'] ?? null ) || 'local-business' !== ( $manifest['site_type'] ?? null ) ) {
	fail_local_business_preset( 'manifest-identity', 'Local Business manifest identity/version is invalid.', LOCAL_BUSINESS_PRESET_DIR . '/preset.json', 'schema_version=1, id/site_type=local-business', $manifest );
}

$baseline_locales = $manifest['multilingual']['baseline_locales'] ?? null;
if ( ! is_array( $baseline_locales ) || array_values( $baseline_locales ) !== array( 'en_US', 'es_ES' ) ) {
	fail_local_business_preset( 'locales', 'Local Business preset must ship EN/ES together.', LOCAL_BUSINESS_PRESET_DIR . '/preset.json', '["en_US","es_ES"]', $baseline_locales );
}

$schema = $manifest['schema'] ?? null;
if (
	! is_array( $schema )
	|| 'local_business' !== ( $schema['site_identity'] ?? null )
	|| true !== ( $schema['requires_confirmation'] ?? null )
	|| true !== ( $schema['visible_fact_gates_required'] ?? null )
	|| true !== ( $schema['physical_address_required_for_entity'] ?? null )
) {
	fail_local_business_preset( 'schema-contract', 'Local Business Schema must remain explicit and visible-fact gated.', LOCAL_BUSINESS_PRESET_DIR . '/preset.json', 'local_business + explicit confirmation + visible facts + physical address', $schema );
}

$location_model = $manifest['location_model'] ?? null;
if (
	! is_array( $location_model )
	|| true !== ( $location_model['supports_single_location'] ?? null )
	|| true !== ( $location_model['supports_multiple_locations'] ?? null )
	|| false !== ( $location_model['auto_generate_city_pages'] ?? null )
	|| true !== ( $location_model['doorway_pages_forbidden'] ?? null )
	|| true !== ( $location_model['service_area_pages_require_real_local_value'] ?? null )
	|| 'locations' !== ( $location_model['index_key'] ?? null )
	|| 'location-detail' !== ( $location_model['repeatable_key'] ?? null )
) {
	fail_local_business_preset( 'location-model', 'Local Business location model must support real multi-location content without auto-generated city pages.', LOCAL_BUSINESS_PRESET_DIR . '/preset.json', 'single+multi location, no auto city pages, doorway forbidden', $location_model );
}

$disabled = $manifest['explicitly_disabled'] ?? null;
if ( ! is_array( $disabled ) || ! in_array( 'automatic city landing page generation', $disabled, true ) || ! in_array( 'review or rating fabrication', $disabled, true ) ) {
	fail_local_business_preset( 'disabled-features', 'Local Business preset must explicitly disable city-page automation and fabricated reviews.', LOCAL_BUSINESS_PRESET_DIR . '/preset.json', 'anti-doorway + no fabricated reviews', $disabled );
}

$required_templates = $manifest['required_templates'] ?? null;
if ( ! is_array( $required_templates ) ) {
	fail_local_business_preset( 'templates', 'Local Business required_templates is missing.', LOCAL_BUSINESS_PRESET_DIR . '/preset.json', 'template list', $required_templates );
}
foreach ( $required_templates as $template ) {
	if ( ! is_string( $template ) || ! is_file( LOCAL_BUSINESS_THEME_DIR . '/templates/' . $template . '.html' ) ) {
		fail_local_business_preset( 'template-missing', 'Local Business references a missing base template.', LOCAL_BUSINESS_PRESET_DIR . '/preset.json', 'existing theme template', $template );
	}
}

$base_patterns = local_business_base_patterns();
$recommended   = $manifest['recommended_base_patterns'] ?? null;
if ( ! is_array( $recommended ) ) {
	fail_local_business_preset( 'recommended-patterns', 'Local Business base pattern list is missing.', LOCAL_BUSINESS_PRESET_DIR . '/preset.json', 'base pattern list', $recommended );
}
foreach ( $recommended as $slug ) {
	if ( ! is_string( $slug ) || ! isset( $base_patterns[ $slug ] ) ) {
		fail_local_business_preset( 'base-pattern', 'Local Business references a pattern outside the neutral theme contract.', LOCAL_BUSINESS_PRESET_DIR . '/preset.json', 'known base pattern', $slug );
	}
}

$expected_preset_slugs = array(
	'seo-geo-theme/local-business-nap',
	'seo-geo-theme/local-business-service-area',
	'seo-geo-theme/local-business-location',
);
$declared_preset_slugs = $manifest['preset_patterns'] ?? null;
if ( ! is_array( $declared_preset_slugs ) || array_values( $declared_preset_slugs ) !== $expected_preset_slugs ) {
	fail_local_business_preset( 'preset-pattern-list', 'Local Business manifest pattern list is not canonical.', LOCAL_BUSINESS_PRESET_DIR . '/preset.json', json_encode( $expected_preset_slugs ), $declared_preset_slugs );
}

$category = $patterns['category'] ?? null;
if ( ! is_array( $category ) || 'seo-geo-local-business' !== ( $category['slug'] ?? null ) ) {
	fail_local_business_preset( 'pattern-category', 'Local Business pattern category is invalid.', LOCAL_BUSINESS_PRESET_DIR . '/patterns.json', 'seo-geo-local-business', $category );
}
$labels = is_array( $category ) ? ( $category['labels'] ?? null ) : null;
if ( ! is_array( $labels ) || ! isset( $labels['en_US'], $labels['es_ES'] ) ) {
	fail_local_business_preset( 'pattern-category-i18n', 'Local Business category must ship EN/ES labels.', LOCAL_BUSINESS_PRESET_DIR . '/patterns.json', 'en_US + es_ES', $labels );
}

$pattern_rows = $patterns['patterns'] ?? null;
if ( ! is_array( $pattern_rows ) || 3 !== count( $pattern_rows ) ) {
	fail_local_business_preset( 'pattern-count', 'Local Business must ship exactly three preset-owned patterns in 7B.', LOCAL_BUSINESS_PRESET_DIR . '/patterns.json', 3, is_array( $pattern_rows ) ? count( $pattern_rows ) : $pattern_rows );
}

$theme_tokens = local_business_theme_tokens();
$seen         = array();
foreach ( $pattern_rows as $index => $pattern ) {
	$file_line = LOCAL_BUSINESS_PRESET_DIR . '/patterns.json#patterns[' . $index . ']';
	if ( ! is_array( $pattern ) || ! isset( $pattern['slug'] ) || ! is_string( $pattern['slug'] ) ) {
		fail_local_business_preset( 'pattern-slug', 'Local Business pattern slug is missing.', $file_line, 'namespaced slug', $pattern );
	}

	$slug = $pattern['slug'];
	if ( ! in_array( $slug, $expected_preset_slugs, true ) || isset( $seen[ $slug ] ) ) {
		fail_local_business_preset( 'pattern-slug-set', 'Local Business pattern slug is unexpected or duplicated.', $file_line, json_encode( $expected_preset_slugs ), $slug );
	}
	$seen[ $slug ] = true;

	foreach ( array( 'en_US', 'es_ES' ) as $locale ) {
		$localized = $pattern['locales'][ $locale ] ?? null;
		if ( ! is_array( $localized ) ) {
			fail_local_business_preset( 'pattern-locale', 'Local Business pattern is missing a required locale.', $file_line, $locale, $localized );
		}

		foreach ( array( 'title', 'description', 'content' ) as $field ) {
			if ( ! isset( $localized[ $field ] ) || ! is_string( $localized[ $field ] ) || '' === trim( $localized[ $field ] ) ) {
				fail_local_business_preset( 'pattern-field', 'Local Business localized pattern field is empty.', $file_line, $locale . '.' . $field, $localized[ $field ] ?? null );
			}
		}

		$markup = $localized['content'];
		if ( 1 === preg_match( '/<\s*(script|style)\b|<!--\s*wp:html\b|https?:\/\/|application\/ld\+json|schema\.org/i', $markup, $matches ) ) {
			fail_local_business_preset( 'unsafe-pattern-content', 'Local Business pattern contains forbidden embedded/remote/Schema content.', $file_line, 'native blocks without scripts/styles/remote URLs/Schema', $matches[0] );
		}
		if ( str_contains( $markup, '"level":1' ) || 1 === preg_match( '/<h1\b/i', $markup ) ) {
			fail_local_business_preset( 'pattern-h1', 'Local Business reusable patterns must not own the page H1.', $file_line, 'no H1', 'H1 found' );
		}
		if ( 1 === preg_match( '/#[0-9A-Fa-f]{3,8}\b/', $markup, $matches ) ) {
			fail_local_business_preset( 'pattern-color', 'Local Business pattern must use semantic theme colors.', $file_line, 'no raw hex color', $matches[0] );
		}
		if ( 1 === preg_match( '/\b[0-9]+(?:\.[0-9]+)?(?:px|rem|em|vw|vh)\b/i', $markup, $matches ) ) {
			fail_local_business_preset( 'pattern-size', 'Local Business pattern must use design-token sizes.', $file_line, 'no raw CSS size literal', $matches[0] );
		}

		preg_match_all( '/var:preset\|([a-z-]+)\|([a-z0-9-]+)/i', $markup, $token_matches, PREG_SET_ORDER );
		foreach ( $token_matches as $token_match ) {
			$type = strtolower( $token_match[1] );
			$name = strtolower( $token_match[2] );
			if ( ! isset( $theme_tokens[ $type ][ $name ] ) ) {
				fail_local_business_preset( 'pattern-token', 'Local Business pattern references an unknown theme token.', $file_line, 'theme.json token', $token_match[0] );
			}
		}
	}
}

$locales = $content['locales'] ?? null;
if ( ! is_array( $locales ) || ! isset( $locales['en_US'], $locales['es_ES'] ) ) {
	fail_local_business_preset( 'content-locales', 'Local Business content map must ship EN/ES together.', LOCAL_BUSINESS_PRESET_DIR . '/content-map.json', 'en_US + es_ES', $locales );
}

$allowed_patterns     = array_fill_keys( array_merge( array_keys( $base_patterns ), $expected_preset_slugs ), true );
$page_keys_by_locale  = array();
$repeatable_by_locale = array();
foreach ( array( 'en_US', 'es_ES' ) as $locale ) {
	$pages = $locales[ $locale ]['pages'] ?? null;
	if ( ! is_array( $pages ) || 8 !== count( $pages ) ) {
		fail_local_business_preset( 'page-count', 'Local Business must ship eight singleton pages per locale.', LOCAL_BUSINESS_PRESET_DIR . '/content-map.json', 8, is_array( $pages ) ? count( $pages ) : $pages );
	}

	$keys  = array();
	$slugs = array();
	$roles = array();
	foreach ( $pages as $page ) {
		if ( ! is_array( $page ) ) {
			fail_local_business_preset( 'page-row', 'Local Business page row is invalid.', LOCAL_BUSINESS_PRESET_DIR . '/content-map.json', 'page object', $page );
		}
		foreach ( array( 'key', 'title', 'slug', 'role', 'template' ) as $field ) {
			if ( ! isset( $page[ $field ] ) || ! is_string( $page[ $field ] ) || '' === trim( $page[ $field ] ) ) {
				fail_local_business_preset( 'page-field', 'Local Business page field is missing.', LOCAL_BUSINESS_PRESET_DIR . '/content-map.json', $locale . '.' . $field, $page[ $field ] ?? null );
			}
		}
		if ( isset( $keys[ $page['key'] ] ) || isset( $slugs[ $page['slug'] ] ) ) {
			fail_local_business_preset( 'page-duplicate', 'Local Business page keys/slugs must be unique per locale.', LOCAL_BUSINESS_PRESET_DIR . '/content-map.json', 'unique keys/slugs', $page );
		}
		$keys[ $page['key'] ] = true;
		$slugs[ $page['slug'] ] = true;
		$roles[ $page['role'] ] = ( $roles[ $page['role'] ] ?? 0 ) + 1;

		$page_patterns = $page['patterns'] ?? array();
		if ( ! is_array( $page_patterns ) ) {
			fail_local_business_preset( 'page-patterns', 'Local Business page composition must be a pattern list.', LOCAL_BUSINESS_PRESET_DIR . '/content-map.json', 'pattern list', $page_patterns );
		}
		foreach ( $page_patterns as $slug ) {
			if ( ! is_string( $slug ) || ! isset( $allowed_patterns[ $slug ] ) ) {
				fail_local_business_preset( 'page-pattern-reference', 'Local Business content map references an unknown pattern.', LOCAL_BUSINESS_PRESET_DIR . '/content-map.json', 'base or Local Business pattern', $slug );
			}
		}
	}

	if ( 1 !== ( $roles['front-page'] ?? 0 ) || 1 !== ( $roles['locations-index'] ?? 0 ) ) {
		fail_local_business_preset( 'page-authority', 'Local Business must define exactly one front page and one locations index per locale.', LOCAL_BUSINESS_PRESET_DIR . '/content-map.json', 'one front-page + one locations-index', $roles );
	}

	$page_keys_by_locale[ $locale ] = array_keys( $keys );
	sort( $page_keys_by_locale[ $locale ] );

	$repeatables = $locales[ $locale ]['repeatable'] ?? null;
	if ( ! is_array( $repeatables ) || 3 !== count( $repeatables ) ) {
		fail_local_business_preset( 'repeatable-count', 'Local Business must define service, location and service-area repeatables.', LOCAL_BUSINESS_PRESET_DIR . '/content-map.json', 3, is_array( $repeatables ) ? count( $repeatables ) : $repeatables );
	}

	$repeatable_keys = array();
	foreach ( $repeatables as $repeatable ) {
		if ( ! is_array( $repeatable ) || ! isset( $repeatable['key'] ) || ! is_string( $repeatable['key'] ) ) {
			fail_local_business_preset( 'repeatable-key', 'Local Business repeatable content key is missing.', LOCAL_BUSINESS_PRESET_DIR . '/content-map.json', 'repeatable key', $repeatable );
		}
		$repeatable_keys[] = $repeatable['key'];

		$requirements = $repeatable['content_requirements'] ?? null;
		if ( ! is_array( $requirements ) || count( $requirements ) < 4 ) {
			fail_local_business_preset( 'repeatable-value', 'Local Business repeatable pages must declare meaningful content requirements.', LOCAL_BUSINESS_PRESET_DIR . '/content-map.json', 'at least four real-value requirements', $requirements );
		}

		$repeatable_patterns = $repeatable['recommended_patterns'] ?? null;
		if ( ! is_array( $repeatable_patterns ) ) {
			fail_local_business_preset( 'repeatable-patterns', 'Local Business repeatable pattern guidance is missing.', LOCAL_BUSINESS_PRESET_DIR . '/content-map.json', 'pattern list', $repeatable_patterns );
		}
		foreach ( $repeatable_patterns as $slug ) {
			if ( ! is_string( $slug ) || ! isset( $allowed_patterns[ $slug ] ) ) {
				fail_local_business_preset( 'repeatable-pattern-reference', 'Local Business repeatable references an unknown pattern.', LOCAL_BUSINESS_PRESET_DIR . '/content-map.json', 'base or Local Business pattern', $slug );
			}
		}
	}

	sort( $repeatable_keys );
	$repeatable_by_locale[ $locale ] = $repeatable_keys;
}

if ( $page_keys_by_locale['en_US'] !== $page_keys_by_locale['es_ES'] ) {
	fail_local_business_preset( 'page-key-parity', 'Local Business EN/ES maps must describe the same singleton pages.', LOCAL_BUSINESS_PRESET_DIR . '/content-map.json', $page_keys_by_locale['en_US'], $page_keys_by_locale['es_ES'] );
}

$expected_repeatables = array( 'location-detail', 'service-area-detail', 'service-detail' );
if ( $repeatable_by_locale['en_US'] !== $expected_repeatables || $repeatable_by_locale['es_ES'] !== $expected_repeatables ) {
	fail_local_business_preset( 'repeatable-parity', 'Local Business EN/ES repeatables must match the canonical service/location/service-area model.', LOCAL_BUSINESS_PRESET_DIR . '/content-map.json', $expected_repeatables, $repeatable_by_locale );
}

printf( "Local Business preset contract OK: explicit Schema identity, anti-doorway location model, 3 bilingual patterns and EN/ES content maps pass.\n" );
