<?php
/**
 * Administrator Native Replatform Composer surface.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge\Replatform;

use WP_Error;

/**
 * Exposes read-only plans and explicit draft creation under Tools.
 */
final class AdminNativeReplatformController {
	public const PAGE_SLUG    = 'seo-geo-native-replatform';
	public const ACTION       = 'seo_geo_native_replatform_create_draft';
	public const NONCE_ACTION = 'seo_geo_native_replatform_create_draft';

	/**
	 * Construct the administrator controller.
	 *
	 * @param NativeCompositionService $composer Draft-only native composer.
	 */
	public function __construct( private NativeCompositionService $composer ) {
	}

	/**
	 * Register the admin surface and authenticated mutation endpoint.
	 */
	public function boot(): void {
		add_action( 'admin_menu', array( $this, 'register_page' ) );
		add_action( 'admin_post_' . self::ACTION, array( $this, 'handle_create_draft' ) );
	}

	/**
	 * Register Tools > Native Replatform.
	 */
	public function register_page(): void {
		add_management_page(
			__( 'SEO/GEO Native Replatform', 'seo-geo-migration-bridge' ),
			__( 'SEO/GEO Native Replatform', 'seo-geo-migration-bridge' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render' )
		);
	}

	/**
	 * Render plans. This screen is read-only until a user explicitly submits a form.
	 */
	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Administrator capability is required.', 'seo-geo-migration-bridge' ), '', array( 'response' => 403 ) );
		}

		$plans = $this->composer->plans();
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'SEO/GEO Native Replatform', 'seo-geo-migration-bridge' ); ?></h1>
			<p><?php echo esc_html__( 'Preserve content, URLs, SEO signals and links; rebuild presentation with the active Theme preset. Source pages are never modified by this step.', 'seo-geo-migration-bridge' ); ?></p>
			<?php $this->render_notice(); ?>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php echo esc_html__( 'Page key', 'seo-geo-migration-bridge' ); ?></th>
						<th><?php echo esc_html__( 'Source', 'seo-geo-migration-bridge' ); ?></th>
						<th><?php echo esc_html__( 'Native composition', 'seo-geo-migration-bridge' ); ?></th>
						<th><?php echo esc_html__( 'State', 'seo-geo-migration-bridge' ); ?></th>
						<th><?php echo esc_html__( 'Action', 'seo-geo-migration-bridge' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $plans as $plan ) : ?>
						<tr>
							<td><code><?php echo esc_html( (string) ( $plan['page_key'] ?? '' ) ); ?></code></td>
							<td>
								<?php if ( is_array( $plan['source'] ?? null ) ) : ?>
									<?php echo esc_html( (string) ( $plan['source']['title'] ?? '' ) ); ?>
									<br><code>#<?php echo esc_html( (string) (int) ( $plan['source']['id'] ?? 0 ) ); ?> · <?php echo esc_html( (string) ( $plan['source']['path'] ?? '' ) ); ?></code>
								<?php else : ?>
									—
								<?php endif; ?>
							</td>
							<td>
								<?php foreach ( is_array( $plan['patterns'] ?? null ) ? $plan['patterns'] : array() as $slug ) : ?>
									<code><?php echo esc_html( (string) $slug ); ?></code><br>
								<?php endforeach; ?>
							</td>
							<td>
								<?php if ( 0 < (int) ( $plan['existing_draft'] ?? 0 ) ) : ?>
									<?php echo esc_html__( 'Native draft ready', 'seo-geo-migration-bridge' ); ?>
								<?php elseif ( true === ( $plan['ready'] ?? false ) ) : ?>
									<?php echo esc_html__( 'Ready to compose', 'seo-geo-migration-bridge' ); ?>
								<?php else : ?>
									<?php echo esc_html( implode( ', ', is_array( $plan['blockers'] ?? null ) ? $plan['blockers'] : array() ) ); ?>
								<?php endif; ?>
							</td>
							<td>
								<?php if ( 0 < (int) ( $plan['existing_draft'] ?? 0 ) ) : ?>
									<a class="button" href="<?php echo esc_url( $this->edit_link( (int) $plan['existing_draft'] ) ); ?>"><?php echo esc_html__( 'Edit native draft', 'seo-geo-migration-bridge' ); ?></a>
								<?php elseif ( true === ( $plan['ready'] ?? false ) ) : ?>
									<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
										<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION ); ?>">
										<input type="hidden" name="page_key" value="<?php echo esc_attr( (string) $plan['page_key'] ); ?>">
										<input type="hidden" name="preset" value="<?php echo esc_attr( (string) $plan['preset'] ); ?>">
										<input type="hidden" name="confirm" value="create-native-draft">
										<?php wp_nonce_field( self::nonce_action( (string) $plan['page_key'] ) ); ?>
										<?php submit_button( __( 'Create native draft', 'seo-geo-migration-bridge' ), 'secondary', 'submit', false ); ?>
									</form>
								<?php else : ?>
									—
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
					<?php if ( array() === $plans ) : ?>
						<tr><td colspan="5"><?php echo esc_html__( 'No active preset composition is available.', 'seo-geo-migration-bridge' ); ?></td></tr>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	/**
	 * Handle explicit draft creation.
	 */
	public function handle_create_draft(): never {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Administrator capability is required.', 'seo-geo-migration-bridge' ), '', array( 'response' => 403 ) );
		}

		$page_key = isset( $_POST['page_key'] ) ? sanitize_key( wp_unslash( $_POST['page_key'] ) ) : '';
		$preset   = isset( $_POST['preset'] ) ? sanitize_key( wp_unslash( $_POST['preset'] ) ) : '';
		$confirm  = isset( $_POST['confirm'] ) ? sanitize_key( wp_unslash( $_POST['confirm'] ) ) : '';

		if ( '' === $page_key || 'create-native-draft' !== $confirm ) {
			wp_die( esc_html__( 'Native replatform request is incomplete or was not explicitly confirmed.', 'seo-geo-migration-bridge' ), '', array( 'response' => 400 ) );
		}

		check_admin_referer( self::nonce_action( $page_key ) );

		$result = $this->composer->create_draft( $page_key, '' !== $preset ? $preset : null );
		if ( $result instanceof WP_Error ) {
			wp_die( esc_html( $result->get_error_message() ), '', array( 'response' => 400 ) );
		}

		$redirect = add_query_arg(
			array(
				'page'                 => self::PAGE_SLUG,
				'seo_geo_replatform'   => (string) ( $result['status'] ?? 'created' ),
				'seo_geo_native_draft' => (int) ( $result['draft_id'] ?? 0 ),
			),
			admin_url( 'tools.php' )
		);

		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * Return a page-scoped nonce action.
	 *
	 * @param string $page_key Preset page key.
	 */
	public static function nonce_action( string $page_key ): string {
		return self::NONCE_ACTION . ':' . sanitize_key( $page_key );
	}

	/**
	 * Return an administrator edit link or a safe inert fallback.
	 *
	 * @param int $post_id Draft post ID.
	 */
	private function edit_link( int $post_id ): string {
		$link = get_edit_post_link( $post_id, '' );

		return is_string( $link ) && '' !== $link ? $link : '#';
	}

	/**
	 * Render the result of the previous explicit action.
	 */
	private function render_notice(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only status after nonce-verified admin action.
		$status = isset( $_GET['seo_geo_replatform'] ) ? sanitize_key( wp_unslash( $_GET['seo_geo_replatform'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only draft ID after nonce-verified admin action.
		$draft_id = isset( $_GET['seo_geo_native_draft'] ) ? absint( wp_unslash( $_GET['seo_geo_native_draft'] ) ) : 0;

		if ( ! in_array( $status, array( 'created', 'existing' ), true ) || 0 >= $draft_id ) {
			return;
		}

		$message = 'created' === $status
			? __( 'Native replacement draft created. The preserved source page was not modified.', 'seo-geo-migration-bridge' )
			: __( 'The equivalent native replacement draft already exists; no duplicate was created.', 'seo-geo-migration-bridge' );
		?>
		<div class="notice notice-success is-dismissible">
			<p>
				<?php echo esc_html( $message ); ?>
				<a href="<?php echo esc_url( $this->edit_link( $draft_id ) ); ?>"><?php echo esc_html__( 'Open draft', 'seo-geo-migration-bridge' ); ?></a>
			</p>
		</div>
		<?php
	}
}
