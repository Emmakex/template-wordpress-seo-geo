<?php
/**
 * Runtime integration detection contract.
 *
 * @package SeoGeoCore
 */

declare(strict_types=1);

namespace SeoGeo\Core\Integrations;

interface IntegrationDetectorInterface {
	public function seoProvider(): string;

	public function languageProvider(): string;
}
