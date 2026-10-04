<?php
/**
 * Corporate Theme bootstrap for reset-first rebuilds.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge\Reset;

use WP_Error;

/**
 * Delegates Corporate preset setup to the Theme-owned SetupExecutor.
 */
final class CorporateThemeBootstrap {
	public const REPORT_OPTION = 'seo_geo_corporate_bootstrap_report_v1';
	public const PRESET        = 'corporate';

	/**
	 * Build a read-only bootstrap plan.
	 *
	 * @return array<string,mixed>
	 */
	public function plan(): array {
		$blockers = array();
		$reset    = get_option( CloneResetEngine::REPORT_OPTION, null );

		if (
			! is_array( $reset )
			|| 'reset-rebuild-clone-reset' !== ( $reset['mode'] ?? null )
			|| 'completed' !== ( $reset['status'] ?? null )
		) {
			$blockers[] = 'completed-clone-reset-required';
		}

		$reset_safety = is_array( $reset['safety'] ?? null ) ? $reset['safety'] : array();
		if (
			is_array( $reset )
			&& (
				true !== ( $reset_safety['manifest_unchanged'] ?? false )
				|| true !== ( $reset_safety['content_unchanged'] ?? false )
				|| true !== ( $reset_safety['target_theme_active'] ?? false )
				|| false !== ( $reset_safety['production_mutation'] ?? null )
			)
		) {
			$blockers[] = 'clone-reset-safety-invalid';
		}

		if ( CloneResetEngine::TARGET_THEME !== $this->active_stylesheet() ) {
			$blockers[] = 'seo-geo-theme-not-active';
		}
		if ( ! function_exists( 'seo_geo_theme_apply_setup' ) || ! function_exists( 'seo_geo_theme_setup_plan' ) ) {
			$blockers[] = 'theme-setup-api-unavailable';
		}

		$site_title = trim( wp_strip_all_tags( get_bloginfo( 'name' ), true ) );
		if ( '' === $site_title ) {
			$blockers[] = 'wordpress-site-title-required';
		}

		$language = $this->language_candidate();
		if ( null === $language ) {
			$blockers[] = 'wordpress-locale-not-supported';
		}

		$blockers = array_values( array_unique( $blockers ) );
		sort( $blockers );

		return array(
			'schema_version' => 1,
			'mode'           => 'reset-rebuild-corporate-bootstrap-plan',
			'ready'          => array() === $blockers,
			'blockers'       => $blockers,
			'preset'         => self::PRESET,
			'theme'          => CloneResetEngine::TARGET_THEME,
			'identity'       => array(
				'type'                    => 'organization',
				'name_source'             => 'wordpress-site-title',
				'explicit_confirmation'   => true,
				'local_business_inferred' => false,
			),
			'language'       => $language,
			'geo'            => array(
				'crawler_policy'              => 'inherit',
				'llms_txt_enabled'            => false,
				'markdown_alternates_enabled' => false,
			),
			'current'        => array(
				'preset' => function_exists( 'seo_geo_theme_active_preset_id' )
					? \seo_geo_theme_active_preset_id()
					: null,
			),
			'safety'         => array(
				'theme_setup_authority'      => 'theme-owned-setup-executor',
				'page_content_mutation'      => false,
				'plugin_mutation'            => false,
				'local_business_inference'   => false,
				'organization_fact_inferred' => false,
			),
		);
	}

	/**
	 * Apply Corporate via the Theme-owned setup authority.
	 *
	 * @param bool $confirm_identity Explicit confirmation that the WordPress site title names the organization.
	 * @return array<string,mixed>|WP_Error
	 */
	public function apply( bool $confirm_identity ): array|WP_Error {
		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error( 'seo_geo_corporate_bootstrap_forbidden', 'Administrator capability is required.' );
		}
		if ( ! $confirm_identity ) {
			return new WP_Error(
				'seo_geo_corporate_identity_confirmation_required',
				'Explicit confirmation is required before Organization identity can be applied.'
			);
		}

		$plan = $this->plan();
		if ( true !== ( $plan['ready'] ?? false ) ) {
			return new WP_Error(
				'seo_geo_corporate_bootstrap_not_ready',
				'Corporate bootstrap is blocked: ' . implode( ', ', is_array( $plan['blockers'] ?? null ) ? $plan['blockers'] : array() )
			);
		}

		$language = $this->language_candidate();
		if ( null === $language ) {
			return new WP_Error( 'seo_geo_corporate_language_invalid', 'The active WordPress locale cannot seed the Theme language configuration.' );
		}

		$candidate = array(
			'preset'                      => self::PRESET,
			'default_language'            => $language['default'],
			'languages'                   => $language['languages'],
			'routing'                     => 'disabled',
			'x_default'                   => null,
			'site_entity_type'            => 'organization',
			'confirm_identity'            => true,
			'crawler_policy'              => array(),
			'llms_txt_enabled'            => false,
			'markdown_alternates_enabled' => false,
		);

		/** @var array<string,mixed> $result */
		$result = \seo_geo_theme_apply_setup( $candidate, true );
		if ( true !== ( $result['valid'] ?? false ) || true !== ( $result['applied'] ?? false ) ) {
			$errors = is_array( $result['errors'] ?? null )
				? array_values( array_filter( $result['errors'], 'is_string' ) )
				: array();

			return new WP_Error(
				'seo_geo_corporate_bootstrap_failed',
				'Theme setup rejected the Corporate bootstrap: ' . implode( ', ', $errors )
			);
		}

		$setup_report = function_exists( 'seo_geo_theme_setup_report' )
			? \seo_geo_theme_setup_report()
			: null;
		$current_preset = function_exists( 'seo_geo_theme_active_preset_id' )
			? \seo_geo_theme_active_preset_id()
			: null;

		if ( self::PRESET !== $current_preset ) {
			return new WP_Error( 'seo_geo_corporate_preset_verification_failed', 'Corporate preset was not persisted by the Theme setup authority.' );
		}

		$report = array(
			'schema_version'       => 1,
			'mode'                 => 'reset-rebuild-corporate-bootstrap',
			'status'               => 'completed',
			'applied_at'           => gmdate( DATE_ATOM ),
			'preset'               => self::PRESET,
			'theme'                => CloneResetEngine::TARGET_THEME,
			'language'             => $language,
			'configuration_sha256' => is_string( $result['configuration_sha256'] ?? null ) ? $result['configuration_sha256'] : '',
			'theme_report_sha256'  => is_array( $setup_report ) && is_string( $setup_report['report_sha256'] ?? null )
				? $setup_report['report_sha256']
				: '',
			'idempotent'           => true === ( $result['idempotent'] ?? false ),
			'safety'               => array(
				'theme_setup_authority'      => 'theme-owned-setup-executor',
				'organization_confirmed'     => true,
				'name_source'                => 'wordpress-site-title',
				'local_business_inferred'    => false,
				'page_content_mutation'      => false,
				'plugin_mutation'            => false,
				'production_cutover'         => false,
			),
		);

		$report['report_sha256'] = hash(
			'sha256',
			(string) wp_json_encode( $report, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE )
		);

		update_option( self::REPORT_OPTION, $report, false );

		return $report;
	}

	/**
	 * Return the latest Corporate bootstrap report.
	 *
	 * @return array<string,mixed>|null
	 */
	public function report(): ?array {
		$value = get_option( self::REPORT_OPTION, null );

		return is_array( $value ) ? $value : null;
	}

	/**
	 * Derive a single-language native configuration from the WordPress locale.
	 *
	 * @return array{default:string,languages:array<string,string>,routing:string,x_default:null}|null
	 */
	private function language_candidate(): ?array {
		$locale = sanitize_locale_name( get_locale() );
		if ( '' === $locale ) {
			return null;
		}

		$parts = preg_split( '/[_-]/', $locale );
		$code  = is_array( $parts ) && isset( $parts[0] ) ? strtolower( (string) $parts[0] ) : '';
		if ( 1 !== preg_match( '/^[a-z]{2,3}$/', $code ) ) {
			return null;
		}

		return array(
			'default'   => $code,
			'languages' => array( $code => $locale ),
			'routing'   => 'disabled',
			'x_default' => null,
		);
	}

	/**
	 * Read the active Theme stylesheet from WordPress options.
	 */
	private function active_stylesheet(): string {
		$value = get_option( 'stylesheet', '' );

		return is_string( $value ) ? $value : '';
	}
}
