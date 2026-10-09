<?php
/**
 * Read-only field-gate preflight for Build / Finish acceptance.
 *
 * @package SeoGeoManager
 */

declare(strict_types=1);

namespace SeoGeo\Manager\Field;

use SeoGeo\Manager\Intelligence\ActionableDiagnostics;
use SeoGeo\Manager\Intelligence\BuildFinishReadiness;
use SeoGeo\Manager\Intelligence\MediaIntelligenceScanner;
use SeoGeo\Manager\Intelligence\SeoAuthorityScanner;
use SeoGeo\Manager\Intelligence\SiteIntelligenceScanner;
use SeoGeo\Manager\Intelligence\ThemeContractScanner;
use SeoGeo\Manager\Support\AuthoritativePermalinkPlanner;
use SeoGeo\Manager\Support\EnvironmentPolicy;
use SeoGeo\Manager\Support\LegacyPermalinkAuthority;
use SeoGeo\Manager\Support\PermalinkInspector;
use SeoGeo\Manager\Support\PermalinkRedirectRuntime;

final class FieldGatePreflight {
	private const SCHEMA_VERSION = 1;

	/**
	 * Build one bounded inspection-only field-gate report.
	 *
	 * @return array<string, mixed>
	 */
	public static function run( bool $include_rendered = false, string $legacy_base_url = '' ): array {
		$legacy_base_url = trim( esc_url_raw( $legacy_base_url ) );
		$site            = self::site_intelligence( $include_rendered );
		$inspection      = PermalinkInspector::preview();
		$runtime         = PermalinkRedirectRuntime::snapshot();
		$authority       = null;
		$plan            = null;
		$runtime_preview = null;

		if ( '' !== $legacy_base_url ) {
			$authority = LegacyPermalinkAuthority::preview( $legacy_base_url );
			$authority_fingerprint = isset( $authority['authority_fingerprint'] ) && is_string( $authority['authority_fingerprint'] )
				? $authority['authority_fingerprint']
				: '';

			if ( '' !== $authority_fingerprint ) {
				$plan = AuthoritativePermalinkPlanner::preview( $legacy_base_url, $authority_fingerprint );
				if ( true === ( $plan['safe_structure_candidate'] ?? false ) && true === ( $plan['requires_redirect_runtime'] ?? false ) ) {
					$runtime_preview = PermalinkRedirectRuntime::preview(
						isset( $plan['redirects'] ) && is_array( $plan['redirects'] ) ? $plan['redirects'] : array(),
						isset( $plan['plan_fingerprint'] ) && is_string( $plan['plan_fingerprint'] ) ? $plan['plan_fingerprint'] : '',
						isset( $plan['authoritative_structure'] ) && is_string( $plan['authoritative_structure'] ) ? $plan['authoritative_structure'] : ''
					);
				}
			}
		}

		$gate = self::gate_summary( $site, $legacy_base_url, $authority, $plan, $runtime_preview );

		return array(
			'schema_version'        => self::SCHEMA_VERSION,
			'mode'                  => 'build-finish-field-preflight',
			'read_only'             => true,
			'write_performed'       => false,
			'generated_at_gmt'      => gmdate( 'c' ),
			'manager_version'       => defined( 'SEO_GEO_MANAGER_VERSION' ) ? (string) SEO_GEO_MANAGER_VERSION : '',
			'environment'           => array_merge(
				EnvironmentPolicy::snapshot(),
				array(
					'home_url' => home_url( '/' ),
					'site_url' => site_url( '/' ),
				)
			),
			'options'               => array(
				'include_rendered' => $include_rendered,
				'legacy_base_url'  => $legacy_base_url,
			),
			'site_intelligence'     => $site,
			'permalinks'            => array(
				'inspection'       => $inspection,
				'active_runtime'   => $runtime,
				'legacy_authority' => $authority,
				'authoritative_plan' => $plan,
				'runtime_preview'  => $runtime_preview,
			),
			'field_gate'            => $gate,
			'policy'                => array(
				'preview_only'                    => true,
				'no_content_write'                => true,
				'no_permalink_write'              => true,
				'no_redirect_runtime_activation'  => true,
				'no_rewrite_flush'                => true,
				'no_operation_created'            => true,
				'write_requires_separate_endpoint'=> true,
			),
		);
	}

	/**
	 * Build the same bounded intelligence report used by the normal Site Intelligence endpoint.
	 *
	 * @return array<string, mixed>
	 */
	private static function site_intelligence( bool $include_rendered ): array {
		$site  = SiteIntelligenceScanner::scan( $include_rendered );
		$theme = ThemeContractScanner::scan();
		$seo   = SeoAuthorityScanner::scan();
		$media = MediaIntelligenceScanner::scan();

		$site['theme_contract']         = $theme;
		$site['seo_authority']          = $seo;
		$site['media_intelligence']     = $media;
		$site['actionable_diagnostics'] = ActionableDiagnostics::build( $site, $theme );
		$site['build_finish_readiness'] = BuildFinishReadiness::aggregate( $site, $theme, $seo, $media );

		return $site;
	}

	/**
	 * Convert detailed inspection evidence into an explicit field decision.
	 *
	 * @param array<string, mixed>      $site Site intelligence.
	 * @param array<string, mixed>|null $authority Historical authority evidence.
	 * @param array<string, mixed>|null $plan Authoritative permalink plan.
	 * @param array<string, mixed>|null $runtime_preview Optional atomic runtime preview.
	 * @return array<string, mixed>
	 */
	private static function gate_summary( array $site, string $legacy_base_url, ?array $authority, ?array $plan, ?array $runtime_preview ): array {
		$readiness = isset( $site['build_finish_readiness'] ) && is_array( $site['build_finish_readiness'] )
			? $site['build_finish_readiness']
			: array();
		$blockers  = isset( $readiness['blockers'] ) ? max( 0, (int) $readiness['blockers'] ) : 0;
		$warnings  = isset( $readiness['warnings'] ) ? max( 0, (int) $readiness['warnings'] ) : 0;
		$build_status = isset( $readiness['overall'] ) && is_string( $readiness['overall'] )
			? sanitize_key( $readiness['overall'] )
			: 'unknown';

		$authority_status = 'not-requested';
		if ( is_array( $authority ) ) {
			$authority_status = true === ( $authority['seo_authority_verified'] ?? false ) ? 'verified' : 'blocked';
		}

		$plan_status = 'not-requested';
		$write_mode  = 'none';
		$plan_ready  = false;
		if ( is_array( $plan ) ) {
			if ( true !== ( $plan['safe_structure_candidate'] ?? false ) ) {
				$plan_status = 'blocked';
			} elseif ( true === ( $plan['requires_redirect_runtime'] ?? false ) ) {
				$runtime_safe = is_array( $runtime_preview ) && true === ( $runtime_preview['safe_to_activate'] ?? false );
				$plan_status  = $runtime_safe ? 'atomic-ready' : 'atomic-runtime-blocked';
				$write_mode   = $runtime_safe ? 'atomic-301' : 'none';
				$plan_ready   = $runtime_safe;
			} elseif ( true === ( $plan['apply_available'] ?? false ) ) {
				$plan_status = 'direct-ready';
				$write_mode  = 'direct';
				$plan_ready  = true;
			} else {
				$plan_status = 'blocked';
			}
		}

		$status      = 'blocked';
		$next_action = 'resolve-build-finish-blockers';
		$eligible    = false;
		$reason      = 'Build / Finish still has blocking findings.';

		if ( 0 === $blockers ) {
			if ( '' === $legacy_base_url ) {
				$status      = 'needs-input';
				$next_action = 'provide-legacy-base-url';
				$reason      = 'Build / Finish has no blocker, but historical permalink authority has not been inspected yet.';
			} elseif ( 'verified' !== $authority_status ) {
				$status      = 'blocked';
				$next_action = 'resolve-legacy-authority-blockers';
				$reason      = 'Historical URLs are not yet a complete, unambiguous SEO authority.';
			} elseif ( ! $plan_ready ) {
				$status      = 'blocked';
				$next_action = 'resolve-authoritative-plan-blockers';
				$reason      = 'Historical authority is verified, but the guarded permalink plan is not ready for a separate Apply.';
			} else {
				$status      = 'ready-for-guarded-write';
				$next_action = 'atomic-301' === $write_mode ? 'preview-atomic-permalink-apply' : 'preview-direct-permalink-apply';
				$reason      = 'Read-only field evidence has no blocker for the next separately confirmed permalink operation.';
				$eligible    = true;
			}
		}

		return array(
			'status'               => $status,
			'next_action'          => $next_action,
			'reason'               => $reason,
			'guarded_write_eligible'=> $eligible,
			'guarded_write_mode'   => $write_mode,
			'build_finish'         => array(
				'status'   => $build_status,
				'blockers' => $blockers,
				'warnings' => $warnings,
			),
			'historical_authority' => array(
				'status' => $authority_status,
			),
			'permalink_plan'       => array(
				'status' => $plan_status,
			),
		);
	}
}
