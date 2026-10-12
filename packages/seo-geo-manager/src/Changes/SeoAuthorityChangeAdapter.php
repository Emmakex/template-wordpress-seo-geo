<?php
/**
 * Unified SEO authority mutation dispatcher.
 *
 * @package SeoGeoManager
 */

declare(strict_types=1);

namespace SeoGeo\Manager\Changes;

use SeoGeo\Manager\Intelligence\SeoAuthorityScanner;
use WP_Error;

/**
 * Resolve the currently accepted SEO authority and delegate to its bounded adapter.
 *
 * The caller never selects the provider. This prevents a remote client from bypassing
 * the single-authority contract or targeting private provider metadata directly.
 */
final class SeoAuthorityChangeAdapter {
	private const NATIVE_AUTHORITY = 'seo-geo-theme';
	private const NATIVE_ADAPTER   = 'seo-geo-theme-native';
	private const YOAST_AUTHORITY  = 'yoast';
	private const YOAST_ADAPTER    = 'yoast-abilities';

	/**
	 * @param array<string, mixed> $payload Exact adapter payload for the resolved authority.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function preview( array $payload ) {
		$authority = self::resolve_authority();
		if ( is_wp_error( $authority ) ) {
			return $authority;
		}

		$result = self::NATIVE_AUTHORITY === $authority['authority_id']
			? NativeSeoMetadataChangeAdapter::preview( $payload )
			: YoastSeoMetadataChangeAdapter::preview( $payload );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$result['remote_seo_authority'] = self::bounded_authority( $authority, $result );

		return $result;
	}

	/**
	 * @param array<string, mixed> $payload Exact adapter payload for the resolved authority.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function apply( array $payload ) {
		$authority = self::resolve_authority();
		if ( is_wp_error( $authority ) ) {
			return $authority;
		}

		$result = self::NATIVE_AUTHORITY === $authority['authority_id']
			? NativeSeoMetadataChangeAdapter::apply( $payload )
			: YoastSeoMetadataChangeAdapter::apply( $payload );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$result['remote_seo_authority'] = self::bounded_authority( $authority, $result );

		return $result;
	}

	/**
	 * Verify the stored provider state using the exact adapter that created the operation.
	 *
	 * @param array<string, mixed> $operation Stored Manager operation.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function verify( array $operation ) {
		$resource_id = isset( $operation['resource_id'] ) ? absint( $operation['resource_id'] ) : 0;
		$adapter     = isset( $operation['adapter'] ) && is_string( $operation['adapter'] ) ? $operation['adapter'] : '';
		$expected    = isset( $operation['after_fingerprint'] ) && is_string( $operation['after_fingerprint'] ) ? $operation['after_fingerprint'] : '';
		if ( 1 > $resource_id || '' === $expected ) {
			return new WP_Error(
				'seo_geo_manager_remote_seo_verify_state_invalid',
				'Remote SEO verification state is incomplete.',
				array( 'status' => 409 )
			);
		}

		if ( self::NATIVE_ADAPTER === $adapter ) {
			$inspection = NativeSeoMetadataChangeAdapter::inspect( $resource_id );
		} elseif ( self::YOAST_ADAPTER === $adapter ) {
			$inspection = YoastSeoMetadataChangeAdapter::inspect( $resource_id );
		} else {
			return self::unsupported_adapter();
		}

		if ( is_wp_error( $inspection ) ) {
			return $inspection;
		}

		$current = isset( $inspection['fingerprint'] ) && is_string( $inspection['fingerprint'] ) ? $inspection['fingerprint'] : '';
		if ( '' === $current || ! hash_equals( $expected, $current ) ) {
			return new WP_Error(
				'seo_geo_manager_remote_verification_stale',
				'The SEO authority state no longer matches the applied remote operation.',
				array( 'status' => 409 )
			);
		}

		return array(
			'verified'            => true,
			'current_fingerprint' => $current,
			'seo_authority'       => self::operation_authority( $operation ),
		);
	}

	/**
	 * Roll back through the exact adapter that created the operation.
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	public static function rollback( string $operation_id ) {
		$operation = OperationStore::get( $operation_id );
		if ( ! is_array( $operation ) ) {
			return new WP_Error(
				'seo_geo_manager_operation_not_found',
				'Manager operation not found.',
				array( 'status' => 404 )
			);
		}

		$adapter = isset( $operation['adapter'] ) && is_string( $operation['adapter'] ) ? $operation['adapter'] : '';
		if ( self::NATIVE_ADAPTER === $adapter ) {
			return NativeSeoMetadataChangeAdapter::rollback( $operation_id );
		}
		if ( self::YOAST_ADAPTER === $adapter ) {
			return YoastSeoMetadataChangeAdapter::rollback( $operation_id );
		}

		return self::unsupported_adapter();
	}

	/**
	 * @return array{authority_id:string,adapter:string,provider:string}|WP_Error
	 */
	private static function resolve_authority() {
		$scan      = SeoAuthorityScanner::scan();
		$authority = isset( $scan['authority'] ) && is_array( $scan['authority'] ) ? $scan['authority'] : array();
		$id        = isset( $authority['id'] ) && is_string( $authority['id'] ) ? sanitize_key( $authority['id'] ) : '';
		$adapter   = isset( $authority['adapter'] ) && is_string( $authority['adapter'] ) ? sanitize_key( $authority['adapter'] ) : '';

		if ( true !== ( $scan['authority_resolved'] ?? false ) || true === ( $scan['conflict'] ?? false ) ) {
			return new WP_Error(
				'seo_geo_manager_remote_seo_authority_unresolved',
				'Remote SEO writes require one resolved SEO authority with no provider conflict.',
				array( 'status' => 409 )
			);
		}

		if ( self::NATIVE_AUTHORITY === $id && self::NATIVE_ADAPTER === $adapter ) {
			return array(
				'authority_id' => $id,
				'adapter'      => $adapter,
				'provider'     => 'theme-native',
			);
		}
		if ( self::YOAST_AUTHORITY === $id && self::YOAST_ADAPTER === $adapter ) {
			return array(
				'authority_id' => $id,
				'adapter'      => $adapter,
				'provider'     => 'yoast',
			);
		}

		return new WP_Error(
			'seo_geo_manager_remote_seo_authority_unsupported',
			'The resolved SEO authority does not have an accepted remote write adapter.',
			array( 'status' => 409 )
		);
	}

	/**
	 * @param array{authority_id:string,adapter:string,provider:string} $authority Resolved authority.
	 * @param array<string, mixed>                                    $result    Adapter result.
	 * @return array<string, mixed>
	 */
	private static function bounded_authority( array $authority, array $result ): array {
		$policy   = isset( $result['policy'] ) && is_array( $result['policy'] ) ? $result['policy'] : array();
		$writable = isset( $policy['writable_signals'] ) && is_array( $policy['writable_signals'] ) ? $policy['writable_signals'] : array();
		if ( array() === $writable ) {
			$writable = self::NATIVE_AUTHORITY === $authority['authority_id']
				? array( 'title-meta', 'canonical', 'robots' )
				: array( 'canonical', 'robots', 'schema' );
		}

		return array(
			'authority_id'     => $authority['authority_id'],
			'adapter'          => $authority['adapter'],
			'provider'         => $authority['provider'],
			'writable_signals' => array_values( array_filter( array_map( 'sanitize_key', $writable ) ) ),
		);
	}

	/**
	 * @param array<string, mixed> $operation Stored operation.
	 * @return array<string, mixed>
	 */
	private static function operation_authority( array $operation ): array {
		$stored = isset( $operation['remote_seo_authority'] ) && is_array( $operation['remote_seo_authority'] )
			? $operation['remote_seo_authority']
			: array();

		return array(
			'authority_id' => (string) ( $stored['authority_id'] ?? '' ),
			'adapter'      => (string) ( $stored['adapter'] ?? ( $operation['adapter'] ?? '' ) ),
			'provider'     => (string) ( $stored['provider'] ?? ( $operation['provider'] ?? '' ) ),
		);
	}

	private static function unsupported_adapter(): WP_Error {
		return new WP_Error(
			'seo_geo_manager_remote_seo_adapter_unsupported',
			'The stored SEO operation does not use an accepted remote adapter.',
			array( 'status' => 409 )
		);
	}
}
