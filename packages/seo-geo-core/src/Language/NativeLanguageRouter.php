<?php
/**
 * Native prefixed language routing.
 *
 * @package SeoGeoCore
 */

declare(strict_types=1);

namespace SeoGeo\Core\Language;

use WP;

/**
 * Adds validated language prefixes without relying on a multilingual plugin.
 */
final class NativeLanguageRouter {
	/**
	 * Public query variable owned by the native router.
	 */
	public const QUERY_VAR = 'seo_geo_lang';

	/**
	 * Native language configuration.
	 *
	 * @var NativeLanguageConfiguration
	 */
	private NativeLanguageConfiguration $configuration;

	/**
	 * Active language code for a matched prefixed route.
	 *
	 * @var string|null
	 */
	private ?string $active_language_code = null;

	/**
	 * Active locale for a matched prefixed route.
	 *
	 * @var string|null
	 */
	private ?string $active_locale = null;

	/**
	 * Create the router.
	 *
	 * @param NativeLanguageConfiguration $configuration Validated native configuration.
	 */
	public function __construct( NativeLanguageConfiguration $configuration ) {
		$this->configuration = $configuration;
	}

	/**
	 * Register routing hooks only when prefix routing is explicitly enabled.
	 */
	public function register(): void {
		if ( ! $this->enabled() ) {
			return;
		}

		add_filter( 'query_vars', array( $this, 'filter_query_vars' ) );
		add_filter( 'rewrite_rules_array', array( $this, 'filter_rewrite_rules' ), 20 );
		add_action( 'parse_request', array( $this, 'activate_request_locale' ), 20 );
		add_filter( 'redirect_canonical', array( $this, 'filter_canonical_redirect' ), 20, 2 );
		add_filter( 'language_attributes', array( $this, 'filter_language_attributes' ), 20, 2 );
	}

	/**
	 * Report whether native prefix routing is active for this configuration.
	 */
	public function enabled(): bool {
		return $this->configuration->routing_enabled();
	}

	/**
	 * Report whether the current request matched a validated localized route.
	 */
	public function is_localized_request(): bool {
		return null !== $this->active_language_code && null !== $this->active_locale;
	}

	/**
	 * Return the active prefixed route language code.
	 */
	public function active_language_code(): ?string {
		return $this->active_language_code;
	}

	/**
	 * Return the active prefixed route locale.
	 */
	public function active_locale(): ?string {
		return $this->active_locale;
	}

	/**
	 * Register the language query variable with WordPress.
	 *
	 * @param array<int, string> $query_vars Existing public query variables.
	 * @return array<int, string>
	 */
	public function filter_query_vars( array $query_vars ): array {
		if ( ! in_array( self::QUERY_VAR, $query_vars, true ) ) {
			$query_vars[] = self::QUERY_VAR;
		}

		return $query_vars;
	}

	/**
	 * Prefix public WordPress rewrite rules with each configured language code.
	 *
	 * Literal prefixes are added outside the original regular expression so
	 * existing $matches[n] capture numbering remains unchanged.
	 *
	 * @param array<string, string> $rules Existing WordPress rewrite rules.
	 * @return array<string, string>
	 */
	public function filter_rewrite_rules( array $rules ): array {
		$localized = array();

		foreach ( array_keys( $this->configuration->languages() ) as $language_code ) {
			$escaped_prefix = preg_quote( $language_code, '#' );

			$localized[ '^' . $escaped_prefix . '/?$' ] = 'index.php?' . self::QUERY_VAR . '=' . rawurlencode( $language_code );

			foreach ( $rules as $regex => $query ) {
				$base_regex = str_starts_with( $regex, '^' ) ? substr( $regex, 1 ) : $regex;

				if ( $this->is_infrastructure_rule( $base_regex ) ) {
					continue;
				}

				$localized_regex = '^' . $escaped_prefix . '/' . $base_regex;

				$localized[ $localized_regex ] = $this->append_language_query_var( $query, $language_code );
			}
		}

		return $localized + $rules;
	}

	/**
	 * Activate a locale only when WordPress matched a real prefixed rewrite rule.
	 *
	 * A query-string value alone is intentionally insufficient to switch locale.
	 *
	 * @param WP $wp Parsed WordPress request state.
	 */
	public function activate_request_locale( WP $wp ): void {
		$requested = $wp->query_vars[ self::QUERY_VAR ] ?? null;

		if ( ! is_string( $requested ) ) {
			return;
		}

		$locale       = $this->configuration->locale_for( $requested );
		$matched_rule = $wp->matched_rule;

		if ( null === $locale || ! $this->matched_language_prefix( $matched_rule, $requested ) ) {
			unset( $wp->query_vars[ self::QUERY_VAR ] );
			return;
		}

		if ( function_exists( 'switch_to_locale' ) ) {
			switch_to_locale( $locale );
		}

		$this->active_language_code = $requested;
		$this->active_locale        = $locale;

		add_filter( 'locale', array( $this, 'filter_active_locale' ), PHP_INT_MAX );
		add_filter( 'determine_locale', array( $this, 'filter_active_locale' ), PHP_INT_MAX );
	}

	/**
	 * Keep locale helpers aligned with the validated active route.
	 *
	 * @param string $locale Locale resolved by WordPress.
	 */
	public function filter_active_locale( string $locale ): string {
		return $this->active_locale ?? $locale;
	}

	/**
	 * Keep the document language attribute aligned with the validated route.
	 *
	 * Existing direction and any other WordPress-provided attributes are
	 * preserved; only lang/xml:lang are normalized from the active locale.
	 *
	 * @param string $output  Existing language attributes.
	 * @param string $doctype Document type requested by WordPress.
	 */
	public function filter_language_attributes( string $output, string $doctype ): string {
		if ( null === $this->active_locale ) {
			return $output;
		}

		$language_tag = str_replace( '_', '-', $this->active_locale );
		$escaped_tag  = esc_attr( $language_tag );
		$replacement  = 'lang="' . $escaped_tag . '"';
		$updated      = preg_replace( '/(?<!xml:)lang="[^"]*"/', $replacement, $output, 1 );
		$output       = is_string( $updated ) ? $updated : $output;

		if ( ! str_contains( $output, 'lang="' ) ) {
			$output = trim( $output . ' ' . $replacement );
		}

		if ( 'xhtml' === $doctype ) {
			$xml_replacement = 'xml:lang="' . $escaped_tag . '"';
			$xml_updated     = preg_replace( '/xml:lang="[^"]*"/', $xml_replacement, $output, 1 );
			$output          = is_string( $xml_updated ) ? $xml_updated : $output;

			if ( ! str_contains( $output, 'xml:lang="' ) ) {
				$output = trim( $output . ' ' . $xml_replacement );
			}
		}

		return $output;
	}

	/**
	 * Prevent WordPress from canonical-redirecting a validated localized route
	 * back to its unprefixed permalink while 4B routing is active.
	 *
	 * @param string|false $redirect_url  Proposed redirect URL.
	 * @param string       $requested_url Original requested URL.
	 * @return string|false
	 */
	public function filter_canonical_redirect( string|false $redirect_url, string $requested_url ): string|false {
		if ( $this->is_localized_request() || $this->has_unconfigured_language_prefix( $requested_url ) ) {
			return false;
		}

		return $redirect_url;
	}

	/**
	 * Report whether the request starts with a language-like prefix that is not
	 * part of the validated native configuration.
	 *
	 * In prefix-routing mode, language-shaped first path segments are reserved
	 * for the language namespace. Unknown prefixes must remain 404s instead of
	 * being canonical-redirected to an unrelated unprefixed resource.
	 *
	 * @param string $requested_url Original requested URL.
	 */
	private function has_unconfigured_language_prefix( string $requested_url ): bool {
		$path = wp_parse_url( $requested_url, PHP_URL_PATH );
		if ( ! is_string( $path ) ) {
			return false;
		}

		$trimmed = trim( $path, '/' );
		if ( '' === $trimmed ) {
			return false;
		}

		$segments = explode( '/', $trimmed );
		$prefix   = strtolower( rawurldecode( $segments[0] ) );

		if ( 1 !== preg_match( '/^[a-z]{2,3}(?:-[a-z0-9]{2,8})*$/', $prefix ) ) {
			return false;
		}

		return null === $this->configuration->locale_for( $prefix );
	}

	/**
	 * Verify that the query variable originated from a configured rewrite prefix.
	 *
	 * @param string $matched_rule  WordPress matched rewrite rule.
	 * @param string $language_code Requested configured language code.
	 */
	private function matched_language_prefix( string $matched_rule, string $language_code ): bool {
		$prefix = '^' . preg_quote( $language_code, '#' ) . '/';

		return str_starts_with( $matched_rule, $prefix );
	}

	/**
	 * Add the router query variable without changing existing rewrite captures.
	 *
	 * @param string $query         Existing rewrite destination.
	 * @param string $language_code Configured language code.
	 */
	private function append_language_query_var( string $query, string $language_code ): string {
		$separator = str_contains( $query, '?' ) ? '&' : '?';

		return $query . $separator . self::QUERY_VAR . '=' . rawurlencode( $language_code );
	}

	/**
	 * Exclude infrastructure endpoints that must remain global and unprefixed.
	 *
	 * @param string $regex Rewrite regular expression without its leading ^.
	 */
	private function is_infrastructure_rule( string $regex ): bool {
		$prefixes = array(
			'wp-json',
			'robots\\.txt',
			'favicon\\.ico',
			'wp-sitemap',
			'sitemap',
			'feed/',
			'comments/feed',
			'trackback',
		);

		foreach ( $prefixes as $prefix ) {
			if ( str_starts_with( $regex, $prefix ) ) {
				return true;
			}
		}

		return str_contains( $regex, '\\.xsl' );
	}
}
