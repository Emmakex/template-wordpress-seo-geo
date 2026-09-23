<?php
/**
 * Setup wizard preview composer.
 *
 * @package SeoGeoTheme
 */

declare(strict_types=1);

namespace SeoGeo\Theme\Wizard;

use SeoGeo\Theme\Setup\EntityGeoValidator;
use SeoGeo\Theme\Setup\PresetLanguageValidator;

/**
 * Composes existing setup validators without introducing new authorities.
 */
final class SetupWizardPreview {
	/**
	 * Preset/language validator.
	 *
	 * @var PresetLanguageValidator
	 */
	private PresetLanguageValidator $preset_language;

	/**
	 * Entity/GEO validator.
	 *
	 * @var EntityGeoValidator
	 */
	private EntityGeoValidator $entity_geo;

	/**
	 * Construct the preview service.
	 *
	 * @param PresetLanguageValidator|null $preset_language Optional validator.
	 * @param EntityGeoValidator|null       $entity_geo      Optional validator.
	 */
	public function __construct(
		?PresetLanguageValidator $preset_language = null,
		?EntityGeoValidator $entity_geo = null
	) {
		$this->preset_language = $preset_language ?? new PresetLanguageValidator();
		$this->entity_geo      = $entity_geo ?? new EntityGeoValidator();
	}

	/**
	 * Validate the complete wizard candidate without persistence.
	 *
	 * @param array<string,mixed> $input     Candidate setup values.
	 * @param bool                $confirmed Explicit preview-only confirmation.
	 * @return array<string,mixed>
	 */
	public function validate( array $input, bool $confirmed ): array {
		$preset_language = $this->preset_language->validate( $input );
		$entity_geo      = $this->entity_geo->validate( $input );

		$errors = array_merge(
			is_array( $preset_language['errors'] ?? null ) ? $preset_language['errors'] : array(),
			is_array( $entity_geo['errors'] ?? null ) ? $entity_geo['errors'] : array()
		);
		$warnings = array_merge(
			is_array( $preset_language['warnings'] ?? null ) ? $preset_language['warnings'] : array(),
			is_array( $entity_geo['warnings'] ?? null ) ? $entity_geo['warnings'] : array()
		);

		if ( ! $confirmed ) {
			$errors[] = 'preview-confirmation-required';
		}

		$errors   = array_values( array_unique( array_filter( $errors, 'is_string' ) ) );
		$warnings = array_values( array_unique( array_filter( $warnings, 'is_string' ) ) );
		$valid    = array() === $errors
			&& true === ( $preset_language['valid'] ?? false )
			&& true === ( $entity_geo['valid'] ?? false );

		return array(
			'schema_version' => 1,
			'mode'           => 'theme-setup-wizard-preview',
			'valid'          => $valid,
			'errors'         => $errors,
			'warnings'       => $warnings,
			'normalized'     => $valid
				? array(
					'preset_language' => $preset_language['normalized'],
					'entity_geo'      => $entity_geo['normalized'],
				)
				: null,
			'authorities'    => array(
				'preset_language' => PresetLanguageValidator::class,
				'entity_geo'      => EntityGeoValidator::class,
			),
			'safety'         => array(
				'options_persisted'      => false,
				'pages_created'          => false,
				'plugins_mutated'        => false,
				'credentials_requested'  => false,
				'outbound_requests_made' => false,
			),
		);
	}
}
