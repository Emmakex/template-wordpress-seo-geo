<?php
/**
 * Browser-acceptance locale fixture.
 *
 * This MU-plugin is copied only into the disposable CI WordPress install. It
 * lets one fixture exercise EN and ES requests without pretending that native
 * single-language WordPress is a multilingual provider.
 *
 * @package SeoGeoAcceptance
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'SEO_GEO_ACCEPTANCE_FIXTURE' ) || true !== SEO_GEO_ACCEPTANCE_FIXTURE ) {
	return;
}

/**
 * Resolve an acceptance-only locale from the fixture query parameter.
 *
 * @param string $locale Current WordPress locale.
 * @return string
 */
function seo_geo_acceptance_fixture_locale( string $locale ): string {
	if ( ! isset( $_GET['fixture_lang'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only CI fixture selector.
		return $locale;
	}

	$requested = sanitize_key( wp_unslash( (string) $_GET['fixture_lang'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only CI fixture selector.

	if ( 'es' === $requested ) {
		return 'es_ES';
	}

	if ( 'en' === $requested ) {
		return 'en_US';
	}

	return $locale;
}

add_filter( 'locale', 'seo_geo_acceptance_fixture_locale', 999 );
add_filter( 'determine_locale', 'seo_geo_acceptance_fixture_locale', 999 );
