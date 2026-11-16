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
	 */
	public static function render( array $operator_status ): void {
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

		$analyzed       = true === ( $operator_status['baseline']['available'] ?? false )
			|| 'complete' === ( $inventory['status'] ?? '' );
		$exported       = 'complete' === ( $package['status'] ?? '' ) && 'complete' === ( $package['stage'] ?? '' );
		$imported       = 'verified' === ( $promotion['status'] ?? '' )
			&& true === ( $promotion['handoff_ready'] ?? false );
		$verified       = 'ready' === ( $handoff['status'] ?? '' )
			&& true === ( $handoff['handoff_ready'] ?? false );
		$is_import_only = 'import' === $operation;

		$steps = array(
			array(
				'title' => __( '1. Analyze', 'seo-geo-migration-bridge' ),
				'state' => $analyzed ? 'complete' : 'pending',
				'note'  => __( 'Public baseline or completed source inventory.', 'seo-geo-migration-bridge' ),
			),
			array(
				'title' => __( '2. Export', 'seo-geo-migration-bridge' ),
				'state' => $is_import_only ? 'external' : ( $exported ? 'complete' : 'pending' ),
				'note'  => __( 'Database, files and verified package; resumable by job.', 'seo-geo-migration-bridge' ),
			),
			array(
				'title' => __( '3. Import', 'seo-geo-migration-bridge' ),
				'state' => 'export' === $operation ? 'external' : ( $imported ? 'complete' : 'pending' ),
				'note'  => __( 'Destination checks, restoration and safe activation.', 'seo-geo-migration-bridge' ),
			),
			array(
				'title' => __( '4. Verify', 'seo-geo-migration-bridge' ),
				'state' => $verified ? 'complete' : 'pending',
				'note'  => __( 'Final handoff, URL/SEO checks and recovery evidence.', 'seo-geo-migration-bridge' ),
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
			<h2 id="seo-geo-simple-heading" style="margin-top:0;"><?php echo esc_html__( 'WordPress migration: simple view', 'seo-geo-migration-bridge' ); ?></h2>
			<p><?php echo esc_html__( 'Four guided stages. Existing migration checks and confirmations remain mandatory. Opening a job never changes the website.', 'seo-geo-migration-bridge' ); ?></p>
			<?php if ( 0 < count( $jobs ) ) : ?>
				<form method="get" action="<?php echo esc_url( admin_url( 'tools.php' ) ); ?>" style="margin:16px 0;">
					<input type="hidden" name="page" value="<?php echo esc_attr( AdminOperatorScreen::PAGE_SLUG ); ?>">
					<label for="seo-geo-simple-job"><strong><?php echo esc_html__( 'Saved migration', 'seo-geo-migration-bridge' ); ?></strong></label>
					<select id="seo-geo-simple-job" name="clone_job_id">
						<?php foreach ( $jobs as $saved ) : ?>
							<?php
							$id    = (string) $saved['job_id'];
							$label = sprintf( '%s · %s · %s', $id, (string) $saved['operation'], (string) $saved['status'] );
							?>
							<option value="<?php echo esc_attr( $id ); ?>" <?php selected( $id, $job_id ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
					<button class="button" type="submit"><?php echo esc_html__( 'View', 'seo-geo-migration-bridge' ); ?></button>
				</form>
				<?php
				$counters = is_array( $job['counters'] ?? null ) ? $job['counters'] : array();
				$done     = (int) ( $counters['completed'] ?? 0 );
				$total    = $counters['total'] ?? null;
				?>
				<p><strong><?php echo esc_html__( 'Recorded job stage:', 'seo-geo-migration-bridge' ); ?></strong>
					<code><?php echo esc_html( (string) ( $job['phase'] ?? '' ) ); ?></code>
					· <strong><?php echo esc_html__( 'Status:', 'seo-geo-migration-bridge' ); ?></strong>
					<code><?php echo esc_html( (string) ( $job['status'] ?? '' ) ); ?></code>
					· <strong><?php echo esc_html__( 'Batch progress:', 'seo-geo-migration-bridge' ); ?></strong>
					<?php echo esc_html( null === $total ? (string) $done : $done . ' / ' . (string) (int) $total ); ?>
				</p>
				<?php if ( in_array( $job['status'] ?? '', array( 'failed-retryable', 'failed-terminal', 'paused' ), true ) ) : ?>
					<p class="notice notice-warning inline"><?php echo esc_html__( 'This job needs operator attention; no automatic retry is performed.', 'seo-geo-migration-bridge' ); ?></p>
				<?php endif; ?>
			<?php else : ?>
				<p><?php echo esc_html__( 'No saved migration yet. Open the actions below to capture a baseline and create a job.', 'seo-geo-migration-bridge' ); ?></p>
			<?php endif; ?>
			<ol style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;padding-left:0;list-style:none;">
				<?php foreach ( $steps as $step ) : ?>
					<li style="padding:14px;border:1px solid #c3c4c7;border-radius:4px;">
						<strong><?php echo esc_html( $step['title'] ); ?></strong>
						<p style="margin:6px 0;"><small>
							<?php
							echo esc_html(
								match ( $step['state'] ) {
									'complete' => __( 'Verified stage evidence', 'seo-geo-migration-bridge' ),
									'external' => __( 'Handled in a separate job', 'seo-geo-migration-bridge' ),
									default    => __( 'Not yet verified', 'seo-geo-migration-bridge' ),
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
					<strong><?php echo esc_html__( 'Next checkpoint:', 'seo-geo-migration-bridge' ); ?></strong>
					<?php echo esc_html( $checkpoint['label'] ); ?>
					<p style="margin:4px 0 0;">
						<?php echo esc_html( sprintf( __( '%1$d of %2$d technical checkpoints confirmed.', 'seo-geo-migration-bridge' ), $checkpoint['completed'], $checkpoint['total'] ) ); ?>
						<?php if ( $checkpoint['blocked'] ) : ?>
							<?php echo esc_html__( 'Inspect the blocker; no retry will start automatically.', 'seo-geo-migration-bridge' ); ?>
						<?php endif; ?>
					</p>
				</div>
			<?php endif; ?>
			<p>
				<a class="button button-primary" href="<?php echo esc_url( $advanced_url ); ?>"><?php echo esc_html( '' === $job_id ? __( 'Start with guided actions', 'seo-geo-migration-bridge' ) : ( true === ( $checkpoint['done'] ?? false ) ? __( 'Review completed migration', 'seo-geo-migration-bridge' ) : ( true === ( $checkpoint['blocked'] ?? false ) ? __( 'Review blocked checkpoint', 'seo-geo-migration-bridge' ) : __( 'Open next checkpoint', 'seo-geo-migration-bridge' ) ) ) ); ?></a>
				<a class="button" href="<?php echo esc_url( admin_url( 'tools.php?page=seo-geo-reset-rebuild' ) ); ?>"><?php echo esc_html__( 'Rescue for redesign', 'seo-geo-migration-bridge' ); ?></a>
			</p>
			<p class="description"><?php echo esc_html__( 'A clone or import does not publish a new domain. Production cutover and any destructive action require separate safeguards and approval.', 'seo-geo-migration-bridge' ); ?></p>
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
