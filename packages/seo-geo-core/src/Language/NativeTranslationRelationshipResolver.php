<?php
/**
 * Native reciprocal translation relationship resolver.
 *
 * @package SeoGeoCore
 */

declare(strict_types=1);

namespace SeoGeo\Core\Language;

use WP_Post;

/**
 * Resolves only explicit, reciprocal and publicly viewable native translations.
 */
final class NativeTranslationRelationshipResolver {
	/**
	 * Per-post native language code.
	 */
	public const LANGUAGE_META_KEY = '_seo_geo_language';

	/**
	 * Reciprocal language-code to post-ID map.
	 */
	public const TRANSLATION_SET_META_KEY = '_seo_geo_translation_set';

	/**
	 * Native language configuration.
	 *
	 * @var NativeLanguageConfiguration
	 */
	private NativeLanguageConfiguration $configuration;

	/**
	 * Create the resolver.
	 *
	 * @param NativeLanguageConfiguration $configuration Validated native language configuration.
	 */
	public function __construct( NativeLanguageConfiguration $configuration ) {
		$this->configuration = $configuration;
	}

	/**
	 * Resolve a validated reciprocal translation relationship.
	 *
	 * Any missing, private, draft, type-mismatched or non-reciprocal member
	 * invalidates the entire relationship.
	 *
	 * @param int $post_id Current post ID.
	 */
	public function resolve( int $post_id ): ?TranslationRelationship {
		if ( $post_id <= 0 ) {
			return null;
		}

		$post = get_post( $post_id );
		if ( ! $post instanceof WP_Post || ! is_post_publicly_viewable( $post ) ) {
			return null;
		}

		$current_language = $this->read_language_code( $post_id );
		if ( null === $current_language ) {
			return null;
		}

		$members = $this->normalize_translation_set(
			get_post_meta( $post_id, self::TRANSLATION_SET_META_KEY, true )
		);

		if (
			null === $members
			|| count( $members ) < 2
			|| ! isset( $members[ $current_language ] )
			|| $post_id !== $members[ $current_language ]
		) {
			return null;
		}

		foreach ( $members as $language_code => $member_id ) {
			$member = get_post( $member_id );

			if (
				! $member instanceof WP_Post
				|| ! is_post_publicly_viewable( $member )
				|| $member->post_type !== $post->post_type
				|| $this->read_language_code( $member_id ) !== $language_code
			) {
				return null;
			}

			$member_set = $this->normalize_translation_set(
				get_post_meta( $member_id, self::TRANSLATION_SET_META_KEY, true )
			);

			if ( null === $member_set || $member_set !== $members ) {
				return null;
			}
		}

		return new TranslationRelationship( $post_id, $current_language, $members );
	}

	/**
	 * Read and validate one post language against configured native languages.
	 *
	 * @param int $post_id Post ID.
	 */
	private function read_language_code( int $post_id ): ?string {
		$raw = get_post_meta( $post_id, self::LANGUAGE_META_KEY, true );
		if ( ! is_string( $raw ) ) {
			return null;
		}

		$language_code = strtolower( trim( $raw ) );
		if ( '' === $language_code || null === $this->configuration->locale_for( $language_code ) ) {
			return null;
		}

		return $language_code;
	}

	/**
	 * Normalize and validate a persisted translation set.
	 *
	 * @param mixed $raw Persisted post-meta value.
	 * @return array<string, int>|null
	 */
	private function normalize_translation_set( mixed $raw ): ?array {
		if ( ! is_array( $raw ) || array() === $raw ) {
			return null;
		}

		$members  = array();
		$post_ids = array();

		foreach ( $raw as $raw_language_code => $raw_post_id ) {
			if ( ! is_string( $raw_language_code ) ) {
				return null;
			}

			$language_code = strtolower( trim( $raw_language_code ) );
			if (
				'' === $language_code
				|| null === $this->configuration->locale_for( $language_code )
				|| isset( $members[ $language_code ] )
			) {
				return null;
			}

			$post_id = $this->normalize_post_id( $raw_post_id );
			if ( null === $post_id || isset( $post_ids[ $post_id ] ) ) {
				return null;
			}

			$members[ $language_code ] = $post_id;
			$post_ids[ $post_id ]      = true;
		}

		ksort( $members, SORT_STRING );

		return $members;
	}

	/**
	 * Normalize an integer post ID while rejecting ambiguous scalar types.
	 *
	 * @param mixed $raw_post_id Raw persisted value.
	 */
	private function normalize_post_id( mixed $raw_post_id ): ?int {
		if ( is_int( $raw_post_id ) ) {
			return $raw_post_id > 0 ? $raw_post_id : null;
		}

		if ( ! is_string( $raw_post_id ) || 1 !== preg_match( '/^[1-9][0-9]*$/', $raw_post_id ) ) {
			return null;
		}

		$post_id = (int) $raw_post_id;

		return $post_id > 0 ? $post_id : null;
	}
}
