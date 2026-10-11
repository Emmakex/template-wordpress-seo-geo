<?php
/**
 * Native translation group mutation contract.
 *
 * @package SeoGeoCore
 */

declare(strict_types=1);

namespace SeoGeo\Core\Language;

use WP_Error;
use WP_Post;
use WP_Post_Type;

/**
 * Persists one complete reciprocal native translation group.
 *
 * This class owns storage mutation for the native multilingual contract so
 * external callers never need to write the registry post-meta keys directly.
 */
final class NativeTranslationGroupWriter {
	private const MAX_MEMBERS = 20;

	/**
	 * Native server-side language configuration.
	 *
	 * @var NativeLanguageConfiguration
	 */
	private NativeLanguageConfiguration $configuration;

	/**
	 * Create a native translation group writer.
	 *
	 * @param NativeLanguageConfiguration|null $configuration Validated native configuration.
	 */
	public function __construct( ?NativeLanguageConfiguration $configuration = null ) {
		$this->configuration = $configuration ?? NativeLanguageConfiguration::from_wordpress();
	}

	/**
	 * Inspect the exact persisted state of one native translation group.
	 *
	 * Draft members remain observable here even though NativeTranslationRegistry
	 * intentionally keeps them out of public hreflang output until publication.
	 *
	 * @param string $group_id Native translation group identifier.
	 * @return array<string, mixed>|WP_Error
	 */
	public function inspect( string $group_id ) {
		$group_id = self::normalize_group_id( $group_id );
		if ( null === $group_id ) {
			return self::error( 'seo_geo_native_group_invalid', 'Translation group ID is invalid.', 400 );
		}

		$member_ids = $this->member_ids_for_group( $group_id );
		if ( is_wp_error( $member_ids ) ) {
			return $member_ids;
		}

		$members      = array();
		$translations = array();
		$consistent   = true;
		$post_type    = null;

		foreach ( $member_ids as $post_id ) {
			$post = get_post( $post_id );
			if ( ! $post instanceof WP_Post ) {
				$consistent = false;
				continue;
			}

			$language = $this->stored_language( $post_id );
			$map      = $this->stored_translations( $post_id );
			if ( null === $language || null === $map ) {
				$consistent = false;
			}

			if ( null === $post_type ) {
				$post_type = (string) $post->post_type;
			} elseif ( (string) $post->post_type !== $post_type ) {
				$consistent = false;
			}

			if ( null !== $language ) {
				if ( isset( $translations[ $language ] ) ) {
					$consistent = false;
				} else {
					$translations[ $language ] = $post_id;
				}
			}

			$members[] = array(
				'post_id'      => $post_id,
				'post_type'    => (string) $post->post_type,
				'status'       => (string) $post->post_status,
				'language'     => $language,
				'translations' => $map,
			);
		}

		ksort( $translations );
		foreach ( $members as $member ) {
			if ( $translations !== ( $member['translations'] ?? null ) ) {
				$consistent = false;
				break;
			}
		}

		if ( array() !== $members && count( $members ) < 2 ) {
			$consistent = false;
		}

		$snapshot = array(
			'group_id'     => $group_id,
			'post_type'    => $post_type,
			'translations' => $translations,
			'members'      => $members,
			'consistent'   => $consistent,
		);

		return array(
			'schema_version' => 1,
			'group_id'       => $group_id,
			'post_type'      => $post_type,
			'translations'   => $translations,
			'members'        => $members,
			'consistent'     => $consistent,
			'fingerprint'    => self::fingerprint( $snapshot ),
			'public_ready'   => $this->public_ready( $translations ),
		);
	}

	/**
	 * Replace one complete reciprocal group.
	 *
	 * Members may be draft/pending/future/published resources. Public hreflang
	 * remains silent until NativeTranslationRegistry validates a fully published
	 * reciprocal relationship.
	 *
	 * @param string                    $group_id     Native translation group identifier.
	 * @param array<string, int|string> $translations Language-to-post map.
	 * @return array<string, mixed>|WP_Error
	 */
	public function replace( string $group_id, array $translations ) {
		$group_id = self::normalize_group_id( $group_id );
		if ( null === $group_id ) {
			return self::error( 'seo_geo_native_group_invalid', 'Translation group ID is invalid.', 400 );
		}

		$normalized = $this->normalize_translations( $translations );
		if ( is_wp_error( $normalized ) ) {
			return $normalized;
		}

		$guard = $this->validate_members( $group_id, $normalized );
		if ( is_wp_error( $guard ) ) {
			return $guard;
		}

		$existing_ids = $this->member_ids_for_group( $group_id );
		if ( is_wp_error( $existing_ids ) ) {
			return $existing_ids;
		}

		$affected_ids = array_values( array_unique( array_merge( $existing_ids, array_values( $normalized ) ) ) );
		sort( $affected_ids, SORT_NUMERIC );
		$snapshots = $this->snapshot_posts( $affected_ids );

		foreach ( $normalized as $language => $post_id ) {
			if ( ! $this->write_member( $post_id, $group_id, $language, $normalized ) ) {
				$this->restore_snapshots( $snapshots );

				return self::error(
					'seo_geo_native_group_write_failed',
					'Could not persist the reciprocal native translation group.',
					500
				);
			}
		}

		$desired_ids = array_values( $normalized );
		foreach ( $existing_ids as $post_id ) {
			if ( in_array( $post_id, $desired_ids, true ) ) {
				continue;
			}
			$this->clear_member( $post_id );
		}

		if ( ! $this->verify_replacement( $group_id, $normalized, $existing_ids ) ) {
			$this->restore_snapshots( $snapshots );

			return self::error(
				'seo_geo_native_group_verification_failed',
				'Native translation group verification failed; previous metadata was restored.',
				409
			);
		}

		return $this->inspect( $group_id );
	}

	/**
	 * Remove one complete native group when its current fingerprint is known.
	 *
	 * @param string $group_id Native translation group identifier.
	 * @return array<string, mixed>|WP_Error
	 */
	public function clear( string $group_id ) {
		$group_id = self::normalize_group_id( $group_id );
		if ( null === $group_id ) {
			return self::error( 'seo_geo_native_group_invalid', 'Translation group ID is invalid.', 400 );
		}

		$member_ids = $this->member_ids_for_group( $group_id );
		if ( is_wp_error( $member_ids ) ) {
			return $member_ids;
		}

		$snapshots = $this->snapshot_posts( $member_ids );
		foreach ( $member_ids as $post_id ) {
			$this->clear_member( $post_id );
		}

		$remaining = $this->member_ids_for_group( $group_id );
		if ( is_wp_error( $remaining ) || array() !== $remaining ) {
			$this->restore_snapshots( $snapshots );

			return self::error(
				'seo_geo_native_group_clear_failed',
				'Native translation group could not be cleared; previous metadata was restored.',
				409
			);
		}

		return $this->inspect( $group_id );
	}

	/**
	 * Build a deterministic fingerprint from an inspected group snapshot.
	 *
	 * @param array<string, mixed> $snapshot Group snapshot.
	 */
	public static function fingerprint( array $snapshot ): string {
		$members = isset( $snapshot['members'] ) && is_array( $snapshot['members'] ) ? $snapshot['members'] : array();
		usort(
			$members,
			static fn( array $left, array $right ): int => (int) ( $left['post_id'] ?? 0 ) <=> (int) ( $right['post_id'] ?? 0 )
		);

		return hash(
			'sha256',
			(string) wp_json_encode(
				array(
					'group_id'     => isset( $snapshot['group_id'] ) ? (string) $snapshot['group_id'] : '',
					'post_type'    => isset( $snapshot['post_type'] ) ? (string) $snapshot['post_type'] : '',
					'translations' => isset( $snapshot['translations'] ) && is_array( $snapshot['translations'] ) ? $snapshot['translations'] : array(),
					'members'      => $members,
					'consistent'   => true === ( $snapshot['consistent'] ?? false ),
				)
			)
		);
	}

	/**
	 * Normalize a language-to-post map.
	 *
	 * @param array $translations Raw language-to-post map.
	 * @phpstan-param array<string, int|string> $translations
	 * @return array<string, int>|WP_Error
	 */
	private function normalize_translations( array $translations ) {
		if ( count( $translations ) < 2 || self::MAX_MEMBERS < count( $translations ) ) {
			return self::error(
				'seo_geo_native_group_size_invalid',
				'Native translation groups require between 2 and 20 members.',
				400
			);
		}

		$normalized = array();
		$seen_ids   = array();
		foreach ( $translations as $raw_language => $raw_post_id ) {
			if ( ! is_string( $raw_language ) ) {
				return self::error( 'seo_geo_native_group_language_invalid', 'Translation language code is invalid.', 400 );
			}

			$language = strtolower( trim( $raw_language ) );
			if ( null === $this->configuration->locale_for( $language ) || isset( $normalized[ $language ] ) ) {
				return self::error( 'seo_geo_native_group_language_invalid', 'Translation language is not configured or is duplicated.', 400 );
			}

			if ( is_int( $raw_post_id ) ) {
				$post_id = $raw_post_id;
			} elseif ( is_string( $raw_post_id ) && ctype_digit( $raw_post_id ) ) {
				$post_id = (int) $raw_post_id;
			} else {
				return self::error( 'seo_geo_native_group_member_invalid', 'Translation resource ID is invalid.', 400 );
			}

			if ( 0 >= $post_id || isset( $seen_ids[ $post_id ] ) ) {
				return self::error( 'seo_geo_native_group_member_duplicate', 'Each translation language requires a unique WordPress resource.', 400 );
			}

			$normalized[ $language ] = $post_id;
			$seen_ids[ $post_id ]    = true;
		}
		ksort( $normalized );

		return $normalized;
	}

	/**
	 * Validate all members before a reciprocal group write.
	 *
	 * @param string $group_id     Native translation group identifier.
	 * @param array  $translations Normalized language-to-post map.
	 * @phpstan-param array<string, int> $translations
	 * @return true|WP_Error
	 */
	private function validate_members( string $group_id, array $translations ) {
		$post_type = null;
		foreach ( $translations as $post_id ) {
			$post = get_post( $post_id );
			if ( ! $post instanceof WP_Post || in_array( $post->post_status, array( 'trash', 'auto-draft' ), true ) ) {
				return self::error( 'seo_geo_native_group_member_missing', 'A translation resource does not exist or is not eligible.', 404 );
			}

			$type = get_post_type_object( $post->post_type );
			if ( ! $type instanceof WP_Post_Type || 'attachment' === $post->post_type || ! is_post_type_viewable( $type ) ) {
				return self::error( 'seo_geo_native_group_member_type_invalid', 'Translation resources must use a viewable non-attachment post type.', 400 );
			}

			if ( null === $post_type ) {
				$post_type = (string) $post->post_type;
			} elseif ( (string) $post->post_type !== $post_type ) {
				return self::error( 'seo_geo_native_group_member_type_mismatch', 'All translation resources must use the same post type.', 400 );
			}

			$stored_group = self::normalize_group_id( (string) get_post_meta( $post_id, NativeTranslationRegistry::META_GROUP, true ) );
			if ( null !== $stored_group && $stored_group !== $group_id ) {
				return self::error(
					'seo_geo_native_group_member_conflict',
					'A translation resource already belongs to a different native group.',
					409
				);
			}
		}

		return true;
	}

	/**
	 * Find all bounded members that claim one native translation group.
	 *
	 * @param string $group_id Native translation group identifier.
	 * @return list<int>|WP_Error
	 */
	private function member_ids_for_group( string $group_id ) {
		$post_types = get_post_types( array( 'public' => true ), 'names' );
		$post_types = array_values( array_filter( $post_types, static fn( string $type ): bool => 'attachment' !== $type ) );
		if ( array() === $post_types ) {
			return array();
		}

		// phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_key,WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Bounded lookup against authoritative native translation-group metadata.
		$ids = get_posts(
			array(
				'post_type'              => $post_types,
				'post_status'            => array( 'publish', 'future', 'draft', 'pending', 'private' ),
				'posts_per_page'         => self::MAX_MEMBERS + 1,
				'fields'                 => 'ids',
				'orderby'                => 'ID',
				'order'                  => 'ASC',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'meta_key'               => NativeTranslationRegistry::META_GROUP,
				'meta_value'             => $group_id,
			)
		);
		// phpcs:enable WordPress.DB.SlowDBQuery.slow_db_query_meta_key,WordPress.DB.SlowDBQuery.slow_db_query_meta_value

		if ( self::MAX_MEMBERS < count( $ids ) ) {
			return self::error( 'seo_geo_native_group_too_large', 'Native translation group exceeds the supported member bound.', 409 );
		}

		$result = array_map( 'absint', $ids );
		$result = array_values( array_filter( $result ) );
		sort( $result, SORT_NUMERIC );

		return $result;
	}

	/**
	 * Snapshot native translation metadata for rollback compensation.
	 *
	 * @param array $post_ids Affected resource IDs.
	 * @phpstan-param list<int> $post_ids
	 * @return array<int, array<string, array{exists:bool,value:mixed}>>
	 */
	private function snapshot_posts( array $post_ids ): array {
		$snapshots = array();
		$keys      = array(
			NativeTranslationRegistry::META_GROUP,
			NativeTranslationRegistry::META_LANGUAGE,
			NativeTranslationRegistry::META_TRANSLATIONS,
		);

		foreach ( $post_ids as $post_id ) {
			foreach ( $keys as $key ) {
				$snapshots[ $post_id ][ $key ] = array(
					'exists' => metadata_exists( 'post', $post_id, $key ),
					'value'  => get_post_meta( $post_id, $key, true ),
				);
			}
		}

		return $snapshots;
	}

	/**
	 * Restore previously captured native translation metadata.
	 *
	 * @param array $snapshots Previous metadata.
	 * @phpstan-param array<int, array<string, array{exists:bool,value:mixed}>> $snapshots
	 */
	private function restore_snapshots( array $snapshots ): void {
		foreach ( $snapshots as $post_id => $meta ) {
			foreach ( $meta as $key => $state ) {
				if ( $state['exists'] ) {
					update_post_meta( $post_id, $key, $state['value'] );
				} else {
					delete_post_meta( $post_id, $key );
				}
			}
		}
	}

	/**
	 * Persist one reciprocal translation-group member.
	 *
	 * @param int    $post_id      WordPress resource identifier.
	 * @param string $group_id     Native translation group identifier.
	 * @param string $language     Native language code.
	 * @param array  $translations Normalized relationship map.
	 * @phpstan-param array<string, int> $translations
	 */
	private function write_member( int $post_id, string $group_id, string $language, array $translations ): bool {
		return $this->write_meta( $post_id, NativeTranslationRegistry::META_GROUP, $group_id )
			&& $this->write_meta( $post_id, NativeTranslationRegistry::META_LANGUAGE, $language )
			&& $this->write_meta( $post_id, NativeTranslationRegistry::META_TRANSLATIONS, $translations );
	}

	/**
	 * Persist and verify one metadata value.
	 *
	 * @param int    $post_id WordPress resource identifier.
	 * @param string $key     Metadata key.
	 * @param mixed  $value   Expected metadata value.
	 */
	private function write_meta( int $post_id, string $key, $value ): bool {
		$current = get_post_meta( $post_id, $key, true );
		if ( $value === $current && metadata_exists( 'post', $post_id, $key ) ) {
			return true;
		}

		update_post_meta( $post_id, $key, $value );

		return $value === get_post_meta( $post_id, $key, true );
	}

	/**
	 * Remove native translation metadata from one member.
	 *
	 * @param int $post_id WordPress resource identifier.
	 */
	private function clear_member( int $post_id ): void {
		delete_post_meta( $post_id, NativeTranslationRegistry::META_GROUP );
		delete_post_meta( $post_id, NativeTranslationRegistry::META_LANGUAGE );
		delete_post_meta( $post_id, NativeTranslationRegistry::META_TRANSLATIONS );
	}

	/**
	 * Verify an exact reciprocal replacement and removed legacy members.
	 *
	 * @param string $group_id      Native translation group identifier.
	 * @param array  $translations  Normalized desired relationship.
	 * @param array  $previous_ids  Previous group member IDs.
	 * @phpstan-param array<string, int> $translations
	 * @phpstan-param list<int> $previous_ids
	 */
	private function verify_replacement( string $group_id, array $translations, array $previous_ids ): bool {
		foreach ( $translations as $language => $post_id ) {
			if (
				get_post_meta( $post_id, NativeTranslationRegistry::META_GROUP, true ) !== $group_id
				|| get_post_meta( $post_id, NativeTranslationRegistry::META_LANGUAGE, true ) !== $language
				|| get_post_meta( $post_id, NativeTranslationRegistry::META_TRANSLATIONS, true ) !== $translations
			) {
				return false;
			}
		}

		$desired_ids = array_values( $translations );
		foreach ( $previous_ids as $post_id ) {
			if ( in_array( $post_id, $desired_ids, true ) ) {
				continue;
			}
			if (
				metadata_exists( 'post', $post_id, NativeTranslationRegistry::META_GROUP )
				|| metadata_exists( 'post', $post_id, NativeTranslationRegistry::META_LANGUAGE )
				|| metadata_exists( 'post', $post_id, NativeTranslationRegistry::META_TRANSLATIONS )
			) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Determine whether every relation is eligible for public registry output.
	 *
	 * @param array $translations Normalized translation map.
	 * @phpstan-param array<string, int> $translations
	 */
	private function public_ready( array $translations ): bool {
		if ( count( $translations ) < 2 ) {
			return false;
		}

		$registry = new NativeTranslationRegistry( $this->configuration );
		foreach ( $translations as $post_id ) {
			if ( null === $registry->for_post( $post_id ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Read and validate one stored native language code.
	 *
	 * @param int $post_id WordPress resource identifier.
	 */
	private function stored_language( int $post_id ): ?string {
		$value = get_post_meta( $post_id, NativeTranslationRegistry::META_LANGUAGE, true );
		if ( ! is_string( $value ) ) {
			return null;
		}

		$language = strtolower( trim( $value ) );

		return null !== $this->configuration->locale_for( $language ) ? $language : null;
	}

	/**
	 * Read and normalize one stored reciprocal translation map.
	 *
	 * @param int $post_id WordPress resource identifier.
	 * @return array<string, int>|null
	 */
	private function stored_translations( int $post_id ): ?array {
		$value = get_post_meta( $post_id, NativeTranslationRegistry::META_TRANSLATIONS, true );
		if ( ! is_array( $value ) ) {
			return null;
		}

		$normalized = $this->normalize_translations( $value );

		return is_wp_error( $normalized ) ? null : $normalized;
	}

	/**
	 * Normalize a bounded native translation group identifier.
	 *
	 * @param string $group_id Candidate group identifier.
	 */
	private static function normalize_group_id( string $group_id ): ?string {
		$group_id = strtolower( trim( $group_id ) );

		return 1 === preg_match( '/^[a-z0-9][a-z0-9._:-]{0,127}$/', $group_id ) ? $group_id : null;
	}

	/**
	 * Create a bounded REST-compatible WordPress error.
	 *
	 * @param string $code    Error code.
	 * @param string $message Error message.
	 * @param int    $status  HTTP-compatible status.
	 */
	private static function error( string $code, string $message, int $status ): WP_Error {
		return new WP_Error( $code, $message, array( 'status' => $status ) );
	}
}
