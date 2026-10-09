<?php
/**
 * Guarded 301 runtime for Manager-authorized historical permalink maps.
 *
 * @package SeoGeoManager
 */

declare(strict_types=1);

namespace SeoGeo\Manager\Support;

use WP_Error;

final class PermalinkRedirectRuntime {
	private const OPTION_NAME   = 'seo_geo_manager_permalink_redirect_runtime';
	private const MAX_REDIRECTS = 5000;

	public static function register(): void {
		add_action( 'template_redirect', array( self::class, 'maybe_redirect' ), 0 );
	}

	/**
	 * Return the active redirect-runtime state without exposing mutable internals.
	 *
	 * @return array<string, mixed>
	 */
	public static function snapshot(): array {
		$state = get_option( self::OPTION_NAME, array() );
		if ( ! is_array( $state ) || true !== ( $state['active'] ?? false ) ) {
			return self::inactive_snapshot();
		}

		$redirects = isset( $state['redirects'] ) && is_array( $state['redirects'] ) ? $state['redirects'] : array();

		return array(
			'active'                  => true,
			'operation_id'            => isset( $state['operation_id'] ) && is_string( $state['operation_id'] ) ? $state['operation_id'] : '',
			'plan_fingerprint'        => isset( $state['plan_fingerprint'] ) && is_string( $state['plan_fingerprint'] ) ? $state['plan_fingerprint'] : '',
			'environment_fingerprint' => isset( $state['environment_fingerprint'] ) && is_string( $state['environment_fingerprint'] ) ? $state['environment_fingerprint'] : '',
			'redirect_count'          => count( $redirects ),
			'redirects'               => $redirects,
			'fingerprint'             => isset( $state['fingerprint'] ) && is_string( $state['fingerprint'] ) ? $state['fingerprint'] : '',
			'created_at_gmt'          => isset( $state['created_at_gmt'] ) && is_string( $state['created_at_gmt'] ) ? $state['created_at_gmt'] : '',
		);
	}

	/**
	 * Validate and fingerprint a redirect map without writing WordPress state.
	 *
	 * @param array<int, mixed> $redirects Planned redirects.
	 * @return array<string, mixed>
	 */
	public static function preview( array $redirects, string $plan_fingerprint ): array {
		$normalized = self::normalize_redirects( $redirects );
		if ( is_wp_error( $normalized ) ) {
			return array(
				'mode'             => 'permalink-redirect-runtime-preview',
				'write_performed'  => false,
				'safe_to_activate' => false,
				'redirect_count'   => 0,
				'redirects'        => array(),
				'fingerprint'      => '',
				'block_reason'     => $normalized->get_error_message(),
				'active_runtime'   => self::snapshot(),
			);
		}

		$active   = self::snapshot();
		$conflict = true === ( $active['active'] ?? false );

		return array(
			'mode'             => 'permalink-redirect-runtime-preview',
			'write_performed'  => false,
			'safe_to_activate' => ! $conflict && array() !== $normalized && '' !== $plan_fingerprint,
			'redirect_count'   => count( $normalized ),
			'redirects'        => $normalized,
			'fingerprint'      => self::map_fingerprint( $normalized, $plan_fingerprint ),
			'block_reason'     => $conflict ? 'Ya existe un runtime 301 activo; debe resolverse o revertirse antes de instalar otro mapa.' : '',
			'active_runtime'   => $active,
		);
	}

	/**
	 * Activate a Manager-derived redirect map.
	 *
	 * No arbitrary public REST payload is accepted by this method. Callers must
	 * pass redirects obtained from an already verified authoritative plan.
	 *
	 * @param array<int, mixed> $redirects Planned redirects.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function activate( array $redirects, string $operation_id, string $plan_fingerprint ) {
		if ( '' === $operation_id || '' === $plan_fingerprint ) {
			return new WP_Error(
				'seo_geo_manager_redirect_runtime_identity_invalid',
				'Redirect runtime activation requires an operation ID and authoritative plan fingerprint.',
				array( 'status' => 400 )
			);
		}

		$normalized = self::normalize_redirects( $redirects );
		if ( is_wp_error( $normalized ) ) {
			return $normalized;
		}
		if ( array() === $normalized ) {
			return new WP_Error(
				'seo_geo_manager_redirect_runtime_empty',
				'Redirect runtime activation requires at least one verified redirect.',
				array( 'status' => 400 )
			);
		}

		$current = self::snapshot();
		if ( true === ( $current['active'] ?? false ) ) {
			$current_operation = (string) ( $current['operation_id'] ?? '' );
			$current_plan      = (string) ( $current['plan_fingerprint'] ?? '' );
			if ( $operation_id === $current_operation && $plan_fingerprint === $current_plan ) {
				return $current;
			}

			return new WP_Error(
				'seo_geo_manager_redirect_runtime_conflict',
				'Another Manager permalink redirect runtime is already active.',
				array(
					'status'              => 409,
					'active_operation_id' => $current_operation,
				)
			);
		}

		$environment = EnvironmentPolicy::snapshot();
		$fingerprint = self::map_fingerprint( $normalized, $plan_fingerprint );
		$state       = array(
			'active'                  => true,
			'operation_id'            => $operation_id,
			'plan_fingerprint'        => $plan_fingerprint,
			'environment_fingerprint' => (string) ( $environment['fingerprint'] ?? '' ),
			'redirects'               => $normalized,
			'fingerprint'             => $fingerprint,
			'created_at_gmt'          => gmdate( 'c' ),
		);

		if ( ! update_option( self::OPTION_NAME, $state, false ) ) {
			$stored = get_option( self::OPTION_NAME, array() );
			if ( ! is_array( $stored ) || $stored !== $state ) {
				return new WP_Error(
					'seo_geo_manager_redirect_runtime_write_failed',
					'The Manager could not persist the redirect runtime state.',
					array( 'status' => 500 )
				);
			}
		}

		return self::snapshot();
	}

	/**
	 * Remove the exact runtime owned by one Manager operation.
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	public static function deactivate( string $operation_id, string $expected_fingerprint ) {
		$current = self::snapshot();
		if ( false === ( $current['active'] ?? false ) ) {
			return $current;
		}

		$current_operation   = (string) ( $current['operation_id'] ?? '' );
		$current_fingerprint = (string) ( $current['fingerprint'] ?? '' );
		if ( $operation_id !== $current_operation || '' === $expected_fingerprint || ! hash_equals( $current_fingerprint, $expected_fingerprint ) ) {
			return new WP_Error(
				'seo_geo_manager_redirect_runtime_stale',
				'The active redirect runtime no longer matches this Manager operation; cleanup is blocked.',
				array(
					'status'                     => 409,
					'active_operation_id'        => $current_operation,
					'active_runtime_fingerprint' => $current_fingerprint,
				)
			);
		}

		delete_option( self::OPTION_NAME );
		$after = self::snapshot();
		if ( true === ( $after['active'] ?? false ) ) {
			return new WP_Error(
				'seo_geo_manager_redirect_runtime_delete_failed',
				'The Manager could not remove the redirect runtime state.',
				array( 'status' => 500 )
			);
		}

		return $after;
	}

	/**
	 * Resolve one request URI against the active runtime without sending headers.
	 *
	 * This pure resolver is used by runtime acceptance and keeps the redirect
	 * decision independently testable from PHP process termination.
	 *
	 * @return array<string, mixed>|null
	 */
	public static function resolve( string $request_uri ): ?array {
		$state = self::snapshot();
		if ( true !== ( $state['active'] ?? false ) ) {
			return null;
		}

		$logical_path = self::request_logical_path( $request_uri );
		if ( '' === $logical_path ) {
			return null;
		}

		foreach ( (array) ( $state['redirects'] ?? array() ) as $redirect ) {
			if ( ! is_array( $redirect ) || (string) ( $redirect['source_path'] ?? '' ) !== $logical_path ) {
				continue;
			}

			$target_path = (string) ( $redirect['target_path'] ?? '' );
			$target_url  = home_url( $target_path );
			$query       = wp_parse_url( $request_uri, PHP_URL_QUERY );
			if ( is_string( $query ) && '' !== $query ) {
				$target_url .= '?' . $query;
			}

			return array(
				'status'       => 301,
				'source_path'  => $logical_path,
				'target_path'  => $target_path,
				'target_url'   => $target_url,
				'operation_id' => (string) ( $state['operation_id'] ?? '' ),
				'fingerprint'  => (string) ( $state['fingerprint'] ?? '' ),
			);
		}

		return null;
	}

	public static function maybe_redirect(): void {
		if ( is_admin() || wp_doing_ajax() ) {
			return;
		}

		$method = isset( $_SERVER['REQUEST_METHOD'] )
			? strtoupper( sanitize_key( wp_unslash( (string) $_SERVER['REQUEST_METHOD'] ) ) )
			: 'GET';
		if ( ! in_array( $method, array( 'GET', 'HEAD' ), true ) ) {
			return;
		}

		$request_uri = isset( $_SERVER['REQUEST_URI'] )
			? sanitize_text_field( wp_unslash( (string) $_SERVER['REQUEST_URI'] ) )
			: '';
		$resolved = self::resolve( $request_uri );
		if ( ! is_array( $resolved ) ) {
			return;
		}

		wp_safe_redirect( (string) $resolved['target_url'], 301, 'SEO/GEO Manager' );
		exit;
	}

	/**
	 * @param array<int, mixed> $redirects Planned redirects.
	 * @return array<int, array{post_id:int,source_path:string,target_path:string,status:int}>|WP_Error
	 */
	private static function normalize_redirects( array $redirects ) {
		if ( count( $redirects ) > self::MAX_REDIRECTS ) {
			return new WP_Error(
				'seo_geo_manager_redirect_runtime_too_large',
				'The redirect map exceeds the bounded runtime limit.',
				array( 'status' => 409 )
			);
		}

		$normalized = array();
		$sources    = array();
		foreach ( $redirects as $redirect ) {
			if ( ! is_array( $redirect ) ) {
				return new WP_Error(
					'seo_geo_manager_redirect_runtime_row_invalid',
					'The redirect map contains an invalid row.',
					array( 'status' => 400 )
				);
			}

			$source = self::normalize_path( isset( $redirect['source_path'] ) && is_string( $redirect['source_path'] ) ? $redirect['source_path'] : '' );
			$target = self::normalize_path( isset( $redirect['target_path'] ) && is_string( $redirect['target_path'] ) ? $redirect['target_path'] : '' );
			if ( '' === $source || '/' === $source || '' === $target || $source === $target ) {
				return new WP_Error(
					'seo_geo_manager_redirect_runtime_path_invalid',
					'The redirect map contains an empty, root, identical or otherwise invalid path.',
					array( 'status' => 400 )
				);
			}
			if ( isset( $sources[ $source ] ) ) {
				return new WP_Error(
					'seo_geo_manager_redirect_runtime_duplicate_source',
					'The redirect map contains a duplicate historical source path.',
					array( 'status' => 409 )
				);
			}

			$sources[ $source ] = true;
			$normalized[]       = array(
				'post_id'     => isset( $redirect['post_id'] ) ? absint( $redirect['post_id'] ) : 0,
				'source_path' => $source,
				'target_path' => $target,
				'status'      => 301,
			);
		}

		foreach ( $normalized as $redirect ) {
			if ( isset( $sources[ $redirect['target_path'] ] ) ) {
				return new WP_Error(
					'seo_geo_manager_redirect_runtime_chain_blocked',
					'The redirect map contains a chain or loop; only one-hop 301 targets are accepted.',
					array( 'status' => 409 )
				);
			}
		}

		return $normalized;
	}

	private static function normalize_path( string $path ): string {
		$path = trim( $path );
		if ( '' === $path || false !== strpos( $path, '?' ) || false !== strpos( $path, '#' ) || preg_match( '#^[a-z][a-z0-9+.-]*://#i', $path ) ) {
			return '';
		}

		$path = '/' . ltrim( $path, '/' );
		$path = (string) preg_replace( '#/+#', '/', $path );
		if ( preg_match( '#(^|/)\.\.?(/|$)#', rawurldecode( $path ) ) ) {
			return '';
		}

		return '/' === $path ? '/' : trailingslashit( $path );
	}

	private static function request_logical_path( string $request_uri ): string {
		$request_path = wp_parse_url( $request_uri, PHP_URL_PATH );
		if ( ! is_string( $request_path ) || '' === $request_path ) {
			return '';
		}

		$base_path = wp_parse_url( home_url( '/' ), PHP_URL_PATH );
		$base_path = is_string( $base_path ) ? '/' . trim( $base_path, '/' ) : '/';
		$base_path = '/' === $base_path ? '/' : trailingslashit( $base_path );
		$request   = '/' . ltrim( $request_path, '/' );
		if ( '/' !== $base_path ) {
			$prefix = untrailingslashit( $base_path );
			if ( 0 !== strpos( $request, $prefix . '/' ) && $request !== $prefix ) {
				return '';
			}
			$request = '/' . ltrim( substr( $request, strlen( $prefix ) ), '/' );
		}

		return self::normalize_path( $request );
	}

	/**
	 * @param array<int, array{post_id:int,source_path:string,target_path:string,status:int}> $redirects Redirect map.
	 */
	private static function map_fingerprint( array $redirects, string $plan_fingerprint ): string {
		return hash(
			'sha256',
			(string) wp_json_encode(
				array(
					'plan_fingerprint' => $plan_fingerprint,
					'home_url'         => home_url( '/' ),
					'redirects'        => $redirects,
				)
			)
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function inactive_snapshot(): array {
		return array(
			'active'                  => false,
			'operation_id'            => '',
			'plan_fingerprint'        => '',
			'environment_fingerprint' => '',
			'redirect_count'          => 0,
			'redirects'               => array(),
			'fingerprint'             => '',
			'created_at_gmt'          => '',
		);
	}
}
