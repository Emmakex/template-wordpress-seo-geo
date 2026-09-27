<?php
/**
 * Portable Clone private local package handoff administration endpoint.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge\Clone;

use SeoGeo\MigrationBridge\Operator\AdminOperatorScreen;

/**
 * Capability/nonce-gated same-server package handoff batches.
 */
final class AdminCloneLocalPackageHandoffController {
	public const ACTION       = 'seo_geo_migration_clone_local_package_handoff_advance';
	public const NONCE_ACTION = 'seo_geo_migration_clone_local_package_handoff';

	public const MAX_AUTO_CYCLES = 200;

	private const MODE_BATCH           = 'batch';
	private const MODE_AUTO            = 'auto';
	private const AUTO_BATCH_FILES     = 500;
	private const AUTO_BATCH_MEGABYTES = 32;

	/**
	 * Private local package handoff service.
	 *
	 * @var LocalClonePackageHandoff
	 */
	private LocalClonePackageHandoff $handoff;

	/**
	 * Underlying resumable delivery state used for progress/stall detection.
	 *
	 * @var DeliveryStateStore
	 */
	private DeliveryStateStore $delivery_state;

	/**
	 * Construct controller.
	 *
	 * @param LocalClonePackageHandoff|null $handoff        Optional handoff service.
	 * @param DeliveryStateStore|null       $delivery_state Optional delivery state.
	 */
	public function __construct(
		?LocalClonePackageHandoff $handoff = null,
		?DeliveryStateStore $delivery_state = null
	) {
		$this->handoff        = $handoff ?? new LocalClonePackageHandoff();
		$this->delivery_state = $delivery_state ?? new DeliveryStateStore();
	}

	/**
	 * Register authenticated-only endpoint.
	 */
	public function boot(): void {
		add_action( 'admin_post_' . self::ACTION, array( $this, 'advance' ) );
	}

	/**
	 * Advance one bounded private archive handoff batch.
	 */
	public function advance(): never {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die(
				esc_html__( 'Administrator capability is required to prepare the local clone package handoff.', 'seo-geo-migration-bridge' ),
				'',
				array( 'response' => 403 )
			);
		}

		$job_id = isset( $_POST['clone_job_id'] )
			? sanitize_text_field( wp_unslash( $_POST['clone_job_id'] ) )
			: '';
		check_admin_referer( self::NONCE_ACTION . ':' . $job_id );

		$existing = $this->handoff->snapshot( $job_id );
		if ( ! is_array( $existing ) ) {
			$confirmed = isset( $_POST['local_clone_package_handoff_confirm'] )
				&& '1' === sanitize_text_field( wp_unslash( $_POST['local_clone_package_handoff_confirm'] ) );
			if ( ! $confirmed ) {
				wp_die(
					esc_html__( 'Explicit confirmation is required before building the private same-server handoff archive.', 'seo-geo-migration-bridge' ),
					'',
					array( 'response' => 400 )
				);
			}
		}

		$mode = isset( $_POST['handoff_mode'] )
			? sanitize_key( wp_unslash( $_POST['handoff_mode'] ) )
			: self::MODE_BATCH;
		if ( ! in_array( $mode, array( self::MODE_BATCH, self::MODE_AUTO ), true ) ) {
			$mode = self::MODE_BATCH;
		}

		$auto_cycle = isset( $_POST['handoff_auto_cycle'] )
			? min( self::MAX_AUTO_CYCLES, absint( sanitize_text_field( wp_unslash( $_POST['handoff_auto_cycle'] ) ) ) )
			: 0;

		if ( self::MODE_AUTO === $mode ) {
			$batch_files = self::AUTO_BATCH_FILES;
			$batch_mb    = self::AUTO_BATCH_MEGABYTES;
			$auto_cycle  = min( self::MAX_AUTO_CYCLES, $auto_cycle + 1 );
		} else {
			$batch_files = isset( $_POST['handoff_batch_files'] )
				? absint( wp_unslash( $_POST['handoff_batch_files'] ) )
				: LocalClonePackageHandoff::DEFAULT_BATCH_FILES;
			$batch_mb    = isset( $_POST['handoff_batch_megabytes'] )
				? absint( wp_unslash( $_POST['handoff_batch_megabytes'] ) )
				: 16;
		}

		$before = $this->delivery_state->get( $job_id );
		$result = $this->handoff->advance( $job_id, $batch_files, $batch_mb * 1024 * 1024 );
		if ( ! is_array( $result ) ) {
			wp_die(
				esc_html__( 'The private local clone package handoff could not be started from the verified sandbox runtime.', 'seo-geo-migration-bridge' ),
				'',
				array( 'response' => 400 )
			);
		}

		$status = (string) ( $result['status'] ?? 'blocked' );
		$after  = $this->delivery_state->get( $job_id );
		if (
			self::MODE_AUTO === $mode
			&& 'building' === $status
			&& is_array( $before )
			&& is_array( $after )
			&& hash_equals( $this->progress_signature( $before ), $this->progress_signature( $after ) )
		) {
			$status = 'stalled';
		} elseif ( self::MODE_AUTO === $mode && 'building' === $status && self::MAX_AUTO_CYCLES <= $auto_cycle ) {
			$status = 'paused';
		}

		if ( ! in_array( $status, array( 'building', 'ready', 'blocked', 'paused', 'stalled' ), true ) ) {
			$status = 'blocked';
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'seo_geo_clone_local_package_handoff' => $status,
					'clone_job_id'                        => $job_id,
					'handoff_mode'                        => $mode,
					'handoff_auto_cycle'                  => $auto_cycle,
					'handoff_batch_files'                 => $batch_files,
					'handoff_batch_megabytes'             => $batch_mb,
				),
				admin_url( 'tools.php?page=' . AdminOperatorScreen::PAGE_SLUG )
			)
		);
		exit;
	}

	/**
	 * Build a bounded progress signature for automatic stall detection.
	 *
	 * @param array<string,mixed> $state Delivery state.
	 */
	private function progress_signature( array $state ): string {
		$pending = is_array( $state['pending_dirs'] ?? null ) ? array_values( $state['pending_dirs'] ) : array();
		$payload = array(
			'directory_active'     => true === ( $state['directory_active'] ?? false ),
			'current_dir'          => (string) ( $state['current_dir'] ?? '' ),
			'after_name'           => (string) ( $state['after_name'] ?? '' ),
			'pending_count'        => count( $pending ),
			'archive_file_count'   => (int) ( $state['archive_file_count'] ?? 0 ),
			'archive_source_bytes' => (int) ( $state['archive_source_bytes'] ?? 0 ),
			'verified_file_count'  => (int) ( $state['verified_file_count'] ?? 0 ),
			'verified_byte_count'  => (int) ( $state['verified_byte_count'] ?? 0 ),
		);
		$json    = wp_json_encode( $payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );

		return hash( 'sha256', is_string( $json ) ? $json : '' );
	}
}
