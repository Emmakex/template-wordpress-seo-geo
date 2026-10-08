<?php
/**
 * SEO/GEO Manager WordPress admin dashboard.
 *
 * @package SeoGeoManager
 */

declare(strict_types=1);

namespace SeoGeo\Manager\Admin;

final class Dashboard {
	private const SLUG = 'seo-geo-manager';

	/**
	 * Register the authenticated WordPress admin surface.
	 */
	public static function register(): void {
		add_action( 'admin_menu', array( self::class, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( self::class, 'enqueue_assets' ) );
	}

	/**
	 * Register the top-level Manager page.
	 */
	public static function register_menu(): void {
		add_menu_page(
			__( 'SEO/GEO Manager', 'seo-geo-manager' ),
			__( 'SEO/GEO Manager', 'seo-geo-manager' ),
			'edit_posts',
			self::SLUG,
			array( self::class, 'render' ),
			'dashicons-chart-area',
			58
		);
	}

	/**
	 * Load Manager assets only on its own screen.
	 */
	public static function enqueue_assets( string $hook_suffix ): void {
		if ( 'toplevel_page_' . self::SLUG !== $hook_suffix ) {
			return;
		}

		wp_enqueue_style(
			'seo-geo-manager-dashboard',
			self::asset_url( 'assets/admin/dashboard.css' ),
			array(),
			SEO_GEO_MANAGER_VERSION
		);

		wp_enqueue_script(
			'seo-geo-manager-dashboard',
			self::asset_url( 'assets/admin/dashboard.js' ),
			array( 'wp-api-fetch' ),
			SEO_GEO_MANAGER_VERSION,
			true
		);

		wp_add_inline_script(
			'seo-geo-manager-dashboard',
			'window.SeoGeoManagerDashboard = ' . wp_json_encode(
				array(
					'intelligencePath' => '/seo-geo-manager/v1/site/intelligence',
					'version'          => SEO_GEO_MANAGER_VERSION,
					'labels'           => array(
						'analyzing' => __( 'Analizando el sitio…', 'seo-geo-manager' ),
						'error'     => __( 'No se pudo completar el análisis.', 'seo-geo-manager' ),
					),
				)
			) . ';',
			'before'
		);
	}

	/**
	 * Build a plugin-relative asset URL without coupling the class to a global URL constant.
	 */
	private static function asset_url( string $relative_path ): string {
		$plugin_file = dirname( __DIR__, 2 ) . '/seo-geo-manager.php';

		return plugins_url( ltrim( $relative_path, '/' ), $plugin_file );
	}

	/**
	 * Render the Build / Finish dashboard shell.
	 */
	public static function render(): void {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'No tienes permisos para acceder a SEO/GEO Manager.', 'seo-geo-manager' ) );
		}
		?>
		<div class="wrap seo-geo-manager-admin" id="seo-geo-manager-dashboard">
			<div class="seo-geo-manager-admin__hero">
				<div>
					<p class="seo-geo-manager-admin__eyebrow"><?php esc_html_e( 'Build / Finish', 'seo-geo-manager' ); ?></p>
					<h1><?php esc_html_e( 'SEO/GEO Manager', 'seo-geo-manager' ); ?></h1>
					<p class="seo-geo-manager-admin__lead"><?php esc_html_e( 'Analiza esta instalación, detecta bloqueos antes del lanzamiento y prepara mejoras controladas sin salir de WordPress.', 'seo-geo-manager' ); ?></p>
				</div>
				<div class="seo-geo-manager-admin__actions">
					<button type="button" class="button button-primary button-hero" data-seo-geo-action="analyze">
						<?php esc_html_e( 'Analizar sitio', 'seo-geo-manager' ); ?>
					</button>
					<label class="seo-geo-manager-admin__rendered-toggle">
						<input type="checkbox" data-seo-geo-rendered checked>
						<?php esc_html_e( 'Verificar frontend renderizado', 'seo-geo-manager' ); ?>
					</label>
				</div>
			</div>

			<div class="notice notice-info inline seo-geo-manager-admin__notice" data-seo-geo-status role="status" aria-live="polite">
				<p><?php esc_html_e( 'Pulsa “Analizar sitio” para generar el primer diagnóstico Build / Finish.', 'seo-geo-manager' ); ?></p>
			</div>

			<section class="seo-geo-manager-admin__metrics" aria-label="<?php esc_attr_e( 'Resumen del diagnóstico', 'seo-geo-manager' ); ?>">
				<div class="seo-geo-manager-admin__metric"><span><?php esc_html_e( 'Estado', 'seo-geo-manager' ); ?></span><strong data-seo-geo-metric="overall">—</strong></div>
				<div class="seo-geo-manager-admin__metric"><span><?php esc_html_e( 'Bloqueos', 'seo-geo-manager' ); ?></span><strong data-seo-geo-metric="blockers">—</strong></div>
				<div class="seo-geo-manager-admin__metric"><span><?php esc_html_e( 'Avisos', 'seo-geo-manager' ); ?></span><strong data-seo-geo-metric="warnings">—</strong></div>
				<div class="seo-geo-manager-admin__metric"><span><?php esc_html_e( 'Autoridad SEO', 'seo-geo-manager' ); ?></span><strong data-seo-geo-metric="seo">—</strong></div>
			</section>

			<section class="seo-geo-manager-admin__panel">
				<div class="seo-geo-manager-admin__panel-heading">
					<div>
						<p class="seo-geo-manager-admin__eyebrow"><?php esc_html_e( 'Launch readiness', 'seo-geo-manager' ); ?></p>
						<h2><?php esc_html_e( 'Qué debemos corregir antes de dar la web por terminada', 'seo-geo-manager' ); ?></h2>
					</div>
				</div>
				<div class="seo-geo-manager-admin__checks" data-seo-geo-checks>
					<p class="description"><?php esc_html_e( 'Todavía no hay un análisis cargado.', 'seo-geo-manager' ); ?></p>
				</div>
			</section>

			<section class="seo-geo-manager-admin__grid">
				<div class="seo-geo-manager-admin__panel">
					<h2><?php esc_html_e( 'Estructura y contenido', 'seo-geo-manager' ); ?></h2>
					<div data-seo-geo-structure><p class="description">—</p></div>
				</div>
				<div class="seo-geo-manager-admin__panel">
					<h2><?php esc_html_e( 'Navegación y entorno', 'seo-geo-manager' ); ?></h2>
					<div data-seo-geo-navigation><p class="description">—</p></div>
				</div>
			</section>

			<details class="seo-geo-manager-admin__raw">
				<summary><?php esc_html_e( 'Ver diagnóstico JSON completo', 'seo-geo-manager' ); ?></summary>
				<pre data-seo-geo-raw>{}</pre>
			</details>
		</div>
		<?php
	}
}
