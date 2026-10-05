<?php
/**
 * Administrator whole-site Corporate readiness screen.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge\Reset;

use SeoGeo\MigrationBridge\Plugin;

/**
 * Displays the final machine gate before global browser QA.
 */
final class AdminCorporateSiteReadinessController {
	public const PAGE_SLUG = 'seo-geo-corporate-site-readiness';

	/**
	 * Construct the read-only controller.
	 *
	 * @param CorporateSiteReadiness $readiness Whole-site readiness authority.
	 */
	public function __construct( private CorporateSiteReadiness $readiness ) {
	}

	/** Bootstrap from accepted Plugin services. */
	public static function boot_from_plugin(): void {
		$manifest = Plugin::rescue_manifest();
		$reset    = Plugin::clone_reset_engine();
		$builder  = Plugin::clean_corporate_page_rebuilder();
		$home     = Plugin::home_pilot_readiness();
		if (
			! $manifest instanceof RescueManifest
			|| ! $reset instanceof CloneResetEngine
			|| ! $builder instanceof CleanCorporatePageRebuilder
			|| ! $home instanceof HomePilotReadiness
		) {
			return;
		}

		$kit       = new CorporatePageContentKit();
		$hydrator  = new NativeCorporatePageHydrator( $builder, $kit );
		$handoff   = new CorporatePageSeoHandoff( $manifest, $builder, $hydrator );
		$pages     = new CorporatePageReadiness( $manifest, $reset, $builder, $hydrator, $handoff );
		$insights  = new CorporateInsightsManager( $manifest );
		$readiness = new CorporateSiteReadiness( $home, $pages, $insights, $builder );
		( new self( $readiness ) )->boot();
	}

	/** Register administrator hooks. */
	public function boot(): void {
		add_action( 'admin_menu', array( $this, 'register_page' ) );
	}

	/** Register the Tools page. */
	public function register_page(): void {
		add_management_page(
			__( 'SEO/GEO Clean Site Readiness', 'seo-geo-migration-bridge' ),
			__( 'SEO/GEO Site Readiness', 'seo-geo-migration-bridge' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render' )
		);
	}

	/** Render the final machine preflight. */
	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$report = $this->readiness->report();
		$pages  = is_array( $report['page_reports'] ?? null ) ? $report['page_reports'] : array();
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'SEO/GEO Clean Site Readiness', 'seo-geo-migration-bridge' ); ?></h1>
			<p><?php echo esc_html__( 'This is the final read-only machine gate. Browser QA starts only when every rebuilt page and the dynamic Insights index are ready together.', 'seo-geo-migration-bridge' ); ?></p>

			<div class="notice <?php echo true === ( $report['ready_for_global_browser_qa'] ?? false ) ? 'notice-success' : 'notice-warning'; ?> inline">
				<p><strong><?php echo esc_html__( 'Ready for global browser QA:', 'seo-geo-migration-bridge' ); ?></strong> <?php echo true === ( $report['ready_for_global_browser_qa'] ?? false ) ? 'YES' : 'NO'; ?></p>
			</div>

			<table class="widefat striped" style="max-width:900px">
				<thead><tr><th><?php echo esc_html__( 'Surface', 'seo-geo-migration-bridge' ); ?></th><th><?php echo esc_html__( 'Machine readiness', 'seo-geo-migration-bridge' ); ?></th><th><?php echo esc_html__( 'Blockers', 'seo-geo-migration-bridge' ); ?></th></tr></thead>
				<tbody>
				<?php foreach ( array( 'home', 'services', 'work', 'about', 'contact', 'insights' ) as $page_key ) : ?>
					<?php $page_report = is_array( $pages[ $page_key ] ?? null ) ? $pages[ $page_key ] : array(); ?>
					<tr>
						<td><strong><?php echo esc_html( ucfirst( $page_key ) ); ?></strong></td>
						<td><?php echo true === ( $page_report['ready_for_browser_qa'] ?? false ) ? '✅' : '⛔'; ?></td>
						<td><code><?php echo esc_html( implode( ', ', is_array( $page_report['blockers'] ?? null ) ? $page_report['blockers'] : array() ) ); ?></code></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>

			<?php if ( array() !== ( $report['blockers'] ?? array() ) ) : ?>
				<h2><?php echo esc_html__( 'Global blockers', 'seo-geo-migration-bridge' ); ?></h2>
				<p><code><?php echo esc_html( implode( ', ', $report['blockers'] ) ); ?></code></p>
			<?php endif; ?>

			<?php if ( array() !== ( $report['warnings'] ?? array() ) ) : ?>
				<h2><?php echo esc_html__( 'Review warnings', 'seo-geo-migration-bridge' ); ?></h2>
				<p><code><?php echo esc_html( implode( ', ', $report['warnings'] ) ); ?></code></p>
			<?php endif; ?>

			<h2><?php echo esc_html__( 'Browser QA matrix after this gate passes', 'seo-geo-migration-bridge' ); ?></h2>
			<p><?php echo esc_html__( 'Visual layout, responsive behavior, accessibility, rendered SEO/GEO, performance, links, media and required business functions are reviewed per surface only after the machine report is green.', 'seo-geo-migration-bridge' ); ?></p>
			<p><strong>SHA:</strong> <code><?php echo esc_html( (string) ( $report['report_sha256'] ?? '' ) ); ?></code></p>
		</div>
		<?php
	}
}
