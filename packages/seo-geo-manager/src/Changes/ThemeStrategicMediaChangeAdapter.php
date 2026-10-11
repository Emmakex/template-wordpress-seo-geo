<?php
/**
 * Guarded Theme strategic-media changes.
 *
 * @package SeoGeoManager
 */

declare(strict_types=1);

namespace SeoGeo\Manager\Changes;

use SeoGeo\Manager\Intelligence\ThemeStrategicMediaReader;
use WP_Error;
use WP_Post;

final class ThemeStrategicMediaChangeAdapter {
	private const ADAPTER = 'theme-strategic-media';

	/**
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
			'resource_id'    => (int) $state['resource']->ID,
			'model_id'       => $state['model_id'],
			'slot_id'        => $state['slot_id'],
			'change'         => array(
				'before_attachment_id' => $state['before_attachment_id'],
				'after_attachment_id'  => $state['after_attachment_id'],
			),
			'has_changes'    => true,
			'policy'         => array(
				'declared_theme_slots_only'   => true,
				'image_attachment_only'       => true,
				'published_approval_required' => 'publish' === $state['resource']->post_status,
				'raw_theme_meta_mutation'     => false,
				'stale_safe_rollback'         => true,
			),
		);
	}

	/**
	 * @param array<string, mixed> $payload Request payload.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function apply( array $payload ) {
		$key = isset( $payload['idempotency_key'] ) && is_string( $payload['idempotency_key'] ) ? trim( $payload['idempotency_key'] ) : '';
		if ( '' === $key || 128 < strlen( $key ) ) {
			return new WP_Error( 'seo_geo_manager_theme_media_idempotency_required', 'Apply requires an idempotency key of at most 128 characters.', array( 'status' => 400 ) );
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

		$result = self::mutate( $state, $state['after_attachment_id'] );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$after_id          = self::slot( (int) $state['resource']->ID, $state['model_id'], $state['slot_id'] );
		$after_fingerprint = self::fingerprint( (int) $state['resource']->ID, $state['model_id'], $state['slot_id'] );
		if ( $after_id !== $state['after_attachment_id'] || '' === $after_fingerprint || hash_equals( $state['before_fingerprint'], $after_fingerprint ) ) {
			self::mutate( $state, $state['before_attachment_id'] );
			return new WP_Error( 'seo_geo_manager_theme_media_verify_failed', 'Strategic-media verification failed; Manager attempted compensation.', array( 'status' => 409 ) );
		}

		$operation = array(
			'operation_id'          => $operation_id,
			'operation_type'        => 'theme-strategic-media-change',
			'adapter'               => self::ADAPTER,
			'schema_version'        => 1,
			'status'                => 'applied',
			'target_id'             => (int) $state['resource']->ID,
			'target_type'           => 'page',
			'resource_id'           => (int) $state['resource']->ID,
			'model_id'              => $state['model_id'],
			'slot_id'               => $state['slot_id'],
			'before_attachment_id'  => $state['before_attachment_id'],
			'after_attachment_id'   => $after_id,
			'before_fingerprint'    => $state['before_fingerprint'],
			'after_fingerprint'     => $after_fingerprint,
			'idempotency_key'       => $key,
			'payload_hash'          => $hash,
			'changes'               => array( 'strategic_media_binding' => true ),
			'created_at_gmt'        => gmdate( 'c' ),
			'idempotent_replay'     => false,
		);
		if ( ! OperationStore::save( $operation_id, $operation ) ) {
			self::mutate( $state, $state['before_attachment_id'] );
			return new WP_Error( 'seo_geo_manager_theme_media_store_failed', 'Operation history could not be persisted; Manager attempted compensation.', array( 'status' => 500 ) );
		}

		return $operation;
	}

	/** @return array<string, mixed>|WP_Error */
	public static function rollback( string $operation_id ) {
		$operation = OperationStore::get( $operation_id );
		if ( ! is_array( $operation ) || self::ADAPTER !== ( $operation['adapter'] ?? '' ) ) {
			return new WP_Error( 'seo_geo_manager_theme_media_operation_not_found', 'Theme media operation not found.', array( 'status' => 404 ) );
		}
		if ( 'rolled-back' === ( $operation['status'] ?? '' ) ) {
			return $operation;
		}
		if ( 'applied' !== ( $operation['status'] ?? '' ) || ! self::write_supported() ) {
			return new WP_Error( 'seo_geo_manager_theme_media_rollback_unavailable', 'Theme media rollback is unavailable.', array( 'status' => 409 ) );
		}

		$resource_id = isset( $operation['resource_id'] ) ? absint( $operation['resource_id'] ) : 0;
		$model_id    = isset( $operation['model_id'] ) && is_string( $operation['model_id'] ) ? sanitize_key( $operation['model_id'] ) : '';
		$slot_id     = isset( $operation['slot_id'] ) && is_string( $operation['slot_id'] ) ? sanitize_key( $operation['slot_id'] ) : '';
		$resource    = get_post( $resource_id );
		if ( ! $resource instanceof WP_Post || 'page' !== $resource->post_type || ! current_user_can( 'edit_post', $resource_id ) || ! current_user_can( 'upload_files' ) ) {
			return new WP_Error( 'seo_geo_manager_forbidden', 'You cannot roll back this strategic media binding.', array( 'status' => 403 ) );
		}

		$after_id          = isset( $operation['after_attachment_id'] ) ? absint( $operation['after_attachment_id'] ) : 0;
		$after_fingerprint = isset( $operation['after_fingerprint'] ) && is_string( $operation['after_fingerprint'] ) ? $operation['after_fingerprint'] : '';
		if ( self::slot( $resource_id, $model_id, $slot_id ) !== $after_id || '' === $after_fingerprint || ! hash_equals( $after_fingerprint, self::fingerprint( $resource_id, $model_id, $slot_id ) ) ) {
			return new WP_Error( 'seo_geo_manager_theme_media_rollback_stale', 'Strategic media changed after Apply; rollback is blocked.', array( 'status' => 409 ) );
		}

		$before_id          = isset( $operation['before_attachment_id'] ) ? absint( $operation['before_attachment_id'] ) : 0;
		$before_fingerprint = isset( $operation['before_fingerprint'] ) && is_string( $operation['before_fingerprint'] ) ? $operation['before_fingerprint'] : '';
		$state              = array( 'resource' => $resource, 'model_id' => $model_id, 'slot_id' => $slot_id );
		$result             = self::mutate( $state, $before_id );
		if ( is_wp_error( $result ) || self::slot( $resource_id, $model_id, $slot_id ) !== $before_id || '' === $before_fingerprint || ! hash_equals( $before_fingerprint, self::fingerprint( $resource_id, $model_id, $slot_id ) ) ) {
			self::mutate( $state, $after_id );
			return new WP_Error( 'seo_geo_manager_theme_media_rollback_verify_failed', 'Rollback verification failed; Manager attempted to restore the applied state.', array( 'status' => 409 ) );
		}

		$operation['status']               = 'rolled-back';
		$operation['rolled_back_at_gmt']   = gmdate( 'c' );
		$operation['rollback_fingerprint'] = $before_fingerprint;
		OperationStore::save( $operation_id, $operation );

		return $operation;
	}

	/**
	 * @param array<string, mixed> $payload Request payload.
	 * @return array{resource:WP_Post,model_id:string,slot_id:string,before_attachment_id:int,after_attachment_id:int,before_fingerprint:string}|WP_Error
	 */
	private static function prepare( array $payload, bool $for_apply ) {
		$resource_id = isset( $payload['resource_id'] ) ? absint( $payload['resource_id'] ) : 0;
		$model_id    = isset( $payload['model_id'] ) && is_string( $payload['model_id'] ) ? sanitize_key( $payload['model_id'] ) : '';
		$slot_id     = isset( $payload['slot_id'] ) && is_string( $payload['slot_id'] ) ? sanitize_key( $payload['slot_id'] ) : '';
		$after_id    = isset( $payload['attachment_id'] ) ? absint( $payload['attachment_id'] ) : 0;
		$expected    = isset( $payload['expected_fingerprint'] ) && is_string( $payload['expected_fingerprint'] ) ? trim( $payload['expected_fingerprint'] ) : '';
		if ( 1 > $resource_id || '' === $model_id || '' === $slot_id || '' === $expected || ! self::write_supported() ) {
			return new WP_Error( 'seo_geo_manager_theme_media_request_invalid', 'A supported Theme media request requires resource_id, model_id, slot_id and expected_fingerprint.', array( 'status' => 400 ) );
		}

		$inspection = ThemeStrategicMediaReader::read( $resource_id, $model_id, $slot_id );
		if ( is_wp_error( $inspection ) ) {
			return $inspection;
		}
		$binding     = isset( $inspection['binding'] ) && is_array( $inspection['binding'] ) ? $inspection['binding'] : array();
		$before_id   = isset( $binding['attachment_id'] ) ? absint( $binding['attachment_id'] ) : 0;
		$fingerprint = isset( $binding['fingerprint'] ) && is_string( $binding['fingerprint'] ) ? $binding['fingerprint'] : '';
		if ( '' === $fingerprint || ! hash_equals( $expected, $fingerprint ) ) {
			return new WP_Error( 'seo_geo_manager_theme_media_fingerprint_mismatch', 'Strategic media changed after inspection.', array( 'status' => 409 ) );
		}
		if ( $before_id === $after_id ) {
			return new WP_Error( 'seo_geo_manager_theme_media_no_changes', 'The requested strategic media state is already active.', array( 'status' => 400 ) );
		}

		$resource = get_post( $resource_id );
		if ( ! $resource instanceof WP_Post || 'page' !== $resource->post_type ) {
			return new WP_Error( 'seo_geo_manager_theme_media_resource_not_found', 'The requested strategic Theme page was not found.', array( 'status' => 404 ) );
		}
		if ( $for_apply && 'publish' === $resource->post_status && true !== ( $payload['allow_published_target'] ?? false ) ) {
			return new WP_Error( 'seo_geo_manager_theme_media_public_approval_required', 'Published page writes require explicit approval after Preview.', array( 'status' => 409 ) );
		}
		if ( 0 < $after_id ) {
			$attachment = get_post( $after_id );
			if ( ! $attachment instanceof WP_Post || 'attachment' !== $attachment->post_type || ! str_starts_with( strtolower( (string) $attachment->post_mime_type ), 'image/' ) || ! current_user_can( 'edit_post', $after_id ) ) {
				return new WP_Error( 'seo_geo_manager_theme_media_image_not_found', 'The selected image attachment is unavailable.', array( 'status' => 404 ) );
			}
		}

		return array( 'resource' => $resource, 'model_id' => $model_id, 'slot_id' => $slot_id, 'before_attachment_id' => $before_id, 'after_attachment_id' => $after_id, 'before_fingerprint' => $fingerprint );
	}

	private static function write_supported(): bool {
		return ThemeStrategicMediaReader::theme_read_supported() && function_exists( 'seo_geo_theme_set_strategic_media_slot' ) && function_exists( 'seo_geo_theme_remove_strategic_media_slot' );
	}

	/**
	 * @param array<string, mixed> $state Prepared state.
	 * @return true|WP_Error
	 */
	private static function mutate( array $state, int $attachment_id ) {
		$resource = $state['resource'] ?? null;
		$model_id = isset( $state['model_id'] ) && is_string( $state['model_id'] ) ? $state['model_id'] : '';
		$slot_id  = isset( $state['slot_id'] ) && is_string( $state['slot_id'] ) ? $state['slot_id'] : '';
		if ( ! $resource instanceof WP_Post ) {
			return new WP_Error( 'seo_geo_manager_theme_media_resource_not_found', 'Strategic Theme page unavailable.', array( 'status' => 404 ) );
		}
		if ( 0 < $attachment_id ) {
			/** @var callable(int, string, string, int):true|WP_Error $callback */
			$callback = 'seo_geo_theme_set_strategic_media_slot';
			return $callback( (int) $resource->ID, $model_id, $slot_id, $attachment_id );
		}
		/** @var callable(int, string, string):true|WP_Error $callback */
		$callback = 'seo_geo_theme_remove_strategic_media_slot';
		return $callback( (int) $resource->ID, $model_id, $slot_id );
	}

	private static function slot( int $resource_id, string $model_id, string $slot_id ): int {
		/** @var callable(int, string, string):int $callback */
		$callback = 'seo_geo_theme_strategic_media_slot';
		return $callback( $resource_id, $model_id, $slot_id );
	}

	private static function fingerprint( int $resource_id, string $model_id, string $slot_id ): string {
		/** @var callable(int, string, string):string $callback */
		$callback = 'seo_geo_theme_strategic_media_fingerprint';
		return $callback( $resource_id, $model_id, $slot_id );
	}

	/**
	 * @param array<string, mixed> $payload Request payload.
	 */
	private static function payload_hash( array $payload ): string {
		return hash( 'sha256', (string) wp_json_encode( array( 'resource_id' => isset( $payload['resource_id'] ) ? absint( $payload['resource_id'] ) : 0, 'model_id' => isset( $payload['model_id'] ) ? sanitize_key( (string) $payload['model_id'] ) : '', 'slot_id' => isset( $payload['slot_id'] ) ? sanitize_key( (string) $payload['slot_id'] ) : '', 'attachment_id' => isset( $payload['attachment_id'] ) ? absint( $payload['attachment_id'] ) : 0, 'expected_fingerprint' => isset( $payload['expected_fingerprint'] ) ? (string) $payload['expected_fingerprint'] : '', 'allow_published_target' => true === ( $payload['allow_published_target'] ?? false ) ) ) );
	}

	/** @return array<string, mixed>|WP_Error */
	private static function replay( string $operation_id ) {
		$operation = OperationStore::get( $operation_id );
		if ( ! is_array( $operation ) || self::ADAPTER !== ( $operation['adapter'] ?? '' ) || 'failed' === ( $operation['status'] ?? '' ) ) {
			return new WP_Error( 'seo_geo_manager_theme_media_idempotency_unavailable', 'Idempotency key is unavailable for replay.', array( 'status' => 409 ) );
		}
		$operation['idempotent_replay'] = true;
		return $operation;
	}
}
