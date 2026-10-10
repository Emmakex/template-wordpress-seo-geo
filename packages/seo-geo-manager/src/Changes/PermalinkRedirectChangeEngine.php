<?php
/**
 * Atomic authoritative permalink + 301 runtime apply and rollback engine.
 *
 * @package SeoGeoManager
 */

declare(strict_types=1);

namespace SeoGeo\Manager\Changes;

use SeoGeo\Manager\Support\AuthoritativePermalinkPlanner;
use SeoGeo\Manager\Support\EnvironmentPolicy;
use SeoGeo\Manager\Support\PermalinkRedirectRuntime;
use SeoGeo\Manager\Support\PermalinkStructureRuntime;
use WP_Error;

final class PermalinkRedirectChangeEngine {
	private const OPERATION_TYPE = 'permalink-structure-with-redirects';

	/**
	 * @param array<string, mixed> $payload Request payload.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function apply( array $payload ) {
		$normalized = self::normalize_payload( $payload );
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
		$redirects        = isset( $plan['redirects'] ) && is_array( $plan['redirects'] ) ? $plan['redirects'] : array();
		$runtime_preview  = PermalinkRedirectRuntime::preview(
			$redirects,
			$normalized['plan_fingerprint'],
			$after_structure
		);
		if ( true !== ( $runtime_preview['safe_to_activate'] ?? false ) ) {
			return new WP_Error(
				'seo_geo_manager_permalink_redirect_runtime_blocked',
				(string) ( $runtime_preview['block_reason'] ?? 'The verified 301 runtime cannot be armed safely.' ),
				array( 'status' => 409 )
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

		$before_fingerprint = PermalinkStructureRuntime::fingerprint( $before_structure );
		$runtime            = PermalinkRedirectRuntime::activate(
			$redirects,
			$operation_id,
			$normalized['plan_fingerprint'],
			$after_structure
		);
		if ( is_wp_error( $runtime ) ) {
			self::save_failure(
				$operation_id,
				$normalized,
				$payload_hash,
				$before_structure,
				$after_structure,
				$before_fingerprint,
				$runtime->get_error_message()
			);
			return $runtime;
		}

		$runtime_fingerprint = (string) ( $runtime['fingerprint'] ?? '' );
		if ( true === ( $runtime['effective'] ?? true ) ) {
			PermalinkRedirectRuntime::deactivate( $operation_id, $runtime_fingerprint );
			return new WP_Error(
				'seo_geo_manager_permalink_redirect_runtime_not_armed',
				'The redirect runtime became effective before the permalink structure changed.',
				array( 'status' => 409 )
			);
		}

		$write = PermalinkStructureRuntime::set( $after_structure );
		if ( is_wp_error( $write ) ) {
			PermalinkRedirectRuntime::deactivate( $operation_id, $runtime_fingerprint );
			self::save_failure(
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

		$verification_plan = AuthoritativePermalinkPlanner::preview(
			$normalized['legacy_base_url'],
			$normalized['authority_fingerprint']
		);
		$verification = self::verification_errors(
			$plan,
			$verification_plan,
			$after_structure,
			$operation_id,
			$normalized['plan_fingerprint'],
			$runtime_fingerprint
		);
		if ( array() !== $verification ) {
			PermalinkStructureRuntime::restore( $before_structure );
			PermalinkRedirectRuntime::deactivate( $operation_id, $runtime_fingerprint );
			OperationStore::save(
				$operation_id,
				EnvironmentPolicy::bind_operation(
					array(
						'operation_id'                 => $operation_id,
						'operation_type'               => self::OPERATION_TYPE,
						'status'                       => 'verification-failed-rolled-back',
						'idempotency_key'              => $normalized['idempotency_key'],
						'payload_hash'                 => $payload_hash,
						'authority_fingerprint'        => $normalized['authority_fingerprint'],
						'plan_fingerprint'             => $normalized['plan_fingerprint'],
						'before_structure'             => $before_structure,
						'after_structure'              => $after_structure,
						'before_fingerprint'           => $before_fingerprint,
						'redirect_runtime_fingerprint' => $runtime_fingerprint,
						'verification'                 => $verification,
						'created_at_gmt'               => gmdate( 'c' ),
					)
				)
			);

			return new WP_Error(
				'seo_geo_manager_permalink_redirect_verification_failed',
				'The permalink + 301 operation failed verification and was rolled back.',
				array(
					'status'       => 409,
					'operation_id' => $operation_id,
					'verification' => $verification,
				)
			);
		}

		$operation = EnvironmentPolicy::bind_operation(
			array(
				'operation_id'                 => $operation_id,
				'operation_type'               => self::OPERATION_TYPE,
				'status'                       => 'applied',
				'idempotency_key'              => $normalized['idempotency_key'],
				'payload_hash'                 => $payload_hash,
				'authority_fingerprint'        => $normalized['authority_fingerprint'],
				'plan_fingerprint'             => $normalized['plan_fingerprint'],
				'legacy_base_url'              => $normalized['legacy_base_url'],
				'before_structure'             => $before_structure,
				'after_structure'              => $after_structure,
				'before_fingerprint'           => $before_fingerprint,
				'after_fingerprint'            => PermalinkStructureRuntime::fingerprint( (string) get_option( 'permalink_structure', '' ) ),
				'seo_preservation_mode'        => (string) ( $plan['seo_preservation_mode'] ?? 'authoritative-301' ),
				'planned_redirects'            => count( $redirects ),
				'redirect_runtime_fingerprint' => $runtime_fingerprint,
				'redirect_runtime_effective'   => true,
				'created_at_gmt'               => gmdate( 'c' ),
				'idempotent_replay'            => false,
			)
		);
		if ( ! OperationStore::save( $operation_id, $operation ) ) {
			PermalinkStructureRuntime::restore( $before_structure );
			PermalinkRedirectRuntime::deactivate( $operation_id, $runtime_fingerprint );
			return new WP_Error(
				'seo_geo_manager_permalink_redirect_operation_log_failed',
				'The atomic operation could not be persisted and the previous state was restored.',
				array( 'status' => 500 )
			);
		}

		return $operation;
	}

	/**
	 * @return array<string, mixed>|WP_Error
	 */
	public static function rollback( string $operation_id ) {
		$operation = OperationStore::get( $operation_id );
		if ( ! is_array( $operation ) || self::OPERATION_TYPE !== ( $operation['operation_type'] ?? '' ) ) {
			return new WP_Error(
				'seo_geo_manager_permalink_redirect_operation_not_found',
				'Atomic permalink + 301 operation not found.',
				array( 'status' => 404 )
			);
		}
		if ( 'rolled-back' === ( $operation['status'] ?? '' ) ) {
			return $operation;
		}
		if ( 'applied' !== ( $operation['status'] ?? '' ) ) {
			return new WP_Error(
				'seo_geo_manager_permalink_redirect_rollback_unavailable',
				'This operation is not rollback-ready.',
				array( 'status' => 409 )
			);
		}

		$after_structure = (string) ( $operation['after_structure'] ?? '' );
		$after_expected  = (string) ( $operation['after_fingerprint'] ?? '' );
		$current         = (string) get_option( 'permalink_structure', '' );
		if (
			'' === $after_expected ||
			0 !== strcmp( $after_structure, $current ) ||
			! hash_equals( $after_expected, PermalinkStructureRuntime::fingerprint( $current ) )
		) {
			return new WP_Error(
				'seo_geo_manager_permalink_redirect_rollback_stale',
				'The permalink state changed after Apply; rollback is blocked.',
				array( 'status' => 409 )
			);
		}

		$runtime_fingerprint = (string) ( $operation['redirect_runtime_fingerprint'] ?? '' );
		$runtime             = PermalinkRedirectRuntime::snapshot();
		$runtime_operation   = (string) ( $runtime['operation_id'] ?? '' );
		if (
			true !== ( $runtime['active'] ?? false ) ||
			true !== ( $runtime['effective'] ?? false ) ||
			0 !== strcmp( $operation_id, $runtime_operation ) ||
			'' === $runtime_fingerprint ||
			! hash_equals( $runtime_fingerprint, (string) ( $runtime['fingerprint'] ?? '' ) )
		) {
			return new WP_Error(
				'seo_geo_manager_permalink_redirect_runtime_rollback_stale',
				'The 301 runtime changed after Apply; rollback is blocked.',
				array( 'status' => 409 )
			);
		}

		$before_structure = (string) ( $operation['before_structure'] ?? '' );
		$restore          = PermalinkStructureRuntime::restore( $before_structure );
		if ( is_wp_error( $restore ) ) {
			return $restore;
		}
		$cleanup = PermalinkRedirectRuntime::deactivate( $operation_id, $runtime_fingerprint );
		if ( is_wp_error( $cleanup ) ) {
			PermalinkStructureRuntime::set( $after_structure );
			return new WP_Error(
				'seo_geo_manager_permalink_redirect_rollback_cleanup_failed',
				'Runtime cleanup failed; the Manager attempted to restore the applied state.',
				array( 'status' => 500 )
			);
		}

		$restored = PermalinkStructureRuntime::fingerprint( (string) get_option( 'permalink_structure', '' ) );
		if (
			! hash_equals( (string) ( $operation['before_fingerprint'] ?? '' ), $restored ) ||
			true === ( PermalinkRedirectRuntime::snapshot()['active'] ?? false )
		) {
			return new WP_Error(
				'seo_geo_manager_permalink_redirect_rollback_verification_failed',
				'Atomic rollback could not be verified.',
				array( 'status' => 500 )
			);
		}

		$operation['status']                   = 'rolled-back';
		$operation['rolled_back_at_gmt']       = gmdate( 'c' );
		$operation['rollback_fingerprint']     = $restored;
		$operation['redirect_runtime_removed'] = true;
		OperationStore::save( $operation_id, $operation );

		return $operation;
	}

	/**
	 * @param array<string, mixed> $payload Request payload.
	 * @return array<string, string|bool>|WP_Error
	 */
	private static function normalize_payload( array $payload ) {
		$normalized = array(
			'legacy_base_url'          => isset( $payload['legacy_base_url'] ) && is_string( $payload['legacy_base_url'] ) ? trim( $payload['legacy_base_url'] ) : '',
			'authority_fingerprint'    => isset( $payload['authority_fingerprint'] ) && is_string( $payload['authority_fingerprint'] ) ? trim( $payload['authority_fingerprint'] ) : '',
			'plan_fingerprint'         => isset( $payload['plan_fingerprint'] ) && is_string( $payload['plan_fingerprint'] ) ? trim( $payload['plan_fingerprint'] ) : '',
			'current_fingerprint'      => isset( $payload['current_fingerprint'] ) && is_string( $payload['current_fingerprint'] ) ? trim( $payload['current_fingerprint'] ) : '',
			'idempotency_key'          => isset( $payload['idempotency_key'] ) && is_string( $payload['idempotency_key'] ) ? trim( $payload['idempotency_key'] ) : '',
			'confirm_permalink_change' => true === ( $payload['confirm_permalink_change'] ?? false ),
			'confirm_redirect_runtime' => true === ( $payload['confirm_redirect_runtime'] ?? false ),
		);
		foreach ( array( 'legacy_base_url', 'authority_fingerprint', 'plan_fingerprint', 'current_fingerprint', 'idempotency_key' ) as $required ) {
			if ( '' === $normalized[ $required ] ) {
				return new WP_Error(
					'seo_geo_manager_permalink_redirect_payload_invalid',
					'Atomic Apply is missing required inspected-state fields.',
					array( 'status' => 400 )
				);
			}
		}
		if ( 128 < strlen( (string) $normalized['idempotency_key'] ) ) {
			return new WP_Error(
				'seo_geo_manager_permalink_redirect_idempotency_invalid',
				'Idempotency keys are limited to 128 characters.',
				array( 'status' => 400 )
			);
		}
		if ( true !== $normalized['confirm_permalink_change'] || true !== $normalized['confirm_redirect_runtime'] ) {
			return new WP_Error(
				'seo_geo_manager_permalink_redirect_confirmation_required',
				'Atomic Apply requires explicit permalink and 301-runtime approval.',
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
		if (
			true !== ( $plan['safe_structure_candidate'] ?? false ) ||
			true !== ( $plan['requires_redirect_runtime'] ?? false ) ||
			1 > (int) ( $plan['planned_redirects'] ?? 0 )
		) {
			return new WP_Error(
				'seo_geo_manager_permalink_redirect_plan_blocked',
				'This plan is not an authoritative redirect-required candidate.',
				array( 'status' => 409 )
			);
		}

		$plan_fingerprint    = (string) ( $plan['plan_fingerprint'] ?? '' );
		$current_fingerprint = (string) ( $plan['current_fingerprint'] ?? '' );
		if (
			'' === $plan_fingerprint ||
			! hash_equals( $plan_fingerprint, (string) $normalized['plan_fingerprint'] ) ||
			'' === $current_fingerprint ||
			! hash_equals( $current_fingerprint, (string) $normalized['current_fingerprint'] )
		) {
			return new WP_Error(
				'seo_geo_manager_permalink_redirect_plan_stale',
				'The inspected permalink plan or current state changed.',
				array( 'status' => 409 )
			);
		}

		$current_structure = (string) ( $plan['current_structure'] ?? '' );
		$target_structure  = (string) ( $plan['authoritative_structure'] ?? '' );
		if ( 0 === strcmp( $current_structure, $target_structure ) ) {
			return new WP_Error(
				'seo_geo_manager_permalink_redirect_structure_unchanged',
				'The authoritative structure is already active.',
				array( 'status' => 400 )
			);
		}

		return true;
	}

	/**
	 * @param array<string, mixed> $original Original plan.
	 * @param array<string, mixed> $verified Revalidated plan.
	 * @return array<int, string>
	 */
	private static function verification_errors( array $original, array $verified, string $structure, string $operation_id, string $plan_fingerprint, string $runtime_fingerprint ): array {
		$errors             = array();
		$current_structure  = (string) get_option( 'permalink_structure', '' );
		$original_mode      = (string) ( $original['seo_preservation_mode'] ?? '' );
		$verified_mode      = (string) ( $verified['seo_preservation_mode'] ?? '' );
		$supported_301_mode = in_array( $original_mode, array( 'authoritative-301', 'one-hop-301-to-clean-target' ), true );
		$mode_stable        = $supported_301_mode && 0 === strcmp( $original_mode, $verified_mode );
		if (
			0 !== strcmp( $structure, $current_structure ) ||
			true !== ( $verified['safe_structure_candidate'] ?? false ) ||
			! $mode_stable
		) {
			$errors[] = 'authoritative-structure-verification-failed';
		}

		$before_redirects = isset( $original['redirects'] ) && is_array( $original['redirects'] ) ? $original['redirects'] : array();
		$after_redirects  = isset( $verified['redirects'] ) && is_array( $verified['redirects'] ) ? $verified['redirects'] : array();
		if ( 0 !== strcmp( (string) wp_json_encode( $before_redirects ), (string) wp_json_encode( $after_redirects ) ) ) {
			$errors[] = 'authoritative-redirect-map-changed';
		}

		$runtime           = PermalinkRedirectRuntime::snapshot();
		$runtime_operation = (string) ( $runtime['operation_id'] ?? '' );
		$runtime_plan      = (string) ( $runtime['plan_fingerprint'] ?? '' );
		if (
			true !== ( $runtime['active'] ?? false ) ||
			true !== ( $runtime['effective'] ?? false ) ||
			0 !== strcmp( $operation_id, $runtime_operation ) ||
			0 !== strcmp( $plan_fingerprint, $runtime_plan ) ||
			'' === $runtime_fingerprint ||
			! hash_equals( $runtime_fingerprint, (string) ( $runtime['fingerprint'] ?? '' ) )
		) {
			$errors[] = 'redirect-runtime-verification-failed';
		}

		foreach ( $before_redirects as $redirect ) {
			if ( ! is_array( $redirect ) ) {
				$errors[] = 'redirect-runtime-invalid-row';
				continue;
			}
			$source_url  = home_url( (string) ( $redirect['source_path'] ?? '' ) );
			$request_uri = wp_parse_url( $source_url, PHP_URL_PATH );
			$resolved    = is_string( $request_uri ) ? PermalinkRedirectRuntime::resolve( $request_uri ) : null;
			$target_path = (string) ( $redirect['target_path'] ?? '' );
			$resolved_target = is_array( $resolved ) ? (string) ( $resolved['target_path'] ?? '' ) : '';
			if ( ! is_array( $resolved ) || 301 !== (int) ( $resolved['status'] ?? 0 ) || 0 !== strcmp( $target_path, $resolved_target ) ) {
				$errors[] = 'redirect-resolution-mismatch';
				break;
			}
		}

		return $errors;
	}

	/**
	 * @return array<string, mixed>|WP_Error
	 */
	private static function idempotent_operation( string $operation_id ) {
		$operation = OperationStore::get( $operation_id );
		if ( ! is_array( $operation ) || self::OPERATION_TYPE !== ( $operation['operation_type'] ?? '' ) ) {
			return new WP_Error(
				'seo_geo_manager_permalink_redirect_idempotent_result_missing',
				'The reserved operation record is unavailable.',
				array( 'status' => 409 )
			);
		}
		$operation['idempotent_replay'] = true;

		return $operation;
	}

	/**
	 * @param array<string, string|bool> $normalized Normalized request.
	 */
	private static function save_failure( string $operation_id, array $normalized, string $payload_hash, string $before_structure, string $after_structure, string $before_fingerprint, string $error ): void {
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
}
