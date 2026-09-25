<?php
/**
 * Resumable portable clone job state.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge\Portable;

/**
 * Stores one bounded non-autoloaded clone/export/import job.
 */
final class PortableCloneJobStore {
	/**
	 * Persistent job option.
	 */
	public const OPTION_NAME = 'seo_geo_migration_portable_clone_job_v1';

	/**
	 * Job schema version.
	 */
	public const SCHEMA_VERSION = 1;

	/**
	 * Initial portable package schema version.
	 */
	public const PACKAGE_SCHEMA_VERSION = 1;

	/**
	 * Return the current normalized job.
	 *
	 * @return array<string,mixed>|null
	 */
	public function latest(): ?array {
		$value = get_option( self::OPTION_NAME, null );

		if ( ! is_array( $value ) || self::SCHEMA_VERSION !== ( $value['schema_version'] ?? null ) ) {
			return null;
		}

		return $value;
	}

	/**
	 * Start one planned local-clone job from a ready clone plan.
	 *
	 * A repeated prepare request never replaces an active/retryable job.
	 *
	 * @param array<string,mixed> $plan Ready portable clone plan.
	 * @return array<string,mixed>|null
	 */
	public function start( array $plan ): ?array {
		if ( true !== ( $plan['ready'] ?? false ) || 'local-subdirectory' !== ( $plan['mode'] ?? null ) ) {
			return null;
		}

		$current = $this->latest();
		if ( is_array( $current ) && ! $this->terminal( (string) ( $current['status'] ?? '' ) ) ) {
			return null;
		}

		$now = gmdate( DATE_ATOM );
		$job = array(
			'schema_version'         => self::SCHEMA_VERSION,
			'job_id'                 => wp_generate_uuid4(),
			'package_id'             => $this->package_id(),
			'operation'              => 'local-clone',
			'status'                 => 'created',
			'phase'                  => 'inventory',
			'cursor'                 => null,
			'completed'              => array(
				'files'  => 0,
				'bytes'  => 0,
				'tables' => 0,
				'rows'   => 0,
			),
			'totals'                 => array(
				'files'  => null,
				'bytes'  => null,
				'tables' => null,
				'rows'   => null,
			),
			'last_error_code'        => null,
			'created_at'             => $now,
			'updated_at'             => $now,
			'package_schema_version' => self::PACKAGE_SCHEMA_VERSION,
			'runtime_version'        => defined( 'SEO_GEO_MIGRATION_BRIDGE_VERSION' ) ? SEO_GEO_MIGRATION_BRIDGE_VERSION : '',
			'integrity_state'        => 'pending',
			'plan'                   => $plan,
		);

		$saved = false === get_option( self::OPTION_NAME, false )
			? add_option( self::OPTION_NAME, $job, '', false )
			: update_option( self::OPTION_NAME, $job, false );

		return $saved ? $job : null;
	}

	/**
	 * Persist a normalized resumable job update.
	 *
	 * @param array<string,mixed> $job Job state.
	 */
	public function save( array $job ): bool {
		if (
			! is_string( $job['job_id'] ?? null )
			|| '' === $job['job_id']
			|| ! $this->valid_status( (string) ( $job['status'] ?? '' ) )
			|| ! is_string( $job['phase'] ?? null )
			|| '' === $job['phase']
		) {
			return false;
		}

		$job['schema_version']         = self::SCHEMA_VERSION;
		$job['package_schema_version'] = self::PACKAGE_SCHEMA_VERSION;
		$job['updated_at']             = gmdate( DATE_ATOM );

		return false === get_option( self::OPTION_NAME, false )
			? add_option( self::OPTION_NAME, $job, '', false )
			: update_option( self::OPTION_NAME, $job, false );
	}

	/**
	 * Mark the current non-terminal job as cancelled.
	 */
	public function cancel(): bool {
		$job = $this->latest();
		if ( null === $job ) {
			return true;
		}

		if ( $this->terminal( (string) ( $job['status'] ?? '' ) ) ) {
			return true;
		}

		$job['status']          = 'cancelled';
		$job['phase']           = 'cancelled';
		$job['last_error_code'] = null;

		return $this->save( $job );
	}

	/**
	 * Remove only terminal job state.
	 */
	public function clear_terminal(): bool {
		$job = $this->latest();
		if ( null === $job ) {
			return true;
		}

		if ( ! $this->terminal( (string) ( $job['status'] ?? '' ) ) ) {
			return false;
		}

		return delete_option( self::OPTION_NAME );
	}

	/**
	 * Return whether a state is terminal.
	 *
	 * @param string $status Job status.
	 */
	private function terminal( string $status ): bool {
		return in_array( $status, array( 'completed', 'failed-terminal', 'cancelled' ), true );
	}

	/**
	 * Validate a current/future state from the accepted state-machine contract.
	 *
	 * @param string $status Job status.
	 */
	private function valid_status( string $status ): bool {
		return in_array(
			$status,
			array(
				'created',
				'inventory',
				'database',
				'files',
				'manifest',
				'integrity',
				'completed',
				'validate',
				'prepare-target',
				'restore-database',
				'restore-files',
				'rewrite-environment',
				'harden-sandbox',
				'verify',
				'paused',
				'failed-retryable',
				'failed-terminal',
				'cancelled',
			),
			true
		);
	}

	/**
	 * Generate a package identity without exposing credentials or filesystem paths.
	 */
	private function package_id(): string {
		$host_value = wp_parse_url( home_url( '/' ), PHP_URL_HOST );
		$host       = is_string( $host_value ) ? sanitize_key( str_replace( '.', '-', $host_value ) ) : 'site';
		$random     = substr( str_replace( '-', '', wp_generate_uuid4() ), 0, 8 );

		return sprintf( 'seo-geo-clone-%s-%s-%s', '' !== $host ? $host : 'site', gmdate( 'YmdHis' ), $random );
	}
}
