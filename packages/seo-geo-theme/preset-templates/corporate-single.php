<?php
/**
 * Corporate final single-post template.
 *
 * @package SeoGeoTheme
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<!-- wp:template-part {"slug":"header","tagName":"header"} /-->

<!-- wp:group {"tagName":"main","className":"seo-geo-corporate-system-surface","layout":{"type":"default"}} -->
<main class="wp-block-group seo-geo-corporate-system-surface">
	<!-- wp:group {"tagName":"section","align":"full","className":"seo-geo-corporate-native-process","layout":{"type":"constrained"}} -->
	<section class="wp-block-group alignfull seo-geo-corporate-native-process">
		<!-- wp:post-terms {"term":"category","className":"seo-geo-corporate-eyebrow"} /-->
		<!-- wp:post-title {"level":1,"className":"seo-geo-front-page-title"} /-->
		<!-- wp:group {"className":"seo-geo-entry-meta","layout":{"type":"flex","flexWrap":"wrap","justifyContent":"left"}} -->
		<div class="wp-block-group seo-geo-entry-meta">
			<!-- wp:post-date {"fontSize":"sm"} /-->
			<!-- wp:post-author-name {"isLink":true,"fontSize":"sm"} /-->
		</div>
		<!-- /wp:group -->
		<!-- wp:post-excerpt {"moreText":""} /-->
	</section>
	<!-- /wp:group -->

	<!-- wp:group {"tagName":"section","align":"wide","className":"seo-geo-corporate-native-section","layout":{"type":"constrained"}} -->
	<section class="wp-block-group alignwide seo-geo-corporate-native-section">
		<!-- wp:post-featured-image {"aspectRatio":"16/9","align":"wide"} /-->
	</section>
	<!-- /wp:group -->

	<!-- wp:post-content {"layout":{"type":"default"}} /-->

	<!-- wp:group {"tagName":"section","align":"wide","className":"seo-geo-corporate-native-section","layout":{"type":"constrained"}} -->
	<section class="wp-block-group alignwide seo-geo-corporate-native-section">
		<!-- wp:columns {"verticalAlignment":"top","className":"seo-geo-corporate-card-grid"} -->
		<div class="wp-block-columns are-vertically-aligned-top seo-geo-corporate-card-grid">
			<!-- wp:column {"verticalAlignment":"top"} -->
			<div class="wp-block-column is-vertically-aligned-top">
				<!-- wp:group {"className":"seo-geo-corporate-card","layout":{"type":"constrained"}} -->
				<div class="wp-block-group seo-geo-corporate-card">
					<!-- wp:post-author-name {"isLink":true,"fontSize":"lg"} /-->
					<!-- wp:post-author-biography /-->
				</div>
				<!-- /wp:group -->
			</div>
			<!-- /wp:column -->

			<!-- wp:column {"verticalAlignment":"top"} -->
			<div class="wp-block-column is-vertically-aligned-top">
				<!-- wp:group {"className":"seo-geo-corporate-card","layout":{"type":"constrained"}} -->
				<div class="wp-block-group seo-geo-corporate-card">
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
