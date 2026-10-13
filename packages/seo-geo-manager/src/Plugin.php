<?php
/**
 * SEO/GEO Manager bootstrap.
 *
 * @package SeoGeoManager
 */

declare(strict_types=1);

namespace SeoGeo\Manager;

use SeoGeo\Manager\Admin\ContentWorkspace;
use SeoGeo\Manager\Admin\ContextualLinksWorkspace;
use SeoGeo\Manager\Admin\CorrectionActions;
use SeoGeo\Manager\Admin\FeaturedMediaWorkspace;
use SeoGeo\Manager\Admin\FieldGate;
use SeoGeo\Manager\Admin\JsonExportActions;
use SeoGeo\Manager\Admin\MediaAltWorkspace;
use SeoGeo\Manager\Admin\NavigationWorkspace;
use SeoGeo\Manager\Admin\OperationHistory;
use SeoGeo\Manager\Admin\OptimizationQueue;
use SeoGeo\Manager\Admin\PermalinkActions;
use SeoGeo\Manager\Admin\RemoteAccess;
use SeoGeo\Manager\Admin\SeoAuthorityWorkspace;
use SeoGeo\Manager\Admin\WorkspaceDashboard;
use SeoGeo\Manager\Intelligence\PresetPageResolver;
use SeoGeo\Manager\Rest\AuthorBindingController;
use SeoGeo\Manager\Rest\C9FieldPreflightController;
use SeoGeo\Manager\Rest\CapabilitiesController;
use SeoGeo\Manager\Rest\ChangeSetController;
use SeoGeo\Manager\Rest\ContentController;
use SeoGeo\Manager\Rest\ContentLifecycleController;
use SeoGeo\Manager\Rest\ContextualLinkChangeController;
use SeoGeo\Manager\Rest\ContextualLinkController;
use SeoGeo\Manager\Rest\FeaturedMediaChangeController;
use SeoGeo\Manager\Rest\FeaturedMediaController;
use SeoGeo\Manager\Rest\FieldGateController;
use SeoGeo\Manager\Rest\HealthController;
use SeoGeo\Manager\Rest\MediaAltChangeController;
use SeoGeo\Manager\Rest\MediaContextChangeController;
use SeoGeo\Manager\Rest\MediaReferenceController;
use SeoGeo\Manager\Rest\MediaUploadController;
use SeoGeo\Manager\Rest\NativeSeoMetadataController;
use SeoGeo\Manager\Rest\NativeTranslationGroupController;
use SeoGeo\Manager\Rest\NavigationChangeController;
use SeoGeo\Manager\Rest\OperationHistoryController;
use SeoGeo\Manager\Rest\PermalinkController;
use SeoGeo\Manager\Rest\RemoteMutationController;
use SeoGeo\Manager\Rest\RemoteReadController;
use SeoGeo\Manager\Rest\SeoAuthorityController;
use SeoGeo\Manager\Rest\SiteIntelligenceController;
use SeoGeo\Manager\Rest\SiteSnapshotController;
use SeoGeo\Manager\Rest\TaxonomyAssignmentController;
use SeoGeo\Manager\Rest\ThemeModelController;
use SeoGeo\Manager\Rest\ThemeStrategicMediaController;
use SeoGeo\Manager\Rest\ThemeStructuredContentController;
use SeoGeo\Manager\Rest\YoastSeoMetadataController;
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
			WorkspaceDashboard::register();
			ContentWorkspace::register();
			SeoAuthorityWorkspace::register();
			ContextualLinksWorkspace::register();
			NavigationWorkspace::register();
			FeaturedMediaWorkspace::register();
			MediaAltWorkspace::register();
			OptimizationQueue::register();
			RemoteAccess::register();
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
				CapabilitiesController::register_routes();
				ContentController::register_routes();
				ContentLifecycleController::register_routes();
				AuthorBindingController::register_routes();
				TaxonomyAssignmentController::register_routes();
				NativeTranslationGroupController::register_routes();
				NativeSeoMetadataController::register_routes();
				YoastSeoMetadataController::register_routes();
				SeoAuthorityController::register_routes();
				SiteSnapshotController::register_routes();
				SiteIntelligenceController::register_routes();
				ContextualLinkController::register_routes();
				ContextualLinkChangeController::register_routes();
				MediaReferenceController::register_routes();
				MediaAltChangeController::register_routes();
				MediaContextChangeController::register_routes();
				MediaUploadController::register_routes();
				FeaturedMediaController::register_routes();
				FeaturedMediaChangeController::register_routes();
				FieldGateController::register_routes();
				C9FieldPreflightController::register_routes();
				ChangeSetController::register_routes();
				NavigationChangeController::register_routes();
				PermalinkController::register_routes();
				OperationHistoryController::register_routes();
				ThemeModelController::register_routes();
				ThemeStrategicMediaController::register_routes();
				ThemeStructuredContentController::register_routes();
				RemoteReadController::register_routes();
				RemoteMutationController::register_routes();
			}
		);
	}
}