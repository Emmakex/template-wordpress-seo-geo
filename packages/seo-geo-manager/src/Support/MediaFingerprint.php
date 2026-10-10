<?php
/**
 * Deterministic image-attachment fingerprints.
 *
 * @package SeoGeoManager
 */

declare(strict_types=1);

namespace SeoGeo\Manager\Support;

use WP_Post;

final class MediaFingerprint {
	public static function for_attachment( WP_Post $attachment ): string {
		$alt_exists = metadata_exists( 'post', $attachment->ID, '_wp_attachment_image_alt' );
		$alt        = get_post_meta( $attachment->ID, '_wp_attachment_image_alt', true );
		$alt        = is_string( $alt ) ? $alt : '';
		$url        = wp_get_attachment_url( $attachment->ID );
		$url        = is_string( $url ) ? $url : '';
		$metadata   = wp_get_attachment_metadata( $attachment->ID );
		$metadata   = is_array( $metadata ) ? $metadata : array();
		$width      = isset( $metadata['width'] ) && is_numeric( $metadata['width'] ) ? (int) $metadata['width'] : 0;
		$height     = isset( $metadata['height'] ) && is_numeric( $metadata['height'] ) ? (int) $metadata['height'] : 0;

		return hash(
			'sha256',
			(string) wp_json_encode(
				array(
					'id'           => (int) $attachment->ID,
					'modified_gmt' => (string) $attachment->post_modified_gmt,
					'title'        => (string) $attachment->post_title,
					'slug'         => (string) $attachment->post_name,
					'status'       => (string) $attachment->post_status,
					'caption'      => (string) $attachment->post_excerpt,
					'description'  => (string) $attachment->post_content,
					'mime'         => (string) $attachment->post_mime_type,
					'parent'       => (int) $attachment->post_parent,
					'url'          => $url,
					'alt_exists'   => $alt_exists,
					'alt'          => $alt,
					'width'        => $width,
					'height'       => $height,
				)
			)
		);
	}
}
