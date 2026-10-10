<?php
/**
 * Guarded alt-text mutation adapter for existing image attachments.
 *
 * @package SeoGeoManager
 */

declare(strict_types=1);

namespace SeoGeo\Manager\Changes;

use SeoGeo\Manager\Support\MediaFingerprint;
use WP_Error;
use WP_Post;

final class MediaAltChangeAdapter {
	private const SCHEMA_VERSION = 1;
	private const ADAPTER        = 'media-alt';
	private const MAX_ALT_LENGTH = 1000;

	/**
	 * Preview an alt-text change without mutating WordPress.
	 *
	 * @param array<string, mixed> $payload Request payload.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function preview( array $payload ) {
		$prepared = self::prepare( $payload, false );
		if ( is_wp_error( $prepared ) ) {
			return $prepared;
		}

		return array(
			'schema_version' => self::SCHEMA_VERSION,
			'mode'           => 'preview',
			'adapter'        => self::ADAPTER,
			'media'          => self::media_summary( $prepared['attachment'] ),
			'before_alt'     => $prepared['before_alt'],
			'after_alt'      => $prepared['after_alt'],
			'has_changes'    => true,
			'policy'         => array(
				'alt_only'                   => true,
				'attachment_id_only'         => true,
				'fabricated_metadata'        => false,
				'binary_mutation_supported'  => false,
				'public_media_blocked'       => true !== ( $payload['allow_public_media'] ?? false ),
				'allow_public_media'         => true === ( $payload['allow_public_media'] ?? false ),
			),
		);
	}

	/**
	 * Apply one guarded alt-text change.
	 *
	 * @param array<string, mixed> $payload Request payload.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function apply( array $payload ) {
		$idempotency_key = isset( $payload['idempotency_key'] ) && is_string( $payload['idempotency_key'] ) ? trim( $payload['idempotency_key'] ) : '';
		if ( '' === $idempotency_key || 128 < strlen( $idempotency_key ) ) {
			return new WP_Error(
				'seo_geo_manager_media_alt_idempotency_required',
				'Media alt Apply requires an idempotency key of at most 128 characters.',
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

		$attachment = $prepared['attachment'];
		update_post_meta( $attachment->ID, '_wp_attachment_image_alt', $prepared['after_alt'] );

		$after = get_post( $attachment->ID );
		if ( ! $after instanceof WP_Post ) {
			self::restore_alt( $attachment->ID, $prepared['before_alt_exists'], $prepared['before_alt'] );
			self::save_failed_operation( $operation_id, $prepared );
			return new WP_Error(
				'seo_geo_manager_media_alt_apply_missing',
				'Image attachment could not be reloaded after alt Apply; the adapter attempted compensation.',
				array( 'status' => 500 )
			);
		}

		$stored_alt        = get_post_meta( $after->ID, '_wp_attachment_image_alt', true );
		$stored_alt        = is_string( $stored_alt ) ? $stored_alt : '';
		$after_fingerprint = MediaFingerprint::for_attachment( $after );
		if ( $stored_alt !== $prepared['after_alt'] || hash_equals( $prepared['before_fingerprint'], $after_fingerprint ) ) {
			self::restore_alt( $attachment->ID, $prepared['before_alt_exists'], $prepared['before_alt'] );
			self::save_failed_operation( $operation_id, $prepared );
			return new WP_Error(
				'seo_geo_manager_media_alt_verify_failed',
				'Media alt Apply verification failed and the adapter attempted compensation.',
				array( 'status' => 409 )
			);
		}

		$operation = array(
			'operation_id'       => $operation_id,
			'operation_type'     => 'media-alt-change',
			'adapter'            => self::ADAPTER,
			'status'             => 'applied',
			'target_id'          => (int) $after->ID,
			'target_type'        => 'attachment',
			'media_id'           => (int) $after->ID,
			'before_fingerprint' => $prepared['before_fingerprint'],
			'after_fingerprint'  => $after_fingerprint,
			'before_alt_exists'  => $prepared['before_alt_exists'],
			'before_alt'         => $prepared['before_alt'],
			'after_alt'          => $prepared['after_alt'],
			'changes'            => array( 'image_alt' => true ),
			'created_at_gmt'     => gmdate( 'c' ),
			'idempotent_replay'  => false,
		);

		if ( ! OperationStore::save( $operation_id, $operation ) ) {
			self::restore_alt( $after->ID, $prepared['before_alt_exists'], $prepared['before_alt'] );
			return new WP_Error(
				'seo_geo_manager_media_alt_operation_store_failed',
				'Image alt changed but the operation record could not be persisted; the adapter attempted compensation.',
				array( 'status' => 500 )
			);
		}

		return $operation;
	}

	/**
	 * Roll back an applied alt-text operation when the attachment is unchanged.
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	public static function rollback( string $operation_id ) {
		$operation = OperationStore::get( $operation_id );
		if ( ! is_array( $operation ) || self::ADAPTER !== ( $operation['adapter'] ?? '' ) ) {
			return new WP_Error( 'seo_geo_manager_media_alt_operation_not_found', 'Media alt operation not found.', array( 'status' => 404 ) );
		}
		if ( 'rolled-back' === ( $operation['status'] ?? '' ) ) {
			return $operation;
		}
		if ( 'applied' !== ( $operation['status'] ?? '' ) ) {
			return new WP_Error( 'seo_geo_manager_media_alt_rollback_unavailable', 'Only applied media alt operations can be rolled back.', array( 'status' => 409 ) );
		}

		$media_id   = isset( $operation['media_id'] ) ? absint( $operation['media_id'] ) : 0;
		$attachment = get_post( $media_id );
		if ( ! self::is_image_attachment( $attachment ) ) {
			return new WP_Error( 'seo_geo_manager_media_alt_attachment_missing', 'Media alt rollback attachment no longer exists.', array( 'status' => 404 ) );
		}
		if ( ! current_user_can( 'edit_post', $attachment->ID ) ) {
			return new WP_Error( 'seo_geo_manager_forbidden', 'You cannot roll back this image alt.', array( 'status' => 403 ) );
		}

		$current           = MediaFingerprint::for_attachment( $attachment );
		$after_fingerprint = isset( $operation['after_fingerprint'] ) && is_string( $operation['after_fingerprint'] ) ? $operation['after_fingerprint'] : '';
		if ( '' === $after_fingerprint || ! hash_equals( $after_fingerprint, $current ) ) {
			return new WP_Error(
				'seo_geo_manager_media_alt_rollback_stale',
				'Image metadata changed after Apply; rollback is blocked to avoid overwriting newer edits.',
				array( 'status' => 409 )
			);
		}

		$current_alt = get_post_meta( $attachment->ID, '_wp_attachment_image_alt', true );
		$current_alt = is_string( $current_alt ) ? $current_alt : '';
		$after_alt   = isset( $operation['after_alt'] ) && is_string( $operation['after_alt'] ) ? $operation['after_alt'] : '';
		if ( $current_alt !== $after_alt ) {
			return new WP_Error( 'seo_geo_manager_media_alt_rollback_state_mismatch', 'Stored media alt state no longer matches the attachment.', array( 'status' => 409 ) );
		}

		$before_exists = true === ( $operation['before_alt_exists'] ?? false );
		$before_alt    = isset( $operation['before_alt'] ) && is_string( $operation['before_alt'] ) ? $operation['before_alt'] : '';
		self::restore_alt( $attachment->ID, $before_exists, $before_alt );

		$restored = get_post( $attachment->ID );
		if ( ! $restored instanceof WP_Post ) {
			update_post_meta( $attachment->ID, '_wp_attachment_image_alt', $after_alt );
			return new WP_Error( 'seo_geo_manager_media_alt_rollback_verify_failed', 'Media alt rollback could not reload the attachment.', array( 'status' => 409 ) );
		}

		$restored_exists      = metadata_exists( 'post', $restored->ID, '_wp_attachment_image_alt' );
		$restored_alt         = get_post_meta( $restored->ID, '_wp_attachment_image_alt', true );
		$restored_alt         = is_string( $restored_alt ) ? $restored_alt : '';
		$restored_fingerprint = MediaFingerprint::for_attachment( $restored );
		$before_fingerprint   = isset( $operation['before_fingerprint'] ) && is_string( $operation['before_fingerprint'] ) ? $operation['before_fingerprint'] : '';
		if ( $restored_exists !== $before_exists || $restored_alt !== $before_alt || '' === $before_fingerprint || ! hash_equals( $before_fingerprint, $restored_fingerprint ) ) {
			update_post_meta( $restored->ID, '_wp_attachment_image_alt', $after_alt );
			return new WP_Error(
				'seo_geo_manager_media_alt_rollback_verify_failed',
				'Media alt rollback verification failed; the adapter attempted to restore the applied state.',
				array( 'status' => 409 )
			);
		}

		$operation['status']               = 'rolled-back';
		$operation['rolled_back_at_gmt']   = gmdate( 'c' );
		$operation['rollback_fingerprint'] = $restored_fingerprint;
		OperationStore::save( $operation_id, $operation );

		return $operation;
	}

	/**
	 * @param array<string, mixed> $payload Request payload.
	 * @return array<string, mixed>|WP_Error
	 */
	private static function prepare( array $payload, bool $for_apply ) {
		$schema_version = isset( $payload['schema_version'] ) ? (int) $payload['schema_version'] : self::SCHEMA_VERSION;
		$media_id       = isset( $payload['media_id'] ) ? absint( $payload['media_id'] ) : 0;
		$expected       = isset( $payload['expected_fingerprint'] ) && is_string( $payload['expected_fingerprint'] ) ? trim( $payload['expected_fingerprint'] ) : '';
		$has_alt        = array_key_exists( 'alt', $payload ) && is_string( $payload['alt'] );
		$after_alt      = $has_alt ? sanitize_text_field( $payload['alt'] ) : '';

		if ( self::SCHEMA_VERSION !== $schema_version || 1 > $media_id || '' === $expected || ! $has_alt || self::MAX_ALT_LENGTH < strlen( $after_alt ) ) {
			return new WP_Error(
				'seo_geo_manager_media_alt_request_invalid',
				'Media alt requests require media_id, expected_fingerprint and an alt string of at most 1000 bytes.',
				array( 'status' => 400 )
			);
		}
		if ( $for_apply && true !== ( $payload['allow_public_media'] ?? false ) ) {
			return new WP_Error(
				'seo_geo_manager_media_alt_public_approval_required',
				'Image alt writes require explicit public-media approval after Preview.',
				array( 'status' => 409 )
			);
		}

		$attachment = get_post( $media_id );
		if ( ! self::is_image_attachment( $attachment ) ) {
			return new WP_Error( 'seo_geo_manager_media_alt_not_found', 'The requested image attachment was not found.', array( 'status' => 404 ) );
		}
		if ( ! current_user_can( 'upload_files' ) || ! current_user_can( 'edit_post', $attachment->ID ) ) {
			return new WP_Error( 'seo_geo_manager_forbidden', 'You cannot modify this image attachment.', array( 'status' => 403 ) );
		}

		$current_fingerprint = MediaFingerprint::for_attachment( $attachment );
		if ( ! hash_equals( $expected, $current_fingerprint ) ) {
			return new WP_Error(
				'seo_geo_manager_media_alt_fingerprint_mismatch',
				'Image metadata changed after inspection; inspect the attachment again before applying.',
				array( 'status' => 409 )
			);
		}

		$before_exists = metadata_exists( 'post', $attachment->ID, '_wp_attachment_image_alt' );
		$before_alt    = get_post_meta( $attachment->ID, '_wp_attachment_image_alt', true );
		$before_alt    = is_string( $before_alt ) ? $before_alt : '';
		if ( $before_alt === $after_alt ) {
			return new WP_Error( 'seo_geo_manager_media_alt_no_changes', 'The image already has the requested alt text.', array( 'status' => 400 ) );
		}

		return array(
			'attachment'        => $attachment,
			'before_fingerprint' => $current_fingerprint,
			'before_alt_exists'  => $before_exists,
			'before_alt'         => $before_alt,
			'after_alt'          => $after_alt,
		);
	}

	/**
	 * @param WP_Post|mixed $attachment Attachment candidate.
	 */
	private static function is_image_attachment( $attachment ): bool {
		return $attachment instanceof WP_Post
			&& 'attachment' === $attachment->post_type
			&& str_starts_with( strtolower( (string) $attachment->post_mime_type ), 'image/' );
	}

	private static function restore_alt( int $media_id, bool $existed, string $alt ): void {
		if ( $existed ) {
			update_post_meta( $media_id, '_wp_attachment_image_alt', $alt );
			return;
		}
		delete_post_meta( $media_id, '_wp_attachment_image_alt' );
	}

	/**
	 * @param array<string, mixed> $prepared Prepared mutation.
	 */
	private static function save_failed_operation( string $operation_id, array $prepared ): void {
		OperationStore::save(
			$operation_id,
			array(
				'operation_id'       => $operation_id,
				'operation_type'     => 'media-alt-change',
				'adapter'            => self::ADAPTER,
				'status'             => 'failed',
				'target_id'          => (int) $prepared['attachment']->ID,
				'target_type'        => 'attachment',
				'media_id'           => (int) $prepared['attachment']->ID,
				'before_fingerprint' => $prepared['before_fingerprint'],
				'created_at_gmt'     => gmdate( 'c' ),
			)
		);
	}

	/** @return array<string, mixed>|WP_Error */
	private static function replay_operation( string $operation_id ) {
		$operation = OperationStore::get( $operation_id );
		if ( ! is_array( $operation ) || self::ADAPTER !== ( $operation['adapter'] ?? '' ) ) {
			return new WP_Error( 'seo_geo_manager_media_alt_idempotency_unavailable', 'Idempotency key points to an incompatible operation.', array( 'status' => 409 ) );
		}
		if ( 'failed' === ( $operation['status'] ?? '' ) ) {
			return new WP_Error( 'seo_geo_manager_media_alt_previous_attempt_failed', 'This idempotency key belongs to a failed media alt attempt.', array( 'status' => 409 ) );
		}
		$operation['idempotent_replay'] = true;
		return $operation;
	}

	/**
	 * @param array<string, mixed> $payload Request payload.
	 */
	private static function payload_hash( array $payload ): string {
		$alt = array_key_exists( 'alt', $payload ) && is_string( $payload['alt'] ) ? sanitize_text_field( $payload['alt'] ) : null;
		return hash(
			'sha256',
			(string) wp_json_encode(
				array(
					'schema_version'       => isset( $payload['schema_version'] ) ? (int) $payload['schema_version'] : self::SCHEMA_VERSION,
					'media_id'             => isset( $payload['media_id'] ) ? absint( $payload['media_id'] ) : 0,
					'expected_fingerprint' => isset( $payload['expected_fingerprint'] ) && is_string( $payload['expected_fingerprint'] ) ? trim( $payload['expected_fingerprint'] ) : '',
					'alt'                  => $alt,
					'allow_public_media'   => true === ( $payload['allow_public_media'] ?? false ),
				)
			)
		);
	}

	/** @return array<string, mixed> */
	private static function media_summary( WP_Post $attachment ): array {
		return array(
			'id'          => (int) $attachment->ID,
			'title'       => html_entity_decode( get_the_title( $attachment ), ENT_QUOTES | ENT_HTML5, get_bloginfo( 'charset' ) ),
			'mime_type'   => (string) $attachment->post_mime_type,
			'fingerprint' => MediaFingerprint::for_attachment( $attachment ),
		);
	}
}
