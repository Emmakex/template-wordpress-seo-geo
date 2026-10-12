<?php
/**
 * Explicit environment approval policy for Manager mutations.
 *
 * @package SeoGeoManager
 */

declare(strict_types=1);

namespace SeoGeo\Manager\Support;

use WP_Error;

final class EnvironmentPolicy {
	private const PRODUCTION = 'production';

	/**
	 * Return the bounded environment contract exposed to authenticated callers.
	 *
	 * @return array{type:string,fingerprint:string,write_approval_required:bool}
	 */
	public static function snapshot(): array {
		return array(
			'type'                    => self::environment_type(),
			'fingerprint'             => self::fingerprint(),
			'write_approval_required' => self::requires_write_approval(),
		);
	}

	/**
	 * Validate the caller's acknowledgement of the current environment.
	 *
	 * Production mutations require the exact fingerprint obtained during
	 * inspection. Non-production environments remain draft-first but do not
	 * require this extra acknowledgement.
	 *
	 * @param array<string, mixed> $payload Request payload.
	 * @return true|WP_Error
	 */
	public static function validate_payload( array $payload ) {
		if ( ! self::requires_write_approval() ) {
			return true;
		}

		$provided = isset( $payload['environment_fingerprint'] ) && is_string( $payload['environment_fingerprint'] )
			? trim( $payload['environment_fingerprint'] )
			: '';
		$current  = self::fingerprint();

		if ( '' === $provided || ! hash_equals( $current, $provided ) ) {
			return new WP_Error(
				'seo_geo_manager_environment_approval_required',
				'Production writes require the current environment fingerprint obtained from /site/snapshot.',
				array(
					'status'                      => 409,
					'environment_type'            => self::environment_type(),
					'current_environment_fingerprint' => $current,
				)
			);
		}

		return true;
	}

	/**
	 * Bind an operation to the environment where it was applied.
	 *
	 * @param array<string, mixed> $operation Operation record.
	 * @return array<string, mixed>
	 */
	public static function bind_operation( array $operation ): array {
		$operation['environment'] = self::snapshot();

		return $operation;
	}

	/**
	 * Refuse production rollback when an operation belongs to another or an
	 * unbound environment. This prevents imported operation metadata from being
	 * replayed blindly after a clone/migration.
	 *
	 * @param array<string, mixed> $operation Operation record.
	 * @return true|WP_Error
	 */
	public static function validate_operation_environment( array $operation ) {
		if ( ! self::requires_write_approval() ) {
			return true;
		}

		$environment = isset( $operation['environment'] ) && is_array( $operation['environment'] ) ? $operation['environment'] : array();
		$stored      = isset( $environment['fingerprint'] ) && is_string( $environment['fingerprint'] ) ? $environment['fingerprint'] : '';
		$current     = self::fingerprint();

		if ( '' === $stored ) {
			return new WP_Error(
				'seo_geo_manager_operation_environment_unbound',
				'Production rollback is blocked because this operation predates environment binding.',
				array(
					'status' => 409,
				)
			);
		}

		if ( ! hash_equals( $stored, $current ) ) {
			return new WP_Error(
				'seo_geo_manager_operation_environment_changed',
				'Production rollback is blocked because the operation belongs to a different environment.',
				array(
					'status'                      => 409,
					'operation_environment_fingerprint' => $stored,
					'current_environment_fingerprint'   => $current,
				)
			);
		}

		return true;
	}

	private static function requires_write_approval(): bool {
		return self::PRODUCTION === self::environment_type();
	}

	private static function environment_type(): string {
		$type = function_exists( 'wp_get_environment_type' ) ? wp_get_environment_type() : self::PRODUCTION;

		return is_string( $type ) && '' !== $type ? sanitize_key( $type ) : self::PRODUCTION;
	}

	private static function fingerprint(): string {
		$identity = implode(
			'|',
			array(
				self::environment_type(),
				untrailingslashit( home_url( '/' ) ),
				untrailingslashit( site_url( '/' ) ),
			)
		);

		return hash( 'sha256', $identity );
	}
}
