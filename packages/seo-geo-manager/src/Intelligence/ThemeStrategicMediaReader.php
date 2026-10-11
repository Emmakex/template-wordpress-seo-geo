<?php
/**
 * Bounded Theme strategic-media binding reader for external orchestration.
 *
 * @package SeoGeoManager
 */

declare(strict_types=1);

namespace SeoGeo\Manager\Intelligence;

use SeoGeo\Manager\Support\MediaFingerprint;
use WP_Error;
use WP_Post;

/** Inspect one Theme-declared strategic media binding without reading Theme internals. */
final class ThemeStrategicMediaReader {
	private const SCHEMA_VERSION = 1;

	/**
	 * Read one declared strategic-media binding from the active Theme.
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	public static function read( int $resource_id, string $model_id, string $slot_id ) {
		$model_id = sanitize_key( $model_id );
		$slot_id  = sanitize_key( $slot_id );
		if ( 1 > $resource_id || '' === $model_id || '' === $slot_id ) {
			return new WP_Error(
				'seo_geo_manager_theme_media_binding_request_invalid',
				'Theme media-binding inspection requires resource_id, model_id and slot_id.',
				array( 'status' => 400 )
			);
		}

		if ( ! self::theme_read_supported() ) {
			return new WP_Error(
				'seo_geo_manager_theme_media_binding_unsupported',
				'The active Theme does not expose the strategic-media read contract required by Manager.',
				array( 'status' => 409 )
			);
		}

		$resource = get_post( $resource_id );
		if ( ! self::is_supported_resource( $resource ) ) {
			return new WP_Error(
				'seo_geo_manager_theme_media_binding_resource_not_found',
				'The requested strategic Theme page was not found.',
				array( 'status' => 404 )
			);
		}
		if ( ! current_user_can( 'edit_post', $resource->ID ) || ! current_user_can( 'upload_files' ) ) {
			return new WP_Error(
				'seo_geo_manager_theme_media_binding_forbidden',
				'You cannot inspect strategic media bindings for this page.',
				array( 'status' => 403 )
			);
		}

		$slots      = self::theme_contract( $model_id );
		$definition = $slots[ $slot_id ] ?? null;
		if ( ! self::is_image_slot_definition( $definition ) ) {
			return new WP_Error(
				'seo_geo_manager_theme_media_binding_slot_not_declared',
				'The active Theme does not declare this model/slot as an image media binding.',
				array( 'status' => 404 )
			);
		}

		$attachment_id = absint( self::theme_slot( $resource->ID, $model_id, $slot_id ) );
		$attachment    = 0 < $attachment_id ? get_post( $attachment_id ) : null;
		$media         = self::is_image_attachment( $attachment ) ? self::media_summary( $attachment ) : null;
		$fingerprint   = self::theme_fingerprint( $resource->ID, $model_id, $slot_id );
		if ( '' === $fingerprint ) {
			return new WP_Error(
				'seo_geo_manager_theme_media_binding_fingerprint_unavailable',
				'The active Theme did not provide a strategic-media binding fingerprint.',
				array( 'status' => 409 )
			);
		}

		return array(
			'schema_version' => self::SCHEMA_VERSION,
			'resource'       => array(
				'id'        => (int) $resource->ID,
				'post_type' => (string) $resource->post_type,
				'status'    => (string) $resource->post_status,
				'title'     => html_entity_decode( get_the_title( $resource ), ENT_QUOTES | ENT_HTML5, self::charset() ),
			),
			'binding'        => array(
				'model_id'      => $model_id,
				'slot_id'       => $slot_id,
				'attachment_id' => $attachment_id,
				'fingerprint'   => $fingerprint,
			),
			'slot'           => array(
				'type'     => 'media',
				'required' => true === ( $definition['required'] ?? false ),
				'accept'   => 'image',
			),
			'media'          => $media,
			'policy'         => array(
				'bounded'                     => true,
				'page_resources_only'         => true,
				'declared_theme_slots_only'   => true,
				'image_attachment_only'       => true,
				'raw_attachment_metadata'     => false,
				'binary_mutation_supported'   => false,
				'content_mutation_supported'  => false,
				'theme_layout_authority'      => true,
				'preview_before_mutation'     => true,
				'expected_fingerprint_source' => 'binding.fingerprint',
			),
		);
	}

	/** Whether the active Theme exposes the complete read-side media contract. */
	public static function theme_read_supported(): bool {
		return function_exists( 'seo_geo_theme_strategic_media_contract' )
			&& function_exists( 'seo_geo_theme_strategic_media_slot' )
			&& function_exists( 'seo_geo_theme_strategic_media_fingerprint' );
	}

	/**
	 * Invoke the Theme contract callback after capability discovery.
	 *
	 * @return array<string, array{type:string,required:bool,accept:string}>
	 */
	private static function theme_contract( string $model_id ): array {
		/** @var callable(string):array<string, array{type:string,required:bool,accept:string}> $callback */
		$callback = 'seo_geo_theme_strategic_media_contract';

		return $callback( $model_id );
	}

	/** Invoke the Theme slot callback after capability discovery. */
	private static function theme_slot( int $resource_id, string $model_id, string $slot_id ): int {
		/** @var callable(int, string, string):int $callback */
		$callback = 'seo_geo_theme_strategic_media_slot';

		return $callback( $resource_id, $model_id, $slot_id );
	}

	/** Invoke the Theme fingerprint callback after capability discovery. */
	private static function theme_fingerprint( int $resource_id, string $model_id, string $slot_id ): string {
		/** @var callable(int, string, string):string $callback */
		$callback = 'seo_geo_theme_strategic_media_fingerprint';

		return $callback( $resource_id, $model_id, $slot_id );
	}

	/**
	 * Check whether one WordPress resource can own Theme strategic media.
	 *
	 * @param WP_Post|mixed $post WordPress resource.
	 */
	private static function is_supported_resource( $post ): bool {
		return $post instanceof WP_Post
			&& 'page' === $post->post_type
			&& ! in_array( $post->post_status, array( 'trash', 'auto-draft' ), true );
	}

	/**
	 * Check whether one Theme slot definition is a supported image binding.
	 *
	 * @param mixed $definition Theme slot definition.
	 */
	private static function is_image_slot_definition( $definition ): bool {
		return is_array( $definition )
			&& 'media' === ( $definition['type'] ?? null )
			&& 'image' === ( $definition['accept'] ?? null );
	}

	/**
	 * Check whether one WordPress resource is an image attachment.
	 *
	 * @param WP_Post|mixed $post WordPress resource.
	 */
	private static function is_image_attachment( $post ): bool {
		return $post instanceof WP_Post
			&& 'attachment' === $post->post_type
			&& str_starts_with( strtolower( (string) $post->post_mime_type ), 'image/' );
	}

	/** @return array<string, mixed> */
	private static function media_summary( WP_Post $media ): array {
		$url = wp_get_attachment_url( $media->ID );
		$alt = get_post_meta( $media->ID, '_wp_attachment_image_alt', true );

		return array(
			'id'          => (int) $media->ID,
			'title'       => html_entity_decode( get_the_title( $media ), ENT_QUOTES | ENT_HTML5, self::charset() ),
			'mime_type'   => (string) $media->post_mime_type,
			'url'         => is_string( $url ) ? $url : '',
			'alt'         => is_string( $alt ) ? $alt : '',
			'fingerprint' => MediaFingerprint::for_attachment( $media ),
		);
	}

	/** Resolve the WordPress document charset with a safe default. */
	private static function charset(): string {
		$charset = get_bloginfo( 'charset' );

		return is_string( $charset ) && '' !== $charset ? $charset : 'UTF-8';
	}
}
