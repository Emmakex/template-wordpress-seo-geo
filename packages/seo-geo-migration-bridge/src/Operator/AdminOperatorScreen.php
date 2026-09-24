<?php
/**
 * Migration Bridge operator administration screen.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge\Operator;

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

			<h2><?php echo esc_html( $this->copy->text( 'overview_heading' ) ); ?></h2>
			<table class="widefat striped" role="presentation">
				<tbody>
					<?php $this->render_overview_row( 'label_baseline', $this->baseline_label_key( $status ) ); ?>
					<?php $this->render_overview_row( 'label_dependency_plan', $this->dependency_label_key( $status ) ); ?>
					<?php $this->render_overview_row( 'label_cutover', $this->cutover_label_key( $status ) ); ?>
					<?php $this->render_overview_row( 'label_final_report', $this->report_label_key( $status ) ); ?>
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

			<h2><?php echo esc_html( $this->copy->text( 'review_heading' ) ); ?></h2>
			<dl>
				<dt><?php echo esc_html( $this->copy->text( 'label_blocking_review' ) ); ?></dt>
				<dd><?php echo esc_html( (string) (int) ( $status['final_report']['blocking_review_count'] ?? 0 ) ); ?></dd>
				<dt><?php echo esc_html( $this->copy->text( 'label_advisory_review' ) ); ?></dt>
				<dd><?php echo esc_html( (string) (int) ( $status['final_report']['advisory_review_count'] ?? 0 ) ); ?></dd>
				<dt><?php echo esc_html( $this->copy->text( 'label_bridge_disposition' ) ); ?></dt>
				<dd><?php echo esc_html( $this->disposition_label( $status ) ); ?></dd>
			</dl>

			<h2><?php echo esc_html( $this->copy->text( 'next_step_heading' ) ); ?></h2>
			<p class="notice notice-info inline">
				<?php echo esc_html( $this->next_step_text( $status ) ); ?>
			</p>
			<?php if ( true !== ( $status['baseline']['available'] ?? false ) ) : ?>
				<?php $this->render_baseline_capture_form(); ?>
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
		$status = isset( $_GET['seo_geo_baseline'] )
			? sanitize_key( wp_unslash( $_GET['seo_geo_baseline'] ) )
			: '';

		$key = match ( $status ) {
			'success' => 'baseline_capture_success',
			'exists'  => 'baseline_capture_exists',
			'error'   => 'baseline_capture_error',
			default   => null,
		};

		if ( null === $key ) {
			return;
		}

		$class = 'error' === $status ? 'notice notice-error' : 'notice notice-success';
		?>
		<div class="<?php echo esc_attr( $class ); ?> is-dismissible">
			<p><?php echo esc_html( $this->copy->text( $key ) ); ?></p>
		</div>
		<?php
	}

	/**
	 * Render the explicit same-origin public baseline capture action.
	 */
	private function render_baseline_capture_form(): void {
		?>
		<div class="seo-geo-migration-baseline-action">
			<p><?php echo esc_html( $this->copy->text( 'baseline_capture_help' ) ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="<?php echo esc_attr( AdminBaselineCaptureController::ACTION ); ?>">
				<?php wp_nonce_field( AdminBaselineCaptureController::NONCE_ACTION ); ?>
				<?php submit_button( $this->copy->text( 'baseline_capture_button' ), 'primary', 'submit', false ); ?>
			</form>
		</div>
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
