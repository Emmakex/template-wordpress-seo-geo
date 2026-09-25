<?php
/**
 * Portable Clone authenticated delivery administration endpoints.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge\Clone;

use SeoGeo\MigrationBridge\Operator\AdminOperatorScreen;

/**
 * Capability/nonce-gated package build, download and cleanup controller.
 */
final class AdminCloneDeliveryController {
	public const BUILD_ACTION    = 'seo_geo_migration_clone_delivery_build';
	public const DOWNLOAD_ACTION = 'seo_geo_migration_clone_delivery_download';
	public const CLEANUP_ACTION  = 'seo_geo_migration_clone_delivery_cleanup';
	public const NONCE_ACTION    = 'seo_geo_migration_clone_delivery';

	/**
	 * Delivery service.
	 *
	 * @var PackageDelivery
	 */
	private PackageDelivery $delivery;

	/**
	 * Construct controller.
	 *
	 * @param PackageDelivery|null $delivery Optional delivery service.
	 */
	public function __construct( ?PackageDelivery $delivery = null ) {
		$this->delivery = $delivery ?? new PackageDelivery();
	}

	/**
	 * Register authenticated actions and bounded retention maintenance.
	 */
	public function boot(): void {
		add_action( 'admin_post_' . self::BUILD_ACTION, array( $this, 'handle_build' ) );
		add_action( 'admin_post_' . self::DOWNLOAD_ACTION, array( $this, 'handle_download' ) );
		add_action( 'admin_post_' . self::CLEANUP_ACTION, array( $this, 'handle_cleanup' ) );
		add_action( 'admin_init', array( $this, 'maintenance' ) );
	}

	/**
	 * Opportunistically advance one bounded expired-artifact cleanup batch.
	 */
	public function maintenance(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$this->delivery->cleanup_expired( 1, PackageDelivery::DEFAULT_CLEANUP_BATCH );
	}

	/**
	 * Advance one bounded private ZIP build.
	 */
	public function handle_build(): never {
		$this->require_admin();

		$job_id = $this->posted_job_id();
		check_admin_referer( self::NONCE_ACTION . ':build:' . $job_id );

		$batch_files = isset( $_POST['delivery_batch_size'] )
			? absint( sanitize_text_field( wp_unslash( $_POST['delivery_batch_size'] ) ) )
			: PackageDelivery::DEFAULT_BATCH_FILES;
		$batch_megabytes = isset( $_POST['delivery_batch_megabytes'] )
			? absint( sanitize_text_field( wp_unslash( $_POST['delivery_batch_megabytes'] ) ) )
			: 16;

		$result = $this->delivery->advance( $job_id, $batch_files, $batch_megabytes * 1024 * 1024 );
		if ( ! is_array( $result ) ) {
			wp_die(
				esc_html__( 'Portable Clone delivery could not be advanced.', 'seo-geo-migration-bridge' ),
				'',
				array( 'response' => 400 )
			);
		}

		$this->redirect_to_operator( $job_id, (string) ( $result['status'] ?? 'building' ) );
	}

	/**
	 * Stream one verified private ZIP only to an authenticated administrator.
	 */
	public function handle_download(): never {
		$this->require_admin();

		$job_id = $this->posted_job_id();
		check_admin_referer( self::NONCE_ACTION . ':download:' . $job_id );

		$download = $this->delivery->download_info( $job_id );
		if ( ! is_array( $download ) || ! $this->delivery->mark_downloaded( $job_id ) ) {
			$this->delivery->cleanup_expired( 1, PackageDelivery::DEFAULT_CLEANUP_BATCH );
			wp_die(
				esc_html__( 'The Portable Clone package is unavailable, expired or failed its archive integrity check.', 'seo-geo-migration-bridge' ),
				'',
				array( 'response' => 410 )
			);
		}

		nocache_headers();
		header( 'Content-Type: application/zip' );
		header( 'Content-Disposition: attachment; filename="' . $download['name'] . '"' );
		header( 'Content-Length: ' . (string) $download['bytes'] );
		header( 'X-Content-Type-Options: nosniff' );
		header( 'X-Robots-Tag: noindex, nofollow, noarchive', true );

		while ( 0 < ob_get_level() ) {
			ob_end_clean();
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile -- Authenticated streaming of one verified private job-owned archive.
		readfile( $download['path'] );
		exit;
	}

	/**
	 * Advance one explicit bounded cleanup batch.
	 */
	public function handle_cleanup(): never {
		$this->require_admin();

		$job_id = $this->posted_job_id();
		check_admin_referer( self::NONCE_ACTION . ':cleanup:' . $job_id );

		$confirmation = isset( $_POST['cleanup_confirmation'] )
			? sanitize_key( wp_unslash( $_POST['cleanup_confirmation'] ) )
			: '';
		if ( 'cleanup' !== $confirmation ) {
			wp_die(
				esc_html__( 'Explicit cleanup confirmation is required.', 'seo-geo-migration-bridge' ),
				'',
				array( 'response' => 400 )
			);
		}

		$result = $this->delivery->cleanup( $job_id, PackageDelivery::DEFAULT_CLEANUP_BATCH );
		if ( ! is_array( $result ) ) {
			wp_die(
				esc_html__( 'Portable Clone private-artifact cleanup could not be advanced.', 'seo-geo-migration-bridge' ),
				'',
				array( 'response' => 400 )
			);
		}

		$this->redirect_to_operator( $job_id, (string) ( $result['status'] ?? 'cleaning' ) );
	}

	/**
	 * Require administrator capability.
	 */
	private function require_admin(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die(
				esc_html__( 'Administrator capability is required for Portable Clone package delivery.', 'seo-geo-migration-bridge' ),
				'',
				array( 'response' => 403 )
			);
		}
	}

	/**
	 * Return the bounded posted clone job identifier.
	 */
	private function posted_job_id(): string {
		return isset( $_POST['clone_job_id'] )
			? sanitize_text_field( wp_unslash( $_POST['clone_job_id'] ) )
			: '';
	}

	/**
	 * Redirect back to the operator screen with a bounded result status.
	 *
	 * @param string $job_id Clone job identifier.
	 * @param string $status Delivery status.
	 */
	private function redirect_to_operator( string $job_id, string $status ): never {
		$redirect = add_query_arg(
			array(
				'seo_geo_clone_delivery' => sanitize_key( $status ),
				'clone_job_id'           => $job_id,
			),
			admin_url( 'tools.php?page=' . AdminOperatorScreen::PAGE_SLUG )
		);

		wp_safe_redirect( $redirect );
		exit;
	}
}
