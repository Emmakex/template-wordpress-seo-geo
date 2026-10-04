<?php
/**
 * Native per-resource SEO metadata overrides.
 *
 * @package SeoGeoCore
 */

declare(strict_types=1);

namespace SeoGeo\Core\Seo;

/**
 * Provides provider-neutral, WordPress-native SEO overrides for singular content.
 */
final class NativeSeoMetadata {
	public const META_TITLE        = '_seo_geo_title_v1';
	public const META_DESCRIPTION  = '_seo_geo_description_v1';
	public const META_CANONICAL    = '_seo_geo_canonical_v1';
	public const META_INDEXABILITY = '_seo_geo_indexability_v1';

	/**
	 * Apply one singular-resource SEO title override.
	 *
	 * @param string $title Existing WordPress document title.
	 */
	public function filter_document_title( string $title ): string {
		$post_id = $this->current_post_id();
		if ( 0 >= $post_id ) {
			return $title;
		}

		$override = $this->text_meta( $post_id, self::META_TITLE );

		return null !== $override ? $override : $title;
	}

	/**
	 * Apply one singular-resource meta-description override.
	 *
	 * @param string|null $description Existing native description.
	 */
	public function filter_meta_description( ?string $description ): ?string {
		$post_id = $this->current_post_id();
		if ( 0 >= $post_id ) {
			return $description;
		}

		$override = $this->text_meta( $post_id, self::META_DESCRIPTION );

		return null !== $override ? $override : $description;
	}

	/**
	 * Apply one singular-resource canonical override.
	 *
	 * @param string|null $url          Existing native canonical.
	 * @param string      $indexability Resolved indexability state.
	 */
	public function filter_canonical_url( ?string $url, string $indexability ): ?string {
		if ( IndexabilityResolver::INDEXABLE !== $indexability ) {
			return $url;
		}

		$post_id = $this->current_post_id();
		if ( 0 >= $post_id ) {
			return $url;
		}

		$value = get_post_meta( $post_id, self::META_CANONICAL, true );
		if ( ! is_scalar( $value ) ) {
			return $url;
		}

		$value = trim( (string) $value );
		if ( '' === $value || 1 !== preg_match( '#^https?://#i', $value ) ) {
			return $url;
		}

		$normalized = esc_url_raw( $value );

		return '' !== $normalized ? $normalized : $url;
	}

	/**
	 * Apply one singular-resource indexability override.
	 *
	 * @param string $state Existing native indexability state.
	 */
	public function filter_indexability_state( string $state ): string {
		$post_id = $this->current_post_id();
		if ( 0 >= $post_id ) {
			return $state;
		}

		$value = get_post_meta( $post_id, self::META_INDEXABILITY, true );
		$value = is_scalar( $value ) ? sanitize_key( (string) $value ) : '';

		return in_array(
			$value,
			array(
				IndexabilityResolver::INDEXABLE,
				IndexabilityResolver::NOINDEX_FOLLOW,
				IndexabilityResolver::NOINDEX_NOFOLLOW,
			),
			true
		)
			? $value
			: $state;
	}

	/**
	 * Resolve the current singular post ID only.
	 */
	private function current_post_id(): int {
		if ( ! is_singular() ) {
			return 0;
		}

		return max( 0, get_queried_object_id() );
	}

	/**
	 * Read one normalized text override.
	 *
	 * @param int    $post_id  WordPress post ID.
	 * @param string $meta_key Native SEO meta key.
	 */
	private function text_meta( int $post_id, string $meta_key ): ?string {
		$value = get_post_meta( $post_id, $meta_key, true );
		if ( ! is_scalar( $value ) ) {
			return null;
		}

		$value = sanitize_text_field( (string) $value );

		return '' !== $value ? $value : null;
	}
}
