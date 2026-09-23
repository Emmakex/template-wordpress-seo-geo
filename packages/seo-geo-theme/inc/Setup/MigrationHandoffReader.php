<?php
/**
 * Accepted Migration Bridge handoff reader.
 *
 * @package SeoGeoTheme
 */

declare(strict_types=1);

namespace SeoGeo\Theme\Setup;

/**
 * Reads the persisted Phase 8 handoff without loading Migration Bridge code.
 */
final class MigrationHandoffReader {
	/**
	 * Stable option written by Phase 8H.
	 */
	public const OPTION_NAME = 'seo_geo_migration_report_v1';

	/**
	 * Return a bounded handoff summary.
	 *
	 * @return array<string,mixed>
	 */
	public function read(): array {
		$envelope = get_option( self::OPTION_NAME, null );

		if ( null === $envelope ) {
			return $this->missing();
		}

		if ( ! is_array( $envelope ) || 1 !== ( $envelope['schema_version'] ?? null ) ) {
			return $this->invalid( 'invalid-envelope' );
		}

		$report = $envelope['report'] ?? null;
		if (
			! is_array( $report )
			|| 1 !== ( $report['schema_version'] ?? null )
			|| 'migration-report' !== ( $report['mode'] ?? null )
			|| true !== ( $report['ready_for_handoff'] ?? false )
		) {
			return $this->invalid( 'report-not-ready' );
		}

		$safety = $report['safety'] ?? null;
		if (
			! is_array( $safety )
			|| true !== ( $safety['report_is_runtime_dependency'] ?? null ) === false
		) {
			return $this->invalid( 'runtime-dependency-contract-invalid' );
		}

		$report_sha256 = is_string( $report['report_sha256'] ?? null ) ? strtolower( $report['report_sha256'] ) : '';
		$envelope_sha256 = is_string( $envelope['sha256'] ?? null ) ? strtolower( $envelope['sha256'] ) : '';

		if ( ! $this->sha256( $report_sha256 ) || ! $this->sha256( $envelope_sha256 ) ) {
			return $this->invalid( 'handoff-fingerprint-invalid' );
		}

		$manual = is_array( $report['manual_review'] ?? null ) ? $report['manual_review'] : array();
		$blocking = is_array( $manual['blocking'] ?? null ) ? $manual['blocking'] : array();
		$advisory = is_array( $manual['advisory'] ?? null ) ? $manual['advisory'] : array();
		$disposition = is_array( $report['bridge_disposition'] ?? null ) ? $report['bridge_disposition'] : array();

		return array(
			'available'                   => true,
			'valid'                       => true,
			'source'                      => 'migration-bridge-handoff-v1',
			'reason'                      => null,
			'id'                          => is_string( $envelope['id'] ?? null ) ? $envelope['id'] : null,
			'saved_at'                    => is_string( $envelope['saved_at'] ?? null ) ? $envelope['saved_at'] : null,
			'envelope_sha256'             => $envelope_sha256,
			'report_sha256'               => $report_sha256,
			'blocking_review_count'       => count( $blocking ),
			'advisory_review_count'       => count( $advisory ),
			'bridge_disposition'          => is_string( $disposition['decision'] ?? null ) ? $disposition['decision'] : null,
			'runtime_dependency_required' => true === ( $disposition['runtime_dependency_required'] ?? false ),
		);
	}

	/**
	 * Return an absent-handoff state.
	 *
	 * @return array<string,mixed>
	 */
	private function missing(): array {
		return array(
			'available' => false,
			'valid'     => false,
			'source'    => null,
			'reason'    => 'not-present',
		);
	}

	/**
	 * Return an invalid-handoff state.
	 *
	 * @param string $reason Validation reason.
	 * @return array<string,mixed>
	 */
	private function invalid( string $reason ): array {
		return array(
			'available' => true,
			'valid'     => false,
			'source'    => 'migration-bridge-handoff-v1',
			'reason'    => $reason,
		);
	}

	/**
	 * Validate one SHA-256 value.
	 *
	 * @param string $value Candidate hash.
	 */
	private function sha256( string $value ): bool {
		return 1 === preg_match( '/^[a-f0-9]{64}$/', $value );
	}
}
