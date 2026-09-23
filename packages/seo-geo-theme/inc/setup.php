<?php
/**
 * Theme setup foundation bootstrap.
 *
 * @package SeoGeoTheme
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/Setup/SetupConfigurationContract.php';
require_once __DIR__ . '/Setup/MigrationHandoffReader.php';
require_once __DIR__ . '/Setup/SetupCompatibilityDetector.php';
require_once __DIR__ . '/Setup/SetupPlanner.php';
require_once __DIR__ . '/Setup/PresetLanguageValidator.php';
require_once __DIR__ . '/Setup/EntityGeoValidator.php';

use SeoGeo\Theme\Setup\SetupPlanner;

/**
 * Return one fresh read-only setup plan.
 *
 * @return array<string,mixed>
 */
function seo_geo_theme_setup_plan(): array {
	return ( new \SeoGeo\Theme\Setup\SetupPlanner() )->plan();
}

/**
 * Validate explicit preset and native-language choices without persisting them.
 *
 * @param array<string,mixed> $input Candidate setup values.
 * @return array<string,mixed>
 */
function seo_geo_theme_validate_preset_language_setup( array $input ): array {
	return ( new \SeoGeo\Theme\Setup\PresetLanguageValidator() )->validate( $input );
}

/**
 * Validate explicit site-entity and GEO/discovery choices without persistence.
 *
 * @param array<string,mixed> $input Candidate setup values.
 * @return array<string,mixed>
 */
function seo_geo_theme_validate_entity_geo_setup( array $input ): array {
	return ( new \SeoGeo\Theme\Setup\EntityGeoValidator() )->validate( $input );
}
