<?php
/**
 * Publisher / Editorial final archive template.
 *
 * Archive hierarchy is driven only by the resolved WordPress archive context
 * and real public posts. No popularity, recency or editorial-priority signal is
 * fabricated by the template.
 *
 * @package SeoGeoTheme
 */
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<!-- wp:template-part {"slug":"header","tagName":"header"} /-->

<!-- wp:group {"tagName":"main","className":"seo-geo-publisher-system-surface","layout":{"type":"default"}} -->
<main class="wp-block-group seo-geo-publisher-system-surface">
	<!-- wp:group {"tagName":"section","align":"full","className":"seo-geo-publisher-section seo-geo-publisher-section--paper","layout":{"type":"constrained"}} -->
	<section class="wp-block-group alignfull seo-geo-publisher-section seo-geo-publisher-section--paper">
		<!-- wp:group {"className":"seo-geo-layout-shell seo-geo-publisher-section__heading seo-geo-safe-copy","layout":{"type":"constrained"}} -->
		<div class="wp-block-group seo-geo-layout-shell seo-geo-publisher-section__heading seo-geo-safe-copy">
			<!-- wp:query-title {"type":"archive","showPrefix":false,"className":"seo-geo-front-page-title"} /-->
			<!-- wp:term-description /-->
		</div>
		<!-- /wp:group -->
	</section>
	<!-- /wp:group -->

	<!-- wp:group {"tagName":"section","align":"wide","className":"seo-geo-publisher-section seo-geo-layout-shell","layout":{"type":"constrained"}} -->
	<section class="wp-block-group alignwide seo-geo-publisher-section seo-geo-layout-shell">
		<!-- wp:query {"query":{"perPage":9,"pages":0,"offset":0,"postType":"post","order":"desc","orderBy":"date","inherit":true},"align":"wide"} -->
		<div class="wp-block-query alignwide">
			<!-- wp:post-template {"className":"seo-geo-publisher-story-grid","layout":{"type":"grid","columnCount":3}} -->
				<!-- wp:group {"className":"seo-geo-publisher-story-card seo-geo-surface-card","layout":{"type":"constrained"}} -->
				<div class="wp-block-group seo-geo-publisher-story-card seo-geo-surface-card">
					<!-- wp:post-featured-image {"isLink":true,"aspectRatio":"16/9"} /-->
					<!-- wp:post-terms {"term":"category","className":"seo-geo-publisher-kicker"} /-->
					<!-- wp:post-title {"isLink":true,"level":2} /-->
					<!-- wp:group {"className":"seo-geo-entry-meta","layout":{"type":"flex","flexWrap":"wrap","justifyContent":"left"}} -->
					<div class="wp-block-group seo-geo-entry-meta">
						<!-- wp:post-date {"fontSize":"sm"} /-->
						<!-- wp:post-author-name {"isLink":true,"fontSize":"sm"} /-->
					</div>
					<!-- /wp:group -->
					<!-- wp:post-excerpt {"moreText":""} /-->
				</div>
				<!-- /wp:group -->
			<!-- /wp:post-template -->

			<!-- wp:query-no-results -->
				<!-- wp:group {"className":"seo-geo-publisher-story-card seo-geo-surface-card","layout":{"type":"constrained"}} -->
				<div class="wp-block-group seo-geo-publisher-story-card seo-geo-surface-card">
					<!-- wp:search {"showLabel":false,"buttonUseIcon":true,"buttonPosition":"button-inside"} /-->
				</div>
				<!-- /wp:group -->
			<!-- /wp:query-no-results -->

			<!-- wp:query-pagination {"layout":{"type":"flex","justifyContent":"space-between"}} -->
				<!-- wp:query-pagination-previous /-->
				<!-- wp:query-pagination-numbers /-->
				<!-- wp:query-pagination-next /-->
			<!-- /wp:query-pagination -->
		</div>
		<!-- /wp:query -->
	</section>
	<!-- /wp:group -->
</main>
<!-- /wp:group -->

<!-- wp:template-part {"slug":"footer","tagName":"footer"} /-->
