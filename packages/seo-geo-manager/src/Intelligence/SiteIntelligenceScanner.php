<?php
/**
 * Bounded site-intelligence scanner for Build / Finish workflows.
 *
 * @package SeoGeoManager
 */

declare(strict_types=1);

namespace SeoGeo\Manager\Intelligence;

use WP_HTML_Tag_Processor;
use WP_Post;
use WP_Query;

final class SiteIntelligenceScanner {
	private const MAX_RESOURCES         = 250;
	private const MAX_LINK_EDGES        = 500;
	private const MAX_RENDERED_SURFACES = 20;

	/**
	 * Build a bounded site-intelligence report.
	 *
	 * @return array<string, mixed>
	 */
	public static function scan( bool $include_rendered = false ): array {
		$home_url  = home_url( '/' );
		$home_host = strtolower( (string) wp_parse_url( $home_url, PHP_URL_HOST ) );
		$home_path = self::normalize_path( (string) wp_parse_url( $home_url, PHP_URL_PATH ) );
		$resources = self::resources( $home_path );
		$maps      = self::resource_maps( $resources );
		$analysis  = self::scan_stored_links( $resources, $maps, $home_url, $home_host, $home_path );
		$menus     = self::scan_menu_links( $maps, $home_url, $home_host, $home_path );

		self::merge_link_analysis( $analysis, $menus );

		$rendered = array(
			'enabled'  => false,
			'scanned'  => 0,
			'failures' => array(),
		);

		if ( $include_rendered ) {
			$rendered = self::scan_rendered_surfaces( $resources, $maps, $home_url, $home_host, $home_path );
			self::merge_link_analysis( $analysis, $rendered['analysis'] );
			unset( $rendered['analysis'] );
		}

		$orphan_candidates = self::orphan_page_candidates( $resources, $analysis['inbound_counts'] );
		$readiness         = self::launch_readiness( $analysis, $orphan_candidates, $rendered );

		return array(
			'generated_at_gmt' => gmdate( 'c' ),
			'environment'      => array(
				'home_url'  => $home_url,
				'home_host' => $home_host,
				'home_path' => $home_path,
			),
			'limits'           => array(
				'max_resources'         => self::MAX_RESOURCES,
				'max_link_edges'        => self::MAX_LINK_EDGES,
				'max_rendered_surfaces' => self::MAX_RENDERED_SURFACES,
			),
			'resources'        => array(
				'count' => count( $resources ),
				'items' => $resources,
			),
			'links'            => array(
				'totals'                              => $analysis['totals'],
				'internal_edges'                      => array_slice( $analysis['internal_edges'], 0, self::MAX_LINK_EDGES ),
				'environment_leakage_candidates'      => $analysis['environment_leakage_candidates'],
				'unresolved_internal_path_candidates' => $analysis['unresolved_internal_path_candidates'],
				'orphan_page_candidates'              => $orphan_candidates,
			),
			'rendered_scan'     => $rendered,
			'launch_readiness'  => $readiness,
		);
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	private static function resources( string $home_path ): array {
		$query = new WP_Query(
			array(
				'post_type'              => array( 'page', 'post' ),
				'post_status'            => array( 'publish', 'future', 'draft', 'pending', 'private' ),
				'posts_per_page'         => self::MAX_RESOURCES,
				'orderby'                => array(
					'post_type' => 'ASC',
					'ID'        => 'ASC',
				),
				'order'                  => 'ASC',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);

		$resources = array();
		foreach ( $query->posts as $post ) {
			if ( ! $post instanceof WP_Post ) {
				continue;
			}

			$permalink = get_permalink( $post );
			$permalink = is_string( $permalink ) ? $permalink : '';
			$path      = self::normalize_path( (string) wp_parse_url( $permalink, PHP_URL_PATH ) );
			$text      = self::plain_text( (string) $post->post_content );

			$resources[] = array(
				'id'           => (int) $post->ID,
				'type'         => (string) $post->post_type,
				'status'       => (string) $post->post_status,
				'slug'         => (string) $post->post_name,
				'title'        => html_entity_decode( get_the_title( $post ), ENT_QUOTES | ENT_HTML5, get_bloginfo( 'charset' ) ),
				'permalink'    => $permalink,
				'path'         => $path,
				'logical_path' => self::logical_path( $path, $home_path ),
				'word_count'   => self::word_count( $text ),
				'modified_gmt' => (string) $post->post_modified_gmt,
				'fingerprint'  => self::fingerprint( $post ),
			);
		}

		return $resources;
	}

	/**
	 * @param array<int, array<string, mixed>> $resources Resources.
	 * @return array<string, array<string, int>>
	 */
	private static function resource_maps( array $resources ): array {
		$paths         = array();
		$logical_paths = array();

		foreach ( $resources as $resource ) {
			if ( 'publish' !== $resource['status'] || '' === $resource['permalink'] ) {
				continue;
			}

			$paths[ (string) $resource['path'] ]                 = (int) $resource['id'];
			$logical_paths[ (string) $resource['logical_path'] ] = (int) $resource['id'];
		}

		return array(
			'paths'         => $paths,
			'logical_paths' => $logical_paths,
		);
	}

	/**
	 * @param array<int, array<string, mixed>>  $resources Resources.
	 * @param array<string, array<string, int>> $maps Resource maps.
	 * @return array<string, mixed>
	 */
	private static function scan_stored_links( array $resources, array $maps, string $home_url, string $home_host, string $home_path ): array {
		$analysis = self::empty_link_analysis();

		foreach ( $resources as $resource ) {
			if ( 'publish' !== $resource['status'] ) {
				continue;
			}

			$post = get_post( (int) $resource['id'] );
			if ( ! $post instanceof WP_Post ) {
				continue;
			}

			self::scan_html(
				(string) $post->post_content,
				array(
					'kind'      => 'content',
					'id'        => (int) $resource['id'],
					'permalink' => (string) $resource['permalink'],
				),
				$maps,
				$home_url,
				$home_host,
				$home_path,
				$analysis
			);
		}

		return $analysis;
	}

	/**
	 * @param array<string, array<string, int>> $maps Resource maps.
	 * @return array<string, mixed>
	 */
	private static function scan_menu_links( array $maps, string $home_url, string $home_host, string $home_path ): array {
		$analysis = self::empty_link_analysis();

		foreach ( wp_get_nav_menus() as $menu ) {
			$items = wp_get_nav_menu_items( $menu->term_id );
			if ( ! is_array( $items ) ) {
				continue;
			}

			foreach ( $items as $item ) {
				if ( ! is_object( $item ) || ! isset( $item->url ) || ! is_string( $item->url ) ) {
					continue;
				}

				self::classify_link(
					$item->url,
					array(
						'kind'      => 'menu',
						'id'        => (int) $menu->term_id,
						'permalink' => $home_url,
					),
					$maps,
					$home_url,
					$home_host,
					$home_path,
					$analysis
				);
			}
		}

		return $analysis;
	}

	/**
	 * @param array<int, array<string, mixed>>  $resources Resources.
	 * @param array<string, array<string, int>> $maps Resource maps.
	 * @return array<string, mixed>
	 */
	private static function scan_rendered_surfaces( array $resources, array $maps, string $home_url, string $home_host, string $home_path ): array {
		$analysis = self::empty_link_analysis();
		$failures = array();
		$scanned  = 0;

		foreach ( $resources as $resource ) {
			if ( $scanned >= self::MAX_RENDERED_SURFACES ) {
				break;
			}

			if ( 'publish' !== $resource['status'] || 'page' !== $resource['type'] || '' === $resource['permalink'] ) {
				continue;
			}

			$response = wp_safe_remote_get(
				(string) $resource['permalink'],
				array(
					'timeout'     => 5,
					'redirection' => 2,
					'headers'     => array(
						'User-Agent' => 'SEO-GEO-Manager/' . ( defined( 'SEO_GEO_MANAGER_VERSION' ) ? SEO_GEO_MANAGER_VERSION : 'dev' ),
					),
				)
			);

			if ( is_wp_error( $response ) ) {
				$failures[] = array(
					'id'      => (int) $resource['id'],
					'url'     => (string) $resource['permalink'],
					'message' => $response->get_error_message(),
				);
				continue;
			}

			$code = (int) wp_remote_retrieve_response_code( $response );
			if ( $code < 200 || $code >= 400 ) {
				$failures[] = array(
					'id'      => (int) $resource['id'],
					'url'     => (string) $resource['permalink'],
					'message' => 'HTTP ' . $code,
				);
				continue;
			}

			self::scan_html(
				(string) wp_remote_retrieve_body( $response ),
				array(
					'kind'      => 'rendered',
					'id'        => (int) $resource['id'],
					'permalink' => (string) $resource['permalink'],
				),
				$maps,
				$home_url,
				$home_host,
				$home_path,
				$analysis
			);
			++$scanned;
		}

		return array(
			'enabled'  => true,
			'scanned'  => $scanned,
			'failures' => $failures,
			'analysis' => $analysis,
		);
	}

	/**
	 * @param array<string, mixed>              $source Source descriptor.
	 * @param array<string, array<string, int>> $maps Resource maps.
	 * @param array<string, mixed>              $analysis Link analysis accumulator.
	 */
	private static function scan_html( string $html, array $source, array $maps, string $home_url, string $home_host, string $home_path, array &$analysis ): void {
		if ( '' === trim( $html ) ) {
			return;
		}

		$processor = new WP_HTML_Tag_Processor( $html );
		while ( $processor->next_tag( 'a' ) ) {
			$href = $processor->get_attribute( 'href' );
			if ( ! is_string( $href ) ) {
				continue;
			}

			self::classify_link( $href, $source, $maps, $home_url, $home_host, $home_path, $analysis );
		}
	}

	/**
	 * @param array<string, mixed>              $source Source descriptor.
	 * @param array<string, array<string, int>> $maps Resource maps.
	 * @param array<string, mixed>              $analysis Link analysis accumulator.
	 */
	private static function classify_link( string $href, array $source, array $maps, string $home_url, string $home_host, string $home_path, array &$analysis ): void {
		$href = trim( html_entity_decode( $href, ENT_QUOTES | ENT_HTML5, get_bloginfo( 'charset' ) ) );
		if ( self::skip_href( $href ) ) {
			return;
		}

		$base     = isset( $source['permalink'] ) && is_string( $source['permalink'] ) ? $source['permalink'] : $home_url;
		$absolute = \WP_Http::make_absolute_url( $href, $base );
		if ( '' === $absolute ) {
			return;
		}

		$host = strtolower( (string) wp_parse_url( $absolute, PHP_URL_HOST ) );
		$path = self::normalize_path( (string) wp_parse_url( $absolute, PHP_URL_PATH ) );
		++$analysis['totals']['all'];

		$exact_target = $maps['paths'][ $path ] ?? 0;
		if ( 0 !== $exact_target && ( '' === $host || $host === $home_host ) ) {
			++$analysis['totals']['internal_resolved'];
			self::record_internal_edge( $analysis, $source, (int) $exact_target, $absolute );
			return;
		}

		$logical_target = $maps['logical_paths'][ $path ] ?? 0;
		$is_same_host   = '' === $host || $host === $home_host;
		$is_within_home = self::is_within_home_path( $path, $home_path );

		if ( 0 !== $logical_target && ( ! $is_within_home || ! $is_same_host ) ) {
			++$analysis['totals']['environment_leakage_candidates'];
			$analysis['environment_leakage_candidates'][] = array(
				'source'            => $source,
				'href'              => $href,
				'absolute_url'      => $absolute,
				'target_resource_id'=> (int) $logical_target,
				'confidence'        => $is_same_host ? 'high' : 'medium',
				'reason'            => $is_same_host ? 'same_host_outside_current_home_path' : 'external_host_matches_local_resource_path',
			);
			return;
		}

		if ( $is_same_host && $is_within_home && self::is_content_candidate_path( $path, $home_path ) ) {
			++$analysis['totals']['unresolved_internal_path_candidates'];
			$analysis['unresolved_internal_path_candidates'][] = array(
				'source'       => $source,
				'href'         => $href,
				'absolute_url' => $absolute,
				'path'         => $path,
			);
			return;
		}

		++$analysis['totals']['external_or_system'];
	}

	/**
	 * @param array<string, mixed> $analysis Link analysis accumulator.
	 * @param array<string, mixed> $source Source descriptor.
	 */
	private static function record_internal_edge( array &$analysis, array $source, int $target_id, string $url ): void {
		$analysis['inbound_counts'][ $target_id ] = ( $analysis['inbound_counts'][ $target_id ] ?? 0 ) + 1;

		if ( count( $analysis['internal_edges'] ) >= self::MAX_LINK_EDGES ) {
			return;
		}

		$analysis['internal_edges'][] = array(
			'source'             => $source,
			'target_resource_id' => $target_id,
			'url'                => $url,
		);
	}

	/**
	 * @param array<string, mixed> $target Target accumulator.
	 * @param array<string, mixed> $source Source accumulator.
	 */
	private static function merge_link_analysis( array &$target, array $source ): void {
		foreach ( $target['totals'] as $key => $value ) {
			$target['totals'][ $key ] = (int) $value + (int) ( $source['totals'][ $key ] ?? 0 );
		}

		foreach ( $source['inbound_counts'] as $resource_id => $count ) {
			$target['inbound_counts'][ (int) $resource_id ] = ( $target['inbound_counts'][ (int) $resource_id ] ?? 0 ) + (int) $count;
		}

		$target['internal_edges'] = array_slice(
			array_merge( $target['internal_edges'], $source['internal_edges'] ),
			0,
			self::MAX_LINK_EDGES
		);
		$target['environment_leakage_candidates'] = array_merge( $target['environment_leakage_candidates'], $source['environment_leakage_candidates'] );
		$target['unresolved_internal_path_candidates'] = array_merge( $target['unresolved_internal_path_candidates'], $source['unresolved_internal_path_candidates'] );
	}

	/**
	 * @param array<int, array<string, mixed>> $resources Resources.
	 * @param array<int, int>                  $inbound_counts Inbound counts.
	 * @return array<int, array<string, mixed>>
	 */
	private static function orphan_page_candidates( array $resources, array $inbound_counts ): array {
		$front_page_id = (int) get_option( 'page_on_front', 0 );
		$posts_page_id = (int) get_option( 'page_for_posts', 0 );
		$candidates    = array();

		foreach ( $resources as $resource ) {
			$id = (int) $resource['id'];
			if ( 'page' !== $resource['type'] || 'publish' !== $resource['status'] || $id === $front_page_id || $id === $posts_page_id ) {
				continue;
			}

			if ( 0 !== ( $inbound_counts[ $id ] ?? 0 ) ) {
				continue;
			}

			$candidates[] = array(
				'id'        => $id,
				'slug'      => (string) $resource['slug'],
				'title'     => (string) $resource['title'],
				'permalink' => (string) $resource['permalink'],
			);
		}

		return $candidates;
	}

	/**
	 * @param array<string, mixed>              $analysis Link analysis.
	 * @param array<int, array<string, mixed>>  $orphan_candidates Orphan candidates.
	 * @param array<string, mixed>              $rendered Rendered scan metadata.
	 * @return array<string, mixed>
	 */
	private static function launch_readiness( array $analysis, array $orphan_candidates, array $rendered ): array {
		$checks   = array();
		$blockers = 0;
		$warnings = 0;

		$high_confidence_leaks = array_values(
			array_filter(
				$analysis['environment_leakage_candidates'],
				static fn ( array $item ): bool => 'high' === ( $item['confidence'] ?? '' )
			)
		);

		$checks[] = self::readiness_check(
			'environment-links',
			empty( $high_confidence_leaks ) ? 'pass' : 'blocker',
			empty( $high_confidence_leaks ) ? 'No high-confidence clone/current-environment link leakage detected in scanned sources.' : 'Links matching local resources were found outside the current WordPress home path.',
			array( 'count' => count( $high_confidence_leaks ) )
		);
		$blockers += empty( $high_confidence_leaks ) ? 0 : 1;

		$checks[] = self::readiness_check(
			'internal-resolution',
			empty( $analysis['unresolved_internal_path_candidates'] ) ? 'pass' : 'warning',
			empty( $analysis['unresolved_internal_path_candidates'] ) ? 'No unresolved content-path candidates detected in scanned sources.' : 'Some same-environment links did not resolve to an inventoried page/post path and require review.',
			array( 'count' => count( $analysis['unresolved_internal_path_candidates'] ) )
		);
		$warnings += empty( $analysis['unresolved_internal_path_candidates'] ) ? 0 : 1;

		$checks[] = self::readiness_check(
			'orphan-pages',
			empty( $orphan_candidates ) ? 'pass' : 'warning',
			empty( $orphan_candidates ) ? 'No published page orphan candidates detected from stored/menu/rendered link evidence.' : 'Published pages without detected inbound links require review.',
			array( 'count' => count( $orphan_candidates ) )
		);
		$warnings += empty( $orphan_candidates ) ? 0 : 1;

		$front_page_ready = 'page' !== get_option( 'show_on_front', 'posts' ) || (int) get_option( 'page_on_front', 0 ) > 0;
		$checks[]         = self::readiness_check(
			'front-page',
			$front_page_ready ? 'pass' : 'warning',
			$front_page_ready ? 'Front-page configuration is internally consistent.' : 'WordPress is configured for a static front page but no front-page resource is selected.'
		);
		$warnings += $front_page_ready ? 0 : 1;

		$sitemap_ready = false;
		if ( function_exists( 'wp_sitemaps_get_server' ) ) {
			$server = wp_sitemaps_get_server();
			$sitemap_ready = is_object( $server ) && method_exists( $server, 'sitemaps_enabled' ) && $server->sitemaps_enabled();
		}
		$checks[] = self::readiness_check(
			'sitemap',
			$sitemap_ready ? 'pass' : 'warning',
			$sitemap_ready ? 'WordPress sitemap discovery is enabled.' : 'No enabled WordPress sitemap was detected; provider-owned sitemap output may still need separate authority verification.'
		);
		$warnings += $sitemap_ready ? 0 : 1;

		if ( ! $rendered['enabled'] ) {
			$checks[] = self::readiness_check(
				'rendered-verification',
				'info',
				'Rendered-page scanning was not requested. Use include_rendered=1 before final Build / Finish acceptance.'
			);
		} elseif ( ! empty( $rendered['failures'] ) ) {
			$checks[] = self::readiness_check(
				'rendered-verification',
				'warning',
				'One or more rendered surfaces could not be fetched and require review.',
				array( 'failures' => count( $rendered['failures'] ) )
			);
			++$warnings;
		} else {
			$checks[] = self::readiness_check(
				'rendered-verification',
				'pass',
				'Rendered-page scan completed without fetch failures.',
				array( 'scanned' => (int) $rendered['scanned'] )
			);
		}

		$status = 0 < $blockers ? 'blocked' : ( 0 < $warnings ? 'review' : 'ready-signal' );

		return array(
			'status'        => $status,
			'blocker_count' => $blockers,
			'warning_count' => $warnings,
			'checks'        => $checks,
			'note'          => 'Readiness is bounded diagnostic evidence, not a ranking or business-outcome guarantee.',
		);
	}

	/**
	 * @param array<string, mixed> $evidence Evidence.
	 * @return array<string, mixed>
	 */
	private static function readiness_check( string $key, string $status, string $message, array $evidence = array() ): array {
		return array(
			'key'      => $key,
			'status'   => $status,
			'message'  => $message,
			'evidence' => $evidence,
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function empty_link_analysis(): array {
		return array(
			'totals' => array(
				'all'                                 => 0,
				'internal_resolved'                   => 0,
				'environment_leakage_candidates'      => 0,
				'unresolved_internal_path_candidates' => 0,
				'external_or_system'                  => 0,
			),
			'inbound_counts'                      => array(),
			'internal_edges'                      => array(),
			'environment_leakage_candidates'      => array(),
			'unresolved_internal_path_candidates' => array(),
		);
	}

	private static function skip_href( string $href ): bool {
		if ( '' === $href || str_starts_with( $href, '#' ) ) {
			return true;
		}

		$lower = strtolower( $href );
		foreach ( array( 'mailto:', 'tel:', 'javascript:', 'data:' ) as $scheme ) {
			if ( str_starts_with( $lower, $scheme ) ) {
				return true;
			}
		}

		return false;
	}

	private static function is_within_home_path( string $path, string $home_path ): bool {
		if ( '/' === $home_path ) {
			return true;
		}

		$base = rtrim( $home_path, '/' );

		return $path === $base . '/' || str_starts_with( $path, $base . '/' );
	}

	private static function is_content_candidate_path( string $path, string $home_path ): bool {
		$logical = self::logical_path( $path, $home_path );
		foreach ( array( '/wp-admin/', '/wp-content/', '/wp-includes/', '/wp-json/', '/feed/', '/category/', '/tag/', '/author/' ) as $system_path ) {
			if ( str_starts_with( $logical, $system_path ) ) {
				return false;
			}
		}

		return true;
	}

	private static function logical_path( string $path, string $home_path ): string {
		if ( '/' === $home_path ) {
			return $path;
		}

		$base = rtrim( $home_path, '/' );
		if ( $path === $base . '/' ) {
			return '/';
		}

		if ( str_starts_with( $path, $base . '/' ) ) {
			return self::normalize_path( substr( $path, strlen( $base ) ) );
		}

		return $path;
	}

	private static function normalize_path( string $path ): string {
		$path = '/' . trim( $path, '/' );

		return '/' === $path ? '/' : $path . '/';
	}

	private static function plain_text( string $content ): string {
		$text = wp_strip_all_tags( strip_shortcodes( $content ) );
		$text = preg_replace( '/\s+/u', ' ', $text );

		return is_string( $text ) ? trim( $text ) : '';
	}

	private static function word_count( string $text ): int {
		if ( '' === $text ) {
			return 0;
		}

		$words = preg_split( '/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY );

		return is_array( $words ) ? count( $words ) : 0;
	}

	private static function fingerprint( WP_Post $post ): string {
		return hash(
			'sha256',
			implode(
				'|',
				array(
					(string) $post->ID,
					(string) $post->post_modified_gmt,
					(string) $post->post_title,
					(string) $post->post_name,
					(string) $post->post_status,
					(string) $post->post_content,
				)
			)
		);
	}
}
