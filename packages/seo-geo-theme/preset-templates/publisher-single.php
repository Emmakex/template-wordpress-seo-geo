<?php
/**
 * Publisher / Editorial final single-post template.
 *
 * All editorial identity, dates and authored content come from native WordPress
 * sources. This template never invents reviewer, expertise or source evidence.
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
			<!-- wp:post-terms {"term":"category","className":"seo-geo-publisher-kicker"} /-->
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

	<!-- wp:group {"tagName":"section","align":"wide","className":"seo-geo-publisher-section seo-geo-layout-shell","layout":{"type":"constrained"}} -->
	<section class="wp-block-group alignwide seo-geo-publisher-section seo-geo-layout-shell">
		<!-- wp:post-featured-image {"aspectRatio":"16/9","align":"wide"} /-->
	</section>
	<!-- /wp:group -->

	<!-- wp:group {"className":"seo-geo-reading-width seo-geo-safe-copy","layout":{"type":"constrained"}} -->
	<div class="wp-block-group seo-geo-reading-width seo-geo-safe-copy">
		<!-- wp:post-content {"layout":{"type":"default"}} /-->
	</div>
	<!-- /wp:group -->

	<!-- wp:group {"tagName":"section","align":"wide","className":"seo-geo-publisher-section seo-geo-layout-shell","layout":{"type":"constrained"}} -->
	<section class="wp-block-group alignwide seo-geo-publisher-section seo-geo-layout-shell">
		<!-- wp:columns {"verticalAlignment":"top","className":"seo-geo-publisher-resource-split"} -->
		<div class="wp-block-columns are-vertically-aligned-top seo-geo-publisher-resource-split">
			<!-- wp:column {"verticalAlignment":"top"} -->
			<div class="wp-block-column is-vertically-aligned-top">
				<!-- wp:group {"className":"seo-geo-publisher-story-card seo-geo-surface-card","layout":{"type":"constrained"}} -->
				<div class="wp-block-group seo-geo-publisher-story-card seo-geo-surface-card">
					<!-- wp:post-author-name {"isLink":true,"fontSize":"lg"} /-->
					<!-- wp:post-author-biography /-->
				</div>
				<!-- /wp:group -->
			</div>
			<!-- /wp:column -->

			<!-- wp:column {"verticalAlignment":"top"} -->
			<div class="wp-block-column is-vertically-aligned-top">
				<!-- wp:group {"className":"seo-geo-publisher-story-card seo-geo-surface-card","layout":{"type":"constrained"}} -->
				<div class="wp-block-group seo-geo-publisher-story-card seo-geo-surface-card">
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
