<?php
/**
 * Local Business / Local Pro final single-post template.
 *
 * @package SeoGeoTheme
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<!-- wp:template-part {"slug":"header","tagName":"header"} /-->

<!-- wp:group {"tagName":"main","className":"seo-geo-local-system-surface","layout":{"type":"default"}} -->
<main class="wp-block-group seo-geo-local-system-surface">
	<!-- wp:group {"tagName":"section","align":"full","className":"seo-geo-local-section seo-geo-local-section--surface","layout":{"type":"constrained"}} -->
	<section class="wp-block-group alignfull seo-geo-local-section seo-geo-local-section--surface">
		<!-- wp:group {"className":"seo-geo-local-shell seo-geo-local-section__heading","layout":{"type":"constrained"}} -->
		<div class="wp-block-group seo-geo-local-shell seo-geo-local-section__heading">
			<!-- wp:post-terms {"term":"category","className":"seo-geo-local-kicker"} /-->
			<!-- wp:post-title {"level":1} /-->
			<!-- wp:group {"layout":{"type":"flex","flexWrap":"wrap","justifyContent":"left"}} -->
			<div class="wp-block-group">
				<!-- wp:post-date {"fontSize":"sm"} /-->
				<!-- wp:post-author-name {"isLink":true,"fontSize":"sm"} /-->
			</div>
			<!-- /wp:group -->
			<!-- wp:post-excerpt {"moreText":""} /-->
		</div>
		<!-- /wp:group -->
	</section>
	<!-- /wp:group -->

	<!-- wp:group {"tagName":"section","align":"wide","className":"seo-geo-local-section seo-geo-local-shell","layout":{"type":"constrained"}} -->
	<section class="wp-block-group alignwide seo-geo-local-section seo-geo-local-shell">
		<!-- wp:post-featured-image {"aspectRatio":"16/9","align":"wide"} /-->
		<!-- wp:post-content {"layout":{"type":"default"}} /-->
	</section>
	<!-- /wp:group -->

	<!-- wp:group {"tagName":"section","align":"wide","className":"seo-geo-local-section seo-geo-local-shell","layout":{"type":"constrained"}} -->
	<section class="wp-block-group alignwide seo-geo-local-section seo-geo-local-shell">
		<!-- wp:columns {"verticalAlignment":"top","className":"seo-geo-local-split"} -->
		<div class="wp-block-columns are-vertically-aligned-top seo-geo-local-split">
			<!-- wp:column {"verticalAlignment":"top"} -->
			<div class="wp-block-column is-vertically-aligned-top">
				<!-- wp:group {"className":"seo-geo-local-service-card","layout":{"type":"constrained"}} -->
				<div class="wp-block-group seo-geo-local-service-card">
					<!-- wp:post-author-name {"isLink":true,"fontSize":"lg"} /-->
					<!-- wp:post-author-biography /-->
				</div>
				<!-- /wp:group -->
			</div>
			<!-- /wp:column -->
			<!-- wp:column {"verticalAlignment":"top"} -->
			<div class="wp-block-column is-vertically-aligned-top">
				<!-- wp:group {"className":"seo-geo-local-service-card","layout":{"type":"constrained"}} -->
				<div class="wp-block-group seo-geo-local-service-card">
					<!-- wp:post-navigation-link {"type":"previous","showTitle":true} /-->
					<!-- wp:post-navigation-link {"type":"next","showTitle":true} /-->
				</div>
				<!-- /wp:group -->
			</div>
			<!-- /wp:column -->
		</div>
		<!-- /wp:columns -->
	</section>
	<!-- /wp:group -->
</main>
<!-- /wp:group -->

<!-- wp:template-part {"slug":"footer","tagName":"footer"} /-->
