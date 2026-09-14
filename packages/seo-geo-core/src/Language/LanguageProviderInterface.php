<?php
/**
 * Language provider contract.
 *
 * @package SeoGeoCore
 */

declare(strict_types=1);

namespace SeoGeo\Core\Language;

interface LanguageProviderInterface {
	public function id(): string;

	public function currentLocale(): string;

	public function defaultLocale(): string;

	public function isMultilingual(): bool;
}
