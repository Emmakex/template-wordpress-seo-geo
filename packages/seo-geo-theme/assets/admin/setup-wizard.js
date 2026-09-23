/**
 * Focus Phase 9D validation results after page load.
 *
 * @package SeoGeoTheme
 */

document.addEventListener(
	'DOMContentLoaded',
	function () {
		var results = document.getElementById( 'seo-geo-setup-results' );

		if ( results ) {
			results.focus();
		}
	}
);
