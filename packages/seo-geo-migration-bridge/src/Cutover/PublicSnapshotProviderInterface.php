<?php
/**
 * Public snapshot provider contract for Phase 8G cutover checks.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge\Cutover;

/**
 * Supplies a fresh public-output snapshot for parity checks.
 */
interface PublicSnapshotProviderInterface {
	/**
	 * Capture the current public-output snapshot.
	 *
	 * @return array<string,mixed>
	 */
	public function capture(): array;
}
