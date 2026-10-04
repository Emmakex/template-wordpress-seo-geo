<?php
/**
 * Clean Home field-pilot readiness gate.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge\Reset;

use SeoGeo\MigrationBridge\Sandbox\SandboxGuard;
use WP_Post;

/**
 * Produces a read-only machine preflight before browser QA / cutover review.
 */
final class HomePilotReadiness {
	public const MODE = 'clean-home-field-pilot-readiness';

	/**
	 * Construct the readiness gate.
	 *
	 * @param RescueManifest     $manifest Rescue Manifest authority.
	 * @param CloneResetEngine   $reset    Clone reset authority.
	 * @param CleanHomeRebuilder $builder  Clean Home builder.
	 * @param NativeHomeHydrator $hydrator Native Home hydrator.
	 * @param HomeSeoHandoff     $handoff  Native SEO handoff authority.
	 */
	public function __construct(
		private RescueManifest $manifest,
		private CloneResetEngine $reset,
		private CleanHomeRebuilder $builder,
		private NativeHomeHydrator $hydrator,
		private HomeSeoHandoff $handoff
	) {
	}

	/**
	 * Build the field-pilot readiness report.
	 *
	 * @return array<string,mixed>
	 */
	public function report(): array {
		$blockers = array();
		$warnings = array();
		$checks   = array();

		$manifest         = $this->manifest->saved();
		$reset_report     = $this->reset->report();
		$home_plan        = $this->builder->plan();
		$hydration_plan   = $this->hydrator->plan();
		$draft_id         = (int) ( $home_plan['existing_draft'] ?? 0 );
		$draft            = 0 < $draft_id ? get_post( $draft_id ) : null;
		$seo_report       = 0 < $draft_id ? $this->handoff->report( $draft_id ) : null;
		$source_id        = $draft instanceof WP_Post ? (int) get_post_meta( $draft_id, CleanHomeRebuilder::SOURCE_ID_META, true ) : 0;
		$current_front_id = (int) get_option( 'page_on_front', 0 );

		$checks['sandbox_marker'] = SandboxGuard::enabled();
		if ( ! $checks['sandbox_marker'] ) {
			$blockers[] = 'sandbox-marker-required';
		}

		$checks['rescue_manifest'] = is_array( $manifest ) && '' !== (string) ( $manifest['manifest_sha256'] ?? '' );
		if ( ! $checks['rescue_manifest'] ) {
			$blockers[] = 'rescue-manifest-required';
		}

		$checks['reset_completed'] = is_array( $reset_report )
			&& 'completed' === (string) ( $reset_report['status'] ?? '' )
			&& true === ( $reset_report['safety']['manifest_unchanged'] ?? false )
			&& true === ( $reset_report['safety']['content_unchanged'] ?? false )
			&& true === ( $reset_report['safety']['target_theme_active'] ?? false );
		if ( ! $checks['reset_completed'] ) {
			$blockers[] = 'clone-reset-completed-report-required';
		}

		$checks['theme_active'] = CloneResetEngine::TARGET_THEME === get_stylesheet();
		if ( ! $checks['theme_active'] ) {
			$blockers[] = 'seo-geo-theme-must-be-active';
		}

		$checks['home_plan_ready'] = true === ( $home_plan['ready'] ?? false );
		if ( ! $checks['home_plan_ready'] ) {
			$blockers[] = 'clean-home-plan-not-ready';
		}

		$checks['draft_ready'] = $draft instanceof WP_Post
			&& 'page' === $draft->post_type
			&& 'draft' === $draft->post_status
			&& NativeHomeHydrator::CONTENT_STATE === (string) get_post_meta( $draft_id, CleanHomeRebuilder::CONTENT_STATE_META, true );
		if ( ! $checks['draft_ready'] ) {
			$blockers[] = 'hydrated-clean-home-draft-required';
		}

		$checks['hydration_ready'] = true === ( $hydration_plan['ready'] ?? false )
			&& NativeHomeHydrator::CONTENT_STATE === (string) ( $hydration_plan['state'] ?? '' );
		if ( ! $checks['hydration_ready'] ) {
			$blockers[] = 'native-home-hydration-must-be-clean';
		}

		$checks['source_unchanged'] = $this->source_matches_manifest( $manifest, $source_id );
		if ( ! $checks['source_unchanged'] ) {
			$blockers[] = 'rescued-home-source-drift';
		}

		$checks['front_page_unchanged'] = 0 < $source_id && $current_front_id === $source_id;
		if ( ! $checks['front_page_unchanged'] ) {
			$blockers[] = 'front-page-must-still-point-to-source';
		}

		$checks['seo_handoff_applied'] = is_array( $seo_report )
			&& in_array( (string) ( $seo_report['status'] ?? '' ), array( 'applied', 'existing' ), true )
			&& true === ( $seo_report['source_unchanged'] ?? false )
			&& true === ( $seo_report['front_page_unchanged'] ?? false )
			&& true === ( $seo_report['draft_only'] ?? false );
		if ( ! $checks['seo_handoff_applied'] ) {
			$blockers[] = 'native-seo-handoff-report-required';
		}

		$checks['seo_cutover_ready'] = true === ( $seo_report['cutover_seo_ready'] ?? false );
		if ( ! $checks['seo_cutover_ready'] ) {
			$blockers[] = 'seo-review-required-before-browser-qa';
		}

		$content        = $draft instanceof WP_Post ? (string) $draft->post_content : '';
		$legacy_markers = $this->legacy_markers( $content );
		$checks['legacy_builder_free'] = array() === $legacy_markers;
		if ( ! $checks['legacy_builder_free'] ) {
			$blockers[] = 'legacy-builder-markup-detected';
		}

		$placeholder_markers = $this->placeholder_markers( $content );
		$checks['preset_placeholders_removed'] = array() === $placeholder_markers;
		if ( ! $checks['preset_placeholders_removed'] ) {
			$blockers[] = 'preset-placeholder-content-detected';
		}

		$verified_groups = is_array( $hydration_plan['verified_groups'] ?? null ) ? $hydration_plan['verified_groups'] : array();
		$unverified_enabled = array_values(
			array_keys(
				array_filter(
					$verified_groups,
					static fn( mixed $value ): bool => true === $value
				)
			)
		);
		$checks['evidence_groups_reviewed'] = array() === $unverified_enabled;
		if ( array() !== $unverified_enabled ) {
			$warnings[] = 'verified-evidence-groups-require-field-review';
		}

		if ( '' === trim( wp_strip_all_tags( $content ) ) ) {
			$blockers[] = 'hydrated-home-content-empty';
		}

		$blockers = array_values( array_unique( $blockers ) );
		$warnings = array_values( array_unique( $warnings ) );
		sort( $blockers );
		sort( $warnings );

		$material = array(
			'schema_version'        => 1,
			'mode'                  => self::MODE,
			'draft_id'              => $draft_id,
			'source_id'             => $source_id,
			'front_page_id'         => $current_front_id,
			'checks'                => $checks,
			'legacy_markers'        => $legacy_markers,
			'placeholder_markers'   => $placeholder_markers,
			'seo_review_items'      => is_array( $seo_report['review_items'] ?? null ) ? $seo_report['review_items'] : array(),
			'verified_groups'       => $verified_groups,
			'manual_browser_checks' => array(
				'visual-layout',
				'responsive-behavior',
				'accessibility',
				'seo-geo-rendered-output',
				'performance',
			),
		);

		return array_merge(
			$material,
			array(
				'ready_for_browser_qa' => array() === $blockers,
				'blockers'             => $blockers,
				'warnings'             => $warnings,
				'report_sha256'        => hash(
					'sha256',
					(string) wp_json_encode( $material, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE )
				),
			)
		);
	}

	/**
	 * Confirm the rescued source still matches the Manifest fingerprint.
	 *
	 * @param array<string,mixed>|null $manifest  Saved Rescue Manifest.
	 * @param int                      $source_id Source page ID.
	 */
	private function source_matches_manifest( ?array $manifest, int $source_id ): bool {
		if ( ! is_array( $manifest ) || 0 >= $source_id ) {
			return false;
		}

		foreach ( is_array( $manifest['resources'] ?? null ) ? $manifest['resources'] : array() as $resource ) {
			if ( ! is_array( $resource ) || $source_id !== (int) ( $resource['id'] ?? 0 ) ) {
				continue;
			}

			$expected = (string) ( $resource['content_sha256'] ?? '' );
			$content  = get_post_field( 'post_content', $source_id );

			return is_string( $content )
				&& '' !== $expected
				&& hash_equals( $expected, hash( 'sha256', $content ) );
		}

		return false;
	}

	/**
	 * Detect presentation-runtime debris that must not enter the clean Home.
	 *
	 * @param string $content Hydrated block content.
	 * @return list<string>
	 */
	private function legacy_markers( string $content ): array {
		$patterns = array(
			'et_pb_'         => '/\bet_pb_[a-z0-9_-]+/i',
			'divi-shortcode' => '/\[\/?et_pb_[^\]]*\]/i',
			'elementor'      => '/(?:data-elementor-|\belementor-[a-z0-9_-]+)/i',
			'visual-composer'=> '/(?:\[\/?vc_[^\]]*\]|\bwpb_[a-z0-9_-]+)/i',
			'fusion-builder' => '/(?:\[\/?fusion_[^\]]*\]|\bfusion-builder\b)/i',
			'beaver-builder' => '/\bfl-builder-[a-z0-9_-]+/i',
		);
		$found = array();

		foreach ( $patterns as $label => $pattern ) {
			if ( 1 === preg_match( $pattern, $content ) ) {
				$found[] = $label;
			}
		}

		return $found;
	}

	/**
	 * Detect known untouched preset placeholder copy.
	 *
	 * @param string $content Hydrated block content.
	 * @return list<string>
	 */
	private function placeholder_markers( string $content ): array {
		$needles = array(
			'State the next useful step clearly',
			'Add the minimum supporting context a visitor needs before taking action.',
			'Take the next step',
			'Write a clear, specific value proposition',
			'Add a short category or trust signal',
			'Primary action',
			'Secondary action',
		);
		$found = array();

		foreach ( $needles as $needle ) {
			if ( str_contains( $content, $needle ) ) {
				$found[] = $needle;
			}
		}

		return $found;
	}
}
