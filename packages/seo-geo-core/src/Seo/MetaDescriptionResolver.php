<?php
/**
 * Meta description resolver.
 *
 * @package SeoGeoCore
 */

declare(strict_types=1);

namespace SeoGeo\Core\Seo;

use WP_Post;

/**
 * Resolves one concise description for native SEO output.
 */
final class MetaDescriptionResolver {
	private const MAX_LENGTH = 160;

	/**
	 * Resolve the current request description.
	 */
	public function resolve(): ?string {
		$source = '';

		if ( is_singular() ) {
			$post = get_post( get_queried_object_id() );

			if ( $post instanceof WP_Post ) {
				$source = has_excerpt( $post ) ? get_the_excerpt( $post ) : $post->post_content;
			}
		} elseif ( is_front_page() || is_home() ) {
			$source = get_bloginfo( 'description' );
		} elseif ( is_category() || is_tag() || is_tax() ) {
			$source = term_description();
		}

		$description = $this->normalize( $source );

		/**
		 * Filters the resolved native meta description.
		 *
		 * Returning an empty or non-string value suppresses output.
		 *
		 * @param string|null $description Meta description.
		 */
		$filtered = apply_filters( 'seo_geo_meta_description', $description );

		if ( ! is_string( $filtered ) ) {
			return null;
		}

		$filtered = trim( $filtered );

		return '' !== $filtered ? $this->truncate( $filtered ) : null;
	}

	/**
	 * Normalize source content into plain text.
	 *
	 * @param string $source Raw source content.
	 */
	private function normalize( string $source ): ?string {
		if ( '' === trim( $source ) ) {
			return null;
		}

		$charset = get_bloginfo( 'charset' );
		if ( '' === $charset ) {
			$charset = 'UTF-8';
		}

		$text = strip_shortcodes( $source );
		$text = wp_strip_all_tags( $text, true );
		$text = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, $charset );
		$text = preg_replace( '/\s+/u', ' ', $text );

		if ( ! is_string( $text ) ) {
			return null;
		}

		$text = trim( $text );

		return '' !== $text ? $this->truncate( $text ) : null;
	}

	/**
	 * Enforce the native description length ceiling.
	 *
	 * @param string $description Plain-text description.
	 */
	private function truncate( string $description ): string {
		if ( self::MAX_LENGTH >= mb_strlen( $description ) ) {
			return $description;
		}

		$trimmed = mb_substr( $description, 0, self::MAX_LENGTH - 1 );
		$trimmed = rtrim( $trimmed, " \t\n\r\0\x0B,.;:-" );

		return $trimmed . '…';
	}
}
