<?php
/**
 * Migration Bridge bootstrap.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge;

/**
 * Exposes the read-only analyzer without registering mutation hooks.
 */
final class Plugin {
	/**
	 * Analyzer singleton.
	 */
	private static ?SiteAnalyzer $analyzer = null;

	/**
	 * Initialize the analyzer service.
	 */
	public static function boot(): void {
		self::$analyzer ??= new SiteAnalyzer();
	}

	/**
	 * Return the analyzer service.
	 */
	public static function analyzer(): ?SiteAnalyzer {
		return self::$analyzer;
	}
}
