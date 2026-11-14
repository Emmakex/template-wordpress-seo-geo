<?php
/**
 * Isolated regression tests for WordPress classic menu hierarchy and footer.
 *
 * No WordPress connection or website mutation is performed.
 */
declare(strict_types=1);

use SeoGeo\Theme\Navigation\PresetNavigationRuntime;

class WP_Post {
	public int $ID = 0;
	public string $post_status = 'publish';
	public string $url = '';
	public string $title = '';
	public int $menu_item_parent = 0;
}

$GLOBALS['nav_fixture_same_menu'] = true;
$GLOBALS['nav_fixture_items']     = array();

function menu_fixture( int $id, string $label, string $path, int $parent = 0 ): WP_Post {
	$item                   = new WP_Post();
	$item->ID               = $id;
	$item->title            = $label;
	$item->url              = 'https://example.test/nuevaweb/' . $path;
	$item->menu_item_parent = $parent;
	return $item;
}

$GLOBALS['nav_fixture_items'][6] = array(
	menu_fixture( 1, 'EMMAKE', '' ),
	menu_fixture( 2, 'Servicios', 'servicios/' ),
	menu_fixture( 3, 'Asistentes de voz', 'asistentes-virtuales-de-voz/', 2 ),
	menu_fixture( 4, 'Sobre Nosotros', 'sobre-nosotros/' ),
	menu_fixture( 5, 'Nuestras Marcas', 'nuestras-marcas/' ),
	menu_fixture( 6, 'Trabaja con Nosotros', 'trabaja-con-nosotros/' ),
	menu_fixture( 7, 'Blog', 'blog/' ),
	menu_fixture( 8, 'Blog digital', 'blog-transformacion-digital/', 7 ),
	menu_fixture( 9, 'Contacto', 'contacto/' ),
);
$GLOBALS['nav_fixture_items'][7] = array(
	menu_fixture( 10, 'Política de privacidad', 'privacidad/' ),
	menu_fixture( 11, 'Contacto', 'contacto/' ),
);

function get_nav_menu_locations(): array {
	return array(
		'primary' => 6,
		'footer'  => $GLOBALS['nav_fixture_same_menu'] ? 6 : 7,
	);
}

function wp_get_nav_menu_items( int $term_id ): array {
	return $GLOBALS['nav_fixture_items'][ $term_id ] ?? array();
}

function esc_url( string $value ): string {
	return htmlspecialchars( $value, ENT_QUOTES, 'UTF-8' );
}

function esc_html( string $value ): string {
	return htmlspecialchars( $value, ENT_QUOTES, 'UTF-8' );
}

function esc_attr( string $value ): string {
	return esc_html( $value );
}

function __( string $value, string $domain = '' ): string {
	return $value;
}

function esc_html__( string $value, string $domain = '' ): string {
	return esc_html( $value );
}

function wp_strip_all_tags( string $value ): string {
	return strip_tags( $value );
}

function sanitize_key( string $value ): string {
	return preg_replace( '/[^a-z0-9_-]/', '', strtolower( $value ) ) ?? '';
}

function sanitize_text_field( string $value ): string {
	return $value;
}

function wp_unslash( string $value ): string {
	return $value;
}

function wp_parse_url( string $value, int $component = -1 ): mixed {
	return parse_url( $value, $component );
}

function untrailingslashit( string $value ): string {
	return rtrim( $value, '/' );
}

function seo_geo_theme_active_preset_id(): string {
	return 'corporate';
}

function seo_geo_theme_preset_locale(): string {
	return 'es_ES';
}

function seo_geo_theme_preset_document( string $preset, string $document ): array {
	if ( 'preset.json' === $document ) {
		return array( 'navigation' => array( 'footer' => array( 'privacy-policy', 'legal-notice', 'contact' ) ) );
	}
	return array(
		'locales' => array(
			'es_ES' => array(
				'pages' => array(
					array( 'key' => 'privacy-policy', 'title' => 'Privacidad', 'slug' => 'privacidad' ),
					array( 'key' => 'legal-notice', 'title' => 'Aviso legal', 'slug' => 'aviso-legal' ),
					array( 'key' => 'contact', 'title' => 'Contacto', 'slug' => 'contacto' ),
				),
			),
		),
	);
}

function get_privacy_policy_url(): string {
	return 'https://example.test/nuevaweb/privacidad/';
}

function get_page_by_path( string $path, string $type = 'OBJECT', string $post_type = 'page' ): ?WP_Post {
	if ( 'contacto' !== $path ) {
		return null;
	}
	$item              = menu_fixture( 77, 'Contacto', 'contacto/' );
	$item->post_status = 'publish';
	return $item;
}

function get_permalink( WP_Post|int $post ): string {
	return $post instanceof WP_Post ? $post->url : '';
}

require_once dirname( __DIR__, 2 ) . '/packages/seo-geo-theme/inc/Navigation/PresetNavigationRuntime.php';

function assert_nav( bool $condition, string $message ): void {
	if ( ! $condition ) {
		fwrite( STDERR, "Navigation regression failed: $message\n" );
		exit( 1 );
	}
}

$_SERVER['REQUEST_URI'] = '/nuevaweb/servicios/';
$renderer = new PresetNavigationRuntime();

$primary = $renderer->render( array( 'location' => 'primary' ) );
assert_nav( 18 === substr_count( $primary, 'class="seo-geo-preset-navigation__item' ), 'Expected nine links in each of desktop and mobile.' );
assert_nav( 2 === substr_count( $primary, 'class="seo-geo-preset-navigation__sublist"' ) * 0 + 4, 'Nested submenu markup missing.' );
assert_nav( str_contains( $primary, 'Servicios</a><ul class="seo-geo-preset-navigation__sublist"' ), 'Service child was flattened.' );
assert_nav( str_contains( $primary, 'Blog</a><ul class="seo-geo-preset-navigation__sublist"' ), 'Blog child was flattened.' );
assert_nav( str_contains( $primary, 'aria-current="page"' ), 'Current page was not marked.' );

$footer = $renderer->render( array( 'location' => 'footer' ) );
assert_nav( ! str_contains( $footer, 'Asistentes de voz' ), 'Shared footer assignment mirrored full primary.' );
assert_nav( str_contains( $footer, 'Privacidad' ) && str_contains( $footer, 'Contacto' ), 'Preset legal/contact links were not used.' );

$GLOBALS['nav_fixture_same_menu'] = false;
$dedicated_footer = $renderer->render( array( 'location' => 'footer' ) );
assert_nav( str_contains( $dedicated_footer, 'Política de privacidad' ), 'Distinct WordPress footer was overridden.' );
assert_nav( ! str_contains( $dedicated_footer, 'Servicios' ), 'Distinct footer unexpectedly used primary.' );

// Orphan and cyclic relationships must remain reachable instead of disappearing.
$GLOBALS['nav_fixture_items'][6][] = menu_fixture( 20, 'Huérfana', 'orfana/', 999 );
$GLOBALS['nav_fixture_items'][6][] = menu_fixture( 21, 'Ciclo A', 'ciclo-a/', 22 );
$GLOBALS['nav_fixture_items'][6][] = menu_fixture( 22, 'Ciclo B', 'ciclo-b/', 21 );
$degenerate = $renderer->render( array( 'location' => 'primary' ) );
assert_nav( str_contains( $degenerate, 'Huérfana' ) && str_contains( $degenerate, 'Ciclo A' ) && str_contains( $degenerate, 'Ciclo B' ), 'Invalid parent prevented reachable links.' );

echo "Nested classic navigation and independent footer: OK\n";