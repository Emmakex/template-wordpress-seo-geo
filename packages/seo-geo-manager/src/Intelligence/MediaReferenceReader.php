<?php
/**
 * Bounded image-media reference discovery for external orchestration.
 *
 * @package SeoGeoManager
 */

declare(strict_types=1);

namespace SeoGeo\Manager\Intelligence;

use WP_Error;
use WP_Post;
use WP_Query;

final class MediaReferenceReader {
	private const MAX_MEDIA = 100;

	/**
	 * List bounded image references available to the current operator.
	 *
	 * @return array<string, mixed>
	 */
	public static function list_media(): array {
		$query = new WP_Query(
			array(
				'post_type'              => 'attachment',
				'post_status'            => 'inherit',
				'post_mime_type'         => 'image',
				'posts_per_page'         => self::MAX_MEDIA,
				'orderby'                => 'ID',
				'order'                  => 'ASC',
				'no_found_rows'          => false,
				'update_post_meta_cache' => true,
				'update_post_term_cache' => false,
			)
		);

		$items = array();
		foreach ( $query->posts as $attachment ) {
			if ( ! $attachment instanceof WP_Post || ! current_user_can( 'edit_post', $attachment->ID ) ) {
				continue;
			}
			$items[] = self::summary( $attachment );
		}

		return array(
			'schema_version' => 1,
			'bounded'        => true,
			'media_count'    => count( $items ),
			'total_images'   => (int) $query->found_posts,
			'truncated'      => (int) $query->found_posts > self::MAX_MEDIA,
			'limits'         => array( 'max_media' => self::MAX_MEDIA ),
			'items'          => $items,
			'policy'         => self::policy(),
		);
	}

	/**
	 * Read one image reference without exposing raw attachment metadata/EXIF.
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	public static function read_media( int $media_id ) {
		$attachment = get_post( $media_id );
		$is_image   = $attachment instanceof WP_Post && str_starts_with( strtolower( (string) $attachment->post_mime_type ), 'image/' );
		if ( ! $attachment instanceof WP_Post || 'attachment' !== $attachment->post_type || ! $is_image ) {
			return new WP_Error(
				'seo_geo_manager_media_not_found',
				'The requested image attachment was not found.',
				array( 'status' => 404 )
			);
		}
		if ( ! current_user_can( 'edit_post', $attachment->ID ) ) {
			return new WP_Error(
				'seo_geo_manager_media_forbidden',
				'You cannot inspect this image attachment.',
				array( 'status' => 403 )
			);
		}

		return array(
			'schema_version' => 1,
			'bounded'        => true,
			'media'          => self::summary( $attachment ),
			'policy'         => self::policy(),
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function summary( WP_Post $attachment ): array {
		$alt      = get_post_meta( $attachment->ID, '_wp_attachment_image_alt', true );
		$alt      = is_string( $alt ) ? $alt : '';
		$url      = wp_get_attachment_url( $attachment->ID );
		$url      = is_string( $url ) ? $url : '';
		$metadata = wp_get_attachment_metadata( $attachment->ID );
		$metadata = is_array( $metadata ) ? $metadata : array();
		$width    = isset( $metadata['width'] ) && is_numeric( $metadata['width'] ) ? (int) $metadata['width'] : 0;
		$height   = isset( $metadata['height'] ) && is_numeric( $metadata['height'] ) ? (int) $metadata['height'] : 0;

		$fingerprint_payload = array(
			'id'           => (int) $attachment->ID,
			'modified_gmt' => (string) $attachment->post_modified_gmt,
			'title'        => (string) $attachment->post_title,
			'mime'         => (string) $attachment->post_mime_type,
			'parent'       => (int) $attachment->post_parent,
			'url'          => $url,
			'alt'          => $alt,
			'width'        => $width,
			'height'       => $height,
		);

		return array(
			'id'          => (int) $attachment->ID,
			'status'      => (string) $attachment->post_status,
			'title'       => html_entity_decode( get_the_title( $attachment ), ENT_QUOTES | ENT_HTML5, get_bloginfo( 'charset' ) ),
			'mime_type'   => (string) $attachment->post_mime_type,
			'url'         => $url,
			'attached_to' => (int) $attachment->post_parent,
			'alt'         => $alt,
			'alt_present' => '' !== trim( $alt ),
			'dimensions'  => array(
				'width'  => $width,
				'height' => $height,
			),
			'fingerprint' => hash( 'sha256', (string) wp_json_encode( $fingerprint_payload ) ),
		);
	}

	/**
	 * @return array<string, bool>
	 */
	private static function policy(): array {
		return array(
			'raw_attachment_metadata_returned' => false,
			'exif_returned'                    => false,
			'fabricated_metadata'              => false,
			'mutation_supported'               => false,
		);
	}
}
