<?php
/**
 * SEO provider interoperability fixture.
 *
 * This file is copied into the disposable WordPress installation as a
 * must-use plugin. It uses only documented public provider filters to force
 * deterministic canonical and description values for the acceptance page.
 */

$seo_geo_provider_fixture = get_option( 'seo_geo_provider_fixture', '' );

if ( ! is_string( $seo_geo_provider_fixture ) || '' === $seo_geo_provider_fixture ) {
	return;
}

$seo_geo_provider_fixture_canonical   = home_url( '/provider-seo-canonical/' );
$seo_geo_provider_fixture_description = 'SEO GEO provider interoperability description.';

switch ( $seo_geo_provider_fixture ) {
	case 'yoast':
		add_filter(
			'wpseo_canonical',
			static function () use ( $seo_geo_provider_fixture_canonical ) {
				return $seo_geo_provider_fixture_canonical;
			}
		);
		add_filter(
			'wpseo_metadesc',
			static function () use ( $seo_geo_provider_fixture_description ) {
				return $seo_geo_provider_fixture_description;
			}
		);
		break;

	case 'rank-math':
		add_filter(
			'rank_math/frontend/canonical',
			static function () use ( $seo_geo_provider_fixture_canonical ) {
				return $seo_geo_provider_fixture_canonical;
			}
		);
		add_filter(
			'rank_math/frontend/description',
			static function () use ( $seo_geo_provider_fixture_description ) {
				return $seo_geo_provider_fixture_description;
			}
		);
		break;

	case 'aioseo':
		add_filter(
			'aioseo_canonical_url',
			static function () use ( $seo_geo_provider_fixture_canonical ) {
				return $seo_geo_provider_fixture_canonical;
			}
		);
		add_filter(
			'aioseo_description',
			static function () use ( $seo_geo_provider_fixture_description ) {
				return $seo_geo_provider_fixture_description;
			}
		);
		break;
}
