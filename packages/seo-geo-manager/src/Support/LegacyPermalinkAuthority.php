<?php
/**
 * Read-only recovery of historical public post URLs from a legacy WordPress REST source.
 *
 * @package SeoGeoManager
 */

declare(strict_types=1);

namespace SeoGeo\Manager\Support;

final class LegacyPermalinkAuthority {
	private const MAX_POSTS = 2000;
	private const PER_PAGE  = 100;
	private const TIMEOUT   = 8;

	/**
	 * Recover authoritative historical links for the current site's published posts.
	 *
	 * This first authority provider is deliberately same-host only. It never writes
	 * local or remote state and refuses to treat the current clone path as a legacy
	 * source. A future provider can support uploaded manifests or explicitly approved
	 * cross-host migrations without weakening this baseline.
	 *
	 * @return array<string, mixed>
	 */
	public static function preview( string $legacy_base_url ): array {
		$base = self::normalize_base_url( $legacy_base_url );
		if ( '' === $base ) {
			return self::blocked( '', 'La URL base histórica no es una URL HTTP/HTTPS válida.' );
		}

		$home = self::normalize_base_url( home_url( '/' ) );
		if ( '' === $home || self::host_key( $base ) !== self::host_key( $home ) ) {
			return self::blocked( $base, 'Esta primera versión solo acepta una fuente histórica del mismo host que el WordPress actual.' );
		}
		if ( self::canonical_base_key( $base ) === self::canonical_base_key( $home ) ) {
			return self::blocked( $base, 'La fuente histórica no puede ser la misma ruta base que el clon actual.' );
		}

		$local = self::local_posts();
		if ( ! $local['complete'] ) {
			return self::blocked( $base, 'El inventario local supera el límite seguro de esta fase; no se puede declarar autoridad histórica completa.' );
		}

		$remote = self::remote_posts( $base );
		if ( false === $remote['ok'] ) {
			return self::blocked( $base, (string) $remote['message'], $local, $remote );
		}

		$remote_by_slug         = array();
		$duplicate_remote_slugs = array();
		foreach ( $remote['posts'] as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$slug = isset( $row['slug'] ) && is_string( $row['slug'] ) ? sanitize_title( $row['slug'] ) : '';
			$link = isset( $row['link'] ) && is_string( $row['link'] ) ? esc_url_raw( $row['link'] ) : '';
			if ( '' === $slug || '' === $link ) {
				continue;
			}
			if ( isset( $remote_by_slug[ $slug ] ) && $remote_by_slug[ $slug ] !== $link ) {
				$duplicate_remote_slugs[ $slug ] = true;
				continue;
			}
			$remote_by_slug[ $slug ] = $link;
		}

		$rows              = array();
		$missing           = array();
		$invalid_links     = array();
		$structure_counts  = array();
		$local_slug_counts = array_count_values( array_column( $local['posts'], 'slug' ) );
		$duplicate_local   = array_keys( array_filter( $local_slug_counts, static fn ( int $count ): bool => 1 < $count ) );

		foreach ( $local['posts'] as $post ) {
			$post_id = (int) $post['post_id'];
			$slug    = (string) $post['slug'];
			if ( '' === $slug || ! isset( $remote_by_slug[ $slug ] ) ) {
				$missing[] = array( 'post_id' => $post_id, 'slug' => $slug );
				continue;
			}

			$legacy_url = (string) $remote_by_slug[ $slug ];
			if ( self::host_key( $legacy_url ) !== self::host_key( $base ) || ! self::url_inside_base( $legacy_url, $base ) ) {
				$invalid_links[] = array( 'post_id' => $post_id, 'slug' => $slug, 'legacy_url' => $legacy_url );
				continue;
			}

			$candidate = self::candidate_structure( $legacy_url, $base, $slug );
			if ( '' !== $candidate ) {
				$structure_counts[ $candidate ] = ( $structure_counts[ $candidate ] ?? 0 ) + 1;
			}

			$rows[] = array(
				'post_id'             => $post_id,
				'slug'                => $slug,
				'title'               => (string) $post['title'],
				'legacy_url'          => $legacy_url,
				'legacy_path'         => self::url_path( $legacy_url ),
				'candidate_structure' => $candidate,
			);
		}

		$complete_scan          = true === $remote['complete'] && true === $local['complete'];
		$duplicate_remote       = array_keys( $duplicate_remote_slugs );
		$all_mapped             = count( $rows ) === count( $local['posts'] ) && array() === $missing && array() === $invalid_links;
		$mapping_authoritative  = $complete_scan && $all_mapped && array() === $duplicate_local && array() === $duplicate_remote;
		$inferred_structure     = 1 === count( $structure_counts ) ? (string) array_key_first( $structure_counts ) : '';
		$structure_consistent   = '' !== $inferred_structure && count( $rows ) === (int) ( $structure_counts[ $inferred_structure ] ?? 0 );
		$seo_authority_verified = $mapping_authoritative && $structure_consistent;

		$fingerprint_payload = array(
			'legacy_base_url'    => $base,
			'local_posts'        => $local['posts'],
			'rows'               => $rows,
			'inferred_structure' => $inferred_structure,
		);
		$authority_fingerprint = hash( 'sha256', (string) wp_json_encode( $fingerprint_payload ) );

		$block_reason = '';
		if ( ! $complete_scan ) {
			$block_reason = 'El escaneo histórico quedó incompleto; no se puede usar como autoridad SEO.';
		} elseif ( array() !== $duplicate_local || array() !== $duplicate_remote ) {
			$block_reason = 'Hay slugs duplicados y la correspondencia histórica no es unívoca.';
		} elseif ( array() !== $missing ) {
			$block_reason = 'Faltan URLs históricas para una o más entradas publicadas del clon.';
		} elseif ( array() !== $invalid_links ) {
			$block_reason = 'La fuente histórica devolvió enlaces fuera de la base autorizada.';
		} elseif ( ! $structure_consistent ) {
			$block_reason = 'Las URLs históricas se recuperaron, pero no comparten una estructura simple y única que pueda convertirse automáticamente en permalink_structure.';
		}

		return array(
			'mode'                               => 'legacy-permalink-authority-preview',
			'write_performed'                    => false,
			'legacy_base_url'                    => $base,
			'rest_endpoint'                      => trailingslashit( $base ) . 'wp-json/wp/v2/posts',
			'current_posts_scanned'              => count( $local['posts'] ),
			'legacy_posts_scanned'               => count( $remote['posts'] ),
			'matched_posts'                      => count( $rows ),
			'missing_count'                      => count( $missing ),
			'missing'                            => $missing,
			'duplicate_local_slugs'              => $duplicate_local,
			'duplicate_legacy_slugs'             => $duplicate_remote,
			'invalid_legacy_links'               => $invalid_links,
			'complete_scan'                      => $complete_scan,
			'mapping_authoritative'              => $mapping_authoritative,
			'inferred_structure'                 => $inferred_structure,
			'structure_consistent'               => $structure_consistent,
			'seo_authority_verified'             => $seo_authority_verified,
			'authority_fingerprint'              => $authority_fingerprint,
			'rows'                               => $rows,
			'apply_blocked'                      => true,
			'block_reason'                       => $block_reason,
			'next_action'                        => $seo_authority_verified ? 'integrate-authoritative-permalink-plan' : 'review-legacy-authority-gaps',
			'environment'                        => EnvironmentPolicy::snapshot(),
			'policy'                             => array(
				'preview_only'                 => true,
				'same_host_only'               => true,
				'current_clone_path_rejected'  => true,
				'no_local_write'               => true,
				'no_remote_write'              => true,
				'complete_mapping_required'    => true,
				'unique_slug_mapping_required' => true,
			),
		);
	}

	/**
	 * @return array{posts:array<int,array{post_id:int,slug:string,title:string}>,complete:bool}
	 */
	private static function local_posts(): array {
		$ids = get_posts(
			array(
				'post_type'        => 'post',
				'post_status'      => 'publish',
				'fields'           => 'ids',
				'numberposts'      => self::MAX_POSTS + 1,
				'orderby'          => 'ID',
				'order'            => 'ASC',
				'suppress_filters' => false,
			)
		);
		$ids      = array_values( array_map( 'intval', is_array( $ids ) ? $ids : array() ) );
		$complete = count( $ids ) <= self::MAX_POSTS;
		if ( ! $complete ) {
			$ids = array_slice( $ids, 0, self::MAX_POSTS );
		}

		$posts = array();
		foreach ( $ids as $id ) {
			$post = get_post( $id );
			if ( ! $post ) {
				continue;
			}
			$posts[] = array(
				'post_id' => $id,
				'slug'    => (string) $post->post_name,
				'title'   => (string) $post->post_title,
			);
		}

		return array( 'posts' => $posts, 'complete' => $complete );
	}

	/**
	 * @return array{ok:bool,posts:array<int,array<string,mixed>>,complete:bool,message:string}
	 */
	private static function remote_posts( string $base ): array {
		$posts       = array();
		$total_pages = null;
		$complete    = true;
		$max_pages   = (int) ceil( self::MAX_POSTS / self::PER_PAGE );

		for ( $page = 1; $page <= $max_pages; ++$page ) {
			$url = add_query_arg(
				array(
					'per_page' => self::PER_PAGE,
					'page'     => $page,
					'status'   => 'publish',
					'_fields'  => 'id,slug,link',
				),
				trailingslashit( $base ) . 'wp-json/wp/v2/posts'
			);

			$response = wp_safe_remote_get(
				$url,
				array(
					'timeout'     => self::TIMEOUT,
					'redirection' => 2,
					'user-agent'  => 'SEO-GEO-Manager/' . ( defined( 'SEO_GEO_MANAGER_VERSION' ) ? SEO_GEO_MANAGER_VERSION : 'unknown' ),
				)
			);
			if ( is_wp_error( $response ) ) {
				return array( 'ok' => false, 'posts' => $posts, 'complete' => false, 'message' => 'No se pudo consultar el REST histórico: ' . $response->get_error_message() );
			}

			$status = (int) wp_remote_retrieve_response_code( $response );
			if ( 200 !== $status ) {
				return array( 'ok' => false, 'posts' => $posts, 'complete' => false, 'message' => 'El REST histórico respondió HTTP ' . $status . '.' );
			}
			$data = json_decode( (string) wp_remote_retrieve_body( $response ), true );
			if ( ! is_array( $data ) ) {
				return array( 'ok' => false, 'posts' => $posts, 'complete' => false, 'message' => 'El REST histórico no devolvió JSON válido.' );
			}

			if ( null === $total_pages ) {
				$header      = wp_remote_retrieve_header( $response, 'x-wp-totalpages' );
				$total_pages = is_numeric( $header ) ? max( 1, (int) $header ) : null;
				if ( null !== $total_pages && $total_pages > $max_pages ) {
					$complete = false;
				}
			}

			foreach ( $data as $row ) {
				if ( is_array( $row ) ) {
					$posts[] = $row;
				}
			}
			if ( count( $posts ) > self::MAX_POSTS ) {
				$posts    = array_slice( $posts, 0, self::MAX_POSTS );
				$complete = false;
				break;
			}

			if ( ( null !== $total_pages && $page >= $total_pages ) || ( null === $total_pages && count( $data ) < self::PER_PAGE ) ) {
				break;
			}
		}

		return array( 'ok' => true, 'posts' => $posts, 'complete' => $complete, 'message' => '' );
	}

	private static function candidate_structure( string $legacy_url, string $base, string $slug ): string {
		$path      = self::url_path( $legacy_url );
		$base_path = self::url_path( $base );
		$relative  = '/' === $base_path ? $path : '/' . ltrim( substr( $path, strlen( untrailingslashit( $base_path ) ) ), '/' );
		$trimmed   = trim( rawurldecode( $relative ), '/' );
		$segments  = '' === $trimmed ? array() : explode( '/', $trimmed );
		if ( array() === $segments || sanitize_title( (string) end( $segments ) ) !== sanitize_title( $slug ) ) {
			return '';
		}
		array_pop( $segments );
		$prefix = array_values( array_filter( $segments, static fn ( string $segment ): bool => '' !== $segment ) );

		return '/' . ( $prefix ? implode( '/', $prefix ) . '/' : '' ) . '%postname%/';
	}

	private static function normalize_base_url( string $url ): string {
		$url   = trim( $url );
		$parts = wp_parse_url( $url );
		if ( ! is_array( $parts ) || empty( $parts['scheme'] ) || empty( $parts['host'] ) ) {
			return '';
		}
		$scheme = strtolower( (string) $parts['scheme'] );
		if ( ! in_array( $scheme, array( 'http', 'https' ), true ) || isset( $parts['user'] ) || isset( $parts['pass'] ) ) {
			return '';
		}
		$host = strtolower( (string) $parts['host'] );
		$port = isset( $parts['port'] ) ? ':' . (int) $parts['port'] : '';
		$path = isset( $parts['path'] ) ? '/' . trim( (string) $parts['path'], '/' ) . '/' : '/';
		$path = '//' === $path ? '/' : $path;

		return $scheme . '://' . $host . $port . $path;
	}

	private static function canonical_base_key( string $url ): string {
		return untrailingslashit( self::normalize_base_url( $url ) );
	}

	private static function host_key( string $url ): string {
		$parts = wp_parse_url( $url );
		if ( ! is_array( $parts ) || empty( $parts['host'] ) ) {
			return '';
		}
		return strtolower( (string) $parts['host'] ) . ( isset( $parts['port'] ) ? ':' . (int) $parts['port'] : '' );
	}

	private static function url_inside_base( string $url, string $base ): bool {
		$url_path  = self::url_path( $url );
		$base_path = trailingslashit( self::url_path( $base ) );
		if ( '/' === $base_path ) {
			return true;
		}
		return 0 === strpos( trailingslashit( $url_path ), $base_path );
	}

	private static function url_path( string $url ): string {
		$path = wp_parse_url( $url, PHP_URL_PATH );
		if ( ! is_string( $path ) || '' === $path ) {
			return '/';
		}
		return '/' . ltrim( $path, '/' );
	}

	/**
	 * @param array<string,mixed>|null $local Local scan context.
	 * @param array<string,mixed>|null $remote Remote scan context.
	 * @return array<string,mixed>
	 */
	private static function blocked( string $base, string $message, ?array $local = null, ?array $remote = null ): array {
		return array(
			'mode'                   => 'legacy-permalink-authority-preview',
			'write_performed'        => false,
			'legacy_base_url'        => $base,
			'current_posts_scanned'  => is_array( $local ) && isset( $local['posts'] ) && is_array( $local['posts'] ) ? count( $local['posts'] ) : 0,
			'legacy_posts_scanned'   => is_array( $remote ) && isset( $remote['posts'] ) && is_array( $remote['posts'] ) ? count( $remote['posts'] ) : 0,
			'matched_posts'          => 0,
			'complete_scan'          => false,
			'mapping_authoritative'  => false,
			'inferred_structure'     => '',
			'structure_consistent'   => false,
			'seo_authority_verified' => false,
			'authority_fingerprint'  => '',
			'rows'                   => array(),
			'apply_blocked'          => true,
			'block_reason'           => $message,
			'next_action'            => 'review-legacy-authority-source',
			'environment'            => EnvironmentPolicy::snapshot(),
		);
	}
}
