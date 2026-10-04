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
	public const PAGE_SLUG                = 'seo-geo-reset-rebuild';
	public const ACTION                   = 'seo_geo_reset_capture_rescue_manifest';
	public const NONCE_ACTION             = 'seo_geo_reset_capture_rescue_manifest';
	public const RESET_ACTION             = 'seo_geo_reset_apply_clone_runtime';
	public const RESET_NONCE_ACTION       = 'seo_geo_reset_apply_clone_runtime';
	public const BOOTSTRAP_ACTION         = 'seo_geo_reset_apply_corporate_bootstrap';
	public const BOOTSTRAP_NONCE_ACTION   = 'seo_geo_reset_apply_corporate_bootstrap';
	public const CLEAN_HOME_ACTION        = 'seo_geo_reset_create_clean_home';
	public const CLEAN_HOME_NONCE_ACTION  = 'seo_geo_reset_create_clean_home';
	public const CONTENT_KIT_ACTION       = 'seo_geo_reset_save_home_content_kit';
	public const CONTENT_KIT_NONCE_ACTION = 'seo_geo_reset_save_home_content_kit';
	public const BLUEPRINT_ACTION         = 'seo_geo_reset_import_home_content_blueprint';
	public const BLUEPRINT_NONCE_ACTION   = 'seo_geo_reset_import_home_content_blueprint';
	public const MAX_BLUEPRINT_BYTES      = 65536;
	public const HYDRATE_ACTION           = 'seo_geo_reset_hydrate_home';
	public const HYDRATE_NONCE_ACTION     = 'seo_geo_reset_hydrate_home';
	public const ROLLBACK_ACTION          = 'seo_geo_reset_rollback_home_hydration';
	public const ROLLBACK_NONCE_ACTION    = 'seo_geo_reset_rollback_home_hydration';
	public const SEO_HANDOFF_ACTION       = 'seo_geo_reset_apply_home_seo_handoff';
	public const SEO_HANDOFF_NONCE_ACTION = 'seo_geo_reset_apply_home_seo_handoff';
	public const CLEAN_SERVICES_ACTION    = 'seo_geo_reset_create_clean_services';
	public const CLEAN_SERVICES_NONCE     = 'seo_geo_reset_create_clean_services';

	/**
	 * Construct the reset-first administrator controller.
	 *
	 * @param RescueManifest          $manifest    Rescue manifest service.
	 * @param CloneResetEngine        $reset       Clone reset service.
	 * @param CorporateThemeBootstrap $bootstrap   Corporate Theme bootstrap service.
	 * @param CleanHomeRebuilder          $clean_home  Clean Corporate Home draft builder.
	 * @param CleanCorporatePageRebuilder $clean_pages Clean Corporate inner-page builder.
	 * @param CorporateHomeContentKit     $content_kit Structured Home content service.
	 * @param NativeHomeHydrator      $hydrator    Native Home hydrator.
	 * @param HomeSeoHandoff          $seo_handoff Native SEO handoff service.
	 * @param HomePilotReadiness      $readiness   Field-pilot readiness gate.
	 */
	public function __construct(
		private RescueManifest $manifest,
		private CloneResetEngine $reset,
		private CorporateThemeBootstrap $bootstrap,
		private CleanHomeRebuilder $clean_home,
		private CleanCorporatePageRebuilder $clean_pages,
		private CorporateHomeContentKit $content_kit,
		private NativeHomeHydrator $hydrator,
		private HomeSeoHandoff $seo_handoff,
		private HomePilotReadiness $readiness
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
		add_action( 'admin_post_' . self::CONTENT_KIT_ACTION, array( $this, 'handle_content_kit' ) );
		add_action( 'admin_post_' . self::BLUEPRINT_ACTION, array( $this, 'handle_content_blueprint' ) );
		add_action( 'admin_post_' . self::HYDRATE_ACTION, array( $this, 'handle_hydrate_home' ) );
		add_action( 'admin_post_' . self::ROLLBACK_ACTION, array( $this, 'handle_rollback_home' ) );
		add_action( 'admin_post_' . self::SEO_HANDOFF_ACTION, array( $this, 'handle_home_seo_handoff' ) );
		add_action( 'admin_post_' . self::CLEAN_SERVICES_ACTION, array( $this, 'handle_clean_services' ) );
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

		$saved              = $this->manifest->saved();
		$plan               = $this->reset->plan();
		$report             = $this->reset->report();
		$bootstrap_plan     = $this->bootstrap->plan();
		$bootstrap_report   = $this->bootstrap->report();
		$clean_home_plan    = $this->clean_home->plan();
		$content_model      = $this->content_kit->model();
		$content_kit        = $this->content_kit->saved();
		$hydration_plan     = $this->hydrator->plan();
		$seo_handoff_plan   = $this->seo_handoff->plan();
		$seo_handoff_report = $this->seo_handoff->report( (int) ( $clean_home_plan['existing_draft'] ?? 0 ) );
		$readiness_report   = $this->readiness->report();
		$services_plan      = $this->clean_pages->plan( 'services' );
		$services_sources   = $this->clean_pages->source_candidates();
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
						<?php $clean_home_edit_link = get_edit_post_link( $clean_home_draft_id, '' ); ?>
						<a class="button button-primary" href="<?php echo esc_url( is_string( $clean_home_edit_link ) && '' !== $clean_home_edit_link ? $clean_home_edit_link : '#' ); ?>"><?php echo esc_html__( 'Edit clean Home draft', 'seo-geo-migration-bridge' ); ?></a>
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

			<?php $this->render_content_kit_step( $clean_home_plan, $content_model, $content_kit, $hydration_plan ); ?>
			<?php $this->render_home_seo_handoff_step( $seo_handoff_plan, $seo_handoff_report ); ?>
			<?php $this->render_home_pilot_readiness_step( $readiness_report ); ?>
			<?php $this->render_services_rebuild_step( $services_plan, $services_sources ); ?>
		</div>
		<?php
	}

	/**
	 * Render Step 5 — reviewed Content Kit + native hydration.
	 *
	 * @param array<string,mixed>      $clean_home_plan Clean Home plan.
	 * @param array<string,mixed>|null $content_model   Theme-owned content model.
	 * @param array<string,mixed>|null $content_kit     Saved Content Kit.
	 * @param array<string,mixed>      $hydration_plan  Native hydration plan.
	 */
	private function render_content_kit_step(
		array $clean_home_plan,
		?array $content_model,
		?array $content_kit,
		array $hydration_plan
	): void {
		$draft_id = (int) ( $clean_home_plan['existing_draft'] ?? 0 );
		$values   = is_array( $content_kit['values'] ?? null ) ? $content_kit['values'] : array();
		$groups   = is_array( $content_kit['verified_groups'] ?? null ) ? $content_kit['verified_groups'] : array();
		$slots    = is_array( $content_model['slots'] ?? null ) ? $content_model['slots'] : array();
		?>
		<h2><?php echo esc_html__( 'Step 5 — Home Content Kit + native hydration', 'seo-geo-migration-bridge' ); ?></h2>
		<p><?php echo esc_html__( 'Write reviewed content into semantic Corporate slots. No legacy layout is read. Evidence groups remain hidden until explicitly verified.', 'seo-geo-migration-bridge' ); ?></p>

		<?php if ( 0 >= $draft_id || ! is_array( $content_model ) ) : ?>
			<div class="notice notice-warning inline">
				<p><?php echo esc_html__( 'Create the clean Home draft first. The Content Kit is only available after Step 4.', 'seo-geo-migration-bridge' ); ?></p>
			</div>
			<?php return; ?>
		<?php endif; ?>

		<?php if ( is_array( $content_kit ) ) : ?>
			<div class="notice notice-success inline">
				<p>
					<strong><?php echo esc_html__( 'Saved Content Kit:', 'seo-geo-migration-bridge' ); ?></strong>
					<code><?php echo esc_html( (string) ( $content_kit['kit_sha256'] ?? '' ) ); ?></code>
				</p>
				<?php if ( 'blueprint' === ( $content_kit['input_mode'] ?? null ) ) : ?>
					<p>
						<strong><?php echo esc_html__( 'Imported Blueprint:', 'seo-geo-migration-bridge' ); ?></strong>
						<code><?php echo esc_html( (string) ( $content_kit['blueprint_sha256'] ?? '' ) ); ?></code>
					</p>
				<?php endif; ?>
			</div>
		<?php endif; ?>

		<h3><?php echo esc_html__( 'Import portable Content Blueprint', 'seo-geo-migration-bridge' ); ?></h3>
		<p><?php echo esc_html__( 'Paste reviewed JSON that contains only locale, semantic values and evidence flags. Draft IDs and plan hashes are bound to this clean Home during import, so a blueprint can move between environments without carrying server identity.', 'seo-geo-migration-bridge' ); ?></p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="<?php echo esc_attr( self::BLUEPRINT_ACTION ); ?>">
			<input type="hidden" name="confirm" value="import-home-content-blueprint">
			<input type="hidden" name="draft_id" value="<?php echo esc_attr( (string) $draft_id ); ?>">
			<input type="hidden" name="plan_sha256" value="<?php echo esc_attr( (string) ( $clean_home_plan['plan_sha256'] ?? '' ) ); ?>">
			<?php wp_nonce_field( self::BLUEPRINT_NONCE_ACTION ); ?>
			<textarea class="large-text code" rows="12" maxlength="<?php echo esc_attr( (string) self::MAX_BLUEPRINT_BYTES ); ?>" name="content_blueprint_json" required placeholder='{"schema_version":1,"mode":"corporate-home-content-blueprint","model":"corporate-home-v1","locale":"es_ES","values":{},"verified_groups":{"hero-proof":false,"proof":false,"case-study":false}}'></textarea>
			<p class="description"><?php echo esc_html__( 'The blueprint locale must match the active Corporate preset locale. Unknown slots/groups and runtime-bound fields are rejected.', 'seo-geo-migration-bridge' ); ?></p>
			<?php submit_button( __( 'Import reviewed Content Blueprint', 'seo-geo-migration-bridge' ), 'secondary', 'submit', false ); ?>
		</form>

		<h3><?php echo esc_html__( 'Edit Content Kit manually', 'seo-geo-migration-bridge' ); ?></h3>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="<?php echo esc_attr( self::CONTENT_KIT_ACTION ); ?>">
			<input type="hidden" name="confirm" value="save-home-content-kit">
			<input type="hidden" name="draft_id" value="<?php echo esc_attr( (string) $draft_id ); ?>">
			<input type="hidden" name="plan_sha256" value="<?php echo esc_attr( (string) ( $clean_home_plan['plan_sha256'] ?? '' ) ); ?>">
			<?php wp_nonce_field( self::CONTENT_KIT_NONCE_ACTION ); ?>

			<h3><?php echo esc_html__( 'Evidence verification', 'seo-geo-migration-bridge' ); ?></h3>
			<p><?php echo esc_html__( 'Only check a group when every claim in that group is backed by real evidence. Unchecked groups are omitted from the hydrated Home.', 'seo-geo-migration-bridge' ); ?></p>
			<?php foreach ( array( 'hero-proof', 'proof', 'case-study' ) as $group ) : ?>
				<label style="display:block;margin:.45rem 0;">
					<input type="checkbox" name="verified_groups[<?php echo esc_attr( $group ); ?>]" value="1" <?php checked( true === ( $groups[ $group ] ?? false ) ); ?>>
					<?php echo esc_html( ucwords( str_replace( '-', ' ', $group ) ) ); ?>
				</label>
			<?php endforeach; ?>

			<h3><?php echo esc_html__( 'Semantic content fields', 'seo-geo-migration-bridge' ); ?></h3>
			<table class="form-table" role="presentation">
				<tbody>
				<?php foreach ( $slots as $slot ) : ?>
					<?php
					if ( ! is_array( $slot ) || ! is_string( $slot['id'] ?? null ) || ! is_string( $slot['type'] ?? null ) ) {
						continue;
					}
					$slot_id       = $slot['id'];
					$type          = $slot['type'];
					$current_value = $values[ $slot_id ] ?? null;
					$required      = true === ( $slot['required'] ?? false );
					$verification  = is_string( $slot['verification_group'] ?? null ) ? $slot['verification_group'] : '';
					?>
					<tr>
						<th scope="row">
							<label for="seo-geo-slot-<?php echo esc_attr( $slot_id ); ?>"><?php echo esc_html( ucwords( str_replace( '-', ' ', $slot_id ) ) ); ?></label>
							<?php if ( $required ) : ?>
								<span aria-label="<?php echo esc_attr__( 'Required', 'seo-geo-migration-bridge' ); ?>"> *</span>
							<?php endif; ?>
						</th>
						<td>
							<?php if ( '' !== $verification ) : ?>
								<p class="description">
									<?php
									/* translators: %s: evidence verification group name. */
									echo esc_html( sprintf( __( 'Evidence group: %s', 'seo-geo-migration-bridge' ), $verification ) );
									?>
								</p>
							<?php endif; ?>
							<?php if ( 'link' === $type ) : ?>
								<?php $link = is_array( $current_value ) ? $current_value : array(); ?>
								<input id="seo-geo-slot-<?php echo esc_attr( $slot_id ); ?>" class="regular-text" type="text" name="content_values[<?php echo esc_attr( $slot_id ); ?>][label]" value="<?php echo esc_attr( (string) ( $link['label'] ?? '' ) ); ?>" placeholder="<?php echo esc_attr__( 'Link label', 'seo-geo-migration-bridge' ); ?>">
								<input class="regular-text" type="text" name="content_values[<?php echo esc_attr( $slot_id ); ?>][url]" value="<?php echo esc_attr( (string) ( $link['url'] ?? '' ) ); ?>" placeholder="/contact/">
							<?php elseif ( 'list' === $type ) : ?>
								<?php $items = is_array( $current_value ) ? array_values( array_map( 'strval', $current_value ) ) : array(); ?>
								<textarea id="seo-geo-slot-<?php echo esc_attr( $slot_id ); ?>" class="large-text" rows="4" name="content_lists[<?php echo esc_attr( $slot_id ); ?>]"><?php echo esc_textarea( implode( "\n", $items ) ); ?></textarea>
								<p class="description"><?php echo esc_html__( 'One verified item per line.', 'seo-geo-migration-bridge' ); ?></p>
							<?php else : ?>
								<textarea id="seo-geo-slot-<?php echo esc_attr( $slot_id ); ?>" class="large-text" rows="3" name="content_values[<?php echo esc_attr( $slot_id ); ?>]"><?php echo esc_textarea( is_string( $current_value ) ? $current_value : '' ); ?></textarea>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			<?php submit_button( __( 'Save reviewed Home Content Kit', 'seo-geo-migration-bridge' ) ); ?>
		</form>

		<?php if ( is_array( $content_kit ) ) : ?>
			<h3><?php echo esc_html__( 'Apply to clean Home draft', 'seo-geo-migration-bridge' ); ?></h3>
			<?php if ( true !== ( $hydration_plan['ready'] ?? false ) ) : ?>
				<div class="notice notice-warning inline">
					<p><strong><?php echo esc_html__( 'Hydration is blocked:', 'seo-geo-migration-bridge' ); ?></strong></p>
					<p><code><?php echo esc_html( implode( ', ', is_array( $hydration_plan['blockers'] ?? null ) ? $hydration_plan['blockers'] : array() ) ); ?></code></p>
				</div>
			<?php else : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;margin-right:.5rem;">
					<input type="hidden" name="action" value="<?php echo esc_attr( self::HYDRATE_ACTION ); ?>">
					<input type="hidden" name="confirm" value="hydrate-clean-home">
					<?php wp_nonce_field( self::HYDRATE_NONCE_ACTION ); ?>
					<?php submit_button( __( 'Hydrate clean Home draft', 'seo-geo-migration-bridge' ), 'primary', 'submit', false ); ?>
				</form>
			<?php endif; ?>

			<?php if ( NativeHomeHydrator::CONTENT_STATE === (string) get_post_meta( $draft_id, CleanHomeRebuilder::CONTENT_STATE_META, true ) ) : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;">
					<input type="hidden" name="action" value="<?php echo esc_attr( self::ROLLBACK_ACTION ); ?>">
					<input type="hidden" name="confirm" value="rollback-home-hydration">
					<?php wp_nonce_field( self::ROLLBACK_NONCE_ACTION ); ?>
					<?php submit_button( __( 'Restore preset scaffold', 'seo-geo-migration-bridge' ), 'secondary', 'submit', false ); ?>
				</form>
			<?php endif; ?>
		<?php endif; ?>
		<?php
	}

	/**
	 * Render Step 6 — native SEO/GEO handoff.
	 *
	 * @param array<string,mixed>      $plan   Handoff plan.
	 * @param array<string,mixed>|null $report Persisted handoff report.
	 */
	private function render_home_seo_handoff_step( array $plan, ?array $report ): void {
		?>
		<h2><?php echo esc_html__( 'Step 6 — Native SEO/GEO handoff', 'seo-geo-migration-bridge' ); ?></h2>
		<p><?php echo esc_html__( 'Translate safe rescued Yoast/Rank Math signals into provider-neutral Theme metadata. Custom canonicals and unresolved legacy templates are never copied automatically.', 'seo-geo-migration-bridge' ); ?></p>

		<?php if ( true !== ( $plan['ready'] ?? false ) ) : ?>
			<div class="notice notice-warning inline">
				<p><strong><?php echo esc_html__( 'SEO handoff is not ready:', 'seo-geo-migration-bridge' ); ?></strong></p>
				<p><code><?php echo esc_html( implode( ', ', is_array( $plan['blockers'] ?? null ) ? $plan['blockers'] : array() ) ); ?></code></p>
			</div>
		<?php else : ?>
			<p>
				<strong><?php echo esc_html__( 'Detected provider:', 'seo-geo-migration-bridge' ); ?></strong>
				<code><?php echo esc_html( (string) ( $plan['provider'] ?? 'none' ) ); ?></code>
				·
				<strong><?php echo esc_html__( 'Canonical:', 'seo-geo-migration-bridge' ); ?></strong>
				<code><?php echo esc_html( (string) ( $plan['canonical_strategy'] ?? 'native-self-canonical' ) ); ?></code>
			</p>

			<?php if ( true === ( $plan['review_required'] ?? false ) ) : ?>
				<div class="notice notice-warning inline">
					<p><strong><?php echo esc_html__( 'Manual SEO review required before cutover:', 'seo-geo-migration-bridge' ); ?></strong></p>
					<p><code><?php echo esc_html( implode( ', ', is_array( $plan['review_items'] ?? null ) ? $plan['review_items'] : array() ) ); ?></code></p>
				</div>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="<?php echo esc_attr( self::SEO_HANDOFF_ACTION ); ?>">
				<input type="hidden" name="confirm" value="apply-home-seo-handoff">
				<?php wp_nonce_field( self::SEO_HANDOFF_NONCE_ACTION ); ?>
				<?php submit_button( __( 'Apply safe native SEO handoff', 'seo-geo-migration-bridge' ), 'primary', 'submit', false ); ?>
			</form>
		<?php endif; ?>

		<?php if ( is_array( $report ) ) : ?>
			<div class="notice <?php echo true === ( $report['cutover_seo_ready'] ?? false ) ? 'notice-success' : 'notice-warning'; ?> inline">
				<p>
					<strong><?php echo esc_html__( 'SEO handoff report:', 'seo-geo-migration-bridge' ); ?></strong>
					<code><?php echo esc_html( (string) ( $report['report_sha256'] ?? '' ) ); ?></code>
					—
					<?php echo true === ( $report['cutover_seo_ready'] ?? false ) ? esc_html__( 'SEO-ready', 'seo-geo-migration-bridge' ) : esc_html__( 'review required', 'seo-geo-migration-bridge' ); ?>
				</p>
			</div>
		<?php endif; ?>
		<?php
	}

	/**
	 * Render Step 7 — field-pilot readiness gate.
	 *
	 * @param array<string,mixed> $report Read-only readiness report.
	 */
	private function render_home_pilot_readiness_step( array $report ): void {
		$ready    = true === ( $report['ready_for_browser_qa'] ?? false );
		$blockers = is_array( $report['blockers'] ?? null ) ? $report['blockers'] : array();
		$warnings = is_array( $report['warnings'] ?? null ) ? $report['warnings'] : array();
		$manual   = is_array( $report['manual_browser_checks'] ?? null ) ? $report['manual_browser_checks'] : array();
		?>
		<h2><?php echo esc_html__( 'Step 7 — Field-pilot readiness', 'seo-geo-migration-bridge' ); ?></h2>
		<p><?php echo esc_html__( 'Read-only preflight before browser QA. It does not replace visual, accessibility or performance review and it never changes the current front page.', 'seo-geo-migration-bridge' ); ?></p>

		<div class="notice <?php echo $ready ? 'notice-success' : 'notice-warning'; ?> inline">
			<p>
				<strong><?php echo esc_html__( 'Machine preflight:', 'seo-geo-migration-bridge' ); ?></strong>
				<?php echo $ready ? esc_html__( 'ready for browser QA', 'seo-geo-migration-bridge' ) : esc_html__( 'blocked', 'seo-geo-migration-bridge' ); ?>
				—
				<code><?php echo esc_html( (string) ( $report['report_sha256'] ?? '' ) ); ?></code>
			</p>
		</div>

		<?php if ( array() !== $blockers ) : ?>
			<p><strong><?php echo esc_html__( 'Blocking findings:', 'seo-geo-migration-bridge' ); ?></strong></p>
			<ul>
				<?php foreach ( $blockers as $blocker ) : ?>
					<li><code><?php echo esc_html( (string) $blocker ); ?></code></li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>

		<?php if ( array() !== $warnings ) : ?>
			<p><strong><?php echo esc_html__( 'Review warnings:', 'seo-geo-migration-bridge' ); ?></strong></p>
			<ul>
				<?php foreach ( $warnings as $warning ) : ?>
					<li><code><?php echo esc_html( (string) $warning ); ?></code></li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>

		<p><strong><?php echo esc_html__( 'Browser QA still required:', 'seo-geo-migration-bridge' ); ?></strong></p>
		<ul>
			<?php foreach ( $manual as $check ) : ?>
				<li><?php echo esc_html( (string) $check ); ?></li>
			<?php endforeach; ?>
		</ul>
		<?php
	}

	/**
	 * Render Step 8 — clean Services scaffold.
	 *
	 * @param array<string,mixed> $plan       Current Services plan.
	 * @param array               $candidates Rescued published page candidates.
	 * @phpstan-param list<array{id:int,title:string,slug:string,path:string}> $candidates
	 */
	private function render_services_rebuild_step( array $plan, array $candidates ): void {
		$existing_draft = (int) ( $plan['existing_draft'] ?? 0 );
		$bound_source   = (int) ( $plan['source']['id'] ?? 0 );
		?>
		<h2><?php echo esc_html__( 'Step 8 — Start Services rebuild', 'seo-geo-migration-bridge' ); ?></h2>
		<p><?php echo esc_html__( 'Map the Corporate Services page to one rescued published page explicitly. The source URL/content stays untouched; the new page is a private native-block draft with no legacy layout input.', 'seo-geo-migration-bridge' ); ?></p>

		<?php if ( 0 < $bound_source ) : ?>
			<div class="notice <?php echo true === ( $plan['ready'] ?? false ) ? 'notice-success' : 'notice-warning'; ?> inline">
				<p>
					<strong><?php echo esc_html__( 'Bound source:', 'seo-geo-migration-bridge' ); ?></strong>
					<code><?php echo esc_html( (string) ( $plan['source']['path'] ?? '' ) ); ?></code>
					—
					<?php echo esc_html( (string) ( $plan['source']['title'] ?? '' ) ); ?>
				</p>
				<?php if ( 0 < $existing_draft ) : ?>
					<p><strong><?php echo esc_html__( 'Clean Services draft:', 'seo-geo-migration-bridge' ); ?></strong> <code>#<?php echo esc_html( (string) $existing_draft ); ?></code></p>
				<?php endif; ?>
			</div>
		<?php endif; ?>

		<?php if ( true !== ( $plan['ready'] ?? false ) && array() !== ( $plan['blockers'] ?? array() ) ) : ?>
			<p><strong><?php echo esc_html__( 'Current plan:', 'seo-geo-migration-bridge' ); ?></strong> <code><?php echo esc_html( implode( ', ', is_array( $plan['blockers'] ?? null ) ? $plan['blockers'] : array() ) ); ?></code></p>
		<?php endif; ?>

		<?php if ( array() === $candidates ) : ?>
			<div class="notice notice-warning inline">
				<p><?php echo esc_html__( 'No rescued published inner pages are available for explicit Services mapping.', 'seo-geo-migration-bridge' ); ?></p>
			</div>
			<?php return; ?>
		<?php endif; ?>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="<?php echo esc_attr( self::CLEAN_SERVICES_ACTION ); ?>">
			<input type="hidden" name="confirm" value="create-clean-services">
			<?php wp_nonce_field( self::CLEAN_SERVICES_NONCE ); ?>
			<label for="seo-geo-services-source"><strong><?php echo esc_html__( 'Rescued Services source', 'seo-geo-migration-bridge' ); ?></strong></label>
			<select id="seo-geo-services-source" name="source_id" required>
				<option value=""><?php echo esc_html__( 'Select the existing page whose URL must be preserved', 'seo-geo-migration-bridge' ); ?></option>
				<?php foreach ( $candidates as $candidate ) : ?>
					<option value="<?php echo esc_attr( (string) $candidate['id'] ); ?>" <?php selected( $bound_source, $candidate['id'] ); ?>>
						<?php echo esc_html( $candidate['title'] . ' — ' . $candidate['path'] ); ?>
					</option>
				<?php endforeach; ?>
			</select>
			<p class="description"><?php echo esc_html__( 'Selection is explicit: preset slugs never overwrite or guess the client URL.', 'seo-geo-migration-bridge' ); ?></p>
			<?php submit_button( 0 < $existing_draft ? __( 'Reuse clean Services draft', 'seo-geo-migration-bridge' ) : __( 'Create clean Services draft', 'seo-geo-migration-bridge' ), 'primary', 'submit', false ); ?>
		</form>
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
	 * Create/reuse the clean Corporate Services draft from an explicit rescued source.
	 */
	public function handle_clean_services(): never {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Administrator capability is required.', 'seo-geo-migration-bridge' ), '', array( 'response' => 403 ) );
		}

		check_admin_referer( self::CLEAN_SERVICES_NONCE );

		$confirm = isset( $_POST['confirm'] ) ? sanitize_key( wp_unslash( $_POST['confirm'] ) ) : '';
		if ( 'create-clean-services' !== $confirm ) {
			wp_die( esc_html__( 'Clean Services rebuild was not explicitly confirmed.', 'seo-geo-migration-bridge' ), '', array( 'response' => 400 ) );
		}

		$source_id = isset( $_POST['source_id'] ) ? absint( wp_unslash( $_POST['source_id'] ) ) : 0;
		if ( 0 >= $source_id ) {
			wp_die( esc_html__( 'A rescued Services source page must be selected.', 'seo-geo-migration-bridge' ), '', array( 'response' => 400 ) );
		}

		$result = $this->clean_pages->create_draft( 'services', $source_id );
		if ( $result instanceof WP_Error ) {
			wp_die( esc_html( $result->get_error_message() ), '', array( 'response' => 400 ) );
		}

		$this->redirect( 'clean-services-' . sanitize_key( (string) ( $result['status'] ?? 'unknown' ) ) );
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
	 * Import a portable reviewed Corporate Home Content Blueprint.
	 */
	public function handle_content_blueprint(): never {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Administrator capability is required.', 'seo-geo-migration-bridge' ), '', array( 'response' => 403 ) );
		}

		check_admin_referer( self::BLUEPRINT_NONCE_ACTION );

		$confirm = filter_input( INPUT_POST, 'confirm', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		if ( 'import-home-content-blueprint' !== $confirm ) {
			wp_die( esc_html__( 'Home Content Blueprint import was not explicitly confirmed.', 'seo-geo-migration-bridge' ), '', array( 'response' => 400 ) );
		}

		$raw_blueprint = filter_input( INPUT_POST, 'content_blueprint_json', FILTER_UNSAFE_RAW );
		$raw_blueprint = is_string( $raw_blueprint ) ? trim( $raw_blueprint ) : '';
		if ( '' === $raw_blueprint || self::MAX_BLUEPRINT_BYTES < strlen( $raw_blueprint ) ) {
			wp_die( esc_html__( 'Content Blueprint JSON is empty or exceeds the allowed size.', 'seo-geo-migration-bridge' ), '', array( 'response' => 400 ) );
		}

		try {
			$blueprint = json_decode( $raw_blueprint, true, 64, JSON_THROW_ON_ERROR );
		} catch ( \JsonException $exception ) {
			wp_die( esc_html__( 'Content Blueprint JSON is invalid.', 'seo-geo-migration-bridge' ), '', array( 'response' => 400 ) );
		}

		if ( ! is_array( $blueprint ) ) {
			wp_die( esc_html__( 'Content Blueprint must decode to a JSON object.', 'seo-geo-migration-bridge' ), '', array( 'response' => 400 ) );
		}

		$result = $this->content_kit->import_blueprint(
			$blueprint,
			(int) filter_input( INPUT_POST, 'draft_id', FILTER_SANITIZE_NUMBER_INT ),
			(string) filter_input( INPUT_POST, 'plan_sha256', FILTER_SANITIZE_FULL_SPECIAL_CHARS )
		);
		if ( $result instanceof WP_Error ) {
			wp_die( esc_html( $result->get_error_message() ), '', array( 'response' => 400 ) );
		}

		$this->redirect( 'home-content-blueprint-imported' );
	}

	/**
	 * Save the reviewed Corporate Home Content Kit.
	 */
	public function handle_content_kit(): never {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Administrator capability is required.', 'seo-geo-migration-bridge' ), '', array( 'response' => 403 ) );
		}

		check_admin_referer( self::CONTENT_KIT_NONCE_ACTION );

		$confirm = filter_input( INPUT_POST, 'confirm', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		if ( 'save-home-content-kit' !== $confirm ) {
			wp_die( esc_html__( 'Home Content Kit save was not explicitly confirmed.', 'seo-geo-migration-bridge' ), '', array( 'response' => 400 ) );
		}

		$raw_values = filter_input( INPUT_POST, 'content_values', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY );
		$raw_lists  = filter_input( INPUT_POST, 'content_lists', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY );
		$raw_groups = filter_input( INPUT_POST, 'verified_groups', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY );
		$values     = is_array( $raw_values ) ? $raw_values : array();
		$lists      = is_array( $raw_lists ) ? $raw_lists : array();

		foreach ( $lists as $slot_id => $raw_list ) {
			if ( ! is_string( $slot_id ) || ! is_string( $raw_list ) ) {
				continue;
			}
			$list_items         = preg_split( '/\r\n|\r|\n/', (string) $raw_list );
			$values[ $slot_id ] = is_array( $list_items ) ? $list_items : array();
		}

		$groups = array();
		foreach ( array( 'hero-proof', 'proof', 'case-study' ) as $group ) {
			$groups[ $group ] = is_array( $raw_groups ) && '1' === (string) ( $raw_groups[ $group ] ?? '' );
		}

		$candidate = array(
			'draft_id'        => (int) filter_input( INPUT_POST, 'draft_id', FILTER_SANITIZE_NUMBER_INT ),
			'plan_sha256'     => (string) filter_input( INPUT_POST, 'plan_sha256', FILTER_SANITIZE_FULL_SPECIAL_CHARS ),
			'values'          => $values,
			'verified_groups' => $groups,
		);

		$result = $this->content_kit->save( $candidate );
		if ( $result instanceof WP_Error ) {
			wp_die( esc_html( $result->get_error_message() ), '', array( 'response' => 400 ) );
		}

		$this->redirect( 'home-content-kit-saved' );
	}

	/**
	 * Hydrate the clean Home draft from the reviewed Content Kit.
	 */
	public function handle_hydrate_home(): never {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Administrator capability is required.', 'seo-geo-migration-bridge' ), '', array( 'response' => 403 ) );
		}

		check_admin_referer( self::HYDRATE_NONCE_ACTION );

		$confirm = filter_input( INPUT_POST, 'confirm', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		if ( 'hydrate-clean-home' !== $confirm ) {
			wp_die( esc_html__( 'Home hydration was not explicitly confirmed.', 'seo-geo-migration-bridge' ), '', array( 'response' => 400 ) );
		}

		$result = $this->hydrator->apply();
		if ( $result instanceof WP_Error ) {
			wp_die( esc_html( $result->get_error_message() ), '', array( 'response' => 400 ) );
		}

		$this->redirect( 'home-hydration-' . sanitize_key( (string) ( $result['status'] ?? 'unknown' ) ) );
	}

	/**
	 * Apply safe rescued SEO signals to the native clean Home.
	 */
	public function handle_home_seo_handoff(): never {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Administrator capability is required.', 'seo-geo-migration-bridge' ), '', array( 'response' => 403 ) );
		}

		check_admin_referer( self::SEO_HANDOFF_NONCE_ACTION );

		$confirm = filter_input( INPUT_POST, 'confirm', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		if ( 'apply-home-seo-handoff' !== $confirm ) {
			wp_die( esc_html__( 'Home SEO handoff was not explicitly confirmed.', 'seo-geo-migration-bridge' ), '', array( 'response' => 400 ) );
		}

		$result = $this->seo_handoff->apply();
		if ( $result instanceof WP_Error ) {
			wp_die( esc_html( $result->get_error_message() ), '', array( 'response' => 400 ) );
		}

		$this->redirect( 'home-seo-handoff-' . sanitize_key( (string) ( $result['status'] ?? 'unknown' ) ) );
	}

	/**
	 * Roll the clean Home draft back to the original preset scaffold.
	 */
	public function handle_rollback_home(): never {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Administrator capability is required.', 'seo-geo-migration-bridge' ), '', array( 'response' => 403 ) );
		}

		check_admin_referer( self::ROLLBACK_NONCE_ACTION );

		$confirm = filter_input( INPUT_POST, 'confirm', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		if ( 'rollback-home-hydration' !== $confirm ) {
			wp_die( esc_html__( 'Home hydration rollback was not explicitly confirmed.', 'seo-geo-migration-bridge' ), '', array( 'response' => 400 ) );
		}

		$result = $this->hydrator->rollback();
		if ( $result instanceof WP_Error ) {
			wp_die( esc_html( $result->get_error_message() ), '', array( 'response' => 400 ) );
		}

		$this->redirect( 'home-hydration-rolled-back' );
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
