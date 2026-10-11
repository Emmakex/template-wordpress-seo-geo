<?php
/**
 * Bridge to the public Yoast SEO WordPress Abilities contract.
 *
 * @package SeoGeoManager
 */

declare(strict_types=1);

namespace SeoGeo\Manager\Support;

use WP_Ability;
use WP_Error;

/** Keep Yoast integration on its public provider contract, never private post meta. */
final class YoastSeoAbilityBridge {
	private const READ_ABILITY  = 'yoast-seo/get-post-seo-data';
	private const WRITE_ABILITY = 'yoast-seo/update-post-seo-data';

	/** Determine whether both accepted Yoast SEO abilities are registered. */
	public static function available(): bool {
		if ( ! function_exists( 'wp_get_ability' ) ) {
			return false;
		}

		return wp_get_ability( self::READ_ABILITY ) instanceof WP_Ability
			&& wp_get_ability( self::WRITE_ABILITY ) instanceof WP_Ability;
	}

	/**
	 * Read one post's provider SEO state through Yoast's public read ability.
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	public static function inspect( int $resource_id ) {
		if ( 1 > $resource_id ) {
			return new WP_Error(
				'seo_geo_manager_yoast_resource_invalid',
				'Yoast SEO inspection requires a valid WordPress resource ID.',
				array( 'status' => 400 )
			);
		}

		$ability = self::ability( self::READ_ABILITY );
		if ( is_wp_error( $ability ) ) {
			return $ability;
		}

		$result = $ability->execute( array( 'post_id' => $resource_id ) );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		if ( ! is_array( $result ) || 1 !== count( $result ) ) {
			return new WP_Error(
				'seo_geo_manager_yoast_read_contract_invalid',
				'Yoast SEO returned an unexpected read response.',
				array( 'status' => 502 )
			);
		}

		$item = reset( $result );
		if ( ! is_array( $item ) || $resource_id !== (int) ( $item['post_id'] ?? 0 ) ) {
			return new WP_Error(
				'seo_geo_manager_yoast_read_contract_invalid',
				'Yoast SEO returned SEO data for an unexpected resource.',
				array( 'status' => 502 )
			);
		}

		return $item;
	}

	/**
	 * Validate an intended update against Yoast's current public ability schema.
	 *
	 * @param array<string, mixed> $values Provider values to validate.
	 * @return true|WP_Error
	 */
	public static function validate_update( int $resource_id, array $values ) {
		$ability = self::ability( self::WRITE_ABILITY );
		if ( is_wp_error( $ability ) ) {
			return $ability;
		}

		$input            = $values;
		$input['post_id'] = $resource_id;

		return $ability->validate_input( $input );
	}

	/**
	 * Update only fields supported by the accepted Manager adapter.
	 *
	 * @param array<string, mixed> $values Provider values to update.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function update( int $resource_id, array $values ) {
		if ( 1 > $resource_id ) {
			return new WP_Error(
				'seo_geo_manager_yoast_resource_invalid',
				'Yoast SEO update requires a valid WordPress resource ID.',
				array( 'status' => 400 )
			);
		}

		$ability = self::ability( self::WRITE_ABILITY );
		if ( is_wp_error( $ability ) ) {
			return $ability;
		}

		$input            = $values;
		$input['post_id'] = $resource_id;
		$result           = $ability->execute( $input );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		if ( ! is_array( $result ) || $resource_id !== (int) ( $result['post_id'] ?? 0 ) ) {
			return new WP_Error(
				'seo_geo_manager_yoast_write_contract_invalid',
				'Yoast SEO returned an unexpected update response.',
				array( 'status' => 502 )
			);
		}

		return $result;
	}

	/**
	 * Resolve one public Yoast ability.
	 *
	 * @return WP_Ability|WP_Error
	 */
	private static function ability( string $name ) {
		if ( ! function_exists( 'wp_get_ability' ) ) {
			return self::unavailable();
		}

		$ability = wp_get_ability( $name );
		if ( ! $ability instanceof WP_Ability ) {
			return self::unavailable();
		}

		return $ability;
	}

	private static function unavailable(): WP_Error {
		return new WP_Error(
			'seo_geo_manager_yoast_abilities_unavailable',
			'The accepted Yoast SEO WordPress Abilities are not available on this installation.',
			array( 'status' => 503 )
		);
	}
}
