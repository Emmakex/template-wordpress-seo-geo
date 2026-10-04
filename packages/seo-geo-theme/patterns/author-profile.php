<?php
/**
 * Title: Author profile — expertise and bio
 * Slug: seo-geo-theme/author-profile
 * Categories: about, text
 * Description: A reusable author or expert profile that emphasizes identity, role and first-hand expertise.
 * Viewport Width: 900
 *
 * @package SeoGeoTheme
 */

?>
<!-- wp:group {"align":"wide","className":"seo-geo-native-author-profile","backgroundColor":"surface","style":{"border":{"radius":"var:preset|border-radius|lg"},"spacing":{"padding":{"top":"var:preset|spacing|xl","right":"var:preset|spacing|xl","bottom":"var:preset|spacing|xl","left":"var:preset|spacing|xl"}}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group alignwide seo-geo-native-author-profile has-surface-background-color has-background" style="border-radius:var(--wp--preset--border-radius--lg);padding-top:var(--wp--preset--spacing--xl);padding-right:var(--wp--preset--spacing--xl);padding-bottom:var(--wp--preset--spacing--xl);padding-left:var(--wp--preset--spacing--xl)">
	<!-- wp:paragraph {"fontSize":"sm","className":"seo-geo-content-slot--author-eyebrow"} -->
	<p class="has-sm-font-size seo-geo-content-slot--author-eyebrow"><?php esc_html_e( 'Author / expert', 'seo-geo-theme' ); ?></p>
	<!-- /wp:paragraph -->

	<!-- wp:heading {"level":3,"fontSize":"xl","className":"seo-geo-content-slot--author-name"} -->
	<h3 class="wp-block-heading has-xl-font-size seo-geo-content-slot--author-name"><?php esc_html_e( 'Add the author name', 'seo-geo-theme' ); ?></h3>
	<!-- /wp:heading -->

	<!-- wp:paragraph {"textColor":"muted","className":"seo-geo-content-slot--author-role"} -->
	<p class="has-muted-color has-text-color seo-geo-content-slot--author-role"><?php esc_html_e( 'Add the role, specialty or relevant qualification', 'seo-geo-theme' ); ?></p>
	<!-- /wp:paragraph -->

	<!-- wp:paragraph {"className":"seo-geo-content-slot--author-bio"} -->
	<p class="seo-geo-content-slot--author-bio"><?php esc_html_e( 'Write a concise bio focused on relevant experience, responsibilities and the perspective this person brings to the content.', 'seo-geo-theme' ); ?></p>
	<!-- /wp:paragraph -->

	<!-- wp:buttons -->
	<div class="wp-block-buttons">
		<!-- wp:button {"className":"is-style-outline seo-geo-content-slot--author-profile-link"} -->
		<div class="wp-block-button is-style-outline seo-geo-content-slot--author-profile-link"><a class="wp-block-button__link wp-element-button" href="#"><?php esc_html_e( 'View author profile', 'seo-geo-theme' ); ?></a></div>
		<!-- /wp:button -->
	</div>
	<!-- /wp:buttons -->
</div>
<!-- /wp:group -->
