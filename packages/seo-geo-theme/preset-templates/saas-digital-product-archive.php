<?php
/**
 * SaaS / Digital Product final archive template.
 *
 * @package SeoGeoTheme
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<!-- wp:template-part {"slug":"header","tagName":"header"} /-->

<!-- wp:group {"tagName":"main","className":"seo-geo-saas-system-surface","layout":{"type":"default"}} -->
<main class="wp-block-group seo-geo-saas-system-surface">
	<!-- wp:group {"tagName":"section","align":"full","className":"seo-geo-saas-section seo-geo-saas-section--surface","layout":{"type":"constrained"}} -->
	<section class="wp-block-group alignfull seo-geo-saas-section seo-geo-saas-section--surface">
		<!-- wp:group {"className":"seo-geo-layout-shell seo-geo-saas-section__heading","layout":{"type":"constrained"}} -->
		<div class="wp-block-group seo-geo-layout-shell seo-geo-saas-section__heading">
			<!-- wp:query-title {"type":"archive","showPrefix":false,"className":"seo-geo-front-page-title"} /-->
			<!-- wp:term-description /-->
		</div>
		<!-- /wp:group -->
	</section>
	<!-- /wp:group -->

	<!-- wp:group {"tagName":"section","align":"wide","className":"seo-geo-saas-section seo-geo-layout-shell","layout":{"type":"constrained"}} -->
	<section class="wp-block-group alignwide seo-geo-saas-section seo-geo-layout-shell">
		<!-- wp:query {"query":{"perPage":9,"pages":0,"offset":0,"postType":"post","order":"desc","orderBy":"date","inherit":true},"align":"wide"} -->
		<div class="wp-block-query alignwide">
			<!-- wp:post-template {"layout":{"type":"grid","columnCount":3}} -->
				<!-- wp:group {"className":"seo-geo-saas-card seo-geo-surface-card","layout":{"type":"constrained"}} -->
				<div class="wp-block-group seo-geo-saas-card seo-geo-surface-card">
					<!-- wp:post-featured-image {"isLink":true,"aspectRatio":"16/9"} /-->
					<!-- wp:post-terms {"term":"category","className":"seo-geo-saas-kicker"} /-->
					<!-- wp:post-title {"isLink":true,"level":2} /-->
					<!-- wp:post-date {"fontSize":"sm"} /-->
					<!-- wp:post-excerpt {"moreText":""} /-->
				</div>
				<!-- /wp:group -->
			<!-- /wp:post-template -->
			<!-- wp:query-no-results -->
				<!-- wp:search {"showLabel":false,"buttonUseIcon":true,"buttonPosition":"button-inside"} /-->
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
