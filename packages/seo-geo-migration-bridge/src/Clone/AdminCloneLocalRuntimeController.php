<?php
/**
 * Portable Clone local WordPress core runtime administration endpoint.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge\Clone;

use SeoGeo\MigrationBridge\Operator\AdminOperatorScreen;

/**
 * Capability/nonce-gated bounded local-clone core runtime batches.
 */
final class AdminCloneLocalRuntimeController {
	public const ACTION       = 'seo_geo_migration_clone_local_runtime_advance';
	public const NONCE_ACTION = 'seo_geo_migration_clone_local_runtime';

	private LocalCloneRuntimeBootstrapper $runtime;

	/**
	 * Construct the controller.
	 *
	 * @param LocalCloneRuntimeBootstrapper|null $runtime Optional runtime bootstrapper.
	 */
	public function __construct( ?LocalCloneRuntimeBootstrapper $runtime = null ) {
		$this->runtime = $runtime ?? new LocalCloneRuntimeBootstrapper();
	}

	/**
	 * Register the authenticated-only endpoint.
	 */
	public function boot(): void {
		add_action( 'admin_post_' . self::ACTION, array( $this, 'advance' ) );
	}

	/**
	 * Advance one bounded core copy/verification request.
	 */
	public function advance(): never {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die(
				esc_html__( 'Administrator capability is required to bootstrap the local clone WordPress runtime.', 'seo-geo-migration-bridge' ),
				'',
				array( 'response' => 403 )
			);
		}

		$job_id = isset( $_POST['clone_job_id'] )
			? sanitize_text_field( wp_unslash( $_POST['clone_job_id'] ) )
			: '';
		check_admin_referer( self::NONCE_ACTION . ':' . $job_id );

		$existing = $this->runtime->snapshot( $job_id );
		if ( ! is_array( $existing ) ) {
			$confirmed = isset( $_POST['local_clone_runtime_confirm'] )
				&& '1' === sanitize_text_field( wp_unslash( $_POST['local_clone_runtime_confirm'] ) );
			if ( ! $confirmed ) {
				wp_die(
					esc_html__( 'Explicit confirmation is required before the first local clone runtime copy batch.', 'seo-geo-migration-bridge' ),
					'',
					array( 'response' => 400 )
				);
			}
		}

		$batch_files = isset( $_POST['runtime_batch_files'] )
			? absint( wp_unslash( $_POST['runtime_batch_files'] ) )
			: LocalCloneRuntimeBootstrapper::DEFAULT_BATCH_FILES;
		$batch_mb = isset( $_POST['runtime_batch_megabytes'] )
			? absint( wp_unslash( $_POST['runtime_batch_megabytes'] ) )
			: 8;

		$result = $this->runtime->advance( $job_id, $batch_files, $batch_mb * 1024 * 1024 );
		if ( ! is_array( $result ) ) {
			wp_die(
				esc_html__( 'The local clone WordPress core runtime batch could not be started from the verified ownership contract.', 'seo-geo-migration-bridge' ),
				'',
				array( 'response' => 400 )
			);
		}

		$status = (string) ( $result['status'] ?? 'blocked' );
		if ( ! in_array( $status, array( 'running', 'complete', 'blocked' ), true ) ) {
			$status = 'blocked';
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'seo_geo_clone_local_runtime' => $status,
					'clone_job_id'                => $job_id,
				),
				admin_url( 'tools.php?page=' . AdminOperatorScreen::PAGE_SLUG )
			)
		);
		exit;
	}
}
