<?php
/**
 * Bounded Manager operation storage and idempotency reservations.
 *
 * @package SeoGeoManager
 */

declare(strict_types=1);

namespace SeoGeo\Manager\Changes;

use WP_Error;

final class OperationStore {
	private const IDEMPOTENCY_PREFIX = 'seo_geo_manager_idem_';
	private const OPERATION_PREFIX   = 'seo_geo_manager_op_';

	/**
	 * Look up an existing idempotency key before validating mutable target state.
	 *
	 * @return array{existing:bool,operation_id:string}|WP_Error
	 */
	public static function lookup( string $idempotency_key, string $payload_hash ) {
		$existing = get_option( self::idempotency_option_name( $idempotency_key ), null );
		if ( null === $existing || false === $existing ) {
			return array(
				'existing'     => false,
				'operation_id' => '',
			);
		}

		return self::validate_existing( $existing, $payload_hash );
	}

	/**
	 * Reserve one idempotency key atomically.
	 *
	 * @return array{existing:bool,operation_id:string}|WP_Error
	 */
	public static function reserve( string $idempotency_key, string $payload_hash, string $operation_id ) {
		$option_name = self::idempotency_option_name( $idempotency_key );
		$record      = array(
			'operation_id' => $operation_id,
			'payload_hash' => $payload_hash,
		);

		if ( add_option( $option_name, $record, '', false ) ) {
			return array(
				'existing'     => false,
				'operation_id' => $operation_id,
			);
		}

		$existing = get_option( $option_name, null );

		return self::validate_existing( $existing, $payload_hash );
	}

	/**
	 * @param mixed $existing Stored idempotency record.
	 * @return array{existing:bool,operation_id:string}|WP_Error
	 */
	private static function validate_existing( $existing, string $payload_hash ) {
		if ( ! is_array( $existing ) ) {
			return new WP_Error(
				'seo_geo_manager_idempotency_unavailable',
				'Could not read the reserved idempotency key.',
				array( 'status' => 409 )
			);
		}

		$existing_id   = isset( $existing['operation_id'] ) && is_string( $existing['operation_id'] ) ? $existing['operation_id'] : '';
		$existing_hash = isset( $existing['payload_hash'] ) && is_string( $existing['payload_hash'] ) ? $existing['payload_hash'] : '';
		if ( '' === $existing_id || '' === $existing_hash || ! hash_equals( $existing_hash, $payload_hash ) ) {
			return new WP_Error(
				'seo_geo_manager_idempotency_conflict',
				'The idempotency key has already been used with a different payload.',
				array( 'status' => 409 )
			);
		}

		return array(
			'existing'     => true,
			'operation_id' => $existing_id,
		);
	}

	/**
	 * @param array<string, mixed> $operation Operation record.
	 */
	public static function save( string $operation_id, array $operation ): bool {
		return update_option( self::operation_option_name( $operation_id ), $operation, false );
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public static function get( string $operation_id ): ?array {
		$value = get_option( self::operation_option_name( $operation_id ), null );

		return is_array( $value ) ? $value : null;
	}

	private static function idempotency_option_name( string $idempotency_key ): string {
		return self::IDEMPOTENCY_PREFIX . hash( 'sha256', $idempotency_key );
	}

	private static function operation_option_name( string $operation_id ): string {
		return self::OPERATION_PREFIX . hash( 'sha256', $operation_id );
	}
}
