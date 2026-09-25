<?php
/**
 * Portable Import sandbox activation-plan administration endpoint.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge\Clone;

use SeoGeo\MigrationBridge\Operator\AdminOperatorScreen;

/**
 * Capability/nonce-gated non-mutating activation-plan endpoint.
 */
final class AdminCloneImportActivationPlanController {
	public const ACTION       = 'seo_geo_migration_clone_import_activation_plan';
	public const NONCE_ACTION = 'seo_geo_migration_clone_import_activation_plan';

	/**
	 * Activation planner.
	 *
	 * @var ImportActivationPlanner
	 */
	private ImportActivationPlanner $planner;

	/**
	 * Construct controller.
	 *
	 * @param ImportActivationPlanner|null $planner Optional planner.
	 */
	public function __construct( ?ImportActivationPlanner $planner = null ) {
		$this->planner = $planner ?? new ImportActivationPlanner();
	}

	/**
	 * Register authenticated endpoint.
	 */
	public function boot(): void {
		add_action( 'admin_post_' . self::ACTION, array( $this, 'handle' ) );
	}

	/**
	 * Build one read-only activation plan from explicit recovery evidence.
	 */
	public function handle(): never {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die(
				esc_html__( 'Administrator capability is required for Portable Import activation planning.', 'seo-geo-migration-bridge' ),
				'',
				array( 'response' => 403 )
			);
		}

		$job_id = $this->posted( 'clone_job_id', 128 );
		check_admin_referer( self::NONCE_ACTION . ':' . $job_id );

		$evidence = array(
			'database'   => $this->evidence_row( 'database', 'full-database' ),
			'wp_content' => $this->evidence_row( 'wp_content', 'wp-content-tree' ),
		);

		$result = $this->planner->plan( $job_id, $evidence );
		if ( ! is_array( $result ) ) {
			wp_die(
				esc_html__( 'Portable Import activation planning could not be completed.', 'seo-geo-migration-bridge' ),
				'',
				array( 'response' => 400 )
			);
		}

		$redirect = add_query_arg(
			array(
				'seo_geo_clone_activation_plan' => (string) ( $result['status'] ?? 'blocked' ),
				'clone_job_id'                  => $job_id,
			),
			admin_url( 'tools.php?page=' . AdminOperatorScreen::PAGE_SLUG )
		);

		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * Build one submitted recovery evidence row.
	 *
	 * @param string $key   Evidence key prefix.
	 * @param string $scope Fixed expected scope.
	 * @return array<string,mixed>
	 */
	private function evidence_row( string $key, string $scope ): array {
		return array(
			'reference'  => $this->posted( 'recovery_' . $key . '_reference', 200 ),
			'sha256'     => strtolower( $this->posted( 'recovery_' . $key . '_sha256', 64 ) ),
			'created_at' => $this->posted( 'recovery_' . $key . '_created_at', 40 ),
			'scope'      => $scope,
			'size_bytes' => $this->posted_int( 'recovery_' . $key . '_size_bytes' ),
		);
	}

	/**
	 * Return one bounded posted text value.
	 *
	 * @param string $key   Field name.
	 * @param int    $limit Maximum bytes.
	 */
	private function posted( string $key, int $limit ): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Job-scoped nonce is checked by the calling handler before plan execution.
		if ( ! isset( $_POST[ $key ] ) || ! is_string( $_POST[ $key ] ) ) {
			return '';
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Same nonce-verified request.
		return substr( sanitize_text_field( wp_unslash( $_POST[ $key ] ) ), 0, $limit );
	}

	/**
	 * Return one non-negative submitted integer.
	 *
	 * @param string $key Field name.
	 */
	private function posted_int( string $key ): int {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Job-scoped nonce is checked by the calling handler before plan execution.
		return isset( $_POST[ $key ] ) ? max( 0, absint( $_POST[ $key ] ) ) : 0;
	}
}
