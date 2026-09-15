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
	 *
	 * @return string
	 */
	public function id(): string;

	/**
	 * Return the current WordPress locale.
	 *
	 * @return string
	 */
	public function current_locale(): string;

	/**
	 * Return the configured default locale.
	 *
	 * @return string
	 */
	public function default_locale(): string;

	/**
	 * Report whether the active provider supports multiple languages.
	 *
	 * @return bool
	 */
	public function is_multilingual(): bool;
}
