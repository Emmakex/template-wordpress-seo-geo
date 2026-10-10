<?php
/**
 * Runtime acceptance for the read-only SEO output authority resolver.
 *
 * Executed inside the isolated Manager WordPress fixture.
 */

use SeoGeo\Manager\Intelligence\SeoAuthorityScanner;

if ( ! defined( 'ABSPATH' ) ) {
	throw new RuntimeException( 'WordPress is not loaded.' );
}

/**
 * @param bool   $condition Assertion state.
 * @param string $message Failure message.
 */
function seo_geo_manager_authority_accept( bool $condition, string $message ): void {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
}

$original_plugins = get_option( 'active_plugins', array() );
$original_plugins = is_array( $original_plugins ) ? $original_plugins : array();

update_option( 'active_plugins', array() );
$theme = SeoAuthorityScanner::scan();
seo_geo_manager_authority_accept( true === ( $theme['authority_resolved'] ?? false ), 'Theme-native SEO authority was not resolved.' );
seo_geo_manager_authority_accept( 'resolved-theme-native-read-only' === ( $theme['state'] ?? '' ), 'Unexpected Theme-native SEO authority state.' );
seo_geo_manager_authority_accept( 'seo-geo-theme' === ( $theme['authority']['id'] ?? '' ), 'Theme-native authority identity mismatch.' );
seo_geo_manager_authority_accept( false === ( $theme['safe_to_write_seo_metadata'] ?? true ), 'Theme-native resolver enabled SEO writes before its write adapter exists.' );

update_option(
	'active_plugins',
	array(
		'wordpress-seo/wp-seo.php',
		'wordpress-seo-premium/wp-seo-premium.php',
	)
);
$yoast = SeoAuthorityScanner::scan();
seo_geo_manager_authority_accept( true === ( $yoast['authority_resolved'] ?? false ), 'Yoast SEO authority was not resolved.' );
seo_geo_manager_authority_accept( 'resolved-external-provider-read-only' === ( $yoast['state'] ?? '' ), 'Unexpected Yoast SEO authority state.' );
seo_geo_manager_authority_accept( 'yoast' === ( $yoast['authority']['id'] ?? '' ), 'Yoast family was not collapsed to one authority.' );
seo_geo_manager_authority_accept( 1 === count( $yoast['provider_families'] ?? array() ), 'Yoast free/premium variants were treated as separate authorities.' );
seo_geo_manager_authority_accept( false === ( $yoast['safe_to_write_seo_metadata'] ?? true ), 'External provider resolver enabled SEO writes before its write adapter exists.' );

update_option(
	'active_plugins',
	array(
		'wordpress-seo/wp-seo.php',
		'seo-by-rank-math/rank-math.php',
	)
);
$conflict = SeoAuthorityScanner::scan();
seo_geo_manager_authority_accept( false === ( $conflict['authority_resolved'] ?? true ), 'Multiple SEO providers were incorrectly resolved to one authority.' );
seo_geo_manager_authority_accept( true === ( $conflict['conflict'] ?? false ), 'Multiple SEO providers did not raise an authority conflict.' );
seo_geo_manager_authority_accept( 'multiple-provider-conflict' === ( $conflict['state'] ?? '' ), 'Unexpected multiple-provider authority state.' );
seo_geo_manager_authority_accept( false === ( $conflict['safe_to_write_seo_metadata'] ?? true ), 'SEO metadata writes were enabled during an authority conflict.' );

update_option( 'active_plugins', $original_plugins );

$result = array(
	'ok'                         => true,
	'theme_native_resolution'    => true,
	'provider_family_resolution' => true,
	'free_pro_collapse'          => true,
	'multiple_provider_conflict' => true,
	'provider_writes_disabled'   => true,
);

echo wp_json_encode( $result, JSON_UNESCAPED_SLASHES ) . PHP_EOL;
