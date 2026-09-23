<?php
/**
 * Elementor content dependency detector.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge\Content;

use WP_Post;

/**
 * Detects Elementor post-meta coupling without exporting Elementor payloads.
 */
final class ElementorContentDetector implements ContentDependencyDetectorInterface {
	/**
	 * Return the stable dependency identifier.
	 */
	public function id(): string {
		return 'elementor';
	}

	/**
	 * Inspect one resource for Elementor ownership signals.
	 *
	 * @param WP_Post $post WordPress post/resource.
	 * @return array{coupled:bool,evidence:list<string>}
	 */
	public function inspect( WP_Post $post ): array {
		$evidence  = array();
		$edit_mode = get_post_meta( $post->ID, '_elementor_edit_mode', true );
		$data      = get_post_meta( $post->ID, '_elementor_data', true );

		if ( 'builder' === $edit_mode ) {
			$evidence[] = 'post-meta:_elementor_edit_mode=builder';
		}

		if ( ( is_string( $data ) && '' !== trim( $data ) ) || ( is_array( $data ) && array() !== $data ) ) {
			$evidence[] = 'post-meta:_elementor_data:present';
		}

		return array(
			'coupled'  => array() !== $evidence,
			'evidence' => $evidence,
		);
	}
}
