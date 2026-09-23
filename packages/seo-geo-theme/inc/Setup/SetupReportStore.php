<?php
/**
 * Persisted theme setup report reader.
 *
 * @package SeoGeoTheme
 */

declare(strict_types=1);

namespace SeoGeo\Theme\Setup;

/**
 * Defines and reads the stable non-sensitive setup report option.
 */
final class SetupReportStore {
	/**
	 * Stable report option consumed by future onboarding/release checks.
	 */
	public const OPTION_NAME = 'seo_geo_theme_setup_report_v1';

	/**
	 * Return the latest valid report.
	 *
	 * @return array<string,mixed>|null
	 */
	public function latest(): ?array {
		$value = get_option( self::OPTION_NAME, null );
		if (
			! is_array( $value )
			|| 1 !== ( $value['schema_version'] ?? null )
			|| 'theme-setup-report' !== ( $value['mode'] ?? null )
			|| ! is_string( $value['configuration_sha256'] ?? null )
			|| 1 !== preg_match( '/^[a-f0-9]{64}$/', $value['configuration_sha256'] )
			|| ! is_string( $value['report_sha256'] ?? null )
			|| 1 !== preg_match( '/^[a-f0-9]{64}$/', $value['report_sha256'] )
		) {
			return null;
		}

		return $value;
	}
}
