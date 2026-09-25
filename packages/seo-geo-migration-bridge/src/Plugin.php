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
use SeoGeo\MigrationBridge\Clone\CloneInventory;
use SeoGeo\MigrationBridge\Clone\CloneInventoryStore;
use SeoGeo\MigrationBridge\Clone\CloneJobStore;
use SeoGeo\MigrationBridge\Clone\DestinationSafetyPlanner;
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
		self::$analyzer                     ??= new SiteAnalyzer();
		self::$baseline_snapshotter         ??= new BaselineSnapshotter();
		self::$dependency_graph             ??= new DependencyGraphBuilder();
		self::$sandbox_lab                  ??= new SandboxMigrationLab();
		self::$migration_engine             ??= new MigrationEngine();
		self::$migration_controller         ??= new AdminMigrationController( self::$migration_engine );
		self::$parity_engine                ??= new SeoParityEngine();
		self::$cutover_engine               ??= new CutoverEngine();
		self::$cutover_controller           ??= new AdminCutoverController( self::$cutover_engine );
		self::$operator_status              ??= new OperatorStatus();
		self::$operator_screen              ??= new AdminOperatorScreen( self::$operator_status );
		self::$migration_report             ??= new MigrationReportEngine();
		self::$migration_report_store       ??= new MigrationReportStore();

		self::$clone_job_store              ??= new CloneJobStore();
		self::$clone_controller             ??= new AdminCloneController( self::$clone_job_store );
		self::$clone_inventory_store        ??= new CloneInventoryStore();
		self::$clone_inventory              ??= new CloneInventory( self::$clone_inventory_store, self::$clone_job_store );
		self::$clone_inventory_controller   ??= new AdminCloneInventoryController( self::$clone_inventory );
		self::$destination_safety_planner   ??= new DestinationSafetyPlanner();

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
