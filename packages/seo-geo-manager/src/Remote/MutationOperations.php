<?php
/**
 * Exact allowlist and semantic dispatch for authenticated remote Manager mutations.
 *
 * @package SeoGeoManager
 */

declare(strict_types=1);

namespace SeoGeo\Manager\Remote;

use SeoGeo\Manager\Changes\ChangeSetEngine;
use SeoGeo\Manager\Changes\ThemeStructuredContentAdapter;
use WP_Error;

final class MutationOperations {
	/**
	 * @return list<string>
	 */
	public static function supported(): array {
		return array(
			'content-change',
			'theme-structured-content',
		);
	}

	public static function supports( string $operation ): bool {
		return in_array( $operation, self::supported(), true );
	}

	/**
	 * @param array<string, mixed> $payload Exact semantic Manager payload.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function preview( string $operation, array $payload ) {
		$rendered_guard = self::validate_remote_rendered_policy( $payload );
		if ( is_wp_error( $rendered_guard ) ) {
			return $rendered_guard;
		}

		if ( 'content-change' === $operation ) {
			return ChangeSetEngine::preview( $payload );
		}

		if ( 'theme-structured-content' === $operation ) {
			return ThemeStructuredContentAdapter::preview( $payload );
		}

		return self::unsupported();
	}

	/**
	 * @param array<string, mixed> $payload Exact semantic Manager payload.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function apply( string $operation, array $payload ) {
		$rendered_guard = self::validate_remote_rendered_policy( $payload );
		if ( is_wp_error( $rendered_guard ) ) {
			return $rendered_guard;
		}

		if ( 'content-change' === $operation ) {
			return ChangeSetEngine::apply( $payload );
		}

		if ( 'theme-structured-content' === $operation ) {
			return ThemeStructuredContentAdapter::apply( $payload );
		}

		return self::unsupported();
	}

	/**
	 * @param array<string, mixed> $payload Exact semantic Manager payload.
	 */
	private static function validate_remote_rendered_policy( array $payload ): true|WP_Error {
		if ( true !== ( $payload['verify_rendered'] ?? false ) ) {
			return true;
		}

		return new WP_Error(
			'seo_geo_manager_remote_rendered_verification_unsupported',
			'Remote R3.2 mutations do not yet expose rendered verification. Use stored-state verification.',
			array( 'status' => 400 )
		);
	}

	private static function unsupported(): WP_Error {
		return new WP_Error(
			'seo_geo_manager_remote_operation_unsupported',
			'The requested remote mutation operation is not supported.',
			array(
				'status'               => 400,
				'supported_operations' => self::supported(),
			)
		);
	}
}
