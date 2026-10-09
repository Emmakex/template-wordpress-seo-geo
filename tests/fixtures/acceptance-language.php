<?php
/**
 * Browser-acceptance locale and preset fixture.
 *
 * This MU-plugin is copied only into the disposable CI WordPress install. It
 * lets one fixture exercise EN and ES requests and one isolated request exercise
 * Corporate without persisting those test-only choices into WordPress state.
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

if ( isset( $_GET['fixture_media'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Disposable browser fixture selector.
	$fixture_media = sanitize_key( wp_unslash( (string) $_GET['fixture_media'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Disposable browser fixture selector.
	if ( 'atlas' === $fixture_media ) {
		$atlas_path = WP_CONTENT_DIR . '/themes/seo-geo-theme/assets/images/presets/corporate/v5/mf08-media-atlas.webp';
		if ( is_dir( dirname( $atlas_path ) ) && ! is_file( $atlas_path ) ) {
			touch( $atlas_path );
		}
	}
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
 * Activate Corporate only for the isolated acceptance request.
 *
 * Returning the incoming pre-option value for every other request leaves the
 * real persisted preset state untouched.
 *
 * @param mixed $pre_option Existing short-circuit value.
 * @return mixed
 */
function seo_geo_acceptance_fixture_active_preset( $pre_option ) {
	if ( ! isset( $_GET['fixture_preset'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only CI fixture selector.
		return $pre_option;
	}

	$requested = sanitize_key( wp_unslash( (string) $_GET['fixture_preset'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only CI fixture selector.

	return 'corporate' === $requested ? 'corporate' : $pre_option;
}

add_filter( 'pre_option_seo_geo_active_preset', 'seo_geo_acceptance_fixture_active_preset', 999 );

/**
 * Make the isolated Corporate performance/visual fixture exercise the master
 * Home asset path. This exists only in the disposable acceptance MU-plugin.
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
