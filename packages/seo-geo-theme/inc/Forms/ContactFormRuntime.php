<?php
/**
 * Native contact-form runtime owned by the self-contained Theme.
 *
 * @package SeoGeoTheme
 */

declare(strict_types=1);

namespace SeoGeo\Theme\Forms;

use WP_Block;

/**
 * Renders migrated contact forms and handles submissions without plugin runtime.
 */
final class ContactFormRuntime {
	/**
	 * Post-meta key populated by migration adapters.
	 */
	public const META_KEY = '_seo_geo_contact_forms_v1';

	/**
	 * Public block name emitted by the Migration Bridge.
	 */
	public const BLOCK_NAME = 'seo-geo/contact-form';

	/**
	 * Submission action.
	 */
	private const ACTION = 'seo_geo_contact_submit';

	/**
	 * Register the dynamic block and public submission handlers.
	 */
	public function register(): void {
		add_action( 'init', array( $this, 'register_block' ) );
		add_action( 'admin_post_' . self::ACTION, array( $this, 'handle' ) );
		add_action( 'admin_post_nopriv_' . self::ACTION, array( $this, 'handle' ) );
	}

	/**
	 * Register the dynamic block used by migrated pages.
	 */
	public function register_block(): void {
		register_block_type(
			self::BLOCK_NAME,
			array(
				'api_version'     => 3,
				'attributes'      => array(
					'formId' => array(
						'type' => 'string',
					),
				),
				'render_callback' => array( $this, 'render' ),
			)
		);
	}

	/**
	 * Render one migrated form.
	 *
	 * @param array<string,mixed> $attributes Block attributes.
	 * @param string              $content    Inner content.
	 * @param WP_Block|null       $block      Block instance.
	 */
	public function render( array $attributes, string $content = '', ?WP_Block $block = null ): string {
		unset( $content );

		$form_id = isset( $attributes['formId'] ) && is_string( $attributes['formId'] )
			? sanitize_key( $attributes['formId'] )
			: '';
		$post_id = $this->resolve_post_id( $block );

		if ( '' === $form_id || 0 >= $post_id ) {
			return '';
		}

		$config = $this->form_config( $post_id, $form_id );
		if ( null === $config ) {
			return current_user_can( 'edit_post', $post_id )
				? '<p class="seo-geo-contact-form-error">' . esc_html__( 'Migrated contact form configuration is unavailable.', 'seo-geo-theme' ) . '</p>'
				: '';
		}

		$fields = is_array( $config['fields'] ?? null ) ? $config['fields'] : array();
		if ( array() === $fields ) {
			return '';
		}

		// Read-only status query args are bounded to sanitize_key values and never mutate state.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$status = isset( $_GET['seo_geo_form'] ) ? sanitize_key( wp_unslash( $_GET['seo_geo_form'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$status_form = isset( $_GET['seo_geo_form_id'] ) ? sanitize_key( wp_unslash( $_GET['seo_geo_form_id'] ) ) : '';

		$html = '<div class="seo-geo-contact-form-wrap" id="seo-geo-form-' . esc_attr( $form_id ) . '">';

		if ( $status_form === $form_id && 'success' === $status ) {
			$message = isset( $config['success_message'] ) && is_string( $config['success_message'] ) && '' !== trim( $config['success_message'] )
				? trim( $config['success_message'] )
				: __( 'Thanks. Your message has been sent.', 'seo-geo-theme' );
			$html   .= '<p class="seo-geo-contact-form-status seo-geo-contact-form-success" role="status">' . esc_html( $message ) . '</p>';
		} elseif ( $status_form === $form_id && in_array( $status, array( 'invalid', 'failed', 'blocked' ), true ) ) {
			$html .= '<p class="seo-geo-contact-form-status seo-geo-contact-form-error" role="alert">' . esc_html__( 'The form could not be sent. Please review the fields and try again.', 'seo-geo-theme' ) . '</p>';
		}

		$html .= '<form class="seo-geo-contact-form" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		$html .= '<input type="hidden" name="action" value="' . esc_attr( self::ACTION ) . '">';
		$html .= '<input type="hidden" name="post_id" value="' . esc_attr( (string) $post_id ) . '">';
		$html .= '<input type="hidden" name="form_id" value="' . esc_attr( $form_id ) . '">';
		$html .= wp_nonce_field( $this->nonce_action( $post_id, $form_id ), '_seo_geo_contact_nonce', true, false );
		$html .= '<p class="seo-geo-contact-form-honeypot" aria-hidden="true"><label>Website<input type="text" name="seo_geo_website" value="" tabindex="-1" autocomplete="off"></label></p>';

		foreach ( $fields as $field ) {
			if ( ! is_array( $field ) ) {
				continue;
			}
			$html .= $this->render_field( $post_id, $form_id, $field );
		}

		$button = isset( $config['button_text'] ) && is_string( $config['button_text'] ) && '' !== trim( $config['button_text'] )
			? trim( $config['button_text'] )
			: __( 'Send', 'seo-geo-theme' );
		$html  .= '<p class="seo-geo-contact-form-submit"><button type="submit">' . esc_html( $button ) . '</button></p>';
		$html  .= '</form></div>';

		return $html;
	}

	/**
	 * Handle one public or authenticated form submission.
	 */
	public function handle(): never {
		// These three bounded values are required to identify and verify the exact nonce action.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$post_id = isset( $_POST['post_id'] ) ? absint( wp_unslash( $_POST['post_id'] ) ) : 0;
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$form_id = isset( $_POST['form_id'] ) ? sanitize_key( wp_unslash( $_POST['form_id'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$nonce = isset( $_POST['_seo_geo_contact_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_seo_geo_contact_nonce'] ) ) : '';

		$return_url = 0 < $post_id ? get_permalink( $post_id ) : home_url( '/' );
		if ( ! is_string( $return_url ) || '' === $return_url || '' === $form_id || 0 >= $post_id ) {
			$this->redirect( home_url( '/' ), $form_id, 'invalid' );
		}

		$config = $this->form_config( $post_id, $form_id );
		if ( null === $config || ! wp_verify_nonce( $nonce, $this->nonce_action( $post_id, $form_id ) ) ) {
			$this->redirect( $return_url, $form_id, 'invalid' );
		}

		$honeypot = isset( $_POST['seo_geo_website'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['seo_geo_website'] ) ) ) : '';
		if ( '' !== $honeypot || $this->rate_limited( $post_id, $form_id ) ) {
			$this->redirect( $return_url, $form_id, 'blocked' );
		}

		$recipient = isset( $config['recipient'] ) && is_string( $config['recipient'] )
			? sanitize_email( $config['recipient'] )
			: '';
		if ( ! is_email( $recipient ) ) {
			$this->redirect( $return_url, $form_id, 'failed' );
		}

		// Values are sanitized below according to the stored, trusted field type.
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$submitted = isset( $_POST['seo_geo_fields'] ) && is_array( $_POST['seo_geo_fields'] )
			? wp_unslash( $_POST['seo_geo_fields'] )
			: array();

		$fields   = is_array( $config['fields'] ?? null ) ? $config['fields'] : array();
		$lines    = array();
		$reply_to = '';

		foreach ( $fields as $field ) {
			if ( ! is_array( $field ) ) {
				continue;
			}

			$id       = isset( $field['id'] ) && is_string( $field['id'] ) ? sanitize_key( $field['id'] ) : '';
			$label    = isset( $field['label'] ) && is_string( $field['label'] ) ? trim( $field['label'] ) : $id;
			$type     = isset( $field['type'] ) && is_string( $field['type'] ) ? sanitize_key( $field['type'] ) : 'text';
			$required = ! empty( $field['required'] );
			$raw      = '' !== $id && isset( $submitted[ $id ] ) && is_scalar( $submitted[ $id ] ) ? (string) $submitted[ $id ] : '';
			$value    = $this->sanitize_value( $raw, $type );

			if ( $required && '' === $value ) {
				$this->redirect( $return_url, $form_id, 'invalid' );
			}
			if ( 'email' === $type && '' !== $value && ! is_email( $value ) ) {
				$this->redirect( $return_url, $form_id, 'invalid' );
			}
			if ( 'email' === $type && '' === $reply_to && is_email( $value ) ) {
				$reply_to = $value;
			}

			$lines[] = wp_strip_all_tags( $label ) . ': ' . $value;
		}

		$page_title = get_the_title( $post_id );
		$subject    = sprintf(
			/* translators: 1: site name, 2: page title. */
			__( '[%1$s] Contact form: %2$s', 'seo-geo-theme' ),
			wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
			wp_strip_all_tags( is_string( $page_title ) ? $page_title : '' )
		);
		$body       = implode( "\n\n", $lines );
		$headers    = array();

		if ( '' !== $reply_to ) {
			$headers[] = 'Reply-To: ' . $reply_to;
		}

		if ( ! wp_mail( $recipient, $subject, $body, $headers ) ) {
			$this->redirect( $return_url, $form_id, 'failed' );
		}

		$this->touch_rate_limit( $post_id, $form_id );
		$this->redirect( $return_url, $form_id, 'success' );
	}

	/**
	 * Render one configured field.
	 *
	 * @param int                 $post_id Post ID.
	 * @param string              $form_id Form identifier.
	 * @param array<string,mixed> $field   Field configuration.
	 */
	private function render_field( int $post_id, string $form_id, array $field ): string {
		$id = isset( $field['id'] ) && is_string( $field['id'] ) ? sanitize_key( $field['id'] ) : '';
		if ( '' === $id ) {
			return '';
		}

		$label         = isset( $field['label'] ) && is_string( $field['label'] ) && '' !== trim( $field['label'] )
			? trim( $field['label'] )
			: ucfirst( str_replace( '_', ' ', $id ) );
		$type          = isset( $field['type'] ) && is_string( $field['type'] ) ? sanitize_key( $field['type'] ) : 'text';
		$required      = ! empty( $field['required'] );
		$control_id    = 'seo-geo-field-' . $post_id . '-' . $form_id . '-' . $id;
		$name          = 'seo_geo_fields[' . $id . ']';
		$required_attr = $required ? ' required aria-required="true"' : '';
		$marker        = $required ? ' <span aria-hidden="true">*</span>' : '';

		$html  = '<p class="seo-geo-contact-form-field seo-geo-contact-form-field-' . esc_attr( $type ) . '">';
		$html .= '<label for="' . esc_attr( $control_id ) . '">' . esc_html( $label ) . $marker . '</label>';

		if ( 'textarea' === $type ) {
			$html .= '<textarea id="' . esc_attr( $control_id ) . '" name="' . esc_attr( $name ) . '" rows="5"' . $required_attr . '></textarea>';
		} else {
			$input_type = in_array( $type, array( 'email', 'url', 'tel' ), true ) ? $type : 'text';
			$html      .= '<input id="' . esc_attr( $control_id ) . '" type="' . esc_attr( $input_type ) . '" name="' . esc_attr( $name ) . '"' . $required_attr . '>';
		}

		$html .= '</p>';
		return $html;
	}

	/**
	 * Resolve current post ID from block context or the main query.
	 *
	 * @param WP_Block|null $block Block instance.
	 */
	private function resolve_post_id( ?WP_Block $block ): int {
		if ( $block instanceof WP_Block && isset( $block->context['postId'] ) ) {
			return absint( $block->context['postId'] );
		}

		return absint( get_the_ID() );
	}

	/**
	 * Return one validated form configuration.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $form_id Form identifier.
	 * @return array<string,mixed>|null
	 */
	private function form_config( int $post_id, string $form_id ): ?array {
		$forms = get_post_meta( $post_id, self::META_KEY, true );
		if ( ! is_array( $forms ) || ! isset( $forms[ $form_id ] ) || ! is_array( $forms[ $form_id ] ) ) {
			return null;
		}

		return $forms[ $form_id ];
	}

	/**
	 * Build a stable nonce action.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $form_id Form identifier.
	 */
	private function nonce_action( int $post_id, string $form_id ): string {
		return self::ACTION . ':' . $post_id . ':' . $form_id;
	}

	/**
	 * Sanitize one submitted value based on its configured type.
	 *
	 * @param string $value Submitted value.
	 * @param string $type  Configured field type.
	 */
	private function sanitize_value( string $value, string $type ): string {
		return match ( $type ) {
			'email'    => sanitize_email( $value ),
			'url'      => esc_url_raw( $value ),
			'textarea' => sanitize_textarea_field( $value ),
			default    => sanitize_text_field( $value ),
		};
	}

	/**
	 * Apply a short anti-repeat throttle without storing raw IP addresses.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $form_id Form identifier.
	 */
	private function rate_limited( int $post_id, string $form_id ): bool {
		return false !== get_transient( $this->rate_key( $post_id, $form_id ) );
	}

	/**
	 * Mark a successful submission for the short anti-repeat window.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $form_id Form identifier.
	 */
	private function touch_rate_limit( int $post_id, string $form_id ): void {
		set_transient( $this->rate_key( $post_id, $form_id ), '1', 10 );
	}

	/**
	 * Build an opaque rate-limit key.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $form_id Form identifier.
	 */
	private function rate_key( int $post_id, string $form_id ): string {
		$ip     = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$opaque = hash_hmac( 'sha256', $post_id . '|' . $form_id . '|' . $ip, wp_salt( 'nonce' ) );

		return 'seo_geo_form_' . substr( $opaque, 0, 32 );
	}

	/**
	 * Redirect back to the source page with a bounded result code.
	 *
	 * @param string $return_url Return URL.
	 * @param string $form_id    Form identifier.
	 * @param string $status     Bounded result status.
	 */
	private function redirect( string $return_url, string $form_id, string $status ): never {
		$url = add_query_arg(
			array(
				'seo_geo_form'    => $status,
				'seo_geo_form_id' => $form_id,
			),
			$return_url
		);
		wp_safe_redirect( $url . '#seo-geo-form-' . rawurlencode( $form_id ) );
		exit;
	}
}
