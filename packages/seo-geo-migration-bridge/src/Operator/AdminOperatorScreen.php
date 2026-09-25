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
use SeoGeo\MigrationBridge\Clone\AdminCloneDatabaseExportController;
use SeoGeo\MigrationBridge\Clone\AdminCloneFileExportController;
use SeoGeo\MigrationBridge\Clone\CloneInventory;
use SeoGeo\MigrationBridge\Clone\CloneInventoryStore;
use SeoGeo\MigrationBridge\Clone\CloneJobStore;
use SeoGeo\MigrationBridge\Clone\DatabaseExporter;
use SeoGeo\MigrationBridge\Clone\ExportStateStore;
use SeoGeo\MigrationBridge\Clone\FileExporter;
use SeoGeo\MigrationBridge\Clone\FileExportStateStore;
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
						<th scope="row"><?php echo esc_html( $this->copy->text( 'label_clone_file_export_manifest' ) ); ?></th>
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
		<?php endif; ?>
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
