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
	 * Return the active language provider identifier.
	 *
	 * @return string
	 */
	public function language_provider(): string;
}
