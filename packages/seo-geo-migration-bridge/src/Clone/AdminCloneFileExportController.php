<?php
/**
 * Portable Clone Engine file-export administration endpoint.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge\Clone;

use SeoGeo\MigrationBridge\Operator\AdminOperatorScreen;

/**
 * Advances resumable file-export batches in manual or one-click automatic mode.
 */
final class AdminCloneFileExportController {
	public const ACTION       = 'seo_geo_migration_clone_file_export';
	public const NONCE_ACTION = 'seo_geo_migration_clone_file_export';

	private const MODE_BATCH          = 'batch';
	private const MODE_AUTO           = 'auto';
	private const AUTO_BATCH_FILES    = 100;
	private const AUTO_BATCH_MEGABYTES = 16;
	private const MAX_AUTO_CYCLES     = 500;

	/**
	 * Resumable file exporter.
	 *
	 * @var FileExporter
	 */
	private FileExporter $exporter;

	/**
	 * Construct controller.
	 *
	 * @param FileExporter|null $exporter Optional file exporter.
	 */
	public function __construct( ?FileExporter $exporter = null ) {
		$this->exporter = $exporter ?? new FileExporter();
	}

	/** Register authenticated endpoint. */
	public function boot(): void {
		add_action( 'admin_post_' . self::ACTION, array( $this, 'handle' ) );
	}

	/** Advance one file-export request. */
	public function handle(): never {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die(
				esc_html__( 'Administrator capability is required to export Portable Clone files.', 'seo-geo-migration-bridge' ),
				'',
				array( 'response' => 403 )
			);
		}

		$job_id = isset( $_POST['clone_job_id'] )
			? sanitize_text_field( wp_unslash( $_POST['clone_job_id'] ) )
			: '';
		check_admin_referer( self::NONCE_ACTION . ':' . $job_id );

		$mode = isset( $_POST['file_export_mode'] )
			? sanitize_key( wp_unslash( $_POST['file_export_mode'] ) )
			: self::MODE_BATCH;
		if ( ! in_array( $mode, array( self::MODE_BATCH, self::MODE_AUTO ), true ) ) {
			$mode = self::MODE_BATCH;
		}

		$auto_cycle = isset( $_POST['file_export_auto_cycle'] )
			? min( self::MAX_AUTO_CYCLES, absint( sanitize_text_field( wp_unslash( $_POST['file_export_auto_cycle'] ) ) ) )
			: 0;

		if ( self::MODE_AUTO === $mode ) {
			$batch_files     = self::AUTO_BATCH_FILES;
			$batch_megabytes = self::AUTO_BATCH_MEGABYTES;
			$auto_cycle      = min( self::MAX_AUTO_CYCLES, $auto_cycle + 1 );
		} else {
			$batch_files     = isset( $_POST['file_batch_size'] )
				? absint( sanitize_text_field( wp_unslash( $_POST['file_batch_size'] ) ) )
				: FileExporter::DEFAULT_BATCH_FILES;
			$batch_megabytes = isset( $_POST['file_batch_megabytes'] )
				? absint( sanitize_text_field( wp_unslash( $_POST['file_batch_megabytes'] ) ) )
				: 8;
		}
		$batch_bytes = $batch_megabytes * 1024 * 1024;

		$result = $this->exporter->advance( $job_id, $batch_files, $batch_bytes );
		if ( ! is_array( $result ) ) {
			wp_die(
				esc_html__( 'Portable Clone file export could not be advanced.', 'seo-geo-migration-bridge' ),
				'',
				array( 'response' => 400 )
			);
		}

		$status = (string) ( $result['status'] ?? 'running' );
		if ( self::MODE_AUTO === $mode && 'running' === $status && self::MAX_AUTO_CYCLES <= $auto_cycle ) {
			$status = 'paused';
		}

		$redirect = add_query_arg(
			array(
				'seo_geo_clone_file_export' => $status,
				'clone_job_id'              => $job_id,
				'file_export_mode'          => $mode,
				'file_export_auto_cycle'    => $auto_cycle,
				'file_batch_size'           => $batch_files,
				'file_batch_megabytes'      => $batch_megabytes,
			),
			admin_url( 'tools.php?page=' . AdminOperatorScreen::PAGE_SLUG )
		);
		wp_safe_redirect( $redirect );
		exit;
	}
}
