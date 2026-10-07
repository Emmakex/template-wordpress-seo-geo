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

/**
 * Make the isolated Corporate performance fixture exercise the exact v3 Home
 * asset path. This filter exists only in the disposable acceptance MU-plugin;
 * production still detects v3 from the hydrated native Home content marker.
 *
 * @param bool $uses_master_home Theme-detected Home state.
 */
function seo_geo_acceptance_fixture_corporate_master_home( bool $uses_master_home ): bool {
	if ( ! isset( $_GET['fixture_preset'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only CI fixture selector.
		return $uses_master_home;
	}

	$requested = sanitize_key( wp_unslash( (string) $_GET['fixture_preset'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only CI fixture selector.

	return 'corporate' === $requested ? true : $uses_master_home;
}

add_filter( 'seo_geo_theme_corporate_master_home_layer', 'seo_geo_acceptance_fixture_corporate_master_home', 999 );
