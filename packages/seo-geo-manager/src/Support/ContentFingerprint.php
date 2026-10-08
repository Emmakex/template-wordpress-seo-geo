<?php
/**
 * Deterministic WordPress content-resource fingerprints.
 *
 * @package SeoGeoManager
 */

declare(strict_types=1);

namespace SeoGeo\Manager\Support;

use WP_Post;

final class ContentFingerprint {
	public static function for_post( WP_Post $post ): string {
		return hash(
			'sha256',
			implode(
				'|',
				array(
					(string) $post->ID,
					(string) $post->post_modified_gmt,
					(string) $post->post_title,
					(string) $post->post_name,
					(string) $post->post_status,
					(string) $post->post_content,
				)
			)
		);
	}
}
