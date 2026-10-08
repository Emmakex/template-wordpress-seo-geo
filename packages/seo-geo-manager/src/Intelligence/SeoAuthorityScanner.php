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
	 * Inspect likely public SEO-output ownership without mutating provider data.
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

		$providers = self::providers( $active_plugins );
		$theme     = wp_get_theme();
		$is_theme  = self::is_seo_geo_theme(
			(string) $theme->get_stylesheet(),
			(string) $theme->get_template(),
			(string) $theme->get( 'TextDomain' )
		);

		if ( 1 < count( $providers ) ) {
			return array(
				'state'                      => 'multiple-provider-conflict-candidate',
				'providers'                  => $providers,
				'theme_baseline_available'   => $is_theme,
				'safe_to_write_seo_metadata' => false,
				'outputs'                    => self::output_map( 'unresolved', 'blocked-multiple-providers' ),
				'next_action'                => 'resolve-one-authority-before-seo-mutation',
			);
		}

		if ( 1 === count( $providers ) ) {
			$provider = $providers[0];

			return array(
				'state'                      => 'external-provider-candidate',
				'providers'                  => $providers,
				'theme_baseline_available'   => $is_theme,
				'safe_to_write_seo_metadata' => false,
				'outputs'                    => self::output_map( $provider, 'adapter-confirmation-required' ),
				'next_action'                => 'confirm-provider-adapter-before-seo-mutation',
			);
		}

		if ( $is_theme ) {
			return array(
				'state'                      => 'theme-native-candidate',
				'providers'                  => array(),
				'theme_baseline_available'   => true,
				'safe_to_write_seo_metadata' => false,
				'outputs'                    => self::output_map( 'seo-geo-theme', 'manager-resolver-not-yet-bound' ),
				'next_action'                => 'bind-manager-output-authority-resolver',
			);
		}

		return array(
			'state'                      => 'wordpress-native-or-unresolved',
			'providers'                  => array(),
			'theme_baseline_available'   => false,
			'safe_to_write_seo_metadata' => false,
			'outputs'                    => self::output_map( 'unresolved', 'authority-not-established' ),
			'next_action'                => 'establish-output-authority-before-seo-mutation',
		);
	}

	/**
	 * @param list<string> $active_plugins Active plugin files.
	 * @return list<string>
	 */
	private static function providers( array $active_plugins ): array {
		$map       = array(
			'wordpress-seo/wp-seo.php'                         => 'yoast',
			'wordpress-seo-premium/wp-seo-premium.php'         => 'yoast-premium',
			'seo-by-rank-math/rank-math.php'                   => 'rank-math',
			'rank-math/rank-math.php'                          => 'rank-math',
			'all-in-one-seo-pack/all_in_one_seo_pack.php'       => 'aioseo',
			'all-in-one-seo-pack-pro/all_in_one_seo_pack.php'   => 'aioseo-pro',
		);
		$providers = array();
		foreach ( $map as $plugin_file => $provider ) {
			if ( in_array( $plugin_file, $active_plugins, true ) ) {
				$providers[] = $provider;
			}
		}

		return array_values( array_unique( $providers ) );
	}

	/**
	 * @return array<string, array<string, string>>
	 */
	private static function output_map( string $owner, string $state ): array {
		$outputs = array();
		foreach ( array( 'title-meta', 'canonical', 'robots', 'open-graph', 'schema', 'hreflang', 'sitemap' ) as $signal ) {
			$outputs[ $signal ] = array(
				'owner_candidate' => $owner,
				'state'           => $state,
			);
		}

		return $outputs;
	}

	private static function is_seo_geo_theme( string $stylesheet, string $template, string $text_domain ): bool {
		$haystack = strtolower( implode( '|', array( $stylesheet, $template, $text_domain ) ) );

		return false !== strpos( $haystack, 'seo-geo' );
	}
}
