<?php
/**
 * Publisher / Editorial final 404 template.
 *
 * @package SeoGeoTheme
 */
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$seo_geo_publisher_404_locale  = function_exists( 'determine_locale' ) ? determine_locale() : get_locale();
$seo_geo_publisher_404_spanish = str_starts_with( strtolower( $seo_geo_publisher_404_locale ), 'es' );
$seo_geo_publisher_404_copy    = array(
	'eyebrow'       => '404',
	'title'         => $seo_geo_publisher_404_spanish ? 'Esta página ya no está aquí.' : 'This page is no longer here.',
	'body'          => $seo_geo_publisher_404_spanish
		? 'La dirección puede haber cambiado o el contenido puede haberse movido. Vuelve al inicio o busca una publicación relacionada.'
		: 'The address may have changed or the content may have moved. Return home or search for related published work.',
	'home_label'    => $seo_geo_publisher_404_spanish ? 'Volver al inicio' : 'Back to home',
	'search_kicker' => $seo_geo_publisher_404_spanish ? 'Buscar en la publicación' : 'Search the publication',
	'search_title'  => $seo_geo_publisher_404_spanish ? 'Encuentra otra lectura útil.' : 'Find another useful read.',
);
?>
<!-- wp:template-part {"slug":"header","tagName":"header"} /-->

<!-- wp:group {"tagName":"main","className":"seo-geo-publisher-system-surface","layout":{"type":"default"}} -->
<main class="wp-block-group seo-geo-publisher-system-surface">
	<!-- wp:group {"tagName":"section","align":"full","className":"seo-geo-publisher-section seo-geo-publisher-section--paper","layout":{"type":"constrained"}} -->
	<section class="wp-block-group alignfull seo-geo-publisher-section seo-geo-publisher-section--paper">
		<!-- wp:group {"className":"seo-geo-layout-shell seo-geo-publisher-section__heading seo-geo-safe-copy","layout":{"type":"constrained"}} -->
		<div class="wp-block-group seo-geo-layout-shell seo-geo-publisher-section__heading seo-geo-safe-copy">
			<!-- wp:paragraph {"className":"seo-geo-publisher-kicker"} -->
			<p class="seo-geo-publisher-kicker"><?php echo esc_html( $seo_geo_publisher_404_copy['eyebrow'] ); ?></p>
			<!-- /wp:paragraph -->
			<!-- wp:heading {"level":1,"className":"seo-geo-front-page-title"} -->
			<h1 class="wp-block-heading seo-geo-front-page-title"><?php echo esc_html( $seo_geo_publisher_404_copy['title'] ); ?></h1>
			<!-- /wp:heading -->
			<!-- wp:paragraph {"className":"seo-geo-publisher-lead"} -->
			<p class="seo-geo-publisher-lead"><?php echo esc_html( $seo_geo_publisher_404_copy['body'] ); ?></p>
			<!-- /wp:paragraph -->
			<!-- wp:buttons {"className":"seo-geo-publisher-actions"} -->
			<div class="wp-block-buttons seo-geo-publisher-actions">
				<!-- wp:button -->
				<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php echo esc_html( $seo_geo_publisher_404_copy['home_label'] ); ?></a></div>
				<!-- /wp:button -->
			</div>
			<!-- /wp:buttons -->
		</div>
		<!-- /wp:group -->
	</section>
	<!-- /wp:group -->

	<!-- wp:group {"tagName":"section","align":"wide","className":"seo-geo-publisher-section seo-geo-layout-shell","layout":{"type":"constrained"}} -->
	<section class="wp-block-group alignwide seo-geo-publisher-section seo-geo-layout-shell">
		<!-- wp:group {"className":"seo-geo-publisher-story-card seo-geo-surface-card seo-geo-safe-copy","layout":{"type":"constrained"}} -->
		<div class="wp-block-group seo-geo-publisher-story-card seo-geo-surface-card seo-geo-safe-copy">
			<!-- wp:paragraph {"className":"seo-geo-publisher-kicker"} -->
			<p class="seo-geo-publisher-kicker"><?php echo esc_html( $seo_geo_publisher_404_copy['search_kicker'] ); ?></p>
			<!-- /wp:paragraph -->
			<!-- wp:heading {"level":2} -->
			<h2 class="wp-block-heading"><?php echo esc_html( $seo_geo_publisher_404_copy['search_title'] ); ?></h2>
			<!-- /wp:heading -->
			<!-- wp:search {"showLabel":false,"buttonUseIcon":true,"buttonPosition":"button-inside"} /-->
		</div>
		<!-- /wp:group -->
	</section>
	<!-- /wp:group -->
</main>
<!-- /wp:group -->

<!-- wp:template-part {"slug":"footer","tagName":"footer"} /-->
