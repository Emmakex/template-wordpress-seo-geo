<?php
/**
 * Native multilingual configuration.
 *
 * @package SeoGeoCore
 */

declare(strict_types=1);

namespace SeoGeo\Core\Language;

/**
 * Resolves the server-authoritative native language configuration.
 */
final class NativeLanguageConfiguration {
	/**
	 * WordPress option that stores the native language map.
	 */
	public const OPTION_NAME = 'seo_geo_native_languages';

	/**
	 * Default language code.
	 *
	 * @var string
	 */
	private string $default_language_code;

	/**
	 * Language-code to WordPress-locale map.
	 *
	 * @var array<string, string>
	 */
	private array $languages;

	/**
	 * Create a validated configuration.
	 *
	 * @param string                $default_language_code Default language code.
	 * @param array<string, string> $languages             Language-to-locale map.
	 */
	private function __construct( string $default_language_code, array $languages ) {
		$this->default_language_code = $default_language_code;
		$this->languages             = $languages;
	}

	/**
	 * Resolve the native language configuration from WordPress.
	 *
	 * Malformed configuration is rejected atomically and falls back to the
	 * current WordPress site locale rather than creating a partial language map.
	 */
	public static function from_wordpress(): self {
		$site_locale = self::normalize_locale( get_locale() );
		if ( null === $site_locale ) {
			$site_locale = 'en_US';
		}

		$fallback = self::single_language( $site_locale );
		$raw      = get_option( self::OPTION_NAME, null );

		if ( ! is_array( $raw ) ) {
			return $fallback;
		}

		$raw_default   = $raw['default'] ?? null;
		$raw_languages = $raw['languages'] ?? null;

		if ( ! is_string( $raw_default ) || ! is_array( $raw_languages ) || array() === $raw_languages ) {
			return $fallback;
		}

		$languages    = array();
		$seen_locales = array();

		foreach ( $raw_languages as $raw_code => $raw_locale ) {
			if ( ! is_string( $raw_code ) || ! is_string( $raw_locale ) ) {
				return $fallback;
			}

			$code   = self::normalize_language_code( $raw_code );
			$locale = self::normalize_locale( $raw_locale );

			if ( null === $code || null === $locale || isset( $languages[ $code ] ) ) {
				return $fallback;
			}

			$locale_key = strtolower( $locale );
			if ( isset( $seen_locales[ $locale_key ] ) ) {
				return $fallback;
			}

			$languages[ $code ]          = $locale;
			$seen_locales[ $locale_key ] = true;
		}

		$default_language_code = self::normalize_language_code( $raw_default );
		if ( null === $default_language_code || ! isset( $languages[ $default_language_code ] ) ) {
			return $fallback;
		}

		return new self( $default_language_code, $languages );
	}

	/**
	 * Return the configured default language code.
	 */
	public function default_language_code(): string {
		return $this->default_language_code;
	}

	/**
	 * Return the configured default WordPress locale.
	 */
	public function default_locale(): string {
		return $this->languages[ $this->default_language_code ];
	}

	/**
	 * Return the normalized language-to-locale map.
	 *
	 * @return array<string, string>
	 */
	public function languages(): array {
		return $this->languages;
	}

	/**
	 * Report whether more than one native language is configured.
	 */
	public function is_multilingual(): bool {
		return count( $this->languages ) > 1;
	}

	/**
	 * Resolve a WordPress locale for a configured language code.
	 */
	public function locale_for( string $language_code ): ?string {
		$normalized = self::normalize_language_code( $language_code );
		if ( null === $normalized ) {
			return null;
		}

		return $this->languages[ $normalized ] ?? null;
	}

	/**
	 * Resolve a configured language code for a WordPress locale.
	 */
	public function language_for_locale( string $locale ): ?string {
		$normalized = self::normalize_locale( $locale );
		if ( null === $normalized ) {
			return null;
		}

		foreach ( $this->languages as $language_code => $configured_locale ) {
			if ( 0 === strcasecmp( $configured_locale, $normalized ) ) {
				return $language_code;
			}
		}

		return null;
	}

	/**
	 * Build a single-language fallback from the active WordPress locale.
	 */
	private static function single_language( string $locale ): self {
		$language_code = self::language_code_from_locale( $locale );

		return new self( $language_code, array( $language_code => $locale ) );
	}

	/**
	 * Normalize a language code used by the native contract.
	 */
	private static function normalize_language_code( string $language_code ): ?string {
		$normalized = strtolower( trim( $language_code ) );

		if ( 1 !== preg_match( '/^[a-z]{2,3}(?:-[a-z0-9]{2,8})*$/', $normalized ) ) {
			return null;
		}

		return $normalized;
	}

	/**
	 * Accept only locale names that WordPress itself considers safe.
	 */
	private static function normalize_locale( string $locale ): ?string {
		$normalized = trim( $locale );
		if ( '' === $normalized || sanitize_locale_name( $normalized ) !== $normalized ) {
			return null;
		}

		return $normalized;
	}

	/**
	 * Derive the base language code from a WordPress locale.
	 */
	private static function language_code_from_locale( string $locale ): string {
		$parts = preg_split( '/[_-]/', $locale );
		$code  = is_array( $parts ) && isset( $parts[0] ) ? strtolower( $parts[0] ) : 'en';

		return 1 === preg_match( '/^[a-z]{2,3}$/', $code ) ? $code : 'en';
	}
}
