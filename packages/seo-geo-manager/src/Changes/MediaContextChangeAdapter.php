<?php
/**
 * Guarded editorial-context mutation adapter for existing image attachments.
 *
 * @package SeoGeoManager
 */

declare(strict_types=1);

namespace SeoGeo\Manager\Changes;

use SeoGeo\Manager\Support\MediaFingerprint;
use WP_Error;
use WP_Post;

final class MediaContextChangeAdapter {
	private const SCHEMA_VERSION         = 1;
	private const ADAPTER                = 'media-context';
	private const MAX_TITLE_LENGTH       = 500;
	private const MAX_CAPTION_LENGTH     = 4000;
	private const MAX_DESCRIPTION_LENGTH = 12000;

	/**
	 * Preview an editorial-context change without mutating WordPress.
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
			'changes'        => self::preview_changes( $prepared['before_fields'], $prepared['after_fields'] ),
			'has_changes'    => true,
			'policy'         => array(
				'context_fields_only'        => true,
				'supported_fields'           => array( 'title', 'caption', 'description' ),
				'attachment_id_only'         => true,
				'fabricated_metadata'        => false,
				'binary_mutation_supported'  => false,
				'public_media_blocked'       => true !== ( $payload['allow_public_media'] ?? false ),
				'allow_public_media'         => true === ( $payload['allow_public_media'] ?? false ),
			),
		);
	}

	/**
	 * Apply one guarded editorial-context change.
	 *
	 * @param array<string, mixed> $payload Request payload.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function apply( array $payload ) {
		$idempotency_key = isset( $payload['idempotency_key'] ) && is_string( $payload['idempotency_key'] ) ? trim( $payload['idempotency_key'] ) : '';
		if ( '' === $idempotency_key || 128 < strlen( $idempotency_key ) ) {
			return new WP_Error(
				'seo_geo_manager_media_context_idempotency_required',
				'Media context Apply requires an idempotency key of at most 128 characters.',
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
		$result     = wp_update_post( self::update_payload( $attachment->ID, $prepared['after_fields'] ), true );
		if ( is_wp_error( $result ) ) {
			self::save_failed_operation( $operation_id, $prepared );
			return $result;
		}

		$after = get_post( $attachment->ID );
		if ( ! $after instanceof WP_Post ) {
			self::restore_fields( $attachment->ID, $prepared['before_fields'] );
			self::save_failed_operation( $operation_id, $prepared );
			return new WP_Error(
				'seo_geo_manager_media_context_apply_missing',
				'Image attachment could not be reloaded after context Apply; the adapter attempted compensation.',
				array( 'status' => 500 )
			);
		}

		$after_fingerprint = MediaFingerprint::for_attachment( $after );
		if ( ! self::fields_match( $after, $prepared['after_fields'] ) || hash_equals( $prepared['before_fingerprint'], $after_fingerprint ) ) {
			self::restore_fields( $after->ID, $prepared['before_fields'] );
			self::save_failed_operation( $operation_id, $prepared );
			return new WP_Error(
				'seo_geo_manager_media_context_verify_failed',
				'Media context Apply verification failed and the adapter attempted compensation.',
				array( 'status' => 409 )
			);
		}

		$changes = array();
		foreach ( array_keys( $prepared['after_fields'] ) as $field ) {
			$changes[ $field ] = true;
		}

		$operation = array(
			'operation_id'       => $operation_id,
			'operation_type'     => 'media-context-change',
			'adapter'            => self::ADAPTER,
			'status'             => 'applied',
			'target_id'          => (int) $after->ID,
			'target_type'        => 'attachment',
			'media_id'           => (int) $after->ID,
			'before_fingerprint' => $prepared['before_fingerprint'],
			'after_fingerprint'  => $after_fingerprint,
			'before_fields'      => $prepared['before_fields'],
			'after_fields'       => $prepared['after_fields'],
			'changes'            => $changes,
			'created_at_gmt'     => gmdate( 'c' ),
			'idempotent_replay'  => false,
		);

		if ( ! OperationStore::save( $operation_id, $operation ) ) {
			self::restore_fields( $after->ID, $prepared['before_fields'] );
			return new WP_Error(
				'seo_geo_manager_media_context_operation_store_failed',
				'Image context changed but the operation record could not be persisted; the adapter attempted compensation.',
				array( 'status' => 500 )
			);
		}

		return $operation;
	}

	/**
	 * Roll back an applied editorial-context operation when the attachment is unchanged.
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	public static function rollback( string $operation_id ) {
		$operation = OperationStore::get( $operation_id );
		if ( ! is_array( $operation ) || self::ADAPTER !== ( $operation['adapter'] ?? '' ) ) {
			return new WP_Error( 'seo_geo_manager_media_context_operation_not_found', 'Media context operation not found.', array( 'status' => 404 ) );
		}
		if ( 'rolled-back' === ( $operation['status'] ?? '' ) ) {
			return $operation;
		}
		if ( 'applied' !== ( $operation['status'] ?? '' ) ) {
			return new WP_Error( 'seo_geo_manager_media_context_rollback_unavailable', 'Only applied media context operations can be rolled back.', array( 'status' => 409 ) );
		}

		$media_id   = isset( $operation['media_id'] ) ? absint( $operation['media_id'] ) : 0;
		$attachment = get_post( $media_id );
		if ( ! self::is_image_attachment( $attachment ) ) {
			return new WP_Error( 'seo_geo_manager_media_context_attachment_missing', 'Media context rollback attachment no longer exists.', array( 'status' => 404 ) );
		}
		if ( ! current_user_can( 'upload_files' ) || ! current_user_can( 'edit_post', $attachment->ID ) ) {
			return new WP_Error( 'seo_geo_manager_forbidden', 'You cannot roll back this image context.', array( 'status' => 403 ) );
		}

		$current           = MediaFingerprint::for_attachment( $attachment );
		$after_fingerprint = isset( $operation['after_fingerprint'] ) && is_string( $operation['after_fingerprint'] ) ? $operation['after_fingerprint'] : '';
		if ( '' === $after_fingerprint || ! hash_equals( $after_fingerprint, $current ) ) {
			return new WP_Error(
				'seo_geo_manager_media_context_rollback_stale',
				'Image context changed after Apply; rollback is blocked to avoid overwriting newer edits.',
				array( 'status' => 409 )
			);
		}

		$before_fields = isset( $operation['before_fields'] ) && is_array( $operation['before_fields'] ) ? $operation['before_fields'] : array();
		$after_fields  = isset( $operation['after_fields'] ) && is_array( $operation['after_fields'] ) ? $operation['after_fields'] : array();
		if ( array() === $before_fields || array() === $after_fields || ! self::fields_match( $attachment, $after_fields ) ) {
			return new WP_Error( 'seo_geo_manager_media_context_rollback_state_mismatch', 'Stored media context state no longer matches the attachment.', array( 'status' => 409 ) );
		}

		$result = wp_update_post( self::update_payload( $attachment->ID, $before_fields ), true );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$restored = get_post( $attachment->ID );
		$before_fingerprint = isset( $operation['before_fingerprint'] ) && is_string( $operation['before_fingerprint'] ) ? $operation['before_fingerprint'] : '';
		if ( ! $restored instanceof WP_Post || ! self::fields_match( $restored, $before_fields ) || '' === $before_fingerprint || ! hash_equals( $before_fingerprint, MediaFingerprint::for_attachment( $restored ) ) ) {
			wp_update_post( self::update_payload( $attachment->ID, $after_fields ) );
			return new WP_Error(
				'seo_geo_manager_media_context_rollback_verify_failed',
				'Media context rollback verification failed; the adapter attempted to restore the applied state.',
				array( 'status' => 409 )
			);
		}

		$operation['status']               = 'rolled-back';
		$operation['rolled_back_at_gmt']   = gmdate( 'c' );
		$operation['rollback_fingerprint'] = MediaFingerprint::for_attachment( $restored );
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
		$fields         = isset( $payload['fields'] ) && is_array( $payload['fields'] ) ? $payload['fields'] : array();

		if ( self::SCHEMA_VERSION !== $schema_version || 1 > $media_id || '' === $expected || array() === $fields ) {
			return new WP_Error(
				'seo_geo_manager_media_context_request_invalid',
				'Media context requests require media_id, expected_fingerprint and at least one supported field.',
				array( 'status' => 400 )
			);
		}
		if ( $for_apply && true !== ( $payload['allow_public_media'] ?? false ) ) {
			return new WP_Error(
				'seo_geo_manager_media_context_public_approval_required',
				'Image context writes require explicit public-media approval after Preview.',
				array( 'status' => 409 )
			);
		}

		$normalized = self::normalize_fields( $fields );
		if ( is_wp_error( $normalized ) ) {
			return $normalized;
		}

		$attachment = get_post( $media_id );
		if ( ! self::is_image_attachment( $attachment ) ) {
			return new WP_Error( 'seo_geo_manager_media_context_not_found', 'The requested image attachment was not found.', array( 'status' => 404 ) );
		}
		if ( ! current_user_can( 'upload_files' ) || ! current_user_can( 'edit_post', $attachment->ID ) ) {
			return new WP_Error( 'seo_geo_manager_forbidden', 'You cannot modify this image attachment.', array( 'status' => 403 ) );
		}

		$current_fingerprint = MediaFingerprint::for_attachment( $attachment );
		if ( ! hash_equals( $expected, $current_fingerprint ) ) {
			return new WP_Error(
				'seo_geo_manager_media_context_fingerprint_mismatch',
				'Image context changed after inspection; inspect the attachment again before applying.',
				array( 'status' => 409 )
			);
		}

		$current_fields = self::current_fields( $attachment );
		$before_fields  = array();
		$after_fields   = array();
		foreach ( $normalized as $field => $value ) {
			if ( $current_fields[ $field ] === $value ) {
				continue;
			}
			$before_fields[ $field ] = $current_fields[ $field ];
			$after_fields[ $field ]  = $value;
		}
		if ( array() === $after_fields ) {
			return new WP_Error( 'seo_geo_manager_media_context_no_changes', 'The image already has the requested editorial context.', array( 'status' => 400 ) );
		}

		return array(
			'attachment'         => $attachment,
			'before_fingerprint' => $current_fingerprint,
			'before_fields'      => $before_fields,
			'after_fields'       => $after_fields,
		);
	}

	/**
	 * @param array<string, mixed> $fields Requested fields.
	 * @return array<string, string>|WP_Error
	 */
	private static function normalize_fields( array $fields ) {
		$allowed = array( 'title', 'caption', 'description' );
		foreach ( array_keys( $fields ) as $field ) {
			if ( ! is_string( $field ) || ! in_array( $field, $allowed, true ) ) {
				return new WP_Error( 'seo_geo_manager_media_context_field_unsupported', 'Media context accepts only title, caption and description.', array( 'status' => 400 ) );
			}
		}

		$normalized = array();
		if ( array_key_exists( 'title', $fields ) ) {
			if ( ! is_string( $fields['title'] ) ) {
				return new WP_Error( 'seo_geo_manager_media_context_title_invalid', 'Media title must be a string.', array( 'status' => 400 ) );
			}
			$title = sanitize_text_field( $fields['title'] );
			if ( '' === trim( $title ) || self::MAX_TITLE_LENGTH < strlen( $title ) ) {
				return new WP_Error( 'seo_geo_manager_media_context_title_invalid', 'Media title must be non-empty and at most 500 bytes.', array( 'status' => 400 ) );
			}
			$normalized['title'] = $title;
		}
		if ( array_key_exists( 'caption', $fields ) ) {
			if ( ! is_string( $fields['caption'] ) ) {
				return new WP_Error( 'seo_geo_manager_media_context_caption_invalid', 'Media caption must be a string.', array( 'status' => 400 ) );
			}
			$caption = sanitize_textarea_field( $fields['caption'] );
			if ( self::MAX_CAPTION_LENGTH < strlen( $caption ) ) {
				return new WP_Error( 'seo_geo_manager_media_context_caption_invalid', 'Media caption must be at most 4000 bytes.', array( 'status' => 400 ) );
			}
			$normalized['caption'] = $caption;
		}
		if ( array_key_exists( 'description', $fields ) ) {
			if ( ! is_string( $fields['description'] ) ) {
				return new WP_Error( 'seo_geo_manager_media_context_description_invalid', 'Media description must be a string.', array( 'status' => 400 ) );
			}
			$description = wp_kses_post( $fields['description'] );
			if ( self::MAX_DESCRIPTION_LENGTH < strlen( $description ) ) {
				return new WP_Error( 'seo_geo_manager_media_context_description_invalid', 'Media description must be at most 12000 bytes.', array( 'status' => 400 ) );
			}
			$normalized['description'] = $description;
		}

		if ( array() === $normalized ) {
			return new WP_Error( 'seo_geo_manager_media_context_request_invalid', 'At least one supported media context field is required.', array( 'status' => 400 ) );
		}

		return $normalized;
	}

	/** @return array<string, string> */
	private static function current_fields( WP_Post $attachment ): array {
		return array(
			'title'       => (string) $attachment->post_title,
			'caption'     => (string) $attachment->post_excerpt,
			'description' => (string) $attachment->post_content,
		);
	}

	/**
	 * @param array<string, mixed> $fields Context fields.
	 * @return array<string, mixed>
	 */
	private static function update_payload( int $media_id, array $fields ): array {
		$update = array( 'ID' => $media_id );
		if ( isset( $fields['title'] ) && is_string( $fields['title'] ) ) {
			$update['post_title'] = $fields['title'];
		}
		if ( isset( $fields['caption'] ) && is_string( $fields['caption'] ) ) {
			$update['post_excerpt'] = $fields['caption'];
		}
		if ( isset( $fields['description'] ) && is_string( $fields['description'] ) ) {
			$update['post_content'] = $fields['description'];
		}
		return $update;
	}

	/** @param array<string, mixed> $fields Expected fields. */
	private static function fields_match( WP_Post $attachment, array $fields ): bool {
		$current = self::current_fields( $attachment );
		foreach ( $fields as $field => $value ) {
			if ( ! is_string( $field ) || ! is_string( $value ) || ! isset( $current[ $field ] ) || $current[ $field ] !== $value ) {
				return false;
			}
		}
		return true;
	}

	/** @param array<string, mixed> $fields Previous fields. */
	private static function restore_fields( int $media_id, array $fields ): void {
		wp_update_post( self::update_payload( $media_id, $fields ) );
	}

	/**
	 * @param array<string, mixed> $prepared Prepared mutation.
	 */
	private static function save_failed_operation( string $operation_id, array $prepared ): void {
		OperationStore::save(
			$operation_id,
			array(
				'operation_id'       => $operation_id,
				'operation_type'     => 'media-context-change',
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
			return new WP_Error( 'seo_geo_manager_media_context_idempotency_unavailable', 'Idempotency key points to an incompatible operation.', array( 'status' => 409 ) );
		}
		if ( 'failed' === ( $operation['status'] ?? '' ) ) {
			return new WP_Error( 'seo_geo_manager_media_context_previous_attempt_failed', 'This idempotency key belongs to a failed media context attempt.', array( 'status' => 409 ) );
		}
		$operation['idempotent_replay'] = true;
		return $operation;
	}

	/**
	 * @param array<string, mixed> $payload Request payload.
	 */
	private static function payload_hash( array $payload ): string {
		$fields = isset( $payload['fields'] ) && is_array( $payload['fields'] ) ? $payload['fields'] : array();
		$normalized = array();
		if ( isset( $fields['title'] ) && is_string( $fields['title'] ) ) {
			$normalized['title'] = sanitize_text_field( $fields['title'] );
		}
		if ( array_key_exists( 'caption', $fields ) && is_string( $fields['caption'] ) ) {
			$normalized['caption'] = sanitize_textarea_field( $fields['caption'] );
		}
		if ( array_key_exists( 'description', $fields ) && is_string( $fields['description'] ) ) {
			$normalized['description'] = wp_kses_post( $fields['description'] );
		}
		ksort( $normalized );

		return hash(
			'sha256',
			(string) wp_json_encode(
				array(
					'schema_version'       => isset( $payload['schema_version'] ) ? (int) $payload['schema_version'] : self::SCHEMA_VERSION,
					'media_id'             => isset( $payload['media_id'] ) ? absint( $payload['media_id'] ) : 0,
					'expected_fingerprint' => isset( $payload['expected_fingerprint'] ) && is_string( $payload['expected_fingerprint'] ) ? trim( $payload['expected_fingerprint'] ) : '',
					'fields'               => $normalized,
					'allow_public_media'   => true === ( $payload['allow_public_media'] ?? false ),
				)
			)
		);
	}

	/**
	 * @param array<string, mixed> $before Before fields.
	 * @param array<string, mixed> $after After fields.
	 * @return array<string, array<string, string>>
	 */
	private static function preview_changes( array $before, array $after ): array {
		$changes = array();
		foreach ( $after as $field => $value ) {
			if ( is_string( $field ) && is_string( $value ) && isset( $before[ $field ] ) && is_string( $before[ $field ] ) ) {
				$changes[ $field ] = array(
					'before' => $before[ $field ],
					'after'  => $value,
				);
			}
		}
		return $changes;
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

	/**
	 * @param WP_Post|mixed $attachment Attachment candidate.
	 */
	private static function is_image_attachment( $attachment ): bool {
		return $attachment instanceof WP_Post
			&& 'attachment' === $attachment->post_type
			&& str_starts_with( strtolower( (string) $attachment->post_mime_type ), 'image/' );
	}
}
