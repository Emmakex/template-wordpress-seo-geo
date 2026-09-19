<?php
/**
 * Divi detector.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge\Builders;

/**
 * Detects Divi from installed themes or the Divi Builder plugin.
 */
final class DiviDetector implements BuilderDetectorInterface {
	/**
	 * {@inheritDoc}
	 */
	public function id(): string {
		return 'divi';
	}

	/**
	 * {@inheritDoc}
	 */
	public function detect( array $plugins, array $themes ): array {
		$evidence = array();
		$active   = false;

		foreach ( $plugins as $plugin ) {
			$basename = $plugin['basename'] ?? null;
			if ( ! is_string( $basename ) || ! str_starts_with( $basename, 'divi-builder/' ) ) {
				continue;
			}

			$evidence[] = 'plugin:' . $basename;
			$active     = $active || true === ( $plugin['active'] ?? false );
		}

		foreach ( $themes as $theme ) {
			$stylesheet = $theme['stylesheet'] ?? null;
			$template   = $theme['template'] ?? null;
			$name       = $theme['name'] ?? null;
			$haystack   = strtolower(
				implode(
					' ',
					array_filter(
						array( $stylesheet, $template, $name ),
						'is_string'
					)
				)
			);

			if ( '' === $haystack || ! str_contains( $haystack, 'divi' ) ) {
				continue;
			}

			$evidence[] = 'theme:' . ( is_string( $stylesheet ) ? $stylesheet : 'divi' );
			$active     = $active || 'active' === ( $theme['status'] ?? null );
		}

		sort( $evidence );

		return array(
			'id'                     => $this->id(),
			'installed'              => array() !== $evidence,
			'active'                 => $active,
			'content_scan_performed' => false,
			'evidence'               => $evidence,
		);
	}
}
