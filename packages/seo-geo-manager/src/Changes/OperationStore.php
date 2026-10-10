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
	private const HISTORY_OPTION     = 'seo_geo_manager_operation_history';
	private const HISTORY_LIMIT      = 100;

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
	 * Persist one full private operation and refresh its bounded safe-history row.
	 *
	 * @param array<string, mixed> $operation Operation record.
	 */
	public static function save( string $operation_id, array $operation ): bool {
		$option_name = self::operation_option_name( $operation_id );
		$saved       = update_option( $option_name, $operation, false );
		if ( ! $saved ) {
			$stored = get_option( $option_name, null );
			if ( ! is_array( $stored ) || $stored !== $operation ) {
				return false;
			}
		}

		self::index_operation( $operation_id, $operation );

		return true;
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public static function get( string $operation_id ): ?array {
		$value = get_option( self::operation_option_name( $operation_id ), null );

		return is_array( $value ) ? $value : null;
	}

	/**
	 * Return recent privacy-bounded operation summaries.
	 *
	 * The index intentionally excludes content bodies, previous values, requested
	 * values, payload hashes, rendered response bodies/digests and environment
	 * fingerprints. Full operation records remain private and are read only by the
	 * mutation/rollback engines.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function recent_summaries( int $limit = 20 ): array {
		$limit   = max( 1, min( self::HISTORY_LIMIT, $limit ) );
		$history = get_option( self::HISTORY_OPTION, array() );
		if ( ! is_array( $history ) ) {
			return array();
		}

		$rows = array_values( array_filter( $history, 'is_array' ) );

		return array_slice( $rows, 0, $limit );
	}

	/**
	 * @param array<string, mixed> $operation Full private operation.
	 */
	private static function index_operation( string $operation_id, array $operation ): void {
		$history = get_option( self::HISTORY_OPTION, array() );
		$history = is_array( $history ) ? $history : array();
		$next    = array( self::summary( $operation_id, $operation ) );

		foreach ( $history as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$existing_id = isset( $row['operation_id'] ) && is_string( $row['operation_id'] ) ? $row['operation_id'] : '';
			if ( $operation_id === $existing_id ) {
				continue;
			}
			$next[] = $row;
			if ( self::HISTORY_LIMIT <= count( $next ) ) {
				break;
			}
		}

		update_option( self::HISTORY_OPTION, $next, false );
	}

	/**
	 * Build a safe operation-history summary without mutation payload content.
	 *
	 * @param array<string, mixed> $operation Full private operation.
	 * @return array<string, mixed>
	 */
	private static function summary( string $operation_id, array $operation ): array {
		$type = isset( $operation['operation_type'] ) && is_string( $operation['operation_type'] )
			? sanitize_key( $operation['operation_type'] )
			: 'content-change';
		if ( 'content-change' === $type && isset( $operation['structured_model'] ) && is_array( $operation['structured_model'] ) ) {
			$type = 'theme-structured-content';
		}

		$changes = isset( $operation['changes'] ) && is_array( $operation['changes'] )
			? array_keys( $operation['changes'] )
			: array();
		$changed_fields = array_values(
			array_filter(
				array_map(
					static fn ( $field ): string => is_string( $field ) ? sanitize_key( $field ) : '',
					$changes
				)
			)
		);

		$environment           = isset( $operation['environment'] ) && is_array( $operation['environment'] ) ? $operation['environment'] : array();
		$structured_model      = isset( $operation['structured_model'] ) && is_array( $operation['structured_model'] ) ? $operation['structured_model'] : array();
		$rendered_verification = isset( $operation['rendered_verification'] ) && is_array( $operation['rendered_verification'] ) ? $operation['rendered_verification'] : array();
		$status                = isset( $operation['status'] ) && is_string( $operation['status'] ) ? sanitize_key( $operation['status'] ) : 'unknown';
		$rendered_status       = isset( $rendered_verification['status'] ) && is_string( $rendered_verification['status'] ) ? sanitize_key( $rendered_verification['status'] ) : '';
		$rollback_state        = 'unavailable';
		if ( in_array( $status, array( 'applied', 'verification-failed', 'rendered-verification-failed' ), true ) ) {
			$rollback_state = 'guarded';
		} elseif ( 'rolled-back' === $status ) {
			$rollback_state = 'completed';
		}

		return array(
			'operation_id'                => $operation_id,
			'operation_type'              => $type,
			'status'                      => $status,
			'target_id'                   => isset( $operation['target_id'] ) ? absint( $operation['target_id'] ) : 0,
			'target_type'                 => isset( $operation['target_type'] ) && is_string( $operation['target_type'] ) ? sanitize_key( $operation['target_type'] ) : '',
			'changed_fields'              => $changed_fields,
			'structured_model'            => isset( $structured_model['model_id'] ) && is_string( $structured_model['model_id'] ) ? sanitize_text_field( $structured_model['model_id'] ) : '',
			'planned_redirects'           => isset( $operation['planned_redirects'] ) ? max( 0, (int) $operation['planned_redirects'] ) : 0,
			'rendered_verification_status' => $rendered_status,
			'environment_type'            => isset( $environment['type'] ) && is_string( $environment['type'] ) ? sanitize_key( $environment['type'] ) : '',
			'created_at_gmt'              => isset( $operation['created_at_gmt'] ) && is_string( $operation['created_at_gmt'] ) ? sanitize_text_field( $operation['created_at_gmt'] ) : '',
			'rolled_back_at_gmt'          => isset( $operation['rolled_back_at_gmt'] ) && is_string( $operation['rolled_back_at_gmt'] ) ? sanitize_text_field( $operation['rolled_back_at_gmt'] ) : '',
			'rollback_state'              => $rollback_state,
		);
	}

	private static function idempotency_option_name( string $idempotency_key ): string {
		return self::IDEMPOTENCY_PREFIX . hash( 'sha256', $idempotency_key );
	}

	private static function operation_option_name( string $operation_id ): string {
		return self::OPERATION_PREFIX . hash( 'sha256', $operation_id );
	}
}
