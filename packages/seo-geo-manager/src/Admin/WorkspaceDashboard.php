<?php
/**
 * Permanent SEO/GEO Manager control-plane dashboard.
 *
 * @package SeoGeoManager
 */

declare(strict_types=1);

namespace SeoGeo\Manager\Admin;

final class WorkspaceDashboard {
	private const SLUG = 'seo-geo-manager';

	public static function register(): void {
		add_action( 'admin_menu', array( self::class, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( self::class, 'enqueue_assets' ) );
	}

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
			'seo-geo-manager-workspace-dashboard',
			self::asset_url( 'assets/admin/workspace-dashboard.css' ),
			array( 'seo-geo-manager-dashboard' ),
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

	private static function asset_url( string $relative_path ): string {
		$plugin_file = dirname( __DIR__, 2 ) . '/seo-geo-manager.php';

		return plugins_url( ltrim( $relative_path, '/' ), $plugin_file );
	}

	public static function render(): void {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'No tienes permisos para acceder a SEO/GEO Manager.', 'seo-geo-manager' ) );
		}
		?>
		<div class="wrap seo-geo-manager-admin seo-geo-manager-workspace" id="seo-geo-manager-dashboard">
			<header class="seo-geo-manager-admin__hero seo-geo-manager-workspace__hero">
				<div>
					<p class="seo-geo-manager-admin__eyebrow"><?php esc_html_e( 'Control plane · SEO/GEO', 'seo-geo-manager' ); ?></p>
					<h1><?php esc_html_e( 'SEO/GEO Manager', 'seo-geo-manager' ); ?></h1>
					<p class="seo-geo-manager-admin__lead"><?php esc_html_e( 'Centro de control permanente para inspeccionar, preparar, aplicar y verificar cambios de contenido, SEO/GEO, estructura, enlaces y medios. La migración es solo una fase del ciclo de vida.', 'seo-geo-manager' ); ?></p>
				</div>
				<div class="seo-geo-manager-workspace__identity">
					<span><?php esc_html_e( 'Manager', 'seo-geo-manager' ); ?></span>
					<strong>v<?php echo esc_html( SEO_GEO_MANAGER_VERSION ); ?></strong>
					<small><?php esc_html_e( 'Inspect → Preview → Apply → Verify → Rollback', 'seo-geo-manager' ); ?></small>
				</div>
			</header>

			<section class="seo-geo-manager-workspaces" aria-label="<?php esc_attr_e( 'Modos de trabajo del Manager', 'seo-geo-manager' ); ?>">
				<article class="seo-geo-manager-workspace-card is-active">
					<span class="seo-geo-manager-workspace-card__badge"><?php esc_html_e( 'Activo', 'seo-geo-manager' ); ?></span>
					<p class="seo-geo-manager-admin__eyebrow"><?php esc_html_e( 'Build / Finish', 'seo-geo-manager' ); ?></p>
					<h2><?php esc_html_e( 'Construir y completar', 'seo-geo-manager' ); ?></h2>
					<p><?php esc_html_e( 'Completar estructura, contenido, SEO, enlaces, medios y launch readiness hasta dejar el sitio listo.', 'seo-geo-manager' ); ?></p>
				</article>
				<article class="seo-geo-manager-workspace-card">
					<span class="seo-geo-manager-workspace-card__badge"><?php esc_html_e( 'Continuo', 'seo-geo-manager' ); ?></span>
					<p class="seo-geo-manager-admin__eyebrow"><?php esc_html_e( 'Optimize', 'seo-geo-manager' ); ?></p>
					<h2><?php esc_html_e( 'Optimizar', 'seo-geo-manager' ); ?></h2>
					<p><?php esc_html_e( 'Detectar oportunidades y ejecutar mejoras verificables sobre un sitio ya operativo sin perder control ni historial.', 'seo-geo-manager' ); ?></p>
				</article>
				<article class="seo-geo-manager-workspace-card">
					<span class="seo-geo-manager-workspace-card__badge"><?php esc_html_e( 'Expansión', 'seo-geo-manager' ); ?></span>
					<p class="seo-geo-manager-admin__eyebrow"><?php esc_html_e( 'Grow', 'seo-geo-manager' ); ?></p>
					<h2><?php esc_html_e( 'Crecer', 'seo-geo-manager' ); ?></h2>
					<p><?php esc_html_e( 'Crear nuevas páginas, contenidos, idiomas y activos mediante operaciones semánticas y verificables del Manager.', 'seo-geo-manager' ); ?></p>
				</article>
			</section>

			<section class="seo-geo-manager-admin__panel seo-geo-manager-workspace__site-control">
				<div class="seo-geo-manager-admin__panel-heading">
					<div>
						<p class="seo-geo-manager-admin__eyebrow"><?php esc_html_e( 'Site control', 'seo-geo-manager' ); ?></p>
						<h2><?php esc_html_e( 'Estado actual y prioridades', 'seo-geo-manager' ); ?></h2>
						<p class="description"><?php esc_html_e( 'El Manager inspecciona el estado real de WordPress y devuelve únicamente acciones acotadas. La estrategia y el contenido se preparan externamente.', 'seo-geo-manager' ); ?></p>
					</div>
					<div class="seo-geo-manager-workspace__analyze-actions">
						<label class="seo-geo-manager-admin__rendered-toggle">
							<input type="checkbox" data-seo-geo-rendered checked>
							<?php esc_html_e( 'Verificar frontend', 'seo-geo-manager' ); ?>
						</label>
						<button type="button" class="button button-primary" data-seo-geo-action="analyze"><?php esc_html_e( 'Analizar sitio', 'seo-geo-manager' ); ?></button>
					</div>
				</div>

				<div class="notice notice-info inline seo-geo-manager-admin__notice" data-seo-geo-status role="status" aria-live="polite">
					<p><?php esc_html_e( 'Ejecuta el análisis para cargar readiness, autoridad SEO, estructura, enlaces y causas raíz.', 'seo-geo-manager' ); ?></p>
				</div>

				<div class="seo-geo-manager-admin__metrics" aria-label="<?php esc_attr_e( 'Resumen operativo', 'seo-geo-manager' ); ?>">
					<div class="seo-geo-manager-admin__metric"><span><?php esc_html_e( 'Estado', 'seo-geo-manager' ); ?></span><strong data-seo-geo-metric="overall">—</strong></div>
					<div class="seo-geo-manager-admin__metric"><span><?php esc_html_e( 'Bloqueos', 'seo-geo-manager' ); ?></span><strong data-seo-geo-metric="blockers">—</strong></div>
					<div class="seo-geo-manager-admin__metric"><span><?php esc_html_e( 'Avisos', 'seo-geo-manager' ); ?></span><strong data-seo-geo-metric="warnings">—</strong></div>
					<div class="seo-geo-manager-admin__metric"><span><?php esc_html_e( 'Autoridad SEO', 'seo-geo-manager' ); ?></span><strong data-seo-geo-metric="seo">—</strong></div>
				</div>
			</section>

			<section class="seo-geo-manager-admin__panel">
				<p class="seo-geo-manager-admin__eyebrow"><?php esc_html_e( 'Readiness', 'seo-geo-manager' ); ?></p>
				<h2><?php esc_html_e( 'Comprobaciones del sitio', 'seo-geo-manager' ); ?></h2>
				<div class="seo-geo-manager-admin__checks" data-seo-geo-checks><p class="description"><?php esc_html_e( 'Todavía no hay un análisis cargado.', 'seo-geo-manager' ); ?></p></div>
			</section>

			<section class="seo-geo-manager-admin__panel">
				<p class="seo-geo-manager-admin__eyebrow"><?php esc_html_e( 'Actionable diagnostics', 'seo-geo-manager' ); ?></p>
				<h2><?php esc_html_e( 'Prioridades y causas raíz', 'seo-geo-manager' ); ?></h2>
				<div data-seo-geo-actionables><p class="description">—</p></div>
			</section>

			<section class="seo-geo-manager-admin__grid">
				<div class="seo-geo-manager-admin__panel">
					<p class="seo-geo-manager-admin__eyebrow"><?php esc_html_e( 'Content model', 'seo-geo-manager' ); ?></p>
					<h2><?php esc_html_e( 'Estructura y contenido', 'seo-geo-manager' ); ?></h2>
					<div data-seo-geo-structure><p class="description">—</p></div>
				</div>
				<div class="seo-geo-manager-admin__panel">
					<p class="seo-geo-manager-admin__eyebrow"><?php esc_html_e( 'Site graph', 'seo-geo-manager' ); ?></p>
					<h2><?php esc_html_e( 'Navegación y entorno', 'seo-geo-manager' ); ?></h2>
					<div data-seo-geo-navigation><p class="description">—</p></div>
				</div>
			</section>

			<details class="seo-geo-manager-workspace__build-finish">
				<summary>
					<div>
						<p class="seo-geo-manager-admin__eyebrow"><?php esc_html_e( 'Build / Finish · herramientas protegidas', 'seo-geo-manager' ); ?></p>
						<strong><?php esc_html_e( 'Finalización técnica, migración y compatibilidad', 'seo-geo-manager' ); ?></strong>
						<span><?php esc_html_e( 'Field Gate, permalinks y 301 se conservan como operaciones acotadas cuando un sitio las necesita; no definen el producto.', 'seo-geo-manager' ); ?></span>
					</div>
				</summary>
				<div class="seo-geo-manager-workspace__build-finish-body">
					<?php if ( current_user_can( 'manage_options' ) ) : ?>
						<div data-seo-geo-field-slot></div>
						<div data-seo-geo-finalization-actions></div>
					<?php else : ?>
						<div class="notice notice-warning inline"><p><?php esc_html_e( 'Las operaciones de finalización de sitio requieren permisos de administrador.', 'seo-geo-manager' ); ?></p></div>
					<?php endif; ?>
				</div>
			</details>

			<details class="seo-geo-manager-admin__technical">
				<summary>
					<strong><?php esc_html_e( 'Historial, evidencia y herramientas técnicas', 'seo-geo-manager' ); ?></strong>
					<span><?php esc_html_e( 'Operaciones, diagnóstico completo y JSON para soporte y automatización.', 'seo-geo-manager' ); ?></span>
				</summary>
				<div class="seo-geo-manager-admin__technical-body">
					<div class="seo-geo-manager-workspace__technical-actions">
						<button type="button" class="button button-secondary" data-seo-geo-action="analyze"><?php esc_html_e( 'Actualizar diagnóstico', 'seo-geo-manager' ); ?></button>
					</div>
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
