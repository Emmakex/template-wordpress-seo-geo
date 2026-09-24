<?php
/**
 * Privacy-bounded sandbox handoff manifest.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge\Sandbox;

use SeoGeo\MigrationBridge\BaselineSnapshotStore;
use SeoGeo\MigrationBridge\DependencyGraphBuilder;
use SeoGeo\MigrationBridge\Review\DependencyReviewStore;
use SeoGeo\MigrationBridge\SiteAnalyzer;

/**
 * Builds one portable planning manifest without exporting private content.
 */
final class SandboxHandoffManifest {
	/**
	 * Build the bounded handoff manifest from live read-only evidence.
	 *
	 * @return array<string,mixed>
	 */
	public function build(): array {
		$analysis = ( new SiteAnalyzer() )->analyze();
		$baseline = ( new BaselineSnapshotStore() )->latest();

		$baseline_snapshot = is_array( $baseline['snapshot'] ?? null ) ? $baseline['snapshot'] : null;
		$graph             = ( new DependencyGraphBuilder() )->build( $analysis, $baseline_snapshot );

		$site               = is_array( $analysis['site'] ?? null ) ? $analysis['site'] : array();
		$themes             = is_array( $analysis['themes'] ?? null ) ? $analysis['themes'] : array();
		$components         = is_array( $graph['components'] ?? null ) ? $graph['components'] : array();
		$summary            = is_array( $graph['summary'] ?? null ) ? $graph['summary'] : array();
		$review_store       = new DependencyReviewStore();
		$bounded_components = $this->bounded_components( $components, $review_store );

		return array(
			'schema_version' => 2,
			'mode'           => 'seo-geo-sandbox-handoff',
			'generated_at'   => gmdate( DATE_ATOM ),
			'source'         => array(
				'home_url'          => is_string( $site['home_url'] ?? null ) ? $site['home_url'] : home_url( '/' ),
				'locale'            => is_string( $site['locale'] ?? null ) ? $site['locale'] : get_locale(),
				'wordpress_version' => is_string( $site['wordpress_version'] ?? null ) ? $site['wordpress_version'] : '',
				'php_version'       => is_string( $site['php_version'] ?? null ) ? $site['php_version'] : PHP_VERSION,
				'active_theme'      => $this->active_theme( $themes ),
			),
			'baseline'       => $this->baseline_reference( $baseline ),
			'dependencies'   => array(
				'summary'    => $this->normalized_summary( $summary ),
				'components' => $bounded_components,
				'review'     => $this->review_summary( $bounded_components ),
			),
			'target'         => array(
				'theme_stylesheet' => SandboxMigrationLab::DESTINATION_THEME,
				'release_version'  => '0.1.0',
			),
			'sandbox'        => array(
				'accepted_modes'                         => array( 'origin', 'subdirectory' ),
				'default_mode'                           => 'origin',
				'mode_marker'                            => SandboxGuard::MODE_MARKER,
				'origin_mode_requires_distinct_origin'   => true,
				'subdirectory_mode_value'                => 'subdirectory',
				'subdirectory_requires_same_origin'      => true,
				'subdirectory_requires_distinct_path'    => true,
				'subdirectory_requires_non_root_path'    => true,
				'subdirectory_requires_storage_isolation' => true,
				'subdirectory_storage_marker'            => SandboxGuard::STORAGE_ISOLATED_MARKER,
				'requires_marker'                        => SandboxGuard::MARKER,
				'requires_marker_value'                  => true,
				'requires_search_visibility_off'         => true,
				'requires_outbound_safety'               => true,
				'requires_outbound_marker'               => SandboxGuard::OUTBOUND_SAFE_MARKER,
				'requires_fresh_backups'                 => true,
				'requires_backup_marker'                 => SandboxGuard::BACKUPS_READY_MARKER,
				'production_mutation_allowed'            => false,
			),
			'safety'         => array(
				'read_only_generation'               => true,
				'post_bodies_exported'               => false,
				'builder_payloads_exported'          => false,
				'credentials_exported'               => false,
				'option_values_exported'             => false,
				'raw_database_exported'              => false,
				'raw_uploads_exported'               => false,
				'customer_data_exported'             => false,
				'baseline_body_content'              => false,
				'review_decisions_execute_mutations' => false,
			),
		);
	}

	/**
	 * Return the active theme as bounded identity metadata.
	 *
	 * @param list<array<string,mixed>> $themes Theme inventory.
	 * @return array{stylesheet:string,name:string,version:string}|null
	 */
	private function active_theme( array $themes ): ?array {
		foreach ( $themes as $theme ) {
			if ( 'active' !== ( $theme['status'] ?? null ) ) {
				continue;
			}

			return array(
				'stylesheet' => is_string( $theme['stylesheet'] ?? null ) ? $theme['stylesheet'] : '',
				'name'       => is_string( $theme['name'] ?? null ) ? $theme['name'] : '',
				'version'    => is_string( $theme['version'] ?? null ) ? $theme['version'] : '',
			);
		}

		return null;
	}

	/**
	 * Return a bounded reference to the accepted public baseline.
	 *
	 * @param array<string,mixed>|null $baseline Baseline envelope.
	 * @return array<string,mixed>
	 */
	private function baseline_reference( ?array $baseline ): array {
		$snapshot = is_array( $baseline['snapshot'] ?? null ) ? $baseline['snapshot'] : array();
		$crawl    = is_array( $snapshot['crawl'] ?? null ) ? $snapshot['crawl'] : array();

		return array(
			'available'        => is_array( $baseline ),
			'id'               => is_string( $baseline['id'] ?? null ) ? $baseline['id'] : null,
			'sha256'           => is_string( $baseline['sha256'] ?? null ) ? $baseline['sha256'] : null,
			'kind'             => is_string( $snapshot['kind'] ?? null ) ? $snapshot['kind'] : null,
			'generated_at'     => is_string( $snapshot['generated_at'] ?? null ) ? $snapshot['generated_at'] : null,
			'discovered'       => isset( $crawl['discovered'] ) ? max( 0, (int) $crawl['discovered'] ) : 0,
			'captured'         => isset( $crawl['captured'] ) ? max( 0, (int) $crawl['captured'] ) : 0,
			'request_failures' => isset( $crawl['request_failures'] ) ? max( 0, (int) $crawl['request_failures'] ) : 0,
			'truncated'        => true === ( $crawl['truncated'] ?? false ),
		);
	}

	/**
	 * Return classification counts with stable keys.
	 *
	 * @param array<string,mixed> $summary Dependency summary.
	 * @return array<string,int>
	 */
	private function normalized_summary( array $summary ): array {
		$normalized = array();

		foreach ( array( 'KEEP', 'REPLACE', 'MIGRATE', 'OPTIONAL', 'REMOVE-CANDIDATE', 'UNKNOWN' ) as $classification ) {
			$normalized[ $classification ] = isset( $summary[ $classification ] )
				? max( 0, (int) $summary[ $classification ] )
				: 0;
		}

		return $normalized;
	}

	/**
	 * Summarize bounded operator review progress for UNKNOWN dependencies.
	 *
	 * @param list<array<string,mixed>> $components Bounded dependency rows.
	 * @return array{reviewed_unknown:int,unreviewed_unknown:int,complete:bool}
	 */
	private function review_summary( array $components ): array {
		$reviewed   = 0;
		$unreviewed = 0;

		foreach ( $components as $component ) {
			if ( 'UNKNOWN' !== ( $component['classification'] ?? null ) ) {
				continue;
			}

			if ( true === ( $component['reviewed'] ?? false ) ) {
				++$reviewed;
			} else {
				++$unreviewed;
			}
		}

		return array(
			'reviewed_unknown'   => $reviewed,
			'unreviewed_unknown' => $unreviewed,
			'complete'           => 0 === $unreviewed,
		);
	}

	/**
	 * Keep only safe component planning fields.
	 *
	 * @param list<array<string,mixed>> $components   Dependency graph components.
	 * @param DependencyReviewStore     $review_store Planning-only review store.
	 * @return list<array<string,mixed>>
	 */
	private function bounded_components( array $components, DependencyReviewStore $review_store ): array {
		$rows = array();

		foreach ( $components as $component ) {
			$component_id   = is_string( $component['component_id'] ?? null ) ? $component['component_id'] : '';
			$classification = is_string( $component['classification'] ?? null ) ? $component['classification'] : 'UNKNOWN';
			$review         = 'UNKNOWN' === $classification && '' !== $component_id
				? $review_store->decision_for( $component_id )
				: null;

			$rows[] = array(
				'component_id'    => $component_id,
				'type'            => is_string( $component['type'] ?? null ) ? $component['type'] : '',
				'id'              => is_string( $component['id'] ?? null ) ? $component['id'] : '',
				'category'        => is_string( $component['category'] ?? null ) ? $component['category'] : null,
				'active'          => true === ( $component['active'] ?? false ),
				'classification'  => $classification,
				'reason'          => is_string( $component['reason'] ?? null ) ? $component['reason'] : 'insufficient-evidence',
				'resource_count'  => isset( $component['resource_count'] ) ? max( 0, (int) $component['resource_count'] ) : null,
				'manual_review'   => true === ( $component['manual_review'] ?? false ),
				'reviewed'        => is_array( $review ),
				'review_decision' => is_array( $review ) ? $review['classification'] : null,
				'review_reason'   => is_array( $review ) ? $review['reason'] : null,
				'reviewed_at'     => is_array( $review ) ? $review['reviewed_at'] : null,
			);
		}

		usort(
			$rows,
			static fn( array $left, array $right ): int => strcmp(
				(string) $left['classification'] . '|' . (string) $left['component_id'],
				(string) $right['classification'] . '|' . (string) $right['component_id']
			)
		);

		return $rows;
	}
}
