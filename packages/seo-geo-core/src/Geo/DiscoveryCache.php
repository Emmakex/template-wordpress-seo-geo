<?php
/**
 * Discovery output cache.
 *
 * @package SeoGeoCore
 */

declare(strict_types=1);

namespace SeoGeo\Core\Geo;

/**
 * Provides versioned object-cache keys for safe derived discovery output.
 */
final class DiscoveryCache {
	/**
	 * Dedicated object-cache group.
	 */
	public const GROUP = 'seo_geo_discovery';

	/**
	 * Bound unreachable old-generation entries in persistent cache backends.
	 */
	private const TTL = 3600;

	/**
	 * Build one versioned cache key.
	 *
	 * The last-changed token makes previous entries unreachable after
	 * invalidation without requiring a global cache flush.
	 *
	 * @param string $scope    Stable output scope.
	 * @param string $identity Stable identity inside the scope.
	 */
	public function key( string $scope, string $identity ): string {
		$generation = wp_cache_get_last_changed( self::GROUP );

		return sanitize_key( $scope ) . ':' . hash( 'sha256', $identity . '|' . $generation );
	}

	/**
	 * Read a cached text value.
	 *
	 * @param string $key   Versioned cache key.
	 * @param bool   $found Whether the key existed.
	 */
	public function get_text( string $key, bool &$found ): ?string {
		$cache_found = null;
		$value       = wp_cache_get( $key, self::GROUP, false, $cache_found );
		$found       = true === $cache_found;

		if ( ! $found || ! is_string( $value ) ) {
			return null;
		}

		return $value;
	}

	/**
	 * Store a derived text value.
	 *
	 * Generation-based invalidation makes old entries unreachable immediately.
	 * A bounded TTL additionally prevents old generations from accumulating
	 * indefinitely in persistent cache backends.
	 *
	 * @param string $key   Versioned cache key.
	 * @param string $value Derived public text.
	 */
	public function set_text( string $key, string $value ): void {
		wp_cache_set( $key, $value, self::GROUP, self::TTL );
	}

	/**
	 * Advance the discovery cache generation.
	 */
	public function invalidate(): void {
		wp_cache_set_last_changed( self::GROUP );
	}
}
