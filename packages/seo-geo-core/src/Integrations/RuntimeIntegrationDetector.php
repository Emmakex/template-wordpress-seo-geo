<?php
/**
 * Runtime integration detector.
 *
 * Detection is informational in Phase 1. Provider delegation is implemented
 * in later phases so no output ownership changes yet.
 *
 * @package SeoGeoCore
 */

declare(strict_types=1);

namespace SeoGeo\Core\Integrations;

final class RuntimeIntegrationDetector implements IntegrationDetectorInterface {
	public function seoProvider(): string {
		if ( defined( 'WPSEO_VERSION' ) ) {
			return 'yoast';
		}

		if ( class_exists( '\\RankMath\\Helper' ) ) {
			return 'rank-math';
		}

		if ( function_exists( 'aioseo' ) ) {
			return 'aioseo';
		}

		return 'native';
	}

	public function languageProvider(): string {
		if ( defined( 'ICL_SITEPRESS_VERSION' ) ) {
			return 'wpml';
		}

		if ( function_exists( 'pll_current_language' ) ) {
			return 'polylang';
		}

		return 'native';
	}
}
