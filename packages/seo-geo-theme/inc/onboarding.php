<?php
/**
 * Theme-owned SEO/GEO onboarding.
 *
 * @package SeoGeoTheme
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Return the onboarding admin-page slug.
 */
function seo_geo_theme_onboarding_page_slug(): string {
	return 'seo-geo-setup';
}

/**
 * Resolve the onboarding copy locale from the current admin user.
 *
 * @param string|null $locale Optional explicit locale for tests.
 */
function seo_geo_theme_onboarding_locale( ?string $locale = null ): string {
	$locale = null === $locale ? get_user_locale() : $locale;

	return str_starts_with( strtolower( $locale ), 'es' ) ? 'es_ES' : 'en_US';
}

/**
 * Return one customer-facing onboarding string with EN fallback.
 *
 * @param string      $key    Copy key.
 * @param string|null $locale Optional explicit locale for tests.
 */
function seo_geo_theme_onboarding_copy( string $key, ?string $locale = null ): string {
	$copy = array(
		'en_US' => array(
			'page_title'       => 'SEO/GEO setup',
			'menu_title'       => 'SEO/GEO Setup',
			'intro'            => 'Configure the reusable SEO/GEO baseline from one server-authoritative setup screen. No SEO/GEO plugin is required for the baseline.',
			'preset_heading'   => '1. Choose a site preset',
			'preset_intro'     => 'The preset selects reusable site architecture and editor patterns. It does not create business facts, editorial claims or commerce data.',
			'preset_label'     => 'Site preset',
			'no_preset'        => 'No preset selected',
			'save_preset'      => 'Save preset',
			'saved'            => 'The site preset was saved.',
			'invalid'          => 'The submitted preset was not recognized. No configuration was changed.',
			'status_heading'   => 'Current setup status',
			'active_preset'    => 'Active preset',
			'site_locale'      => 'WordPress site locale',
			'baseline'         => 'Baseline',
			'baseline_value'   => 'Self-contained theme; zero required plugins',
			'not_selected'     => 'Not selected',
			'next_heading'     => 'Next onboarding step',
			'next_copy'        => 'Language configuration is the next Phase 8 microphase. Existing language, Schema and crawler authorities will be reused rather than duplicated.',
			'security_note'    => 'Only administrators with manage_options can change onboarding configuration.',
		),
		'es_ES' => array(
			'page_title'       => 'Configuración SEO/GEO',
			'menu_title'       => 'Configurar SEO/GEO',
			'intro'            => 'Configura la base reutilizable SEO/GEO desde una única pantalla autoritativa del servidor. La base no requiere instalar ningún plugin SEO/GEO.',
			'preset_heading'   => '1. Elige un preset de sitio',
			'preset_intro'     => 'El preset selecciona arquitectura reutilizable y patrones del editor. No crea datos de negocio, afirmaciones editoriales ni información comercial.',
			'preset_label'     => 'Preset del sitio',
			'no_preset'        => 'Ningún preset seleccionado',
			'save_preset'      => 'Guardar preset',
			'saved'            => 'El preset del sitio se ha guardado.',
			'invalid'          => 'El preset enviado no se reconoce. No se ha modificado la configuración.',
			'status_heading'   => 'Estado actual de la configuración',
			'active_preset'    => 'Preset activo',
			'site_locale'      => 'Locale del sitio WordPress',
			'baseline'         => 'Base',
			'baseline_value'   => 'Theme autosuficiente; cero plugins obligatorios',
			'not_selected'     => 'No seleccionado',
			'next_heading'     => 'Siguiente paso del onboarding',
			'next_copy'        => 'La configuración de idiomas es la siguiente microfase de la Fase 8. Se reutilizarán las autoridades existentes de idioma, Schema y crawlers sin duplicarlas.',
			'security_note'    => 'Solo los administradores con manage_options pueden modificar la configuración del onboarding.',
		),
	);

	$resolved_locale = seo_geo_theme_onboarding_locale( $locale );
	$value           = $copy[ $resolved_locale ][ $key ] ?? $copy['en_US'][ $key ] ?? '';

	/**
	 * Filter one onboarding UI string.
	 *
	 * @param string $value           Resolved copy.
	 * @param string $key             Copy key.
	 * @param string $resolved_locale Resolved EN/ES locale.
	 */
	return (string) apply_filters( 'seo_geo_theme_onboarding_copy', $value, $key, $resolved_locale );
}

/**
 * Validate a submitted preset identifier.
 *
 * Empty string is the intentional "no preset" state. Null means invalid input.
 *
 * @param mixed $value Submitted value.
 * @return string|null
 */
function seo_geo_theme_onboarding_validate_preset( $value ): ?string {
	if ( ! is_string( $value ) ) {
		return null;
	}

	$value = trim( $value );
	if ( '' === $value ) {
		return '';
	}

	$preset_id = sanitize_key( $value );
	if ( $preset_id !== $value || ! in_array( $preset_id, seo_geo_theme_preset_ids(), true ) ) {
		return null;
	}

	return $preset_id;
}

/**
 * Resolve one localized preset label from its declarative package.
 *
 * @param string $preset_id Allowlisted preset identifier.
 */
function seo_geo_theme_onboarding_preset_label( string $preset_id ): string {
	$document = seo_geo_theme_preset_document( $preset_id, 'patterns.json' );
	$labels   = is_array( $document ) ? ( $document['category']['labels'] ?? null ) : null;
	$locale   = seo_geo_theme_onboarding_locale();

	if ( is_array( $labels ) ) {
		$candidate = $labels[ $locale ] ?? $labels['en_US'] ?? null;
		if ( is_string( $candidate ) && '' !== trim( $candidate ) ) {
			return $candidate;
		}
	}

	return ucwords( str_replace( '-', ' ', $preset_id ) );
}

/**
 * Register the theme-owned onboarding screen.
 */
function seo_geo_theme_onboarding_register_page(): void {
	add_theme_page(
		seo_geo_theme_onboarding_copy( 'page_title' ),
		seo_geo_theme_onboarding_copy( 'menu_title' ),
		'manage_options',
		seo_geo_theme_onboarding_page_slug(),
		'seo_geo_theme_onboarding_render_page'
	);
}
add_action( 'admin_menu', 'seo_geo_theme_onboarding_register_page' );

/**
 * Build the onboarding page URL.
 *
 * @param string|null $status Optional result status.
 */
function seo_geo_theme_onboarding_url( ?string $status = null ): string {
	$args = array(
		'page' => seo_geo_theme_onboarding_page_slug(),
	);

	if ( null !== $status ) {
		$args['seo_geo_setup_status'] = sanitize_key( $status );
	}

	return add_query_arg( $args, admin_url( 'themes.php' ) );
}

/**
 * Save the selected preset from the privileged admin-post action.
 */
function seo_geo_theme_onboarding_save_preset(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html( seo_geo_theme_onboarding_copy( 'security_note' ) ) );
	}

	check_admin_referer( 'seo_geo_onboarding_preset', 'seo_geo_onboarding_nonce' );

	$submitted = isset( $_POST['seo_geo_active_preset'] )
		? wp_unslash( $_POST['seo_geo_active_preset'] )
		: null;
	$preset_id = seo_geo_theme_onboarding_validate_preset( $submitted );

	if ( null === $preset_id ) {
		wp_safe_redirect( seo_geo_theme_onboarding_url( 'invalid' ) );
		exit;
	}

	if ( '' === $preset_id ) {
		delete_option( 'seo_geo_active_preset' );
	} else {
		update_option( 'seo_geo_active_preset', $preset_id );
	}

	wp_safe_redirect( seo_geo_theme_onboarding_url( 'saved' ) );
	exit;
}
add_action( 'admin_post_seo_geo_save_preset', 'seo_geo_theme_onboarding_save_preset' );

/**
 * Render one result notice from the redirect status.
 */
function seo_geo_theme_onboarding_render_notice(): void {
	$status = isset( $_GET['seo_geo_setup_status'] )
		? sanitize_key( wp_unslash( $_GET['seo_geo_setup_status'] ) )
		: '';

	if ( 'saved' === $status ) {
		printf(
			'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
			esc_html( seo_geo_theme_onboarding_copy( 'saved' ) )
		);
	}

	if ( 'invalid' === $status ) {
		printf(
			'<div class="notice notice-error"><p>%s</p></div>',
			esc_html( seo_geo_theme_onboarding_copy( 'invalid' ) )
		);
	}
}

/**
 * Render the Phase 8 onboarding screen.
 */
function seo_geo_theme_onboarding_render_page(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html( seo_geo_theme_onboarding_copy( 'security_note' ) ) );
	}

	$active_preset = seo_geo_theme_active_preset_id();
	?>
	<div class="wrap">
		<h1><?php echo esc_html( seo_geo_theme_onboarding_copy( 'page_title' ) ); ?></h1>
		<p><?php echo esc_html( seo_geo_theme_onboarding_copy( 'intro' ) ); ?></p>

		<?php seo_geo_theme_onboarding_render_notice(); ?>

		<h2><?php echo esc_html( seo_geo_theme_onboarding_copy( 'preset_heading' ) ); ?></h2>
		<p><?php echo esc_html( seo_geo_theme_onboarding_copy( 'preset_intro' ) ); ?></p>

		<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
			<input type="hidden" name="action" value="seo_geo_save_preset">
			<?php wp_nonce_field( 'seo_geo_onboarding_preset', 'seo_geo_onboarding_nonce' ); ?>

			<table class="form-table" role="presentation">
				<tbody>
					<tr>
						<th scope="row">
							<label for="seo-geo-active-preset">
								<?php echo esc_html( seo_geo_theme_onboarding_copy( 'preset_label' ) ); ?>
							</label>
						</th>
						<td>
							<select id="seo-geo-active-preset" name="seo_geo_active_preset">
								<option value=""<?php selected( null, $active_preset ); ?>>
									<?php echo esc_html( seo_geo_theme_onboarding_copy( 'no_preset' ) ); ?>
								</option>
								<?php foreach ( seo_geo_theme_preset_ids() as $preset_id ) : ?>
									<option value="<?php echo esc_attr( $preset_id ); ?>"<?php selected( $preset_id, $active_preset ); ?>>
										<?php echo esc_html( seo_geo_theme_onboarding_preset_label( $preset_id ) ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
				</tbody>
			</table>

			<?php submit_button( seo_geo_theme_onboarding_copy( 'save_preset' ) ); ?>
		</form>

		<h2><?php echo esc_html( seo_geo_theme_onboarding_copy( 'status_heading' ) ); ?></h2>
		<table class="widefat striped">
			<tbody>
				<tr>
					<th scope="row"><?php echo esc_html( seo_geo_theme_onboarding_copy( 'active_preset' ) ); ?></th>
					<td>
						<?php
						echo esc_html(
							null === $active_preset
								? seo_geo_theme_onboarding_copy( 'not_selected' )
								: seo_geo_theme_onboarding_preset_label( $active_preset )
						);
						?>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php echo esc_html( seo_geo_theme_onboarding_copy( 'site_locale' ) ); ?></th>
					<td><code><?php echo esc_html( get_locale() ); ?></code></td>
				</tr>
				<tr>
					<th scope="row"><?php echo esc_html( seo_geo_theme_onboarding_copy( 'baseline' ) ); ?></th>
					<td><?php echo esc_html( seo_geo_theme_onboarding_copy( 'baseline_value' ) ); ?></td>
				</tr>
			</tbody>
		</table>

		<h2><?php echo esc_html( seo_geo_theme_onboarding_copy( 'next_heading' ) ); ?></h2>
		<p><?php echo esc_html( seo_geo_theme_onboarding_copy( 'next_copy' ) ); ?></p>
	</div>
	<?php
}
