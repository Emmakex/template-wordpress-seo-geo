<?php
/**
 * Allowlisted recovery descriptors for Manager operations.
 *
 * @package SeoGeoManager
 */

declare(strict_types=1);

namespace SeoGeo\Manager\Support;

final class OperationRecoveryRegistry {
	/**
	 * Build a privacy-safe descriptor that points only to existing, type-specific
	 * Verify/Rollback endpoints. This registry never executes a mutation.
	 *
	 * @param array<string, mixed> $operation Private stored operation.
	 * @return array<string, mixed>
	 */
	public static function describe( array $operation ): array {
		$operation_id   = isset( $operation['operation_id'] ) && is_string( $operation['operation_id'] ) ? $operation['operation_id'] : '';
		$operation_type = isset( $operation['operation_type'] ) && is_string( $operation['operation_type'] ) ? $operation['operation_type'] : '';
		$adapter        = isset( $operation['adapter'] ) && is_string( $operation['adapter'] ) ? $operation['adapter'] : '';
		$status         = isset( $operation['status'] ) && is_string( $operation['status'] ) ? $operation['status'] : '';
		$route_base     = self::route_base( $operation_type, $adapter );
		$supported      = '' !== $operation_id && null !== $route_base;
		$available      = $supported && 'applied' === $status;

		return array(
			'supported'      => $supported,
			'operation_type' => $operation_type,
			'status'         => $status,
			'verify'         => array(
				'available' => $available,
				'method'    => 'GET',
				'path'      => $supported ? $route_base . rawurlencode( $operation_id ) . '/verify' : null,
			),
			'rollback'       => array(
				'available'                        => $available,
				'method'                           => 'POST',
				'path'                             => $supported ? $route_base . rawurlencode( $operation_id ) . '/rollback' : null,
				'requires_environment_fingerprint' => true,
			),
			'policy'         => array(
				'allowlisted_operation_types_only'  => true,
				'type_specific_endpoints_only'      => true,
				'generic_mutation_endpoint'         => false,
				'generic_rollback_endpoint'         => false,
				'private_operation_payload_returned' => false,
			),
		);
	}

	private static function route_base( string $operation_type, string $adapter ): ?string {
		if ( 'seo-authority-change' === $operation_type && in_array( $adapter, array( 'seo-geo-theme-native', 'yoast-abilities' ), true ) ) {
			return '/seo-geo-manager/v1/content/seo/authority/changes/';
		}
		if ( 'contextual-link-change' === $operation_type && 'contextual-link' === $adapter ) {
			return '/seo-geo-manager/v1/links/contextual/changes/';
		}
		if ( 'navigation-change' === $operation_type && 'navigation-menu' === $adapter ) {
			return '/seo-geo-manager/v1/navigation/changes/';
		}
		if ( 'featured-media-change' === $operation_type && 'featured-media' === $adapter ) {
			return '/seo-geo-manager/v1/content/featured-media/changes/';
		}
		if ( 'media-alt-change' === $operation_type && 'media-alt' === $adapter ) {
			return '/seo-geo-manager/v1/media/alt/changes/';
		}

		return null;
	}
}
