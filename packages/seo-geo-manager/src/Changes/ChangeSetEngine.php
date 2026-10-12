<?php
/**
 * Controlled content change-set engine.
 *
 * @package SeoGeoManager
 */

declare(strict_types=1);

namespace SeoGeo\Manager\Changes;

use SeoGeo\Manager\Support\ContentFingerprint;
use WP_Error;
use WP_Post;

final class ChangeSetEngine {
	private const SCHEMA_VERSION = 1;

	/**
	 * Preview a bounded content mutation without writing WordPress state.
	 *
	 * @param array<string, mixed> $payload Request payload.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function preview( array $payload ) {
		$normalized = self::normalize_payload( $payload, false );
		if ( is_wp_error( $normalized ) ) {
			return $normalized;
		}

		$post = self::target_post( $normalized );
		if ( is_wp_error( $post ) ) {
			return $post;
		}

		$guard = self::guard_target( $post, $normalized, false );
		if ( is_wp_error( $guard ) ) {
			return $guard;
		}

		$diff = self::diff( $post, $normalized['changes'] );

		return array(
			'schema_version' => self::SCHEMA_VERSION,
			'mode'           => 'preview',
			'target'         => self::target_summary( $post ),
			'diff'           => $diff,
			'has_changes'    => array() !== $diff,
			'policy'         => array(
				'draft_first'              => true,
				'published_target_blocked' => 'publish' === $post->post_status && ! $normalized['allow_published_target'],
				'allow_published_target'   => $normalized['allow_published_target'],
			),
		);
	}

	/**
	 * Apply one validated change set with idempotency and optimistic concurrency.
	 *
	 * @param array<string, mixed> $payload Request payload.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function apply( array $payload ) {
		$normalized = self::normalize_payload( $payload, true );
		if ( is_wp_error( $normalized ) ) {
			return $normalized;
		}

		$payload_hash = self::payload_hash( $normalized );
		$lookup       = OperationStore::lookup( $normalized['idempotency_key'], $payload_hash );
		if ( is_wp_error( $lookup ) ) {
			return $lookup;
		}
		if ( $lookup['existing'] ) {
			return self::idempotent_operation( $lookup['operation_id'] );
		}

		$post = self::target_post( $normalized );
		if ( is_wp_error( $post ) ) {
			return $post;
		}

		$guard = self::guard_target( $post, $normalized, true );
		if ( is_wp_error( $guard ) ) {
			return $guard;
		}

		$diff = self::diff( $post, $normalized['changes'] );
		if ( array() === $diff ) {
			return new WP_Error(
				'seo_geo_manager_no_changes',
				'The proposed change set does not change the target resource.',
				array( 'status' => 400 )
			);
		}

		$operation_id = wp_generate_uuid4();
		$reservation  = OperationStore::reserve(
			$normalized['idempotency_key'],
			$payload_hash,
			$operation_id
		);
		if ( is_wp_error( $reservation ) ) {
			return $reservation;
		}

		if ( $reservation['existing'] ) {
			return self::idempotent_operation( $reservation['operation_id'] );
		}

		$previous = self::previous_values( $post, array_keys( $normalized['changes'] ) );
		$revision = wp_save_post_revision( $post->ID );
		$update   = self::update_args( $post->ID, $normalized['changes'] );
		$result   = wp_update_post( $update, true );

		if ( is_wp_error( $result ) ) {
			$failed = array(
				'operation_id'    => $operation_id,
				'status'          => 'failed',
				'target_id'       => (int) $post->ID,
				'payload_hash'    => $payload_hash,
				'idempotency_key' => $normalized['idempotency_key'],
				'created_at_gmt'  => gmdate( 'c' ),
				'error'           => $result->get_error_message(),
			);
			OperationStore::save( $operation_id, $failed );

			return $result;
		}

		$after = get_post( $post->ID );
		if ( ! $after instanceof WP_Post ) {
			return new WP_Error(
				'seo_geo_manager_post_apply_missing',
				'The target resource could not be reloaded after applying the change set.',
				array( 'status' => 500 )
			);
		}

		$verification = self::verify_changes( $after, $normalized['changes'] );
		$operation    = array(
			'operation_id'         => $operation_id,
			'schema_version'       => self::SCHEMA_VERSION,
			'status'               => array() === $verification ? 'applied' : 'verification-failed',
			'target_id'            => (int) $post->ID,
			'target_type'          => (string) $post->post_type,
			'idempotency_key'      => $normalized['idempotency_key'],
			'payload_hash'         => $payload_hash,
			'expected_fingerprint' => $normalized['expected_fingerprint'],
			'before_fingerprint'   => ContentFingerprint::for_post( $post ),
			'after_fingerprint'    => ContentFingerprint::for_post( $after ),
			'before'               => $previous,
			'changes'              => $normalized['changes'],
			'diff'                 => $diff,
			'revision_id'          => is_numeric( $revision ) ? (int) $revision : 0,
			'verification'         => $verification,
			'created_at_gmt'       => gmdate( 'c' ),
			'idempotent_replay'    => false,
		);

		if ( ! OperationStore::save( $operation_id, $operation ) ) {
			return new WP_Error(
				'seo_geo_manager_operation_log_failed',
				'The content changed, but the Manager operation log could not be persisted.',
				array(
					'status'       => 500,
					'operation_id' => $operation_id,
				)
			);
		}

		if ( array() !== $verification ) {
			return new WP_Error(
				'seo_geo_manager_verification_failed',
				'The target changed, but post-apply verification did not match the requested values.',
				array(
					'status'       => 409,
					'operation_id' => $operation_id,
					'mismatches'   => $verification,
				)
			);
		}

		return $operation;
	}

	/**
	 * Roll back only fields changed by one Manager operation.
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	public static function rollback( string $operation_id ) {
		$operation = OperationStore::get( $operation_id );
		if ( ! is_array( $operation ) ) {
			return new WP_Error(
				'seo_geo_manager_operation_not_found',
				'Manager operation not found.',
				array( 'status' => 404 )
			);
		}

		if ( 'rolled-back' === ( $operation['status'] ?? '' ) ) {
			return $operation;
		}

		$target_id = isset( $operation['target_id'] ) ? (int) $operation['target_id'] : 0;
		$post      = get_post( $target_id );
		if ( ! $post instanceof WP_Post ) {
			return new WP_Error(
				'seo_geo_manager_rollback_target_missing',
				'The rollback target no longer exists.',
				array( 'status' => 404 )
			);
		}

		if ( ! current_user_can( 'edit_post', $post->ID ) ) {
			return new WP_Error(
				'seo_geo_manager_forbidden',
				'You cannot roll back this content resource.',
				array( 'status' => 403 )
			);
		}

		$expected_after = isset( $operation['after_fingerprint'] ) && is_string( $operation['after_fingerprint'] ) ? $operation['after_fingerprint'] : '';
		$current        = ContentFingerprint::for_post( $post );
		if ( '' === $expected_after || ! hash_equals( $expected_after, $current ) ) {
			return new WP_Error(
				'seo_geo_manager_rollback_stale',
				'The resource changed after the Manager operation; rollback is blocked to protect newer edits.',
				array(
					'status'              => 409,
					'current_fingerprint' => $current,
				)
			);
		}

		$before = isset( $operation['before'] ) && is_array( $operation['before'] ) ? $operation['before'] : array();
		if ( array() === $before ) {
			return new WP_Error(
				'seo_geo_manager_rollback_unavailable',
				'This operation has no bounded rollback values.',
				array( 'status' => 409 )
			);
		}

		wp_save_post_revision( $post->ID );
		$result = wp_update_post( self::update_args( $post->ID, $before ), true );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$restored = get_post( $post->ID );
		if ( ! $restored instanceof WP_Post ) {
			return new WP_Error(
				'seo_geo_manager_rollback_reload_failed',
				'The resource could not be reloaded after rollback.',
				array( 'status' => 500 )
			);
		}

		$operation['status']               = 'rolled-back';
		$operation['rolled_back_at_gmt']   = gmdate( 'c' );
		$operation['rollback_fingerprint'] = ContentFingerprint::for_post( $restored );
		OperationStore::save( $operation_id, $operation );

		return $operation;
	}

	/**
	 * @param array<string, mixed> $payload Payload.
	 * @return array<string, mixed>|WP_Error
	 */
	private static function normalize_payload( array $payload, bool $require_idempotency ) {
		$schema_version = isset( $payload['schema_version'] ) ? (int) $payload['schema_version'] : self::SCHEMA_VERSION;
		if ( self::SCHEMA_VERSION !== $schema_version ) {
			return new WP_Error(
				'seo_geo_manager_change_schema_unsupported',
				'Unsupported change-set schema version.',
				array( 'status' => 400 )
			);
		}

		$target      = isset( $payload['target'] ) && is_array( $payload['target'] ) ? $payload['target'] : array();
		$id          = isset( $target['id'] ) ? absint( $target['id'] ) : 0;
		$fingerprint = isset( $target['expected_fingerprint'] ) && is_string( $target['expected_fingerprint'] ) ? trim( $target['expected_fingerprint'] ) : '';
		if ( 1 > $id || '' === $fingerprint ) {
			return new WP_Error(
				'seo_geo_manager_change_target_invalid',
				'Target ID and expected fingerprint are required.',
				array( 'status' => 400 )
			);
		}

		$raw_changes = isset( $payload['changes'] ) && is_array( $payload['changes'] ) ? $payload['changes'] : array();
		$changes     = self::normalize_changes( $raw_changes );
		if ( is_wp_error( $changes ) ) {
			return $changes;
		}
		if ( array() === $changes ) {
			return new WP_Error(
				'seo_geo_manager_change_empty',
				'At least one supported field change is required.',
				array( 'status' => 400 )
			);
		}

		$idempotency_key = isset( $payload['idempotency_key'] ) && is_string( $payload['idempotency_key'] ) ? trim( $payload['idempotency_key'] ) : '';
		if ( $require_idempotency && ( '' === $idempotency_key || 128 < strlen( $idempotency_key ) ) ) {
			return new WP_Error(
				'seo_geo_manager_idempotency_required',
				'Apply requests require an idempotency key of at most 128 characters.',
				array( 'status' => 400 )
			);
		}

		return array(
			'schema_version'         => self::SCHEMA_VERSION,
			'target_id'              => $id,
			'expected_fingerprint'   => $fingerprint,
			'idempotency_key'        => $idempotency_key,
			'allow_published_target' => true === ( $payload['allow_published_target'] ?? false ),
			'allow_empty_content'    => true === ( $payload['allow_empty_content'] ?? false ),
			'changes'                => $changes,
		);
	}

	/**
	 * @param array<string, mixed> $changes Raw changes.
	 * @return array<string, string>|WP_Error
	 */
	private static function normalize_changes( array $changes ) {
		$normalized = array();
		$allowed    = array( 'title', 'slug', 'excerpt', 'content', 'status' );

		foreach ( $changes as $field => $value ) {
			if ( ! is_string( $field ) || ! in_array( $field, $allowed, true ) ) {
				return new WP_Error(
					'seo_geo_manager_change_field_unsupported',
					'The change set contains an unsupported field.',
					array( 'status' => 400 )
				);
			}
			if ( ! is_string( $value ) ) {
				return new WP_Error(
					'seo_geo_manager_change_value_invalid',
					'Change-set field values must be strings.',
					array( 'status' => 400 )
				);
			}

			if ( 'title' === $field ) {
				$value = sanitize_text_field( $value );
				if ( '' === $value ) {
					return new WP_Error( 'seo_geo_manager_title_empty', 'Title cannot be empty.', array( 'status' => 400 ) );
				}
			} elseif ( 'slug' === $field ) {
				$value = sanitize_title( $value );
				if ( '' === $value ) {
					return new WP_Error( 'seo_geo_manager_slug_empty', 'Slug cannot be empty.', array( 'status' => 400 ) );
				}
			} elseif ( 'excerpt' === $field || 'content' === $field ) {
				$value = wp_kses_post( $value );
			} elseif ( 'status' === $field ) {
				$value = sanitize_key( $value );
				if ( ! in_array( $value, array( 'draft', 'pending' ), true ) ) {
					return new WP_Error(
						'seo_geo_manager_status_blocked',
						'M2 baseline only allows draft or pending status writes.',
						array( 'status' => 400 )
					);
				}
			}

			$normalized[ $field ] = $value;
		}

		return $normalized;
	}

	/**
	 * @param array<string, mixed> $normalized Normalized payload.
	 * @return WP_Post|WP_Error
	 */
	private static function target_post( array $normalized ) {
		$post = get_post( (int) $normalized['target_id'] );
		if ( ! $post instanceof WP_Post || ! in_array( $post->post_type, array( 'page', 'post' ), true ) ) {
			return new WP_Error(
				'seo_geo_manager_change_target_missing',
				'Change-set target page/post was not found.',
				array( 'status' => 404 )
			);
		}

		if ( ! current_user_can( 'edit_post', $post->ID ) ) {
			return new WP_Error(
				'seo_geo_manager_forbidden',
				'You cannot modify this content resource.',
				array( 'status' => 403 )
			);
		}

		return $post;
	}

	/**
	 * @param array<string, mixed> $normalized Normalized payload.
	 * @return true|WP_Error
	 */
	private static function guard_target( WP_Post $post, array $normalized, bool $for_apply ) {
		$current = ContentFingerprint::for_post( $post );
		if ( ! hash_equals( (string) $normalized['expected_fingerprint'], $current ) ) {
			return new WP_Error(
				'seo_geo_manager_change_stale',
				'The resource changed after it was inspected. Re-read it before preparing another change set.',
				array(
					'status'              => 409,
					'current_fingerprint' => $current,
				)
			);
		}

		$changes = $normalized['changes'];
		if ( isset( $changes['slug'] ) && $changes['slug'] !== $post->post_name ) {
			$collision = self::slug_collision( (string) $changes['slug'], $post );
			if ( $collision instanceof WP_Post ) {
				return new WP_Error(
					'seo_geo_manager_slug_collision',
					'The requested slug is already used by another resource.',
					array(
						'status'       => 409,
						'conflict_id'  => (int) $collision->ID,
						'conflict_url' => get_permalink( $collision ),
					)
				);
			}
		}

		if ( isset( $changes['content'] ) && '' === trim( (string) $changes['content'] ) && '' !== trim( (string) $post->post_content ) && ! $normalized['allow_empty_content'] ) {
			return new WP_Error(
				'seo_geo_manager_empty_content_blocked',
				'Clearing non-empty content requires allow_empty_content=true.',
				array( 'status' => 409 )
			);
		}

		if ( $for_apply && 'publish' === $post->post_status && ! $normalized['allow_published_target'] ) {
			return new WP_Error(
				'seo_geo_manager_published_target_blocked',
				'Published resources are read-only by default. Explicitly allow the published target after preview/approval.',
				array( 'status' => 409 )
			);
		}

		if ( $for_apply && 'publish' === $post->post_status && $normalized['allow_published_target'] && ! self::can_publish_type( $post ) ) {
			return new WP_Error(
				'seo_geo_manager_publish_capability_required',
				'Applying to an already published target requires the post type publish capability.',
				array( 'status' => 403 )
			);
		}

		return true;
	}

	private static function can_publish_type( WP_Post $post ): bool {
		$object = get_post_type_object( $post->post_type );
		if ( ! is_object( $object ) || ! isset( $object->cap ) || ! is_object( $object->cap ) ) {
			return false;
		}
		$capability = isset( $object->cap->publish_posts ) && is_string( $object->cap->publish_posts ) ? $object->cap->publish_posts : '';

		return '' !== $capability && current_user_can( $capability );
	}

	private static function slug_collision( string $slug, WP_Post $post ): ?WP_Post {
		$matches = get_posts(
			array(
				'name'           => $slug,
				'post_type'      => $post->post_type,
				'post_status'    => array( 'publish', 'future', 'draft', 'pending', 'private' ),
				'posts_per_page' => 3,
				'exclude'        => array( $post->ID ),
			)
		);
		foreach ( $matches as $match ) {
			if ( $match instanceof WP_Post && $match->ID !== $post->ID ) {
				return $match;
			}
		}

		return null;
	}

	/**
	 * @param array<string, string> $changes Changes.
	 * @return array<string, array<string, string>>
	 */
	private static function diff( WP_Post $post, array $changes ): array {
		$diff = array();
		foreach ( $changes as $field => $value ) {
			$current = self::field_value( $post, $field );
			if ( $current === $value ) {
				continue;
			}
			$diff[ $field ] = array(
				'from' => $current,
				'to'   => $value,
			);
		}

		return $diff;
	}

	/**
	 * Return one previously stored operation as an idempotent replay.
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	private static function idempotent_operation( string $operation_id ) {
		$existing = OperationStore::get( $operation_id );
		if ( is_array( $existing ) ) {
			$existing['idempotent_replay'] = true;

			return $existing;
		}

		return new WP_Error(
			'seo_geo_manager_idempotent_operation_missing',
			'The idempotency key is reserved but its operation record is unavailable.',
			array( 'status' => 409 )
		);
	}

	/**
	 * @param list<string> $fields Fields.
	 * @return array<string, string>
	 */
	private static function previous_values( WP_Post $post, array $fields ): array {
		$result = array();
		foreach ( $fields as $field ) {
			$result[ $field ] = self::field_value( $post, $field );
		}

		return $result;
	}

	private static function field_value( WP_Post $post, string $field ): string {
		$map = array(
			'title'   => (string) $post->post_title,
			'slug'    => (string) $post->post_name,
			'excerpt' => (string) $post->post_excerpt,
			'content' => (string) $post->post_content,
			'status'  => (string) $post->post_status,
		);

		return $map[ $field ] ?? '';
	}

	/**
	 * @param array<string, string> $changes Changes.
	 * @return array<string, mixed>
	 */
	private static function update_args( int $post_id, array $changes ): array {
		$args = array( 'ID' => $post_id );
		$map  = array(
			'title'   => 'post_title',
			'slug'    => 'post_name',
			'excerpt' => 'post_excerpt',
			'content' => 'post_content',
			'status'  => 'post_status',
		);
		foreach ( $changes as $field => $value ) {
			if ( isset( $map[ $field ] ) ) {
				$args[ $map[ $field ] ] = $value;
			}
		}

		return $args;
	}

	/**
	 * @param array<string, string> $changes Changes.
	 * @return array<string, array<string, string>>
	 */
	private static function verify_changes( WP_Post $post, array $changes ): array {
		$mismatches = array();
		foreach ( $changes as $field => $expected ) {
			$actual = self::field_value( $post, $field );
			if ( $actual !== $expected ) {
				$mismatches[ $field ] = array(
					'expected' => $expected,
					'actual'   => $actual,
				);
			}
		}

		return $mismatches;
	}

	/**
	 * @param array<string, mixed> $normalized Normalized payload.
	 */
	private static function payload_hash( array $normalized ): string {
		$encoded = wp_json_encode( $normalized );

		return hash( 'sha256', is_string( $encoded ) ? $encoded : '' );
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function target_summary( WP_Post $post ): array {
		$permalink = get_permalink( $post );

		return array(
			'id'          => (int) $post->ID,
			'type'        => (string) $post->post_type,
			'status'      => (string) $post->post_status,
			'slug'        => (string) $post->post_name,
			'title'       => html_entity_decode( get_the_title( $post ), ENT_QUOTES | ENT_HTML5, get_bloginfo( 'charset' ) ),
			'permalink'   => is_string( $permalink ) ? $permalink : '',
			'fingerprint' => ContentFingerprint::for_post( $post ),
		);
	}
}
