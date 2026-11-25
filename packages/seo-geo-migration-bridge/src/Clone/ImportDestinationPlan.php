<?php
/**
 * Read-only destination identity and replacement-intent classification.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge\Clone;

/**
 * Distinguish an intentional website replacement from a sandbox clone.
 *
 * This planner never authorizes a restore or alters WordPress runtime state.
 * Backups are handled externally. This planner only classifies the destination and requires a clear warning and explicit replacement confirmation.
 */
final class ImportDestinationPlan {
	/**
	 * Inspect the ZIP source and the CURRENT WordPress destination.
	 *
	 * @param string $source_home Source WordPress home URL from verified manifest.
	 * @param string $source_site Source WordPress siteurl from verified manifest.
	 * @param string $target_home Current destination home URL.
	 * @param string $target_site Current destination siteurl.
	 * @return array{status:string,scenario:string,source_home:string,target_home:string,source_site:string,target_site:string,show_external_backup_warning:bool,requires_confirmation:bool,restore_authorized:bool}
	 */
	public static function inspect( string $source_home, string $source_site, string $target_home, string $target_site ): array {
		$source_home_url = self::normalize( $source_home );
		$source_site_url = self::normalize( $source_site );
		$target_home_url = self::normalize( $target_home );
		$target_site_url = self::normalize( $target_site );

		$status   = 'requires-protected-production-import';
		$scenario = 'different-domain';
		if (
			null === $source_home_url
			|| null === $source_site_url
			|| null === $target_home_url
			|| null === $target_site_url
		) {
			$status   = 'invalid-site-identity';
			$scenario = 'unknown';
		} elseif (
			$source_home_url['url'] === $target_home_url['url']
			&& $source_site_url['url'] === $target_site_url['url']
		) {
			// A ZIP uploaded into the exact same WP installation is not a migration.
			$status   = 'same-site-replacement-not-automatic';
			$scenario = 'same-site';
		} elseif ( $source_home_url['origin'] === $target_home_url['origin'] ) {
			$scenario = 'same-domain-different-path';
			// A siteurl change alone, without a distinct home URL, needs review.
			if ( $source_home_url['url'] === $target_home_url['url'] ) {
				$status = 'same-home-different-siteurl-review';
			}
		}

		return array(
			'status'                       => $status,
			'scenario'                     => $scenario,
			'source_home'                  => $source_home,
			'target_home'                  => $target_home,
			'source_site'                  => $source_site,
			'target_site'                  => $target_site,
			'show_external_backup_warning' => true,
			'requires_confirmation'        => true,
			'restore_authorized'           => false,
		);
	}

	/**
	 * Canonicalize only ordinary web URLs, without userinfo or query data.
	 *
	 * @param string $url Absolute source or destination URL.
	 * @return array{url:string,origin:string}|null
	 */
	private static function normalize( string $url ): ?array {
		$parts = wp_parse_url( $url );
		if (
			! is_array( $parts )
			|| ! isset( $parts['scheme'], $parts['host'] )
			|| isset( $parts['user'] ) || isset( $parts['pass'] )
			|| isset( $parts['query'] ) || isset( $parts['fragment'] )
		) {
			return null;
		}

		$scheme = strtolower( (string) $parts['scheme'] );
		$host   = strtolower( rtrim( (string) $parts['host'], '.' ) );
		if ( ! in_array( $scheme, array( 'http', 'https' ), true ) || '' === $host ) {
			return null;
		}

		$port = isset( $parts['port'] ) ? (int) $parts['port'] : null;
		$path = (string) ( $parts['path'] ?? '/' );
		$path = '/' . trim( $path, '/' );
		$path = '/' === $path ? '/' : rtrim( $path, '/' );
		if ( str_contains( $path, '/../' ) || str_contains( $path, '/./' ) || str_contains( $path, '\\' ) ) {
			return null;
		}
		$origin = $scheme . '://' . $host . ( null !== $port ? ':' . $port : '' );

		return array(
			'url'    => $origin . $path,
			'origin' => $origin,
		);
	}
}
