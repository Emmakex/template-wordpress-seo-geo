<?php
/**
 * Minimal rescue manifest for reset-first rebuilds.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge\Reset;

use WP_HTML_Tag_Processor;
use WP_Post;

/**
 * Captures only the WordPress/search assets needed by a clean rebuild.
 */
final class RescueManifest {
	public const OPTION = 'seo_geo_reset_rescue_manifest_v1';

	/**
	 * SEO metadata keys worth carrying into a rebuild when present.
	 *
	 * @var list<string>
	 */
	private const SEO_META_KEYS = array(
		'_yoast_wpseo_title',
		'_yoast_wpseo_metadesc',
		'_yoast_wpseo_canonical',
		'_yoast_wpseo_meta-robots-noindex',
		'_yoast_wpseo_meta-robots-nofollow',
		'rank_math_title',
		'rank_math_description',
		'rank_math_canonical_url',
		'rank_math_robots',
	);

	/**
	 * Capture and persist the current rescue manifest.
	 *
	 * This intentionally ignores theme/plugin dependency classification.
	 *
	 * @return array<string,mixed>
	 */
	public function capture(): array {
		$resources = array();
		$post_ids  = get_posts(
			array(
				'post_type'              => array( 'page', 'post' ),
				'post_status'            => array( 'publish', 'draft', 'private', 'pending' ),
				'posts_per_page'         => -1,
				'orderby'                => 'ID',
				'order'                  => 'ASC',
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);

		foreach ( $post_ids as $post_id ) {
			$post = get_post( (int) $post_id );
			if ( ! $post instanceof WP_Post ) {
				continue;
			}

			$resources[] = $this->resource( $post );
		}

		$front_page_id   = (int) get_option( 'page_on_front', 0 );
		$posts_page_id   = (int) get_option( 'page_for_posts', 0 );
		$privacy_page_id = (int) get_option( 'wp_page_for_privacy_policy', 0 );

		$material = array(
			'schema_version' => 1,
			'mode'           => 'reset-rebuild-rescue-manifest',
			'site'           => array(
				'home_url'        => home_url( '/' ),
				'site_url'        => site_url( '/' ),
				'locale'          => get_locale(),
				'front_page_id'   => $front_page_id,
				'posts_page_id'   => $posts_page_id,
				'privacy_page_id' => $privacy_page_id,
			),
			'resources'      => $resources,
			'counts'         => array(
				'resources' => count( $resources ),
				'pages'     => count(
					array_filter(
						$resources,
						static fn( array $item ): bool => 'page' === ( $item['post_type'] ?? null )
					)
				),
				'posts'     => count(
					array_filter(
						$resources,
						static fn( array $item ): bool => 'post' === ( $item['post_type'] ?? null )
					)
				),
			),
			'policy'         => array(
				'legacy_theme_preserved'      => false,
				'legacy_builder_preserved'    => false,
				'legacy_plugins_preserved'    => false,
				'dependency_review_required'  => false,
				'unknown_components_blocking' => false,
			),
		);

		$manifest_sha = hash(
			'sha256',
			(string) wp_json_encode( $material, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE )
		);

		$manifest                    = $material;
		$manifest['captured_at']     = gmdate( DATE_ATOM );
		$manifest['manifest_sha256'] = $manifest_sha;

		update_option( self::OPTION, $manifest, false );

		return $manifest;
	}

	/**
	 * Return the saved manifest.
	 *
	 * @return array<string,mixed>|null
	 */
	public function saved(): ?array {
		$value = get_option( self::OPTION, null );

		return is_array( $value ) ? $value : null;
	}

	/**
	 * Build one resource record without embedding the legacy presentation runtime.
	 *
	 * @param WP_Post $post WordPress content resource.
	 * @return array<string,mixed>
	 */
	private function resource( WP_Post $post ): array {
		$content = (string) $post->post_content;
		$excerpt = (string) $post->post_excerpt;
		$links   = array();
		$media   = array();

		if ( class_exists( WP_HTML_Tag_Processor::class ) ) {
			$processor = new WP_HTML_Tag_Processor( $content );
			while ( $processor->next_tag() ) {
				$tag = strtoupper( (string) $processor->get_tag() );
				if ( 'A' === $tag ) {
					$href = $processor->get_attribute( 'href' );
					if ( is_string( $href ) && '' !== trim( $href ) ) {
						$links[] = trim( $href );
					}
				}
				if ( 'IMG' === $tag ) {
					$src = $processor->get_attribute( 'src' );
					if ( is_string( $src ) && '' !== trim( $src ) ) {
						$media[] = trim( $src );
					}
				}
			}
		}

		$links = array_values( array_unique( $links ) );
		$media = array_values( array_unique( $media ) );
		sort( $links );
		sort( $media );

		$seo = array();
		foreach ( self::SEO_META_KEYS as $meta_key ) {
			$value = get_post_meta( $post->ID, $meta_key, true );
			if ( is_scalar( $value ) && '' !== (string) $value ) {
				$seo[ $meta_key ] = (string) $value;
			} elseif ( is_array( $value ) && array() !== $value ) {
				$seo[ $meta_key ] = array_values( array_map( 'strval', $value ) );
			}
		}
		ksort( $seo );

		$path = '';
		if ( 'publish' === $post->post_status ) {
			$permalink = get_permalink( $post );
			if ( is_string( $permalink ) ) {
				$parsed = wp_parse_url( $permalink, PHP_URL_PATH );
				$path   = is_string( $parsed ) ? $parsed : '';
			}
		}

		return array(
			'id'             => (int) $post->ID,
			'post_type'      => (string) $post->post_type,
			'status'         => (string) $post->post_status,
			'title'          => get_the_title( $post ),
			'slug'           => (string) $post->post_name,
			'parent_id'      => (int) $post->post_parent,
			'path'           => $path,
			'content_sha256' => hash( 'sha256', $content ),
			'excerpt_sha256' => hash( 'sha256', $excerpt ),
			'links'          => $links,
			'media'          => $media,
			'seo'            => $seo,
		);
	}
}
