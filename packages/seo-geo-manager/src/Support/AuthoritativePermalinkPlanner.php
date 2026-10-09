<?php
/**
 * Read-only permalink plan derived from verified historical WordPress URLs.
 *
 * @package SeoGeoManager
 */

declare(strict_types=1);

namespace SeoGeo\Manager\Support;

final class AuthoritativePermalinkPlanner {
	private const MAX_EXISTING_RESOURCES = 5000;

	/**
	 * Build a bounded SEO preservation plan from verified historical URLs.
	 *
	 * @return array<string, mixed>
	 */
	public static function preview( string $legacy_base_url, string $expected_authority_fingerprint = '' ): array {
		$authority = LegacyPermalinkAuthority::preview( $legacy_base_url );
		if ( true !== ( $authority['seo_authority_verified'] ?? false ) ) {
			return self::blocked( $authority, 'La fuente histórica todavía no constituye una autoridad SEO completa y unívoca.', 'refresh-legacy-authority-preview' );
		}

		$authority_fingerprint = (string) ( $authority['authority_fingerprint'] ?? '' );
		if ( '' !== $expected_authority_fingerprint && ! hash_equals( $authority_fingerprint, $expected_authority_fingerprint ) ) {
			return self::blocked( $authority, 'La autoridad histórica cambió desde la previsualización anterior. Vuelve a inspeccionarla antes de continuar.', 'refresh-legacy-authority-preview', true );
		}

		$authoritative_structure = (string) ( $authority['inferred_structure'] ?? '' );
		if ( '' === $authoritative_structure || false === strpos( $authoritative_structure, '%postname%' ) ) {
			return self::blocked( $authority, 'La estructura histórica verificada no contiene un patrón de post utilizable.', 'review-legacy-authority-gaps' );
		}

		$home_base   = trailingslashit( home_url( '/' ) );
		$legacy_base = (string) ( $authority['legacy_base_url'] ?? '' );
		$existing    = self::existing_public_resource_index( $home_base );
		$rows        = array();
		$redirects   = array();
		$collisions  = array();
		$sources     = array();
		$targets     = array();
		$preserved   = 0;

		foreach ( (array) ( $authority['rows'] ?? array() ) as $authority_row ) {
			if ( ! is_array( $authority_row ) ) {
				continue;
			}

			$post_id    = (int) ( $authority_row['post_id'] ?? 0 );
			$slug       = (string) ( $authority_row['slug'] ?? '' );
			$legacy_url = (string) ( $authority_row['legacy_url'] ?? '' );
			$post       = 0 < $post_id ? get_post( $post_id ) : null;

			if ( ! $post || 'post' !== $post->post_type || 'publish' !== $post->post_status || $slug !== (string) $post->post_name ) {
				$collisions[] = array(
					'type'    => 'local-post-changed-since-authority-scan',
					'post_id' => $post_id,
					'slug'    => $slug,
				);
				continue;
			}

			$target_url          = self::permalink_for_structure( $post_id, $authoritative_structure );
			$historical_path     = self::logical_path( $legacy_url, $legacy_base );
			$target_logical_path = self::logical_path( $target_url, $home_base );

			if ( '' === $historical_path || '' === $target_logical_path ) {
				$collisions[] = array(
					'type'       => 'invalid-logical-path',
					'post_id'    => $post_id,
					'legacy_url' => $legacy_url,
					'target_url' => $target_url,
				);
				continue;
			}

			if ( isset( $sources[ $historical_path ] ) && $post_id !== $sources[ $historical_path ] ) {
				$collisions[] = array(
					'type'          => 'duplicate-historical-source',
					'post_id'       => $post_id,
					'other_post_id' => $sources[ $historical_path ],
					'source_path'   => $historical_path,
				);
			} else {
				$sources[ $historical_path ] = $post_id;
			}

			if ( isset( $targets[ $target_logical_path ] ) && $post_id !== $targets[ $target_logical_path ] ) {
				$collisions[] = array(
					'type'          => 'duplicate-authoritative-target',
					'post_id'       => $post_id,
					'other_post_id' => $targets[ $target_logical_path ],
					'target_path'   => $target_logical_path,
				);
			} else {
				$targets[ $target_logical_path ] = $post_id;
			}

			if ( isset( $existing['paths'][ $target_logical_path ] ) ) {
				$collisions[] = array(
					'type'                 => 'target-collides-with-existing-public-resource',
					'post_id'              => $post_id,
					'target_path'           => $target_logical_path,
					'existing_resource_id' => $existing['paths'][ $target_logical_path ],
				);
			}

			$path_preserved = $historical_path === $target_logical_path;
			if ( $path_preserved ) {
				++$preserved;
			} else {
				$redirects[] = array(
					'post_id'     => $post_id,
					'source_path' => $historical_path,
					'target_path' => $target_logical_path,
					'status'      => 301,
				);
			}

			$rows[] = array(
				'post_id'             => $post_id,
				'slug'                => $slug,
				'title'               => (string) $post->post_title,
				'historical_url'      => $legacy_url,
				'historical_path'     => $historical_path,
				'clone_preview_url'   => $target_url,
				'target_logical_path' => $target_logical_path,
				'path_preserved'      => $path_preserved,
			);
		}

		$complete_scan             = true === ( $authority['complete_scan'] ?? false ) && true === $existing['complete'];
		$safe_structure_candidate = $complete_scan && array() === $collisions && count( $rows ) === (int) ( $authority['matched_posts'] ?? 0 );
		$requires_redirect_runtime = 0 < count( $redirects );
		$apply_available           = $safe_structure_candidate && ! $requires_redirect_runtime;
		$preservation_mode         = array() === $redirects ? 'exact-path-preservation' : 'authoritative-301';
		$inspection                = PermalinkInspector::preview();

		$plan_payload = array(
			'authority_fingerprint'   => $authority_fingerprint,
			'current_fingerprint'     => (string) ( $inspection['current_fingerprint'] ?? '' ),
			'authoritative_structure' => $authoritative_structure,
			'rows'                    => $rows,
			'redirects'               => $redirects,
			'collisions'              => $collisions,
		);
		$plan_fingerprint = hash( 'sha256', (string) wp_json_encode( $plan_payload ) );

		$block_reason = '';
		if ( ! $complete_scan ) {
			$block_reason = 'El inventario de recursos públicos quedó truncado y no permite aprobar el cambio de estructura.';
		} elseif ( array() !== $collisions ) {
			$block_reason = 'El plan histórico contiene colisiones o cambios locales que deben resolverse antes de modificar los enlaces permanentes.';
		} elseif ( $requires_redirect_runtime ) {
			$block_reason = 'La estructura histórica verificada cambia una o más rutas públicas; Apply queda bloqueado hasta disponer de runtime 301 probado.';
		}

		return array(
			'mode'                         => 'authoritative-permalink-plan-preview',
			'write_performed'              => false,
			'legacy_base_url'              => $legacy_base,
			'authority_fingerprint'        => $authority_fingerprint,
			'current_structure'            => (string) ( $inspection['current_structure'] ?? '' ),
			'current_fingerprint'          => (string) ( $inspection['current_fingerprint'] ?? '' ),
			'authoritative_structure'      => $authoritative_structure,
			'plan_fingerprint'             => $plan_fingerprint,
			'matched_posts'                => count( $rows ),
			'path_preservation_count'      => $preserved,
			'planned_redirects'            => count( $redirects ),
			'redirects'                    => $redirects,
			'rows'                         => $rows,
			'collision_count'              => count( $collisions ),
			'collisions'                   => $collisions,
			'complete_scan'                => $complete_scan,
			'seo_preservation_mode'        => $preservation_mode,
			'safe_structure_candidate'     => $safe_structure_candidate,
			'requires_redirect_runtime'    => $requires_redirect_runtime,
			'apply_available'              => $apply_available,
			'apply_blocked'                => ! $apply_available,
			'block_reason'                 => $block_reason,
			'next_action'                  => $apply_available ? 'apply-authoritative-structure' : ( $safe_structure_candidate ? 'implement-authoritative-redirect-runtime-and-rollback' : 'resolve-authoritative-plan-blockers' ),
			'environment'                  => EnvironmentPolicy::snapshot(),
			'policy'                       => array(
				'preview_only'                    => true,
				'authority_revalidated'            => true,
				'logical_paths_compared'           => true,
				'clone_base_ignored_for_seo_path'  => true,
				'apply_requires_explicit_confirm'  => true,
				'apply_requires_current_plan'      => true,
				'apply_requires_environment_guard' => true,
				'no_redirect_registration'         => true,
				'complete_collision_scan_required' => true,
			),
		);
	}

	/**
	 * @param array<string, mixed> $authority Historical authority result.
	 * @return array<string, mixed>
	 */
	private static function blocked( array $authority, string $message, string $next_action, bool $stale_authority = false ): array {
		return array(
			'mode'                      => 'authoritative-permalink-plan-preview',
			'write_performed'           => false,
			'legacy_base_url'           => (string) ( $authority['legacy_base_url'] ?? '' ),
			'authority_fingerprint'     => (string) ( $authority['authority_fingerprint'] ?? '' ),
			'authoritative_structure'   => (string) ( $authority['inferred_structure'] ?? '' ),
			'plan_fingerprint'          => '',
			'matched_posts'             => 0,
			'path_preservation_count'   => 0,
			'planned_redirects'         => 0,
			'redirects'                 => array(),
			'rows'                      => array(),
			'collision_count'           => 0,
			'collisions'                => array(),
			'complete_scan'             => false,
			'seo_preservation_mode'     => 'blocked',
			'safe_structure_candidate'  => false,
			'requires_redirect_runtime' => false,
			'apply_available'           => false,
			'stale_authority'           => $stale_authority,
			'apply_blocked'             => true,
			'block_reason'              => $message,
			'next_action'               => $next_action,
			'environment'               => EnvironmentPolicy::snapshot(),
		);
	}

	/**
	 * @return array{paths:array<string,int>,complete:bool}
	 */
	private static function existing_public_resource_index( string $home_base ): array {
		$post_types = get_post_types( array( 'public' => true ), 'names' );
		$post_types = is_array( $post_types ) ? array_values( array_filter( $post_types, 'is_string' ) ) : array();
		$post_types = array_values( array_diff( $post_types, array( 'post', 'attachment' ) ) );
		if ( array() === $post_types ) {
			return array( 'paths' => array(), 'complete' => true );
		}

		$ids = get_posts(
			array(
				'post_type'        => $post_types,
				'post_status'      => 'publish',
				'fields'           => 'ids',
				'numberposts'      => self::MAX_EXISTING_RESOURCES + 1,
				'orderby'          => 'ID',
				'order'            => 'ASC',
				'suppress_filters' => false,
			)
		);
		$ids      = array_values( array_map( 'intval', is_array( $ids ) ? $ids : array() ) );
		$complete = count( $ids ) <= self::MAX_EXISTING_RESOURCES;
		if ( ! $complete ) {
			$ids = array_slice( $ids, 0, self::MAX_EXISTING_RESOURCES );
		}

		$paths = array();
		foreach ( $ids as $id ) {
			$url  = get_permalink( $id );
			$path = is_string( $url ) ? self::logical_path( $url, $home_base ) : '';
			if ( '' !== $path ) {
				$paths[ $path ] = $id;
			}
		}

		return array( 'paths' => $paths, 'complete' => $complete );
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
}
