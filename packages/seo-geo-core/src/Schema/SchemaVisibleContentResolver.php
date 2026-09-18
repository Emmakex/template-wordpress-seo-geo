<?php
/**
 * Visible-content authority for native Schema output.
 *
 * @package SeoGeoCore
 */

declare(strict_types=1);

namespace SeoGeo\Core\Schema;

use WP_Post;

/**
 * Resolves conservative visible text from the current public singular document.
 */
final class SchemaVisibleContentResolver {
	/**
	 * Return normalized visible text authored in the current published document.
	 *
	 * Dynamic shortcode output and block-comment metadata are intentionally not
	 * trusted as visibility evidence. If a value cannot be proven from the
	 * public document text, Schema consumers should omit it.
	 */
	public function current_document_text(): string {
		$post = get_queried_object();

		if ( ! $post instanceof WP_Post || 'publish' !== $post->post_status ) {
			return '';
		}

		$content = (string) $post->post_content;
		$content = preg_replace( '/<!--.*?-->/s', ' ', $content );

		if ( ! is_string( $content ) ) {
			return '';
		}

		$content = strip_shortcodes( $content );

		return $this->normalize( wp_strip_all_tags( $content, true ) );
	}

	/**
	 * Check whether one configured value is present in normalized visible text.
	 *
	 * @param string $document_text Normalized public document text.
	 * @param string $expected      Configured value that must be visible.
	 */
	public function contains( string $document_text, string $expected ): bool {
		$expected = $this->normalize( $expected );

		return '' !== $expected && false !== strpos( $document_text, $expected );
	}

	/**
	 * Normalize text for conservative exact-substring comparisons.
	 *
	 * @param string $value Raw text.
	 */
	private function normalize( string $value ): string {
		$charset = get_bloginfo( 'charset' );
		$value   = html_entity_decode( $value, ENT_QUOTES | ENT_HTML5, '' !== $charset ? $charset : 'UTF-8' );
		$value   = preg_replace( '/\s+/u', ' ', $value );

		return is_string( $value ) ? trim( $value ) : '';
	}
}
