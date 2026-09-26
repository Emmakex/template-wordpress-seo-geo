<?php
/**
 * Resumable Portable Clone Engine job persistence.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge\Clone;

/**
 * Stores bounded clone/export/import job state in one non-autoloaded option.
 */
final class CloneJobStore {
	/**
	 * Job storage option.
	 */
	public const OPTION_NAME = 'seo_geo_migration_clone_jobs_v1';

	/**
	 * Job schema version.
	 */
	public const SCHEMA_VERSION = 1;

	/**
	 * Maximum retained job records in the first contract.
	 */
	private const MAX_JOBS = 20;

	/**
	 * Return accepted job operation types.
	 *
	 * @return list<string>
	 */
	public static function allowed_operations(): array {
		return array( 'local-clone', 'export', 'import' );
	}

	/**
	 * Return accepted resumable job phases.
	 *
	 * @return list<string>
	 */
	public static function allowed_phases(): array {
		return array(
			'created',
			'inventory',
			'database',
			'files',
			'manifest',
			'integrity',
			'validate',
			'prepare-target',
			'restore-database',
			'restore-files',
			'rewrite-environment',
			'finalize-preflight',
			'harden-sandbox',
			'verify',
			'completed',
		);
	}

	/**
	 * Return accepted job statuses.
	 *
	 * @return list<string>
	 */
	public static function allowed_statuses(): array {
		return array(
			'active',
			'paused',
			'failed-retryable',
			'failed-terminal',
			'completed',
			'cancelled',
		);
	}

	/**
	 * Create one resumable planning job.
	 *
	 * @param string      $operation Operation type.
	 * @param string|null $job_id    Optional deterministic fixture/job identifier.
	 * @return array<string,mixed>|null
	 */
	public function create( string $operation, ?string $job_id = null ): ?array {
		if ( ! in_array( $operation, self::allowed_operations(), true ) ) {
			return null;
		}

		$job_id = null === $job_id ? 'clone-' . wp_generate_uuid4() : $job_id;
		if ( ! $this->valid_job_id( $job_id ) ) {
			return null;
		}

		$jobs = $this->all();
		if ( isset( $jobs[ $job_id ] ) ) {
			return $jobs[ $job_id ];
		}

		$now = gmdate( DATE_ATOM );
		$job = array(
			'schema_version' => self::SCHEMA_VERSION,
			'job_id'         => $job_id,
			'operation'      => $operation,
			'phase'          => 'created',
			'status'         => 'active',
			'cursor'         => null,
			'counters'       => array(
				'completed' => 0,
				'total'     => null,
			),
			'error_code'     => null,
			'created_at'     => $now,
			'updated_at'     => $now,
		);

		$jobs[ $job_id ] = $job;
		$jobs            = $this->bounded_jobs( $jobs );

		if ( false === get_option( self::OPTION_NAME, false ) ) {
			$saved = add_option( self::OPTION_NAME, $jobs, '', false );
		} else {
			$saved = update_option( self::OPTION_NAME, $jobs, false );
		}

		return $saved ? $job : null;
	}

	/**
	 * Return all normalized jobs keyed by job ID.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public function all(): array {
		$value = get_option( self::OPTION_NAME, array() );
		if ( ! is_array( $value ) ) {
			return array();
		}

		$jobs = array();
		foreach ( $value as $job_id => $job ) {
			if ( ! is_string( $job_id ) || ! is_array( $job ) ) {
				continue;
			}

			$normalized = $this->normalize_job( $job_id, $job );
			if ( null !== $normalized ) {
				$jobs[ $job_id ] = $normalized;
			}
		}

		return $jobs;
	}

	/**
	 * Return one normalized job.
	 *
	 * @param string $job_id Job identifier.
	 * @return array<string,mixed>|null
	 */
	public function get( string $job_id ): ?array {
		$jobs = $this->all();

		return $jobs[ $job_id ] ?? null;
	}

	/**
	 * Return the most recently updated job.
	 *
	 * @return array<string,mixed>|null
	 */
	public function latest(): ?array {
		$jobs = array_values( $this->all() );
		if ( array() === $jobs ) {
			return null;
		}

		usort(
			$jobs,
			static fn( array $left, array $right ): int => strcmp(
				(string) ( $right['updated_at'] ?? '' ),
				(string) ( $left['updated_at'] ?? '' )
			)
		);

		return $jobs[0];
	}

	/**
	 * Persist resumable phase/cursor/counter progress.
	 *
	 * @param string              $job_id    Job identifier.
	 * @param string              $phase     Current phase.
	 * @param string|int|null     $cursor    Bounded resumable cursor.
	 * @param array<string,mixed> $counters  Completed/total counters.
	 * @return array<string,mixed>|null
	 */
	public function update_progress( string $job_id, string $phase, string|int|null $cursor, array $counters ): ?array {
		if ( ! in_array( $phase, self::allowed_phases(), true ) || ! $this->valid_cursor( $cursor ) ) {
			return null;
		}

		$jobs = $this->all();
		if ( ! isset( $jobs[ $job_id ] ) ) {
			return null;
		}

		$completed = isset( $counters['completed'] ) ? max( 0, (int) $counters['completed'] ) : 0;
		$total     = isset( $counters['total'] ) ? max( 0, (int) $counters['total'] ) : null;
		if ( null !== $total && $completed > $total ) {
			return null;
		}

		$jobs[ $job_id ]['phase']      = $phase;
		$jobs[ $job_id ]['cursor']     = $cursor;
		$jobs[ $job_id ]['counters']   = array(
			'completed' => $completed,
			'total'     => $total,
		);
		$jobs[ $job_id ]['updated_at'] = gmdate( DATE_ATOM );

		if ( ! update_option( self::OPTION_NAME, $jobs, false ) ) {
			return $jobs[ $job_id ];
		}

		return $jobs[ $job_id ];
	}

	/**
	 * Persist one bounded job status transition.
	 *
	 * @param string      $job_id     Job identifier.
	 * @param string      $status     New status.
	 * @param string|null $error_code Optional bounded machine-readable error code.
	 * @return array<string,mixed>|null
	 */
	public function transition( string $job_id, string $status, ?string $error_code = null ): ?array {
		if ( ! in_array( $status, self::allowed_statuses(), true ) || ! $this->valid_error_code( $error_code ) ) {
			return null;
		}

		$jobs = $this->all();
		if ( ! isset( $jobs[ $job_id ] ) ) {
			return null;
		}

		$jobs[ $job_id ]['status']     = $status;
		$jobs[ $job_id ]['error_code'] = $error_code;
		$jobs[ $job_id ]['updated_at'] = gmdate( DATE_ATOM );

		if ( 'completed' === $status ) {
			$jobs[ $job_id ]['phase'] = 'completed';
		}

		if ( ! update_option( self::OPTION_NAME, $jobs, false ) ) {
			return $jobs[ $job_id ];
		}

		return $jobs[ $job_id ];
	}

	/**
	 * Normalize one stored job.
	 *
	 * @param string              $job_id Job identifier.
	 * @param array<string,mixed> $job    Stored job.
	 * @return array<string,mixed>|null
	 */
	private function normalize_job( string $job_id, array $job ): ?array {
		$operation = $job['operation'] ?? null;
		$phase     = $job['phase'] ?? null;
		$status    = $job['status'] ?? null;

		if (
			! $this->valid_job_id( $job_id )
			|| ! is_string( $operation )
			|| ! in_array( $operation, self::allowed_operations(), true )
			|| ! is_string( $phase )
			|| ! in_array( $phase, self::allowed_phases(), true )
			|| ! is_string( $status )
			|| ! in_array( $status, self::allowed_statuses(), true )
		) {
			return null;
		}

		$cursor = $job['cursor'] ?? null;
		if ( ! $this->valid_cursor( $cursor ) ) {
			$cursor = null;
		}

		$counters  = is_array( $job['counters'] ?? null ) ? $job['counters'] : array();
		$completed = isset( $counters['completed'] ) ? max( 0, (int) $counters['completed'] ) : 0;
		$total     = isset( $counters['total'] ) ? max( 0, (int) $counters['total'] ) : null;

		return array(
			'schema_version' => self::SCHEMA_VERSION,
			'job_id'         => $job_id,
			'operation'      => $operation,
			'phase'          => $phase,
			'status'         => $status,
			'cursor'         => $cursor,
			'counters'       => array(
				'completed' => $completed,
				'total'     => $total,
			),
			'error_code'     => is_string( $job['error_code'] ?? null ) ? $job['error_code'] : null,
			'created_at'     => is_string( $job['created_at'] ?? null ) ? $job['created_at'] : '',
			'updated_at'     => is_string( $job['updated_at'] ?? null ) ? $job['updated_at'] : '',
		);
	}

	/**
	 * Keep only the most recent bounded set of jobs.
	 *
	 * @param array<string,array<string,mixed>> $jobs Jobs keyed by ID.
	 * @return array<string,array<string,mixed>>
	 */
	private function bounded_jobs( array $jobs ): array {
		if ( count( $jobs ) <= self::MAX_JOBS ) {
			return $jobs;
		}

		uasort(
			$jobs,
			static fn( array $left, array $right ): int => strcmp(
				(string) ( $right['updated_at'] ?? '' ),
				(string) ( $left['updated_at'] ?? '' )
			)
		);

		return array_slice( $jobs, 0, self::MAX_JOBS, true );
	}

	/**
	 * Validate a bounded job identifier.
	 *
	 * @param string $job_id Job identifier.
	 */
	private function valid_job_id( string $job_id ): bool {
		return 1 === preg_match( '/^[A-Za-z0-9][A-Za-z0-9._:-]{7,127}$/', $job_id );
	}

	/**
	 * Validate a bounded resumable cursor.
	 *
	 * @param string|int|null $cursor Cursor value.
	 */
	private function valid_cursor( string|int|null $cursor ): bool {
		if ( null === $cursor || is_int( $cursor ) ) {
			return true;
		}

		return 160 >= strlen( $cursor ) && 1 === preg_match( '/^[A-Za-z0-9._:\/-]*$/', $cursor );
	}

	/**
	 * Validate one bounded machine-readable error code.
	 *
	 * @param string|null $error_code Error code.
	 */
	private function valid_error_code( ?string $error_code ): bool {
		return null === $error_code
			|| ( 120 >= strlen( $error_code ) && 1 === preg_match( '/^[a-z0-9][a-z0-9._-]*$/', $error_code ) );
	}
}
