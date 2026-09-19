<?php
/**
 * Page-builder detection contract.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge\Builders;

/**
 * Allows builder detection to grow without coupling SiteAnalyzer to vendors.
 */
interface BuilderDetectorInterface {
	/**
	 * Return a stable detector identifier.
	 */
	public function id(): string;

	/**
	 * Detect the builder from read-only theme/plugin inventory.
	 *
	 * @param list<array<string, mixed>> $plugins Plugin inventory.
	 * @param list<array<string, mixed>> $themes  Theme inventory.
	 * @return array<string, mixed>
	 */
	public function detect( array $plugins, array $themes ): array;
}
