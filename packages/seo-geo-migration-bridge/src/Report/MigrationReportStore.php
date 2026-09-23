<?php
/**
 * Persistent Phase 8H migration report storage.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge\Report;

use RuntimeException;

/**
 * Persists one accepted migration handoff report outside runtime hooks.
 */
final class MigrationReportStore {
	/**
	 * Stable non-autoloaded handoff option.
	 */
	public const OPTION_NAME = 'seo_geo_migration_report_v1';

	/**
	 * Save one final report.
	 *
	 * @param array<string,mixed> $report  Final migration report.
	 * @param bool                $replace Whether an existing report may be replaced.
	 * @return array{saved:bool,id:string|null,saved_at:string|null,sha256:string|null,replaced:bool,reason:string|null}
	 * @throws RuntimeException When the report cannot be encoded.
	 */
	public function save( array $report, bool $replace = false ): array {
		if (
			1 !== ( $report['schema_version'] ?? null )
			|| 'migration-report' !== ( $report['mode'] ?? null )
			|| true !== ( $report['ready_for_handoff'] ?? false )
		) {
			return array(
				'saved'    => false,
				'id'       => null,
				'saved_at' => null,
				'sha256'   => null,
				'replaced' => false,
				'reason'   => 'report-not-ready-for-handoff',
			);
		}

		$existing = $this->latest();
		if ( null !== $existing && ! $replace ) {
			return array(
				'saved'    => false,
				'id'       => isset( $existing['id'] ) && is_string( $existing['id'] ) ? $existing['id'] : null,
				'saved_at' => isset( $existing['saved_at'] ) && is_string( $existing['saved_at'] ) ? $existing['saved_at'] : null,
				'sha256'   => isset( $existing['sha256'] ) && is_string( $existing['sha256'] ) ? $existing['sha256'] : null,
				'replaced' => false,
				'reason'   => 'report-exists',
			);
		}

		$encoded = wp_json_encode( $report, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		if ( false === $encoded ) {
			throw new RuntimeException( 'Could not encode the migration handoff report.' );
		}

		$envelope = array(
			'schema_version' => 1,
			'id'             => wp_generate_uuid4(),
			'saved_at'       => gmdate( DATE_ATOM ),
			'sha256'         => hash( 'sha256', $encoded ),
			'report'         => $report,
		);

		$saved = null === $existing
			? add_option( self::OPTION_NAME, $envelope, '', false )
			: update_option( self::OPTION_NAME, $envelope, false );

		return array(
			'saved'    => $saved,
			'id'       => $saved ? $envelope['id'] : null,
			'saved_at' => $saved ? $envelope['saved_at'] : null,
			'sha256'   => $saved ? $envelope['sha256'] : null,
			'replaced' => $saved && null !== $existing,
			'reason'   => $saved ? null : 'report-storage-write-failed',
		);
	}

	/**
	 * Return the persisted handoff report envelope.
	 *
	 * @return array<string,mixed>|null
	 */
	public function latest(): ?array {
		$value = get_option( self::OPTION_NAME, null );
		if (
			! is_array( $value )
			|| 1 !== ( $value['schema_version'] ?? null )
			|| ! isset( $value['report'] )
			|| ! is_array( $value['report'] )
		) {
			return null;
		}

		return $value;
	}
}
