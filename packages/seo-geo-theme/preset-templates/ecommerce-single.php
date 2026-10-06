<?php
/**
 * Ecommerce final editorial single-post template.
 *
 * This surface is intentionally limited to native WordPress editorial content.
 * Product identity, price, stock, variants, reviews and commerce Schema remain
 * owned by the active commerce provider and its accepted adapter.
 *
 * @package SeoGeoTheme
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<!-- wp:template-part {"slug":"header","tagName":"header"} /-->

<!-- wp:group {"tagName":"main","className":"seo-geo-commerce-system-surface","layout":{"type":"default"}} -->
<main class="wp-block-group seo-geo-commerce-system-surface">
	<!-- wp:group {"tagName":"section","align":"full","className":"seo-geo-commerce-section seo-geo-commerce-section--surface","layout":{"type":"constrained"}} -->
	<section class="wp-block-group alignfull seo-geo-commerce-section seo-geo-commerce-section--surface">
		<!-- wp:group {"className":"seo-geo-layout-shell seo-geo-commerce-section__heading seo-geo-safe-copy","layout":{"type":"constrained"}} -->
		<div class="wp-block-group seo-geo-layout-shell seo-geo-commerce-section__heading seo-geo-safe-copy">
			<!-- wp:post-terms {"term":"category","className":"seo-geo-commerce-kicker"} /-->
			<!-- wp:post-title {"level":1,"className":"seo-geo-front-page-title"} /-->
			<!-- wp:group {"className":"seo-geo-entry-meta","layout":{"type":"flex","flexWrap":"wrap","justifyContent":"left"}} -->
			<div class="wp-block-group seo-geo-entry-meta">
				<!-- wp:post-date {"fontSize":"sm"} /-->
				<!-- wp:post-author-name {"isLink":true,"fontSize":"sm"} /-->
			</div>
			<!-- /wp:group -->
			<!-- wp:post-excerpt {"moreText":""} /-->
		</div>
		<!-- /wp:group -->
	</section>
	<!-- /wp:group -->

	<!-- wp:group {"tagName":"section","align":"wide","className":"seo-geo-commerce-section seo-geo-layout-shell","layout":{"type":"constrained"}} -->
	<section class="wp-block-group alignwide seo-geo-commerce-section seo-geo-layout-shell">
		<!-- wp:post-featured-image {"aspectRatio":"16/9","align":"wide"} /-->
	</section>
	<!-- /wp:group -->

	<!-- wp:group {"className":"seo-geo-reading-width seo-geo-safe-copy","layout":{"type":"constrained"}} -->
	<div class="wp-block-group seo-geo-reading-width seo-geo-safe-copy">
		<!-- wp:post-content {"layout":{"type":"default"}} /-->
	</div>
	<!-- /wp:group -->

	<!-- wp:group {"tagName":"section","align":"wide","className":"seo-geo-commerce-section seo-geo-layout-shell","layout":{"type":"constrained"}} -->
	<section class="wp-block-group alignwide seo-geo-commerce-section seo-geo-layout-shell">
		<!-- wp:group {"className":"seo-geo-commerce-confidence-card seo-geo-surface-card","layout":{"type":"constrained"}} -->
		<div class="wp-block-group seo-geo-commerce-confidence-card seo-geo-surface-card">
			<!-- wp:post-navigation-link {"type":"previous","showTitle":true} /-->
			<!-- wp:post-navigation-link {"type":"next","showTitle":true} /-->
		</div>
		<!-- /wp:group -->
	</section>
	<!-- /wp:group -->
</main>
<!-- /wp:group -->

<!-- wp:template-part {"slug":"footer","tagName":"footer"} /-->
