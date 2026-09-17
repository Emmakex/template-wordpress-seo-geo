<?php
/**
 * SEO provider interoperability fixture.
 *
 * This file is copied into the disposable WordPress installation as a
 * must-use plugin. It uses documented public provider output filters to force
 * deterministic canonical and description values for the acceptance page.
 *
 * Rank Math's setup-complete option is test-only fixture state. Phase 3B
 * validates interoperability after each supported provider has been configured;
 * onboarding/incomplete-setup behavior belongs to the later setup phase.
 */

$seo_geo_provider_fixture = get_option( 'seo_geo_provider_fixture', '' );

if ( ! is_string( $seo_geo_provider_fixture ) || '' === $seo_geo_provider_fixture ) {
	return;
}

if ( 'rank-math' === $seo_geo_provider_fixture && ! get_option( 'rank_math_is_configured' ) ) {
	update_option( 'rank_math_is_configured', true, false );
}

$seo_geo_provider_fixture_canonical   = home_url( '/provider-seo-fixture/' );
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
