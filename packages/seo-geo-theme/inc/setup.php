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
require_once __DIR__ . '/Setup/SetupOptionWriterInterface.php';
require_once __DIR__ . '/Setup/WordPressSetupOptionWriter.php';
require_once __DIR__ . '/Setup/SetupReportStore.php';
require_once __DIR__ . '/Setup/SetupRewriteMaintenance.php';
require_once __DIR__ . '/Setup/SetupWriteFailure.php';
require_once __DIR__ . '/Setup/SetupExecutor.php';
require_once __DIR__ . '/Wizard/SetupWizardCopy.php';
require_once __DIR__ . '/Wizard/SetupWizardPreview.php';
require_once __DIR__ . '/Wizard/AdminSetupWizard.php';

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

/**
 * Validate and atomically apply one complete setup candidate.
 *
 * @param array<string,mixed> $input     Explicit setup values.
 * @param bool                $confirmed Explicit save acknowledgement.
 * @return array<string,mixed>
 */
function seo_geo_theme_apply_setup( array $input, bool $confirmed ): array {
	return ( new \SeoGeo\Theme\Setup\SetupExecutor() )->execute( $input, $confirmed );
}

/**
 * Return the latest non-sensitive setup report.
 *
 * @return array<string,mixed>|null
 */
function seo_geo_theme_setup_report(): ?array {
	return ( new \SeoGeo\Theme\Setup\SetupReportStore() )->latest();
}

/**
 * Return the theme-owned setup wizard singleton.
 */
function seo_geo_theme_setup_wizard(): \SeoGeo\Theme\Wizard\AdminSetupWizard {
	static $wizard = null;

	if ( ! $wizard instanceof \SeoGeo\Theme\Wizard\AdminSetupWizard ) {
		$wizard = new \SeoGeo\Theme\Wizard\AdminSetupWizard();
	}

	return $wizard;
}

seo_geo_theme_setup_wizard()->register();
( new \SeoGeo\Theme\Setup\SetupRewriteMaintenance() )->register();
