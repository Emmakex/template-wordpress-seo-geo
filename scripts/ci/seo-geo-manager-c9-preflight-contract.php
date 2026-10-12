<?php
/**
 * Standalone contract for the C9 read-only field preflight.
 *
 * @package SeoGeoManager
 */

declare(strict_types=1);

namespace {
	final class C9ContractUser {
		public int $ID = 41;
	}

	$GLOBALS['c9_contract_stack']       = 'theme-native';
	$GLOBALS['c9_contract_https']       = true;
	$GLOBALS['c9_contract_manage']      = false;
	$GLOBALS['c9_contract_yoast_cap']   = false;
	$GLOBALS['c9_contract_fingerprint'] = str_repeat( 'a', 64 );

	function sanitize_key( string $value ): string {
		return strtolower( preg_replace( '/[^a-z0-9_\-]/', '', $value ) ?? '' );
	}

	function sanitize_text_field( string $value ): string {
		return trim( $value );
	}

	function wp_get_current_user(): C9ContractUser {
		return new C9ContractUser();
	}

	function current_user_can( string $capability ): bool {
		if ( 'edit_posts' === $capability ) {
			return true;
		}
		if ( 'manage_options' === $capability ) {
			return true === $GLOBALS['c9_contract_manage'];
		}
		if ( 'wpseo_edit_advanced_metadata' === $capability ) {
			return true === $GLOBALS['c9_contract_yoast_cap'];
		}

		return false;
	}

	function home_url( string $path = '' ): string {
		$scheme = true === $GLOBALS['c9_contract_https'] ? 'https' : 'http';
		return $scheme . '://field-one.example' . $path;
	}

	function site_url( string $path = '' ): string {
		return home_url( $path );
	}

	function is_ssl(): bool {
		return true === $GLOBALS['c9_contract_https'];
	}

	function wp_parse_url( string $url, int $component = -1 ) {
		return parse_url( $url, $component );
	}

	function wp_is_application_passwords_supported(): bool {
		return true;
	}

	function wp_is_application_passwords_available(): bool {
		return true;
	}
}

namespace SeoGeo\Manager\Support {
	final class PackageIdentity {
		/** @return array{algorithm:string,fingerprint:string,file_count:int,version:string} */
		public static function snapshot(): array {
			return array(
				'algorithm'   => 'sha256-tree-v1',
				'fingerprint' => (string) $GLOBALS['c9_contract_fingerprint'],
				'file_count'  => 103,
				'version'     => '0.3.49-test',
			);
		}
	}

	final class EnvironmentPolicy {
		/** @return array{type:string,fingerprint:string,write_approval_required:bool} */
		public static function snapshot(): array {
			return array(
				'type'                    => 'production',
				'fingerprint'             => str_repeat( 'e', 64 ),
				'write_approval_required' => true,
			);
		}
	}
}

namespace SeoGeo\Manager\Intelligence {
	final class SeoAuthorityScanner {
		/** @return array<string, mixed> */
		public static function scan(): array {
			if ( 'yoast' === $GLOBALS['c9_contract_stack'] ) {
				return array(
					'state'                      => 'resolved-external-provider-writable',
					'authority_resolved'         => true,
					'safe_to_write_seo_metadata' => true,
					'conflict'                   => false,
					'authority'                  => array( 'id' => 'yoast', 'adapter' => 'yoast-abilities' ),
				);
			}

			return array(
				'state'                      => 'resolved-theme-native-writable',
				'authority_resolved'         => true,
				'safe_to_write_seo_metadata' => true,
				'conflict'                   => false,
				'authority'                  => array( 'id' => 'seo-geo-theme', 'adapter' => 'seo-geo-theme-native' ),
			);
		}
	}
}

namespace {
	require_once dirname( __DIR__, 2 ) . '/packages/seo-geo-manager/src/Field/C9FieldPreflight.php';

	use SeoGeo\Manager\Field\C9FieldPreflight;

	function c9_assert( bool $condition, string $message ): void {
		if ( ! $condition ) {
			fwrite( STDERR, "C9 preflight contract failed: {$message}\n" );
			exit( 1 );
		}
	}

	$fingerprint = (string) $GLOBALS['c9_contract_fingerprint'];

	$theme = C9FieldPreflight::run( 'theme-native', '0.3.49-test', $fingerprint );
	c9_assert( 'ready-for-remote-proof' === $theme['status'], 'Theme stack should be ready.' );
	c9_assert( true === $theme['checks']['exact_manager_package'], 'Theme package identity must match.' );
	c9_assert( true === $theme['checks']['https'], 'Theme HTTPS must pass.' );
	c9_assert( true === $theme['checks']['least_privilege_principal'], 'Theme operator must be least privilege.' );
	c9_assert( true === $theme['checks']['expected_seo_authority'], 'Theme authority must match.' );
	c9_assert( false === $theme['authentication']['current_transport_proven'], 'Preflight must not claim transport proof.' );
	c9_assert( false === $theme['authentication']['secrets_returned'], 'Preflight must not return secrets.' );
	c9_assert( true === $theme['policy']['preflight_is_not_field_acceptance'], 'Preflight must not claim C9 closure.' );
	c9_assert( false === $theme['write_performed'], 'Preflight must remain read-only.' );

	$GLOBALS['c9_contract_stack']     = 'yoast';
	$GLOBALS['c9_contract_yoast_cap'] = true;
	$yoast = C9FieldPreflight::run( 'yoast', '0.3.49-test', $fingerprint );
	c9_assert( 'ready-for-remote-proof' === $yoast['status'], 'Yoast stack should be ready.' );
	c9_assert( true === $yoast['checks']['expected_seo_authority'], 'Yoast authority must match.' );
	c9_assert( true === $yoast['checks']['provider_permission'], 'Yoast advanced permission must pass.' );

	$GLOBALS['c9_contract_https'] = false;
	$http = C9FieldPreflight::run( 'yoast', '0.3.49-test', $fingerprint );
	c9_assert( 'blocked' === $http['status'], 'HTTP site must be blocked.' );
	c9_assert( false === $http['checks']['https'], 'HTTP site must fail HTTPS check.' );
	c9_assert( in_array( 'https', $http['blocking_checks'], true ), 'HTTPS blocker must be explicit.' );

	$GLOBALS['c9_contract_https'] = true;
	$invalid = C9FieldPreflight::run( 'unknown', '0.3.49-test', 'bad' );
	c9_assert( 'needs-input' === $invalid['status'], 'Invalid expected inputs must require input.' );
	c9_assert( false === $invalid['checks']['inputs_valid'], 'Invalid inputs must fail input validation.' );

	echo json_encode(
		array(
			'ok'                         => true,
			'theme_native_ready'         => true,
			'yoast_ready'                => true,
			'https_enforced'             => true,
			'exact_package_required'     => true,
			'preflight_read_only'        => true,
			'preflight_not_field_proof'  => true,
		),
		JSON_UNESCAPED_SLASHES
	) . PHP_EOL;
}
