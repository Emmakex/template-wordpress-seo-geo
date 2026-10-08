<?php
/**
 * Resolve one public SEO-output authority without mutating provider data.
 *
 * @package SeoGeoManager
 */

declare(strict_types=1);

namespace SeoGeo\Manager\Intelligence;

final class SeoAuthorityResolver {
	/**
	 * Signals that must have one accepted public-output owner before Manager
	 * starts provider-specific SEO writes.
	 *
	 * @var list<string>
	 */
	private const SIGNALS = array(
		'title-meta',
		'canonical',
		'robots',
		'open-graph',
		'schema',
		'hreflang',
		'sitemap',
	);

	/**
	 * Resolve active plugin families plus the Theme fallback.
	 *
	 * @param list<string> $active_plugins Active plugin files.
	 * @return array<string, mixed>
	 */
	public static function resolve( array $active_plugins, bool $theme_available ): array {
		$families = self::active_families( $active_plugins );

		if ( 1 < count( $families ) ) {
			return array(
				'resolved'            => false,
				'authority'           => null,
				'provider_families'   => array_values( $families ),
				'conflict'            => true,
				'write_adapter_ready' => false,
				'signals'             => self::signals( 'unresolved', false, 'blocked-multiple-providers' ),
				'next_action'         => 'select-one-seo-output-authority',
			);
		}

		if ( 1 === count( $families ) ) {
			$authority = array_values( $families )[0];

			return array(
				'resolved'            => true,
				'authority'           => $authority,
				'provider_families'   => array( $authority ),
				'conflict'            => false,
				'write_adapter_ready' => false,
				'signals'             => self::signals( (string) $authority['id'], false, 'resolved-read-only-adapter' ),
				'next_action'         => 'implement-and-verify-provider-write-adapter',
			);
		}

		if ( $theme_available ) {
			$authority = array(
				'id'           => 'seo-geo-theme',
				'type'         => 'theme-native',
				'adapter'      => 'seo-geo-theme-native',
				'label'        => 'SEO/GEO Theme',
				'plugin_files' => array(),
			);

			return array(
				'resolved'            => true,
				'authority'           => $authority,
				'provider_families'   => array(),
				'conflict'            => false,
				'write_adapter_ready' => false,
				'signals'             => self::signals( 'seo-geo-theme', false, 'resolved-read-only-adapter' ),
				'next_action'         => 'implement-and-verify-theme-seo-write-adapter',
			);
		}

		return array(
			'resolved'            => false,
			'authority'           => null,
			'provider_families'   => array(),
			'conflict'            => false,
			'write_adapter_ready' => false,
			'signals'             => self::signals( 'unresolved', false, 'authority-not-established' ),
			'next_action'         => 'establish-output-authority-before-seo-mutation',
		);
	}

	/**
	 * Collapse free/pro plugin variants into one provider family. This prevents
	 * the resolver from treating a single provider suite as two authorities.
	 *
	 * @param list<string> $active_plugins Active plugin files.
	 * @return array<string, array<string, mixed>>
	 */
	private static function active_families( array $active_plugins ): array {
		$definitions = self::definitions();
		$active      = array();

		foreach ( $definitions as $family_id => $definition ) {
			$matched = array();
			$files   = isset( $definition['plugin_files'] ) && is_array( $definition['plugin_files'] ) ? $definition['plugin_files'] : array();
			foreach ( $files as $plugin_file ) {
				if ( is_string( $plugin_file ) && in_array( $plugin_file, $active_plugins, true ) ) {
					$matched[] = $plugin_file;
				}
			}

			if ( array() === $matched ) {
				continue;
			}

			$active[ $family_id ] = array(
				'id'           => $family_id,
				'type'         => 'plugin',
				'adapter'      => (string) ( $definition['adapter'] ?? '' ),
				'label'        => (string) ( $definition['label'] ?? $family_id ),
				'plugin_files' => $matched,
			);
		}

		return $active;
	}

	/**
	 * @return array<string, array<string, mixed>>
	 */
	private static function definitions(): array {
		return array(
			'yoast' => array(
				'label'        => 'Yoast SEO',
				'adapter'      => 'yoast',
				'plugin_files' => array(
					'wordpress-seo/wp-seo.php',
					'wordpress-seo-premium/wp-seo-premium.php',
				),
			),
			'rank-math' => array(
				'label'        => 'Rank Math',
				'adapter'      => 'rank-math',
				'plugin_files' => array(
					'seo-by-rank-math/rank-math.php',
					'rank-math/rank-math.php',
				),
			),
			'aioseo' => array(
				'label'        => 'All in One SEO',
				'adapter'      => 'aioseo',
				'plugin_files' => array(
					'all-in-one-seo-pack/all_in_one_seo_pack.php',
					'all-in-one-seo-pack-pro/all_in_one_seo_pack.php',
				),
			),
			'seo-framework' => array(
				'label'        => 'The SEO Framework',
				'adapter'      => 'seo-framework',
				'plugin_files' => array(
					'autodescription/autodescription.php',
					'seo-framework/autodescription.php',
				),
			),
		);
	}

	/**
	 * @return array<string, array<string, mixed>>
	 */
	private static function signals( string $owner, bool $writable, string $state ): array {
		$result = array();
		foreach ( self::SIGNALS as $signal ) {
			$result[ $signal ] = array(
				'owner'    => $owner,
				'resolved' => 'unresolved' !== $owner,
				'readable' => 'unresolved' !== $owner,
				'writable' => $writable,
				'state'    => $state,
			);
		}

		return $result;
	}
}
