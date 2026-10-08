<?php
/**
 * Read-only SEO output authority intelligence.
 *
 * @package SeoGeoManager
 */

declare(strict_types=1);

namespace SeoGeo\Manager\Intelligence;

final class SeoAuthorityScanner {
	/**
	 * Inspect and resolve likely public SEO-output ownership without mutating
	 * provider data. Resolution establishes ownership only; provider-specific
	 * writes remain disabled until their adapters are verified independently.
	 *
	 * @return array<string, mixed>
	 */
	public static function scan(): array {
		$active_plugins = get_option( 'active_plugins', array() );
		$active_plugins = is_array( $active_plugins ) ? array_values( array_filter( $active_plugins, 'is_string' ) ) : array();

		if ( is_multisite() ) {
			$network = get_site_option( 'active_sitewide_plugins', array() );
			if ( is_array( $network ) ) {
				$active_plugins = array_values( array_unique( array_merge( $active_plugins, array_keys( $network ) ) ) );
			}
		}

		$theme    = wp_get_theme();
		$is_theme = self::is_seo_geo_theme(
			(string) $theme->get_stylesheet(),
			(string) $theme->get_template(),
			(string) $theme->get( 'TextDomain' )
		);
		$resolution = SeoAuthorityResolver::resolve( $active_plugins, $is_theme );
		$authority  = isset( $resolution['authority'] ) && is_array( $resolution['authority'] ) ? $resolution['authority'] : null;
		$providers  = self::provider_ids( $resolution );
		$resolved   = true === ( $resolution['resolved'] ?? false );
		$conflict   = true === ( $resolution['conflict'] ?? false );
		$write_ready = true === ( $resolution['write_adapter_ready'] ?? false );

		if ( $conflict ) {
			$state = 'multiple-provider-conflict';
		} elseif ( $resolved && is_array( $authority ) && 'theme-native' === ( $authority['type'] ?? '' ) ) {
			$state = $write_ready ? 'resolved-theme-native-writable' : 'resolved-theme-native-read-only';
		} elseif ( $resolved && is_array( $authority ) ) {
			$state = $write_ready ? 'resolved-external-provider-writable' : 'resolved-external-provider-read-only';
		} else {
			$state = 'wordpress-native-or-unresolved';
		}

		return array(
			'state'                      => $state,
			'providers'                  => $providers,
			'theme_baseline_available'   => $is_theme,
			'authority_resolved'         => $resolved,
			'authority'                  => $authority,
			'provider_families'          => $resolution['provider_families'] ?? array(),
			'conflict'                   => $conflict,
			'write_adapter_ready'        => $write_ready,
			'safe_to_write_seo_metadata' => $resolved && $write_ready && ! $conflict,
			'outputs'                    => $resolution['signals'] ?? array(),
			'next_action'                => $resolution['next_action'] ?? 'establish-output-authority-before-seo-mutation',
		);
	}

	/**
	 * Preserve the compact provider list used by earlier consumers while the
	 * richer provider_families contract carries adapter evidence.
	 *
	 * @param array<string, mixed> $resolution Resolver output.
	 * @return list<string>
	 */
	private static function provider_ids( array $resolution ): array {
		$families = isset( $resolution['provider_families'] ) && is_array( $resolution['provider_families'] ) ? $resolution['provider_families'] : array();
		$result   = array();
		foreach ( $families as $family ) {
			if ( is_array( $family ) && isset( $family['id'] ) && is_string( $family['id'] ) && '' !== $family['id'] ) {
				$result[] = $family['id'];
			}
		}

		return array_values( array_unique( $result ) );
	}

	private static function is_seo_geo_theme( string $stylesheet, string $template, string $text_domain ): bool {
		$haystack = strtolower( implode( '|', array( $stylesheet, $template, $text_domain ) ) );

		return false !== strpos( $haystack, 'seo-geo' );
	}
}
