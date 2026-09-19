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
	 * Return the stable builder identifier.
	 */
	public function id(): string {
		return 'native-blocks';
	}

	/**
	 * Detect native block availability.
	 *
	 * @param list<array<string, mixed>> $plugins Plugin inventory.
	 * @param list<array<string, mixed>> $themes  Theme inventory.
	 * @return array<string, mixed>
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
