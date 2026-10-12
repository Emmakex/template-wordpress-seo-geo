<?php
/**
 * Aggregate bounded Build / Finish readiness evidence.
 *
 * @package SeoGeoManager
 */

declare(strict_types=1);

namespace SeoGeo\Manager\Intelligence;

final class BuildFinishReadiness {
	/**
	 * @param array<string, mixed> $site Site intelligence.
	 * @param array<string, mixed> $theme Theme contract intelligence.
	 * @param array<string, mixed> $seo SEO authority intelligence.
	 * @param array<string, mixed> $media Media intelligence.
	 * @return array<string, mixed>
	 */
	public static function aggregate( array $site, array $theme, array $seo, array $media ): array {
		$checks = array();

		$theme_summary = isset( $theme['summary'] ) && is_array( $theme['summary'] ) ? $theme['summary'] : array();
		if ( true === ( $theme['applicable'] ?? false ) ) {
			$missing_pages = isset( $theme_summary['missing_pages'] ) ? (int) $theme_summary['missing_pages'] : 0;
			$checks[]      = self::check(
				'structure',
				0 === $missing_pages ? 'pass' : 'blocker',
				0 === $missing_pages ? 'Expected preset pages are resolved.' : 'One or more expected preset pages are unresolved.',
				array( 'missing_pages' => $missing_pages )
			);

			$content_state = self::theme_content_state( $theme );
			if ( 0 < $content_state['partial_pages'] ) {
				$content_level   = 'blocker';
				$content_message = 'Partially hydrated Theme models still have required content gaps.';
			} elseif ( 0 < $content_state['unhydrated_pages'] || 0 < $content_state['evidence_pages'] ) {
				$content_level   = 'warning';
				$content_message = 'Theme-rendered pages still need Manager slot hydration and/or verified evidence review.';
			} else {
				$content_level   = 'pass';
				$content_message = 'Required structured content is hydrated and evidence requirements are satisfied.';
			}

			$checks[] = self::check(
				'content',
				$content_level,
				$content_message,
				array(
					'partial_model_pages'    => $content_state['partial_pages'],
					'unhydrated_model_pages' => $content_state['unhydrated_pages'],
					'evidence_review_pages'  => $content_state['evidence_pages'],
					'raw_missing_slots'      => isset( $theme_summary['missing_required_slots'] ) ? (int) $theme_summary['missing_required_slots'] : 0,
				)
			);
		} else {
			$checks[] = self::check(
				'structure',
				'warning',
				'No SEO/GEO Theme preset contract is available; generic WordPress intelligence remains active.',
				array()
			);
		}

		$navigation_state = self::navigation_state( $site );
		if ( 0 < $navigation_state['blockers'] ) {
			$navigation_level   = 'blocker';
			$navigation_message = 'Actionable environment-link or permalink blockers remain.';
		} elseif ( 0 < $navigation_state['warnings'] ) {
			$navigation_level   = 'warning';
			$navigation_message = 'Some internal navigation findings still require review.';
		} else {
			$navigation_level   = 'pass';
			$navigation_message = 'No bounded navigation blockers were detected.';
		}
		$checks[] = self::check(
			'navigation',
			$navigation_level,
			$navigation_message,
			$navigation_state
		);

		$seo_state    = isset( $seo['state'] ) && is_string( $seo['state'] ) ? $seo['state'] : 'unresolved';
		$seo_resolved = true === ( $seo['authority_resolved'] ?? false );
		$seo_conflict = true === ( $seo['conflict'] ?? false );
		$seo_writable = true === ( $seo['write_adapter_ready'] ?? false );
		if ( $seo_conflict ) {
			$seo_level   = 'blocker';
			$seo_message = 'Multiple SEO provider families can own the same public signals; one authority must be selected.';
		} elseif ( ! $seo_resolved ) {
			$seo_level   = 'warning';
			$seo_message = 'SEO output authority is not established yet.';
		} elseif ( ! $seo_writable ) {
			$seo_level   = 'warning';
			$seo_message = 'One SEO output authority is resolved read-only; its write adapter is not enabled yet.';
		} else {
			$seo_level   = 'pass';
			$seo_message = 'One SEO output authority is resolved and its write adapter is ready.';
		}
		$checks[] = self::check(
			'seo-authority',
			$seo_level,
			$seo_message,
			array(
				'state'               => $seo_state,
				'authority_resolved'  => $seo_resolved,
				'write_adapter_ready' => $seo_writable,
				'authority'           => $seo['authority'] ?? null,
			)
		);

		$missing_alt = isset( $media['images_missing_alt'] ) ? (int) $media['images_missing_alt'] : 0;
		$checks[]    = self::check(
			'media',
			0 === $missing_alt ? 'pass' : 'warning',
			0 === $missing_alt ? 'No missing image-alt values were found in the bounded library scan.' : 'Some scanned image attachments have no alt text.',
			array(
				'images_missing_alt' => $missing_alt,
				'truncated'          => true === ( $media['truncated'] ?? false ),
			)
		);

		$rendered          = isset( $site['rendered_scan'] ) && is_array( $site['rendered_scan'] ) ? $site['rendered_scan'] : array();
		$rendered_enabled  = true === ( $rendered['enabled'] ?? false );
		$rendered_failures = isset( $rendered['failures'] ) && is_array( $rendered['failures'] ) ? count( $rendered['failures'] ) : 0;
		$checks[]          = self::check(
			'frontend-verification',
			! $rendered_enabled ? 'warning' : ( 0 < $rendered_failures ? 'warning' : 'pass' ),
			! $rendered_enabled ? 'Rendered verification has not been requested for this scan.' : ( 0 < $rendered_failures ? 'Some rendered surfaces could not be verified.' : 'Bounded rendered surfaces were verified.' ),
			array(
				'enabled'  => $rendered_enabled,
				'failures' => $rendered_failures,
			)
		);

		$checks[] = self::check(
			'operations',
			'pass',
			'M2 preview/apply/verify/rollback and Theme structured-slot mutations are available under explicit safety policy.',
			array(
				'm2_ready'                 => true,
				'structured_theme_writes'  => true,
				'environment_bound_writes' => true,
			)
		);

		$blockers = 0;
		$warnings = 0;
		foreach ( $checks as $check ) {
			if ( 'blocker' === $check['status'] ) {
				++$blockers;
			} elseif ( 'warning' === $check['status'] ) {
				++$warnings;
			}
		}

		return array(
			'overall'  => 0 < $blockers ? 'blocker' : ( 0 < $warnings ? 'warning' : 'pass' ),
			'blockers' => $blockers,
			'warnings' => $warnings,
			'checks'   => $checks,
			'note'     => 'Readiness is bounded diagnostic evidence, not a ranking or business-outcome guarantee.',
		);
	}

	/**
	 * Distinguish Theme defaults that are not hydrated from partially missing data.
	 *
	 * @param array<string, mixed> $theme Theme contract intelligence.
	 * @return array{partial_pages:int,unhydrated_pages:int,evidence_pages:int}
	 */
	private static function theme_content_state( array $theme ): array {
		$state = array(
			'partial_pages'    => 0,
			'unhydrated_pages' => 0,
			'evidence_pages'   => 0,
		);
		$pages = isset( $theme['pages'] ) && is_array( $theme['pages'] ) ? $theme['pages'] : array();
		foreach ( $pages as $page ) {
			if ( ! is_array( $page ) || true !== ( $page['resolved'] ?? false ) ) {
				continue;
			}
			$model = isset( $page['model'] ) && is_array( $page['model'] ) ? $page['model'] : array();
			if ( ! isset( $model['model_id'] ) || ! is_string( $model['model_id'] ) || '' === $model['model_id'] ) {
				continue;
			}

			$required = isset( $model['required_slots'] ) && is_array( $model['required_slots'] ) ? $model['required_slots'] : array();
			$present  = isset( $model['present_required_slots'] ) && is_array( $model['present_required_slots'] ) ? $model['present_required_slots'] : array();
			$missing  = isset( $model['missing_required_slots'] ) && is_array( $model['missing_required_slots'] ) ? $model['missing_required_slots'] : array();
			$missing_any = isset( $model['missing_required_any'] ) && is_array( $model['missing_required_any'] ) ? $model['missing_required_any'] : array();
			$missing_verified = isset( $model['missing_verified_groups'] ) && is_array( $model['missing_verified_groups'] ) ? $model['missing_verified_groups'] : array();

			if ( array() !== $required && array() === $present && array() !== $missing ) {
				++$state['unhydrated_pages'];
			} elseif ( array() !== $missing || array() !== $missing_any ) {
				++$state['partial_pages'];
			}
			if ( array() !== $missing_verified ) {
				++$state['evidence_pages'];
			}
		}

		return $state;
	}

	/**
	 * Prefer grouped root causes when available, while retaining a safe fallback.
	 *
	 * @param array<string, mixed> $site Site intelligence.
	 * @return array<string, int>
	 */
	private static function navigation_state( array $site ): array {
		$state = array(
			'blockers'                    => 0,
			'warnings'                    => 0,
			'unique_navigation_issues'    => 0,
			'high_confidence_leakage'     => 0,
			'unresolved_path_occurrences' => 0,
		);
		$diagnostics = isset( $site['actionable_diagnostics'] ) && is_array( $site['actionable_diagnostics'] ) ? $site['actionable_diagnostics'] : array();
		$items       = isset( $diagnostics['items'] ) && is_array( $diagnostics['items'] ) ? $diagnostics['items'] : array();
		if ( array() !== $items ) {
			foreach ( $items as $item ) {
				if ( ! is_array( $item ) ) {
					continue;
				}
				$category = isset( $item['category'] ) && is_string( $item['category'] ) ? $item['category'] : '';
				if ( 'navigation' !== $category && 'permalinks' !== $category ) {
					continue;
				}
				++$state['unique_navigation_issues'];
				if ( 'blocker' === ( $item['severity'] ?? '' ) ) {
					++$state['blockers'];
				} elseif ( 'warning' === ( $item['severity'] ?? '' ) ) {
					++$state['warnings'];
				}
				if ( 'environment-link-leakage' === ( $item['code'] ?? '' ) && 'high' === ( $item['confidence'] ?? '' ) ) {
					$state['high_confidence_leakage'] += max( 1, (int) ( $item['occurrences'] ?? 1 ) );
				}
				if ( 'unresolved-internal-path' === ( $item['code'] ?? '' ) || 'malformed-permalink-template' === ( $item['code'] ?? '' ) ) {
					$state['unresolved_path_occurrences'] += max( 1, (int) ( $item['occurrences'] ?? 1 ) );
				}
			}

			return $state;
		}

		$links      = isset( $site['links'] ) && is_array( $site['links'] ) ? $site['links'] : array();
		$leakage    = isset( $links['environment_leakage_candidates'] ) && is_array( $links['environment_leakage_candidates'] ) ? $links['environment_leakage_candidates'] : array();
		$unresolved = isset( $links['unresolved_internal_path_candidates'] ) && is_array( $links['unresolved_internal_path_candidates'] ) ? $links['unresolved_internal_path_candidates'] : array();
		foreach ( $leakage as $candidate ) {
			if ( is_array( $candidate ) && 'high' === ( $candidate['confidence'] ?? '' ) ) {
				++$state['high_confidence_leakage'];
			}
		}
		$state['unresolved_path_occurrences'] = count( $unresolved );
		$state['blockers']                    = 0 < $state['high_confidence_leakage'] ? 1 : 0;
		$state['warnings']                    = 0 < $state['unresolved_path_occurrences'] ? 1 : 0;
		$state['unique_navigation_issues']    = $state['blockers'] + $state['warnings'];

		return $state;
	}

	/**
	 * @param array<string, mixed> $evidence Evidence.
	 * @return array<string, mixed>
	 */
	private static function check( string $category, string $status, string $message, array $evidence ): array {
		return array(
			'category' => $category,
			'status'   => $status,
			'message'  => $message,
			'evidence' => $evidence,
		);
	}
}
