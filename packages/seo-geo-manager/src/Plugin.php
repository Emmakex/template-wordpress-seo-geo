<?php
/**
 * SEO/GEO Manager bootstrap.
 *
 * @package SeoGeoManager
 */

declare(strict_types=1);

namespace SeoGeo\Manager;

use SeoGeo\Manager\Admin\CorrectionActions;
use SeoGeo\Manager\Admin\Dashboard;
use SeoGeo\Manager\Admin\FieldGate;
use SeoGeo\Manager\Admin\JsonExportActions;
use SeoGeo\Manager\Admin\OperationHistory;
use SeoGeo\Manager\Admin\PermalinkActions;
use SeoGeo\Manager\Intelligence\PresetPageResolver;
use SeoGeo\Manager\Rest\ChangeSetController;
use SeoGeo\Manager\Rest\ContentController;
use SeoGeo\Manager\Rest\FieldGateController;
use SeoGeo\Manager\Rest\HealthController;
use SeoGeo\Manager\Rest\NavigationChangeController;
use SeoGeo\Manager\Rest\OperationHistoryController;
use SeoGeo\Manager\Rest\PermalinkController;
use SeoGeo\Manager\Rest\SiteIntelligenceController;
use SeoGeo\Manager\Rest\SiteSnapshotController;
use SeoGeo\Manager\Rest\ThemeStructuredContentController;
use SeoGeo\Manager\Support\PermalinkRedirectRuntime;

final class Plugin {
	private static bool $booted = false;

	public static function boot(): void {
		if ( self::$booted ) {
			return;
		}

		self::$booted = true;
		PresetPageResolver::register();
		PermalinkRedirectRuntime::register();

		if ( is_admin() ) {
			Dashboard::register();
			JsonExportActions::register();
			CorrectionActions::register();
			PermalinkActions::register();
			OperationHistory::register();
			FieldGate::register();
		}

		add_action(
			'rest_api_init',
			static function (): void {
				HealthController::register_routes();
				ContentController::register_routes();
				SiteSnapshotController::register_routes();
				SiteIntelligenceController::register_routes();
				FieldGateController::register_routes();
				ChangeSetController::register_routes();
				NavigationChangeController::register_routes();
				PermalinkController::register_routes();
				OperationHistoryController::register_routes();
				ThemeStructuredContentController::register_routes();
			}
		);
	}
}
