<?php
/**
 * Privacy-bounded Manager operation history endpoint.
 *
 * @package SeoGeoManager
 */

declare(strict_types=1);

namespace SeoGeo\Manager\Rest;

use SeoGeo\Manager\Changes\OperationStore;
use SeoGeo\Manager\Support\OperationRecoveryRegistry;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

final class OperationHistoryController {
	private const NAMESPACE = 'seo-geo-manager/v1';

	public static function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/operations',
			array(
				'methods'             => 'GET',
				'callback'            => array( self::class, 'index' ),
				'permission_callback' => array( self::class, 'can_read' ),
				'args'                => array(
					'per_page' => array(
						'default'           => 20,
						'sanitize_callback' => 'absint',
						'validate_callback' => static fn ( $value ): bool => 1 <= (int) $value && 50 >= (int) $value,
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/operations/(?P<operation_id>[a-f0-9\-]{36})/recovery',
			array(
				'methods'             => 'GET',
				'callback'            => array( self::class, 'recovery' ),
				'permission_callback' => array( self::class, 'can_read' ),
				'args'                => array(
					'operation_id' => array( 'sanitize_callback' => 'sanitize_text_field' ),
				),
			)
		);
	}

	public static function can_read(): bool {
		return current_user_can( 'edit_posts' );
	}

	public static function index( WP_REST_Request $request ): WP_REST_Response {
		$per_page = max( 1, min( 50, (int) $request->get_param( 'per_page' ) ) );
		$rows     = OperationStore::recent_summaries( 100 );
		$visible  = array();

		foreach ( $rows as $row ) {
			if ( ! self::can_view_row( $row ) ) {
				continue;
			}
			$visible[] = $row;
			if ( $per_page <= count( $visible ) ) {
				break;
			}
		}

		return new WP_REST_Response(
			array(
				'mode'         => 'operation-history-summary',
				'privacy_safe' => true,
				'count'        => count( $visible ),
				'items'        => $visible,
				'policy'       => array(
					'bounded_index'                    => true,
					'full_operation_payloads_excluded' => true,
					'content_values_excluded'          => true,
					'payload_hashes_excluded'          => true,
					'environment_fingerprints_excluded' => true,
				),
			),
			200
		);
	}

	/** @return WP_REST_Response|WP_Error */
	public static function recovery( WP_REST_Request $request ) {
		$operation_id = (string) $request->get_param( 'operation_id' );
		$row          = self::visible_row( $operation_id );
		if ( null === $row ) {
			return new WP_Error(
				'seo_geo_manager_operation_recovery_not_found',
				'Operation recovery evidence is unavailable or not visible to this user.',
				array( 'status' => 404 )
			);
		}

		$operation = OperationStore::get( $operation_id );
		if ( ! is_array( $operation ) ) {
			return new WP_Error(
				'seo_geo_manager_operation_recovery_missing',
				'The private operation record is no longer available.',
				array( 'status' => 404 )
			);
		}

		return new WP_REST_Response(
			array(
				'mode'         => 'operation-recovery-manifest',
				'privacy_safe' => true,
				'operation'    => array(
					'operation_id'   => $operation_id,
					'operation_type' => (string) ( $row['operation_type'] ?? '' ),
					'status'         => (string) ( $row['status'] ?? '' ),
					'target_id'      => (int) ( $row['target_id'] ?? 0 ),
					'target_type'    => (string) ( $row['target_type'] ?? '' ),
					'rollback_state' => (string) ( $row['rollback_state'] ?? 'unavailable' ),
				),
				'recovery'     => OperationRecoveryRegistry::describe( $operation ),
				'policy'       => array(
					'visible_history_only'             => true,
					'type_specific_endpoints_only'     => true,
					'generic_mutation_endpoint'        => false,
					'generic_rollback_endpoint'        => false,
					'full_operation_payload_excluded'  => true,
					'private_fingerprints_excluded'    => true,
					'environment_fingerprint_excluded' => true,
				),
			),
			200
		);
	}

	/**
	 * Site-wide operations require manage_options. Content-scoped operations may
	 * be seen by users who can still edit the target resource.
	 *
	 * @param array<string, mixed> $row History summary.
	 */
	private static function can_view_row( array $row ): bool {
		if ( current_user_can( 'manage_options' ) ) {
			return true;
		}

		$target_id = isset( $row['target_id'] ) ? absint( $row['target_id'] ) : 0;

		return 0 < $target_id && current_user_can( 'edit_post', $target_id );
	}

	/** @return array<string, mixed>|null */
	private static function visible_row( string $operation_id ): ?array {
		foreach ( OperationStore::recent_summaries( 100 ) as $row ) {
			if ( ( $row['operation_id'] ?? '' ) !== $operation_id || ! self::can_view_row( $row ) ) {
				continue;
			}

			return $row;
		}

		return null;
	}
}
