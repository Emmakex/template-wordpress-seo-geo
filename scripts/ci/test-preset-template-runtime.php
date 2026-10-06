<?php
/**
 * Executable isolation/fallback test for PresetTemplateRuntime.
 */

declare(strict_types=1);

use SeoGeo\Theme\Templates\PresetTemplateRuntime;

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/fixture-wordpress/' );
}

/** Minimal WordPress block-template fixture. */
class WP_Block_Template {
	public string $source = 'theme';
	public string $slug = 'single';
	public string $content = 'neutral-template';
}

$GLOBALS['seo_geo_runtime_test_preset']       = 'saas-digital-product';
$GLOBALS['seo_geo_runtime_test_template_dir'] = dirname( __DIR__, 2 ) . '/packages/seo-geo-theme';

/** No-op filter registration fixture. */
function add_filter( string $hook_name, callable $callback, int $priority = 10, int $accepted_args = 1 ): bool {
	unset( $hook_name, $callback, $priority, $accepted_args );
	return true;
}

/** Active-preset fixture. */
function seo_geo_theme_active_preset_id(): ?string {
	$value = $GLOBALS['seo_geo_runtime_test_preset'] ?? null;
	return is_string( $value ) ? $value : null;
}

/** Theme-path fixture. */
function get_template_directory(): string {
	$value = $GLOBALS['seo_geo_runtime_test_template_dir'] ?? '';
	return is_string( $value ) ? $value : '';
}

function determine_locale(): string {
	return 'en_US';
}

function get_locale(): string {
	return 'en_US';
}

function esc_html( string $value ): string {
	return htmlspecialchars( $value, ENT_QUOTES, 'UTF-8' );
}

function esc_url( string $value ): string {
	return $value;
}

function home_url( string $path = '/' ): string {
	return 'https://example.test' . $path;
}

require_once dirname( __DIR__, 2 ) . '/packages/seo-geo-theme/inc/Templates/PresetTemplateRuntime.php';

/**
 * Fail test with an actionable message.
 */
function runtime_test_fail( string $message ): never {
	fwrite( STDERR, $message . "\n" );
	exit( 1 );
}

/**
 * Build one block-template fixture.
 */
function runtime_test_template( string $slug, string $source = 'theme', string $content = 'neutral-template' ): WP_Block_Template {
	$template          = new WP_Block_Template();
	$template->slug    = $slug;
	$template->source  = $source;
	$template->content = $content;
	return $template;
}

$runtime = new PresetTemplateRuntime();
$runtime->register();

foreach ( array( 'single', 'archive', '404' ) as $slug ) {
	$GLOBALS['seo_geo_runtime_test_preset'] = 'saas-digital-product';
	$original = runtime_test_template( $slug );
	$result   = $runtime->filter_template( $original, 'seo-geo-theme//' . $slug, 'wp_template' );
	if ( ! $result instanceof WP_Block_Template || $result === $original || ! str_contains( $result->content, 'seo-geo-saas-system-surface' ) ) {
		runtime_test_fail( 'SaaS did not receive its preset-owned ' . $slug . ' template.' );
	}
}

foreach ( array( 'single', 'archive', '404' ) as $slug ) {
	$GLOBALS['seo_geo_runtime_test_preset'] = 'local-business';
	$original = runtime_test_template( $slug );
	$result   = $runtime->filter_template( $original, 'seo-geo-theme//' . $slug, 'wp_template' );
	if ( ! $result instanceof WP_Block_Template || $result === $original || ! str_contains( $result->content, 'seo-geo-local-system-surface' ) ) {
		runtime_test_fail( 'Local Pro did not receive its preset-owned ' . $slug . ' template.' );
	}
}

$GLOBALS['seo_geo_runtime_test_preset'] = 'corporate';
$corporate_original = runtime_test_template( 'single' );
$corporate_result   = $runtime->filter_template( $corporate_original, 'seo-geo-theme//single', 'wp_template' );
if ( ! $corporate_result instanceof WP_Block_Template || $corporate_result === $corporate_original || ! str_contains( $corporate_result->content, 'seo-geo-corporate-system-surface' ) ) {
	runtime_test_fail( 'Corporate runtime behavior regressed while adding Local Pro support.' );
}

$GLOBALS['seo_geo_runtime_test_preset'] = 'publisher';
$neutral_original = runtime_test_template( 'single', 'theme', 'keep-neutral' );
$neutral_result   = $runtime->filter_template( $neutral_original, 'seo-geo-theme//single', 'wp_template' );
if ( $neutral_result !== $neutral_original || 'keep-neutral' !== $neutral_result->content ) {
	runtime_test_fail( 'A preset without final system templates did not keep the neutral Theme template.' );
}

$GLOBALS['seo_geo_runtime_test_preset'] = 'local-business';
$custom_original = runtime_test_template( 'single', 'custom', 'site-editor-custom' );
$custom_result   = $runtime->filter_template( $custom_original, 'seo-geo-theme//single', 'wp_template' );
if ( $custom_result !== $custom_original || 'site-editor-custom' !== $custom_result->content ) {
	runtime_test_fail( 'Site Editor custom template was overridden by the Local Pro preset runtime.' );
}

$type_original = runtime_test_template( 'single', 'theme', 'wrong-type-neutral' );
$type_result   = $runtime->filter_template( $type_original, 'seo-geo-theme//single', 'wp_template_part' );
if ( $type_result !== $type_original || 'wrong-type-neutral' !== $type_result->content ) {
	runtime_test_fail( 'Preset runtime modified a non-template object type.' );
}

$slug_original = runtime_test_template( 'page', 'theme', 'unsupported-slug-neutral' );
$slug_result   = $runtime->filter_template( $slug_original, 'seo-geo-theme//page', 'wp_template' );
if ( $slug_result !== $slug_original || 'unsupported-slug-neutral' !== $slug_result->content ) {
	runtime_test_fail( 'Preset runtime modified a template slug outside its allowlist.' );
}

$GLOBALS['seo_geo_runtime_test_template_dir'] = sys_get_temp_dir() . '/seo-geo-missing-theme';
$missing_original = runtime_test_template( 'single', 'theme', 'missing-file-neutral' );
$missing_result   = $runtime->filter_template( $missing_original, 'seo-geo-theme//single', 'wp_template' );
if ( $missing_result !== $missing_original || 'missing-file-neutral' !== $missing_result->content ) {
	runtime_test_fail( 'Missing preset template did not fail safe to the neutral Theme template.' );
}

echo "Preset template runtime isolation/fallback: OK\n";
