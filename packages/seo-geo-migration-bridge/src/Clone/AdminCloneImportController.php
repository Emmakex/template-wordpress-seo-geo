<?php
/**
 * Portable Clone import intake/preflight administration endpoint.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge\Clone;

use SeoGeo\MigrationBridge\Operator\AdminOperatorScreen;

/**
 * Capability/nonce-gated private ZIP intake and read-only import preflight.
 */
final class AdminCloneImportController {
	public const ACTION       = 'seo_geo_migration_clone_import_preflight';
	public const NONCE_ACTION = 'seo_geo_migration_clone_import_preflight';

	/**
	 * Import preflight service.
	 *
	 * @var ImportPreflight
	 */
	private ImportPreflight $preflight;

	/**
	 * Construct controller.
	 *
	 * @param ImportPreflight|null $preflight Optional preflight service.
	 */
	public function __construct( ?ImportPreflight $preflight = null ) {
		$this->preflight = $preflight ?? new ImportPreflight();
	}

	/**
	 * Register authenticated endpoint.
	 */
	public function boot(): void {
		add_action( 'admin_post_' . self::ACTION, array( $this, 'handle' ) );
	}

	/**
	 * Stage an optional uploaded ZIP and execute read-only import preflight.
	 */
	public function handle(): never {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die(
				esc_html__( 'Administrator capability is required for Portable Clone import preflight.', 'seo-geo-migration-bridge' ),
				'',
				array( 'response' => 403 )
			);
		}

		$job_id = $this->posted_job_id();
		check_admin_referer( self::NONCE_ACTION . ':' . $job_id );

		if ( $this->has_upload() ) {
			$source = $this->validated_upload_path();
			if ( null === $source || null === $this->preflight->stage( $job_id, $source ) ) {
				wp_die(
					esc_html__( 'The Portable Clone ZIP could not be staged safely.', 'seo-geo-migration-bridge' ),
					'',
					array( 'response' => 400 )
				);
			}
		} elseif ( null === $this->preflight->snapshot( $job_id ) ) {
			wp_die(
				esc_html__( 'Choose a Portable Clone ZIP before running the first import preflight.', 'seo-geo-migration-bridge' ),
				'',
				array( 'response' => 400 )
			);
		}

		$result = $this->preflight->validate( $job_id );
		if ( ! is_array( $result ) ) {
			wp_die(
				esc_html__( 'Portable Clone import preflight could not be completed.', 'seo-geo-migration-bridge' ),
				'',
				array( 'response' => 400 )
			);
		}

		$status = 'preflight-ready' === ( $result['status'] ?? null ) ? 'ready' : 'blocked';
		$this->redirect_to_operator( $job_id, $status );
	}

	/**
	 * Whether an upload field was submitted.
	 */
	private function has_upload(): bool {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Caller validates the job-scoped nonce before this helper is invoked.
		return isset( $_FILES['clone_package'] ) && is_array( $_FILES['clone_package'] );
	}

	/**
	 * Validate the HTTP-uploaded ZIP and return its temporary path.
	 */
	private function validated_upload_path(): ?string {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Caller validates the job-scoped nonce before this helper is invoked.
		$upload = isset( $_FILES['clone_package'] ) && is_array( $_FILES['clone_package'] )
			? $_FILES['clone_package']
			: null;
		if ( ! is_array( $upload ) ) {
			return null;
		}

		$error = isset( $upload['error'] ) ? (int) $upload['error'] : UPLOAD_ERR_NO_FILE;
		$size  = isset( $upload['size'] ) ? max( 0, (int) $upload['size'] ) : 0;
		$name  = isset( $upload['name'] ) && is_string( $upload['name'] )
			? sanitize_file_name( wp_unslash( $upload['name'] ) )
			: '';

		// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Native PHP upload tmp path is verified with is_uploaded_file before use.
		$tmp_name = isset( $upload['tmp_name'] ) && is_string( $upload['tmp_name'] )
			? wp_normalize_path( $upload['tmp_name'] )
			: '';

		if (
			UPLOAD_ERR_OK !== $error
			|| 0 >= $size
			|| 'zip' !== strtolower( (string) pathinfo( $name, PATHINFO_EXTENSION ) )
			|| '' === $tmp_name
			|| ! is_uploaded_file( $tmp_name )
			|| ! is_file( $tmp_name )
			|| ! is_readable( $tmp_name )
		) {
			return null;
		}

		$actual_size = filesize( $tmp_name );
		if ( false === $actual_size || (int) $actual_size !== $size ) {
			return null;
		}

		return $tmp_name;
	}

	/**
	 * Return the bounded posted clone job identifier.
	 */
	private function posted_job_id(): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- The job ID scopes the nonce checked immediately by the calling handler.
		if ( ! isset( $_POST['clone_job_id'] ) ) {
			return '';
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- The job ID scopes the nonce checked immediately by the calling handler.
		return sanitize_text_field( wp_unslash( $_POST['clone_job_id'] ) );
	}

	/**
	 * Redirect to the operator screen with bounded import status.
	 *
	 * @param string $job_id Clone job identifier.
	 * @param string $status Preflight status.
	 */
	private function redirect_to_operator( string $job_id, string $status ): never {
		$redirect = add_query_arg(
			array(
				'seo_geo_clone_import' => sanitize_key( $status ),
				'clone_job_id'         => $job_id,
			),
			admin_url( 'tools.php?page=' . AdminOperatorScreen::PAGE_SLUG )
		);

		wp_safe_redirect( $redirect );
		exit;
	}
}
