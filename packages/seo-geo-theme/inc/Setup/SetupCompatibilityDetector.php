<?php
/**
 * Theme setup compatibility detector.
 *
 * @package SeoGeoTheme
 */

declare(strict_types=1);

namespace SeoGeo\Theme\Setup;

use SeoGeo\Core\Integrations\RuntimeIntegrationDetector;

/**
 * Reports optional external systems without turning them into dependencies.
 */
final class SetupCompatibilityDetector {
	/**
	 * Core integration detector.
	 *
	 * @var RuntimeIntegrationDetector
	 */
	private RuntimeIntegrationDetector $integrations;

	/**
	 * Construct the detector.
	 *
	 * @param RuntimeIntegrationDetector|null $integrations Optional Core detector.
	 */
	public function __construct( ?RuntimeIntegrationDetector $integrations = null ) {
		$this->integrations = $integrations ?? new RuntimeIntegrationDetector();
	}

	/**
	 * Return informational providers and compatibility warnings.
	 *
	 * @return array<string,mixed>
	 */
	public function detect(): array {
		$seo_provider      = $this->integrations->seo_provider();
		$language_provider = $this->integrations->language_provider();
		$active_plugins    = $this->active_plugins();
		$warnings          = array();

		if ( 'native' !== $seo_provider ) {
			$warnings[] = $this->warning( 'external-seo-provider', $seo_provider );
		}

		if ( 'native' !== $language_provider ) {
			$warnings[] = $this->warning( 'external-language-provider', $language_provider );
		}

		foreach (
			array(
				'woocommerce/woocommerce.php' => array( 'commerce-provider', 'woocommerce' ),
				'elementor/elementor.php'     => array( 'legacy-builder', 'elementor' ),
				'divi-builder/divi-builder.php' => array( 'legacy-builder', 'divi' ),
			) as $basename => $warning
		) {
			if ( in_array( $basename, $active_plugins, true ) ) {
				$warnings[] = $this->warning( $warning[0], $warning[1] );
			}
		}

		return array(
			'providers' => array(
				'seo'      => $seo_provider,
				'language' => $language_provider,
			),
			'warnings'  => $warnings,
			'safety'    => array(
				'detection_only'          => true,
				'integration_required'    => false,
				'plugins_mutated'         => false,
				'credentials_read'        => false,
				'credentials_written'     => false,
			),
		);
	}

	/**
	 * Return normalized active plugin basenames.
	 *
	 * @return list<string>
	 */
	private function active_plugins(): array {
		$plugins = get_option( 'active_plugins', array() );
		if ( ! is_array( $plugins ) ) {
			return array();
		}

		$plugins = array_values( array_filter( $plugins, 'is_string' ) );
		sort( $plugins );

		return $plugins;
	}

	/**
	 * Build one bounded informational warning.
	 *
	 * @param string $type     Warning type.
	 * @param string $provider Provider identifier.
	 * @return array{type:string,provider:string,severity:string,required:bool}
	 */
	private function warning( string $type, string $provider ): array {
		return array(
			'type'     => $type,
			'provider' => $provider,
			'severity' => 'advisory',
			'required' => false,
		);
	}
}
