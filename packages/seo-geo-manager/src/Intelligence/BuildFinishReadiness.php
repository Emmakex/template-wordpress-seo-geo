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

		$links      = isset( $site['links'] ) && is_array( $site['links'] ) ? $site['links'] : array();
		$leakage    = isset( $links['environment_leakage_candidates'] ) && is_array( $links['environment_leakage_candidates'] ) ? count( $links['environment_leakage_candidates'] ) : 0;
		$unresolved = isset( $links['unresolved_internal_path_candidates'] ) && is_array( $links['unresolved_internal_path_candidates'] ) ? count( $links['unresolved_internal_path_candidates'] ) : 0;
		$checks[]   = self::check(
			'navigation',
			0 < $leakage ? 'blocker' : ( 0 < $unresolved ? 'warning' : 'pass' ),
			0 < $leakage ? 'Environment/domain leakage candidates remain.' : ( 0 < $unresolved ? 'Some internal paths could not be resolved to known content.' : 'No bounded navigation blockers were detected.' ),
			array(
				'environment_leakage_candidates'      => $leakage,
				'unresolved_internal_path_candidates' => $unresolved,
			)
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
				'state'                  => $seo_state,
				'authority_resolved'     => $seo_resolved,
				'write_adapter_ready'    => $seo_writable,
				'authority'              => $seo['authority'] ?? null,
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
