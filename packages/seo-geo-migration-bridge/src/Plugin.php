<?php
/**
 * Migration Bridge bootstrap.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge;

use SeoGeo\MigrationBridge\Migration\AdminMigrationController;
use SeoGeo\MigrationBridge\Migration\MigrationEngine;
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
	 * Initialize Migration Bridge services.
	 */
	public static function boot(): void {
		self::$analyzer             ??= new SiteAnalyzer();
		self::$baseline_snapshotter ??= new BaselineSnapshotter();
		self::$dependency_graph     ??= new DependencyGraphBuilder();
		self::$sandbox_lab          ??= new SandboxMigrationLab();
		self::$migration_engine     ??= new MigrationEngine();
		self::$migration_controller ??= new AdminMigrationController( self::$migration_engine );

		SandboxGuard::boot();
		self::$migration_controller->boot();
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
}
