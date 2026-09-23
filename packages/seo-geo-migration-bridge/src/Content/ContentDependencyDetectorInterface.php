<?php
/**
 * Content dependency detection contract.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge\Content;

use WP_Post;

/**
 * Detects builder/content coupling without exporting source content.
 */
interface ContentDependencyDetectorInterface {
	/**
	 * Return a stable dependency identifier.
	 */
	public function id(): string;

	/**
	 * Inspect one WordPress resource.
	 *
	 * @param WP_Post $post WordPress post/resource.
	 * @return array{coupled:bool,evidence:list<string>}
	 */
	public function inspect( WP_Post $post ): array;
}
