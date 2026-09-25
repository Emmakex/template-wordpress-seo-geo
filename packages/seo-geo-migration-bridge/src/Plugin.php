<?php
/**
 * Migration Bridge bootstrap.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge;

use SeoGeo\MigrationBridge\Clone\AdminCloneController;
use SeoGeo\MigrationBridge\Clone\AdminCloneInventoryController;
use SeoGeo\MigrationBridge\Clone\AdminCloneImportController;
use SeoGeo\MigrationBridge\Clone\AdminCloneImportPayloadController;
use SeoGeo\MigrationBridge\Clone\AdminCloneImportDatabaseController;
use SeoGeo\MigrationBridge\Clone\AdminCloneDatabaseExportController;
use SeoGeo\MigrationBridge\Clone\AdminCloneFileExportController;
use SeoGeo\MigrationBridge\Clone\AdminClonePackageController;
use SeoGeo\MigrationBridge\Clone\AdminCloneDeliveryController;
use SeoGeo\MigrationBridge\Clone\CloneInventory;
use SeoGeo\MigrationBridge\Clone\CloneInventoryStore;
use SeoGeo\MigrationBridge\Clone\CloneJobStore;
use SeoGeo\MigrationBridge\Clone\DestinationSafetyPlanner;
use SeoGeo\MigrationBridge\Clone\DatabaseExporter;
use SeoGeo\MigrationBridge\Clone\ExportStateStore;
use SeoGeo\MigrationBridge\Clone\FileExporter;
use SeoGeo\MigrationBridge\Clone\FileExportStateStore;
use SeoGeo\MigrationBridge\Clone\ImportPayloadStateStore;
use SeoGeo\MigrationBridge\Clone\ImportPayloadVerifier;
use SeoGeo\MigrationBridge\Clone\ImportDatabaseRestorer;
use SeoGeo\MigrationBridge\Clone\ImportDatabaseStateStore;
use SeoGeo\MigrationBridge\Clone\ImportPreflight;
use SeoGeo\MigrationBridge\Clone\ImportStateStore;
use SeoGeo\MigrationBridge\Clone\PackageBuilder;
use SeoGeo\MigrationBridge\Clone\PackageStateStore;
use SeoGeo\MigrationBridge\Clone\DeliveryStateStore;
use SeoGeo\MigrationBridge\Clone\PackageDelivery;
use SeoGeo\MigrationBridge\Cutover\AdminCutoverController;
use SeoGeo\MigrationBridge\Cutover\CutoverEngine;
use SeoGeo\MigrationBridge\Migration\AdminMigrationController;
use SeoGeo\MigrationBridge\Migration\MigrationEngine;
use SeoGeo\MigrationBridge\Operator\AdminBaselineCaptureController;
use SeoGeo\MigrationBridge\Operator\AdminOperatorScreen;
use SeoGeo\MigrationBridge\Operator\AdminSandboxHandoffController;
use SeoGeo\MigrationBridge\Operator\OperatorStatus;
use SeoGeo\MigrationBridge\Parity\SeoParityEngine;
use SeoGeo\MigrationBridge\Report\MigrationReportEngine;
use SeoGeo\MigrationBridge\Report\MigrationReportStore;
use SeoGeo\MigrationBridge\Review\AdminDependencyReviewController;
use SeoGeo\MigrationBridge\Sandbox\SandboxGuard;
use SeoGeo\MigrationBridge\Sandbox\SandboxMigrationLab;

/**
 * Exposes migration analysis and baseline services without frontend mutation hooks.
 */
final class Plugin {
	/**
	 * Read-only analyzer singleton.
	 *
	 * @var SiteAnalyzer|null
	 */
	private static ?SiteAnalyzer $analyzer = null;

	/**
	 * Public baseline snapshotter singleton.
	 *
	 * @var BaselineSnapshotter|null
	 */
	private static ?BaselineSnapshotter $baseline_snapshotter = null;

	/**
	 * Resumable public baseline capture singleton.
	 *
	 * @var IncrementalBaselineCapture|null
	 */
	private static ?IncrementalBaselineCapture $incremental_baseline_capture = null;

	/**
	 * Migration dependency graph singleton.
	 *
	 * @var DependencyGraphBuilder|null
	 */
	private static ?DependencyGraphBuilder $dependency_graph = null;

	/**
	 * Sandbox migration lab singleton.
	 *
	 * @var SandboxMigrationLab|null
	 */
	private static ?SandboxMigrationLab $sandbox_lab = null;

	/**
	 * Controlled sandbox migration engine singleton.
	 *
	 * @var MigrationEngine|null
	 */
	private static ?MigrationEngine $migration_engine = null;

	/**
	 * Administrator migration entrypoint singleton.
	 *
	 * @var AdminMigrationController|null
	 */
	private static ?AdminMigrationController $migration_controller = null;

	/**
	 * Read-only SEO/GEO parity engine singleton.
	 *
	 * @var SeoParityEngine|null
	 */
	private static ?SeoParityEngine $parity_engine = null;

	/**
	 * Reversible production cutover engine singleton.
	 *
	 * @var CutoverEngine|null
	 */
	private static ?CutoverEngine $cutover_engine = null;

	/**
	 * Administrator cutover controller singleton.
	 *
	 * @var AdminCutoverController|null
	 */
	private static ?AdminCutoverController $cutover_controller = null;

	/**
	 * Read-only Migration Bridge operator status singleton.
	 *
	 * @var OperatorStatus|null
	 */
	private static ?OperatorStatus $operator_status = null;

	/**
	 * Migration Bridge operator screen singleton.
	 *
	 * @var AdminOperatorScreen|null
	 */
	private static ?AdminOperatorScreen $operator_screen = null;

	/**
	 * Explicit public-baseline capture controller singleton.
	 *
	 * @var AdminBaselineCaptureController|null
	 */
	private static ?AdminBaselineCaptureController $baseline_capture_controller = null;

	/**
	 * Bounded sandbox-handoff download controller singleton.
	 *
	 * @var AdminSandboxHandoffController|null
	 */
	private static ?AdminSandboxHandoffController $sandbox_handoff_controller = null;

	/**
	 * Planning-only dependency-review controller singleton.
	 *
	 * @var AdminDependencyReviewController|null
	 */
	private static ?AdminDependencyReviewController $dependency_review_controller = null;

	/**
	 * Portable Clone resumable job store singleton.
	 *
	 * @var CloneJobStore|null
	 */
	private static ?CloneJobStore $clone_job_store = null;

	/**
	 * Portable Clone planning controller singleton.
	 *
	 * @var AdminCloneController|null
	 */
	private static ?AdminCloneController $clone_controller = null;

	/**
	 * Portable Clone inventory store singleton.
	 *
	 * @var CloneInventoryStore|null
	 */
	private static ?CloneInventoryStore $clone_inventory_store = null;

	/**
	 * Portable Clone read-only inventory singleton.
	 *
	 * @var CloneInventory|null
	 */
	private static ?CloneInventory $clone_inventory = null;

	/**
	 * Portable Clone inventory controller singleton.
	 *
	 * @var AdminCloneInventoryController|null
	 */
	private static ?AdminCloneInventoryController $clone_inventory_controller = null;

	/**
	 * Local-clone destination safety planner singleton.
	 *
	 * @var DestinationSafetyPlanner|null
	 */
	private static ?DestinationSafetyPlanner $destination_safety_planner = null;

	/**
	 * Portable Clone export-state store singleton.
	 *
	 * @var ExportStateStore|null
	 */
	private static ?ExportStateStore $clone_export_state_store = null;

	/**
	 * Portable Clone database exporter singleton.
	 *
	 * @var DatabaseExporter|null
	 */
	private static ?DatabaseExporter $clone_database_exporter = null;

	/**
	 * Portable Clone database export controller singleton.
	 *
	 * @var AdminCloneDatabaseExportController|null
	 */
	private static ?AdminCloneDatabaseExportController $clone_database_export_controller = null;

	/**
	 * Portable Clone file export-state store singleton.
	 *
	 * @var FileExportStateStore|null
	 */
	private static ?FileExportStateStore $clone_file_export_state_store = null;

	/**
	 * Portable Clone file exporter singleton.
	 *
	 * @var FileExporter|null
	 */
	private static ?FileExporter $clone_file_exporter = null;

	/**
	 * Portable Clone file export controller singleton.
	 *
	 * @var AdminCloneFileExportController|null
	 */
	private static ?AdminCloneFileExportController $clone_file_export_controller = null;

	/**
	 * Portable Clone package-state store singleton.
	 *
	 * @var PackageStateStore|null
	 */
	private static ?PackageStateStore $clone_package_state_store = null;

	/**
	 * Portable Clone package builder singleton.
	 *
	 * @var PackageBuilder|null
	 */
	private static ?PackageBuilder $clone_package_builder = null;

	/**
	 * Portable Clone package controller singleton.
	 *
	 * @var AdminClonePackageController|null
	 */
	private static ?AdminClonePackageController $clone_package_controller = null;

	/**
	 * Portable Clone delivery-state store singleton.
	 *
	 * @var DeliveryStateStore|null
	 */
	private static ?DeliveryStateStore $clone_delivery_state_store = null;

	/**
	 * Portable Clone authenticated delivery service singleton.
	 *
	 * @var PackageDelivery|null
	 */
	private static ?PackageDelivery $clone_package_delivery = null;

	/**
	 * Portable Clone delivery controller singleton.
	 *
	 * @var AdminCloneDeliveryController|null
	 */
	private static ?AdminCloneDeliveryController $clone_delivery_controller = null;

	/**
	 * Portable Clone import-preflight state store singleton.
	 *
	 * @var ImportStateStore|null
	 */
	private static ?ImportStateStore $clone_import_state_store = null;

	/**
	 * Portable Clone import intake/preflight singleton.
	 *
	 * @var ImportPreflight|null
	 */
	private static ?ImportPreflight $clone_import_preflight = null;

	/**
	 * Portable Clone import preflight controller singleton.
	 *
	 * @var AdminCloneImportController|null
	 */
	private static ?AdminCloneImportController $clone_import_controller = null;

	/**
	 * Portable Clone import payload state store singleton.
	 *
	 * @var ImportPayloadStateStore|null
	 */
	private static ?ImportPayloadStateStore $clone_import_payload_state_store = null;

	/**
	 * Portable Clone private payload verifier singleton.
	 *
	 * @var ImportPayloadVerifier|null
	 */
	private static ?ImportPayloadVerifier $clone_import_payload_verifier = null;

	/**
	 * Portable Clone import payload controller singleton.
	 *
	 * @var AdminCloneImportPayloadController|null
	 */
	private static ?AdminCloneImportPayloadController $clone_import_payload_controller = null;

	/**
	 * Portable Clone import database restore state store singleton.
	 *
	 * @var ImportDatabaseStateStore|null
	 */
	private static ?ImportDatabaseStateStore $clone_import_database_state_store = null;

	/**
	 * Portable Clone staging database restorer singleton.
	 *
	 * @var ImportDatabaseRestorer|null
	 */
	private static ?ImportDatabaseRestorer $clone_import_database_restorer = null;

	/**
	 * Portable Clone import database restore controller singleton.
	 *
	 * @var AdminCloneImportDatabaseController|null
	 */
	private static ?AdminCloneImportDatabaseController $clone_import_database_controller = null;

	/**
	 * Read-only final migration report engine singleton.
	 *
	 * @var MigrationReportEngine|null
	 */
	private static ?MigrationReportEngine $migration_report = null;

	/**
	 * Persistent final migration report store singleton.
	 *
	 * @var MigrationReportStore|null
	 */
	private static ?MigrationReportStore $migration_report_store = null;

	/**
	 * Initialize Migration Bridge services.
	 */
	public static function boot(): void {
		self::$analyzer               ??= new SiteAnalyzer();
		self::$baseline_snapshotter   ??= new BaselineSnapshotter();
		self::$dependency_graph       ??= new DependencyGraphBuilder();
		self::$sandbox_lab            ??= new SandboxMigrationLab();
		self::$migration_engine       ??= new MigrationEngine();
		self::$migration_controller   ??= new AdminMigrationController( self::$migration_engine );
		self::$parity_engine          ??= new SeoParityEngine();
		self::$cutover_engine         ??= new CutoverEngine();
		self::$cutover_controller     ??= new AdminCutoverController( self::$cutover_engine );
		self::$operator_status        ??= new OperatorStatus();
		self::$operator_screen        ??= new AdminOperatorScreen( self::$operator_status );
		self::$migration_report       ??= new MigrationReportEngine();
		self::$migration_report_store ??= new MigrationReportStore();

		self::$clone_job_store                  ??= new CloneJobStore();
		self::$clone_controller                 ??= new AdminCloneController( self::$clone_job_store );
		self::$clone_inventory_store            ??= new CloneInventoryStore();
		self::$clone_inventory                  ??= new CloneInventory( self::$clone_inventory_store, self::$clone_job_store );
		self::$clone_inventory_controller       ??= new AdminCloneInventoryController( self::$clone_inventory );
		self::$destination_safety_planner       ??= new DestinationSafetyPlanner();
		self::$clone_export_state_store         ??= new ExportStateStore();
		self::$clone_database_exporter          ??= new DatabaseExporter( self::$clone_export_state_store, self::$clone_inventory_store, self::$clone_job_store );
		self::$clone_database_export_controller ??= new AdminCloneDatabaseExportController( self::$clone_database_exporter );
		self::$clone_file_export_state_store    ??= new FileExportStateStore();
		self::$clone_file_exporter              ??= new FileExporter( self::$clone_file_export_state_store, self::$clone_inventory_store, self::$clone_export_state_store, self::$clone_job_store );
		self::$clone_file_export_controller     ??= new AdminCloneFileExportController( self::$clone_file_exporter );
		self::$clone_package_state_store        ??= new PackageStateStore();
		self::$clone_package_builder            ??= new PackageBuilder( self::$clone_package_state_store, self::$clone_inventory_store, self::$clone_export_state_store, self::$clone_file_export_state_store, self::$clone_job_store );
		self::$clone_package_controller         ??= new AdminClonePackageController( self::$clone_package_builder );
		self::$clone_delivery_state_store       ??= new DeliveryStateStore();
		self::$clone_package_delivery           ??= new PackageDelivery( self::$clone_delivery_state_store, self::$clone_package_state_store, self::$clone_inventory_store, self::$clone_export_state_store, self::$clone_file_export_state_store, self::$clone_job_store );
		self::$clone_delivery_controller        ??= new AdminCloneDeliveryController( self::$clone_package_delivery );
		self::$clone_import_state_store         ??= new ImportStateStore();
		self::$clone_import_preflight           ??= new ImportPreflight( self::$clone_import_state_store, self::$clone_job_store );
		self::$clone_import_controller          ??= new AdminCloneImportController( self::$clone_import_preflight );
		self::$clone_import_payload_state_store ??= new ImportPayloadStateStore();
		self::$clone_import_payload_verifier    ??= new ImportPayloadVerifier( self::$clone_import_payload_state_store, self::$clone_import_state_store, self::$clone_job_store, self::$clone_import_preflight );
		self::$clone_import_payload_controller  ??= new AdminCloneImportPayloadController( self::$clone_import_payload_verifier );
		self::$clone_import_database_state_store ??= new ImportDatabaseStateStore();
		self::$clone_import_database_restorer    ??= new ImportDatabaseRestorer( self::$clone_import_database_state_store, self::$clone_import_state_store, self::$clone_import_payload_state_store, self::$clone_job_store, self::$clone_import_preflight );
		self::$clone_import_database_controller  ??= new AdminCloneImportDatabaseController( self::$clone_import_database_restorer );

		self::$incremental_baseline_capture ??= new IncrementalBaselineCapture();

		self::$baseline_capture_controller ??= new AdminBaselineCaptureController( self::$incremental_baseline_capture );

		self::$sandbox_handoff_controller   ??= new AdminSandboxHandoffController();
		self::$dependency_review_controller ??= new AdminDependencyReviewController();

		SandboxGuard::boot();
		self::$migration_controller->boot();
		self::$cutover_controller->boot();
		self::$baseline_capture_controller->boot();
		self::$sandbox_handoff_controller->boot();
		self::$dependency_review_controller->boot();
		self::$clone_controller->boot();
		self::$clone_inventory_controller->boot();
		self::$clone_database_export_controller->boot();
		self::$clone_file_export_controller->boot();
		self::$clone_package_controller->boot();
		self::$clone_delivery_controller->boot();
		self::$clone_import_controller->boot();
		self::$clone_import_payload_controller->boot();
		self::$clone_import_database_controller->boot();
		self::$operator_screen->register();
	}

	/**
	 * Return the read-only Site Analyzer.
	 */
	public static function analyzer(): ?SiteAnalyzer {
		return self::$analyzer;
	}

	/**
	 * Return the public-output baseline snapshot service.
	 */
	public static function baseline_snapshotter(): ?BaselineSnapshotter {
		return self::$baseline_snapshotter;
	}

	/**
	 * Return the resumable public baseline capture service.
	 */
	public static function incremental_baseline_capture(): ?IncrementalBaselineCapture {
		return self::$incremental_baseline_capture;
	}

	/**
	 * Return the read-only migration dependency graph builder.
	 */
	public static function dependency_graph(): ?DependencyGraphBuilder {
		return self::$dependency_graph;
	}

	/**
	 * Return the provider-neutral sandbox migration lab.
	 */
	public static function sandbox_lab(): ?SandboxMigrationLab {
		return self::$sandbox_lab;
	}

	/**
	 * Return the controlled sandbox Migration Engine.
	 */
	public static function migration_engine(): ?MigrationEngine {
		return self::$migration_engine;
	}

	/**
	 * Return the read-only SEO/GEO parity engine.
	 */
	public static function parity_engine(): ?SeoParityEngine {
		return self::$parity_engine;
	}

	/**
	 * Return the reversible production cutover engine.
	 */
	public static function cutover_engine(): ?CutoverEngine {
		return self::$cutover_engine;
	}

	/**
	 * Return the read-only Migration Bridge operator status service.
	 */
	public static function operator_status(): ?OperatorStatus {
		return self::$operator_status;
	}

	/**
	 * Return the Portable Clone resumable job store.
	 */
	public static function clone_job_store(): ?CloneJobStore {
		return self::$clone_job_store;
	}

	/**
	 * Return the Portable Clone read-only source inventory service.
	 */
	public static function clone_inventory(): ?CloneInventory {
		return self::$clone_inventory;
	}

	/**
	 * Return the local-clone destination safety planner.
	 */
	public static function destination_safety_planner(): ?DestinationSafetyPlanner {
		return self::$destination_safety_planner;
	}

	/**
	 * Return the resumable Portable Clone database exporter.
	 */
	public static function clone_database_exporter(): ?DatabaseExporter {
		return self::$clone_database_exporter;
	}

	/**
	 * Return the resumable Portable Clone file exporter.
	 */
	public static function clone_file_exporter(): ?FileExporter {
		return self::$clone_file_exporter;
	}

	/**
	 * Return the resumable Portable Clone package builder.
	 */
	public static function clone_package_builder(): ?PackageBuilder {
		return self::$clone_package_builder;
	}

	/**
	 * Return the authenticated Portable Clone package delivery service.
	 */
	public static function clone_package_delivery(): ?PackageDelivery {
		return self::$clone_package_delivery;
	}

	/**
	 * Return the Portable Clone import preflight service.
	 */
	public static function clone_import_preflight(): ?ImportPreflight {
		return self::$clone_import_preflight;
	}

	/**
	 * Return the resumable Portable Clone import payload verifier.
	 */
	public static function clone_import_payload_verifier(): ?ImportPayloadVerifier {
		return self::$clone_import_payload_verifier;
	}

	/**
	 * Return the resumable Portable Clone staging database restorer.
	 */
	public static function clone_import_database_restorer(): ?ImportDatabaseRestorer {
		return self::$clone_import_database_restorer;
	}

	/**
	 * Return the read-only final migration report engine.
	 */
	public static function migration_report(): ?MigrationReportEngine {
		return self::$migration_report;
	}

	/**
	 * Return the persistent final migration report store.
	 */
	public static function migration_report_store(): ?MigrationReportStore {
		return self::$migration_report_store;
	}
}
