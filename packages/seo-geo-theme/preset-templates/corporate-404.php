<?php
/**
 * Corporate final 404 template.
 *
 * @package SeoGeoTheme
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$locale     = function_exists( 'determine_locale' ) ? determine_locale() : get_locale();
$is_spanish = str_starts_with( strtolower( $locale ), 'es' );
$eyebrow    = '404';
$title      = $is_spanish ? 'No encontramos esta página.' : 'We could not find this page.';
$body       = $is_spanish
	? 'La dirección puede haber cambiado. Puedes volver al inicio o buscar el contenido que necesitas.'
	: 'The address may have changed. You can return home or search for the content you need.';
$home_label = $is_spanish ? 'Volver al inicio' : 'Back to home';
$search_kicker = $is_spanish ? 'Encontrar contenido' : 'Find content';
$search_title  = $is_spanish ? 'Busca dentro del sitio.' : 'Search inside the site.';
?>
<!-- wp:template-part {"slug":"header","tagName":"header"} /-->

<!-- wp:group {"tagName":"main","className":"seo-geo-corporate-system-surface","layout":{"type":"default"}} -->
<main class="wp-block-group seo-geo-corporate-system-surface">
	<!-- wp:group {"tagName":"section","align":"full","className":"seo-geo-corporate-native-process","layout":{"type":"constrained"}} -->
	<section class="wp-block-group alignfull seo-geo-corporate-native-process">
		<!-- wp:paragraph {"className":"seo-geo-corporate-eyebrow"} -->
		<p class="seo-geo-corporate-eyebrow"><?php echo esc_html( $eyebrow ); ?></p>
		<!-- /wp:paragraph -->
		<!-- wp:heading {"level":1,"className":"seo-geo-front-page-title"} -->
		<h1 class="wp-block-heading seo-geo-front-page-title"><?php echo esc_html( $title ); ?></h1>
		<!-- /wp:heading -->
		<!-- wp:paragraph {"className":"seo-geo-corporate-lead"} -->
		<p class="seo-geo-corporate-lead"><?php echo esc_html( $body ); ?></p>
		<!-- /wp:paragraph -->
		<!-- wp:buttons {"className":"seo-geo-corporate-actions"} -->
		<div class="wp-block-buttons seo-geo-corporate-actions">
			<!-- wp:button -->
			<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php echo esc_html( $home_label ); ?></a></div>
			<!-- /wp:button -->
		</div>
		<!-- /wp:buttons -->
	</section>
	<!-- /wp:group -->

	<!-- wp:group {"tagName":"section","align":"wide","className":"seo-geo-corporate-native-section","layout":{"type":"constrained"}} -->
	<section class="wp-block-group alignwide seo-geo-corporate-native-section">
		<!-- wp:group {"className":"seo-geo-corporate-card","layout":{"type":"constrained"}} -->
		<div class="wp-block-group seo-geo-corporate-card">
			<!-- wp:paragraph {"className":"seo-geo-corporate-eyebrow"} -->
			<p class="seo-geo-corporate-eyebrow"><?php echo esc_html( $search_kicker ); ?></p>
			<!-- /wp:paragraph -->
			<!-- wp:heading {"level":2} -->
			<h2 class="wp-block-heading"><?php echo esc_html( $search_title ); ?></h2>
			<!-- /wp:heading -->
			<!-- wp:search {"showLabel":false,"buttonUseIcon":true,"buttonPosition":"button-inside"} /-->
		</div>
		<!-- /wp:group -->
	</section>
	<!-- /wp:group -->
</main>
<!-- /wp:group -->

<!-- wp:template-part {"slug":"footer","tagName":"footer"} /-->
