<?php
/**
 * Runtime integration detector.
 *
 * Detection is separate from output readiness so an installed provider cannot
 * suppress native SEO before it is ready to emit complete frontend metadata.
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
	 * Return whether the detected SEO provider is ready to own frontend output.
	 *
	 * Rank Math intentionally separates activation from completion of its setup
	 * wizard. Until its public Helper reports configured, native Core remains the
	 * output authority so canonical, description and robots cannot disappear.
	 *
	 * @return bool
	 */
	public function seo_provider_ready(): bool {
		$provider = $this->seo_provider();

		if ( 'rank-math' !== $provider ) {
			return true;
		}

		$callback = array( '\\RankMath\\Helper', 'is_configured' );
		if ( ! is_callable( $callback ) ) {
			return false;
		}

		return (bool) call_user_func( $callback );
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
