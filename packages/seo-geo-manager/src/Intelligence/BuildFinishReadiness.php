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

			$incomplete = isset( $theme_summary['incomplete_model_pages'] ) ? (int) $theme_summary['incomplete_model_pages'] : 0;
			$checks[]   = self::check(
				'content',
				0 === $incomplete ? 'pass' : 'blocker',
				0 === $incomplete ? 'Required structured content slots are present.' : 'Required structured content slots are missing.',
				array(
					'incomplete_model_pages' => $incomplete,
					'missing_required_slots' => isset( $theme_summary['missing_required_slots'] ) ? (int) $theme_summary['missing_required_slots'] : 0,
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

		$links    = isset( $site['links'] ) && is_array( $site['links'] ) ? $site['links'] : array();
		$leakage  = isset( $links['environment_leakage_candidates'] ) && is_array( $links['environment_leakage_candidates'] ) ? count( $links['environment_leakage_candidates'] ) : 0;
		$unresolved = isset( $links['unresolved_internal_path_candidates'] ) && is_array( $links['unresolved_internal_path_candidates'] ) ? count( $links['unresolved_internal_path_candidates'] ) : 0;
		$checks[] = self::check(
			'navigation',
			0 < $leakage ? 'blocker' : ( 0 < $unresolved ? 'warning' : 'pass' ),
			0 < $leakage ? 'Environment/domain leakage candidates remain.' : ( 0 < $unresolved ? 'Some internal paths could not be resolved to known content.' : 'No bounded navigation blockers were detected.' ),
			array(
				'environment_leakage_candidates'      => $leakage,
				'unresolved_internal_path_candidates' => $unresolved,
			)
		);

		$seo_state = isset( $seo['state'] ) && is_string( $seo['state'] ) ? $seo['state'] : 'unresolved';
		$seo_level = 'pass';
		if ( 'multiple-provider-conflict-candidate' === $seo_state ) {
			$seo_level = 'blocker';
		} elseif ( false === strpos( $seo_state, 'candidate' ) ) {
			$seo_level = 'warning';
		} elseif ( false === ( $seo['safe_to_write_seo_metadata'] ?? false ) ) {
			$seo_level = 'warning';
		}
		$checks[] = self::check(
			'seo-authority',
			$seo_level,
			'SEO output authority state: ' . $seo_state . '.',
			array( 'state' => $seo_state )
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

		$rendered = isset( $site['rendered_scan'] ) && is_array( $site['rendered_scan'] ) ? $site['rendered_scan'] : array();
		$rendered_enabled = true === ( $rendered['enabled'] ?? false );
		$rendered_failures = isset( $rendered['failures'] ) && is_array( $rendered['failures'] ) ? count( $rendered['failures'] ) : 0;
		$checks[] = self::check(
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
			'warning',
			'M2 preview/apply/verify/rollback is not complete yet; Manager remains read-only for this readiness report.',
			array( 'm2_ready' => false )
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
