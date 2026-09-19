<?php
/**
 * Validate the Phase 7E SaaS / Digital Product preset contract.
 */

declare(strict_types=1);

const SAAS_PRESET_DIR = 'presets/saas-digital-product';
const SAAS_THEME_DIR  = 'packages/seo-geo-theme';

/**
 * Emit one structured actionable failure and stop.
 *
 * @param mixed $expected Expected value.
 * @param mixed $received Actual value.
 */
function fail_saas_preset( string $code, string $message, string $file_line, mixed $expected, mixed $received ): never {
	$signature = substr( hash( 'sha256', $code . ':' . $file_line . ':' . $message ), 0, 12 );

	echo json_encode(
		array(
			'schema_version'    => 1,
			'pipeline'          => getenv( 'GITHUB_WORKFLOW' ) ?: 'foundation',
			'run_id'            => getenv( 'GITHUB_RUN_ID' ) ?: 'local',
			'run_attempt'       => getenv( 'GITHUB_RUN_ATTEMPT' ) ?: '1',
			'job'               => getenv( 'GITHUB_JOB' ) ?: 'foundation',
			'step'              => 'saas-digital-product-preset-contract',
			'command'           => 'php scripts/ci/validate-saas-digital-product-preset.php',
			'exit_code'         => 1,
			'primary_error'     => $message,
			'file_line'         => $file_line,
			'expected'          => is_scalar( $expected ) || null === $expected ? $expected : json_encode( $expected, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ),
			'received'          => is_scalar( $received ) || null === $received ? $received : json_encode( $received, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ),
			'error_signature'   => $signature,
			'root_cause_status' => 'unknown',
		),
		JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
	) . PHP_EOL;

	exit( 1 );
}

/**
 * Decode one required JSON document.
 *
 * @return array<string, mixed>
 */
function saas_json( string $path ): array {
	if ( ! is_file( $path ) ) {
		fail_saas_preset( 'missing-json', 'SaaS preset document is missing.', $path, 'file exists', 'missing' );
	}

	try {
		$value = json_decode( (string) file_get_contents( $path ), true, 512, JSON_THROW_ON_ERROR );
	} catch ( JsonException $exception ) {
		fail_saas_preset( 'invalid-json', 'SaaS preset document is invalid JSON.', $path, 'valid JSON object', $exception->getMessage() );
	}

	if ( ! is_array( $value ) ) {
		fail_saas_preset( 'json-object', 'SaaS preset document must decode to an object.', $path, 'JSON object', gettype( $value ) );
	}

	return $value;
}

/**
 * Extract neutral base pattern slugs.
 *
 * @return array<string, true>
 */
function saas_base_patterns(): array {
	$files = glob( SAAS_THEME_DIR . '/patterns/*.php' );
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
 * Return design tokens available to preset patterns.
 *
 * @return array<string, array<string, true>>
 */
function saas_theme_tokens(): array {
	$theme = saas_json( SAAS_THEME_DIR . '/theme.json' );
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

$manifest = saas_json( SAAS_PRESET_DIR . '/preset.json' );
$content  = saas_json( SAAS_PRESET_DIR . '/content-map.json' );
$patterns = saas_json( SAAS_PRESET_DIR . '/patterns.json' );

if ( 1 !== ( $manifest['schema_version'] ?? null ) || 'saas-digital-product' !== ( $manifest['id'] ?? null ) || 'saas-digital-product' !== ( $manifest['site_type'] ?? null ) ) {
	fail_saas_preset( 'manifest-identity', 'SaaS manifest identity/version is invalid.', SAAS_PRESET_DIR . '/preset.json', 'schema_version=1, id/site_type=saas-digital-product', $manifest );
}

$baseline_locales = $manifest['multilingual']['baseline_locales'] ?? null;
if ( ! is_array( $baseline_locales ) || array_values( $baseline_locales ) !== array( 'en_US', 'es_ES' ) ) {
	fail_saas_preset( 'locales', 'SaaS preset must ship EN/ES together.', SAAS_PRESET_DIR . '/preset.json', array( 'en_US', 'es_ES' ), $baseline_locales );
}

$schema = $manifest['schema'] ?? null;
if (
	! is_array( $schema )
	|| 'organization' !== ( $schema['site_identity'] ?? null )
	|| true !== ( $schema['requires_confirmation'] ?? null )
	|| 'SoftwareApplication' !== ( $schema['optional_product_entity'] ?? null )
	|| 'recommendation-only' !== ( $schema['software_application_status'] ?? null )
	|| false !== ( $schema['theme_emits_software_application'] ?? null )
	|| true !== ( $schema['requires_explicit_supported_contract'] ?? null )
) {
	fail_saas_preset( 'schema-boundary', 'SaaS product Schema must remain recommendation-only until an accepted visible-fact contract exists.', SAAS_PRESET_DIR . '/preset.json', 'explicit Organization + recommendation-only SoftwareApplication + no theme emission', $schema );
}

$product_model = $manifest['product_model'] ?? null;
$expected_model = array(
	'zero_plugin_preset_safe'              => true,
	'product_backend_external'             => true,
	'billing_provider_external'            => true,
	'crm_provider_external'                => true,
	'telemetry_provider_external'          => true,
	'credentials_never_created_by_preset'  => true,
	'integrations_require_explicit_configuration' => true,
	'auto_generate_customer_proof'         => false,
	'auto_generate_performance_claims'     => false,
	'auto_generate_comparison_pages'       => false,
	'auto_generate_pricing'                => false,
);
if ( ! is_array( $product_model ) ) {
	fail_saas_preset( 'product-model', 'SaaS product ownership model is missing.', SAAS_PRESET_DIR . '/preset.json', $expected_model, $product_model );
}
foreach ( $expected_model as $key => $expected ) {
	if ( ( $product_model[ $key ] ?? null ) !== $expected ) {
		fail_saas_preset( 'product-rule', 'SaaS product/provider safety rule is invalid.', SAAS_PRESET_DIR . '/preset.json#product_model.' . $key, $expected, $product_model[ $key ] ?? null );
	}
}

$required_templates = $manifest['required_templates'] ?? null;
if ( ! is_array( $required_templates ) ) {
	fail_saas_preset( 'templates', 'SaaS required_templates is missing.', SAAS_PRESET_DIR . '/preset.json', 'template list', $required_templates );
}
foreach ( $required_templates as $template ) {
	if ( ! is_string( $template ) || ! is_file( SAAS_THEME_DIR . '/templates/' . $template . '.html' ) ) {
		fail_saas_preset( 'template-missing', 'SaaS references a missing base template.', SAAS_PRESET_DIR . '/preset.json', 'existing theme template', $template );
	}
}

$base_patterns = saas_base_patterns();
$recommended   = $manifest['recommended_base_patterns'] ?? null;
if ( ! is_array( $recommended ) ) {
	fail_saas_preset( 'recommended-patterns', 'SaaS base pattern list is missing.', SAAS_PRESET_DIR . '/preset.json', 'base pattern list', $recommended );
}
foreach ( $recommended as $slug ) {
	if ( ! is_string( $slug ) || ! isset( $base_patterns[ $slug ] ) ) {
		fail_saas_preset( 'base-pattern', 'SaaS references a pattern outside the neutral theme contract.', SAAS_PRESET_DIR . '/preset.json', 'known base pattern', $slug );
	}
}

$expected_preset_slugs = array(
	'seo-geo-theme/saas-product-overview',
	'seo-geo-theme/saas-use-cases',
	'seo-geo-theme/saas-integrations',
	'seo-geo-theme/saas-pricing-plans',
	'seo-geo-theme/saas-comparison-framework',
);
$declared_preset_slugs = $manifest['preset_patterns'] ?? null;
if ( ! is_array( $declared_preset_slugs ) || array_values( $declared_preset_slugs ) !== $expected_preset_slugs ) {
	fail_saas_preset( 'preset-pattern-list', 'SaaS manifest pattern list is not canonical.', SAAS_PRESET_DIR . '/preset.json', $expected_preset_slugs, $declared_preset_slugs );
}

$category = $patterns['category'] ?? null;
if ( ! is_array( $category ) || 'seo-geo-saas' !== ( $category['slug'] ?? null ) ) {
	fail_saas_preset( 'pattern-category', 'SaaS pattern category is invalid.', SAAS_PRESET_DIR . '/patterns.json', 'seo-geo-saas', $category );
}
$labels = is_array( $category ) ? ( $category['labels'] ?? null ) : null;
if ( ! is_array( $labels ) || ! isset( $labels['en_US'], $labels['es_ES'] ) ) {
	fail_saas_preset( 'pattern-category-i18n', 'SaaS category must ship EN/ES labels.', SAAS_PRESET_DIR . '/patterns.json', 'en_US + es_ES', $labels );
}

$pattern_rows = $patterns['patterns'] ?? null;
if ( ! is_array( $pattern_rows ) || 5 !== count( $pattern_rows ) ) {
	fail_saas_preset( 'pattern-count', 'SaaS must ship exactly five preset-owned patterns in 7E.', SAAS_PRESET_DIR . '/patterns.json', 5, is_array( $pattern_rows ) ? count( $pattern_rows ) : $pattern_rows );
}

$theme_tokens = saas_theme_tokens();
$seen         = array();
foreach ( $pattern_rows as $index => $pattern ) {
	$file_line = SAAS_PRESET_DIR . '/patterns.json#patterns[' . $index . ']';
	if ( ! is_array( $pattern ) || ! isset( $pattern['slug'] ) || ! is_string( $pattern['slug'] ) ) {
		fail_saas_preset( 'pattern-slug', 'SaaS pattern slug is missing.', $file_line, 'namespaced slug', $pattern );
	}

	$slug = $pattern['slug'];
	if ( ! in_array( $slug, $expected_preset_slugs, true ) || isset( $seen[ $slug ] ) ) {
		fail_saas_preset( 'pattern-slug-set', 'SaaS pattern slug is unexpected or duplicated.', $file_line, $expected_preset_slugs, $slug );
	}
	$seen[ $slug ] = true;

	foreach ( array( 'en_US', 'es_ES' ) as $locale ) {
		$localized = $pattern['locales'][ $locale ] ?? null;
		if ( ! is_array( $localized ) ) {
			fail_saas_preset( 'pattern-locale', 'SaaS pattern is missing a required locale.', $file_line, $locale, $localized );
		}
		foreach ( array( 'title', 'description', 'content' ) as $field ) {
			if ( ! isset( $localized[ $field ] ) || ! is_string( $localized[ $field ] ) || '' === trim( $localized[ $field ] ) ) {
				fail_saas_preset( 'pattern-field', 'SaaS localized pattern field is empty.', $file_line, $locale . '.' . $field, $localized[ $field ] ?? null );
			}
		}

		$markup = $localized['content'];
		if ( 1 === preg_match( '/<\s*(script|style)\b|<!--\s*wp:(?:html|woocommerce\/)|https?:\/\/|application\/ld\+json|schema\.org/i', $markup, $matches ) ) {
			fail_saas_preset( 'unsafe-pattern-content', 'SaaS pattern contains forbidden embedded/remote/provider/Schema content.', $file_line, 'core blocks without scripts/styles/remote URLs/provider blocks/Schema', $matches[0] );
		}
		if ( str_contains( $markup, '"level":1' ) || 1 === preg_match( '/<h1\b/i', $markup ) ) {
			fail_saas_preset( 'pattern-h1', 'SaaS reusable patterns must not own the page H1.', $file_line, 'no H1', 'H1 found' );
		}
		if ( 1 === preg_match( '/#[0-9A-Fa-f]{3,8}\b/', $markup, $matches ) ) {
			fail_saas_preset( 'pattern-color', 'SaaS pattern must use semantic theme colors.', $file_line, 'no raw hex color', $matches[0] );
		}
		if ( 1 === preg_match( '/\b[0-9]+(?:\.[0-9]+)?(?:px|rem|em|vw|vh)\b/i', $markup, $matches ) ) {
			fail_saas_preset( 'pattern-size', 'SaaS pattern must use design-token sizes.', $file_line, 'no raw CSS size literal', $matches[0] );
		}

		preg_match_all( '/var:preset\|([a-z-]+)\|([a-z0-9-]+)/i', $markup, $token_matches, PREG_SET_ORDER );
		foreach ( $token_matches as $token_match ) {
			$type = strtolower( $token_match[1] );
			$name = strtolower( $token_match[2] );
			if ( ! isset( $theme_tokens[ $type ][ $name ] ) ) {
				fail_saas_preset( 'pattern-token', 'SaaS pattern references an unknown theme token.', $file_line, 'theme.json token', $token_match[0] );
			}
		}
	}
}

$locales = $content['locales'] ?? null;
if ( ! is_array( $locales ) || ! isset( $locales['en_US'], $locales['es_ES'] ) ) {
	fail_saas_preset( 'content-locales', 'SaaS content map must ship EN/ES together.', SAAS_PRESET_DIR . '/content-map.json', 'en_US + es_ES', $locales );
}

$allowed_patterns    = array_fill_keys( array_merge( array_keys( $base_patterns ), $expected_preset_slugs ), true );
$page_keys_by_locale = array();
$comparison_keys     = array();

foreach ( array( 'en_US', 'es_ES' ) as $locale ) {
	$pages = $locales[ $locale ]['pages'] ?? null;
	if ( ! is_array( $pages ) || 13 !== count( $pages ) ) {
		fail_saas_preset( 'page-count', 'SaaS must ship thirteen singleton content/legal pages per locale.', SAAS_PRESET_DIR . '/content-map.json', 13, is_array( $pages ) ? count( $pages ) : $pages );
	}

	$keys  = array();
	$slugs = array();
	$roles = array();
	foreach ( $pages as $page ) {
		if ( ! is_array( $page ) ) {
			fail_saas_preset( 'page-row', 'SaaS page row is invalid.', SAAS_PRESET_DIR . '/content-map.json', 'page object', $page );
		}
		foreach ( array( 'key', 'title', 'slug', 'role', 'template' ) as $field ) {
			if ( ! isset( $page[ $field ] ) || ! is_string( $page[ $field ] ) || '' === trim( $page[ $field ] ) ) {
				fail_saas_preset( 'page-field', 'SaaS page field is missing.', SAAS_PRESET_DIR . '/content-map.json', $locale . '.' . $field, $page[ $field ] ?? null );
			}
		}
		if ( isset( $keys[ $page['key'] ] ) || isset( $slugs[ $page['slug'] ] ) ) {
			fail_saas_preset( 'page-duplicate', 'SaaS page keys/slugs must be unique per locale.', SAAS_PRESET_DIR . '/content-map.json', 'unique keys/slugs', $page );
		}
		$keys[ $page['key'] ] = true;
		$slugs[ $page['slug'] ] = true;
		$roles[ $page['role'] ] = ( $roles[ $page['role'] ] ?? 0 ) + 1;

		$page_patterns = $page['patterns'] ?? array();
		if ( ! is_array( $page_patterns ) ) {
			fail_saas_preset( 'page-patterns', 'SaaS page composition must be a pattern list.', SAAS_PRESET_DIR . '/content-map.json', 'pattern list', $page_patterns );
		}
		foreach ( $page_patterns as $pattern_slug ) {
			if ( ! is_string( $pattern_slug ) || ! isset( $allowed_patterns[ $pattern_slug ] ) ) {
				fail_saas_preset( 'page-pattern-reference', 'SaaS content map references an unknown pattern.', SAAS_PRESET_DIR . '/content-map.json', 'base or SaaS pattern', $pattern_slug );
			}
		}
	}

	if ( 1 !== ( $roles['front-page'] ?? 0 ) || 1 !== ( $roles['posts-page'] ?? 0 ) ) {
		fail_saas_preset( 'page-authority', 'SaaS must define exactly one front page and one resources/posts page per locale.', SAAS_PRESET_DIR . '/content-map.json', 'one front-page + one posts-page', $roles );
	}

	$required_keys = array( 'about', 'contact-demo', 'documentation', 'features', 'home', 'integrations', 'legal-notice', 'pricing', 'privacy-policy', 'product', 'resources', 'solutions', 'terms-conditions' );
	$page_keys_by_locale[ $locale ] = array_keys( $keys );
	sort( $page_keys_by_locale[ $locale ] );
	if ( $page_keys_by_locale[ $locale ] !== $required_keys ) {
		fail_saas_preset( 'page-set', 'SaaS content map is missing a required modern product surface.', SAAS_PRESET_DIR . '/content-map.json', $required_keys, $page_keys_by_locale[ $locale ] );
	}

	$editorial = $locales[ $locale ]['optional_editorial'] ?? null;
	if ( ! is_array( $editorial ) || 1 !== count( $editorial ) ) {
		fail_saas_preset( 'comparison-count', 'SaaS must define one optional comparison-page contract.', SAAS_PRESET_DIR . '/content-map.json', 1, is_array( $editorial ) ? count( $editorial ) : $editorial );
	}

	$comparison = $editorial[0];
	if (
		! is_array( $comparison )
		|| 'comparison' !== ( $comparison['key'] ?? null )
		|| 'wordpress-page' !== ( $comparison['source'] ?? null )
		|| 'page' !== ( $comparison['template'] ?? null )
		|| true !== ( $comparison['create_only_when_useful'] ?? null )
	) {
		fail_saas_preset( 'comparison-contract', 'SaaS comparison page must be optional, WordPress-page based and value-gated.', SAAS_PRESET_DIR . '/content-map.json', 'comparison wordpress-page, create only when useful', $comparison );
	}

	$comparison_patterns = $comparison['recommended_patterns'] ?? null;
	if ( ! is_array( $comparison_patterns ) || ! in_array( 'seo-geo-theme/saas-comparison-framework', $comparison_patterns, true ) ) {
		fail_saas_preset( 'comparison-pattern', 'SaaS comparison contract must use the evidence-based comparison framework.', SAAS_PRESET_DIR . '/content-map.json', 'saas-comparison-framework', $comparison_patterns );
	}
	foreach ( $comparison_patterns as $pattern_slug ) {
		if ( ! is_string( $pattern_slug ) || ! isset( $allowed_patterns[ $pattern_slug ] ) ) {
			fail_saas_preset( 'comparison-pattern-reference', 'SaaS comparison contract references an unknown pattern.', SAAS_PRESET_DIR . '/content-map.json', 'base or SaaS pattern', $pattern_slug );
		}
	}

	$requirements = $comparison['content_requirements'] ?? null;
	if ( ! is_array( $requirements ) || count( $requirements ) < 5 ) {
		fail_saas_preset( 'comparison-value', 'SaaS comparison pages must require substantial evidence and anti-doorway safeguards.', SAAS_PRESET_DIR . '/content-map.json', 'at least five comparison requirements', $requirements );
	}

	$comparison_keys[ $locale ] = array( 'comparison' );
}

if ( $page_keys_by_locale['en_US'] !== $page_keys_by_locale['es_ES'] ) {
	fail_saas_preset( 'page-key-parity', 'SaaS EN/ES maps must describe the same singleton pages.', SAAS_PRESET_DIR . '/content-map.json', $page_keys_by_locale['en_US'], $page_keys_by_locale['es_ES'] );
}
if ( $comparison_keys['en_US'] !== $comparison_keys['es_ES'] ) {
	fail_saas_preset( 'comparison-parity', 'SaaS EN/ES optional comparison surfaces must match.', SAAS_PRESET_DIR . '/content-map.json', $comparison_keys['en_US'], $comparison_keys['es_ES'] );
}

printf( "SaaS / Digital Product preset contract OK: zero-plugin-safe product boundaries, EN/ES maps and evidence-based patterns pass.\n" );
