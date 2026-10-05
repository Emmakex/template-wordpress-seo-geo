<?php
/**
 * Administrator screen for the reusable Corporate inner-page pipeline.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge\Reset;

use SeoGeo\MigrationBridge\Plugin;
use WP_Error;

/**
 * Exposes draft, blueprint, hydration, SEO handoff and readiness for Corporate pages.
 */
final class AdminCorporatePagePipelineController {
	public const PAGE_SLUG = 'seo-geo-corporate-page-pipeline';

	private const CREATE_ACTION    = 'seo_geo_corporate_page_create';
	private const BLUEPRINT_ACTION = 'seo_geo_corporate_page_blueprint';
	private const HYDRATE_ACTION   = 'seo_geo_corporate_page_hydrate';
	private const ROLLBACK_ACTION  = 'seo_geo_corporate_page_rollback';
	private const SEO_ACTION       = 'seo_geo_corporate_page_seo';
	private const NONCE_ACTION     = 'seo_geo_corporate_page_pipeline';
	private const MAX_BLUEPRINT    = 65536;

	/**
	 * Corporate pages supported by the reusable static-page pipeline.
	 *
	 * @var list<string>
	 */
	private const PAGE_KEYS = array( 'services', 'work', 'about', 'contact' );

	/**
	 * Construct the administrator controller.
	 *
	 * @param CleanCorporatePageRebuilder $builder   Clean inner-page builder.
	 * @param CorporatePageContentKit     $kit       Reviewed content service.
	 * @param NativeCorporatePageHydrator $hydrator  Native content hydrator.
	 * @param CorporatePageSeoHandoff     $handoff   Native SEO handoff service.
	 * @param CorporatePageReadiness      $readiness Page readiness service.
	 */
	public function __construct(
		private CleanCorporatePageRebuilder $builder,
		private CorporatePageContentKit $kit,
		private NativeCorporatePageHydrator $hydrator,
		private CorporatePageSeoHandoff $handoff,
		private CorporatePageReadiness $readiness
	) {
	}

	/** Bootstrap the page from already accepted Plugin services. */
	public static function boot_from_plugin(): void {
		$manifest = Plugin::rescue_manifest();
		$reset    = Plugin::clone_reset_engine();
		$builder  = Plugin::clean_corporate_page_rebuilder();
		if ( ! $manifest instanceof RescueManifest || ! $reset instanceof CloneResetEngine || ! $builder instanceof CleanCorporatePageRebuilder ) {
			return;
		}

		$kit       = new CorporatePageContentKit();
		$hydrator  = new NativeCorporatePageHydrator( $builder, $kit );
		$handoff   = new CorporatePageSeoHandoff( $manifest, $builder, $hydrator );
		$readiness = new CorporatePageReadiness( $manifest, $reset, $builder, $hydrator, $handoff );
		( new self( $builder, $kit, $hydrator, $handoff, $readiness ) )->boot();
	}

	/** Register administrator hooks. */
	public function boot(): void {
		add_action( 'admin_menu', array( $this, 'register_page' ) );
		add_action( 'admin_post_' . self::CREATE_ACTION, array( $this, 'handle_create' ) );
		add_action( 'admin_post_' . self::BLUEPRINT_ACTION, array( $this, 'handle_blueprint' ) );
		add_action( 'admin_post_' . self::HYDRATE_ACTION, array( $this, 'handle_hydrate' ) );
		add_action( 'admin_post_' . self::ROLLBACK_ACTION, array( $this, 'handle_rollback' ) );
		add_action( 'admin_post_' . self::SEO_ACTION, array( $this, 'handle_seo' ) );
	}

	/** Register the Tools screen. */
	public function register_page(): void {
		add_management_page(
			__( 'SEO/GEO Corporate Page Pipeline', 'seo-geo-migration-bridge' ),
			__( 'SEO/GEO Page Pipeline', 'seo-geo-migration-bridge' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render' )
		);
	}

	/** Render the selected page pipeline. */
	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$page_key   = $this->requested_page_key();
		$plan       = $this->builder->plan( $page_key );
		$bound      = $this->builder->bound_source_id( $page_key );
		$candidates = $this->builder->source_candidates();
		$model      = $this->kit->model( $page_key );
		$model_name = $this->kit->model_name( $page_key );
		$saved_kit  = $this->kit->saved( $page_key );
		$hydration  = $this->hydrator->plan( $page_key );
		$draft_id   = (int) ( $plan['existing_draft'] ?? 0 );
		$seo_report = 0 < $draft_id ? $this->handoff->report( $draft_id ) : null;
		$ready      = $this->readiness->report( $page_key );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only administrator status message.
		$status = isset( $_GET['pipeline_status'] ) ? sanitize_key( wp_unslash( $_GET['pipeline_status'] ) ) : '';
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'SEO/GEO Corporate Page Pipeline', 'seo-geo-migration-bridge' ); ?></h1>
			<p><?php echo esc_html__( 'Finish each clean page before field testing: bind the rescued URL, import reviewed semantic content, hydrate native blocks, carry safe SEO signals and require readiness.', 'seo-geo-migration-bridge' ); ?></p>

			<?php if ( '' !== $status ) : ?>
				<div class="notice notice-success inline"><p><code><?php echo esc_html( $status ); ?></code></p></div>
			<?php endif; ?>

			<form method="get">
				<input type="hidden" name="page" value="<?php echo esc_attr( self::PAGE_SLUG ); ?>">
				<label for="seo-geo-page-key"><strong><?php echo esc_html__( 'Corporate page', 'seo-geo-migration-bridge' ); ?></strong></label>
				<select id="seo-geo-page-key" name="page_key">
					<?php foreach ( self::PAGE_KEYS as $candidate_key ) : ?>
						<option value="<?php echo esc_attr( $candidate_key ); ?>" <?php selected( $page_key, $candidate_key ); ?>><?php echo esc_html( ucfirst( $candidate_key ) ); ?></option>
					<?php endforeach; ?>
				</select>
				<?php submit_button( __( 'Open pipeline', 'seo-geo-migration-bridge' ), 'secondary', 'submit', false ); ?>
			</form>

			<hr>
			<h2><?php echo esc_html( strtoupper( $page_key ) ); ?></h2>
			<p><strong><?php echo esc_html__( 'Native model:', 'seo-geo-migration-bridge' ); ?></strong> <code><?php echo esc_html( '' !== $model_name ? $model_name : 'not-defined' ); ?></code></p>

			<h3><?php echo esc_html__( '1. Bind rescued source and create clean draft', 'seo-geo-migration-bridge' ); ?></h3>
			<p><strong><?php echo esc_html__( 'Plan:', 'seo-geo-migration-bridge' ); ?></strong> <?php echo true === ( $plan['ready'] ?? false ) ? '✅' : '⛔'; ?> <code><?php echo esc_html( implode( ', ', is_array( $plan['blockers'] ?? null ) ? $plan['blockers'] : array() ) ); ?></code></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="<?php echo esc_attr( self::CREATE_ACTION ); ?>">
				<input type="hidden" name="page_key" value="<?php echo esc_attr( $page_key ); ?>">
				<?php wp_nonce_field( self::NONCE_ACTION ); ?>
				<?php if ( 0 < $bound ) : ?>
					<input type="hidden" name="source_id" value="<?php echo esc_attr( (string) $bound ); ?>">
					<p><?php echo esc_html__( 'Source binding locked:', 'seo-geo-migration-bridge' ); ?> <code>#<?php echo esc_html( (string) $bound ); ?></code></p>
				<?php else : ?>
					<select name="source_id" required>
						<option value=""><?php echo esc_html__( 'Select rescued published source', 'seo-geo-migration-bridge' ); ?></option>
						<?php foreach ( $candidates as $candidate ) : ?>
							<option value="<?php echo esc_attr( (string) $candidate['id'] ); ?>"><?php echo esc_html( $candidate['title'] . ' — ' . $candidate['path'] ); ?></option>
						<?php endforeach; ?>
					</select>
				<?php endif; ?>
				<?php submit_button( 0 < $draft_id ? __( 'Reuse clean draft', 'seo-geo-migration-bridge' ) : __( 'Create clean draft', 'seo-geo-migration-bridge' ), 'primary', 'submit', false ); ?>
			</form>

			<h3><?php echo esc_html__( '2. Import portable Content Blueprint', 'seo-geo-migration-bridge' ); ?></h3>
			<?php if ( ! is_array( $model ) ) : ?>
				<div class="notice notice-warning inline"><p><?php echo esc_html__( 'This page does not have a native semantic model yet.', 'seo-geo-migration-bridge' ); ?></p></div>
			<?php elseif ( 0 >= $draft_id ) : ?>
				<p><?php echo esc_html__( 'Create the clean draft first.', 'seo-geo-migration-bridge' ); ?></p>
			<?php else : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="<?php echo esc_attr( self::BLUEPRINT_ACTION ); ?>">
					<input type="hidden" name="page_key" value="<?php echo esc_attr( $page_key ); ?>">
					<?php wp_nonce_field( self::NONCE_ACTION ); ?>
					<textarea name="blueprint_json" rows="14" class="large-text code" maxlength="<?php echo esc_attr( (string) self::MAX_BLUEPRINT ); ?>" placeholder="{ ... }"></textarea>
					<?php submit_button( __( 'Validate and import Blueprint', 'seo-geo-migration-bridge' ), 'primary', 'submit', false ); ?>
				</form>
			<?php endif; ?>
			<p><strong><?php echo esc_html__( 'Content Kit:', 'seo-geo-migration-bridge' ); ?></strong> <?php echo is_array( $saved_kit ) ? '✅' : '—'; ?></p>

			<h3><?php echo esc_html__( '3. Hydrate native draft', 'seo-geo-migration-bridge' ); ?></h3>
			<p><?php echo true === ( $hydration['ready'] ?? false ) ? '✅' : '⛔'; ?> <code><?php echo esc_html( implode( ', ', is_array( $hydration['blockers'] ?? null ) ? $hydration['blockers'] : array() ) ); ?></code></p>
			<?php $this->action_form( self::HYDRATE_ACTION, $page_key, __( 'Hydrate page', 'seo-geo-migration-bridge' ) ); ?>
			<?php $this->action_form( self::ROLLBACK_ACTION, $page_key, __( 'Rollback hydration', 'seo-geo-migration-bridge' ), 'secondary' ); ?>

			<h3><?php echo esc_html__( '4. Native SEO/GEO handoff', 'seo-geo-migration-bridge' ); ?></h3>
			<p><strong><?php echo esc_html__( 'Report:', 'seo-geo-migration-bridge' ); ?></strong> <?php echo is_array( $seo_report ) ? '✅' : '—'; ?></p>
			<?php $this->action_form( self::SEO_ACTION, $page_key, __( 'Apply safe SEO handoff', 'seo-geo-migration-bridge' ) ); ?>

			<h3><?php echo esc_html__( '5. Readiness', 'seo-geo-migration-bridge' ); ?></h3>
			<p><strong><?php echo esc_html__( 'Ready for browser QA:', 'seo-geo-migration-bridge' ); ?></strong> <?php echo true === ( $ready['ready_for_browser_qa'] ?? false ) ? '✅' : '⛔'; ?></p>
			<?php if ( array() !== ( $ready['blockers'] ?? array() ) ) : ?>
				<p><strong><?php echo esc_html__( 'Blockers:', 'seo-geo-migration-bridge' ); ?></strong> <code><?php echo esc_html( implode( ', ', $ready['blockers'] ) ); ?></code></p>
			<?php endif; ?>
			<?php if ( array() !== ( $ready['warnings'] ?? array() ) ) : ?>
				<p><strong><?php echo esc_html__( 'Warnings:', 'seo-geo-migration-bridge' ); ?></strong> <code><?php echo esc_html( implode( ', ', $ready['warnings'] ) ); ?></code></p>
			<?php endif; ?>
			<p><strong>SHA:</strong> <code><?php echo esc_html( (string) ( $ready['report_sha256'] ?? '' ) ); ?></code></p>
		</div>
		<?php
	}

	/** Create or reuse one clean page draft. */
	public function handle_create(): never {
		$this->authorize_request();
		check_admin_referer( self::NONCE_ACTION );

		$page_key  = $this->validated_page_key( isset( $_POST['page_key'] ) ? sanitize_key( wp_unslash( $_POST['page_key'] ) ) : '' );
		$source_id = isset( $_POST['source_id'] ) ? absint( wp_unslash( $_POST['source_id'] ) ) : 0;
		$result    = $this->builder->create_draft( $page_key, $source_id );
		$this->finish( $page_key, $result, 'draft' );
	}

	/** Validate and import one portable Content Blueprint. */
	public function handle_blueprint(): never {
		$this->authorize_request();
		check_admin_referer( self::NONCE_ACTION );

		$page_key = $this->validated_page_key( isset( $_POST['page_key'] ) ? sanitize_key( wp_unslash( $_POST['page_key'] ) ) : '' );
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON is decoded and then validated against a strict semantic blueprint contract.
		$raw = isset( $_POST['blueprint_json'] ) ? (string) wp_unslash( $_POST['blueprint_json'] ) : '';
		if ( '' === trim( $raw ) || self::MAX_BLUEPRINT < strlen( $raw ) ) {
			wp_die( esc_html__( 'Blueprint JSON is empty or exceeds the 64KB limit.', 'seo-geo-migration-bridge' ), '', array( 'response' => 400 ) );
		}

		$data = json_decode( $raw, true );
		if ( ! is_array( $data ) ) {
			wp_die( esc_html__( 'Blueprint JSON is invalid.', 'seo-geo-migration-bridge' ), '', array( 'response' => 400 ) );
		}

		$plan     = $this->builder->plan( $page_key );
		$draft_id = (int) ( $plan['existing_draft'] ?? 0 );
		$result   = $this->kit->import_blueprint( $page_key, $data, $draft_id, (string) ( $plan['plan_sha256'] ?? '' ) );
		$this->finish( $page_key, $result, 'blueprint' );
	}

	/** Hydrate one clean page draft. */
	public function handle_hydrate(): never {
		$this->authorize_request();
		check_admin_referer( self::NONCE_ACTION );

		$page_key = $this->validated_page_key( isset( $_POST['page_key'] ) ? sanitize_key( wp_unslash( $_POST['page_key'] ) ) : '' );
		$this->finish( $page_key, $this->hydrator->apply( $page_key ), 'hydration' );
	}

	/** Roll back one clean page hydration. */
	public function handle_rollback(): never {
		$this->authorize_request();
		check_admin_referer( self::NONCE_ACTION );

		$page_key = $this->validated_page_key( isset( $_POST['page_key'] ) ? sanitize_key( wp_unslash( $_POST['page_key'] ) ) : '' );
		$this->finish( $page_key, $this->hydrator->rollback( $page_key ), 'rollback' );
	}

	/** Apply safe native SEO signals. */
	public function handle_seo(): never {
		$this->authorize_request();
		check_admin_referer( self::NONCE_ACTION );

		$page_key = $this->validated_page_key( isset( $_POST['page_key'] ) ? sanitize_key( wp_unslash( $_POST['page_key'] ) ) : '' );
		$this->finish( $page_key, $this->handoff->apply( $page_key ), 'seo' );
	}

	/**
	 * Render one action-only form.
	 *
	 * @param string $action       Admin-post action.
	 * @param string $page_key     Corporate page key.
	 * @param string $label        Submit-button label.
	 * @param string $button_class WordPress submit-button class.
	 */
	private function action_form( string $action, string $page_key, string $label, string $button_class = 'primary' ): void {
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;margin-right:8px">
			<input type="hidden" name="action" value="<?php echo esc_attr( $action ); ?>">
			<input type="hidden" name="page_key" value="<?php echo esc_attr( $page_key ); ?>">
			<?php wp_nonce_field( self::NONCE_ACTION ); ?>
			<?php submit_button( $label, $button_class, 'submit', false ); ?>
		</form>
		<?php
	}

	/** Enforce administrator capability for a mutating request. */
	private function authorize_request(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Administrator capability is required.', 'seo-geo-migration-bridge' ), '', array( 'response' => 403 ) );
		}
	}

	/**
	 * Validate one supplied Corporate page key.
	 *
	 * @param string $page_key Candidate page key.
	 */
	private function validated_page_key( string $page_key ): string {
		if ( ! in_array( $page_key, self::PAGE_KEYS, true ) ) {
			wp_die( esc_html__( 'Unsupported Corporate page key.', 'seo-geo-migration-bridge' ), '', array( 'response' => 400 ) );
		}

		return $page_key;
	}

	/** Resolve the selected page key from the read-only query string. */
	private function requested_page_key(): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only administrator page selector.
		$page_key = isset( $_GET['page_key'] ) ? sanitize_key( wp_unslash( $_GET['page_key'] ) ) : 'services';

		return in_array( $page_key, self::PAGE_KEYS, true ) ? $page_key : 'services';
	}

	/**
	 * Redirect one action result or surface its error.
	 *
	 * @param string         $page_key Corporate page key.
	 * @param array|WP_Error $result   Action result.
	 * @param string         $action   Status action label.
	 */
	private function finish( string $page_key, array|WP_Error $result, string $action ): never {
		if ( $result instanceof WP_Error ) {
			wp_die( esc_html( $result->get_error_message() ), '', array( 'response' => 400 ) );
		}

		$status = sanitize_key( $action . '-' . (string) ( $result['status'] ?? 'ok' ) );
		$url    = add_query_arg(
			array(
				'page'            => self::PAGE_SLUG,
				'page_key'        => $page_key,
				'pipeline_status' => $status,
			),
			admin_url( 'tools.php' )
		);
		wp_safe_redirect( $url );
		exit;
	}
}
