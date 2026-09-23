<?php
/**
 * Read-only content dependency scanner.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge\Content;

use WP_Post;

/**
 * Maps WordPress resources to builders and shortcode dependencies.
 */
final class ContentDependencyScanner {
	/**
	 * Dependency detectors.
	 *
	 * @var list<ContentDependencyDetectorInterface>
	 */
	private array $detectors;

	/**
	 * Construct the scanner.
	 *
	 * @param array<int, ContentDependencyDetectorInterface>|null $detectors Optional detector override.
	 */
	public function __construct( ?array $detectors = null ) {
		$this->detectors = $detectors ?? array(
			new NativeBlocksContentDetector(),
			new ElementorContentDetector(),
			new DiviContentDetector(),
		);
	}

	/**
	 * Scan content without exporting post bodies or builder payloads.
	 *
	 * @return array{
	 *     scanned:int,
	 *     coupled:int,
	 *     resources:list<array<string,mixed>>,
	 *     builder_counts:array<string,int>,
	 *     shortcode_counts:array<string,int>,
	 *     safety:array<string,bool>
	 * }
	 */
	public function scan(): array {
		$post_types = array_values(
			array_filter(
				get_post_types(
					array(
						'show_ui' => true,
					),
					'names'
				),
				static fn( string $post_type ): bool => ! in_array( $post_type, array( 'attachment', 'revision', 'nav_menu_item' ), true )
			)
		);

		$post_ids = array();
		if ( array() !== $post_types ) {
			$post_ids = get_posts(
				array(
					'post_type'        => $post_types,
					'post_status'      => array( 'publish', 'private', 'draft', 'pending', 'future' ),
					'numberposts'      => -1,
					'fields'           => 'ids',
					'orderby'          => 'ID',
					'order'            => 'ASC',
					'suppress_filters' => false,
				)
			);
		}

		$resources        = array();
		$builder_counts   = array();
		$shortcode_counts = array();
		$scanned          = 0;

		foreach ( $post_ids as $post_id ) {
			$post = get_post( (int) $post_id );
			if ( ! $post instanceof WP_Post ) {
				continue;
			}

			++$scanned;
			$builders = array();

			foreach ( $this->detectors as $detector ) {
				$result = $detector->inspect( $post );
				if ( true !== $result['coupled'] ) {
					continue;
				}

				$id = $detector->id();
				$builders[] = array(
					'id'       => $id,
					'evidence' => array_values( array_map( 'strval', $result['evidence'] ) ),
				);
				$builder_counts[ $id ] = ( $builder_counts[ $id ] ?? 0 ) + 1;
			}

			$shortcodes = $this->shortcodes_in_content( $post->post_content );
			foreach ( $shortcodes as $shortcode ) {
				$shortcode_counts[ $shortcode ] = ( $shortcode_counts[ $shortcode ] ?? 0 ) + 1;
			}

			if ( array() === $builders && array() === $shortcodes ) {
				continue;
			}

			$resources[] = array(
				'object_id'  => (int) $post->ID,
				'post_type'  => (string) $post->post_type,
				'status'     => (string) $post->post_status,
				'public_url' => $this->public_url( $post ),
				'builders'   => $builders,
				'shortcodes' => $shortcodes,
			);
		}

		ksort( $builder_counts );
		ksort( $shortcode_counts );

		return array(
			'scanned'          => $scanned,
			'coupled'          => count( $resources ),
			'resources'        => $resources,
			'builder_counts'   => $builder_counts,
			'shortcode_counts' => $shortcode_counts,
			'safety'           => array(
				'content_scan_performed'   => true,
				'raw_content_exported'      => false,
				'builder_payload_exported'  => false,
				'private_body_exported'     => false,
			),
		);
	}

	/**
	 * Extract shortcode identifiers from content without exporting shortcode payloads.
	 *
	 * @param string $content Post content.
	 * @return list<string>
	 */
	private function shortcodes_in_content( string $content ): array {
		if ( '' === $content || ! str_contains( $content, '[' ) ) {
			return array();
		}

		$matches = array();
		$result  = preg_match_all( '/' . get_shortcode_regex() . '/s', $content, $matches );
		if ( false === $result || 0 === $result ) {
			return array();
		}

		$shortcodes = array_values(
			array_unique(
				array_filter(
					array_map( 'strval', $matches[2] ),
					static fn( string $shortcode ): bool => '' !== $shortcode
				)
			)
		);
		sort( $shortcodes );

		return $shortcodes;
	}

	/**
	 * Return a public permalink only when the resource is currently public.
	 *
	 * @param WP_Post $post WordPress post/resource.
	 */
	private function public_url( WP_Post $post ): ?string {
		if ( 'publish' !== $post->post_status ) {
			return null;
		}

		$post_type = get_post_type_object( $post->post_type );
		if ( null === $post_type || true !== $post_type->public || true !== $post_type->publicly_queryable ) {
			return null;
		}

		$url = get_permalink( $post );
		return false === $url ? null : $url;
	}
}
