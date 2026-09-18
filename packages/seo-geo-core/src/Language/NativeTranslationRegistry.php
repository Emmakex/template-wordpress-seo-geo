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
	 * Post meta key containing the reciprocal language-to-resource map.
	 */
	public const META_TRANSLATIONS = '_seo_geo_translations';

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
	 * A relationship exists only when the current published resource explicitly
	 * names its group, language and complete translation map, and every mapped
	 * published resource declares the same group and reciprocal map.
	 *
	 * @param int $post_id Current WordPress resource ID.
	 */
	public function for_post( int $post_id ): ?NativeTranslationRelationship {
		$current_post = get_post( $post_id );
		if ( ! $this->is_public_resource( $current_post ) ) {
			return null;
		}

		$group_id     = $this->group_id_for( $post_id );
		$language     = $this->language_for( $post_id );
		$translations = $this->translations_for( $post_id );

		if (
			null === $group_id
			|| null === $language
			|| null === $translations
			|| count( $translations ) < 2
			|| ! isset( $translations[ $language ] )
			|| $translations[ $language ] !== $post_id
		) {
			return null;
		}

		$seen_ids = array();

		foreach ( $translations as $candidate_language => $candidate_id ) {
			if ( isset( $seen_ids[ $candidate_id ] ) ) {
				return null;
			}
			$seen_ids[ $candidate_id ] = true;

			$candidate = get_post( $candidate_id );
			if ( ! $this->is_public_resource( $candidate ) ) {
				return null;
			}

			if (
				$group_id !== $this->group_id_for( $candidate_id )
				|| $candidate_language !== $this->language_for( $candidate_id )
				|| $translations !== $this->translations_for( $candidate_id )
			) {
				return null;
			}
		}

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
	 * Resolve and normalize an explicit reciprocal translation map.
	 *
	 * @param int $post_id WordPress resource ID.
	 * @return array<string, int>|null
	 */
	private function translations_for( int $post_id ): ?array {
		$value = get_post_meta( $post_id, self::META_TRANSLATIONS, true );
		if ( ! is_array( $value ) || array() === $value ) {
			return null;
		}

		$translations = array();

		foreach ( $value as $raw_language => $raw_post_id ) {
			if ( ! is_string( $raw_language ) ) {
				return null;
			}

			$language = strtolower( trim( $raw_language ) );
			if ( null === $this->configuration->locale_for( $language ) ) {
				return null;
			}

			if ( is_int( $raw_post_id ) ) {
				$candidate_id = $raw_post_id;
			} elseif ( is_string( $raw_post_id ) && ctype_digit( $raw_post_id ) ) {
				$candidate_id = (int) $raw_post_id;
			} else {
				return null;
			}

			if ( 0 >= $candidate_id || isset( $translations[ $language ] ) ) {
				return null;
			}

			$translations[ $language ] = $candidate_id;
		}

		ksort( $translations );

		return $translations;
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
