<?php
/**
 * Explicit sandbox indexing guard.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge\Sandbox;

/**
 * Makes an explicitly marked migration sandbox non-indexable.
 */
final class SandboxGuard {
	/**
	 * Stable runtime marker name.
	 */
	public const MARKER = 'SEO_GEO_MIGRATION_SANDBOX';

	/**
	 * Register sandbox-only public guards.
	 */
	public static function boot(): void {
		if ( ! self::enabled() ) {
			return;
		}

		add_filter( 'wp_robots', array( self::class, 'robots' ), 999 );
		add_filter( 'wp_headers', array( self::class, 'headers' ), 999 );
	}

	/**
	 * Whether this installation is explicitly marked as a migration sandbox.
	 */
	public static function enabled(): bool {
		return defined( self::MARKER ) && true === constant( self::MARKER );
	}

	/**
	 * Force noindex/noarchive semantics in sandbox output.
	 *
	 * @param array<string, bool|string> $robots Current WordPress robots directives.
	 * @return array<string, bool|string>
	 */
	public static function robots( array $robots ): array {
		if ( ! self::enabled() ) {
			return $robots;
		}

		unset( $robots['index'], $robots['follow'] );
		$robots['noindex']   = true;
		$robots['nofollow']  = true;
		$robots['noarchive'] = true;

		return $robots;
	}

	/**
	 * Add a defense-in-depth X-Robots-Tag header.
	 *
	 * @param array<string, string> $headers Current HTTP headers.
	 * @return array<string, string>
	 */
	public static function headers( array $headers ): array {
		if ( self::enabled() ) {
			$headers['X-Robots-Tag'] = 'noindex, nofollow, noarchive';
		}

		return $headers;
	}
}
