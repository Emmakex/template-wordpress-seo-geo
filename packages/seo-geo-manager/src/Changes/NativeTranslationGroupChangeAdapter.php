<?php
/**
 * Guarded native translation-group changes through the accepted Core authority.
 *
 * @package SeoGeoManager
 */

declare(strict_types=1);

namespace SeoGeo\Manager\Changes;

use SeoGeo\Manager\Intelligence\NativeTranslationGroupReader;
use SeoGeo\Manager\Support\NativeTranslationBridge;
use WP_Error;
use WP_Post;
use WP_Post_Type;

/** Replace reciprocal native translation relationships without writing raw multilingual metadata. */
final class NativeTranslationGroupChangeAdapter {
	private const ADAPTER = 'native-translation-group';

	/**
	 * Preview one complete reciprocal group replacement.
	 *
	 * @param array<string, mixed> $payload Request payload.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function preview( array $payload ) {
		$state = self::prepare( $payload, false );
		if ( is_wp_error( $state ) ) {
			return $state;
		}

		return array(
			'schema_version' => 1,
			'mode'           => 'preview',
			'adapter'        => self::ADAPTER,
			'provider'       => 'native-core',
			'group_id'       => $state['group_id'],
			'change'         => array(
				'before_translations' => $state['before_translations'],
				'after_translations'  => $state['after_translations'],
			),
			'affected_resources' => $state['affected_resources'],
			'has_changes'    => true,
			'policy'         => array(
				'provider_owned_storage'       => true,
				'raw_meta_write_supported'     => false,
				'complete_reciprocal_replace'  => true,
				'published_approval_required'  => $state['has_published_affected'],
				'expected_fingerprint_source'  => 'fingerprint',
				'stale_safe_rollback'           => true,
			),
		);
	}

	/**
	 * Apply one complete reciprocal group replacement.
	 *
	 * @param array<string, mixed> $payload Request payload.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function apply( array $payload ) {
		$key = isset( $payload['idempotency_key'] ) && is_string( $payload['idempotency_key'] )
			? trim( $payload['idempotency_key'] )
			: '';
		if ( '' === $key || 128 < strlen( $key ) ) {
			return new WP_Error(
				'seo_geo_manager_native_translation_idempotency_required',
				'Native translation Apply requires an idempotency key of at most 128 characters.',
				array( 'status' => 400 )
			);
		}

		$hash   = self::payload_hash( $payload );
		$lookup = OperationStore::lookup( $key, $hash );
		if ( is_wp_error( $lookup ) ) {
			return $lookup;
		}
		if ( true === $lookup['existing'] ) {
			return self::replay( $lookup['operation_id'] );
		}

		$state = self::prepare( $payload, true );
		if ( is_wp_error( $state ) ) {
			return $state;
		}

		$operation_id = wp_generate_uuid4();
		$reservation  = OperationStore::reserve( $key, $hash, $operation_id );
		if ( is_wp_error( $reservation ) ) {
			return $reservation;
		}
		if ( true === $reservation['existing'] ) {
			return self::replay( $reservation['operation_id'] );
		}

		$result = NativeTranslationBridge::replace( $state['group_id'], $state['after_translations'] );
		if ( is_wp_error( $result ) ) {
			self::save_failed( $operation_id, $state, $hash, $key, $result );

			return $result;
		}

		$after = NativeTranslationGroupReader::read( $state['group_id'] );
		if ( is_wp_error( $after ) ) {
			self::restore_group( $state['group_id'], $state['before_translations'] );
			self::save_failed( $operation_id, $state, $hash, $key, $after );

			return $after;
		}
		$after_translations = self::normalized_snapshot_map( $after );
		$after_fingerprint  = isset( $after['fingerprint'] ) && is_string( $after['fingerprint'] )
			? $after['fingerprint']
			: '';
		if (
			$after_translations !== $state['after_translations']
			|| '' === $after_fingerprint
			|| hash_equals( $state['before_fingerprint'], $after_fingerprint )
		) {
			self::restore_group( $state['group_id'], $state['before_translations'] );
			$error = new WP_Error(
				'seo_geo_manager_native_translation_verify_failed',
				'Native translation relationship verification failed; Manager attempted compensation.',
				array( 'status' => 409 )
			);
			self::save_failed( $operation_id, $state, $hash, $key, $error );

			return $error;
		}

		$operation = array(
			'operation_id'        => $operation_id,
			'operation_type'      => 'native-translation-group-change',
			'adapter'             => self::ADAPTER,
			'schema_version'      => 1,
			'status'              => 'applied',
			'target_id'           => 0,
			'target_type'         => 'translation-group',
			'group_id'            => $state['group_id'],
			'before_translations' => $state['before_translations'],
			'after_translations'  => $after_translations,
			'before_fingerprint'  => $state['before_fingerprint'],
			'after_fingerprint'   => $after_fingerprint,
			'idempotency_key'     => $key,
			'payload_hash'        => $hash,
			'changes'             => array( 'native_translation_group' => true ),
			'created_at_gmt'      => gmdate( 'c' ),
			'idempotent_replay'   => false,
		);
		if ( ! OperationStore::save( $operation_id, $operation ) ) {
			self::restore_group( $state['group_id'], $state['before_translations'] );

			return new WP_Error(
				'seo_geo_manager_native_translation_operation_store_failed',
				'Native translation relationships changed but operation history could not be persisted; Manager attempted compensation.',
				array( 'status' => 500 )
			);
		}

		return $operation;
	}

	/**
	 * Roll back one applied relationship change only when the group is still at the exact applied fingerprint.
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	public static function rollback( string $operation_id ) {
		$operation = OperationStore::get( $operation_id );
		if ( ! is_array( $operation ) || self::ADAPTER !== ( $operation['adapter'] ?? '' ) ) {
			return new WP_Error(
				'seo_geo_manager_native_translation_operation_not_found',
				'Native translation operation not found.',
				array( 'status' => 404 )
			);
		}
		if ( 'rolled-back' === ( $operation['status'] ?? '' ) ) {
			return $operation;
		}
		if ( 'applied' !== ( $operation['status'] ?? '' ) ) {
			return new WP_Error(
				'seo_geo_manager_native_translation_rollback_unavailable',
				'Only applied native translation operations can be rolled back.',
				array( 'status' => 409 )
			);
		}

		$group_id = isset( $operation['group_id'] ) && is_string( $operation['group_id'] )
			? NativeTranslationGroupReader::normalize_group_id( $operation['group_id'] )
			: null;
		if ( null === $group_id ) {
			return new WP_Error(
				'seo_geo_manager_native_translation_operation_invalid',
				'Native translation operation group identity is invalid.',
				array( 'status' => 409 )
			);
		}

		$current = NativeTranslationGroupReader::read( $group_id );
		if ( is_wp_error( $current ) ) {
			return $current;
		}
		$current_map = self::normalized_snapshot_map( $current );
		$current_fp  = isset( $current['fingerprint'] ) && is_string( $current['fingerprint'] )
			? $current['fingerprint']
			: '';
		$after_map   = self::normalize_stored_map( $operation['after_translations'] ?? array() );
		$after_fp    = isset( $operation['after_fingerprint'] ) && is_string( $operation['after_fingerprint'] )
			? $operation['after_fingerprint']
			: '';
		if ( is_wp_error( $after_map ) ) {
			return $after_map;
		}
		if ( $current_map !== $after_map || '' === $after_fp || ! hash_equals( $after_fp, $current_fp ) ) {
			return new WP_Error(
				'seo_geo_manager_native_translation_rollback_stale',
				'Native translation relationships changed after Apply; rollback is blocked to avoid overwriting newer edits.',
				array( 'status' => 409 )
			);
		}

		$before_map = self::normalize_stored_map( $operation['before_translations'] ?? array(), true );
		if ( is_wp_error( $before_map ) ) {
			return $before_map;
		}
		$permission_check = self::validate_resources( $before_map, true );
		if ( is_wp_error( $permission_check ) ) {
			return $permission_check;
		}
		$before_fp = isset( $operation['before_fingerprint'] ) && is_string( $operation['before_fingerprint'] )
			? $operation['before_fingerprint']
			: '';

		$restore = self::restore_group( $group_id, $before_map );
		if ( is_wp_error( $restore ) ) {
			return $restore;
		}

		$restored = NativeTranslationGroupReader::read( $group_id );
		if ( is_wp_error( $restored ) ) {
			self::restore_group( $group_id, $after_map );

			return $restored;
		}
		$restored_map = self::normalized_snapshot_map( $restored );
		$restored_fp  = isset( $restored['fingerprint'] ) && is_string( $restored['fingerprint'] )
			? $restored['fingerprint']
			: '';
		if ( $restored_map !== $before_map || '' === $before_fp || ! hash_equals( $before_fp, $restored_fp ) ) {
			self::restore_group( $group_id, $after_map );

			return new WP_Error(
				'seo_geo_manager_native_translation_rollback_verify_failed',
				'Native translation rollback verification failed; Manager attempted to restore the applied relationship.',
				array( 'status' => 409 )
			);
		}

		$operation['status']               = 'rolled-back';
		$operation['rolled_back_at_gmt']   = gmdate( 'c' );
		$operation['rollback_fingerprint'] = $restored_fp;
		OperationStore::save( $operation_id, $operation );

		return $operation;
	}

	/**
	 * Build a validated operation state.
	 *
	 * @param array<string, mixed> $payload Request payload.
	 * @return array{group_id:string,before_translations:array<string,int>,after_translations:array<string,int>,before_fingerprint:string,affected_resources:list<array<string,mixed>>,has_published_affected:bool}|WP_Error
	 */
	private static function prepare( array $payload, bool $for_apply ) {
		$group_id = isset( $payload['group_id'] ) && is_string( $payload['group_id'] )
			? NativeTranslationGroupReader::normalize_group_id( $payload['group_id'] )
			: null;
		$expected = isset( $payload['expected_fingerprint'] ) && is_string( $payload['expected_fingerprint'] )
			? trim( $payload['expected_fingerprint'] )
			: '';
		$after = self::normalize_requested_map( $payload['translations'] ?? null );
		if ( null === $group_id || '' === $expected || is_wp_error( $after ) ) {
			return is_wp_error( $after )
				? $after
				: new WP_Error(
					'seo_geo_manager_native_translation_request_invalid',
					'Native translation requests require group_id, translations and expected_fingerprint.',
					array( 'status' => 400 )
				);
		}

		$inspection = NativeTranslationGroupReader::read( $group_id );
		if ( is_wp_error( $inspection ) ) {
			return $inspection;
		}
		if ( true !== ( $inspection['consistent'] ?? false ) ) {
			return new WP_Error(
				'seo_geo_manager_native_translation_group_inconsistent',
				'The current native translation group is inconsistent and cannot be changed through the safe relationship adapter.',
				array( 'status' => 409 )
			);
		}

		$before = self::normalized_snapshot_map( $inspection );
		$before_fingerprint = isset( $inspection['fingerprint'] ) && is_string( $inspection['fingerprint'] )
			? $inspection['fingerprint']
			: '';
		if ( '' === $before_fingerprint || ! hash_equals( $expected, $before_fingerprint ) ) {
			return new WP_Error(
				'seo_geo_manager_native_translation_fingerprint_mismatch',
				'Native translation relationships changed after inspection.',
				array( 'status' => 409 )
			);
		}
		if ( $before === $after ) {
			return new WP_Error(
				'seo_geo_manager_native_translation_no_changes',
				'The requested native translation relationship is already active.',
				array( 'status' => 400 )
			);
		}

		$after_resources = self::validate_resources( $after, false );
		if ( is_wp_error( $after_resources ) ) {
			return $after_resources;
		}

		$affected = array();
		$published = false;
		$before_resources = isset( $inspection['resources'] ) && is_array( $inspection['resources'] )
			? $inspection['resources']
			: array();
		foreach ( array_merge( $before_resources, $after_resources ) as $resource ) {
			if ( ! is_array( $resource ) ) {
				continue;
			}
			$id = isset( $resource['id'] ) ? absint( $resource['id'] ) : 0;
			if ( 1 > $id ) {
				continue;
			}
			$affected[ $id ] = $resource;
			if ( 'publish' === ( $resource['status'] ?? '' ) ) {
				$published = true;
			}
		}

		if ( $for_apply && $published && true !== ( $payload['allow_published_targets'] ?? false ) ) {
			return new WP_Error(
				'seo_geo_manager_native_translation_public_approval_required',
				'Changing translation relationships for published resources requires explicit approval after Preview.',
				array( 'status' => 409 )
			);
		}

		return array(
			'group_id'                 => $group_id,
			'before_translations'      => $before,
			'after_translations'       => $after,
			'before_fingerprint'       => $before_fingerprint,
			'affected_resources'       => array_values( $affected ),
			'has_published_affected'   => $published,
		);
	}

	/**
	 * Normalize external requested relationships.
	 *
	 * @param mixed $raw Requested relationship map.
	 * @return array<string, int>|WP_Error
	 */
	private static function normalize_requested_map( $raw ) {
		$normalized = self::normalize_map( $raw );
		if ( is_wp_error( $normalized ) ) {
			return $normalized;
		}
		if ( count( $normalized ) < 2 || 20 < count( $normalized ) ) {
			return new WP_Error(
				'seo_geo_manager_native_translation_size_invalid',
				'Native translation groups require between 2 and 20 resources.',
				array( 'status' => 400 )
			);
		}

		return $normalized;
	}

	/**
	 * Normalize stored operation relationships. Empty is valid for a newly created group's rollback state.
	 *
	 * @param mixed $raw Stored relationship map.
	 * @return array<string, int>|WP_Error
	 */
	private static function normalize_stored_map( $raw, bool $allow_empty = false ) {
		$normalized = self::normalize_map( $raw );
		if ( is_wp_error( $normalized ) ) {
			return $normalized;
		}
		$count = count( $normalized );
		if ( ( ! $allow_empty && 2 > $count ) || 20 < $count || ( $allow_empty && 1 === $count ) ) {
			return new WP_Error(
				'seo_geo_manager_native_translation_operation_invalid',
				'Stored native translation operation data is invalid.',
				array( 'status' => 409 )
			);
		}

		return $normalized;
	}

	/**
	 * Normalize a relationship map and require unique resources.
	 *
	 * @param mixed $raw Relationship map.
	 * @return array<string, int>|WP_Error
	 */
	private static function normalize_map( $raw ) {
		if ( ! is_array( $raw ) ) {
			return new WP_Error(
				'seo_geo_manager_native_translation_map_invalid',
				'Native translation relationships must be a language-to-resource map.',
				array( 'status' => 400 )
			);
		}

		$normalized = array();
		$seen       = array();
		foreach ( $raw as $language => $resource_id ) {
			if ( ! is_string( $language ) ) {
				return new WP_Error( 'seo_geo_manager_native_translation_language_invalid', 'Native translation language code is invalid.', array( 'status' => 400 ) );
			}
			$language = strtolower( trim( $language ) );
			$resource_id = is_int( $resource_id ) || ( is_string( $resource_id ) && ctype_digit( $resource_id ) )
				? absint( $resource_id )
				: 0;
			if ( 1 !== preg_match( '/^[a-z][a-z0-9-]{0,15}$/', $language ) || 1 > $resource_id ) {
				return new WP_Error( 'seo_geo_manager_native_translation_member_invalid', 'Native translation relationship contains an invalid language or resource ID.', array( 'status' => 400 ) );
			}
			if ( isset( $seen[ $resource_id ] ) ) {
				return new WP_Error( 'seo_geo_manager_native_translation_member_duplicate', 'One WordPress resource cannot represent multiple languages in the same translation group.', array( 'status' => 400 ) );
			}
			$normalized[ $language ] = $resource_id;
			$seen[ $resource_id ]    = true;
		}
		ksort( $normalized );

		return $normalized;
	}

	/**
	 * Validate current WordPress resources and current identity permissions.
	 *
	 * @param array<string, int> $translations Translation map.
	 * @return list<array<string, mixed>>|WP_Error
	 */
	private static function validate_resources( array $translations, bool $allow_empty ) {
		if ( $allow_empty && array() === $translations ) {
			return array();
		}

		$resources = array();
		$post_type = null;
		foreach ( $translations as $resource_id ) {
			$post = get_post( $resource_id );
			if ( ! $post instanceof WP_Post || in_array( $post->post_status, array( 'trash', 'auto-draft' ), true ) ) {
				return new WP_Error(
					'seo_geo_manager_native_translation_resource_not_found',
					'A requested translation resource does not exist or is not eligible.',
					array( 'status' => 404 )
				);
			}
			$type = get_post_type_object( $post->post_type );
			if ( ! $type instanceof WP_Post_Type || 'attachment' === $post->post_type || ! is_post_type_viewable( $type ) ) {
				return new WP_Error(
					'seo_geo_manager_native_translation_resource_type_invalid',
					'Native translation resources must use the same viewable non-attachment post type.',
					array( 'status' => 400 )
				);
			}
			if ( null === $post_type ) {
				$post_type = (string) $post->post_type;
			} elseif ( (string) $post->post_type !== $post_type ) {
				return new WP_Error(
					'seo_geo_manager_native_translation_resource_type_mismatch',
					'All resources in one native translation group must use the same post type.',
					array( 'status' => 400 )
				);
			}
			if ( ! current_user_can( 'edit_post', $post->ID ) ) {
				return new WP_Error(
					'seo_geo_manager_native_translation_forbidden',
					'You cannot edit every requested resource in this native translation group.',
					array( 'status' => 403 )
				);
			}
			$resources[] = array(
				'id'        => (int) $post->ID,
				'post_type' => (string) $post->post_type,
				'status'    => (string) $post->post_status,
			);
		}

		return $resources;
	}

	/** @return array<string, int> */
	private static function normalized_snapshot_map( array $snapshot ): array {
		$map = NativeTranslationGroupReader::normalize_translations( $snapshot['translations'] ?? array() );

		return is_wp_error( $map ) ? array() : $map;
	}

	/**
	 * Restore an exact previous group state.
	 *
	 * @param array<string, int> $translations Previous relationship map.
	 * @return array<string, mixed>|WP_Error
	 */
	private static function restore_group( string $group_id, array $translations ) {
		return array() === $translations
			? NativeTranslationBridge::clear( $group_id )
			: NativeTranslationBridge::replace( $group_id, $translations );
	}

	/** @param array<string, mixed> $payload Request payload. */
	private static function payload_hash( array $payload ): string {
		$translations = self::normalize_map( $payload['translations'] ?? array() );
		$translations = is_wp_error( $translations ) ? array() : $translations;

		return hash(
			'sha256',
			(string) wp_json_encode(
				array(
					'group_id'                => isset( $payload['group_id'] ) && is_string( $payload['group_id'] ) ? strtolower( trim( $payload['group_id'] ) ) : '',
					'translations'             => $translations,
					'expected_fingerprint'     => isset( $payload['expected_fingerprint'] ) && is_string( $payload['expected_fingerprint'] ) ? trim( $payload['expected_fingerprint'] ) : '',
					'allow_published_targets'  => true === ( $payload['allow_published_targets'] ?? false ),
				)
			)
		);
	}

	/** @return array<string, mixed>|WP_Error */
	private static function replay( string $operation_id ) {
		$operation = OperationStore::get( $operation_id );
		if ( ! is_array( $operation ) || self::ADAPTER !== ( $operation['adapter'] ?? '' ) ) {
			return new WP_Error(
				'seo_geo_manager_native_translation_replay_unavailable',
				'Native translation idempotent replay is unavailable.',
				array( 'status' => 409 )
			);
		}
		$operation['idempotent_replay'] = true;

		return $operation;
	}

	/**
	 * Persist a privacy-bounded failed operation after an idempotency reservation.
	 *
	 * @param array<string, mixed> $state Prepared state.
	 */
	private static function save_failed( string $operation_id, array $state, string $hash, string $key, WP_Error $error ): void {
		OperationStore::save(
			$operation_id,
			array(
				'operation_id'      => $operation_id,
				'operation_type'    => 'native-translation-group-change',
				'adapter'           => self::ADAPTER,
				'schema_version'    => 1,
				'status'            => 'verification-failed',
				'target_id'         => 0,
				'target_type'       => 'translation-group',
				'group_id'          => $state['group_id'] ?? '',
				'idempotency_key'   => $key,
				'payload_hash'      => $hash,
				'changes'           => array( 'native_translation_group' => true ),
				'error_code'        => $error->get_error_code(),
				'created_at_gmt'    => gmdate( 'c' ),
				'idempotent_replay' => false,
			)
		);
	}
}
