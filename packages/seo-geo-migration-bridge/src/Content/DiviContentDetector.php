<?php
/**
 * Divi content dependency detector.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge\Content;

use WP_Post;

/**
 * Detects Divi builder coupling without exporting Divi content.
 */
final class DiviContentDetector implements ContentDependencyDetectorInterface {
	/**
	 * Return the stable dependency identifier.
	 */
	public function id(): string {
		return 'divi';
	}

	/**
	 * Inspect one resource for Divi ownership signals.
	 *
	 * @param WP_Post $post WordPress post/resource.
	 * @return array{coupled:bool,evidence:list<string>}
	 */
	public function inspect( WP_Post $post ): array {
		$evidence    = array();
		$use_builder = get_post_meta( $post->ID, 'et_pb_use_builder', true );

		if ( 'on' === $use_builder ) {
			$evidence[] = 'post-meta:et_pb_use_builder=on';
		}

		if ( str_contains( $post->post_content, '[et_pb_' ) ) {
			$evidence[] = 'shortcode-prefix:et_pb_';
		}

		return array(
			'coupled'  => array() !== $evidence,
			'evidence' => $evidence,
		);
	}
}
