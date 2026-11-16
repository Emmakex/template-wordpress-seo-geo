<?php
/**
 * Read-only, evidence-backed navigation for retained migration jobs.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge\Operator;

use SeoGeo\MigrationBridge\Clone\CloneInventoryStore;
use SeoGeo\MigrationBridge\Clone\DeliveryStateStore;
use SeoGeo\MigrationBridge\Clone\ExportStateStore;
use SeoGeo\MigrationBridge\Clone\FileExportStateStore;
use SeoGeo\MigrationBridge\Clone\ImportDatabaseActivationStateStore;
use SeoGeo\MigrationBridge\Clone\ImportDatabaseStateStore;
use SeoGeo\MigrationBridge\Clone\ImportFilePromotionStateStore;
use SeoGeo\MigrationBridge\Clone\ImportFileStateStore;
use SeoGeo\MigrationBridge\Clone\ImportFinalizeStateStore;
use SeoGeo\MigrationBridge\Clone\ImportPayloadStateStore;
use SeoGeo\MigrationBridge\Clone\ImportRewriteStateStore;
use SeoGeo\MigrationBridge\Clone\ImportStateStore;
use SeoGeo\MigrationBridge\Clone\LocalCloneBootstrapStateStore;
use SeoGeo\MigrationBridge\Clone\LocalCloneDatabaseActivationStateStore;
use SeoGeo\MigrationBridge\Clone\LocalCloneDatabaseRestoreStateStore;
use SeoGeo\MigrationBridge\Clone\LocalCloneEnvironmentRewriteStateStore;
use SeoGeo\MigrationBridge\Clone\LocalCloneFilePromotionStateStore;
use SeoGeo\MigrationBridge\Clone\LocalCloneFileRestoreStateStore;
use SeoGeo\MigrationBridge\Clone\LocalCloneFinalizationPlanStateStore;
use SeoGeo\MigrationBridge\Clone\LocalCloneHandoffReportStateStore;
use SeoGeo\MigrationBridge\Clone\LocalClonePackageHandoffStateStore;
use SeoGeo\MigrationBridge\Clone\LocalClonePayloadVerificationStateStore;
use SeoGeo\MigrationBridge\Clone\LocalCloneRuntimeStateStore;
use SeoGeo\MigrationBridge\Clone\LocalCloneSandboxRuntimeStateStore;
use SeoGeo\MigrationBridge\Clone\LocalCloneStateStore;
use SeoGeo\MigrationBridge\Clone\LocalCloneTargetPreflightStateStore;
use SeoGeo\MigrationBridge\Clone\PackageStateStore;

/**
 * Never executes or resumes any migration step. It only recommends where to inspect next.
 */
final class MigrationWizardNavigator {
	/**
	 * Return the first step without persisted completion evidence.
	 *
	 * @param array<string,mixed> $job Normalized existing job.
	 * @return array{anchor:string,label:string,group:int,blocked:bool,done:bool,completed:int,total:int}
	 */
	public static function next( array $job ): array {
		$job_id    = is_string( $job['job_id'] ?? null ) ? $job['job_id'] : '';
		$operation = is_string( $job['operation'] ?? null ) ? $job['operation'] : '';
		$steps     = self::steps( $operation );
		$completed = 0;

		foreach ( $steps as $step ) {
			$class_name = $step['store'];
			$store      = new $class_name();
			$state      = $store->get( $job_id );
			$status     = is_array( $state ) ? (string) ( $state['status'] ?? '' ) : '';
			$is_done    = in_array( $status, $step['accepted'], true );

			if ( $is_done && 'package' === $step['code'] ) {
				$is_done = 'complete' === ( $state['stage'] ?? '' );
			}
			if ( $is_done && 'handoff' === $step['code'] ) {
				$is_done = true === ( $state['handoff_ready'] ?? false );
			}

			if ( $is_done ) {
				++$completed;
				continue;
			}

			return array(
				'anchor'    => $step['anchor'],
				'label'     => $step['label'],
				'group'     => $step['group'],
				'blocked'   => 'blocked' === $status || in_array( $job['status'] ?? '', array( 'failed-terminal', 'cancelled' ), true ),
				'done'      => false,
				'completed' => $completed,
				'total'     => count( $steps ),
			);
		}

		return array(
			'anchor'    => 'seo-geo-clone-selected',
			'label'     => __( 'Review the completed migration', 'seo-geo-migration-bridge' ),
			'group'     => 4,
			'blocked'   => false,
			'done'      => true,
			'completed' => $completed,
			'total'     => count( $steps ),
		);
	}

	/**
	 * Declare exact store evidence and DOM destinations in chronological order.
	 *
	 * @param string $operation CloneJobStore operation.
	 * @return list<array{store:class-string,accepted:list<string>,anchor:string,label:string,group:int,code:string}>
	 */
	private static function steps( string $operation ): array {
		$source = array(
			self::stage( CloneInventoryStore::class, 'complete', 'clone_inventory_heading', 1, 'inventory' ),
			self::stage( ExportStateStore::class, 'complete', 'clone_database_export_heading', 2, 'database' ),
			self::stage( FileExportStateStore::class, 'complete', 'clone_file_export_heading', 2, 'files' ),
			self::stage( PackageStateStore::class, 'complete', 'clone_package_heading', 2, 'package' ),
		);

		if ( 'export' === $operation ) {
			$source[] = self::stage( DeliveryStateStore::class, 'ready', 'clone_delivery_heading', 2, 'delivery' );
			return $source;
		}

		if ( 'local-clone' === $operation ) {
			return array_merge(
				$source,
				array(
					self::stage( LocalCloneStateStore::class, 'ready', 'clone_local_plan_heading', 3, 'plan' ),
					self::stage( LocalCloneBootstrapStateStore::class, 'claimed', 'clone_local_bootstrap_heading', 3, 'claim' ),
					self::stage( LocalCloneRuntimeStateStore::class, 'complete', 'clone_local_runtime_heading', 3, 'runtime' ),
					self::stage( LocalCloneSandboxRuntimeStateStore::class, 'complete', 'clone_local_sandbox_runtime_heading', 3, 'sandbox-runtime' ),
					self::stage( LocalClonePackageHandoffStateStore::class, 'ready', 'clone_local_handoff_heading', 3, 'package-handoff' ),
					self::stage( LocalCloneTargetPreflightStateStore::class, 'ready', 'clone_local_target_preflight_heading', 3, 'preflight' ),
					self::stage( LocalClonePayloadVerificationStateStore::class, 'ready', 'clone_local_payload_heading', 3, 'payload' ),
					self::stage( LocalCloneDatabaseRestoreStateStore::class, 'ready', 'clone_local_database_heading', 3, 'restore-db' ),
					self::stage( LocalCloneFileRestoreStateStore::class, 'ready', 'clone_local_files_heading', 3, 'restore-files' ),
					self::stage( LocalCloneEnvironmentRewriteStateStore::class, 'ready', 'clone_local_rewrite_heading', 3, 'rewrite' ),
					self::stage( LocalCloneFinalizationPlanStateStore::class, 'ready', 'clone_local_finalization_heading', 4, 'finalize' ),
					self::stage( LocalCloneDatabaseActivationStateStore::class, 'activated', 'clone_local_database_activation_heading', 4, 'activate-db' ),
					self::stage( LocalCloneFilePromotionStateStore::class, 'verified', 'clone_local_file_promotion_heading', 4, 'promote-files' ),
					self::stage( LocalCloneHandoffReportStateStore::class, 'ready', 'clone_local_final_handoff_heading', 4, 'handoff' ),
				)
			);
		}

		if ( 'import' === $operation ) {
			return array(
				self::stage( ImportStateStore::class, 'preflight-ready', 'clone_import_heading', 3, 'preflight' , array( 'payload-verified' ) ),
				self::stage( ImportPayloadStateStore::class, 'complete', 'clone_import_payload_heading', 3, 'payload' ),
				self::stage( ImportDatabaseStateStore::class, 'complete', 'clone_import_database_heading', 3, 'restore-db' ),
				self::stage( ImportFileStateStore::class, 'complete', 'clone_import_file_heading', 3, 'restore-files' ),
				self::stage( ImportRewriteStateStore::class, 'complete', 'clone_import_rewrite_heading', 3, 'rewrite' ),
				self::stage( ImportFinalizeStateStore::class, 'ready', 'clone_import_finalize_heading', 4, 'finalize' ),
				self::stage( ImportDatabaseActivationStateStore::class, 'activated', 'clone_database_activation_heading', 4, 'activate-db' ),
				self::stage( ImportFilePromotionStateStore::class, 'verified', 'clone_file_promotion_heading', 4, 'handoff' ),
			);
		}

		return array();
	}

	/**
	 * @param class-string $store       Normalized state store.
	 * @param string       $accepted    Required success status.
	 * @param string       $anchor      OperatorCopy heading key.
	 * @param int          $group       One of the four overview stages.
	 * @param string       $code        Specific extra evidence contract.
	 * @param list<string> $also        Additional success statuses.
	 * @return array{store:class-string,accepted:list<string>,anchor:string,label:string,group:int,code:string}
	 */
	private static function stage( string $store, string $accepted, string $anchor, int $group, string $code, array $also = array() ): array {
		return array(
			'store'    => $store,
			'accepted' => array_merge( array( $accepted ), $also ),
			'anchor'   => 'seo-geo-guide-' . str_replace( '_heading', '', $anchor ),
			'label'    => self::label( $code ),
			'group'    => $group,
			'code'     => $code,
		);
	}

	/**
	 * @param string $code Step identifier, without client data.
	 */
	private static function label( string $code ): string {
		return match ( $code ) {
			'inventory'        => __( 'Analyze source inventory', 'seo-geo-migration-bridge' ),
			'database'         => __( 'Export database', 'seo-geo-migration-bridge' ),
			'files'            => __( 'Export files', 'seo-geo-migration-bridge' ),
			'package'          => __( 'Verify export package', 'seo-geo-migration-bridge' ),
			'delivery'         => __( 'Prepare package delivery', 'seo-geo-migration-bridge' ),
			'plan'             => __( 'Confirm destination plan', 'seo-geo-migration-bridge' ),
			'claim'            => __( 'Confirm destination ownership', 'seo-geo-migration-bridge' ),
			'runtime'          => __( 'Prepare destination WordPress', 'seo-geo-migration-bridge' ),
			'sandbox-runtime'  => __( 'Prepare protected sandbox', 'seo-geo-migration-bridge' ),
			'package-handoff'  => __( 'Transfer verified package', 'seo-geo-migration-bridge' ),
			'preflight'        => __( 'Verify destination safety', 'seo-geo-migration-bridge' ),
			'payload'          => __( 'Verify imported payload', 'seo-geo-migration-bridge' ),
			'restore-db'       => __( 'Restore database', 'seo-geo-migration-bridge' ),
			'restore-files'    => __( 'Restore files', 'seo-geo-migration-bridge' ),
			'rewrite'          => __( 'Adapt destination URLs', 'seo-geo-migration-bridge' ),
			'finalize'         => __( 'Verify restoration integrity', 'seo-geo-migration-bridge' ),
			'activate-db'      => __( 'Review database activation', 'seo-geo-migration-bridge' ),
			'promote-files'    => __( 'Review file promotion', 'seo-geo-migration-bridge' ),
			'handoff'          => __( 'Verify final handoff', 'seo-geo-migration-bridge' ),
			default            => __( 'Review migration status', 'seo-geo-migration-bridge' ),
		};
	}
}
