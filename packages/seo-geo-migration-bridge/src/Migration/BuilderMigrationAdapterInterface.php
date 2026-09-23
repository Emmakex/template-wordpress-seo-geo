<?php
/**
 * Builder migration adapter contract.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge\Migration;

use WP_Post;

/**
 * Defines one explicit legacy-builder to native-block transformation boundary.
 */
interface BuilderMigrationAdapterInterface {
	/**
	 * Stable adapter identifier.
	 */
	public function id(): string;

	/**
	 * Produce a safe migration plan without exporting source content.
	 *
	 * @param WP_Post $post Resource to inspect.
	 * @return array{
	 *     supported:bool,
	 *     source:string,
	 *     target:string,
	 *     operations:list<string>,
	 *     blockers:list<string>,
	 *     warnings:list<string>,
	 *     media_ids:list<int>
	 * }
	 */
	public function plan( WP_Post $post ): array;

	/**
	 * Build the internal mutation payload after the plan is accepted.
	 *
	 * @param WP_Post $post Resource to transform.
	 * @return array{
	 *     content:string,
	 *     delete_meta:list<string>,
	 *     update_meta:array<string,mixed>
	 * }
	 */
	public function transform( WP_Post $post ): array;
}
