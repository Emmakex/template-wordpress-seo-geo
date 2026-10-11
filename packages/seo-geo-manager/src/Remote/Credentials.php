<?php
/**
 * Site-specific identity and scope state for the authenticated SEO/GEO Manager bridge.
 *
 * @package SeoGeoManager
 */

declare(strict_types=1);

namespace SeoGeo\Manager\Remote;

final class Credentials {
	private const OPTION = 'seo_geo_manager_remote_connection';

	/**
	 * @return list<string>
	 */
	public static function supported_scopes(): array {
		return array(
			'site.read',
			'site.diagnostics',
			'content.read',
			'content.write',
			'seo.read',
			'seo.write',
			'media.read',
			'media.write',
			'navigation.read',
			'navigation.write',
			'operations.read',
			'operations.apply',
			'operations.rollback',
			'credentials.manage',
		);
	}

	/**
	 * @return list<string>
	 */
	public static function default_scopes(): array {
		return array( 'site.read', 'site.diagnostics', 'operations.read' );
	}

	/**
	 * @return array<string, mixed>
	 */
	public function ensure_identity(): array {
		$state = $this->raw_state();
		if ( '' !== (string) ( $state['connection_id'] ?? '' ) ) {
			return $this->public_state( $state );
		}

		$state = array(
			'connection_id'     => 'sgmc_' . str_replace( '-', '', wp_generate_uuid4() ),
			'label'             => 'ChatGPT Manager Bridge',
			'enabled'           => false,
			'scopes'            => self::default_scopes(),
			'credential_hash'   => '',
			'credential_prefix' => '',
			'wordpress_user_id' => 0,
			'created_at'        => gmdate( DATE_W3C ),
			'rotated_at'        => '',
			'revoked_at'        => '',
			'last_used_at'      => '',
			'last_request_id'   => '',
			'generation'        => 0,
		);
		$this->persist( $state );

		return $this->public_state( $state );
	}

	/**
	 * @return array<string, mixed>
	 */
	public function state(): array {
		$state = $this->raw_state();
		if ( '' === (string) ( $state['connection_id'] ?? '' ) ) {
			return $this->ensure_identity();
		}

		return $this->public_state( $state );
	}

	/**
	 * @param list<string> $scopes Requested scopes.
	 * @return list<string>
	 */
	public function normalize_scopes( array $scopes ): array {
		$supported = self::supported_scopes();
		$clean     = array();

		foreach ( $scopes as $scope ) {
			$scope = strtolower( trim( (string) $scope ) );
			if ( in_array( $scope, $supported, true ) ) {
				$clean[] = $scope;
			}
		}

		return array_values( array_unique( $clean ) );
	}

	/**
	 * @param array<string, mixed> $connection Public connection state.
	 */
	public function has_scope( array $connection, string $scope ): bool {
		$scopes = is_array( $connection['scopes'] ?? null ) ? $connection['scopes'] : array();

		return in_array( $scope, $scopes, true );
	}

	/**
	 * @return array<string, mixed>
	 */
	public function raw_state(): array {
		$state = get_option( self::OPTION, array() );

		return is_array( $state ) ? $state : array();
	}

	/**
	 * @param array<string, mixed> $state Raw state.
	 */
	public function persist( array $state ): void {
		if ( false === get_option( self::OPTION, false ) ) {
			add_option( self::OPTION, $state, '', false );
			return;
		}

		update_option( self::OPTION, $state, false );
	}

	/**
	 * @param array<string, mixed> $state Raw connection state.
	 * @return array<string, mixed>
	 */
	public function public_state( array $state ): array {
		$scopes         = is_array( $state['scopes'] ?? null ) ? array_values( $state['scopes'] ) : array();
		$writes_enabled = false;

		foreach ( $scopes as $scope ) {
			if ( str_ends_with( (string) $scope, '.write' ) || in_array( $scope, array( 'operations.apply', 'operations.rollback' ), true ) ) {
				$writes_enabled = true;
				break;
			}
		}

		return array(
			'connection_id'      => (string) ( $state['connection_id'] ?? '' ),
			'label'              => (string) ( $state['label'] ?? '' ),
			'enabled'            => ! empty( $state['enabled'] ),
			'credential_present' => '' !== (string) ( $state['credential_hash'] ?? '' ),
			'credential_prefix'  => (string) ( $state['credential_prefix'] ?? '' ),
			'scopes'             => $scopes,
			'writes_enabled'     => $writes_enabled,
			'wordpress_user_id'  => (int) ( $state['wordpress_user_id'] ?? 0 ),
			'created_at'         => (string) ( $state['created_at'] ?? '' ),
			'rotated_at'         => (string) ( $state['rotated_at'] ?? '' ),
			'revoked_at'         => (string) ( $state['revoked_at'] ?? '' ),
			'last_used_at'       => (string) ( $state['last_used_at'] ?? '' ),
			'last_request_id'    => (string) ( $state['last_request_id'] ?? '' ),
			'generation'         => (int) ( $state['generation'] ?? 0 ),
		);
	}
}
