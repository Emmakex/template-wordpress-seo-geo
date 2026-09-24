<?php
/**
 * Setup option mutation boundary.
 *
 * @package SeoGeoTheme
 */

declare(strict_types=1);

namespace SeoGeo\Theme\Setup;

/**
 * Abstracts WordPress option writes so setup execution can prove rollback.
 */
interface SetupOptionWriterInterface {
	/**
	 * Read one option while distinguishing absent from falsey values.
	 *
	 * @param string $option_name Option name.
	 * @return array{exists:bool,value:mixed}
	 */
	public function read( string $option_name ): array;

	/**
	 * Persist one exact option value.
	 *
	 * @param string $option_name Option name.
	 * @param mixed  $value       Exact option value.
	 */
	public function write( string $option_name, mixed $value ): bool;

	/**
	 * Remove one option.
	 *
	 * @param string $option_name Option name.
	 */
	public function delete( string $option_name ): bool;
}
