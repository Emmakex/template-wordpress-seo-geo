<?php
/**
 * SEO/GEO Manager bootstrap.
 *
 * @package SeoGeoManager
 */

declare(strict_types=1);

namespace SeoGeo\Manager;

use SeoGeo\Manager\Rest\ContentController;
use SeoGeo\Manager\Rest\HealthController;
use SeoGeo\Manager\Rest\SiteSnapshotController;

final class Plugin {
	private static bool $booted = false;

	public static function boot(): void {
		if ( self::$booted ) {
			return;
		}

		self::$booted = true;

		add_action(
			'rest_api_init',
			static function (): void {
				HealthController::register_routes();
				ContentController::register_routes();
				SiteSnapshotController::register_routes();
			}
		);
	}
}
