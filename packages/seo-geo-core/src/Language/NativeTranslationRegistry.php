<?php
/**
 * Native translation relationship registry.
 *
 * @package SeoGeoCore
 */

declare(strict_types=1);

namespace SeoGeo\Core\Language;

use WP_Post;

/**
 * Validates explicit translation relationships stored on WordPress objects.
 */
final class NativeTranslationRegistry {
	/**
	 * Post meta key containing the explicit translation group.
	 */
	public const META_GROUP = '_seo_geo_translation_group';

	/**
	 * Post meta key containing the configured language code.
	 */
	public const META_LANGUAGE = '_seo_geo_language';

	/**
	 * Native server-side language configuration.
	 *
	 * @var NativeLanguageConfiguration
	 */
	private NativeLanguageConfiguration $configuration;

	/**
	 * Create the registry.
	 *
	 * @param NativeLanguageConfiguration $configuration Validated language configuration.
	 */
	public function __construct( NativeLanguageConfiguration $configuration ) {
		$this->configuration = $configuration;
	}

	/**
	 * Resolve one validated reciprocal translation relationship.
	 *
	 * A relationship exists only when the current published resource and at
	 * least one other published public resource share an explicit group ID,
	 * every member declares one configured language, and no language is
	 * duplicated inside the group.
	 *
	 * @param int $post_id Current WordPress resource ID.
	 */
	public function for_post( int $post_id ): ?NativeTranslationRelationship {
		$current_post = get_post( $post_id );
		if ( ! $this->is_public_resource( $current_post ) ) {
			return null;
		}

		$group_id = $this->group_id_for( $post_id );
		$language = $this->language_for( $post_id );

		if ( null === $group_id || null === $language ) {
			return null;
		}

		$post_types = array_values( get_post_types( array( 'public' => true ), 'names' ) );
		$post_types = array_values( array_diff( $post_types, array( 'attachment' ) ) );

		$candidate_ids = get_posts(
			array(
				'post_type'              => $post_types,
				'post_status'            => 'publish',
				'posts_per_page'         => -1,
				'fields'                 => 'ids',
				'orderby'                => 'ID',
				'order'                  => 'ASC',
				'no_found_rows'          => true,
				'update_post_meta_cache' => true,
				'update_post_term_cache' => false,
				'suppress_filters'       => true,
				'meta_key'               => self::META_GROUP,
				'meta_value'             => $group_id,
			)
		);

		$translations = array();

		foreach ( $candidate_ids as $candidate_id ) {
			$candidate_id = (int) $candidate_id;
			$candidate     = get_post( $candidate_id );

			if ( ! $this->is_public_resource( $candidate ) ) {
				continue;
			}

			$candidate_group    = $this->group_id_for( $candidate_id );
			$candidate_language = $this->language_for( $candidate_id );

			if ( $group_id !== $candidate_group || null === $candidate_language ) {
				return null;
			}

			if ( isset( $translations[ $candidate_language ] ) ) {
				return null;
			}

			$translations[ $candidate_language ] = $candidate_id;
		}

		if (
			count( $translations ) < 2
			|| ! isset( $translations[ $language ] )
			|| $translations[ $language ] !== $post_id
		) {
			return null;
		}

		ksort( $translations );

		return new NativeTranslationRelationship( $group_id, $language, $translations );
	}

	/**
	 * Normalize the explicit translation group assigned to a resource.
	 *
	 * @param int $post_id WordPress resource ID.
	 */
	private function group_id_for( int $post_id ): ?string {
		$value = get_post_meta( $post_id, self::META_GROUP, true );
		if ( ! is_string( $value ) ) {
			return null;
		}

		$group_id = strtolower( trim( $value ) );

		if ( 1 !== preg_match( '/^[a-z0-9][a-z0-9._:-]{0,127}$/', $group_id ) ) {
			return null;
		}

		return $group_id;
	}

	/**
	 * Resolve the configured language assigned to a resource.
	 *
	 * @param int $post_id WordPress resource ID.
	 */
	private function language_for( int $post_id ): ?string {
		$value = get_post_meta( $post_id, self::META_LANGUAGE, true );
		if ( ! is_string( $value ) ) {
			return null;
		}

		$language = strtolower( trim( $value ) );

		return null !== $this->configuration->locale_for( $language ) ? $language : null;
	}

	/**
	 * Report whether a resource is published and belongs to a public post type.
	 *
	 * @param WP_Post|null $post Candidate resource.
	 */
	private function is_public_resource( ?WP_Post $post ): bool {
		if ( ! $post instanceof WP_Post || 'publish' !== $post->post_status || 'attachment' === $post->post_type ) {
			return false;
		}

		$post_type = get_post_type_object( $post->post_type );

		return null !== $post_type && is_post_type_viewable( $post_type );
	}
}
