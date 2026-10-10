<?php
/**
 * Optional rendered post-write verification for generic content change sets.
 *
 * @package SeoGeoManager
 */

declare(strict_types=1);

namespace SeoGeo\Manager\Changes;

use SeoGeo\Manager\Support\EnvironmentPolicy;
use SeoGeo\Manager\Support\RenderedResourceVerifier;
use WP_Error;
use WP_Post;

final class RenderedChangeSetAdapter {
	private const IDEMPOTENCY_NAMESPACE = 'rendered-v1-';

	/**
	 * Preview the normal change set plus public-route verification capability.
	 *
	 * @param array<string, mixed> $payload Request payload.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function preview( array $payload ) {
		$result = ChangeSetEngine::preview( self::engine_payload( $payload, false ) );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$post = self::target_post( $payload );
		if ( is_wp_error( $post ) ) {
			return $post;
		}

		$result['policy']['rendered_verification'] = array_merge(
			array( 'requested' => true ),
			RenderedResourceVerifier::capability( $post )
		);

		return $result;
	}

	/**
	 * Apply through M2, then verify the exact current public permalink.
	 *
	 * A public verification failure does not auto-rollback the stored change.
	 * The operation is marked accordingly and remains manually rollbackable.
	 *
	 * @param array<string, mixed> $payload Request payload.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function apply( array $payload ) {
		$post = self::target_post( $payload );
		if ( is_wp_error( $post ) ) {
			return $post;
		}

		$guard = self::guard_rendered_target( $post, $payload );
		if ( is_wp_error( $guard ) ) {
			return $guard;
		}

		$result = ChangeSetEngine::apply( self::engine_payload( $payload, true ) );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$operation_id = isset( $result['operation_id'] ) && is_string( $result['operation_id'] ) ? $result['operation_id'] : '';
		if ( '' === $operation_id ) {
			return new WP_Error( 'seo_geo_manager_rendered_operation_missing', 'Rendered verification requires a persisted Manager operation.', array( 'status' => 500 ) );
		}

		$stored = OperationStore::get( $operation_id );
		if ( ! is_array( $stored ) ) {
			return new WP_Error( 'seo_geo_manager_rendered_operation_missing', 'The Manager operation could not be reloaded for rendered verification.', array( 'status' => 500 ) );
		}

		$existing = isset( $stored['rendered_verification'] ) && is_array( $stored['rendered_verification'] ) ? $stored['rendered_verification'] : array();
		if ( array() !== $existing ) {
			$result['rendered_verification'] = $existing;
			$result['status']                = isset( $stored['status'] ) && is_string( $stored['status'] ) ? $stored['status'] : (string) ( $result['status'] ?? '' );

			return self::respond_to_evidence( $result, $operation_id, $existing );
		}

		$after = get_post( (int) ( $stored['target_id'] ?? 0 ) );
		if ( ! $after instanceof WP_Post ) {
			return new WP_Error( 'seo_geo_manager_rendered_target_missing', 'The target could not be reloaded for rendered verification.', array( 'status' => 500, 'operation_id' => $operation_id ) );
		}

		$verification                    = RenderedResourceVerifier::verify( $after );
		$stored['rendered_verification'] = $verification;
		$result['rendered_verification'] = $verification;

		if ( 'passed' !== ( $verification['status'] ?? '' ) ) {
			$stored['status'] = 'rendered-verification-failed';
			$result['status'] = 'rendered-verification-failed';
		}

		$stored = EnvironmentPolicy::bind_operation( $stored );
		if ( ! OperationStore::save( $operation_id, $stored ) ) {
			return new WP_Error( 'seo_geo_manager_rendered_operation_log_failed', 'Rendered verification completed, but its operation evidence could not be persisted.', array( 'status' => 500, 'operation_id' => $operation_id ) );
		}

		return self::respond_to_evidence( $result, $operation_id, $verification );
	}

	/**
	 * @param array<string, mixed> $payload Request payload.
	 * @return array<string, mixed>
	 */
	private static function engine_payload( array $payload, bool $for_apply ): array {
		unset( $payload['verify_rendered'] );
		if ( ! $for_apply ) {
			return $payload;
		}

		$key = isset( $payload['idempotency_key'] ) && is_string( $payload['idempotency_key'] ) ? trim( $payload['idempotency_key'] ) : '';
		if ( '' !== $key ) {
			$payload['idempotency_key'] = self::IDEMPOTENCY_NAMESPACE . hash( 'sha256', $key );
		}

		return $payload;
	}

	/**
	 * @param array<string, mixed> $payload Request payload.
	 * @return WP_Post|WP_Error
	 */
	private static function target_post( array $payload ) {
		$target = isset( $payload['target'] ) && is_array( $payload['target'] ) ? $payload['target'] : array();
		$id     = isset( $target['id'] ) ? absint( $target['id'] ) : 0;
		$post   = 0 < $id ? get_post( $id ) : null;

		if ( ! $post instanceof WP_Post || ! in_array( $post->post_type, array( 'page', 'post' ), true ) ) {
			return new WP_Error( 'seo_geo_manager_rendered_target_missing', 'Rendered verification requires an existing page/post target.', array( 'status' => 404 ) );
		}

		return $post;
	}

	/**
	 * @param array<string, mixed> $payload Request payload.
	 * @return true|WP_Error
	 */
	private static function guard_rendered_target( WP_Post $post, array $payload ) {
		$changes = isset( $payload['changes'] ) && is_array( $payload['changes'] ) ? $payload['changes'] : array();
		if ( isset( $changes['status'] ) ) {
			return new WP_Error(
				'seo_geo_manager_rendered_status_change_blocked',
				'Rendered post-write verification cannot be combined with a status transition.',
				array( 'status' => 409 )
			);
		}

		$capability = RenderedResourceVerifier::capability( $post );
		if ( true !== ( $capability['applicable'] ?? false ) ) {
			return new WP_Error(
				'seo_geo_manager_rendered_verification_unavailable',
				'Rendered post-write verification requires a published same-site public permalink.',
				array(
					'status' => 409,
					'reason' => $capability['reason'] ?? 'unavailable',
				)
			);
		}

		return true;
	}

	/**
	 * @param array<string, mixed> $result Operation result.
	 * @param array<string, mixed> $verification Rendered evidence.
	 * @return array<string, mixed>|WP_Error
	 */
	private static function respond_to_evidence( array $result, string $operation_id, array $verification ) {
		if ( 'passed' === ( $verification['status'] ?? '' ) ) {
			return $result;
		}

		return new WP_Error(
			'seo_geo_manager_rendered_verification_failed',
			'The WordPress mutation persisted, but the exact public route did not pass rendered verification. Review the evidence before rollback or retry.',
			array(
				'status'                => 409,
				'operation_id'          => $operation_id,
				'rendered_verification' => $verification,
			)
		);
	}
}
