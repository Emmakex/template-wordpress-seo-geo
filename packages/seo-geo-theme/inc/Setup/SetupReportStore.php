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

		$expected = $value['report_sha256'];
		unset( $value['report_sha256'] );

		if ( ! hash_equals( $expected, $this->fingerprint( $value ) ) ) {
			return null;
		}

		$value['report_sha256'] = $expected;

		return $value;
	}

	/**
	 * Produce the same deterministic fingerprint used by the executor.
	 *
	 * @param mixed $value Report value.
	 */
	private function fingerprint( mixed $value ): string {
		$encoded = wp_json_encode( $this->canonical( $value ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );

		return hash( 'sha256', false === $encoded ? '' : $encoded );
	}

	/**
	 * Canonicalize nested arrays before hashing.
	 *
	 * @param mixed $value Candidate value.
	 */
	private function canonical( mixed $value ): mixed {
		if ( ! is_array( $value ) ) {
			return $value;
		}

		if ( array_is_list( $value ) ) {
			return array_map( array( $this, 'canonical' ), $value );
		}

		ksort( $value );
		foreach ( $value as $key => $item ) {
			$value[ $key ] = $this->canonical( $item );
		}

		return $value;
	}
}
