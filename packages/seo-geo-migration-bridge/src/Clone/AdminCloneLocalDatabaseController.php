<?php
/**
 * Portable Clone local database staging administration endpoint.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge\Clone;

use SeoGeo\MigrationBridge\Operator\AdminOperatorScreen;

/**
 * Capability/nonce-gated local-clone database staging controller.
 */
final class AdminCloneLocalDatabaseController {
	public const ACTION       = 'seo_geo_migration_clone_local_database';
	public const NONCE_ACTION = 'seo_geo_migration_clone_local_database';

	/**
	 * Local database restorer.
	 *
	 * @var LocalCloneDatabaseRestorer
	 */
	private LocalCloneDatabaseRestorer $restorer;

	/**
	 * Construct controller.
	 *
	 * @param LocalCloneDatabaseRestorer|null $restorer Optional local database restorer.
	 */
	public function __construct( ?LocalCloneDatabaseRestorer $restorer = null ) {
		$this->restorer = $restorer ?? new LocalCloneDatabaseRestorer();
	}

	/**
	 * Register authenticated endpoint.
	 */
	public function boot(): void {
		add_action( 'admin_post_' . self::ACTION, array( $this, 'handle' ) );
	}

	/**
	 * Advance one bounded transactional staging batch.
	 */
	public function handle(): never {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die(
				esc_html__( 'Administrator capability is required to stage the local clone database.', 'seo-geo-migration-bridge' ),
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
			$confirmed = isset( $_POST['local_clone_database_confirm'] )
				&& '1' === sanitize_text_field( wp_unslash( $_POST['local_clone_database_confirm'] ) );
			if ( ! $confirmed ) {
				wp_die(
					esc_html__( 'Explicit confirmation is required before creating isolated job-owned database staging tables.', 'seo-geo-migration-bridge' ),
					'',
					array( 'response' => 400 )
				);
			}
		}

		$batch_rows = isset( $_POST['local_clone_database_batch_rows'] )
			? absint( sanitize_text_field( wp_unslash( $_POST['local_clone_database_batch_rows'] ) ) )
			: ImportDatabaseRestorer::DEFAULT_BATCH_ROWS;

		$result = $this->restorer->advance( $job_id, $batch_rows );
		if ( ! is_array( $result ) ) {
			wp_die(
				esc_html__( 'Local clone database staging could not be advanced.', 'seo-geo-migration-bridge' ),
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
					'seo_geo_clone_local_database' => $status,
					'clone_job_id'                 => $job_id,
				),
				admin_url( 'tools.php?page=' . AdminOperatorScreen::PAGE_SLUG )
			)
		);
		exit;
	}
}
