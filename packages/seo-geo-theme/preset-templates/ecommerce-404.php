<?php
/**
 * Ecommerce final 404 template.
 *
 * Recovery stays Theme-owned and provider-neutral. It does not invent a shop
 * route, product availability, offer, delivery promise or support channel.
 *
 * @package SeoGeoTheme
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$seo_geo_ecommerce_404_locale  = function_exists( 'determine_locale' ) ? determine_locale() : get_locale();
$seo_geo_ecommerce_404_spanish = str_starts_with( strtolower( $seo_geo_ecommerce_404_locale ), 'es' );
$seo_geo_ecommerce_404_copy    = array(
	'eyebrow'      => '404',
	'title'        => $seo_geo_ecommerce_404_spanish ? 'Esta página ya no está disponible.' : 'This page is no longer available.',
	'body'         => $seo_geo_ecommerce_404_spanish
		? 'La dirección puede haber cambiado. Vuelve al inicio o busca contenido publicado en el sitio.'
		: 'The address may have changed. Return home or search published site content.',
	'home_label'   => $seo_geo_ecommerce_404_spanish ? 'Volver al inicio' : 'Back to home',
	'search_title' => $seo_geo_ecommerce_404_spanish ? 'Busca otra página o guía.' : 'Search for another page or guide.',
);
?>
<!-- wp:template-part {"slug":"header","tagName":"header"} /-->

<!-- wp:group {"tagName":"main","className":"seo-geo-commerce-system-surface","layout":{"type":"default"}} -->
<main class="wp-block-group seo-geo-commerce-system-surface">
	<!-- wp:group {"tagName":"section","align":"full","className":"seo-geo-commerce-section seo-geo-commerce-section--surface","layout":{"type":"constrained"}} -->
	<section class="wp-block-group alignfull seo-geo-commerce-section seo-geo-commerce-section--surface">
		<!-- wp:group {"className":"seo-geo-layout-shell seo-geo-commerce-section__heading seo-geo-safe-copy","layout":{"type":"constrained"}} -->
		<div class="wp-block-group seo-geo-layout-shell seo-geo-commerce-section__heading seo-geo-safe-copy">
			<!-- wp:paragraph {"className":"seo-geo-commerce-kicker"} -->
			<p class="seo-geo-commerce-kicker"><?php echo esc_html( $seo_geo_ecommerce_404_copy['eyebrow'] ); ?></p>
			<!-- /wp:paragraph -->
			<!-- wp:heading {"level":1,"className":"seo-geo-front-page-title"} -->
			<h1 class="wp-block-heading seo-geo-front-page-title"><?php echo esc_html( $seo_geo_ecommerce_404_copy['title'] ); ?></h1>
			<!-- /wp:heading -->
			<!-- wp:paragraph {"className":"seo-geo-commerce-lead"} -->
			<p class="seo-geo-commerce-lead"><?php echo esc_html( $seo_geo_ecommerce_404_copy['body'] ); ?></p>
			<!-- /wp:paragraph -->
			<!-- wp:buttons {"className":"seo-geo-commerce-actions"} -->
			<div class="wp-block-buttons seo-geo-commerce-actions">
				<!-- wp:button -->
				<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php echo esc_html( $seo_geo_ecommerce_404_copy['home_label'] ); ?></a></div>
				<!-- /wp:button -->
			</div>
			<!-- /wp:buttons -->
		</div>
		<!-- /wp:group -->
	</section>
	<!-- /wp:group -->

	<!-- wp:group {"tagName":"section","align":"wide","className":"seo-geo-commerce-section seo-geo-layout-shell","layout":{"type":"constrained"}} -->
	<section class="wp-block-group alignwide seo-geo-commerce-section seo-geo-layout-shell">
		<!-- wp:group {"className":"seo-geo-commerce-confidence-card seo-geo-surface-card seo-geo-safe-copy","layout":{"type":"constrained"}} -->
		<div class="wp-block-group seo-geo-commerce-confidence-card seo-geo-surface-card seo-geo-safe-copy">
			<!-- wp:heading {"level":2} -->
			<h2 class="wp-block-heading"><?php echo esc_html( $seo_geo_ecommerce_404_copy['search_title'] ); ?></h2>
			<!-- /wp:heading -->
			<!-- wp:search {"showLabel":false,"buttonUseIcon":true,"buttonPosition":"button-inside"} /-->
		</div>
		<!-- /wp:group -->
	</section>
	<!-- /wp:group -->
</main>
<!-- /wp:group -->

<!-- wp:template-part {"slug":"footer","tagName":"footer"} /-->
