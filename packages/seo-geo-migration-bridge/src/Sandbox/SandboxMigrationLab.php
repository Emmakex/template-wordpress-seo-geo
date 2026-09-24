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
use SeoGeo\MigrationBridge\Review\DependencyReviewStore;

/**
 * Reports whether an isolated WordPress clone is safe to use for migration work.
 */
final class SandboxMigrationLab {
	/**
	 * Destination theme stylesheet required by the current product.
	 */
	public const DESTINATION_THEME = 'seo-geo-theme';

	/**
	 * Planning-only dependency review store.
	 *
	 * @var DependencyReviewStore
	 */
	private DependencyReviewStore $review_store;

	/**
	 * Construct the sandbox lab.
	 *
	 * @param DependencyReviewStore|null $review_store Optional dependency-review store.
	 */
	public function __construct( ?DependencyReviewStore $review_store = null ) {
		$this->review_store = $review_store ?? new DependencyReviewStore();
	}

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
		$outbound_safe       = SandboxGuard::outbound_safe();
		$backups_ready       = SandboxGuard::backups_ready();
		$active_stylesheet   = (string) get_option( 'stylesheet', '' );
		$destination_active  = self::DESTINATION_THEME === $active_stylesheet;
		$baseline_available  = null !== $baseline;
		$dependency_complete = 1 === ( $graph['schema_version'] ?? null ) && 'read-only-planning' === ( $graph['mode'] ?? null );
		$current_origin      = $this->origin_key( home_url( '/' ) );
		$source_origin       = $this->baseline_origin( $baseline );
		$distinct_origin     = '' !== $source_origin && '' !== $current_origin && $source_origin !== $current_origin;
		$review              = $this->review_status( $graph );

		$blockers = array();
		if ( ! $sandbox_marked ) {
			$blockers[] = 'sandbox-marker-missing';
		}
		if ( ! $distinct_origin ) {
			$blockers[] = 'sandbox-origin-not-distinct-from-baseline';
		}
		if ( ! $search_discouraged ) {
			$blockers[] = 'search-engine-visibility-not-disabled';
		}
		if ( ! $outbound_safe ) {
			$blockers[] = 'outbound-safety-not-confirmed';
		}
		if ( ! $backups_ready ) {
			$blockers[] = 'fresh-backups-not-confirmed';
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
		if ( ! $review['complete'] ) {
			$blockers[] = 'dependency-review-incomplete';
		}

		$states = $this->migration_states( $graph );

		return array(
			'schema_version' => 2,
			'mode'           => 'sandbox-migration-lab',
			'ready'          => array() === $blockers,
			'environment'    => array(
				'sandbox_marker'             => $sandbox_marked,
				'source_origin'              => $source_origin,
				'current_origin'             => $current_origin,
				'distinct_origin'            => $distinct_origin,
				'search_engine_visibility'   => $search_discouraged ? 'discouraged' : 'public',
				'outbound_safety_confirmed'  => $outbound_safe,
				'fresh_backups_confirmed'    => $backups_ready,
				'destination_theme'          => $active_stylesheet,
				'destination_theme_active'   => $destination_active,
				'baseline_available'         => $baseline_available,
				'dependency_graph_ready'     => $dependency_complete,
				'dependency_review_complete' => $review['complete'],
				'reviewed_unknown'           => $review['reviewed_unknown'],
				'unreviewed_unknown'         => $review['unreviewed_unknown'],
			),
			'blockers'       => $blockers,
			'migration'      => array(
				'states'  => $states,
				'summary' => $this->state_summary( $states ),
			),
			'safety'         => array(
				'production_cutover_allowed'    => false,
				'production_mutation_allowed'   => false,
				'indexing_allowed'              => false,
				'canonical_competition_allowed' => false,
				'baseline_is_reference_only'    => true,
				'review_decisions_are_planning' => true,
			),
		);
	}

	/**
	 * Convert dependency classifications and explicit UNKNOWN reviews into sandbox migration states.
	 *
	 * Raw graph classification remains authoritative. A review decision only chooses
	 * the sandbox planning state for an UNKNOWN component.
	 *
	 * @param array<string, mixed> $graph Phase 8C dependency graph.
	 * @return list<array{component_id:string,state:string,classification:string,review_decision:string|null}>
	 */
	private function migration_states( array $graph ): array {
		$states     = array();
		$components = isset( $graph['components'] ) && is_array( $graph['components'] ) ? $graph['components'] : array();

		foreach ( $components as $component ) {
			if ( ! is_array( $component ) ) {
				continue;
			}

			$component_id   = $component['component_id'] ?? null;
			$classification = $component['classification'] ?? null;
			if ( ! is_string( $component_id ) || ! is_string( $classification ) ) {
				continue;
			}

			$review          = 'UNKNOWN' === $classification ? $this->review_store->decision_for( $component_id ) : null;
			$review_decision = is_array( $review ) ? $review['classification'] : null;

			$state = match ( $classification ) {
				'KEEP'             => 'unchanged',
				'MIGRATE', 'REPLACE' => 'migrate',
				'OPTIONAL', 'REMOVE-CANDIDATE' => 'manual-review',
				'UNKNOWN'          => $this->reviewed_unknown_state( $review_decision ),
				default            => 'blocked',
			};

			$states[] = array(
				'component_id'    => $component_id,
				'state'           => $state,
				'classification'  => $classification,
				'review_decision' => $review_decision,
			);
		}

		usort(
			$states,
			static fn( array $left, array $right ): int => strcmp( $left['component_id'], $right['component_id'] )
		);

		return $states;
	}

	/**
	 * Convert one reviewed UNKNOWN decision to a sandbox planning state.
	 *
	 * @param string|null $decision Operator review decision.
	 */
	private function reviewed_unknown_state( ?string $decision ): string {
		return match ( $decision ) {
			'KEEP'               => 'unchanged',
			'MIGRATE', 'REPLACE' => 'migrate',
			'OPTIONAL', 'REMOVE-CANDIDATE' => 'manual-review',
			default              => 'manual-review',
		};
	}

	/**
	 * Return review progress across raw UNKNOWN graph components.
	 *
	 * @param array<string,mixed> $graph Dependency graph.
	 * @return array{reviewed_unknown:int,unreviewed_unknown:int,complete:bool}
	 */
	private function review_status( array $graph ): array {
		$reviewed   = 0;
		$unreviewed = 0;
		$components = isset( $graph['components'] ) && is_array( $graph['components'] ) ? $graph['components'] : array();

		foreach ( $components as $component ) {
			if ( ! is_array( $component ) || 'UNKNOWN' !== ( $component['classification'] ?? null ) ) {
				continue;
			}

			$component_id = $component['component_id'] ?? null;
			if ( is_string( $component_id ) && is_array( $this->review_store->decision_for( $component_id ) ) ) {
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
	 * Return the normalized production/source origin recorded by the baseline.
	 *
	 * @param array<string,mixed>|null $baseline Baseline snapshot.
	 */
	private function baseline_origin( ?array $baseline ): string {
		$site = is_array( $baseline['site'] ?? null ) ? $baseline['site'] : array();
		$url  = is_string( $site['home_url'] ?? null ) ? $site['home_url'] : '';

		return $this->origin_key( $url );
	}

	/**
	 * Normalize an HTTP(S) URL to scheme + host + effective port.
	 *
	 * @param string $url URL to normalize.
	 */
	private function origin_key( string $url ): string {
		$scheme_value = wp_parse_url( $url, PHP_URL_SCHEME );
		$host_value   = wp_parse_url( $url, PHP_URL_HOST );
		$port_value   = wp_parse_url( $url, PHP_URL_PORT );
		$scheme       = is_string( $scheme_value ) ? strtolower( $scheme_value ) : '';
		$host         = is_string( $host_value ) ? strtolower( $host_value ) : '';

		if ( ! in_array( $scheme, array( 'http', 'https' ), true ) || '' === $host ) {
			return '';
		}

		$port = is_int( $port_value ) ? $port_value : ( 'https' === $scheme ? 443 : 80 );

		return $scheme . '://' . $host . ':' . (string) $port;
	}

	/**
	 * Summarize migration-state counts.
	 *
	 * @param list<array{component_id:string,state:string,classification:string,review_decision:string|null}> $states Migration states.
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
