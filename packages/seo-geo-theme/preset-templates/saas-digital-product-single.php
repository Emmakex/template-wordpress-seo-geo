<?php
/**
 * SaaS / Digital Product final single-post template.
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
		<!-- wp:group {"className":"seo-geo-layout-shell","layout":{"type":"constrained"}} -->
		<div class="wp-block-group seo-geo-layout-shell">
			<!-- wp:post-terms {"term":"category","className":"seo-geo-saas-kicker"} /-->
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

	<!-- wp:group {"tagName":"section","align":"wide","className":"seo-geo-saas-section seo-geo-layout-shell","layout":{"type":"constrained"}} -->
	<section class="wp-block-group alignwide seo-geo-saas-section seo-geo-layout-shell">
		<!-- wp:post-featured-image {"aspectRatio":"16/9","align":"wide"} /-->
	</section>
	<!-- /wp:group -->

	<!-- wp:group {"align":"wide","className":"seo-geo-layout-shell","layout":{"type":"constrained"}} -->
	<div class="wp-block-group alignwide seo-geo-layout-shell">
		<!-- wp:post-content {"layout":{"type":"default"}} /-->
	</div>
	<!-- /wp:group -->

	<!-- wp:group {"tagName":"section","align":"wide","className":"seo-geo-saas-section seo-geo-layout-shell","layout":{"type":"constrained"}} -->
	<section class="wp-block-group alignwide seo-geo-saas-section seo-geo-layout-shell">
		<!-- wp:columns {"verticalAlignment":"top","className":"seo-geo-saas-split"} -->
		<div class="wp-block-columns are-vertically-aligned-top seo-geo-saas-split">
			<!-- wp:column {"verticalAlignment":"top"} -->
			<div class="wp-block-column is-vertically-aligned-top">
				<!-- wp:group {"className":"seo-geo-saas-card seo-geo-surface-card","layout":{"type":"constrained"}} -->
				<div class="wp-block-group seo-geo-saas-card seo-geo-surface-card">
					<!-- wp:post-author-name {"isLink":true,"fontSize":"lg"} /-->
					<!-- wp:post-author-biography /-->
				</div>
				<!-- /wp:group -->
			</div>
			<!-- /wp:column -->

			<!-- wp:column {"verticalAlignment":"top"} -->
			<div class="wp-block-column is-vertically-aligned-top">
				<!-- wp:group {"className":"seo-geo-saas-card seo-geo-surface-card","layout":{"type":"constrained"}} -->
				<div class="wp-block-group seo-geo-saas-card seo-geo-surface-card">
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
