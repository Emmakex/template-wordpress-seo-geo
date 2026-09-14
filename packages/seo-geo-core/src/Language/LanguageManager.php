<?php
/**
 * Normalized language facade.
 *
 * @package SeoGeoCore
 */

declare(strict_types=1);

namespace SeoGeo\Core\Language;

final class LanguageManager {
	public function __construct( private LanguageProviderInterface $provider ) {
	}

	public function providerId(): string {
		return $this->provider->id();
	}

	public function currentLocale(): string {
		return $this->provider->currentLocale();
	}

	public function defaultLocale(): string {
		return $this->provider->defaultLocale();
	}

	public function isMultilingual(): bool {
		return $this->provider->isMultilingual();
	}

	public function currentLanguageCode(): string {
		$locale = str_replace( '-', '_', $this->currentLocale() );
		$parts  = explode( '_', $locale );

		return strtolower( $parts[0] ?? $locale );
	}
}
