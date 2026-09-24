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
	 * Explicit confirmation that live outbound transactions are disabled or safely redirected.
	 */
	public const OUTBOUND_SAFE_MARKER = 'SEO_GEO_MIGRATION_OUTBOUND_SAFE';

	/**
	 * Explicit confirmation that fresh recoverable database/uploads backup references exist.
	 */
	public const BACKUPS_READY_MARKER = 'SEO_GEO_MIGRATION_BACKUPS_READY';

	/**
	 * Optional sandbox location mode. Defaults to distinct-origin mode.
	 */
	public const MODE_MARKER = 'SEO_GEO_MIGRATION_SANDBOX_MODE';

	/**
	 * Explicit confirmation that a same-origin subdirectory uses isolated storage/runtime state.
	 */
	public const STORAGE_ISOLATED_MARKER = 'SEO_GEO_MIGRATION_STORAGE_ISOLATED';

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
		return self::constant_enabled( self::MARKER );
	}

	/**
	 * Whether sandbox outbound transactions were explicitly confirmed safe.
	 */
	public static function outbound_safe(): bool {
		return self::constant_enabled( self::OUTBOUND_SAFE_MARKER );
	}

	/**
	 * Whether fresh sandbox recovery references were explicitly confirmed.
	 */
	public static function backups_ready(): bool {
		return self::constant_enabled( self::BACKUPS_READY_MARKER );
	}

	/**
	 * Return the requested sandbox location mode.
	 *
	 * Missing mode keeps the v0.8.7 distinct-origin behavior.
	 */
	public static function mode(): string {
		if ( ! defined( self::MODE_MARKER ) ) {
			return 'origin';
		}

		$value = constant( self::MODE_MARKER );
		if ( ! is_string( $value ) ) {
			return 'invalid';
		}

		$value = strtolower( trim( $value ) );

		return in_array( $value, array( 'origin', 'subdirectory' ), true ) ? $value : 'invalid';
	}

	/**
	 * Whether same-origin subdirectory storage isolation was explicitly confirmed.
	 */
	public static function storage_isolated(): bool {
		return self::constant_enabled( self::STORAGE_ISOLATED_MARKER );
	}

	/**
	 * Resolve a boolean runtime safety marker without accepting truthy strings.
	 *
	 * @param string $name Constant name.
	 */
	private static function constant_enabled( string $name ): bool {
		return defined( $name ) && true === constant( $name );
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
