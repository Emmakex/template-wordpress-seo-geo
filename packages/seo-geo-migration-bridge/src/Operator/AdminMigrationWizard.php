<?php
/**
 * Read-only, four-step entry point for the existing Migration Bridge engines.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge\Operator;

use SeoGeo\MigrationBridge\Clone\CloneInventoryStore;
use SeoGeo\MigrationBridge\Clone\CloneJobStore;
use SeoGeo\MigrationBridge\Clone\ImportFilePromotionStateStore;
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

		$base_url     = admin_url( 'tools.php?page=' . AdminOperatorScreen::PAGE_SLUG );
		$advanced_url = add_query_arg( array( 'advanced' => '1' ), $base_url );
		if ( '' !== $job_id ) {
			$advanced_url = add_query_arg( array( 'clone_job_id' => $job_id ), $advanced_url );
		}
		$baseline_ready = true === ( $operator_status['baseline']['available'] ?? false );
		$checkpoint     = '' !== $job_id ? MigrationWizardNavigator::next( $job ) : null;
		$anchor         = ! $baseline_ready
			? '#seo-geo-baseline-capture-form'
			: ( is_array( $checkpoint ) ? '#' . $checkpoint['anchor'] : '#seo-geo-clone-workflow' );
		$advanced_url   = $advanced_url . $anchor;
		?>
		<section class="seo-geo-migration-simple" aria-labelledby="seo-geo-simple-heading" style="margin:20px 0;padding:20px;background:#fff;border:1px solid #c3c4c7;">
			<h2 id="seo-geo-simple-heading" style="margin-top:0;"><?php echo esc_html$copy->text( 'wizard_title' ); ?></h2>
			<p><?php echo esc_html$copy->text( 'wizard_intro' ); ?></p>
			<?php if ( 0 < count( $jobs ) ) : ?>
				<form method="get" action="<?php echo esc_url( admin_url( 'tools.php' ) ); ?>" style="margin:16px 0;">
					<input type="hidden" name="page" value="<?php echo esc_attr( AdminOperatorScreen::PAGE_SLUG ); ?>">
					<label for="seo-geo-simple-job"><strong><?php echo esc_html$copy->text( 'wizard_saved' ); ?></strong></label>
					<select id="seo-geo-simple-job" name="clone_job_id">
						<?php foreach ( $jobs as $saved ) : ?>
							<?php
							$id    = (string) $saved['job_id'];
							$label = sprintf( '%s · %s · %s', $id, (string) $saved['operation'], (string) $saved['status'] );
							?>
							<option value="<?php echo esc_attr( $id ); ?>" <?php selected( $id, $job_id ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
					<button class="button" type="submit"><?php echo esc_html$copy->text( 'wizard_view' ); ?></button>
				</form>
				<?php
				$counters = is_array( $job['counters'] ?? null ) ? $job['counters'] : array();
				$done     = (int) ( $counters['completed'] ?? 0 );
				$total    = $counters['total'] ?? null;
				?>
				<p><strong><?php echo esc_html$copy->text( 'wizard_phase' ); ?></strong>
					<code><?php echo esc_html( (string) ( $job['phase'] ?? '' ) ); ?></code>
					· <strong><?php echo esc_html$copy->text( 'wizard_status' ); ?></strong>
					<code><?php echo esc_html( (string) ( $job['status'] ?? '' ) ); ?></code>
					· <strong><?php echo esc_html$copy->text( 'wizard_batch' ); ?></strong>
					<?php echo esc_html( null === $total ? (string) $done : $done . ' / ' . (string) (int) $total ); ?>
				</p>
				<?php if ( in_array( $job['status'] ?? '', array( 'failed-retryable', 'failed-terminal', 'paused' ), true ) ) : ?>
					<p class="notice notice-warning inline"><?php echo esc_html$copy->text( 'wizard_attention' ); ?></p>
				<?php endif; ?>
			<?php else : ?>
				<p><?php echo esc_html$copy->text( 'wizard_empty' ); ?></p>
			<?php endif; ?>
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
					<strong><?php echo esc_html$copy->text( 'wizard_next' ); ?></strong>
					<?php echo esc_html( $copy->text( $checkpoint['label_key'] ) ); ?>
					<p style="margin:4px 0 0;">
						<?php
						// translators: 1: Number of completed checkpoints, 2: Total checkpoint count.
						echo esc_html( sprintf( $copy->text( 'wizard_progress' ), $checkpoint['completed'], $checkpoint['total'] ) );
						?>
						<?php if ( $checkpoint['blocked'] ) : ?>
							<?php echo esc_html$copy->text( 'wizard_blocked_note' ); ?>
						<?php endif; ?>
					</p>
				</div>
			<?php endif; ?>
			<p>
				<a class="button button-primary" href="<?php echo esc_url( $advanced_url ); ?>"><?php echo esc_html( '' === $job_id ? $copy->text( 'wizard_start' ) : ( true === ( $checkpoint['done'] ?? false ) ? $copy->text( 'wizard_review_done' ) : ( true === ( $checkpoint['blocked'] ?? false ) ? $copy->text( 'wizard_review_blocked' ) : $copy->text( 'wizard_open_next' ) ) ) ); ?></a>
				<a class="button" href="<?php echo esc_url( admin_url( 'tools.php?page=seo-geo-reset-rebuild' ) ); ?>"><?php echo esc_html$copy->text( 'wizard_rescue' ); ?></a>
			</p>
			<p class="description"><?php echo esc_html$copy->text( 'wizard_cutover_notice' ); ?></p>
		</section>
		<?php
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
