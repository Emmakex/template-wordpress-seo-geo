<?php
/**
 * Portable Clone Engine versioned planning manifest.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge\Clone;

/**
 * Builds bounded clone-package planning metadata before payload creation exists.
 */
final class CloneManifest {
	/**
	 * Portable clone package schema version.
	 */
	public const SCHEMA_VERSION = 1;

	/**
	 * Build the Phase 10E.2A.1 planning manifest.
	 *
	 * @param array<string,mixed>|null $job Optional persisted clone job.
	 * @return array<string,mixed>
	 */
	public function build( ?array $job = null ): array {
		$operation = is_string( $job['operation'] ?? null ) ? $job['operation'] : null;
		$job_id    = is_string( $job['job_id'] ?? null ) ? $job['job_id'] : null;

		return array(
			'schema_version' => self::SCHEMA_VERSION,
			'mode'           => 'portable-clone-planning',
			'package_id'     => $job_id,
			'operation'      => $operation,
			'source'         => array(
				'home_url'          => home_url( '/' ),
				'site_url'          => site_url( '/' ),
				'wordpress_version' => get_bloginfo( 'version' ),
				'php_version'       => PHP_VERSION,
			),
			'payload'        => array(
				'database' => array( 'status' => 'not-started' ),
				'uploads'  => array( 'status' => 'not-started' ),
				'plugins'  => array( 'status' => 'not-started' ),
				'themes'   => array( 'status' => 'not-started' ),
			),
			'integrity'      => array(
				'algorithm'        => 'sha256',
				'package_checksum' => null,
				'verified'         => false,
			),
			'resumability'   => array(
				'job_schema_version' => CloneJobStore::SCHEMA_VERSION,
				'batched'            => true,
				'single_request_job' => false,
			),
			'safety'         => array(
				'production_source_mutation_allowed'  => false,
				'production_database_restore_allowed' => false,
				'third_party_clone_plugin_required'   => false,
				'payload_created_in_this_phase'       => false,
				'credentials_in_manifest'             => false,
				'private_payload_repository_safe'     => false,
			),
		);
	}
}
