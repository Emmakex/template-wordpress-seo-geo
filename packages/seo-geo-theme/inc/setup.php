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

use SeoGeo\Theme\Setup\SetupPlanner;

/**
 * Return one fresh read-only setup plan.
 *
 * @return array<string,mixed>
 */
function seo_geo_theme_setup_plan(): array {
	return ( new SetupPlanner() )->plan();
}
