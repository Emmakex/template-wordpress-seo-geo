<?php
/**
 * Theme-owned setup planner.
 *
 * @package SeoGeoTheme
 */

declare(strict_types=1);

namespace SeoGeo\Theme\Setup;

use SeoGeo\Core\Language\NativeLanguageConfiguration;

/**
 * Produces a setup plan before any configuration mutation is allowed.
 */
final class SetupPlanner {
	/**
	 * Migration handoff reader.
	 *
	 * @var MigrationHandoffReader
	 */
	private MigrationHandoffReader $handoff_reader;

	/**
	 * Compatibility detector.
	 *
	 * @var SetupCompatibilityDetector
	 */
	private SetupCompatibilityDetector $compatibility;

	/**
	 * Construct the planner.
	 *
	 * @param MigrationHandoffReader|null     $handoff_reader Optional handoff reader.
	 * @param SetupCompatibilityDetector|null $compatibility  Optional compatibility detector.
	 */
	public function __construct(
		?MigrationHandoffReader $handoff_reader = null,
		?SetupCompatibilityDetector $compatibility = null
	) {
		$this->handoff_reader = $handoff_reader ?? new MigrationHandoffReader();
		$this->compatibility  = $compatibility ?? new SetupCompatibilityDetector();
	}

	/**
	 * Build one read-only setup plan.
	 *
	 * @return array<string,mixed>
	 */
	public function plan(): array {
		$handoff      = $this->handoff_reader->read();
		$compatibility = $this->compatibility->detect();
		$languages    = NativeLanguageConfiguration::from_wordpress();
		$active_preset = \seo_geo_theme_active_preset_id();
		$presets       = $this->presets();

		$site_mode = true === ( $handoff['valid'] ?? false ) ? 'migrated' : 'clean';
		$warnings  = $compatibility['warnings'];

		if ( true === ( $handoff['available'] ?? false ) && true !== ( $handoff['valid'] ?? false ) ) {
			$warnings[] = array(
				'type'     => 'migration-handoff-invalid',
				'provider' => 'migration-bridge-handoff-v1',
				'severity' => 'blocking',
				'required' => true,
				'reason'   => is_string( $handoff['reason'] ?? null ) ? $handoff['reason'] : 'invalid',
			);
		}

		return array(
			'schema_version'         => 1,
			'mode'                   => 'theme-setup-plan-read-only',
			'generated_at'           => gmdate( DATE_ATOM ),
			'site_mode'              => $site_mode,
			'configuration_contract' => SetupConfigurationContract::definition(),
			'available_presets'      => $presets,
			'current'                => array(
				'preset'    => $active_preset,
				'languages' => array(
					'default'   => $languages->default_language_code(),
					'locales'   => $languages->languages(),
					'routing'   => $languages->routing_mode(),
					'x_default' => $languages->x_default_language_code(),
				),
			),
			'migration_handoff'      => $handoff,
			'compatibility'          => array(
				'providers' => $compatibility['providers'],
				'warnings'  => $warnings,
			),
			'next_step'              => $this->next_step( $handoff, $active_preset ),
			'safety'                 => array(
				'setup_mutations_performed' => false,
				'pages_created'             => false,
				'plugins_installed'         => false,
				'plugins_activated'         => false,
				'plugins_deactivated'       => false,
				'external_credentials_read' => false,
				'external_credentials_saved'=> false,
				'migration_bridge_loaded'   => false,
			),
		);
	}

	/**
	 * Return allowlisted preset metadata.
	 *
	 * @return list<array<string,mixed>>
	 */
	private function presets(): array {
		$presets = array();

		foreach ( \seo_geo_theme_preset_ids() as $preset_id ) {
			$document = \seo_geo_theme_preset_document( $preset_id, 'preset.json' );
			if ( ! is_array( $document ) ) {
				continue;
			}

			$schema = is_array( $document['schema'] ?? null ) ? $document['schema'] : array();

			$presets[] = array(
				'id'                     => $preset_id,
				'site_type'              => is_string( $document['site_type'] ?? null ) ? $document['site_type'] : $preset_id,
				'suggested_site_identity'=> is_string( $schema['site_identity'] ?? null ) ? $schema['site_identity'] : null,
				'identity_confirmation_required' => true === ( $schema['requires_confirmation'] ?? false ),
			);
		}

		return $presets;
	}

	/**
	 * Resolve the next setup step without executing it.
	 *
	 * @param array<string,mixed> $handoff      Migration handoff state.
	 * @param string|null         $active_preset Current preset.
	 */
	private function next_step( array $handoff, ?string $active_preset ): string {
		if ( true === ( $handoff['available'] ?? false ) && true !== ( $handoff['valid'] ?? false ) ) {
			return 'review-migration-handoff';
		}

		if ( null === $active_preset ) {
			return 'choose-preset';
		}

		return 'configure-languages';
	}
}
