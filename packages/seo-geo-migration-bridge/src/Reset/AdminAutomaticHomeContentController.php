<?php
/**
 * Automatic Home content operator flow.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge\Reset;

use SeoGeo\MigrationBridge\Plugin;
use WP_Error;

/**
 * Adds the recommended automatic rescue-to-Home path to Reset & Rebuild.
 */
final class AdminAutomaticHomeContentController {
	private const ACTION       = 'seo_geo_auto_home_content';
	private const NONCE_ACTION = 'seo_geo_auto_home_content';

	/** Construct the operator controller. */
	public function __construct(
		private AutomaticHomeContentKit $automatic,
		private CleanHomeRebuilder $builder
	) {
	}

	/** Bootstrap from the accepted Plugin service graph. */
	public static function boot_from_plugin(): void {
		$manifest = Plugin::rescue_manifest();
		$builder  = Plugin::clean_home_rebuilder();
		$kit      = Plugin::corporate_home_content_kit();
		$hydrator = Plugin::native_home_hydrator();
		if (
			! $manifest instanceof RescueManifest
			|| ! $builder instanceof CleanHomeRebuilder
			|| ! $kit instanceof CorporateHomeContentKit
			|| ! $hydrator instanceof NativeHomeHydrator
		) {
			return;
		}

		( new self( new AutomaticHomeContentKit( $manifest, $builder, $kit, $hydrator ), $builder ) )->boot();
	}

	/** Register the notice and mutation action. */
	public function boot(): void {
		add_action( 'admin_notices', array( $this, 'render_notice' ) );
		add_action( 'admin_post_' . self::ACTION, array( $this, 'handle_apply' ) );
	}

	/** Render the recommended automatic content path on Reset & Rebuild only. */
	public function render_notice(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$screen = get_current_screen();
		if ( null === $screen || 'tools_page_' . AdminRescueManifestController::PAGE_SLUG !== $screen->id ) {
			return;
		}

		$home_plan = $this->builder->plan();
		$draft_id  = (int) ( $home_plan['existing_draft'] ?? 0 );
		if ( 0 >= $draft_id ) {
			return;
		}

		$plan = $this->automatic->plan();
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only status returned by our own redirect.
		$status = isset( $_GET['seo_geo_auto_home_status'] ) ? sanitize_key( wp_unslash( $_GET['seo_geo_auto_home_status'] ) ) : '';
		?>
		<div class="notice <?php echo true === ( $plan['ready'] ?? false ) ? 'notice-info' : 'notice-warning'; ?>">
			<p><strong><?php echo esc_html__( 'Recommended: Automatic Home Content', 'seo-geo-migration-bridge' ); ?></strong></p>
			<?php if ( 'applied' === $status ) : ?>
				<p><strong><?php echo esc_html__( 'Automatic content generated and hydrated successfully.', 'seo-geo-migration-bridge' ); ?></strong> <?php echo esc_html__( 'Evidence sections remain disabled until explicitly verified.', 'seo-geo-migration-bridge' ); ?></p>
				<?php $preview = get_preview_post_link( $draft_id ); ?>
				<?php if ( is_string( $preview ) && '' !== $preview ) : ?>
					<p><a class="button button-primary" href="<?php echo esc_url( $preview ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html__( 'Preview generated Home', 'seo-geo-migration-bridge' ); ?></a></p>
				<?php endif; ?>
			<?php elseif ( true === ( $plan['ready'] ?? false ) ) : ?>
				<p><?php echo esc_html__( 'The plugin can now read the preserved Home and related pages, extract useful authored text, map it to the Corporate semantic model and hydrate the clean draft automatically. Legacy builder layout is never executed or copied.', 'seo-geo-migration-bridge' ); ?></p>
				<p>
					<?php echo esc_html__( 'Detected:', 'seo-geo-migration-bridge' ); ?>
					<strong><?php echo esc_html( (string) (int) ( $plan['detected']['sentences'] ?? 0 ) ); ?></strong> <?php echo esc_html__( 'content units', 'seo-geo-migration-bridge' ); ?> ·
					<strong><?php echo esc_html( (string) (int) ( $plan['detected']['capabilities'] ?? 0 ) ); ?></strong> <?php echo esc_html__( 'capabilities', 'seo-geo-migration-bridge' ); ?> ·
					<?php echo esc_html__( 'contact target', 'seo-geo-migration-bridge' ); ?> <code><?php echo esc_html( (string) ( $plan['detected']['contact_path'] ?? '' ) ); ?></code>
				</p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION ); ?>">
					<input type="hidden" name="confirm" value="generate-and-hydrate-home">
					<?php wp_nonce_field( self::NONCE_ACTION ); ?>
					<?php submit_button( __( 'Generate and hydrate Home automatically', 'seo-geo-migration-bridge' ), 'primary', 'submit', false ); ?>
				</form>
				<p class="description"><?php echo esc_html__( 'Manual Content Kit fields below are advanced fallback/editing tools, not the normal migration path.', 'seo-geo-migration-bridge' ); ?></p>
			<?php else : ?>
				<p><?php echo esc_html__( 'Automatic extraction needs attention only for the missing items below; the rest of the rescued content does not need to be entered manually.', 'seo-geo-migration-bridge' ); ?></p>
				<p><code><?php echo esc_html( implode( ', ', is_array( $plan['blockers'] ?? null ) ? $plan['blockers'] : array() ) ); ?></code></p>
			<?php endif; ?>
		</div>
		<?php
	}

	/** Generate the semantic kit and hydrate the private Home draft. */
	public function handle_apply(): never {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Administrator capability is required.', 'seo-geo-migration-bridge' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( self::NONCE_ACTION );

		$confirm = isset( $_POST['confirm'] ) ? sanitize_key( wp_unslash( $_POST['confirm'] ) ) : '';
		if ( 'generate-and-hydrate-home' !== $confirm ) {
			wp_die( esc_html__( 'Automatic Home generation was not explicitly confirmed.', 'seo-geo-migration-bridge' ), '', array( 'response' => 400 ) );
		}

		$result = $this->automatic->apply();
		if ( $result instanceof WP_Error ) {
			wp_die( esc_html( $result->get_error_message() ), '', array( 'response' => 400 ) );
		}

		$url = add_query_arg(
			array(
				'page'                     => AdminRescueManifestController::PAGE_SLUG,
				'seo_geo_auto_home_status' => 'applied',
			),
			admin_url( 'tools.php' )
		);
		wp_safe_redirect( $url );
		exit;
	}
}
