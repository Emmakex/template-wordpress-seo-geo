<?php
/**
 * Native single-language WordPress adapter.
 *
 * @package SeoGeoCore
 */

declare(strict_types=1);

namespace SeoGeo\Core\Language;

/**
 * Adapts a standard single-language WordPress install to the language contract.
 */
final class NativeWordPressAdapter implements LanguageProviderInterface {
	/**
	 * Return the stable provider identifier.
	 *
	 * @return string
	 */
	public function id(): string {
		return 'native';
	}

	/**
	 * Return the current WordPress locale.
	 *
	 * @return string
	 */
	public function current_locale(): string {
		return get_locale();
	}

	/**
	 * Return the configured default locale.
	 *
	 * @return string
	 */
	public function default_locale(): string {
		$locale = (string) get_option( 'WPLANG', '' );

		return '' !== $locale ? $locale : 'en_US';
	}

	/**
	 * Native WordPress mode represents a single-language site.
	 *
	 * @return bool
	 */
	public function is_multilingual(): bool {
		return false;
	}
}
