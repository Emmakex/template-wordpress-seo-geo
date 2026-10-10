<?php
/**
 * Generic post/page creation and publication lifecycle operations.
 *
 * @package SeoGeoManager
 */

declare(strict_types=1);

namespace SeoGeo\Manager\Changes;

use DateTimeImmutable;
use DateTimeZone;
use Exception;
use SeoGeo\Manager\Support\ContentFingerprint;
use WP_Error;
use WP_Post;
use WP_Post_Type;

final class ContentLifecycleEngine {
	private const SCHEMA_VERSION      = 1;
	private const MAX_TITLE_BYTES     = 200;
	private const MAX_SLUG_BYTES      = 200;
	private const MAX_EXCERPT_BYTES   = 10000;
	private const MAX_CONTENT_BYTES   = 500000;
	private const MAX_IDEMPOTENCY_LEN = 128;

	/**
	 * Preview creation of one normal WordPress post/page without writing state.
	 *
	 * @param array<string, mixed> $payload Payload.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function preview_create( array $payload ) {
		$normalized = self::normalize_create_payload( $payload, false );
		if ( is_wp_error( $normalized ) ) {
			return $normalized;
		}

		$guard = self::guard_create( $normalized );
		if ( is_wp_error( $guard ) ) {
			return $guard;
		}

		return array(
			'schema_version' => self::SCHEMA_VERSION,
			'mode'           => 'preview',
			'operation_type' => 'content-create',
			'resource'       => self::public_create_plan( $normalized ),
			'will_create'    => true,
			'policy'         => array(
				'post_types'              => array( 'post', 'page' ),
				'draft_first_supported'    => true,
				'publish_capability_gate'  => true,
				'schedule_capability_gate' => true,
				'slug_collision_blocked'   => true,
				'idempotent_apply'         => true,
			),
		);
	}

	/**
	 * Create one normal WordPress post/page with idempotency and capability gates.
	 *
	 * @param array<string, mixed> $payload Payload.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function create( array $payload ) {
		$normalized = self::normalize_create_payload( $payload, true );
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

		$guard = self::guard_create( $normalized );
		if ( is_wp_error( $guard ) ) {
			return $guard;
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

		$insert = array(
			'post_type'    => $normalized['type'],
			'post_title'   => $normalized['title'],
			'post_name'    => $normalized['slug'],
			'post_excerpt' => $normalized['excerpt'],
			'post_content' => $normalized['content'],
			'post_status'  => $normalized['status'],
			'post_author'  => get_current_user_id(),
		);

		if ( 'future' === $normalized['status'] ) {
			$insert['post_date_gmt'] = $normalized['scheduled_mysql_gmt'];
			$insert['post_date']     = get_date_from_gmt( $normalized['scheduled_mysql_gmt'] );
		}

		$result = wp_insert_post( $insert, true );
		if ( is_wp_error( $result ) ) {
			self::save_failed_operation(
				$operation_id,
				'content-create',
				$payload_hash,
				$normalized['idempotency_key'],
				$result
			);

			return $result;
		}

		$post = get_post( (int) $result );
		if ( ! $post instanceof WP_Post ) {
			return self::error(
				'seo_geo_manager_create_reload_failed',
				'The resource was created but could not be reloaded.',
				500
			);
		}

		$verification = self::verify_created_post( $post, $normalized );
		$operation    = array(
			'operation_id'      => $operation_id,
			'operation_type'    => 'content-create',
			'schema_version'    => self::SCHEMA_VERSION,
			'status'            => array() === $verification ? 'created' : 'verification-failed',
			'target_id'         => (int) $post->ID,
			'target_type'       => (string) $post->post_type,
			'idempotency_key'   => $normalized['idempotency_key'],
			'payload_hash'      => $payload_hash,
			'changes'           => self::public_create_plan( $normalized ),
			'after_fingerprint' => ContentFingerprint::for_post( $post ),
			'permalink'         => (string) get_permalink( $post ),
			'verification'      => $verification,
			'created_at_gmt'    => gmdate( 'c' ),
			'idempotent_replay' => false,
		);

		if ( ! OperationStore::save( $operation_id, $operation ) ) {
			return new WP_Error(
				'seo_geo_manager_operation_log_failed',
				'The resource was created, but the Manager operation log could not be persisted.',
				array(
					'status'       => 500,
					'operation_id' => $operation_id,
					'target_id'    => (int) $post->ID,
				)
			);
		}

		if ( array() !== $verification ) {
			return new WP_Error(
				'seo_geo_manager_create_verification_failed',
				'The resource was created, but verification did not match the requested values.',
				array(
					'status'       => 409,
					'operation_id' => $operation_id,
					'target_id'    => (int) $post->ID,
					'mismatches'   => $verification,
				)
			);
		}

		return $operation;
	}

	/**
	 * Preview publish/schedule transition for an existing post/page.
	 *
	 * @param array<string, mixed> $payload Payload.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function preview_publication( array $payload ) {
		$normalized = self::normalize_publication_payload( $payload, false );
		if ( is_wp_error( $normalized ) ) {
			return $normalized;
		}

		$post = self::publication_target( $normalized );
		if ( is_wp_error( $post ) ) {
			return $post;
		}

		$guard = self::guard_publication( $post, $normalized );
		if ( is_wp_error( $guard ) ) {
			return $guard;
		}

		return array(
			'schema_version' => self::SCHEMA_VERSION,
			'mode'           => 'preview',
			'operation_type' => 'content-publication',
			'target'         => self::target_summary( $post ),
			'transition'     => array(
				'from_status'      => (string) $post->post_status,
				'to_status'        => $normalized['status'],
				'scheduled_at_gmt' => $normalized['scheduled_at_gmt'],
			),
			'has_changes'    => self::publication_has_changes( $post, $normalized ),
			'policy'         => array(
				'expected_fingerprint_required' => true,
				'publish_capability_required'   => true,
				'idempotent_apply'              => true,
			),
		);
	}

	/**
	 * Publish now or schedule an existing post/page.
	 *
	 * @param array<string, mixed> $payload Payload.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function apply_publication( array $payload ) {
		$normalized = self::normalize_publication_payload( $payload, true );
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

		$post = self::publication_target( $normalized );
		if ( is_wp_error( $post ) ) {
			return $post;
		}

		$guard = self::guard_publication( $post, $normalized );
		if ( is_wp_error( $guard ) ) {
			return $guard;
		}
		if ( ! self::publication_has_changes( $post, $normalized ) ) {
			return self::error(
				'seo_geo_manager_publication_no_changes',
				'The requested publication state already matches the target resource.',
				400
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

		$before = array(
			'status'        => (string) $post->post_status,
			'post_date'     => (string) $post->post_date,
			'post_date_gmt' => (string) $post->post_date_gmt,
		);
		wp_save_post_revision( $post->ID );

		$update = array(
			'ID'          => (int) $post->ID,
			'post_status' => $normalized['status'],
		);
		if ( 'future' === $normalized['status'] ) {
			$update['post_date_gmt'] = $normalized['scheduled_mysql_gmt'];
			$update['post_date']     = get_date_from_gmt( $normalized['scheduled_mysql_gmt'] );
		} else {
			$now_gmt                 = gmdate( 'Y-m-d H:i:s' );
			$update['post_date_gmt'] = $now_gmt;
			$update['post_date']     = get_date_from_gmt( $now_gmt );
		}

		$result = wp_update_post( $update, true );
		if ( is_wp_error( $result ) ) {
			self::save_failed_operation(
				$operation_id,
				'content-publication',
				$payload_hash,
				$normalized['idempotency_key'],
				$result
			);

			return $result;
		}

		$after = get_post( $post->ID );
		if ( ! $after instanceof WP_Post ) {
			return self::error(
				'seo_geo_manager_publication_reload_failed',
				'The publication transition completed but the resource could not be reloaded.',
				500
			);
		}

		$verification = self::verify_publication( $after, $normalized );
		$status       = 'future' === $normalized['status'] ? 'scheduled' : 'published';
		if ( array() !== $verification ) {
			$status = 'verification-failed';
		}

		$operation = array(
			'operation_id'         => $operation_id,
			'operation_type'       => 'content-publication',
			'schema_version'       => self::SCHEMA_VERSION,
			'status'               => $status,
			'target_id'            => (int) $after->ID,
			'target_type'          => (string) $after->post_type,
			'idempotency_key'      => $normalized['idempotency_key'],
			'payload_hash'         => $payload_hash,
			'expected_fingerprint' => $normalized['expected_fingerprint'],
			'before_fingerprint'   => ContentFingerprint::for_post( $post ),
			'after_fingerprint'    => ContentFingerprint::for_post( $after ),
			'before'               => $before,
			'changes'              => array(
				'status'           => $normalized['status'],
				'scheduled_at_gmt' => $normalized['scheduled_at_gmt'],
			),
			'permalink'            => (string) get_permalink( $after ),
			'verification'         => $verification,
			'created_at_gmt'       => gmdate( 'c' ),
			'idempotent_replay'    => false,
		);

		if ( ! OperationStore::save( $operation_id, $operation ) ) {
			return new WP_Error(
				'seo_geo_manager_operation_log_failed',
				'The publication state changed, but the Manager operation log could not be persisted.',
				array(
					'status'       => 500,
					'operation_id' => $operation_id,
					'target_id'    => (int) $after->ID,
				)
			);
		}

		if ( array() !== $verification ) {
			return new WP_Error(
				'seo_geo_manager_publication_verification_failed',
				'The publication state changed, but verification did not match the requested state.',
				array(
					'status'       => 409,
					'operation_id' => $operation_id,
					'target_id'    => (int) $after->ID,
					'mismatches'   => $verification,
				)
			);
		}

		return $operation;
	}

	/**
	 * @param array<string, mixed> $payload Payload.
	 * @return array<string, mixed>|WP_Error
	 */
	private static function normalize_create_payload( array $payload, bool $require_idempotency ) {
		$schema = isset( $payload['schema_version'] ) ? (int) $payload['schema_version'] : self::SCHEMA_VERSION;
		if ( self::SCHEMA_VERSION !== $schema ) {
			return self::error( 'seo_geo_manager_create_schema_unsupported', 'Unsupported creation schema version.', 400 );
		}

		$type = isset( $payload['type'] ) && is_string( $payload['type'] ) ? sanitize_key( $payload['type'] ) : 'post';
		if ( ! in_array( $type, array( 'post', 'page' ), true ) ) {
			return self::error( 'seo_geo_manager_create_type_unsupported', 'Only normal WordPress posts and pages may be created by this operation.', 400 );
		}

		$title = isset( $payload['title'] ) && is_string( $payload['title'] ) ? sanitize_text_field( $payload['title'] ) : '';
		if ( '' === $title || self::MAX_TITLE_BYTES < strlen( $title ) ) {
			return self::error( 'seo_geo_manager_create_title_invalid', 'A non-empty title of at most 200 bytes is required.', 400 );
		}

		$slug = isset( $payload['slug'] ) && is_string( $payload['slug'] ) ? sanitize_title( $payload['slug'] ) : '';
		if ( self::MAX_SLUG_BYTES < strlen( $slug ) ) {
			return self::error( 'seo_geo_manager_create_slug_invalid', 'Slug exceeds the supported size.', 400 );
		}

		$excerpt = isset( $payload['excerpt'] ) && is_string( $payload['excerpt'] ) ? wp_kses_post( $payload['excerpt'] ) : '';
		$content = isset( $payload['content'] ) && is_string( $payload['content'] ) ? wp_kses_post( $payload['content'] ) : '';
		if ( self::MAX_EXCERPT_BYTES < strlen( $excerpt ) || self::MAX_CONTENT_BYTES < strlen( $content ) ) {
			return self::error( 'seo_geo_manager_create_content_too_large', 'Excerpt or content exceeds the bounded Manager payload size.', 413 );
		}

		$status = isset( $payload['status'] ) && is_string( $payload['status'] ) ? sanitize_key( $payload['status'] ) : 'draft';
		if ( ! in_array( $status, array( 'draft', 'pending', 'publish', 'future' ), true ) ) {
			return self::error( 'seo_geo_manager_create_status_unsupported', 'Unsupported creation status.', 400 );
		}

		$schedule = self::normalize_schedule( $status, $payload['scheduled_at_gmt'] ?? '' );
		if ( is_wp_error( $schedule ) ) {
			return $schedule;
		}

		$idempotency_key = isset( $payload['idempotency_key'] ) && is_string( $payload['idempotency_key'] ) ? trim( $payload['idempotency_key'] ) : '';
		if ( $require_idempotency && ( '' === $idempotency_key || self::MAX_IDEMPOTENCY_LEN < strlen( $idempotency_key ) ) ) {
			return self::error( 'seo_geo_manager_idempotency_required', 'Apply requests require an idempotency key of at most 128 characters.', 400 );
		}

		return array(
			'schema_version'      => self::SCHEMA_VERSION,
			'type'                => $type,
			'title'               => $title,
			'slug'                => $slug,
			'excerpt'             => $excerpt,
			'content'             => $content,
			'status'              => $status,
			'scheduled_at_gmt'    => $schedule['iso'],
			'scheduled_mysql_gmt' => $schedule['mysql'],
			'idempotency_key'     => $idempotency_key,
		);
	}

	/**
	 * @param array<string, mixed> $payload Payload.
	 * @return array<string, mixed>|WP_Error
	 */
	private static function normalize_publication_payload( array $payload, bool $require_idempotency ) {
		$schema = isset( $payload['schema_version'] ) ? (int) $payload['schema_version'] : self::SCHEMA_VERSION;
		if ( self::SCHEMA_VERSION !== $schema ) {
			return self::error( 'seo_geo_manager_publication_schema_unsupported', 'Unsupported publication schema version.', 400 );
		}

		$target      = isset( $payload['target'] ) && is_array( $payload['target'] ) ? $payload['target'] : array();
		$target_id   = isset( $target['id'] ) ? absint( $target['id'] ) : 0;
		$fingerprint = isset( $target['expected_fingerprint'] ) && is_string( $target['expected_fingerprint'] ) ? trim( $target['expected_fingerprint'] ) : '';
		if ( 1 > $target_id || '' === $fingerprint ) {
			return self::error( 'seo_geo_manager_publication_target_invalid', 'Target ID and expected fingerprint are required.', 400 );
		}

		$status = isset( $payload['status'] ) && is_string( $payload['status'] ) ? sanitize_key( $payload['status'] ) : '';
		if ( ! in_array( $status, array( 'publish', 'future' ), true ) ) {
			return self::error( 'seo_geo_manager_publication_status_unsupported', 'Publication transition must be publish or future.', 400 );
		}

		$schedule = self::normalize_schedule( $status, $payload['scheduled_at_gmt'] ?? '' );
		if ( is_wp_error( $schedule ) ) {
			return $schedule;
		}

		$idempotency_key = isset( $payload['idempotency_key'] ) && is_string( $payload['idempotency_key'] ) ? trim( $payload['idempotency_key'] ) : '';
		if ( $require_idempotency && ( '' === $idempotency_key || self::MAX_IDEMPOTENCY_LEN < strlen( $idempotency_key ) ) ) {
			return self::error( 'seo_geo_manager_idempotency_required', 'Apply requests require an idempotency key of at most 128 characters.', 400 );
		}

		return array(
			'schema_version'       => self::SCHEMA_VERSION,
			'target_id'            => $target_id,
			'expected_fingerprint' => $fingerprint,
			'status'               => $status,
			'scheduled_at_gmt'     => $schedule['iso'],
			'scheduled_mysql_gmt'  => $schedule['mysql'],
			'idempotency_key'      => $idempotency_key,
		);
	}

	/**
	 * @param mixed $raw Raw scheduled value.
	 * @return array{iso:string,mysql:string}|WP_Error
	 */
	private static function normalize_schedule( string $status, $raw ) {
		$value = is_string( $raw ) ? trim( $raw ) : '';
		if ( 'future' !== $status ) {
			if ( '' !== $value ) {
				return self::error( 'seo_geo_manager_schedule_unexpected', 'scheduled_at_gmt is only valid when status is future.', 400 );
			}

			return array(
				'iso'   => '',
				'mysql' => '',
			);
		}
		if ( '' === $value ) {
			return self::error( 'seo_geo_manager_schedule_required', 'scheduled_at_gmt is required when status is future.', 400 );
		}

		try {
			$date = new DateTimeImmutable( $value );
			$date = $date->setTimezone( new DateTimeZone( 'UTC' ) );
		} catch ( Exception $exception ) {
			unset( $exception );

			return self::error( 'seo_geo_manager_schedule_invalid', 'scheduled_at_gmt must be a valid ISO-8601 date/time.', 400 );
		}

		if ( $date->getTimestamp() <= time() + 60 ) {
			return self::error( 'seo_geo_manager_schedule_not_future', 'Scheduled publication must be more than 60 seconds in the future.', 400 );
		}

		return array(
			'iso'   => $date->format( 'Y-m-d\TH:i:s\Z' ),
			'mysql' => $date->format( 'Y-m-d H:i:s' ),
		);
	}

	/**
	 * @param array<string, mixed> $normalized Normalized creation payload.
	 * @return true|WP_Error
	 */
	private static function guard_create( array $normalized ) {
		$object = self::post_type_object( $normalized['type'] );
		if ( is_wp_error( $object ) ) {
			return $object;
		}

		$create_cap = self::post_type_capability( $object, 'create_posts' );
		if ( '' === $create_cap ) {
			$create_cap = self::post_type_capability( $object, 'edit_posts' );
		}
		if ( '' === $create_cap || ! current_user_can( $create_cap ) ) {
			return self::error( 'seo_geo_manager_create_forbidden', 'You cannot create this WordPress content type.', 403 );
		}

		if ( in_array( $normalized['status'], array( 'publish', 'future' ), true ) ) {
			$publish_cap = self::post_type_capability( $object, 'publish_posts' );
			if ( '' === $publish_cap || ! current_user_can( $publish_cap ) ) {
				return self::error( 'seo_geo_manager_publish_capability_required', 'Publishing or scheduling requires the post type publish capability.', 403 );
			}
		}

		if ( '' !== $normalized['slug'] && self::slug_collision( $normalized['type'], $normalized['slug'] ) ) {
			return self::error( 'seo_geo_manager_create_slug_collision', 'The requested slug is already used by another resource of this type.', 409 );
		}

		return true;
	}

	/**
	 * @param array<string, mixed> $normalized Normalized publication payload.
	 * @return WP_Post|WP_Error
	 */
	private static function publication_target( array $normalized ) {
		$post = get_post( (int) $normalized['target_id'] );
		if ( ! $post instanceof WP_Post || ! in_array( $post->post_type, array( 'post', 'page' ), true ) ) {
			return self::error( 'seo_geo_manager_publication_target_missing', 'Publication target post/page was not found.', 404 );
		}
		if ( ! current_user_can( 'edit_post', $post->ID ) ) {
			return self::error( 'seo_geo_manager_publication_forbidden', 'You cannot edit this content resource.', 403 );
		}

		return $post;
	}

	/**
	 * @param array<string, mixed> $normalized Normalized publication payload.
	 * @return true|WP_Error
	 */
	private static function guard_publication( WP_Post $post, array $normalized ) {
		$current = ContentFingerprint::for_post( $post );
		if ( ! hash_equals( $normalized['expected_fingerprint'], $current ) ) {
			return new WP_Error(
				'seo_geo_manager_publication_stale',
				'The resource changed after inspection. Re-read it before publishing or scheduling.',
				array(
					'status'              => 409,
					'current_fingerprint' => $current,
				)
			);
		}

		$object = self::post_type_object( $post->post_type );
		if ( is_wp_error( $object ) ) {
			return $object;
		}

		$publish_cap = self::post_type_capability( $object, 'publish_posts' );
		if ( '' === $publish_cap || ! current_user_can( $publish_cap ) ) {
			return self::error( 'seo_geo_manager_publish_capability_required', 'Publishing or scheduling requires the post type publish capability.', 403 );
		}

		return true;
	}

	/**
	 * @return WP_Post_Type|WP_Error
	 */
	private static function post_type_object( string $type ) {
		$object = get_post_type_object( $type );
		if ( ! $object instanceof WP_Post_Type ) {
			return self::error( 'seo_geo_manager_content_type_unavailable', 'The requested WordPress content type is unavailable.', 400 );
		}

		return $object;
	}

	private static function post_type_capability( WP_Post_Type $post_type, string $name ): string {
		$capabilities = (array) $post_type->cap;
		$value        = $capabilities[ $name ] ?? '';

		return is_string( $value ) ? $value : '';
	}

	/**
	 * @param array<string, mixed> $normalized Normalized payload.
	 * @return array<string, mixed>
	 */
	private static function public_create_plan( array $normalized ): array {
		return array(
			'type'             => $normalized['type'],
			'title'            => $normalized['title'],
			'slug'             => $normalized['slug'],
			'excerpt'          => $normalized['excerpt'],
			'content'          => $normalized['content'],
			'status'           => $normalized['status'],
			'scheduled_at_gmt' => $normalized['scheduled_at_gmt'],
		);
	}

	/**
	 * @param array<string, mixed> $normalized Normalized payload.
	 * @return array<string, array<string, string>>
	 */
	private static function verify_created_post( WP_Post $post, array $normalized ): array {
		$expected = array(
			'type'    => $normalized['type'],
			'title'   => $normalized['title'],
			'excerpt' => $normalized['excerpt'],
			'content' => $normalized['content'],
			'status'  => $normalized['status'],
		);
		$actual   = array(
			'type'    => (string) $post->post_type,
			'title'   => (string) $post->post_title,
			'excerpt' => (string) $post->post_excerpt,
			'content' => (string) $post->post_content,
			'status'  => (string) $post->post_status,
		);

		if ( '' !== $normalized['slug'] ) {
			$expected['slug'] = $normalized['slug'];
			$actual['slug']   = (string) $post->post_name;
		}
		if ( 'future' === $normalized['status'] ) {
			$expected['scheduled_at_gmt'] = $normalized['scheduled_mysql_gmt'];
			$actual['scheduled_at_gmt']   = (string) $post->post_date_gmt;
		}

		return self::mismatches( $expected, $actual );
	}

	/**
	 * @param array<string, mixed> $normalized Normalized publication payload.
	 * @return array<string, array<string, string>>
	 */
	private static function verify_publication( WP_Post $post, array $normalized ): array {
		$expected = array(
			'status' => $normalized['status'],
		);
		$actual   = array(
			'status' => (string) $post->post_status,
		);

		if ( 'future' === $normalized['status'] ) {
			$expected['scheduled_at_gmt'] = $normalized['scheduled_mysql_gmt'];
			$actual['scheduled_at_gmt']   = (string) $post->post_date_gmt;
		}

		return self::mismatches( $expected, $actual );
	}

	/**
	 * @param array<string, string> $expected Expected values.
	 * @param array<string, string> $actual Actual values.
	 * @return array<string, array<string, string>>
	 */
	private static function mismatches( array $expected, array $actual ): array {
		$mismatches = array();
		foreach ( $expected as $field => $value ) {
			if ( ! isset( $actual[ $field ] ) || $actual[ $field ] !== $value ) {
				$mismatches[ $field ] = array(
					'expected' => $value,
					'actual'   => $actual[ $field ] ?? '',
				);
			}
		}

		return $mismatches;
	}

	/**
	 * @param array<string, mixed> $normalized Normalized publication payload.
	 */
	private static function publication_has_changes( WP_Post $post, array $normalized ): bool {
		if ( (string) $post->post_status !== $normalized['status'] ) {
			return true;
		}
		if ( 'future' === $normalized['status'] ) {
			return (string) $post->post_date_gmt !== $normalized['scheduled_mysql_gmt'];
		}

		return false;
	}

	private static function slug_collision( string $type, string $slug ): bool {
		$matches = get_posts(
			array(
				'name'           => $slug,
				'post_type'      => $type,
				'post_status'    => array( 'publish', 'future', 'draft', 'pending', 'private' ),
				'posts_per_page' => 1,
				'fields'         => 'ids',
			)
		);

		return array() !== $matches;
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function target_summary( WP_Post $post ): array {
		return array(
			'id'          => (int) $post->ID,
			'type'        => (string) $post->post_type,
			'status'      => (string) $post->post_status,
			'slug'        => (string) $post->post_name,
			'permalink'   => (string) get_permalink( $post ),
			'fingerprint' => ContentFingerprint::for_post( $post ),
		);
	}

	/**
	 * @param array<string, mixed> $normalized Normalized payload.
	 */
	private static function payload_hash( array $normalized ): string {
		$encoded = wp_json_encode( $normalized );

		return hash( 'sha256', is_string( $encoded ) ? $encoded : '' );
	}

	/**
	 * @return array<string, mixed>|WP_Error
	 */
	private static function idempotent_operation( string $operation_id ) {
		$existing = OperationStore::get( $operation_id );
		if ( is_array( $existing ) ) {
			$existing['idempotent_replay'] = true;

			return $existing;
		}

		return self::error(
			'seo_geo_manager_idempotent_operation_missing',
			'The idempotency key is reserved but its operation record is unavailable.',
			409
		);
	}

	private static function save_failed_operation(
		string $operation_id,
		string $type,
		string $payload_hash,
		string $idempotency_key,
		WP_Error $error
	): void {
		OperationStore::save(
			$operation_id,
			array(
				'operation_id'    => $operation_id,
				'operation_type'  => $type,
				'status'          => 'failed',
				'payload_hash'    => $payload_hash,
				'idempotency_key' => $idempotency_key,
				'error'           => $error->get_error_message(),
				'created_at_gmt'  => gmdate( 'c' ),
			)
		);
	}

	private static function error( string $code, string $message, int $status ): WP_Error {
		return new WP_Error( $code, $message, array( 'status' => $status ) );
	}
}
