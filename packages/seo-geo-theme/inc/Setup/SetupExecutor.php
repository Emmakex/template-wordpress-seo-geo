<?php
/**
 * Atomic theme setup execution.
 *
 * @package SeoGeoTheme
 */

declare(strict_types=1);

namespace SeoGeo\Theme\Setup;

use SeoGeo\Core\Geo\CrawlerPolicyResolver;
use SeoGeo\Core\Geo\LlmsTxtResolver;
use SeoGeo\Core\Geo\MarkdownAlternateResolver;
use SeoGeo\Core\Language\NativeLanguageConfiguration;
use SeoGeo\Core\Schema\SchemaIdentityResolver;
use SeoGeo\Core\Schema\SchemaLocalBusinessResolver;
use SeoGeo\Theme\Wizard\SetupWizardPreview;
use Throwable;

/**
 * Revalidates and atomically applies one complete setup candidate.
 */
final class SetupExecutor {
	/**
	 * Complete setup validator.
	 *
	 * @var SetupWizardPreview
	 */
	private SetupWizardPreview $preview;

	/**
	 * Read-only setup planner.
	 *
	 * @var SetupPlanner
	 */
	private SetupPlanner $planner;

	/**
	 * Option mutation boundary.
	 *
	 * @var SetupOptionWriterInterface
	 */
	private SetupOptionWriterInterface $writer;

	/**
	 * Setup report reader.
	 *
	 * @var SetupReportStore
	 */
	private SetupReportStore $reports;

	/**
	 * Construct the executor.
	 *
	 * @param SetupWizardPreview|null         $preview Optional validator.
	 * @param SetupPlanner|null               $planner Optional setup planner.
	 * @param SetupOptionWriterInterface|null $writer  Optional mutation boundary.
	 * @param SetupReportStore|null           $reports Optional report reader.
	 */
	public function __construct(
		?SetupWizardPreview $preview = null,
		?SetupPlanner $planner = null,
		?SetupOptionWriterInterface $writer = null,
		?SetupReportStore $reports = null
	) {
		$this->preview = $preview ?? new SetupWizardPreview();
		$this->planner = $planner ?? new SetupPlanner();
		$this->writer  = $writer ?? new WordPressSetupOptionWriter();
		$this->reports = $reports ?? new SetupReportStore();
	}

	/**
	 * Validate and atomically apply one setup candidate.
	 *
	 * @param array<string,mixed> $input     Explicit setup choices.
	 * @param bool                $confirmed Explicit save acknowledgement.
	 * @return array<string,mixed>
	 */
	public function execute( array $input, bool $confirmed ): array {
		if ( ! current_user_can( 'manage_options' ) ) {
			return $this->failure(
				array( 'setup-capability-required' ),
				array(),
				null,
				false,
				array()
			);
		}

		if ( ! $confirmed ) {
			return $this->failure(
				array( 'apply-confirmation-required' ),
				array(),
				null,
				false,
				array()
			);
		}

		$validation = $this->preview->validate( $input, true );
		$errors     = $this->string_list( $validation['errors'] ?? null );
		$warnings   = $this->string_list( $validation['warnings'] ?? null );
		$normalized = is_array( $validation['normalized'] ?? null ) ? $validation['normalized'] : null;

		if ( true !== ( $validation['valid'] ?? false ) || null === $normalized ) {
			return $this->failure( $errors, $warnings, $normalized, false, array() );
		}

		$plan    = $this->planner->plan();
		$handoff = is_array( $plan['migration_handoff'] ?? null ) ? $plan['migration_handoff'] : array();

		if ( true === ( $handoff['available'] ?? false ) && true !== ( $handoff['valid'] ?? false ) ) {
			$errors[] = 'migration-handoff-invalid';
		}

		if (
			true === ( $handoff['valid'] ?? false )
			&& true === ( $handoff['runtime_dependency_required'] ?? false )
		) {
			$errors[] = 'migration-handoff-still-runtime-dependent';
		}

		if (
			true === ( $handoff['valid'] ?? false )
			&& 0 < (int) ( $handoff['blocking_review_count'] ?? 0 )
		) {
			$errors[] = 'migration-handoff-blocking-review';
		}

		$warnings = array_values(
			array_unique(
				array_merge(
					$warnings,
					$this->compatibility_warning_codes(
						is_array( $plan['compatibility'] ?? null ) ? $plan['compatibility'] : array()
					)
				)
			)
		);

		if ( array() !== $errors ) {
			return $this->failure(
				array_values( array_unique( $errors ) ),
				$warnings,
				$normalized,
				false,
				array()
			);
		}

		$authority_options = $this->authority_options( $normalized );
		$handoff_summary   = $this->handoff_summary( $handoff );
		$config_sha256     = $this->fingerprint(
			array(
				'options'   => $authority_options,
				'migration' => $handoff_summary,
			)
		);

		$setup_state      = $this->setup_state( $normalized, $handoff_summary, $config_sha256 );
		$targets          = $authority_options;
		$language_current = $this->writer->read( NativeLanguageConfiguration::OPTION_NAME );
		$language_changed = true !== $language_current['exists']
			|| $language_current['value'] !== $authority_options[ NativeLanguageConfiguration::OPTION_NAME ];

		$targets[ SetupConfigurationContract::OPTION_NAME ] = $setup_state;

		if ( $language_changed ) {
			$targets[ SetupRewriteMaintenance::OPTION_NAME ] = array(
				'schema_version'       => 1,
				'configuration_sha256' => $config_sha256,
			);
		}

		$existing_report = $this->reports->latest();
		if (
			$this->targets_match( $targets )
			&& is_array( $existing_report )
			&& ( $existing_report['configuration_sha256'] ?? null ) === $config_sha256
		) {
			return $this->success(
				$normalized,
				$warnings,
				$config_sha256,
				array(),
				$existing_report,
				true
			);
		}

		$snapshots       = $this->snapshots( array_merge( array_keys( $targets ), array( SetupReportStore::OPTION_NAME ) ) );
		$changed_options = array();

		try {
			foreach ( $targets as $option_name => $value ) {
				$current = $snapshots[ $option_name ];
				if ( true === $current['exists'] && $current['value'] === $value ) {
					continue;
				}

				$this->require_write( $option_name, $value );
				$changed_options[] = $option_name;
			}

			$report = $this->report(
				$normalized,
				$plan,
				$warnings,
				$config_sha256,
				$changed_options,
				$language_changed
			);

			$current_report = $snapshots[ SetupReportStore::OPTION_NAME ];
			if (
				true !== $current_report['exists']
				|| $report !== $current_report['value']
			) {
				$this->require_write( SetupReportStore::OPTION_NAME, $report );
			}
		} catch ( Throwable $throwable ) {
			$rollback_errors = $this->rollback( $snapshots, array_merge( $changed_options, array( SetupReportStore::OPTION_NAME ) ) );
			$failed_option   = $throwable instanceof SetupWriteFailure ? $throwable->option_name() : 'unknown';

			return $this->failure(
				array_merge( array( 'setup-write-failed:' . $failed_option ), $rollback_errors ),
				$warnings,
				$normalized,
				true,
				$changed_options
			);
		}

		$stored_report = $this->reports->latest();
		if ( ! is_array( $stored_report ) || ( $stored_report['configuration_sha256'] ?? null ) !== $config_sha256 ) {
			$rollback_errors = $this->rollback( $snapshots, array_merge( $changed_options, array( SetupReportStore::OPTION_NAME ) ) );

			return $this->failure(
				array_merge( array( 'setup-report-verification-failed' ), $rollback_errors ),
				$warnings,
				$normalized,
				true,
				$changed_options
			);
		}

		return $this->success(
			$normalized,
			$warnings,
			$config_sha256,
			$changed_options,
			$stored_report,
			false
		);
	}

	/**
	 * Build the authoritative option set from validated values.
	 *
	 * @param array<string,mixed> $normalized Complete normalized setup.
	 * @return array<string,mixed>
	 */
	private function authority_options( array $normalized ): array {
		$preset_language = is_array( $normalized['preset_language'] ?? null ) ? $normalized['preset_language'] : array();
		$entity_geo      = is_array( $normalized['entity_geo'] ?? null ) ? $normalized['entity_geo'] : array();
		$entity          = is_array( $entity_geo['entity'] ?? null ) ? $entity_geo['entity'] : array();
		$geo             = is_array( $entity_geo['geo'] ?? null ) ? $entity_geo['geo'] : array();
		$language_config = is_array( $preset_language['languages'] ?? null ) ? $preset_language['languages'] : array();
		$entity_type     = is_string( $entity['site_entity_type'] ?? null ) ? $entity['site_entity_type'] : '';
		$local_business  = is_array( $entity['local_business'] ?? null ) ? $entity['local_business'] : array();
		$crawler_policy  = is_array( $geo['crawler_policy']['value'] ?? null ) ? $geo['crawler_policy']['value'] : array();

		$llms     = $this->enabled_configuration(
			LlmsTxtResolver::OPTION_NAME,
			true === ( $geo['llms_txt']['enabled'] ?? false )
		);
		$markdown = $this->enabled_configuration(
			MarkdownAlternateResolver::OPTION_NAME,
			true === ( $geo['markdown']['enabled'] ?? false )
		);

		return array(
			'seo_geo_active_preset'                  => is_string( $preset_language['preset'] ?? null ) ? $preset_language['preset'] : '',
			NativeLanguageConfiguration::OPTION_NAME => $language_config,
			SchemaIdentityResolver::OPTION_NAME      => array(
				'site_entity_type' => $entity_type,
			),
			SchemaLocalBusinessResolver::OPTION_NAME => SchemaIdentityResolver::SITE_ENTITY_LOCAL_BUSINESS === $entity_type
				? $local_business
				: array(),
			CrawlerPolicyResolver::OPTION_NAME       => $crawler_policy,
			LlmsTxtResolver::OPTION_NAME             => $llms,
			MarkdownAlternateResolver::OPTION_NAME   => $markdown,
		);
	}

	/**
	 * Preserve existing discovery configuration while changing only enabled.
	 *
	 * @param string $option_name Option authority.
	 * @param bool   $enabled     Explicit setup choice.
	 * @return array<string,mixed>
	 */
	private function enabled_configuration( string $option_name, bool $enabled ): array {
		$current = $this->writer->read( $option_name );
		$value   = true === $current['exists'] && is_array( $current['value'] ) ? $current['value'] : array();

		$value['enabled'] = $enabled;

		return $value;
	}

	/**
	 * Build deterministic persisted setup state without duplicating public facts.
	 *
	 * @param array<string,mixed> $normalized      Complete normalized setup.
	 * @param array<string,mixed> $handoff_summary Bounded migration handoff.
	 * @param string              $config_sha256   Configuration fingerprint.
	 * @return array<string,mixed>
	 */
	private function setup_state( array $normalized, array $handoff_summary, string $config_sha256 ): array {
		$preset_language = is_array( $normalized['preset_language'] ?? null ) ? $normalized['preset_language'] : array();
		$entity_geo      = is_array( $normalized['entity_geo'] ?? null ) ? $normalized['entity_geo'] : array();
		$languages       = is_array( $preset_language['languages'] ?? null ) ? $preset_language['languages'] : array();
		$entity          = is_array( $entity_geo['entity'] ?? null ) ? $entity_geo['entity'] : array();
		$geo             = is_array( $entity_geo['geo'] ?? null ) ? $entity_geo['geo'] : array();
		$crawler         = is_array( $geo['crawler_policy']['value'] ?? null ) ? $geo['crawler_policy']['value'] : array();

		return array(
			'schema_version'       => SetupConfigurationContract::SCHEMA_VERSION,
			'mode'                 => 'theme-setup-applied',
			'configuration_sha256' => $config_sha256,
			'preset'               => is_string( $preset_language['preset'] ?? null ) ? $preset_language['preset'] : null,
			'languages'            => array(
				'default'   => is_string( $languages['default'] ?? null ) ? $languages['default'] : null,
				'codes'     => is_array( $languages['languages'] ?? null ) ? array_keys( $languages['languages'] ) : array(),
				'routing'   => is_string( $languages['routing'] ?? null ) ? $languages['routing'] : null,
				'x_default' => is_string( $languages['x_default'] ?? null ) ? $languages['x_default'] : null,
			),
			'site_entity'          => array(
				'type'                      => is_string( $entity['site_entity_type'] ?? null ) ? $entity['site_entity_type'] : null,
				'local_business_configured' => is_array( $entity['local_business'] ?? null ),
			),
			'geo'                  => array(
				'crawler_policy_sha256'       => $this->fingerprint( $crawler ),
				'llms_txt_enabled'            => true === ( $geo['llms_txt']['enabled'] ?? false ),
				'markdown_alternates_enabled' => true === ( $geo['markdown']['enabled'] ?? false ),
			),
			'migration'            => $handoff_summary,
		);
	}

	/**
	 * Build a non-sensitive generated report.
	 *
	 * @param array<string,mixed> $normalized      Complete normalized setup.
	 * @param array<string,mixed> $plan            Current setup plan.
	 * @param array               $warnings        Validation warnings.
	 * @phpstan-param list<string> $warnings
	 * @param string              $config_sha256   Configuration fingerprint.
	 * @param array               $changed_options Changed option names.
	 * @phpstan-param list<string> $changed_options
	 * @param bool                $rewrite_flush_pending Whether a rewrite flush was scheduled.
	 * @return array<string,mixed>
	 */
	private function report(
		array $normalized,
		array $plan,
		array $warnings,
		string $config_sha256,
		array $changed_options,
		bool $rewrite_flush_pending
	): array {
		$preset_language = is_array( $normalized['preset_language'] ?? null ) ? $normalized['preset_language'] : array();
		$entity_geo      = is_array( $normalized['entity_geo'] ?? null ) ? $normalized['entity_geo'] : array();
		$languages       = is_array( $preset_language['languages'] ?? null ) ? $preset_language['languages'] : array();
		$entity          = is_array( $entity_geo['entity'] ?? null ) ? $entity_geo['entity'] : array();
		$geo             = is_array( $entity_geo['geo'] ?? null ) ? $entity_geo['geo'] : array();
		$compatibility   = is_array( $plan['compatibility'] ?? null ) ? $plan['compatibility'] : array();
		$handoff         = is_array( $plan['migration_handoff'] ?? null ) ? $plan['migration_handoff'] : array();

		$report = array(
			'schema_version'       => 1,
			'mode'                 => 'theme-setup-report',
			'applied_at'           => gmdate( DATE_ATOM ),
			'configuration_sha256' => $config_sha256,
			'site_mode'            => is_string( $plan['site_mode'] ?? null ) ? $plan['site_mode'] : 'clean',
			'preset'               => is_string( $preset_language['preset'] ?? null ) ? $preset_language['preset'] : null,
			'languages'            => array(
				'default'   => is_string( $languages['default'] ?? null ) ? $languages['default'] : null,
				'codes'     => is_array( $languages['languages'] ?? null ) ? array_keys( $languages['languages'] ) : array(),
				'routing'   => is_string( $languages['routing'] ?? null ) ? $languages['routing'] : null,
				'x_default' => is_string( $languages['x_default'] ?? null ) ? $languages['x_default'] : null,
			),
			'entity'               => array(
				'type'                       => is_string( $entity['site_entity_type'] ?? null ) ? $entity['site_entity_type'] : null,
				'local_business_configured'  => is_array( $entity['local_business'] ?? null ),
				'visible_fact_gate_required' => true === ( $entity['visible_fact_gate_required'] ?? false ),
			),
			'geo'                  => array(
				'crawler_policy_sha256'       => $this->fingerprint( is_array( $geo['crawler_policy']['value'] ?? null ) ? $geo['crawler_policy']['value'] : array() ),
				'llms_txt_enabled'            => true === ( $geo['llms_txt']['enabled'] ?? false ),
				'markdown_alternates_enabled' => true === ( $geo['markdown']['enabled'] ?? false ),
				'provenance_mode'             => is_string( $geo['provenance']['mode'] ?? null ) ? $geo['provenance']['mode'] : null,
			),
			'migration_handoff'    => $this->handoff_summary( $handoff ),
			'compatibility'        => $this->bounded_compatibility( $compatibility ),
			'validation_warnings'  => $warnings,
			'changed_options'      => array_values( $changed_options ),
			'maintenance'          => array(
				'rewrite_flush_pending' => $rewrite_flush_pending,
			),
			'safety'               => array(
				'pages_created'                  => false,
				'plugins_installed'              => false,
				'plugins_activated'              => false,
				'plugins_deactivated'            => false,
				'external_credentials_read'      => false,
				'external_credentials_saved'     => false,
				'private_content_exported'       => false,
				'local_business_facts_in_report' => false,
				'migration_bridge_loaded'        => false,
			),
		);

		$report['report_sha256'] = $this->fingerprint( $report );

		return $report;
	}

	/**
	 * Return bounded migration handoff metadata.
	 *
	 * @param array<string,mixed> $handoff Handoff status.
	 * @return array<string,mixed>|null
	 */
	private function handoff_summary( array $handoff ): ?array {
		if ( true !== ( $handoff['valid'] ?? false ) ) {
			return null;
		}

		return array(
			'source'                => 'migration-bridge-handoff-v1',
			'id'                    => is_string( $handoff['id'] ?? null ) ? $handoff['id'] : null,
			'report_sha256'         => is_string( $handoff['report_sha256'] ?? null ) ? $handoff['report_sha256'] : null,
			'bridge_disposition'    => is_string( $handoff['bridge_disposition'] ?? null ) ? $handoff['bridge_disposition'] : null,
			'blocking_review_count' => isset( $handoff['blocking_review_count'] ) ? (int) $handoff['blocking_review_count'] : 0,
			'advisory_review_count' => isset( $handoff['advisory_review_count'] ) ? (int) $handoff['advisory_review_count'] : 0,
		);
	}

	/**
	 * Convert structured compatibility notices into bounded operator warning codes.
	 *
	 * @param array<string,mixed> $compatibility Current compatibility result.
	 * @return list<string>
	 */
	private function compatibility_warning_codes( array $compatibility ): array {
		$warnings = is_array( $compatibility['warnings'] ?? null ) ? $compatibility['warnings'] : array();
		$codes    = array();

		foreach ( $warnings as $warning ) {
			if ( ! is_array( $warning ) ) {
				continue;
			}

			$type     = is_string( $warning['type'] ?? null ) ? sanitize_key( $warning['type'] ) : 'unknown';
			$provider = is_string( $warning['provider'] ?? null ) ? sanitize_key( $warning['provider'] ) : 'unknown';
			$codes[]  = 'compatibility:' . $type . ':' . $provider;
		}

		return array_values( array_unique( $codes ) );
	}

	/**
	 * Keep compatibility evidence bounded to provider IDs and warning codes.
	 *
	 * @param array<string,mixed> $compatibility Current compatibility result.
	 * @return array<string,mixed>
	 */
	private function bounded_compatibility( array $compatibility ): array {
		$providers = is_array( $compatibility['providers'] ?? null ) ? $compatibility['providers'] : array();
		$warnings  = is_array( $compatibility['warnings'] ?? null ) ? $compatibility['warnings'] : array();
		$bounded   = array();

		foreach ( $warnings as $warning ) {
			if ( ! is_array( $warning ) ) {
				continue;
			}

			$bounded[] = array(
				'type'     => is_string( $warning['type'] ?? null ) ? $warning['type'] : 'unknown',
				'provider' => is_string( $warning['provider'] ?? null ) ? $warning['provider'] : null,
				'severity' => is_string( $warning['severity'] ?? null ) ? $warning['severity'] : 'advisory',
				'required' => true === ( $warning['required'] ?? false ),
			);
		}

		return array(
			'providers' => array(
				'seo'      => is_string( $providers['seo'] ?? null ) ? $providers['seo'] : 'native',
				'language' => is_string( $providers['language'] ?? null ) ? $providers['language'] : 'native',
			),
			'warnings'  => $bounded,
		);
	}

	/**
	 * Persist one required option or identify the exact failed write.
	 *
	 * @param string $option_name Option name.
	 * @param mixed  $value       Exact option value.
	 * @throws SetupWriteFailure When verified persistence fails.
	 */
	private function require_write( string $option_name, mixed $value ): void {
		if ( ! $this->writer->write( $option_name, $value ) ) {
			$failure = new SetupWriteFailure( $option_name );
			throw $failure;
		}
	}

	/**
	 * Snapshot one option list before mutation.
	 *
	 * @param array $option_names Option names.
	 * @phpstan-param list<string> $option_names
	 * @return array<string,array{exists:bool,value:mixed}>
	 */
	private function snapshots( array $option_names ): array {
		$snapshots = array();
		foreach ( array_values( array_unique( $option_names ) ) as $option_name ) {
			$snapshots[ $option_name ] = $this->writer->read( $option_name );
		}

		return $snapshots;
	}

	/**
	 * Return whether all deterministic target options already match.
	 *
	 * @param array<string,mixed> $targets Desired values.
	 */
	private function targets_match( array $targets ): bool {
		foreach ( $targets as $option_name => $value ) {
			$current = $this->writer->read( $option_name );
			if ( true !== $current['exists'] || $current['value'] !== $value ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Restore changed option snapshots in reverse order.
	 *
	 * @param array<string,array{exists:bool,value:mixed}> $snapshots       Original values.
	 * @param array                                        $changed_options Potentially changed options.
	 * @phpstan-param list<string> $changed_options
	 * @return list<string>
	 */
	private function rollback( array $snapshots, array $changed_options ): array {
		$errors = array();
		foreach ( array_reverse( array_values( array_unique( $changed_options ) ) ) as $option_name ) {
			if ( ! isset( $snapshots[ $option_name ] ) ) {
				continue;
			}

			$snapshot = $snapshots[ $option_name ];
			$restored = true === $snapshot['exists']
				? $this->writer->write( $option_name, $snapshot['value'] )
				: $this->writer->delete( $option_name );

			if ( ! $restored ) {
				$errors[] = 'setup-rollback-failed:' . $option_name;
			}
		}

		return $errors;
	}

	/**
	 * Normalize one unknown list into strings.
	 *
	 * @param mixed $value Candidate list.
	 * @return list<string>
	 */
	private function string_list( mixed $value ): array {
		return is_array( $value ) ? array_values( array_filter( $value, 'is_string' ) ) : array();
	}

	/**
	 * Produce one deterministic SHA-256 fingerprint.
	 *
	 * @param mixed $value Value to hash.
	 */
	private function fingerprint( mixed $value ): string {
		$encoded = wp_json_encode( $this->canonical( $value ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );

		return hash( 'sha256', false === $encoded ? '' : $encoded );
	}

	/**
	 * Canonicalize nested arrays for deterministic fingerprints.
	 *
	 * @param mixed $value Candidate value.
	 */
	private function canonical( mixed $value ): mixed {
		if ( ! is_array( $value ) ) {
			return $value;
		}

		if ( array_is_list( $value ) ) {
			return array_map( array( $this, 'canonical' ), $value );
		}

		ksort( $value );
		foreach ( $value as $key => $item ) {
			$value[ $key ] = $this->canonical( $item );
		}

		return $value;
	}

	/**
	 * Build one failed execution result.
	 *
	 * @param array                    $errors             Errors.
	 * @phpstan-param list<string> $errors
	 * @param array                    $warnings           Warnings.
	 * @phpstan-param list<string> $warnings
	 * @param array<string,mixed>|null $normalized         Normalized candidate.
	 * @param bool                     $rollback_attempted Whether rollback ran.
	 * @param array                    $changed_options    Options changed before failure.
	 * @phpstan-param list<string> $changed_options
	 * @return array<string,mixed>
	 */
	private function failure(
		array $errors,
		array $warnings,
		?array $normalized,
		bool $rollback_attempted,
		array $changed_options
	): array {
		return array(
			'schema_version'     => 1,
			'mode'               => 'theme-setup-execution',
			'valid'              => false,
			'applied'            => false,
			'idempotent'         => false,
			'errors'             => array_values( array_unique( $errors ) ),
			'warnings'           => array_values( array_unique( $warnings ) ),
			'normalized'         => $normalized,
			'changed_options'    => array_values( $changed_options ),
			'rollback_attempted' => $rollback_attempted,
			'report'             => null,
		);
	}

	/**
	 * Build one successful execution result.
	 *
	 * @param array<string,mixed> $normalized      Normalized setup.
	 * @param array               $warnings        Validation warnings.
	 * @phpstan-param list<string> $warnings
	 * @param string              $config_sha256   Configuration fingerprint.
	 * @param array               $changed_options Changed option names.
	 * @phpstan-param list<string> $changed_options
	 * @param array<string,mixed> $report          Stored non-sensitive report.
	 * @param bool                $idempotent      Whether no writes were needed.
	 * @return array<string,mixed>
	 */
	private function success(
		array $normalized,
		array $warnings,
		string $config_sha256,
		array $changed_options,
		array $report,
		bool $idempotent
	): array {
		return array(
			'schema_version'       => 1,
			'mode'                 => 'theme-setup-execution',
			'valid'                => true,
			'applied'              => true,
			'idempotent'           => $idempotent,
			'errors'               => array(),
			'warnings'             => $warnings,
			'normalized'           => $normalized,
			'configuration_sha256' => $config_sha256,
			'changed_options'      => array_values( $changed_options ),
			'rollback_attempted'   => false,
			'report'               => $report,
		);
	}
}
