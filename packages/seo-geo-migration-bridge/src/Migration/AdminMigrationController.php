<?php
/**
 * Administrator-initiated Migration Engine entrypoint.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge\Migration;

use WP_Error;

/**
 * Handles explicit administrator migration submissions.
 */
final class AdminMigrationController {
	/**
	 * Admin-post action name.
	 */
	public const ACTION = 'seo_geo_migration_execute';

	/**
	 * Migration engine.
	 *
	 * @var MigrationEngine
	 */
	private MigrationEngine $engine;

	/**
	 * Construct the controller.
	 */
	public function __construct( MigrationEngine $engine ) {
		$this->engine = $engine;
	}

	/**
	 * Register the authenticated admin-post endpoint.
	 */
	public function boot(): void {
		add_action( 'admin_post_' . self::ACTION, array( $this, 'handle' ) );
	}

	/**
	 * Execute one explicitly confirmed migration request.
	 */
	public function handle(): never {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Administrator migration capability is required.', 'seo-geo-migration-bridge' ), '', array( 'response' => 403 ) );
		}

		$object_id  = isset( $_POST['object_id'] ) ? absint( wp_unslash( $_POST['object_id'] ) ) : 0;
		$adapter_id = isset( $_POST['adapter'] ) ? sanitize_key( wp_unslash( $_POST['adapter'] ) ) : '';
		$preset_id  = isset( $_POST['preset'] ) ? sanitize_key( wp_unslash( $_POST['preset'] ) ) : '';
		$confirm    = isset( $_POST['confirm'] ) ? sanitize_key( wp_unslash( $_POST['confirm'] ) ) : '';
		$nonce      = isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '';

		if ( 0 >= $object_id || '' === $adapter_id || 'migrate' !== $confirm ) {
			wp_die( esc_html__( 'Migration request is incomplete or was not explicitly confirmed.', 'seo-geo-migration-bridge' ), '', array( 'response' => 400 ) );
		}

		check_admin_referer( MigrationEngine::nonce_action( $object_id, $adapter_id ) );

		$result = $this->engine->execute(
			$object_id,
			$adapter_id,
			$nonce,
			'' !== $preset_id ? $preset_id : null,
			true
		);

		if ( $result instanceof WP_Error ) {
			wp_die( esc_html( $result->get_error_message() ), '', array( 'response' => 400 ) );
		}

		$redirect = wp_get_referer();
		if ( ! is_string( $redirect ) || '' === $redirect ) {
			$redirect = admin_url();
		}

		$redirect = add_query_arg(
			array(
				'seo_geo_migration' => 'success',
				'object_id'         => $object_id,
			),
			$redirect
		);

		wp_safe_redirect( $redirect );
		exit;
	}
}
