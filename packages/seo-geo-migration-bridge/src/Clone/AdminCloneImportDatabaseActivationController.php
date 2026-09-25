<?php
/**
 * Portable Clone reversible database activation administration endpoint.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge\Clone;

use SeoGeo\MigrationBridge\Operator\AdminOperatorScreen;

/**
 * Capability/nonce/explicit-confirmation gated database activation controller.
 */
final class AdminCloneImportDatabaseActivationController {
	public const ACTION       = 'seo_geo_migration_clone_import_database_activation';
	public const NONCE_ACTION = 'seo_geo_migration_clone_import_database_activation';

	private ImportDatabaseActivator $activator;

	public function __construct( ?ImportDatabaseActivator $activator = null ) {
		$this->activator = $activator ?? new ImportDatabaseActivator();
	}

	public function boot(): void {
		add_action( 'admin_post_' . self::ACTION, array( $this, 'handle' ) );
	}

	public function handle(): never {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die(
				esc_html__( 'Administrator capability is required for Portable Clone database activation.', 'seo-geo-migration-bridge' ),
				'',
				array( 'response' => 403 )
			);
		}

		$job_id = $this->posted_job_id();
		check_admin_referer( self::NONCE_ACTION . ':' . $job_id );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is verified immediately above.
		$step = isset( $_POST['database_activation_step'] )
			? sanitize_key( wp_unslash( $_POST['database_activation_step'] ) )
			: '';

		if ( 'prepare' === $step ) {
			$result = $this->activator->prepare( $job_id );
		} elseif ( 'activate' === $step ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is verified above; explicit confirmation is a second destructive-action guard.
			$confirmation = isset( $_POST['database_activation_confirmation'] )
				? sanitize_text_field( wp_unslash( $_POST['database_activation_confirmation'] ) )
				: '';
			$result       = 'ACTIVATE_DATABASE' === $confirmation
				? $this->activator->activate( $job_id )
				: null;
		} elseif ( 'rollback' === $step ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is verified above; explicit confirmation is a second destructive-action guard.
			$confirmation = isset( $_POST['database_activation_confirmation'] )
				? sanitize_text_field( wp_unslash( $_POST['database_activation_confirmation'] ) )
				: '';
			$result       = 'ROLLBACK_DATABASE' === $confirmation
				? $this->activator->rollback( $job_id )
				: null;
		} else {
			$result = null;
		}

		if ( ! is_array( $result ) ) {
			wp_die(
				esc_html__( 'Portable Clone database activation request was rejected.', 'seo-geo-migration-bridge' ),
				'',
				array( 'response' => 400 )
			);
		}

		$redirect = add_query_arg(
			array(
				'seo_geo_clone_database_activation' => sanitize_key( (string) ( $result['status'] ?? 'unknown' ) ),
				'clone_job_id'                      => $job_id,
			),
			admin_url( 'tools.php?page=' . AdminOperatorScreen::PAGE_SLUG )
		);

		wp_safe_redirect( $redirect );
		exit;
	}

	private function posted_job_id(): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Job ID scopes the nonce checked by the calling handler.
		if ( ! isset( $_POST['clone_job_id'] ) ) {
			return '';
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Job ID scopes the nonce checked by the calling handler.
		return sanitize_text_field( wp_unslash( $_POST['clone_job_id'] ) );
	}
}
