<?php
/**
 * Native breadcrumb data resolver.
 *
 * @package SeoGeoCore
 */

declare(strict_types=1);

namespace SeoGeo\Core\Seo;

use WP_Post;
use WP_Term;
use WP_User;

/**
 * Builds one reusable breadcrumb data contract from the current WordPress query.
 */
final class BreadcrumbResolver {
	/**
	 * Resolve breadcrumb items for the current request.
	 *
	 * @return array<int, array{label: string, url: string|null, current: bool}>
	 */
	public function resolve(): array {
		$home_label = $this->label( get_bloginfo( 'name' ) );
		$home_url   = home_url( '/' );

		if ( is_front_page() ) {
			return array( $this->item( $home_label, $home_url, true ) );
		}

		$items = array( $this->item( $home_label, $home_url, false ) );

		if ( is_singular() ) {
			return $this->resolve_singular( $items );
		}

		if ( is_category() || is_tag() || is_tax() ) {
			return $this->resolve_term( $items );
		}

		if ( is_post_type_archive() ) {
			$post_type = get_query_var( 'post_type' );
			if ( is_array( $post_type ) ) {
				$post_type = reset( $post_type );
			}

			if ( is_string( $post_type ) && '' !== $post_type ) {
				$url = get_post_type_archive_link( $post_type );
				return array_merge(
					$items,
					array(
						$this->item(
							post_type_archive_title( '', false ),
							is_string( $url ) ? $url : null,
							true
						),
					)
				);
			}
		}

		if ( is_author() ) {
			$author = get_queried_object();
			if ( $author instanceof WP_User ) {
				return array_merge(
					$items,
					array(
						$this->item(
							$author->display_name,
							get_author_posts_url( $author->ID ),
							true
						),
					)
				);
			}
		}

		return array_merge(
			$items,
			array( $this->item( wp_get_document_title(), null, true ) )
		);
	}

	/**
	 * Resolve a singular hierarchy.
	 *
	 * @param array<int, array{label: string, url: string|null, current: bool}> $items Existing items.
	 * @return array<int, array{label: string, url: string|null, current: bool}>
	 */
	private function resolve_singular( array $items ): array {
		$post = get_post( get_queried_object_id() );

		if ( ! $post instanceof WP_Post ) {
			return $items;
		}

		if ( 0 < $post->post_parent ) {
			$ancestor_ids = array_reverse( get_post_ancestors( $post ) );

			foreach ( $ancestor_ids as $ancestor_id ) {
				$ancestor = get_post( $ancestor_id );
				if ( ! $ancestor instanceof WP_Post ) {
					continue;
				}

				$url     = get_permalink( $ancestor );
				$items[] = $this->item(
					get_the_title( $ancestor ),
					$url,
					false
				);
			}
		} elseif ( 'post' === $post->post_type ) {
			$posts_page_id = (int) get_option( 'page_for_posts' );
			if ( 0 < $posts_page_id ) {
				$posts_page = get_post( $posts_page_id );
				if ( $posts_page instanceof WP_Post ) {
					$url     = get_permalink( $posts_page );
					$items[] = $this->item(
						get_the_title( $posts_page ),
						$url,
						false
					);
				}
			}
		}

		$url     = get_permalink( $post );
		$items[] = $this->item(
			get_the_title( $post ),
			$url,
			true
		);

		return $items;
	}

	/**
	 * Resolve a taxonomy hierarchy.
	 *
	 * @param array<int, array{label: string, url: string|null, current: bool}> $items Existing items.
	 * @return array<int, array{label: string, url: string|null, current: bool}>
	 */
	private function resolve_term( array $items ): array {
		$term = get_queried_object();

		if ( ! $term instanceof WP_Term ) {
			return $items;
		}

		if ( is_taxonomy_hierarchical( $term->taxonomy ) ) {
			$ancestor_ids = array_reverse( get_ancestors( $term->term_id, $term->taxonomy, 'taxonomy' ) );

			foreach ( $ancestor_ids as $ancestor_id ) {
				$ancestor = get_term( $ancestor_id, $term->taxonomy );
				if ( ! $ancestor instanceof WP_Term ) {
					continue;
				}

				$url     = get_term_link( $ancestor );
				$items[] = $this->item(
					$ancestor->name,
					$url,
					false
				);
			}
		}

		$url     = get_term_link( $term );
		$items[] = $this->item(
			$term->name,
			is_string( $url ) ? $url : null,
			true
		);

		return $items;
	}

	/**
	 * Create one normalized breadcrumb item.
	 *
	 * @param string      $label   Display label.
	 * @param string|null $url     Absolute URL when available.
	 * @param bool        $current Whether this item represents the current request.
	 * @return array{label: string, url: string|null, current: bool}
	 */
	private function item( string $label, ?string $url, bool $current ): array {
		return array(
			'label'   => $this->label( $label ),
			'url'     => null !== $url && '' !== trim( $url ) ? $url : null,
			'current' => $current,
		);
	}

	/**
	 * Normalize a breadcrumb label.
	 *
	 * @param string $label Raw label.
	 */
	private function label( string $label ): string {
		$label = trim( wp_strip_all_tags( $label, true ) );

		return '' !== $label ? $label : __( 'Home' );
	}
}
