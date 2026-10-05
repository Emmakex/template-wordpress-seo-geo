<?php
/**
 * Whole-site Corporate reset-first readiness gate.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge\Reset;

use WP_Post;

/**
 * Aggregates accepted page pipelines before global browser QA begins.
 */
final class CorporateSiteReadiness {
	/**
	 * Corporate inner pages required by the whole-site gate.
	 *
	 * @var list<string>
	 */
	private const INNER_PAGE_KEYS = array( 'services', 'work', 'about', 'contact' );

	/**
	 * Construct the whole-site readiness gate.
	 *
	 * @param HomePilotReadiness            $home     Home readiness authority.
	 * @param CorporatePageReadiness        $pages    Corporate inner-page readiness authority.
	 * @param CorporateInsightsManager      $insights Corporate Insights manager.
	 * @param CleanCorporatePageRebuilder   $builder  Clean Corporate page builder.
	 */
	public function __construct(
		private HomePilotReadiness $home,
		private CorporatePageReadiness $pages,
		private CorporateInsightsManager $insights,
		private CleanCorporatePageRebuilder $builder
	) {
	}

	/**
	 * Build one deterministic whole-site preflight report.
	 *
	 * @return array<string,mixed>
	 */
	public function report(): array {
		$blockers = array();
		$warnings = array();
		$reports  = array();

		$reports['home'] = $this->home->report();
		if ( true !== ( $reports['home']['ready_for_browser_qa'] ?? false ) ) {
			$blockers[] = 'home-not-ready';
		}

		$source_ids   = array();
		$source_paths = array();
		$front_id     = (int) get_option( 'page_on_front', 0 );
		if ( 0 < $front_id ) {
			$source_ids['home'] = $front_id;
		}

		foreach ( self::INNER_PAGE_KEYS as $page_key ) {
			$report               = $this->pages->report( $page_key );
			$reports[ $page_key ] = $report;
			if ( true !== ( $report['ready_for_browser_qa'] ?? false ) ) {
				$blockers[] = $page_key . '-not-ready';
			}

			$source_id   = (int) ( $report['source_id'] ?? 0 );
			$source_path = (string) ( $report['source_path'] ?? '' );
			if ( 0 < $source_id ) {
				$source_ids[ $page_key ] = $source_id;
			}
			if ( '' !== $source_path ) {
				$source_paths[ $page_key ] = $source_path;
			}

			$plan     = $this->builder->plan( $page_key );
			$draft_id = (int) ( $plan['existing_draft'] ?? 0 );
			$draft    = 0 < $draft_id ? get_post( $draft_id ) : null;
			if ( $draft instanceof WP_Post ) {
				$content = (string) $draft->post_content;
				if ( 1 === preg_match( '/href=(["\'])\#\1/i', $content ) ) {
					$blockers[] = $page_key . '-placeholder-link-detected';
				}
				if ( 1 === preg_match( '/href=(["\'])javascript:/i', $content ) ) {
					$blockers[] = $page_key . '-unsafe-link-detected';
				}
			}

			foreach ( is_array( $report['warnings'] ?? null ) ? $report['warnings'] : array() as $warning ) {
				if ( is_string( $warning ) ) {
					$warnings[] = $page_key . ':' . $warning;
				}
			}
		}

		$reports['insights'] = $this->insights->readiness();
		if ( true !== ( $reports['insights']['ready_for_browser_qa'] ?? false ) ) {
			$blockers[] = 'insights-not-ready';
		}
		$insights_id = (int) ( $reports['insights']['source_id'] ?? 0 );
		if ( 0 < $insights_id ) {
			$source_ids['insights'] = $insights_id;
			$path                   = wp_parse_url( get_permalink( $insights_id ), PHP_URL_PATH );
			if ( is_string( $path ) && '' !== $path ) {
				$source_paths['insights'] = $path;
			}
		}

		$duplicates = $this->duplicate_values( $source_ids );
		if ( array() !== $duplicates ) {
			$blockers[] = 'duplicate-page-source-binding';
		}
		$duplicate_paths = $this->duplicate_values( $source_paths );
		if ( array() !== $duplicate_paths ) {
			$blockers[] = 'duplicate-public-path-binding';
		}

		$posts_page = (int) get_option( 'page_for_posts', 0 );
		if ( 0 >= $front_id || 0 >= $posts_page || $posts_page === $front_id ) {
			$blockers[] = 'front-and-insights-authority-invalid';
		}

		if ( 'page' !== (string) get_option( 'show_on_front', 'posts' ) ) {
			$blockers[] = 'static-front-page-mode-required';
		}

		$blockers = array_values( array_unique( $blockers ) );
		$warnings = array_values( array_unique( $warnings ) );
		sort( $blockers );
		sort( $warnings );

		$manual_matrix = array();
		foreach ( array( 'home', 'services', 'work', 'about', 'contact', 'insights' ) as $page_key ) {
			$manual_matrix[ $page_key ] = array(
				'visual-layout',
				'responsive-behavior',
				'accessibility',
				'seo-geo-rendered-output',
				'performance',
				'internal-external-links',
				'required-media-and-functions',
			);
		}

		$material = array(
			'schema_version'        => 1,
			'mode'                  => 'corporate-clean-site-readiness',
			'page_reports'          => $reports,
			'source_bindings'       => $source_ids,
			'public_paths'          => $source_paths,
			'duplicate_sources'     => $duplicates,
			'duplicate_paths'       => $duplicate_paths,
			'front_page_id'         => $front_id,
			'posts_page_id'         => $posts_page,
			'manual_browser_matrix' => $manual_matrix,
		);

		return array_merge(
			$material,
			array(
				'ready_for_global_browser_qa' => array() === $blockers,
				'blockers'                    => $blockers,
				'warnings'                    => $warnings,
				'report_sha256'               => hash(
					'sha256',
					(string) wp_json_encode( $material, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE )
				),
			)
		);
	}

	/**
	 * Find duplicated IDs/paths while preserving their owning page keys.
	 *
	 * @param array<string,int|string> $values Page-keyed values.
	 * @return array<string,list<string>>
	 */
	private function duplicate_values( array $values ): array {
		$owners = array();
		foreach ( $values as $page_key => $value ) {
			$key              = (string) $value;
			$owners[ $key ] ??= array();
			$owners[ $key ][] = $page_key;
		}

		$duplicates = array();
		foreach ( $owners as $value => $page_keys ) {
			if ( 1 < count( $page_keys ) ) {
				$duplicates[ $value ] = array_values( $page_keys );
			}
		}
		ksort( $duplicates );

		return $duplicates;
	}
}
