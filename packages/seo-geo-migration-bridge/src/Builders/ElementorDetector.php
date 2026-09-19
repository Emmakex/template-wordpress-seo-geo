<?php
/**
 * Elementor detector.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge\Builders;

/**
 * Detects Elementor packages without loading or modifying Elementor data.
 */
final class ElementorDetector implements BuilderDetectorInterface {
	/**
	 * {@inheritDoc}
	 */
	public function id(): string {
		return 'elementor';
	}

	/**
	 * {@inheritDoc}
	 */
	public function detect( array $plugins, array $themes ): array {
		unset( $themes );

		$matches = array();
		$active  = false;

		foreach ( $plugins as $plugin ) {
			$basename = $plugin['basename'] ?? null;
			if ( ! is_string( $basename ) ) {
				continue;
			}

			if ( ! str_starts_with( $basename, 'elementor/' ) && ! str_starts_with( $basename, 'elementor-pro/' ) ) {
				continue;
			}

			$matches[] = $basename;
			$active    = $active || true === ( $plugin['active'] ?? false );
		}

		sort( $matches );

		return array(
			'id'                     => $this->id(),
			'installed'              => array() !== $matches,
			'active'                 => $active,
			'content_scan_performed' => false,
			'evidence'               => $matches,
		);
	}
}
