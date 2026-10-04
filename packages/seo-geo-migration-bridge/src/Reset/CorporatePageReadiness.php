<?php
/**
 * Clean Corporate inner-page readiness gate.
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
final class CorporatePageReadiness {
	public const MODE = 'clean-corporate-page-readiness';

	/**
	 * Construct the readiness gate.
	 */
	public function __construct(
		private RescueManifest $manifest,
		private CloneResetEngine $reset,
		private CleanCorporatePageRebuilder $builder,
		private NativeCorporatePageHydrator $hydrator,
		private CorporatePageSeoHandoff $handoff
	) {
	}

	/**
	 * Build one page readiness report.
	 *
	 * @param string $page_key Corporate page key.
	 * @return array<string,mixed>
	 */
	public function report( string $page_key ): array {
		$page_key = sanitize_key( $page_key );
		$blockers = array();
		$warnings = array();
		$checks   = array();

		$manifest       = $this->manifest->saved();
		$reset_report   = $this->reset->report();
		$page_plan      = $this->builder->plan( $page_key );
		$hydration_plan = $this->hydrator->plan( $page_key );
		$draft_id       = (int) ( $page_plan['existing_draft'] ?? 0 );
		$draft          = 0 < $draft_id ? get_post( $draft_id ) : null;
		$seo_report     = 0 < $draft_id ? $this->handoff->report( $draft_id ) : null;
		$source_id      = $draft instanceof WP_Post ? (int) get_post_meta( $draft_id, CleanCorporatePageRebuilder::SOURCE_ID_META, true ) : 0;
		$source_path    = $draft instanceof WP_Post ? (string) get_post_meta( $draft_id, CleanCorporatePageRebuilder::SOURCE_PATH_META, true ) : '';

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

		$expected_plugins = is_array( $reset_report['after']['active_plugins'] ?? null )
			? array_values( array_map( 'strval', $reset_report['after']['active_plugins'] ) )
			: array();
		$current_plugins = array_values( array_map( 'strval', get_option( 'active_plugins', array() ) ) );
		sort( $expected_plugins );
		sort( $current_plugins );
		$checks['plugin_set_unchanged'] = $expected_plugins === $current_plugins;
		if ( ! $checks['plugin_set_unchanged'] ) {
			$blockers[] = 'plugin-set-drift-since-reset';
		}

		$checks['page_plan_ready'] = true === ( $page_plan['ready'] ?? false );
		if ( ! $checks['page_plan_ready'] ) {
			$blockers[] = 'clean-page-plan-not-ready';
		}

		$checks['draft_ready'] = $draft instanceof WP_Post
			&& 'page' === $draft->post_type
			&& 'draft' === $draft->post_status
			&& $page_key === (string) get_post_meta( $draft_id, CleanCorporatePageRebuilder::PAGE_KEY_META, true )
			&& NativeCorporatePageHydrator::CONTENT_STATE === (string) get_post_meta( $draft_id, CleanCorporatePageRebuilder::CONTENT_STATE_META, true );
		if ( ! $checks['draft_ready'] ) {
			$blockers[] = 'hydrated-clean-page-draft-required';
		}

		$checks['hydration_ready'] = true === ( $hydration_plan['ready'] ?? false )
			&& NativeCorporatePageHydrator::CONTENT_STATE === (string) ( $hydration_plan['state'] ?? '' );
		if ( ! $checks['hydration_ready'] ) {
			$blockers[] = 'native-page-hydration-must-be-clean';
		}

		$checks['source_unchanged'] = $this->source_matches_manifest( $manifest, $source_id );
		if ( ! $checks['source_unchanged'] ) {
			$blockers[] = 'rescued-page-source-drift';
		}

		$checks['source_path_unchanged'] = $this->source_path_matches( $source_id, $source_path );
		if ( ! $checks['source_path_unchanged'] ) {
			$blockers[] = 'rescued-page-path-drift';
		}

		$checks['source_still_published'] = 0 < $source_id && 'publish' === get_post_status( $source_id );
		if ( ! $checks['source_still_published'] ) {
			$blockers[] = 'rescued-page-source-must-remain-published';
		}

		$checks['seo_handoff_applied'] = is_array( $seo_report )
			&& $page_key === (string) ( $seo_report['page_key'] ?? '' )
			&& in_array( (string) ( $seo_report['status'] ?? '' ), array( 'applied', 'existing' ), true )
			&& true === ( $seo_report['source_unchanged'] ?? false )
			&& true === ( $seo_report['source_path_unchanged'] ?? false )
			&& true === ( $seo_report['draft_only'] ?? false );
		if ( ! $checks['seo_handoff_applied'] ) {
			$blockers[] = 'native-seo-handoff-report-required';
		}

		$checks['seo_cutover_ready'] = true === ( $seo_report['cutover_seo_ready'] ?? false );
		if ( ! $checks['seo_cutover_ready'] ) {
			$blockers[] = 'seo-review-required-before-browser-qa';
		}

		$content                       = $draft instanceof WP_Post ? (string) $draft->post_content : '';
		$legacy_markers                = $this->legacy_markers( $content );
		$checks['legacy_builder_free'] = array() === $legacy_markers;
		if ( ! $checks['legacy_builder_free'] ) {
			$blockers[] = 'legacy-builder-markup-detected';
		}

		$placeholder_markers                 = $this->placeholder_markers( $content );
		$checks['preset_placeholders_removed'] = array() === $placeholder_markers;
		if ( ! $checks['preset_placeholders_removed'] ) {
			$blockers[] = 'preset-placeholder-content-detected';
		}

		$verified_groups = is_array( $hydration_plan['verified_groups'] ?? null ) ? $hydration_plan['verified_groups'] : array();
		$checks['evidence_groups_reviewed'] = ! in_array( true, array_values( $verified_groups ), true );
		if ( ! $checks['evidence_groups_reviewed'] ) {
			$warnings[] = 'verified-evidence-groups-require-field-review';
		}

		if ( '' === trim( wp_strip_all_tags( $content ) ) ) {
			$blockers[] = 'hydrated-page-content-empty';
		}

		$blockers = array_values( array_unique( $blockers ) );
		$warnings = array_values( array_unique( $warnings ) );
		sort( $blockers );
		sort( $warnings );

		$material = array(
			'schema_version'        => 1,
			'mode'                  => self::MODE,
			'page_key'              => $page_key,
			'draft_id'              => $draft_id,
			'source_id'             => $source_id,
			'source_path'           => $source_path,
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

	/** Confirm rescued source still matches the Manifest fingerprint. */
	private function source_matches_manifest( ?array $manifest, int $source_id ): bool {
		if ( ! is_array( $manifest ) || 0 >= $source_id ) {
			return false;
		}
		foreach ( is_array( $manifest['resources'] ?? null ) ? $manifest['resources'] : array() as $resource ) {
			if ( ! is_array( $resource ) || (int) ( $resource['id'] ?? 0 ) !== $source_id ) {
				continue;
			}
			$expected = (string) ( $resource['content_sha256'] ?? '' );
			$content  = get_post_field( 'post_content', $source_id );

			return is_string( $content ) && '' !== $expected && hash_equals( $expected, hash( 'sha256', $content ) );
		}

		return false;
	}

	/** Confirm source permalink still matches the rescued path. */
	private function source_path_matches( int $source_id, string $expected_path ): bool {
		if ( 0 >= $source_id || '' === $expected_path ) {
			return false;
		}
		$path = wp_parse_url( get_permalink( $source_id ), PHP_URL_PATH );
		if ( ! is_string( $path ) ) {
			return false;
		}

		return $this->normalize_path( $path ) === $this->normalize_path( $expected_path );
	}

	/** Normalize one URL path. */
	private function normalize_path( string $path ): string {
		$path = '/' . trim( $path, '/' );

		return '/' === $path ? '/' : $path . '/';
	}

	/** Detect legacy presentation-runtime debris. */
	private function legacy_markers( string $content ): array {
		$patterns = array(
			'et_pb_'          => '/\bet_pb_[a-z0-9_-]+/i',
			'divi-shortcode'  => '/\[\/?et_pb_[^\]]*\]/i',
			'elementor'       => '/(?:data-elementor-|\belementor-[a-z0-9_-]+)/i',
			'visual-composer' => '/(?:\[\/?vc_[^\]]*\]|\bwpb_[a-z0-9_-]+)/i',
			'fusion-builder'  => '/(?:\[\/?fusion_[^\]]*\]|\bfusion-builder\b)/i',
			'beaver-builder'  => '/\bfl-builder-[a-z0-9_-]+/i',
		);
		$found = array();
		foreach ( $patterns as $label => $pattern ) {
			if ( 1 === preg_match( $pattern, $content ) ) {
				$found[] = $label;
			}
		}

		return $found;
	}

	/** Detect known untouched preset placeholder copy. */
	private function placeholder_markers( string $content ): array {
		$needles = array(
			'Replace this with a real customer question',
			'Add a second high-value question',
			'Add a question that helps the visitor decide',
			'Present a real project with verifiable context',
			'Presenta un proyecto real con contexto verificable',
			'Add the author name',
			'Add the public contact email address.',
			'Add the public phone number and, if relevant, service hours.',
			'Add a real address or describe the geographic area served.',
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
