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

		wp_enqueue_style(
			'seo-geo-manager-focused-finalization',
			self::asset_url( 'assets/admin/focused-finalization.css' ),
			array( 'seo-geo-manager-dashboard' ),
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
	 * Render the focused Build / Finish dashboard.
	 */
	public static function render(): void {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'No tienes permisos para acceder a SEO/GEO Manager.', 'seo-geo-manager' ) );
		}
		?>
		<div class="wrap seo-geo-manager-admin" id="seo-geo-manager-dashboard">
			<div class="seo-geo-manager-admin__hero seo-geo-manager-admin__hero--focused">
				<div>
					<p class="seo-geo-manager-admin__eyebrow"><?php esc_html_e( 'Build / Finish · finalización', 'seo-geo-manager' ); ?></p>
					<h1><?php esc_html_e( 'Finalizar migración SEO/GEO', 'seo-geo-manager' ); ?></h1>
					<p class="seo-geo-manager-admin__lead"><?php esc_html_e( 'Una sola ruta operativa: comprobar el estado actual, aplicar únicamente la operación SEO/GEO necesaria y verificar el resultado.', 'seo-geo-manager' ); ?></p>
				</div>
				<div class="seo-geo-manager-admin__version">
					<span><?php esc_html_e( 'Manager', 'seo-geo-manager' ); ?></span>
					<strong>v<?php echo esc_html( SEO_GEO_MANAGER_VERSION ); ?></strong>
				</div>
			</div>

			<section class="seo-geo-manager-admin__panel seo-geo-manager-flow" aria-label="<?php esc_attr_e( 'Flujo de finalización', 'seo-geo-manager' ); ?>">
				<div class="seo-geo-manager-flow__step is-current">
					<span>1</span>
					<div><strong><?php esc_html_e( 'Comprobar', 'seo-geo-manager' ); ?></strong><small><?php esc_html_e( 'Field Gate actual', 'seo-geo-manager' ); ?></small></div>
				</div>
				<div class="seo-geo-manager-flow__step">
					<span>2</span>
					<div><strong><?php esc_html_e( 'Aplicar', 'seo-geo-manager' ); ?></strong><small><?php esc_html_e( 'Cambio protegido', 'seo-geo-manager' ); ?></small></div>
				</div>
				<div class="seo-geo-manager-flow__step">
					<span>3</span>
					<div><strong><?php esc_html_e( 'Verificar', 'seo-geo-manager' ); ?></strong><small><?php esc_html_e( 'Confirmar o revertir', 'seo-geo-manager' ); ?></small></div>
				</div>
			</section>

			<?php if ( current_user_can( 'manage_options' ) ) : ?>
				<div data-seo-geo-field-slot></div>
				<div data-seo-geo-finalization-actions></div>
			<?php else : ?>
				<div class="notice notice-warning inline seo-geo-manager-admin__notice"><p><?php esc_html_e( 'La finalización protegida requiere permisos de administrador.', 'seo-geo-manager' ); ?></p></div>
			<?php endif; ?>

			<details class="seo-geo-manager-admin__technical">
				<summary>
					<strong><?php esc_html_e( 'Detalles técnicos y herramientas avanzadas', 'seo-geo-manager' ); ?></strong>
					<span><?php esc_html_e( 'Diagnóstico completo, correcciones auxiliares, historial y JSON.', 'seo-geo-manager' ); ?></span>
				</summary>
				<div class="seo-geo-manager-admin__technical-body">
					<div class="seo-geo-manager-admin__technical-actions">
						<button type="button" class="button button-secondary" data-seo-geo-action="analyze"><?php esc_html_e( 'Ejecutar diagnóstico completo', 'seo-geo-manager' ); ?></button>
						<label class="seo-geo-manager-admin__rendered-toggle">
							<input type="checkbox" data-seo-geo-rendered checked>
							<?php esc_html_e( 'Verificar frontend renderizado', 'seo-geo-manager' ); ?>
						</label>
					</div>

					<div class="notice notice-info inline seo-geo-manager-admin__notice" data-seo-geo-status role="status" aria-live="polite">
						<p><?php esc_html_e( 'El diagnóstico avanzado es opcional para la finalización actual.', 'seo-geo-manager' ); ?></p>
					</div>

					<section class="seo-geo-manager-admin__metrics" aria-label="<?php esc_attr_e( 'Resumen del diagnóstico técnico', 'seo-geo-manager' ); ?>">
						<div class="seo-geo-manager-admin__metric"><span><?php esc_html_e( 'Estado', 'seo-geo-manager' ); ?></span><strong data-seo-geo-metric="overall">—</strong></div>
						<div class="seo-geo-manager-admin__metric"><span><?php esc_html_e( 'Bloqueos', 'seo-geo-manager' ); ?></span><strong data-seo-geo-metric="blockers">—</strong></div>
						<div class="seo-geo-manager-admin__metric"><span><?php esc_html_e( 'Avisos', 'seo-geo-manager' ); ?></span><strong data-seo-geo-metric="warnings">—</strong></div>
						<div class="seo-geo-manager-admin__metric"><span><?php esc_html_e( 'Autoridad SEO', 'seo-geo-manager' ); ?></span><strong data-seo-geo-metric="seo">—</strong></div>
					</section>

					<section class="seo-geo-manager-admin__panel">
						<p class="seo-geo-manager-admin__eyebrow"><?php esc_html_e( 'Launch readiness', 'seo-geo-manager' ); ?></p>
						<h2><?php esc_html_e( 'Diagnóstico técnico completo', 'seo-geo-manager' ); ?></h2>
						<div class="seo-geo-manager-admin__checks" data-seo-geo-checks><p class="description"><?php esc_html_e( 'Todavía no hay un análisis técnico cargado.', 'seo-geo-manager' ); ?></p></div>
					</section>

					<section class="seo-geo-manager-admin__panel">
						<p class="seo-geo-manager-admin__eyebrow"><?php esc_html_e( 'Actionable diagnostics', 'seo-geo-manager' ); ?></p>
						<h2><?php esc_html_e( 'Causas raíz y correcciones auxiliares', 'seo-geo-manager' ); ?></h2>
						<div data-seo-geo-actionables><p class="description">—</p></div>
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
			</details>
		</div>
		<?php
	}
}
