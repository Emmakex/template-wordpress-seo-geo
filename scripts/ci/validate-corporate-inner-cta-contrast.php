<?php
declare(strict_types=1);

/** Contrast gate for native Gutenberg buttons on Corporate inner pages. */
$css = file_get_contents( dirname( __DIR__, 2 ) . '/packages/seo-geo-theme/assets/css/presets/corporate-inner-v1.css' );
if ( ! is_string( $css ) ) {
	throw new RuntimeException( 'Corporate inner stylesheet absent.' );
}
$required = array(
	'Contrast-safe native Gutenberg CTAs',
	'body.seo-geo-preset-corporate:not(.seo-geo-corporate-home-v5)',
	'.wp-block-post-content .wp-block-button > a',
	'.wp-block-post-content a.wp-block-button__link',
	'min-height: 44px;',
	'background-color: #087d67 !important;',
	'color: #fff !important;',
	'background-color: #075845 !important;',
);
foreach ( $required as $token ) {
	if ( ! str_contains( $css, $token ) ) {
		throw new RuntimeException( 'Missing scoped inner CTA contract: ' . $token );
	}
}
function linear_luminance( string $hex ): float {
	$rgb = array( hexdec( substr( $hex, 1, 2 ) ), hexdec( substr( $hex, 3, 2 ) ), hexdec( substr( $hex, 5, 2 ) ) );
	$components = array_map(
		static function ( int $v ): float {
			$c = $v / 255;
			return $c <= 0.04045 ? $c / 12.92 : ( ( $c + 0.055 ) / 1.055 ) ** 2.4;
		},
		$rgb
	);
	return $components[0] * 0.2126 + $components[1] * 0.7152 + $components[2] * 0.0722;
}
foreach ( array( '#087d67', '#075845' ) as $background ) {
	$contrast = ( linear_luminance( '#ffffff' ) + 0.05 ) / ( linear_luminance( $background ) + 0.05 );
	if ( $contrast < 4.5 ) {
		throw new RuntimeException( 'Corporate CTA contrast below WCAG AA' );
	}
}
echo "PASS: Corporate native inner CTAs have >=4.5:1 text contrast\n";
