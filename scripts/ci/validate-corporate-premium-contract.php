<?php
/**
 * Static acceptance contract for Corporate Premium composition.
 *
 * @package SeoGeoTheme
 */

declare(strict_types=1);

$root    = dirname( __DIR__, 2 );
$premium = $root . '/packages/seo-geo-theme/assets/css/presets/corporate-premium.css';
$shared  = $root . '/packages/seo-geo-theme/assets/css/design-system.css';
$cta     = $root . '/packages/seo-geo-theme/patterns/cta.php';

$failures = array();

/**
 * Require a set of literal markers in one product-contract file.
 *
 * @param string       $file     File to inspect.
 * @param list<string> $needles  Required markers.
 * @param list<string> $failures Failure accumulator.
 * @param string       $label    Diagnostic label.
 */
function seo_geo_require_corporate_premium_markers( string $file, array $needles, array &$failures, string $label ): void {
	$content = is_file( $file ) ? file_get_contents( $file ) : false;
	if ( false === $content ) {
		$failures[] = $label . ':missing-file';
		return;
	}

	foreach ( $needles as $needle ) {
		if ( ! str_contains( $content, $needle ) ) {
			$failures[] = $label . ':missing-marker:' . $needle;
		}
	}
}

seo_geo_require_corporate_premium_markers(
	$shared,
	array(
		'--sg-shell:',
		'.seo-geo-bento',
		'.seo-geo-editorial-grid',
		'.seo-geo-process-sequence',
		'.seo-geo-final-cta',
		'prefers-reduced-motion',
		'@media print',
	),
	$failures,
	'shared-design-system'
);

seo_geo_require_corporate_premium_markers(
	$premium,
	array(
		'.seo-geo-front-page-title',
		'grid-template-columns: repeat(12, minmax(0, 1fr));',
		'.seo-geo-corporate-card-grid > .seo-geo-corporate-card:nth-child(1)',
		'grid-column: span 7;',
		'.seo-geo-corporate-card-grid > .seo-geo-corporate-card:nth-child(2)',
		'grid-column: span 5;',
		'.seo-geo-corporate-card-grid > .seo-geo-corporate-card:nth-child(3)',
		'grid-column: span 12;',
		'.seo-geo-corporate-process-grid::before',
		'.seo-geo-corporate-insights-query .wp-block-post-template > li:first-child',
		'.seo-geo-final-cta',
		'grid-template-areas:',
		'@media (max-width: 900px)',
		'@media print',
		'grid-template-columns: repeat(3, minmax(0, 1fr)) !important;',
		'.seo-geo-corporate-card--featured',
		'background: #fff !important;',
		'prefers-reduced-motion',
	),
	$failures,
	'corporate-premium'
);

seo_geo_require_corporate_premium_markers(
	$cta,
	array(
		'seo-geo-final-cta',
		'seo-geo-safe-copy',
		'seo-geo-content-slot--final-cta-heading',
		'seo-geo-content-slot--final-cta-body',
		'seo-geo-content-slot--final-cta-button',
	),
	$failures,
	'cta-pattern'
);

foreach ( array( $shared, $premium ) as $css_file ) {
	$css = is_file( $css_file ) ? file_get_contents( $css_file ) : false;
	if ( false === $css ) {
		continue;
	}
	if ( preg_match( '#@import\s+url|https?://|fonts\.(googleapis|gstatic)\.com#i', $css ) ) {
		$failures[] = 'remote-design-dependency:' . basename( $css_file );
	}
}

if ( array() !== $failures ) {
	fwrite( STDERR, "Corporate Premium contract failed:\n- " . implode( "\n- ", $failures ) . "\n" );
	exit( 1 );
}

fwrite( STDOUT, "Corporate Premium visual contract OK.\n" );
