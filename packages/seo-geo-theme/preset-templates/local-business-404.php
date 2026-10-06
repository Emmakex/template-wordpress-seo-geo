<?php
/**
 * Local Business / Local Pro final 404 template.
 *
 * @package SeoGeoTheme
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$seo_geo_local_404_locale  = function_exists( 'determine_locale' ) ? determine_locale() : get_locale();
$seo_geo_local_404_spanish = str_starts_with( strtolower( $seo_geo_local_404_locale ), 'es' );
$seo_geo_local_404_copy    = array(
	'eyebrow'       => '404',
	'title'         => $seo_geo_local_404_spanish ? 'No hemos encontrado esta página.' : 'We could not find this page.',
	'body'          => $seo_geo_local_404_spanish
		? 'La dirección puede haber cambiado. Vuelve al inicio o utiliza la búsqueda para encontrar el servicio o contenido que necesitas.'
		: 'The address may have changed. Return home or use search to find the service or content you need.',
	'home_label'    => $seo_geo_local_404_spanish ? 'Volver al inicio' : 'Back to home',
	'search_kicker' => $seo_geo_local_404_spanish ? 'Buscar en el sitio' : 'Search the site',
	'search_title'  => $seo_geo_local_404_spanish ? 'Encuentra una ruta útil.' : 'Find a useful route.',
);
?>
<!-- wp:template-part {"slug":"header","tagName":"header"} /-->

<!-- wp:group {"tagName":"main","className":"seo-geo-local-system-surface","layout":{"type":"default"}} -->
<main class="wp-block-group seo-geo-local-system-surface">
	<!-- wp:group {"tagName":"section","align":"full","className":"seo-geo-local-hero seo-geo-local-section--surface","layout":{"type":"constrained"}} -->
	<section class="wp-block-group alignfull seo-geo-local-hero seo-geo-local-section--surface">
		<!-- wp:group {"className":"seo-geo-local-shell seo-geo-safe-copy","layout":{"type":"constrained"}} -->
		<div class="wp-block-group seo-geo-local-shell seo-geo-safe-copy">
			<!-- wp:paragraph {"className":"seo-geo-local-kicker"} -->
			<p class="seo-geo-local-kicker"><?php echo esc_html( $seo_geo_local_404_copy['eyebrow'] ); ?></p>
			<!-- /wp:paragraph -->
			<!-- wp:heading {"level":1} -->
			<h1 class="wp-block-heading"><?php echo esc_html( $seo_geo_local_404_copy['title'] ); ?></h1>
			<!-- /wp:heading -->
			<!-- wp:paragraph {"className":"seo-geo-local-lead"} -->
			<p class="seo-geo-local-lead"><?php echo esc_html( $seo_geo_local_404_copy['body'] ); ?></p>
			<!-- /wp:paragraph -->
			<!-- wp:buttons {"className":"seo-geo-local-actions"} -->
			<div class="wp-block-buttons seo-geo-local-actions">
				<!-- wp:button -->
				<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php echo esc_html( $seo_geo_local_404_copy['home_label'] ); ?></a></div>
				<!-- /wp:button -->
			</div>
			<!-- /wp:buttons -->
		</div>
		<!-- /wp:group -->
	</section>
	<!-- /wp:group -->

	<!-- wp:group {"tagName":"section","align":"wide","className":"seo-geo-local-section seo-geo-local-shell","layout":{"type":"constrained"}} -->
	<section class="wp-block-group alignwide seo-geo-local-section seo-geo-local-shell">
		<!-- wp:group {"className":"seo-geo-local-service-card","layout":{"type":"constrained"}} -->
		<div class="wp-block-group seo-geo-local-service-card">
			<!-- wp:paragraph {"className":"seo-geo-local-kicker"} -->
			<p class="seo-geo-local-kicker"><?php echo esc_html( $seo_geo_local_404_copy['search_kicker'] ); ?></p>
			<!-- /wp:paragraph -->
			<!-- wp:heading {"level":2} -->
			<h2 class="wp-block-heading"><?php echo esc_html( $seo_geo_local_404_copy['search_title'] ); ?></h2>
			<!-- /wp:heading -->
			<!-- wp:search {"showLabel":false,"buttonUseIcon":true,"buttonPosition":"button-inside"} /-->
		</div>
		<!-- /wp:group -->
	</section>
	<!-- /wp:group -->
</main>
<!-- /wp:group -->

<!-- wp:template-part {"slug":"footer","tagName":"footer"} /-->
