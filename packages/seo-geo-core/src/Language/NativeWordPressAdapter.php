<?php
/**
 * Native single-language WordPress adapter.
 *
 * @package SeoGeoCore
 */

declare(strict_types=1);

namespace SeoGeo\Core\Language;

final class NativeWordPressAdapter implements LanguageProviderInterface {
	public function id(): string {
		return 'native';
	}

	public function currentLocale(): string {
		return get_locale();
	}

	public function defaultLocale(): string {
		$locale = (string) get_option( 'WPLANG', '' );

		return '' !== $locale ? $locale : 'en_US';
	}

	public function isMultilingual(): bool {
		return false;
	}
}
