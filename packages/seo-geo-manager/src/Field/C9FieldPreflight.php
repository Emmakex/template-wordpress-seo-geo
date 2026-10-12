<?php
/**
 * Read-only C9 field-acceptance preflight.
 *
 * @package SeoGeoManager
 */

declare(strict_types=1);

namespace SeoGeo\Manager\Field;

use SeoGeo\Manager\Intelligence\SeoAuthorityScanner;
use SeoGeo\Manager\Support\EnvironmentPolicy;
use SeoGeo\Manager\Support\PackageIdentity;

/** Prove runtime prerequisites without claiming transport, mutation or revocation evidence. */
final class C9FieldPreflight {
	private const SCHEMA_VERSION = 1;
	private const STACK_THEME    = 'theme-native';
	private const STACK_YOAST    = 'yoast';

	/**
	 * Build one bounded, inspection-only C9 report.
	 *
	 * @return array<string, mixed>
	 */
	public static function run( string $expected_stack, string $expected_version, string $expected_package_fingerprint ): array {
		$expected_stack               = sanitize_key( $expected_stack );
		$expected_version             = trim( sanitize_text_field( $expected_version ) );
		$expected_package_fingerprint = strtolower( trim( sanitize_text_field( $expected_package_fingerprint ) ) );
		$package                      = PackageIdentity::snapshot();
		$environment                  = EnvironmentPolicy::snapshot();
		$seo                          = SeoAuthorityScanner::scan();
		$authority                    = isset( $seo['authority'] ) && is_array( $seo['authority'] ) ? $seo['authority'] : array();
		$current_user                 = wp_get_current_user();
		$home_url                     = home_url( '/' );
		$site_url                     = site_url( '/' );

		$inputs_valid = in_array( $expected_stack, array( self::STACK_THEME, self::STACK_YOAST ), true )
			&& '' !== $expected_version
			&& 1 === preg_match( '/^[a-f0-9]{64}$/', $expected_package_fingerprint );
		$package_match = $inputs_valid
			&& hash_equals( $expected_version, (string) $package['version'] )
			&& hash_equals( $expected_package_fingerprint, (string) $package['fingerprint'] );
		$https_ready = is_ssl()
			&& 'https' === strtolower( (string) wp_parse_url( $home_url, PHP_URL_SCHEME ) )
			&& 'https' === strtolower( (string) wp_parse_url( $site_url, PHP_URL_SCHEME ) );
		$app_passwords_supported = function_exists( 'wp_is_application_passwords_supported' )
			&& wp_is_application_passwords_supported();
		$app_passwords_available = function_exists( 'wp_is_application_passwords_available' )
			&& wp_is_application_passwords_available();
		$editorial_access = 0 < (int) $current_user->ID && current_user_can( 'edit_posts' );
		$least_privilege  = $editorial_access && ! current_user_can( 'manage_options' );
		$authority_match  = self::authority_matches( $expected_stack, $seo, $authority );
		$provider_access  = self::provider_access( $expected_stack );
		$conflict_free    = true !== ( $seo['conflict'] ?? false );

		$checks = array(
			'inputs_valid'                  => $inputs_valid,
			'exact_manager_package'         => $package_match,
			'https'                         => $https_ready,
			'application_passwords_supported' => $app_passwords_supported,
			'application_passwords_available' => $app_passwords_available,
			'authenticated_editorial_principal' => $editorial_access,
			'least_privilege_principal'     => $least_privilege,
			'expected_seo_authority'        => $authority_match,
			'provider_permission'           => $provider_access,
			'single_authority'              => $conflict_free,
		);
		$blocking = array_keys(
			array_filter(
				$checks,
				static fn ( bool $passed ): bool => ! $passed
			)
		);

		$status = 'ready-for-remote-proof';
		if ( ! $inputs_valid ) {
			$status = 'needs-input';
		} elseif ( array() !== $blocking ) {
			$status = 'blocked';
		}

		return array(
			'schema_version' => self::SCHEMA_VERSION,
			'mode'           => 'c9-field-preflight',
			'read_only'      => true,
			'write_performed'=> false,
			'generated_at_gmt' => gmdate( 'c' ),
			'status'         => $status,
			'expected'       => array(
				'stack'               => $expected_stack,
				'manager_version'     => $expected_version,
				'package_fingerprint' => $expected_package_fingerprint,
			),
			'package'        => $package,
			'environment'    => array_merge(
				$environment,
				array(
					'home_url' => $home_url,
					'site_url' => $site_url,
					'is_ssl'   => $https_ready,
				)
			),
			'principal'      => array(
				'user_id'             => (int) $current_user->ID,
				'authenticated'       => 0 < (int) $current_user->ID,
				'edit_posts'          => current_user_can( 'edit_posts' ),
				'manage_options'      => current_user_can( 'manage_options' ),
				'yoast_advanced_meta' => current_user_can( 'wpseo_edit_advanced_metadata' ),
			),
			'authentication' => array(
				'application_passwords_supported' => $app_passwords_supported,
				'application_passwords_available' => $app_passwords_available,
				'current_transport_proven'         => false,
				'secrets_returned'                 => false,
			),
			'seo_authority'   => $seo,
			'checks'          => $checks,
			'blocking_checks' => $blocking,
			'pending_real_proofs' => array(
				'application-password-authentication',
				'reversible-draft-seo-operation',
				'application-password-revocation',
			),
			'policy' => array(
				'preflight_is_not_field_acceptance' => true,
				'no_secret_reflection'              => true,
				'no_content_write'                  => true,
				'no_seo_write'                      => true,
				'no_operation_created'              => true,
				'same_package_required'             => true,
				'no_client_specific_fork'           => true,
			),
		);
	}

	/**
	 * @param array<string, mixed> $seo SEO authority scan.
	 * @param array<string, mixed> $authority Resolved authority.
	 */
	private static function authority_matches( string $expected_stack, array $seo, array $authority ): bool {
		if ( true !== ( $seo['safe_to_write_seo_metadata'] ?? false ) || true === ( $seo['conflict'] ?? false ) ) {
			return false;
		}

		if ( self::STACK_THEME === $expected_stack ) {
			return 'resolved-theme-native-writable' === ( $seo['state'] ?? '' )
				&& 'seo-geo-theme' === ( $authority['id'] ?? '' )
				&& 'seo-geo-theme-native' === ( $authority['adapter'] ?? '' );
		}

		if ( self::STACK_YOAST === $expected_stack ) {
			return 'resolved-external-provider-writable' === ( $seo['state'] ?? '' )
				&& 'yoast' === ( $authority['id'] ?? '' )
				&& 'yoast-abilities' === ( $authority['adapter'] ?? '' );
		}

		return false;
	}

	private static function provider_access( string $expected_stack ): bool {
		if ( self::STACK_YOAST === $expected_stack ) {
			return current_user_can( 'wpseo_edit_advanced_metadata' );
		}

		return self::STACK_THEME === $expected_stack && current_user_can( 'edit_posts' );
	}
}
