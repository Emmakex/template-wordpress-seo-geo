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
 * Builds the bounded manifest shared by local clone and future export/import payloads.
 */
final class PortablePackageManifest {
	/**
	 * Build a package contract from one clone/export/import job.
	 *
	 * @param array<string,mixed> $job Clone job.
	 * @return array<string,mixed>
	 */
	public function build( array $job ): array {
		$plan     = is_array( $job['plan'] ?? null ) ? $job['plan'] : array();
		$target   = is_array( $plan['target'] ?? null ) ? $plan['target'] : array();
		$baseline = ( new BaselineSnapshotStore() )->latest();

		return array(
			'schema_version' => PortableCloneJobStore::PACKAGE_SCHEMA_VERSION,
			'package_id'     => is_string( $job['package_id'] ?? null ) ? $job['package_id'] : '',
			'operation'      => is_string( $job['operation'] ?? null ) ? $job['operation'] : '',
			'generated_at'   => gmdate( DATE_ATOM ),
			'source'         => array(
				'home_url'          => trailingslashit( home_url( '/' ) ),
				'site_url'          => trailingslashit( site_url( '/' ) ),
				'wordpress_version' => get_bloginfo( 'version' ),
				'php_version'       => PHP_VERSION,
				'baseline_id'       => is_string( $baseline['id'] ?? null ) ? $baseline['id'] : null,
				'baseline_sha256'   => is_string( $baseline['sha256'] ?? null ) ? $baseline['sha256'] : null,
			),
			'target'         => array(
				'mode'      => is_string( $job['operation'] ?? null ) ? $job['operation'] : '',
				'directory' => is_string( $target['directory'] ?? null ) ? $target['directory'] : '',
				'home_url'  => is_string( $target['home_url'] ?? null ) ? $target['home_url'] : '',
			),
			'payload'        => array(
				'database' => $this->empty_payload_class( 'segmented-sql-v1' ),
				'uploads'  => $this->empty_payload_class( 'segmented-files-v1' ),
				'plugins'  => $this->empty_payload_class( 'segmented-files-v1' ),
				'themes'   => $this->empty_payload_class( 'segmented-files-v1' ),
			),
			'integrity'      => array(
				'algorithm'       => 'sha256',
				'manifest_sha256' => null,
				'package_sha256'  => null,
				'verification'    => is_string( $job['integrity_state'] ?? null ) ? $job['integrity_state'] : 'pending',
			),
			'sandbox'        => array(
				'hardening_required' => true,
				'preflight_required' => true,
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

	/**
	 * Return an empty payload class before export implementation exists.
	 *
	 * @param string $format Future segmented payload format.
	 * @return array{format:string,segments:array<int,mixed>}
	 */
	private function empty_payload_class( string $format ): array {
		return array(
			'format'   => $format,
			'segments' => array(),
		);
	}
}
