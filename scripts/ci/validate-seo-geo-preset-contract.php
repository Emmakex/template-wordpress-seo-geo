<?php
/**
 * Validate the shared SEO/GEO-first contract across every preset.
 */

declare(strict_types=1);

const SEO_GEO_PRESET_IDS = array(
	'corporate',
	'local-business',
	'publisher',
	'ecommerce',
	'saas-digital-product',
);

const SEO_GEO_PRESET_EXPECTED_RUNTIME = array(
	'html_delivery'                       => 'semantic-server-rendered',
	'primary_navigation'                  => 'crawlable-html-anchors',
	'javascript_policy'                   => 'zero-by-default',
	'canonical_authority'                 => 'shared-core-or-declared-provider',
	'indexability_authority'              => 'shared-core',
	'hreflang_authority'                  => 'shared-core',
	'schema_authority'                    => 'shared-core-or-declared-provider',
	'single_h1_required'                  => true,
	'internal_links'                      => 'crawlable-html-anchors',
	'structured_data_visible_fact_gate'   => true,
	'llms_txt'                            => 'optional-indexable-content-only',
	'markdown_alternates'                 => 'optional-noncanonical',
	'content_provenance'                  => 'required-when-applicable',
	'responsive_images'                   => 'wordpress-srcset-sizes',
	'below_fold_media'                    => 'lazy',
	'lcp_media'                           => 'not-lazy-loaded',
	'performance_profile'                 => 'core-web-vitals-strict',
);

/**
 * Fail with a structured CI diagnostic.
 *
 * @param mixed $expected Expected value.
 * @param mixed $received Received value.
 */
function fail_seo_geo_preset_contract( string $code, string $message, string $file_line, mixed $expected, mixed $received ): never {
	$signature = substr( hash( 'sha256', $code . ':' . $file_line . ':' . $message ), 0, 12 );

	echo json_encode(
		array(
			'schema_version'    => 1,
			'pipeline'          => getenv( 'GITHUB_WORKFLOW' ) ?: 'foundation',
			'run_id'            => getenv( 'GITHUB_RUN_ID' ) ?: 'local',
			'run_attempt'       => getenv( 'GITHUB_RUN_ATTEMPT' ) ?: '1',
			'job'               => getenv( 'GITHUB_JOB' ) ?: 'foundation',
			'step'              => 'seo-geo-first-preset-contract',
			'command'           => 'php scripts/ci/validate-seo-geo-preset-contract.php',
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
 * Decode one required JSON object.
 *
 * @return array<string,mixed>
 */
function seo_geo_preset_json( string $path ): array {
	if ( ! is_file( $path ) ) {
		fail_seo_geo_preset_contract( 'missing-json', 'Required SEO/GEO preset contract document is missing.', $path, 'file exists', 'missing' );
	}

	try {
		$value = json_decode( (string) file_get_contents( $path ), true, 512, JSON_THROW_ON_ERROR );
	} catch ( JsonException $exception ) {
		fail_seo_geo_preset_contract( 'invalid-json', 'SEO/GEO preset contract document is invalid JSON.', $path, 'valid JSON object', $exception->getMessage() );
	}

	if ( ! is_array( $value ) ) {
		fail_seo_geo_preset_contract( 'json-object', 'SEO/GEO preset contract document must decode to an object.', $path, 'JSON object', gettype( $value ) );
	}

	return $value;
}

foreach ( SEO_GEO_PRESET_IDS as $preset_id ) {
	$path     = 'presets/' . $preset_id . '/preset.json';
	$manifest = seo_geo_preset_json( $path );
	$runtime  = $manifest['seo_geo_runtime'] ?? null;

	if ( ! is_array( $runtime ) ) {
		fail_seo_geo_preset_contract( 'runtime-missing', 'Preset does not declare the shared SEO/GEO runtime contract.', $path, SEO_GEO_PRESET_EXPECTED_RUNTIME, $runtime );
	}

	foreach ( SEO_GEO_PRESET_EXPECTED_RUNTIME as $key => $expected ) {
		$received = $runtime[ $key ] ?? null;
		if ( $received !== $expected ) {
			fail_seo_geo_preset_contract( 'runtime-value', 'Preset weakens or diverges from the shared SEO/GEO runtime contract.', $path . '#seo_geo_runtime.' . $key, $expected, $received );
		}
	}

	$seo_acceptance = $manifest['seo_acceptance'] ?? null;
	if ( ! is_array( $seo_acceptance ) || 4 > count( $seo_acceptance ) ) {
		fail_seo_geo_preset_contract( 'seo-acceptance', 'Preset must keep an explicit SEO acceptance list.', $path . '#seo_acceptance', 'at least four acceptance rules', $seo_acceptance );
	}

	$navigation = $manifest['navigation']['primary'] ?? null;
	if ( ! is_array( $navigation ) || array() === $navigation ) {
		fail_seo_geo_preset_contract( 'primary-navigation', 'Preset must declare crawlable primary navigation.', $path . '#navigation.primary', 'non-empty primary navigation list', $navigation );
	}
}

$budgets = seo_geo_preset_json( 'tests/performance/budgets.json' );
$global  = $budgets['global'] ?? null;
if (
	! is_array( $global )
	|| 0 !== ( $global['maxThirdPartyRequests'] ?? null )
	|| 0 !== ( $global['maxProjectJavaScriptBytes'] ?? null )
) {
	fail_seo_geo_preset_contract(
		'performance-global',
		'Global performance baseline must remain zero-third-party and zero-project-JS by default.',
		'tests/performance/budgets.json#global',
		array( 'maxThirdPartyRequests' => 0, 'maxProjectJavaScriptBytes' => 0 ),
		$global
	);
}

$pages = $budgets['pages'] ?? null;
if ( ! is_array( $pages ) ) {
	fail_seo_geo_preset_contract( 'performance-pages', 'Performance page budgets are missing.', 'tests/performance/budgets.json#pages', 'page budgets', $pages );
}

foreach ( $pages as $page_key => $page_budget ) {
	if ( ! is_array( $page_budget ) ) {
		fail_seo_geo_preset_contract( 'performance-page-row', 'Performance page budget is invalid.', 'tests/performance/budgets.json#pages.' . $page_key, 'budget object', $page_budget );
	}

	if (
		( $page_budget['minPerformanceScore'] ?? 0 ) < 95
		|| ( $page_budget['maxLcpMs'] ?? PHP_INT_MAX ) > 1200
		|| ( $page_budget['maxCls'] ?? 1 ) > 0.05
		|| ( $page_budget['maxTbtMs'] ?? PHP_INT_MAX ) > 100
	) {
		fail_seo_geo_preset_contract(
			'performance-budget',
			'Performance baseline is weaker than the SEO/GEO-first product contract.',
			'tests/performance/budgets.json#pages.' . $page_key,
			'performance>=95, LCP<=1200ms, CLS<=0.05, TBT<=100ms',
			$page_budget
		);
	}
}

$semantic_templates = array(
	'packages/seo-geo-theme/templates/page.html',
	'packages/seo-geo-theme/templates/single.html',
);
foreach ( $semantic_templates as $template_path ) {
	$source = is_file( $template_path ) ? (string) file_get_contents( $template_path ) : '';
	if (
		'' === $source
		|| ! str_contains( $source, '"tagName":"main"' )
		|| ! str_contains( $source, '"level":1' )
	) {
		fail_seo_geo_preset_contract(
			'semantic-template',
			'Shared public templates must preserve a main landmark and page-owned H1.',
			$template_path,
			'main landmark + level 1 post title',
			$source
		);
	}
}

$header = is_file( 'packages/seo-geo-theme/parts/header.html' )
	? (string) file_get_contents( 'packages/seo-geo-theme/parts/header.html' )
	: '';
if ( ! str_contains( $header, 'seo-geo/preset-navigation' ) ) {
	fail_seo_geo_preset_contract(
		'crawlable-navigation-runtime',
		'Shared header must use the preset navigation runtime rather than JavaScript-only discovery.',
		'packages/seo-geo-theme/parts/header.html',
		'seo-geo/preset-navigation block',
		$header
	);
}

printf(
	"SEO/GEO-first preset contract OK: 5 presets preserve semantic server HTML, shared SEO/Schema authority, crawlable navigation, optional safe GEO alternates and strict performance budgets.\n"
);
