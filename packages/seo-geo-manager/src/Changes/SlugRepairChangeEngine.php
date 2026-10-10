<?php
/**
 * Protected repair engine for migration-corrupted local post_name values.
 *
 * @package SeoGeoManager
 */

declare(strict_types=1);

namespace SeoGeo\Manager\Changes;

use SeoGeo\Manager\Support\AuthoritativePermalinkPlanner;
use SeoGeo\Manager\Support\EnvironmentPolicy;
use WP_Error;

final class SlugRepairChangeEngine {
	private const OPERATION_TYPE = 'post-slug-repair';

	/**
	 * Build one read-only repair preview from the current authoritative plan.
	 *
	 * @return array<string, mixed>
	 */
	public static function preview( string $legacy_base_url, string $expected_authority_fingerprint = '', string $expected_plan_fingerprint = '' ): array {
		$plan = AuthoritativePermalinkPlanner::preview( $legacy_base_url, $expected_authority_fingerprint );

		$plan_fingerprint = isset( $plan['plan_fingerprint'] ) && is_string( $plan['plan_fingerprint'] )
			? $plan['plan_fingerprint']
			: '';
		$blockers = array();
		if ( '' !== $expected_plan_fingerprint && ( '' === $plan_fingerprint || ! hash_equals( $plan_fingerprint, $expected_plan_fingerprint ) ) ) {
			$blockers[] = array(
				'code'    => 'stale-plan',
				'message' => 'The authoritative permalink plan changed. Generate a fresh preview before repairing slugs.',
			);
		}

		$repairs = isset( $plan['local_slug_repairs'] ) && is_array( $plan['local_slug_repairs'] )
			? $plan['local_slug_repairs']
			: array();
		$candidates = array();
		$repair_ids = array();

		foreach ( $repairs as $repair ) {
			if ( ! is_array( $repair ) ) {
				continue;
			}

			$post_id         = isset( $repair['post_id'] ) ? absint( $repair['post_id'] ) : 0;
			$before_slug     = isset( $repair['local_slug'] ) && is_string( $repair['local_slug'] ) ? $repair['local_slug'] : '';
			$historical_slug = isset( $repair['historical_slug'] ) && is_string( $repair['historical_slug'] ) ? $repair['historical_slug'] : '';
			$historical_url  = isset( $repair['historical_url'] ) && is_string( $repair['historical_url'] ) ? $repair['historical_url'] : '';
			$post            = 0 < $post_id ? get_post( $post_id ) : null;
			$target_slug     = sanitize_title( $historical_slug );

			if ( ! $post || 'post' !== $post->post_type || 'publish' !== $post->post_status ) {
				$blockers[] = array(
					'code'    => 'post-unavailable',
					'post_id' => $post_id,
					'message' => 'A planned slug-repair post is no longer a published post.',
				);
				continue;
			}
			if ( '' === $before_slug || $before_slug !== (string) $post->post_name ) {
				$blockers[] = array(
					'code'    => 'local-slug-changed',
					'post_id' => $post_id,
					'message' => 'A local post slug changed after the authoritative scan.',
				);
				continue;
			}
			if ( '' === $target_slug || $target_slug === $before_slug ) {
				$blockers[] = array(
					'code'    => 'invalid-target-slug',
					'post_id' => $post_id,
					'message' => 'The verified historical slug does not produce a distinct safe WordPress post slug.',
				);
				continue;
			}

			$unique_slug = wp_unique_post_slug( $target_slug, $post_id, 'publish', 'post', 0 );
			if ( $unique_slug !== $target_slug ) {
				$blockers[] = array(
					'code'           => 'target-slug-collision',
					'post_id'        => $post_id,
					'target_slug'    => $target_slug,
					'wordpress_slug' => $unique_slug,
					'message'        => 'The verified historical slug collides with another WordPress post slug.',
				);
				continue;
			}

			$repair_ids[ $post_id ] = true;
			$candidates[] = array(
				'post_id'            => $post_id,
				'before_slug'        => $before_slug,
				'target_slug'        => $target_slug,
				'historical_slug'    => $historical_slug,
				'historical_url'     => $historical_url,
				'before_fingerprint' => self::slug_fingerprint( $post_id, $before_slug ),
			);
		}

		$plan_collisions = isset( $plan['collisions'] ) && is_array( $plan['collisions'] ) ? $plan['collisions'] : array();
		foreach ( $plan_collisions as $collision ) {
			if ( ! is_array( $collision ) ) {
				continue;
			}
			$type    = isset( $collision['type'] ) && is_string( $collision['type'] ) ? $collision['type'] : '';
			$post_id = isset( $collision['post_id'] ) ? absint( $collision['post_id'] ) : 0;
			if ( 'corrupted-local-slug-requires-repair' === $type && isset( $repair_ids[ $post_id ] ) ) {
				continue;
			}
			$blockers[] = array(
				'code'    => 'unrelated-plan-collision',
				'post_id' => $post_id,
				'type'    => $type,
				'message' => 'The permalink plan contains a collision unrelated to the protected slug repairs.',
			);
		}

		$expected_repair_count = isset( $plan['local_slug_repair_count'] ) ? max( 0, (int) $plan['local_slug_repair_count'] ) : 0;
		if ( 0 === $expected_repair_count ) {
			$blockers[] = array(
				'code'    => 'no-repairs-required',
				'message' => 'The current authoritative plan does not require any protected local slug repair.',
			);
		} elseif ( count( $candidates ) !== $expected_repair_count ) {
			$blockers[] = array(
				'code'    => 'repair-set-incomplete',
				'message' => 'Not every planned corrupted local slug could be converted into a safe repair candidate.',
			);
		}

		$fingerprint_payload = array(
			'authority_fingerprint' => (string) ( $plan['authority_fingerprint'] ?? '' ),
			'plan_fingerprint'      => $plan_fingerprint,
			'target_structure'      => (string) ( $plan['target_structure'] ?? '' ),
			'repairs'               => $candidates,
		);
		$repair_fingerprint = hash( 'sha256', (string) wp_json_encode( $fingerprint_payload ) );
		$safe_to_apply      = array() === $blockers && 0 < count( $candidates );

		return array(
			'mode'                   => 'protected-local-slug-repair-preview',
			'write_performed'        => false,
			'legacy_base_url'        => (string) ( $plan['legacy_base_url'] ?? $legacy_base_url ),
			'authority_fingerprint'  => (string) ( $plan['authority_fingerprint'] ?? '' ),
			'plan_fingerprint'       => $plan_fingerprint,
			'repair_fingerprint'     => $repair_fingerprint,
			'target_structure'       => (string) ( $plan['target_structure'] ?? '' ),
			'repair_count'           => count( $candidates ),
			'repairs'                => $candidates,
			'blockers'               => $blockers,
			'safe_to_apply'          => $safe_to_apply,
			'apply_blocked'          => ! $safe_to_apply,
			'next_action'            => $safe_to_apply ? 'apply-protected-local-slug-repair' : 'refresh-slug-repair-preview',
			'environment'            => EnvironmentPolicy::snapshot(),
			'policy'                 => array(
				'preview_only'                         => true,
				'authority_revalidated'                => true,
				'exact_current_slug_required'          => true,
				'wordpress_unique_slug_required'       => true,
				'permalink_structure_unchanged'        => true,
				'redirect_runtime_unchanged'           => true,
				'apply_requires_explicit_confirmation' => true,
				'apply_requires_environment_guard'     => true,
			),
		);
	}

	/**
	 * Apply every currently verified migration-corrupted slug as one reversible operation.
	 *
	 * @param array<string, mixed> $payload Request payload.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function apply( array $payload ) {
		$normalized = self::normalize_apply_payload( $payload );
		if ( is_wp_error( $normalized ) ) {
			return $normalized;
		}

		$payload_hash = hash( 'sha256', (string) wp_json_encode( $normalized ) );
		$lookup       = OperationStore::lookup( $normalized['idempotency_key'], $payload_hash );
		if ( is_wp_error( $lookup ) ) {
			return $lookup;
		}
		if ( $lookup['existing'] ) {
			return self::idempotent_operation( $lookup['operation_id'] );
		}

		$preview = self::preview(
			$normalized['legacy_base_url'],
			$normalized['authority_fingerprint'],
			$normalized['plan_fingerprint']
		);
		if ( true !== ( $preview['safe_to_apply'] ?? false ) ) {
			return new WP_Error(
				'seo_geo_manager_slug_repair_preview_blocked',
				'The protected slug repair preview is not safe to apply.',
				array(
					'status'   => 409,
					'blockers' => $preview['blockers'] ?? array(),
				)
			);
		}

		$repair_fingerprint = isset( $preview['repair_fingerprint'] ) && is_string( $preview['repair_fingerprint'] ) ? $preview['repair_fingerprint'] : '';
		if ( '' === $repair_fingerprint || ! hash_equals( $repair_fingerprint, $normalized['repair_fingerprint'] ) ) {
			return new WP_Error(
				'seo_geo_manager_slug_repair_state_stale',
				'The protected slug repair set changed. Generate a fresh preview before Apply.',
				array( 'status' => 409 )
			);
		}

		$repairs      = isset( $preview['repairs'] ) && is_array( $preview['repairs'] ) ? $preview['repairs'] : array();
		$operation_id = wp_generate_uuid4();
		$reservation  = OperationStore::reserve( $normalized['idempotency_key'], $payload_hash, $operation_id );
		if ( is_wp_error( $reservation ) ) {
			return $reservation;
		}
		if ( $reservation['existing'] ) {
			return self::idempotent_operation( $reservation['operation_id'] );
		}

		$states = array();
		foreach ( $repairs as $repair ) {
			if ( ! is_array( $repair ) ) {
				continue;
			}

			$post_id     = isset( $repair['post_id'] ) ? absint( $repair['post_id'] ) : 0;
			$before_slug = isset( $repair['before_slug'] ) && is_string( $repair['before_slug'] ) ? $repair['before_slug'] : '';
			$target_slug = isset( $repair['target_slug'] ) && is_string( $repair['target_slug'] ) ? $repair['target_slug'] : '';
			$current     = 0 < $post_id ? (string) get_post_field( 'post_name', $post_id ) : '';
			if ( 0 === $post_id || '' === $before_slug || '' === $target_slug || $current !== $before_slug ) {
				self::restore_states( $states );
				return self::failed_apply(
					$operation_id,
					$normalized,
					$payload_hash,
					$states,
					'Local slug state changed before the protected repair could be completed.'
				);
			}

			$state = array(
				'post_id'            => $post_id,
				'before_slug'        => $before_slug,
				'after_slug'         => $target_slug,
				'before_fingerprint' => self::slug_fingerprint( $post_id, $before_slug ),
				'old_slug_meta'      => array_values( array_map( 'strval', get_post_meta( $post_id, '_wp_old_slug', false ) ) ),
				'historical_url'     => isset( $repair['historical_url'] ) && is_string( $repair['historical_url'] ) ? $repair['historical_url'] : '',
			);
			$states[] = $state;

			$updated = wp_update_post(
				array(
					'ID'        => $post_id,
					'post_name' => $target_slug,
				),
				true
			);
			if ( is_wp_error( $updated ) || $post_id !== (int) $updated || $target_slug !== (string) get_post_field( 'post_name', $post_id ) ) {
				self::restore_states( $states );
				$message = is_wp_error( $updated ) ? $updated->get_error_message() : 'WordPress did not persist the exact verified historical slug.';
				return self::failed_apply( $operation_id, $normalized, $payload_hash, $states, $message );
			}
		}

		if ( count( $states ) !== count( $repairs ) ) {
			self::restore_states( $states );
			return self::failed_apply( $operation_id, $normalized, $payload_hash, $states, 'The protected slug repair set was incomplete during Apply.' );
		}

		$after_plan   = AuthoritativePermalinkPlanner::preview( $normalized['legacy_base_url'] );
		$verification = self::verification_errors( $after_plan, (string) ( $preview['target_structure'] ?? '' ) );
		if ( array() !== $verification ) {
			self::restore_states( $states );
			return self::failed_apply( $operation_id, $normalized, $payload_hash, $states, implode( '; ', $verification ) );
		}

		foreach ( $states as $index => $state ) {
			$post_id = (int) $state['post_id'];
			$states[ $index ]['after_fingerprint'] = self::slug_fingerprint( $post_id, (string) $state['after_slug'] );
		}

		$operation = EnvironmentPolicy::bind_operation(
			array(
				'operation_id'                 => $operation_id,
				'operation_type'               => self::OPERATION_TYPE,
				'status'                       => 'applied',
				'idempotency_key'              => $normalized['idempotency_key'],
				'payload_hash'                 => $payload_hash,
				'legacy_base_url'              => $normalized['legacy_base_url'],
				'authority_fingerprint'        => $normalized['authority_fingerprint'],
				'plan_fingerprint'             => $normalized['plan_fingerprint'],
				'repair_fingerprint'           => $normalized['repair_fingerprint'],
				'target_type'                  => 'post-batch',
				'target_id'                    => 0,
				'changes'                      => array( 'post_name' => count( $states ) ),
				'repair_count'                 => count( $states ),
				'repairs'                      => $states,
				'permalink_structure_changed'  => false,
				'redirect_runtime_changed'     => false,
				'rewrite_flush_performed'      => false,
				'next_authority_fingerprint'   => (string) ( $after_plan['authority_fingerprint'] ?? '' ),
				'next_plan_fingerprint'        => (string) ( $after_plan['plan_fingerprint'] ?? '' ),
				'next_target_structure'        => (string) ( $after_plan['target_structure'] ?? '' ),
				'next_planned_redirects'       => (int) ( $after_plan['planned_redirects'] ?? 0 ),
				'next_path_preservation_count' => (int) ( $after_plan['path_preservation_count'] ?? 0 ),
				'created_at_gmt'               => gmdate( 'c' ),
				'idempotent_replay'            => false,
			)
		);

		if ( ! OperationStore::save( $operation_id, $operation ) ) {
			self::restore_states( $states );
			return new WP_Error(
				'seo_geo_manager_slug_repair_operation_log_failed',
				'The slugs were repaired, but the operation record could not be saved, so the original slugs were restored.',
				array( 'status' => 500 )
			);
		}

		return $operation;
	}

	/**
	 * Restore the exact pre-repair slugs only when no later slug change intervened.
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	public static function rollback( string $operation_id ) {
		$operation = OperationStore::get( $operation_id );
		if ( ! is_array( $operation ) || self::OPERATION_TYPE !== ( $operation['operation_type'] ?? '' ) ) {
			return new WP_Error(
				'seo_geo_manager_slug_repair_operation_not_found',
				'Protected slug-repair operation not found.',
				array( 'status' => 404 )
			);
		}
		if ( 'rolled-back' === ( $operation['status'] ?? '' ) ) {
			return $operation;
		}
		if ( 'applied' !== ( $operation['status'] ?? '' ) ) {
			return new WP_Error(
				'seo_geo_manager_slug_repair_rollback_unavailable',
				'This slug-repair operation is not in an applied state that can be rolled back.',
				array( 'status' => 409 )
			);
		}

		$states = isset( $operation['repairs'] ) && is_array( $operation['repairs'] ) ? $operation['repairs'] : array();
		foreach ( $states as $state ) {
			if ( ! is_array( $state ) ) {
				return new WP_Error( 'seo_geo_manager_slug_repair_rollback_invalid', 'Stored slug-repair state is invalid.', array( 'status' => 500 ) );
			}
			$post_id    = isset( $state['post_id'] ) ? absint( $state['post_id'] ) : 0;
			$after_slug = isset( $state['after_slug'] ) && is_string( $state['after_slug'] ) ? $state['after_slug'] : '';
			$current    = 0 < $post_id ? (string) get_post_field( 'post_name', $post_id ) : '';
			if ( 0 === $post_id || '' === $after_slug || $current !== $after_slug ) {
				return new WP_Error(
					'seo_geo_manager_slug_repair_rollback_stale',
					'A repaired post slug changed after this Manager operation; rollback is blocked to protect the newer state.',
					array(
						'status'  => 409,
						'post_id' => $post_id,
					)
				);
			}
		}

		if ( ! self::restore_states( $states ) ) {
			return new WP_Error(
				'seo_geo_manager_slug_repair_rollback_failed',
				'The original migration-corrupted slug state could not be restored exactly.',
				array( 'status' => 500 )
			);
		}

		$operation['status']             = 'rolled-back';
		$operation['rolled_back_at_gmt'] = gmdate( 'c' );
		$operation['rollback_verified']  = true;
		OperationStore::save( $operation_id, $operation );

		return $operation;
	}

	/**
	 * @param array<string, mixed> $payload Request payload.
	 * @return array<string, string|bool>|WP_Error
	 */
	private static function normalize_apply_payload( array $payload ) {
		$normalized = array(
			'legacy_base_url'       => isset( $payload['legacy_base_url'] ) && is_string( $payload['legacy_base_url'] ) ? trim( $payload['legacy_base_url'] ) : '',
			'authority_fingerprint' => isset( $payload['authority_fingerprint'] ) && is_string( $payload['authority_fingerprint'] ) ? trim( $payload['authority_fingerprint'] ) : '',
			'plan_fingerprint'      => isset( $payload['plan_fingerprint'] ) && is_string( $payload['plan_fingerprint'] ) ? trim( $payload['plan_fingerprint'] ) : '',
			'repair_fingerprint'    => isset( $payload['repair_fingerprint'] ) && is_string( $payload['repair_fingerprint'] ) ? trim( $payload['repair_fingerprint'] ) : '',
			'idempotency_key'       => isset( $payload['idempotency_key'] ) && is_string( $payload['idempotency_key'] ) ? trim( $payload['idempotency_key'] ) : '',
			'confirm_slug_repair'   => true === ( $payload['confirm_slug_repair'] ?? false ),
		);

		foreach ( array( 'legacy_base_url', 'authority_fingerprint', 'plan_fingerprint', 'repair_fingerprint', 'idempotency_key' ) as $required ) {
			if ( '' === $normalized[ $required ] ) {
				return new WP_Error(
					'seo_geo_manager_slug_repair_payload_invalid',
					'Protected slug repair requires legacy authority, plan/repair fingerprints and an idempotency key.',
					array( 'status' => 400 )
				);
			}
		}
		if ( 128 < strlen( $normalized['idempotency_key'] ) ) {
			return new WP_Error(
				'seo_geo_manager_slug_repair_idempotency_invalid',
				'Protected slug-repair idempotency keys are limited to 128 characters.',
				array( 'status' => 400 )
			);
		}
		if ( true !== $normalized['confirm_slug_repair'] ) {
			return new WP_Error(
				'seo_geo_manager_slug_repair_confirmation_required',
				'Protected slug repair requires explicit confirm_slug_repair=true approval.',
				array( 'status' => 409 )
			);
		}

		return $normalized;
	}

	/**
	 * @param array<string, mixed> $plan Fresh post-repair plan.
	 * @return array<int, string>
	 */
	private static function verification_errors( array $plan, string $expected_target_structure ): array {
		$errors = array();
		if ( true !== ( $plan['authority_mapping_verified'] ?? false ) ) {
			$errors[] = 'historical-authority-no-longer-mapped';
		}
		if ( 0 !== (int) ( $plan['local_slug_repair_count'] ?? -1 ) ) {
			$errors[] = 'local-slug-repairs-remain';
		}
		if ( '' === $expected_target_structure || $expected_target_structure !== (string) ( $plan['target_structure'] ?? '' ) ) {
			$errors[] = 'target-structure-changed-after-slug-repair';
		}

		return $errors;
	}

	/**
	 * Restore exact slugs and the pre-operation _wp_old_slug metadata.
	 *
	 * @param array<int, array<string, mixed>> $states Repair states.
	 */
	private static function restore_states( array $states ): bool {
		global $wpdb;

		$ok = true;
		foreach ( $states as $state ) {
			if ( ! is_array( $state ) ) {
				$ok = false;
				continue;
			}
			$post_id     = isset( $state['post_id'] ) ? absint( $state['post_id'] ) : 0;
			$before_slug = isset( $state['before_slug'] ) && is_string( $state['before_slug'] ) ? $state['before_slug'] : '';
			if ( 0 === $post_id || '' === $before_slug ) {
				$ok = false;
				continue;
			}

			$updated = $wpdb->update(
				$wpdb->posts,
				array( 'post_name' => $before_slug ),
				array( 'ID' => $post_id ),
				array( '%s' ),
				array( '%d' )
			);
			if ( false === $updated ) {
				$ok = false;
				continue;
			}

			delete_post_meta( $post_id, '_wp_old_slug' );
			$old_slug_meta = isset( $state['old_slug_meta'] ) && is_array( $state['old_slug_meta'] ) ? $state['old_slug_meta'] : array();
			foreach ( $old_slug_meta as $old_slug ) {
				if ( is_string( $old_slug ) ) {
					add_post_meta( $post_id, '_wp_old_slug', $old_slug, false );
				}
			}
			clean_post_cache( $post_id );
			if ( $before_slug !== (string) get_post_field( 'post_name', $post_id ) ) {
				$ok = false;
			}
		}

		return $ok;
	}

	/**
	 * @param array<string, string|bool>       $normalized Normalized payload.
	 * @param array<int, array<string, mixed>> $states Repair states.
	 */
	private static function failed_apply( string $operation_id, array $normalized, string $payload_hash, array $states, string $message ): WP_Error {
		$operation = EnvironmentPolicy::bind_operation(
			array(
				'operation_id'         => $operation_id,
				'operation_type'       => self::OPERATION_TYPE,
				'status'               => 'verification-failed-rolled-back',
				'idempotency_key'      => $normalized['idempotency_key'],
				'payload_hash'         => $payload_hash,
				'legacy_base_url'      => $normalized['legacy_base_url'],
				'authority_fingerprint'=> $normalized['authority_fingerprint'],
				'plan_fingerprint'     => $normalized['plan_fingerprint'],
				'repair_fingerprint'   => $normalized['repair_fingerprint'],
				'target_type'          => 'post-batch',
				'target_id'            => 0,
				'changes'              => array( 'post_name' => count( $states ) ),
				'repair_count'         => count( $states ),
				'repairs'              => $states,
				'failure_message'      => $message,
				'created_at_gmt'       => gmdate( 'c' ),
			)
		);
		OperationStore::save( $operation_id, $operation );

		return new WP_Error(
			'seo_geo_manager_slug_repair_verification_failed',
			'The protected slug repair could not be verified, so the original local slugs were restored.',
			array(
				'status'       => 409,
				'operation_id' => $operation_id,
				'detail'       => $message,
			)
		);
	}

	/**
	 * @return array<string, mixed>|WP_Error
	 */
	private static function idempotent_operation( string $operation_id ) {
		$operation = OperationStore::get( $operation_id );
		if ( ! is_array( $operation ) || self::OPERATION_TYPE !== ( $operation['operation_type'] ?? '' ) ) {
			return new WP_Error(
				'seo_geo_manager_slug_repair_idempotency_missing',
				'The protected slug-repair idempotency key points to a missing operation.',
				array( 'status' => 409 )
			);
		}
		$operation['idempotent_replay'] = true;

		return $operation;
	}

	private static function slug_fingerprint( int $post_id, string $slug ): string {
		return hash( 'sha256', $post_id . '|' . $slug );
	}
}
