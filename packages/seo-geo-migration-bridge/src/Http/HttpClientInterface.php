<?php
/**
 * HTTP client contract for public migration snapshots.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge\Http;

/**
 * Fetches one public URL without granting authentication or redirect authority.
 */
interface HttpClientInterface {
	/**
	 * Fetch one URL.
	 *
	 * @return array{
	 *     status:int,
	 *     body:string,
	 *     headers:array{content_type:string,location:string,x_robots_tag:string},
	 *     error:string|null
	 * }
	 */
	public function get( string $url ): array;
}
