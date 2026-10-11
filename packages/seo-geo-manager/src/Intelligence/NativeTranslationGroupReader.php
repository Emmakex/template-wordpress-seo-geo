<?php
/**
 * Authorized reader for optional native translation groups.
 *
 * @package SeoGeoManager
 */

declare(strict_types=1);

namespace SeoGeo\Manager\Intelligence;

use SeoGeo\Manager\Support\NativeTranslationBridge;
use WP_Error;
use WP_Post;

/** Expose bounded native translation state only when the current identity can edit every real member. */
final class NativeTranslationGroupReader {
	/**
	 * Inspect one native translation group.
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	public static function read( string $group_id ) {
		$group_id = self::normalize_group_id( $group_id );
		if ( null === $group_id ) {
			return new WP_Error(
				'seo_geo_manager_native_translation_group_invalid',
				'The native translation group ID is invalid.',
				array( 'status' => 400 )
			);
		}

		$snapshot = NativeTranslationBridge::inspect( $group_id );
		if ( is_wp_error( $snapshot ) ) {
			return $snapshot;
		}

		$members = isset( $snapshot['members'] ) && is_array( $snapshot['members'] )
			? $snapshot['members']
			: array();
		$resources = array();
		foreach ( $members as $member ) {
			if ( ! is_array( $member ) ) {
				return self::contract_error();
			}
			$post_id = isset( $member['post_id'] ) ? absint( $member['post_id'] ) : 0;
			$post    = get_post( $post_id );
			if ( ! $post instanceof WP_Post || 1 > $post_id ) {
				return self::contract_error();
			}
			if ( ! current_user_can( 'edit_post', $post_id ) ) {
				return new WP_Error(
					'seo_geo_manager_native_translation_forbidden',
					'You cannot inspect every resource in this native translation group.',
					array( 'status' => 403 )
				);
			}
			$resources[] = self::resource_summary( $post );
		}

		$translations = self::normalize_translations( $snapshot['translations'] ?? array() );
		if ( is_wp_error( $translations ) ) {
			return $translations;
		}

		$fingerprint = isset( $snapshot['fingerprint'] ) && is_string( $snapshot['fingerprint'] )
			? strtolower( trim( $snapshot['fingerprint'] ) )
			: '';
		if ( 1 !== preg_match( '/^[a-f0-9]{64}$/', $fingerprint ) ) {
			return self::contract_error();
		}

		return array(
			'schema_version' => 1,
			'provider'       => 'native-core',
			'group_id'       => $group_id,
			'post_type'      => isset( $snapshot['post_type'] ) && is_string( $snapshot['post_type'] )
				? sanitize_key( $snapshot['post_type'] )
				: null,
			'translations'   => $translations,
			'resources'      => $resources,
			'consistent'     => true === ( $snapshot['consistent'] ?? false ),
			'public_ready'   => true === ( $snapshot['public_ready'] ?? false ),
			'fingerprint'    => $fingerprint,
			'policy'         => array(
				'provider_owned_storage' => true,
				'raw_meta_exposed'       => false,
				'reciprocal_group'       => true,
				'minimum_members'        => 2,
				'maximum_members'        => 20,
			),
		);
	}

	/**
	 * Normalize a provider translation map.
	 *
	 * @param mixed $raw Raw provider value.
	 * @return array<string, int>|WP_Error
	 */
	public static function normalize_translations( $raw ) {
		if ( ! is_array( $raw ) ) {
			return self::contract_error();
		}

		$normalized = array();
		foreach ( $raw as $language => $post_id ) {
			if ( ! is_string( $language ) ) {
				return self::contract_error();
			}
			$language = strtolower( trim( $language ) );
			$post_id  = is_int( $post_id ) || ( is_string( $post_id ) && ctype_digit( $post_id ) )
				? absint( $post_id )
				: 0;
			if ( 1 !== preg_match( '/^[a-z][a-z0-9-]{0,15}$/', $language ) || 1 > $post_id ) {
				return self::contract_error();
			}
			$normalized[ $language ] = $post_id;
		}
		ksort( $normalized );

		return $normalized;
	}

	/** Normalize a bounded group identifier using the native provider syntax. */
	public static function normalize_group_id( string $group_id ): ?string {
		$group_id = strtolower( trim( $group_id ) );

		return 1 === preg_match( '/^[a-z0-9][a-z0-9._:-]{0,127}$/', $group_id ) ? $group_id : null;
	}

	/** @return array<string, mixed> */
	private static function resource_summary( WP_Post $post ): array {
		return array(
			'id'        => (int) $post->ID,
			'post_type' => (string) $post->post_type,
			'status'    => (string) $post->post_status,
			'title'     => html_entity_decode( get_the_title( $post ), ENT_QUOTES | ENT_HTML5, self::charset() ),
		);
	}

	private static function contract_error(): WP_Error {
		return new WP_Error(
			'seo_geo_manager_native_translation_contract_invalid',
			'The native translation provider returned inconsistent relationship data.',
			array( 'status' => 502 )
		);
	}

	private static function charset(): string {
		$charset = get_bloginfo( 'charset' );

		return is_string( $charset ) && '' !== $charset ? $charset : 'UTF-8';
	}
}
