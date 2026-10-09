<?php
/**
 * Read-only redirect planning for permalink structure repairs.
 *
 * @package SeoGeoManager
 */

declare(strict_types=1);

namespace SeoGeo\Manager\Support;

final class PermalinkRedirectPlanner {
	private const MAX_POSTS              = 2000;
	private const MAX_EXISTING_RESOURCES = 5000;

	/**
	 * Build a bounded, read-only redirect map for a deterministic permalink repair.
	 *
	 * @return array<string, mixed>
	 */
	public static function preview(): array {
		$inspection = PermalinkInspector::preview();
		$current    = (string) ( $inspection['current_structure'] ?? '' );
		$proposed   = (string) ( $inspection['proposed_structure'] ?? '' );

		if ( true !== ( $inspection['safe_candidate'] ?? false ) ) {
			return self::blocked_plan( $inspection, 'The permalink repair candidate is not deterministic enough to build a redirect plan.' );
		}

		$post_ids = get_posts(
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
		$post_ids = array_values( array_map( 'intval', is_array( $post_ids ) ? $post_ids : array() ) );
		$complete_post_scan = count( $post_ids ) <= self::MAX_POSTS;
		if ( ! $complete_post_scan ) {
			$post_ids = array_slice( $post_ids, 0, self::MAX_POSTS );
		}

		$existing = self::existing_public_resource_index();
		$redirects = array();
		$collisions = array();
		$targets = array();
		$skipped_unchanged = 0;
		$home_host = self::host_key( home_url( '/' ) );

		foreach ( $post_ids as $post_id ) {
			$old_url = self::permalink_for_structure( $post_id, $current );
			$new_url = self::permalink_for_structure( $post_id, $proposed );
			$old_key = self::canonical_url( $old_url );
			$new_key = self::canonical_url( $new_url );

			if ( '' === $old_key || '' === $new_key ) {
				$collisions[] = array(
					'type'    => 'invalid-generated-url',
					'post_id' => $post_id,
					'old_url' => $old_url,
					'new_url' => $new_url,
				);
				continue;
			}

			if ( $home_host !== self::host_key( $old_url ) || $home_host !== self::host_key( $new_url ) ) {
				$collisions[] = array(
					'type'    => 'host-change-detected',
					'post_id' => $post_id,
					'old_url' => $old_url,
					'new_url' => $new_url,
				);
				continue;
			}

			if ( $old_key === $new_key ) {
				++$skipped_unchanged;
				continue;
			}

			if ( isset( $targets[ $new_key ] ) && $targets[ $new_key ] !== $post_id ) {
				$collisions[] = array(
					'type'          => 'duplicate-new-target',
					'post_id'       => $post_id,
					'other_post_id' => $targets[ $new_key ],
					'new_url'       => $new_url,
				);
			} else {
				$targets[ $new_key ] = $post_id;
			}

			if ( isset( $existing['urls'][ $new_key ] ) ) {
				$collisions[] = array(
					'type'                 => 'target-collides-with-existing-public-resource',
					'post_id'              => $post_id,
					'new_url'              => $new_url,
					'existing_resource_id' => $existing['urls'][ $new_key ],
				);
			}

			$redirects[] = array(
				'post_id'  => $post_id,
				'title'    => (string) get_the_title( $post_id ),
				'old_url'  => $old_url,
				'new_url'  => $new_url,
				'old_path' => self::url_path( $old_url ),
				'new_path' => self::url_path( $new_url ),
				'status'   => 301,
			);
		}

		$complete_scan = $complete_post_scan && true === $existing['complete'];
		$safe_to_apply = $complete_scan && array() === $collisions && count( $redirects ) > 0;
		$plan_fingerprint = hash(
			'sha256',
			(string) wp_json_encode(
				array(
					'structure_fingerprint' => (string) ( $inspection['current_fingerprint'] ?? '' ),
					'proposed_structure'     => $proposed,
					'redirects'              => $redirects,
			)
			)
		);

		return array(
			'mode'                       => 'redirect-plan-preview',
			'write_performed'            => false,
			'current_structure'          => $current,
			'proposed_structure'         => $proposed,
			'structure_fingerprint'      => (string) ( $inspection['current_fingerprint'] ?? '' ),
			'plan_fingerprint'           => $plan_fingerprint,
			'published_posts_scanned'    => count( $post_ids ),
			'planned_redirects'          => count( $redirects ),
			'skipped_unchanged'          => $skipped_unchanged,
			'collision_count'            => count( $collisions ),
			'collisions'                 => $collisions,
			'redirects'                  => $redirects,
			'complete_scan'              => $complete_scan,
			'posts_scan_truncated'       => ! $complete_post_scan,
			'resource_scan_truncated'    => true !== $existing['complete'],
			'safe_to_apply'              => $safe_to_apply,
			'apply_blocked'              => true,
			'next_action'                => $safe_to_apply ? 'build-permalink-redirect-runtime-and-approval' : 'resolve-permalink-plan-blockers',
			'environment'                => EnvironmentPolicy::snapshot(),
			'policy'                     => array(
				'preview_only'                   => true,
				'exact_301_redirects_required'    => true,
				'no_permalink_option_write'        => true,
				'no_redirect_registration'         => true,
				'no_rewrite_flush'                 => true,
				'complete_collision_scan_required' => true,
			),
		);
	}

	/**
	 * @param array<string, mixed> $inspection Permalink inspection result.
	 * @return array<string, mixed>
	 */
	private static function blocked_plan( array $inspection, string $message ): array {
		return array(
			'mode'                  => 'redirect-plan-preview',
			'write_performed'       => false,
			'current_structure'     => (string) ( $inspection['current_structure'] ?? '' ),
			'proposed_structure'    => (string) ( $inspection['proposed_structure'] ?? '' ),
			'structure_fingerprint' => (string) ( $inspection['current_fingerprint'] ?? '' ),
			'plan_fingerprint'      => '',
			'planned_redirects'     => 0,
			'collision_count'       => 0,
			'collisions'            => array(),
			'redirects'             => array(),
			'complete_scan'         => false,
			'safe_to_apply'         => false,
			'apply_blocked'         => true,
			'block_reason'          => $message,
			'next_action'           => 'manual-permalink-review',
			'environment'           => EnvironmentPolicy::snapshot(),
		);
	}

	/**
	 * @return array{urls:array<string,int>,complete:bool}
	 */
	private static function existing_public_resource_index(): array {
		$post_types = get_post_types( array( 'public' => true ), 'names' );
		$post_types = is_array( $post_types ) ? array_values( array_filter( $post_types, 'is_string' ) ) : array();
		$post_types = array_values( array_diff( $post_types, array( 'post', 'attachment' ) ) );

		if ( array() === $post_types ) {
			return array(
				'urls'     => array(),
				'complete' => true,
			);
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
		$ids = array_values( array_map( 'intval', is_array( $ids ) ? $ids : array() ) );
		$complete = count( $ids ) <= self::MAX_EXISTING_RESOURCES;
		if ( ! $complete ) {
			$ids = array_slice( $ids, 0, self::MAX_EXISTING_RESOURCES );
		}

		$urls = array();
		foreach ( $ids as $id ) {
			$url = get_permalink( $id );
			$key = is_string( $url ) ? self::canonical_url( $url ) : '';
			if ( '' !== $key ) {
				$urls[ $key ] = $id;
			}
		}

		return array(
			'urls'     => $urls,
			'complete' => $complete,
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

	private static function canonical_url( string $url ): string {
		$parts = wp_parse_url( $url );
		if ( ! is_array( $parts ) || empty( $parts['host'] ) ) {
			return '';
		}
		$scheme = isset( $parts['scheme'] ) ? strtolower( (string) $parts['scheme'] ) : 'https';
		$host   = strtolower( (string) $parts['host'] );
		$port   = isset( $parts['port'] ) ? ':' . (int) $parts['port'] : '';
		$path   = isset( $parts['path'] ) ? (string) $parts['path'] : '/';
		$path   = '/' . ltrim( $path, '/' );
		if ( '/' !== $path ) {
			$path = untrailingslashit( $path );
		}

		return $scheme . '://' . $host . $port . $path;
	}

	private static function host_key( string $url ): string {
		$parts = wp_parse_url( $url );
		if ( ! is_array( $parts ) || empty( $parts['host'] ) ) {
			return '';
		}
		$host = strtolower( (string) $parts['host'] );
		$port = isset( $parts['port'] ) ? ':' . (int) $parts['port'] : '';

		return $host . $port;
	}

	private static function url_path( string $url ): string {
		$path = wp_parse_url( $url, PHP_URL_PATH );

		return is_string( $path ) && '' !== $path ? $path : '/';
	}
}
