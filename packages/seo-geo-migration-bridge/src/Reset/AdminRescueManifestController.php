<?php
/**
 * Administrator Reset/Rebuild surface.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge\Reset;

use WP_Error;

/**
 * Provides Rescue Manifest + Clone Reset Engine actions.
 */
final class AdminRescueManifestController {
	public const PAGE_SLUG              = 'seo-geo-reset-rebuild';
	public const ACTION                 = 'seo_geo_reset_capture_rescue_manifest';
	public const NONCE_ACTION           = 'seo_geo_reset_capture_rescue_manifest';
	public const RESET_ACTION           = 'seo_geo_reset_apply_clone_runtime';
	public const RESET_NONCE_ACTION     = 'seo_geo_reset_apply_clone_runtime';
	public const BOOTSTRAP_ACTION       = 'seo_geo_reset_apply_corporate_bootstrap';
	public const BOOTSTRAP_NONCE_ACTION = 'seo_geo_reset_apply_corporate_bootstrap';
	public const CLEAN_HOME_ACTION      = 'seo_geo_reset_create_clean_home';
	public const CLEAN_HOME_NONCE_ACTION = 'seo_geo_reset_create_clean_home';

	/**
	 * Construct the reset-first administrator controller.
	 *
	 * @param RescueManifest          $manifest  Rescue manifest service.
	 * @param CloneResetEngine        $reset     Clone reset service.
	 * @param CorporateThemeBootstrap $bootstrap  Corporate Theme bootstrap service.
	 * @param CleanHomeRebuilder       $clean_home Clean Corporate Home draft builder.
	 */
	public function __construct(
		private RescueManifest $manifest,
		private CloneResetEngine $reset,
		private CorporateThemeBootstrap $bootstrap,
		private CleanHomeRebuilder $clean_home
	) {
	}

	/**
	 * Register administrator hooks.
	 */
	public function boot(): void {
		add_action( 'admin_menu', array( $this, 'register_page' ) );
		add_action( 'admin_post_' . self::ACTION, array( $this, 'handle_capture' ) );
		add_action( 'admin_post_' . self::RESET_ACTION, array( $this, 'handle_reset' ) );
		add_action( 'admin_post_' . self::BOOTSTRAP_ACTION, array( $this, 'handle_bootstrap' ) );
		add_action( 'admin_post_' . self::CLEAN_HOME_ACTION, array( $this, 'handle_clean_home' ) );
	}

	/**
	 * Register Tools > SEO/GEO Reset & Rebuild.
	 */
	public function register_page(): void {
		add_management_page(
			__( 'SEO/GEO Reset & Rebuild', 'seo-geo-migration-bridge' ),
			__( 'SEO/GEO Reset & Rebuild', 'seo-geo-migration-bridge' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render' )
		);
	}

	/**
	 * Render the reset-first workflow.
	 */
	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Administrator capability is required.', 'seo-geo-migration-bridge' ), '', array( 'response' => 403 ) );
		}

		$saved            = $this->manifest->saved();
		$plan             = $this->reset->plan();
		$report           = $this->reset->report();
		$bootstrap_plan   = $this->bootstrap->plan();
		$bootstrap_report = $this->bootstrap->report();
		$clean_home_plan  = $this->clean_home->plan();
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'SEO/GEO Reset & Rebuild', 'seo-geo-migration-bridge' ); ?></h1>
			<p><?php echo esc_html__( 'Rescue the useful digital asset, remove legacy runtime baggage, then rebuild with the SEO/GEO Theme and preset.', 'seo-geo-migration-bridge' ); ?></p>

			<div class="notice notice-info inline">
				<p><strong><?php echo esc_html__( 'Full redesign mode does not require every legacy UNKNOWN dependency to be reviewed.', 'seo-geo-migration-bridge' ); ?></strong></p>
				<p><?php echo esc_html__( 'Pages/posts remain in WordPress for reconstruction. Reset removes presentation/runtime baggage, not rescued content.', 'seo-geo-migration-bridge' ); ?></p>
			</div>

			<?php if ( is_array( $saved ) ) : ?>
				<h2><?php echo esc_html__( 'Saved Rescue Manifest', 'seo-geo-migration-bridge' ); ?></h2>
				<table class="widefat striped" style="max-width:900px">
					<tbody>
						<tr><th><?php echo esc_html__( 'Captured', 'seo-geo-migration-bridge' ); ?></th><td><code><?php echo esc_html( (string) ( $saved['captured_at'] ?? '' ) ); ?></code></td></tr>
						<tr><th><?php echo esc_html__( 'Resources', 'seo-geo-migration-bridge' ); ?></th><td><?php echo esc_html( (string) (int) ( $saved['counts']['resources'] ?? 0 ) ); ?></td></tr>
						<tr><th><?php echo esc_html__( 'Pages', 'seo-geo-migration-bridge' ); ?></th><td><?php echo esc_html( (string) (int) ( $saved['counts']['pages'] ?? 0 ) ); ?></td></tr>
						<tr><th><?php echo esc_html__( 'Posts', 'seo-geo-migration-bridge' ); ?></th><td><?php echo esc_html( (string) (int) ( $saved['counts']['posts'] ?? 0 ) ); ?></td></tr>
						<tr><th><?php echo esc_html__( 'Manifest SHA-256', 'seo-geo-migration-bridge' ); ?></th><td><code><?php echo esc_html( (string) ( $saved['manifest_sha256'] ?? '' ) ); ?></code></td></tr>
					</tbody>
				</table>
			<?php endif; ?>

			<h2><?php echo esc_html__( 'Step 1 — Rescue only what matters', 'seo-geo-migration-bridge' ); ?></h2>
			<p><?php echo esc_html__( 'Capture page/post identity, URL paths, content fingerprints, useful SEO metadata, links and media references. Theme/plugin/builder dependency analysis is deliberately outside this manifest.', 'seo-geo-migration-bridge' ); ?></p>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION ); ?>">
				<input type="hidden" name="confirm" value="capture-rescue-manifest">
				<?php wp_nonce_field( self::NONCE_ACTION ); ?>
				<?php submit_button( is_array( $saved ) ? __( 'Refresh Rescue Manifest', 'seo-geo-migration-bridge' ) : __( 'Create Rescue Manifest', 'seo-geo-migration-bridge' ) ); ?>
			</form>

			<h2><?php echo esc_html__( 'Step 2 — Reset clone runtime', 'seo-geo-migration-bridge' ); ?></h2>
			<p><?php echo esc_html__( 'The SEO/GEO Theme becomes active. Every other theme and every plugin not explicitly kept is deactivated and deleted. Migration Bridge keeps itself until rebuild handoff is complete.', 'seo-geo-migration-bridge' ); ?></p>

			<?php if ( is_array( $report ) ) : ?>
				<div class="notice <?php echo 'completed' === ( $report['status'] ?? '' ) ? 'notice-success' : 'notice-warning'; ?> inline">
					<p>
						<strong><?php echo esc_html__( 'Latest reset:', 'seo-geo-migration-bridge' ); ?></strong>
						<?php echo esc_html( (string) ( $report['status'] ?? '' ) ); ?>
						—
						<?php echo esc_html( (string) ( $report['reset_at'] ?? '' ) ); ?>
					</p>
					<p>
						<?php echo esc_html__( 'Target theme active:', 'seo-geo-migration-bridge' ); ?>
						<strong><?php echo true === ( $report['safety']['target_theme_active'] ?? false ) ? esc_html__( 'yes', 'seo-geo-migration-bridge' ) : esc_html__( 'no', 'seo-geo-migration-bridge' ); ?></strong>
						·
						<?php echo esc_html__( 'Rescued content unchanged:', 'seo-geo-migration-bridge' ); ?>
						<strong><?php echo true === ( $report['safety']['content_unchanged'] ?? false ) ? esc_html__( 'yes', 'seo-geo-migration-bridge' ) : esc_html__( 'no', 'seo-geo-migration-bridge' ); ?></strong>
					</p>
				</div>
			<?php endif; ?>

			<?php if ( true !== ( $plan['ready'] ?? false ) ) : ?>
				<div class="notice notice-warning inline">
					<p><strong><?php echo esc_html__( 'Clone reset is not ready:', 'seo-geo-migration-bridge' ); ?></strong></p>
					<p><code><?php echo esc_html( implode( ', ', is_array( $plan['blockers'] ?? null ) ? $plan['blockers'] : array() ) ); ?></code></p>
				</div>
			<?php else : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="<?php echo esc_attr( self::RESET_ACTION ); ?>">
					<input type="hidden" name="confirm" value="reset-clone-runtime">
					<?php wp_nonce_field( self::RESET_NONCE_ACTION ); ?>

					<h3><?php echo esc_html__( 'Plugins to remove by default', 'seo-geo-migration-bridge' ); ?></h3>
					<p><?php echo esc_html__( 'Leave everything unchecked for the cleanest rebuild. Check only a plugin whose business function must genuinely survive.', 'seo-geo-migration-bridge' ); ?></p>
					<?php foreach ( is_array( $plan['plugins']['remove'] ?? null ) ? $plan['plugins']['remove'] : array() as $plugin ) : ?>
						<?php if ( ! is_array( $plugin ) ) : ?>
							<?php continue; ?>
						<?php endif; ?>
						<label style="display:block;margin:.4rem 0;">
							<input type="checkbox" name="keep_plugins[]" value="<?php echo esc_attr( (string) ( $plugin['file'] ?? '' ) ); ?>">
							<?php echo esc_html( (string) ( $plugin['name'] ?? $plugin['file'] ?? '' ) ); ?>
							<code><?php echo esc_html( (string) ( $plugin['file'] ?? '' ) ); ?></code>
							<?php echo true === ( $plugin['active'] ?? false ) ? esc_html__( ' — active now', 'seo-geo-migration-bridge' ) : ''; ?>
						</label>
					<?php endforeach; ?>

					<h3><?php echo esc_html__( 'Themes to remove', 'seo-geo-migration-bridge' ); ?></h3>
					<?php foreach ( is_array( $plan['themes']['remove'] ?? null ) ? $plan['themes']['remove'] : array() as $theme ) : ?>
						<?php if ( is_array( $theme ) ) : ?>
							<p><code><?php echo esc_html( (string) ( $theme['stylesheet'] ?? '' ) ); ?></code> — <?php echo esc_html( (string) ( $theme['name'] ?? '' ) ); ?></p>
						<?php endif; ?>
					<?php endforeach; ?>

					<p><strong><?php echo esc_html__( 'Target theme:', 'seo-geo-migration-bridge' ); ?></strong> <code><?php echo esc_html( CloneResetEngine::TARGET_THEME ); ?></code></p>
					<p><?php echo esc_html__( 'This action does not delete pages/posts or the Rescue Manifest.', 'seo-geo-migration-bridge' ); ?></p>
					<?php submit_button( __( 'Reset clone runtime', 'seo-geo-migration-bridge' ), 'delete', 'submit', false ); ?>
				</form>
			<?php endif; ?>

			<h2><?php echo esc_html__( 'Step 3 — Bootstrap Theme + Corporate preset', 'seo-geo-migration-bridge' ); ?></h2>
			<p><?php echo esc_html__( 'Apply the Corporate preset through the Theme-owned setup authority. Language comes from the active WordPress locale; crawler policy stays inherited; llms.txt and Markdown alternatives remain off until we decide otherwise.', 'seo-geo-migration-bridge' ); ?></p>

			<?php if ( is_array( $bootstrap_report ) ) : ?>
				<div class="notice notice-success inline">
					<p>
						<strong><?php echo esc_html__( 'Corporate bootstrap:', 'seo-geo-migration-bridge' ); ?></strong>
						<?php echo esc_html( (string) ( $bootstrap_report['status'] ?? '' ) ); ?>
						—
						<code><?php echo esc_html( (string) ( $bootstrap_report['report_sha256'] ?? '' ) ); ?></code>
					</p>
				</div>
			<?php endif; ?>

			<?php if ( true !== ( $bootstrap_plan['ready'] ?? false ) ) : ?>
				<div class="notice notice-warning inline">
					<p><strong><?php echo esc_html__( 'Corporate bootstrap is not ready:', 'seo-geo-migration-bridge' ); ?></strong></p>
					<p><code><?php echo esc_html( implode( ', ', is_array( $bootstrap_plan['blockers'] ?? null ) ? $bootstrap_plan['blockers'] : array() ) ); ?></code></p>
				</div>
			<?php else : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="<?php echo esc_attr( self::BOOTSTRAP_ACTION ); ?>">
					<input type="hidden" name="confirm" value="bootstrap-corporate-theme">
					<?php wp_nonce_field( self::BOOTSTRAP_NONCE_ACTION ); ?>
					<label style="display:block;margin:1rem 0;">
						<input type="checkbox" name="confirm_identity" value="1" required>
						<?php echo esc_html__( 'I confirm that the current WordPress site title identifies the organization represented by this site.', 'seo-geo-migration-bridge' ); ?>
					</label>
					<p>
						<strong><?php echo esc_html__( 'Preset:', 'seo-geo-migration-bridge' ); ?></strong>
						<code>corporate</code>
						·
						<strong><?php echo esc_html__( 'Theme:', 'seo-geo-migration-bridge' ); ?></strong>
						<code><?php echo esc_html( CloneResetEngine::TARGET_THEME ); ?></code>
					</p>
					<?php submit_button( __( 'Apply Corporate bootstrap', 'seo-geo-migration-bridge' ), 'primary', 'submit', false ); ?>
				</form>
			<?php endif; ?>

			<h2><?php echo esc_html__( 'Step 4 — Create clean Corporate Home', 'seo-geo-migration-bridge' ); ?></h2>
			<p><?php echo esc_html__( 'Create a new private Home draft from Theme-owned Corporate patterns only. The old Divi/Elementor layout is not copied and Content Remap is not required.', 'seo-geo-migration-bridge' ); ?></p>

			<?php if ( 0 < (int) ( $clean_home_plan['existing_draft'] ?? 0 ) ) : ?>
				<?php $clean_home_draft_id = (int) $clean_home_plan['existing_draft']; ?>
				<div class="notice notice-success inline">
					<p>
						<strong><?php echo esc_html__( 'Clean Home draft ready.', 'seo-geo-migration-bridge' ); ?></strong>
						<?php echo esc_html__( 'It contains only Corporate preset structure; the current front page and its URL remain unchanged.', 'seo-geo-migration-bridge' ); ?>
					</p>
					<p>
						<a class="button button-primary" href="<?php echo esc_url( get_edit_post_link( $clean_home_draft_id, '' ) ?: '#' ); ?>"><?php echo esc_html__( 'Edit clean Home draft', 'seo-geo-migration-bridge' ); ?></a>
						<a class="button" href="<?php echo esc_url( get_preview_post_link( $clean_home_draft_id ) ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html__( 'Preview clean Home', 'seo-geo-migration-bridge' ); ?></a>
					</p>
				</div>
			<?php elseif ( true !== ( $clean_home_plan['ready'] ?? false ) ) : ?>
				<div class="notice notice-warning inline">
					<p><strong><?php echo esc_html__( 'Clean Home rebuild is not ready:', 'seo-geo-migration-bridge' ); ?></strong></p>
					<p><code><?php echo esc_html( implode( ', ', is_array( $clean_home_plan['blockers'] ?? null ) ? $clean_home_plan['blockers'] : array() ) ); ?></code></p>
				</div>
			<?php else : ?>
				<p>
					<strong><?php echo esc_html__( 'Source URL retained:', 'seo-geo-migration-bridge' ); ?></strong>
					<code><?php echo esc_html( (string) ( $clean_home_plan['source']['path'] ?? '/' ) ); ?></code>
					·
					<strong><?php echo esc_html__( 'Patterns:', 'seo-geo-migration-bridge' ); ?></strong>
					<?php echo esc_html( (string) count( is_array( $clean_home_plan['patterns'] ?? null ) ? $clean_home_plan['patterns'] : array() ) ); ?>
				</p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="<?php echo esc_attr( self::CLEAN_HOME_ACTION ); ?>">
					<input type="hidden" name="confirm" value="create-clean-home">
					<?php wp_nonce_field( self::CLEAN_HOME_NONCE_ACTION ); ?>
					<?php submit_button( __( 'Create clean Home draft', 'seo-geo-migration-bridge' ), 'primary', 'submit', false ); ?>
				</form>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Capture/refresh the manifest after explicit administrator confirmation.
	 */
	public function handle_capture(): never {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Administrator capability is required.', 'seo-geo-migration-bridge' ), '', array( 'response' => 403 ) );
		}

		check_admin_referer( self::NONCE_ACTION );

		$confirm = isset( $_POST['confirm'] ) ? sanitize_key( wp_unslash( $_POST['confirm'] ) ) : '';
		if ( 'capture-rescue-manifest' !== $confirm ) {
			wp_die( esc_html__( 'Rescue Manifest capture was not explicitly confirmed.', 'seo-geo-migration-bridge' ), '', array( 'response' => 400 ) );
		}

		$this->manifest->capture();
		$this->redirect( 'rescue-saved' );
	}

	/**
	 * Apply the clone runtime reset.
	 */
	public function handle_reset(): never {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Administrator capability is required.', 'seo-geo-migration-bridge' ), '', array( 'response' => 403 ) );
		}

		check_admin_referer( self::RESET_NONCE_ACTION );

		$confirm = isset( $_POST['confirm'] ) ? sanitize_key( wp_unslash( $_POST['confirm'] ) ) : '';
		if ( 'reset-clone-runtime' !== $confirm ) {
			wp_die( esc_html__( 'Clone reset was not explicitly confirmed.', 'seo-geo-migration-bridge' ), '', array( 'response' => 400 ) );
		}

		$raw_keep_plugins = filter_input( INPUT_POST, 'keep_plugins', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY );
		$raw_keep_plugins = is_array( $raw_keep_plugins ) ? $raw_keep_plugins : array();
		$keep_plugins     = array_values(
			array_filter(
				array_map(
					static fn( mixed $value ): string => is_string( $value ) ? sanitize_text_field( $value ) : '',
					$raw_keep_plugins
				)
			)
		);

		$result = $this->reset->apply( $keep_plugins );
		if ( $result instanceof WP_Error ) {
			wp_die( esc_html( $result->get_error_message() ), '', array( 'response' => 400 ) );
		}

		$this->redirect( 'clone-reset-' . sanitize_key( (string) ( $result['status'] ?? 'unknown' ) ) );
	}


	/**
	 * Apply the Corporate Theme bootstrap through Theme-owned setup.
	 */
	public function handle_bootstrap(): never {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Administrator capability is required.', 'seo-geo-migration-bridge' ), '', array( 'response' => 403 ) );
		}

		check_admin_referer( self::BOOTSTRAP_NONCE_ACTION );

		$confirm = isset( $_POST['confirm'] ) ? sanitize_key( wp_unslash( $_POST['confirm'] ) ) : '';
		if ( 'bootstrap-corporate-theme' !== $confirm ) {
			wp_die( esc_html__( 'Corporate bootstrap was not explicitly confirmed.', 'seo-geo-migration-bridge' ), '', array( 'response' => 400 ) );
		}

		$confirm_identity = '1' === filter_input( INPUT_POST, 'confirm_identity', FILTER_SANITIZE_NUMBER_INT );
		$result           = $this->bootstrap->apply( $confirm_identity );
		if ( $result instanceof WP_Error ) {
			wp_die( esc_html( $result->get_error_message() ), '', array( 'response' => 400 ) );
		}

		$this->redirect( 'corporate-bootstrap-' . sanitize_key( (string) ( $result['status'] ?? 'unknown' ) ) );
	}


	/**
	 * Create/reuse the private clean Corporate Home draft.
	 */
	public function handle_clean_home(): never {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Administrator capability is required.', 'seo-geo-migration-bridge' ), '', array( 'response' => 403 ) );
		}

		check_admin_referer( self::CLEAN_HOME_NONCE_ACTION );

		$confirm = isset( $_POST['confirm'] ) ? sanitize_key( wp_unslash( $_POST['confirm'] ) ) : '';
		if ( 'create-clean-home' !== $confirm ) {
			wp_die( esc_html__( 'Clean Home creation was not explicitly confirmed.', 'seo-geo-migration-bridge' ), '', array( 'response' => 400 ) );
		}

		$result = $this->clean_home->create_draft();
		if ( $result instanceof WP_Error ) {
			wp_die( esc_html( $result->get_error_message() ), '', array( 'response' => 400 ) );
		}

		$this->redirect( 'clean-home-' . sanitize_key( (string) ( $result['status'] ?? 'unknown' ) ) );
	}

	/**
	 * Redirect back to the Reset/Rebuild page.
	 *
	 * @param string $status Result status.
	 */
	private function redirect( string $status ): never {
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'                 => self::PAGE_SLUG,
					'seo_geo_reset_status' => sanitize_key( $status ),
				),
				admin_url( 'tools.php' )
			)
		);
		exit;
	}
}
