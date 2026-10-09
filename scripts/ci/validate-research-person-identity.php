<?php
/**
 * Validate the Research preset Person identity contract.
 *
 * @package SeoGeoTheme
 */

declare(strict_types=1);

$root = dirname( __DIR__, 2 );

$identity_path  = $root . '/packages/seo-geo-core/src/Schema/SchemaIdentityResolver.php';
$graph_path     = $root . '/packages/seo-geo-core/src/Schema/SchemaGraphBuilder.php';
$validator_path = $root . '/packages/seo-geo-theme/inc/Setup/EntityGeoValidator.php';
$executor_path  = $root . '/packages/seo-geo-theme/inc/Setup/SetupExecutor.php';
$wizard_path    = $root . '/packages/seo-geo-theme/inc/Wizard/AdminSetupWizard.php';
$copy_path      = $root . '/packages/seo-geo-theme/inc/Wizard/SetupWizardCopy.php';
$preset_path    = $root . '/presets/research/preset.json';

$required_paths = array(
	$identity_path,
	$graph_path,
	$validator_path,
	$executor_path,
	$wizard_path,
	$copy_path,
	$preset_path,
);

foreach ( $required_paths as $path ) {
	if ( ! is_file( $path ) ) {
		fwrite( STDERR, 'Research Person identity required path missing: ' . $path . PHP_EOL );
		exit( 1 );
	}
}

$identity = (string) file_get_contents( $identity_path );
foreach (
	array(
		"public const SITE_ENTITY_PERSON = 'person';",
		'public function site_person(): ?array',
		"'person'",
		"'same_as'",
		'$this->ids->person( $home_url )',
	) as $guard
) {
	if ( ! str_contains( $identity, $guard ) ) {
		fwrite( STDERR, 'Research Person identity resolver guard missing: ' . $guard . PHP_EOL );
		exit( 1 );
	}
}

$graph = (string) file_get_contents( $graph_path );
foreach (
	array(
		'$site_person    = $this->identity->site_person();',
		"\$web_page['@type']      = 'ProfilePage';",
		"\$person['sameAs'] = \$same_as;",
		"\$article_node['publisher'] = array( '@id' => \$site_person['id'] );",
	) as $guard
) {
	if ( ! str_contains( $graph, $guard ) ) {
		fwrite( STDERR, 'Research Person graph guard missing: ' . $guard . PHP_EOL );
		exit( 1 );
	}
}

$validator = (string) file_get_contents( $validator_path );
foreach (
	array(
		'person-configuration-required',
		'person-name-required',
		'person-name-too-long',
		'person-same-as-limit-exceeded',
		'person-same-as-invalid',
		"'credentials_inferred'     => false",
		"'affiliation_inferred'     => false",
	) as $guard
) {
	if ( ! str_contains( $validator, $guard ) ) {
		fwrite( STDERR, 'Research Person validation guard missing: ' . $guard . PHP_EOL );
		exit( 1 );
	}
}

$executor = (string) file_get_contents( $executor_path );
foreach (
	array(
		"\$identity_configuration['person'] = \$person;",
		"'person_configured'",
		"'person_facts_in_report'         => false",
	) as $guard
) {
	if ( ! str_contains( $executor, $guard ) ) {
		fwrite( STDERR, 'Research Person persistence guard missing: ' . $guard . PHP_EOL );
		exit( 1 );
	}
}

$wizard = (string) file_get_contents( $wizard_path );
foreach (
	array(
		'SchemaIdentityResolver::SITE_ENTITY_PERSON',
		'seo_geo_person_name',
		'seo_geo_person_description',
		'seo_geo_person_same_as',
		'wp_verify_nonce',
		"current_user_can( 'manage_options' )",
	) as $guard
) {
	if ( ! str_contains( $wizard, $guard ) ) {
		fwrite( STDERR, 'Research Person setup wizard guard missing: ' . $guard . PHP_EOL );
		exit( 1 );
	}
}

$copy = (string) file_get_contents( $copy_path );
foreach ( array( "'entity_person'", "'person_name'", "'person_same_as'" ) as $guard ) {
	if ( ! str_contains( $copy, $guard ) ) {
		fwrite( STDERR, 'Research Person wizard copy guard missing: ' . $guard . PHP_EOL );
		exit( 1 );
	}
}

$preset_json = file_get_contents( $preset_path );
$preset      = is_string( $preset_json ) ? json_decode( $preset_json, true ) : null;

if ( ! is_array( $preset ) ) {
	fwrite( STDERR, 'Research preset JSON is invalid.' . PHP_EOL );
	exit( 1 );
}

$schema         = is_array( $preset['schema'] ?? null ) ? $preset['schema'] : array();
$research_model = is_array( $preset['research_model'] ?? null ) ? $preset['research_model'] : array();

if ( 'research' !== ( $preset['id'] ?? null ) ) {
	fwrite( STDERR, 'Research preset id contract is invalid.' . PHP_EOL );
	exit( 1 );
}

if ( true !== ( $preset['setup_ready'] ?? false ) ) {
	fwrite( STDERR, 'Research preset must remain hidden until Person setup is complete.' . PHP_EOL );
	exit( 1 );
}

if ( 'person' !== ( $schema['site_identity'] ?? null ) || true !== ( $schema['requires_confirmation'] ?? false ) ) {
	fwrite( STDERR, 'Research preset Person identity contract is invalid.' . PHP_EOL );
	exit( 1 );
}

foreach (
	array(
		'auto_infer_credentials',
		'auto_infer_affiliation',
		'auto_infer_peer_review',
		'auto_infer_identifiers',
		'auto_infer_citation_metrics',
	) as $anti_inference_key
) {
	if ( false !== ( $research_model[ $anti_inference_key ] ?? null ) ) {
		fwrite( STDERR, 'Research preset anti-inference contract failed: ' . $anti_inference_key . PHP_EOL );
		exit( 1 );
	}
}

fwrite( STDOUT, 'Research Person identity contract OK.' . PHP_EOL );
