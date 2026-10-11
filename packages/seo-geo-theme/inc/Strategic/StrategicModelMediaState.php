<?php
/**
 * Theme-owned persistence for strategic semantic media bindings.
 *
 * @package SeoGeoTheme
 */

declare(strict_types=1);

namespace SeoGeo\Theme\Strategic;

use WP_Error;
use WP_Post;

final class StrategicModelMediaState {
	public const META_KEY = '_seo_geo_theme_strategic_media_v1';

	private const SCHEMA_VERSION = 1;
	private const MAX_MODELS     = 20;
	private const MAX_SLOTS      = 50;

	/** Register the protected, non-REST post meta owned by the Theme. */
	public static function register(): void {
		add_action( 'init', array( self::class, 'register_meta' ) );
	}

	/** Register the versioned page-meta container. */
	public static function register_meta(): void {
		register_post_meta(
			'page',
			self::META_KEY,
			array(
				'type'              => 'array',
				'single'            => true,
				'show_in_rest'      => false,
				'sanitize_callback' => array( self::class, 'sanitize_state' ),
			)
		);
	}

	/**
	 * Sanitize the stored state without inventing model or attachment data.
	 *
	 * @param mixed $value Candidate meta value.
	 * @return array{schema_version:int,models:array<string, array<string, int>>}
	 */
	public static function sanitize_state( $value ): array {
		$clean = array(
			'schema_version' => self::SCHEMA_VERSION,
			'models'         => array(),
		);
		if ( ! is_array( $value ) || self::SCHEMA_VERSION !== (int) ( $value['schema_version'] ?? self::SCHEMA_VERSION ) ) {
			return $clean;
		}

		$models = $value['models'] ?? null;
		if ( ! is_array( $models ) ) {
			return $clean;
		}

		$model_count = 0;
		foreach ( $models as $model_id => $slots ) {
			if ( self::MAX_MODELS <= $model_count || ! is_string( $model_id ) || ! is_array( $slots ) ) {
				continue;
			}

			$model_id = sanitize_key( $model_id );
			if ( '' === $model_id ) {
				continue;
			}

			$clean_slots = array();
			$slot_count  = 0;
			foreach ( $slots as $slot_id => $attachment_id ) {
				if ( self::MAX_SLOTS <= $slot_count || ! is_string( $slot_id ) ) {
					continue;
				}

				$slot_id       = sanitize_key( $slot_id );
				$attachment_id = absint( $attachment_id );
				if ( '' === $slot_id || 1 > $attachment_id ) {
					continue;
				}

				$clean_slots[ $slot_id ] = $attachment_id;
				++$slot_count;
			}

			if ( array() !== $clean_slots ) {
				$clean['models'][ $model_id ] = $clean_slots;
				++$model_count;
			}
		}

		return $clean;
	}

	/**
	 * Return the effective image attachment for one declared media slot.
	 */
	public static function slot( int $post_id, string $model_id, string $slot_id ): int {
		$model_id = sanitize_key( $model_id );
		$slot_id  = sanitize_key( $slot_id );
		if ( 1 > $post_id || null === StrategicMediaContract::slot( $model_id, $slot_id ) ) {
			return 0;
		}

		$state         = self::state( $post_id );
		$attachment_id = isset( $state['models'][ $model_id ][ $slot_id ] ) ? absint( $state['models'][ $model_id ][ $slot_id ] ) : 0;
		if ( 1 > $attachment_id ) {
			return 0;
		}

		$attachment = get_post( $attachment_id );
		return self::is_image_attachment( $attachment ) ? $attachment_id : 0;
	}

	/**
	 * Stable fingerprint for one effective model/slot binding.
	 */
	public static function fingerprint( int $post_id, string $model_id, string $slot_id ): string {
		$model_id = sanitize_key( $model_id );
		$slot_id  = sanitize_key( $slot_id );

		return hash( 'sha256', $post_id . '|' . $model_id . '|' . $slot_id . '|' . self::slot( $post_id, $model_id, $slot_id ) );
	}

	/**
	 * Bind one declared strategic media slot to an existing WordPress image.
	 *
	 * @return true|WP_Error
	 */
	public static function set_slot( int $post_id, string $model_id, string $slot_id, int $attachment_id ) {
		$guard = self::mutation_guard( $post_id, $model_id, $slot_id, $attachment_id );
		if ( is_wp_error( $guard ) ) {
			return $guard;
		}

		$model_id = sanitize_key( $model_id );
		$slot_id  = sanitize_key( $slot_id );
		$state    = self::state( $post_id );
		if ( ! isset( $state['models'][ $model_id ] ) || ! is_array( $state['models'][ $model_id ] ) ) {
			$state['models'][ $model_id ] = array();
		}
		$state['models'][ $model_id ][ $slot_id ] = $attachment_id;
		$state                                      = self::sanitize_state( $state );

		update_post_meta( $post_id, self::META_KEY, $state );
		if ( $attachment_id !== self::slot( $post_id, $model_id, $slot_id ) ) {
			return new WP_Error( 'seo_geo_theme_strategic_media_store_failed', 'Strategic media binding could not be verified.' );
		}

		return true;
	}

	/**
	 * Remove one declared strategic media binding while preserving other slots.
	 *
	 * @return true|WP_Error
	 */
	public static function remove_slot( int $post_id, string $model_id, string $slot_id ) {
		$model_id = sanitize_key( $model_id );
		$slot_id  = sanitize_key( $slot_id );
		$guard    = self::mutation_guard( $post_id, $model_id, $slot_id, null );
		if ( is_wp_error( $guard ) ) {
			return $guard;
		}

		$state = self::state( $post_id );
		unset( $state['models'][ $model_id ][ $slot_id ] );
		if ( isset( $state['models'][ $model_id ] ) && array() === $state['models'][ $model_id ] ) {
			unset( $state['models'][ $model_id ] );
		}

		if ( array() === $state['models'] ) {
			delete_post_meta( $post_id, self::META_KEY );
		} else {
			update_post_meta( $post_id, self::META_KEY, self::sanitize_state( $state ) );
		}

		if ( 0 !== self::slot( $post_id, $model_id, $slot_id ) ) {
			return new WP_Error( 'seo_geo_theme_strategic_media_remove_failed', 'Strategic media removal could not be verified.' );
		}

		return true;
	}

	/**
	 * @return array{schema_version:int,models:array<string, array<string, int>>}
	 */
	private static function state( int $post_id ): array {
		$value = 0 < $post_id ? get_post_meta( $post_id, self::META_KEY, true ) : null;

		return self::sanitize_state( $value );
	}

	/**
	 * @return true|WP_Error
	 */
	private static function mutation_guard( int $post_id, string $model_id, string $slot_id, ?int $attachment_id ) {
		$post = get_post( $post_id );
		if ( ! $post instanceof WP_Post || 'page' !== $post->post_type || in_array( $post->post_status, array( 'trash', 'auto-draft' ), true ) ) {
			return new WP_Error( 'seo_geo_theme_strategic_media_page_invalid', 'Strategic media can only be bound to an existing page.' );
		}
		if ( ! current_user_can( 'edit_post', $post_id ) || ! current_user_can( 'upload_files' ) ) {
			return new WP_Error( 'seo_geo_theme_strategic_media_forbidden', 'You cannot change strategic media on this page.' );
		}

		$model_id = sanitize_key( $model_id );
		$slot_id  = sanitize_key( $slot_id );
		if ( null === StrategicMediaContract::slot( $model_id, $slot_id ) ) {
			return new WP_Error( 'seo_geo_theme_strategic_media_slot_unknown', 'The requested strategic media slot is not declared by the active Theme preset.' );
		}

		if ( null !== $attachment_id ) {
			$attachment = get_post( $attachment_id );
			if ( ! self::is_image_attachment( $attachment ) ) {
				return new WP_Error( 'seo_geo_theme_strategic_media_attachment_invalid', 'The requested strategic media resource is not a WordPress image attachment.' );
			}
			if ( ! current_user_can( 'edit_post', $attachment_id ) ) {
				return new WP_Error( 'seo_geo_theme_strategic_media_attachment_forbidden', 'You cannot use this image attachment.' );
			}
		}

		return true;
	}

	/** @param WP_Post|mixed $post */
	private static function is_image_attachment( $post ): bool {
		return $post instanceof WP_Post
			&& 'attachment' === $post->post_type
			&& str_starts_with( strtolower( (string) $post->post_mime_type ), 'image/' );
	}
}
