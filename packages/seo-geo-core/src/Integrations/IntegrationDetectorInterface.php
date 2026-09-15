<?php
/**
 * Runtime integration detection contract.
 *
 * @package SeoGeoCore
 */

declare(strict_types=1);

namespace SeoGeo\Core\Integrations;

/**
 * Defines the normalized runtime integration detector contract.
 */
interface IntegrationDetectorInterface {
	/**
	 * Return the active SEO provider identifier.
	 *
	 * @return string
	 */
	public function seo_provider(): string;

	/**
	 * Return whether the detected SEO provider is ready to own frontend output.
	 *
	 * Detection and output authority are intentionally separate: an active
	 * provider can still require initial setup before it emits complete metadata.
	 *
	 * @return bool
	 */
	public function seo_provider_ready(): bool;

	/**
	 * Return the active language provider identifier.
	 *
	 * @return string
	 */
	public function language_provider(): string;
}
