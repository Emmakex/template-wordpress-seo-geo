<?php
/**
 * Normalized language facade.
 *
 * @package SeoGeoCore
 */

declare(strict_types=1);

namespace SeoGeo\Core\Language;

/**
 * Exposes one normalized language API to the rest of Core.
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
	 */
	public function provider_id(): string {
		return $this->provider->id();
	}

	/**
	 * Return the current locale.
	 */
	public function current_locale(): string {
		return $this->provider->current_locale();
	}

	/**
	 * Return the current normalized language code.
	 */
	public function current_language_code(): string {
		return $this->provider->current_language_code();
	}

	/**
	 * Return the default locale.
	 */
	public function default_locale(): string {
		return $this->provider->default_locale();
	}

	/**
	 * Return the default normalized language code.
	 */
	public function default_language_code(): string {
		return $this->provider->default_language_code();
	}

	/**
	 * Return the normalized language-code to locale map.
	 *
	 * @return array<string, string>
	 */
	public function available_languages(): array {
		return $this->provider->available_languages();
	}

	/**
	 * Resolve a configured WordPress locale for a language code.
	 */
	public function locale_for_language( string $language_code ): ?string {
		return $this->provider->locale_for_language( $language_code );
	}

	/**
	 * Report whether the active provider is multilingual.
	 */
	public function is_multilingual(): bool {
		return $this->provider->is_multilingual();
	}
}
