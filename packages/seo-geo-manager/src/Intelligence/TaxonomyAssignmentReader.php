<?php
/**
 * Bounded taxonomy-assignment reader for external orchestration.
 *
 * @package SeoGeoManager
 */

declare(strict_types=1);

namespace SeoGeo\Manager\Intelligence;

use WP_Error;
use WP_Post;
use WP_Taxonomy;
use WP_Term;

/** Read the exact existing-term assignment for one post/page taxonomy. */
final class TaxonomyAssignmentReader {
	/**
	 * Read one taxonomy assignment without mutating terms.
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	public static function read( int $resource_id, string $taxonomy_name ) {
		$taxonomy_name = sanitize_key( $taxonomy_name );
		$resource      = get_post( $resource_id );
		if (
			! $resource instanceof WP_Post
			|| ! in_array( $resource->post_type, array( 'post', 'page' ), true )
			|| in_array( $resource->post_status, array( 'trash', 'auto-draft' ), true )
		) {
			return new WP_Error(
				'seo_geo_manager_taxonomy_resource_not_found',
				'The requested post/page was not found.',
				array( 'status' => 404 )
			);
		}

		$taxonomy = self::taxonomy_for_resource( $resource, $taxonomy_name );
		if ( is_wp_error( $taxonomy ) ) {
			return $taxonomy;
		}
		if ( ! current_user_can( 'edit_post', $resource->ID ) || ! self::can_assign( $taxonomy ) ) {
			return new WP_Error(
				'seo_geo_manager_taxonomy_forbidden',
				'You cannot inspect or assign this taxonomy on the requested resource.',
				array( 'status' => 403 )
			);
		}

		$terms = wp_get_object_terms(
			$resource->ID,
			$taxonomy->name,
			array( 'orderby' => 'term_id' )
		);
		if ( is_wp_error( $terms ) ) {
			return $terms;
		}

		$term_ids  = array();
		$summaries = array();
		foreach ( $terms as $term ) {
			if ( ! $term instanceof WP_Term ) {
				continue;
			}
			$term_ids[]   = (int) $term->term_id;
			$summaries[] = self::term_summary( $term );
		}
		sort( $term_ids, SORT_NUMERIC );

		return array(
			'schema_version' => 1,
			'resource'       => array(
				'id'        => (int) $resource->ID,
				'post_type' => (string) $resource->post_type,
				'status'    => (string) $resource->post_status,
				'title'     => html_entity_decode( get_the_title( $resource ), ENT_QUOTES | ENT_HTML5, self::charset() ),
			),
			'taxonomy'       => array(
				'name'         => (string) $taxonomy->name,
				'label'        => (string) $taxonomy->label,
				'hierarchical' => (bool) $taxonomy->hierarchical,
			),
			'assignment'     => array(
				'term_ids'    => $term_ids,
				'terms'       => $summaries,
				'fingerprint' => self::fingerprint_from_ids( $resource->ID, $taxonomy->name, $term_ids ),
			),
			'policy'         => array(
				'bounded'                     => true,
				'posts_pages_only'            => true,
				'existing_terms_only'         => true,
				'term_creation_supported'     => false,
				'exact_replace_assignment'    => true,
				'preview_before_mutation'     => true,
				'expected_fingerprint_source' => 'assignment.fingerprint',
			),
		);
	}

	/**
	 * Return the exact current term IDs for one supported assignment.
	 *
	 * @return list<int>|WP_Error
	 */
	public static function term_ids( int $resource_id, string $taxonomy_name ) {
		$resource = get_post( $resource_id );
		if (
			! $resource instanceof WP_Post
			|| ! in_array( $resource->post_type, array( 'post', 'page' ), true )
			|| in_array( $resource->post_status, array( 'trash', 'auto-draft' ), true )
		) {
			return new WP_Error(
				'seo_geo_manager_taxonomy_resource_not_found',
				'The requested post/page was not found.',
				array( 'status' => 404 )
			);
		}

		$taxonomy = self::taxonomy_for_resource( $resource, sanitize_key( $taxonomy_name ) );
		if ( is_wp_error( $taxonomy ) ) {
			return $taxonomy;
		}

		$ids = wp_get_object_terms( $resource->ID, $taxonomy->name, array( 'fields' => 'ids' ) );
		if ( is_wp_error( $ids ) ) {
			return $ids;
		}

		$result = array_map( 'absint', $ids );
		$result = array_values( array_filter( $result ) );
		sort( $result, SORT_NUMERIC );

		return $result;
	}

	/** Build a deterministic assignment fingerprint. */
	public static function fingerprint_from_ids( int $resource_id, string $taxonomy_name, array $term_ids ): string {
		$ids = array_map( 'absint', $term_ids );
		$ids = array_values( array_filter( $ids ) );
		sort( $ids, SORT_NUMERIC );

		return hash(
			'sha256',
			(string) wp_json_encode(
				array(
					'resource_id' => $resource_id,
					'taxonomy'    => sanitize_key( $taxonomy_name ),
					'term_ids'    => $ids,
				)
			)
		);
	}

	/** @return WP_Taxonomy|WP_Error */
	private static function taxonomy_for_resource( WP_Post $post, string $taxonomy_name ) {
		if ( '' === $taxonomy_name ) {
			return new WP_Error( 'seo_geo_manager_taxonomy_invalid', 'A taxonomy name is required.', array( 'status' => 400 ) );
		}

		$taxonomy = get_taxonomy( $taxonomy_name );
		if ( ! $taxonomy instanceof WP_Taxonomy || ! in_array( $post->post_type, $taxonomy->object_type, true ) ) {
			return new WP_Error(
				'seo_geo_manager_taxonomy_unsupported',
				'The requested taxonomy is not registered for this content type.',
				array( 'status' => 404 )
			);
		}

		return $taxonomy;
	}

	private static function can_assign( WP_Taxonomy $taxonomy ): bool {
		$capability = isset( $taxonomy->cap->assign_terms ) && is_string( $taxonomy->cap->assign_terms )
			? $taxonomy->cap->assign_terms
			: '';

		return '' !== $capability && current_user_can( $capability );
	}

	/** @return array<string, mixed> */
	private static function term_summary( WP_Term $term ): array {
		return array(
			'id'     => (int) $term->term_id,
			'name'   => (string) $term->name,
			'slug'   => (string) $term->slug,
			'parent' => (int) $term->parent,
		);
	}

	private static function charset(): string {
		$charset = get_bloginfo( 'charset' );

		return is_string( $charset ) && '' !== $charset ? $charset : 'UTF-8';
	}
}
