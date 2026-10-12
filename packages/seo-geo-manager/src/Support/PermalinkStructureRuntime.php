<?php
/**
 * Shared guarded permalink-structure write primitives.
 *
 * @package SeoGeoManager
 */

declare(strict_types=1);

namespace SeoGeo\Manager\Support;

use WP_Error;

final class PermalinkStructureRuntime {
	/**
	 * Persist one permalink structure through WordPress and flush rewrite rules.
	 *
	 * @return true|WP_Error
	 */
	public static function set( string $structure ) {
		global $wp_rewrite;
		if ( ! $wp_rewrite instanceof \WP_Rewrite ) {
			return new WP_Error(
				'seo_geo_manager_permalink_rewrite_unavailable',
				'WordPress rewrite runtime is unavailable.',
				array( 'status' => 500 )
			);
		}

		$wp_rewrite->set_permalink_structure( $structure );
		flush_rewrite_rules( false );
		wp_cache_delete( 'permalink_structure', 'options' );
		wp_cache_delete( 'alloptions', 'options' );

		if ( (string) get_option( 'permalink_structure', '' ) !== $structure ) {
			return new WP_Error(
				'seo_geo_manager_permalink_write_failed',
				'WordPress did not persist the requested permalink structure.',
				array( 'status' => 500 )
			);
		}

		return true;
	}

	/**
	 * Restore exactly a Manager-captured prior value, including malformed values.
	 *
	 * @return true|WP_Error
	 */
	public static function restore( string $structure ) {
		$preserve = static function () use ( $structure ): string {
			return $structure;
		};
		add_filter( 'sanitize_option_permalink_structure', $preserve, PHP_INT_MAX, 0 );
		try {
			$result = self::set( $structure );
		} finally {
			remove_filter( 'sanitize_option_permalink_structure', $preserve, PHP_INT_MAX );
		}

		return $result;
	}

	public static function fingerprint( string $structure ): string {
		return hash(
			'sha256',
			(string) wp_json_encode(
				array(
					'home_url'            => home_url( '/' ),
					'site_url'            => site_url( '/' ),
					'permalink_structure' => $structure,
				)
			)
		);
	}
}
