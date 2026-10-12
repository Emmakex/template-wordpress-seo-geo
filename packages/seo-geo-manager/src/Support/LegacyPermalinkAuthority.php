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
	private const MAX_STRUCTURE_MISMATCH_EVIDENCE = 20;
	private const MIGRATION_MARKER_PATTERN = '/\{([a-f0-9]{32,64})\}/i';

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
		$remote_by_id           = array();
		$duplicate_remote_slugs = array();
		$duplicate_remote_ids   = array();
		foreach ( $remote['posts'] as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$remote_id = isset( $row['id'] ) && is_numeric( $row['id'] ) ? (int) $row['id'] : 0;
			$slug      = isset( $row['slug'] ) && is_string( $row['slug'] ) ? sanitize_title( $row['slug'] ) : '';
			$link      = isset( $row['link'] ) && is_string( $row['link'] ) ? esc_url_raw( $row['link'] ) : '';
			if ( '' === $slug || '' === $link ) {
				continue;
			}

			$record = array(
				'id'   => $remote_id,
				'slug' => $slug,
				'link' => $link,
			);

			if ( isset( $remote_by_slug[ $slug ] ) && (string) $remote_by_slug[ $slug ]['link'] !== $link ) {
				$duplicate_remote_slugs[ $slug ] = true;
			} else {
				$remote_by_slug[ $slug ] = $record;
			}

			if ( 0 < $remote_id ) {
				if (
					isset( $remote_by_id[ $remote_id ] )
					&& (
						(string) $remote_by_id[ $remote_id ]['slug'] !== $slug
						|| (string) $remote_by_id[ $remote_id ]['link'] !== $link
					)
				) {
					$duplicate_remote_ids[ $remote_id ] = true;
				} else {
					$remote_by_id[ $remote_id ] = $record;
				}
			}
		}

		$rows              = array();
		$missing           = array();
		$invalid_links     = array();
		$recovered_by_id   = array();
		$structure_counts  = array();
		$local_slug_counts = array_count_values( array_column( $local['posts'], 'slug' ) );
		$duplicate_local   = array_keys( array_filter( $local_slug_counts, static fn ( int $count ): bool => 1 < $count ) );

		foreach ( $local['posts'] as $post ) {
			$post_id            = (int) $post['post_id'];
			$slug               = (string) $post['slug'];
			$legacy_record      = null;
			$match_method       = 'slug';
			$local_slug_corrupt = self::is_recoverably_corrupted_slug( $slug );

			if ( '' !== $slug && isset( $remote_by_slug[ $slug ] ) ) {
				$legacy_record = $remote_by_slug[ $slug ];
			} elseif (
				$local_slug_corrupt
				&& isset( $remote_by_id[ $post_id ] )
				&& ! isset( $duplicate_remote_ids[ $post_id ] )
			) {
				$legacy_record = $remote_by_id[ $post_id ];
				$match_method  = 'corrupted-slug-id-fallback';
			} else {
				$missing[] = array( 'post_id' => $post_id, 'slug' => $slug );
				continue;
			}

			$historical_slug = isset( $legacy_record['slug'] ) && is_string( $legacy_record['slug'] ) ? $legacy_record['slug'] : '';
			$legacy_url      = isset( $legacy_record['link'] ) && is_string( $legacy_record['link'] ) ? $legacy_record['link'] : '';
			if ( '' === $historical_slug || '' === $legacy_url || self::host_key( $legacy_url ) !== self::host_key( $base ) || ! self::url_inside_base( $legacy_url, $base ) ) {
				$invalid_links[] = array( 'post_id' => $post_id, 'slug' => $slug, 'legacy_url' => $legacy_url );
				continue;
			}

			$candidate = self::candidate_structure( $legacy_url, $base, $historical_slug );
			if ( '' !== $candidate ) {
				$structure_counts[ $candidate ] = ( $structure_counts[ $candidate ] ?? 0 ) + 1;
			}

			if ( 'corrupted-slug-id-fallback' === $match_method ) {
				$recovered_by_id[] = array(
					'post_id'         => $post_id,
					'local_slug'      => $slug,
					'historical_slug' => $historical_slug,
					'legacy_url'      => $legacy_url,
				);
			}

			$rows[] = array(
				'post_id'              => $post_id,
				'slug'                 => $slug,
				'historical_slug'      => $historical_slug,
				'match_method'         => $match_method,
				'local_slug_corrupted' => $local_slug_corrupt,
				'title'                => (string) $post['title'],
				'legacy_url'           => $legacy_url,
				'legacy_path'          => self::url_path( $legacy_url ),
				'candidate_structure'  => $candidate,
			);
		}

		$complete_scan         = true === $remote['complete'] && true === $local['complete'];
		$duplicate_remote      = array_keys( $duplicate_remote_slugs );
		$duplicate_ids         = array_map( 'intval', array_keys( $duplicate_remote_ids ) );
		$all_mapped            = count( $rows ) === count( $local['posts'] ) && array() === $missing && array() === $invalid_links;
		$mapping_authoritative = $complete_scan && $all_mapped && array() === $duplicate_local && array() === $duplicate_remote && array() === $duplicate_ids;
		$inferred_structure    = 1 === count( $structure_counts ) ? (string) array_key_first( $structure_counts ) : '';
		$structure_consistent  = '' !== $inferred_structure && count( $rows ) === (int) ( $structure_counts[ $inferred_structure ] ?? 0 );
		$structure_validation  = null;
		$structure_method      = $structure_consistent ? 'literal-prefix' : 'unresolved';

		if ( $mapping_authoritative && ! $structure_consistent ) {
			$inspection = PermalinkInspector::preview();
			$syntactic_candidate = isset( $inspection['proposed_structure'] ) && is_string( $inspection['proposed_structure'] )
				? $inspection['proposed_structure']
				: '';
			if (
				true === ( $inspection['safe_candidate'] ?? false )
				&& '' !== $syntactic_candidate
				&& false !== strpos( $syntactic_candidate, '%postname%' )
			) {
				$structure_validation = self::validate_dynamic_structure( $rows, $base, $syntactic_candidate );
				if ( true === ( $structure_validation['verified'] ?? false ) ) {
					$inferred_structure   = $syntactic_candidate;
					$structure_consistent = true;
					$structure_method     = 'syntactic-candidate-rendered-match';
				}
			}
		}

		$seo_authority_verified = $mapping_authoritative && $structure_consistent;

		$fingerprint_payload = array(
			'legacy_base_url'             => $base,
			'local_posts'                 => $local['posts'],
			'rows'                        => $rows,
			'inferred_structure'          => $inferred_structure,
			'structure_validation_method' => $structure_method,
			'structure_validation'        => $structure_validation,
		);
		$authority_fingerprint = hash( 'sha256', (string) wp_json_encode( $fingerprint_payload ) );

		$block_reason = '';
		if ( ! $complete_scan ) {
			$block_reason = 'El escaneo histórico quedó incompleto; no se puede usar como autoridad SEO.';
		} elseif ( array() !== $duplicate_local || array() !== $duplicate_remote || array() !== $duplicate_ids ) {
			$block_reason = 'Hay slugs o IDs históricos duplicados y la correspondencia histórica no es unívoca.';
		} elseif ( array() !== $missing ) {
			$block_reason = 'Faltan URLs históricas para una o más entradas publicadas del clon.';
		} elseif ( array() !== $invalid_links ) {
			$block_reason = 'La fuente histórica devolvió enlaces fuera de la base autorizada.';
		} elseif ( ! $structure_consistent ) {
			$block_reason = 'Las URLs históricas se recuperaron, pero no comparten una estructura simple o una estructura dinámica de WordPress verificada que pueda convertirse automáticamente en permalink_structure.';
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
			'recovered_by_id_count'              => count( $recovered_by_id ),
			'recovered_by_id'                    => $recovered_by_id,
			'duplicate_local_slugs'              => $duplicate_local,
			'duplicate_legacy_slugs'             => $duplicate_remote,
			'duplicate_legacy_ids'               => $duplicate_ids,
			'invalid_legacy_links'               => $invalid_links,
			'complete_scan'                      => $complete_scan,
			'mapping_authoritative'              => $mapping_authoritative,
			'inferred_structure'                 => $inferred_structure,
			'structure_consistent'               => $structure_consistent,
			'structure_validation_method'        => $structure_method,
			'structure_validation'               => $structure_validation,
			'seo_authority_verified'             => $seo_authority_verified,
			'authority_fingerprint'              => $authority_fingerprint,
			'rows'                               => $rows,
			'apply_blocked'                      => true,
			'block_reason'                       => $block_reason,
			'next_action'                        => $seo_authority_verified ? ( array() === $recovered_by_id ? 'integrate-authoritative-permalink-plan' : 'review-corrupted-local-slug-repair' ) : 'review-legacy-authority-gaps',
			'environment'                        => EnvironmentPolicy::snapshot(),
			'policy'                             => array(
				'preview_only'                          => true,
				'same_host_only'                        => true,
				'current_clone_path_rejected'           => true,
				'no_local_write'                        => true,
				'no_remote_write'                       => true,
				'complete_mapping_required'             => true,
				'unique_slug_mapping_required'          => true,
				'corrupted_slug_id_fallback_only'       => true,
				'id_fallback_requires_unique_remote_id' => true,
				'clean_slug_id_fallback_forbidden'      => true,
				'dynamic_structure_candidate_safe_only' => true,
				'dynamic_structure_rendered_match'      => true,
				'corrupted_slug_rows_skipped_in_structure_proof' => true,
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

	/**
	 * Validate a syntactically recovered dynamic WordPress permalink candidate by
	 * rendering each clean local post through WordPress and comparing logical paths
	 * with its verified historical URL. Corrupted local slugs are deliberately
	 * excluded from structure proof; their identity is handled separately by the
	 * same-ID migration fallback and remains a repair blocker in the planner.
	 *
	 * @param array<int, array<string, mixed>> $rows Historical mapping rows.
	 * @return array<string, mixed>
	 */
	private static function validate_dynamic_structure( array $rows, string $legacy_base, string $candidate ): array {
		$current_base = trailingslashit( home_url( '/' ) );
		$eligible     = 0;
		$matched      = 0;
		$skipped      = 0;
		$mismatches   = array();

		foreach ( $rows as $row ) {
			if ( true === ( $row['local_slug_corrupted'] ?? false ) ) {
				++$skipped;
				continue;
			}

			$post_id    = (int) ( $row['post_id'] ?? 0 );
			$legacy_url = isset( $row['legacy_url'] ) && is_string( $row['legacy_url'] ) ? $row['legacy_url'] : '';
			if ( 0 >= $post_id || '' === $legacy_url ) {
				self::append_structure_mismatch(
					$mismatches,
					array(
						'post_id' => $post_id,
						'reason'  => 'missing-post-or-historical-url',
					)
				);
				continue;
			}

			$post = get_post( $post_id );
			if ( ! $post || 'post' !== $post->post_type || 'publish' !== $post->post_status ) {
				self::append_structure_mismatch(
					$mismatches,
					array(
						'post_id' => $post_id,
						'reason'  => 'local-post-no-longer-published',
					)
				);
				continue;
			}

			++$eligible;
			$candidate_url  = self::permalink_for_structure( $post_id, $candidate );
			$candidate_path = self::logical_path( $candidate_url, $current_base );
			$legacy_path    = self::logical_path( $legacy_url, $legacy_base );

			if ( '' !== $candidate_path && '' !== $legacy_path && $candidate_path === $legacy_path ) {
				++$matched;
				continue;
			}

			self::append_structure_mismatch(
				$mismatches,
				array(
					'post_id'        => $post_id,
					'candidate_path' => $candidate_path,
					'legacy_path'    => $legacy_path,
					'reason'         => 'rendered-path-mismatch',
				)
			);
		}

		$mismatch_count = max( 0, $eligible - $matched );
		$verified       = 0 < $eligible && $eligible === $matched && 0 === $mismatch_count;

		return array(
			'candidate'               => $candidate,
			'eligible_posts'          => $eligible,
			'matched_posts'           => $matched,
			'skipped_corrupted_posts' => $skipped,
			'mismatch_count'          => $mismatch_count,
			'mismatches'              => $mismatches,
			'evidence_truncated'       => $mismatch_count > count( $mismatches ),
			'verified'                 => $verified,
			'write_performed'          => false,
		);
	}

	private static function permalink_for_structure( int $post_id, string $structure ): string {
		$filter = static function () use ( $structure ): string {
			return $structure;
		};
		add_filter( 'pre_option_permalink_structure', $filter, PHP_INT_MAX, 0 );
		try {
			$url = get_permalink( $post_id );
		} finally {
			remove_filter( 'pre_option_permalink_structure', $filter, PHP_INT_MAX );
		}

		return is_string( $url ) ? $url : '';
	}

	private static function logical_path( string $url, string $base ): string {
		$url_parts  = wp_parse_url( $url );
		$base_parts = wp_parse_url( $base );
		if ( ! is_array( $url_parts ) || ! is_array( $base_parts ) || empty( $url_parts['host'] ) || empty( $base_parts['host'] ) ) {
			return '';
		}

		$url_host  = strtolower( (string) $url_parts['host'] ) . ( isset( $url_parts['port'] ) ? ':' . (int) $url_parts['port'] : '' );
		$base_host = strtolower( (string) $base_parts['host'] ) . ( isset( $base_parts['port'] ) ? ':' . (int) $base_parts['port'] : '' );
		if ( $url_host !== $base_host ) {
			return '';
		}

		$url_path  = isset( $url_parts['path'] ) ? '/' . ltrim( (string) $url_parts['path'], '/' ) : '/';
		$base_path = isset( $base_parts['path'] ) ? '/' . trim( (string) $base_parts['path'], '/' ) : '/';
		$base_path = '/' === $base_path ? '/' : trailingslashit( $base_path );
		if ( '/' !== $base_path && 0 !== strpos( trailingslashit( $url_path ), $base_path ) ) {
			return '';
		}

		$relative = '/' === $base_path ? $url_path : '/' . ltrim( substr( $url_path, strlen( untrailingslashit( $base_path ) ) ), '/' );
		$relative = '/' . ltrim( $relative, '/' );

		return '/' === $relative ? '/' : trailingslashit( $relative );
	}

	/**
	 * @param array<int, array<string, mixed>> $mismatches Mismatch evidence.
	 * @param array<string, mixed>             $row Mismatch row.
	 */
	private static function append_structure_mismatch( array &$mismatches, array $row ): void {
		if ( count( $mismatches ) < self::MAX_STRUCTURE_MISMATCH_EVIDENCE ) {
			$mismatches[] = $row;
		}
	}

	private static function is_recoverably_corrupted_slug( string $slug ): bool {
		if ( '' === $slug || ! preg_match_all( self::MIGRATION_MARKER_PATTERN, $slug, $matches ) ) {
			return false;
		}

		$markers = array_values(
			array_unique(
				array_map(
					static fn ( string $marker ): string => strtolower( $marker ),
					array_values( array_filter( $matches[0], 'is_string' ) )
				)
			)
		);
		if ( 1 !== count( $markers ) ) {
			return false;
		}

		$restored = str_ireplace( $markers[0], '%', $slug );
		if ( $restored === $slug || preg_match( self::MIGRATION_MARKER_PATTERN, $restored ) ) {
			return false;
		}
		if ( preg_match( '/%(?![a-f0-9]{2})/i', $restored ) ) {
			return false;
		}

		return 1 === preg_match( '/(?:%[a-f0-9]{2}){2,}/i', $restored );
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
			'missing_count'          => 0,
			'missing'                => array(),
			'recovered_by_id_count'  => 0,
			'recovered_by_id'        => array(),
			'duplicate_legacy_ids'   => array(),
			'complete_scan'          => false,
			'mapping_authoritative'  => false,
			'inferred_structure'     => '',
			'structure_consistent'   => false,
			'structure_validation_method' => 'unresolved',
			'structure_validation'   => null,
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
