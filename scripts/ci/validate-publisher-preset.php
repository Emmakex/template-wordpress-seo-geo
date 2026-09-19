<?php
/**
 * Validate the Phase 7C Publisher preset contract.
 */

declare(strict_types=1);

const PUBLISHER_PRESET_DIR = 'presets/publisher';
const PUBLISHER_THEME_DIR  = 'packages/seo-geo-theme';

/**
 * Emit one structured actionable failure and stop.
 *
 * @param mixed $expected Expected value.
 * @param mixed $received Actual value.
 */
function fail_publisher_preset( string $code, string $message, string $file_line, mixed $expected, mixed $received ): never {
	$signature = substr( hash( 'sha256', $code . ':' . $file_line . ':' . $message ), 0, 12 );

	echo json_encode(
		array(
			'schema_version'    => 1,
			'pipeline'          => getenv( 'GITHUB_WORKFLOW' ) ?: 'foundation',
			'run_id'            => getenv( 'GITHUB_RUN_ID' ) ?: 'local',
			'run_attempt'       => getenv( 'GITHUB_RUN_ATTEMPT' ) ?: '1',
			'job'               => getenv( 'GITHUB_JOB' ) ?: 'foundation',
			'step'              => 'publisher-preset-contract',
			'command'           => 'php scripts/ci/validate-publisher-preset.php',
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
 * Decode one required Publisher JSON document.
 *
 * @return array<string, mixed>
 */
function publisher_json( string $path ): array {
	if ( ! is_file( $path ) ) {
		fail_publisher_preset( 'missing-json', 'Publisher preset document is missing.', $path, 'file exists', 'missing' );
	}

	try {
		$value = json_decode( (string) file_get_contents( $path ), true, 512, JSON_THROW_ON_ERROR );
	} catch ( JsonException $exception ) {
		fail_publisher_preset( 'invalid-json', 'Publisher preset document is invalid JSON.', $path, 'valid JSON object', $exception->getMessage() );
	}

	if ( ! is_array( $value ) ) {
		fail_publisher_preset( 'json-object', 'Publisher preset document must decode to an object.', $path, 'JSON object', gettype( $value ) );
	}

	return $value;
}

/**
 * Extract neutral base pattern slugs.
 *
 * @return array<string, true>
 */
function publisher_base_patterns(): array {
	$files = glob( PUBLISHER_THEME_DIR . '/patterns/*.php' );
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
 * Return design tokens available to preset pattern content.
 *
 * @return array<string, array<string, true>>
 */
function publisher_theme_tokens(): array {
	$theme = publisher_json( PUBLISHER_THEME_DIR . '/theme.json' );
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

$manifest = publisher_json( PUBLISHER_PRESET_DIR . '/preset.json' );
$content  = publisher_json( PUBLISHER_PRESET_DIR . '/content-map.json' );
$patterns = publisher_json( PUBLISHER_PRESET_DIR . '/patterns.json' );

if ( 1 !== ( $manifest['schema_version'] ?? null ) || 'publisher' !== ( $manifest['id'] ?? null ) || 'publisher' !== ( $manifest['site_type'] ?? null ) ) {
	fail_publisher_preset( 'manifest-identity', 'Publisher manifest identity/version is invalid.', PUBLISHER_PRESET_DIR . '/preset.json', 'schema_version=1, id/site_type=publisher', $manifest );
}

$baseline_locales = $manifest['multilingual']['baseline_locales'] ?? null;
if ( ! is_array( $baseline_locales ) || array_values( $baseline_locales ) !== array( 'en_US', 'es_ES' ) ) {
	fail_publisher_preset( 'locales', 'Publisher preset must ship EN/ES together.', PUBLISHER_PRESET_DIR . '/preset.json', array( 'en_US', 'es_ES' ), $baseline_locales );
}

$schema = $manifest['schema'] ?? null;
if (
	! is_array( $schema )
	|| 'organization' !== ( $schema['site_identity'] ?? null )
	|| true !== ( $schema['requires_confirmation'] ?? null )
	|| 'wordpress-post' !== ( $schema['article_source'] ?? null )
	|| 'wordpress-user' !== ( $schema['author_source'] ?? null )
	|| 'wordpress-post-dates' !== ( $schema['dates_source'] ?? null )
) {
	fail_publisher_preset( 'schema-authority', 'Publisher must reuse explicit Organization plus native post/user/date authorities.', PUBLISHER_PRESET_DIR . '/preset.json', 'organization confirmation + WordPress post/user/dates', $schema );
}

$entities = is_array( $schema ) ? ( $schema['expected_entities'] ?? null ) : null;
foreach ( array( 'Organization', 'BlogPosting', 'Person', 'ProfilePage' ) as $entity ) {
	if ( ! is_array( $entities ) || ! in_array( $entity, $entities, true ) ) {
		fail_publisher_preset( 'schema-entity', 'Publisher expected Schema entities are incomplete.', PUBLISHER_PRESET_DIR . '/preset.json', $entity, $entities );
	}
}

$editorial = $manifest['editorial_model'] ?? null;
$editorial_expected = array(
	'built_in_post_is_article'            => true,
	'author_profiles_native'               => true,
	'publication_dates_native'             => true,
	'source_links_require_real_references' => true,
	'editorial_policy_required'            => true,
	'auto_generate_topics'                 => false,
	'infer_reviewed_by'                    => false,
	'infer_author_expertise'               => false,
	'infer_sources'                        => false,
	'article_schema_on_pages'              => false,
);
if ( ! is_array( $editorial ) ) {
	fail_publisher_preset( 'editorial-model', 'Publisher editorial model is missing.', PUBLISHER_PRESET_DIR . '/preset.json', $editorial_expected, $editorial );
}
foreach ( $editorial_expected as $key => $expected ) {
	if ( ( $editorial[ $key ] ?? null ) !== $expected ) {
		fail_publisher_preset( 'editorial-rule', 'Publisher editorial safety rule is invalid.', PUBLISHER_PRESET_DIR . '/preset.json#editorial_model.' . $key, $expected, $editorial[ $key ] ?? null );
	}
}

$disabled = $manifest['explicitly_disabled'] ?? null;
foreach (
	array(
		'automatic topic generation',
		'fabricated citations or source URLs',
		'invented author expertise or credentials',
		'automatic reviewed-by identity',
		'Article or BlogPosting Schema on WordPress pages',
		'publication or modification date guessing',
		'duplicate publisher/provenance metadata',
	) as $required_disabled
) {
	if ( ! is_array( $disabled ) || ! in_array( $required_disabled, $disabled, true ) ) {
		fail_publisher_preset( 'disabled-feature', 'Publisher must explicitly disable unsafe editorial inference.', PUBLISHER_PRESET_DIR . '/preset.json', $required_disabled, $disabled );
	}
}

$required_templates = $manifest['required_templates'] ?? null;
if ( ! is_array( $required_templates ) ) {
	fail_publisher_preset( 'templates', 'Publisher required_templates is missing.', PUBLISHER_PRESET_DIR . '/preset.json', 'template list', $required_templates );
}
foreach ( $required_templates as $template ) {
	if ( ! is_string( $template ) || ! is_file( PUBLISHER_THEME_DIR . '/templates/' . $template . '.html' ) ) {
		fail_publisher_preset( 'template-missing', 'Publisher references a missing base template.', PUBLISHER_PRESET_DIR . '/preset.json', 'existing theme template', $template );
	}
}

$base_patterns = publisher_base_patterns();
$recommended   = $manifest['recommended_base_patterns'] ?? null;
if ( ! is_array( $recommended ) ) {
	fail_publisher_preset( 'recommended-patterns', 'Publisher base pattern list is missing.', PUBLISHER_PRESET_DIR . '/preset.json', 'base pattern list', $recommended );
}
foreach ( $recommended as $slug ) {
	if ( ! is_string( $slug ) || ! isset( $base_patterns[ $slug ] ) ) {
		fail_publisher_preset( 'base-pattern', 'Publisher references a pattern outside the neutral theme contract.', PUBLISHER_PRESET_DIR . '/preset.json', 'known base pattern', $slug );
	}
}

$expected_preset_slugs = array(
	'seo-geo-theme/publisher-article-summary',
	'seo-geo-theme/publisher-key-facts',
	'seo-geo-theme/publisher-sources',
	'seo-geo-theme/publisher-related-content',
);
$declared_preset_slugs = $manifest['preset_patterns'] ?? null;
if ( ! is_array( $declared_preset_slugs ) || array_values( $declared_preset_slugs ) !== $expected_preset_slugs ) {
	fail_publisher_preset( 'preset-pattern-list', 'Publisher manifest pattern list is not canonical.', PUBLISHER_PRESET_DIR . '/preset.json', $expected_preset_slugs, $declared_preset_slugs );
}

$category = $patterns['category'] ?? null;
if ( ! is_array( $category ) || 'seo-geo-publisher' !== ( $category['slug'] ?? null ) ) {
	fail_publisher_preset( 'pattern-category', 'Publisher pattern category is invalid.', PUBLISHER_PRESET_DIR . '/patterns.json', 'seo-geo-publisher', $category );
}
$labels = is_array( $category ) ? ( $category['labels'] ?? null ) : null;
if ( ! is_array( $labels ) || ! isset( $labels['en_US'], $labels['es_ES'] ) ) {
	fail_publisher_preset( 'pattern-category-i18n', 'Publisher category must ship EN/ES labels.', PUBLISHER_PRESET_DIR . '/patterns.json', 'en_US + es_ES', $labels );
}

$pattern_rows = $patterns['patterns'] ?? null;
if ( ! is_array( $pattern_rows ) || 4 !== count( $pattern_rows ) ) {
	fail_publisher_preset( 'pattern-count', 'Publisher must ship exactly four preset-owned patterns in 7C.', PUBLISHER_PRESET_DIR . '/patterns.json', 4, is_array( $pattern_rows ) ? count( $pattern_rows ) : $pattern_rows );
}

$theme_tokens = publisher_theme_tokens();
$seen         = array();
foreach ( $pattern_rows as $index => $pattern ) {
	$file_line = PUBLISHER_PRESET_DIR . '/patterns.json#patterns[' . $index . ']';
	if ( ! is_array( $pattern ) || ! isset( $pattern['slug'] ) || ! is_string( $pattern['slug'] ) ) {
		fail_publisher_preset( 'pattern-slug', 'Publisher pattern slug is missing.', $file_line, 'namespaced slug', $pattern );
	}

	$slug = $pattern['slug'];
	if ( ! in_array( $slug, $expected_preset_slugs, true ) || isset( $seen[ $slug ] ) ) {
		fail_publisher_preset( 'pattern-slug-set', 'Publisher pattern slug is unexpected or duplicated.', $file_line, $expected_preset_slugs, $slug );
	}
	$seen[ $slug ] = true;

	foreach ( array( 'en_US', 'es_ES' ) as $locale ) {
		$localized = $pattern['locales'][ $locale ] ?? null;
		if ( ! is_array( $localized ) ) {
			fail_publisher_preset( 'pattern-locale', 'Publisher pattern is missing a required locale.', $file_line, $locale, $localized );
		}
		foreach ( array( 'title', 'description', 'content' ) as $field ) {
			if ( ! isset( $localized[ $field ] ) || ! is_string( $localized[ $field ] ) || '' === trim( $localized[ $field ] ) ) {
				fail_publisher_preset( 'pattern-field', 'Publisher localized pattern field is empty.', $file_line, $locale . '.' . $field, $localized[ $field ] ?? null );
			}
		}

		$markup = $localized['content'];
		if ( 1 === preg_match( '/<\s*(script|style)\b|<!--\s*wp:html\b|https?:\/\/|application\/ld\+json|schema\.org/i', $markup, $matches ) ) {
			fail_publisher_preset( 'unsafe-pattern-content', 'Publisher pattern contains forbidden embedded/remote/Schema content.', $file_line, 'native blocks without scripts/styles/remote URLs/Schema', $matches[0] );
		}
		if ( str_contains( $markup, '"level":1' ) || 1 === preg_match( '/<h1\b/i', $markup ) ) {
			fail_publisher_preset( 'pattern-h1', 'Publisher reusable patterns must not own the page H1.', $file_line, 'no H1', 'H1 found' );
		}
		if ( 1 === preg_match( '/#[0-9A-Fa-f]{3,8}\b/', $markup, $matches ) ) {
			fail_publisher_preset( 'pattern-color', 'Publisher pattern must use semantic theme colors.', $file_line, 'no raw hex color', $matches[0] );
		}
		if ( 1 === preg_match( '/\b[0-9]+(?:\.[0-9]+)?(?:px|rem|em|vw|vh)\b/i', $markup, $matches ) ) {
			fail_publisher_preset( 'pattern-size', 'Publisher pattern must use design-token sizes.', $file_line, 'no raw CSS size literal', $matches[0] );
		}

		preg_match_all( '/var:preset\|([a-z-]+)\|([a-z0-9-]+)/i', $markup, $token_matches, PREG_SET_ORDER );
		foreach ( $token_matches as $token_match ) {
			$type = strtolower( $token_match[1] );
			$name = strtolower( $token_match[2] );
			if ( ! isset( $theme_tokens[ $type ][ $name ] ) ) {
				fail_publisher_preset( 'pattern-token', 'Publisher pattern references an unknown theme token.', $file_line, 'theme.json token', $token_match[0] );
			}
		}
	}
}

$locales = $content['locales'] ?? null;
if ( ! is_array( $locales ) || ! isset( $locales['en_US'], $locales['es_ES'] ) ) {
	fail_publisher_preset( 'content-locales', 'Publisher content map must ship EN/ES together.', PUBLISHER_PRESET_DIR . '/content-map.json', 'en_US + es_ES', $locales );
}

$allowed_patterns    = array_fill_keys( array_merge( array_keys( $base_patterns ), $expected_preset_slugs ), true );
$page_keys_by_locale = array();
$dynamic_by_locale   = array();
$dynamic_sources     = array(
	'article'        => 'wordpress-post',
	'topic-archive'  => 'wordpress-category',
	'author-profile' => 'wordpress-author',
);

foreach ( array( 'en_US', 'es_ES' ) as $locale ) {
	$pages = $locales[ $locale ]['pages'] ?? null;
	if ( ! is_array( $pages ) || 10 !== count( $pages ) ) {
		fail_publisher_preset( 'page-count', 'Publisher must ship ten singleton pages per locale.', PUBLISHER_PRESET_DIR . '/content-map.json', 10, is_array( $pages ) ? count( $pages ) : $pages );
	}

	$keys  = array();
	$slugs = array();
	$roles = array();
	foreach ( $pages as $page ) {
		if ( ! is_array( $page ) ) {
			fail_publisher_preset( 'page-row', 'Publisher page row is invalid.', PUBLISHER_PRESET_DIR . '/content-map.json', 'page object', $page );
		}
		foreach ( array( 'key', 'title', 'slug', 'role', 'template' ) as $field ) {
			if ( ! isset( $page[ $field ] ) || ! is_string( $page[ $field ] ) || '' === trim( $page[ $field ] ) ) {
				fail_publisher_preset( 'page-field', 'Publisher page field is missing.', PUBLISHER_PRESET_DIR . '/content-map.json', $locale . '.' . $field, $page[ $field ] ?? null );
			}
		}
		if ( isset( $keys[ $page['key'] ] ) || isset( $slugs[ $page['slug'] ] ) ) {
			fail_publisher_preset( 'page-duplicate', 'Publisher page keys/slugs must be unique per locale.', PUBLISHER_PRESET_DIR . '/content-map.json', 'unique keys/slugs', $page );
		}
		$keys[ $page['key'] ] = true;
		$slugs[ $page['slug'] ] = true;
		$roles[ $page['role'] ] = ( $roles[ $page['role'] ] ?? 0 ) + 1;

		$page_patterns = $page['patterns'] ?? array();
		if ( ! is_array( $page_patterns ) ) {
			fail_publisher_preset( 'page-patterns', 'Publisher page composition must be a pattern list.', PUBLISHER_PRESET_DIR . '/content-map.json', 'pattern list', $page_patterns );
		}
		foreach ( $page_patterns as $slug ) {
			if ( ! is_string( $slug ) || ! isset( $allowed_patterns[ $slug ] ) ) {
				fail_publisher_preset( 'page-pattern-reference', 'Publisher content map references an unknown pattern.', PUBLISHER_PRESET_DIR . '/content-map.json', 'base or Publisher pattern', $slug );
			}
		}
	}

	if ( 1 !== ( $roles['front-page'] ?? 0 ) || 1 !== ( $roles['posts-page'] ?? 0 ) || 1 !== ( $roles['editorial-policy'] ?? 0 ) ) {
		fail_publisher_preset( 'page-authority', 'Publisher must define one front page, posts page and editorial policy per locale.', PUBLISHER_PRESET_DIR . '/content-map.json', 'one front-page + posts-page + editorial-policy', $roles );
	}

	$page_keys_by_locale[ $locale ] = array_keys( $keys );
	sort( $page_keys_by_locale[ $locale ] );

	$dynamic = $locales[ $locale ]['dynamic'] ?? null;
	if ( ! is_array( $dynamic ) || 3 !== count( $dynamic ) ) {
		fail_publisher_preset( 'dynamic-count', 'Publisher must model article, topic archive and author profile as native dynamic surfaces.', PUBLISHER_PRESET_DIR . '/content-map.json', 3, is_array( $dynamic ) ? count( $dynamic ) : $dynamic );
	}

	$dynamic_keys = array();
	foreach ( $dynamic as $surface ) {
		if ( ! is_array( $surface ) || ! isset( $surface['key'], $surface['source'], $surface['template'] ) || ! is_string( $surface['key'] ) || ! is_string( $surface['source'] ) || ! is_string( $surface['template'] ) ) {
			fail_publisher_preset( 'dynamic-row', 'Publisher dynamic surface is invalid.', PUBLISHER_PRESET_DIR . '/content-map.json', 'key/source/template strings', $surface );
		}

		$key = $surface['key'];
		$dynamic_keys[] = $key;
		if ( ! isset( $dynamic_sources[ $key ] ) || $dynamic_sources[ $key ] !== $surface['source'] ) {
			fail_publisher_preset( 'dynamic-source', 'Publisher dynamic surface uses the wrong WordPress authority.', PUBLISHER_PRESET_DIR . '/content-map.json', $dynamic_sources[ $key ] ?? 'known surface', $surface['source'] );
		}
		if ( ! is_file( PUBLISHER_THEME_DIR . '/templates/' . $surface['template'] . '.html' ) ) {
			fail_publisher_preset( 'dynamic-template', 'Publisher dynamic surface references a missing theme template.', PUBLISHER_PRESET_DIR . '/content-map.json', 'existing theme template', $surface['template'] );
		}

		$requirements = $surface['content_requirements'] ?? null;
		if ( ! is_array( $requirements ) || count( $requirements ) < 4 ) {
			fail_publisher_preset( 'dynamic-value', 'Publisher dynamic surfaces must declare real editorial requirements.', PUBLISHER_PRESET_DIR . '/content-map.json', 'at least four requirements', $requirements );
		}

		$surface_patterns = $surface['recommended_patterns'] ?? null;
		if ( ! is_array( $surface_patterns ) ) {
			fail_publisher_preset( 'dynamic-patterns', 'Publisher dynamic pattern guidance is missing.', PUBLISHER_PRESET_DIR . '/content-map.json', 'pattern list', $surface_patterns );
		}
		foreach ( $surface_patterns as $slug ) {
			if ( ! is_string( $slug ) || ! isset( $allowed_patterns[ $slug ] ) ) {
				fail_publisher_preset( 'dynamic-pattern-reference', 'Publisher dynamic surface references an unknown pattern.', PUBLISHER_PRESET_DIR . '/content-map.json', 'base or Publisher pattern', $slug );
			}
		}
	}

	sort( $dynamic_keys );
	$dynamic_by_locale[ $locale ] = $dynamic_keys;
}

if ( $page_keys_by_locale['en_US'] !== $page_keys_by_locale['es_ES'] ) {
	fail_publisher_preset( 'page-key-parity', 'Publisher EN/ES maps must describe the same singleton pages.', PUBLISHER_PRESET_DIR . '/content-map.json', $page_keys_by_locale['en_US'], $page_keys_by_locale['es_ES'] );
}

$expected_dynamic = array( 'article', 'author-profile', 'topic-archive' );
if ( $dynamic_by_locale['en_US'] !== $expected_dynamic || $dynamic_by_locale['es_ES'] !== $expected_dynamic ) {
	fail_publisher_preset( 'dynamic-parity', 'Publisher EN/ES maps must share the native article/topic/author model.', PUBLISHER_PRESET_DIR . '/content-map.json', $expected_dynamic, $dynamic_by_locale );
}

printf( "Publisher preset contract OK: native editorial authorities, 4 bilingual patterns and EN/ES content maps pass.\n" );
