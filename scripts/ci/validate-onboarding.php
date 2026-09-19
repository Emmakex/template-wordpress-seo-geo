<?php
/**
 * Validate the Phase 8A onboarding foundation contract.
 */

declare(strict_types=1);

const ONBOARDING_MODULE = 'packages/seo-geo-theme/inc/onboarding.php';
const ONBOARDING_BOOT   = 'packages/seo-geo-theme/functions.php';

/**
 * Emit one structured actionable failure and stop.
 *
 * @param mixed $expected Expected value.
 * @param mixed $received Actual value.
 */
function fail_onboarding_contract( string $code, string $message, string $file_line, mixed $expected, mixed $received ): never {
	$signature = substr( hash( 'sha256', $code . ':' . $file_line . ':' . $message ), 0, 12 );

	echo json_encode(
		array(
			'schema_version'    => 1,
			'pipeline'          => getenv( 'GITHUB_WORKFLOW' ) ?: 'foundation',
			'run_id'            => getenv( 'GITHUB_RUN_ID' ) ?: 'local',
			'run_attempt'       => getenv( 'GITHUB_RUN_ATTEMPT' ) ?: '1',
			'job'               => getenv( 'GITHUB_JOB' ) ?: 'foundation',
			'step'              => 'onboarding-contract',
			'command'           => 'php scripts/ci/validate-onboarding.php',
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

$required_paths = array(
	ONBOARDING_MODULE,
	'scripts/ci/onboarding-preset-acceptance.sh',
	'docs/ONBOARDING.md',
);

foreach ( $required_paths as $path ) {
	if ( ! is_file( $path ) ) {
		fail_onboarding_contract( 'missing-path', 'Phase 8A onboarding path is missing.', $path, 'file exists', 'missing' );
	}
}

$source = (string) file_get_contents( ONBOARDING_MODULE );
$boot   = is_file( ONBOARDING_BOOT ) ? (string) file_get_contents( ONBOARDING_BOOT ) : '';

$required_fragments = array(
	"add_theme_page(",
	"'manage_options'",
	"check_admin_referer( 'seo_geo_onboarding_preset'",
	"add_action( 'admin_post_seo_geo_save_preset'",
	"'seo_geo_active_preset'",
	'seo_geo_theme_preset_ids()',
	'seo_geo_theme_preset_document(',
	'get_user_locale()',
	"'en_US'",
	"'es_ES'",
	"admin_url( 'admin-post.php' )",
	'sanitize_text_field(',
	'wp_unslash(',
	'wp_safe_redirect(',
);

foreach ( $required_fragments as $fragment ) {
	if ( ! str_contains( $source, $fragment ) ) {
		fail_onboarding_contract( 'missing-authority', 'Onboarding is missing a required authority/security primitive.', ONBOARDING_MODULE, $fragment, 'missing' );
	}
}

if ( ! str_contains( $boot, "require_once get_template_directory() . '/inc/onboarding.php';" ) ) {
	fail_onboarding_contract( 'bootstrap', 'Theme bootstrap does not load the onboarding module.', ONBOARDING_BOOT, 'onboarding.php required', 'missing' );
}

$forbidden_fragments = array(
	'plugins_api(',
	'activate_plugin(',
	'install_plugin_install_status(',
	'wp_ajax_',
	"update_option( 'seo_geo_onboarding",
	"add_option( 'seo_geo_onboarding",
	'<script',
	'<style',
);

foreach ( $forbidden_fragments as $fragment ) {
	if ( str_contains( $source, $fragment ) ) {
		fail_onboarding_contract( 'forbidden-behavior', 'Onboarding introduced a forbidden dependency, authority or duplicate state.', ONBOARDING_MODULE, 'fragment absent', $fragment );
	}
}

foreach ( array( 'corporate', 'local-business', 'publisher', 'ecommerce' ) as $preset_id ) {
	if ( str_contains( $source, "update_option( 'seo_geo_active_preset', '" . $preset_id . "'" ) ) {
		fail_onboarding_contract( 'hardcoded-preset-write', 'Onboarding must use the shared preset allowlist instead of hardcoded preset writes.', ONBOARDING_MODULE, 'shared allowlist', $preset_id );
	}
}

if ( substr_count( $source, "current_user_can( 'manage_options' )" ) < 2 ) {
	fail_onboarding_contract( 'capability-checks', 'Onboarding must authorize both save and render paths server-side.', ONBOARDING_MODULE, 'at least two manage_options checks', substr_count( $source, "current_user_can( 'manage_options' )" ) );
}

if ( ! str_contains( $source, "return str_starts_with( strtolower( $locale ), 'es' ) ? 'es_ES' : 'en_US';" ) ) {
	fail_onboarding_contract( 'admin-locale', 'Onboarding EN/ES copy must resolve from the current admin-user locale with English fallback.', ONBOARDING_MODULE, 'ES/EN admin locale resolver', 'missing' );
}

printf( "Phase 8A onboarding contract OK: server authority, EN/ES copy, shared preset ownership and zero-plugin safety pass.\n" );
