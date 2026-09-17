<?php
/**
 * Language provider contract.
 *
 * @package SeoGeoCore
 */

declare(strict_types=1);

namespace SeoGeo\Core\Language;

/**
 * Defines the normalized language provider contract used by Core services.
 */
interface LanguageProviderInterface {
	/**
	 * Return the stable provider identifier.
	 */
	public function id(): string;

	/**
	 * Return the current WordPress locale.
	 */
	public function current_locale(): string;

	/**
	 * Return the current normalized language code.
	 */
	public function current_language_code(): string;

	/**
	 * Return the configured default locale.
	 */
	public function default_locale(): string;

	/**
	 * Return the configured default language code.
	 */
	public function default_language_code(): string;

	/**
	 * Return the available language-code to locale map.
	 *
	 * @return array<string, string>
	 */
	public function available_languages(): array;

	/**
	 * Resolve a configured locale for a language code.
	 */
	public function locale_for_language( string $language_code ): ?string;

	/**
	 * Report whether the active provider supports multiple languages.
	 */
	public function is_multilingual(): bool;
}
