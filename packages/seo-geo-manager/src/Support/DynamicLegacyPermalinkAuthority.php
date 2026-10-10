<?php
/**
 * Read-only dynamic validation of historical permalink structures.
 *
 * @package SeoGeoManager
 */

declare(strict_types=1);

namespace SeoGeo\Manager\Support;

final class DynamicLegacyPermalinkAuthority {
	private const MAX_MISMATCH_EVIDENCE = 20;

	/**
	 * Extend legacy authority with rendered validation of a safe syntactic candidate.
	 *
	 * The baseline authority remains responsible for historical identity. This layer
	 * only resolves structure when the historical paths vary because WordPress tokens
	 * such as %category% expand to different values for different posts.
	 *
	 * @return array<string, mixed>
	 */
	public static function preview( string $legacy_base_url ): array {
		$authority = LegacyPermalinkAuthority::preview( $legacy_base_url );

		if (
			true !== ( $authority['mapping_authoritative'] ?? false )
			|| true === ( $authority['seo_authority_verified'] ?? false )
		) {
			return $authority;
		}

		$inspection = PermalinkInspector::preview();
		$candidate  = isset( $inspection['proposed_structure'] ) && is_string( $inspection['proposed_structure'] )
			? $inspection['proposed_structure']
			: '';

		if (
			true !== ( $inspection['safe_candidate'] ?? false )
			|| '' === $candidate
			|| false === strpos( $candidate, '%postname%' )
		) {
			return $authority;
		}

		$validation = self::validate_candidate( $authority, $candidate );
		$authority['structure_validation']        = $validation;
		$authority['structure_validation_method'] = 'syntactic-candidate-rendered-match';

		if ( true !== ( $validation['verified'] ?? false ) ) {
			return $authority;
		}

		$authority['inferred_structure']     = $candidate;
		$authority['structure_consistent']   = true;
		$authority['seo_authority_verified'] = true;
		$authority['block_reason']           = '';
		$authority['next_action']            = 0 < (int) ( $authority['recovered_by_id_count'] ?? 0 )
			? 'review-corrupted-local-slug-repair'
			: 'integrate-authoritative-permalink-plan';

		$fingerprint_payload = array(
			'baseline_authority_fingerprint' => (string) ( $authority['authority_fingerprint'] ?? '' ),
			'validated_structure'            => $candidate,
			'validation'                     => array(
				'eligible_posts'          => (int) ( $validation['eligible_posts'] ?? 0 ),
				'matched_posts'           => (int) ( $validation['matched_posts'] ?? 0 ),
				'skipped_corrupted_posts' => (int) ( $validation['skipped_corrupted_posts'] ?? 0 ),
				'mismatch_count'          => (int) ( $validation['mismatch_count'] ?? 0 ),
			),
		);
		$authority['authority_fingerprint'] = hash( 'sha256', (string) wp_json_encode( $fingerprint_payload ) );

		$policy = isset( $authority['policy'] ) && is_array( $authority['policy'] ) ? $authority['policy'] : array();
		$policy['dynamic_structure_candidate_requires_safe_inspector'] = true;
		$policy['dynamic_structure_rendered_match_required']           = true;
		$policy['corrupted_slug_rows_excluded_from_structure_proof']   = true;
		$policy['dynamic_structure_validation_read_only']              = true;
		$authority['policy'] = $policy;

		return $authority;
	}

	/**
	 * @param array<string, mixed> $authority Baseline authority result.
	 * @return array<string, mixed>
	 */
	private static function validate_candidate( array $authority, string $candidate ): array {
		$legacy_base = isset( $authority['legacy_base_url'] ) && is_string( $authority['legacy_base_url'] )
			? $authority['legacy_base_url']
			: '';
		$current_base = trailingslashit( home_url( '/' ) );
		$eligible     = 0;
		$matched      = 0;
		$skipped      = 0;
		$mismatches   = array();

		foreach ( (array) ( $authority['rows'] ?? array() ) as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			if ( true === ( $row['local_slug_corrupted'] ?? false ) ) {
				++$skipped;
				continue;
			}

			$post_id    = (int) ( $row['post_id'] ?? 0 );
			$legacy_url = isset( $row['legacy_url'] ) && is_string( $row['legacy_url'] ) ? $row['legacy_url'] : '';
			if ( 0 >= $post_id || '' === $legacy_url ) {
				self::append_mismatch(
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
				self::append_mismatch(
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

			self::append_mismatch(
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
	private static function append_mismatch( array &$mismatches, array $row ): void {
		if ( count( $mismatches ) < self::MAX_MISMATCH_EVIDENCE ) {
			$mismatches[] = $row;
		}
	}
}
