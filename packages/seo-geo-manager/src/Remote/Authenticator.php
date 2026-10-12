<?php
/**
 * Authentication and scope gate for SEO/GEO Manager remote requests.
 *
 * @package SeoGeoManager
 */

declare(strict_types=1);

namespace SeoGeo\Manager\Remote;

use WP_Error;
use WP_REST_Request;

final class Authenticator {
	/** @var array<int, array<string, mixed>> */
	private array $contexts = array();

	public function __construct(
		private Credentials $credentials,
		private TokenService $tokens,
		private RequestGuard $guard,
		private Audit $audit
	) {
	}

	public function authorize( WP_REST_Request $request, string $required_scope, string $event = 'remote-read' ): bool|WP_Error {
		$metadata = $this->guard->validate_metadata( $request );
		if ( is_wp_error( $metadata ) ) {
			$this->record_failure( $request, $required_scope, $event, $metadata, array(), array() );
			return $metadata;
		}

		if ( $this->requires_ssl() && ! is_ssl() ) {
			$error = new WP_Error( 'authentication_failed', 'Remote Manager API requires HTTPS.', array( 'status' => 403 ) );
			$this->record_failure( $request, $required_scope, $event, $error, $metadata, array() );
			return $error;
		}

		$credential = $this->bearer_credential( $request );
		if ( '' === $credential ) {
			$error = new WP_Error( 'authentication_failed', 'Remote Manager bearer credential is required.', array( 'status' => 401 ) );
			$this->record_failure( $request, $required_scope, $event, $error, $metadata, array() );
			return $error;
		}

		$connection = $this->tokens->authenticate( $credential );
		if ( is_wp_error( $connection ) ) {
			$this->record_failure( $request, $required_scope, $event, $connection, $metadata, array() );
			return $connection;
		}

		if ( ! $this->credentials->has_scope( $connection, $required_scope ) ) {
			$error = new WP_Error( 'scope_denied', sprintf( 'Remote Manager connection does not grant %s.', $required_scope ), array( 'status' => 403 ) );
			$this->record_failure( $request, $required_scope, $event, $error, $metadata, $connection );
			return $error;
		}

		$rate = $this->guard->enforce_rate_limit( (string) $connection['connection_id'] );
		if ( is_wp_error( $rate ) ) {
			$this->record_failure( $request, $required_scope, $event, $rate, $metadata, $connection );
			return $rate;
		}

		$nonce = $this->guard->consume_nonce( (string) $connection['connection_id'], (string) $metadata['nonce'] );
		if ( is_wp_error( $nonce ) ) {
			$this->record_failure( $request, $required_scope, $event, $nonce, $metadata, $connection );
			return $nonce;
		}

		$user_id = (int) ( $connection['wordpress_user_id'] ?? 0 );
		wp_set_current_user( $user_id );
		if ( ! current_user_can( 'manage_options' ) ) {
			$error = new WP_Error( 'capability_unavailable', 'The local WordPress execution identity is not authorised.', array( 'status' => 403 ) );
			$this->record_failure( $request, $required_scope, $event, $error, $metadata, $connection );
			return $error;
		}

		$this->contexts[ spl_object_id( $request ) ] = array(
			'connection' => $connection,
			'metadata'   => $metadata,
			'scope'      => $required_scope,
			'event'      => $event,
		);
		$this->tokens->note_used( (string) $metadata['request_id'] );

		return true;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function context( WP_REST_Request $request ): array {
		return $this->contexts[ spl_object_id( $request ) ] ?? array();
	}

	/**
	 * @param array<string, scalar> $extra Bounded non-secret audit metadata.
	 */
	public function record_success( WP_REST_Request $request, array $extra = array() ): void {
		$context    = $this->context( $request );
		$metadata   = is_array( $context['metadata'] ?? null ) ? $context['metadata'] : array();
		$connection = is_array( $context['connection'] ?? null ) ? $context['connection'] : array();

		$this->audit->record(
			array(
				'request_id'        => (string) ( $metadata['request_id'] ?? '' ),
				'connection_id'     => (string) ( $connection['connection_id'] ?? '' ),
				'wordpress_user_id' => (int) ( $connection['wordpress_user_id'] ?? 0 ),
				'method'            => $request->get_method(),
				'route'             => $request->get_route(),
				'scope'             => (string) ( $context['scope'] ?? '' ),
				'event'             => (string) ( $context['event'] ?? 'remote-read' ),
				'outcome'           => 'success',
				'plan_id'           => (string) ( $extra['plan_id'] ?? '' ),
				'operation_id'      => (string) ( $extra['operation_id'] ?? '' ),
			)
		);
	}

	private function bearer_credential( WP_REST_Request $request ): string {
		$authorization = trim( (string) $request->get_header( 'authorization' ) );
		if ( ! preg_match( '/^Bearer\s+(.+)$/i', $authorization, $matches ) ) {
			return '';
		}

		return trim( (string) $matches[1] );
	}

	private function requires_ssl(): bool {
		$environment = function_exists( 'wp_get_environment_type' ) ? wp_get_environment_type() : 'production';

		return ! in_array( $environment, array( 'local', 'development' ), true );
	}

	/**
	 * @param array<string, mixed> $metadata Request metadata when valid.
	 * @param array<string, mixed> $connection Public connection state when available.
	 */
	private function record_failure( WP_REST_Request $request, string $scope, string $event, WP_Error $error, array $metadata, array $connection ): void {
		$this->audit->record(
			array(
				'request_id'        => (string) ( $metadata['request_id'] ?? '' ),
				'connection_id'     => (string) ( $connection['connection_id'] ?? '' ),
				'wordpress_user_id' => (int) ( $connection['wordpress_user_id'] ?? 0 ),
				'method'            => $request->get_method(),
				'route'             => $request->get_route(),
				'scope'             => $scope,
				'event'             => $event,
				'outcome'           => 'rejected',
				'error_code'        => $error->get_error_code(),
				'reason'            => $error->get_error_message(),
			)
		);
	}
}
