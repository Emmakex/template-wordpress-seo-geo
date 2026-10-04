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
 * Exposes read-only plans, draft creation and explicit reviewed remap application.
 */
final class AdminNativeReplatformController {
	public const PAGE_SLUG          = 'seo-geo-native-replatform';
	public const ACTION             = 'seo_geo_native_replatform_create_draft';
	public const NONCE_ACTION       = 'seo_geo_native_replatform_create_draft';
	public const APPLY_ACTION       = 'seo_geo_native_replatform_apply_reviewed';
	public const APPLY_NONCE_ACTION = 'seo_geo_native_replatform_apply_reviewed';

	/**
	 * Construct the administrator controller.
	 *
	 * @param NativeCompositionService $composer Draft-only native composer.
	 * @param ReviewedRemapApplier     $applier  Reviewed content applier.
	 */
	public function __construct(
		private NativeCompositionService $composer,
		private ReviewedRemapApplier $applier
	) {
	}

	/**
	 * Register the admin surface and authenticated mutation endpoints.
	 */
	public function boot(): void {
		add_action( 'admin_menu', array( $this, 'register_page' ) );
		add_action( 'admin_post_' . self::ACTION, array( $this, 'handle_create_draft' ) );
		add_action( 'admin_post_' . self::APPLY_ACTION, array( $this, 'handle_apply_reviewed' ) );
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

			<?php $this->render_review_forms( $plans ); ?>
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

		$this->redirect_result(
			(string) ( $result['status'] ?? 'created' ),
			(int) ( $result['draft_id'] ?? 0 )
		);
	}

	/**
	 * Handle explicit reviewed content application.
	 */
	public function handle_apply_reviewed(): never {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Administrator capability is required.', 'seo-geo-migration-bridge' ), '', array( 'response' => 403 ) );
		}

		$draft_id = isset( $_POST['draft_id'] ) ? absint( wp_unslash( $_POST['draft_id'] ) ) : 0;
		$confirm  = isset( $_POST['confirm'] ) ? sanitize_key( wp_unslash( $_POST['confirm'] ) ) : '';

		if ( 0 >= $draft_id || 'apply-reviewed-remap' !== $confirm ) {
			wp_die( esc_html__( 'Reviewed remap request is incomplete or was not explicitly confirmed.', 'seo-geo-migration-bridge' ), '', array( 'response' => 400 ) );
		}

		check_admin_referer( self::apply_nonce_action( $draft_id ) );

		$raw_selections = isset( $_POST['selections'] ) && is_array( $_POST['selections'] )
			? map_deep( wp_unslash( $_POST['selections'] ), 'sanitize_text_field' )
			: array();
		$raw_verified   = isset( $_POST['verified_sections'] ) && is_array( $_POST['verified_sections'] )
			? map_deep( wp_unslash( $_POST['verified_sections'] ), 'sanitize_text_field' )
			: array();

		$selections = $this->sanitize_selections( $raw_selections );
		$verified   = array_values(
			array_unique(
				array_filter(
					array_map(
						static fn( mixed $value ): string => is_string( $value ) ? sanitize_key( $value ) : '',
						$raw_verified
					)
				)
			)
		);

		$result = $this->applier->apply( $draft_id, $selections, $verified );
		if ( $result instanceof WP_Error ) {
			wp_die( esc_html( $result->get_error_message() ), '', array( 'response' => 400 ) );
		}

		$status = 'existing' === ( $result['status'] ?? null ) ? 'remap-existing' : 'remap-applied';
		$this->redirect_result( $status, $draft_id );
	}

	/**
	 * Render reviewed candidate forms for existing native drafts.
	 *
	 * @param array<int,array<string,mixed>> $plans Native replatform plans.
	 */
	private function render_review_forms( array $plans ): void {
		foreach ( $plans as $plan ) {
			$draft_id = (int) ( $plan['existing_draft'] ?? 0 );
			$remap    = is_array( $plan['content_remap'] ?? null ) ? $plan['content_remap'] : array();
			$slots    = is_array( $remap['slots'] ?? null ) ? $remap['slots'] : array();

			if ( 0 >= $draft_id || array() === $slots || array() === ( $plan['remap_slots'] ?? array() ) ) {
				continue;
			}

			$assets = $this->asset_index( is_array( $remap['assets'] ?? null ) ? $remap['assets'] : array() );
			?>
			<hr>
			<h2>
				<?php
				echo esc_html(
					sprintf(
						/* translators: %s: preset page key. */
						__( 'Reviewed content remap — %s', 'seo-geo-migration-bridge' ),
						(string) ( $plan['page_key'] ?? '' )
					)
				);
				?>
			</h2>
			<p><?php echo esc_html__( 'Select only preserved source assets that belong in each semantic slot. Evidence-sensitive sections require explicit verification. Nothing is applied automatically.', 'seo-geo-migration-bridge' ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="<?php echo esc_attr( self::APPLY_ACTION ); ?>">
				<input type="hidden" name="draft_id" value="<?php echo esc_attr( (string) $draft_id ); ?>">
				<input type="hidden" name="confirm" value="apply-reviewed-remap">
				<?php wp_nonce_field( self::apply_nonce_action( $draft_id ) ); ?>

				<?php foreach ( $slots as $slot ) : ?>
					<?php
					if ( ! is_array( $slot ) || ! isset( $slot['section'] ) || ! is_string( $slot['section'] ) ) {
						continue;
					}
					$section    = sanitize_key( $slot['section'] );
					$candidates = is_array( $slot['candidates'] ?? null ) ? $slot['candidates'] : array();
					?>
					<fieldset style="margin:1rem 0;padding:1rem;border:1px solid #dcdcde;">
						<legend><strong><code><?php echo esc_html( $section ); ?></code></strong></legend>

						<?php foreach ( array( 'units', 'links', 'media' ) as $type ) : ?>
							<?php foreach ( is_array( $candidates[ $type ] ?? null ) ? $candidates[ $type ] : array() as $asset_id ) : ?>
								<?php
								$asset_id = is_string( $asset_id ) ? $asset_id : '';
								$asset    = $assets[ $type ][ $asset_id ] ?? null;
								if ( '' === $asset_id || ! is_array( $asset ) ) {
									continue;
								}
								?>
								<label style="display:block;margin:.5rem 0;">
									<input
										type="checkbox"
										name="selections[<?php echo esc_attr( $section ); ?>][<?php echo esc_attr( $type ); ?>][]"
										value="<?php echo esc_attr( $asset_id ); ?>"
									>
									<code><?php echo esc_html( $asset_id ); ?></code>
									<?php echo esc_html( $this->asset_label( $type, $asset ) ); ?>
								</label>
							<?php endforeach; ?>
						<?php endforeach; ?>

						<?php if ( true === ( $slot['requires_verification'] ?? false ) ) : ?>
							<label style="display:block;margin-top:.75rem;">
								<input type="checkbox" name="verified_sections[]" value="<?php echo esc_attr( $section ); ?>">
								<strong><?php echo esc_html__( 'I verified that the selected evidence is factual and appropriate for publication.', 'seo-geo-migration-bridge' ); ?></strong>
							</label>
						<?php endif; ?>
					</fieldset>
				<?php endforeach; ?>

				<?php submit_button( __( 'Apply reviewed content to native draft', 'seo-geo-migration-bridge' ), 'primary', 'submit', false ); ?>
			</form>
			<?php
		}
	}

	/**
	 * Build asset lookup maps for reviewed forms.
	 *
	 * @param array<string,mixed> $assets Content-remap assets.
	 * @return array{units:array<string,array<string,mixed>>,links:array<string,array<string,mixed>>,media:array<string,array<string,mixed>>}
	 */
	private function asset_index( array $assets ): array {
		$result = array(
			'units' => array(),
			'links' => array(),
			'media' => array(),
		);

		foreach ( array_keys( $result ) as $type ) {
			foreach ( is_array( $assets[ $type ] ?? null ) ? $assets[ $type ] : array() as $asset ) {
				if ( is_array( $asset ) && isset( $asset['id'] ) && is_string( $asset['id'] ) ) {
					$result[ $type ][ $asset['id'] ] = $asset;
				}
			}
		}

		return $result;
	}

	/**
	 * Return a concise human-readable asset label.
	 *
	 * @param string              $type  Asset type.
	 * @param array<string,mixed> $asset Asset payload.
	 */
	private function asset_label( string $type, array $asset ): string {
		if ( 'units' === $type ) {
			$text = isset( $asset['text'] ) && is_string( $asset['text'] ) ? $asset['text'] : '';
			return mb_strlen( $text ) > 140 ? mb_substr( $text, 0, 137 ) . '…' : $text;
		}
		if ( 'links' === $type ) {
			return isset( $asset['url'] ) && is_string( $asset['url'] ) ? $asset['url'] : '';
		}

		$url = isset( $asset['url'] ) && is_string( $asset['url'] ) ? $asset['url'] : '';
		$alt = isset( $asset['alt'] ) && is_string( $asset['alt'] ) ? $asset['alt'] : '';

		return '' !== $alt ? $alt . ' — ' . $url : $url;
	}

	/**
	 * Sanitize nested reviewed selections.
	 *
	 * @param array<string,mixed> $raw Raw request selections.
	 * @return array<string,array{units:list<string>,links:list<string>,media:list<string>}>
	 */
	private function sanitize_selections( array $raw ): array {
		$result = array();

		foreach ( $raw as $section => $selection ) {
			if ( ! is_array( $selection ) ) {
				continue;
			}

			$section = sanitize_key( $section );
			if ( '' === $section ) {
				continue;
			}

			$result[ $section ] = array(
				'units' => array(),
				'links' => array(),
				'media' => array(),
			);
			foreach ( array_keys( $result[ $section ] ) as $type ) {
				$values = isset( $selection[ $type ] ) && is_array( $selection[ $type ] ) ? $selection[ $type ] : array();
				foreach ( $values as $value ) {
					if ( is_string( $value ) && '' !== trim( $value ) ) {
						$result[ $section ][ $type ][] = sanitize_text_field( $value );
					}
				}
				$result[ $section ][ $type ] = array_values( array_unique( $result[ $section ][ $type ] ) );
			}
		}

		return $result;
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
	 * Return a draft-scoped reviewed-apply nonce action.
	 *
	 * @param int $draft_id Native draft ID.
	 */
	public static function apply_nonce_action( int $draft_id ): string {
		return self::APPLY_NONCE_ACTION . ':' . $draft_id;
	}

	/**
	 * Redirect back to the Native Replatform screen after an explicit action.
	 *
	 * @param string $status   Result status.
	 * @param int    $draft_id Native draft ID.
	 */
	private function redirect_result( string $status, int $draft_id ): never {
		$redirect = add_query_arg(
			array(
				'page'                 => self::PAGE_SLUG,
				'seo_geo_replatform'   => sanitize_key( $status ),
				'seo_geo_native_draft' => $draft_id,
			),
			admin_url( 'tools.php' )
		);

		wp_safe_redirect( $redirect );
		exit;
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

		if ( 0 >= $draft_id ) {
			return;
		}

		$messages = array(
			'created'        => __( 'Native replacement draft created. The preserved source page was not modified.', 'seo-geo-migration-bridge' ),
			'existing'       => __( 'The equivalent native replacement draft already exists; no duplicate was created.', 'seo-geo-migration-bridge' ),
			'remap-applied'  => __( 'Reviewed preserved content was applied to the native draft. The source page remains unchanged.', 'seo-geo-migration-bridge' ),
			'remap-existing' => __( 'The same reviewed content selection was already applied; no duplicate mutation was performed.', 'seo-geo-migration-bridge' ),
		);

		if ( ! isset( $messages[ $status ] ) ) {
			return;
		}
		?>
		<div class="notice notice-success is-dismissible">
			<p>
				<?php echo esc_html( $messages[ $status ] ); ?>
				<a href="<?php echo esc_url( $this->edit_link( $draft_id ) ); ?>"><?php echo esc_html__( 'Open draft', 'seo-geo-migration-bridge' ); ?></a>
			</p>
		</div>
		<?php
	}
}
