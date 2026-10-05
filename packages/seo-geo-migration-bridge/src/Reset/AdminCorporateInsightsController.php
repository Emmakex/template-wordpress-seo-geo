<?php
/**
 * Administrator screen for the native Corporate Insights index.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge\Reset;

use SeoGeo\MigrationBridge\Plugin;
use WP_Error;

/**
 * Exposes explicit reversible Insights/posts-index assignment.
 */
final class AdminCorporateInsightsController {
	public const PAGE_SLUG = 'seo-geo-corporate-insights';

	private const APPLY_ACTION    = 'seo_geo_corporate_insights_apply';
	private const ROLLBACK_ACTION = 'seo_geo_corporate_insights_rollback';
	private const NONCE_ACTION    = 'seo_geo_corporate_insights';

	/**
	 * Construct the Insights controller.
	 *
	 * @param CorporateInsightsManager    $manager Insights manager.
	 * @param CleanCorporatePageRebuilder $builder Clean Corporate page builder.
	 */
	public function __construct(
		private CorporateInsightsManager $manager,
		private CleanCorporatePageRebuilder $builder
	) {
	}

	/** Bootstrap from accepted Plugin services. */
	public static function boot_from_plugin(): void {
		$manifest = Plugin::rescue_manifest();
		$builder  = Plugin::clean_corporate_page_rebuilder();
		if ( ! $manifest instanceof RescueManifest || ! $builder instanceof CleanCorporatePageRebuilder ) {
			return;
		}

		( new self( new CorporateInsightsManager( $manifest ), $builder ) )->boot();
	}

	/** Register hooks. */
	public function boot(): void {
		add_action( 'admin_menu', array( $this, 'register_page' ) );
		add_action( 'admin_post_' . self::APPLY_ACTION, array( $this, 'handle_apply' ) );
		add_action( 'admin_post_' . self::ROLLBACK_ACTION, array( $this, 'handle_rollback' ) );
	}

	/** Register the Tools page. */
	public function register_page(): void {
		add_management_page(
			__( 'SEO/GEO Insights Index', 'seo-geo-migration-bridge' ),
			__( 'SEO/GEO Insights', 'seo-geo-migration-bridge' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render' )
		);
	}

	/** Render current plan/readiness and explicit actions. */
	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$bound      = (int) get_option( CorporateInsightsManager::SOURCE_OPTION, 0 );
		$plan       = $this->manager->plan();
		$readiness  = $this->manager->readiness();
		$candidates = $this->builder->source_candidates();
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only administrator status message.
		$status = isset( $_GET['insights_status'] ) ? sanitize_key( wp_unslash( $_GET['insights_status'] ) ) : '';
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'SEO/GEO Native Insights Index', 'seo-geo-migration-bridge' ); ?></h1>
			<p><?php echo esc_html__( 'Reuse the rescued Insights URL as WordPress posts page. The Theme renders a native Query Loop; legacy page-builder content remains stored but is not rendered.', 'seo-geo-migration-bridge' ); ?></p>

			<?php if ( '' !== $status ) : ?>
				<div class="notice notice-success inline"><p><code><?php echo esc_html( $status ); ?></code></p></div>
			<?php endif; ?>

			<p><strong><?php echo esc_html__( 'Plan:', 'seo-geo-migration-bridge' ); ?></strong> <?php echo true === ( $plan['ready'] ?? false ) ? '✅' : '⛔'; ?> <code><?php echo esc_html( implode( ', ', is_array( $plan['blockers'] ?? null ) ? $plan['blockers'] : array() ) ); ?></code></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="<?php echo esc_attr( self::APPLY_ACTION ); ?>">
				<?php wp_nonce_field( self::NONCE_ACTION ); ?>
				<?php if ( 0 < $bound ) : ?>
					<input type="hidden" name="source_id" value="<?php echo esc_attr( (string) $bound ); ?>">
					<p><?php echo esc_html__( 'Insights source binding locked:', 'seo-geo-migration-bridge' ); ?> <code>#<?php echo esc_html( (string) $bound ); ?></code></p>
				<?php else : ?>
					<select name="source_id" required>
						<option value=""><?php echo esc_html__( 'Select rescued Insights page', 'seo-geo-migration-bridge' ); ?></option>
						<?php foreach ( $candidates as $candidate ) : ?>
							<option value="<?php echo esc_attr( (string) $candidate['id'] ); ?>"><?php echo esc_html( $candidate['title'] . ' — ' . $candidate['path'] ); ?></option>
						<?php endforeach; ?>
					</select>
				<?php endif; ?>
				<?php submit_button( __( 'Apply native Insights index', 'seo-geo-migration-bridge' ), 'primary', 'submit', false ); ?>
			</form>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="<?php echo esc_attr( self::ROLLBACK_ACTION ); ?>">
				<?php wp_nonce_field( self::NONCE_ACTION ); ?>
				<?php submit_button( __( 'Rollback Insights assignment', 'seo-geo-migration-bridge' ), 'secondary', 'submit', false ); ?>
			</form>

			<h2><?php echo esc_html__( 'Readiness', 'seo-geo-migration-bridge' ); ?></h2>
			<p><strong><?php echo esc_html__( 'Ready for browser QA:', 'seo-geo-migration-bridge' ); ?></strong> <?php echo true === ( $readiness['ready_for_browser_qa'] ?? false ) ? '✅' : '⛔'; ?></p>
			<?php if ( array() !== ( $readiness['blockers'] ?? array() ) ) : ?>
				<p><strong><?php echo esc_html__( 'Blockers:', 'seo-geo-migration-bridge' ); ?></strong> <code><?php echo esc_html( implode( ', ', $readiness['blockers'] ) ); ?></code></p>
			<?php endif; ?>
			<p><strong>SHA:</strong> <code><?php echo esc_html( (string) ( $readiness['report_sha256'] ?? '' ) ); ?></code></p>
		</div>
		<?php
	}

	/** Apply explicit Insights source. */
	public function handle_apply(): never {
		$this->authorize();
		check_admin_referer( self::NONCE_ACTION );
		$source_id = isset( $_POST['source_id'] ) ? absint( wp_unslash( $_POST['source_id'] ) ) : 0;
		$result    = $this->manager->apply( $source_id );
		$this->finish( $result, 'apply' );
	}

	/** Roll back Insights assignment. */
	public function handle_rollback(): never {
		$this->authorize();
		check_admin_referer( self::NONCE_ACTION );
		$this->finish( $this->manager->rollback(), 'rollback' );
	}

	/** Enforce administrator capability. */
	private function authorize(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Administrator capability is required.', 'seo-geo-migration-bridge' ), '', array( 'response' => 403 ) );
		}
	}

	/**
	 * Redirect an action result or surface its error.
	 *
	 * @param array<string,mixed>|WP_Error $result Action result.
	 * @param string                       $action Action identifier.
	 */
	private function finish( array|WP_Error $result, string $action ): never {
		if ( $result instanceof WP_Error ) {
			wp_die( esc_html( $result->get_error_message() ), '', array( 'response' => 400 ) );
		}
		$status = sanitize_key( $action . '-' . (string) ( $result['status'] ?? 'ok' ) );
		$url    = add_query_arg(
			array(
				'page'            => self::PAGE_SLUG,
				'insights_status' => $status,
			),
			admin_url( 'tools.php' )
		);
		wp_safe_redirect( $url );
		exit;
	}
}
