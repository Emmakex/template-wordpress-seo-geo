<?php
/**
 * Accepted sandbox gate for Native Replatform operations.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge\Replatform;

use SeoGeo\MigrationBridge\Sandbox\SandboxMigrationLab;
use SeoGeo\MigrationBridge\SiteAnalyzer;
use Throwable;

/**
 * Resolves the full Phase 8 sandbox acceptance state once per request.
 */
final class AcceptedSandboxGate {
	/**
	 * Cached bounded gate report.
	 *
	 * @var array<string,mixed>|null
	 */
	private ?array $report = null;

	/**
	 * Return a bounded Native Replatform sandbox gate.
	 *
	 * @return array<string,mixed>
	 */
	public function report(): array {
		if ( null !== $this->report ) {
			return $this->report;
		}

		try {
			$raw         = ( new SandboxMigrationLab() )->report( ( new SiteAnalyzer() )->analyze() );
			$environment = is_array( $raw['environment'] ?? null ) ? $raw['environment'] : array();
			$blockers    = is_array( $raw['blockers'] ?? null )
				? array_values( array_filter( array_map( 'strval', $raw['blockers'] ) ) )
				: array();
			$ready       = true === ( $raw['ready'] ?? false ) && array() === $blockers;

			sort( $blockers );

			$this->report = array(
				'schema_version'             => 1,
				'mode'                       => 'accepted-sandbox-gate',
				'ready'                      => $ready,
				'blockers'                   => $blockers,
				'sandbox_marker'             => true === ( $environment['sandbox_marker'] ?? false ),
				'baseline_available'         => true === ( $environment['baseline_available'] ?? false ),
				'dependency_graph_ready'     => true === ( $environment['dependency_graph_ready'] ?? false ),
				'dependency_review_complete' => true === ( $environment['dependency_review_complete'] ?? false ),
				'unreviewed_unknown'          => max( 0, (int) ( $environment['unreviewed_unknown'] ?? 0 ) ),
				'destination_theme_active'    => true === ( $environment['destination_theme_active'] ?? false ),
			);

			return $this->report;
		} catch ( Throwable ) {
			$this->report = array(
				'schema_version'             => 1,
				'mode'                       => 'accepted-sandbox-gate',
				'ready'                      => false,
				'blockers'                   => array( 'sandbox-readiness-unavailable' ),
				'sandbox_marker'             => false,
				'baseline_available'         => false,
				'dependency_graph_ready'     => false,
				'dependency_review_complete' => false,
				'unreviewed_unknown'          => 0,
				'destination_theme_active'    => false,
			);

			return $this->report;
		}
	}
}
