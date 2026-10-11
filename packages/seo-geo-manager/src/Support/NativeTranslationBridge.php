<?php
/**
 * Dynamic bridge to the optional native multilingual core contract.
 *
 * @package SeoGeoManager
 */

declare(strict_types=1);

namespace SeoGeo\Manager\Support;

use WP_Error;

/** Keep Manager independently installable while reusing the accepted native core authority when present. */
final class NativeTranslationBridge {
	private const WRITER_CLASS = 'SeoGeo\\Core\\Language\\NativeTranslationGroupWriter';

	/** Determine whether the accepted native translation writer is available. */
	public static function available(): bool {
		if ( ! class_exists( self::WRITER_CLASS ) ) {
			return false;
		}

		$writer = self::writer();
		if ( is_wp_error( $writer ) ) {
			return false;
		}

		return is_callable( array( $writer, 'inspect' ) )
			&& is_callable( array( $writer, 'replace' ) )
			&& is_callable( array( $writer, 'clear' ) );
	}

	/**
	 * Inspect one native translation group.
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	public static function inspect( string $group_id ) {
		return self::invoke( 'inspect', array( $group_id ) );
	}

	/**
	 * Replace one complete native translation group.
	 *
	 * @param array<string, int> $translations Language-to-resource map.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function replace( string $group_id, array $translations ) {
		return self::invoke( 'replace', array( $group_id, $translations ) );
	}

	/**
	 * Clear one native translation group.
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	public static function clear( string $group_id ) {
		return self::invoke( 'clear', array( $group_id ) );
	}

	/**
	 * Invoke one accepted writer method without creating a compile-time dependency on Core.
	 *
	 * @param string       $method Method name.
	 * @param list<mixed>  $args   Method arguments.
	 * @return array<string, mixed>|WP_Error
	 */
	private static function invoke( string $method, array $args ) {
		$writer = self::writer();
		if ( is_wp_error( $writer ) ) {
			return $writer;
		}

		$callable = array( $writer, $method );
		if ( ! is_callable( $callable ) ) {
			return self::unavailable();
		}

		$result = call_user_func_array( $callable, $args );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		if ( ! is_array( $result ) ) {
			return new WP_Error(
				'seo_geo_manager_native_translation_contract_invalid',
				'The native translation provider returned an invalid response.',
				array( 'status' => 502 )
			);
		}

		return $result;
	}

	/**
	 * Instantiate the optional writer dynamically.
	 *
	 * @return object|WP_Error
	 */
	private static function writer() {
		if ( ! class_exists( self::WRITER_CLASS ) ) {
			return self::unavailable();
		}

		$class_name = self::WRITER_CLASS;
		$writer     = new $class_name();
		if ( ! is_object( $writer ) ) {
			return self::unavailable();
		}

		return $writer;
	}

	private static function unavailable(): WP_Error {
		return new WP_Error(
			'seo_geo_manager_native_translation_provider_unavailable',
			'The accepted native multilingual provider is not available on this WordPress installation.',
			array( 'status' => 503 )
		);
	}
}
