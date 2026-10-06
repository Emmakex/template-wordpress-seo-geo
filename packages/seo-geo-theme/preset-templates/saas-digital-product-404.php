<?php
/**
 * SaaS / Digital Product final 404 template.
 *
 * @package SeoGeoTheme
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$seo_geo_saas_404_locale  = function_exists( 'determine_locale' ) ? determine_locale() : get_locale();
$seo_geo_saas_404_spanish = str_starts_with( strtolower( $seo_geo_saas_404_locale ), 'es' );
$seo_geo_saas_404_copy    = array(
	'eyebrow'       => '404',
	'title'         => $seo_geo_saas_404_spanish ? 'Esta ruta ya no está disponible.' : 'This route is no longer available.',
	'body'          => $seo_geo_saas_404_spanish
		? 'Puede que la dirección haya cambiado. Vuelve al inicio o utiliza la búsqueda para encontrar el contenido correcto.'
		: 'The address may have changed. Return home or use search to find the right content.',
	'home_label'    => $seo_geo_saas_404_spanish ? 'Volver al inicio' : 'Back to home',
	'search_kicker' => $seo_geo_saas_404_spanish ? 'Buscar en el sitio' : 'Search the site',
	'search_title'  => $seo_geo_saas_404_spanish ? 'Encuentra la siguiente ruta útil.' : 'Find the next useful route.',
);
?>
<!-- wp:template-part {"slug":"header","tagName":"header"} /-->

<!-- wp:group {"tagName":"main","className":"seo-geo-saas-system-surface","layout":{"type":"default"}} -->
<main class="wp-block-group seo-geo-saas-system-surface">
	<!-- wp:group {"tagName":"section","align":"full","className":"seo-geo-saas-hero seo-geo-saas-section--surface","layout":{"type":"constrained"}} -->
	<section class="wp-block-group alignfull seo-geo-saas-hero seo-geo-saas-section--surface">
		<!-- wp:group {"className":"seo-geo-layout-shell seo-geo-safe-copy","layout":{"type":"constrained"}} -->
		<div class="wp-block-group seo-geo-layout-shell seo-geo-safe-copy">
			<!-- wp:paragraph {"className":"seo-geo-saas-kicker"} -->
			<p class="seo-geo-saas-kicker"><?php echo esc_html( $seo_geo_saas_404_copy['eyebrow'] ); ?></p>
			<!-- /wp:paragraph -->
			<!-- wp:heading {"level":1,"className":"seo-geo-front-page-title"} -->
			<h1 class="wp-block-heading seo-geo-front-page-title"><?php echo esc_html( $seo_geo_saas_404_copy['title'] ); ?></h1>
			<!-- /wp:heading -->
			<!-- wp:paragraph {"className":"seo-geo-saas-lead"} -->
			<p class="seo-geo-saas-lead"><?php echo esc_html( $seo_geo_saas_404_copy['body'] ); ?></p>
			<!-- /wp:paragraph -->
			<!-- wp:buttons {"className":"seo-geo-saas-actions"} -->
			<div class="wp-block-buttons seo-geo-saas-actions">
				<!-- wp:button -->
				<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php echo esc_html( $seo_geo_saas_404_copy['home_label'] ); ?></a></div>
				<!-- /wp:button -->
			</div>
			<!-- /wp:buttons -->
		</div>
		<!-- /wp:group -->
	</section>
	<!-- /wp:group -->

	<!-- wp:group {"tagName":"section","align":"wide","className":"seo-geo-saas-section seo-geo-layout-shell","layout":{"type":"constrained"}} -->
	<section class="wp-block-group alignwide seo-geo-saas-section seo-geo-layout-shell">
		<!-- wp:group {"className":"seo-geo-saas-card seo-geo-surface-card","layout":{"type":"constrained"}} -->
		<div class="wp-block-group seo-geo-saas-card seo-geo-surface-card">
			<!-- wp:paragraph {"className":"seo-geo-saas-kicker"} -->
			<p class="seo-geo-saas-kicker"><?php echo esc_html( $seo_geo_saas_404_copy['search_kicker'] ); ?></p>
			<!-- /wp:paragraph -->
			<!-- wp:heading {"level":2} -->
			<h2 class="wp-block-heading"><?php echo esc_html( $seo_geo_saas_404_copy['search_title'] ); ?></h2>
			<!-- /wp:heading -->
			<!-- wp:search {"showLabel":false,"buttonUseIcon":true,"buttonPosition":"button-inside"} /-->
		</div>
		<!-- /wp:group -->
	</section>
	<!-- /wp:group -->
</main>
<!-- /wp:group -->

<!-- wp:template-part {"slug":"footer","tagName":"footer"} /-->
