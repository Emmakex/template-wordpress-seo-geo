<?php
/**
 * Normalized language facade.
 *
 * @package SeoGeoCore
 */

declare(strict_types=1);

namespace SeoGeo\Core\Language;

/**
 * Exposes one normalized language API to the rest of the Core plugin.
 */
final class LanguageManager {
	/**
	 * Active language provider.
	 *
	 * @var LanguageProviderInterface
	 */
	private LanguageProviderInterface $provider;

	/**
	 * Create the language facade.
	 *
	 * @param LanguageProviderInterface $provider Active language provider.
	 */
	public function __construct( LanguageProviderInterface $provider ) {
		$this->provider = $provider;
	}

	/**
	 * Return the active provider identifier.
	 *
	 * @return string
	 */
	public function provider_id(): string {
		return $this->provider->id();
	}

	/**
	 * Return the current locale.
	 *
	 * @return string
	 */
	public function current_locale(): string {
		return $this->provider->current_locale();
	}

	/**
	 * Return the default locale.
	 *
	 * @return string
	 */
	public function default_locale(): string {
		return $this->provider->default_locale();
	}

	/**
	 * Report whether the active provider is multilingual.
	 *
	 * @return bool
	 */
	public function is_multilingual(): bool {
		return $this->provider->is_multilingual();
	}

	/**
	 * Return the normalized two-letter-or-provider language code.
	 *
	 * @return string
	 */
	public function current_language_code(): string {
		$locale = str_replace( '-', '_', $this->current_locale() );
		$parts  = explode( '_', $locale );

		return strtolower( $parts[0] ?? $locale );
	}
}
