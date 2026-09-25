<?php
/**
 * Portable Clone package-integrity administration endpoint.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge\Clone;

use SeoGeo\MigrationBridge\Operator\AdminOperatorScreen;

/**
 * Advances one bounded package checksum/integrity batch.
 */
final class AdminClonePackageController {
	public const ACTION       = 'seo_geo_migration_clone_package_integrity';
	public const NONCE_ACTION = 'seo_geo_migration_clone_package_integrity';

	/**
	 * Package builder.
	 *
	 * @var PackageBuilder
	 */
	private PackageBuilder $builder;

	/**
	 * Construct controller.
	 *
	 * @param PackageBuilder|null $builder Optional package builder.
	 */
	public function __construct( ?PackageBuilder $builder = null ) {
		$this->builder = $builder ?? new PackageBuilder();
	}

	/**
	 * Register authenticated endpoint.
	 */
	public function boot(): void {
		add_action( 'admin_post_' . self::ACTION, array( $this, 'handle' ) );
	}

	/**
	 * Advance one bounded package/integrity batch.
	 */
	public function handle(): never {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die(
				esc_html__( 'Administrator capability is required to build Portable Clone package integrity.', 'seo-geo-migration-bridge' ),
				'',
				array( 'response' => 403 )
			);
		}

		$job_id = isset( $_POST['clone_job_id'] )
			? sanitize_text_field( wp_unslash( $_POST['clone_job_id'] ) )
			: '';
		check_admin_referer( self::NONCE_ACTION . ':' . $job_id );

		$batch_files     = isset( $_POST['package_batch_size'] )
			? absint( sanitize_text_field( wp_unslash( $_POST['package_batch_size'] ) ) )
			: PackageBuilder::DEFAULT_BATCH_FILES;
		$batch_megabytes = isset( $_POST['package_batch_megabytes'] )
			? absint( sanitize_text_field( wp_unslash( $_POST['package_batch_megabytes'] ) ) )
			: 16;
		$batch_bytes     = $batch_megabytes * 1024 * 1024;

		$result = $this->builder->advance( $job_id, $batch_files, $batch_bytes );
		if ( ! is_array( $result ) ) {
			wp_die(
				esc_html__( 'Portable Clone package integrity could not be advanced.', 'seo-geo-migration-bridge' ),
				'',
				array( 'response' => 400 )
			);
		}

		$redirect = add_query_arg(
			array(
				'seo_geo_clone_package' => (string) ( $result['status'] ?? 'running' ),
				'clone_job_id'          => $job_id,
			),
			admin_url( 'tools.php?page=' . AdminOperatorScreen::PAGE_SLUG )
		);

		wp_safe_redirect( $redirect );
		exit;
	}
}
