<?php
/**
 * Read-only Migration Bridge operator status.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge\Operator;

use SeoGeo\MigrationBridge\BaselineSnapshotStore;
use SeoGeo\MigrationBridge\Cutover\CutoverSnapshotStore;
use SeoGeo\MigrationBridge\DependencyGraphBuilder;
use SeoGeo\MigrationBridge\IncrementalBaselineCapture;
use SeoGeo\MigrationBridge\Portable\PortableCloneJobStore;
use SeoGeo\MigrationBridge\Report\MigrationReportStore;
use SeoGeo\MigrationBridge\Review\DependencyReviewStore;
use SeoGeo\MigrationBridge\Sandbox\SandboxGuard;
use SeoGeo\MigrationBridge\Sandbox\SandboxMigrationLab;
use SeoGeo\MigrationBridge\SiteAnalyzer;
use Throwable;

/**
 * Produces bounded operational status without exposing migration source bodies.
 */
final class OperatorStatus {
	/**
	 * Baseline store.
	 *
	 * @var BaselineSnapshotStore
	 */
	private BaselineSnapshotStore $baseline_store;

	/**
	 * Cutover history.
	 *
	 * @var CutoverSnapshotStore
	 */
	private CutoverSnapshotStore $cutover_store;

	/**
	 * Final report store.
	 *
	 * @var MigrationReportStore
	 */
	private MigrationReportStore $report_store;

	/**
	 * Current site analyzer.
	 *
	 * @var SiteAnalyzer
	 */
	private SiteAnalyzer $analyzer;

	/**
	 * Dependency graph builder.
	 *
	 * @var DependencyGraphBuilder
	 */
	private DependencyGraphBuilder $graph;

	/**
	 * Planning-only dependency review decisions.
	 *
	 * @var DependencyReviewStore
	 */
	private DependencyReviewStore $review_store;

	/**
	 * Portable clone job store.
	 *
	 * @var PortableCloneJobStore
	 */
	private PortableCloneJobStore $portable_clone_store;

	/**
	 * Construct the status service.
	 *
	 * @param BaselineSnapshotStore|null  $baseline_store Optional baseline store.
	 * @param CutoverSnapshotStore|null   $cutover_store  Optional cutover store.
	 * @param MigrationReportStore|null   $report_store   Optional final-report store.
	 * @param SiteAnalyzer|null           $analyzer       Optional site analyzer.
	 * @param DependencyGraphBuilder|null $graph          Optional dependency graph.
	 * @param DependencyReviewStore|null  $review_store   Optional planning review store.
	 * @param PortableCloneJobStore|null  $portable_clone_store Optional portable clone job store.
	 */
	public function __construct(
		?BaselineSnapshotStore $baseline_store = null,
		?CutoverSnapshotStore $cutover_store = null,
		?MigrationReportStore $report_store = null,
		?SiteAnalyzer $analyzer = null,
		?DependencyGraphBuilder $graph = null,
		?DependencyReviewStore $review_store = null,
		?PortableCloneJobStore $portable_clone_store = null
	) {
		$this->baseline_store = $baseline_store ?? new BaselineSnapshotStore();
		$this->cutover_store  = $cutover_store ?? new CutoverSnapshotStore();
		$this->report_store   = $report_store ?? new MigrationReportStore();
		$this->analyzer       = $analyzer ?? new SiteAnalyzer();
		$this->graph          = $graph ?? new DependencyGraphBuilder();
		$this->review_store         = $review_store ?? new DependencyReviewStore();
		$this->portable_clone_store = $portable_clone_store ?? new PortableCloneJobStore();
	}

	/**
	 * Return one bounded read-only operator snapshot.
	 *
	 * @return array<string,mixed>
	 */
	public function snapshot(): array {
		$baseline = $this->baseline_store->latest();
		$cutover  = $this->cutover_store->latest();
		$report   = $this->report_store->latest();

		$report_payload = is_array( $report['report'] ?? null ) ? $report['report'] : null;
		$dependency     = $this->dependency_status( $baseline, $report_payload );
		$final_report   = $this->report_status( $report, $report_payload );
		$cutover_status = $this->cutover_status( $cutover );
		$capture        = ( new IncrementalBaselineCapture() )->status();
		$sandbox        = $this->sandbox_status( $baseline );

		return array(
			'schema_version'  => 1,
			'mode'            => 'operator-status-read-only',
			'generated_at'    => gmdate( DATE_ATOM ),
			'baseline'        => array(
				'available' => is_array( $baseline ),
				'id'        => is_array( $baseline ) && is_string( $baseline['id'] ?? null ) ? $baseline['id'] : null,
				'sha256'    => is_array( $baseline ) && is_string( $baseline['sha256'] ?? null ) ? $baseline['sha256'] : null,
			),
			'capture'         => $capture,
			'dependency_plan' => $dependency,
			'cutover'         => $cutover_status,
			'final_report'    => $final_report,
			'portable_clone'  => $this->portable_clone_status(),
			'sandbox'         => $sandbox,
			'next_step'       => $this->next_step(
				is_array( $baseline ),
				$cutover_status,
				$final_report,
				$sandbox
			),
			'safety'          => array(
				'mutations_performed'          => false,
				'private_content_exported'     => false,
				'builder_payload_exported'     => false,
				'credentials_exported'         => false,
				'raw_recovery_exported'        => false,
				'page_render_executes_actions' => false,
			),
		);
	}

	/**
	 * Resolve dependency status from final report when available, otherwise live read-only analysis.
	 *
	 * @param array<string,mixed>|null $baseline Baseline envelope.
	 * @param array<string,mixed>|null $report   Final report.
	 * @return array<string,mixed>
	 */
	private function dependency_status( ?array $baseline, ?array $report ): array {
		$summary      = null;
		$live_summary = array();
		$components   = array();
		$source       = 'live-read-only';

		try {
			$analysis     = $this->analyzer->analyze();
			$graph        = $this->graph->build(
				$analysis,
				is_array( $baseline['snapshot'] ?? null ) ? $baseline['snapshot'] : null
			);
			$live_summary = isset( $graph['summary'] ) && is_array( $graph['summary'] ) ? $graph['summary'] : array();
			$components   = isset( $graph['components'] ) && is_array( $graph['components'] )
				? $this->bounded_component_rows( $graph['components'] )
				: array();
		} catch ( Throwable ) {
			$live_summary = array();
			$components   = array();
		}

		if (
			is_array( $report )
			&& isset( $report['dependencies']['after']['classification_summary'] )
			&& is_array( $report['dependencies']['after']['classification_summary'] )
		) {
			$summary = $report['dependencies']['after']['classification_summary'];
			$source  = 'final-report';
		} else {
			$summary = $live_summary;
		}

		$normalized = array();
		foreach ( array( 'KEEP', 'REPLACE', 'MIGRATE', 'OPTIONAL', 'REMOVE-CANDIDATE', 'UNKNOWN' ) as $classification ) {
			$normalized[ $classification ] = isset( $summary[ $classification ] ) ? max( 0, (int) $summary[ $classification ] ) : 0;
		}

		$reviewed_unknown   = 0;
		$unreviewed_unknown = 0;
		foreach ( $components as $component ) {
			if ( 'UNKNOWN' !== ( $component['classification'] ?? null ) ) {
				continue;
			}

			if ( true === ( $component['reviewed'] ?? false ) ) {
				++$reviewed_unknown;
			} else {
				++$unreviewed_unknown;
			}
		}

		return array(
			'available'                => array_sum( $normalized ) > 0,
			'source'                   => $source,
			'summary'                  => $normalized,
			'components'               => $components,
			'blocking_count'           => $normalized['MIGRATE'] + $normalized['UNKNOWN'],
			'advisory_count'           => $normalized['OPTIONAL'] + $normalized['REMOVE-CANDIDATE'],
			'reviewed_unknown_count'   => $reviewed_unknown,
			'unreviewed_unknown_count' => $unreviewed_unknown,
			'review_complete'          => 0 === $unreviewed_unknown,
		);
	}

	/**
	 * Keep only bounded dependency-planning fields for the operator UI.
	 *
	 * @param list<array<string,mixed>> $components Dependency components.
	 * @return list<array<string,mixed>>
	 */
	private function bounded_component_rows( array $components ): array {
		$priority = array(
			'UNKNOWN'          => 0,
			'MIGRATE'          => 1,
			'REPLACE'          => 2,
			'KEEP'             => 3,
			'OPTIONAL'         => 4,
			'REMOVE-CANDIDATE' => 5,
		);

		$rows = array();
		foreach ( $components as $component ) {
			$component_id   = is_string( $component['component_id'] ?? null ) ? $component['component_id'] : '';
			$classification = is_string( $component['classification'] ?? null ) ? $component['classification'] : 'UNKNOWN';
			$review         = 'UNKNOWN' === $classification && '' !== $component_id
				? $this->review_store->decision_for( $component_id )
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
			static function ( array $left, array $right ) use ( $priority ): int {
				$left_class  = $left['classification'];
				$right_class = $right['classification'];
				$left_rank   = $priority[ $left_class ] ?? 99;
				$right_rank  = $priority[ $right_class ] ?? 99;

				return $left_rank === $right_rank
					? strcmp( $left['component_id'], $right['component_id'] )
					: $left_rank <=> $right_rank;
			}
		);

		return $rows;
	}

	/**
	 * Return bounded portable clone planning/job status.
	 *
	 * @return array<string,mixed>
	 */
	private function portable_clone_status(): array {
		$job = $this->portable_clone_store->latest();

		if ( null === $job ) {
			return array(
				'available' => false,
				'status'    => 'none',
			);
		}

		$plan     = is_array( $job['plan'] ?? null ) ? $job['plan'] : array();
		$target   = is_array( $plan['target'] ?? null ) ? $plan['target'] : array();
		$progress = is_array( $job['progress'] ?? null ) ? $job['progress'] : array();

		return array(
			'available'        => true,
			'job_id'           => is_string( $job['job_id'] ?? null ) ? $job['job_id'] : '',
			'mode'             => is_string( $job['mode'] ?? null ) ? $job['mode'] : '',
			'status'           => is_string( $job['status'] ?? null ) ? $job['status'] : 'unknown',
			'stage'            => is_string( $job['stage'] ?? null ) ? $job['stage'] : 'unknown',
			'target_directory' => is_string( $target['directory'] ?? null ) ? $target['directory'] : '',
			'target_home_url'  => is_string( $target['home_url'] ?? null ) ? $target['home_url'] : '',
			'progress'         => array(
				'files_discovered' => max( 0, (int) ( $progress['files_discovered'] ?? 0 ) ),
				'files_copied'     => max( 0, (int) ( $progress['files_copied'] ?? 0 ) ),
				'bytes_copied'     => max( 0, (int) ( $progress['bytes_copied'] ?? 0 ) ),
				'tables_total'     => max( 0, (int) ( $progress['tables_total'] ?? 0 ) ),
				'tables_copied'    => max( 0, (int) ( $progress['tables_copied'] ?? 0 ) ),
				'rows_copied'      => max( 0, (int) ( $progress['rows_copied'] ?? 0 ) ),
			),
			'created_at'       => is_string( $job['created_at'] ?? null ) ? $job['created_at'] : null,
			'updated_at'       => is_string( $job['updated_at'] ?? null ) ? $job['updated_at'] : null,
		);
	}

	/**
	 * Return bounded sandbox preflight status only on explicitly marked sandboxes.
	 *
	 * @param array<string,mixed>|null $baseline Baseline envelope.
	 * @return array<string,mixed>
	 */
	private function sandbox_status( ?array $baseline ): array {
		if ( ! SandboxGuard::enabled() ) {
			return array(
				'active'      => false,
				'ready'       => false,
				'blockers'    => array(),
				'environment' => array(
					'sandbox_marker' => false,
				),
			);
		}

		try {
			$analysis         = $this->analyzer->analyze();
			$graph            = $this->graph->build(
				$analysis,
				is_array( $baseline['snapshot'] ?? null ) ? $baseline['snapshot'] : null
			);
			$report           = ( new SandboxMigrationLab( $this->review_store ) )->report( $analysis, $graph );
			$report['active'] = true;

			return $report;
		} catch ( Throwable ) {
			return array(
				'active'      => true,
				'ready'       => false,
				'blockers'    => array( 'sandbox-preflight-error' ),
				'environment' => array(
					'sandbox_marker' => true,
				),
			);
		}
	}

	/**
	 * Normalize latest cutover state.
	 *
	 * @param array<string,mixed>|null $cutover Latest cutover record.
	 * @return array<string,mixed>
	 */
	private function cutover_status( ?array $cutover ): array {
		$events = is_array( $cutover['events'] ?? null ) ? $cutover['events'] : array();

		return array(
			'available'   => is_array( $cutover ),
			'id'          => is_array( $cutover ) && is_string( $cutover['id'] ?? null ) ? $cutover['id'] : null,
			'status'      => is_array( $cutover ) && is_string( $cutover['status'] ?? null ) ? $cutover['status'] : null,
			'event_count' => count( $events ),
			'accepted'    => is_array( $cutover ) && 'accepted' === ( $cutover['status'] ?? null ),
		);
	}

	/**
	 * Normalize persisted final-report state.
	 *
	 * @param array<string,mixed>|null $envelope Report envelope.
	 * @param array<string,mixed>|null $report   Report payload.
	 * @return array<string,mixed>
	 */
	private function report_status( ?array $envelope, ?array $report ): array {
		$manual      = is_array( $report['manual_review'] ?? null ) ? $report['manual_review'] : array();
		$blocking    = is_array( $manual['blocking'] ?? null ) ? $manual['blocking'] : array();
		$advisory    = is_array( $manual['advisory'] ?? null ) ? $manual['advisory'] : array();
		$disposition = is_array( $report['bridge_disposition'] ?? null ) ? $report['bridge_disposition'] : array();

		return array(
			'available'                   => is_array( $envelope ) && is_array( $report ),
			'ready_for_handoff'           => is_array( $report ) && true === ( $report['ready_for_handoff'] ?? false ),
			'id'                          => is_array( $envelope ) && is_string( $envelope['id'] ?? null ) ? $envelope['id'] : null,
			'sha256'                      => is_array( $envelope ) && is_string( $envelope['sha256'] ?? null ) ? $envelope['sha256'] : null,
			'blocking_review_count'       => count( $blocking ),
			'advisory_review_count'       => count( $advisory ),
			'bridge_disposition'          => is_string( $disposition['decision'] ?? null ) ? $disposition['decision'] : null,
			'runtime_dependency_required' => true === ( $disposition['runtime_dependency_required'] ?? false ),
		);
	}

	/**
	 * Resolve one stable next-step key for operator guidance.
	 *
	 * @param bool                $baseline_available Whether baseline exists.
	 * @param array<string,mixed> $cutover            Normalized cutover state.
	 * @param array<string,mixed> $report             Normalized final-report state.
	 * @param array<string,mixed> $sandbox            Normalized sandbox preflight state.
	 */
	private function next_step( bool $baseline_available, array $cutover, array $report, array $sandbox ): string {
		if ( ! $baseline_available ) {
			return 'capture-baseline';
		}
		if ( true === ( $sandbox['active'] ?? false ) && true !== ( $sandbox['ready'] ?? false ) ) {
			return 'sandbox-preflight';
		}
		if ( true !== ( $cutover['available'] ?? false ) ) {
			return 'continue-migration';
		}
		if ( true !== ( $cutover['accepted'] ?? false ) ) {
			return 'complete-cutover';
		}
		if ( true !== ( $report['available'] ?? false ) ) {
			return 'generate-report';
		}
		if ( 0 < (int) ( $report['blocking_review_count'] ?? 0 ) ) {
			return 'resolve-blockers';
		}
		if (
			'retain-audit-only' === ( $report['bridge_disposition'] ?? null )
			|| 0 < (int) ( $report['advisory_review_count'] ?? 0 )
		) {
			return 'review-advisories';
		}

		return 'remove-bridge';
	}
}
