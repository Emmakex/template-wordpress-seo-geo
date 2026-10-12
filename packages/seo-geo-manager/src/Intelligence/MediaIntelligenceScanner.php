<?php
/**
 * Bounded media-library intelligence for Build / Finish workflows.
 *
 * @package SeoGeoManager
 */

declare(strict_types=1);

namespace SeoGeo\Manager\Intelligence;

use WP_Post;
use WP_Query;

final class MediaIntelligenceScanner {
	private const MAX_ATTACHMENTS = 200;
	private const MAX_MISSING_ALT_SAMPLE = 25;

	/**
	 * Inspect image-library hygiene without crawling remote media.
	 *
	 * @return array<string, mixed>
	 */
	public static function scan(): array {
		$query = new WP_Query(
			array(
				'post_type'              => 'attachment',
				'post_status'            => 'inherit',
				'post_mime_type'         => 'image',
				'posts_per_page'         => self::MAX_ATTACHMENTS,
				'orderby'                => 'ID',
				'order'                  => 'ASC',
				'no_found_rows'          => false,
				'update_post_meta_cache' => true,
				'update_post_term_cache' => false,
			)
		);

		$missing_alt = array();
		$with_alt    = 0;

		foreach ( $query->posts as $attachment ) {
			if ( ! $attachment instanceof WP_Post ) {
				continue;
			}

			$alt = get_post_meta( $attachment->ID, '_wp_attachment_image_alt', true );
			if ( is_string( $alt ) && '' !== trim( $alt ) ) {
				++$with_alt;
				continue;
			}

			if ( count( $missing_alt ) < self::MAX_MISSING_ALT_SAMPLE ) {
				$missing_alt[] = array(
					'id'    => (int) $attachment->ID,
					'title' => html_entity_decode( get_the_title( $attachment ), ENT_QUOTES | ENT_HTML5, get_bloginfo( 'charset' ) ),
					'url'   => wp_get_attachment_url( $attachment->ID ) ?: '',
				);
			}
		}

		$scanned = count( $query->posts );
		$missing = max( 0, $scanned - $with_alt );

		return array(
			'bounded'            => true,
			'max_attachments'    => self::MAX_ATTACHMENTS,
			'total_images'       => (int) $query->found_posts,
			'scanned_images'     => $scanned,
			'images_with_alt'    => $with_alt,
			'images_missing_alt' => $missing,
			'missing_alt_sample' => $missing_alt,
			'truncated'          => (int) $query->found_posts > self::MAX_ATTACHMENTS,
		);
	}
}
