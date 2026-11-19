<?php
/**
 * Read-only, four-step entry point for the existing Migration Bridge engines.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge\Operator;

use SeoGeo\MigrationBridge\Clone\AdminCloneController;
use SeoGeo\MigrationBridge\Clone\AdminCloneDeliveryController;
use SeoGeo\MigrationBridge\Clone\AdminCloneDatabaseExportController;
use SeoGeo\MigrationBridge\Clone\AdminCloneFileExportController;
use SeoGeo\MigrationBridge\Clone\AdminCloneInventoryController;
use SeoGeo\MigrationBridge\Clone\AdminCloneImportController;
use SeoGeo\MigrationBridge\Clone\CloneInventoryStore;
use SeoGeo\MigrationBridge\Clone\CloneJobStore;
use SeoGeo\MigrationBridge\Clone\ImportFilePromotionStateStore;
use SeoGeo\MigrationBridge\Clone\ImportPayloadStateStore;
use SeoGeo\MigrationBridge\Clone\ImportStateStore;
use SeoGeo\MigrationBridge\Clone\LocalCloneHandoffReportStateStore;
use SeoGeo\MigrationBridge\Clone\LocalCloneFilePromotionStateStore;
use SeoGeo\MigrationBridge\Clone\PackageStateStore;

/**
 * Presentation-only facade: never creates, resumes, activates or modifies jobs.
 */
final class AdminMigrationWizard {
	/**
	 * Render the simple operator view from persisted, normalized evidence.
	 *
	 * @param array<string,mixed> $operator_status Read-only operator snapshot.
	 * @param OperatorCopy|null   $copy            EN/ES operator copy for this view.
	 */
	public static function render( array $operator_status, ?OperatorCopy $copy = null ): void {
		$copy = $copy ?? new OperatorCopy();
		$jobs = array_values( ( new CloneJobStore() )->all() );
		usort(
			$jobs,
			static fn( array $left, array $right ): int => strcmp(
				(string) ( $right['updated_at'] ?? '' ),
				(string) ( $left['updated_at'] ?? '' )
			)
		);

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only selects a read-only job view.
		$requested = isset( $_GET['clone_job_id'] ) && is_string( $_GET['clone_job_id'] )
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- No state mutation.
			? sanitize_text_field( wp_unslash( $_GET['clone_job_id'] ) )
			: '';
		$job = $jobs[0] ?? null;
		foreach ( $jobs as $candidate ) {
			if ( '' !== $requested && hash_equals( (string) $candidate['job_id'], $requested ) ) {
				$job = $candidate;
				break;
			}
		}

		$job_id    = is_array( $job ) ? (string) $job['job_id'] : '';
		$operation = is_array( $job ) ? (string) $job['operation'] : '';
		$inventory = '' !== $job_id ? ( new CloneInventoryStore() )->get( $job_id ) : null;
		$package   = '' !== $job_id ? ( new PackageStateStore() )->get( $job_id ) : null;
		$handoff   = 'local-clone' === $operation ? ( new LocalCloneHandoffReportStateStore() )->get( $job_id ) : null;
		$promotion = 'local-clone' === $operation
			? ( new LocalCloneFilePromotionStateStore() )->get( $job_id )
			: ( 'import' === $operation ? ( new ImportFilePromotionStateStore() )->get( $job_id ) : null );

		$is_import_only = 'import' === $operation;
		$analyzed       = 'complete' === ( $inventory['status'] ?? '' );
		$exported       = 'complete' === ( $package['status'] ?? '' )
			&& 'complete' === ( $package['stage'] ?? '' );
		$imported       = 'verified' === ( $promotion['status'] ?? '' );
		$verified       = 'ready' === ( $handoff['status'] ?? '' )
			&& true === ( $handoff['handoff_ready'] ?? false );
		if ( $is_import_only ) {
			$verified = $imported && true === ( $promotion['handoff_ready'] ?? false );
		}
		$steps = array(
			array(
				'title' => $copy->text( 'wizard_step_analyze' ),
				'state' => $is_import_only ? 'external' : ( $analyzed ? 'complete' : 'pending' ),
				'note'  => $copy->text( 'wizard_analyze_note' ),
			),
			array(
				'title' => $copy->text( 'wizard_step_export' ),
				'state' => $is_import_only ? 'external' : ( $exported ? 'complete' : 'pending' ),
				'note'  => $copy->text( 'wizard_export_note' ),
			),
			array(
				'title' => $copy->text( 'wizard_step_import' ),
				'state' => 'export' === $operation ? 'external' : ( $imported ? 'complete' : 'pending' ),
				'note'  => $copy->text( 'wizard_import_note' ),
			),
			array(
				'title' => $copy->text( 'wizard_step_verify' ),
				'state' => 'export' === $operation ? 'external' : ( $verified ? 'complete' : 'pending' ),
				'note'  => $copy->text( 'wizard_verify_note' ),
			),
		);

		$base_url     = admin_url( 'admin.php?page=' . AdminOperatorScreen::DASHBOARD_SLUG );
		$advanced_url = add_query_arg( array( 'advanced' => '1' ), $base_url );
		if ( '' !== $job_id ) {
			$advanced_url = add_query_arg( array( 'clone_job_id' => $job_id ), $advanced_url );
		}
		$baseline_ready = true === ( $operator_status['baseline']['available'] ?? false );
		$checkpoint     = '' !== $job_id ? MigrationWizardNavigator::next( $job ) : null;
		$import_state   = 'import' === $operation && '' !== $job_id ? ( new ImportStateStore() )->get( $job_id ) : null;
		$auto_import    = is_array( $import_state )
			&& 'replace-production' === ( $import_state['destination_policy'] ?? '' )
			&& 0 < (int) ( $import_state['archive_bytes'] ?? 0 )
			&& in_array( (string) ( $import_state['status'] ?? '' ), array( 'preflight-ready', 'payload-verified' ), true );
		$payload_state  = 'import' === $operation && '' !== $job_id ? ( new ImportPayloadStateStore() )->get( $job_id ) : null;
		$show_upload    = 'import' === $operation && '' !== $job_id && ! is_array( $payload_state )
			&& ! in_array( (string) ( $job['status'] ?? '' ), array( 'failed-terminal', 'cancelled' ), true );
		$has_archive    = is_array( $import_state ) && 0 < (int) ( $import_state['archive_bytes'] ?? 0 );
		$anchor         = '' === $job_id && ! $baseline_ready
			? '#seo-geo-baseline-capture-form'
			: ( is_array( $checkpoint ) ? '#' . $checkpoint['anchor'] : '#seo-geo-clone-workflow' );
		$advanced_url   = $advanced_url . $anchor;
		$button_text    = $copy->text( 'wizard_start' );
		$batch          = null;

		if ( is_array( $checkpoint ) ) {
			if ( $checkpoint['done'] ) {
				$button_text = $copy->text( 'wizard_review_done' );
			} elseif ( $checkpoint['blocked'] ) {
				$button_text = $copy->text( 'wizard_review_blocked' );
			} else {
				$button_text = $copy->text( 'wizard_open_next' );
			}
			if ( 'active' === ( $job['status'] ?? '' ) && ! $checkpoint['blocked'] && ! $checkpoint['done'] ) {
				$batch = self::safe_batch_action( $checkpoint );
			}
		}
		?>
		<section class="seo-geo-migration-simple" aria-labelledby="seo-geo-simple-heading" style="margin:20px 0;padding:20px;background:#fff;border:1px solid #c3c4c7;">
			<h2 id="seo-geo-simple-heading" style="margin-top:0;"><?php echo esc_html( $copy->text( 'wizard_title' ) ); ?></h2>
			<p><?php echo esc_html( $copy->text( 'wizard_intro' ) ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:flex;flex-wrap:wrap;gap:8px;margin:12px 0;">
					<input type="hidden" name="action" value="<?php echo esc_attr( AdminCloneController::ACTION ); ?>">
					<?php wp_nonce_field( AdminCloneController::NONCE_ACTION ); ?>
					<button class="button button-primary button-hero" type="submit" name="clone_operation" value="export"><?php echo esc_html( $copy->text( 'wizard_primary_export' ) ); ?></button>
					<button class="button button-primary button-hero" type="submit" name="clone_operation" value="import"><?php echo esc_html( $copy->text( 'wizard_primary_import' ) ); ?></button>
			</form>
			<?php if ( 0 < count( $jobs ) ) : ?>
				<form method="get" action="<?php echo esc_url( admin_url( 'tools.php' ) ); ?>" style="margin:16px 0;">
					<input type="hidden" name="page" value="<?php echo esc_attr( AdminOperatorScreen::PAGE_SLUG ); ?>">
					<label for="seo-geo-simple-job"><strong><?php echo esc_html( $copy->text( 'wizard_saved' ) ); ?></strong></label>
					<select id="seo-geo-simple-job" name="clone_job_id" style="max-width:100%;width:460px;">
						<?php foreach ( $jobs as $saved ) : ?>
							<?php
							$id    = (string) $saved['job_id'];
							$kind  = $copy->text( 'wizard_job_' . str_replace( '-', '_', (string) $saved['operation'] ) );
							$state = $copy->text( 'wizard_state_' . str_replace( '-', '_', (string) $saved['status'] ) );
							$stamp = strtotime( (string) ( $saved['updated_at'] ?? '' ) );
							$when  = false !== $stamp ? wp_date( 'd/m H:i', $stamp ) : '';
							$label = sprintf( '%s · %s · %s · #%s', $kind, $state, $when, substr( $id, -8 ) );
							?>
							<option value="<?php echo esc_attr( $id ); ?>" <?php selected( $id, $job_id ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
					<button class="button" type="submit"><?php echo esc_html( $copy->text( 'wizard_view' ) ); ?></button>
				</form>
				<?php
				$counters = is_array( $job['counters'] ?? null ) ? $job['counters'] : array();
				$done     = (int) ( $counters['completed'] ?? 0 );
				$total    = $counters['total'] ?? null;
				?>
				<p><strong><?php echo esc_html( $copy->text( 'wizard_current_job' ) ); ?></strong>
					<?php echo esc_html( $copy->text( 'wizard_job_' . str_replace( '-', '_', $operation ) ) ); ?>
					· <?php echo esc_html( $copy->text( 'wizard_state_' . str_replace( '-', '_', (string) ( $job['status'] ?? '' ) ) ) ); ?>
					<?php if ( 'local-clone' === $operation ) : ?>
						· <strong><?php echo esc_html( $copy->text( 'wizard_batch' ) ); ?></strong>
						<?php echo esc_html( null === $total ? (string) $done : $done . ' / ' . (string) (int) $total ); ?>
					<?php endif; ?>
				</p>
				<?php if ( in_array( $job['status'] ?? '', array( 'failed-retryable', 'failed-terminal', 'paused' ), true ) ) : ?>
					<p class="notice notice-warning inline"><?php echo esc_html( $copy->text( 'wizard_attention' ) ); ?></p>
				<?php endif; ?>
			<?php else : ?>
				<p><?php echo esc_html( $copy->text( 'wizard_empty' ) ); ?></p>
			<?php endif; ?>
			<?php if ( $show_upload ) : ?>
				<section aria-labelledby="seo-geo-simple-import-heading" style="border:1px solid #c3c4c7;border-radius:4px;padding:16px;margin:16px 0;">
					<h3 id="seo-geo-simple-import-heading"><?php echo esc_html( $copy->text( 'wizard_primary_import' ) ); ?></h3>
					<p class="notice notice-warning inline" role="alert"><strong><?php echo esc_html( $copy->text( 'clone_import_external_backup_warning' ) ); ?></strong></p>
					<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="<?php echo esc_attr( AdminCloneImportController::ACTION ); ?>">
						<input type="hidden" name="clone_job_id" value="<?php echo esc_attr( $job_id ); ?>">
						<?php wp_nonce_field( AdminCloneImportController::NONCE_ACTION . ':' . $job_id ); ?>
						<p><label for="seo-geo-simple-import-package"><strong><?php echo esc_html( $copy->text( 'clone_import_upload_label' ) ); ?></strong></label></p>
						<input type="file" id="seo-geo-simple-import-package" name="clone_package" accept=".zip,application/zip" <?php echo $has_archive ? '' : 'required'; ?>>
						<?php if ( ! $has_archive ) : ?>
							<input type="hidden" name="import_destination_policy" value="replace-production">
						<?php endif; ?>
						<p class="description"><?php echo esc_html( $copy->text( 'clone_import_production_not_activated' ) ); ?></p>
						<?php submit_button( $copy->text( $has_archive ? 'clone_import_revalidate' : 'clone_import_validate' ), 'primary', 'submit', false ); ?>
					</form>
				</section>
			<?php endif; ?>
			<details style="margin:12px 0;">
				<summary style="cursor:pointer;font-weight:600;"><?php echo esc_html( $copy->text( 'wizard_technical_details' ) ); ?></summary>
			<ol style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;padding-left:0;list-style:none;">
				<?php foreach ( $steps as $step ) : ?>
					<li style="padding:14px;border:1px solid #c3c4c7;border-radius:4px;">
						<strong><?php echo esc_html( $step['title'] ); ?></strong>
						<p style="margin:6px 0;"><small>
							<?php
							echo esc_html(
								match ( $step['state'] ) {
									'complete' => $copy->text( 'wizard_verified' ),
									'external' => $copy->text( 'wizard_external' ),
									default    => $copy->text( 'wizard_pending' ),
								}
							);
							?>
						</small></p>
						<p style="margin:0;"><?php echo esc_html( $step['note'] ); ?></p>
					</li>
				<?php endforeach; ?>
			</ol>
			<?php if ( is_array( $checkpoint ) ) : ?>
				<div role="status" style="padding:12px;margin:12px 0;border-left:4px solid #2271b1;background:#f6f7f7;">
					<strong><?php echo esc_html( $copy->text( 'wizard_next' ) ); ?></strong>
					<?php echo esc_html( $copy->text( $checkpoint['label_key'] ) ); ?>
					<p style="margin:4px 0 0;">
						<?php
						// translators: 1: Number of completed checkpoints, 2: Total checkpoint count.
						echo esc_html( sprintf( $copy->text( 'wizard_progress' ), $checkpoint['completed'], $checkpoint['total'] ) );
						?>
						<?php if ( $checkpoint['blocked'] ) : ?>
							<?php echo esc_html( $copy->text( 'wizard_blocked_note' ) ); ?>
						<?php endif; ?>
					</p>
				</div>
			<?php endif; ?>
			</details>
			<?php if ( $auto_import ) : ?>
				<?php AdminOperatorScreen::render_auto_import_panel( $job_id ); ?>
			<?php elseif ( 'export' === $operation ) : ?>
				<?php $auto = AdminAutoExportController::snapshot( $job ); ?>
				<div
					data-seo-geo-auto-export
					data-job-id="<?php echo esc_attr( $job_id ); ?>"
					data-action="<?php echo esc_attr( AdminAutoExportController::ACTION ); ?>"
					data-nonce="<?php echo esc_attr( wp_create_nonce( AdminAutoExportController::NONCE_ACTION . ':' . $job_id ) ); ?>"
					data-endpoint="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>"
					data-initial-progress="<?php echo esc_attr( (string) wp_json_encode( $auto ) ); ?>"
					data-msg-start="<?php echo esc_attr( $copy->text( 'wizard_auto_start_note' ) ); ?>"
					data-msg-running="<?php echo esc_attr( $copy->text( 'wizard_auto_running' ) ); ?>"
					data-msg-pausing="<?php echo esc_attr( $copy->text( 'wizard_auto_pausing' ) ); ?>"
					data-msg-paused="<?php echo esc_attr( $copy->text( 'wizard_auto_paused' ) ); ?>"
					data-msg-ready="<?php echo esc_attr( $copy->text( 'wizard_auto_ready' ) ); ?>"
					data-msg-blocked="<?php echo esc_attr( $copy->text( 'wizard_auto_blocked' ) ); ?>"
					data-msg-network="<?php echo esc_attr( $copy->text( 'wizard_auto_network' ) ); ?>"
					data-msg-limit="<?php echo esc_attr( $copy->text( 'wizard_auto_limit' ) ); ?>"
					data-msg-files="<?php echo esc_attr( $copy->text( 'wizard_auto_files' ) ); ?>"
					data-msg-rows="<?php echo esc_attr( $copy->text( 'wizard_auto_rows' ) ); ?>"
					style="margin:16px 0;padding:16px;border:1px solid #c3c4c7;border-radius:4px;"
				>
					<p><strong><?php echo esc_html( $copy->text( 'wizard_auto_heading' ) ); ?></strong></p>
					<progress max="100" value="<?php echo esc_attr( (string) $auto['percent'] ); ?>" data-auto-progress style="width:100%;height:18px;" aria-label="<?php echo esc_attr( $copy->text( 'wizard_auto_heading' ) ); ?>"></progress>
					<p>
						<span data-auto-label><?php echo esc_html( $auto['label'] ); ?></span>
						<span aria-hidden="true"> · </span><span data-auto-stage><?php echo esc_html( (string) $auto['completed'] . ' / ' . (string) $auto['steps'] ); ?></span>
						<span aria-hidden="true"> · </span><span data-auto-processed><?php echo esc_html( (string) $auto['processed'] ); ?></span>
					</p>
					<p role="status" aria-live="polite" data-auto-status><?php echo esc_html( $copy->text( 'wizard_auto_start_note' ) ); ?></p>
					<p>
						<button type="button" class="button button-primary" data-auto-run <?php disabled( 'running' !== $auto['status'] ); ?>><?php echo esc_html( $copy->text( 'wizard_auto_start' ) ); ?></button>
						<button type="button" class="button" data-auto-pause disabled><?php echo esc_html( $copy->text( 'wizard_auto_pause' ) ); ?></button>
					</p>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" data-auto-download <?php echo 'ready' === $auto['status'] ? '' : 'hidden'; ?>>
						<input type="hidden" name="action" value="<?php echo esc_attr( AdminCloneDeliveryController::DOWNLOAD_ACTION ); ?>">
						<input type="hidden" name="clone_job_id" value="<?php echo esc_attr( $job_id ); ?>">
						<?php wp_nonce_field( AdminCloneDeliveryController::NONCE_ACTION . ':download:' . $job_id ); ?>
						<button class="button button-primary" type="submit"><?php echo esc_html( $copy->text( 'wizard_auto_download' ) ); ?></button>
					</form>
				</div>
			<?php elseif ( is_array( $batch ) ) : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin:12px 0;">
					<input type="hidden" name="action" value="<?php echo esc_attr( $batch['action'] ); ?>">
					<input type="hidden" name="clone_job_id" value="<?php echo esc_attr( $job_id ); ?>">
					<?php wp_nonce_field( $batch['nonce'] . ':' . $job_id ); ?>
					<?php foreach ( $batch['fields'] as $field => $value ) : ?>
						<input type="hidden" name="<?php echo esc_attr( $field ); ?>" value="<?php echo esc_attr( (string) $value ); ?>">
					<?php endforeach; ?>
					<button type="submit" class="button button-primary"><?php echo esc_html( $copy->text( 'wizard_safe_batch' ) ); ?></button>
				</form>
			<?php endif; ?>
			<p>
				<?php if ( ! $auto_import ) : ?>
					<a class="button" href="<?php echo esc_url( $advanced_url ); ?>"><?php echo esc_html( $button_text ); ?></a>
				<?php endif; ?>
			</p>
			<p class="description"><?php echo esc_html( $copy->text( 'wizard_cutover_notice' ) ); ?></p>
		</section>
		<?php
	}

	/**
	 * Return the existing controller route for one explicitly requested safe batch.
	 *
	 * @param array<string,mixed> $checkpoint Navigation checkpoint.
	 * @return array{action:string,nonce:string,fields:array<string,int>}|null
	 */
	public static function safe_batch_action( array $checkpoint ): ?array {
		return match ( $checkpoint['anchor'] ?? '' ) {
			'seo-geo-guide-clone_inventory' => array(
				'action' => AdminCloneInventoryController::ACTION,
				'nonce'  => AdminCloneInventoryController::NONCE_ACTION,
				'fields' => array( 'inventory_batch_size' => 100 ),
			),
			'seo-geo-guide-clone_database_export' => array(
				'action' => AdminCloneDatabaseExportController::ACTION,
				'nonce'  => AdminCloneDatabaseExportController::NONCE_ACTION,
				'fields' => array( 'database_batch_rows' => 100 ),
			),
			'seo-geo-guide-clone_file_export' => array(
				'action' => AdminCloneFileExportController::ACTION,
				'nonce'  => AdminCloneFileExportController::NONCE_ACTION,
				'fields' => array(
					'file_batch_size'      => 25,
					'file_batch_megabytes' => 8,
				),
			),
			default => null,
		};
	}

	/**
	 * Keep technical output visible after explicit navigation or an action result.
	 */
	public static function advanced_open(): bool {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only visibility toggle.
		if ( isset( $_GET['advanced'] ) && '1' === $_GET['advanced'] ) {
			return true;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only opens HTML details on results.
		foreach ( array_keys( $_GET ) as $key ) {
			if ( is_string( $key ) && 0 === strpos( $key, 'seo_geo_' ) ) {
				return true;
			}
		}
		return false;
	}
}
