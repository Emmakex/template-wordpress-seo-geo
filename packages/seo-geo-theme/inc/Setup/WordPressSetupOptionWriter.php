<?php
/**
 * WordPress setup option writer.
 *
 * @package SeoGeoTheme
 */

declare(strict_types=1);

namespace SeoGeo\Theme\Setup;

use stdClass;

/**
 * Isolates all Phase 9E option mutations behind verified writes.
 */
final class WordPressSetupOptionWriter implements SetupOptionWriterInterface {
	/**
	 * Read one option while preserving absence.
	 *
	 * @return array{exists:bool,value:mixed}
	 */
	public function read( string $option_name ): array {
		$sentinel = new stdClass();
		$value    = get_option( $option_name, $sentinel );

		return array(
			'exists' => $value !== $sentinel,
			'value'  => $value !== $sentinel ? $value : null,
		);
	}

	/**
	 * Persist one exact option value and verify read-back.
	 */
	public function write( string $option_name, mixed $value ): bool {
		$current = $this->read( $option_name );
		if ( true === $current['exists'] && $current['value'] === $value ) {
			return true;
		}

		if ( ! update_option( $option_name, $value, false ) ) {
			return false;
		}

		$stored = $this->read( $option_name );

		return true === $stored['exists'] && $stored['value'] === $value;
	}

	/**
	 * Remove one option and verify absence.
	 */
	public function delete( string $option_name ): bool {
		$current = $this->read( $option_name );
		if ( false === $current['exists'] ) {
			return true;
		}

		if ( ! delete_option( $option_name ) ) {
			return false;
		}

		return false === $this->read( $option_name )['exists'];
	}
}
