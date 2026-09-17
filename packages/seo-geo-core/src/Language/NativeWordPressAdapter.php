<?php
/**
 * Native WordPress language adapter.
 *
 * @package SeoGeoCore
 */

declare(strict_types=1);

namespace SeoGeo\Core\Language;

/**
 * Adapts WordPress and the native language configuration to the language contract.
 */
final class NativeWordPressAdapter implements LanguageProviderInterface {
	/**
	 * Native server-side configuration.
	 *
	 * @var NativeLanguageConfiguration
	 */
	private NativeLanguageConfiguration $configuration;

	/**
	 * Create the native adapter.
	 *
	 * @param NativeLanguageConfiguration|null $configuration Validated configuration.
	 */
	public function __construct( ?NativeLanguageConfiguration $configuration = null ) {
		$this->configuration = $configuration ?? NativeLanguageConfiguration::from_wordpress();
	}

	/**
	 * Return the stable provider identifier.
	 */
	public function id(): string {
		return 'native';
	}

	/**
	 * Return the current WordPress locale.
	 *
	 * Phase 4A does not change request routing or switch locales. It reports the
	 * locale that WordPress already resolved for the current execution context.
	 */
	public function current_locale(): string {
		return get_locale();
	}

	/**
	 * Return the current normalized language code.
	 */
	public function current_language_code(): string {
		$current_locale = $this->current_locale();
		$configured     = $this->configuration->language_for_locale( $current_locale );

		if ( null !== $configured ) {
			return $configured;
		}

		$parts = preg_split( '/[_-]/', $current_locale );
		$code  = is_array( $parts ) && isset( $parts[0] ) ? strtolower( $parts[0] ) : '';

		return 1 === preg_match( '/^[a-z]{2,3}$/', $code ) ? $code : $this->default_language_code();
	}

	/**
	 * Return the configured default locale.
	 */
	public function default_locale(): string {
		return $this->configuration->default_locale();
	}

	/**
	 * Return the configured default language code.
	 */
	public function default_language_code(): string {
		return $this->configuration->default_language_code();
	}

	/**
	 * Return all configured native languages.
	 *
	 * @return array<string, string>
	 */
	public function available_languages(): array {
		return $this->configuration->languages();
	}

	/**
	 * Resolve a configured locale for a language code.
	 *
	 * @param string $language_code Normalized language code to resolve.
	 */
	public function locale_for_language( string $language_code ): ?string {
		return $this->configuration->locale_for( $language_code );
	}

	/**
	 * Report whether more than one native language is configured.
	 */
	public function is_multilingual(): bool {
		return $this->configuration->is_multilingual();
	}
}
