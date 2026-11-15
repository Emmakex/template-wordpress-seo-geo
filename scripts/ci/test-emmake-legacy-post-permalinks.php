<?php
/**
 * Deterministic legacy URL smoke: no live WordPress connection or mutations.
 * Verifies 24 category exceptions, default blog URLs, site scope and one-shot
 * rewrite flush before a production Site Manager package deployment.
 */
declare(strict_types=1);

error_reporting( E_ALL );
set_error_handler( static function ( int $severity, string $message ): never {
	throw new RuntimeException( $message, $severity );
} );

class WP_Post {
	public int $ID;
	public string $post_type = 'post';
	public string $post_status = 'publish';
	public string $post_name;

	public function __construct( int $id, string $slug ) {
		$this->ID = $id;
		$this->post_name = $slug;
	}
}

$GLOBALS['fixture_home'] = 'https://emmake.com/nuevaweb/';
$GLOBALS['fixture_rules'] = array();
$GLOBALS['fixture_filters'] = array();
$GLOBALS['fixture_actions'] = array();
$GLOBALS['fixture_options'] = array();
$GLOBALS['fixture_flushes'] = 0;

function home_url( string $path = '/' ): string {
	return rtrim( $GLOBALS['fixture_home'], '/' ) . '/' . ltrim( $path, '/' );
}
function wp_parse_url( string $url, int $component ): mixed {
	return parse_url( $url, $component );
}
function add_filter( string $name, callable $callback, int $priority = 10, int $arg_count = 1 ): void {
	$GLOBALS['fixture_filters'][] = $name;
}
function add_action( string $name, callable $callback, int $priority = 10, int $arg_count = 1 ): void {
	$GLOBALS['fixture_actions'][] = $name;
}
function add_rewrite_rule( string $regex, string $query, string $after ): void {
	$GLOBALS['fixture_rules'][] = array( $regex, $query, $after );
}
function wp_json_encode( mixed $value ): string {
	return json_encode( $value, JSON_THROW_ON_ERROR );
}
function get_option( string $name, string $default = '' ): string {
	return $GLOBALS['fixture_options'][ $name ] ?? $default;
}
function update_option( string $name, string $value, bool $autoload = false ): bool {
	$GLOBALS['fixture_options'][ $name ] = $value;
	return true;
}
function flush_rewrite_rules( bool $hard = true ): void {
	if ( $hard ) {
		throw new RuntimeException( 'Hard rewrite flush forbidden' );
	}
	++$GLOBALS['fixture_flushes'];
}

require dirname( __DIR__, 2 ) . '/packages/seo-geo-theme/inc/Emmake/LegacyPostPermalinks.php';
$runtime = new \SeoGeo\Theme\Emmake\LegacyPostPermalinks();
$runtime->register();

if ( 1 !== count( $GLOBALS['fixture_filters'] ) || 3 !== count( $GLOBALS['fixture_actions'] ) ) {
	throw new RuntimeException( 'Emmake scope did not register exactly expected hooks' );
}

$runtime->add_routes();
if ( 25 !== count( $GLOBALS['fixture_rules'] ) ) {
	throw new RuntimeException( 'Expected 1 blog route and 24 exact-category routes' );
}
$unique = array_unique( array_column( $GLOBALS['fixture_rules'], 0 ) );
if ( 25 !== count( $unique ) ) {
	throw new RuntimeException( 'Duplicate category rewrite rules' );
}

$cases = array(
	array( 12345, 'ejemplo-publicado', '/blog/ejemplo-publicado/' ),
	array( 50212, 'instagram-presenta-igtv-la-proxima-generacion-de-videos', '/redes-sociales/instagram-presenta-igtv-la-proxima-generacion-de-videos/' ),
	array( 50234, 'cual-es-el-futuro-del-marketing-digital', '/marketing-digital/cual-es-el-futuro-del-marketing-digital/' ),
	array( 50504, 'guia-para-principiantes-de-marketing-digital', '/uncategorized/guia-para-principiantes-de-marketing-digital/' ),
	array( 51663, 'ventajas-y-desventajas-de-los-relojes-de-tiempo-basados-en-la-web', '/blog/ventajas-y-desventajas-de-los-relojes-de-tiempo-basados-en-la-web/' ),
	array( 52690, 'estrategias-de-marketing%e2%81%a2-digital-impulsando-el-exito-en-la-era-digital', '/blog/estrategias-de-marketing%e2%81%a2-digital-impulsando-el-exito-en-la-era-digital/' ),
);
foreach ( $cases as array( $id, $slug, $expected_path ) ) {
	$link = $runtime->historical_post_link( 'https://emmake.com/nuevaweb/' . $slug . '/', new WP_Post( $id, $slug ), false );
	if ( 'https://emmake.com/nuevaweb' . $expected_path !== $link ) {
		throw new RuntimeException( 'Post permalink mismatch for ' . $id . ': ' . $link );
	}
}

$draft = new WP_Post( 12345, 'my-draft' );
$draft->post_status = 'draft';
if ( 'original' !== $runtime->historical_post_link( 'original', $draft, false ) ) {
	throw new RuntimeException( 'Draft permalink should be unchanged' );
}

$runtime->maybe_flush_rewrites();
$runtime->maybe_flush_rewrites();
if ( 1 !== $GLOBALS['fixture_flushes'] ) {
	throw new RuntimeException( 'One-shot rewrite flush not idempotent' );
}
$GLOBALS['fixture_home'] = 'https://emmake.com/';
$link = $runtime->historical_post_link( '', new WP_Post( 12345, 'ejemplo-publicado' ), false );
if ( 'https://emmake.com/blog/ejemplo-publicado/' !== $link ) {
	throw new RuntimeException( 'Root-site portability broken' );
}

$GLOBALS['fixture_home'] = 'https://other-client.example/';
$GLOBALS['fixture_filters'] = array();
$GLOBALS['fixture_actions'] = array();
( new \SeoGeo\Theme\Emmake\LegacyPostPermalinks() )->register();
if ( $GLOBALS['fixture_filters'] || $GLOBALS['fixture_actions'] ) {
	throw new RuntimeException( 'Emmake legacy URLs leaked to another client' );
}

echo "PASS: legacy 1702 blog + 24 category routes, special Unicode slug, one-shot flush and strict site scope\n";
