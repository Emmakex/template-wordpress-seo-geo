<?php
/**
 * Migration Bridge operator administration screen.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge\Operator;

use SeoGeo\MigrationBridge\Clone\AdminCloneController;
use SeoGeo\MigrationBridge\Clone\AdminCloneInventoryController;
use SeoGeo\MigrationBridge\Clone\AdminCloneImportController;
use SeoGeo\MigrationBridge\Clone\AdminCloneImportPayloadController;
use SeoGeo\MigrationBridge\Clone\AdminCloneImportDatabaseController;
use SeoGeo\MigrationBridge\Clone\AdminCloneImportFileController;
use SeoGeo\MigrationBridge\Clone\AdminCloneImportRewriteController;
use SeoGeo\MigrationBridge\Clone\AdminCloneImportActivationPlanController;
use SeoGeo\MigrationBridge\Clone\AdminCloneDatabaseExportController;
use SeoGeo\MigrationBridge\Clone\AdminCloneFileExportController;
use SeoGeo\MigrationBridge\Clone\AdminClonePackageController;
use SeoGeo\MigrationBridge\Clone\AdminCloneDeliveryController;
use SeoGeo\MigrationBridge\Clone\CloneInventory;
use SeoGeo\MigrationBridge\Clone\CloneInventoryStore;
use SeoGeo\MigrationBridge\Clone\CloneJobStore;
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
use SeoGeo\MigrationBridge\Clone\ImportActivationPlanStore;
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
			<?php $this->render_clone_delivery_result_notice(); ?>
			<?php $this->render_clone_import_result_notice(); ?>
			<?php $this->render_clone_import_payload_result_notice(); ?>
			<?php $this->render_clone_import_database_result_notice(); ?>
			<?php $this->render_clone_import_file_result_notice(); ?>
			<?php $this->render_clone_import_rewrite_result_notice(); ?>
			<?php $this->render_clone_import_activation_plan_result_notice(); ?>

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
		<?php elseif ( 'complete' === $status ) : ?>
			<?php $this->render_clone_import_activation_plan_section( $job_id ); ?>
		<?php endif; ?>
		<?php
	}

	/**
	 * Render activation-plan result notice.
	 */
	private function render_clone_import_activation_plan_result_notice(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only notice after nonce-verified activation planning.
		$status = isset( $_GET['seo_geo_clone_activation_plan'] )
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Same read-only result value.
			? sanitize_key( wp_unslash( $_GET['seo_geo_clone_activation_plan'] ) )
			: '';

		$key = match ( $status ) {
			'ready'   => 'clone_import_activation_ready',
			'blocked' => 'clone_import_activation_blocked',
			default   => null,
		};
		if ( null === $key ) {
			return;
		}

		$class = 'ready' === $status ? 'notice notice-success' : 'notice notice-error';
		?>
		<div class="<?php echo esc_attr( $class ); ?> is-dismissible">
			<p><?php echo esc_html( $this->copy->text( $key ) ); ?></p>
		</div>
		<?php
	}

	/**
	 * Render non-mutating sandbox activation planning controls.
	 *
	 * @param string $job_id Clone job identifier.
	 */
	private function render_clone_import_activation_plan_section( string $job_id ): void {
		$plan       = ( new ImportActivationPlanStore() )->get( $job_id );
		$status     = is_array( $plan ) ? (string) ( $plan['status'] ?? 'pending' ) : 'pending';
		$blockers   = is_array( $plan['blockers'] ?? null ) ? array_values( array_filter( $plan['blockers'], 'is_string' ) ) : array();
		$advisories = is_array( $plan['advisories'] ?? null ) ? array_values( array_filter( $plan['advisories'], 'is_string' ) ) : array();
		$recovery   = is_array( $plan['recovery'] ?? null ) ? $plan['recovery'] : array();
		$db          = is_array( $recovery['database'] ?? null ) ? $recovery['database'] : array();
		$content     = is_array( $recovery['wp_content'] ?? null ) ? $recovery['wp_content'] : array();
		?>
		<h4><?php echo esc_html( $this->copy->text( 'clone_import_activation_heading' ) ); ?></h4>
		<p><?php echo esc_html( $this->copy->text( 'clone_import_activation_help' ) ); ?></p>
		<?php if ( is_array( $plan ) ) : ?>
			<table class="widefat striped" role="presentation">
				<tbody>
					<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'label_clone_status' ) ); ?></th><td><code><?php echo esc_html( $status ); ?></code></td></tr>
					<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'clone_import_activation_plan_hash' ) ); ?></th><td><code><?php echo esc_html( (string) ( $plan['plan_sha256'] ?? '' ) ); ?></code></td></tr>
					<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'clone_import_activation_tables' ) ); ?></th><td><?php echo esc_html( (string) (int) ( $plan['table_count'] ?? 0 ) ); ?></td></tr>
					<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'clone_import_activation_files' ) ); ?></th><td><?php echo esc_html( (string) (int) ( $plan['staging_file_count'] ?? 0 ) ); ?></td></tr>
					<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'clone_import_activation_bytes' ) ); ?></th><td><?php echo esc_html( size_format( (int) ( $plan['staging_file_bytes'] ?? 0 ) ) ); ?></td></tr>
					<?php $this->render_sandbox_boolean_row( 'clone_import_activation_recovery', true === ( $plan['recovery_valid'] ?? false ) ); ?>
					<?php $this->render_sandbox_boolean_row( 'clone_import_activation_allowed_db', true === ( $plan['database_activation_allowed'] ?? false ) ); ?>
					<?php $this->render_sandbox_boolean_row( 'clone_import_activation_allowed_files', true === ( $plan['file_activation_allowed'] ?? false ) ); ?>
					<?php $this->render_sandbox_boolean_row( 'clone_import_activation_mutations', true === ( $plan['mutations_performed'] ?? false ) ); ?>
					<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'clone_import_activation_blockers' ) ); ?></th><td><code><?php echo esc_html( array() === $blockers ? $this->copy->text( 'clone_import_none' ) : implode( ', ', $blockers ) ); ?></code></td></tr>
					<tr><th scope="row"><?php echo esc_html( $this->copy->text( 'clone_import_activation_advisories' ) ); ?></th><td><code><?php echo esc_html( array() === $advisories ? $this->copy->text( 'clone_import_none' ) : implode( ', ', $advisories ) ); ?></code></td></tr>
				</tbody>
			</table>
		<?php endif; ?>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="<?php echo esc_attr( AdminCloneImportActivationPlanController::ACTION ); ?>">
			<input type="hidden" name="clone_job_id" value="<?php echo esc_attr( $job_id ); ?>">
			<?php wp_nonce_field( AdminCloneImportActivationPlanController::NONCE_ACTION . ':' . $job_id ); ?>
			<p class="description"><?php echo esc_html( $this->copy->text( 'clone_import_recovery_help' ) ); ?></p>
			<table class="form-table" role="presentation">
				<tbody>
					<?php $this->render_activation_evidence_row( 'clone_import_recovery_db_ref', 'recovery_database_reference', (string) ( $db['reference'] ?? '' ) ); ?>
					<?php $this->render_activation_evidence_row( 'clone_import_recovery_db_sha', 'recovery_database_sha256', (string) ( $db['sha256'] ?? '' ) ); ?>
					<?php $this->render_activation_evidence_row( 'clone_import_recovery_db_at', 'recovery_database_created_at', (string) ( $db['created_at'] ?? '' ) ); ?>
					<?php $this->render_activation_evidence_row( 'clone_import_recovery_db_size', 'recovery_database_size_bytes', (string) ( $db['size_bytes'] ?? '' ), 'number' ); ?>
					<?php $this->render_activation_evidence_row( 'clone_import_recovery_content_ref', 'recovery_wp_content_reference', (string) ( $content['reference'] ?? '' ) ); ?>
					<?php $this->render_activation_evidence_row( 'clone_import_recovery_content_sha', 'recovery_wp_content_sha256', (string) ( $content['sha256'] ?? '' ) ); ?>
					<?php $this->render_activation_evidence_row( 'clone_import_recovery_content_at', 'recovery_wp_content_created_at', (string) ( $content['created_at'] ?? '' ) ); ?>
					<?php $this->render_activation_evidence_row( 'clone_import_recovery_content_size', 'recovery_wp_content_size_bytes', (string) ( $content['size_bytes'] ?? '' ), 'number' ); ?>
				</tbody>
			</table>
			<?php submit_button( $this->copy->text( 'clone_import_activation_button' ), 'secondary', 'submit', false ); ?>
		</form>
		<?php
	}

	/**
	 * Render one activation recovery evidence input.
	 *
	 * @param string $label_key Copy key.
	 * @param string $name      Input name.
	 * @param string $value     Current value.
	 * @param string $type      Input type.
	 */
	private function render_activation_evidence_row( string $label_key, string $name, string $value, string $type = 'text' ): void {
		?>
		<tr>
			<th scope="row"><label for="<?php echo esc_attr( $name ); ?>"><?php echo esc_html( $this->copy->text( $label_key ) ); ?></label></th>
			<td><input class="regular-text" type="<?php echo esc_attr( $type ); ?>" min="0" id="<?php echo esc_attr( $name ); ?>" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $value ); ?>" required></td>
		</tr>
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
