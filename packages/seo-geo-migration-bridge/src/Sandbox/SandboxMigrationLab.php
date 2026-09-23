<?php
/**
 * Provider-neutral sandbox migration lab.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge\Sandbox;

use SeoGeo\MigrationBridge\BaselineSnapshotStore;
use SeoGeo\MigrationBridge\DependencyGraphBuilder;

/**
 * Reports whether an isolated WordPress clone is safe to use for migration work.
 */
final class SandboxMigrationLab {
	/**
	 * Destination theme stylesheet required by the current product.
	 */
	public const DESTINATION_THEME = 'seo-geo-theme';

	/**
	 * Build sandbox readiness and migration-state report.
	 *
	 * @param array<string, mixed>      $analysis Phase 8A analysis.
	 * @param array<string, mixed>|null $graph    Optional Phase 8C dependency graph.
	 * @return array<string,mixed>
	 */
	public function report( array $analysis, ?array $graph = null ): array {
		$baseline_envelope = ( new BaselineSnapshotStore() )->latest();
		$baseline          = null !== $baseline_envelope && isset( $baseline_envelope['snapshot'] ) && is_array( $baseline_envelope['snapshot'] )
			? $baseline_envelope['snapshot']
			: null;

		if ( null === $graph ) {
			$graph = ( new DependencyGraphBuilder() )->build( $analysis, $baseline );
		}

		$sandbox_marked      = SandboxGuard::enabled();
		$search_discouraged  = '0' === (string) get_option( 'blog_public', '1' );
		$active_stylesheet   = (string) get_option( 'stylesheet', '' );
		$destination_active  = self::DESTINATION_THEME === $active_stylesheet;
		$baseline_available  = null !== $baseline;
		$dependency_complete = 1 === ( $graph['schema_version'] ?? null ) && 'read-only-planning' === ( $graph['mode'] ?? null );

		$blockers = array();
		if ( ! $sandbox_marked ) {
			$blockers[] = 'sandbox-marker-missing';
		}
		if ( ! $search_discouraged ) {
			$blockers[] = 'search-engine-visibility-not-disabled';
		}
		if ( ! $destination_active ) {
			$blockers[] = 'destination-theme-not-active';
		}
		if ( ! $baseline_available ) {
			$blockers[] = 'phase-8b-baseline-missing';
		}
		if ( ! $dependency_complete ) {
			$blockers[] = 'phase-8c-dependency-graph-missing';
		}

		$states = $this->migration_states( $graph );

		return array(
			'schema_version' => 1,
			'mode'           => 'sandbox-migration-lab',
			'ready'          => array() === $blockers,
			'environment'    => array(
				'sandbox_marker'           => $sandbox_marked,
				'search_engine_visibility' => $search_discouraged ? 'discouraged' : 'public',
				'destination_theme'         => $active_stylesheet,
				'destination_theme_active'  => $destination_active,
				'baseline_available'        => $baseline_available,
				'dependency_graph_ready'    => $dependency_complete,
			),
			'blockers'       => $blockers,
			'migration'      => array(
				'states'  => $states,
				'summary' => $this->state_summary( $states ),
			),
			'safety'         => array(
				'production_cutover_allowed' => false,
				'production_mutation_allowed' => false,
				'indexing_allowed'            => false,
				'canonical_competition_allowed' => false,
				'baseline_is_reference_only'  => true,
			),
		);
	}

	/**
	 * Convert dependency classifications into sandbox migration states.
	 *
	 * @param array<string, mixed> $graph Phase 8C dependency graph.
	 * @return list<array{component_id:string,state:string,classification:string}>
	 */
	private function migration_states( array $graph ): array {
		$states     = array();
		$components = isset( $graph['components'] ) && is_array( $graph['components'] ) ? $graph['components'] : array();

		foreach ( $components as $component ) {
			if ( ! is_array( $component ) ) {
				continue;
			}

			$component_id  = $component['component_id'] ?? null;
			$classification = $component['classification'] ?? null;
			if ( ! is_string( $component_id ) || ! is_string( $classification ) ) {
				continue;
			}

			$state = match ( $classification ) {
				'KEEP'             => 'unchanged',
				'MIGRATE', 'REPLACE' => 'migrate',
				'OPTIONAL', 'REMOVE-CANDIDATE', 'UNKNOWN' => 'manual-review',
				default            => 'blocked',
			};

			$states[] = array(
				'component_id'  => $component_id,
				'state'         => $state,
				'classification' => $classification,
			);
		}

		usort(
			$states,
			static fn( array $left, array $right ): int => strcmp( $left['component_id'], $right['component_id'] )
		);

		return $states;
	}

	/**
	 * Summarize migration-state counts.
	 *
	 * @param list<array{component_id:string,state:string,classification:string}> $states Migration states.
	 * @return array{migrate:int,blocked:int,manual-review:int,unchanged:int}
	 */
	private function state_summary( array $states ): array {
		$summary = array(
			'migrate'       => 0,
			'blocked'       => 0,
			'manual-review' => 0,
			'unchanged'     => 0,
		);

		foreach ( $states as $state ) {
			$key = $state['state'];
			if ( isset( $summary[ $key ] ) ) {
				++$summary[ $key ];
			}
		}

		return $summary;
	}
}
