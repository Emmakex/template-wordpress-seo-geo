<?php
/**
 * Persistent incremental SEO/GEO baseline capture state.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge;

/**
 * Stores one non-autoloaded resumable capture state.
 */
final class IncrementalBaselineStore {
	/**
	 * Dedicated non-autoloaded progress option.
	 */
	public const OPTION_NAME = 'seo_geo_migration_baseline_progress_v1';

	/**
	 * Return the current progress state.
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
	 * Save the current progress state.
	 *
	 * @param array<string,mixed> $state Capture state.
	 */
	public function save( array $state ): bool {
		$state['schema_version'] = 1;
		$state['updated_at']     = gmdate( DATE_ATOM );

		$existing = $this->latest();

		return null === $existing
			? add_option( self::OPTION_NAME, $state, '', false )
			: update_option( self::OPTION_NAME, $state, false );
	}
}
