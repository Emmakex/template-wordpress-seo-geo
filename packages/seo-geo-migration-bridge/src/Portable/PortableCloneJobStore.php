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
	 * Return the current normalized job.
	 *
	 * @return array<string,mixed>|null
	 */
	public function latest(): ?array {
		$value = get_option( self::OPTION_NAME, null );

		if ( ! is_array( $value ) || 1 !== ( $value['schema_version'] ?? null ) ) {
			return null;
		}

		return $value;
	}

	/**
	 * Start one planned job from a ready clone plan.
	 *
	 * @param array<string,mixed> $plan Ready portable clone plan.
	 * @return array<string,mixed>|null
	 */
	public function start( array $plan ): ?array {
		if ( true !== ( $plan['ready'] ?? false ) || 'local-subdirectory' !== ( $plan['mode'] ?? null ) ) {
			return null;
		}

		$current = $this->latest();
		if ( is_array( $current ) && in_array( $current['status'] ?? null, array( 'planned', 'running' ), true ) ) {
			return null;
		}

		$now = gmdate( DATE_ATOM );
		$job = array(
			'schema_version' => 1,
			'job_id'         => wp_generate_uuid4(),
			'kind'           => 'portable-clone',
			'mode'           => 'local-subdirectory',
			'status'         => 'planned',
			'stage'          => 'inventory',
			'created_at'     => $now,
			'updated_at'     => $now,
			'plan'           => $plan,
			'progress'       => array(
				'files_discovered' => 0,
				'files_copied'     => 0,
				'bytes_copied'     => 0,
				'tables_total'     => 0,
				'tables_copied'    => 0,
				'rows_copied'      => 0,
			),
			'errors'         => array(),
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
		if ( ! is_string( $job['job_id'] ?? null ) || '' === $job['job_id'] ) {
			return false;
		}

		$job['schema_version'] = 1;
		$job['updated_at']     = gmdate( DATE_ATOM );

		return false === get_option( self::OPTION_NAME, false )
			? add_option( self::OPTION_NAME, $job, '', false )
			: update_option( self::OPTION_NAME, $job, false );
	}

	/**
	 * Mark the current planned/running job as cancelled.
	 */
	public function cancel(): bool {
		$job = $this->latest();
		if ( null === $job ) {
			return true;
		}

		$job['status'] = 'cancelled';
		$job['stage']  = 'cancelled';

		return $this->save( $job );
	}

	/**
	 * Remove only terminal planning state.
	 */
	public function clear_terminal(): bool {
		$job = $this->latest();
		if ( null === $job ) {
			return true;
		}

		if ( ! in_array( $job['status'] ?? null, array( 'cancelled', 'complete', 'error' ), true ) ) {
			return false;
		}

		return delete_option( self::OPTION_NAME );
	}
}
