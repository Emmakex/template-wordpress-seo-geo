<?php
/**
 * Administrator Reset/Rebuild rescue-manifest surface.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge\Reset;


/**
 * Provides the first reset-first rebuild action without legacy dependency gates.
 */
final class AdminRescueManifestController {
	public const PAGE_SLUG    = 'seo-geo-reset-rebuild';
	public const ACTION       = 'seo_geo_reset_capture_rescue_manifest';
	public const NONCE_ACTION = 'seo_geo_reset_capture_rescue_manifest';

	/**
	 * @param RescueManifest $manifest Rescue manifest service.
	 */
	public function __construct( private RescueManifest $manifest ) {
	}

	/**
	 * Register administrator hooks.
	 */
	public function boot(): void {
		add_action( 'admin_menu', array( $this, 'register_page' ) );
		add_action( 'admin_post_' . self::ACTION, array( $this, 'handle_capture' ) );
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
	 * Render the reset-first entrypoint.
	 */
	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Administrator capability is required.', 'seo-geo-migration-bridge' ), '', array( 'response' => 403 ) );
		}

		$saved = $this->manifest->saved();
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'SEO/GEO Reset & Rebuild', 'seo-geo-migration-bridge' ); ?></h1>
			<p><?php echo esc_html__( 'Full-redesign mode: rescue the useful digital asset, reset legacy presentation/runtime baggage, then rebuild with the SEO/GEO Theme and preset.', 'seo-geo-migration-bridge' ); ?></p>

			<div class="notice notice-info inline">
				<p><strong><?php echo esc_html__( 'This path does not require every legacy UNKNOWN dependency to be reviewed.', 'seo-geo-migration-bridge' ); ?></strong></p>
				<p><?php echo esc_html__( 'The Rescue Manifest does not deactivate plugins, delete themes or change content. It records only the WordPress/search assets the clean rebuild may need.', 'seo-geo-migration-bridge' ); ?></p>
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
			<p><?php echo esc_html__( 'Capture pages/posts, URL identity, content fingerprints, useful SEO metadata, links and media references. Theme/plugin/builder dependency analysis is intentionally outside this manifest.', 'seo-geo-migration-bridge' ); ?></p>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION ); ?>">
				<input type="hidden" name="confirm" value="capture-rescue-manifest">
				<?php wp_nonce_field( self::NONCE_ACTION ); ?>
				<?php submit_button( is_array( $saved ) ? __( 'Refresh Rescue Manifest', 'seo-geo-migration-bridge' ) : __( 'Create Rescue Manifest', 'seo-geo-migration-bridge' ) ); ?>
			</form>

			<h2><?php echo esc_html__( 'Next', 'seo-geo-migration-bridge' ); ?></h2>
			<p><?php echo esc_html__( 'The next microphase is the Reset Engine: remove legacy theme/builder/plugin baggage while retaining the Rescue Manifest and WordPress content needed by the clean rebuild.', 'seo-geo-migration-bridge' ); ?></p>
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

		$redirect = add_query_arg(
			array(
				'page'                  => self::PAGE_SLUG,
				'seo_geo_rescue_saved'  => '1',
			),
			admin_url( 'tools.php' )
		);

		wp_safe_redirect( $redirect );
		exit;
	}
}
