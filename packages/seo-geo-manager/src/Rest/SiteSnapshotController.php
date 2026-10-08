<?php
/**
 * Bounded site-intelligence snapshot endpoint.
 *
 * @package SeoGeoManager
 */

declare(strict_types=1);

namespace SeoGeo\Manager\Rest;

use SeoGeo\Manager\Support\EnvironmentPolicy;
use WP_REST_Response;

final class SiteSnapshotController {
	private const NAMESPACE = 'seo-geo-manager/v1';

	public static function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/site/snapshot',
			array(
				'methods'             => 'GET',
				'callback'            => array( self::class, 'show' ),
				'permission_callback' => array( self::class, 'can_read' ),
			)
		);
	}

	public static function can_read(): bool {
		return current_user_can( 'edit_posts' );
	}

	public static function show(): WP_REST_Response {
		$theme          = wp_get_theme();
		$active_plugins = get_option( 'active_plugins', array() );
		$active_plugins = is_array( $active_plugins ) ? array_values( array_filter( $active_plugins, 'is_string' ) ) : array();
		$environment    = EnvironmentPolicy::snapshot();

		$data = array(
			'generated_at_gmt' => gmdate( 'c' ),
			'environment'      => array(
				'home_url'                => home_url( '/' ),
				'site_url'                => site_url( '/' ),
				'wordpress_version'       => get_bloginfo( 'version' ),
				'language'                => get_bloginfo( 'language' ),
				'permalink_structure'     => (string) get_option( 'permalink_structure', '' ),
				'is_ssl'                  => is_ssl(),
				'type'                    => $environment['type'],
				'fingerprint'             => $environment['fingerprint'],
				'write_approval_required' => $environment['write_approval_required'],
			),
			'products'         => array(
				'manager'          => array(
					'active'  => true,
					'version' => defined( 'SEO_GEO_MANAGER_VERSION' ) ? (string) SEO_GEO_MANAGER_VERSION : '',
				),
				'migration_bridge' => array(
					'active'  => defined( 'SEO_GEO_MIGRATION_BRIDGE_VERSION' ),
					'version' => defined( 'SEO_GEO_MIGRATION_BRIDGE_VERSION' ) ? (string) SEO_GEO_MIGRATION_BRIDGE_VERSION : '',
				),
				'core'             => array(
					'active'  => defined( 'SEO_GEO_CORE_VERSION' ),
					'version' => defined( 'SEO_GEO_CORE_VERSION' ) ? (string) SEO_GEO_CORE_VERSION : '',
				),
			),
			'theme'            => array(
				'name'             => (string) $theme->get( 'Name' ),
				'version'          => (string) $theme->get( 'Version' ),
				'stylesheet'       => (string) $theme->get_stylesheet(),
				'template'         => (string) $theme->get_template(),
				'text_domain'      => (string) $theme->get( 'TextDomain' ),
				'is_seo_geo_theme' => self::is_seo_geo_theme( $theme->get_stylesheet(), $theme->get_template(), $theme->get( 'TextDomain' ) ),
			),
			'content'          => array(
				'pages'       => self::post_counts( 'page' ),
				'posts'       => self::post_counts( 'post' ),
				'attachments' => self::post_counts( 'attachment' ),
			),
			'front_page'       => array(
				'show_on_front' => (string) get_option( 'show_on_front', 'posts' ),
				'page_on_front' => (int) get_option( 'page_on_front', 0 ),
				'page_for_posts' => (int) get_option( 'page_for_posts', 0 ),
			),
			'navigation'       => array(
				'menu_count' => count( wp_get_nav_menus() ),
			),
			'taxonomies'       => array(
				'categories' => self::term_count( 'category' ),
				'tags'       => self::term_count( 'post_tag' ),
			),
			'seo'              => array(
				'providers'       => self::detect_seo_providers( $active_plugins ),
				'authority_state' => self::seo_authority_state( $active_plugins ),
			),
			'discovery'        => array(
				'robots_url'  => home_url( '/robots.txt' ),
				'sitemap_url' => self::sitemap_url(),
			),
		);

		return new WP_REST_Response( $data, 200 );
	}

	/**
	 * @return array<string, int>
	 */
	private static function post_counts( string $post_type ): array {
		$counts = wp_count_posts( $post_type );

		$result = array();
		foreach ( array( 'publish', 'future', 'draft', 'pending', 'private', 'inherit' ) as $status ) {
			if ( isset( $counts->{$status} ) ) {
				$result[ $status ] = (int) $counts->{$status};
			}
		}

		return $result;
	}

	private static function term_count( string $taxonomy ): int {
		$count = wp_count_terms(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
			)
		);

		return is_int( $count ) ? $count : 0;
	}

	private static function is_seo_geo_theme( string $stylesheet, string $template, string $text_domain ): bool {
		$haystack = strtolower( implode( '|', array( $stylesheet, $template, $text_domain ) ) );

		return false !== strpos( $haystack, 'seo-geo' );
	}

	/**
	 * @param list<string> $active_plugins Active plugin files.
	 * @return list<string>
	 */
	private static function detect_seo_providers( array $active_plugins ): array {
		$providers = array();
		$map       = array(
			'wordpress-seo/wp-seo.php'                         => 'yoast',
			'seo-by-rank-math/rank-math.php'                   => 'rank-math',
			'rank-math/rank-math.php'                          => 'rank-math',
			'all-in-one-seo-pack/all_in_one_seo_pack.php'      => 'aioseo',
			'all-in-one-seo-pack-pro/all_in_one_seo_pack.php'  => 'aioseo-pro',
		);

		foreach ( $map as $plugin_file => $provider ) {
			if ( in_array( $plugin_file, $active_plugins, true ) ) {
				$providers[] = $provider;
			}
		}

		return array_values( array_unique( $providers ) );
	}

	/**
	 * @param list<string> $active_plugins Active plugin files.
	 */
	private static function seo_authority_state( array $active_plugins ): string {
		$count = count( self::detect_seo_providers( $active_plugins ) );
		if ( 0 === $count ) {
			return 'unresolved-native-or-none';
		}

		return 1 === $count ? 'single-provider-detected' : 'multiple-providers-detected';
	}

	private static function sitemap_url(): string {
		if ( function_exists( 'wp_sitemaps_get_server' ) ) {
			$server = wp_sitemaps_get_server();
			if ( $server->sitemaps_enabled() ) {
				return home_url( '/wp-sitemap.xml' );
			}
		}

		return '';
	}
}
