<?php
/**
 * Exact allowlist and semantic dispatch for authenticated remote Manager mutations.
 *
 * @package SeoGeoManager
 */

declare(strict_types=1);

namespace SeoGeo\Manager\Remote;

use SeoGeo\Manager\Changes\ChangeSetEngine;
use SeoGeo\Manager\Changes\ContextualLinkChangeAdapter;
use SeoGeo\Manager\Changes\NavigationChangeAdapter;
use SeoGeo\Manager\Changes\SeoAuthorityChangeAdapter;
use SeoGeo\Manager\Changes\ThemeStructuredContentAdapter;
use SeoGeo\Manager\Support\ContentFingerprint;
use WP_Error;
use WP_Post;

final class MutationOperations {
	/**
	 * @return list<string>
	 */
	public static function supported(): array {
		return array(
			'content-change',
			'theme-structured-content',
			'seo-authority-change',
			'contextual-link-change',
			'navigation-change',
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
		if ( 'seo-authority-change' === $operation ) {
			return SeoAuthorityChangeAdapter::preview( $payload );
		}
		if ( 'contextual-link-change' === $operation ) {
			return ContextualLinkChangeAdapter::preview( $payload );
		}
		if ( 'navigation-change' === $operation ) {
			return NavigationChangeAdapter::preview( $payload );
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
		if ( 'seo-authority-change' === $operation ) {
			return SeoAuthorityChangeAdapter::apply( $payload );
		}
		if ( 'contextual-link-change' === $operation ) {
			return ContextualLinkChangeAdapter::apply( $payload );
		}
		if ( 'navigation-change' === $operation ) {
			return NavigationChangeAdapter::apply( $payload );
		}

		return self::unsupported();
	}

	/**
	 * Verify an applied semantic operation through the engine that owns its fingerprint.
	 *
	 * @param array<string, mixed> $stored Stored Manager operation.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function verify( string $operation, array $stored ) {
		if ( 'seo-authority-change' === $operation ) {
			return SeoAuthorityChangeAdapter::verify( $stored );
		}
		if ( 'navigation-change' === $operation ) {
			return NavigationChangeAdapter::verify( $stored );
		}
		if ( ! in_array( $operation, array( 'content-change', 'theme-structured-content', 'contextual-link-change' ), true ) ) {
			return self::unsupported();
		}

		$target_id = isset( $stored['target_id'] ) ? (int) $stored['target_id'] : 0;
		$post      = get_post( $target_id );
		if ( ! $post instanceof WP_Post || ! current_user_can( 'edit_post', $target_id ) ) {
			return new WP_Error(
				'seo_geo_manager_remote_verify_target_unavailable',
				'Remote operation target is unavailable to the execution identity.',
				array( 'status' => 403 )
			);
		}

		$expected = isset( $stored['after_fingerprint'] ) && is_string( $stored['after_fingerprint'] ) ? $stored['after_fingerprint'] : '';
		$current  = ContentFingerprint::for_post( $post );
		if ( '' === $expected || ! hash_equals( $expected, $current ) ) {
			return new WP_Error(
				'seo_geo_manager_remote_verification_stale',
				'The resource no longer matches the applied remote operation.',
				array( 'status' => 409 )
			);
		}

		return array(
			'verified'            => true,
			'current_fingerprint' => $current,
		);
	}

	/**
	 * Roll back through the engine that owns the stored operation.
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	public static function rollback( string $operation, string $operation_id ) {
		if ( 'seo-authority-change' === $operation ) {
			return SeoAuthorityChangeAdapter::rollback( $operation_id );
		}
		if ( 'contextual-link-change' === $operation ) {
			return ContextualLinkChangeAdapter::rollback( $operation_id );
		}
		if ( 'navigation-change' === $operation ) {
			return NavigationChangeAdapter::rollback( $operation_id );
		}
		if ( in_array( $operation, array( 'content-change', 'theme-structured-content' ), true ) ) {
			return ChangeSetEngine::rollback( $operation_id );
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
			'Remote R3.5 mutations do not yet expose rendered verification. Use stored-state verification.',
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
