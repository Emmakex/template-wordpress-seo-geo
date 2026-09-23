<?php
/**
 * Native block content dependency detector.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge\Content;

use WP_Post;

/**
 * Detects native WordPress block markup without exporting block content.
 */
final class NativeBlocksContentDetector implements ContentDependencyDetectorInterface {
	/**
	 * Return the stable dependency identifier.
	 */
	public function id(): string {
		return 'native-blocks';
	}

	/**
	 * Inspect one resource for native block markup.
	 *
	 * @param WP_Post $post WordPress post/resource.
	 * @return array{coupled:bool,evidence:list<string>}
	 */
	public function inspect( WP_Post $post ): array {
		$coupled = function_exists( 'has_blocks' ) && has_blocks( $post->post_content );

		return array(
			'coupled'  => $coupled,
			'evidence' => $coupled ? array( 'block-markup-present' ) : array(),
		);
	}
}
