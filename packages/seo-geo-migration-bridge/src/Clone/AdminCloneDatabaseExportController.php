<?php
/**
 * Portable Clone resumable database-export admin endpoint.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge\Clone;

use SeoGeo\MigrationBridge\Operator\AdminOperatorScreen;

/**
 * Advances one bounded private database-export batch.
 */
final class AdminCloneDatabaseExportController {
	public const ACTION = 'seo_geo_migration_clone_database_export';
	public const NONCE_ACTION = 'seo_geo_migration_clone_database_export';

	private DatabaseExporter $exporter;

	public function __construct( ?DatabaseExporter $exporter = null ) {
		$this->exporter = $exporter ?? new DatabaseExporter();
	}

	public function boot(): void {
		add_action( 'admin_post_' . self::ACTION, array( $this, 'handle' ) );
	}

	public function handle(): never {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die(
				esc_html__( 'Administrator capability is required to export Portable Clone database chunks.', 'seo-geo-migration-bridge' ),
				'',
				array( 'response' => 403 )
			);
		}

		$job_id = isset( $_POST['clone_job_id'] )
			? sanitize_text_field( wp_unslash( $_POST['clone_job_id'] ) )
			: '';

		check_admin_referer( self::NONCE_ACTION . ':' . $job_id );

		$batch_rows = isset( $_POST['database_batch_rows'] )
			? absint( sanitize_text_field( wp_unslash( $_POST['database_batch_rows'] ) ) )
			: DatabaseExporter::DEFAULT_BATCH_ROWS;

		$result = $this->exporter->advance( $job_id, $batch_rows );
		if ( ! is_array( $result ) ) {
			wp_die(
				esc_html__( 'Portable Clone database export could not be advanced.', 'seo-geo-migration-bridge' ),
				'',
				array( 'response' => 400 )
			);
		}

		$redirect = add_query_arg(
			array(
				'seo_geo_clone_database_export' => (string) ( $result['status'] ?? 'running' ),
				'clone_job_id'                  => $job_id,
			),
			admin_url( 'tools.php?page=' . AdminOperatorScreen::PAGE_SLUG )
		);

		wp_safe_redirect( $redirect );
		exit;
	}
}
