<?php
/**
 * Validate the Phase 7D Ecommerce preset contract.
 */

declare(strict_types=1);

const ECOMMERCE_PRESET_DIR = 'presets/ecommerce';
const ECOMMERCE_THEME_DIR  = 'packages/seo-geo-theme';

/**
 * Emit one structured actionable failure and stop.
 *
 * @param mixed $expected Expected value.
 * @param mixed $received Actual value.
 */
function fail_ecommerce_preset( string $code, string $message, string $file_line, mixed $expected, mixed $received ): never {
	$signature = substr( hash( 'sha256', $code . ':' . $file_line . ':' . $message ), 0, 12 );

	echo json_encode(
		array(
			'schema_version'    => 1,
			'pipeline'          => getenv( 'GITHUB_WORKFLOW' ) ?: 'foundation',
			'run_id'            => getenv( 'GITHUB_RUN_ID' ) ?: 'local',
			'run_attempt'       => getenv( 'GITHUB_RUN_ATTEMPT' ) ?: '1',
			'job'               => getenv( 'GITHUB_JOB' ) ?: 'foundation',
			'step'              => 'ecommerce-preset-contract',
			'command'           => 'php scripts/ci/validate-ecommerce-preset.php',
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
 * Decode one required Ecommerce JSON document.
 *
 * @return array<string, mixed>
 */
function ecommerce_json( string $path ): array {
	if ( ! is_file( $path ) ) {
		fail_ecommerce_preset( 'missing-json', 'Ecommerce preset document is missing.', $path, 'file exists', 'missing' );
	}

	try {
		$value = json_decode( (string) file_get_contents( $path ), true, 512, JSON_THROW_ON_ERROR );
	} catch ( JsonException $exception ) {
		fail_ecommerce_preset( 'invalid-json', 'Ecommerce preset document is invalid JSON.', $path, 'valid JSON object', $exception->getMessage() );
	}

	if ( ! is_array( $value ) ) {
		fail_ecommerce_preset( 'json-object', 'Ecommerce preset document must decode to an object.', $path, 'JSON object', gettype( $value ) );
	}

	return $value;
}

/**
 * Extract neutral base pattern slugs.
 *
 * @return array<string, true>
 */
function ecommerce_base_patterns(): array {
	$files = glob( ECOMMERCE_THEME_DIR . '/patterns/*.php' );
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
function ecommerce_theme_tokens(): array {
	$theme = ecommerce_json( ECOMMERCE_THEME_DIR . '/theme.json' );
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

$manifest = ecommerce_json( ECOMMERCE_PRESET_DIR . '/preset.json' );
$content  = ecommerce_json( ECOMMERCE_PRESET_DIR . '/content-map.json' );
$patterns = ecommerce_json( ECOMMERCE_PRESET_DIR . '/patterns.json' );

if ( 1 !== ( $manifest['schema_version'] ?? null ) || 'ecommerce' !== ( $manifest['id'] ?? null ) || 'ecommerce' !== ( $manifest['site_type'] ?? null ) ) {
	fail_ecommerce_preset( 'manifest-identity', 'Ecommerce manifest identity/version is invalid.', ECOMMERCE_PRESET_DIR . '/preset.json', 'schema_version=1, id/site_type=ecommerce', $manifest );
}

$baseline_locales = $manifest['multilingual']['baseline_locales'] ?? null;
if ( ! is_array( $baseline_locales ) || array_values( $baseline_locales ) !== array( 'en_US', 'es_ES' ) ) {
	fail_ecommerce_preset( 'locales', 'Ecommerce preset must ship EN/ES together.', ECOMMERCE_PRESET_DIR . '/preset.json', array( 'en_US', 'es_ES' ), $baseline_locales );
}
if ( true !== ( $manifest['multilingual']['commerce_routes_provider_owned'] ?? null ) ) {
	fail_ecommerce_preset( 'multilingual-owner', 'Commerce multilingual routing must remain provider-owned.', ECOMMERCE_PRESET_DIR . '/preset.json', true, $manifest['multilingual']['commerce_routes_provider_owned'] ?? null );
}

$schema = $manifest['schema'] ?? null;
if (
	! is_array( $schema )
	|| 'organization' !== ( $schema['site_identity'] ?? null )
	|| true !== ( $schema['requires_confirmation'] ?? null )
	|| 'commerce-provider' !== ( $schema['product_schema_owner'] ?? null )
	|| false !== ( $schema['theme_emits_product_schema'] ?? null )
) {
	fail_ecommerce_preset( 'schema-ownership', 'Ecommerce Product Schema must remain provider-owned and Organization identity explicit.', ECOMMERCE_PRESET_DIR . '/preset.json', 'organization confirmation + provider-owned Product Schema', $schema );
}

$provider_entities = is_array( $schema ) ? ( $schema['provider_owned_commerce_entities'] ?? null ) : null;
foreach ( array( 'Product', 'Offer', 'AggregateRating', 'Review' ) as $entity ) {
	if ( ! is_array( $provider_entities ) || ! in_array( $entity, $provider_entities, true ) ) {
		fail_ecommerce_preset( 'provider-schema-entity', 'Ecommerce provider-owned Schema entity set is incomplete.', ECOMMERCE_PRESET_DIR . '/preset.json', $entity, $provider_entities );
	}
}

$commerce = $manifest['commerce_model'] ?? null;
$commerce_expected = array(
	'preferred_provider'                           => 'woocommerce',
	'integration_status'                           => 'preferred-provider-contract-only',
	'requires_supported_adapter_for_live_commerce' => true,
	'zero_plugin_preset_safe'                       => true,
	'provider_owns_product_facts'                   => true,
	'provider_owns_price_availability'              => true,
	'provider_owns_reviews_ratings'                 => true,
	'provider_owns_multilingual_routing'            => true,
	'faceted_urls_require_explicit_indexability_policy' => true,
	'auto_index_facets'                             => false,
	'auto_generate_category_copy'                   => false,
	'auto_generate_product_claims'                  => false,
	'checkout_cart_account_are_provider_surfaces'   => true,
);
if ( ! is_array( $commerce ) ) {
	fail_ecommerce_preset( 'commerce-model', 'Ecommerce commerce model is missing.', ECOMMERCE_PRESET_DIR . '/preset.json', $commerce_expected, $commerce );
}
foreach ( $commerce_expected as $key => $expected ) {
	if ( ( $commerce[ $key ] ?? null ) !== $expected ) {
		fail_ecommerce_preset( 'commerce-rule', 'Ecommerce provider ownership/safety rule is invalid.', ECOMMERCE_PRESET_DIR . '/preset.json#commerce_model.' . $key, $expected, $commerce[ $key ] ?? null );
	}
}

$optional_integrations = $manifest['optional_integrations'] ?? null;
if ( ! is_array( $optional_integrations ) || array_values( $optional_integrations ) !== array( 'woocommerce' ) ) {
	fail_ecommerce_preset( 'integration-list', 'Ecommerce may declare WooCommerce only as the preferred optional integration in 7D.', ECOMMERCE_PRESET_DIR . '/preset.json', array( 'woocommerce' ), $optional_integrations );
}

$disabled = $manifest['explicitly_disabled'] ?? null;
foreach (
	array(
		'WooCommerce supported-combination claim without an accepted adapter',
		'Product or Offer Schema emitted by the theme',
		'price or availability inference',
		'review or rating fabrication',
		'automatic faceted URL indexing',
		'custom duplicate product routing',
		'custom duplicate multilingual commerce routing',
		'automatic product, category or brand claims',
		'checkout, cart or account SEO landing assumptions',
	) as $required_disabled
) {
	if ( ! is_array( $disabled ) || ! in_array( $required_disabled, $disabled, true ) ) {
		fail_ecommerce_preset( 'disabled-feature', 'Ecommerce must explicitly disable unsupported or duplicative commerce behavior.', ECOMMERCE_PRESET_DIR . '/preset.json', $required_disabled, $disabled );
	}
}

$required_templates = $manifest['required_templates'] ?? null;
if ( ! is_array( $required_templates ) ) {
	fail_ecommerce_preset( 'templates', 'Ecommerce required_templates is missing.', ECOMMERCE_PRESET_DIR . '/preset.json', 'template list', $required_templates );
}
foreach ( $required_templates as $template ) {
	if ( ! is_string( $template ) || ! is_file( ECOMMERCE_THEME_DIR . '/templates/' . $template . '.html' ) ) {
		fail_ecommerce_preset( 'template-missing', 'Ecommerce references a missing base template.', ECOMMERCE_PRESET_DIR . '/preset.json', 'existing theme template', $template );
	}
}

$base_patterns = ecommerce_base_patterns();
$recommended   = $manifest['recommended_base_patterns'] ?? null;
if ( ! is_array( $recommended ) ) {
	fail_ecommerce_preset( 'recommended-patterns', 'Ecommerce base pattern list is missing.', ECOMMERCE_PRESET_DIR . '/preset.json', 'base pattern list', $recommended );
}
foreach ( $recommended as $slug ) {
	if ( ! is_string( $slug ) || ! isset( $base_patterns[ $slug ] ) ) {
		fail_ecommerce_preset( 'base-pattern', 'Ecommerce references a pattern outside the neutral theme contract.', ECOMMERCE_PRESET_DIR . '/preset.json', 'known base pattern', $slug );
	}
}

$expected_preset_slugs = array(
	'seo-geo-theme/ecommerce-category-guide',
	'seo-geo-theme/ecommerce-buying-guide',
	'seo-geo-theme/ecommerce-policy-navigation',
	'seo-geo-theme/ecommerce-brand-editorial',
);
$declared_preset_slugs = $manifest['preset_patterns'] ?? null;
if ( ! is_array( $declared_preset_slugs ) || array_values( $declared_preset_slugs ) !== $expected_preset_slugs ) {
	fail_ecommerce_preset( 'preset-pattern-list', 'Ecommerce manifest pattern list is not canonical.', ECOMMERCE_PRESET_DIR . '/preset.json', $expected_preset_slugs, $declared_preset_slugs );
}

$category = $patterns['category'] ?? null;
if ( ! is_array( $category ) || 'seo-geo-ecommerce' !== ( $category['slug'] ?? null ) ) {
	fail_ecommerce_preset( 'pattern-category', 'Ecommerce pattern category is invalid.', ECOMMERCE_PRESET_DIR . '/patterns.json', 'seo-geo-ecommerce', $category );
}
$labels = is_array( $category ) ? ( $category['labels'] ?? null ) : null;
if ( ! is_array( $labels ) || ! isset( $labels['en_US'], $labels['es_ES'] ) ) {
	fail_ecommerce_preset( 'pattern-category-i18n', 'Ecommerce category must ship EN/ES labels.', ECOMMERCE_PRESET_DIR . '/patterns.json', 'en_US + es_ES', $labels );
}

$pattern_rows = $patterns['patterns'] ?? null;
if ( ! is_array( $pattern_rows ) || 4 !== count( $pattern_rows ) ) {
	fail_ecommerce_preset( 'pattern-count', 'Ecommerce must ship exactly four preset-owned patterns in 7D.', ECOMMERCE_PRESET_DIR . '/patterns.json', 4, is_array( $pattern_rows ) ? count( $pattern_rows ) : $pattern_rows );
}

$theme_tokens = ecommerce_theme_tokens();
$seen         = array();
foreach ( $pattern_rows as $index => $pattern ) {
	$file_line = ECOMMERCE_PRESET_DIR . '/patterns.json#patterns[' . $index . ']';
	if ( ! is_array( $pattern ) || ! isset( $pattern['slug'] ) || ! is_string( $pattern['slug'] ) ) {
		fail_ecommerce_preset( 'pattern-slug', 'Ecommerce pattern slug is missing.', $file_line, 'namespaced slug', $pattern );
	}

	$slug = $pattern['slug'];
	if ( ! in_array( $slug, $expected_preset_slugs, true ) || isset( $seen[ $slug ] ) ) {
		fail_ecommerce_preset( 'pattern-slug-set', 'Ecommerce pattern slug is unexpected or duplicated.', $file_line, $expected_preset_slugs, $slug );
	}
	$seen[ $slug ] = true;

	foreach ( array( 'en_US', 'es_ES' ) as $locale ) {
		$localized = $pattern['locales'][ $locale ] ?? null;
		if ( ! is_array( $localized ) ) {
			fail_ecommerce_preset( 'pattern-locale', 'Ecommerce pattern is missing a required locale.', $file_line, $locale, $localized );
		}
		foreach ( array( 'title', 'description', 'content' ) as $field ) {
			if ( ! isset( $localized[ $field ] ) || ! is_string( $localized[ $field ] ) || '' === trim( $localized[ $field ] ) ) {
				fail_ecommerce_preset( 'pattern-field', 'Ecommerce localized pattern field is empty.', $file_line, $locale . '.' . $field, $localized[ $field ] ?? null );
			}
		}

		$markup = $localized['content'];
		if ( 1 === preg_match( '/<\s*(script|style)\b|<!--\s*wp:(?:html|woocommerce\/)|https?:\/\/|application\/ld\+json|schema\.org/i', $markup, $matches ) ) {
			fail_ecommerce_preset( 'unsafe-pattern-content', 'Ecommerce pattern contains forbidden embedded/remote/WooCommerce/Schema content.', $file_line, 'core blocks without scripts/styles/remote URLs/Woo blocks/Schema', $matches[0] );
		}
		if ( str_contains( $markup, '"level":1' ) || 1 === preg_match( '/<h1\b/i', $markup ) ) {
			fail_ecommerce_preset( 'pattern-h1', 'Ecommerce reusable patterns must not own the page H1.', $file_line, 'no H1', 'H1 found' );
		}
		if ( 1 === preg_match( '/#[0-9A-Fa-f]{3,8}\b/', $markup, $matches ) ) {
			fail_ecommerce_preset( 'pattern-color', 'Ecommerce pattern must use semantic theme colors.', $file_line, 'no raw hex color', $matches[0] );
		}
		if ( 1 === preg_match( '/\b[0-9]+(?:\.[0-9]+)?(?:px|rem|em|vw|vh)\b/i', $markup, $matches ) ) {
			fail_ecommerce_preset( 'pattern-size', 'Ecommerce pattern must use design-token sizes.', $file_line, 'no raw CSS size literal', $matches[0] );
		}

		preg_match_all( '/var:preset\|([a-z-]+)\|([a-z0-9-]+)/i', $markup, $token_matches, PREG_SET_ORDER );
		foreach ( $token_matches as $token_match ) {
			$type = strtolower( $token_match[1] );
			$name = strtolower( $token_match[2] );
			if ( ! isset( $theme_tokens[ $type ][ $name ] ) ) {
				fail_ecommerce_preset( 'pattern-token', 'Ecommerce pattern references an unknown theme token.', $file_line, 'theme.json token', $token_match[0] );
			}
		}
	}
}

$locales = $content['locales'] ?? null;
if ( ! is_array( $locales ) || ! isset( $locales['en_US'], $locales['es_ES'] ) ) {
	fail_ecommerce_preset( 'content-locales', 'Ecommerce content map must ship EN/ES together.', ECOMMERCE_PRESET_DIR . '/content-map.json', 'en_US + es_ES', $locales );
}

$allowed_patterns    = array_fill_keys( array_merge( array_keys( $base_patterns ), $expected_preset_slugs ), true );
$page_keys_by_locale = array();
$dynamic_by_locale   = array();
$editorial_by_locale = array();
$dynamic_sources     = array(
	'shop'             => 'woocommerce-shop',
	'product-category' => 'woocommerce-product-category',
	'product'          => 'woocommerce-product',
);

foreach ( array( 'en_US', 'es_ES' ) as $locale ) {
	$pages = $locales[ $locale ]['pages'] ?? null;
	if ( ! is_array( $pages ) || 10 !== count( $pages ) ) {
		fail_ecommerce_preset( 'page-count', 'Ecommerce must ship ten singleton editorial/policy pages per locale.', ECOMMERCE_PRESET_DIR . '/content-map.json', 10, is_array( $pages ) ? count( $pages ) : $pages );
	}

	$keys  = array();
	$slugs = array();
	$roles = array();
	foreach ( $pages as $page ) {
		if ( ! is_array( $page ) ) {
			fail_ecommerce_preset( 'page-row', 'Ecommerce page row is invalid.', ECOMMERCE_PRESET_DIR . '/content-map.json', 'page object', $page );
		}
		foreach ( array( 'key', 'title', 'slug', 'role', 'template' ) as $field ) {
			if ( ! isset( $page[ $field ] ) || ! is_string( $page[ $field ] ) || '' === trim( $page[ $field ] ) ) {
				fail_ecommerce_preset( 'page-field', 'Ecommerce page field is missing.', ECOMMERCE_PRESET_DIR . '/content-map.json', $locale . '.' . $field, $page[ $field ] ?? null );
			}
		}
		if ( isset( $keys[ $page['key'] ] ) || isset( $slugs[ $page['slug'] ] ) ) {
			fail_ecommerce_preset( 'page-duplicate', 'Ecommerce page keys/slugs must be unique per locale.', ECOMMERCE_PRESET_DIR . '/content-map.json', 'unique keys/slugs', $page );
		}
		$keys[ $page['key'] ] = true;
		$slugs[ $page['slug'] ] = true;
		$roles[ $page['role'] ] = ( $roles[ $page['role'] ] ?? 0 ) + 1;

		$page_patterns = $page['patterns'] ?? array();
		if ( ! is_array( $page_patterns ) ) {
			fail_ecommerce_preset( 'page-patterns', 'Ecommerce page composition must be a pattern list.', ECOMMERCE_PRESET_DIR . '/content-map.json', 'pattern list', $page_patterns );
		}
		foreach ( $page_patterns as $slug ) {
			if ( ! is_string( $slug ) || ! isset( $allowed_patterns[ $slug ] ) ) {
				fail_ecommerce_preset( 'page-pattern-reference', 'Ecommerce content map references an unknown pattern.', ECOMMERCE_PRESET_DIR . '/content-map.json', 'base or Ecommerce pattern', $slug );
			}
		}
	}

	if ( 1 !== ( $roles['front-page'] ?? 0 ) || 1 !== ( $roles['posts-page'] ?? 0 ) ) {
		fail_ecommerce_preset( 'page-authority', 'Ecommerce must define exactly one front page and buying-guide posts page per locale.', ECOMMERCE_PRESET_DIR . '/content-map.json', 'one front-page + one posts-page', $roles );
	}

	$page_keys_by_locale[ $locale ] = array_keys( $keys );
	sort( $page_keys_by_locale[ $locale ] );

	$dynamic = $locales[ $locale ]['dynamic'] ?? null;
	if ( ! is_array( $dynamic ) || 3 !== count( $dynamic ) ) {
		fail_ecommerce_preset( 'dynamic-count', 'Ecommerce must model shop, product category and product as provider-owned dynamic surfaces.', ECOMMERCE_PRESET_DIR . '/content-map.json', 3, is_array( $dynamic ) ? count( $dynamic ) : $dynamic );
	}

	$dynamic_keys = array();
	foreach ( $dynamic as $surface ) {
		if ( ! is_array( $surface ) || ! isset( $surface['key'], $surface['provider'], $surface['source'], $surface['template_owner'], $surface['theme_fallback'] ) ) {
			fail_ecommerce_preset( 'dynamic-row', 'Ecommerce dynamic commerce surface is incomplete.', ECOMMERCE_PRESET_DIR . '/content-map.json', 'provider-owned surface object', $surface );
		}

		$key = $surface['key'];
		if ( ! is_string( $key ) || ! isset( $dynamic_sources[ $key ] ) ) {
			fail_ecommerce_preset( 'dynamic-key', 'Ecommerce dynamic surface key is invalid.', ECOMMERCE_PRESET_DIR . '/content-map.json', array_keys( $dynamic_sources ), $key );
		}
		$dynamic_keys[] = $key;

		if (
			'woocommerce' !== $surface['provider']
			|| $dynamic_sources[ $key ] !== $surface['source']
			|| 'provider' !== $surface['template_owner']
			|| true !== ( $surface['requires_integration'] ?? null )
		) {
			fail_ecommerce_preset( 'dynamic-owner', 'Ecommerce dynamic surface must remain WooCommerce/provider-owned and integration-gated.', ECOMMERCE_PRESET_DIR . '/content-map.json', 'woocommerce + provider owner + requires integration', $surface );
		}
		if ( ! is_string( $surface['theme_fallback'] ) || ! is_file( ECOMMERCE_THEME_DIR . '/templates/' . $surface['theme_fallback'] . '.html' ) ) {
			fail_ecommerce_preset( 'dynamic-fallback', 'Ecommerce dynamic surface references a missing neutral theme fallback.', ECOMMERCE_PRESET_DIR . '/content-map.json', 'existing neutral template', $surface['theme_fallback'] );
		}

		$requirements = $surface['content_requirements'] ?? null;
		if ( ! is_array( $requirements ) || count( $requirements ) < 4 ) {
			fail_ecommerce_preset( 'dynamic-value', 'Ecommerce dynamic surfaces must declare product-fact/provider requirements.', ECOMMERCE_PRESET_DIR . '/content-map.json', 'at least four requirements', $requirements );
		}
	}
	sort( $dynamic_keys );
	$dynamic_by_locale[ $locale ] = $dynamic_keys;

	$editorial = $locales[ $locale ]['optional_editorial'] ?? null;
	if ( ! is_array( $editorial ) || 1 !== count( $editorial ) ) {
		fail_ecommerce_preset( 'optional-editorial-count', 'Ecommerce must define one optional brand/editorial landing contract.', ECOMMERCE_PRESET_DIR . '/content-map.json', 1, is_array( $editorial ) ? count( $editorial ) : $editorial );
	}

	$brand = $editorial[0];
	if (
		! is_array( $brand )
		|| 'brand-editorial' !== ( $brand['key'] ?? null )
		|| 'wordpress-page' !== ( $brand['source'] ?? null )
		|| 'page' !== ( $brand['template'] ?? null )
		|| true !== ( $brand['create_only_when_useful'] ?? null )
	) {
		fail_ecommerce_preset( 'brand-editorial', 'Ecommerce brand/editorial landing must be optional, page-based and value-gated.', ECOMMERCE_PRESET_DIR . '/content-map.json', 'brand-editorial wordpress page, create only when useful', $brand );
	}

	$brand_patterns = $brand['recommended_patterns'] ?? null;
	if ( ! is_array( $brand_patterns ) ) {
		fail_ecommerce_preset( 'brand-patterns', 'Ecommerce brand/editorial pattern guidance is missing.', ECOMMERCE_PRESET_DIR . '/content-map.json', 'pattern list', $brand_patterns );
	}
	foreach ( $brand_patterns as $slug ) {
		if ( ! is_string( $slug ) || ! isset( $allowed_patterns[ $slug ] ) ) {
			fail_ecommerce_preset( 'brand-pattern-reference', 'Ecommerce brand/editorial landing references an unknown pattern.', ECOMMERCE_PRESET_DIR . '/content-map.json', 'base or Ecommerce pattern', $slug );
		}
	}
	$brand_requirements = $brand['content_requirements'] ?? null;
	if ( ! is_array( $brand_requirements ) || count( $brand_requirements ) < 4 ) {
		fail_ecommerce_preset( 'brand-value', 'Ecommerce brand/editorial landing must require original useful content.', ECOMMERCE_PRESET_DIR . '/content-map.json', 'at least four original-value requirements', $brand_requirements );
	}

	$editorial_by_locale[ $locale ] = array( 'brand-editorial' );
}

if ( $page_keys_by_locale['en_US'] !== $page_keys_by_locale['es_ES'] ) {
	fail_ecommerce_preset( 'page-key-parity', 'Ecommerce EN/ES maps must describe the same singleton pages.', ECOMMERCE_PRESET_DIR . '/content-map.json', $page_keys_by_locale['en_US'], $page_keys_by_locale['es_ES'] );
}

$expected_dynamic = array( 'product', 'product-category', 'shop' );
if ( $dynamic_by_locale['en_US'] !== $expected_dynamic || $dynamic_by_locale['es_ES'] !== $expected_dynamic ) {
	fail_ecommerce_preset( 'dynamic-parity', 'Ecommerce EN/ES maps must share the provider-owned shop/category/product model.', ECOMMERCE_PRESET_DIR . '/content-map.json', $expected_dynamic, $dynamic_by_locale );
}
if ( $editorial_by_locale['en_US'] !== $editorial_by_locale['es_ES'] ) {
	fail_ecommerce_preset( 'editorial-parity', 'Ecommerce EN/ES optional editorial surfaces must match.', ECOMMERCE_PRESET_DIR . '/content-map.json', $editorial_by_locale['en_US'], $editorial_by_locale['es_ES'] );
}

printf( "Ecommerce preset contract OK: provider-owned commerce, zero-plugin-safe patterns and EN/ES maps pass.\n" );
