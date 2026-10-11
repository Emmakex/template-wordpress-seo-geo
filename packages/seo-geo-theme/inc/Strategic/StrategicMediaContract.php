<?php
/**
 * Theme-owned strategic-media contract reader.
 *
 * @package SeoGeoTheme
 */

declare(strict_types=1);

namespace SeoGeo\Theme\Strategic;

/** Resolve allowlisted strategic media slots for the active Theme preset. */
final class StrategicMediaContract {
	private const FILENAME       = 'strategic-media.json';
	private const SCHEMA_VERSION = 1;

	/**
	 * Return media-slot definitions for one model in the active preset.
	 *
	 * @param string $model_id Semantic model identifier.
	 * @return array<string, array{type:string,required:bool,accept:string}>
	 */
	public static function model_slots( string $model_id ): array {
		$model_id = sanitize_key( $model_id );
		if ( '' === $model_id ) {
			return array();
		}

		$document = self::document();
		$models   = is_array( $document ) ? ( $document['models'] ?? null ) : null;
		$model    = is_array( $models ) ? ( $models[ $model_id ] ?? null ) : null;
		$slots    = is_array( $model ) ? ( $model['slots'] ?? null ) : null;
		if ( ! is_array( $slots ) ) {
			return array();
		}

		$definitions = array();
		foreach ( $slots as $slot ) {
			if ( ! is_array( $slot ) ) {
				continue;
			}

			$id     = isset( $slot['id'] ) && is_string( $slot['id'] ) ? sanitize_key( $slot['id'] ) : '';
			$type   = isset( $slot['type'] ) && is_string( $slot['type'] ) ? sanitize_key( $slot['type'] ) : '';
			$accept = isset( $slot['accept'] ) && is_string( $slot['accept'] ) ? sanitize_key( $slot['accept'] ) : '';
			if ( '' === $id || 'media' !== $type || 'image' !== $accept ) {
				continue;
			}

			$definitions[ $id ] = array(
				'type'     => 'media',
				'required' => true === ( $slot['required'] ?? false ),
				'accept'   => 'image',
			);
		}

		return $definitions;
	}

	/**
	 * Return one declared media-slot definition.
	 *
	 * @param string $model_id Semantic model identifier.
	 * @param string $slot_id  Strategic media slot identifier.
	 * @return array{type:string,required:bool,accept:string}|null
	 */
	public static function slot( string $model_id, string $slot_id ): ?array {
		$slot_id = sanitize_key( $slot_id );
		$slots   = self::model_slots( $model_id );

		return '' !== $slot_id && isset( $slots[ $slot_id ] ) ? $slots[ $slot_id ] : null;
	}

	/**
	 * Load and validate the active preset's strategic-media document.
	 *
	 * @return array<string, mixed>|null
	 */
	private static function document(): ?array {
		if ( ! function_exists( 'seo_geo_theme_active_preset_id' ) || ! function_exists( 'seo_geo_theme_preset_root' ) || ! function_exists( 'wp_json_file_decode' ) ) {
			return null;
		}

		$preset_id = seo_geo_theme_active_preset_id();
		if ( null === $preset_id ) {
			return null;
		}

		$path = trailingslashit( seo_geo_theme_preset_root() ) . $preset_id . '/' . self::FILENAME;
		if ( ! is_readable( $path ) ) {
			return null;
		}

		$document = wp_json_file_decode( $path, array( 'associative' => true ) );
		if ( ! is_array( $document ) || self::SCHEMA_VERSION !== (int) ( $document['schema_version'] ?? 0 ) || ( $document['preset'] ?? null ) !== $preset_id ) {
			return null;
		}

		return $document;
	}
}
