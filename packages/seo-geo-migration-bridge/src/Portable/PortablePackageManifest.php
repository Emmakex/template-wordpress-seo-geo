<?php
/**
 * Portable clone/export package manifest contract.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge\Portable;

use SeoGeo\MigrationBridge\BaselineSnapshotStore;

/**
 * Builds the non-secret manifest shared by local clone and future export/import payloads.
 */
final class PortablePackageManifest {
	/**
	 * Build a package contract from one planned clone job.
	 *
	 * @param array<string,mixed> $job Planned clone job.
	 * @return array<string,mixed>
	 */
	public function build( array $job ): array {
		$plan     = is_array( $job['plan'] ?? null ) ? $job['plan'] : array();
		$target   = is_array( $plan['target'] ?? null ) ? $plan['target'] : array();
		$baseline = ( new BaselineSnapshotStore() )->latest();

		return array(
			'schema_version' => 1,
			'kind'           => 'seo-geo-portable-site-package',
			'package_id'     => is_string( $job['job_id'] ?? null ) ? $job['job_id'] : '',
			'generated_at'   => gmdate( DATE_ATOM ),
			'source'         => array(
				'home_url'          => trailingslashit( home_url( '/' ) ),
				'wordpress_version' => get_bloginfo( 'version' ),
				'php_version'       => PHP_VERSION,
				'baseline_id'       => is_string( $baseline['id'] ?? null ) ? $baseline['id'] : null,
				'baseline_sha256'   => is_string( $baseline['sha256'] ?? null ) ? $baseline['sha256'] : null,
			),
			'target'         => array(
				'mode'      => is_string( $job['mode'] ?? null ) ? $job['mode'] : '',
				'directory' => is_string( $target['directory'] ?? null ) ? $target['directory'] : '',
				'home_url'  => is_string( $target['home_url'] ?? null ) ? $target['home_url'] : '',
			),
			'payload'        => array(
				'files'    => array(
					'format'   => 'segmented-files-v1',
					'segments' => array(),
				),
				'database' => array(
					'format'   => 'segmented-sql-v1',
					'segments' => array(),
				),
			),
			'integrity'      => array(
				'algorithm'       => 'sha256',
				'manifest_sha256' => null,
			),
			'privacy'        => array(
				'contains_private_site_data'         => true,
				'repository_safe'                    => false,
				'wp_config_included'                 => false,
				'credentials_included'               => false,
				'auth_salts_included'                => false,
				'package_requires_protected_storage' => true,
			),
			'safety'         => array(
				'production_restore_allowed'         => false,
				'blind_dynamic_data_restore_allowed' => false,
				'target_integrity_required'          => true,
				'resume_required'                    => true,
			),
		);
	}
}
