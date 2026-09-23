<?php
/**
 * Administrator cutover/rollback/acceptance entrypoints.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge\Cutover;

use WP_Error;

/**
 * Handles explicit administrator Phase 8G submissions.
 */
final class AdminCutoverController {
	/**
	 * Execute action.
	 */
	public const ACTION_EXECUTE = 'seo_geo_cutover_execute';

	/**
	 * Rollback action.
	 */
	public const ACTION_ROLLBACK = 'seo_geo_cutover_rollback';

	/**
	 * Acceptance action.
	 */
	public const ACTION_ACCEPT = 'seo_geo_cutover_accept';

	/**
	 * Cutover engine.
	 *
	 * @var CutoverEngine
	 */
	private CutoverEngine $engine;

	/**
	 * Construct the controller.
	 *
	 * @param CutoverEngine $engine Controlled cutover engine.
	 */
	public function __construct( CutoverEngine $engine ) {
		$this->engine = $engine;
	}

	/**
	 * Register authenticated administrator endpoints.
	 */
	public function boot(): void {
		add_action( 'admin_post_' . self::ACTION_EXECUTE, array( $this, 'handle_execute' ) );
		add_action( 'admin_post_' . self::ACTION_ROLLBACK, array( $this, 'handle_rollback' ) );
		add_action( 'admin_post_' . self::ACTION_ACCEPT, array( $this, 'handle_accept' ) );
	}

	/**
	 * Execute one explicitly confirmed cutover.
	 */
	public function handle_execute(): never {
		$this->require_admin();
		check_admin_referer( CutoverEngine::execute_nonce_action() );

		$confirm = isset( $_POST['confirm'] ) && is_string( $_POST['confirm'] )
			? sanitize_key( wp_unslash( $_POST['confirm'] ) )
			: '';

		if ( 'cutover' !== $confirm ) {
			wp_die( esc_html__( 'Cutover was not explicitly confirmed.', 'seo-geo-migration-bridge' ), '', array( 'response' => 400 ) );
		}

		$backup_evidence  = isset( $_POST['backup_evidence'] ) && is_string( $_POST['backup_evidence'] )
			? sanitize_textarea_field( wp_unslash( $_POST['backup_evidence'] ) )
			: '';
		$plugins          = isset( $_POST['plugins'] ) && is_string( $_POST['plugins'] )
			? sanitize_textarea_field( wp_unslash( $_POST['plugins'] ) )
			: '';
		$allowlist        = isset( $_POST['allowlist'] ) && is_string( $_POST['allowlist'] )
			? sanitize_textarea_field( wp_unslash( $_POST['allowlist'] ) )
			: '';
		$quality_evidence = isset( $_POST['quality_evidence'] ) && is_string( $_POST['quality_evidence'] )
			? sanitize_textarea_field( wp_unslash( $_POST['quality_evidence'] ) )
			: '';
		$maintenance      = isset( $_POST['maintenance'] ) && is_string( $_POST['maintenance'] )
			? sanitize_textarea_field( wp_unslash( $_POST['maintenance'] ) )
			: '';
		$nonce            = isset( $_POST['_wpnonce'] ) && is_string( $_POST['_wpnonce'] )
			? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) )
			: '';

		$result = $this->engine->execute(
			$this->decode_array_value( $backup_evidence ),
			$this->decode_string_list_value( $plugins ),
			$this->decode_list_value( $allowlist ),
			$this->decode_array_value( $quality_evidence ),
			$this->decode_array_value( $maintenance ),
			$nonce,
			true
		);

		$this->redirect_or_die( $result, 'cutover' );
	}

	/**
	 * Roll back the latest unaccepted cutover.
	 */
	public function handle_rollback(): never {
		$this->require_admin();
		check_admin_referer( CutoverEngine::rollback_nonce_action() );

		$confirm = isset( $_POST['confirm'] ) && is_string( $_POST['confirm'] )
			? sanitize_key( wp_unslash( $_POST['confirm'] ) )
			: '';

		if ( 'rollback' !== $confirm ) {
			wp_die( esc_html__( 'Rollback was not explicitly confirmed.', 'seo-geo-migration-bridge' ), '', array( 'response' => 400 ) );
		}

		$allowlist = isset( $_POST['allowlist'] ) && is_string( $_POST['allowlist'] )
			? sanitize_textarea_field( wp_unslash( $_POST['allowlist'] ) )
			: '';
		$nonce     = isset( $_POST['_wpnonce'] ) && is_string( $_POST['_wpnonce'] )
			? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) )
			: '';

		$result = $this->engine->rollback(
			$this->decode_list_value( $allowlist ),
			$nonce,
			true
		);

		$this->redirect_or_die( $result, 'rollback' );
	}

	/**
	 * Explicitly accept the latest cutover.
	 */
	public function handle_accept(): never {
		$this->require_admin();
		check_admin_referer( CutoverEngine::accept_nonce_action() );

		$confirm = isset( $_POST['confirm'] ) && is_string( $_POST['confirm'] )
			? sanitize_key( wp_unslash( $_POST['confirm'] ) )
			: '';

		if ( 'accept' !== $confirm ) {
			wp_die( esc_html__( 'Acceptance was not explicitly confirmed.', 'seo-geo-migration-bridge' ), '', array( 'response' => 400 ) );
		}

		$allowlist = isset( $_POST['allowlist'] ) && is_string( $_POST['allowlist'] )
			? sanitize_textarea_field( wp_unslash( $_POST['allowlist'] ) )
			: '';
		$nonce     = isset( $_POST['_wpnonce'] ) && is_string( $_POST['_wpnonce'] )
			? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) )
			: '';

		$result = $this->engine->accept(
			$this->decode_list_value( $allowlist ),
			$nonce,
			true
		);

		$this->redirect_or_die( $result, 'accept' );
	}

	/**
	 * Require administrator capability before parsing payloads.
	 */
	private function require_admin(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Administrator cutover capability is required.', 'seo-geo-migration-bridge' ), '', array( 'response' => 403 ) );
		}
	}

	/**
	 * Decode one JSON object/array value.
	 *
	 * @param mixed $value Raw request value.
	 * @return array<string,mixed>
	 */
	private function decode_array_value( mixed $value ): array {
		if ( ! is_string( $value ) || '' === trim( $value ) ) {
			return array();
		}

		$decoded = json_decode( $value, true );
		return is_array( $decoded ) ? $decoded : array();
	}

	/**
	 * Decode one JSON list value.
	 *
	 * @param mixed $value Raw request value.
	 * @return array<int,mixed>
	 */
	private function decode_list_value( mixed $value ): array {
		if ( ! is_string( $value ) || '' === trim( $value ) ) {
			return array();
		}

		$decoded = json_decode( $value, true );
		return is_array( $decoded ) && array_is_list( $decoded ) ? $decoded : array();
	}

	/**
	 * Decode one JSON list of strings.
	 *
	 * @param mixed $value Raw request value.
	 * @return list<string>
	 */
	private function decode_string_list_value( mixed $value ): array {
		return array_values(
			array_filter(
				$this->decode_list_value( $value ),
				'is_string'
			)
		);
	}

	/**
	 * Redirect after success or stop on an engine error.
	 *
	 * @param array<string,mixed>|WP_Error $result    Engine result.
	 * @param string                       $operation Operation label.
	 */
	private function redirect_or_die( array|WP_Error $result, string $operation ): never {
		if ( $result instanceof WP_Error ) {
			wp_die( esc_html( $result->get_error_message() ), '', array( 'response' => 400 ) );
		}

		$redirect = wp_get_referer();
		if ( ! is_string( $redirect ) || '' === $redirect ) {
			$redirect = admin_url();
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'seo_geo_cutover' => $operation . '-success',
				),
				$redirect
			)
		);
		exit;
	}
}
