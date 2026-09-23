<?php
/**
 * Internal setup write failure.
 *
 * @package SeoGeoTheme
 */

declare(strict_types=1);

namespace SeoGeo\Theme\Setup;

use RuntimeException;

/**
 * Identifies the exact option whose verified setup write failed.
 */
final class SetupWriteFailure extends RuntimeException {
	/**
	 * Failed option name.
	 *
	 * @var string
	 */
	private string $failed_option_name;

	/**
	 * Create the failure.
	 *
	 * @param string $option_name Failed option name.
	 */
	public function __construct( string $option_name ) {
		parent::__construct( 'Setup option write failed.' );
		$this->failed_option_name = $option_name;
	}

	/**
	 * Return the failed option name.
	 */
	public function option_name(): string {
		return $this->failed_option_name;
	}
}
