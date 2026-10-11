<?php
/**
 * Credential lifecycle for the SEO/GEO Manager remote bridge.
 *
 * @package SeoGeoManager
 */

declare(strict_types=1);

namespace SeoGeo\Manager\Remote;

use Throwable;
use WP_Error;
use WP_User;

final class TokenService {
	public function __construct( private Credentials $credentials ) {
	}

	/**
	 * @param list<string> $scopes Requested scopes.
	 * @return array<string, mixed>|WP_Error
	 */
	public function issue( array $scopes, int $wordpress_user_id, string $label = 'ChatGPT Manager Bridge' ): array|WP_Error {
		$user = get_user_by( 'id', $wordpress_user_id );
		if ( ! $user instanceof WP_User || ! user_can( $user, 'manage_options' ) ) {
			return new WP_Error( 'capability_unavailable', 'Remote Manager credentials require an authorised WordPress administrator.', array( 'status' => 403 ) );
		}

		$scopes = $this->credentials->normalize_scopes( $scopes );
		if ( array() === $scopes ) {
			return new WP_Error( 'validation_failed', 'At least one supported remote scope is required.', array( 'status' => 400 ) );
		}

		$state = $this->credentials->raw_state();
		if ( '' === (string) ( $state['connection_id'] ?? '' ) ) {
			$this->credentials->ensure_identity();
			$state = $this->credentials->raw_state();
		}

		try {
			$token = 'sgm1_' . bin2hex( random_bytes( 32 ) );
		} catch ( Throwable $error ) {
			return new WP_Error( 'capability_unavailable', 'Unable to generate a cryptographically secure remote credential.', array( 'status' => 500 ) );
		}

		$state['label']             = sanitize_text_field( $label ) ?: 'ChatGPT Manager Bridge';
		$state['enabled']           = true;
		$state['scopes']            = $scopes;
		$state['credential_hash']   = wp_hash_password( $token );
		$state['credential_prefix'] = substr( $token, 0, 13 );
		$state['wordpress_user_id'] = $wordpress_user_id;
		$state['rotated_at']        = gmdate( DATE_W3C );
		$state['revoked_at']        = '';
		$state['last_used_at']      = '';
		$state['last_request_id']   = '';
		$state['generation']        = max( 0, (int) ( $state['generation'] ?? 0 ) ) + 1;
		$this->credentials->persist( $state );

		return array(
			'credential' => $token,
			'connection' => $this->credentials->public_state( $state ),
		);
	}

	/**
	 * @return array<string, mixed>|WP_Error
	 */
	public function rotate( int $wordpress_user_id ): array|WP_Error {
		$state  = $this->credentials->raw_state();
		$scopes = is_array( $state['scopes'] ?? null ) ? $state['scopes'] : Credentials::default_scopes();
		$label  = (string) ( $state['label'] ?? 'ChatGPT Manager Bridge' );

		return $this->issue( $scopes, $wordpress_user_id, $label );
	}

	/**
	 * @return array<string, mixed>
	 */
	public function revoke(): array {
		$state                      = $this->credentials->raw_state();
		$state['enabled']           = false;
		$state['credential_hash']   = '';
		$state['credential_prefix'] = '';
		$state['revoked_at']        = gmdate( DATE_W3C );
		$state['last_request_id']   = '';
		$this->credentials->persist( $state );

		return $this->credentials->public_state( $state );
	}

	/**
	 * @return array<string, mixed>|WP_Error
	 */
	public function authenticate( string $token ): array|WP_Error {
		$state = $this->credentials->raw_state();
		if ( empty( $state['enabled'] ) || '' === (string) ( $state['credential_hash'] ?? '' ) ) {
			return new WP_Error( 'authentication_failed', 'Remote Manager access is disabled or has no active credential.', array( 'status' => 401 ) );
		}
		if ( '' === $token || ! wp_check_password( $token, (string) $state['credential_hash'] ) ) {
			return new WP_Error( 'authentication_failed', 'Remote Manager credential is invalid.', array( 'status' => 401 ) );
		}

		$user_id = (int) ( $state['wordpress_user_id'] ?? 0 );
		$user    = get_user_by( 'id', $user_id );
		if ( ! $user instanceof WP_User || ! user_can( $user, 'manage_options' ) ) {
			return new WP_Error( 'capability_unavailable', 'The WordPress administrator bound to this connection is no longer authorised.', array( 'status' => 403 ) );
		}

		return $this->credentials->public_state( $state );
	}

	public function note_used( string $request_id ): void {
		$state                    = $this->credentials->raw_state();
		$state['last_used_at']    = gmdate( DATE_W3C );
		$state['last_request_id'] = sanitize_text_field( $request_id );
		$this->credentials->persist( $state );
	}
}
