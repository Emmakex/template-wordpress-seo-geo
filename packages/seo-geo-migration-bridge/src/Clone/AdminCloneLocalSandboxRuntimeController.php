<?php
/**
 * Portable Clone isolated sandbox runtime administration endpoint.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge\Clone;

use SeoGeo\MigrationBridge\Operator\AdminOperatorScreen;

/**
 * Capability/nonce-gated sandbox runtime batches.
 */
final class AdminCloneLocalSandboxRuntimeController {
	public const ACTION       = 'seo_geo_migration_clone_local_sandbox_runtime_advance';
	public const NONCE_ACTION = 'seo_geo_migration_clone_local_sandbox_runtime';

	/**
	 * Isolated sandbox runtime service.
	 *
	 * @var LocalCloneSandboxRuntimeBootstrapper
	 */
	private LocalCloneSandboxRuntimeBootstrapper $runtime;

	/**
	 * Construct controller.
	 *
	 * @param LocalCloneSandboxRuntimeBootstrapper|null $runtime Optional runtime service.
	 */
	public function __construct( ?LocalCloneSandboxRuntimeBootstrapper $runtime = null ) {
		$this->runtime = $runtime ?? new LocalCloneSandboxRuntimeBootstrapper();
	}

	/**
	 * Register authenticated-only endpoint.
	 */
	public function boot(): void {
		add_action( 'admin_post_' . self::ACTION, array( $this, 'advance' ) );
	}

	/**
	 * Advance one bounded Bridge/config/hardening batch.
	 */
	public function advance(): never {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die(
				esc_html__( 'Administrator capability is required to build the isolated sandbox runtime.', 'seo-geo-migration-bridge' ),
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
			$confirmed = isset( $_POST['local_clone_sandbox_runtime_confirm'] )
				&& '1' === sanitize_text_field( wp_unslash( $_POST['local_clone_sandbox_runtime_confirm'] ) );
			if ( ! $confirmed ) {
				wp_die(
					esc_html__( 'Explicit confirmation is required before installing the isolated sandbox control runtime.', 'seo-geo-migration-bridge' ),
					'',
					array( 'response' => 400 )
				);
			}
		}

		$batch_files = isset( $_POST['sandbox_runtime_batch_files'] )
			? absint( wp_unslash( $_POST['sandbox_runtime_batch_files'] ) )
			: LocalCloneSandboxRuntimeBootstrapper::DEFAULT_BATCH_FILES;
		$batch_mb    = isset( $_POST['sandbox_runtime_batch_megabytes'] )
			? absint( wp_unslash( $_POST['sandbox_runtime_batch_megabytes'] ) )
			: 8;

		$result = $this->runtime->advance( $job_id, $batch_files, $batch_mb * 1024 * 1024 );
		if ( ! is_array( $result ) ) {
			wp_die(
				esc_html__( 'The isolated sandbox runtime could not be started from the verified core/ownership contract.', 'seo-geo-migration-bridge' ),
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
					'seo_geo_clone_local_sandbox_runtime' => $status,
					'clone_job_id'                        => $job_id,
				),
				admin_url( 'tools.php?page=' . AdminOperatorScreen::PAGE_SLUG )
			)
		);
		exit;
	}
}
