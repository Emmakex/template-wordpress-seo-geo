<?php
/**
 * Controlled permalink-structure apply and rollback engine.
 *
 * @package SeoGeoManager
 */

declare(strict_types=1);

namespace SeoGeo\Manager\Changes;

use SeoGeo\Manager\Support\AuthoritativePermalinkPlanner;
use SeoGeo\Manager\Support\EnvironmentPolicy;
use WP_Error;

final class PermalinkChangeEngine {
	private const OPERATION_TYPE = 'permalink-structure';

	/**
	 * Apply a verified authoritative permalink structure without redirect runtime.
	 *
	 * @param array<string, mixed> $payload Request payload.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function apply( array $payload ) {
		$normalized = self::normalize_apply_payload( $payload );
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

		$plan = AuthoritativePermalinkPlanner::preview(
			$normalized['legacy_base_url'],
			$normalized['authority_fingerprint']
		);
		$guard = self::guard_plan( $plan, $normalized );
		if ( is_wp_error( $guard ) ) {
			return $guard;
		}

		$before_structure = (string) ( $plan['current_structure'] ?? '' );
		$after_structure  = (string) ( $plan['authoritative_structure'] ?? '' );
		if ( $before_structure === $after_structure ) {
			return new WP_Error(
				'seo_geo_manager_permalink_already_authoritative',
				'The current permalink structure already matches the verified historical authority.',
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

		$before_fingerprint = self::structure_fingerprint( $before_structure );
		$write              = self::set_structure( $after_structure );
		if ( is_wp_error( $write ) ) {
			self::save_failed_operation(
				$operation_id,
				$normalized,
				$payload_hash,
				$before_structure,
				$after_structure,
				$before_fingerprint,
				$write->get_error_message()
			);
			return $write;
		}

		$after_fingerprint = self::structure_fingerprint( (string) get_option( 'permalink_structure', '' ) );
		$verification_plan = AuthoritativePermalinkPlanner::preview(
			$normalized['legacy_base_url'],
			$normalized['authority_fingerprint']
		);
		$verification = self::verification_errors( $verification_plan, $after_structure );

		if ( array() !== $verification ) {
			self::set_structure( $before_structure );
			$failed = EnvironmentPolicy::bind_operation(
				array(
					'operation_id'          => $operation_id,
					'operation_type'        => self::OPERATION_TYPE,
					'status'                => 'verification-failed-rolled-back',
					'idempotency_key'       => $normalized['idempotency_key'],
					'payload_hash'          => $payload_hash,
					'authority_fingerprint' => $normalized['authority_fingerprint'],
					'plan_fingerprint'      => $normalized['plan_fingerprint'],
					'before_structure'      => $before_structure,
					'after_structure'       => $after_structure,
					'before_fingerprint'    => $before_fingerprint,
					'after_fingerprint'     => $after_fingerprint,
					'verification'          => $verification,
					'created_at_gmt'        => gmdate( 'c' ),
				)
			);
			OperationStore::save( $operation_id, $failed );

			return new WP_Error(
				'seo_geo_manager_permalink_verification_failed',
				'The permalink structure changed but verification failed, so the previous structure was restored.',
				array(
					'status'       => 409,
					'operation_id' => $operation_id,
					'verification' => $verification,
				)
			);
		}

		$operation = EnvironmentPolicy::bind_operation(
			array(
				'operation_id'          => $operation_id,
				'operation_type'        => self::OPERATION_TYPE,
				'status'                => 'applied',
				'idempotency_key'       => $normalized['idempotency_key'],
				'payload_hash'          => $payload_hash,
				'authority_fingerprint' => $normalized['authority_fingerprint'],
				'plan_fingerprint'      => $normalized['plan_fingerprint'],
				'legacy_base_url'       => $normalized['legacy_base_url'],
				'before_structure'      => $before_structure,
				'after_structure'       => $after_structure,
				'before_fingerprint'    => $before_fingerprint,
				'after_fingerprint'     => $after_fingerprint,
				'seo_preservation_mode' => (string) ( $verification_plan['seo_preservation_mode'] ?? '' ),
				'path_preservation_count'=> (int) ( $verification_plan['path_preservation_count'] ?? 0 ),
				'planned_redirects'     => (int) ( $verification_plan['planned_redirects'] ?? 0 ),
				'rewrite_flush_performed'=> true,
				'verification'          => array(),
				'created_at_gmt'        => gmdate( 'c' ),
				'idempotent_replay'     => false,
			)
		);

		if ( ! OperationStore::save( $operation_id, $operation ) ) {
			self::set_structure( $before_structure );
			return new WP_Error(
				'seo_geo_manager_permalink_operation_log_failed',
				'The permalink structure changed, but the operation log could not be persisted; the previous structure was restored.',
				array( 'status' => 500 )
			);
		}

		return $operation;
	}

	/**
	 * Roll back one permalink operation if no newer structure change intervened.
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	public static function rollback( string $operation_id ) {
		$operation = OperationStore::get( $operation_id );
		if ( ! is_array( $operation ) || self::OPERATION_TYPE !== ( $operation['operation_type'] ?? '' ) ) {
			return new WP_Error(
				'seo_geo_manager_permalink_operation_not_found',
				'Permalink Manager operation not found.',
				array( 'status' => 404 )
			);
		}
		if ( 'rolled-back' === ( $operation['status'] ?? '' ) ) {
			return $operation;
		}
		if ( 'applied' !== ( $operation['status'] ?? '' ) ) {
			return new WP_Error(
				'seo_geo_manager_permalink_rollback_unavailable',
				'This permalink operation is not in an applied state that can be rolled back.',
				array( 'status' => 409 )
			);
		}

		$current_structure   = (string) get_option( 'permalink_structure', '' );
		$current_fingerprint = self::structure_fingerprint( $current_structure );
		$expected_after      = (string) ( $operation['after_fingerprint'] ?? '' );
		$after_structure     = (string) ( $operation['after_structure'] ?? '' );
		if ( '' === $expected_after || ! hash_equals( $expected_after, $current_fingerprint ) || $current_structure !== $after_structure ) {
			return new WP_Error(
				'seo_geo_manager_permalink_rollback_stale',
				'The permalink structure changed after this Manager operation; rollback is blocked to protect the newer state.',
				array(
					'status'              => 409,
					'current_fingerprint' => $current_fingerprint,
				)
			);
		}

		$before_structure   = (string) ( $operation['before_structure'] ?? '' );
		$before_fingerprint = (string) ( $operation['before_fingerprint'] ?? '' );
		$write              = self::set_structure( $before_structure );
		if ( is_wp_error( $write ) ) {
			return $write;
		}

		$restored_fingerprint = self::structure_fingerprint( (string) get_option( 'permalink_structure', '' ) );
		if ( '' === $before_fingerprint || ! hash_equals( $before_fingerprint, $restored_fingerprint ) ) {
			return new WP_Error(
				'seo_geo_manager_permalink_rollback_verification_failed',
				'The previous permalink structure could not be verified after rollback.',
				array( 'status' => 500 )
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
	 * @return array<string, string|bool>|WP_Error
	 */
	private static function normalize_apply_payload( array $payload ) {
		$normalized = array(
			'legacy_base_url'       => isset( $payload['legacy_base_url'] ) && is_string( $payload['legacy_base_url'] ) ? trim( $payload['legacy_base_url'] ) : '',
			'authority_fingerprint' => isset( $payload['authority_fingerprint'] ) && is_string( $payload['authority_fingerprint'] ) ? trim( $payload['authority_fingerprint'] ) : '',
			'plan_fingerprint'      => isset( $payload['plan_fingerprint'] ) && is_string( $payload['plan_fingerprint'] ) ? trim( $payload['plan_fingerprint'] ) : '',
			'current_fingerprint'   => isset( $payload['current_fingerprint'] ) && is_string( $payload['current_fingerprint'] ) ? trim( $payload['current_fingerprint'] ) : '',
			'idempotency_key'       => isset( $payload['idempotency_key'] ) && is_string( $payload['idempotency_key'] ) ? trim( $payload['idempotency_key'] ) : '',
			'confirm_permalink_change'=> true === ( $payload['confirm_permalink_change'] ?? false ),
		);

		foreach ( array( 'legacy_base_url', 'authority_fingerprint', 'plan_fingerprint', 'current_fingerprint', 'idempotency_key' ) as $required ) {
			if ( '' === $normalized[ $required ] ) {
				return new WP_Error(
					'seo_geo_manager_permalink_apply_payload_invalid',
					'Permalink Apply requires legacy authority, plan/current fingerprints and an idempotency key.',
					array( 'status' => 400 )
				);
			}
		}
		if ( 128 < strlen( (string) $normalized['idempotency_key'] ) ) {
			return new WP_Error(
				'seo_geo_manager_permalink_idempotency_invalid',
				'Permalink Apply idempotency keys are limited to 128 characters.',
				array( 'status' => 400 )
			);
		}
		if ( true !== $normalized['confirm_permalink_change'] ) {
			return new WP_Error(
				'seo_geo_manager_permalink_confirmation_required',
				'Permalink Apply requires explicit confirm_permalink_change=true approval.',
				array( 'status' => 409 )
			);
		}

		return $normalized;
	}

	/**
	 * @param array<string, mixed> $plan Verified plan.
	 * @param array<string, string|bool> $normalized Normalized request.
	 * @return true|WP_Error
	 */
	private static function guard_plan( array $plan, array $normalized ) {
		if ( true !== ( $plan['safe_structure_candidate'] ?? false ) ) {
			return new WP_Error(
				'seo_geo_manager_permalink_plan_blocked',
				(string) ( $plan['block_reason'] ?? 'The authoritative permalink plan is not safe to apply.' ),
				array( 'status' => 409 )
			);
		}
		if ( true === ( $plan['requires_redirect_runtime'] ?? false ) || 0 < (int) ( $plan['planned_redirects'] ?? 0 ) ) {
			return new WP_Error(
				'seo_geo_manager_permalink_redirect_runtime_required',
				'This plan changes historical SEO paths and cannot be applied until the 301 runtime is implemented and verified.',
				array( 'status' => 409 )
			);
		}

		$plan_fingerprint = (string) ( $plan['plan_fingerprint'] ?? '' );
		if ( '' === $plan_fingerprint || ! hash_equals( $plan_fingerprint, (string) $normalized['plan_fingerprint'] ) ) {
			return new WP_Error(
				'seo_geo_manager_permalink_plan_stale',
				'The authoritative permalink plan changed. Generate a fresh preview before Apply.',
				array( 'status' => 409 )
			);
		}

		$current_fingerprint = (string) ( $plan['current_fingerprint'] ?? '' );
		if ( '' === $current_fingerprint || ! hash_equals( $current_fingerprint, (string) $normalized['current_fingerprint'] ) ) {
			return new WP_Error(
				'seo_geo_manager_permalink_state_stale',
				'The current permalink state changed after preview. Generate a fresh preview before Apply.',
				array( 'status' => 409 )
			);
		}

		return true;
	}

	/**
	 * @param array<string, mixed> $plan Verification plan.
	 * @return array<int, string>
	 */
	private static function verification_errors( array $plan, string $expected_structure ): array {
		$errors = array();
		if ( $expected_structure !== (string) get_option( 'permalink_structure', '' ) ) {
			$errors[] = 'permalink_structure-mismatch';
		}
		if ( true !== ( $plan['safe_structure_candidate'] ?? false ) ) {
			$errors[] = 'authoritative-plan-no-longer-safe';
		}
		if ( 0 !== (int) ( $plan['planned_redirects'] ?? -1 ) ) {
			$errors[] = 'unexpected-authoritative-redirects';
		}
		if ( 'exact-path-preservation' !== (string) ( $plan['seo_preservation_mode'] ?? '' ) ) {
			$errors[] = 'historical-paths-not-preserved';
		}

		return $errors;
	}

	/**
	 * @return true|WP_Error
	 */
	private static function set_structure( string $structure ) {
		global $wp_rewrite;
		if ( ! $wp_rewrite instanceof \WP_Rewrite ) {
			return new WP_Error(
				'seo_geo_manager_permalink_rewrite_unavailable',
				'WordPress rewrite runtime is unavailable.',
				array( 'status' => 500 )
			);
		}

		$wp_rewrite->set_permalink_structure( $structure );
		flush_rewrite_rules( false );
		wp_cache_delete( 'permalink_structure', 'options' );
		wp_cache_delete( 'alloptions', 'options' );

		if ( $structure !== (string) get_option( 'permalink_structure', '' ) ) {
			return new WP_Error(
				'seo_geo_manager_permalink_write_failed',
				'WordPress did not persist the requested permalink structure.',
				array( 'status' => 500 )
			);
		}

		return true;
	}

	/**
	 * @param array<string, string|bool> $normalized Normalized request.
	 */
	private static function payload_hash( array $normalized ): string {
		return hash( 'sha256', (string) wp_json_encode( $normalized ) );
	}

	/**
	 * @return array<string, mixed>|WP_Error
	 */
	private static function idempotent_operation( string $operation_id ) {
		$operation = OperationStore::get( $operation_id );
		if ( ! is_array( $operation ) || self::OPERATION_TYPE !== ( $operation['operation_type'] ?? '' ) ) {
			return new WP_Error(
				'seo_geo_manager_permalink_idempotent_result_missing',
				'The idempotency key exists but its permalink operation record is unavailable.',
				array( 'status' => 409 )
			);
		}
		$operation['idempotent_replay'] = true;

		return $operation;
	}

	/**
	 * @param array<string, string|bool> $normalized Normalized request.
	 */
	private static function save_failed_operation( string $operation_id, array $normalized, string $payload_hash, string $before_structure, string $after_structure, string $before_fingerprint, string $error ): void {
		OperationStore::save(
			$operation_id,
			EnvironmentPolicy::bind_operation(
				array(
					'operation_id'          => $operation_id,
					'operation_type'        => self::OPERATION_TYPE,
					'status'                => 'failed',
					'idempotency_key'       => $normalized['idempotency_key'],
					'payload_hash'          => $payload_hash,
					'authority_fingerprint' => $normalized['authority_fingerprint'],
					'plan_fingerprint'      => $normalized['plan_fingerprint'],
					'before_structure'      => $before_structure,
					'after_structure'       => $after_structure,
					'before_fingerprint'    => $before_fingerprint,
					'error'                 => $error,
					'created_at_gmt'        => gmdate( 'c' ),
				)
			)
		);
	}

	private static function structure_fingerprint( string $structure ): string {
		return hash(
			'sha256',
			(string) wp_json_encode(
				array(
					'home_url'            => home_url( '/' ),
					'site_url'            => site_url( '/' ),
					'permalink_structure' => $structure,
				)
			)
		);
	}
}
