<?php
/**
 * Live public snapshot provider for cutover verification.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge\Cutover;

use SeoGeo\MigrationBridge\BaselineSnapshotter;

/**
 * Reuses the bounded anonymous Phase 8B crawler for cutover checks.
 */
final class BaselinePublicSnapshotProvider implements PublicSnapshotProviderInterface {
	/**
	 * Snapshotter.
	 *
	 * @var BaselineSnapshotter
	 */
	private BaselineSnapshotter $snapshotter;

	/**
	 * Construct the provider.
	 *
	 * @param BaselineSnapshotter|null $snapshotter Optional snapshotter override.
	 */
	public function __construct( ?BaselineSnapshotter $snapshotter = null ) {
		$this->snapshotter = $snapshotter ?? new BaselineSnapshotter();
	}

	/**
	 * Capture a fresh bounded anonymous snapshot.
	 *
	 * @return array<string,mixed>
	 */
	public function capture(): array {
		return $this->snapshotter->capture();
	}
}
