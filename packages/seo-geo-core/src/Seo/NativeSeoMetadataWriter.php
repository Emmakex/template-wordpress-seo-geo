<?php
/**
 * Native per-resource SEO metadata mutation contract.
 *
 * @package SeoGeoCore
 */

declare(strict_types=1);

namespace SeoGeo\Core\Seo;

use WP_Error;
use WP_Post;
use WP_Post_Type;

/**
 * Persists the complete native SEO override state for one singular resource.
 *
 * Manager and other remote operators must use this authority instead of writing
 * the native SEO post-meta keys directly.
 */
final class NativeSeoMetadataWriter {
	private const MAX_TITLE_LENGTH       = 320;
	private const MAX_DESCRIPTION_LENGTH = 1024;

	/**
	 * Inspect the complete normalized native SEO override state.
	 *
	 * @param int $post_id WordPress resource ID.
	 * @return array<string, mixed>|WP_Error
	 */
	public function inspect( int $post_id ) {
		$post = $this->eligible_post( $post_id );
		if ( is_wp_error( $post ) ) {
			return $post;
		}

		$values = array(
			'title'        => $this->text_value( $post_id, NativeSeoMetadata::META_TITLE ),
			'description'  => $this->text_value( $post_id, NativeSeoMetadata::META_DESCRIPTION ),
			'canonical'    => $this->canonical_value( $post_id ),
			'indexability' => $this->indexability_value( $post_id ),
		);

		return array(
			'schema_version' => 1,
			'provider'       => 'native-core',
			'resource_id'    => $post_id,
			'post_type'      => (string) $post->post_type,
			'status'         => (string) $post->post_status,
			'values'         => $values,
			'fingerprint'    => self::fingerprint( $post_id, $values ),
		);
	}

	/**
	 * Replace the complete normalized native SEO override state.
	 *
	 * Every supported field is required in the payload. A null value clears the
	 * corresponding override and restores the normal Core fallback behavior.
	 *
	 * @param int                  $post_id WordPress resource ID.
	 * @param array<string, mixed> $values  Complete desired override state.
	 * @return array<string, mixed>|WP_Error
	 */
	public function replace( int $post_id, array $values ) {
		$post = $this->eligible_post( $post_id );
		if ( is_wp_error( $post ) ) {
			return $post;
		}

		$normalized = $this->normalize_values( $values );
		if ( is_wp_error( $normalized ) ) {
			return $normalized;
		}

		$before = $this->snapshot( $post_id );
		$write  = $this->persist( $post_id, $normalized );
		if ( is_wp_error( $write ) ) {
			$this->restore( $post_id, $before );
			return $write;
		}

		$inspection = $this->inspect( $post_id );
		if ( is_wp_error( $inspection ) || ( $inspection['values'] ?? null ) !== $normalized ) {
			$this->restore( $post_id, $before );

			return new WP_Error(
				'seo_geo_native_seo_verification_failed',
				'Native SEO override verification failed; previous metadata was restored.',
				array( 'status' => 409 )
			);
		}

		return $inspection;
	}

	/**
	 * Build the deterministic resource SEO fingerprint.
	 *
	 * @param int                        $post_id WordPress resource ID.
	 * @param array<string, string|null> $values  Normalized override state.
	 */
	public static function fingerprint( int $post_id, array $values ): string {
		return hash(
			'sha256',
			(string) wp_json_encode(
				array(
					'resource_id' => $post_id,
					'values'      => $values,
				)
			)
		);
	}

	/**
	 * Validate one WordPress resource supported by the native SEO contract.
	 *
	 * @param int $post_id WordPress resource ID.
	 * @return WP_Post|WP_Error
	 */
	private function eligible_post( int $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post instanceof WP_Post || in_array( $post->post_status, array( 'trash', 'auto-draft' ), true ) ) {
			return new WP_Error(
				'seo_geo_native_seo_resource_missing',
				'The SEO target resource does not exist or is not eligible.',
				array( 'status' => 404 )
			);
		}

		$type = get_post_type_object( $post->post_type );
		if ( ! $type instanceof WP_Post_Type || 'attachment' === $post->post_type || ! is_post_type_viewable( $type ) ) {
			return new WP_Error(
				'seo_geo_native_seo_resource_type_invalid',
				'Native SEO overrides require a viewable non-attachment post type.',
				array( 'status' => 400 )
			);
		}

		return $post;
	}

	/**
	 * Normalize the complete desired override state.
	 *
	 * @param array<string, mixed> $values Raw override state.
	 * @return array<string, string|null>|WP_Error
	 */
	private function normalize_values( array $values ) {
		$expected = array( 'title', 'description', 'canonical', 'indexability' );
		$keys     = array_keys( $values );
		sort( $expected );
		sort( $keys );
		if ( $expected !== $keys ) {
			return new WP_Error(
				'seo_geo_native_seo_values_invalid',
				'Native SEO replacement requires exactly title, description, canonical and indexability.',
				array( 'status' => 400 )
			);
		}

		$title = $this->normalize_text( $values['title'], self::MAX_TITLE_LENGTH, 'title' );
		if ( is_wp_error( $title ) ) {
			return $title;
		}
		$description = $this->normalize_text( $values['description'], self::MAX_DESCRIPTION_LENGTH, 'description' );
		if ( is_wp_error( $description ) ) {
			return $description;
		}
		$canonical = $this->normalize_canonical( $values['canonical'] );
		if ( is_wp_error( $canonical ) ) {
			return $canonical;
		}
		$indexability = $this->normalize_indexability( $values['indexability'] );
		if ( is_wp_error( $indexability ) ) {
			return $indexability;
		}

		return array(
			'title'        => $title,
			'description'  => $description,
			'canonical'    => $canonical,
			'indexability' => $indexability,
		);
	}

	/**
	 * Normalize a bounded plain-text override.
	 *
	 * @param mixed  $value     Raw value.
	 * @param int    $max_bytes Maximum UTF-8 byte length after normalization.
	 * @param string $field     Field identifier for errors.
	 * @return string|null|WP_Error
	 */
	private function normalize_text( $value, int $max_bytes, string $field ) {
		if ( null === $value || '' === $value ) {
			return null;
		}
		if ( ! is_string( $value ) ) {
			return new WP_Error( 'seo_geo_native_seo_' . $field . '_invalid', 'Native SEO text overrides must be strings or null.', array( 'status' => 400 ) );
		}

		$normalized = sanitize_text_field( $value );
		if ( '' === $normalized ) {
			return null;
		}
		if ( $max_bytes < strlen( $normalized ) ) {
			return new WP_Error( 'seo_geo_native_seo_' . $field . '_too_long', 'Native SEO text override exceeds the supported length bound.', array( 'status' => 400 ) );
		}

		return $normalized;
	}

	/**
	 * Normalize one absolute HTTP(S) canonical override.
	 *
	 * @param mixed $value Raw value.
	 * @return string|null|WP_Error
	 */
	private function normalize_canonical( $value ) {
		if ( null === $value || '' === $value ) {
			return null;
		}
		if ( ! is_string( $value ) ) {
			return new WP_Error( 'seo_geo_native_seo_canonical_invalid', 'Canonical override must be an absolute HTTP(S) URL or null.', array( 'status' => 400 ) );
		}

		$normalized = esc_url_raw( trim( $value ), array( 'http', 'https' ) );
		$scheme     = strtolower( (string) wp_parse_url( $normalized, PHP_URL_SCHEME ) );
		$host       = (string) wp_parse_url( $normalized, PHP_URL_HOST );
		if ( '' === $normalized || ! in_array( $scheme, array( 'http', 'https' ), true ) || '' === $host ) {
			return new WP_Error( 'seo_geo_native_seo_canonical_invalid', 'Canonical override must be an absolute HTTP(S) URL or null.', array( 'status' => 400 ) );
		}

		return $normalized;
	}

	/**
	 * Normalize one supported indexability override.
	 *
	 * @param mixed $value Raw value.
	 * @return string|null|WP_Error
	 */
	private function normalize_indexability( $value ) {
		if ( null === $value || '' === $value ) {
			return null;
		}
		if ( ! is_string( $value ) ) {
			return new WP_Error( 'seo_geo_native_seo_indexability_invalid', 'Indexability override is invalid.', array( 'status' => 400 ) );
		}

		$normalized = sanitize_key( $value );
		$allowed    = array(
			IndexabilityResolver::INDEXABLE,
			IndexabilityResolver::NOINDEX_FOLLOW,
			IndexabilityResolver::NOINDEX_NOFOLLOW,
		);
		if ( ! in_array( $normalized, $allowed, true ) ) {
			return new WP_Error( 'seo_geo_native_seo_indexability_invalid', 'Indexability override is invalid.', array( 'status' => 400 ) );
		}

		return $normalized;
	}

	/**
	 * Capture exact raw metadata state for compensation.
	 *
	 * @param int $post_id WordPress resource ID.
	 * @return array<string, array{exists:bool,value:mixed}>
	 */
	private function snapshot( int $post_id ): array {
		$result = array();
		foreach ( $this->meta_keys() as $key ) {
			$result[ $key ] = array(
				'exists' => metadata_exists( 'post', $post_id, $key ),
				'value'  => get_post_meta( $post_id, $key, true ),
			);
		}

		return $result;
	}

	/**
	 * Persist all normalized override values.
	 *
	 * @param int                        $post_id WordPress resource ID.
	 * @param array<string, string|null> $values  Normalized state.
	 * @return true|WP_Error
	 */
	private function persist( int $post_id, array $values ) {
		$mapping = array(
			'title'        => NativeSeoMetadata::META_TITLE,
			'description'  => NativeSeoMetadata::META_DESCRIPTION,
			'canonical'    => NativeSeoMetadata::META_CANONICAL,
			'indexability' => NativeSeoMetadata::META_INDEXABILITY,
		);

		foreach ( $mapping as $field => $meta_key ) {
			$value = $values[ $field ];
			if ( null === $value ) {
				delete_post_meta( $post_id, $meta_key );
				if ( metadata_exists( 'post', $post_id, $meta_key ) ) {
					return new WP_Error( 'seo_geo_native_seo_write_failed', 'Could not clear a native SEO override.', array( 'status' => 500 ) );
				}
				continue;
			}

			update_post_meta( $post_id, $meta_key, $value );
			if ( get_post_meta( $post_id, $meta_key, true ) !== $value ) {
				return new WP_Error( 'seo_geo_native_seo_write_failed', 'Could not persist a native SEO override.', array( 'status' => 500 ) );
			}
		}

		return true;
	}

	/**
	 * Restore raw metadata after a failed replacement.
	 *
	 * @param int                                           $post_id  WordPress resource ID.
	 * @param array<string, array{exists:bool,value:mixed}> $snapshot Previous raw metadata state.
	 */
	private function restore( int $post_id, array $snapshot ): void {
		foreach ( $snapshot as $key => $state ) {
			if ( true === $state['exists'] ) {
				update_post_meta( $post_id, $key, $state['value'] );
			} else {
				delete_post_meta( $post_id, $key );
			}
		}
	}

	/**
	 * Read one normalized text override.
	 *
	 * @param int    $post_id  WordPress resource ID.
	 * @param string $meta_key Native SEO metadata key.
	 */
	private function text_value( int $post_id, string $meta_key ): ?string {
		$value = get_post_meta( $post_id, $meta_key, true );
		if ( ! is_scalar( $value ) ) {
			return null;
		}

		$value = sanitize_text_field( (string) $value );

		return '' !== $value ? $value : null;
	}

	/**
	 * Read one normalized canonical override.
	 *
	 * @param int $post_id WordPress resource ID.
	 */
	private function canonical_value( int $post_id ): ?string {
		$value = get_post_meta( $post_id, NativeSeoMetadata::META_CANONICAL, true );
		if ( ! is_scalar( $value ) ) {
			return null;
		}

		$value = esc_url_raw( trim( (string) $value ), array( 'http', 'https' ) );

		return '' !== $value ? $value : null;
	}

	/**
	 * Read one normalized indexability override.
	 *
	 * @param int $post_id WordPress resource ID.
	 */
	private function indexability_value( int $post_id ): ?string {
		$value = get_post_meta( $post_id, NativeSeoMetadata::META_INDEXABILITY, true );
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
			: null;
	}

	/**
	 * Return the native SEO metadata keys owned by this writer.
	 *
	 * @return list<string>
	 */
	private function meta_keys(): array {
		return array(
			NativeSeoMetadata::META_TITLE,
			NativeSeoMetadata::META_DESCRIPTION,
			NativeSeoMetadata::META_CANONICAL,
			NativeSeoMetadata::META_INDEXABILITY,
		);
	}
}
