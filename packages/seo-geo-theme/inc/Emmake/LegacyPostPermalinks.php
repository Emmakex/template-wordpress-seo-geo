<?php
/**
 * Preserve the original Emmake public article URLs across the staged cutover.
 *
 * This is intentionally site-scoped: other clients using SEO GEO Theme retain
 * their own WordPress permalink configuration untouched.
 *
 * @package SeoGeoTheme
 */

declare(strict_types=1);

namespace SeoGeo\Theme\Emmake;

use WP_Post;

/** Site-scoped, reversible historical permalink compatibility. */
final class LegacyPostPermalinks {
	/**
	 * Explicit pre-migration category routes; all other published posts
	 * use /blog/%postname%/. IDs were verified unchanged on both installs.
	 *
	 * @var array<int,string>
	 */
	private const CATEGORY_PATHS = array(
		50212 => 'redes-sociales/instagram-presenta-igtv-la-proxima-generacion-de-videos',
		50234 => 'marketing-digital/cual-es-el-futuro-del-marketing-digital',
		50240 => 'consultoria/habitacion-gratis-para-estudiantes-en-barcelona',
		50390 => 'business-intelligence/errores-que-siguen-cometiendo-las-marcas-en-marketing-digital',
		50406 => 'analisis-de-datos/marketing-de-contenidos-en-la-transformacion-digital',
		50428 => 'investigacion-de-mercado/inteligencia-de-mercado-mas-alla-de-la-investigacion-de-mercado',
		50504 => 'uncategorized/guia-para-principiantes-de-marketing-digital',
		50505 => 'uncategorized/reviva-sus-negocios-con-el-marketing-digital',
		50515 => 'uncategorized/5-razones-por-las-que-los-innovadores-de-marketing-deberian-adoptar-eventos-virtuales-inmersivos-en-3d',
		50517 => 'uncategorized/inteligencia-de-negocios',
		53293 => 'uncategorized/desentranando-el-mundo-del-procesamiento-de-datos',
		53392 => 'uncategorized/descubre-el-poder-del-analisis-empresarial-avanzado',
		53545 => 'uncategorized/desata-tu-creatividad-contenidos-impactantes-para-redes-sociales',
		53607 => 'uncategorized/explora-el-mundo-omnicanal-conectando-experiencias',
		53619 => 'uncategorized/revolution-ai-transformacion-con-inteligencia-artificial',
		53632 => 'uncategorized/descubre-las-claves-del-analisis-empresarial-avanzado',
		53691 => 'uncategorized/desafia-lo-convencional-estrategias-de-marketing-digital',
		53748 => 'uncategorized/maestria-digital-estrategias-innovadoras-en-diseno',
		53800 => 'uncategorized/desplegando-estrategias-la-distribucion-de-contenidos',
		53816 => 'uncategorized/domina-el-arte-del-diseno-de-estrategias-digitales',
		53828 => 'uncategorized/desvelando-el-poder-de-la-ia-soluciones-innovadoras',
		53854 => 'uncategorized/descubre-el-poder-de-los-estudios-de-mercado',
		53863 => 'uncategorized/deslumbra-en-la-red-estrategias-de-promocion-digital',
		53868 => 'uncategorized/deslumbra-con-la-magia-de-la-visualizacion-de-datos',
	);

	/** WordPress permalink rewrite persistence marker. */
	private const REWRITE_OPTION = 'seo_geo_emmake_legacy_post_rewrite_v1';

	/**
	 * The one source slug that contained two URL-encoded invisible characters.
	 * The current WordPress slug is intentionally normalized; this exact old
	 * path must permanently redirect to its clean permalink, never 404.
	 */
	private const INVISIBLE_SLUG_OLD_PATH = '/blog/ventajas-y-desventajas-de-los-relojes-de-tiempo-basados-%e2%80%8b%e2%80%8ben-la-web/';
	private const INVISIBLE_SLUG_POST_ID  = 51663;

	/** Attach hooks only for the approved Emmake root or /nuevaweb install. */
	public function register(): void {
		if ( ! $this->matches_site() ) {
			return;
		}

		add_action( 'init', array( $this, 'add_routes' ), 20 );
		add_filter( 'post_link', array( $this, 'historical_post_link' ), 30, 3 );
		add_action( 'wp_loaded', array( $this, 'maybe_flush_rewrites' ), 99 );
		add_action( 'template_redirect', array( $this, 'redirect_invisible_slug' ), 1 );
	}

	/** Configure the article namespace and exactly 24 legacy category routes. */
	public function add_routes(): void {
		add_rewrite_rule( '^blog/([^/]+)/?$', 'index.php?post_type=post&name=$matches[1]', 'top' );

		foreach ( self::CATEGORY_PATHS as $post_id => $route ) {
			add_rewrite_rule( '^' . preg_quote( $route, '#' ) . '/?$', 'index.php?p=' . (int) $post_id, 'top' );
		}
	}

	/**
	 * Preserve historic public post permalinks, including metadata and sitemaps.
	 *
	 * @param string  $url       WordPress-generated link.
	 * @param WP_Post $post      Post object.
	 * @param bool    $leavename Preserve %postname% placeholder when requested.
	 */
	public function historical_post_link( string $url, WP_Post $post, bool $leavename ): string {
		if ( 'post' !== $post->post_type || 'publish' !== $post->post_status ) {
			return $url;
		}

		if ( isset( self::CATEGORY_PATHS[ $post->ID ] ) ) {
			return home_url( '/' . self::CATEGORY_PATHS[ $post->ID ] . '/' );
		}

		$slug = $leavename ? '%postname%' : $post->post_name;
		return '' !== $slug ? home_url( '/blog/' . $slug . '/' ) : $url;
	}

	/**
	 * Rewrite registration is a once-per-manifest, soft flush. Theme updates
	 * do not trigger after_switch_theme; never flush on every request.
	 */
	public function maybe_flush_rewrites(): void {
		$signature = hash( 'sha256', (string) wp_json_encode( array( 'blog/%postname%', self::CATEGORY_PATHS ) ) );
		if ( get_option( self::REWRITE_OPTION, '' ) === $signature ) {
			return;
		}

		flush_rewrite_rules( false );
		update_option( self::REWRITE_OPTION, $signature, false );
	}

	/** Exactly one permanently mapped Unicode-normalization redirect. */
	public function redirect_invisible_slug(): void {
		if ( ! isset( $_SERVER['REQUEST_URI'] ) || ! is_string( $_SERVER['REQUEST_URI'] ) ) {
			return;
		}
		$uri  = esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) );
		$path = wp_parse_url( $uri, PHP_URL_PATH );
		if ( ! is_string( $path ) ) {
			return;
		}

		$base = rtrim( (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH ), '/' );
		if ( '' !== $base ) {
			if ( ! str_starts_with( $path, $base . '/' ) ) {
				return;
			}
			$path = substr( $path, strlen( $base ) );
		}

		$requested = strtolower( rtrim( rawurldecode( $path ), '/' ) );
		$old       = strtolower( rtrim( rawurldecode( self::INVISIBLE_SLUG_OLD_PATH ), '/' ) );
		if ( $requested !== $old ) {
			return;
		}

		$post = get_post( self::INVISIBLE_SLUG_POST_ID );
		if ( ! $post instanceof WP_Post || 'publish' !== $post->post_status ) {
			return;
		}

		$target = get_permalink( $post );
		if ( '' !== $target ) {
			wp_safe_redirect( $target, 301, 'Emmake Legacy Permalink' );
			exit;
		}
	}

	/** The template is reusable; this site-specific map never affects clients. */
	private function matches_site(): bool {
		$host = strtolower( (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST ) );
		$base = rtrim( (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH ), '/' );
		return 'emmake.com' === $host && in_array( $base, array( '', '/nuevaweb' ), true );
	}
}
