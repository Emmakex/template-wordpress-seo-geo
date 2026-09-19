<?php
/**
 * Native block-editor detector.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge\Builders;

/**
 * Detects the WordPress native block capability without scanning content.
 */
final class NativeBlocksDetector implements BuilderDetectorInterface {
	/**
	 * {@inheritDoc}
	 */
	public function id(): string {
		return 'native-blocks';
	}

	/**
	 * {@inheritDoc}
	 */
	public function detect( array $plugins, array $themes ): array {
		unset( $plugins, $themes );

		$available = function_exists( 'parse_blocks' );

		return array(
			'id'                     => $this->id(),
			'installed'              => $available,
			'active'                 => $available,
			'content_scan_performed' => false,
			'evidence'               => $available ? array( 'wordpress-core-block-api' ) : array(),
		);
	}
}
