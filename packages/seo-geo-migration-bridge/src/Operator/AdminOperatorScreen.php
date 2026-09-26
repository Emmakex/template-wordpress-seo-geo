<?php
/**
 * Migration Bridge operator administration screen.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge\Operator;

use SeoGeo\MigrationBridge\Clone\AdminCloneController;
use SeoGeo\MigrationBridge\Clone\AdminCloneLocalPlanController;
use SeoGeo\MigrationBridge\Clone\AdminCloneLocalBootstrapController;
use SeoGeo\MigrationBridge\Clone\AdminCloneLocalRuntimeController;
use SeoGeo\MigrationBridge\Clone\AdminCloneLocalSandboxRuntimeController;
use SeoGeo\MigrationBridge\Clone\AdminCloneLocalPackageHandoffController;
use SeoGeo\MigrationBridge\Clone\AdminCloneLocalTargetPreflightController;
use SeoGeo\MigrationBridge\Clone\AdminCloneLocalPayloadController;
use SeoGeo\MigrationBridge\Clone\AdminCloneLocalDatabaseController;
use SeoGeo\MigrationBridge\Clone\AdminCloneLocalFileController;
use SeoGeo\MigrationBridge\Clone\AdminCloneLocalEnvironmentRewriteController;
use SeoGeo\MigrationBridge\Clone\AdminCloneLocalFinalizationController;
use SeoGeo\MigrationBridge\Clone\AdminCloneLocalDatabaseActivationController;
use SeoGeo\MigrationBridge\Clone\AdminCloneLocalFilePromotionController;
use SeoGeo\MigrationBridge\Clone\AdminCloneLocalHandoffController;
use SeoGeo\MigrationBridge\Clone\AdminCloneInventoryController;
use SeoGeo\MigrationBridge\Clone\AdminCloneImportController;
use SeoGeo\MigrationBridge\Clone\AdminCloneImportPayloadController;
use SeoGeo\MigrationBridge\Clone\AdminCloneImportDatabaseController;
use SeoGeo\MigrationBridge\Clone\AdminCloneImportFileController;
use SeoGeo\MigrationBridge\Clone\AdminCloneImportRewriteController;
use SeoGeo\MigrationBridge\Clone\AdminCloneImportFinalizeController;
use SeoGeo\MigrationBridge\Clone\AdminCloneImportDatabaseActivationController;
use SeoGeo\MigrationBridge\Clone\AdminCloneImportFilePromotionController;
use SeoGeo\MigrationBridge\Clone\AdminCloneDatabaseExportController;
use SeoGeo\MigrationBridge\Clone\AdminCloneFileExportController;
use SeoGeo\MigrationBridge\Clone\AdminClonePackageController;
use SeoGeo\MigrationBridge\Clone\AdminCloneDeliveryController;
use SeoGeo\MigrationBridge\Clone\CloneInventory;
use SeoGeo\MigrationBridge\Clone\CloneInventoryStore;
use SeoGeo\MigrationBridge\Clone\CloneJobStore;
use SeoGeo\MigrationBridge\Clone\LocalCloneStateStore;
use SeoGeo\MigrationBridge\Clone\LocalCloneBootstrapStateStore;
use SeoGeo\MigrationBridge\Clone\LocalCloneRuntimeStateStore;
use SeoGeo\MigrationBridge\Clone\LocalCloneRuntimeBootstrapper;
use SeoGeo\MigrationBridge\Clone\LocalCloneSandboxRuntimeStateStore;
use SeoGeo\MigrationBridge\Clone\LocalCloneSandboxRuntimeBootstrapper;
use SeoGeo\MigrationBridge\Clone\LocalClonePackageHandoffStateStore;
use SeoGeo\MigrationBridge\Clone\LocalClonePackageHandoff;
use SeoGeo\MigrationBridge\Clone\LocalCloneTargetPreflightStateStore;
use SeoGeo\MigrationBridge\Clone\LocalClonePayloadVerificationStateStore;
use SeoGeo\MigrationBridge\Clone\LocalCloneDatabaseRestoreStateStore;
use SeoGeo\MigrationBridge\Clone\LocalCloneFileRestoreStateStore;
use SeoGeo\MigrationBridge\Clone\LocalCloneEnvironmentRewriteStateStore;
use SeoGeo\MigrationBridge\Clone\LocalCloneFinalizationPlanStateStore;
use SeoGeo\MigrationBridge\Clone\LocalCloneDatabaseActivationStateStore;
use SeoGeo\MigrationBridge\Clone\LocalCloneFilePromotionStateStore;
use SeoGeo\MigrationBridge\Clone\LocalCloneHandoffReportStateStore;
use SeoGeo\MigrationBridge\Clone\DatabaseExporter;
use SeoGeo\MigrationBridge\Clone\ExportStateStore;
use SeoGeo\MigrationBridge\Clone\FileExporter;
use SeoGeo\MigrationBridge\Clone\FileExportStateStore;
use SeoGeo\MigrationBridge\Clone\ImportPayloadStateStore;
use SeoGeo\MigrationBridge\Clone\ImportPayloadVerifier;
use SeoGeo\MigrationBridge\Clone\ImportDatabaseRestorer;
use SeoGeo\MigrationBridge\Clone\ImportDatabaseStateStore;
use SeoGeo\MigrationBridge\Clone\ImportFileRestorer;
use SeoGeo\MigrationBridge\Clone\ImportFileStateStore;
use SeoGeo\MigrationBridge\Clone\ImportEnvironmentRewriter;
use SeoGeo\MigrationBridge\Clone\ImportRewriteStateStore;
use SeoGeo\MigrationBridge\Clone\ImportFinalizeStateStore;
use SeoGeo\MigrationBridge\Clone\ImportFinalizationPlanner;
use SeoGeo\MigrationBridge\Clone\ImportDatabaseActivationStateStore;
use SeoGeo\MigrationBridge\Clone\ImportFilePromotionStateStore;
use SeoGeo\MigrationBridge\Clone\ImportFilePromoter;
use SeoGeo\MigrationBridge\Clone\ImportStateStore;
use SeoGeo\MigrationBridge\Clone\PackageBuilder;
use SeoGeo\MigrationBridge\Clone\PackageStateStore;
use SeoGeo\MigrationBridge\Clone\DeliveryStateStore;
use SeoGeo\MigrationBridge\Clone\PackageDelivery;
use SeoGeo\MigrationBridge\IncrementalBaselineCapture;
use SeoGeo\MigrationBridge\Review\AdminDependencyReviewController;
use SeoGeo\MigrationBridge\Review\DependencyReviewStore;

/**
 * Renders one capability-gated, read-only migration status screen under Tools.
 */
final class AdminOperatorScreen {
	/**
	 * WordPress Tools page slug.
	 */
	public const PAGE_SLUG = 'seo-geo-migration-bridge';

	/**
	 * Read-only status source.
	 *
	 * @var OperatorStatus
	 */
	private OperatorStatus $status;

	/**
	 * Localized operator copy.
	 *
	 * @var OperatorCopy
	 */
	private OperatorCopy $copy;

	/**
	 * Construct the operator screen.
	 *
	 * @param OperatorStatus|null $status Optional status source.
	 * @param OperatorCopy|null   $copy   Optional copy source.
	 */
	public function __construct( ?OperatorStatus $status = null, ?OperatorCopy $copy = null ) {
		$this->status = $status ?? new OperatorStatus();
		$this->copy   = $copy ?? new OperatorCopy();
	}

	/**
	 * Register WordPress administration hooks.
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'register_page' ) );
	}

	/**
	 * Register the read-only Tools screen.
	 */
	public function register_page(): void {
		add_management_page(
			$this->copy->text( 'page_title' ),
			$this->copy->text( 'menu_title' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render_page' )
		);
	}

	/**
	 * Render bounded migration status.
	 */
	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die(
				esc_html( $this->copy->text( 'forbidden' ) ),
				'',
				array( 'response' => 403 )
			);
		}

		$status = $this->status->snapshot();
		?>
		<div class="wrap seo-geo-migration-operator">
			<h1><?php echo esc_html( $this->copy->text( 'page_title' ) ); ?></h1>
			<p><?php echo esc_html( $this->copy->text( 'intro' ) ); ?></p>
			<?php $this->render_baseline_result_notice(); ?>
			<?php $this->render_dependency_review_result_notice(); ?>
			<?php $this->render_clone_result_notice(); ?>
			<?php $this->render_clone_inventory_result_notice(); ?>
			<?php $this->render_clone_database_export_result_notice(); ?>
			<?php $this->render_clone_file_export_result_notice(); ?>
			<?php $this->render_clone_package_result_notice(); ?>
			<?php $this->render_clone_local_plan_result_notice(); ?>
			<?php $this->render_clone_local_bootstrap_result_notice(); ?>
			<?php $this->render_clone_local_runtime_result_notice(); ?>
			<?php $this->render_clone_local_sandbox_runtime_result_notice(); ?>
			<?php $this->render_clone_local_package_handoff_result_notice(); ?>
			<?php $this->render_clone_local_target_preflight_result_notice(); ?>
			<?php $this->render_clone_local_payload_result_notice(); ?>
			<?php $this->render_clone_local_database_result_notice(); ?>
			<?php $this->render_clone_local_file_result_notice(); ?>
			<?php $this->render_clone_local_rewrite_result_notice(); ?>
			<?php $this->render_clone_local_finalization_result_notice(); ?>
			<?php $this->render_clone_local_database_activation_result_notice(); ?>
			<?php $this->render_clone_local_file_promotion_result_notice(); ?>
			<?php $this->render_clone_local_handoff_result_notice(); ?>
			<?php $this->render_clone_delivery_result_notice(); ?>
			<?php $this->render_clone_import_result_notice(); ?>
			<?php $this->render_clone_import_payload_result_notice(); ?>
			<?php $this->render_clone_import_database_result_notice(); ?>
			<?php $this->render_clone_import_file_result_notice(); ?>
			<?php $this->render_clone_import_rewrite_result_notice(); ?>
			<?php $this->render_clone_import_finalize_result_notice(); ?>
			<?php $this->render_clone_database_activation_result_notice(); ?>
			<?php $this->render_clone_file_promotion_result_notice(); ?>

			<h2><?php echo esc_html( $this->copy->text( 'overview_heading' ) ); ?></h2>
			<table class="widefat striped" role="presentation">
				<tbody>
					<?php $this->render_overview_row( 'label_baseline', $this->baseline_label_key( $status ) ); ?>
					<?php $this->render_overview_row( 'label_dependency_plan', $this->dependency_label_key( $status ) ); ?>
					<?php $this->render_overview_row( 'label_cutover', $this->cutover_label_key( $status ) ); ?>
					<?php $this->render_overview_row( 'label_final_report', $this->report_label_key( $status ) ); ?>
					<?php if ( true === ( $status['sandbox']['active'] ?? false ) ) : ?>
						<?php $this->render_overview_row( 'label_sandbox_readiness', $this->sandbox_label_key( $status ) ); ?>
					<?php endif; ?>
				</tbody>
			</table>

			<h2><?php echo esc_html( $this->copy->text( 'dependency_heading' ) ); ?></h2>
			<table class="widefat striped">
				<thead>
					<tr>
						<th scope="col"><?php echo esc_html( $this->copy->text( 'label_classification' ) ); ?></th>
						<th scope="col"><?php echo esc_html( $this->copy->text( 'label_count' ) ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php
					$summary = isset( $status['dependency_plan']['summary'] ) && is_array( $status['dependency_plan']['summary'] )
						? $status['dependency_plan']['summary']
						: array();
					foreach ( array( 'KEEP', 'REPLACE', 'MIGRATE', 'OPTIONAL', 'REMOVE-CANDIDATE', 'UNKNOWN' ) as $classification ) :
						?>
						<tr>
							<th scope="row"><code><?php echo esc_html( $classification ); ?></code></th>
							<td><?php echo esc_html( (string) (int) ( $summary[ $classification ] ?? 0 ) ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<?php
			$components = isset( $status['dependency_plan']['components'] ) && is_array( $status['dependency_plan']['components'] )
				? $status['dependency_plan']['components']
				: array();
			?>
			<?php if ( array() !== $components ) : ?>
				<h2><?php echo esc_html( $this->copy->text( 'dependency_detail_heading' ) ); ?></h2>
				<p><?php echo esc_html( $this->copy->text( 'dependency_detail_help' ) ); ?></p>
				<div style="overflow-x:auto">
					<table class="widefat striped">
						<thead>
							<tr>
								<th scope="col"><?php echo esc_html( $this->copy->text( 'label_component' ) ); ?></th>
								<th scope="col"><?php echo esc_html( $this->copy->text( 'label_type' ) ); ?></th>
								<th scope="col"><?php echo esc_html( $this->copy->text( 'label_classification' ) ); ?></th>
								<th scope="col"><?php echo esc_html( $this->copy->text( 'label_reason' ) ); ?></th>
								<th scope="col"><?php echo esc_html( $this->copy->text( 'label_active' ) ); ?></th>
								<th scope="col"><?php echo esc_html( $this->copy->text( 'label_resources' ) ); ?></th>
								<th scope="col"><?php echo esc_html( $this->copy->text( 'label_review' ) ); ?></th>
								<th scope="col"><?php echo esc_html( $this->copy->text( 'label_action' ) ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $components as $component ) : ?>
								<?php if ( is_array( $component ) ) : ?>
									<tr>
										<td><code><?php echo esc_html( (string) ( $component['id'] ?? $component['component_id'] ?? '' ) ); ?></code></td>
										<td><?php echo esc_html( (string) ( $component['type'] ?? '' ) ); ?></td>
										<td><code><?php echo esc_html( (string) ( $component['classification'] ?? 'UNKNOWN' ) ); ?></code></td>
										<td><?php echo esc_html( (string) ( $component['reason'] ?? '' ) ); ?></td>
										<td><?php echo esc_html( true === ( $component['active'] ?? false ) ? $this->copy->text( 'yes' ) : $this->copy->text( 'no' ) ); ?></td>
										<td><?php echo esc_html( null !== ( $component['resource_count'] ?? null ) ? (string) (int) $component['resource_count'] : '—' ); ?></td>
										<td>
											<?php if ( 'UNKNOWN' === ( $component['classification'] ?? null ) ) : ?>
												<?php if ( true === ( $component['reviewed'] ?? false ) ) : ?>
													<code><?php echo esc_html( (string) ( $component['review_decision'] ?? '' ) ); ?></code>
												<?php else : ?>
													<?php echo esc_html( $this->copy->text( 'review_pending' ) ); ?>
												<?php endif; ?>
											<?php else : ?>
												—
											<?php endif; ?>
										</td>
										<td>
											<?php if ( 'UNKNOWN' === ( $component['classification'] ?? null ) ) : ?>
												<?php $this->render_dependency_review_form( $component ); ?>
											<?php else : ?>
												—
											<?php endif; ?>
										</td>
									</tr>
								<?php endif; ?>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			<?php endif; ?>

			<h2><?php echo esc_html( $this->copy->text( 'review_heading' ) ); ?></h2>
			<p><?php echo esc_html( $this->copy->text( 'dependency_review_help' ) ); ?></p>
			<dl>
				<dt><?php echo esc_html( $this->copy->text( 'label_reviewed_unknown' ) ); ?></dt>
				<dd><?php echo esc_html( (string) (int) ( $status['dependency_plan']['reviewed_unknown_count'] ?? 0 ) ); ?></dd>
				<dt><?php echo esc_html( $this->copy->text( 'label_unreviewed_unknown' ) ); ?></dt>
				<dd><?php echo esc_html( (string) (int) ( $status['dependency_plan']['unreviewed_unknown_count'] ?? 0 ) ); ?></dd>
				<dt><?php echo esc_html( $this->copy->text( 'label_blocking_review' ) ); ?></dt>
				<dd><?php echo esc_html( (string) (int) ( $status['final_report']['blocking_review_count'] ?? 0 ) ); ?></dd>
				<dt><?php echo esc_html( $this->copy->text( 'label_advisory_review' ) ); ?></dt>
				<dd><?php echo esc_html( (string) (int) ( $status['final_report']['advisory_review_count'] ?? 0 ) ); ?></dd>
				<dt><?php echo esc_html( $this->copy->text( 'label_bridge_disposition' ) ); ?></dt>
				<dd><?php echo esc_html( $this->disposition_label( $status ) ); ?></dd>
			</dl>

			<?php if ( true === ( $status['sandbox']['active'] ?? false ) ) : ?>
				<?php $this->render_sandbox_preflight( $status ); ?>
			<?php endif; ?>

			<h2><?php echo esc_html( $this->copy->text( 'next_step_heading' ) ); ?></h2>
			<p class="notice notice-info inline">
				<?php echo esc_html( $this->next_step_text( $status ) ); ?>
			</p>
			<?php if ( true !== ( $status['baseline']['available'] ?? false ) ) : ?>
				<?php $this->render_baseline_capture_form( $status ); ?>
			<?php else : ?>
				<h2><?php echo esc_html( $this->copy->text( 'sandbox_handoff_heading' ) ); ?></h2>
				<p><?php echo esc_html( $this->copy->text( 'sandbox_handoff_help' ) ); ?></p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="<?php echo esc_attr( AdminSandboxHandoffController::ACTION ); ?>">
					<?php wp_nonce_field( AdminSandboxHandoffController::NONCE_ACTION ); ?>
					<?php submit_button( $this->copy->text( 'sandbox_handoff_button' ), 'secondary', 'submit', false ); ?>
				</form>
				<?php $this->render_clone_planning_section(); ?>
			<?php endif; ?>

			<h2><?php echo esc_html( $this->copy->text( 'privacy_heading' ) ); ?></h2>
			<p><?php echo esc_html( $this->copy->text( 'privacy_text' ) ); ?></p>
		</div>
		<?php
	}

	/**
	 * Render a bounded result notice after an explicit baseline action.
	 */
	private function render_baseline_result_notice(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only result notice after the nonce-verified admin action.
		$status = isset( $_GET['seo_geo_baseline'] )
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Same read-only result notice value.
			? sanitize_key( wp_unslash( $_GET['seo_geo_baseline'] ) )
			: '';

		$key = match ( $status ) {
			'success'  => 'baseline_capture_success',
			'progress' => 'baseline_progress_notice',
			'exists'   => 'baseline_capture_exists',
			'error'    => 'baseline_capture_error',
			default    => null,
		};

		if ( null === $key ) {
			return;
		}

		$class = match ( $status ) {
			'error'    => 'notice notice-error',
			'progress' => 'notice notice-info',
			default    => 'notice notice-success',
		};
		?>
		<div class="<?php echo esc_attr( $class ); ?> is-dismissible">
			<p><?php echo esc_html( $this->copy->text( $key ) ); ?></p>
		</div>
		<?php
	}

	/**
	 * Render a bounded result notice after an explicit dependency review action.
	 */
	private function render_dependency_review_result_notice(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only result notice after the nonce-verified admin action.
		$status = isset( $_GET['seo_geo_dependency_review'] )
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Same read-only result notice value.
			? sanitize_key( wp_unslash( $_GET['seo_geo_dependency_review'] ) )
			: '';

		$key = match ( $status ) {
			'saved'   => 'dependency_review_saved',
			'cleared' => 'dependency_review_cleared',
			default   => null,
		};

		if ( null === $key ) {
			return;
		}
		?>
		<div class="notice notice-success is-dismissible">
			<p><?php echo esc_html( $this->copy->text( $key ) ); ?></p>
		</div>
		<?php
	}

	/**
	 * Render a bounded result notice after creating a Portable Clone planning job.
	 */
	private function render_clone_result_notice(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only result notice after nonce-verified admin action.
		$status = isset( $_GET['seo_geo_clone_job'] )
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Same read-only result notice value.
			? sanitize_key( wp_unslash( $_GET['seo_geo_clone_job'] ) )
			: '';

		if ( 'created' !== $status ) {
			return;
		}
		?>
		<div class="notice notice-success is-dismissible">
			<p><?php echo esc_html( $this->copy->text( 'clone_job_created' ) ); ?></p>
		</div>
		<?php
	}

	/**
	 * Render Phase 10E.2A.1 planning-only Portable Clone controls.
	 */
	private function render_clone_planning_section(): void {
		$latest = ( new CloneJobStore() )->latest();
		?>
		<h2><?php echo esc_html( $this->copy->text( 'clone_heading' ) ); ?></h2>
		<p><?php echo esc_html( $this->copy->text( 'clone_help' ) ); ?></p>
		<div style="display:flex;gap:8px;flex-wrap:wrap">
			<?php $this->render_clone_job_form( 'local-clone', 'clone_local_button' ); ?>
			<?php $this->render_clone_job_form( 'export', 'clone_export_button' ); ?>
			<?php $this->render_clone_job_form( 'import', 'clone_import_button' ); ?>
		</div>
		<?php if ( is_array( $latest ) ) : ?>
			<h3><?php echo esc_html( $this->copy->text( 'clone_latest_heading' ) ); ?></h3>
			<table class="widefat striped" role="presentation">
				<tbody>
					<tr>
						<th scope="row"><?php echo esc_html( $this->copy->text( 'label_clone_job_id' ) ); ?></th>
						<td><code><?php echo esc_html( (string) ( $latest['job_id'] ?? '' ) ); ?></code></td>
					</tr>
					<tr>
						<th scope="row"><?php echo esc_html( $this->copy->text( 'label_clone_operation' ) ); ?></th>
						<td><code><?php echo esc_html( (string) ( $latest['operation'] ?? '' ) ); ?></code></td>
					</tr>
					<tr>
						<th scope="row"><?php echo esc_html( $this->copy->text( 'label_clone_phase' ) ); ?></th>
						<td><code><?php echo esc_html( (string) ( $latest['phase'] ?? '' ) ); ?></code></td>
					</tr>
					<tr>
						<th scope="row"><?php echo esc_html( $this->copy->text( 'label_clone_status' ) ); ?></th>
						<td><code><?php echo esc_html( (string) ( $latest['status'] ?? '' ) ); ?></code></td>
					</tr>
					<tr>
						<th scope="row"><?php echo esc_html( $this->copy->text( 'label_clone_progress' ) ); ?></th>
						<td>
							<?php
							$counters = is_array( $latest['counters'] ?? null ) ? $latest['counters'] : array();
							$done     = (int) ( $counters['completed'] ?? 0 );
							$total    = $counters['total'] ?? null;
							echo esc_html( null === $total ? (string) $done : $done . ' / ' . (string) (int) $total );
							?>
						</td>
					</tr>
				</tbody>
			</table>
			<?php if ( in_array( $latest['operation'] ?? null, array( 'local-clone', 'export' ), true ) ) : ?>
				<?php $this->render_clone_inventory_section( $latest ); ?>
			<?php elseif ( 'import' === ( $latest['operation'] ?? null ) ) : ?>
				<?php $this->render_clone_import_section( $latest ); ?>
			<?php endif; ?>
		<?php endif; ?>
		<?php
	}

	/**
	 * Render a bounded result notice after a source-inventory step.
	 */
	private function render_clone_inventory_result_notice(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only result notice after nonce-verified inventory action.
		$status = isset( $_GET['seo_geo_clone_inventory'] )
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Same read-only result notice value.
			? sanitize_key( wp_unslash( $_GET['seo_geo_clone_inventory'] ) )
			: '';

		$key = match ( $status ) {
			'complete' => 'clone_inventory_complete',
			'running'  => 'clone_inventory_running',
			'blocked'  => 'clone_inventory_blocked',
			default    => null,
		};

		if ( null === $key ) {
			return;
		}

		$class = 'blocked' === $status ? 'notice notice-error' : 'notice notice-success';
		?>
		<div class="<?php echo esc_attr( $class ); ?> is-dismissible">
			<p><?php echo esc_html( $this->copy->text( $key ) ); ?></p>
		</div>
		<?php
	}

	/**
	 * Render read-only source inventory progress and explicit bounded action.
	 *
	 * @param array<string,mixed> $job Latest clone job.
	 */
	private function render_clone_inventory_section( array $job ): void {
		$job_id    = is_string( $job['job_id'] ?? null ) ? $job['job_id'] : '';
		$inventory = '' === $job_id ? null : ( new CloneInventoryStore() )->get( $job_id );
		$status    = is_array( $inventory ) ? (string) ( $inventory['status'] ?? 'pending' ) : 'pending';
		$database  = is_array( $inventory['database'] ?? null ) ? $inventory['database'] : array();
		$button    = 'pending' === $status ? 'clone_inventory_start' : 'clone_inventory_continue';

		if ( '' === $job_id ) {
			return;
		}
		?>
		<h3><?php echo esc_html( $this->copy->text( 'clone_inventory_heading' ) ); ?></h3>
		<p><?php echo esc_html( $this->copy->text( 'clone_inventory_help' ) ); ?></p>
		<table class="widefat striped" role="presentation">
			<tbody>
				<tr>
					<th scope="row"><?php echo esc_html( $this->copy->text( 'label_clone_inventory_status' ) ); ?></th>
					<td><code><?php echo esc_html( $status ); ?></code></td>
				</tr>
				<tr>
					<th scope="row"><?php echo esc_html( $this->copy->text( 'label_clone_db_tables' ) ); ?></th>
					<td><?php echo esc_html( (string) (int) ( $database['table_count'] ?? 0 ) ); ?></td>
				</tr>
				<tr>
					<th scope="row"><?php echo esc_html( $this->copy->text( 'label_clone_db_rows' ) ); ?></th>
					<td><?php echo esc_html( (string) (int) ( $database['estimated_rows'] ?? 0 ) ); ?></td>
				</tr>
				<tr>
					<th scope="row"><?php echo esc_html( $this->copy->text( 'label_clone_db_bytes' ) ); ?></th>
					<td><?php echo esc_html( size_format( (int) ( $database['estimated_bytes'] ?? 0 ) ) ); ?></td>
				</tr>
				<tr>
					<th scope="row"><?php echo esc_html( $this->copy->text( 'label_clone_files' ) ); ?></th>
					<td><?php echo esc_html( (string) (int) ( $inventory['file_count'] ?? 0 ) ); ?></td>
				</tr>
				<tr>
					<th scope="row"><?php echo esc_html( $this->copy->text( 'label_clone_bytes' ) ); ?></th>
					<td><?php echo esc_html( size_format( (int) ( $inventory['byte_count'] ?? 0 ) ) ); ?></td>
				</tr>
				<tr>
					<th scope="row"><?php echo esc_html( $this->copy->text( 'label_clone_excluded' ) ); ?></th>
					<td><?php echo esc_html( (string) (int) ( $inventory['excluded_count'] ?? 0 ) ); ?></td>
				</tr>
				<tr>
					<th scope="row"><?php echo esc_html( $this->copy->text( 'label_clone_symlinks' ) ); ?></th>
					<td><?php echo esc_html( (string) (int) ( $inventory['symlink_count'] ?? 0 ) ); ?></td>
				</tr>
				<tr>
					<th scope="row"><?php echo esc_html( $this->copy->text( 'label_clone_unreadable' ) ); ?></th>
					<td><?php echo esc_html( (string) (int) ( $inventory['unreadable_count'] ?? 0 ) ); ?></td>
				</tr>
				<tr>
					<th scope="row"><?php echo esc_html( $this->copy->text( 'label_clone_fingerprint' ) ); ?></th>
					<td><code><?php echo esc_html( (string) ( $inventory['fingerprint'] ?? '' ) ); ?></code></td>
				</tr>
			</tbody>
		</table>

		<?php if ( ! in_array( $status, array( 'complete', 'blocked' ), true ) ) : ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="<?php echo esc_attr( AdminCloneInventoryController::ACTION ); ?>">
				<input type="hidden" name="clone_job_id" value="<?php echo esc_attr( $job_id ); ?>">
				<?php wp_nonce_field( AdminCloneInventoryController::NONCE_ACTION . ':' . $job_id ); ?>
				<p>
					<label for="seo-geo-clone-inventory-batch"><strong><?php echo esc_html( $this->copy->text( 'clone_inventory_batch_label' ) ); ?></strong></label>
					<select id="seo-geo-clone-inventory-batch" name="inventory_batch_size">
						<?php foreach ( array( 25, 50, 100, 200, 500 ) as $size ) : ?>
							<option value="<?php echo esc_attr( (string) $size ); ?>" <?php selected( CloneInventory::DEFAULT_BATCH_SIZE, $size ); ?>><?php echo esc_html( (string) $size ); ?></option>
						<?php endforeach; ?>
					</select>
				</p>
				<p class="description"><?php echo esc_html( $this->copy->text( 'clone_inventory_batch_help' ) ); ?></p>
				<?php submit_button( $this->copy->text( $button ), 'secondary', 'submit', false ); ?>
			</form>
		<?php elseif ( 'complete' === $status ) : ?>
			<?php $this->render_clone_database_export_section( $job ); ?>
		<?php endif; ?>
		<?php
	}

	/**
	 * Render a bounded result notice after a database-export batch.
	 */
	private function render_clone_database_export_result_notice(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only result notice after nonce-verified export action.
		$status = isset( $_GET['seo_geo_clone_database_export'] )
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Same read-only result notice value.
			? sanitize_key( wp_unslash( $_GET['seo_geo_clone_database_export'] ) )
			: '';

		$key = match ( $status ) {
			'complete' => 'clone_database_export_complete',
			'running'  => 'clone_database_export_running',
			'blocked'  => 'clone_database_export_blocked',
			default    => null,
		};

		if ( null === $key ) {
			return;
		}

		$class = 'blocked' === $status ? 'notice notice-error' : 'notice notice-success';
		?>
		<div class="<?php echo esc_attr( $class ); ?> is-dismissible">
			<p><?php echo esc_html( $this->copy->text( $key ) ); ?></p>
		</div>
		<?php
	}

	/**
	 * Render resumable private database export progress.
	 *
	 * @param array<string,mixed> $job Clone job.
	 */
	private function render_clone_database_export_section( array $job ): void {
		$job_id = is_string( $job['job_id'] ?? null ) ? $job['job_id'] : '';
		if ( '' === $job_id ) {
			return;
		}

		$export = ( new ExportStateStore() )->get( $job_id );
		$status = is_array( $export ) ? (string) ( $export['status'] ?? 'pending' ) : 'pending';
		$button = 'pending' === $status ? 'clone_database_export_start' : 'clone_database_export_continue';
		?>
		<h3><?php echo esc_html( $this->copy->text( 'clone_database_export_heading' ) ); ?></h3>
		<p><?php echo esc_html( $this->copy->text( 'clone_database_export_help' ) ); ?></p>
		<table class="widefat striped" role="presentation">
			<tbody>
				<tr>
					<th scope="row"><?php echo esc_html( $this->copy->text( 'label_clone_status' ) ); ?></th>
					<td><code><?php echo esc_html( $status ); ?></code></td>
				</tr>
				<tr>
					<th scope="row"><?php echo esc_html( $this->copy->text( 'label_clone_export_tables_done' ) ); ?></th>
					<td><?php echo esc_html( (string) (int) ( $export['tables_completed'] ?? 0 ) . ' / ' . (string) (int) ( $export['table_count'] ?? 0 ) ); ?></td>
				</tr>
				<tr>
					<th scope="row"><?php echo esc_html( $this->copy->text( 'label_clone_export_rows' ) ); ?></th>
					<td><?php echo esc_html( (string) (int) ( $export['row_count'] ?? 0 ) ); ?></td>
				</tr>
				<tr>
					<th scope="row"><?php echo esc_html( $this->copy->text( 'label_clone_export_chunks' ) ); ?></th>
					<td><?php echo esc_html( (string) (int) ( $export['chunk_count'] ?? 0 ) ); ?></td>
				</tr>
				<tr>
					<th scope="row"><?php echo esc_html( $this->copy->text( 'label_clone_export_bytes' ) ); ?></th>
					<td><?php echo esc_html( size_format( (int) ( $export['byte_count'] ?? 0 ) ) ); ?></td>
				</tr>
				<?php if ( 'complete' === $status ) : ?>
					<tr>
						<th scope="row"><?php echo esc_html( $this->copy->text( 'label_clone_export_manifest' ) ); ?></th>
						<td><code><?php echo esc_html( (string) ( $export['database_manifest_hash'] ?? '' ) ); ?></code></td>
					</tr>
				<?php endif; ?>
			</tbody>
		</table>

		<?php if ( ! in_array( $status, array( 'complete', 'blocked' ), true ) ) : ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="<?php echo esc_attr( AdminCloneDatabaseExportController::ACTION ); ?>">
				<input type="hidden" name="clone_job_id" value="<?php echo esc_attr( $job_id ); ?>">
				<?php wp_nonce_field( AdminCloneDatabaseExportController::NONCE_ACTION . ':' . $job_id ); ?>
				<p>
					<label for="seo-geo-clone-db-batch"><strong><?php echo esc_html( $this->copy->text( 'clone_database_batch_label' ) ); ?></strong></label>
					<select id="seo-geo-clone-db-batch" name="database_batch_rows">
						<?php foreach ( array( 25, 50, 100, 200, 500 ) as $size ) : ?>
							<option value="<?php echo esc_attr( (string) $size ); ?>" <?php selected( DatabaseExporter::DEFAULT_BATCH_ROWS, $size ); ?>><?php echo esc_html( (string) $size ); ?></option>
						<?php endforeach; ?>
					</select>
				</p>
				<p class="description"><?php echo esc_html( $this->copy->text( 'clone_database_batch_help' ) ); ?></p>
				<?php submit_button( $this->copy->text( $button ), 'secondary', 'submit', false ); ?>
			</form>
		<?php elseif ( 'complete' === $status ) : ?>
			<?php $this->render_clone_file_export_section( $job ); ?>
		<?php endif; ?>
		<?php
	}

	/**
	 * Render a bounded result notice after a file-export batch.
	 */
	private function render_clone_file_export_result_notice(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only result notice after nonce-verified export action.
		$status = isset( $_GET['seo_geo_clone_file_export'] )
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Same read-only result notice value.
			? sanitize_key( wp_unslash( $_GET['seo_geo_clone_file_export'] ) )
			: '';

		$key = match ( $status ) {
			'complete' => 'clone_file_export_complete',
			'running'  => 'clone_file_export_running',
			'blocked'  => 'clone_file_export_blocked',
			default    => null,
		};
		if ( null === $key ) {
			return;
		}
		$class = 'blocked' === $status ? 'notice notice-error' : 'notice notice-success';
		?>
		<div class="<?php echo esc_attr( $class ); ?> is-dismissible">
			<p><?php echo esc_html( $this->copy->text( $key ) ); ?></p>
		</div>
		<?php
	}

	/**
	 * Render resumable private file export progress.
	 *
	 * @param array<string,mixed> $job Clone job.
	 */
	private function render_clone_file_export_section( array $job ): void {
		$job_id = is_string( $job['job_id'] ?? null ) ? $job['job_id'] : '';
		if ( '' === $job_id ) {
			return;
		}
		$export = ( new FileExportStateStore() )->get( $job_id );
		$status = is_array( $export ) ? (string) ( $export['status'] ?? 'pending' ) : 'pending';
		$button = 'pending' === $status ? 'clone_file_export_start' : 'clone_file_export_continue';
		?>
		<h3><?php echo esc_html( $this->copy->text( 'clone_file_export_heading' ) ); ?></h3>
		<p><?php echo esc_html( $this->copy->text( 'clone_file_export_help' ) ); ?></p>
		<table class="widefat striped" role="presentation">
			<tbody>
				<tr>
					<th scope="row"><?php echo esc_html( $this->copy->text( 'label_clone_status' ) ); ?></th>
					<td><code><?php echo esc_html( $status ); ?></code></td>
				</tr>
				<tr>
					<th scope="row"><?php echo esc_html( $this->copy->text( 'label_clone_file_export_files' ) ); ?></th>
					<td><?php echo esc_html( (string) (int) ( $export['file_count'] ?? 0 ) . ' / ' . (string) (int) ( $export['inventory_file_count'] ?? 0 ) ); ?></td>
				</tr>
				<tr>
					<th scope="row"><?php echo esc_html( $this->copy->text( 'label_clone_file_export_bytes' ) ); ?></th>
					<td><?php echo esc_html( size_format( (int) ( $export['byte_count'] ?? 0 ) ) . ' / ' . size_format( (int) ( $export['inventory_byte_count'] ?? 0 ) ) ); ?></td>
				</tr>
				<?php if ( 'complete' === $status ) : ?>
					<tr>
						<th scope="row"><?php echo esc_html( $this->copy->text( 'label_clone_files_manifest' ) ); ?></th>
						<td><code><?php echo esc_html( (string) ( $export['files_manifest_hash'] ?? '' ) ); ?></code></td>
					</tr>
				<?php endif; ?>
			</tbody>
		</table>

		<?php if ( ! in_array( $status, array( 'complete', 'blocked' ), true ) ) : ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="<?php echo esc_attr( AdminCloneFileExportController::ACTION ); ?>">
				<input type="hidden" name="clone_job_id" value="<?php echo esc_attr( $job_id ); ?>">
				<?php wp_nonce_field( AdminCloneFileExportController::NONCE_ACTION . ':' . $job_id ); ?>
				<p>
					<label for="seo-geo-clone-file-batch"><strong><?php echo esc_html( $this->copy->text( 'clone_file_batch_label' ) ); ?></strong></label>
					<select id="seo-geo-clone-file-batch" name="file_batch_size">
						<?php foreach ( array( 5, 10, 25, 50, 100, 200 ) as $size ) : ?>
							<option value="<?php echo esc_attr( (string) $size ); ?>" <?php selected( FileExporter::DEFAULT_BATCH_FILES, $size ); ?>><?php echo esc_html( (string) $size ); ?></option>
						<?php endforeach; ?>
					</select>
					<label for="seo-geo-clone-file-megabytes"><strong><?php echo esc_html( $this->copy->text( 'clone_file_megabytes_label' ) ); ?></strong></label>
					<select id="seo-geo-clone-file-megabytes" name="file_batch_megabytes">
						<?php foreach ( array( 1, 4, 8, 16, 32, 64 ) as $megabytes ) : ?>
							<option value="<?php echo esc_attr( (string) $megabytes ); ?>" <?php selected( 8, $megabytes ); ?>><?php echo esc_html( (string) $megabytes ); ?></option>
						<?php endforeach; ?>
					</select>
				</p>
				<p class="description"><?php echo esc_html( $this->copy->text( 'clone_file_batch_help' ) ); ?></p>
				<?php submit_button( $this->copy->text( $button ), 'secondary', 'submit', false ); ?>
			</form>
		<?php elseif ( 'complete' === $status ) : ?>
			<?php $this->render_clone_package_section( $job ); ?>
		<?php endif; ?>
		<?php
	}

	/**
	 * Render a bounded result notice after a package-integrity batch.
	 */
	private function render_clone_package_result_notice(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only result notice after nonce-verified package action.
		$status = isset( $_GET['seo_geo_clone_package'] )
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Same read-only result notice value.
			? sanitize_key( wp_unslash( $_GET['seo_geo_clone_package'] ) )
			: '';

		$key = match ( $status ) {
			'complete' => 'clone_package_complete',
			'running'  => 'clone_package_running',
			'blocked'  => 'clone_package_blocked',
			default    => null,
		};

		if ( null === $key ) {
			return;
		}

		$class = 'blocked' === $status ? 'notice notice-error' : 'notice notice-success';
		?>
		<div class="<?php echo esc_attr( $class ); ?> is-dismissible">
			<p><?php echo esc_html( $this->copy->text( $key ) ); ?></p>
		</div>
		<?php
	}

	/**
	 * Render resumable package-manifest/integrity progress.
	 *
	 * @param array<string,mixed> $job Clone job.
	 */
	private function render_clone_package_section( array $job ): void {
		$job_id = is_string( $job['job_id'] ?? null ) ? $job['job_id'] : '';
		if ( '' === $job_id ) {
			return;
		}

		$package = ( new PackageStateStore() )->get( $job_id );
		$status  = is_array( $package ) ? (string) ( $package['status'] ?? 'pending' ) : 'pending';
		$stage   = is_array( $package ) ? (string) ( $package['stage'] ?? 'pending' ) : 'pending';
		$button  = 'pending' === $status ? 'clone_package_start' : 'clone_package_continue';
		$files   = 'verify' === $stage
			? (int) ( $package['verify_file_count'] ?? 0 )
			: (int) ( $package['payload_file_count'] ?? 0 );
		$bytes   = 'verify' === $stage
			? (int) ( $package['verify_byte_count'] ?? 0 )
			: (int) ( $package['payload_byte_count'] ?? 0 );
		?>
		<h3><?php echo esc_html( $this->copy->text( 'clone_package_heading' ) ); ?></h3>
		<p><?php echo esc_html( $this->copy->text( 'clone_package_help' ) ); ?></p>
		<table class="widefat striped" role="presentation">
			<tbody>
				<tr>
					<th scope="row"><?php echo esc_html( $this->copy->text( 'label_clone_status' ) ); ?></th>
					<td><code><?php echo esc_html( $status ); ?></code></td>
				</tr>
				<tr>
					<th scope="row"><?php echo esc_html( $this->copy->text( 'clone_package_stage' ) ); ?></th>
					<td><code><?php echo esc_html( $stage ); ?></code></td>
				</tr>
				<tr>
					<th scope="row"><?php echo esc_html( $this->copy->text( 'clone_package_files' ) ); ?></th>
					<td><?php echo esc_html( (string) $files ); ?></td>
				</tr>
				<tr>
					<th scope="row"><?php echo esc_html( $this->copy->text( 'clone_package_bytes' ) ); ?></th>
					<td><?php echo esc_html( size_format( $bytes ) ); ?></td>
				</tr>
				<?php if ( is_array( $package ) && '' !== (string) ( $package['package_checksum'] ?? '' ) ) : ?>
					<tr>
						<th scope="row"><?php echo esc_html( $this->copy->text( 'clone_package_checksum' ) ); ?></th>
						<td><code><?php echo esc_html( (string) $package['package_checksum'] ); ?></code></td>
					</tr>
				<?php endif; ?>
				<?php if ( 'complete' === $status ) : ?>
					<tr>
						<th scope="row"><?php echo esc_html( $this->copy->text( 'clone_package_manifest' ) ); ?></th>
						<td><code><?php echo esc_html( (string) ( $package['package_manifest_hash'] ?? '' ) ); ?></code></td>
					</tr>
				<?php endif; ?>
			</tbody>
		</table>

		<?php if ( ! in_array( $status, array( 'complete', 'blocked' ), true ) ) : ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="<?php echo esc_attr( AdminClonePackageController::ACTION ); ?>">
				<input type="hidden" name="clone_job_id" value="<?php echo esc_attr( $job_id ); ?>">
				<?php wp_nonce_field( AdminClonePackageController::NONCE_ACTION . ':' . $job_id ); ?>
				<p>
					<label for="seo-geo-clone-package-batch"><strong><?php echo esc_html( $this->copy->text( 'clone_package_batch_label' ) ); ?></strong></label>
					<select id="seo-geo-clone-package-batch" name="package_batch_size">
						<?php foreach ( array( 25, 50, 100, 200, 500 ) as $size ) : ?>
							<option value="<?php echo esc_attr( (string) $size ); ?>" <?php selected( PackageBuilder::DEFAULT_BATCH_FILES, $size ); ?>><?php echo esc_html( (string) $size ); ?></option>
						<?php endforeach; ?>
					</select>
					<label for="seo-geo-clone-package-megabytes"><strong><?php echo esc_html( $this->copy->text( 'clone_package_mb_label' ) ); ?></strong></label>
					<select id="seo-geo-clone-package-megabytes" name="package_batch_megabytes">
						<?php foreach ( array( 4, 8, 16, 32, 64, 128 ) as $megabytes ) : ?>
							<option value="<?php echo esc_attr( (string) $megabytes ); ?>" <?php selected( 16, $megabytes ); ?>><?php echo esc_html( (string) $megabytes ); ?></option>
						<?php endforeach; ?>
					</select>
				</p>
				<p class="description"><?php echo esc_html( $this->copy->text( 'clone_package_batch_help' ) ); ?></p>
				<?php submit_button( $this->copy->text( $button ), 'secondary', 'submit', false ); ?>
			</form>
		<?php elseif ( 'complete' === $status && 'export' === ( $job['operation'] ?? null ) ) : ?>
			<?php $this->render_clone_delivery_section( $job ); ?>
		<?php elseif ( 'complete' === $status && 'local-clone' === ( $job['operation'] ?? null ) ) : ?>
			<?php $this->render_clone_local_plan_section( $job ); ?>
		<?php endif; ?>
		<?php
	}

	/**
	 * Render the result of a local-clone destination-plan action.
	 */
	private function render_clone_local_plan_result_notice(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only notice after nonce-verified action.
		$status = isset( $_GET['seo_geo_clone_local_plan'] )
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Same bounded result value.
			? sanitize_key( wp_unslash( $_GET['seo_geo_clone_local_plan'] ) )
			: '';

		$key = match ( $status ) {
			'ready'   => 'clone_local_plan_ready',
			'blocked' => 'clone_local_plan_blocked',
			default   => null,
		};
		if ( null === $key ) {
			return;
		}
		$class = 'blocked' === $status ? 'notice notice-error' : 'notice notice-success';
		?>
		<div class="<?php echo esc_attr( $class ); ?> is-dismissible">
			<p><?php echo esc_html( $this->copy->text( $key ) ); ?></p>
		</div>
		<?php
	}

	/**
	 * Render the frozen read-only local-clone destination contract.
	 *
	 * @param array<string,mixed> $job Clone job.
	 */
	private function render_clone_local_plan_section( array $job ): void {
		$job_id = is_string( $job['job_id'] ?? null ) ? $job['job_id'] : '';
		if ( '' === $job_id ) {
			return;
		}

		$state  = ( new LocalCloneStateStore() )->get( $job_id );
		$status = is_array( $state ) ? (string) ( $state['status'] ?? 'pending' ) : 'pending';
		global $wpdb;
		$default_path   = trailingslashit( wp_normalize_path( ABSPATH ) ) . 'nuevaweb';
		$default_url    = trailingslashit( home_url( '/nuevaweb/' ) );
		$source_prefix  = $wpdb instanceof \wpdb ? $wpdb->prefix : 'wp_';
		$default_prefix = $source_prefix . 'sg' . substr( hash( 'sha256', $job_id ), 0, 6 ) . '_';
		$target_path    = is_array( $state ) && '' !== (string) ( $state['target_path'] ?? '' ) ? (string) $state['target_path'] : $default_path;
		$target_url     = is_array( $state ) && '' !== (string) ( $state['target_url'] ?? '' ) ? (string) $state['target_url'] : $default_url;
		$target_prefix  = is_array( $state ) && '' !== (string) ( $state['target_table_prefix'] ?? '' ) ? (string) $state['target_table_prefix'] : $default_prefix;
		?>
		<h3><?php echo esc_html( $this->copy->text( 'clone_local_plan_heading' ) ); ?></h3>
		<p><?php echo esc_html( $this->copy->text( 'clone_local_plan_help' ) ); ?></p>
		<?php if ( is_array( $state ) ) : ?>
			<table class="widefat striped" role="presentation">
				<tbody>
					<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'label_clone_status' ) ); ?></th><td><code><?php echo esc_html( $status ); ?></code></td></tr>
					<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'clone_local_target_path' ) ); ?></th><td><code><?php echo esc_html( $target_path ); ?></code></td></tr>
					<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'clone_local_target_url' ) ); ?></th><td><code><?php echo esc_html( $target_url ); ?></code></td></tr>
					<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'clone_local_target_prefix' ) ); ?></th><td><code><?php echo esc_html( $target_prefix ); ?></code></td></tr>
					<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'clone_local_same_origin' ) ); ?></th><td><?php echo esc_html( true === ( $state['same_origin'] ?? false ) ? $this->copy->text( 'yes' ) : $this->copy->text( 'no' ) ); ?></td></tr>
					<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'clone_local_required_bytes' ) ); ?></th><td><?php echo esc_html( size_format( (int) ( $state['required_bytes'] ?? 0 ) ) ); ?></td></tr>
					<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'clone_local_free_bytes' ) ); ?></th><td><?php echo esc_html( null === ( $state['free_bytes'] ?? null ) ? '—' : size_format( (int) $state['free_bytes'] ) ); ?></td></tr>
					<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'clone_local_plan_hash' ) ); ?></th><td><code><?php echo esc_html( (string) ( $state['plan_hash'] ?? '' ) ); ?></code></td></tr>
					<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'clone_local_blockers' ) ); ?></th><td><code><?php echo esc_html( array() === ( $state['blockers'] ?? array() ) ? $this->copy->text( 'clone_import_none' ) : implode( ', ', (array) $state['blockers'] ) ); ?></code></td></tr>
					<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'clone_local_advisories' ) ); ?></th><td><code><?php echo esc_html( array() === ( $state['advisories'] ?? array() ) ? $this->copy->text( 'clone_import_none' ) : implode( ', ', (array) $state['advisories'] ) ); ?></code></td></tr>
				</tbody>
			</table>
		<?php endif; ?>

		<?php if ( 'ready' !== $status ) : ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="<?php echo esc_attr( AdminCloneLocalPlanController::ACTION ); ?>">
				<input type="hidden" name="clone_job_id" value="<?php echo esc_attr( $job_id ); ?>">
				<?php wp_nonce_field( AdminCloneLocalPlanController::NONCE_ACTION . ':' . $job_id ); ?>
				<p><label><strong><?php echo esc_html( $this->copy->text( 'clone_local_target_path' ) ); ?></strong><br><input class="regular-text code" type="text" name="local_clone_target_path" value="<?php echo esc_attr( $target_path ); ?>" required></label></p>
				<p><label><strong><?php echo esc_html( $this->copy->text( 'clone_local_target_url' ) ); ?></strong><br><input class="regular-text code" type="url" name="local_clone_target_url" value="<?php echo esc_attr( $target_url ); ?>" required></label></p>
				<p><label><strong><?php echo esc_html( $this->copy->text( 'clone_local_target_prefix' ) ); ?></strong><br><input class="regular-text code" type="text" name="local_clone_target_prefix" value="<?php echo esc_attr( $target_prefix ); ?>" pattern="[A-Za-z0-9_]+" required></label></p>
				<p><label><input type="checkbox" name="local_clone_confirm" value="1" required> <?php echo esc_html( $this->copy->text( 'clone_local_confirm' ) ); ?></label></p>
				<p class="description"><?php echo esc_html( $this->copy->text( 'clone_local_plan_readonly' ) ); ?></p>
				<?php submit_button( $this->copy->text( 'clone_local_plan_button' ), 'secondary', 'submit', false ); ?>
			</form>
		<?php else : ?>
			<p class="notice notice-info inline"><?php echo esc_html( $this->copy->text( 'clone_local_plan_next' ) ); ?></p>
			<?php $this->render_clone_local_bootstrap_section( $job ); ?>
		<?php endif; ?>
		<?php
	}

	/**
	 * Render a bounded result notice after local-clone target ownership actions.
	 */
	private function render_clone_local_bootstrap_result_notice(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only notice after nonce-verified action.
		$status = isset( $_GET['seo_geo_clone_local_bootstrap'] )
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Same bounded result value.
			? sanitize_key( wp_unslash( $_GET['seo_geo_clone_local_bootstrap'] ) )
			: '';

		$key = match ( $status ) {
			'claimed'  => 'clone_local_bootstrap_claimed',
			'blocked'  => 'clone_local_bootstrap_blocked',
			'released' => 'clone_local_bootstrap_released',
			default    => null,
		};
		if ( null === $key ) {
			return;
		}

		$class = 'blocked' === $status ? 'notice notice-error' : 'notice notice-success';
		?>
		<div class="<?php echo esc_attr( $class ); ?> is-dismissible">
			<p><?php echo esc_html( $this->copy->text( $key ) ); ?></p>
		</div>
		<?php
	}

	/**
	 * Render the local-clone target ownership/recovery contract.
	 *
	 * @param array<string,mixed> $job Clone job.
	 */
	private function render_clone_local_bootstrap_section( array $job ): void {
		$job_id = is_string( $job['job_id'] ?? null ) ? $job['job_id'] : '';
		if ( '' === $job_id ) {
			return;
		}

		$state  = ( new LocalCloneBootstrapStateStore() )->get( $job_id );
		$status = is_array( $state ) ? (string) ( $state['status'] ?? 'pending' ) : 'pending';
		?>
		<h3><?php echo esc_html( $this->copy->text( 'clone_local_bootstrap_heading' ) ); ?></h3>
		<p><?php echo esc_html( $this->copy->text( 'clone_local_bootstrap_help' ) ); ?></p>

		<?php if ( is_array( $state ) ) : ?>
			<table class="widefat striped" role="presentation">
				<tbody>
					<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'label_clone_status' ) ); ?></th><td><code><?php echo esc_html( $status ); ?></code></td></tr>
					<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'clone_local_bootstrap_owned' ) ); ?></th><td><?php echo esc_html( true === ( $state['target_owned'] ?? false ) ? $this->copy->text( 'yes' ) : $this->copy->text( 'no' ) ); ?></td></tr>
					<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'clone_local_bootstrap_created' ) ); ?></th><td><?php echo esc_html( true === ( $state['target_created'] ?? false ) ? $this->copy->text( 'yes' ) : $this->copy->text( 'no' ) ); ?></td></tr>
					<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'clone_local_bootstrap_marker' ) ); ?></th><td><code><?php echo esc_html( (string) ( $state['marker_relative_path'] ?? '' ) ); ?></code></td></tr>
					<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'clone_local_bootstrap_marker_hash' ) ); ?></th><td><code><?php echo esc_html( (string) ( $state['marker_sha256'] ?? '' ) ); ?></code></td></tr>
					<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'clone_local_bootstrap_source_safe' ) ); ?></th><td><?php echo esc_html( true === ( $state['production_untouched'] ?? false ) ? $this->copy->text( 'yes' ) : $this->copy->text( 'no' ) ); ?></td></tr>
					<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'clone_local_bootstrap_db_safe' ) ); ?></th><td><?php echo esc_html( true === ( $state['database_untouched'] ?? false ) ? $this->copy->text( 'yes' ) : $this->copy->text( 'no' ) ); ?></td></tr>
					<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'clone_local_blockers' ) ); ?></th><td><code><?php echo esc_html( array() === ( $state['blockers'] ?? array() ) ? $this->copy->text( 'clone_import_none' ) : implode( ', ', (array) $state['blockers'] ) ); ?></code></td></tr>
				</tbody>
			</table>
		<?php endif; ?>

		<?php if ( 'claimed' !== $status ) : ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="<?php echo esc_attr( AdminCloneLocalBootstrapController::CLAIM_ACTION ); ?>">
				<input type="hidden" name="clone_job_id" value="<?php echo esc_attr( $job_id ); ?>">
				<?php wp_nonce_field( AdminCloneLocalBootstrapController::NONCE_ACTION . ':' . AdminCloneLocalBootstrapController::CLAIM_ACTION . ':' . $job_id ); ?>
				<p><label><input type="checkbox" name="local_clone_bootstrap_confirm" value="1" required> <?php echo esc_html( $this->copy->text( 'clone_local_bootstrap_confirm' ) ); ?></label></p>
				<?php submit_button( $this->copy->text( 'clone_local_bootstrap_claim' ), 'secondary', 'submit', false ); ?>
			</form>
		<?php else : ?>
			<p class="notice notice-info inline"><?php echo esc_html( $this->copy->text( 'clone_local_bootstrap_next' ) ); ?></p>
			<?php $this->render_clone_local_runtime_section( $job ); ?>
			<?php if ( null === ( new LocalCloneRuntimeStateStore() )->get( $job_id ) ) : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="<?php echo esc_attr( AdminCloneLocalBootstrapController::RELEASE_ACTION ); ?>">
					<input type="hidden" name="clone_job_id" value="<?php echo esc_attr( $job_id ); ?>">
					<?php wp_nonce_field( AdminCloneLocalBootstrapController::NONCE_ACTION . ':' . AdminCloneLocalBootstrapController::RELEASE_ACTION . ':' . $job_id ); ?>
					<p><label><input type="checkbox" name="local_clone_bootstrap_release_confirm" value="1" required> <?php echo esc_html( $this->copy->text( 'clone_local_bootstrap_release_confirm' ) ); ?></label></p>
					<?php submit_button( $this->copy->text( 'clone_local_bootstrap_release' ), 'secondary', 'submit', false ); ?>
				</form>
			<?php endif; ?>
		<?php endif; ?>
		<?php
	}

	/**
	 * Render a bounded result notice after local-clone core runtime batches.
	 */
	private function render_clone_local_runtime_result_notice(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only notice after nonce-verified action.
		$status = isset( $_GET['seo_geo_clone_local_runtime'] )
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Same bounded result value.
			? sanitize_key( wp_unslash( $_GET['seo_geo_clone_local_runtime'] ) )
			: '';

		$key = match ( $status ) {
			'running'  => 'clone_local_runtime_running',
			'complete' => 'clone_local_runtime_complete',
			'blocked'  => 'clone_local_runtime_blocked',
			default    => null,
		};
		if ( null === $key ) {
			return;
		}

		$class = 'blocked' === $status ? 'notice notice-error' : ( 'complete' === $status ? 'notice notice-success' : 'notice notice-info' );
		?>
		<div class="<?php echo esc_attr( $class ); ?> is-dismissible">
			<p><?php echo esc_html( $this->copy->text( $key ) ); ?></p>
		</div>
		<?php
	}

	/**
	 * Render resumable local-clone WordPress core runtime controls.
	 *
	 * @param array<string,mixed> $job Clone job.
	 */
	private function render_clone_local_runtime_section( array $job ): void {
		$job_id = is_string( $job['job_id'] ?? null ) ? $job['job_id'] : '';
		if ( '' === $job_id ) {
			return;
		}

		$state  = ( new LocalCloneRuntimeStateStore() )->get( $job_id );
		$status = is_array( $state ) ? (string) ( $state['status'] ?? 'pending' ) : 'pending';
		$stage  = is_array( $state ) ? (string) ( $state['stage'] ?? 'pending' ) : 'pending';
		?>
		<h3><?php echo esc_html( $this->copy->text( 'clone_local_runtime_heading' ) ); ?></h3>
		<p><?php echo esc_html( $this->copy->text( 'clone_local_runtime_help' ) ); ?></p>

		<?php if ( is_array( $state ) ) : ?>
			<table class="widefat striped" role="presentation">
				<tbody>
					<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'label_clone_status' ) ); ?></th><td><code><?php echo esc_html( $status ); ?></code></td></tr>
					<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'clone_local_runtime_stage' ) ); ?></th><td><code><?php echo esc_html( $stage ); ?></code></td></tr>
					<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'clone_local_runtime_copy_files' ) ); ?></th><td><?php echo esc_html( (string) (int) ( $state['copy_file_count'] ?? 0 ) ); ?></td></tr>
					<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'clone_local_runtime_copy_bytes' ) ); ?></th><td><?php echo esc_html( size_format( (int) ( $state['copy_byte_count'] ?? 0 ) ) ); ?></td></tr>
					<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'clone_local_runtime_verify_files' ) ); ?></th><td><?php echo esc_html( (string) (int) ( $state['verify_file_count'] ?? 0 ) ); ?></td></tr>
					<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'clone_local_runtime_verify_bytes' ) ); ?></th><td><?php echo esc_html( size_format( (int) ( $state['verify_byte_count'] ?? 0 ) ) ); ?></td></tr>
					<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'clone_local_runtime_fingerprint' ) ); ?></th><td><code><?php echo esc_html( (string) ( $state['copy_fingerprint'] ?? '' ) ); ?></code></td></tr>
					<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'clone_local_runtime_content_safe' ) ); ?></th><td><?php echo esc_html( true === ( $state['wp_content_untouched'] ?? false ) ? $this->copy->text( 'yes' ) : $this->copy->text( 'no' ) ); ?></td></tr>
					<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'clone_local_bootstrap_db_safe' ) ); ?></th><td><?php echo esc_html( true === ( $state['database_untouched'] ?? false ) ? $this->copy->text( 'yes' ) : $this->copy->text( 'no' ) ); ?></td></tr>
					<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'clone_local_blockers' ) ); ?></th><td><code><?php echo esc_html( array() === ( $state['blockers'] ?? array() ) ? $this->copy->text( 'clone_import_none' ) : implode( ', ', (array) $state['blockers'] ) ); ?></code></td></tr>
				</tbody>
			</table>
		<?php endif; ?>

		<?php if ( 'complete' === $status ) : ?>
			<p class="notice notice-success inline"><?php echo esc_html( $this->copy->text( 'clone_local_runtime_next' ) ); ?></p>
			<?php $this->render_clone_local_sandbox_runtime_section( $job ); ?>
		<?php elseif ( 'blocked' !== $status ) : ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="<?php echo esc_attr( AdminCloneLocalRuntimeController::ACTION ); ?>">
				<input type="hidden" name="clone_job_id" value="<?php echo esc_attr( $job_id ); ?>">
				<?php wp_nonce_field( AdminCloneLocalRuntimeController::NONCE_ACTION . ':' . $job_id ); ?>
				<?php if ( ! is_array( $state ) ) : ?>
					<p><label><input type="checkbox" name="local_clone_runtime_confirm" value="1" required> <?php echo esc_html( $this->copy->text( 'clone_local_runtime_confirm' ) ); ?></label></p>
				<?php endif; ?>
				<p>
					<label for="seo-geo-local-runtime-batch"><strong><?php echo esc_html( $this->copy->text( 'clone_local_runtime_batch_label' ) ); ?></strong></label>
					<select id="seo-geo-local-runtime-batch" name="runtime_batch_files">
						<?php foreach ( array( 10, 25, 50, 100, 200 ) as $size ) : ?>
							<option value="<?php echo esc_attr( (string) $size ); ?>" <?php selected( LocalCloneRuntimeBootstrapper::DEFAULT_BATCH_FILES, $size ); ?>><?php echo esc_html( (string) $size ); ?></option>
						<?php endforeach; ?>
					</select>
					<label for="seo-geo-local-runtime-mb"><strong><?php echo esc_html( $this->copy->text( 'clone_local_runtime_mb_label' ) ); ?></strong></label>
					<select id="seo-geo-local-runtime-mb" name="runtime_batch_megabytes">
						<?php foreach ( array( 4, 8, 16, 32, 64 ) as $megabytes ) : ?>
							<option value="<?php echo esc_attr( (string) $megabytes ); ?>" <?php selected( 8, $megabytes ); ?>><?php echo esc_html( (string) $megabytes ); ?></option>
						<?php endforeach; ?>
					</select>
				</p>
				<p class="description"><?php echo esc_html( $this->copy->text( 'clone_local_runtime_batch_help' ) ); ?></p>
				<?php submit_button( $this->copy->text( is_array( $state ) ? 'clone_local_runtime_continue' : 'clone_local_runtime_start' ), 'secondary', 'submit', false ); ?>
			</form>
		<?php endif; ?>
		<?php
	}

	/**
	 * Render one result notice after isolated sandbox runtime batches.
	 */
	private function render_clone_local_sandbox_runtime_result_notice(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only notice after nonce-verified action.
		$status = isset( $_GET['seo_geo_clone_local_sandbox_runtime'] )
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Same bounded result value.
			? sanitize_key( wp_unslash( $_GET['seo_geo_clone_local_sandbox_runtime'] ) )
			: '';

		$key = match ( $status ) {
			'running'  => 'clone_local_sandbox_runtime_running',
			'complete' => 'clone_local_sandbox_runtime_complete',
			'blocked'  => 'clone_local_sandbox_runtime_blocked',
			default    => null,
		};
		if ( null === $key ) {
			return;
		}

		$class = 'blocked' === $status ? 'notice notice-error' : ( 'complete' === $status ? 'notice notice-success' : 'notice notice-info' );
		?>
		<div class="<?php echo esc_attr( $class ); ?> is-dismissible">
			<p><?php echo esc_html( $this->copy->text( $key ) ); ?></p>
		</div>
		<?php
	}

	/**
	 * Render isolated Migration Bridge/config/hardening runtime controls.
	 *
	 * @param array<string,mixed> $job Clone job.
	 */
	private function render_clone_local_sandbox_runtime_section( array $job ): void {
		$job_id = is_string( $job['job_id'] ?? null ) ? $job['job_id'] : '';
		if ( '' === $job_id ) {
			return;
		}

		$state  = ( new LocalCloneSandboxRuntimeStateStore() )->get( $job_id );
		$status = is_array( $state ) ? (string) ( $state['status'] ?? 'pending' ) : 'pending';
		$stage  = is_array( $state ) ? (string) ( $state['stage'] ?? 'pending' ) : 'pending';
		?>
		<h3><?php echo esc_html( $this->copy->text( 'clone_local_sandbox_runtime_heading' ) ); ?></h3>
		<p><?php echo esc_html( $this->copy->text( 'clone_local_sandbox_runtime_help' ) ); ?></p>

		<?php if ( is_array( $state ) ) : ?>
			<table class="widefat striped" role="presentation">
				<tbody>
					<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'label_clone_status' ) ); ?></th><td><code><?php echo esc_html( $status ); ?></code></td></tr>
					<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'clone_local_sandbox_runtime_stage' ) ); ?></th><td><code><?php echo esc_html( $stage ); ?></code></td></tr>
					<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'clone_local_sandbox_runtime_bridge_files' ) ); ?></th><td><?php echo esc_html( (string) (int) ( $state['bridge_copy_files'] ?? 0 ) ); ?></td></tr>
					<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'clone_local_sandbox_runtime_bridge_verified' ) ); ?></th><td><?php echo esc_html( (string) (int) ( $state['bridge_verify_files'] ?? 0 ) ); ?></td></tr>
					<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'clone_local_sandbox_runtime_config_hash' ) ); ?></th><td><code><?php echo esc_html( (string) ( $state['wp_config_sha256'] ?? '' ) ); ?></code></td></tr>
					<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'clone_local_sandbox_runtime_mu_hash' ) ); ?></th><td><code><?php echo esc_html( (string) ( $state['mu_plugin_sha256'] ?? '' ) ); ?></code></td></tr>
					<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'clone_local_sandbox_runtime_search' ) ); ?></th><td><?php echo esc_html( true === ( $state['search_blocked'] ?? false ) ? $this->copy->text( 'yes' ) : $this->copy->text( 'no' ) ); ?></td></tr>
					<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'clone_local_sandbox_runtime_outbound' ) ); ?></th><td><?php echo esc_html( true === ( $state['outbound_blocked'] ?? false ) ? $this->copy->text( 'yes' ) : $this->copy->text( 'no' ) ); ?></td></tr>
					<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'clone_local_sandbox_runtime_storage' ) ); ?></th><td><?php echo esc_html( true === ( $state['storage_isolated'] ?? false ) ? $this->copy->text( 'yes' ) : $this->copy->text( 'no' ) ); ?></td></tr>
					<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'clone_local_blockers' ) ); ?></th><td><code><?php echo esc_html( array() === ( $state['blockers'] ?? array() ) ? $this->copy->text( 'clone_import_none' ) : implode( ', ', (array) $state['blockers'] ) ); ?></code></td></tr>
				</tbody>
			</table>
		<?php endif; ?>

		<?php if ( 'complete' === $status ) : ?>
			<p class="notice notice-success inline"><?php echo esc_html( $this->copy->text( 'clone_local_sandbox_runtime_next' ) ); ?></p>
			<?php $this->render_clone_local_package_handoff_section( $job ); ?>
		<?php elseif ( 'blocked' !== $status ) : ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="<?php echo esc_attr( AdminCloneLocalSandboxRuntimeController::ACTION ); ?>">
				<input type="hidden" name="clone_job_id" value="<?php echo esc_attr( $job_id ); ?>">
				<?php wp_nonce_field( AdminCloneLocalSandboxRuntimeController::NONCE_ACTION . ':' . $job_id ); ?>
				<?php if ( ! is_array( $state ) ) : ?>
					<p><label><input type="checkbox" name="local_clone_sandbox_runtime_confirm" value="1" required> <?php echo esc_html( $this->copy->text( 'clone_local_sandbox_runtime_confirm' ) ); ?></label></p>
				<?php endif; ?>
				<p>
					<label for="seo-geo-local-sandbox-runtime-batch"><strong><?php echo esc_html( $this->copy->text( 'clone_local_runtime_batch_label' ) ); ?></strong></label>
					<select id="seo-geo-local-sandbox-runtime-batch" name="sandbox_runtime_batch_files">
						<?php foreach ( array( 10, 25, 50, 100, 200 ) as $size ) : ?>
							<option value="<?php echo esc_attr( (string) $size ); ?>" <?php selected( LocalCloneSandboxRuntimeBootstrapper::DEFAULT_BATCH_FILES, $size ); ?>><?php echo esc_html( (string) $size ); ?></option>
						<?php endforeach; ?>
					</select>
					<label for="seo-geo-local-sandbox-runtime-mb"><strong><?php echo esc_html( $this->copy->text( 'clone_local_runtime_mb_label' ) ); ?></strong></label>
					<select id="seo-geo-local-sandbox-runtime-mb" name="sandbox_runtime_batch_megabytes">
						<?php foreach ( array( 4, 8, 16, 32, 64 ) as $megabytes ) : ?>
							<option value="<?php echo esc_attr( (string) $megabytes ); ?>" <?php selected( 8, $megabytes ); ?>><?php echo esc_html( (string) $megabytes ); ?></option>
						<?php endforeach; ?>
					</select>
				</p>
				<p class="description"><?php echo esc_html( $this->copy->text( 'clone_local_sandbox_runtime_batch_help' ) ); ?></p>
				<?php submit_button( $this->copy->text( is_array( $state ) ? 'clone_local_sandbox_runtime_continue' : 'clone_local_sandbox_runtime_start' ), 'secondary', 'submit', false ); ?>
			</form>
		<?php endif; ?>
		<?php
	}

	/**
	 * Render a bounded result notice after private local-package handoff batches.
	 */
	private function render_clone_local_package_handoff_result_notice(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only notice after nonce-verified action.
		$status = isset( $_GET['seo_geo_clone_local_package_handoff'] )
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Same bounded result value.
			? sanitize_key( wp_unslash( $_GET['seo_geo_clone_local_package_handoff'] ) )
			: '';

		$key = match ( $status ) {
			'building' => 'clone_local_handoff_building',
			'ready'    => 'clone_local_handoff_ready',
			'blocked'  => 'clone_local_handoff_blocked',
			default    => null,
		};
		if ( null === $key ) {
			return;
		}

		$class = 'blocked' === $status ? 'notice notice-error' : ( 'ready' === $status ? 'notice notice-success' : 'notice notice-info' );
		?>
		<div class="<?php echo esc_attr( $class ); ?> is-dismissible">
			<p><?php echo esc_html( $this->copy->text( $key ) ); ?></p>
		</div>
		<?php
	}

	/**
	 * Render the private same-server package handoff controls.
	 *
	 * @param array<string,mixed> $job Clone job.
	 */
	private function render_clone_local_package_handoff_section( array $job ): void {
		$job_id = is_string( $job['job_id'] ?? null ) ? $job['job_id'] : '';
		if ( '' === $job_id ) {
			return;
		}

		$state  = ( new LocalClonePackageHandoffStateStore() )->get( $job_id );
		$status = is_array( $state ) ? (string) ( $state['status'] ?? 'pending' ) : 'pending';
		?>
		<h3><?php echo esc_html( $this->copy->text( 'clone_local_handoff_heading' ) ); ?></h3>
		<p><?php echo esc_html( $this->copy->text( 'clone_local_handoff_help' ) ); ?></p>

		<?php if ( is_array( $state ) ) : ?>
			<table class="widefat striped" role="presentation">
				<tbody>
					<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'label_clone_status' ) ); ?></th><td><code><?php echo esc_html( $status ); ?></code></td></tr>
					<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'clone_local_handoff_transport' ) ); ?></th><td><code><?php echo esc_html( (string) ( $state['transport'] ?? '' ) ); ?></code></td></tr>
					<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'clone_local_handoff_archive_bytes' ) ); ?></th><td><?php echo esc_html( size_format( (int) ( $state['archive_bytes'] ?? 0 ) ) ); ?></td></tr>
					<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'clone_local_handoff_archive_hash' ) ); ?></th><td><code><?php echo esc_html( (string) ( $state['archive_sha256'] ?? '' ) ); ?></code></td></tr>
					<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'clone_local_handoff_next_label' ) ); ?></th><td><code><?php echo esc_html( (string) ( $state['handoff_next'] ?? '' ) ); ?></code></td></tr>
					<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'clone_local_blockers' ) ); ?></th><td><code><?php echo esc_html( array() === ( $state['blockers'] ?? array() ) ? $this->copy->text( 'clone_import_none' ) : implode( ', ', (array) $state['blockers'] ) ); ?></code></td></tr>
				</tbody>
			</table>
		<?php endif; ?>

		<?php if ( 'ready' === $status ) : ?>
			<p class="notice notice-success inline"><?php echo esc_html( $this->copy->text( 'clone_local_handoff_next' ) ); ?></p>
			<?php $this->render_clone_local_target_preflight_section( $job ); ?>
		<?php elseif ( 'blocked' !== $status ) : ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="<?php echo esc_attr( AdminCloneLocalPackageHandoffController::ACTION ); ?>">
				<input type="hidden" name="clone_job_id" value="<?php echo esc_attr( $job_id ); ?>">
				<?php wp_nonce_field( AdminCloneLocalPackageHandoffController::NONCE_ACTION . ':' . $job_id ); ?>
				<?php if ( ! is_array( $state ) ) : ?>
					<p><label><input type="checkbox" name="local_clone_package_handoff_confirm" value="1" required> <?php echo esc_html( $this->copy->text( 'clone_local_handoff_confirm' ) ); ?></label></p>
				<?php endif; ?>
				<p>
					<label for="seo-geo-local-handoff-batch"><strong><?php echo esc_html( $this->copy->text( 'clone_local_handoff_batch_label' ) ); ?></strong></label>
					<select id="seo-geo-local-handoff-batch" name="handoff_batch_files">
						<?php foreach ( array( 25, 50, 100, 200, 500 ) as $size ) : ?>
							<option value="<?php echo esc_attr( (string) $size ); ?>" <?php selected( LocalClonePackageHandoff::DEFAULT_BATCH_FILES, $size ); ?>><?php echo esc_html( (string) $size ); ?></option>
						<?php endforeach; ?>
					</select>
					<label for="seo-geo-local-handoff-mb"><strong><?php echo esc_html( $this->copy->text( 'clone_local_handoff_mb_label' ) ); ?></strong></label>
					<select id="seo-geo-local-handoff-mb" name="handoff_batch_megabytes">
						<?php foreach ( array( 4, 8, 16, 32, 64, 128 ) as $megabytes ) : ?>
							<option value="<?php echo esc_attr( (string) $megabytes ); ?>" <?php selected( 16, $megabytes ); ?>><?php echo esc_html( (string) $megabytes ); ?></option>
						<?php endforeach; ?>
					</select>
				</p>
				<p class="description"><?php echo esc_html( $this->copy->text( 'clone_local_handoff_batch_help' ) ); ?></p>
				<?php submit_button( $this->copy->text( is_array( $state ) ? 'clone_local_handoff_continue' : 'clone_local_handoff_start' ), 'secondary', 'submit', false ); ?>
			</form>
		<?php endif; ?>
		<?php
	}


	/**
	 * Render a bounded result notice after private local target intake/preflight.
	 */
	private function render_clone_local_target_preflight_result_notice(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only notice after nonce-verified action.
		$status = isset( $_GET['seo_geo_clone_local_target_preflight'] )
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Same bounded result value.
			? sanitize_key( wp_unslash( $_GET['seo_geo_clone_local_target_preflight'] ) )
			: '';

		$key = match ( $status ) {
			'running' => 'clone_local_target_preflight_running',
			'ready'   => 'clone_local_target_preflight_ready',
			'blocked' => 'clone_local_target_preflight_blocked',
			default   => null,
		};
		if ( null === $key ) {
			return;
		}

		$class = 'blocked' === $status ? 'notice notice-error' : ( 'ready' === $status ? 'notice notice-success' : 'notice notice-info' );
		?>
		<div class="<?php echo esc_attr( $class ); ?> is-dismissible">
			<p><?php echo esc_html( $this->copy->text( $key ) ); ?></p>
		</div>
		<?php
	}

	/**
	 * Render private same-server target intake/preflight controls.
	 *
	 * @param array<string,mixed> $job Parent local-clone job.
	 */
	private function render_clone_local_target_preflight_section( array $job ): void {
		$job_id = is_string( $job['job_id'] ?? null ) ? $job['job_id'] : '';
		if ( '' === $job_id ) {
			return;
		}

		$state  = ( new LocalCloneTargetPreflightStateStore() )->get( $job_id );
		$status = is_array( $state ) ? (string) ( $state['status'] ?? 'pending' ) : 'pending';
		?>
		<h3><?php echo esc_html( $this->copy->text( 'clone_local_target_preflight_heading' ) ); ?></h3>
		<p><?php echo esc_html( $this->copy->text( 'clone_local_target_preflight_help' ) ); ?></p>

		<?php if ( is_array( $state ) ) : ?>
			<table class="widefat striped" role="presentation">
				<tbody>
					<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'label_clone_status' ) ); ?></th><td><code><?php echo esc_html( $status ); ?></code></td></tr>
					<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'clone_local_target_preflight_child_job' ) ); ?></th><td><code><?php echo esc_html( (string) ( $state['child_import_job_id'] ?? '' ) ); ?></code></td></tr>
					<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'clone_local_target_preflight_import_status' ) ); ?></th><td><code><?php echo esc_html( (string) ( $state['import_preflight_status'] ?? '' ) ); ?></code></td></tr>
					<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'clone_import_archive_bytes' ) ); ?></th><td><?php echo esc_html( size_format( (int) ( $state['import_archive_bytes'] ?? 0 ) ) ); ?></td></tr>
					<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'clone_import_archive_hash' ) ); ?></th><td><code><?php echo esc_html( (string) ( $state['import_archive_sha256'] ?? '' ) ); ?></code></td></tr>
					<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'clone_import_restore_allowed' ) ); ?></th><td><?php echo esc_html( true === ( $state['restore_allowed'] ?? false ) ? $this->copy->text( 'yes' ) : $this->copy->text( 'no' ) ); ?></td></tr>
					<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'clone_local_target_preflight_database_safe' ) ); ?></th><td><?php echo esc_html( true === ( $state['database_untouched'] ?? false ) ? $this->copy->text( 'yes' ) : $this->copy->text( 'no' ) ); ?></td></tr>
					<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'clone_local_target_preflight_content_safe' ) ); ?></th><td><?php echo esc_html( true === ( $state['client_content_untouched'] ?? false ) ? $this->copy->text( 'yes' ) : $this->copy->text( 'no' ) ); ?></td></tr>
					<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'clone_local_handoff_next_label' ) ); ?></th><td><code><?php echo esc_html( (string) ( $state['preflight_next'] ?? '' ) ); ?></code></td></tr>
					<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'clone_local_blockers' ) ); ?></th><td><code><?php echo esc_html( array() === ( $state['blockers'] ?? array() ) ? $this->copy->text( 'clone_import_none' ) : implode( ', ', (array) $state['blockers'] ) ); ?></code></td></tr>
				</tbody>
			</table>
		<?php endif; ?>

		<?php if ( 'ready' === $status ) : ?>
			<p class="notice notice-success inline"><?php echo esc_html( $this->copy->text( 'clone_local_target_preflight_next' ) ); ?></p>
			<?php $this->render_clone_local_payload_section( $job ); ?>
		<?php elseif ( 'blocked' !== $status ) : ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="<?php echo esc_attr( AdminCloneLocalTargetPreflightController::ACTION ); ?>">
				<input type="hidden" name="clone_job_id" value="<?php echo esc_attr( $job_id ); ?>">
				<?php wp_nonce_field( AdminCloneLocalTargetPreflightController::NONCE_ACTION . ':' . $job_id ); ?>
				<?php if ( ! is_array( $state ) ) : ?>
					<p><label><input type="checkbox" name="local_clone_target_preflight_confirm" value="1" required> <?php echo esc_html( $this->copy->text( 'clone_local_target_preflight_confirm' ) ); ?></label></p>
				<?php endif; ?>
				<?php submit_button( $this->copy->text( 'clone_local_target_preflight_start' ), 'secondary', 'submit', false ); ?>
			</form>
		<?php endif; ?>
		<?php
	}


	/**
	 * Render a bounded result notice after local payload verification batches.
	 */
	private function render_clone_local_payload_result_notice(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only notice after nonce-verified action.
		$status = isset( $_GET['seo_geo_clone_local_payload'] )
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Same bounded result value.
			? sanitize_key( wp_unslash( $_GET['seo_geo_clone_local_payload'] ) )
			: '';

		$key = match ( $status ) {
			'running' => 'clone_local_payload_running',
			'ready'   => 'clone_local_payload_ready',
			'blocked' => 'clone_local_payload_blocked',
			default   => null,
		};
		if ( null === $key ) {
			return;
		}

		$class = 'blocked' === $status ? 'notice notice-error' : ( 'ready' === $status ? 'notice notice-success' : 'notice notice-info' );
		?>
		<div class="<?php echo esc_attr( $class ); ?> is-dismissible">
			<p><?php echo esc_html( $this->copy->text( $key ) ); ?></p>
		</div>
		<?php
	}

	/**
	 * Render local private payload extraction/checksum controls.
	 *
	 * @param array<string,mixed> $job Parent local-clone job.
	 */
	private function render_clone_local_payload_section( array $job ): void {
		$job_id = is_string( $job['job_id'] ?? null ) ? $job['job_id'] : '';
		if ( '' === $job_id ) {
			return;
		}

		$state  = ( new LocalClonePayloadVerificationStateStore() )->get( $job_id );
		$status = is_array( $state ) ? (string) ( $state['status'] ?? 'pending' ) : 'pending';
		$stage  = is_array( $state ) ? (string) ( $state['stage'] ?? 'pending' ) : 'pending';
		?>
		<h3><?php echo esc_html( $this->copy->text( 'clone_local_payload_heading' ) ); ?></h3>
		<p><?php echo esc_html( $this->copy->text( 'clone_local_payload_help' ) ); ?></p>

		<?php if ( is_array( $state ) ) : ?>
			<table class="widefat striped" role="presentation">
				<tbody>
					<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'label_clone_status' ) ); ?></th><td><code><?php echo esc_html( $status ); ?></code></td></tr>
					<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'clone_local_payload_stage' ) ); ?></th><td><code><?php echo esc_html( $stage ); ?></code></td></tr>
					<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'clone_local_target_preflight_child_job' ) ); ?></th><td><code><?php echo esc_html( (string) ( $state['child_import_job_id'] ?? '' ) ); ?></code></td></tr>
					<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'clone_local_payload_extracted_files' ) ); ?></th><td><?php echo esc_html( (string) (int) ( $state['extract_file_count'] ?? 0 ) ); ?></td></tr>
					<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'clone_local_payload_verified_files' ) ); ?></th><td><?php echo esc_html( (string) (int) ( $state['verify_file_count'] ?? 0 ) ); ?> / <?php echo esc_html( (string) (int) ( $state['expected_file_count'] ?? 0 ) ); ?></td></tr>
					<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'clone_local_payload_verified_bytes' ) ); ?></th><td><?php echo esc_html( size_format( (int) ( $state['verify_byte_count'] ?? 0 ) ) ); ?> / <?php echo esc_html( size_format( (int) ( $state['expected_byte_count'] ?? 0 ) ) ); ?></td></tr>
					<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'clone_local_payload_checksum' ) ); ?></th><td><code><?php echo esc_html( (string) ( $state['verification_checksum'] ?? '' ) ); ?></code></td></tr>
					<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'clone_local_payload_restore_gate' ) ); ?></th><td><?php echo esc_html( true === ( $state['child_restore_allowed'] ?? false ) ? $this->copy->text( 'yes' ) : $this->copy->text( 'no' ) ); ?></td></tr>
					<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'clone_local_target_preflight_database_safe' ) ); ?></th><td><?php echo esc_html( true === ( $state['database_untouched'] ?? false ) ? $this->copy->text( 'yes' ) : $this->copy->text( 'no' ) ); ?></td></tr>
					<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'clone_local_target_preflight_content_safe' ) ); ?></th><td><?php echo esc_html( true === ( $state['client_content_untouched'] ?? false ) ? $this->copy->text( 'yes' ) : $this->copy->text( 'no' ) ); ?></td></tr>
					<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'clone_local_handoff_next_label' ) ); ?></th><td><code><?php echo esc_html( (string) ( $state['payload_next'] ?? '' ) ); ?></code></td></tr>
					<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'clone_local_blockers' ) ); ?></th><td><code><?php echo esc_html( array() === ( $state['blockers'] ?? array() ) ? $this->copy->text( 'clone_import_none' ) : implode( ', ', (array) $state['blockers'] ) ); ?></code></td></tr>
				</tbody>
			</table>
		<?php endif; ?>

		<?php if ( 'ready' === $status ) : ?>
			<p class="notice notice-success inline"><?php echo esc_html( $this->copy->text( 'clone_local_payload_next' ) ); ?></p>
			<?php $this->render_clone_local_database_section( $job ); ?>
		<?php else : ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="<?php echo esc_attr( AdminCloneLocalPayloadController::ACTION ); ?>">
				<input type="hidden" name="clone_job_id" value="<?php echo esc_attr( $job_id ); ?>">
				<?php wp_nonce_field( AdminCloneLocalPayloadController::NONCE_ACTION . ':' . $job_id ); ?>
				<?php if ( ! is_array( $state ) ) : ?>
					<p><label><input type="checkbox" name="local_clone_payload_confirm" value="1" required> <?php echo esc_html( $this->copy->text( 'clone_local_payload_confirm' ) ); ?></label></p>
				<?php endif; ?>
				<p>
					<label for="seo-geo-local-payload-batch"><strong><?php echo esc_html( $this->copy->text( 'clone_local_payload_batch_label' ) ); ?></strong></label>
					<select id="seo-geo-local-payload-batch" name="local_clone_payload_batch_size">
						<?php foreach ( array( 10, 25, 50, 100, 200, 500 ) as $size ) : ?>
							<option value="<?php echo esc_attr( (string) $size ); ?>" <?php selected( ImportPayloadVerifier::DEFAULT_BATCH_FILES, $size ); ?>><?php echo esc_html( (string) $size ); ?></option>
						<?php endforeach; ?>
					</select>
					<label for="seo-geo-local-payload-mb"><strong><?php echo esc_html( $this->copy->text( 'clone_local_payload_mb_label' ) ); ?></strong></label>
					<select id="seo-geo-local-payload-mb" name="local_clone_payload_batch_megabytes">
						<?php foreach ( array( 4, 8, 16, 32, 64, 128 ) as $megabytes ) : ?>
							<option value="<?php echo esc_attr( (string) $megabytes ); ?>" <?php selected( 8, $megabytes ); ?>><?php echo esc_html( (string) $megabytes ); ?></option>
						<?php endforeach; ?>
					</select>
				</p>
				<p class="description"><?php echo esc_html( $this->copy->text( 'clone_local_payload_batch_help' ) ); ?></p>
				<?php submit_button( $this->copy->text( is_array( $state ) ? 'clone_local_payload_continue' : 'clone_local_payload_start' ), 'secondary', 'submit', false ); ?>
			</form>
		<?php endif; ?>
		<?php
	}

	/**
	 * Render a bounded result notice after local database staging batches.
	 */
	private function render_clone_local_database_result_notice(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only notice after nonce-verified action.
		$status = isset( $_GET['seo_geo_clone_local_database'] )
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Same bounded result value.
			? sanitize_key( wp_unslash( $_GET['seo_geo_clone_local_database'] ) )
			: '';

		$key = match ( $status ) {
			'running' => 'clone_local_database_running',
			'ready'   => 'clone_local_database_ready',
			'blocked' => 'clone_local_database_blocked',
			default   => null,
		};
		if ( null === $key ) {
			return;
		}

		$class = 'blocked' === $status ? 'notice notice-error' : ( 'ready' === $status ? 'notice notice-success' : 'notice notice-info' );
		?>
		<div class="<?php echo esc_attr( $class ); ?> is-dismissible">
			<p><?php echo esc_html( $this->copy->text( $key ) ); ?></p>
		</div>
		<?php
	}

	/**
	 * Render local transactional database staging controls.
	 *
	 * @param array<string,mixed> $job Parent local-clone job.
	 */
	private function render_clone_local_database_section( array $job ): void {
		$job_id = is_string( $job['job_id'] ?? null ) ? $job['job_id'] : '';
		if ( '' === $job_id ) {
			return;
		}

		$state  = ( new LocalCloneDatabaseRestoreStateStore() )->get( $job_id );
		$status = is_array( $state ) ? (string) ( $state['status'] ?? 'pending' ) : 'pending';
		$stage  = is_array( $state ) ? (string) ( $state['stage'] ?? 'pending' ) : 'pending';
		?>
		<h3><?php echo esc_html( $this->copy->text( 'clone_local_database_heading' ) ); ?></h3>
		<p><?php echo esc_html( $this->copy->text( 'clone_local_database_help' ) ); ?></p>

		<?php if ( is_array( $state ) ) : ?>
			<table class="widefat striped" role="presentation">
				<tbody>
					<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'label_clone_status' ) ); ?></th><td><code><?php echo esc_html( $status ); ?></code></td></tr>
					<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'clone_local_database_stage' ) ); ?></th><td><code><?php echo esc_html( $stage ); ?></code></td></tr>
					<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'clone_local_target_preflight_child_job' ) ); ?></th><td><code><?php echo esc_html( (string) ( $state['child_import_job_id'] ?? '' ) ); ?></code></td></tr>
					<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'clone_local_database_destination_prefix' ) ); ?></th><td><code><?php echo esc_html( (string) ( $state['destination_prefix'] ?? '' ) ); ?></code></td></tr>
					<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'clone_local_database_staging_namespace' ) ); ?></th><td><code><?php echo esc_html( (string) ( $state['staging_namespace'] ?? '' ) ); ?></code></td></tr>
					<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'clone_local_database_tables' ) ); ?></th><td><?php echo esc_html( (string) (int) ( $state['tables_completed'] ?? 0 ) ); ?> / <?php echo esc_html( (string) (int) ( $state['table_count'] ?? 0 ) ); ?></td></tr>
					<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'clone_local_database_rows' ) ); ?></th><td><?php echo esc_html( (string) (int) ( $state['rows_restored'] ?? 0 ) ); ?></td></tr>
					<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'clone_local_database_active_safe' ) ); ?></th><td><?php echo esc_html( true === ( $state['active_tables_untouched'] ?? false ) ? $this->copy->text( 'yes' ) : $this->copy->text( 'no' ) ); ?></td></tr>
					<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'clone_local_database_target_safe' ) ); ?></th><td><?php echo esc_html( true === ( $state['target_tables_untouched'] ?? false ) ? $this->copy->text( 'yes' ) : $this->copy->text( 'no' ) ); ?></td></tr>
					<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'clone_local_target_preflight_content_safe' ) ); ?></th><td><?php echo esc_html( true === ( $state['client_content_untouched'] ?? false ) ? $this->copy->text( 'yes' ) : $this->copy->text( 'no' ) ); ?></td></tr>
					<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'clone_local_handoff_next_label' ) ); ?></th><td><code><?php echo esc_html( (string) ( $state['database_next'] ?? '' ) ); ?></code></td></tr>
					<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'clone_local_blockers' ) ); ?></th><td><code><?php echo esc_html( array() === ( $state['blockers'] ?? array() ) ? $this->copy->text( 'clone_import_none' ) : implode( ', ', (array) $state['blockers'] ) ); ?></code></td></tr>
				</tbody>
			</table>
		<?php endif; ?>

		<?php if ( 'ready' === $status ) : ?>
			<p class="notice notice-success inline"><?php echo esc_html( $this->copy->text( 'clone_local_database_next' ) ); ?></p>
			<?php $this->render_clone_local_file_section( $job ); ?>
		<?php else : ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="<?php echo esc_attr( AdminCloneLocalDatabaseController::ACTION ); ?>">
				<input type="hidden" name="clone_job_id" value="<?php echo esc_attr( $job_id ); ?>">
				<?php wp_nonce_field( AdminCloneLocalDatabaseController::NONCE_ACTION . ':' . $job_id ); ?>
				<?php if ( ! is_array( $state ) ) : ?>
					<p><label><input type="checkbox" name="local_clone_database_confirm" value="1" required> <?php echo esc_html( $this->copy->text( 'clone_local_database_confirm' ) ); ?></label></p>
				<?php endif; ?>
				<p>
					<label for="seo-geo-local-database-batch"><strong><?php echo esc_html( $this->copy->text( 'clone_local_database_batch_label' ) ); ?></strong></label>
					<select id="seo-geo-local-database-batch" name="local_clone_database_batch_rows">
						<?php foreach ( array( 10, 25, 50, 100, 200, 500 ) as $rows ) : ?>
							<option value="<?php echo esc_attr( (string) $rows ); ?>" <?php selected( ImportDatabaseRestorer::DEFAULT_BATCH_ROWS, $rows ); ?>><?php echo esc_html( (string) $rows ); ?></option>
						<?php endforeach; ?>
					</select>
				</p>
				<p class="description"><?php echo esc_html( $this->copy->text( 'clone_local_database_batch_help' ) ); ?></p>
				<?php submit_button( $this->copy->text( is_array( $state ) ? 'clone_local_database_continue' : 'clone_local_database_start' ), 'secondary', 'submit', false ); ?>
			</form>
		<?php endif; ?>
		<?php
	}

	/**
	 * Render a bounded result notice after local private file staging batches.
	 */
	private function render_clone_local_file_result_notice(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only notice after nonce-verified action.
		$status = isset( $_GET['seo_geo_clone_local_files'] )
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Same bounded result value.
			? sanitize_key( wp_unslash( $_GET['seo_geo_clone_local_files'] ) )
			: '';

		$key = match ( $status ) {
			'running' => 'clone_local_files_running',
			'ready'   => 'clone_local_files_ready',
			'blocked' => 'clone_local_files_blocked',
			default   => null,
		};
		if ( null === $key ) {
			return;
		}

		$class = 'blocked' === $status ? 'notice notice-error' : ( 'ready' === $status ? 'notice notice-success' : 'notice notice-info' );
		?>
		<div class="<?php echo esc_attr( $class ); ?> is-dismissible">
			<p><?php echo esc_html( $this->copy->text( $key ) ); ?></p>
		</div>
		<?php
	}

	/**
	 * Render local private file staging controls.
	 *
	 * @param array<string,mixed> $job Parent local-clone job.
	 */
	private function render_clone_local_file_section( array $job ): void {
		$job_id = is_string( $job['job_id'] ?? null ) ? $job['job_id'] : '';
		if ( '' === $job_id ) {
			return;
		}

		$state  = ( new LocalCloneFileRestoreStateStore() )->get( $job_id );
		$status = is_array( $state ) ? (string) ( $state['status'] ?? 'pending' ) : 'pending';
		$stage  = is_array( $state ) ? (string) ( $state['stage'] ?? 'pending' ) : 'pending';
		?>
		<h3><?php echo esc_html( $this->copy->text( 'clone_local_files_heading' ) ); ?></h3>
		<p><?php echo esc_html( $this->copy->text( 'clone_local_files_help' ) ); ?></p>

		<?php if ( is_array( $state ) ) : ?>
			<table class="widefat striped" role="presentation">
				<tbody>
					<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'label_clone_status' ) ); ?></th><td><code><?php echo esc_html( $status ); ?></code></td></tr>
					<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'clone_local_files_stage' ) ); ?></th><td><code><?php echo esc_html( $stage ); ?></code></td></tr>
					<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'clone_local_target_preflight_child_job' ) ); ?></th><td><code><?php echo esc_html( (string) ( $state['child_import_job_id'] ?? '' ) ); ?></code></td></tr>
					<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'clone_local_files_copied' ) ); ?></th><td><?php echo esc_html( (string) (int) ( $state['file_count'] ?? 0 ) ); ?> / <?php echo esc_html( (string) (int) ( $state['expected_file_count'] ?? 0 ) ); ?></td></tr>
					<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'clone_local_files_copied_bytes' ) ); ?></th><td><?php echo esc_html( size_format( (int) ( $state['byte_count'] ?? 0 ) ) ); ?> / <?php echo esc_html( size_format( (int) ( $state['expected_byte_count'] ?? 0 ) ) ); ?></td></tr>
					<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'clone_local_files_verified' ) ); ?></th><td><?php echo esc_html( (string) (int) ( $state['verify_file_count'] ?? 0 ) ); ?> / <?php echo esc_html( (string) (int) ( $state['expected_file_count'] ?? 0 ) ); ?></td></tr>
					<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'clone_local_files_verified_bytes' ) ); ?></th><td><?php echo esc_html( size_format( (int) ( $state['verify_byte_count'] ?? 0 ) ) ); ?> / <?php echo esc_html( size_format( (int) ( $state['expected_byte_count'] ?? 0 ) ) ); ?></td></tr>
					<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'clone_local_files_active_safe' ) ); ?></th><td><?php echo esc_html( true === ( $state['active_roots_untouched'] ?? false ) ? $this->copy->text( 'yes' ) : $this->copy->text( 'no' ) ); ?></td></tr>
					<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'clone_local_files_target_safe' ) ); ?></th><td><?php echo esc_html( true === ( $state['target_client_roots_untouched'] ?? false ) ? $this->copy->text( 'yes' ) : $this->copy->text( 'no' ) ); ?></td></tr>
					<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'clone_local_files_private_verified' ) ); ?></th><td><?php echo esc_html( true === ( $state['private_staging_verified'] ?? false ) ? $this->copy->text( 'yes' ) : $this->copy->text( 'no' ) ); ?></td></tr>
					<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'clone_local_handoff_next_label' ) ); ?></th><td><code><?php echo esc_html( (string) ( $state['file_next'] ?? '' ) ); ?></code></td></tr>
					<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'clone_local_blockers' ) ); ?></th><td><code><?php echo esc_html( array() === ( $state['blockers'] ?? array() ) ? $this->copy->text( 'clone_import_none' ) : implode( ', ', (array) $state['blockers'] ) ); ?></code></td></tr>
				</tbody>
			</table>
		<?php endif; ?>

		<?php if ( 'ready' === $status ) : ?>
			<p class="notice notice-success inline"><?php echo esc_html( $this->copy->text( 'clone_local_files_next' ) ); ?></p>
			<?php $this->render_clone_local_rewrite_section( $job ); ?>
		<?php else : ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="<?php echo esc_attr( AdminCloneLocalFileController::ACTION ); ?>">
				<input type="hidden" name="clone_job_id" value="<?php echo esc_attr( $job_id ); ?>">
				<?php wp_nonce_field( AdminCloneLocalFileController::NONCE_ACTION . ':' . $job_id ); ?>
				<?php if ( ! is_array( $state ) ) : ?>
					<p><label><input type="checkbox" name="local_clone_files_confirm" value="1" required> <?php echo esc_html( $this->copy->text( 'clone_local_files_confirm' ) ); ?></label></p>
				<?php endif; ?>
				<p>
					<label for="seo-geo-local-files-batch"><strong><?php echo esc_html( $this->copy->text( 'clone_local_files_batch_label' ) ); ?></strong></label>
					<select id="seo-geo-local-files-batch" name="local_clone_files_batch_size">
						<?php foreach ( array( 10, 25, 50, 100, 200, 500 ) as $size ) : ?>
							<option value="<?php echo esc_attr( (string) $size ); ?>" <?php selected( ImportFileRestorer::DEFAULT_BATCH_FILES, $size ); ?>><?php echo esc_html( (string) $size ); ?></option>
						<?php endforeach; ?>
					</select>
					<label for="seo-geo-local-files-mb"><strong><?php echo esc_html( $this->copy->text( 'clone_local_files_mb_label' ) ); ?></strong></label>
					<select id="seo-geo-local-files-mb" name="local_clone_files_batch_megabytes">
						<?php foreach ( array( 4, 8, 16, 32, 64, 128 ) as $megabytes ) : ?>
							<option value="<?php echo esc_attr( (string) $megabytes ); ?>" <?php selected( 8, $megabytes ); ?>><?php echo esc_html( (string) $megabytes ); ?></option>
						<?php endforeach; ?>
					</select>
				</p>
				<p class="description"><?php echo esc_html( $this->copy->text( 'clone_local_files_batch_help' ) ); ?></p>
				<?php submit_button( $this->copy->text( is_array( $state ) ? 'clone_local_files_continue' : 'clone_local_files_start' ), 'secondary', 'submit', false ); ?>
			</form>
		<?php endif; ?>
		<?php
	}

	/**
	 * Render a bounded result notice after local staging environment rewrite batches.
	 */
	private function render_clone_local_rewrite_result_notice(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only notice after nonce-verified action.
		$status = isset( $_GET['seo_geo_clone_local_rewrite'] )
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Same bounded result value.
			? sanitize_key( wp_unslash( $_GET['seo_geo_clone_local_rewrite'] ) )
			: '';

		$key = match ( $status ) {
			'running' => 'clone_local_rewrite_running',
			'ready'   => 'clone_local_rewrite_ready',
			'blocked' => 'clone_local_rewrite_blocked',
			default   => null,
		};
		if ( null === $key ) {
			return;
		}

		$class = 'blocked' === $status ? 'notice notice-error' : ( 'ready' === $status ? 'notice notice-success' : 'notice notice-info' );
		?>
		<div class="<?php echo esc_attr( $class ); ?> is-dismissible">
			<p><?php echo esc_html( $this->copy->text( $key ) ); ?></p>
		</div>
		<?php
	}

	/**
	 * Render local serialization-safe staging rewrite controls.
	 *
	 * @param array<string,mixed> $job Parent local-clone job.
	 */
	private function render_clone_local_rewrite_section( array $job ): void {
		$job_id = is_string( $job['job_id'] ?? null ) ? $job['job_id'] : '';
		if ( '' === $job_id ) {
			return;
		}

		$state  = ( new LocalCloneEnvironmentRewriteStateStore() )->get( $job_id );
		$status = is_array( $state ) ? (string) ( $state['status'] ?? 'pending' ) : 'pending';
		$stage  = is_array( $state ) ? (string) ( $state['stage'] ?? 'pending' ) : 'pending';
		?>
		<h3><?php echo esc_html( $this->copy->text( 'clone_local_rewrite_heading' ) ); ?></h3>
		<p><?php echo esc_html( $this->copy->text( 'clone_local_rewrite_help' ) ); ?></p>

		<?php if ( is_array( $state ) ) : ?>
			<table class="widefat striped" role="presentation">
				<tbody>
					<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'label_clone_status' ) ); ?></th><td><code><?php echo esc_html( $status ); ?></code></td></tr>
					<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'clone_local_rewrite_stage' ) ); ?></th><td><code><?php echo esc_html( $stage ); ?></code></td></tr>
					<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'clone_local_target_preflight_child_job' ) ); ?></th><td><code><?php echo esc_html( (string) ( $state['child_import_job_id'] ?? '' ) ); ?></code></td></tr>
					<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'clone_local_rewrite_source_home' ) ); ?></th><td><code><?php echo esc_html( (string) ( $state['source_home_url'] ?? '' ) ); ?></code></td></tr>
					<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'clone_local_rewrite_destination_home' ) ); ?></th><td><code><?php echo esc_html( (string) ( $state['destination_home_url'] ?? '' ) ); ?></code></td></tr>
					<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'clone_local_rewrite_rows' ) ); ?></th><td><?php echo esc_html( (string) (int) ( $state['rows_changed'] ?? 0 ) ); ?> / <?php echo esc_html( (string) (int) ( $state['rows_scanned'] ?? 0 ) ); ?></td></tr>
					<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'clone_local_rewrite_values' ) ); ?></th><td><?php echo esc_html( (string) (int) ( $state['values_changed'] ?? 0 ) ); ?></td></tr>
					<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'clone_local_rewrite_core_urls' ) ); ?></th><td><?php echo esc_html( (string) (int) ( $state['home_rewrites'] ?? 0 ) ); ?> / <?php echo esc_html( (string) (int) ( $state['siteurl_rewrites'] ?? 0 ) ); ?></td></tr>
					<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'clone_local_rewrite_same_origin' ) ); ?></th><td><?php echo esc_html( (string) (int) ( $state['same_origin_rewrites'] ?? 0 ) ); ?></td></tr>
					<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'clone_local_rewrite_upload_urls' ) ); ?></th><td><?php echo esc_html( (string) (int) ( $state['upload_url_rewrites'] ?? 0 ) ); ?></td></tr>
					<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'clone_local_rewrite_structured' ) ); ?></th><td><?php echo esc_html( (string) (int) ( $state['serialized_values'] ?? 0 ) ); ?> / <?php echo esc_html( (string) (int) ( $state['json_values'] ?? 0 ) ); ?></td></tr>
					<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'clone_local_rewrite_credentials' ) ); ?></th><td><?php echo esc_html( (string) (int) ( $state['credential_skips'] ?? 0 ) ); ?></td></tr>
					<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'clone_local_rewrite_verify' ) ); ?></th><td><?php echo esc_html( (string) (int) ( $state['verify_rows_scanned'] ?? 0 ) ); ?> / <?php echo esc_html( (string) (int) ( $state['verify_source_urls'] ?? 0 ) ); ?></td></tr>
					<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'clone_local_rewrite_active_safe' ) ); ?></th><td><?php echo esc_html( true === ( $state['active_tables_untouched'] ?? false ) && true === ( $state['active_roots_untouched'] ?? false ) ? $this->copy->text( 'yes' ) : $this->copy->text( 'no' ) ); ?></td></tr>
					<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'clone_local_rewrite_target_safe' ) ); ?></th><td><?php echo esc_html( true === ( $state['target_unactivated'] ?? false ) ? $this->copy->text( 'yes' ) : $this->copy->text( 'no' ) ); ?></td></tr>
					<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'clone_local_handoff_next_label' ) ); ?></th><td><code><?php echo esc_html( (string) ( $state['rewrite_next'] ?? '' ) ); ?></code></td></tr>
					<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'clone_local_blockers' ) ); ?></th><td><code><?php echo esc_html( array() === ( $state['blockers'] ?? array() ) ? $this->copy->text( 'clone_import_none' ) : implode( ', ', (array) $state['blockers'] ) ); ?></code></td></tr>
				</tbody>
			</table>
		<?php endif; ?>

		<?php if ( 'ready' === $status ) : ?>
			<p class="notice notice-success inline"><?php echo esc_html( $this->copy->text( 'clone_local_rewrite_next' ) ); ?></p>
			<?php $this->render_clone_local_finalization_section( $job ); ?>
		<?php else : ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="<?php echo esc_attr( AdminCloneLocalEnvironmentRewriteController::ACTION ); ?>">
				<input type="hidden" name="clone_job_id" value="<?php echo esc_attr( $job_id ); ?>">
				<?php wp_nonce_field( AdminCloneLocalEnvironmentRewriteController::NONCE_ACTION . ':' . $job_id ); ?>
				<?php if ( ! is_array( $state ) ) : ?>
					<p><label><input type="checkbox" name="local_clone_rewrite_confirm" value="1" required> <?php echo esc_html( $this->copy->text( 'clone_local_rewrite_confirm' ) ); ?></label></p>
				<?php endif; ?>
				<p>
					<label for="seo-geo-local-rewrite-batch"><strong><?php echo esc_html( $this->copy->text( 'clone_local_rewrite_batch_label' ) ); ?></strong></label>
					<select id="seo-geo-local-rewrite-batch" name="local_clone_rewrite_batch_rows">
						<?php foreach ( array( 10, 25, 50, 100, 200, 500 ) as $rows ) : ?>
							<option value="<?php echo esc_attr( (string) $rows ); ?>" <?php selected( ImportEnvironmentRewriter::DEFAULT_BATCH_ROWS, $rows ); ?>><?php echo esc_html( (string) $rows ); ?></option>
						<?php endforeach; ?>
					</select>
				</p>
				<p class="description"><?php echo esc_html( $this->copy->text( 'clone_local_rewrite_batch_help' ) ); ?></p>
				<?php submit_button( $this->copy->text( is_array( $state ) ? 'clone_local_rewrite_continue' : 'clone_local_rewrite_start' ), 'secondary', 'submit', false ); ?>
			</form>
		<?php endif; ?>
		<?php
	}

	/**
	 * Render a bounded result notice after local guarded finalization-plan batches.
	 */
	private function render_clone_local_finalization_result_notice(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only notice after nonce-verified action.
		$status = isset( $_GET['seo_geo_clone_local_finalization'] )
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Same bounded result value.
			? sanitize_key( wp_unslash( $_GET['seo_geo_clone_local_finalization'] ) )
			: '';

		$key = match ( $status ) {
			'running' => 'clone_local_finalization_running',
			'ready'   => 'clone_local_finalization_ready',
			'blocked' => 'clone_local_finalization_blocked',
			default   => null,
		};
		if ( null === $key ) {
			return;
		}

		$class = 'blocked' === $status ? 'notice notice-error' : ( 'ready' === $status ? 'notice notice-success' : 'notice notice-info' );
		?>
		<div class="<?php echo esc_attr( $class ); ?> is-dismissible">
			<p><?php echo esc_html( $this->copy->text( $key ) ); ?></p>
		</div>
		<?php
	}

	/**
	 * Render guarded local finalization planning.
	 *
	 * @param array<string,mixed> $job Parent local-clone job.
	 */
	private function render_clone_local_finalization_section( array $job ): void {
		$job_id = is_string( $job['job_id'] ?? null ) ? $job['job_id'] : '';
		if ( '' === $job_id ) {
			return;
		}

		$state  = ( new LocalCloneFinalizationPlanStateStore() )->get( $job_id );
		$status = is_array( $state ) ? (string) ( $state['status'] ?? 'pending' ) : 'pending';
		$stage  = is_array( $state ) ? (string) ( $state['stage'] ?? 'pending' ) : 'pending';
		?>
		<h3><?php echo esc_html( $this->copy->text( 'clone_local_finalization_heading' ) ); ?></h3>
		<p><?php echo esc_html( $this->copy->text( 'clone_local_finalization_help' ) ); ?></p>

		<?php if ( is_array( $state ) ) : ?>
			<table class="widefat striped" role="presentation">
				<tbody>
					<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'label_clone_status' ) ); ?></th><td><code><?php echo esc_html( $status ); ?></code></td></tr>
					<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'clone_local_finalization_stage' ) ); ?></th><td><code><?php echo esc_html( $stage ); ?></code></td></tr>
					<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'clone_local_target_preflight_child_job' ) ); ?></th><td><code><?php echo esc_html( (string) ( $state['child_import_job_id'] ?? '' ) ); ?></code></td></tr>
					<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'clone_local_finalization_db_rows' ) ); ?></th><td><?php echo esc_html( (string) (int) ( $state['database_rows_hashed'] ?? 0 ) ); ?></td></tr>
					<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'clone_local_finalization_db_hash' ) ); ?></th><td><code><?php echo esc_html( (string) ( $state['database_fingerprint'] ?? '' ) ); ?></code></td></tr>
					<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'clone_local_finalization_files' ) ); ?></th><td><?php echo esc_html( (string) (int) ( $state['files_hashed'] ?? 0 ) . ' / ' . (string) (int) ( $state['expected_file_count'] ?? 0 ) ); ?></td></tr>
					<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'clone_local_finalization_bytes' ) ); ?></th><td><?php echo esc_html( size_format( (int) ( $state['file_bytes_hashed'] ?? 0 ) ) . ' / ' . size_format( (int) ( $state['expected_file_bytes'] ?? 0 ) ) ); ?></td></tr>
					<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'clone_local_finalization_file_hash' ) ); ?></th><td><code><?php echo esc_html( (string) ( $state['file_fingerprint'] ?? '' ) ); ?></code></td></tr>
					<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'clone_local_finalization_plan_hash' ) ); ?></th><td><code><?php echo esc_html( (string) ( $state['activation_plan_hash'] ?? '' ) ); ?></code></td></tr>
					<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'clone_local_finalization_rollback' ) ); ?></th><td><?php echo esc_html( true === ( $state['rollback_plan_ready'] ?? false ) ? $this->copy->text( 'yes' ) : $this->copy->text( 'no' ) ); ?></td></tr>
					<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'clone_local_finalization_target' ) ); ?></th><td><?php echo esc_html( true === ( $state['target_unactivated'] ?? false ) ? $this->copy->text( 'yes' ) : $this->copy->text( 'no' ) ); ?></td></tr>
					<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'clone_local_handoff_next_label' ) ); ?></th><td><code><?php echo esc_html( (string) ( $state['finalization_next'] ?? '' ) ); ?></code></td></tr>
					<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'clone_local_blockers' ) ); ?></th><td><code><?php echo esc_html( array() === ( $state['blockers'] ?? array() ) ? $this->copy->text( 'clone_import_none' ) : implode( ', ', (array) $state['blockers'] ) ); ?></code></td></tr>
				</tbody>
			</table>
		<?php endif; ?>

		<?php if ( 'ready' === $status ) : ?>
			<p class="notice notice-success inline"><?php echo esc_html( $this->copy->text( 'clone_local_finalization_next' ) ); ?></p>
			<?php $this->render_clone_local_database_activation_section( $job ); ?>
		<?php else : ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="<?php echo esc_attr( AdminCloneLocalFinalizationController::ACTION ); ?>">
				<input type="hidden" name="clone_job_id" value="<?php echo esc_attr( $job_id ); ?>">
				<?php wp_nonce_field( AdminCloneLocalFinalizationController::NONCE_ACTION . ':' . $job_id ); ?>
				<?php if ( ! is_array( $state ) ) : ?>
					<p><label><input type="checkbox" name="local_clone_finalization_confirm" value="1" required> <?php echo esc_html( $this->copy->text( 'clone_local_finalization_confirm' ) ); ?></label></p>
				<?php endif; ?>
				<p>
					<label for="seo-geo-local-finalize-rows"><strong><?php echo esc_html( $this->copy->text( 'clone_local_finalization_rows_label' ) ); ?></strong></label>
					<select id="seo-geo-local-finalize-rows" name="local_clone_finalization_batch_rows">
						<?php foreach ( array( 25, 50, 100, 200, 500, 1000 ) as $rows ) : ?>
							<option value="<?php echo esc_attr( (string) $rows ); ?>" <?php selected( ImportFinalizationPlanner::DEFAULT_BATCH_ROWS, $rows ); ?>><?php echo esc_html( (string) $rows ); ?></option>
						<?php endforeach; ?>
					</select>
					<label for="seo-geo-local-finalize-files"><strong><?php echo esc_html( $this->copy->text( 'clone_local_finalization_files_label' ) ); ?></strong></label>
					<select id="seo-geo-local-finalize-files" name="local_clone_finalization_batch_files">
						<?php foreach ( array( 25, 50, 100, 200, 500 ) as $files ) : ?>
							<option value="<?php echo esc_attr( (string) $files ); ?>" <?php selected( ImportFinalizationPlanner::DEFAULT_BATCH_FILES, $files ); ?>><?php echo esc_html( (string) $files ); ?></option>
						<?php endforeach; ?>
					</select>
					<label for="seo-geo-local-finalize-mb"><strong><?php echo esc_html( $this->copy->text( 'clone_local_finalization_mb_label' ) ); ?></strong></label>
					<select id="seo-geo-local-finalize-mb" name="local_clone_finalization_batch_megabytes">
						<?php foreach ( array( 4, 8, 16, 32, 64, 128 ) as $megabytes ) : ?>
							<option value="<?php echo esc_attr( (string) $megabytes ); ?>" <?php selected( 16, $megabytes ); ?>><?php echo esc_html( (string) $megabytes ); ?></option>
						<?php endforeach; ?>
					</select>
				</p>
				<p class="description"><?php echo esc_html( $this->copy->text( 'clone_local_finalization_batch_help' ) ); ?></p>
				<?php submit_button( $this->copy->text( is_array( $state ) ? 'clone_local_finalization_continue' : 'clone_local_finalization_start' ), 'secondary', 'submit', false ); ?>
			</form>
		<?php endif; ?>
		<?php
	}

	/**
	 * Render a bounded result notice after local database activation actions.
	 */
	private function render_clone_local_database_activation_result_notice(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only notice after nonce-verified action.
		$status = isset( $_GET['seo_geo_clone_local_database_activation'] )
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Same bounded result value.
			? sanitize_key( wp_unslash( $_GET['seo_geo_clone_local_database_activation'] ) )
			: '';

		$key = match ( $status ) {
			'prepared'    => 'clone_local_database_activation_prepared',
			'activated'   => 'clone_local_database_activation_activated',
			'rolled-back' => 'clone_local_database_activation_rolled_back',
			'blocked'     => 'clone_local_database_activation_blocked',
			default       => null,
		};
		if ( null === $key ) {
			return;
		}

		$class = 'blocked' === $status
			? 'notice notice-error'
			: ( 'prepared' === $status ? 'notice notice-info' : 'notice notice-success' );
		?>
		<div class="<?php echo esc_attr( $class ); ?> is-dismissible">
			<p><?php echo esc_html( $this->copy->text( $key ) ); ?></p>
		</div>
		<?php
	}

	/**
	 * Render reversible local target database activation.
	 *
	 * @param array<string,mixed> $job Parent local-clone job.
	 */
	private function render_clone_local_database_activation_section( array $job ): void {
		$job_id = is_string( $job['job_id'] ?? null ) ? $job['job_id'] : '';
		if ( '' === $job_id ) {
			return;
		}

		$state  = ( new LocalCloneDatabaseActivationStateStore() )->get( $job_id );
		$status = is_array( $state ) ? (string) ( $state['status'] ?? 'pending' ) : 'pending';
		?>
		<h3><?php echo esc_html( $this->copy->text( 'clone_local_database_activation_heading' ) ); ?></h3>
		<p><?php echo esc_html( $this->copy->text( 'clone_local_database_activation_help' ) ); ?></p>

		<?php if ( is_array( $state ) ) : ?>
			<table class="widefat striped" role="presentation">
				<tbody>
					<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'label_clone_status' ) ); ?></th><td><code><?php echo esc_html( $status ); ?></code></td></tr>
					<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'clone_local_target_preflight_child_job' ) ); ?></th><td><code><?php echo esc_html( (string) ( $state['child_import_job_id'] ?? '' ) ); ?></code></td></tr>
					<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'clone_local_database_activation_prefix' ) ); ?></th><td><code><?php echo esc_html( (string) ( $state['target_table_prefix'] ?? '' ) ); ?></code></td></tr>
					<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'clone_local_database_activation_options' ) ); ?></th><td><code><?php echo esc_html( (string) ( $state['options_target'] ?? '' ) ); ?></code></td></tr>
					<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'clone_local_database_activation_tables' ) ); ?></th><td><?php echo esc_html( (string) (int) ( $state['table_count'] ?? 0 ) ); ?></td></tr>
					<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'clone_local_database_activation_rows' ) ); ?></th><td><?php echo esc_html( (string) (int) ( $state['row_count'] ?? 0 ) ); ?></td></tr>
					<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'clone_local_database_activation_plan_hash' ) ); ?></th><td><code><?php echo esc_html( (string) ( $state['activation_plan_hash'] ?? '' ) ); ?></code></td></tr>
					<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'clone_local_database_activation_swapped' ) ); ?></th><td><?php echo esc_html( true === ( $state['database_swapped'] ?? false ) ? $this->copy->text( 'yes' ) : $this->copy->text( 'no' ) ); ?></td></tr>
					<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'clone_local_database_activation_rollback' ) ); ?></th><td><?php echo esc_html( true === ( $state['rollback_available'] ?? false ) ? $this->copy->text( 'yes' ) : $this->copy->text( 'no' ) ); ?></td></tr>
					<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'clone_local_database_activation_files_safe' ) ); ?></th><td><?php echo esc_html( true === ( $state['active_files_untouched'] ?? false ) ? $this->copy->text( 'yes' ) : $this->copy->text( 'no' ) ); ?></td></tr>
					<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'clone_local_database_activation_target_active' ) ); ?></th><td><?php echo esc_html( true === ( $state['target_database_active'] ?? false ) ? $this->copy->text( 'yes' ) : $this->copy->text( 'no' ) ); ?></td></tr>
					<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'clone_local_handoff_next_label' ) ); ?></th><td><code><?php echo esc_html( (string) ( $state['activation_next'] ?? '' ) ); ?></code></td></tr>
					<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'clone_local_blockers' ) ); ?></th><td><code><?php echo esc_html( array() === ( $state['blockers'] ?? array() ) ? $this->copy->text( 'clone_import_none' ) : implode( ', ', (array) $state['blockers'] ) ); ?></code></td></tr>
				</tbody>
			</table>
		<?php endif; ?>

		<?php if ( 'pending' === $status ) : ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="<?php echo esc_attr( AdminCloneLocalDatabaseActivationController::ACTION ); ?>">
				<input type="hidden" name="clone_job_id" value="<?php echo esc_attr( $job_id ); ?>">
				<input type="hidden" name="local_database_activation_step" value="prepare">
				<?php wp_nonce_field( AdminCloneLocalDatabaseActivationController::NONCE_ACTION . ':' . $job_id ); ?>
				<p class="description"><?php echo esc_html( $this->copy->text( 'clone_local_database_activation_prepare_help' ) ); ?></p>
				<?php submit_button( $this->copy->text( 'clone_local_database_activation_prepare' ), 'secondary', 'submit', false ); ?>
			</form>
		<?php elseif ( 'prepared' === $status ) : ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="<?php echo esc_attr( AdminCloneLocalDatabaseActivationController::ACTION ); ?>">
				<input type="hidden" name="clone_job_id" value="<?php echo esc_attr( $job_id ); ?>">
				<input type="hidden" name="local_database_activation_step" value="activate">
				<?php wp_nonce_field( AdminCloneLocalDatabaseActivationController::NONCE_ACTION . ':' . $job_id ); ?>
				<p><label><?php echo esc_html( $this->copy->text( 'clone_local_database_activation_confirm_label' ) ); ?> <input type="text" name="local_database_activation_confirmation" value="" autocomplete="off" required></label></p>
				<p class="description"><?php echo esc_html( $this->copy->text( 'clone_local_database_activation_confirm_help' ) ); ?></p>
				<?php submit_button( $this->copy->text( 'clone_local_database_activation_activate' ), 'primary', 'submit', false ); ?>
			</form>
		<?php elseif ( 'activated' === $status ) : ?>
			<p class="notice notice-success inline"><?php echo esc_html( $this->copy->text( 'clone_local_database_activation_next' ) ); ?></p>
			<?php $this->render_clone_local_file_promotion_section( $job ); ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="<?php echo esc_attr( AdminCloneLocalDatabaseActivationController::ACTION ); ?>">
				<input type="hidden" name="clone_job_id" value="<?php echo esc_attr( $job_id ); ?>">
				<input type="hidden" name="local_database_activation_step" value="rollback">
				<?php wp_nonce_field( AdminCloneLocalDatabaseActivationController::NONCE_ACTION . ':' . $job_id ); ?>
				<p><label><?php echo esc_html( $this->copy->text( 'clone_local_database_activation_rollback_label' ) ); ?> <input type="text" name="local_database_activation_confirmation" value="" autocomplete="off" required></label></p>
				<p class="description"><?php echo esc_html( $this->copy->text( 'clone_local_database_activation_rollback_help' ) ); ?></p>
				<?php submit_button( $this->copy->text( 'clone_local_database_activation_rollback_button' ), 'secondary', 'submit', false ); ?>
			</form>
		<?php elseif ( 'rolled-back' === $status ) : ?>
			<p class="notice notice-warning inline"><?php echo esc_html( $this->copy->text( 'clone_local_database_activation_rolled_back_next' ) ); ?></p>
		<?php endif; ?>
		<?php
	}

	/**
	 * Render local file-promotion result notice.
	 */
	private function render_clone_local_file_promotion_result_notice(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only notice after nonce-verified action.
		$status = isset( $_GET['seo_geo_clone_local_file_promotion'] )
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Same bounded result value.
			? sanitize_key( wp_unslash( $_GET['seo_geo_clone_local_file_promotion'] ) )
			: '';

		$key = match ( $status ) {
			'prepared'       => 'clone_local_file_promotion_prepared',
			'copying'        => 'clone_local_file_promotion_copying',
			'candidate-ready' => 'clone_local_file_promotion_candidate_ready',
			'promoting'      => 'clone_local_file_promotion_promoting',
			'verifying'      => 'clone_local_file_promotion_verifying',
			'verified'       => 'clone_local_file_promotion_verified',
			'rolled-back'    => 'clone_local_file_promotion_rolled_back',
			'blocked'        => 'clone_local_file_promotion_blocked',
			default          => null,
		};
		if ( null === $key ) {
			return;
		}

		$class = 'blocked' === $status
			? 'notice notice-error'
			: ( 'rolled-back' === $status ? 'notice notice-warning' : ( 'verified' === $status ? 'notice notice-success' : 'notice notice-info' ) );
		?>
		<div class="<?php echo esc_attr( $class ); ?> is-dismissible">
			<p><?php echo esc_html( $this->copy->text( $key ) ); ?></p>
		</div>
		<?php
	}

	/**
	 * Render reversible local file-promotion controls.
	 *
	 * @param array<string,mixed> $job Parent local-clone job.
	 */
	private function render_clone_local_file_promotion_section( array $job ): void {
		$job_id = is_string( $job['job_id'] ?? null ) ? $job['job_id'] : '';
		if ( '' === $job_id ) {
			return;
		}

		$state  = ( new LocalCloneFilePromotionStateStore() )->get( $job_id );
		$status = is_array( $state ) ? (string) ( $state['status'] ?? 'pending' ) : 'pending';
		?>
		<h3><?php echo esc_html( $this->copy->text( 'clone_local_file_promotion_heading' ) ); ?></h3>
		<p><?php echo esc_html( $this->copy->text( 'clone_local_file_promotion_help' ) ); ?></p>

		<?php if ( is_array( $state ) ) : ?>
			<table class="widefat striped" role="presentation">
				<tbody>
					<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'label_clone_status' ) ); ?></th><td><code><?php echo esc_html( $status ); ?></code></td></tr>
					<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'clone_local_target_preflight_child_job' ) ); ?></th><td><code><?php echo esc_html( (string) ( $state['child_import_job_id'] ?? '' ) ); ?></code></td></tr>
					<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'clone_local_file_promotion_files' ) ); ?></th><td><?php echo esc_html( (string) (int) ( $state['file_count'] ?? 0 ) ); ?></td></tr>
					<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'clone_local_file_promotion_bytes' ) ); ?></th><td><?php echo esc_html( size_format( (int) ( $state['byte_count'] ?? 0 ) ) ); ?></td></tr>
					<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'clone_local_file_promotion_verified_files' ) ); ?></th><td><?php echo esc_html( (string) (int) ( $state['verify_file_count'] ?? 0 ) ); ?></td></tr>
					<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'clone_local_file_promotion_plan_hash' ) ); ?></th><td><code><?php echo esc_html( (string) ( $state['activation_plan_hash'] ?? '' ) ); ?></code></td></tr>
					<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'clone_local_file_promotion_rollback' ) ); ?></th><td><?php echo esc_html( true === ( $state['rollback_available'] ?? false ) ? $this->copy->text( 'yes' ) : $this->copy->text( 'no' ) ); ?></td></tr>
					<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'clone_local_file_promotion_handoff' ) ); ?></th><td><?php echo esc_html( true === ( $state['handoff_ready'] ?? false ) ? $this->copy->text( 'yes' ) : $this->copy->text( 'no' ) ); ?></td></tr>
					<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'clone_local_file_promotion_source_safe' ) ); ?></th><td><?php echo esc_html( true === ( $state['source_untouched'] ?? false ) ? $this->copy->text( 'yes' ) : $this->copy->text( 'no' ) ); ?></td></tr>
					<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'clone_local_handoff_next_label' ) ); ?></th><td><code><?php echo esc_html( (string) ( $state['promotion_next'] ?? '' ) ); ?></code></td></tr>
					<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'clone_local_blockers' ) ); ?></th><td><code><?php echo esc_html( array() === ( $state['blockers'] ?? array() ) ? $this->copy->text( 'clone_import_none' ) : implode( ', ', (array) $state['blockers'] ) ); ?></code></td></tr>
				</tbody>
			</table>
		<?php endif; ?>

		<?php
		$step = match ( $status ) {
			'pending'                      => 'prepare',
			'prepared', 'copying'          => 'copy',
			'candidate-ready'              => 'promote',
			'promoting', 'verifying'       => 'verify',
			default                        => '',
		};
		?>
		<?php if ( '' !== $step ) : ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="<?php echo esc_attr( AdminCloneLocalFilePromotionController::ACTION ); ?>">
				<input type="hidden" name="clone_job_id" value="<?php echo esc_attr( $job_id ); ?>">
				<input type="hidden" name="local_clone_file_promotion_step" value="<?php echo esc_attr( $step ); ?>">
				<?php wp_nonce_field( AdminCloneLocalFilePromotionController::NONCE_ACTION . ':' . $job_id ); ?>
				<?php if ( in_array( $step, array( 'copy', 'verify' ), true ) ) : ?>
					<p>
						<label><?php echo esc_html( $this->copy->text( 'clone_local_file_promotion_batch_files' ) ); ?>
							<select name="local_clone_file_promotion_batch_files">
								<?php foreach ( array( 10, 20, 50, 100, 200, 500 ) as $size ) : ?>
									<option value="<?php echo esc_attr( (string) $size ); ?>" <?php selected( ImportFilePromoter::DEFAULT_BATCH_FILES, $size ); ?>><?php echo esc_html( (string) $size ); ?></option>
								<?php endforeach; ?>
							</select>
						</label>
						<label><?php echo esc_html( $this->copy->text( 'clone_local_file_promotion_batch_mb' ) ); ?>
							<select name="local_clone_file_promotion_batch_megabytes">
								<?php foreach ( array( 4, 8, 16, 32, 64, 128 ) as $mb ) : ?>
									<option value="<?php echo esc_attr( (string) $mb ); ?>" <?php selected( 16, $mb ); ?>><?php echo esc_html( (string) $mb ); ?></option>
								<?php endforeach; ?>
							</select>
						</label>
					</p>
				<?php endif; ?>
				<?php if ( 'promote' === $step ) : ?>
					<p><label><input type="checkbox" name="local_clone_file_promotion_confirm" value="1" required> <?php echo esc_html( $this->copy->text( 'clone_local_file_promotion_confirm' ) ); ?></label></p>
				<?php endif; ?>
				<?php submit_button( $this->copy->text( 'clone_local_file_promotion_' . $step . '_button' ), 'promote' === $step ? 'primary' : 'secondary', 'submit', false ); ?>
			</form>
		<?php elseif ( 'verified' === $status ) : ?>
			<p class="notice notice-success inline"><?php echo esc_html( $this->copy->text( 'clone_local_file_promotion_next' ) ); ?></p>
			<?php $this->render_clone_local_handoff_section( $job ); ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="<?php echo esc_attr( AdminCloneLocalFilePromotionController::ACTION ); ?>">
				<input type="hidden" name="clone_job_id" value="<?php echo esc_attr( $job_id ); ?>">
				<input type="hidden" name="local_clone_file_promotion_step" value="rollback">
				<?php wp_nonce_field( AdminCloneLocalFilePromotionController::NONCE_ACTION . ':' . $job_id ); ?>
				<p><label><input type="checkbox" name="local_clone_file_promotion_confirm" value="1" required> <?php echo esc_html( $this->copy->text( 'clone_local_file_promotion_rollback_confirm' ) ); ?></label></p>
				<?php submit_button( $this->copy->text( 'clone_local_file_promotion_rollback_button' ), 'secondary', 'submit', false ); ?>
			</form>
		<?php elseif ( 'rolled-back' === $status ) : ?>
			<p class="notice notice-warning inline"><?php echo esc_html( $this->copy->text( 'clone_local_file_promotion_rolled_back_next' ) ); ?></p>
		<?php endif; ?>
		<?php
	}

	/**
	 * Render final local handoff verification result.
	 */
	private function render_clone_local_handoff_result_notice(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only notice after nonce-verified action.
		$status = isset( $_GET['seo_geo_clone_local_handoff'] )
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Same bounded result value.
			? sanitize_key( wp_unslash( $_GET['seo_geo_clone_local_handoff'] ) )
			: '';

		$key = match ( $status ) {
			'ready'   => 'clone_local_handoff_ready',
			'blocked' => 'clone_local_handoff_blocked',
			default   => null,
		};
		if ( null === $key ) {
			return;
		}
		?>
		<div class="<?php echo esc_attr( 'ready' === $status ? 'notice notice-success' : 'notice notice-error' ); ?> is-dismissible">
			<p><?php echo esc_html( $this->copy->text( $key ) ); ?></p>
		</div>
		<?php
	}

	/**
	 * Render final read-only local target handoff evidence.
	 *
	 * @param array<string,mixed> $job Parent local-clone job.
	 */
	private function render_clone_local_handoff_section( array $job ): void {
		$job_id = is_string( $job['job_id'] ?? null ) ? $job['job_id'] : '';
		if ( '' === $job_id ) {
			return;
		}

		$state  = ( new LocalCloneHandoffReportStateStore() )->get( $job_id );
		$status = is_array( $state ) ? (string) ( $state['status'] ?? 'pending' ) : 'pending';
		?>
		<h3><?php echo esc_html( $this->copy->text( 'clone_local_handoff_heading' ) ); ?></h3>
		<p><?php echo esc_html( $this->copy->text( 'clone_local_handoff_help' ) ); ?></p>

		<?php if ( is_array( $state ) ) : ?>
			<table class="widefat striped" role="presentation">
				<tbody>
					<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'label_clone_status' ) ); ?></th><td><code><?php echo esc_html( $status ); ?></code></td></tr>
					<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'clone_local_handoff_target_url' ) ); ?></th><td><code><?php echo esc_html( (string) ( $state['target_url'] ?? '' ) ); ?></code></td></tr>
					<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'clone_local_target_prefix' ) ); ?></th><td><code><?php echo esc_html( (string) ( $state['target_table_prefix'] ?? '' ) ); ?></code></td></tr>
					<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'clone_local_handoff_database' ) ); ?></th><td><?php echo esc_html( (string) (int) ( $state['table_count'] ?? 0 ) ); ?> / <?php echo esc_html( (string) (int) ( $state['row_count'] ?? 0 ) ); ?></td></tr>
					<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'clone_local_handoff_files' ) ); ?></th><td><?php echo esc_html( (string) (int) ( $state['file_count'] ?? 0 ) ); ?> / <?php echo esc_html( size_format( (int) ( $state['file_bytes'] ?? 0 ) ) ); ?></td></tr>
					<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'clone_local_handoff_urls' ) ); ?></th><td><?php echo esc_html( true === ( $state['home_matches'] ?? false ) && true === ( $state['siteurl_matches'] ?? false ) ? $this->copy->text( 'yes' ) : $this->copy->text( 'no' ) ); ?></td></tr>
					<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'clone_local_handoff_noindex' ) ); ?></th><td><?php echo esc_html( true === ( $state['noindex_ready'] ?? false ) ? $this->copy->text( 'yes' ) : $this->copy->text( 'no' ) ); ?></td></tr>
					<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'clone_local_handoff_runtime' ) ); ?></th><td><?php echo esc_html( true === ( $state['runtime_matches'] ?? false ) && true === ( $state['bridge_control_ready'] ?? false ) ? $this->copy->text( 'yes' ) : $this->copy->text( 'no' ) ); ?></td></tr>
					<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'clone_local_handoff_rollback' ) ); ?></th><td><?php echo esc_html( true === ( $state['rollback_available'] ?? false ) ? $this->copy->text( 'yes' ) : $this->copy->text( 'no' ) ); ?></td></tr>
					<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'clone_local_handoff_source' ) ); ?></th><td><?php echo esc_html( true === ( $state['source_untouched'] ?? false ) ? $this->copy->text( 'yes' ) : $this->copy->text( 'no' ) ); ?></td></tr>
					<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'clone_local_handoff_report_hash' ) ); ?></th><td><code><?php echo esc_html( (string) ( $state['report_sha256'] ?? '' ) ); ?></code></td></tr>
					<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'clone_local_blockers' ) ); ?></th><td><code><?php echo esc_html( array() === ( $state['blockers'] ?? array() ) ? $this->copy->text( 'clone_import_none' ) : implode( ', ', (array) $state['blockers'] ) ); ?></code></td></tr>
				</tbody>
			</table>
		<?php endif; ?>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="<?php echo esc_attr( AdminCloneLocalHandoffController::ACTION ); ?>">
			<input type="hidden" name="clone_job_id" value="<?php echo esc_attr( $job_id ); ?>">
			<?php wp_nonce_field( AdminCloneLocalHandoffController::NONCE_ACTION . ':' . $job_id ); ?>
			<?php submit_button( $this->copy->text( is_array( $state ) ? 'clone_local_handoff_refresh_button' : 'clone_local_handoff_button' ), 'secondary', 'submit', false ); ?>
		</form>

		<?php if ( 'ready' === $status ) : ?>
			<p class="notice notice-success inline"><?php echo esc_html( $this->copy->text( 'clone_local_handoff_next' ) ); ?></p>
		<?php endif; ?>
		<?php
	}

	/**
	 * Render a bounded result notice after authenticated delivery/cleanup actions.
	 */
	private function render_clone_delivery_result_notice(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only result notice after nonce-verified delivery action.
		$status = isset( $_GET['seo_geo_clone_delivery'] )
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Same read-only result notice value.
			? sanitize_key( wp_unslash( $_GET['seo_geo_clone_delivery'] ) )
			: '';

		$key = match ( $status ) {
			'building' => 'clone_delivery_building',
			'ready'    => 'clone_delivery_ready',
			'expired'  => 'clone_delivery_expired',
			'cleaning' => 'clone_delivery_cleaning',
			'cleaned'  => 'clone_delivery_cleaned',
			'blocked'  => 'clone_delivery_blocked',
			default    => null,
		};

		if ( null === $key ) {
			return;
		}

		$class = in_array( $status, array( 'blocked', 'expired' ), true )
			? 'notice notice-error'
			: ( 'building' === $status || 'cleaning' === $status ? 'notice notice-info' : 'notice notice-success' );
		?>
		<div class="<?php echo esc_attr( $class ); ?> is-dismissible">
			<p><?php echo esc_html( $this->copy->text( $key ) ); ?></p>
		</div>
		<?php
	}

	/**
	 * Render authenticated package-delivery and retention controls.
	 *
	 * @param array<string,mixed> $job Clone job.
	 */
	private function render_clone_delivery_section( array $job ): void {
		$job_id = is_string( $job['job_id'] ?? null ) ? $job['job_id'] : '';
		if ( '' === $job_id ) {
			return;
		}

		$delivery = ( new DeliveryStateStore() )->get( $job_id );
		$status   = is_array( $delivery ) ? (string) ( $delivery['status'] ?? 'pending' ) : 'pending';
		$button   = 'pending' === $status ? 'clone_delivery_start' : 'clone_delivery_continue';
		$expires  = is_array( $delivery ) ? (int) ( $delivery['expires_at'] ?? 0 ) : 0;
		?>
		<h3><?php echo esc_html( $this->copy->text( 'clone_delivery_heading' ) ); ?></h3>
		<p><?php echo esc_html( $this->copy->text( 'clone_delivery_help' ) ); ?></p>
		<table class="widefat striped" role="presentation">
			<tbody>
				<tr>
					<th scope="row"><?php echo esc_html( $this->copy->text( 'label_clone_status' ) ); ?></th>
					<td><code><?php echo esc_html( $status ); ?></code></td>
				</tr>
				<tr>
					<th scope="row"><?php echo esc_html( $this->copy->text( 'clone_delivery_files' ) ); ?></th>
					<td><?php echo esc_html( (string) (int) ( $delivery['archive_file_count'] ?? 0 ) ); ?></td>
				</tr>
				<tr>
					<th scope="row"><?php echo esc_html( $this->copy->text( 'clone_delivery_source_bytes' ) ); ?></th>
					<td><?php echo esc_html( size_format( (int) ( $delivery['archive_source_bytes'] ?? 0 ) ) ); ?></td>
				</tr>
				<?php if ( 'ready' === $status ) : ?>
					<tr>
						<th scope="row"><?php echo esc_html( $this->copy->text( 'clone_delivery_archive_bytes' ) ); ?></th>
						<td><?php echo esc_html( size_format( (int) ( $delivery['archive_bytes'] ?? 0 ) ) ); ?></td>
					</tr>
					<tr>
						<th scope="row"><?php echo esc_html( $this->copy->text( 'clone_delivery_archive_hash' ) ); ?></th>
						<td><code><?php echo esc_html( (string) ( $delivery['archive_sha256'] ?? '' ) ); ?></code></td>
					</tr>
					<tr>
						<th scope="row"><?php echo esc_html( $this->copy->text( 'clone_delivery_expires' ) ); ?></th>
						<td><?php echo esc_html( 0 < $expires ? wp_date( 'Y-m-d H:i:s T', $expires ) : '—' ); ?></td>
					</tr>
				<?php endif; ?>
				<?php if ( 'cleaned' === $status ) : ?>
					<tr>
						<th scope="row"><?php echo esc_html( $this->copy->text( 'clone_delivery_cleanup_count' ) ); ?></th>
						<td><?php echo esc_html( (string) (int) ( $delivery['cleanup_deleted_count'] ?? 0 ) ); ?></td>
					</tr>
				<?php endif; ?>
			</tbody>
		</table>

		<?php if ( in_array( $status, array( 'pending', 'building' ), true ) ) : ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="<?php echo esc_attr( AdminCloneDeliveryController::BUILD_ACTION ); ?>">
				<input type="hidden" name="clone_job_id" value="<?php echo esc_attr( $job_id ); ?>">
				<?php wp_nonce_field( AdminCloneDeliveryController::NONCE_ACTION . ':build:' . $job_id ); ?>
				<p>
					<label for="seo-geo-clone-delivery-batch"><strong><?php echo esc_html( $this->copy->text( 'clone_delivery_batch_label' ) ); ?></strong></label>
					<select id="seo-geo-clone-delivery-batch" name="delivery_batch_size">
						<?php foreach ( array( 25, 50, 100, 200, 500 ) as $size ) : ?>
							<option value="<?php echo esc_attr( (string) $size ); ?>" <?php selected( PackageDelivery::DEFAULT_BATCH_FILES, $size ); ?>><?php echo esc_html( (string) $size ); ?></option>
						<?php endforeach; ?>
					</select>
					<label for="seo-geo-clone-delivery-megabytes"><strong><?php echo esc_html( $this->copy->text( 'clone_delivery_mb_label' ) ); ?></strong></label>
					<select id="seo-geo-clone-delivery-megabytes" name="delivery_batch_megabytes">
						<?php foreach ( array( 4, 8, 16, 32, 64, 128 ) as $megabytes ) : ?>
							<option value="<?php echo esc_attr( (string) $megabytes ); ?>" <?php selected( 16, $megabytes ); ?>><?php echo esc_html( (string) $megabytes ); ?></option>
						<?php endforeach; ?>
					</select>
				</p>
				<p class="description"><?php echo esc_html( $this->copy->text( 'clone_delivery_batch_help' ) ); ?></p>
				<?php submit_button( $this->copy->text( $button ), 'secondary', 'submit', false ); ?>
			</form>
		<?php elseif ( 'ready' === $status ) : ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;margin-right:1rem">
				<input type="hidden" name="action" value="<?php echo esc_attr( AdminCloneDeliveryController::DOWNLOAD_ACTION ); ?>">
				<input type="hidden" name="clone_job_id" value="<?php echo esc_attr( $job_id ); ?>">
				<?php wp_nonce_field( AdminCloneDeliveryController::NONCE_ACTION . ':download:' . $job_id ); ?>
				<?php submit_button( $this->copy->text( 'clone_delivery_download' ), 'primary', 'submit', false ); ?>
			</form>
			<?php $this->render_clone_delivery_cleanup_form( $job_id ); ?>
		<?php elseif ( in_array( $status, array( 'expired', 'cleaning', 'blocked' ), true ) ) : ?>
			<?php $this->render_clone_delivery_cleanup_form( $job_id ); ?>
		<?php endif; ?>
		<?php
	}

	/**
	 * Render one explicit destructive private-artifact cleanup form.
	 *
	 * @param string $job_id Clone job identifier.
	 */
	private function render_clone_delivery_cleanup_form( string $job_id ): void {
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block">
			<input type="hidden" name="action" value="<?php echo esc_attr( AdminCloneDeliveryController::CLEANUP_ACTION ); ?>">
			<input type="hidden" name="clone_job_id" value="<?php echo esc_attr( $job_id ); ?>">
			<input type="hidden" name="cleanup_confirmation" value="cleanup">
			<?php wp_nonce_field( AdminCloneDeliveryController::NONCE_ACTION . ':cleanup:' . $job_id ); ?>
			<?php submit_button( $this->copy->text( 'clone_delivery_cleanup' ), 'secondary', 'submit', false ); ?>
		</form>
		<?php
	}

	/**
	 * Render a bounded result notice after Portable Import preflight.
	 */
	private function render_clone_import_result_notice(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only result notice after the nonce-verified import action.
		$status = isset( $_GET['seo_geo_clone_import'] )
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Same read-only result notice value.
			? sanitize_key( wp_unslash( $_GET['seo_geo_clone_import'] ) )
			: '';

		$key = match ( $status ) {
			'ready'   => 'clone_import_ready',
			'blocked' => 'clone_import_blocked',
			default   => null,
		};

		if ( null === $key ) {
			return;
		}

		$class = 'blocked' === $status ? 'notice notice-error' : 'notice notice-success';
		?>
		<div class="<?php echo esc_attr( $class ); ?> is-dismissible">
			<p><?php echo esc_html( $this->copy->text( $key ) ); ?></p>
		</div>
		<?php
	}

	/**
	 * Render Portable Import private ZIP intake and read-only destination preflight.
	 *
	 * @param array<string,mixed> $job Import clone job.
	 */
	private function render_clone_import_section( array $job ): void {
		$job_id = is_string( $job['job_id'] ?? null ) ? $job['job_id'] : '';
		if ( '' === $job_id ) {
			return;
		}

		$state       = ( new ImportStateStore() )->get( $job_id );
		$status      = is_array( $state ) ? (string) ( $state['status'] ?? 'pending' ) : 'pending';
		$blockers    = is_array( $state['blockers'] ?? null ) ? array_values( array_filter( $state['blockers'], 'is_string' ) ) : array();
		$advisories  = is_array( $state['advisories'] ?? null ) ? array_values( array_filter( $state['advisories'], 'is_string' ) ) : array();
		$has_archive = is_array( $state ) && 0 < (int) ( $state['archive_bytes'] ?? 0 );
		$button      = $has_archive ? 'clone_import_revalidate' : 'clone_import_validate';
		?>
		<h3><?php echo esc_html( $this->copy->text( 'clone_import_heading' ) ); ?></h3>
		<p><?php echo esc_html( $this->copy->text( 'clone_import_help' ) ); ?></p>
		<table class="widefat striped" role="presentation">
			<tbody>
				<tr>
					<th scope="row"><?php echo esc_html( $this->copy->text( 'label_clone_status' ) ); ?></th>
					<td><code><?php echo esc_html( $status ); ?></code></td>
				</tr>
				<?php if ( $has_archive ) : ?>
					<tr>
						<th scope="row"><?php echo esc_html( $this->copy->text( 'clone_import_archive_bytes' ) ); ?></th>
						<td><?php echo esc_html( size_format( (int) ( $state['archive_bytes'] ?? 0 ) ) ); ?></td>
					</tr>
					<tr>
						<th scope="row"><?php echo esc_html( $this->copy->text( 'clone_import_archive_hash' ) ); ?></th>
						<td><code><?php echo esc_html( (string) ( $state['archive_sha256'] ?? '' ) ); ?></code></td>
					</tr>
					<tr>
						<th scope="row"><?php echo esc_html( $this->copy->text( 'clone_import_package_id' ) ); ?></th>
						<td><code><?php echo esc_html( (string) ( $state['package_id'] ?? '' ) ); ?></code></td>
					</tr>
					<tr>
						<th scope="row"><?php echo esc_html( $this->copy->text( 'clone_import_package_checksum' ) ); ?></th>
						<td><code><?php echo esc_html( (string) ( $state['package_checksum'] ?? '' ) ); ?></code></td>
					</tr>
					<?php $this->render_sandbox_boolean_row( 'clone_import_manifest_valid', true === ( $state['manifest_contract_valid'] ?? false ) ); ?>
					<?php $this->render_sandbox_boolean_row( 'clone_import_child_hashes_valid', true === ( $state['child_manifest_hashes_valid'] ?? false ) ); ?>
					<?php $this->render_sandbox_boolean_row( 'clone_import_target_authorized', true === ( $state['target_authorized'] ?? false ) ); ?>
					<?php $this->render_sandbox_boolean_row( 'clone_import_payload_verified', true === ( $state['full_payload_verified'] ?? false ) ); ?>
					<?php $this->render_sandbox_boolean_row( 'clone_import_restore_allowed', true === ( $state['restore_allowed'] ?? false ) ); ?>
					<tr>
						<th scope="row"><?php echo esc_html( $this->copy->text( 'clone_import_disk_free' ) ); ?></th>
						<td><?php echo esc_html( null === ( $state['disk_free_bytes'] ?? null ) ? '—' : size_format( (int) $state['disk_free_bytes'] ) ); ?></td>
					</tr>
					<tr>
						<th scope="row"><?php echo esc_html( $this->copy->text( 'clone_import_disk_required' ) ); ?></th>
						<td><?php echo esc_html( size_format( (int) ( $state['disk_required_bytes'] ?? 0 ) ) ); ?></td>
					</tr>
					<tr>
						<th scope="row"><?php echo esc_html( $this->copy->text( 'clone_import_blockers' ) ); ?></th>
						<td><code><?php echo esc_html( array() === $blockers ? $this->copy->text( 'clone_import_none' ) : implode( ', ', $blockers ) ); ?></code></td>
					</tr>
					<tr>
						<th scope="row"><?php echo esc_html( $this->copy->text( 'clone_import_advisories' ) ); ?></th>
						<td><code><?php echo esc_html( array() === $advisories ? $this->copy->text( 'clone_import_none' ) : implode( ', ', $advisories ) ); ?></code></td>
					</tr>
				<?php endif; ?>
			</tbody>
		</table>

		<?php $payload = ( new ImportPayloadStateStore() )->get( $job_id ); ?>
		<?php if ( ! is_array( $payload ) ) : ?>
			<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="<?php echo esc_attr( AdminCloneImportController::ACTION ); ?>">
				<input type="hidden" name="clone_job_id" value="<?php echo esc_attr( $job_id ); ?>">
				<?php wp_nonce_field( AdminCloneImportController::NONCE_ACTION . ':' . $job_id ); ?>
				<p>
					<label for="seo-geo-clone-import-package"><strong><?php echo esc_html( $this->copy->text( 'clone_import_upload_label' ) ); ?></strong></label><br>
					<input
						type="file"
						id="seo-geo-clone-import-package"
						name="clone_package"
						accept=".zip,application/zip"
						<?php echo $has_archive ? '' : 'required'; ?>
					>
				</p>
				<p class="description"><?php echo esc_html( $this->copy->text( 'clone_import_upload_help' ) ); ?></p>
				<?php submit_button( $this->copy->text( $button ), 'secondary', 'submit', false ); ?>
			</form>
		<?php endif; ?>

		<?php if ( in_array( $status, array( 'preflight-ready', 'payload-verified' ), true ) || is_array( $payload ) ) : ?>
			<?php $this->render_clone_import_payload_section( $job_id, $state, $payload ); ?>
		<?php endif; ?>
		<?php if ( is_array( $payload ) && 'complete' === ( $payload['status'] ?? null ) && true === ( $state['restore_allowed'] ?? false ) ) : ?>
			<?php $this->render_clone_import_database_section( $job_id ); ?>
		<?php endif; ?>
		<?php
	}

	/**
	 * Render a bounded result notice after private payload extraction/checksum work.
	 */
	private function render_clone_import_payload_result_notice(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only result notice after nonce-verified payload action.
		$status = isset( $_GET['seo_geo_clone_import_payload'] )
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Same read-only result notice value.
			? sanitize_key( wp_unslash( $_GET['seo_geo_clone_import_payload'] ) )
			: '';

		$key = match ( $status ) {
			'running'  => 'clone_import_payload_running',
			'complete' => 'clone_import_payload_complete',
			'blocked'  => 'clone_import_payload_blocked',
			default    => null,
		};

		if ( null === $key ) {
			return;
		}

		$class = 'blocked' === $status
			? 'notice notice-error'
			: ( 'running' === $status ? 'notice notice-info' : 'notice notice-success' );
		?>
		<div class="<?php echo esc_attr( $class ); ?> is-dismissible">
			<p><?php echo esc_html( $this->copy->text( $key ) ); ?></p>
		</div>
		<?php
	}

	/**
	 * Render bounded private extraction/full-checksum controls.
	 *
	 * @param string                   $job_id  Clone job identifier.
	 * @param array<string,mixed>|null $import  Import preflight state.
	 * @param array<string,mixed>|null $payload Payload-verification state.
	 */
	private function render_clone_import_payload_section( string $job_id, ?array $import, ?array $payload ): void {
		$status         = is_array( $payload ) ? (string) ( $payload['status'] ?? 'running' ) : 'pending';
		$stage          = is_array( $payload ) ? (string) ( $payload['stage'] ?? 'extract' ) : 'extract';
		$extract_files  = (int) ( $payload['extract_file_count'] ?? 0 );
		$extract_bytes  = (int) ( $payload['extract_byte_count'] ?? 0 );
		$verify_files   = (int) ( $payload['verify_file_count'] ?? 0 );
		$verify_bytes   = (int) ( $payload['verify_byte_count'] ?? 0 );
		$expected_files = (int) ( $payload['expected_file_count'] ?? ( $import['payload_file_count'] ?? 0 ) );
		$expected_bytes = (int) ( $payload['expected_byte_count'] ?? ( $import['payload_bytes'] ?? 0 ) );
		$blockers       = is_array( $payload['blockers'] ?? null ) ? array_values( array_filter( $payload['blockers'], 'is_string' ) ) : array();
		$button         = 'pending' === $status ? 'clone_import_payload_start' : 'clone_import_payload_continue';
		?>
		<h4><?php echo esc_html( $this->copy->text( 'clone_import_payload_heading' ) ); ?></h4>
		<p><?php echo esc_html( $this->copy->text( 'clone_import_payload_help' ) ); ?></p>
		<table class="widefat striped" role="presentation">
			<tbody>
				<tr>
					<th scope="row"><?php echo esc_html( $this->copy->text( 'clone_import_payload_stage' ) ); ?></th>
					<td><code><?php echo esc_html( $stage ); ?></code></td>
				</tr>
				<tr>
					<th scope="row"><?php echo esc_html( $this->copy->text( 'clone_import_payload_extract_files' ) ); ?></th>
					<td><?php echo esc_html( (string) $extract_files ); ?></td>
				</tr>
				<tr>
					<th scope="row"><?php echo esc_html( $this->copy->text( 'clone_import_payload_extract_bytes' ) ); ?></th>
					<td><?php echo esc_html( size_format( $extract_bytes ) ); ?></td>
				</tr>
				<tr>
					<th scope="row"><?php echo esc_html( $this->copy->text( 'clone_import_payload_verify_files' ) ); ?></th>
					<td><?php echo esc_html( $verify_files . ' / ' . $expected_files ); ?></td>
				</tr>
				<tr>
					<th scope="row"><?php echo esc_html( $this->copy->text( 'clone_import_payload_verify_bytes' ) ); ?></th>
					<td><?php echo esc_html( size_format( $verify_bytes ) . ' / ' . size_format( $expected_bytes ) ); ?></td>
				</tr>
				<?php if ( is_array( $payload ) && '' !== (string) ( $payload['verification_checksum'] ?? '' ) ) : ?>
					<tr>
						<th scope="row"><?php echo esc_html( $this->copy->text( 'clone_import_payload_checksum' ) ); ?></th>
						<td><code><?php echo esc_html( (string) $payload['verification_checksum'] ); ?></code></td>
					</tr>
				<?php endif; ?>
				<tr>
					<th scope="row"><?php echo esc_html( $this->copy->text( 'clone_import_payload_blockers' ) ); ?></th>
					<td><code><?php echo esc_html( array() === $blockers ? $this->copy->text( 'clone_import_none' ) : implode( ', ', $blockers ) ); ?></code></td>
				</tr>
			</tbody>
		</table>

		<?php if ( ! in_array( $status, array( 'complete', 'blocked' ), true ) ) : ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="<?php echo esc_attr( AdminCloneImportPayloadController::ACTION ); ?>">
				<input type="hidden" name="clone_job_id" value="<?php echo esc_attr( $job_id ); ?>">
				<?php wp_nonce_field( AdminCloneImportPayloadController::NONCE_ACTION . ':' . $job_id ); ?>
				<p>
					<label for="seo-geo-clone-import-payload-batch"><strong><?php echo esc_html( $this->copy->text( 'clone_import_payload_batch_label' ) ); ?></strong></label>
					<select id="seo-geo-clone-import-payload-batch" name="import_payload_batch_size">
						<?php foreach ( array( 10, 25, 50, 100, 200, 500 ) as $size ) : ?>
							<option value="<?php echo esc_attr( (string) $size ); ?>" <?php selected( ImportPayloadVerifier::DEFAULT_BATCH_FILES, $size ); ?>><?php echo esc_html( (string) $size ); ?></option>
						<?php endforeach; ?>
					</select>
					<label for="seo-geo-clone-import-payload-megabytes"><strong><?php echo esc_html( $this->copy->text( 'clone_import_payload_mb_label' ) ); ?></strong></label>
					<select id="seo-geo-clone-import-payload-megabytes" name="import_payload_batch_megabytes">
						<?php foreach ( array( 2, 4, 8, 16, 32, 64, 128 ) as $megabytes ) : ?>
							<option value="<?php echo esc_attr( (string) $megabytes ); ?>" <?php selected( 8, $megabytes ); ?>><?php echo esc_html( (string) $megabytes ); ?></option>
						<?php endforeach; ?>
					</select>
				</p>
				<p class="description"><?php echo esc_html( $this->copy->text( 'clone_import_payload_batch_help' ) ); ?></p>
				<?php submit_button( $this->copy->text( $button ), 'secondary', 'submit', false ); ?>
			</form>
		<?php endif; ?>
		<?php
	}

	/**
	 * Render a bounded result notice after database staging restore work.
	 */
	private function render_clone_import_database_result_notice(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only result notice after nonce-verified database restore action.
		$status = isset( $_GET['seo_geo_clone_import_database'] )
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Same read-only result notice value.
			? sanitize_key( wp_unslash( $_GET['seo_geo_clone_import_database'] ) )
			: '';

		$key = match ( $status ) {
			'running'  => 'clone_import_database_running',
			'complete' => 'clone_import_database_complete',
			'blocked'  => 'clone_import_database_blocked',
			default    => null,
		};
		if ( null === $key ) {
			return;
		}

		$class = 'blocked' === $status
			? 'notice notice-error'
			: ( 'running' === $status ? 'notice notice-info' : 'notice notice-success' );
		?>
		<div class="<?php echo esc_attr( $class ); ?> is-dismissible">
			<p><?php echo esc_html( $this->copy->text( $key ) ); ?></p>
		</div>
		<?php
	}

	/**
	 * Render isolated staging database restore controls.
	 *
	 * @param string $job_id Clone job identifier.
	 */
	private function render_clone_import_database_section( string $job_id ): void {
		$state    = ( new ImportDatabaseStateStore() )->get( $job_id );
		$status   = is_array( $state ) ? (string) ( $state['status'] ?? 'pending' ) : 'pending';
		$stage    = is_array( $state ) ? (string) ( $state['stage'] ?? 'prepare' ) : 'prepare';
		$blockers = is_array( $state['blockers'] ?? null ) ? array_values( array_filter( $state['blockers'], 'is_string' ) ) : array();
		$button   = 'pending' === $status ? 'clone_import_database_start' : 'clone_import_database_continue';
		?>
		<h4><?php echo esc_html( $this->copy->text( 'clone_import_database_heading' ) ); ?></h4>
		<p><?php echo esc_html( $this->copy->text( 'clone_import_database_help' ) ); ?></p>
		<table class="widefat striped" role="presentation">
			<tbody>
				<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'clone_import_database_stage' ) ); ?></th><td><code><?php echo esc_html( $stage ); ?></code></td></tr>
				<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'clone_import_database_tables' ) ); ?></th><td><?php echo esc_html( (string) (int) ( $state['tables_completed'] ?? 0 ) . ' / ' . (string) (int) ( $state['table_count'] ?? 0 ) ); ?></td></tr>
				<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'clone_import_database_rows' ) ); ?></th><td><?php echo esc_html( (string) (int) ( $state['rows_restored'] ?? 0 ) ); ?></td></tr>
				<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'clone_import_database_chunks' ) ); ?></th><td><?php echo esc_html( (string) (int) ( $state['chunks_completed'] ?? 0 ) . ' / ' . (string) (int) ( $state['chunk_count'] ?? 0 ) ); ?></td></tr>
				<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'clone_import_database_staging' ) ); ?></th><td><code><?php echo esc_html( (string) ( $state['current_staging_table'] ?? $state['staging_namespace'] ?? '' ) ); ?></code></td></tr>
				<?php $this->render_sandbox_boolean_row( 'clone_import_database_active_untouched', true === ( $state['active_tables_untouched'] ?? true ) ); ?>
				<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'clone_import_database_blockers' ) ); ?></th><td><code><?php echo esc_html( array() === $blockers ? $this->copy->text( 'clone_import_none' ) : implode( ', ', $blockers ) ); ?></code></td></tr>
			</tbody>
		</table>

		<?php if ( ! in_array( $status, array( 'complete', 'blocked' ), true ) ) : ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="<?php echo esc_attr( AdminCloneImportDatabaseController::ACTION ); ?>">
				<input type="hidden" name="clone_job_id" value="<?php echo esc_attr( $job_id ); ?>">
				<?php wp_nonce_field( AdminCloneImportDatabaseController::NONCE_ACTION . ':' . $job_id ); ?>
				<p>
					<label for="seo-geo-clone-import-db-rows"><strong><?php echo esc_html( $this->copy->text( 'clone_import_database_batch_label' ) ); ?></strong></label>
					<select id="seo-geo-clone-import-db-rows" name="database_restore_batch_rows">
						<?php foreach ( array( 10, 25, 50, 100, 200, 500 ) as $rows ) : ?>
							<option value="<?php echo esc_attr( (string) $rows ); ?>" <?php selected( ImportDatabaseRestorer::DEFAULT_BATCH_ROWS, $rows ); ?>><?php echo esc_html( (string) $rows ); ?></option>
						<?php endforeach; ?>
					</select>
				</p>
				<p class="description"><?php echo esc_html( $this->copy->text( 'clone_import_database_batch_help' ) ); ?></p>
				<?php submit_button( $this->copy->text( $button ), 'secondary', 'submit', false ); ?>
			</form>
		<?php elseif ( 'complete' === $status ) : ?>
			<?php $this->render_clone_import_file_section( $job_id ); ?>
		<?php endif; ?>
		<?php
	}

	/**
	 * Render a bounded result notice after staging-file restore work.
	 */
	private function render_clone_import_file_result_notice(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only result notice after nonce-verified file restore action.
		$status = isset( $_GET['seo_geo_clone_file_restore'] )
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Same read-only result notice value.
			? sanitize_key( wp_unslash( $_GET['seo_geo_clone_file_restore'] ) )
			: '';

		$key = match ( $status ) {
			'running'  => 'clone_import_file_running',
			'complete' => 'clone_import_file_complete',
			'blocked'  => 'clone_import_file_blocked',
			default    => null,
		};
		if ( null === $key ) {
			return;
		}

		$class = 'blocked' === $status
			? 'notice notice-error'
			: ( 'running' === $status ? 'notice notice-info' : 'notice notice-success' );
		?>
		<div class="<?php echo esc_attr( $class ); ?> is-dismissible">
			<p><?php echo esc_html( $this->copy->text( $key ) ); ?></p>
		</div>
		<?php
	}

	/**
	 * Render isolated staging-file restore controls.
	 *
	 * @param string $job_id Clone job identifier.
	 */
	private function render_clone_import_file_section( string $job_id ): void {
		$state    = ( new ImportFileStateStore() )->get( $job_id );
		$status   = is_array( $state ) ? (string) ( $state['status'] ?? 'pending' ) : 'pending';
		$stage    = is_array( $state ) ? (string) ( $state['stage'] ?? 'copy' ) : 'copy';
		$blockers = is_array( $state['blockers'] ?? null ) ? array_values( array_filter( $state['blockers'], 'is_string' ) ) : array();
		$button   = 'pending' === $status ? 'clone_import_file_start' : 'clone_import_file_continue';
		?>
		<h4><?php echo esc_html( $this->copy->text( 'clone_import_file_heading' ) ); ?></h4>
		<p><?php echo esc_html( $this->copy->text( 'clone_import_file_help' ) ); ?></p>
		<table class="widefat striped" role="presentation">
			<tbody>
				<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'clone_import_file_stage' ) ); ?></th><td><code><?php echo esc_html( $stage ); ?></code></td></tr>
				<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'clone_import_file_files' ) ); ?></th><td><?php echo esc_html( (string) (int) ( $state['file_count'] ?? 0 ) . ' / ' . (string) (int) ( $state['expected_file_count'] ?? 0 ) ); ?></td></tr>
				<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'clone_import_file_bytes' ) ); ?></th><td><?php echo esc_html( size_format( (int) ( $state['byte_count'] ?? 0 ) ) . ' / ' . size_format( (int) ( $state['expected_byte_count'] ?? 0 ) ) ); ?></td></tr>
				<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'clone_import_file_verified' ) ); ?></th><td><?php echo esc_html( (string) (int) ( $state['verify_file_count'] ?? 0 ) . ' / ' . (string) (int) ( $state['expected_file_count'] ?? 0 ) ); ?></td></tr>
				<?php $this->render_sandbox_boolean_row( 'clone_import_file_active_untouched', true === ( $state['active_roots_untouched'] ?? true ) ); ?>
				<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'clone_import_file_blockers' ) ); ?></th><td><code><?php echo esc_html( array() === $blockers ? $this->copy->text( 'clone_import_none' ) : implode( ', ', $blockers ) ); ?></code></td></tr>
			</tbody>
		</table>

		<?php if ( ! in_array( $status, array( 'complete', 'blocked' ), true ) ) : ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="<?php echo esc_attr( AdminCloneImportFileController::ACTION ); ?>">
				<input type="hidden" name="clone_job_id" value="<?php echo esc_attr( $job_id ); ?>">
				<?php wp_nonce_field( AdminCloneImportFileController::NONCE_ACTION . ':' . $job_id ); ?>
				<p>
					<label for="seo-geo-clone-import-file-count"><strong><?php echo esc_html( $this->copy->text( 'clone_import_file_batch_label' ) ); ?></strong></label>
					<select id="seo-geo-clone-import-file-count" name="file_restore_batch_files">
						<?php foreach ( range( 1, 20 ) as $files ) : ?>
							<option value="<?php echo esc_attr( (string) $files ); ?>" <?php selected( ImportFileRestorer::DEFAULT_BATCH_FILES, $files ); ?>><?php echo esc_html( (string) $files ); ?></option>
						<?php endforeach; ?>
					</select>
					<label for="seo-geo-clone-import-file-megabytes"><strong><?php echo esc_html( $this->copy->text( 'clone_import_file_mb_label' ) ); ?></strong></label>
					<select id="seo-geo-clone-import-file-megabytes" name="file_restore_batch_megabytes">
						<?php foreach ( array( 2, 4, 8, 16, 32, 64, 128 ) as $megabytes ) : ?>
							<option value="<?php echo esc_attr( (string) $megabytes ); ?>" <?php selected( 8, $megabytes ); ?>><?php echo esc_html( (string) $megabytes ); ?></option>
						<?php endforeach; ?>
					</select>
				</p>
				<p class="description"><?php echo esc_html( $this->copy->text( 'clone_import_file_batch_help' ) ); ?></p>
				<?php submit_button( $this->copy->text( $button ), 'secondary', 'submit', false ); ?>
			</form>
		<?php endif; ?>
		<?php if ( 'complete' === $status ) : ?>
			<?php $this->render_clone_import_rewrite_section( $job_id ); ?>
		<?php endif; ?>
		<?php
	}

	/**
	 * Render a bounded result notice after serialization-safe environment rewrite.
	 */
	private function render_clone_import_rewrite_result_notice(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only result notice after nonce-verified rewrite action.
		$status = isset( $_GET['seo_geo_clone_environment_rewrite'] )
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Same read-only result notice value.
			? sanitize_key( wp_unslash( $_GET['seo_geo_clone_environment_rewrite'] ) )
			: '';

		$key = match ( $status ) {
			'running'  => 'clone_import_rewrite_running',
			'complete' => 'clone_import_rewrite_complete',
			'blocked'  => 'clone_import_rewrite_blocked',
			default    => null,
		};
		if ( null === $key ) {
			return;
		}

		$class = 'blocked' === $status
			? 'notice notice-error'
			: ( 'running' === $status ? 'notice notice-info' : 'notice notice-success' );
		?>
		<div class="<?php echo esc_attr( $class ); ?> is-dismissible">
			<p><?php echo esc_html( $this->copy->text( $key ) ); ?></p>
		</div>
		<?php
	}

	/**
	 * Render serialization-safe staging environment rewrite controls.
	 *
	 * @param string $job_id Clone job identifier.
	 */
	private function render_clone_import_rewrite_section( string $job_id ): void {
		$state      = ( new ImportRewriteStateStore() )->get( $job_id );
		$status     = is_array( $state ) ? (string) ( $state['status'] ?? 'pending' ) : 'pending';
		$stage      = is_array( $state ) ? (string) ( $state['stage'] ?? 'rewrite' ) : 'rewrite';
		$blockers   = is_array( $state['blockers'] ?? null ) ? array_values( array_filter( $state['blockers'], 'is_string' ) ) : array();
		$advisories = is_array( $state['advisories'] ?? null ) ? array_values( array_filter( $state['advisories'], 'is_string' ) ) : array();
		$button     = 'pending' === $status ? 'clone_import_rewrite_start' : 'clone_import_rewrite_continue';
		?>
		<h4><?php echo esc_html( $this->copy->text( 'clone_import_rewrite_heading' ) ); ?></h4>
		<p><?php echo esc_html( $this->copy->text( 'clone_import_rewrite_help' ) ); ?></p>
		<table class="widefat striped" role="presentation">
			<tbody>
				<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'clone_import_rewrite_stage' ) ); ?></th><td><code><?php echo esc_html( $stage ); ?></code></td></tr>
				<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'clone_import_rewrite_rows' ) ); ?></th><td><?php echo esc_html( (string) (int) ( $state['rows_scanned'] ?? 0 ) ); ?></td></tr>
				<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'clone_import_rewrite_rows_changed' ) ); ?></th><td><?php echo esc_html( (string) (int) ( $state['rows_changed'] ?? 0 ) ); ?></td></tr>
				<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'clone_import_rewrite_home' ) ); ?></th><td><?php echo esc_html( (string) (int) ( $state['home_rewrites'] ?? 0 ) ); ?></td></tr>
				<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'clone_import_rewrite_siteurl' ) ); ?></th><td><?php echo esc_html( (string) (int) ( $state['siteurl_rewrites'] ?? 0 ) ); ?></td></tr>
				<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'clone_import_rewrite_same_origin' ) ); ?></th><td><?php echo esc_html( (string) (int) ( $state['same_origin_rewrites'] ?? 0 ) ); ?></td></tr>
				<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'clone_import_rewrite_uploads' ) ); ?></th><td><?php echo esc_html( (string) (int) ( $state['upload_url_rewrites'] ?? 0 ) ); ?></td></tr>
				<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'clone_import_rewrite_serialized' ) ); ?></th><td><?php echo esc_html( (string) (int) ( $state['serialized_values'] ?? 0 ) ); ?></td></tr>
				<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'clone_import_rewrite_json' ) ); ?></th><td><?php echo esc_html( (string) (int) ( $state['json_values'] ?? 0 ) ); ?></td></tr>
				<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'clone_import_rewrite_credential_skips' ) ); ?></th><td><?php echo esc_html( (string) (int) ( $state['credential_skips'] ?? 0 ) ); ?></td></tr>
				<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'clone_import_rewrite_opaque_skips' ) ); ?></th><td><?php echo esc_html( (string) (int) ( $state['opaque_serialized_skips'] ?? 0 ) ); ?></td></tr>
				<?php $this->render_sandbox_boolean_row( 'clone_import_rewrite_active_tables', true === ( $state['active_tables_untouched'] ?? true ) ); ?>
				<?php $this->render_sandbox_boolean_row( 'clone_import_rewrite_active_files', true === ( $state['active_roots_untouched'] ?? true ) ); ?>
				<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'clone_import_rewrite_blockers' ) ); ?></th><td><code><?php echo esc_html( array() === $blockers ? $this->copy->text( 'clone_import_none' ) : implode( ', ', $blockers ) ); ?></code></td></tr>
				<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'clone_import_rewrite_advisories' ) ); ?></th><td><code><?php echo esc_html( array() === $advisories ? $this->copy->text( 'clone_import_none' ) : implode( ', ', $advisories ) ); ?></code></td></tr>
			</tbody>
		</table>

		<?php if ( ! in_array( $status, array( 'complete', 'blocked' ), true ) ) : ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="<?php echo esc_attr( AdminCloneImportRewriteController::ACTION ); ?>">
				<input type="hidden" name="clone_job_id" value="<?php echo esc_attr( $job_id ); ?>">
				<?php wp_nonce_field( AdminCloneImportRewriteController::NONCE_ACTION . ':' . $job_id ); ?>
				<p>
					<label for="seo-geo-clone-import-rewrite-rows"><strong><?php echo esc_html( $this->copy->text( 'clone_import_rewrite_batch_label' ) ); ?></strong></label>
					<select id="seo-geo-clone-import-rewrite-rows" name="rewrite_batch_rows">
						<?php foreach ( array( 10, 25, 50, 100, 200, 500 ) as $rows ) : ?>
							<option value="<?php echo esc_attr( (string) $rows ); ?>" <?php selected( ImportEnvironmentRewriter::DEFAULT_BATCH_ROWS, $rows ); ?>><?php echo esc_html( (string) $rows ); ?></option>
						<?php endforeach; ?>
					</select>
				</p>
				<p class="description"><?php echo esc_html( $this->copy->text( 'clone_import_rewrite_batch_help' ) ); ?></p>
				<?php submit_button( $this->copy->text( $button ), 'secondary', 'submit', false ); ?>
			</form>
		<?php endif; ?>
		<?php if ( 'complete' === $status ) : ?>
			<?php $this->render_clone_import_finalize_section( $job_id ); ?>
		<?php endif; ?>
		<?php
	}

	/**
	 * Render a bounded result notice after sandbox finalization preflight.
	 */
	private function render_clone_import_finalize_result_notice(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only result notice after nonce-verified finalization action.
		$status = isset( $_GET['seo_geo_clone_finalize_preflight'] )
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Same read-only result notice value.
			? sanitize_key( wp_unslash( $_GET['seo_geo_clone_finalize_preflight'] ) )
			: '';

		$key = match ( $status ) {
			'running' => 'clone_import_finalize_running',
			'ready'   => 'clone_import_finalize_ready',
			'blocked' => 'clone_import_finalize_blocked',
			default   => null,
		};
		if ( null === $key ) {
			return;
		}

		$class = 'blocked' === $status
			? 'notice notice-error'
			: ( 'running' === $status ? 'notice notice-info' : 'notice notice-success' );
		?>
		<div class="<?php echo esc_attr( $class ); ?> is-dismissible">
			<p><?php echo esc_html( $this->copy->text( $key ) ); ?></p>
		</div>
		<?php
	}

	/**
	 * Render read-only finalization-preflight fingerprint/rollback controls.
	 *
	 * @param string $job_id Clone job identifier.
	 */
	private function render_clone_import_finalize_section( string $job_id ): void {
		$state      = ( new ImportFinalizeStateStore() )->get( $job_id );
		$status     = is_array( $state ) ? (string) ( $state['status'] ?? 'pending' ) : 'pending';
		$stage      = is_array( $state ) ? (string) ( $state['stage'] ?? 'database-fingerprint' ) : 'database-fingerprint';
		$blockers   = is_array( $state['blockers'] ?? null ) ? array_values( array_filter( $state['blockers'], 'is_string' ) ) : array();
		$advisories = is_array( $state['advisories'] ?? null ) ? array_values( array_filter( $state['advisories'], 'is_string' ) ) : array();
		$button     = 'pending' === $status ? 'clone_import_finalize_start' : 'clone_import_finalize_continue';
		?>
		<h4><?php echo esc_html( $this->copy->text( 'clone_import_finalize_heading' ) ); ?></h4>
		<p><?php echo esc_html( $this->copy->text( 'clone_import_finalize_help' ) ); ?></p>
		<table class="widefat striped" role="presentation">
			<tbody>
				<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'clone_import_finalize_stage' ) ); ?></th><td><code><?php echo esc_html( $stage ); ?></code></td></tr>
				<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'clone_import_finalize_db_rows' ) ); ?></th><td><?php echo esc_html( (string) (int) ( $state['database_rows_hashed'] ?? 0 ) ); ?></td></tr>
				<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'clone_import_finalize_db_hash' ) ); ?></th><td><code><?php echo esc_html( (string) ( $state['database_fingerprint'] ?? '' ) ); ?></code></td></tr>
				<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'clone_import_finalize_files' ) ); ?></th><td><?php echo esc_html( (string) (int) ( $state['files_hashed'] ?? 0 ) . ' / ' . (string) (int) ( $state['expected_file_count'] ?? 0 ) ); ?></td></tr>
				<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'clone_import_finalize_bytes' ) ); ?></th><td><?php echo esc_html( size_format( (int) ( $state['file_bytes_hashed'] ?? 0 ) ) . ' / ' . size_format( (int) ( $state['expected_file_bytes'] ?? 0 ) ) ); ?></td></tr>
				<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'clone_import_finalize_file_hash' ) ); ?></th><td><code><?php echo esc_html( (string) ( $state['file_fingerprint'] ?? '' ) ); ?></code></td></tr>
				<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'clone_import_finalize_plan_hash' ) ); ?></th><td><code><?php echo esc_html( (string) ( $state['activation_plan_hash'] ?? '' ) ); ?></code></td></tr>
				<?php $this->render_sandbox_boolean_row( 'clone_import_finalize_hardening', true === ( $state['sandbox_hardening_ready'] ?? false ) ); ?>
				<?php $this->render_sandbox_boolean_row( 'clone_import_finalize_rollback', true === ( $state['rollback_plan_ready'] ?? false ) ); ?>
				<?php $this->render_sandbox_boolean_row( 'clone_import_finalize_activation', true === ( $state['activation_allowed'] ?? false ) ); ?>
				<?php $this->render_sandbox_boolean_row( 'clone_import_finalize_handoff', true === ( $state['handoff_ready'] ?? false ) ); ?>
				<?php $this->render_sandbox_boolean_row( 'clone_import_finalize_active_tables', true === ( $state['active_tables_untouched'] ?? true ) ); ?>
				<?php $this->render_sandbox_boolean_row( 'clone_import_finalize_active_files', true === ( $state['active_roots_untouched'] ?? true ) ); ?>
				<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'clone_import_finalize_blockers' ) ); ?></th><td><code><?php echo esc_html( array() === $blockers ? $this->copy->text( 'clone_import_none' ) : implode( ', ', $blockers ) ); ?></code></td></tr>
				<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'clone_import_finalize_advisories' ) ); ?></th><td><code><?php echo esc_html( array() === $advisories ? $this->copy->text( 'clone_import_none' ) : implode( ', ', $advisories ) ); ?></code></td></tr>
			</tbody>
		</table>

		<?php if ( ! in_array( $status, array( 'ready', 'blocked' ), true ) ) : ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="<?php echo esc_attr( AdminCloneImportFinalizeController::ACTION ); ?>">
				<input type="hidden" name="clone_job_id" value="<?php echo esc_attr( $job_id ); ?>">
				<?php wp_nonce_field( AdminCloneImportFinalizeController::NONCE_ACTION . ':' . $job_id ); ?>
				<p>
					<label for="seo-geo-clone-finalize-rows"><strong><?php echo esc_html( $this->copy->text( 'clone_import_finalize_rows_label' ) ); ?></strong></label>
					<select id="seo-geo-clone-finalize-rows" name="finalize_batch_rows">
						<?php foreach ( array( 25, 50, 100, 200, 500, 1000 ) as $rows ) : ?>
							<option value="<?php echo esc_attr( (string) $rows ); ?>" <?php selected( ImportFinalizationPlanner::DEFAULT_BATCH_ROWS, $rows ); ?>><?php echo esc_html( (string) $rows ); ?></option>
						<?php endforeach; ?>
					</select>
					<label for="seo-geo-clone-finalize-files"><strong><?php echo esc_html( $this->copy->text( 'clone_import_finalize_files_label' ) ); ?></strong></label>
					<select id="seo-geo-clone-finalize-files" name="finalize_batch_files">
						<?php foreach ( array( 25, 50, 100, 200, 500 ) as $files ) : ?>
							<option value="<?php echo esc_attr( (string) $files ); ?>" <?php selected( ImportFinalizationPlanner::DEFAULT_BATCH_FILES, $files ); ?>><?php echo esc_html( (string) $files ); ?></option>
						<?php endforeach; ?>
					</select>
					<label for="seo-geo-clone-finalize-mb"><strong><?php echo esc_html( $this->copy->text( 'clone_import_finalize_mb_label' ) ); ?></strong></label>
					<select id="seo-geo-clone-finalize-mb" name="finalize_batch_megabytes">
						<?php foreach ( array( 4, 8, 16, 32, 64, 128 ) as $megabytes ) : ?>
							<option value="<?php echo esc_attr( (string) $megabytes ); ?>" <?php selected( 16, $megabytes ); ?>><?php echo esc_html( (string) $megabytes ); ?></option>
						<?php endforeach; ?>
					</select>
				</p>
				<p class="description"><?php echo esc_html( $this->copy->text( 'clone_import_finalize_batch_help' ) ); ?></p>
				<?php submit_button( $this->copy->text( $button ), 'secondary', 'submit', false ); ?>
			</form>
		<?php endif; ?>
		<?php if ( 'ready' === $status ) : ?>
			<?php $this->render_clone_database_activation_section( $job_id ); ?>
		<?php endif; ?>
		<?php
	}

	/**
	 * Render a bounded result notice after reversible database activation actions.
	 */
	private function render_clone_database_activation_result_notice(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only result notice after nonce-verified activation action.
		$status = isset( $_GET['seo_geo_clone_database_activation'] )
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Same read-only result notice value.
			? sanitize_key( wp_unslash( $_GET['seo_geo_clone_database_activation'] ) )
			: '';

		$key = match ( $status ) {
			'prepared'    => 'clone_database_activation_prepared',
			'activated'   => 'clone_database_activation_activated',
			'rolled-back' => 'clone_database_activation_rolled_back',
			'blocked'     => 'clone_database_activation_blocked',
			default       => null,
		};
		if ( null === $key ) {
			return;
		}

		$class = 'blocked' === $status
			? 'notice notice-error'
			: ( 'activated' === $status ? 'notice notice-success' : 'notice notice-info' );
		?>
		<div class="<?php echo esc_attr( $class ); ?> is-dismissible">
			<p><?php echo esc_html( $this->copy->text( $key ) ); ?></p>
		</div>
		<?php
	}

	/**
	 * Render the workspace-backed database activation journal and controls.
	 *
	 * @param string $job_id Clone job identifier.
	 */
	private function render_clone_database_activation_section( string $job_id ): void {
		$state            = ( new ImportDatabaseActivationStateStore() )->get( $job_id );
		$status           = is_array( $state ) ? (string) ( $state['status'] ?? 'pending' ) : 'pending';
		$tables           = is_array( $state['tables'] ?? null ) ? $state['tables'] : array();
		$blockers         = is_array( $state['blockers'] ?? null ) ? array_values( array_filter( $state['blockers'], 'is_string' ) ) : array();
		$promotion        = ( new ImportFilePromotionStateStore() )->get( $job_id );
		$promotion_status = is_array( $promotion ) ? (string) ( $promotion['status'] ?? '' ) : '';
		$rollback_locked  = in_array(
			$promotion_status,
			array( 'prepared', 'copying', 'candidate-ready', 'promoting', 'verifying', 'verified' ),
			true
		);
		?>
		<h4><?php echo esc_html( $this->copy->text( 'clone_database_activation_heading' ) ); ?></h4>
		<p><?php echo esc_html( $this->copy->text( 'clone_database_activation_help' ) ); ?></p>
		<table class="widefat striped" role="presentation">
			<tbody>
				<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'clone_database_activation_status' ) ); ?></th><td><code><?php echo esc_html( $status ); ?></code></td></tr>
				<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'clone_database_activation_tables' ) ); ?></th><td><?php echo esc_html( (string) count( $tables ) ); ?></td></tr>
				<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'clone_database_activation_plan_hash' ) ); ?></th><td><code><?php echo esc_html( (string) ( $state['activation_plan_hash'] ?? '' ) ); ?></code></td></tr>
				<?php $this->render_sandbox_boolean_row( 'clone_database_activation_swapped', true === ( $state['database_swapped'] ?? false ) ); ?>
				<?php $this->render_sandbox_boolean_row( 'clone_database_activation_rollback_ready', true === ( $state['rollback_available'] ?? false ) ); ?>
				<?php $this->render_sandbox_boolean_row( 'clone_database_activation_files', true === ( $state['active_files_untouched'] ?? true ) ); ?>
				<?php $this->render_sandbox_boolean_row( 'clone_database_activation_handoff', true === ( $state['handoff_ready'] ?? false ) ); ?>
				<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'clone_database_activation_blockers' ) ); ?></th><td><code><?php echo esc_html( array() === $blockers ? $this->copy->text( 'clone_import_none' ) : implode( ', ', $blockers ) ); ?></code></td></tr>
			</tbody>
		</table>
		<p class="description"><strong><?php echo esc_html( $this->copy->text( 'clone_database_activation_warning' ) ); ?></strong></p>

		<?php if ( 'pending' === $status ) : ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="<?php echo esc_attr( AdminCloneImportDatabaseActivationController::ACTION ); ?>">
				<input type="hidden" name="clone_job_id" value="<?php echo esc_attr( $job_id ); ?>">
				<input type="hidden" name="database_activation_step" value="prepare">
				<?php wp_nonce_field( AdminCloneImportDatabaseActivationController::NONCE_ACTION . ':' . $job_id ); ?>
				<?php submit_button( $this->copy->text( 'clone_database_activation_prepare' ), 'secondary', 'submit', false ); ?>
			</form>
		<?php elseif ( 'prepared' === $status ) : ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="<?php echo esc_attr( AdminCloneImportDatabaseActivationController::ACTION ); ?>">
				<input type="hidden" name="clone_job_id" value="<?php echo esc_attr( $job_id ); ?>">
				<input type="hidden" name="database_activation_step" value="activate">
				<input type="hidden" name="database_activation_confirmation" value="ACTIVATE_DATABASE">
				<?php wp_nonce_field( AdminCloneImportDatabaseActivationController::NONCE_ACTION . ':' . $job_id ); ?>
				<?php submit_button( $this->copy->text( 'clone_database_activation_activate' ), 'primary', 'submit', false ); ?>
			</form>
		<?php elseif ( in_array( $status, array( 'activated', 'activating' ), true ) && ! $rollback_locked ) : ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="<?php echo esc_attr( AdminCloneImportDatabaseActivationController::ACTION ); ?>">
				<input type="hidden" name="clone_job_id" value="<?php echo esc_attr( $job_id ); ?>">
				<input type="hidden" name="database_activation_step" value="rollback">
				<input type="hidden" name="database_activation_confirmation" value="ROLLBACK_DATABASE">
				<?php wp_nonce_field( AdminCloneImportDatabaseActivationController::NONCE_ACTION . ':' . $job_id ); ?>
				<?php submit_button( $this->copy->text( 'clone_database_activation_rollback' ), 'secondary', 'submit', false ); ?>
			</form>
		<?php elseif ( in_array( $status, array( 'activated', 'activating' ), true ) && $rollback_locked ) : ?>
			<p class="description"><strong><?php echo esc_html( $this->copy->text( 'clone_database_activation_rollback_locked' ) ); ?></strong></p>
		<?php endif; ?>
		<?php if ( 'activated' === $status ) : ?>
			<?php $this->render_clone_file_promotion_section( $job_id ); ?>
		<?php endif; ?>
		<?php
	}

	/**
	 * Render a bounded result notice after reversible file-promotion actions.
	 */
	private function render_clone_file_promotion_result_notice(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only result notice after nonce-verified promotion action.
		$status = isset( $_GET['seo_geo_clone_file_promotion'] )
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Same read-only result notice value.
			? sanitize_key( wp_unslash( $_GET['seo_geo_clone_file_promotion'] ) )
			: '';

		$key = match ( $status ) {
			'prepared'        => 'clone_file_promotion_prepared',
			'copying'         => 'clone_file_promotion_copying',
			'candidate-ready' => 'clone_file_promotion_candidate_ready',
			'promoting'       => 'clone_file_promotion_promoting',
			'verifying'       => 'clone_file_promotion_verifying',
			'verified'        => 'clone_file_promotion_verified',
			'rolled-back'     => 'clone_file_promotion_rolled_back',
			'blocked'         => 'clone_file_promotion_blocked',
			default           => null,
		};
		if ( null === $key ) {
			return;
		}

		$class = 'blocked' === $status
			? 'notice notice-error'
			: ( 'verified' === $status ? 'notice notice-success' : 'notice notice-info' );
		?>
		<div class="<?php echo esc_attr( $class ); ?> is-dismissible">
			<p><?php echo esc_html( $this->copy->text( $key ) ); ?></p>
		</div>
		<?php
	}

	/**
	 * Render the workspace-backed file-promotion journal and controls.
	 *
	 * @param string $job_id Clone job identifier.
	 */
	private function render_clone_file_promotion_section( string $job_id ): void {
		$state    = ( new ImportFilePromotionStateStore() )->get( $job_id );
		$status   = is_array( $state ) ? (string) ( $state['status'] ?? 'pending' ) : 'pending';
		$roots    = is_array( $state['roots'] ?? null ) ? $state['roots'] : array();
		$blockers = is_array( $state['blockers'] ?? null ) ? array_values( array_filter( $state['blockers'], 'is_string' ) ) : array();
		?>
		<h4><?php echo esc_html( $this->copy->text( 'clone_file_promotion_heading' ) ); ?></h4>
		<p><?php echo esc_html( $this->copy->text( 'clone_file_promotion_help' ) ); ?></p>
		<table class="widefat striped" role="presentation">
			<tbody>
				<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'clone_file_promotion_status' ) ); ?></th><td><code><?php echo esc_html( $status ); ?></code></td></tr>
				<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'clone_file_promotion_roots' ) ); ?></th><td><?php echo esc_html( (string) count( $roots ) ); ?></td></tr>
				<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'clone_file_promotion_copied' ) ); ?></th><td><?php echo esc_html( (string) (int) ( $state['file_count'] ?? 0 ) ); ?></td></tr>
				<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'clone_file_promotion_verified_files' ) ); ?></th><td><?php echo esc_html( (string) (int) ( $state['verify_file_count'] ?? 0 ) ); ?></td></tr>
				<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'clone_file_promotion_fingerprint' ) ); ?></th><td><code><?php echo esc_html( (string) ( $state['file_fingerprint'] ?? '' ) ); ?></code></td></tr>
				<?php $this->render_sandbox_boolean_row( 'clone_file_promotion_database', true === ( $state['database_activated'] ?? false ) ); ?>
				<?php $this->render_sandbox_boolean_row( 'clone_file_promotion_rollback_ready', true === ( $state['rollback_available'] ?? false ) ); ?>
				<?php $this->render_sandbox_boolean_row( 'clone_file_promotion_handoff', true === ( $state['handoff_ready'] ?? false ) ); ?>
				<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'clone_file_promotion_blockers' ) ); ?></th><td><code><?php echo esc_html( array() === $blockers ? $this->copy->text( 'clone_import_none' ) : implode( ', ', $blockers ) ); ?></code></td></tr>
			</tbody>
		</table>
		<p class="description"><strong><?php echo esc_html( $this->copy->text( 'clone_file_promotion_warning' ) ); ?></strong></p>

		<?php if ( 'pending' === $status ) : ?>
			<?php $this->render_clone_file_promotion_form( $job_id, 'prepare', 'clone_file_promotion_prepare', false, false ); ?>
		<?php elseif ( in_array( $status, array( 'prepared', 'copying' ), true ) ) : ?>
			<?php $this->render_clone_file_promotion_form( $job_id, 'copy', 'clone_file_promotion_copy', true, false ); ?>
		<?php elseif ( 'candidate-ready' === $status ) : ?>
			<?php $this->render_clone_file_promotion_form( $job_id, 'promote', 'clone_file_promotion_promote', false, true ); ?>
		<?php elseif ( 'promoting' === $status ) : ?>
			<?php $this->render_clone_file_promotion_form( $job_id, 'promote', 'clone_file_promotion_resume_promote', false, true ); ?>
		<?php elseif ( 'verifying' === $status ) : ?>
			<?php $this->render_clone_file_promotion_form( $job_id, 'verify', 'clone_file_promotion_verify', true, false ); ?>
			<?php $this->render_clone_file_promotion_form( $job_id, 'rollback', 'clone_file_promotion_rollback', false, true ); ?>
		<?php elseif ( 'verified' === $status ) : ?>
			<?php $this->render_clone_file_promotion_form( $job_id, 'rollback', 'clone_file_promotion_rollback', false, true ); ?>
		<?php endif; ?>
		<?php
	}

	/**
	 * Render one file-promotion action form.
	 *
	 * @param string $job_id       Clone job identifier.
	 * @param string $step         Promotion step.
	 * @param string $button_key   Localized button key.
	 * @param bool   $show_batches Whether to show bounded batch controls.
	 * @param bool   $destructive  Whether explicit confirmation is required.
	 */
	private function render_clone_file_promotion_form(
		string $job_id,
		string $step,
		string $button_key,
		bool $show_batches,
		bool $destructive
	): void {
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:1rem">
			<input type="hidden" name="action" value="<?php echo esc_attr( AdminCloneImportFilePromotionController::ACTION ); ?>">
			<input type="hidden" name="clone_job_id" value="<?php echo esc_attr( $job_id ); ?>">
			<input type="hidden" name="file_promotion_step" value="<?php echo esc_attr( $step ); ?>">
			<?php if ( $destructive ) : ?>
				<input type="hidden" name="file_promotion_confirmation" value="<?php echo esc_attr( 'rollback' === $step ? 'ROLLBACK_FILES' : 'PROMOTE_FILES' ); ?>">
			<?php endif; ?>
			<?php wp_nonce_field( AdminCloneImportFilePromotionController::NONCE_ACTION . ':' . $job_id ); ?>
			<?php if ( $show_batches ) : ?>
				<p>
					<label for="seo-geo-file-promotion-files-<?php echo esc_attr( $step ); ?>"><strong><?php echo esc_html( $this->copy->text( 'clone_file_promotion_files_label' ) ); ?></strong></label>
					<select id="seo-geo-file-promotion-files-<?php echo esc_attr( $step ); ?>" name="file_promotion_batch_files">
						<?php for ( $files = 1; $files <= 20; ++$files ) : ?>
							<option value="<?php echo esc_attr( (string) $files ); ?>" <?php selected( ImportFilePromoter::DEFAULT_BATCH_FILES, $files ); ?>><?php echo esc_html( (string) $files ); ?></option>
						<?php endfor; ?>
					</select>
					<label for="seo-geo-file-promotion-mb-<?php echo esc_attr( $step ); ?>"><strong><?php echo esc_html( $this->copy->text( 'clone_file_promotion_mb_label' ) ); ?></strong></label>
					<select id="seo-geo-file-promotion-mb-<?php echo esc_attr( $step ); ?>" name="file_promotion_batch_megabytes">
						<?php foreach ( array( 4, 8, 16, 32, 64, 128 ) as $megabytes ) : ?>
							<option value="<?php echo esc_attr( (string) $megabytes ); ?>" <?php selected( 16, $megabytes ); ?>><?php echo esc_html( (string) $megabytes ); ?></option>
						<?php endforeach; ?>
					</select>
				</p>
				<p class="description"><?php echo esc_html( $this->copy->text( 'clone_file_promotion_batch_help' ) ); ?></p>
			<?php endif; ?>
			<?php submit_button( $this->copy->text( $button_key ), 'rollback' === $step ? 'secondary' : 'primary', 'submit', false ); ?>
		</form>
		<?php
	}

	/**
	 * Render one Portable Clone planning-job form.
	 *
	 * @param string $operation Operation identifier.
	 * @param string $button_key Localized button key.
	 */
	private function render_clone_job_form( string $operation, string $button_key ): void {
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="<?php echo esc_attr( AdminCloneController::ACTION ); ?>">
			<input type="hidden" name="clone_operation" value="<?php echo esc_attr( $operation ); ?>">
			<?php wp_nonce_field( AdminCloneController::NONCE_ACTION ); ?>
			<?php submit_button( $this->copy->text( $button_key ), 'secondary', 'submit', false ); ?>
		</form>
		<?php
	}

	/**
	 * Render one explicit planning-only review form for an UNKNOWN dependency.
	 *
	 * @param array<string,mixed> $component Bounded dependency component.
	 */
	private function render_dependency_review_form( array $component ): void {
		$component_id = is_string( $component['component_id'] ?? null ) ? $component['component_id'] : '';
		$current      = is_string( $component['review_decision'] ?? null ) ? $component['review_decision'] : 'UNREVIEWED';
		if ( '' === $component_id ) {
			return;
		}
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="<?php echo esc_attr( AdminDependencyReviewController::ACTION ); ?>">
			<input type="hidden" name="component_id" value="<?php echo esc_attr( $component_id ); ?>">
			<?php wp_nonce_field( AdminDependencyReviewController::NONCE_ACTION . ':' . $component_id ); ?>
			<label class="screen-reader-text" for="seo-geo-review-<?php echo esc_attr( md5( $component_id ) ); ?>">
				<?php echo esc_html( $this->copy->text( 'label_review' ) ); ?>
			</label>
			<select id="seo-geo-review-<?php echo esc_attr( md5( $component_id ) ); ?>" name="review_decision">
				<option value="UNREVIEWED" <?php selected( $current, 'UNREVIEWED' ); ?>><?php echo esc_html( $this->copy->text( 'review_unreviewed' ) ); ?></option>
				<?php foreach ( DependencyReviewStore::allowed_decisions() as $decision ) : ?>
					<option value="<?php echo esc_attr( $decision ); ?>" <?php selected( $current, $decision ); ?>><?php echo esc_html( $decision ); ?></option>
				<?php endforeach; ?>
			</select>
			<?php submit_button( $this->copy->text( 'review_save' ), 'secondary small', 'submit', false ); ?>
		</form>
		<?php
	}

	/**
	 * Render the explicit same-origin public baseline capture action.
	 *
	 * @param array<string,mixed> $status Operator snapshot.
	 */
	private function render_baseline_capture_form( array $status ): void {
		$capture    = is_array( $status['capture'] ?? null ) ? $status['capture'] : array();
		$running    = 'running' === ( $capture['status'] ?? null );
		$processed  = (int) ( $capture['processed'] ?? 0 );
		$total      = (int) ( $capture['total'] ?? 0 );
		$percent    = (int) ( $capture['percent'] ?? 0 );
		$batch_size = (int) ( $capture['batch_size'] ?? IncrementalBaselineCapture::DEFAULT_BATCH_SIZE );
		$button     = $running ? 'baseline_continue' : 'baseline_capture_button';
		?>
		<div class="seo-geo-migration-baseline-action">
			<p><?php echo esc_html( $this->copy->text( 'baseline_capture_help' ) ); ?></p>
			<?php if ( $running ) : ?>
				<p>
					<?php
					echo esc_html(
						sprintf(
							$this->copy->text( 'baseline_progress' ),
							$processed,
							$total,
							$percent
						)
					);
					?>
				</p>
				<progress value="<?php echo esc_attr( (string) $percent ); ?>" max="100"><?php echo esc_html( (string) $percent ); ?>%</progress>
			<?php endif; ?>
			<form id="seo-geo-baseline-capture-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="<?php echo esc_attr( AdminBaselineCaptureController::ACTION ); ?>">
				<?php wp_nonce_field( AdminBaselineCaptureController::NONCE_ACTION ); ?>
				<p>
					<label for="seo-geo-baseline-batch-size"><strong><?php echo esc_html( $this->copy->text( 'batch_size_label' ) ); ?></strong></label>
					<select id="seo-geo-baseline-batch-size" name="batch_size">
						<?php for ( $size = IncrementalBaselineCapture::MIN_BATCH_SIZE; $size <= IncrementalBaselineCapture::MAX_BATCH_SIZE; ++$size ) : ?>
							<option value="<?php echo esc_attr( (string) $size ); ?>" <?php selected( $batch_size, $size ); ?>><?php echo esc_html( (string) $size ); ?></option>
						<?php endfor; ?>
					</select>
				</p>
				<p class="description"><?php echo esc_html( $this->copy->text( 'batch_size_help' ) ); ?></p>
				<?php if ( $running ) : ?>
					<p><?php echo esc_html( sprintf( $this->copy->text( 'batch_size_current' ), $batch_size ) ); ?></p>
				<?php endif; ?>
				<?php submit_button( $this->copy->text( $button ), 'primary', 'submit', false ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Render bounded sandbox preflight evidence.
	 *
	 * @param array<string,mixed> $status Operator snapshot.
	 */
	private function render_sandbox_preflight( array $status ): void {
		$sandbox     = is_array( $status['sandbox'] ?? null ) ? $status['sandbox'] : array();
		$environment = is_array( $sandbox['environment'] ?? null ) ? $sandbox['environment'] : array();
		$blockers    = is_array( $sandbox['blockers'] ?? null ) ? array_values( array_filter( $sandbox['blockers'], 'is_string' ) ) : array();
		?>
		<h2><?php echo esc_html( $this->copy->text( 'sandbox_preflight_heading' ) ); ?></h2>
		<p><?php echo esc_html( $this->copy->text( 'sandbox_preflight_help' ) ); ?></p>
		<table class="widefat striped" role="presentation">
			<tbody>
				<tr>
					<th scope="row"><?php echo esc_html( $this->copy->text( 'label_source_origin' ) ); ?></th>
					<td><code><?php echo esc_html( (string) ( $environment['source_origin'] ?? '' ) ); ?></code></td>
				</tr>
				<tr>
					<th scope="row"><?php echo esc_html( $this->copy->text( 'label_current_origin' ) ); ?></th>
					<td><code><?php echo esc_html( (string) ( $environment['current_origin'] ?? '' ) ); ?></code></td>
				</tr>
				<tr>
					<th scope="row"><?php echo esc_html( $this->copy->text( 'label_sandbox_mode' ) ); ?></th>
					<td><code><?php echo esc_html( (string) ( $environment['sandbox_mode'] ?? '' ) ); ?></code></td>
				</tr>
				<tr>
					<th scope="row"><?php echo esc_html( $this->copy->text( 'label_source_path' ) ); ?></th>
					<td><code><?php echo esc_html( (string) ( $environment['source_base_path'] ?? '' ) ); ?></code></td>
				</tr>
				<tr>
					<th scope="row"><?php echo esc_html( $this->copy->text( 'label_current_path' ) ); ?></th>
					<td><code><?php echo esc_html( (string) ( $environment['current_base_path'] ?? '' ) ); ?></code></td>
				</tr>
				<?php $this->render_sandbox_boolean_row( 'label_location_isolated', true === ( $environment['location_isolated'] ?? false ) ); ?>
				<?php $this->render_sandbox_boolean_row( 'label_distinct_origin', true === ( $environment['distinct_origin'] ?? false ) ); ?>
				<?php if ( 'subdirectory' === ( $environment['sandbox_mode'] ?? null ) ) : ?>
					<?php $this->render_sandbox_boolean_row( 'label_storage_isolated', true === ( $environment['storage_isolation_confirmed'] ?? false ) ); ?>
				<?php endif; ?>
				<?php $this->render_sandbox_boolean_row( 'label_search_disabled', 'discouraged' === ( $environment['search_engine_visibility'] ?? null ) ); ?>
				<?php $this->render_sandbox_boolean_row( 'label_outbound_safe', true === ( $environment['outbound_safety_confirmed'] ?? false ) ); ?>
				<?php $this->render_sandbox_boolean_row( 'label_backups_ready', true === ( $environment['fresh_backups_confirmed'] ?? false ) ); ?>
				<?php $this->render_sandbox_boolean_row( 'label_destination_theme', true === ( $environment['destination_theme_active'] ?? false ) ); ?>
				<?php $this->render_sandbox_boolean_row( 'label_review_complete', true === ( $environment['dependency_review_complete'] ?? false ) ); ?>
				<tr>
					<th scope="row"><?php echo esc_html( $this->copy->text( 'label_sandbox_blockers' ) ); ?></th>
					<td>
						<?php if ( array() === $blockers ) : ?>
							<?php echo esc_html( $this->copy->text( 'sandbox_no_blockers' ) ); ?>
						<?php else : ?>
							<code><?php echo esc_html( implode( ', ', $blockers ) ); ?></code>
						<?php endif; ?>
					</td>
				</tr>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Render one yes/no sandbox evidence row.
	 *
	 * @param string $label_key Localized label key.
	 * @param bool   $value     Evidence state.
	 */
	private function render_sandbox_boolean_row( string $label_key, bool $value ): void {
		?>
		<tr>
			<th scope="row"><?php echo esc_html( $this->copy->text( $label_key ) ); ?></th>
			<td><?php echo esc_html( $this->copy->text( $value ? 'yes' : 'no' ) ); ?></td>
		</tr>
		<?php
	}

	/**
	 * Render one two-column overview row.
	 *
	 * @param string $label_key Localized label key.
	 * @param string $value_key Localized value key.
	 */
	private function render_overview_row( string $label_key, string $value_key ): void {
		?>
		<tr>
			<th scope="row"><?php echo esc_html( $this->copy->text( $label_key ) ); ?></th>
			<td><?php echo esc_html( $this->copy->text( $value_key ) ); ?></td>
		</tr>
		<?php
	}

	/**
	 * Resolve baseline status label.
	 *
	 * @param array<string,mixed> $status Operator snapshot.
	 */
	private function baseline_label_key( array $status ): string {
		return true === ( $status['baseline']['available'] ?? false ) ? 'status_ready' : 'status_missing';
	}

	/**
	 * Resolve dependency-plan status label.
	 *
	 * @param array<string,mixed> $status Operator snapshot.
	 */
	private function dependency_label_key( array $status ): string {
		if ( true !== ( $status['dependency_plan']['available'] ?? false ) ) {
			return 'status_missing';
		}

		return 0 < (int) ( $status['dependency_plan']['blocking_count'] ?? 0 )
			? 'status_needs_attention'
			: 'status_ready';
	}

	/**
	 * Resolve cutover status label.
	 *
	 * @param array<string,mixed> $status Operator snapshot.
	 */
	private function cutover_label_key( array $status ): string {
		if ( true !== ( $status['cutover']['available'] ?? false ) ) {
			return 'status_missing';
		}

		return true === ( $status['cutover']['accepted'] ?? false ) ? 'status_accepted' : 'status_pending';
	}

	/**
	 * Resolve final-report status label.
	 *
	 * @param array<string,mixed> $status Operator snapshot.
	 */
	private function report_label_key( array $status ): string {
		if ( true !== ( $status['final_report']['available'] ?? false ) ) {
			return 'status_missing';
		}

		return true === ( $status['final_report']['ready_for_handoff'] ?? false )
			? 'status_ready'
			: 'status_needs_attention';
	}

	/**
	 * Resolve sandbox preflight status label.
	 *
	 * @param array<string,mixed> $status Operator snapshot.
	 */
	private function sandbox_label_key( array $status ): string {
		return true === ( $status['sandbox']['ready'] ?? false )
			? 'status_ready'
			: 'status_needs_attention';
	}

	/**
	 * Resolve bridge disposition label.
	 *
	 * @param array<string,mixed> $status Operator snapshot.
	 */
	private function disposition_label( array $status ): string {
		$decision = $status['final_report']['bridge_disposition'] ?? null;

		return match ( $decision ) {
			'remove'              => $this->copy->text( 'disposition_remove' ),
			'retain-audit-only'   => $this->copy->text( 'disposition_retain_audit_only' ),
			'retain-operational'  => $this->copy->text( 'disposition_retain_operational' ),
			default               => $this->copy->text( 'status_not_applicable' ),
		};
	}

	/**
	 * Resolve localized next-step guidance.
	 *
	 * @param array<string,mixed> $status Operator snapshot.
	 */
	private function next_step_text( array $status ): string {
		$key = match ( $status['next_step'] ?? null ) {
			'capture-baseline'   => 'next_capture_baseline',
			'sandbox-preflight'  => 'next_sandbox_preflight',
			'continue-migration' => 'next_continue_migration',
			'complete-cutover'   => 'next_complete_cutover',
			'generate-report'    => 'next_generate_report',
			'resolve-blockers'   => 'next_resolve_blockers',
			'review-advisories'  => 'next_review_advisories',
			'remove-bridge'      => 'next_remove_bridge',
			default              => 'next_continue_migration',
		};

		return $this->copy->text( $key );
	}
}
