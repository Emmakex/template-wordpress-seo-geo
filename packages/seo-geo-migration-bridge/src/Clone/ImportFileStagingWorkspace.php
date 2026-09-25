<?php
/**
 * Portable Clone destination file-staging facade.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge\Clone;

/**
 * Delegates every destination-staging filesystem mutation to ExportWorkspace.
 */
final class ImportFileStagingWorkspace {
	/**
	 * Filesystem authority.
	 *
	 * @var ExportWorkspace
	 */
	private ExportWorkspace $workspace;

	/**
	 * Construct facade.
	 *
	 * @param ExportWorkspace|null $workspace Optional filesystem authority.
	 */
	public function __construct( ?ExportWorkspace $workspace = null ) {
		$this->workspace = $workspace ?? new ExportWorkspace();
	}

	/**
	 * Ensure one protected job-owned staging root.
	 *
	 * @param string $job_id Clone job identifier.
	 */
	public function ensure( string $job_id ): ?string {
		return $this->workspace->destination_staging_ensure( $job_id );
	}

	/**
	 * Return one existing job-owned staging root.
	 *
	 * @param string $job_id Clone job identifier.
	 */
	public function root_path( string $job_id ): ?string {
		return $this->workspace->destination_staging_root_path( $job_id );
	}

	/**
	 * Return deterministic filesystem-safe staging key.
	 *
	 * @param string $job_id Clone job identifier.
	 */
	public function root_key( string $job_id ): string {
		return $this->workspace->destination_staging_root_key( $job_id );
	}

	/**
	 * Stage one already-verified payload file atomically.
	 *
	 * @param string $job_id         Clone job identifier.
	 * @param string $root_id        uploads/plugins/themes.
	 * @param string $relative       Root-relative payload path.
	 * @param string $source         Absolute verified extracted source path.
	 * @param int    $expected_bytes Expected bytes.
	 * @param string $expected_hash  Expected SHA-256.
	 * @return array{path:string,bytes:int,sha256:string}|null
	 */
	public function stage_file(
		string $job_id,
		string $root_id,
		string $relative,
		string $source,
		int $expected_bytes,
		string $expected_hash
	): ?array {
		return $this->workspace->destination_stage_file(
			$job_id,
			$root_id,
			$relative,
			$source,
			$expected_bytes,
			$expected_hash
		);
	}

	/**
	 * Return one staged file identity.
	 *
	 * @param string $job_id   Clone job identifier.
	 * @param string $root_id  uploads/plugins/themes.
	 * @param string $relative Root-relative file path.
	 * @return array{path:string,bytes:int,sha256:string}|null
	 */
	public function file_info( string $job_id, string $root_id, string $relative ): ?array {
		return $this->workspace->destination_staged_file_info( $job_id, $root_id, $relative );
	}

	/**
	 * Confirm a new job has no staged payload files.
	 *
	 * @param string $job_id Clone job identifier.
	 */
	public function payload_empty( string $job_id ): bool {
		return $this->workspace->destination_staging_payload_empty( $job_id );
	}

	/**
	 * Summarize all staged payload files.
	 *
	 * @param string $job_id Clone job identifier.
	 * @return array{files:int,bytes:int}|null
	 */
	public function payload_summary( string $job_id ): ?array {
		return $this->workspace->destination_staging_payload_summary( $job_id );
	}

	/**
	 * Delete one job-owned staging tree.
	 *
	 * @param string $job_id Clone job identifier.
	 */
	public function cleanup( string $job_id ): bool {
		return $this->workspace->destination_staging_cleanup( $job_id );
	}

	/**
	 * Return normalized staging base path.
	 */
	public function base_path(): string {
		return $this->workspace->destination_staging_base_path();
	}
}
