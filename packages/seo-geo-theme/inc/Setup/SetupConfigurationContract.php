<?php
/**
 * Theme-owned setup configuration contract.
 *
 * @package SeoGeoTheme
 */

declare(strict_types=1);

namespace SeoGeo\Theme\Setup;

/**
 * Defines the versioned setup shape without persisting any values.
 */
final class SetupConfigurationContract {
	/**
	 * Persisted theme setup state option.
	 */
	public const OPTION_NAME = 'seo_geo_theme_setup_v1';

	/**
	 * Current setup schema.
	 */
	public const SCHEMA_VERSION = 1;

	/**
	 * Return the Phase 9 setup contract.
	 *
	 * @return array<string,mixed>
	 */
	public static function definition(): array {
		return array(
			'schema_version' => self::SCHEMA_VERSION,
			'option_name'    => self::OPTION_NAME,
			'fields'         => array(
				'preset'      => array(
					'type'     => 'preset-id',
					'required' => true,
				),
				'languages'   => array(
					'type'     => 'native-language-map',
					'required' => true,
				),
				'site_entity' => array(
					'type'     => 'explicit-entity-choice',
					'required' => false,
				),
				'geo'         => array(
					'type'     => 'explicit-opt-ins',
					'required' => false,
				),
				'migration'   => array(
					'type'     => 'handoff-reference',
					'required' => false,
				),
			),
			'ownership'      => array(
				'theme_owned'                 => true,
				'seo_geo_plugin_required'     => false,
				'migration_bridge_required'   => false,
				'external_credentials_stored' => false,
			),
		);
	}
}
