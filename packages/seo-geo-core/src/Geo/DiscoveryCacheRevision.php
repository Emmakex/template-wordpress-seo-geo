<?php
/**
 * GEO discovery cache revision.
 *
 * @package SeoGeoCore
 */

declare(strict_types=1);

namespace SeoGeo\Core\Geo;

/**
 * Provides one mutation revision for cache revalidation and external purge hooks.
 */
final class DiscoveryCacheRevision {
	/**
	 * Persisted revision token.
	 */
	public const OPTION_NAME = 'seo_geo_discovery_cache_revision';

	/**
	 * Prevent duplicate bumps inside one WordPress request.
	 *
	 * @var bool
	 */
	private bool $bumped = false;

	/**
	 * Return the current revision without writing during read-only requests.
	 */
	public function current(): string {
		$value = get_option( self::OPTION_NAME, 'initial' );

		if ( ! is_string( $value ) ) {
			return 'initial';
		}

		$value = trim( $value );

		return '' !== $value ? $value : 'initial';
	}

	/**
	 * Replace the revision after one relevant mutation.
	 *
	 * One bump per request is sufficient because clients cannot observe
	 * intermediate mutations within the same completed WordPress request.
	 *
	 * @param string   $reason      Stable mutation reason.
	 * @param int|null $resource_id Optional WordPress resource ID.
	 */
	public function bump( string $reason, ?int $resource_id = null ): string {
		if ( $this->bumped ) {
			return $this->current();
		}

		$revision = wp_generate_uuid4();

		update_option( self::OPTION_NAME, $revision, false );
		$this->bumped = true;

		/**
		 * Fires after public SEO/GEO discovery state may have changed.
		 *
		 * External cache/CDN integrations may use this action to purge their
		 * own caches. Core does not assume ownership of third-party caches.
		 *
		 * @param string   $revision    New mutation revision.
		 * @param string   $reason      Mutation reason.
		 * @param int|null $resource_id Optional affected resource ID.
		 */
		do_action( 'seo_geo_discovery_cache_invalidated', $revision, $reason, $resource_id );

		return $revision;
	}

	/**
	 * Build a strong ETag scoped to one public discovery surface/resource.
	 *
	 * @param string   $surface     Stable surface key.
	 * @param int|null $resource_id Optional WordPress resource ID.
	 */
	public function etag( string $surface, ?int $resource_id = null ): string {
		$key = $surface . '|' . (string) ( $resource_id ?? 0 ) . '|' . $this->current();

		return '"seo-geo-' . substr( hash( 'sha256', $key ), 0, 24 ) . '"';
	}
}
