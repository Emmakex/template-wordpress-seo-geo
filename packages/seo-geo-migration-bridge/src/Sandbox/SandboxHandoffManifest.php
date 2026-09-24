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

		$site       = is_array( $analysis['site'] ?? null ) ? $analysis['site'] : array();
		$themes     = is_array( $analysis['themes'] ?? null ) ? $analysis['themes'] : array();
		$components = is_array( $graph['components'] ?? null ) ? $graph['components'] : array();
		$summary    = is_array( $graph['summary'] ?? null ) ? $graph['summary'] : array();

		return array(
			'schema_version' => 1,
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
				'components' => $this->bounded_components( $components ),
			),
			'target'         => array(
				'theme_stylesheet' => SandboxMigrationLab::DESTINATION_THEME,
				'release_version'  => '0.1.0',
			),
			'sandbox'        => array(
				'requires_distinct_origin'       => true,
				'requires_marker'                => SandboxGuard::MARKER,
				'requires_marker_value'          => true,
				'requires_search_visibility_off' => true,
				'requires_outbound_safety'       => true,
				'requires_fresh_backups'         => true,
				'production_mutation_allowed'    => false,
			),
			'safety'         => array(
				'read_only_generation'      => true,
				'post_bodies_exported'      => false,
				'builder_payloads_exported' => false,
				'credentials_exported'      => false,
				'option_values_exported'    => false,
				'raw_database_exported'     => false,
				'raw_uploads_exported'      => false,
				'customer_data_exported'    => false,
				'baseline_body_content'     => false,
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
			if ( ! is_array( $theme ) || 'active' !== ( $theme['status'] ?? null ) ) {
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
	 * Keep only safe component planning fields.
	 *
	 * @param list<array<string,mixed>> $components Dependency graph components.
	 * @return list<array<string,mixed>>
	 */
	private function bounded_components( array $components ): array {
		$rows = array();

		foreach ( $components as $component ) {
			$rows[] = array(
				'component_id'   => is_string( $component['component_id'] ?? null ) ? $component['component_id'] : '',
				'type'           => is_string( $component['type'] ?? null ) ? $component['type'] : '',
				'id'             => is_string( $component['id'] ?? null ) ? $component['id'] : '',
				'category'       => is_string( $component['category'] ?? null ) ? $component['category'] : null,
				'active'         => true === ( $component['active'] ?? false ),
				'classification' => is_string( $component['classification'] ?? null ) ? $component['classification'] : 'UNKNOWN',
				'reason'         => is_string( $component['reason'] ?? null ) ? $component['reason'] : 'insufficient-evidence',
				'resource_count' => isset( $component['resource_count'] ) ? max( 0, (int) $component['resource_count'] ) : null,
				'manual_review'  => true === ( $component['manual_review'] ?? false ),
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
