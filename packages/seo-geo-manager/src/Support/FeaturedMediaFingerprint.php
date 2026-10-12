<?php
/**
 * Deterministic fingerprint for a content resource and its featured-media binding.
 *
 * @package SeoGeoManager
 */

declare(strict_types=1);

namespace SeoGeo\Manager\Support;

use WP_Post;

final class FeaturedMediaFingerprint {
	public static function for_post( WP_Post $post ): string {
		return hash(
			'sha256',
			ContentFingerprint::for_post( $post ) . '|featured_media=' . (string) get_post_thumbnail_id( $post->ID )
		);
	}
}
