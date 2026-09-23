<?php
/**
 * Theme-owned SEO/GEO setup wizard.
 *
 * @package SeoGeoTheme
 */

declare(strict_types=1);

namespace SeoGeo\Theme\Wizard;

use SeoGeo\Core\Geo\CrawlerPolicyResolver;
use SeoGeo\Core\Geo\LlmsTxtResolver;
use SeoGeo\Core\Geo\MarkdownAlternateResolver;
use SeoGeo\Core\Language\NativeLanguageConfiguration;
use SeoGeo\Core\Schema\SchemaIdentityResolver;
use SeoGeo\Core\Schema\SchemaLocalBusinessResolver;
use SeoGeo\Theme\Setup\SetupPlanner;

/**
 * Renders one WordPress-native preview/validation wizard without persistence.
 */
final class AdminSetupWizard {
	public const PAGE_SLUG    = 'seo-geo-setup';
	public const NONCE_ACTION = 'seo_geo_setup_preview';

	/**
	 * Setup plan authority.
	 *
	 * @var SetupPlanner
	 */
	private SetupPlanner $planner;

	/**
	 * Preview validator.
	 *
	 * @var SetupWizardPreview
	 */
	private SetupWizardPreview $preview;

	/**
	 * Localized copy.
	 *
	 * @var SetupWizardCopy
	 */
	private SetupWizardCopy $copy;

	/**
	 * Crawler-policy authority.
	 *
	 * @var CrawlerPolicyResolver
	 */
	private CrawlerPolicyResolver $crawler_policy;

	/**
	 * Registered admin page hook.
	 *
	 * @var string|null
	 */
	private ?string $page_hook = null;

	/**
	 * Construct the wizard.
	 *
	 * @param SetupPlanner|null          $planner        Optional setup planner.
	 * @param SetupWizardPreview|null    $preview        Optional preview validator.
	 * @param SetupWizardCopy|null       $copy           Optional localized copy.
	 * @param CrawlerPolicyResolver|null $crawler_policy Optional crawler authority.
	 */
	public function __construct(
		?SetupPlanner $planner = null,
		?SetupWizardPreview $preview = null,
		?SetupWizardCopy $copy = null,
		?CrawlerPolicyResolver $crawler_policy = null
	) {
		$this->planner        = $planner ?? new SetupPlanner();
		$this->preview        = $preview ?? new SetupWizardPreview();
		$this->copy           = $copy ?? new SetupWizardCopy();
		$this->crawler_policy = $crawler_policy ?? new CrawlerPolicyResolver();
	}

	/**
	 * Register admin hooks.
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'register_page' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Register the Appearance screen.
	 */
	public function register_page(): void {
		$this->page_hook = add_theme_page(
			$this->copy->text( 'page_title' ),
			$this->copy->text( 'menu_title' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render_page' )
		);
	}

	/**
	 * Load wizard assets only on its admin screen.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_assets( string $hook ): void {
		if ( null === $this->page_hook || $hook !== $this->page_hook ) {
			return;
		}

		$version = wp_get_theme()->get( 'Version' );

		wp_enqueue_style(
			'seo-geo-setup-wizard',
			get_template_directory_uri() . '/assets/admin/setup-wizard.css',
			array(),
			is_string( $version ) ? $version : null
		);
		wp_enqueue_script(
			'seo-geo-setup-wizard',
			get_template_directory_uri() . '/assets/admin/setup-wizard.js',
			array(),
			is_string( $version ) ? $version : null,
			true
		);
	}

	/**
	 * Render the setup wizard and optional validation preview.
	 */
	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html( $this->copy->text( 'forbidden' ) ), '', array( 'response' => 403 ) );
		}

		$plan      = $this->planner->plan();
		$candidate = $this->initial_candidate( $plan );
		$result    = null;

		if ( $this->is_preview_submission() ) {
			$submission = $this->submitted_candidate();
			$candidate  = $submission['candidate'];
			$result     = $this->preview->validate( $candidate, $submission['confirmed'] );
		}

		?>
		<div class="wrap seo-geo-setup-wizard">
			<h1><?php echo esc_html( $this->copy->text( 'page_title' ) ); ?></h1>
			<p><?php echo esc_html( $this->copy->text( 'intro' ) ); ?></p>
			<div class="notice notice-info inline">
				<p><?php echo esc_html( $this->copy->text( 'read_only_notice' ) ); ?></p>
			</div>

			<ol class="seo-geo-setup-wizard__steps" aria-label="<?php echo esc_attr( $this->copy->text( 'page_title' ) ); ?>">
				<li class="seo-geo-setup-wizard__step"><?php echo esc_html( $this->copy->text( 'step_preset' ) ); ?></li>
				<li class="seo-geo-setup-wizard__step"><?php echo esc_html( $this->copy->text( 'step_identity' ) ); ?></li>
				<li class="seo-geo-setup-wizard__step"><?php echo esc_html( $this->copy->text( 'step_geo' ) ); ?></li>
				<li class="seo-geo-setup-wizard__step"><?php echo esc_html( $this->copy->text( 'step_review' ) ); ?></li>
			</ol>

			<form method="post" action="<?php echo esc_url( admin_url( 'themes.php?page=' . self::PAGE_SLUG ) ); ?>">
				<?php wp_nonce_field( self::NONCE_ACTION ); ?>
				<input type="hidden" name="seo_geo_setup_action" value="preview">

				<?php $this->render_preset_languages( $candidate, $plan ); ?>
				<?php $this->render_identity( $candidate ); ?>
				<?php $this->render_geo( $candidate ); ?>

				<fieldset>
					<legend><?php echo esc_html( $this->copy->text( 'step_review' ) ); ?></legend>
					<p class="seo-geo-setup-wizard__checkbox">
						<input
							type="checkbox"
							id="seo-geo-preview-confirm"
							name="seo_geo_preview_confirm"
							value="1"
							required
						>
						<label for="seo-geo-preview-confirm"><?php echo esc_html( $this->copy->text( 'preview_confirm' ) ); ?></label>
					</p>
					<?php submit_button( $this->copy->text( 'validate' ), 'primary', 'seo_geo_validate', false ); ?>
					<p class="description"><?php echo esc_html( $this->copy->text( 'privacy_note' ) ); ?></p>
				</fieldset>
			</form>

			<?php if ( is_array( $result ) ) : ?>
				<?php $this->render_result( $result ); ?>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render preset/language fields.
	 *
	 * @param array<string,mixed> $candidate Current candidate.
	 * @param array<string,mixed> $plan      Read-only setup plan.
	 */
	private function render_preset_languages( array $candidate, array $plan ): void {
		$presets = is_array( $plan['available_presets'] ?? null ) ? $plan['available_presets'] : array();
		?>
		<fieldset>
			<legend><?php echo esc_html( $this->copy->text( 'step_preset' ) ); ?></legend>
			<div class="seo-geo-setup-wizard__grid">
				<div>
					<label for="seo-geo-preset"><?php echo esc_html( $this->copy->text( 'preset' ) ); ?></label>
					<select id="seo-geo-preset" name="seo_geo_preset" required>
						<option value=""><?php echo esc_html( $this->copy->text( 'choose_preset' ) ); ?></option>
						<?php foreach ( $presets as $preset ) : ?>
							<?php
							if ( ! is_array( $preset ) || ! is_string( $preset['id'] ?? null ) ) {
								continue;
							}
							$preset_id = $preset['id'];
							?>
							<option value="<?php echo esc_attr( $preset_id ); ?>" <?php selected( $candidate['preset'] ?? '', $preset_id ); ?>>
								<?php echo esc_html( $preset_id ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>
				<div>
					<label for="seo-geo-default-language"><?php echo esc_html( $this->copy->text( 'default_language' ) ); ?></label>
					<input
						type="text"
						id="seo-geo-default-language"
						name="seo_geo_default_language"
						value="<?php echo esc_attr( $this->string_value( $candidate['default_language'] ?? null ) ); ?>"
						required
					>
				</div>
				<div>
					<label for="seo-geo-languages"><?php echo esc_html( $this->copy->text( 'languages' ) ); ?></label>
					<textarea id="seo-geo-languages" name="seo_geo_languages" rows="4" required><?php echo esc_textarea( $this->language_lines( $candidate['languages'] ?? null ) ); ?></textarea>
					<p class="description"><?php echo esc_html( $this->copy->text( 'languages_help' ) ); ?></p>
				</div>
				<div>
					<label for="seo-geo-routing"><?php echo esc_html( $this->copy->text( 'routing' ) ); ?></label>
					<select id="seo-geo-routing" name="seo_geo_routing">
						<option value="disabled" <?php selected( $candidate['routing'] ?? '', NativeLanguageConfiguration::ROUTING_DISABLED ); ?>>
							<?php echo esc_html( $this->copy->text( 'routing_disabled' ) ); ?>
						</option>
						<option value="prefix" <?php selected( $candidate['routing'] ?? '', NativeLanguageConfiguration::ROUTING_PREFIX ); ?>>
							<?php echo esc_html( $this->copy->text( 'routing_prefix' ) ); ?>
						</option>
					</select>
				</div>
				<div>
					<label for="seo-geo-x-default"><?php echo esc_html( $this->copy->text( 'x_default' ) ); ?></label>
					<input
						type="text"
						id="seo-geo-x-default"
						name="seo_geo_x_default"
						value="<?php echo esc_attr( $this->string_value( $candidate['x_default'] ?? null ) ); ?>"
					>
				</div>
			</div>
		</fieldset>
		<?php
	}

	/**
	 * Render identity fields.
	 *
	 * @param array<string,mixed> $candidate Current candidate.
	 */
	private function render_identity( array $candidate ): void {
		$local = is_array( $candidate['local_business'] ?? null ) ? $candidate['local_business'] : array();
		?>
		<fieldset>
			<legend><?php echo esc_html( $this->copy->text( 'step_identity' ) ); ?></legend>
			<div class="seo-geo-setup-wizard__grid">
				<div>
					<label for="seo-geo-entity-type"><?php echo esc_html( $this->copy->text( 'entity_type' ) ); ?></label>
					<select id="seo-geo-entity-type" name="seo_geo_entity_type" required>
						<option value="organization" <?php selected( $candidate['site_entity_type'] ?? '', SchemaIdentityResolver::SITE_ENTITY_ORGANIZATION ); ?>>
							<?php echo esc_html( $this->copy->text( 'entity_organization' ) ); ?>
						</option>
						<option value="local_business" <?php selected( $candidate['site_entity_type'] ?? '', SchemaIdentityResolver::SITE_ENTITY_LOCAL_BUSINESS ); ?>>
							<?php echo esc_html( $this->copy->text( 'entity_local_business' ) ); ?>
						</option>
					</select>
				</div>
				<div class="seo-geo-setup-wizard__checkbox">
					<input type="checkbox" id="seo-geo-confirm-identity" name="seo_geo_confirm_identity" value="1" <?php checked( true === ( $candidate['confirm_identity'] ?? false ) ); ?>>
					<label for="seo-geo-confirm-identity"><?php echo esc_html( $this->copy->text( 'confirm_identity' ) ); ?></label>
				</div>
				<?php
				$fields = array(
					'type'             => 'local_business_type',
					'street_address'   => 'street_address',
					'address_locality' => 'address_locality',
					'address_region'   => 'address_region',
					'postal_code'      => 'postal_code',
					'address_country'  => 'address_country',
					'telephone'        => 'telephone',
					'price_range'      => 'price_range',
					'latitude'         => 'latitude',
					'longitude'        => 'longitude',
				);
				foreach ( $fields as $field => $label_key ) :
					?>
					<div>
						<label for="<?php echo esc_attr( 'seo-geo-' . str_replace( '_', '-', $field ) ); ?>">
							<?php echo esc_html( $this->copy->text( $label_key ) ); ?>
						</label>
						<input
							type="text"
							id="<?php echo esc_attr( 'seo-geo-' . str_replace( '_', '-', $field ) ); ?>"
							name="<?php echo esc_attr( 'seo_geo_lb_' . $field ); ?>"
							value="<?php echo esc_attr( $this->string_value( $local[ $field ] ?? null ) ); ?>"
						>
					</div>
				<?php endforeach; ?>
			</div>
		</fieldset>
		<?php
	}

	/**
	 * Render GEO/discovery fields.
	 *
	 * @param array<string,mixed> $candidate Current candidate.
	 */
	private function render_geo( array $candidate ): void {
		$crawler_policy = is_array( $candidate['crawler_policy'] ?? null ) ? $candidate['crawler_policy'] : array();
		?>
		<fieldset>
			<legend><?php echo esc_html( $this->copy->text( 'step_geo' ) ); ?></legend>
			<h3><?php echo esc_html( $this->copy->text( 'crawler_policy' ) ); ?></h3>
			<div class="seo-geo-setup-wizard__grid">
				<?php foreach ( $this->crawler_policy->supported_crawlers() as $crawler => $user_agent ) : ?>
					<div>
						<label for="<?php echo esc_attr( 'seo-geo-crawler-' . $crawler ); ?>"><code><?php echo esc_html( $user_agent ); ?></code></label>
						<select id="<?php echo esc_attr( 'seo-geo-crawler-' . $crawler ); ?>" name="<?php echo esc_attr( 'seo_geo_crawler_' . $crawler ); ?>">
							<?php foreach ( array( 'inherit', 'allow', 'disallow' ) as $state ) : ?>
								<option value="<?php echo esc_attr( $state ); ?>" <?php selected( $crawler_policy[ $crawler ] ?? 'inherit', $state ); ?>>
									<?php echo esc_html( $this->copy->text( $state ) ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</div>
				<?php endforeach; ?>
			</div>
			<p class="seo-geo-setup-wizard__checkbox">
				<input type="checkbox" id="seo-geo-llms" name="seo_geo_llms_txt_enabled" value="1" <?php checked( true === ( $candidate['llms_txt_enabled'] ?? false ) ); ?>>
				<label for="seo-geo-llms"><?php echo esc_html( $this->copy->text( 'llms_txt' ) ); ?></label>
			</p>
			<p class="seo-geo-setup-wizard__checkbox">
				<input type="checkbox" id="seo-geo-markdown" name="seo_geo_markdown_alternates_enabled" value="1" <?php checked( true === ( $candidate['markdown_alternates_enabled'] ?? false ) ); ?>>
				<label for="seo-geo-markdown"><?php echo esc_html( $this->copy->text( 'markdown' ) ); ?></label>
			</p>
		</fieldset>
		<?php
	}

	/**
	 * Render preview result.
	 *
	 * @param array<string,mixed> $result Validation result.
	 */
	private function render_result( array $result ): void {
		$valid    = true === ( $result['valid'] ?? false );
		$errors   = is_array( $result['errors'] ?? null ) ? $result['errors'] : array();
		$warnings = is_array( $result['warnings'] ?? null ) ? $result['warnings'] : array();
		$normalized = is_array( $result['normalized'] ?? null ) ? $result['normalized'] : array();
		?>
		<section
			id="seo-geo-setup-results"
			class="seo-geo-setup-wizard__results"
			tabindex="-1"
			aria-live="polite"
			aria-labelledby="seo-geo-setup-result-title"
		>
			<h2 id="seo-geo-setup-result-title">
				<?php echo esc_html( $this->copy->text( $valid ? 'result_ready' : 'result_invalid' ) ); ?>
			</h2>

			<?php $this->render_issues( 'errors', $errors, 'error' ); ?>
			<?php $this->render_issues( 'warnings', $warnings, 'warning' ); ?>

			<?php if ( $valid ) : ?>
				<?php
				$preset_language = is_array( $normalized['preset_language'] ?? null ) ? $normalized['preset_language'] : array();
				$entity_geo      = is_array( $normalized['entity_geo'] ?? null ) ? $normalized['entity_geo'] : array();
				?>
				<h3><?php echo esc_html( $this->copy->text( 'normalized' ) ); ?></h3>
				<dl class="seo-geo-setup-wizard__summary">
					<dt><?php echo esc_html( $this->copy->text( 'normalized_preset' ) ); ?></dt>
					<dd><code><?php echo esc_html( $this->string_value( $preset_language['preset'] ?? null ) ); ?></code></dd>
					<dt><?php echo esc_html( $this->copy->text( 'normalized_languages' ) ); ?></dt>
					<dd><?php echo esc_html( $this->normalized_languages_text( $preset_language['languages'] ?? null ) ); ?></dd>
					<dt><?php echo esc_html( $this->copy->text( 'normalized_entity' ) ); ?></dt>
					<dd><code><?php echo esc_html( $this->normalized_entity_text( $entity_geo['entity'] ?? null ) ); ?></code></dd>
					<dt><?php echo esc_html( $this->copy->text( 'normalized_geo' ) ); ?></dt>
					<dd><?php echo esc_html( $this->normalized_geo_text( $entity_geo['geo'] ?? null ) ); ?></dd>
				</dl>
			<?php endif; ?>
		</section>
		<?php
	}

	/**
	 * Render validation errors/warnings.
	 *
	 * @param string $label_key Copy label key.
	 * @param array  $issues    Issue codes.
	 * @param string $type      Notice type.
	 */
	private function render_issues( string $label_key, array $issues, string $type ): void {
		$issues = array_values( array_filter( $issues, 'is_string' ) );
		if ( array() === $issues ) {
			return;
		}
		?>
		<div class="<?php echo esc_attr( 'notice notice-' . $type . ' inline' ); ?>" role="<?php echo 'error' === $type ? 'alert' : 'status'; ?>">
			<p><strong><?php echo esc_html( $this->copy->text( $label_key ) ); ?></strong></p>
			<ul>
				<?php foreach ( $issues as $issue ) : ?>
					<li><?php echo esc_html( $this->issue_text( $issue ) ); ?></li>
				<?php endforeach; ?>
			</ul>
		</div>
		<?php
	}

	/**
	 * Return the current read-only values used to prefill the form.
	 *
	 * @param array<string,mixed> $plan Setup plan.
	 * @return array<string,mixed>
	 */
	private function initial_candidate( array $plan ): array {
		$current   = is_array( $plan['current'] ?? null ) ? $plan['current'] : array();
		$languages = is_array( $current['languages'] ?? null ) ? $current['languages'] : array();
		$identity  = get_option( SchemaIdentityResolver::OPTION_NAME, array() );
		$local     = get_option( SchemaLocalBusinessResolver::OPTION_NAME, array() );
		$llms      = get_option( LlmsTxtResolver::OPTION_NAME, array() );
		$markdown  = get_option( MarkdownAlternateResolver::OPTION_NAME, array() );

		$crawlers = array();
		foreach ( array_keys( $this->crawler_policy->supported_crawlers() ) as $crawler ) {
			$crawlers[ $crawler ] = $this->crawler_policy->state( $crawler );
		}

		return array(
			'preset'                       => is_string( $current['preset'] ?? null ) ? $current['preset'] : '',
			'default_language'             => is_string( $languages['default'] ?? null ) ? $languages['default'] : '',
			'languages'                    => is_array( $languages['locales'] ?? null ) ? $languages['locales'] : array(),
			'routing'                      => is_string( $languages['routing'] ?? null ) ? $languages['routing'] : NativeLanguageConfiguration::ROUTING_DISABLED,
			'x_default'                    => is_string( $languages['x_default'] ?? null ) ? $languages['x_default'] : '',
			'site_entity_type'             => is_array( $identity ) && is_string( $identity['site_entity_type'] ?? null ) ? $identity['site_entity_type'] : SchemaIdentityResolver::SITE_ENTITY_ORGANIZATION,
			'confirm_identity'             => false,
			'local_business'               => is_array( $local ) ? $local : array(),
			'crawler_policy'               => $crawlers,
			'llms_txt_enabled'             => is_array( $llms ) && true === ( $llms['enabled'] ?? false ),
			'markdown_alternates_enabled'  => is_array( $markdown ) && true === ( $markdown['enabled'] ?? false ),
		);
	}

	/**
	 * Parse one nonce-verified preview submission.
	 *
	 * @return array{candidate:array<string,mixed>,confirmed:bool}
	 */
	private function submitted_candidate(): array {
		check_admin_referer( self::NONCE_ACTION );

		$local = array();
		foreach (
			array(
				'type',
				'street_address',
				'address_locality',
				'address_region',
				'postal_code',
				'address_country',
				'telephone',
				'price_range',
				'latitude',
				'longitude',
			) as $field
		) {
			$key = 'seo_geo_lb_' . $field;
			if ( isset( $_POST[ $key ] ) && is_string( $_POST[ $key ] ) ) {
				$local[ $field ] = sanitize_text_field( wp_unslash( $_POST[ $key ] ) );
			}
		}

		$crawlers = array();
		foreach ( array_keys( $this->crawler_policy->supported_crawlers() ) as $crawler ) {
			$key = 'seo_geo_crawler_' . $crawler;
			if ( isset( $_POST[ $key ] ) && is_string( $_POST[ $key ] ) ) {
				$crawlers[ $crawler ] = sanitize_key( wp_unslash( $_POST[ $key ] ) );
			}
		}

		$language_lines = isset( $_POST['seo_geo_languages'] ) && is_string( $_POST['seo_geo_languages'] )
			? sanitize_textarea_field( wp_unslash( $_POST['seo_geo_languages'] ) )
			: '';

		return array(
			'candidate' => array(
				'preset'                      => $this->post_key( 'seo_geo_preset' ),
				'default_language'            => $this->post_text( 'seo_geo_default_language' ),
				'languages'                   => $this->parse_language_lines( $language_lines ),
				'routing'                     => $this->post_key( 'seo_geo_routing' ),
				'x_default'                   => $this->nullable_post_text( 'seo_geo_x_default' ),
				'site_entity_type'            => $this->post_key( 'seo_geo_entity_type' ),
				'confirm_identity'            => isset( $_POST['seo_geo_confirm_identity'] ),
				'local_business'              => $local,
				'crawler_policy'              => $crawlers,
				'llms_txt_enabled'            => isset( $_POST['seo_geo_llms_txt_enabled'] ),
				'markdown_alternates_enabled' => isset( $_POST['seo_geo_markdown_alternates_enabled'] ),
			),
			'confirmed' => isset( $_POST['seo_geo_preview_confirm'] ),
		);
	}

	/**
	 * Report whether the current request is this page's preview POST.
	 */
	private function is_preview_submission(): bool {
		$method = isset( $_SERVER['REQUEST_METHOD'] ) && is_string( $_SERVER['REQUEST_METHOD'] )
			? strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) )
			: '';

		return 'POST' === $method;
	}

	/**
	 * Parse code=locale lines into the native language map.
	 *
	 * @return array<string,string>
	 */
	private function parse_language_lines( string $value ): array {
		$languages = array();
		$lines     = preg_split( '/\r\n|\r|\n/', $value ) ?: array();

		foreach ( $lines as $index => $line ) {
			$line = trim( $line );
			if ( '' === $line ) {
				continue;
			}

			$parts = explode( '=', $line, 2 );
			if ( 2 !== count( $parts ) ) {
				$languages[ '__invalid_' . (string) $index ] = $line;
				continue;
			}

			$code   = trim( $parts[0] );
			$locale = trim( $parts[1] );
			if ( isset( $languages[ $code ] ) ) {
				$languages[ '__duplicate_' . (string) $index ] = $locale;
				continue;
			}

			$languages[ $code ] = $locale;
		}

		return $languages;
	}

	/**
	 * Convert a language map into editable lines.
	 *
	 * @param mixed $value Candidate language map.
	 */
	private function language_lines( mixed $value ): string {
		if ( ! is_array( $value ) ) {
			return '';
		}

		$lines = array();
		foreach ( $value as $code => $locale ) {
			if ( is_string( $code ) && is_string( $locale ) ) {
				$lines[] = $code . '=' . $locale;
			}
		}

		return implode( "\n", $lines );
	}

	/**
	 * Return one sanitized POST key.
	 *
	 * @param string $name Field name.
	 */
	private function post_key( string $name ): string {
		return isset( $_POST[ $name ] ) && is_string( $_POST[ $name ] )
			? sanitize_key( wp_unslash( $_POST[ $name ] ) )
			: '';
	}

	/**
	 * Return one sanitized POST text value.
	 *
	 * @param string $name Field name.
	 */
	private function post_text( string $name ): string {
		return isset( $_POST[ $name ] ) && is_string( $_POST[ $name ] )
			? sanitize_text_field( wp_unslash( $_POST[ $name ] ) )
			: '';
	}

	/**
	 * Return one nullable sanitized POST text value.
	 *
	 * @param string $name Field name.
	 */
	private function nullable_post_text( string $name ): ?string {
		$value = $this->post_text( $name );

		return '' !== $value ? $value : null;
	}

	/**
	 * Normalize one scalar display value.
	 *
	 * @param mixed $value Candidate scalar.
	 */
	private function string_value( mixed $value ): string {
		return is_scalar( $value ) ? (string) $value : '';
	}

	/**
	 * Human-readable normalized language summary.
	 *
	 * @param mixed $value Normalized language configuration.
	 */
	private function normalized_languages_text( mixed $value ): string {
		if ( ! is_array( $value ) ) {
			return $this->copy->text( 'not_set' );
		}

		$languages = is_array( $value['languages'] ?? null ) ? $value['languages'] : array();
		$parts     = array();
		foreach ( $languages as $code => $locale ) {
			if ( is_string( $code ) && is_string( $locale ) ) {
				$parts[] = $code . '=' . $locale;
			}
		}

		return array() !== $parts ? implode( ', ', $parts ) : $this->copy->text( 'not_set' );
	}

	/**
	 * Human-readable normalized entity summary.
	 *
	 * @param mixed $value Normalized entity configuration.
	 */
	private function normalized_entity_text( mixed $value ): string {
		return is_array( $value ) && is_string( $value['site_entity_type'] ?? null )
			? $value['site_entity_type']
			: $this->copy->text( 'not_set' );
	}

	/**
	 * Human-readable normalized GEO summary.
	 *
	 * @param mixed $value Normalized GEO configuration.
	 */
	private function normalized_geo_text( mixed $value ): string {
		if ( ! is_array( $value ) ) {
			return $this->copy->text( 'not_set' );
		}

		$crawler = is_array( $value['crawler_policy']['value'] ?? null ) ? $value['crawler_policy']['value'] : array();
		$llms    = true === ( $value['llms_txt']['enabled'] ?? false ) ? 'llms.txt:on' : 'llms.txt:off';
		$markdown = true === ( $value['markdown']['enabled'] ?? false ) ? 'markdown:on' : 'markdown:off';

		$parts = array( $llms, $markdown );
		foreach ( $crawler as $key => $state ) {
			if ( is_string( $key ) && is_string( $state ) ) {
				$parts[] = $key . ':' . $state;
			}
		}

		return implode( ', ', $parts );
	}

	/**
	 * Convert one issue code into bounded localized guidance.
	 *
	 * @param string $issue Validation issue code.
	 */
	private function issue_text( string $issue ): string {
		if ( 'preview-confirmation-required' === $issue ) {
			return $this->copy->text( 'confirmation_required' );
		}

		return $this->copy->text( 'validation_issue' ) . ' ' . $issue;
	}
}
