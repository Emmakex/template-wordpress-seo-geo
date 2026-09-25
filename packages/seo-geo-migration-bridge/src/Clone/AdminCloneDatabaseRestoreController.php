<?php
/**
 * Portable Clone isolated database-restore admin endpoint.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge\Clone;

use SeoGeo\MigrationBridge\Operator\AdminOperatorScreen;

/**
 * Advances bounded verified database restore batches.
 */
final class AdminCloneDatabaseRestoreController {
	public const ACTION       = 'seo_geo_migration_clone_database_restore';
	public const NONCE_ACTION = 'seo_geo_migration_clone_database_restore';

	private DatabaseRestorer $restorer;

	public function __construct( ?DatabaseRestorer $restorer = null ) {
		$this->restorer = $restorer ?? new DatabaseRestorer();
	}

	public function boot(): void {
		add_action( 'admin_post_' . self::ACTION, array( $this, 'handle' ) );
	}

	public function handle(): never {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die(
				esc_html__( 'Administrator capability is required for Portable Clone database restore.', 'seo-geo-migration-bridge' ),
				'',
				array( 'response' => 403 )
			);
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Job ID scopes the nonce checked immediately below.
		$job_id = isset( $_POST['clone_job_id'] )
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Job ID scopes the nonce checked immediately below.
			? sanitize_text_field( wp_unslash( $_POST['clone_job_id'] ) )
			: '';

		check_admin_referer( self::NONCE_ACTION . ':' . $job_id );

		$batch_chunks = isset( $_POST['database_restore_batch_chunks'] )
			? absint( sanitize_text_field( wp_unslash( $_POST['database_restore_batch_chunks'] ) ) )
			: DatabaseRestorer::DEFAULT_BATCH_CHUNKS;

		$result = $this->restorer->advance( $job_id, $batch_chunks );
		if ( ! is_array( $result ) ) {
			wp_die(
				esc_html__( 'Portable Clone database restore could not be advanced.', 'seo-geo-migration-bridge' ),
				'',
				array( 'response' => 400 )
			);
		}

		$redirect = add_query_arg(
			array(
				'seo_geo_clone_database_restore' => sanitize_key( (string) ( $result['status'] ?? 'running' ) ),
				'clone_job_id'                   => $job_id,
			),
			admin_url( 'tools.php?page=' . AdminOperatorScreen::PAGE_SLUG )
		);

		wp_safe_redirect( $redirect );
		exit;
	}
}
