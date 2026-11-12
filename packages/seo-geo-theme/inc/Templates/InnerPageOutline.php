<?php
/**
 * Preserves exactly one main page heading on migrated inner-page layouts.
 *
 * This is a render-only guard: Gutenberg post_content and its revision history
 * stay untouched, while native block templates continue to own their layout.
 *
 * @package SeoGeoTheme
 */

declare(strict_types=1);

namespace SeoGeo\Theme\Templates;

/**
 * Render-only heading normalization for singular inner WordPress pages.
 */
final class InnerPageOutline {
	/**
	 * Page IDs whose native title already rendered H1.
	 *
	 * @var array<int, true>
	 */
	private static array $rendered_template_titles = array();

	/** Register the bounded WordPress block render filters. */
	public static function register(): void {
		add_filter( 'render_block_core/post-title', array( self::class, 'record_template_title' ), 20, 3 );
		add_filter( 'render_block_core/post-content', array( self::class, 'normalize_content_heading' ), 20, 3 );
	}

	/**
	 * Only the main queried document's title may satisfy its document H1.
	 *
	 * @param string               $html Rendered post-title block.
	 * @param array<string, mixed> $block Parsed block configuration.
	 * @param object|null          $instance WordPress rendered block context.
	 */
	public static function record_template_title( string $html, array $block, ?object $instance = null ): string {
		$id = self::document_page_id( $instance );
		if ( 0 === $id || 1 !== (int) ( $block['attrs']['level'] ?? 2 ) ) {
			return $html;
		}
		if ( 1 === preg_match( '/<h1(?:\\s|>)/i', $html ) ) {
			self::$rendered_template_titles[ $id ] = true;
		}
		return $html;
	}

	/**
	 * Demote only duplicate H1 tags in rendered page content, never stored data.
	 *
	 * The title block is the authority when it actually emitted H1. Some
	 * migrated page templates have no title block; in those cases the first
	 * content H1 remains the document heading and only later H1s are demoted.
	 *
	 * @param string               $html Rendered content block.
	 * @param array<string, mixed> $block Parsed block attributes (unused).
	 * @param object|null          $instance WordPress rendered block context.
	 */
	public static function normalize_content_heading( string $html, array $block, ?object $instance = null ): string {
		$id = self::document_page_id( $instance );
		if ( 0 === $id || 1 !== preg_match( '/<h1(?:\\s|>)/i', $html ) ) {
			return $html;
		}
		$retain = isset( self::$rendered_template_titles[ $id ] ) ? 0 : 1;
		return self::demote_extra_headings( $html, $retain );
	}

	/**
	 * Resolve only the current main WordPress page, never a nested query post.
	 *
	 * @param object|null $instance Core block context, not an editable ID.
	 */
	private static function document_page_id( ?object $instance ): int {
		if ( is_admin() || ! is_singular( 'page' ) || is_front_page() ) {
			return 0;
		}
		$id = (int) get_queried_object_id();
		if ( $id < 1 ) {
			return 0;
		}
		$context = is_object( $instance ) && isset( $instance->context ) && is_array( $instance->context )
			? $instance->context
			: array();
		if ( isset( $context['postId'] ) && (int) $context['postId'] !== $id ) {
			return 0;
		}
		return $id;
	}

	/**
	 * Change tag names only; preserve inner HTML, classes, attributes and URLs.
	 *
	 * @param string $html Rendered page content.
	 * @param int    $retain Number of content H1s to retain.
	 */
	private static function demote_extra_headings( string $html, int $retain ): string {
		$opened = 0;
		$stack  = array();
		$result = preg_replace_callback(
			'~</?h1(?=[\\s>])[^>]*>~i',
			static function ( array $matches ) use ( &$opened, &$stack, $retain ): string {
				$tag     = $matches[0];
				$closing = 1 === preg_match( '~^</~', $tag );
				if ( $closing ) {
					$demote = array_pop( $stack );
				} else {
					++$opened;
					$demote  = $opened > $retain;
					$stack[] = $demote;
				}
				if ( ! $demote ) {
					return $tag;
				}
				return preg_replace( '~^(</?)h1~i', '$1h2', $tag, 1 ) ?? $tag;
			},
			$html
		);
		return is_string( $result ) ? $result : $html;
	}
}
