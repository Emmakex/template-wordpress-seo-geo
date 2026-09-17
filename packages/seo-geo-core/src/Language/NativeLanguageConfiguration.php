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
	 * Keep native language routing disabled.
	 */
	public const ROUTING_DISABLED = 'disabled';

	/**
	 * Reserve configured language codes as URL prefixes.
	 */
	public const ROUTING_PREFIX = 'prefix';

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
	 * Native routing mode.
	 *
	 * @var string
	 */
	private string $routing_mode;

	/**
	 * Create a validated configuration.
	 *
	 * @param string                $default_language_code Default language code.
	 * @param array<string, string> $languages             Language-to-locale map.
	 * @param string                $routing_mode          Validated routing mode.
	 */
	private function __construct( string $default_language_code, array $languages, string $routing_mode ) {
		$this->default_language_code = $default_language_code;
		$this->languages             = $languages;
		$this->routing_mode          = $routing_mode;
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
		$raw_routing   = $raw['routing'] ?? self::ROUTING_DISABLED;

		if ( ! is_string( $raw_default ) || ! is_array( $raw_languages ) || array() === $raw_languages || ! is_string( $raw_routing ) ) {
			return $fallback;
		}

		$routing_mode = strtolower( trim( $raw_routing ) );
		if ( ! in_array( $routing_mode, array( self::ROUTING_DISABLED, self::ROUTING_PREFIX ), true ) ) {
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

		return new self( $default_language_code, $languages, $routing_mode );
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
	 * Return the validated native routing mode.
	 */
	public function routing_mode(): string {
		return $this->routing_mode;
	}

	/**
	 * Report whether prefixed native routing is enabled.
	 */
	public function routing_enabled(): bool {
		return self::ROUTING_PREFIX === $this->routing_mode && $this->is_multilingual();
	}

	/**
	 * Report whether more than one native language is configured.
	 */
	public function is_multilingual(): bool {
		return count( $this->languages ) > 1;
	}

	/**
	 * Resolve a WordPress locale for a configured language code.
	 *
	 * @param string $language_code Language code to resolve.
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
	 *
	 * @param string $locale WordPress locale to resolve.
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
	 *
	 * @param string $locale Active WordPress locale.
	 */
	private static function single_language( string $locale ): self {
		$language_code = self::language_code_from_locale( $locale );

		return new self( $language_code, array( $language_code => $locale ), self::ROUTING_DISABLED );
	}

	/**
	 * Normalize a language code used by the native contract.
	 *
	 * @param string $language_code Raw language code.
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
	 *
	 * @param string $locale Raw WordPress locale.
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
	 *
	 * @param string $locale WordPress locale.
	 */
	private static function language_code_from_locale( string $locale ): string {
		$parts = preg_split( '/[_-]/', $locale );
		$code  = is_array( $parts ) && isset( $parts[0] ) ? strtolower( $parts[0] ) : 'en';

		return 1 === preg_match( '/^[a-z]{2,3}$/', $code ) ? $code : 'en';
	}
}
