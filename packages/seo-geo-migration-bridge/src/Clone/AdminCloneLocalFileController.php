<?php
/**
 * Portable Clone local file staging administration endpoint.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge\Clone;

use SeoGeo\MigrationBridge\Operator\AdminOperatorScreen;

/**
 * Capability/nonce-gated local-clone file staging controller.
 */
final class AdminCloneLocalFileController {
	public const ACTION       = 'seo_geo_migration_clone_local_files';
	public const NONCE_ACTION = 'seo_geo_migration_clone_local_files';

	/**
	 * Local file restorer.
	 *
	 * @var LocalCloneFileRestorer
	 */
	private LocalCloneFileRestorer $restorer;

	/**
	 * Construct controller.
	 *
	 * @param LocalCloneFileRestorer|null $restorer Optional local file restorer.
	 */
	public function __construct( ?LocalCloneFileRestorer $restorer = null ) {
		$this->restorer = $restorer ?? new LocalCloneFileRestorer();
	}

	/**
	 * Register authenticated endpoint.
	 */
	public function boot(): void {
		add_action( 'admin_post_' . self::ACTION, array( $this, 'handle' ) );
	}

	/**
	 * Advance one bounded private file staging batch.
	 */
	public function handle(): never {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die(
				esc_html__( 'Administrator capability is required to stage the local clone files.', 'seo-geo-migration-bridge' ),
				'',
				array( 'response' => 403 )
			);
		}

		$job_id = isset( $_POST['clone_job_id'] )
			? sanitize_text_field( wp_unslash( $_POST['clone_job_id'] ) )
			: '';
		check_admin_referer( self::NONCE_ACTION . ':' . $job_id );

		$existing = $this->restorer->snapshot( $job_id );
		if ( ! is_array( $existing ) ) {
			$confirmed = isset( $_POST['local_clone_files_confirm'] )
				&& '1' = sanitize_text_field( wp_unslash( $_POST['local_clone_files_confirm'] ) );
			if ( ! $confirmed ) {
				wp_die(
					esc_html__( 'Explicit confirmation is required before copying verified client files into private job staging.', 'seo-geo-migration-bridge' ),
					'',
					array( 'response' => 400 )
				);
			}
		}

		$batch_files = isset( $_POST['local_clone_files_batch_size'] )
			? absint( sanitize_text_field( wp_unslash( $_POST['local_clone_files_batch_size'] ) ) )
			: ImportFileRestorer::DEFAULT_BATCH_FILES;
		$batch_megabytes = isset( $_POST['local_clone_files_batch_megabytes'] )
			? absint( sanitize_text_field( wp_unslash( $_POST['local_clone_files_batch_megabytes'] ) ) )
			: 8;
		$batch_bytes = $batch_megabytes * 1024 * 1024;

		$result = $this->restorer->advance( $job_id, $batch_files, $batch_bytes );
		if ( ! is_array( $result ) ) {
			wp_die(
				esc_html__( 'Local clone file staging could not be advanced.', 'seo-geo-migration-bridge' ),
				'',
				array( 'response' => 400 )
			);
		}

		$status = (string) ( $result['status'] ?? 'running' );
		if ( ! in_array( $status, array( 'running', 'ready', 'blocked' ), true ) ) {
			$status = 'blocked';
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'seo_geo_clone_local_files' => $status,
					'clone_job_id'              => $job_id,
				),
				admin_url( 'tools.php?page=' . AdminOperatorScreen::PAGE_SLUG )
			)
		);
		exit;
	}
}
