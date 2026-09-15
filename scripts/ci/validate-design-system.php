<?php
/**
 * Validate the Phase 2A design-system contract.
 */

declare(strict_types=1);

const THEME_JSON = 'packages/seo-geo-theme/theme.json';

/**
 * Emit one structured actionable failure and stop.
 *
 * @param mixed $received Actual value.
 */
function fail_contract( string $code, string $message, string $path, string $expected, mixed $received ): never {
	$pipeline    = getenv( 'GITHUB_WORKFLOW' ) ?: 'design-system';
	$run_id      = getenv( 'GITHUB_RUN_ID' ) ?: 'local';
	$run_attempt = getenv( 'GITHUB_RUN_ATTEMPT' ) ?: '1';
	$job         = getenv( 'GITHUB_JOB' ) ?: 'design-system';
	$signature   = substr( hash( 'sha256', $code . ':' . $path . ':' . $message ), 0, 12 );

	$payload = array(
		'schema_version'    => 1,
		'pipeline'          => $pipeline,
		'run_id'            => $run_id,
		'run_attempt'       => $run_attempt,
		'job'               => $job,
		'step'              => 'design-system-contract',
		'command'           => 'php scripts/ci/validate-design-system.php',
		'exit_code'         => 1,
		'primary_error'     => $message,
		'file_line'         => THEME_JSON . ':' . $path,
		'expected'          => $expected,
		'received'          => is_scalar( $received ) || null === $received ? $received : json_encode( $received, JSON_UNESCAPED_SLASHES ),
		'error_signature'   => $signature,
		'root_cause_status' => 'unknown',
	);

	echo json_encode( $payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . PHP_EOL;
	exit( 1 );
}

/**
 * Read a nested array path.
 *
 * @param array<string, mixed> $data Parsed data.
 * @param list<string>         $path Keys to traverse.
 * @return mixed
 */
function value_at( array $data, array $path ): mixed {
	$value = $data;

	foreach ( $path as $key ) {
		if ( ! is_array( $value ) || ! array_key_exists( $key, $value ) ) {
			return null;
		}

		$value = $value[ $key ];
	}

	return $value;
}

/**
 * Build a slug => value map from WordPress preset arrays.
 *
 * @param mixed  $presets   Raw preset list.
 * @param string $value_key Value field to expose.
 * @return array<string, string>
 */
function preset_map( mixed $presets, string $value_key ): array {
	if ( ! is_array( $presets ) ) {
		return array();
	}

	$result = array();

	foreach ( $presets as $preset ) {
		if ( ! is_array( $preset ) || ! isset( $preset['slug'], $preset[ $value_key ] ) || ! is_string( $preset['slug'] ) || ! is_string( $preset[ $value_key ] ) ) {
			continue;
		}

		$result[ $preset['slug'] ] = $preset[ $value_key ];
	}

	return $result;
}

/**
 * Convert an sRGB hex channel to relative luminance.
 */
function linear_channel( int $channel ): float {
	$value = $channel / 255;
	return $value <= 0.04045 ? $value / 12.92 : ( ( $value + 0.055 ) / 1.055 ) ** 2.4;
}

/**
 * Calculate WCAG relative luminance from #RRGGBB.
 */
function luminance( string $hex ): float {
	if ( 1 !== preg_match( '/^#[0-9A-Fa-f]{6}$/', $hex ) ) {
		fail_contract( 'invalid-color', 'Design token is not a six-digit hex color.', 'settings.color.palette', '#RRGGBB', $hex );
	}

	$red   = linear_channel( hexdec( substr( $hex, 1, 2 ) ) );
	$green = linear_channel( hexdec( substr( $hex, 3, 2 ) ) );
	$blue  = linear_channel( hexdec( substr( $hex, 5, 2 ) ) );

	return ( 0.2126 * $red ) + ( 0.7152 * $green ) + ( 0.0722 * $blue );
}

/**
 * Calculate WCAG contrast ratio.
 */
function contrast_ratio( string $foreground, string $background ): float {
	$first  = luminance( $foreground );
	$second = luminance( $background );
	$high   = max( $first, $second );
	$low    = min( $first, $second );

	return ( $high + 0.05 ) / ( $low + 0.05 );
}

if ( ! is_file( THEME_JSON ) ) {
	fail_contract( 'missing-theme-json', 'Theme design-system source is missing.', '$', 'theme.json exists', 'missing' );
}

try {
	/** @var array<string, mixed> $theme */
	$theme = json_decode( (string) file_get_contents( THEME_JSON ), true, 512, JSON_THROW_ON_ERROR );
} catch ( JsonException $exception ) {
	fail_contract( 'invalid-json', 'theme.json could not be parsed.', '$', 'valid JSON', $exception->getMessage() );
}

$exact_contract = array(
	'$schema'                                         => 'https://schemas.wp.org/wp/7.1/theme.json',
	'version'                                         => 3,
	'settings.color.custom'                           => false,
	'settings.color.defaultPalette'                   => false,
	'settings.color.customGradient'                   => false,
	'settings.color.defaultGradients'                 => false,
	'settings.spacing.customSpacingSize'              => false,
	'settings.spacing.defaultSpacingSizes'            => false,
	'settings.typography.customFontSize'              => false,
	'settings.typography.defaultFontSizes'            => false,
	'settings.typography.dropCap'                     => false,
	'settings.layout.contentSize'                     => '720px',
	'settings.layout.wideSize'                        => '1200px',
	'styles.color.background'                         => 'var:preset|color|base',
	'styles.color.text'                               => 'var:preset|color|contrast',
	'styles.typography.fontFamily'                    => 'var:preset|font-family|system-sans',
	'styles.typography.fontSize'                      => 'var:preset|font-size|md',
	'styles.elements.link.color.text'                 => 'var:preset|color|accent',
	'styles.elements.link.typography.textDecoration'  => 'underline',
	'styles.elements.button.color.background'         => 'var:preset|color|accent',
	'styles.elements.button.color.text'               => 'var:preset|color|accent-contrast',
);

foreach ( $exact_contract as $dot_path => $expected ) {
	$actual = value_at( $theme, explode( '.', $dot_path ) );
	if ( $actual !== $expected ) {
		fail_contract( 'token-contract', 'Design-system value does not match the Phase 2A contract.', $dot_path, json_encode( $expected ), $actual );
	}
}

$expected_colors = array(
	'base'            => '#FFFFFF',
	'contrast'        => '#111827',
	'surface'         => '#F8FAFC',
	'muted'           => '#475569',
	'border'          => '#CBD5E1',
	'accent'          => '#1D4ED8',
	'accent-strong'   => '#1E40AF',
	'accent-contrast' => '#FFFFFF',
);
$colors          = preset_map( value_at( $theme, array( 'settings', 'color', 'palette' ) ), 'color' );

if ( $colors !== $expected_colors ) {
	fail_contract( 'palette-contract', 'Semantic color palette changed unexpectedly.', 'settings.color.palette', json_encode( $expected_colors ), $colors );
}

$expected_spacing = array(
	'2xs' => '0.25rem',
	'xs'  => '0.5rem',
	'sm'  => '0.75rem',
	'md'  => '1rem',
	'lg'  => '1.5rem',
	'xl'  => '2rem',
	'2xl' => '3rem',
	'3xl' => '4.5rem',
);
$spacing          = preset_map( value_at( $theme, array( 'settings', 'spacing', 'spacingSizes' ) ), 'size' );

if ( $spacing !== $expected_spacing ) {
	fail_contract( 'spacing-contract', 'Spacing scale changed unexpectedly.', 'settings.spacing.spacingSizes', json_encode( $expected_spacing ), $spacing );
}

$expected_font_sizes = array(
	'xs'  => '0.8125rem',
	'sm'  => '0.9375rem',
	'md'  => '1rem',
	'lg'  => '1.25rem',
	'xl'  => '1.75rem',
	'2xl' => '2.5rem',
	'3xl' => '3.5rem',
);
$font_sizes          = preset_map( value_at( $theme, array( 'settings', 'typography', 'fontSizes' ) ), 'size' );

if ( $font_sizes !== $expected_font_sizes ) {
	fail_contract( 'font-size-contract', 'Typography scale changed unexpectedly.', 'settings.typography.fontSizes', json_encode( $expected_font_sizes ), $font_sizes );
}

$font_families = value_at( $theme, array( 'settings', 'typography', 'fontFamilies' ) );
if ( ! is_array( $font_families ) || count( $font_families ) !== 2 ) {
	fail_contract( 'font-family-contract', 'The base theme must expose exactly two system font stacks.', 'settings.typography.fontFamilies', '2 system families', $font_families );
}

foreach ( $font_families as $index => $family ) {
	if ( ! is_array( $family ) || isset( $family['fontFace'] ) ) {
		fail_contract( 'remote-font-contract', 'Base font family must not declare web-font faces.', 'settings.typography.fontFamilies.' . (string) $index, 'system font stack without fontFace', $family );
	}
}

$contrast_contracts = array(
	array( 'contrast', 'base', 7.0 ),
	array( 'muted', 'base', 4.5 ),
	array( 'accent', 'base', 4.5 ),
	array( 'accent-contrast', 'accent', 4.5 ),
	array( 'contrast', 'surface', 7.0 ),
	array( 'accent-contrast', 'accent-strong', 4.5 ),
);

foreach ( $contrast_contracts as list( $foreground_slug, $background_slug, $minimum ) ) {
	$ratio = contrast_ratio( $colors[ $foreground_slug ], $colors[ $background_slug ] );
	if ( $ratio + 0.0001 < $minimum ) {
		fail_contract(
			'contrast-contract',
			'Critical semantic color pair fails the minimum contrast ratio.',
			'settings.color.palette.' . $foreground_slug . '/' . $background_slug,
			sprintf( '>= %.1f:1', $minimum ),
			sprintf( '%.2f:1', $ratio )
		);
	}
}

echo sprintf(
	"Design system OK: %d semantic colors, %d spacing tokens, %d font sizes, system fonts only, critical contrast pairs pass.\n",
	count( $colors ),
	count( $spacing ),
	count( $font_sizes )
);
