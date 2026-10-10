<?php
/**
 * Safe semantic resolver for existing preset pages.
 *
 * @package SeoGeoManager
 */

declare(strict_types=1);

namespace SeoGeo\Manager\Intelligence;

use WP_Post;
use WP_Query;

final class PresetPageResolver {
	/** Keep semantic fallback bounded to the Manager inventory ceiling. */
	private const MAX_CANDIDATES = 250;

	/**
	 * Register the resolver before ThemeContractScanner declares a page missing.
	 */
	public static function register(): void {
		add_filter( 'seo_geo_manager_resolve_preset_page_id', array( self::class, 'resolve' ), 10, 4 );
	}

	/**
	 * Resolve an existing strategic page without creating or mutating content.
	 *
	 * Exact semantic aliases are intentionally small and role-based. If more
	 * than one page matches an alias family, the resolver refuses to guess and
	 * leaves the page unresolved for operator review.
	 *
	 * @param mixed                $resolved_id     Existing filtered result.
	 * @param string               $key             Preset page key.
	 * @param array<string, mixed> $page_definition Preset page definition.
	 * @param string               $preset_id       Active preset identifier.
	 */
	public static function resolve( $resolved_id, string $key, array $page_definition, string $preset_id ): int {
		unset( $preset_id );

		if ( is_numeric( $resolved_id ) && 0 < (int) $resolved_id ) {
			return (int) $resolved_id;
		}

		$role             = isset( $page_definition['role'] ) && is_string( $page_definition['role'] ) ? sanitize_key( $page_definition['role'] ) : '';
		$canonical_slug   = isset( $page_definition['slug'] ) && is_string( $page_definition['slug'] ) ? sanitize_title( $page_definition['slug'] ) : '';
		$canonical_title  = isset( $page_definition['title'] ) && is_string( $page_definition['title'] ) ? trim( $page_definition['title'] ) : '';
		$accepted_slugs   = self::definition_string_list( $page_definition, 'accepted_slugs', true );
		$accepted_titles  = self::definition_string_list( $page_definition, 'accepted_titles', false );
		$semantic_aliases = self::role_aliases( $role, $key );

		$accepted_slugs  = array_values( array_unique( array_merge( $accepted_slugs, $semantic_aliases['slugs'] ) ) );
		$accepted_titles = array_values( array_unique( array_merge( $accepted_titles, $semantic_aliases['titles'] ) ) );

		$exact_matches = self::exact_alias_matches( $accepted_slugs, $accepted_titles );
		if ( 1 === count( $exact_matches ) ) {
			return (int) array_key_first( $exact_matches );
		}
		if ( 1 < count( $exact_matches ) ) {
			return 0;
		}

		return self::bounded_fuzzy_match( $canonical_slug, $canonical_title );
	}

	/**
	 * High-confidence semantic aliases shared by presets and languages.
	 *
	 * @return array{slugs:list<string>,titles:list<string>}
	 */
	private static function role_aliases( string $role, string $key ): array {
		if ( 'about' === $role || 'about' === sanitize_key( $key ) ) {
			return array(
				'slugs'  => array( 'sobre-nosotros', 'quienes-somos', 'empresa', 'about-us' ),
				'titles' => array( 'Sobre Nosotros', 'Quiénes somos', 'Quienes somos', 'Empresa', 'About us' ),
			);
		}

		if ( 'posts-page' === $role || 'insights' === sanitize_key( $key ) ) {
			return array(
				'slugs'  => array( 'blog', 'actualidad', 'insights', 'noticias', 'news' ),
				'titles' => array( 'Blog', 'Actualidad', 'Insights', 'Noticias', 'News' ),
			);
		}

		return array( 'slugs' => array(), 'titles' => array() );
	}

	/**
	 * @param array<string, mixed> $definition Page definition.
	 * @return list<string>
	 */
	private static function definition_string_list( array $definition, string $field, bool $slug ): array {
		$value = $definition[ $field ] ?? array();
		if ( ! is_array( $value ) ) {
			return array();
		}

		$result = array();
		foreach ( $value as $item ) {
			if ( ! is_string( $item ) || '' === trim( $item ) ) {
				continue;
			}
			$result[] = $slug ? sanitize_title( $item ) : trim( $item );
		}

		return array_values( array_unique( array_filter( $result ) ) );
	}

	/**
	 * @param list<string> $slugs  Accepted exact slugs.
	 * @param list<string> $titles Accepted exact titles.
	 * @return array<int, WP_Post>
	 */
	private static function exact_alias_matches( array $slugs, array $titles ): array {
		$matches = array();

		foreach ( $slugs as $slug ) {
			$post = get_page_by_path( trim( $slug, '/' ), OBJECT, 'page' );
			if ( $post instanceof WP_Post ) {
				$matches[ (int) $post->ID ] = $post;
			}
		}

		foreach ( $titles as $title ) {
			$query = new WP_Query(
				array(
					'post_type'      => 'page',
					'post_status'    => array( 'publish', 'future', 'draft', 'pending', 'private' ),
					'posts_per_page' => 2,
					'title'          => $title,
					'no_found_rows'  => true,
				)
			);
			if ( 1 === count( $query->posts ) && $query->posts[0] instanceof WP_Post ) {
				$matches[ (int) $query->posts[0]->ID ] = $query->posts[0];
			}
		}

		return $matches;
	}

	private static function bounded_fuzzy_match( string $slug, string $title ): int {
		$needles = self::tokens( sanitize_title( $slug . ' ' . $title ) );
		if ( array() === $needles ) {
			return 0;
		}

		$query = new WP_Query(
			array(
				'post_type'      => 'page',
				'post_status'    => array( 'publish', 'future', 'draft', 'pending', 'private' ),
				'posts_per_page' => self::MAX_CANDIDATES,
				'orderby'        => 'ID',
				'order'          => 'ASC',
				'no_found_rows'  => true,
			)
		);
		$best_id    = 0;
		$best_score = 0.0;
		$tied       = false;

		foreach ( $query->posts as $candidate ) {
			if ( ! $candidate instanceof WP_Post ) {
				continue;
			}
			$haystack = self::tokens( sanitize_title( $candidate->post_name . ' ' . $candidate->post_title ) );
			if ( array() === $haystack ) {
				continue;
			}
			$hits  = count( array_intersect( $needles, $haystack ) );
			$score = $hits / max( 1, count( $needles ) );
			if ( $score > $best_score ) {
				$best_id    = (int) $candidate->ID;
				$best_score = $score;
				$tied       = false;
			} elseif ( $score === $best_score && 0.0 < $score ) {
				$tied = true;
			}
		}

		return 0.75 <= $best_score && ! $tied ? $best_id : 0;
	}

	/**
	 * @return list<string>
	 */
	private static function tokens( string $value ): array {
		$parts = preg_split( '/-+/', $value );
		if ( ! is_array( $parts ) ) {
			return array();
		}

		$result = array();
		foreach ( $parts as $part ) {
			if ( 3 <= strlen( $part ) ) {
				$result[] = $part;
			}
		}

		return array_values( array_unique( $result ) );
	}
}
