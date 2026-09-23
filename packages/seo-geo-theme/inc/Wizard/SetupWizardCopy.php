<?php
/**
 * Theme setup wizard copy.
 *
 * @package SeoGeoTheme
 */

declare(strict_types=1);

namespace SeoGeo\Theme\Wizard;

/**
 * Provides key-complete English and Spanish wizard copy.
 */
final class SetupWizardCopy {
	/**
	 * Optional locale override.
	 *
	 * @var string|null
	 */
	private ?string $locale;

	/**
	 * Construct the copy resolver.
	 *
	 * @param string|null $locale Optional locale override.
	 */
	public function __construct( ?string $locale = null ) {
		$this->locale = $locale;
	}

	/**
	 * Return all built-in wizard catalogs.
	 *
	 * @return array<string,array<string,string>>
	 */
	public static function catalogs(): array {
		return array(
			'en' => array(
				'page_title'               => 'SEO/GEO Setup',
				'menu_title'               => 'SEO/GEO Setup',
				'intro'                    => 'Validate your preset, languages, site identity and GEO choices before anything is saved.',
				'read_only_notice'         => 'Preview mode: this phase validates only. No setup option, page, plugin or credential is changed.',
				'step_preset'              => '1. Preset and languages',
				'step_identity'            => '2. Site identity',
				'step_geo'                 => '3. GEO and discovery',
				'step_review'              => '4. Review',
				'preset'                   => 'Preset',
				'choose_preset'            => 'Choose a preset',
				'default_language'         => 'Primary language code',
				'languages'                => 'Language map',
				'languages_help'           => 'One code=locale pair per line, for example en=en_US.',
				'routing'                  => 'Native routing',
				'routing_disabled'         => 'Disabled',
				'routing_prefix'           => 'Language prefixes',
				'x_default'                => 'x-default language code',
				'entity_type'              => 'Site entity',
				'entity_organization'      => 'Organization',
				'entity_local_business'    => 'Local business',
				'confirm_identity'         => 'I confirm this identity describes the real public site.',
				'local_business_type'      => 'LocalBusiness type',
				'street_address'           => 'Street address',
				'address_locality'         => 'City / locality',
				'address_region'           => 'Region',
				'postal_code'              => 'Postal code',
				'address_country'          => 'Country code',
				'telephone'                => 'Telephone',
				'price_range'              => 'Price range',
				'latitude'                 => 'Latitude',
				'longitude'                => 'Longitude',
				'crawler_policy'           => 'Crawler policy',
				'inherit'                  => 'Inherit',
				'allow'                    => 'Allow',
				'disallow'                 => 'Disallow',
				'llms_txt'                 => 'Enable llms.txt',
				'markdown'                 => 'Enable Markdown alternates',
				'preview_confirm'          => 'I understand this validates a preview only and does not save settings.',
				'validate'                 => 'Validate setup',
				'result_ready'             => 'Setup preview is valid.',
				'result_invalid'           => 'Setup preview needs attention.',
				'errors'                   => 'Errors',
				'warnings'                 => 'Warnings',
				'normalized'               => 'Validated preview',
				'normalized_preset'        => 'Preset',
				'normalized_languages'     => 'Languages',
				'normalized_entity'        => 'Entity',
				'normalized_geo'           => 'GEO / discovery',
				'not_set'                  => 'Not set',
				'forbidden'                => 'You do not have permission to use the SEO/GEO setup wizard.',
				'confirmation_required'    => 'Confirm preview-only validation before continuing.',
				'validation_issue'         => 'Review this validation item:',
				'privacy_note'             => 'The wizard does not ask for external credentials and does not promise rankings, inclusion, citation, training, or crawler behavior.',
			),
			'es' => array(
				'page_title'               => 'Configuración SEO/GEO',
				'menu_title'               => 'Configuración SEO/GEO',
				'intro'                    => 'Valida el preset, los idiomas, la identidad del sitio y las opciones GEO antes de guardar cualquier cambio.',
				'read_only_notice'         => 'Modo de previsualización: esta fase solo valida. No cambia opciones de configuración, páginas, plugins ni credenciales.',
				'step_preset'              => '1. Preset e idiomas',
				'step_identity'            => '2. Identidad del sitio',
				'step_geo'                 => '3. GEO y descubrimiento',
				'step_review'              => '4. Revisión',
				'preset'                   => 'Preset',
				'choose_preset'            => 'Elige un preset',
				'default_language'         => 'Código del idioma principal',
				'languages'                => 'Mapa de idiomas',
				'languages_help'           => 'Una pareja código=locale por línea, por ejemplo es=es_ES.',
				'routing'                  => 'Enrutado nativo',
				'routing_disabled'         => 'Desactivado',
				'routing_prefix'           => 'Prefijos de idioma',
				'x_default'                => 'Código de idioma x-default',
				'entity_type'              => 'Entidad del sitio',
				'entity_organization'      => 'Organización',
				'entity_local_business'    => 'Negocio local',
				'confirm_identity'         => 'Confirmo que esta identidad describe el sitio público real.',
				'local_business_type'      => 'Tipo LocalBusiness',
				'street_address'           => 'Dirección',
				'address_locality'         => 'Ciudad / localidad',
				'address_region'           => 'Región',
				'postal_code'              => 'Código postal',
				'address_country'          => 'Código de país',
				'telephone'                => 'Teléfono',
				'price_range'              => 'Rango de precios',
				'latitude'                 => 'Latitud',
				'longitude'                => 'Longitud',
				'crawler_policy'           => 'Política de rastreadores',
				'inherit'                  => 'Heredar',
				'allow'                    => 'Permitir',
				'disallow'                 => 'Bloquear',
				'llms_txt'                 => 'Activar llms.txt',
				'markdown'                 => 'Activar alternativos Markdown',
				'preview_confirm'          => 'Entiendo que esto solo valida una previsualización y no guarda ajustes.',
				'validate'                 => 'Validar configuración',
				'result_ready'             => 'La previsualización es válida.',
				'result_invalid'           => 'La previsualización requiere atención.',
				'errors'                   => 'Errores',
				'warnings'                 => 'Avisos',
				'normalized'               => 'Previsualización validada',
				'normalized_preset'        => 'Preset',
				'normalized_languages'     => 'Idiomas',
				'normalized_entity'        => 'Entidad',
				'normalized_geo'           => 'GEO / descubrimiento',
				'not_set'                  => 'Sin configurar',
				'forbidden'                => 'No tienes permisos para usar el asistente de configuración SEO/GEO.',
				'confirmation_required'    => 'Confirma la validación de solo previsualización antes de continuar.',
				'validation_issue'         => 'Revisa este elemento de validación:',
				'privacy_note'             => 'El asistente no solicita credenciales externas ni promete rankings, inclusión, citas, entrenamiento o comportamiento de rastreadores.',
			),
		);
	}

	/**
	 * Resolve one localized key.
	 *
	 * @param string $key Copy key.
	 */
	public function text( string $key ): string {
		$catalogs = self::catalogs();
		$locale   = $this->locale ?? get_user_locale();
		$language = str_starts_with( strtolower( $locale ), 'es' ) ? 'es' : 'en';

		return $catalogs[ $language ][ $key ] ?? $catalogs['en'][ $key ] ?? $key;
	}
}
