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
use SeoGeo\MigrationBridge\Report\MigrationReportStore;
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
	 * Construct the status service.
	 *
	 * @param BaselineSnapshotStore|null  $baseline_store Optional baseline store.
	 * @param CutoverSnapshotStore|null   $cutover_store  Optional cutover store.
	 * @param MigrationReportStore|null   $report_store   Optional final-report store.
	 * @param SiteAnalyzer|null           $analyzer       Optional site analyzer.
	 * @param DependencyGraphBuilder|null $graph          Optional dependency graph.
	 */
	public function __construct(
		?BaselineSnapshotStore $baseline_store = null,
		?CutoverSnapshotStore $cutover_store = null,
		?MigrationReportStore $report_store = null,
		?SiteAnalyzer $analyzer = null,
		?DependencyGraphBuilder $graph = null
	) {
		$this->baseline_store = $baseline_store ?? new BaselineSnapshotStore();
		$this->cutover_store  = $cutover_store ?? new CutoverSnapshotStore();
		$this->report_store   = $report_store ?? new MigrationReportStore();
		$this->analyzer       = $analyzer ?? new SiteAnalyzer();
		$this->graph          = $graph ?? new DependencyGraphBuilder();
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

		return array(
			'schema_version' => 1,
			'mode'           => 'operator-status-read-only',
			'generated_at'   => gmdate( DATE_ATOM ),
			'baseline'       => array(
				'available' => is_array( $baseline ),
				'id'        => is_array( $baseline ) && is_string( $baseline['id'] ?? null ) ? $baseline['id'] : null,
				'sha256'    => is_array( $baseline ) && is_string( $baseline['sha256'] ?? null ) ? $baseline['sha256'] : null,
			),
			'dependency_plan'=> $dependency,
			'cutover'        => $cutover_status,
			'final_report'   => $final_report,
			'next_step'      => $this->next_step(
				is_array( $baseline ),
				$cutover_status,
				$final_report
			),
			'safety'         => array(
				'mutations_performed'         => false,
				'private_content_exported'    => false,
				'builder_payload_exported'    => false,
				'credentials_exported'        => false,
				'raw_recovery_exported'       => false,
				'page_render_executes_actions'=> false,
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
		$summary = null;
		$source  = 'live-read-only';

		if (
			is_array( $report )
			&& isset( $report['dependencies']['after']['classification_summary'] )
			&& is_array( $report['dependencies']['after']['classification_summary'] )
		) {
			$summary = $report['dependencies']['after']['classification_summary'];
			$source  = 'final-report';
		}

		if ( ! is_array( $summary ) ) {
			try {
				$analysis = $this->analyzer->analyze();
				$graph    = $this->graph->build(
					$analysis,
					is_array( $baseline['snapshot'] ?? null ) ? $baseline['snapshot'] : null
				);
				$summary  = isset( $graph['summary'] ) && is_array( $graph['summary'] ) ? $graph['summary'] : array();
			} catch ( Throwable ) {
				$summary = array();
			}
		}

		$normalized = array();
		foreach ( array( 'KEEP', 'REPLACE', 'MIGRATE', 'OPTIONAL', 'REMOVE-CANDIDATE', 'UNKNOWN' ) as $classification ) {
			$normalized[ $classification ] = isset( $summary[ $classification ] ) ? max( 0, (int) $summary[ $classification ] ) : 0;
		}

		return array(
			'available'      => array_sum( $normalized ) > 0,
			'source'         => $source,
			'summary'        => $normalized,
			'blocking_count' => $normalized['MIGRATE'] + $normalized['UNKNOWN'],
			'advisory_count' => $normalized['OPTIONAL'] + $normalized['REMOVE-CANDIDATE'],
		);
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
		$manual = is_array( $report['manual_review'] ?? null ) ? $report['manual_review'] : array();
		$blocking = is_array( $manual['blocking'] ?? null ) ? $manual['blocking'] : array();
		$advisory = is_array( $manual['advisory'] ?? null ) ? $manual['advisory'] : array();
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
	 */
	private function next_step( bool $baseline_available, array $cutover, array $report ): string {
		if ( ! $baseline_available ) {
			return 'capture-baseline';
		}
		if ( ! true === ( $cutover['available'] ?? false ) ) {
			return 'continue-migration';
		}
		if ( ! true === ( $cutover['accepted'] ?? false ) ) {
			return 'complete-cutover';
		}
		if ( ! true === ( $report['available'] ?? false ) ) {
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
