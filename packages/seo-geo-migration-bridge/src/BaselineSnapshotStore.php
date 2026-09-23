<?php
/**
 * Persistent SEO/GEO migration baseline storage.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge;

use RuntimeException;

/**
 * Stores one explicit migration baseline outside the public output surface.
 */
final class BaselineSnapshotStore {
	/**
	 * Dedicated non-autoloaded migration baseline option.
	 */
	public const OPTION_NAME = 'seo_geo_migration_baseline_v1';

	/**
	 * Persist a snapshot.
	 *
	 * Existing baselines are not replaced unless the caller explicitly opts in.
	 *
	 * @param array<string,mixed> $snapshot Baseline snapshot.
	 * @param bool                $replace  Whether an existing baseline may be replaced.
	 * @return array{saved:bool,id:string|null,saved_at:string|null,sha256:string|null,replaced:bool,reason:string|null}
	 * @throws RuntimeException When the snapshot cannot be encoded.
	 */
	public function save( array $snapshot, bool $replace = false ): array {
		$existing = $this->latest();
		if ( null !== $existing && ! $replace ) {
			return array(
				'saved'    => false,
				'id'       => isset( $existing['id'] ) && is_string( $existing['id'] ) ? $existing['id'] : null,
				'saved_at' => isset( $existing['saved_at'] ) && is_string( $existing['saved_at'] ) ? $existing['saved_at'] : null,
				'sha256'   => isset( $existing['sha256'] ) && is_string( $existing['sha256'] ) ? $existing['sha256'] : null,
				'replaced' => false,
				'reason'   => 'baseline-exists',
			);
		}

		$encoded = wp_json_encode( $snapshot, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		if ( false === $encoded ) {
			throw new RuntimeException( 'Could not encode the migration baseline snapshot.' );
		}

		$envelope = array(
			'schema_version' => 1,
			'id'             => wp_generate_uuid4(),
			'saved_at'       => gmdate( DATE_ATOM ),
			'sha256'         => hash( 'sha256', $encoded ),
			'snapshot'       => $snapshot,
		);

		$saved = null === $existing
			? add_option( self::OPTION_NAME, $envelope, '', false )
			: update_option( self::OPTION_NAME, $envelope, false );

		return array(
			'saved'    => $saved,
			'id'       => $envelope['id'],
			'saved_at' => $envelope['saved_at'],
			'sha256'   => $envelope['sha256'],
			'replaced' => null !== $existing,
			'reason'   => $saved ? null : 'storage-write-failed',
		);
	}

	/**
	 * Return the persisted baseline envelope.
	 *
	 * @return array<string,mixed>|null
	 */
	public function latest(): ?array {
		$value = get_option( self::OPTION_NAME, null );
		if ( ! is_array( $value ) || 1 !== ( $value['schema_version'] ?? null ) || ! isset( $value['snapshot'] ) || ! is_array( $value['snapshot'] ) ) {
			return null;
		}

		return $value;
	}
}
