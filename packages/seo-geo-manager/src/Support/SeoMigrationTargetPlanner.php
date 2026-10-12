<?php
/**
 * Read-only SEO/GEO migration target planner.
 *
 * @package SeoGeoManager
 */

declare(strict_types=1);

namespace SeoGeo\Manager\Support;

final class SeoMigrationTargetPlanner {
	private const MIN_SUPPORT_RATIO = 0.90;
	private const MIN_SUPPORT_POSTS = 10;
	private const MAX_OUTLIER_EVIDENCE = 50;

	/**
	 * Derive a stable, category-independent target permalink structure from a
	 * complete historical URL map without treating the legacy architecture as a
	 * mandatory template for the new site.
	 *
	 * @param array<string, mixed> $authority Historical authority report.
	 * @return array<string, mixed>
	 */
	public static function preview( array $authority ): array {
		$rows = isset( $authority['rows'] ) && is_array( $authority['rows'] ) ? $authority['rows'] : array();
		if ( true !== ( $authority['mapping_authoritative'] ?? false ) || array() === $rows ) {
			return self::blocked( 'Historical URL mapping is not complete and authoritative.' );
		}

		$counts = array();
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$candidate = isset( $row['candidate_structure'] ) && is_string( $row['candidate_structure'] )
				? trim( $row['candidate_structure'] )
				: '';
			if ( ! self::is_stable_candidate( $candidate ) || self::is_low_quality_candidate( $candidate ) ) {
				continue;
			}
			$counts[ $candidate ] = ( $counts[ $candidate ] ?? 0 ) + 1;
		}

		if ( array() === $counts ) {
			return self::blocked( 'No stable literal post-prefix candidate is present in the historical URL map.' );
		}

		arsort( $counts, SORT_NUMERIC );
		$candidate = (string) array_key_first( $counts );
		$support   = (int) ( $counts[ $candidate ] ?? 0 );
		$total     = count( $rows );
		$ratio     = 0 < $total ? $support / $total : 0.0;

		if ( $support < self::MIN_SUPPORT_POSTS || $ratio < self::MIN_SUPPORT_RATIO ) {
			return self::blocked(
				'No stable target dominates the historical corpus strongly enough for an automatic SEO/GEO migration recommendation.',
				array(
					'candidate'     => $candidate,
					'support_count' => $support,
					'total_mapped'  => $total,
					'support_ratio' => round( $ratio, 6 ),
				)
			);
		}

		$outliers = array();
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$row_candidate = isset( $row['candidate_structure'] ) && is_string( $row['candidate_structure'] )
				? trim( $row['candidate_structure'] )
				: '';
			if ( $candidate === $row_candidate ) {
				continue;
			}
			if ( count( $outliers ) < self::MAX_OUTLIER_EVIDENCE ) {
				$outliers[] = array(
					'post_id'             => (int) ( $row['post_id'] ?? 0 ),
					'historical_path'     => (string) ( $row['legacy_path'] ?? '' ),
					'candidate_structure' => $row_candidate,
				);
			}
		}

		$outlier_count  = max( 0, $total - $support );
		$pending_slugs  = isset( $authority['recovered_by_id_count'] ) ? max( 0, (int) $authority['recovered_by_id_count'] ) : 0;
		$redirect_floor = $outlier_count;

		return array(
			'mode'                              => 'seo-geo-clean-migration-target-preview',
			'write_performed'                   => false,
			'verified_for_migration'            => true,
			'candidate'                         => $candidate,
			'basis'                             => 'dominant-stable-literal-post-prefix',
			'support_count'                     => $support,
			'total_mapped'                      => $total,
			'support_ratio'                     => round( $ratio, 6 ),
			'outlier_count'                     => $outlier_count,
			'outliers'                          => $outliers,
			'evidence_truncated'                => $outlier_count > count( $outliers ),
			'pending_slug_repair_count'         => $pending_slugs,
			'minimum_redirects_from_outliers'   => $redirect_floor,
			'additional_slug_redirects_pending' => $pending_slugs,
			'architecture_goal'                 => 'stable-category-independent-post-urls',
			'next_action'                       => 0 < $pending_slugs ? 'repair-corrupted-local-slugs-first' : 'build-clean-target-redirect-plan',
			'policy'                            => array(
				'historical_urls_are_seo_authority'       => true,
				'legacy_architecture_is_not_mandatory'     => true,
				'category_independent_post_urls_preferred' => true,
				'one_hop_301_for_outliers'                 => true,
				'canonical_and_internal_links_target_final'=> true,
				'minimum_support_ratio'                    => self::MIN_SUPPORT_RATIO,
				'minimum_support_posts'                    => self::MIN_SUPPORT_POSTS,
			),
		);
	}

	/**
	 * @param array<string, mixed> $evidence Optional bounded evidence.
	 * @return array<string, mixed>
	 */
	private static function blocked( string $reason, array $evidence = array() ): array {
		return array_merge(
			array(
				'mode'                   => 'seo-geo-clean-migration-target-preview',
				'write_performed'        => false,
				'verified_for_migration' => false,
				'candidate'              => '',
				'basis'                  => 'unresolved',
				'block_reason'           => $reason,
				'next_action'            => 'review-migration-target-evidence',
			),
			$evidence
		);
	}

	private static function is_stable_candidate( string $candidate ): bool {
		if ( '' === $candidate || false === strpos( $candidate, '%postname%' ) ) {
			return false;
		}
		if ( 1 !== substr_count( $candidate, '%postname%' ) ) {
			return false;
		}

		$without_postname = str_replace( '%postname%', '', $candidate );
		if ( false !== strpos( $without_postname, '%' ) ) {
			return false;
		}
		if ( 1 !== preg_match( '#^/(?:[a-z0-9-]+/)*%postname%/$#', $candidate ) ) {
			return false;
		}

		return true;
	}

	private static function is_low_quality_candidate( string $candidate ): bool {
		$normalized = strtolower( trim( str_replace( '%postname%', '', $candidate ), '/' ) );
		$segments   = '' === $normalized ? array() : explode( '/', $normalized );
		$blocked    = array( 'uncategorized', 'uncategorised', 'sin-categoria', 'sin-categorizar' );

		return array() !== array_intersect( $segments, $blocked );
	}
}
