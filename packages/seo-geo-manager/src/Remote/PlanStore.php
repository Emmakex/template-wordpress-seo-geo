<?php
/**
 * Short-lived private plan storage for authenticated remote Manager mutations.
 *
 * @package SeoGeoManager
 */

declare(strict_types=1);

namespace SeoGeo\Manager\Remote;

use SeoGeo\Manager\Support\EnvironmentPolicy;
use WP_Error;

final class PlanStore {
	private const PLAN_PREFIX         = 'sgm_remote_plan_';
	private const APPLY_TTL_SECONDS   = 15 * MINUTE_IN_SECONDS;
	private const STORAGE_TTL_SECONDS = HOUR_IN_SECONDS;

	/**
	 * @param array<string, mixed> $payload Exact semantic Manager payload.
	 * @param array<string, mixed> $preview Exact Manager preview returned for the payload.
	 * @return array<string, mixed>|WP_Error
	 */
	public function create( string $connection_id, int $wordpress_user_id, array $payload, array $preview ) {
		if ( '' === $connection_id || 1 > $wordpress_user_id ) {
			return new WP_Error(
				'seo_geo_manager_remote_plan_identity_invalid',
				'Remote mutation plans require a bound connection and WordPress execution identity.',
				array( 'status' => 400 )
			);
		}

		$created = time();
		$plan    = array(
			'plan_id'           => 'sgmp_' . str_replace( '-', '', wp_generate_uuid4() ),
			'operation'         => 'content-change',
			'status'            => ! empty( $preview['has_changes'] ) ? 'prepared' : 'already-matching',
			'connection_id'     => $connection_id,
			'wordpress_user_id' => $wordpress_user_id,
			'payload'           => $payload,
			'preview'           => $preview,
			'environment'       => EnvironmentPolicy::snapshot(),
			'created_at'        => gmdate( DATE_W3C, $created ),
			'expires_at'        => gmdate( DATE_W3C, $created + self::APPLY_TTL_SECONDS ),
			'expires_at_unix'   => $created + self::APPLY_TTL_SECONDS,
			'operation_id'      => '',
			'applied_at'        => '',
		);
		$plan['checksum'] = $this->checksum( $plan );

		if ( ! $this->save( $plan ) ) {
			return new WP_Error(
				'seo_geo_manager_remote_plan_store_failed',
				'The remote Manager plan could not be stored.',
				array( 'status' => 500 )
			);
		}

		return $plan;
	}

	/**
	 * @return array<string, mixed>|WP_Error
	 */
	public function get( string $plan_id ) {
		$value = get_transient( $this->transient_name( $plan_id ) );
		if ( ! is_array( $value ) ) {
			return new WP_Error(
				'seo_geo_manager_remote_plan_not_found',
				'Remote Manager plan not found or no longer retained.',
				array( 'status' => 404 )
			);
		}

		$expected = isset( $value['checksum'] ) && is_string( $value['checksum'] ) ? $value['checksum'] : '';
		if ( '' === $expected || ! hash_equals( $expected, $this->checksum( $value ) ) ) {
			return new WP_Error(
				'seo_geo_manager_remote_plan_integrity_failed',
				'Remote Manager plan integrity verification failed.',
				array( 'status' => 409 )
			);
		}

		$status  = isset( $value['status'] ) && is_string( $value['status'] ) ? $value['status'] : '';
		$expires = isset( $value['expires_at_unix'] ) ? (int) $value['expires_at_unix'] : 0;
		if ( 'prepared' === $status && 0 < $expires && time() > $expires ) {
			$value['status'] = 'expired';
			$this->save( $value );
		}

		return $value;
	}

	/**
	 * @param array<string, mixed> $plan Plan state.
	 */
	public function save( array $plan ): bool {
		$plan_id = isset( $plan['plan_id'] ) && is_string( $plan['plan_id'] ) ? $plan['plan_id'] : '';
		if ( ! preg_match( '/^sgmp_[a-f0-9]{32}$/', $plan_id ) ) {
			return false;
		}

		set_transient( $this->transient_name( $plan_id ), $plan, self::STORAGE_TTL_SECONDS );

		return true;
	}

	/**
	 * Mark a plan as applied without changing its immutable preview/payload contract.
	 *
	 * @param array<string, mixed> $plan Existing plan.
	 * @return array<string, mixed>|WP_Error
	 */
	public function mark_applied( array $plan, string $operation_id ): array|WP_Error {
		if ( ! preg_match( '/^[a-f0-9\-]{36}$/', $operation_id ) ) {
			return new WP_Error(
				'seo_geo_manager_remote_operation_id_invalid',
				'Remote Manager apply did not return a valid operation ID.',
				array( 'status' => 500 )
			);
		}

		$plan['status']       = 'applied';
		$plan['operation_id'] = $operation_id;
		$plan['applied_at']   = gmdate( DATE_W3C );

		if ( ! $this->save( $plan ) ) {
			return new WP_Error(
				'seo_geo_manager_remote_plan_store_failed',
				'The applied remote Manager plan could not be persisted.',
				array( 'status' => 500 )
			);
		}

		return $plan;
	}

	/**
	 * Immutable plan checksum. Mutable lifecycle fields are deliberately excluded.
	 *
	 * @param array<string, mixed> $plan Plan state.
	 */
	private function checksum( array $plan ): string {
		$immutable = array(
			'plan_id'           => $plan['plan_id'] ?? '',
			'operation'         => $plan['operation'] ?? '',
			'connection_id'     => $plan['connection_id'] ?? '',
			'wordpress_user_id' => $plan['wordpress_user_id'] ?? 0,
			'payload'           => $plan['payload'] ?? array(),
			'preview'           => $plan['preview'] ?? array(),
			'environment'       => $plan['environment'] ?? array(),
			'created_at'        => $plan['created_at'] ?? '',
			'expires_at'        => $plan['expires_at'] ?? '',
			'expires_at_unix'   => $plan['expires_at_unix'] ?? 0,
		);

		return hash( 'sha256', wp_json_encode( $immutable ) ?: '' );
	}

	private function transient_name( string $plan_id ): string {
		return self::PLAN_PREFIX . substr( hash( 'sha256', $plan_id ), 0, 40 );
	}
}
