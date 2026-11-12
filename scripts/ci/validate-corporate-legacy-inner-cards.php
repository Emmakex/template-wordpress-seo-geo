<?php
declare(strict_types=1);

/** Fixed-scope CSS safety acceptance for migrated Corporate inner cards. */
$path = dirname( __DIR__, 2 ) . '/packages/seo-geo-theme/assets/css/presets/corporate-inner-v1.css';
$css  = file_get_contents( $path );
if ( ! is_string( $css ) ) {
	throw new RuntimeException( 'Corporate inner CSS missing.' );
}
$required = array(
	'.wp-block-post-content:has(> .sgmb-divi-blurb ~ .sgmb-divi-blurb)',
	'> .sgmb-divi-blurb .wp-block-image img',
	'object-fit: contain;',
	'max-height: 11.5rem;',
	'.wp-block-latest-posts:has(> li:only-child)',
	'.wp-block-latest-posts:has(> li:nth-child(2):last-child)',
	'grid-template-columns: repeat(3, minmax(0, 1fr));',
	'grid-template-columns: repeat(2, minmax(0, 1fr));',
	'grid-template-columns: minmax(0, 1fr);',
);
foreach ( $required as $token ) {
	if ( ! str_contains( $css, $token ) ) {
		throw new RuntimeException( 'Missing reusable legacy inner-card selector: ' . $token );
	}
}
if ( str_contains( $css, 'page-id-50089' ) || str_contains( $css, 'page-id-50344' ) ) {
	throw new RuntimeException( 'Client post ID accidentally leaked into Theme CSS.' );
}
echo "PASS: reusable Corporate inner-card/Latest Posts CSS; no client page IDs\n";
