<?php
/**
 * Bounded featured-media state reader for external orchestration.
 *
 * @package SeoGeoManager
 */

declare(strict_types=1);

namespace SeoGeo\Manager\Intelligence;

use SeoGeo\Manager\Support\FeaturedMediaFingerprint;
use SeoGeo\Manager\Support\MediaFingerprint;
use WP_Error;
use WP_Post;

final class FeaturedMediaReader {
	/**
	 * Read the current featured-media binding for one post/page.
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	public static function read( int $resource_id ) {
		$post = get_post( $resource_id );
		if ( ! self::is_supported_resource( $post ) ) {
			return new WP_Error(
				'seo_geo_manager_featured_media_resource_not_found',
				'The requested post/page was not found.',
				array( 'status' => 404 )
			);
		}
		if ( ! current_user_can( 'edit_post', $post->ID ) || ! current_user_can( 'upload_files' ) ) {
			return new WP_Error(
				'seo_geo_manager_featured_media_forbidden',
				'You cannot inspect featured media for this resource.',
				array( 'status' => 403 )
			);
		}

		$media_id = (int) get_post_thumbnail_id( $post->ID );
		$media    = 0 < $media_id ? get_post( $media_id ) : null;
		$image    = self::is_image_attachment( $media ) ? self::media_summary( $media ) : null;

		return array(
			'schema_version' => 1,
			'resource'       => array(
				'id'                => (int) $post->ID,
				'post_type'         => (string) $post->post_type,
				'status'            => (string) $post->post_status,
				'title'             => html_entity_decode( get_the_title( $post ), ENT_QUOTES | ENT_HTML5, get_bloginfo( 'charset' ) ),
				'featured_media_id' => $media_id,
				'fingerprint'       => FeaturedMediaFingerprint::for_post( $post ),
			),
			'media'          => $image,
			'policy'         => array(
				'bounded'                     => true,
				'posts_pages_only'            => true,
				'image_attachment_only'       => true,
				'raw_attachment_metadata'     => false,
				'binary_mutation_supported'   => false,
				'content_mutation_supported'  => false,
				'preview_before_mutation'     => true,
				'expected_fingerprint_source' => 'resource.fingerprint',
			),
		);
	}

	/** @param WP_Post|mixed $post */
	private static function is_supported_resource( $post ): bool {
		return $post instanceof WP_Post
			&& in_array( $post->post_type, array( 'post', 'page' ), true )
			&& ! in_array( $post->post_status, array( 'trash', 'auto-draft' ), true );
	}

	/** @param WP_Post|mixed $post */
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
			'title'       => html_entity_decode( get_the_title( $media ), ENT_QUOTES | ENT_HTML5, get_bloginfo( 'charset' ) ),
			'mime_type'   => (string) $media->post_mime_type,
			'url'         => is_string( $url ) ? $url : '',
			'alt'         => is_string( $alt ) ? $alt : '',
			'fingerprint' => MediaFingerprint::for_attachment( $media ),
		);
	}
}
