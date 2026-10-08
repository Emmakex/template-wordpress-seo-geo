<?php
/**
 * Group raw Site Intelligence evidence into actionable Build / Finish issues.
 *
 * @package SeoGeoManager
 */

declare(strict_types=1);

namespace SeoGeo\Manager\Intelligence;

final class ActionableDiagnostics {
	/**
	 * Build grouped diagnostic issues from raw site and Theme evidence.
	 *
	 * @param array<string, mixed> $site Site Intelligence report.
	 * @param array<string, mixed> $theme Theme contract report.
	 * @return array<string, mixed>
	 */
	public static function build( array $site, array $theme ): array {
		$items = array_merge(
			self::navigation_items( $site ),
			self::content_items( $theme )
		);

		usort(
			$items,
			static function ( array $left, array $right ): int {
				$rank = array(
					'blocker' => 0,
					'warning' => 1,
					'info'    => 2,
				);
				$left_rank  = $rank[ (string) ( $left['severity'] ?? 'info' ) ] ?? 2;
				$right_rank = $rank[ (string) ( $right['severity'] ?? 'info' ) ] ?? 2;
				if ( $left_rank === $right_rank ) {
					return strcmp( (string) ( $left['code'] ?? '' ), (string) ( $right['code'] ?? '' ) );
				}

				return $left_rank <=> $right_rank;
			}
		);

		$summary = array(
			'unique_issues'     => count( $items ),
			'blockers'          => 0,
			'warnings'          => 0,
			'auto_fixable'      => 0,
			'review_required'   => 0,
			'evidence_required' => 0,
			'raw_occurrences'   => 0,
		);

		foreach ( $items as $item ) {
			$severity       = (string) ( $item['severity'] ?? 'info' );
			$classification = (string) ( $item['classification'] ?? 'review' );
			if ( 'blocker' === $severity ) {
				++$summary['blockers'];
			} elseif ( 'warning' === $severity ) {
				++$summary['warnings'];
			}
			if ( 'auto-fixable' === $classification ) {
				++$summary['auto_fixable'];
			} elseif ( 'evidence-required' === $classification ) {
				++$summary['evidence_required'];
			} else {
				++$summary['review_required'];
			}
			$summary['raw_occurrences'] += max( 1, (int) ( $item['occurrences'] ?? 1 ) );
		}

		return array(
			'summary' => $summary,
			'items'   => $items,
			'note'    => 'Repeated detections are grouped by root cause. Auto-fixable means a deterministic target is known; it does not bypass preview/apply/verify policy.',
		);
	}

	/**
	 * @param array<string, mixed> $site Site Intelligence report.
	 * @return array<int, array<string, mixed>>
	 */
	private static function navigation_items( array $site ): array {
		$links      = isset( $site['links'] ) && is_array( $site['links'] ) ? $site['links'] : array();
		$leakage    = isset( $links['environment_leakage_candidates'] ) && is_array( $links['environment_leakage_candidates'] ) ? $links['environment_leakage_candidates'] : array();
		$unresolved = isset( $links['unresolved_internal_path_candidates'] ) && is_array( $links['unresolved_internal_path_candidates'] ) ? $links['unresolved_internal_path_candidates'] : array();
		$groups     = array();

		foreach ( $leakage as $candidate ) {
			if ( ! is_array( $candidate ) ) {
				continue;
			}
			$target_id  = isset( $candidate['target_resource_id'] ) ? (int) $candidate['target_resource_id'] : 0;
			$confidence = isset( $candidate['confidence'] ) && is_string( $candidate['confidence'] ) ? $candidate['confidence'] : 'medium';
			$reason     = isset( $candidate['reason'] ) && is_string( $candidate['reason'] ) ? $candidate['reason'] : 'environment-link';
			$absolute   = isset( $candidate['absolute_url'] ) && is_string( $candidate['absolute_url'] ) ? $candidate['absolute_url'] : '';

			if ( 'medium' === $confidence && 'external_host_matches_local_resource_path' === $reason ) {
				$external_path = (string) wp_parse_url( $absolute, PHP_URL_PATH );
				if ( '' === trim( $external_path, '/' ) ) {
					continue;
				}
			}

			$key = 'high' === $confidence && 0 < $target_id
				? 'leak:' . $target_id . ':' . $reason
				: 'leak:' . hash( 'sha256', strtolower( untrailingslashit( $absolute ) ) . ':' . $reason );

			if ( ! isset( $groups[ $key ] ) ) {
				$target_url = 0 < $target_id ? get_permalink( $target_id ) : '';
				$groups[ $key ] = array(
					'id'                => substr( hash( 'sha256', $key ), 0, 16 ),
					'category'          => 'navigation',
					'code'              => 'environment-link-leakage',
					'severity'          => 'high' === $confidence ? 'blocker' : 'warning',
					'classification'    => 'high' === $confidence && 0 < $target_id ? 'auto-fixable' : 'review',
					'title'             => 'Internal destination escapes the current WordPress environment.',
					'reason'            => $reason,
					'confidence'        => $confidence,
					'target_resource_id'=> $target_id,
					'current_url'       => $absolute,
					'suggested_url'     => is_string( $target_url ) ? $target_url : '',
					'occurrences'       => 0,
					'sources'           => array(),
				);
			}

			++$groups[ $key ]['occurrences'];
			self::append_source( $groups[ $key ]['sources'], $candidate['source'] ?? null );
		}

		foreach ( $unresolved as $candidate ) {
			if ( ! is_array( $candidate ) ) {
				continue;
			}
			$path               = isset( $candidate['path'] ) && is_string( $candidate['path'] ) ? $candidate['path'] : '';
			$absolute           = isset( $candidate['absolute_url'] ) && is_string( $candidate['absolute_url'] ) ? $candidate['absolute_url'] : '';
			$key                = 'unresolved:' . hash( 'sha256', strtolower( untrailingslashit( '' !== $path ? $path : $absolute ) ) );
			$broken_permalink   = self::looks_like_placeholder_permalink( $path . ' ' . $absolute );

			if ( ! isset( $groups[ $key ] ) ) {
				$groups[ $key ] = array(
					'id'             => substr( hash( 'sha256', $key ), 0, 16 ),
					'category'       => $broken_permalink ? 'permalinks' : 'navigation',
					'code'           => $broken_permalink ? 'malformed-permalink-template' : 'unresolved-internal-path',
					'severity'       => $broken_permalink ? 'blocker' : 'warning',
					'classification' => 'review',
					'title'          => $broken_permalink ? 'Post permalinks contain an unresolved placeholder token.' : 'Internal path does not resolve to inventoried content.',
					'current_url'    => $absolute,
					'path'           => $path,
					'occurrences'    => 0,
					'sources'        => array(),
					'next_action'    => $broken_permalink ? 'review-permalink-structure' : 'review-internal-route',
				);
			}

			++$groups[ $key ]['occurrences'];
			self::append_source( $groups[ $key ]['sources'], $candidate['source'] ?? null );
		}

		return array_values( $groups );
	}

	/**
	 * @param array<string, mixed> $theme Theme contract report.
	 * @return array<int, array<string, mixed>>
	 */
	private static function content_items( array $theme ): array {
		if ( true !== ( $theme['applicable'] ?? false ) ) {
			return array();
		}

		$pages = isset( $theme['pages'] ) && is_array( $theme['pages'] ) ? $theme['pages'] : array();
		$items = array();
		foreach ( $pages as $page ) {
			if ( ! is_array( $page ) || true !== ( $page['resolved'] ?? false ) ) {
				continue;
			}
			$model = isset( $page['model'] ) && is_array( $page['model'] ) ? $page['model'] : array();
			if ( ! isset( $model['model_id'] ) || ! is_string( $model['model_id'] ) || '' === $model['model_id'] ) {
				continue;
			}

			$required         = isset( $model['required_slots'] ) && is_array( $model['required_slots'] ) ? $model['required_slots'] : array();
			$present          = isset( $model['present_required_slots'] ) && is_array( $model['present_required_slots'] ) ? $model['present_required_slots'] : array();
			$missing          = isset( $model['missing_required_slots'] ) && is_array( $model['missing_required_slots'] ) ? $model['missing_required_slots'] : array();
			$missing_any      = isset( $model['missing_required_any'] ) && is_array( $model['missing_required_any'] ) ? $model['missing_required_any'] : array();
			$missing_verified = isset( $model['missing_verified_groups'] ) && is_array( $model['missing_verified_groups'] ) ? $model['missing_verified_groups'] : array();
			$resource         = isset( $page['resource'] ) && is_array( $page['resource'] ) ? $page['resource'] : array();
			$page_key         = isset( $page['key'] ) && is_string( $page['key'] ) ? $page['key'] : 'page';
			$title            = isset( $resource['title'] ) && is_string( $resource['title'] ) ? $resource['title'] : $page_key;

			if ( array() !== $required && array() === $present && array() !== $missing ) {
				$items[] = array(
					'id'             => substr( hash( 'sha256', 'hydrate:' . $page_key . ':' . $model['model_id'] ), 0, 16 ),
					'category'       => 'content',
					'code'           => 'theme-model-not-hydrated',
					'severity'       => 'warning',
					'classification' => 'hydrate',
					'title'          => $title . ' has a resolved Theme contract but no Manager slot markers are hydrated yet.',
					'page_key'       => $page_key,
					'resource_id'    => isset( $resource['id'] ) ? (int) $resource['id'] : 0,
					'model_id'       => $model['model_id'],
					'missing_slots'  => array_values( array_filter( $missing, 'is_string' ) ),
					'occurrences'    => count( $missing ),
					'next_action'    => 'prepare-theme-slot-hydration',
				);
			} elseif ( array() !== $missing || array() !== $missing_any ) {
				$items[] = array(
					'id'             => substr( hash( 'sha256', 'partial:' . $page_key . ':' . $model['model_id'] ), 0, 16 ),
					'category'       => 'content',
					'code'           => 'theme-model-partially-incomplete',
					'severity'       => 'blocker',
					'classification' => 'auto-fixable',
					'title'          => $title . ' has a partially hydrated Theme model with required values missing.',
					'page_key'       => $page_key,
					'resource_id'    => isset( $resource['id'] ) ? (int) $resource['id'] : 0,
					'model_id'       => $model['model_id'],
					'missing_slots'  => array_values( array_filter( $missing, 'is_string' ) ),
					'missing_any'    => $missing_any,
					'occurrences'    => count( $missing ) + count( $missing_any ),
					'next_action'    => 'prepare-structured-content-preview',
				);
			}

			if ( array() !== $missing_verified ) {
				$items[] = array(
					'id'                  => substr( hash( 'sha256', 'evidence:' . $page_key . ':' . $model['model_id'] ), 0, 16 ),
					'category'            => 'content',
					'code'                => 'verified-evidence-required',
					'severity'            => 'warning',
					'classification'      => 'evidence-required',
					'title'               => $title . ' requires verified evidence before proof content can be written.',
					'page_key'            => $page_key,
					'resource_id'         => isset( $resource['id'] ) ? (int) $resource['id'] : 0,
					'model_id'            => $model['model_id'],
					'verification_groups' => array_values( array_filter( $missing_verified, 'is_string' ) ),
					'occurrences'         => count( $missing_verified ),
					'next_action'         => 'collect-verified-evidence',
				);
			}
		}

		return $items;
	}

	/**
	 * Keep a bounded list of unique source descriptors.
	 *
	 * @param array<int, array<string, mixed>> $sources Source list.
	 * @param mixed                            $source Candidate source.
	 */
	private static function append_source( array &$sources, $source ): void {
		if ( ! is_array( $source ) || 8 <= count( $sources ) ) {
			return;
		}
		$id   = isset( $source['id'] ) ? (int) $source['id'] : 0;
		$kind = isset( $source['kind'] ) && is_string( $source['kind'] ) ? $source['kind'] : '';
		foreach ( $sources as $existing ) {
			if ( $id === (int) ( $existing['id'] ?? 0 ) && $kind === (string) ( $existing['kind'] ?? '' ) ) {
				return;
			}
		}
		$sources[] = array(
			'kind'      => $kind,
			'id'        => $id,
			'permalink' => isset( $source['permalink'] ) && is_string( $source['permalink'] ) ? $source['permalink'] : '',
		);
	}

	private static function looks_like_placeholder_permalink( string $value ): bool {
		return 1 === preg_match( '/(?:\\{[a-f0-9]{32,64}\\}|[a-f0-9]{32,64})(?:category|postname)/i', $value );
	}
}
