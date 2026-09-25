<?php
/**
 * Portable Clone local target ownership administration endpoints.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge\Clone;

use SeoGeo\MigrationBridge\Operator\AdminOperatorScreen;

/**
 * Capability/nonce/confirmation-gated target claim and release actions.
 */
final class AdminCloneLocalBootstrapController {
	public const CLAIM_ACTION   = 'seo_geo_migration_clone_local_bootstrap_claim';
	public const RELEASE_ACTION = 'seo_geo_migration_clone_local_bootstrap_release';
	public const NONCE_ACTION   = 'seo_geo_migration_clone_local_bootstrap';

	/**
	 * Local-clone target ownership bootstrapper.
	 *
	 * @var LocalCloneBootstrapper
	 */
	private LocalCloneBootstrapper $bootstrapper;

	/**
	 * Construct the controller.
	 *
	 * @param LocalCloneBootstrapper|null $bootstrapper Optional bootstrap service.
	 */
	public function __construct( ?LocalCloneBootstrapper $bootstrapper = null ) {
		$this->bootstrapper = $bootstrapper ?? new LocalCloneBootstrapper();
	}

	/**
	 * Register authenticated-only endpoints.
	 */
	public function boot(): void {
		add_action( 'admin_post_' . self::CLAIM_ACTION, array( $this, 'claim' ) );
		add_action( 'admin_post_' . self::RELEASE_ACTION, array( $this, 'release' ) );
	}

	/**
	 * Claim the exact accepted local-clone target.
	 */
	public function claim(): never {
		$job_id = $this->authorize( self::CLAIM_ACTION, 'local_clone_bootstrap_confirm' );
		$result = $this->bootstrapper->claim( $job_id );
		if ( ! is_array( $result ) ) {
			wp_die(
				esc_html__( 'The local clone target could not be claimed from the accepted destination plan.', 'seo-geo-migration-bridge' ),
				'',
				array( 'response' => 400 )
			);
		}

		$this->redirect( $job_id, (string) ( $result['status'] ?? 'blocked' ) );
	}

	/**
	 * Release only this microphase's verified ownership marker.
	 */
	public function release(): never {
		$job_id = $this->authorize( self::RELEASE_ACTION, 'local_clone_bootstrap_release_confirm' );
		$result = $this->bootstrapper->release( $job_id );
		if ( ! is_array( $result ) ) {
			wp_die(
				esc_html__( 'The local clone target ownership claim could not be released safely.', 'seo-geo-migration-bridge' ),
				'',
				array( 'response' => 400 )
			);
		}

		$this->redirect( $job_id, (string) ( $result['status'] ?? 'blocked' ) );
	}

	/**
	 * Enforce administrator capability, job-scoped nonce and explicit confirmation.
	 *
	 * @param string $action            Action identifier.
	 * @param string $confirmation_name Confirmation checkbox field.
	 */
	private function authorize( string $action, string $confirmation_name ): string {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die(
				esc_html__( 'Administrator capability is required to change a local clone target ownership claim.', 'seo-geo-migration-bridge' ),
				'',
				array( 'response' => 403 )
			);
		}

		$job_id = isset( $_POST['clone_job_id'] )
			? sanitize_text_field( wp_unslash( $_POST['clone_job_id'] ) )
			: '';
		check_admin_referer( self::NONCE_ACTION . ':' . $action . ':' . $job_id );

		$confirmed = isset( $_POST[ $confirmation_name ] )
			&& '1' === sanitize_text_field( wp_unslash( $_POST[ $confirmation_name ] ) );
		if ( ! $confirmed ) {
			wp_die(
				esc_html__( 'Explicit confirmation is required for this local clone target ownership action.', 'seo-geo-migration-bridge' ),
				'',
				array( 'response' => 400 )
			);
		}

		return $job_id;
	}

	/**
	 * Redirect back to the operator screen with a bounded result.
	 *
	 * @param string $job_id Clone job identifier.
	 * @param string $status Result status.
	 */
	private function redirect( string $job_id, string $status ): never {
		$allowed = array( 'claimed', 'blocked', 'released' );
		$status  = in_array( $status, $allowed, true ) ? $status : 'blocked';

		wp_safe_redirect(
			add_query_arg(
				array(
					'seo_geo_clone_local_bootstrap' => $status,
					'clone_job_id'                  => $job_id,
				),
				admin_url( 'tools.php?page=' . AdminOperatorScreen::PAGE_SLUG )
			)
		);
		exit;
	}
}
