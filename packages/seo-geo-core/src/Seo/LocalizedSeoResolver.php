<?php
/**
 * Localized SEO authority resolver.
 *
 * @package SeoGeoCore
 */

declare(strict_types=1);

namespace SeoGeo\Core\Seo;

use SeoGeo\Core\Language\NativeLanguageRouter;
use SeoGeo\Core\Language\NativeTranslationRegistry;
use SeoGeo\Core\Language\NativeTranslationRelationship;

/**
 * Promotes only validated localized translation routes to SEO authority.
 */
final class LocalizedSeoResolver {
	/**
	 * Native prefix router.
	 *
	 * @var NativeLanguageRouter
	 */
	private NativeLanguageRouter $router;

	/**
	 * Explicit translation relationship registry.
	 *
	 * @var NativeTranslationRegistry
	 */
	private NativeTranslationRegistry $translations;

	/**
	 * Create the localized SEO resolver.
	 *
	 * @param NativeLanguageRouter       $router       Validated native router.
	 * @param NativeTranslationRegistry $translations Explicit translation registry.
	 */
	public function __construct( NativeLanguageRouter $router, NativeTranslationRegistry $translations ) {
		$this->router       = $router;
		$this->translations = $translations;
	}

	/**
	 * Apply localized SEO staging/promotion to an already-resolved state.
	 *
	 * Only singular resources participate in Phase 4C2. Valid translated
	 * resources are authoritative exclusively on the prefix matching their
	 * explicitly assigned relationship language.
	 *
	 * @param string $state Existing native indexability state.
	 */
	public function resolve_indexability( string $state ): string {
		if ( IndexabilityResolver::INDEXABLE !== $state || ! $this->router->enabled() ) {
			return $state;
		}

		$relationship = $this->current_relationship();

		if ( $this->router->is_localized_request() ) {
			return $this->localized_request_matches( $relationship )
				? IndexabilityResolver::INDEXABLE
				: IndexabilityResolver::NOINDEX_FOLLOW;
		}

		if ( null !== $relationship ) {
			return IndexabilityResolver::NOINDEX_FOLLOW;
		}

		return $state;
	}

	/**
	 * Resolve the localized self-canonical for the authoritative current route.
	 */
	public function current_canonical_url(): ?string {
		$relationship = $this->current_relationship();

		if ( ! $this->localized_request_matches( $relationship ) ) {
			return null;
		}

		$post_id = get_queried_object_id();
		$language_code = $this->router->active_language_code();

		if ( 0 >= $post_id || null === $language_code ) {
			return null;
		}

		return $this->url_for_post( $post_id, $language_code );
	}

	/**
	 * Replace the native canonical only for an authoritative localized route.
	 *
	 * @param string|null $url          Existing native canonical candidate.
	 * @param string      $indexability Resolved indexability state.
	 */
	public function filter_canonical_url( ?string $url, string $indexability ): ?string {
		if ( IndexabilityResolver::INDEXABLE !== $indexability ) {
			return $url;
		}

		$localized = $this->current_canonical_url();

		return null !== $localized ? $localized : $url;
	}

	/**
	 * Resolve reciprocal localized alternate URLs for the authoritative route.
	 *
	 * @return array<string, string>
	 */
	public function alternate_urls(): array {
		$relationship = $this->current_relationship();

		if ( ! $this->localized_request_matches( $relationship ) || null === $relationship ) {
			return array();
		}

		$alternates = array();

		foreach ( $relationship->translations() as $language_code => $post_id ) {
			$url = $this->url_for_post( $post_id, $language_code );
			if ( null === $url ) {
				return array();
			}

			$alternates[ $language_code ] = $url;
		}

		return $alternates;
	}

	/**
	 * Resolve one localized absolute URL from a WordPress permalink.
	 *
	 * @param int    $post_id       Published translated resource ID.
	 * @param string $language_code Configured relationship language.
	 */
	public function url_for_post( int $post_id, string $language_code ): ?string {
		$permalink = get_permalink( $post_id );
		if ( '' === $permalink ) {
			return null;
		}

		$home_url       = home_url( '/' );
		$home_path      = wp_parse_url( $home_url, PHP_URL_PATH );
		$permalink_path = wp_parse_url( $permalink, PHP_URL_PATH );

		if ( ! is_string( $home_path ) || ! is_string( $permalink_path ) ) {
			return null;
		}

		$normalized_home_path = '/' . trim( $home_path, '/' );
		if ( '/' !== $normalized_home_path ) {
			$normalized_home_path .= '/';
		}

		if ( ! str_starts_with( $permalink_path, $normalized_home_path ) ) {
			return null;
		}

		$relative_path = ltrim( substr( $permalink_path, strlen( $normalized_home_path ) ), '/' );
		$localized_path = '/' . rawurlencode( strtolower( trim( $language_code ) ) ) . '/';

		if ( '' !== $relative_path ) {
			$localized_path .= $relative_path;
		}

		return home_url( $localized_path );
	}

	/**
	 * Resolve the validated relationship for the current singular resource.
	 */
	private function current_relationship(): ?NativeTranslationRelationship {
		if ( ! is_singular() ) {
			return null;
		}

		$post_id = get_queried_object_id();
		if ( 0 >= $post_id ) {
			return null;
		}

		return $this->translations->for_post( $post_id );
	}

	/**
	 * Verify that current route prefix and relationship language agree.
	 *
	 * @param NativeTranslationRelationship|null $relationship Current relationship.
	 */
	private function localized_request_matches( ?NativeTranslationRelationship $relationship ): bool {
		if ( null === $relationship || ! $this->router->is_localized_request() ) {
			return false;
		}

		$active_language = $this->router->active_language_code();

		return null !== $active_language
			&& $active_language === $relationship->current_language_code();
	}
}
