<?php
/**
 * Guarded featured-media binding for WordPress posts and pages.
 *
 * @package SeoGeoManager
 */
declare(strict_types=1);

namespace SeoGeo\Manager\Changes;

use SeoGeo\Manager\Support\FeaturedMediaFingerprint;
use SeoGeo\Manager\Support\MediaFingerprint;
use WP_Error;
use WP_Post;

final class FeaturedMediaChangeAdapter {
	private const SCHEMA_VERSION = 1;
	private const ADAPTER        = 'featured-media';
	private const ACTION_REPLACE = 'replace';
	private const ACTION_REMOVE  = 'remove';

	/**
	 * @param array<string, mixed> $payload Request payload.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function preview( array $payload ) {
		$prepared = self::prepare( $payload, false );
		if ( is_wp_error( $prepared ) ) {
			return $prepared;
		}

		$media = $prepared['media'] instanceof WP_Post ? self::media_summary( $prepared['media'] ) : null;

		return array(
			'schema_version' => self::SCHEMA_VERSION,
			'mode'           => 'preview',
			'adapter'        => self::ADAPTER,
			'resource'       => self::resource_summary( $prepared['resource'] ),
			'media'          => $media,
			'change'         => array(
				'action'          => $prepared['media_action'],
				'before_media_id' => $prepared['before_media_id'],
				'after_media_id'  => $prepared['after_media_id'],
			),
			'has_changes'    => true,
			'policy'         => array(
				'posts_pages_only'            => true,
				'image_attachment_only'       => true,
				'published_approval_required' => 'publish' === $prepared['resource']->post_status,
				'binary_mutation_supported'   => false,
				'content_mutation_supported'  => false,
				'remove_relationship_supported' => true,
				'stale_safe_rollback'          => true,
			),
		);
	}

	/**
	 * @param array<string, mixed> $payload Request payload.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function apply( array $payload ) {
		$idempotency_key = isset( $payload['idempotency_key'] ) && is_string( $payload['idempotency_key'] ) ? trim( $payload['idempotency_key'] ) : '';
		if ( '' === $idempotency_key || 128 < strlen( $idempotency_key ) ) {
			return new WP_Error(
				'seo_geo_manager_featured_media_idempotency_required',
				'Featured-media Apply requires an idempotency key of at most 128 characters.',
				array( 'status' => 400 )
			);
		}

		$payload_hash = self::payload_hash( $payload );
		$lookup       = OperationStore::lookup( $idempotency_key, $payload_hash );
		if ( is_wp_error( $lookup ) ) {
			return $lookup;
		}
		if ( true === $lookup['existing'] ) {
			return self::replay_operation( $lookup['operation_id'] );
		}

		$prepared = self::prepare( $payload, true );
		if ( is_wp_error( $prepared ) ) {
			return $prepared;
		}

		$operation_id = wp_generate_uuid4();
		$reservation  = OperationStore::reserve( $idempotency_key, $payload_hash, $operation_id );
		if ( is_wp_error( $reservation ) ) {
			return $reservation;
		}
		if ( true === $reservation['existing'] ) {
			return self::replay_operation( $reservation['operation_id'] );
		}

		$resource_id   = (int) $prepared['resource']->ID;
		$after_media_id = (int) $prepared['after_media_id'];
		self::restore_media( $resource_id, $after_media_id );

		$after = get_post( $resource_id );
		if ( ! $after instanceof WP_Post || (int) get_post_thumbnail_id( $resource_id ) !== $after_media_id ) {
			self::restore_media( $resource_id, $prepared['before_media_id'] );
			self::save_failed_operation( $operation_id, $prepared, $payload_hash, $idempotency_key );
			return new WP_Error(
				'seo_geo_manager_featured_media_verify_failed',
				'Featured-media verification failed and the adapter attempted compensation.',
				array( 'status' => 409 )
			);
		}

		$after_fingerprint = FeaturedMediaFingerprint::for_post( $after );
		if ( hash_equals( $prepared['before_fingerprint'], $after_fingerprint ) ) {
			self::restore_media( $resource_id, $prepared['before_media_id'] );
			self::save_failed_operation( $operation_id, $prepared, $payload_hash, $idempotency_key );
			return new WP_Error(
				'seo_geo_manager_featured_media_fingerprint_unchanged',
				'Featured-media assignment did not produce a verifiable resource-state change.',
				array( 'status' => 409 )
			);
		}

		$operation = array(
			'operation_id'       => $operation_id,
			'operation_type'     => 'featured-media-change',
			'adapter'            => self::ADAPTER,
			'schema_version'     => self::SCHEMA_VERSION,
			'status'             => 'applied',
			'target_id'          => $resource_id,
			'target_type'        => (string) $after->post_type,
			'resource_id'        => $resource_id,
			'media_action'       => $prepared['media_action'],
			'before_media_id'    => $prepared['before_media_id'],
			'after_media_id'     => $after_media_id,
			'before_fingerprint' => $prepared['before_fingerprint'],
			'after_fingerprint'  => $after_fingerprint,
			'idempotency_key'    => $idempotency_key,
			'payload_hash'       => $payload_hash,
			'changes'            => array( 'featured_media' => true ),
			'created_at_gmt'     => gmdate( 'c' ),
			'idempotent_replay'  => false,
		);

		if ( ! OperationStore::save( $operation_id, $operation ) ) {
			self::restore_media( $resource_id, $prepared['before_media_id'] );
			return new WP_Error(
				'seo_geo_manager_featured_media_operation_store_failed',
				'Featured media changed but the operation record could not be persisted; the adapter attempted compensation.',
				array( 'status' => 500 )
			);
		}

		return $operation;
	}

	/**
	 * Verify that a stored featured-media operation still owns the current state.
	 *
	 * @param array<string, mixed> $operation Stored Manager operation.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function verify( array $operation ) {
		if ( self::ADAPTER !== ( $operation['adapter'] ?? '' ) ) {
			return new WP_Error( 'seo_geo_manager_featured_media_operation_not_found', 'Featured-media operation not found.', array( 'status' => 404 ) );
		}
		if ( 'applied' !== ( $operation['status'] ?? '' ) ) {
			return new WP_Error( 'seo_geo_manager_featured_media_verify_unavailable', 'Only applied featured-media operations can be verified.', array( 'status' => 409 ) );
		}

		$resource_id = isset( $operation['resource_id'] ) ? absint( $operation['resource_id'] ) : 0;
		$resource    = get_post( $resource_id );
		if ( ! self::is_supported_resource( $resource ) ) {
			return new WP_Error( 'seo_geo_manager_featured_media_resource_missing', 'Featured-media verification target no longer exists.', array( 'status' => 404 ) );
		}
		if ( ! current_user_can( 'edit_post', $resource->ID ) || ! current_user_can( 'upload_files' ) ) {
			return new WP_Error( 'seo_geo_manager_forbidden', 'You cannot verify featured media on this resource.', array( 'status' => 403 ) );
		}

		$current_fingerprint  = FeaturedMediaFingerprint::for_post( $resource );
		$expected_fingerprint = isset( $operation['after_fingerprint'] ) && is_string( $operation['after_fingerprint'] ) ? $operation['after_fingerprint'] : '';
		$expected_media_id    = isset( $operation['after_media_id'] ) ? absint( $operation['after_media_id'] ) : 0;
		$current_media_id     = (int) get_post_thumbnail_id( $resource_id );
		if ( '' === $expected_fingerprint || ! hash_equals( $expected_fingerprint, $current_fingerprint ) || $expected_media_id !== $current_media_id ) {
			return new WP_Error(
				'seo_geo_manager_featured_media_verification_stale',
				'The resource no longer matches the applied featured-media operation.',
				array( 'status' => 409 )
			);
		}

		return array(
			'verified'            => true,
			'current_fingerprint' => $current_fingerprint,
			'featured_media_id'   => $current_media_id,
		);
	}

	/**
	 * @return array<string, mixed>|WP_Error
	 */
	public static function rollback( string $operation_id ) {
		$operation = OperationStore::get( $operation_id );
		if ( ! is_array( $operation ) || self::ADAPTER !== ( $operation['adapter'] ?? '' ) ) {
			return new WP_Error( 'seo_geo_manager_featured_media_operation_not_found', 'Featured-media operation not found.', array( 'status' => 404 ) );
		}
		if ( 'rolled-back' === ( $operation['status'] ?? '' ) ) {
			return $operation;
		}
		if ( 'applied' !== ( $operation['status'] ?? '' ) ) {
			return new WP_Error( 'seo_geo_manager_featured_media_rollback_unavailable', 'Only applied featured-media operations can be rolled back.', array( 'status' => 409 ) );
		}

		$resource_id = isset( $operation['resource_id'] ) ? absint( $operation['resource_id'] ) : 0;
		$resource    = get_post( $resource_id );
		if ( ! self::is_supported_resource( $resource ) ) {
			return new WP_Error( 'seo_geo_manager_featured_media_resource_missing', 'Featured-media rollback target no longer exists.', array( 'status' => 404 ) );
		}
		if ( ! current_user_can( 'edit_post', $resource->ID ) || ! current_user_can( 'upload_files' ) ) {
			return new WP_Error( 'seo_geo_manager_forbidden', 'You cannot roll back featured media on this resource.', array( 'status' => 403 ) );
		}

		$current_fingerprint = FeaturedMediaFingerprint::for_post( $resource );
		$after_fingerprint   = isset( $operation['after_fingerprint'] ) && is_string( $operation['after_fingerprint'] ) ? $operation['after_fingerprint'] : '';
		$after_media_id      = isset( $operation['after_media_id'] ) ? absint( $operation['after_media_id'] ) : 0;
		if ( '' === $after_fingerprint || ! hash_equals( $after_fingerprint, $current_fingerprint ) || (int) get_post_thumbnail_id( $resource_id ) !== $after_media_id ) {
			return new WP_Error(
				'seo_geo_manager_featured_media_rollback_stale',
				'Featured media changed after Apply; rollback is blocked to avoid overwriting newer edits.',
				array( 'status' => 409 )
			);
		}

		$before_media_id = isset( $operation['before_media_id'] ) ? absint( $operation['before_media_id'] ) : 0;
		self::restore_media( $resource_id, $before_media_id );
		$restored           = get_post( $resource_id );
		$before_fingerprint = isset( $operation['before_fingerprint'] ) && is_string( $operation['before_fingerprint'] ) ? $operation['before_fingerprint'] : '';
		if ( ! $restored instanceof WP_Post || (int) get_post_thumbnail_id( $resource_id ) !== $before_media_id || '' === $before_fingerprint || ! hash_equals( $before_fingerprint, FeaturedMediaFingerprint::for_post( $restored ) ) ) {
			self::restore_media( $resource_id, $after_media_id );
			return new WP_Error(
				'seo_geo_manager_featured_media_rollback_verify_failed',
				'Featured-media rollback verification failed; the adapter attempted to restore the applied state.',
				array( 'status' => 409 )
			);
		}

		$operation['status']               = 'rolled-back';
		$operation['rolled_back_at_gmt']   = gmdate( 'c' );
		$operation['rollback_fingerprint'] = FeaturedMediaFingerprint::for_post( $restored );
		OperationStore::save( $operation_id, $operation );

		return $operation;
	}

	/**
	 * @param array<string, mixed> $payload Request payload.
	 * @return array<string, mixed>|WP_Error
	 */
	private static function prepare( array $payload, bool $for_apply ) {
		$schema_version = isset( $payload['schema_version'] ) ? (int) $payload['schema_version'] : self::SCHEMA_VERSION;
		$resource_id    = isset( $payload['resource_id'] ) ? absint( $payload['resource_id'] ) : 0;
		$expected       = isset( $payload['expected_fingerprint'] ) && is_string( $payload['expected_fingerprint'] ) ? trim( $payload['expected_fingerprint'] ) : '';
		$media_action   = self::normalize_action( $payload );
		if ( self::SCHEMA_VERSION !== $schema_version || 1 > $resource_id || '' === $expected || ! in_array( $media_action, array( self::ACTION_REPLACE, self::ACTION_REMOVE ), true ) ) {
			return new WP_Error(
				'seo_geo_manager_featured_media_request_invalid',
				'Featured-media requests require resource_id, expected_fingerprint and a valid media action.',
				array( 'status' => 400 )
			);
		}

		$resource = get_post( $resource_id );
		if ( ! self::is_supported_resource( $resource ) ) {
			return new WP_Error( 'seo_geo_manager_featured_media_resource_not_found', 'The requested post/page was not found.', array( 'status' => 404 ) );
		}
		if ( ! post_type_supports( $resource->post_type, 'thumbnail' ) ) {
			return new WP_Error( 'seo_geo_manager_featured_media_unsupported', 'This post type does not support featured media.', array( 'status' => 409 ) );
		}
		if ( ! current_user_can( 'edit_post', $resource->ID ) || ! current_user_can( 'upload_files' ) ) {
			return new WP_Error( 'seo_geo_manager_forbidden', 'You cannot assign featured media to this resource.', array( 'status' => 403 ) );
		}
		if ( $for_apply && 'publish' === $resource->post_status && true !== ( $payload['allow_published_target'] ?? false ) ) {
			return new WP_Error(
				'seo_geo_manager_featured_media_public_approval_required',
				'Published resource writes require explicit approval after Preview.',
				array( 'status' => 409 )
			);
		}

		$current_fingerprint = FeaturedMediaFingerprint::for_post( $resource );
		if ( ! hash_equals( $expected, $current_fingerprint ) ) {
			return new WP_Error(
				'seo_geo_manager_featured_media_fingerprint_mismatch',
				'Resource featured-media state changed after inspection; inspect it again before applying.',
				array( 'status' => 409 )
			);
		}

		$before_media_id = (int) get_post_thumbnail_id( $resource_id );
		if ( self::ACTION_REMOVE === $media_action ) {
			$requested_media_id = isset( $payload['media_id'] ) ? absint( $payload['media_id'] ) : 0;
			if ( 0 !== $requested_media_id ) {
				return new WP_Error( 'seo_geo_manager_featured_media_remove_invalid', 'Remove requests must use media_id 0.', array( 'status' => 400 ) );
			}
			if ( 1 > $before_media_id ) {
				return new WP_Error( 'seo_geo_manager_featured_media_no_changes', 'The resource has no featured media to remove.', array( 'status' => 400 ) );
			}
			return array(
				'resource'           => $resource,
				'media'              => null,
				'media_action'       => self::ACTION_REMOVE,
				'before_media_id'    => $before_media_id,
				'after_media_id'     => 0,
				'before_fingerprint' => $current_fingerprint,
			);
		}

		$media_id = isset( $payload['media_id'] ) ? absint( $payload['media_id'] ) : 0;
		if ( 1 > $media_id ) {
			return new WP_Error( 'seo_geo_manager_featured_media_request_invalid', 'Replace requests require a positive image attachment media_id.', array( 'status' => 400 ) );
		}
		$media = get_post( $media_id );
		if ( ! self::is_image_attachment( $media ) ) {
			return new WP_Error( 'seo_geo_manager_featured_media_image_not_found', 'The selected image attachment was not found.', array( 'status' => 404 ) );
		}
		if ( ! current_user_can( 'edit_post', $media->ID ) ) {
			return new WP_Error( 'seo_geo_manager_forbidden', 'You cannot use this image attachment.', array( 'status' => 403 ) );
		}
		if ( $media_id === $before_media_id ) {
			return new WP_Error( 'seo_geo_manager_featured_media_no_changes', 'The selected image is already assigned as featured media.', array( 'status' => 400 ) );
		}

		return array(
			'resource'           => $resource,
			'media'              => $media,
			'media_action'       => self::ACTION_REPLACE,
			'before_media_id'    => $before_media_id,
			'after_media_id'     => $media_id,
			'before_fingerprint' => $current_fingerprint,
		);
	}

	/** @param array<string, mixed> $payload */
	private static function normalize_action( array $payload ): string {
		if ( isset( $payload['media_action'] ) && is_string( $payload['media_action'] ) && '' !== trim( $payload['media_action'] ) ) {
			return strtolower( trim( $payload['media_action'] ) );
		}
		return isset( $payload['media_id'] ) && 0 < absint( $payload['media_id'] ) ? self::ACTION_REPLACE : '';
	}

	/** @param WP_Post|mixed $post */
	private static function is_supported_resource( $post ): bool {
		return $post instanceof WP_Post && in_array( $post->post_type, array( 'post', 'page' ), true ) && ! in_array( $post->post_status, array( 'trash', 'auto-draft' ), true );
	}

	/** @param WP_Post|mixed $post */
	private static function is_image_attachment( $post ): bool {
		return $post instanceof WP_Post && 'attachment' === $post->post_type && str_starts_with( strtolower( (string) $post->post_mime_type ), 'image/' );
	}

	/** @return array<string, mixed> */
	private static function resource_summary( WP_Post $post ): array {
		return array(
			'id'                => (int) $post->ID,
			'post_type'         => (string) $post->post_type,
			'status'            => (string) $post->post_status,
			'title'             => html_entity_decode( get_the_title( $post ), ENT_QUOTES | ENT_HTML5, get_bloginfo( 'charset' ) ),
			'featured_media_id' => (int) get_post_thumbnail_id( $post->ID ),
			'fingerprint'       => FeaturedMediaFingerprint::for_post( $post ),
		);
	}

	/** @return array<string, mixed> */
	private static function media_summary( WP_Post $media ): array {
		$url = wp_get_attachment_url( $media->ID );
		$alt = get_post_meta( $media->ID, '_wp_attachment_image_alt', true );
		return array(
			'id'          => (int) $media->ID,
			'title'       => html_entity_decode( get_the_title( $media ), ENT_QUOTES | ENT_HTML5, get_bloginfo( 'charset' ) ),
			'mime_type'   => (string) $media->post_mime_type,
			'url'         => is_string( $url ) ? $url : '',
			'alt'         => is_string( $alt ) ? $alt : '',
			'fingerprint' => MediaFingerprint::for_attachment( $media ),
		);
	}

	private static function restore_media( int $resource_id, int $media_id ): void {
		if ( 0 < $media_id ) {
			set_post_thumbnail( $resource_id, $media_id );
			return;
		}
		delete_post_thumbnail( $resource_id );
	}

	/** @param array<string, mixed> $payload */
	private static function payload_hash( array $payload ): string {
		return hash(
			'sha256',
			(string) wp_json_encode(
				array(
					'schema_version'         => isset( $payload['schema_version'] ) ? (int) $payload['schema_version'] : self::SCHEMA_VERSION,
					'resource_id'            => isset( $payload['resource_id'] ) ? absint( $payload['resource_id'] ) : 0,
					'media_action'           => self::normalize_action( $payload ),
					'media_id'               => isset( $payload['media_id'] ) ? absint( $payload['media_id'] ) : 0,
					'expected_fingerprint'   => isset( $payload['expected_fingerprint'] ) && is_string( $payload['expected_fingerprint'] ) ? trim( $payload['expected_fingerprint'] ) : '',
					'allow_published_target' => true === ( $payload['allow_published_target'] ?? false ),
				)
			)
		);
	}

	/** @return array<string, mixed>|WP_Error */
	private static function replay_operation( string $operation_id ) {
		$operation = OperationStore::get( $operation_id );
		if ( ! is_array( $operation ) || self::ADAPTER !== ( $operation['adapter'] ?? '' ) ) {
			return new WP_Error( 'seo_geo_manager_featured_media_idempotency_unavailable', 'Idempotency key points to an incompatible operation.', array( 'status' => 409 ) );
		}
		if ( 'failed' === ( $operation['status'] ?? '' ) ) {
			return new WP_Error( 'seo_geo_manager_featured_media_previous_attempt_failed', 'This idempotency key belongs to a failed featured-media attempt.', array( 'status' => 409 ) );
		}
		$operation['idempotent_replay'] = true;
		return $operation;
	}

	/**
	 * @param array<string, mixed> $prepared Prepared state.
	 */
	private static function save_failed_operation( string $operation_id, array $prepared, string $payload_hash, string $idempotency_key ): void {
		OperationStore::save(
			$operation_id,
			array(
				'operation_id'       => $operation_id,
				'operation_type'     => 'featured-media-change',
				'adapter'            => self::ADAPTER,
				'schema_version'     => self::SCHEMA_VERSION,
				'status'             => 'failed',
				'target_id'          => (int) $prepared['resource']->ID,
				'target_type'        => (string) $prepared['resource']->post_type,
				'resource_id'        => (int) $prepared['resource']->ID,
				'media_action'       => $prepared['media_action'],
				'before_media_id'    => $prepared['before_media_id'],
				'after_media_id'     => $prepared['after_media_id'],
				'before_fingerprint' => $prepared['before_fingerprint'],
				'idempotency_key'    => $idempotency_key,
				'payload_hash'       => $payload_hash,
				'created_at_gmt'     => gmdate( 'c' ),
			)
		);
	}
}
