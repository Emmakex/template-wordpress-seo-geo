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

/**
 * Detects supported SEO and multilingual integrations at runtime.
 */
final class RuntimeIntegrationDetector implements IntegrationDetectorInterface {
	/**
	 * Return the active SEO provider identifier.
	 *
	 * @return string
	 */
	public function seo_provider(): string {
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

	/**
	 * Return the active language provider identifier.
	 *
	 * @return string
	 */
	public function language_provider(): string {
		if ( defined( 'ICL_SITEPRESS_VERSION' ) ) {
			return 'wpml';
		}

		if ( function_exists( 'pll_current_language' ) ) {
			return 'polylang';
		}

		return 'native';
	}
}
