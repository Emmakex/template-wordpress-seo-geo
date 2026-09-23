<?php
/**
 * Phase 8H final migration report engine.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge\Report;

use SeoGeo\MigrationBridge\BaselineSnapshotStore;
use SeoGeo\MigrationBridge\Cutover\BaselinePublicSnapshotProvider;
use SeoGeo\MigrationBridge\Cutover\CutoverSnapshotStore;
use SeoGeo\MigrationBridge\Cutover\PublicSnapshotProviderInterface;
use SeoGeo\MigrationBridge\DependencyGraphBuilder;
use SeoGeo\MigrationBridge\Migration\MigrationEngine;
use SeoGeo\MigrationBridge\Parity\ParityAllowlist;
use SeoGeo\MigrationBridge\Parity\SeoParityEngine;
use SeoGeo\MigrationBridge\SiteAnalyzer;
use Throwable;

/**
 * Consolidates migration evidence into a stable, privacy-bounded handoff report.
 */
final class MigrationReportEngine {
	/**
	 * Site analyzer.
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
	 * Baseline store.
	 *
	 * @var BaselineSnapshotStore
	 */
	private BaselineSnapshotStore $baseline_store;

	/**
	 * Cutover history store.
	 *
	 * @var CutoverSnapshotStore
	 */
	private CutoverSnapshotStore $cutover_store;

	/**
	 * Fresh public snapshot provider.
	 *
	 * @var PublicSnapshotProviderInterface
	 */
	private PublicSnapshotProviderInterface $snapshot_provider;

	/**
	 * SEO/GEO parity engine.
	 *
	 * @var SeoParityEngine
	 */
	private SeoParityEngine $parity;

	/**
	 * Construct the report engine.
	 *
	 * @param SiteAnalyzer|null                    $analyzer          Optional analyzer override.
	 * @param DependencyGraphBuilder|null          $graph             Optional graph override.
	 * @param BaselineSnapshotStore|null           $baseline_store    Optional baseline store.
	 * @param CutoverSnapshotStore|null            $cutover_store     Optional cutover store.
	 * @param PublicSnapshotProviderInterface|null $snapshot_provider Optional public snapshot provider.
	 * @param SeoParityEngine|null                 $parity            Optional parity engine.
	 */
	public function __construct(
		?SiteAnalyzer $analyzer = null,
		?DependencyGraphBuilder $graph = null,
		?BaselineSnapshotStore $baseline_store = null,
		?CutoverSnapshotStore $cutover_store = null,
		?PublicSnapshotProviderInterface $snapshot_provider = null,
		?SeoParityEngine $parity = null
	) {
		$this->analyzer          = $analyzer ?? new SiteAnalyzer();
		$this->graph             = $graph ?? new DependencyGraphBuilder();
		$this->baseline_store    = $baseline_store ?? new BaselineSnapshotStore();
		$this->cutover_store     = $cutover_store ?? new CutoverSnapshotStore();
		$this->snapshot_provider = $snapshot_provider ?? new BaselinePublicSnapshotProvider();
		$this->parity            = $parity ?? new SeoParityEngine();
	}

	/**
	 * Generate one read-only final migration report.
	 *
	 * @param array<int,mixed> $allowlist_rules Exact Phase 8F intentional-difference rules.
	 * @return array<string,mixed>
	 */
	public function generate( array $allowlist_rules = array() ): array {
		$blockers = array();
		$baseline = $this->baseline_store->latest();
		$cutover  = $this->cutover_store->latest();

		if ( ! is_array( $baseline ) || ! isset( $baseline['snapshot'] ) || ! is_array( $baseline['snapshot'] ) ) {
			$blockers[] = 'baseline-unavailable';
		}
		if ( ! is_array( $cutover ) || 'accepted' !== ( $cutover['status'] ?? null ) ) {
			$blockers[] = 'accepted-cutover-required';
		}

		$analysis = $this->analyzer->analyze();
		$graph    = $this->graph->build(
			$analysis,
			is_array( $baseline ) && isset( $baseline['snapshot'] ) && is_array( $baseline['snapshot'] )
				? $baseline['snapshot']
				: null
		);

		$allowlist_sha256          = ParityAllowlist::fingerprint( $allowlist_rules );
		$expected_allowlist_sha256 = is_array( $cutover )
			&& isset( $cutover['actions']['parity_allowlist_hash'] )
			&& is_string( $cutover['actions']['parity_allowlist_hash'] )
				? $cutover['actions']['parity_allowlist_hash']
				: null;

		if (
			! is_string( $expected_allowlist_sha256 )
			|| ! hash_equals( $expected_allowlist_sha256, $allowlist_sha256 )
		) {
			$blockers[] = 'parity-allowlist-hash-mismatch';
		}

		$parity_report = null;
		if ( is_array( $baseline ) && isset( $baseline['snapshot'] ) && is_array( $baseline['snapshot'] ) ) {
			try {
				$parity_report = $this->parity->compare(
					$baseline['snapshot'],
					$this->snapshot_provider->capture(),
					$allowlist_rules
				);
			} catch ( Throwable ) {
				$blockers[] = 'fresh-parity-unavailable';
			}
		}

		if ( ! is_array( $parity_report ) || true !== ( $parity_report['accepted'] ?? false ) ) {
			$blockers[] = 'fresh-parity-not-accepted';
		}

		$quality = $this->quality( $cutover );
		foreach ( $quality['blockers'] as $quality_blocker ) {
			$blockers[] = $quality_blocker;
		}

		$dependencies     = $this->dependencies( $analysis, $graph, $cutover );
		$migrations       = $this->migrations( $cutover );
		$manual_review    = $this->manual_review( $graph, $parity_report, $quality );
		$cutover_evidence = $this->cutover_evidence( $cutover );

		$blockers = array_values( array_unique( $blockers ) );
		sort( $blockers );

		$ready_for_handoff = array() === $blockers;
		$disposition       = $this->bridge_disposition( $ready_for_handoff, $manual_review );

		$report = array(
			'schema_version'     => 1,
			'mode'               => 'migration-report',
			'generated_at'       => gmdate( DATE_ATOM ),
			'ready_for_handoff'  => $ready_for_handoff,
			'blockers'           => $blockers,
			'baseline'           => array(
				'id'     => is_array( $baseline ) && is_string( $baseline['id'] ?? null ) ? $baseline['id'] : null,
				'sha256' => is_array( $baseline ) && is_string( $baseline['sha256'] ?? null ) ? $baseline['sha256'] : null,
			),
			'dependencies'       => $dependencies,
			'migrations'         => $migrations,
			'parity'             => $this->parity_summary( $parity_report, $allowlist_sha256 ),
			'quality'            => $quality['evidence'],
			'manual_review'      => $manual_review,
			'cutover'            => $cutover_evidence,
			'bridge_disposition' => $disposition,
			'safety'             => array(
				'mutations_performed'          => false,
				'private_content_exported'     => false,
				'raw_backup_content_exported'  => false,
				'credentials_exported'         => false,
				'report_is_runtime_dependency' => false,
			),
		);

		$report['report_sha256'] = ParityAllowlist::fingerprint( $report );
		return $report;
	}

	/**
	 * Build before/after dependency counts and component decisions.
	 *
	 * @param array<string,mixed>      $analysis Current site analysis.
	 * @param array<string,mixed>      $graph    Current dependency graph.
	 * @param array<string,mixed>|null $cutover  Accepted cutover record.
	 * @return array<string,mixed>
	 */
	private function dependencies( array $analysis, array $graph, ?array $cutover ): array {
		$runtime_before = is_array( $cutover['runtime_before'] ?? null ) ? $cutover['runtime_before'] : array();
		$before_active  = isset( $runtime_before['active_plugins'] ) && is_array( $runtime_before['active_plugins'] )
			? array_values( array_filter( $runtime_before['active_plugins'], 'is_string' ) )
			: array();

		$current_plugins = isset( $analysis['plugins'] ) && is_array( $analysis['plugins'] ) ? $analysis['plugins'] : array();
		$after_active    = array();
		foreach ( $current_plugins as $plugin ) {
			if (
				is_array( $plugin )
				&& true === ( $plugin['active'] ?? false )
				&& is_string( $plugin['basename'] ?? null )
			) {
				$after_active[] = $plugin['basename'];
			}
		}
		sort( $before_active );
		sort( $after_active );

		$current_legacy_resources = $this->legacy_builder_resource_ids( $graph );
		$migrated_resources       = $this->migrated_resource_ids( $cutover );
		$before_legacy_resources  = array_values( array_unique( array_merge( $current_legacy_resources, $migrated_resources ) ) );
		sort( $before_legacy_resources );

		$components        = isset( $graph['components'] ) && is_array( $graph['components'] ) ? $graph['components'] : array();
		$kept              = array();
		$remove_candidates = array();

		foreach ( $components as $component ) {
			if ( ! is_array( $component ) || ! is_string( $component['component_id'] ?? null ) ) {
				continue;
			}

			if ( 'KEEP' === ( $component['classification'] ?? null ) ) {
				$kept[] = $component['component_id'];
			}
			if ( 'REMOVE-CANDIDATE' === ( $component['classification'] ?? null ) ) {
				$remove_candidates[] = $component['component_id'];
			}
		}

		sort( $kept );
		sort( $remove_candidates );

		$replaced = is_array( $cutover['actions']['plugins_deactivated'] ?? null )
			? array_values( array_filter( $cutover['actions']['plugins_deactivated'], 'is_string' ) )
			: array();
		sort( $replaced );

		return array(
			'before'    => array(
				'active_plugin_count'      => count( $before_active ),
				'legacy_builder_resources' => count( $before_legacy_resources ),
			),
			'after'     => array(
				'active_plugin_count'      => count( $after_active ),
				'legacy_builder_resources' => count( $current_legacy_resources ),
				'classification_summary'   => isset( $graph['summary'] ) && is_array( $graph['summary'] ) ? $graph['summary'] : array(),
			),
			'delta'     => array(
				'active_plugins'           => count( $after_active ) - count( $before_active ),
				'legacy_builder_resources' => count( $current_legacy_resources ) - count( $before_legacy_resources ),
			),
			'decisions' => array(
				'kept'                 => $kept,
				'replaced_deactivated' => $replaced,
				'removed'              => array(),
				'remove_candidates'    => $remove_candidates,
				'deletion_performed'   => false,
			),
		);
	}

	/**
	 * Return current resources still coupled to a non-native builder.
	 *
	 * @param array<string,mixed> $graph Current graph.
	 * @return list<int>
	 */
	private function legacy_builder_resource_ids( array $graph ): array {
		$resources = isset( $graph['content']['resources'] ) && is_array( $graph['content']['resources'] )
			? $graph['content']['resources']
			: array();
		$ids       = array();

		foreach ( $resources as $resource ) {
			if ( ! is_array( $resource ) || ! isset( $resource['object_id'] ) ) {
				continue;
			}

			$builders = isset( $resource['builders'] ) && is_array( $resource['builders'] ) ? $resource['builders'] : array();
			foreach ( $builders as $builder ) {
				$id = is_array( $builder ) && is_string( $builder['id'] ?? null ) ? $builder['id'] : '';
				if ( '' !== $id && 'native-blocks' !== $id ) {
					$ids[] = (int) $resource['object_id'];
					break;
				}
			}
		}

		$ids = array_values( array_unique( $ids ) );
		sort( $ids );
		return $ids;
	}

	/**
	 * Return resource IDs recorded by the cutover as migrated.
	 *
	 * @param array<string,mixed>|null $cutover Accepted cutover record.
	 * @return list<int>
	 */
	private function migrated_resource_ids( ?array $cutover ): array {
		$rows = is_array( $cutover['migration'] ?? null ) ? $cutover['migration'] : array();
		$ids  = array();

		foreach ( $rows as $row ) {
			if ( is_array( $row ) && isset( $row['object_id'] ) ) {
				$ids[] = (int) $row['object_id'];
			}
		}

		$ids = array_values( array_unique( $ids ) );
		sort( $ids );
		return $ids;
	}

	/**
	 * Return safe migration-state fingerprints.
	 *
	 * @param array<string,mixed>|null $cutover Accepted cutover record.
	 * @return array{count:int,resources:list<array<string,mixed>>}
	 */
	private function migrations( ?array $cutover ): array {
		$rows      = is_array( $cutover['migration'] ?? null ) ? $cutover['migration'] : array();
		$resources = array();

		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) || ! isset( $row['object_id'] ) ) {
				continue;
			}

			$resources[] = array(
				'object_id'     => (int) $row['object_id'],
				'backup_exists' => true === ( $row['backup_exists'] ?? false ),
				'backup_sha256' => is_string( $row['backup_sha256'] ?? null ) ? $row['backup_sha256'] : null,
				'before_sha256' => is_string( $row['before_sha256'] ?? null ) ? $row['before_sha256'] : null,
				'after_sha256'  => is_string( $row['after_sha256'] ?? null ) ? $row['after_sha256'] : null,
			);
		}

		usort(
			$resources,
			static fn( array $left, array $right ): int => (int) $left['object_id'] <=> (int) $right['object_id']
		);

		return array(
			'count'     => count( $resources ),
			'resources' => $resources,
		);
	}

	/**
	 * Normalize stored representative quality evidence.
	 *
	 * @param array<string,mixed>|null $cutover Accepted cutover record.
	 * @return array{evidence:array<string,mixed>,blockers:list<string>}
	 */
	private function quality( ?array $cutover ): array {
		$stored   = is_array( $cutover['quality'] ?? null ) ? $cutover['quality'] : array();
		$evidence = array();
		$blockers = array();

		foreach ( array( 'accessibility', 'performance' ) as $key ) {
			$row    = isset( $stored[ $key ] ) && is_array( $stored[ $key ] ) ? $stored[ $key ] : array();
			$passed = true === ( $row['passed'] ?? false );
			if ( ! $passed ) {
				$blockers[] = 'quality-evidence-not-passed:' . $key;
			}

			$comparison = isset( $row['comparison'] ) && is_array( $row['comparison'] )
				? $row['comparison']
				: null;

			$evidence[ $key ] = array(
				'passed'               => $passed,
				'reference'            => is_string( $row['reference'] ?? null ) ? $row['reference'] : null,
				'sha256'               => is_string( $row['sha256'] ?? null ) ? $row['sha256'] : null,
				'created_at'           => is_string( $row['created_at'] ?? null ) ? $row['created_at'] : null,
				'comparison_available' => is_array( $comparison ),
				'comparison'           => $comparison,
			);
		}

		return array(
			'evidence' => $evidence,
			'blockers' => $blockers,
		);
	}

	/**
	 * Return parity summaries without raw before/after values.
	 *
	 * @param array<string,mixed>|null $parity_report    Fresh parity report.
	 * @param string                   $allowlist_sha256 Allowlist fingerprint.
	 * @return array<string,mixed>
	 */
	private function parity_summary( ?array $parity_report, string $allowlist_sha256 ): array {
		if ( ! is_array( $parity_report ) ) {
			return array(
				'accepted'                 => false,
				'allowlist_sha256'         => $allowlist_sha256,
				'summary'                  => array(),
				'url_redirect'             => array(),
				'intentional_improvements' => array(),
			);
		}

		$differences = isset( $parity_report['differences'] ) && is_array( $parity_report['differences'] )
			? $parity_report['differences']
			: array();

		$url_redirect = array(
			'presence_changes' => 0,
			'status_changes'   => 0,
			'redirect_changes' => 0,
		);
		$improvements = array();

		foreach ( $differences as $difference ) {
			if ( ! is_array( $difference ) || ! is_string( $difference['signal'] ?? null ) ) {
				continue;
			}

			$signal   = $difference['signal'];
			if ( 'presence' === $signal ) {
				++$url_redirect['presence_changes'];
			}
			if ( 'http-status' === $signal ) {
				++$url_redirect['status_changes'];
			}
			if ( str_starts_with( $signal, 'redirect' ) ) {
				++$url_redirect['redirect_changes'];
			}

			if ( 'allowed' === ( $difference['status'] ?? null ) ) {
				$approval = isset( $difference['allowlist'] ) && is_array( $difference['allowlist'] )
					? $difference['allowlist']
					: array();
				$improvements[] = array(
					'path'          => is_string( $difference['path'] ?? null ) ? $difference['path'] : '',
					'signal'        => $signal,
					'before_sha256' => is_string( $difference['before_sha256'] ?? null ) ? $difference['before_sha256'] : null,
					'after_sha256'  => is_string( $difference['after_sha256'] ?? null ) ? $difference['after_sha256'] : null,
					'approval_id'   => is_string( $approval['id'] ?? null ) ? $approval['id'] : null,
					'reason'        => is_string( $approval['reason'] ?? null ) ? $approval['reason'] : null,
				);
			}
		}

		return array(
			'accepted'                 => true === ( $parity_report['accepted'] ?? false ),
			'allowlist_sha256'         => $allowlist_sha256,
			'summary'                  => isset( $parity_report['summary'] ) && is_array( $parity_report['summary'] ) ? $parity_report['summary'] : array(),
			'url_redirect'             => $url_redirect,
			'intentional_improvements' => $improvements,
		);
	}

	/**
	 * Build unresolved manual-review rows.
	 *
	 * @param array<string,mixed>      $graph         Current dependency graph.
	 * @param array<string,mixed>|null $parity_report Fresh parity report.
	 * @param array<string,mixed>      $quality       Normalized quality evidence.
	 * @return array{blocking:list<array<string,mixed>>,advisory:list<array<string,mixed>>}
	 */
	private function manual_review( array $graph, ?array $parity_report, array $quality ): array {
		$blocking = array();
		$advisory = array();

		$components = isset( $graph['components'] ) && is_array( $graph['components'] ) ? $graph['components'] : array();
		foreach ( $components as $component ) {
			if ( ! is_array( $component ) || ! is_string( $component['component_id'] ?? null ) ) {
				continue;
			}

			$classification = is_string( $component['classification'] ?? null ) ? $component['classification'] : 'UNKNOWN';
			$row            = array(
				'type'           => 'dependency',
				'component_id'   => $component['component_id'],
				'classification' => $classification,
				'reason'         => is_string( $component['reason'] ?? null ) ? $component['reason'] : null,
			);

			if ( in_array( $classification, array( 'MIGRATE', 'UNKNOWN' ), true ) ) {
				$blocking[] = $row;
			} elseif ( in_array( $classification, array( 'OPTIONAL', 'REMOVE-CANDIDATE' ), true ) ) {
				$advisory[] = $row;
			}
		}

		if ( is_array( $parity_report ) && isset( $parity_report['differences'] ) && is_array( $parity_report['differences'] ) ) {
			foreach ( $parity_report['differences'] as $difference ) {
				if (
					! is_array( $difference )
					|| ! in_array( $difference['status'] ?? null, array( 'regression', 'unknown' ), true )
				) {
					continue;
				}

				$blocking[] = array(
					'type'          => 'parity',
					'path'          => is_string( $difference['path'] ?? null ) ? $difference['path'] : '',
					'signal'        => is_string( $difference['signal'] ?? null ) ? $difference['signal'] : '',
					'status'        => $difference['status'],
					'before_sha256' => is_string( $difference['before_sha256'] ?? null ) ? $difference['before_sha256'] : null,
					'after_sha256'  => is_string( $difference['after_sha256'] ?? null ) ? $difference['after_sha256'] : null,
				);
			}
		}

		foreach ( array( 'accessibility', 'performance' ) as $key ) {
			$row = isset( $quality['evidence'][ $key ] ) && is_array( $quality['evidence'][ $key ] )
				? $quality['evidence'][ $key ]
				: array();

			if ( true === ( $row['passed'] ?? false ) && true !== ( $row['comparison_available'] ?? false ) ) {
				$advisory[] = array(
					'type'   => 'quality-comparison',
					'gate'   => $key,
					'reason' => 'comparison-summary-not-captured',
				);
			}
		}

		return array(
			'blocking' => $blocking,
			'advisory' => $advisory,
		);
	}

	/**
	 * Summarize cutover lifecycle without exposing backup artifacts.
	 *
	 * @param array<string,mixed>|null $cutover Latest cutover record.
	 * @return array<string,mixed>
	 */
	private function cutover_evidence( ?array $cutover ): array {
		if ( ! is_array( $cutover ) ) {
			return array(
				'status' => null,
				'id'     => null,
				'events' => array(),
			);
		}

		$events        = array();
		$stored_events = isset( $cutover['events'] ) && is_array( $cutover['events'] ) ? $cutover['events'] : array();
		foreach ( $stored_events as $event ) {
			if ( ! is_array( $event ) || ! is_string( $event['status'] ?? null ) ) {
				continue;
			}

			$events[] = array(
				'status' => $event['status'],
				'at'     => is_string( $event['at'] ?? null ) ? $event['at'] : null,
			);
		}

		$backup      = is_array( $cutover['backup'] ?? null ) ? $cutover['backup'] : array();
		$backup_rows = array();
		foreach ( $backup as $key => $row ) {
			if ( ! is_string( $key ) || ! is_array( $row ) ) {
				continue;
			}

			$backup_rows[ $key ] = array(
				'reference'  => is_string( $row['reference'] ?? null ) ? $row['reference'] : null,
				'sha256'     => is_string( $row['sha256'] ?? null ) ? $row['sha256'] : null,
				'created_at' => is_string( $row['created_at'] ?? null ) ? $row['created_at'] : null,
				'scope'      => is_string( $row['scope'] ?? null ) ? $row['scope'] : null,
				'size_bytes' => isset( $row['size_bytes'] ) ? (int) $row['size_bytes'] : 0,
			);
		}

		return array(
			'id'              => is_string( $cutover['id'] ?? null ) ? $cutover['id'] : null,
			'status'          => is_string( $cutover['status'] ?? null ) ? $cutover['status'] : null,
			'created_at'      => is_string( $cutover['created_at'] ?? null ) ? $cutover['created_at'] : null,
			'events'          => $events,
			'backup_evidence' => $backup_rows,
			'plan_sha256'     => is_string( $cutover['plan_sha256'] ?? null ) ? $cutover['plan_sha256'] : null,
		);
	}

	/**
	 * Decide the bridge lifecycle after report generation.
	 *
	 * @param bool  $ready         Whether report can hand off to Phase 9.
	 * @param array $manual_review Manual-review rows.
	 * @phpstan-param array{blocking:list<array<string,mixed>>,advisory:list<array<string,mixed>>} $manual_review
	 * @return array{decision:string,runtime_dependency_required:bool,reason:string}
	 */
	private function bridge_disposition( bool $ready, array $manual_review ): array {
		if ( ! $ready ) {
			return array(
				'decision'                    => 'retain-operational',
				'runtime_dependency_required' => true,
				'reason'                      => 'migration-not-finally-accepted',
			);
		}

		if ( array() !== $manual_review['blocking'] || array() !== $manual_review['advisory'] ) {
			return array(
				'decision'                    => 'retain-audit-only',
				'runtime_dependency_required' => false,
				'reason'                      => 'final-report-has-manual-review-items',
			);
		}

		return array(
			'decision'                    => 'remove',
			'runtime_dependency_required' => false,
			'reason'                      => 'accepted-report-has-no-operational-bridge-dependency',
		);
	}
}
