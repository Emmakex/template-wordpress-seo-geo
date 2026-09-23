<?php
/**
 * Preset and native-language setup validation.
 *
 * @package SeoGeoTheme
 */

declare(strict_types=1);

namespace SeoGeo\Theme\Setup;

use SeoGeo\Core\Integrations\RuntimeIntegrationDetector;
use SeoGeo\Core\Language\NativeLanguageConfiguration;

/**
 * Validates explicit Phase 9B choices without persisting them.
 */
final class PresetLanguageValidator {
	/**
	 * Integration detector.
	 *
	 * @var RuntimeIntegrationDetector
	 */
	private RuntimeIntegrationDetector $integrations;

	/**
	 * Construct the validator.
	 *
	 * @param RuntimeIntegrationDetector|null $integrations Optional detector.
	 */
	public function __construct( ?RuntimeIntegrationDetector $integrations = null ) {
		$this->integrations = $integrations ?? new RuntimeIntegrationDetector();
	}

	/**
	 * Validate one requested preset/language configuration.
	 *
	 * @param array<string,mixed> $input Candidate setup values.
	 * @return array<string,mixed>
	 */
	public function validate( array $input ): array {
		$errors   = array();
		$warnings = array();

		$raw_preset = $input['preset'] ?? null;
		$preset     = is_string( $raw_preset ) ? sanitize_key( $raw_preset ) : '';

		if ( '' === $preset || ! in_array( $preset, \seo_geo_theme_preset_ids(), true ) ) {
			$errors[] = 'unsupported-preset';
		}

		$language_candidate = array(
			'default'   => $input['default_language'] ?? null,
			'languages' => $input['languages'] ?? null,
			'routing'   => $input['routing'] ?? NativeLanguageConfiguration::ROUTING_DISABLED,
			'x_default' => $input['x_default'] ?? null,
		);

		$language_configuration = NativeLanguageConfiguration::from_array( $language_candidate );
		if ( null === $language_configuration ) {
			$errors[] = 'invalid-language-configuration';
		} elseif (
			NativeLanguageConfiguration::ROUTING_PREFIX === $language_configuration->routing_mode()
			&& ! $language_configuration->is_multilingual()
		) {
			$errors[] = 'prefix-routing-requires-multiple-languages';
		}

		$preset_document     = '' !== $preset ? \seo_geo_theme_preset_document( $preset, 'preset.json' ) : null;
		$preset_multilingual = is_array( $preset_document['multilingual'] ?? null )
			? $preset_document['multilingual']
			: array();

		if ( null !== $language_configuration ) {
			$configured_locales = array_values( $language_configuration->languages() );
			$baseline_locales   = isset( $preset_multilingual['baseline_locales'] ) && is_array( $preset_multilingual['baseline_locales'] )
				? array_values( array_filter( $preset_multilingual['baseline_locales'], 'is_string' ) )
				: array();

			foreach ( $baseline_locales as $baseline_locale ) {
				if ( ! in_array( $baseline_locale, $configured_locales, true ) ) {
					$warnings[] = 'preset-baseline-locale-not-configured:' . $baseline_locale;
				}
			}

			if (
				$language_configuration->is_multilingual()
				&& NativeLanguageConfiguration::ROUTING_DISABLED === $language_configuration->routing_mode()
			) {
				$warnings[] = 'multilingual-native-routing-disabled';
			}
		}

		$language_provider = $this->integrations->language_provider();
		if ( 'native' !== $language_provider ) {
			$warnings[] = 'external-language-provider:' . $language_provider;
		}

		$errors   = array_values( array_unique( $errors ) );
		$warnings = array_values( array_unique( $warnings ) );

		$valid = array() === $errors && null !== $language_configuration && is_array( $preset_document );

		return array(
			'schema_version'     => 1,
			'mode'               => 'preset-language-validation',
			'valid'              => $valid,
			'errors'             => $errors,
			'warnings'           => $warnings,
			'normalized'         => $valid
				? array(
					'preset'      => $preset,
					'languages'   => $language_configuration->to_array(),
					'preset_meta' => array(
						'site_type'        => is_string( $preset_document['site_type'] ?? null ) ? $preset_document['site_type'] : $preset,
						'baseline_locales' => isset( $preset_multilingual['baseline_locales'] ) && is_array( $preset_multilingual['baseline_locales'] )
							? array_values( array_filter( $preset_multilingual['baseline_locales'], 'is_string' ) )
							: array(),
						'localized_slugs'  => true === ( $preset_multilingual['localized_slugs'] ?? false ),
					),
				)
				: null,
			'provider_ownership' => array(
				'language' => $language_provider,
			),
			'safety'             => array(
				'options_persisted'      => false,
				'translations_created'   => false,
				'routes_created'         => false,
				'provider_state_mutated' => false,
				'plugins_mutated'        => false,
			),
		);
	}
}
