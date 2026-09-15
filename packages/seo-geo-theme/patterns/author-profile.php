<?php
/**
 * Title: Author profile — expertise and bio
 * Slug: seo-geo-theme/author-profile
 * Categories: about, text
 * Description: A reusable author or expert profile that emphasizes identity, role and first-hand expertise.
 * Viewport Width: 900
 */
?>
<!-- wp:group {"align":"wide","backgroundColor":"surface","style":{"border":{"radius":"var:preset|border-radius|lg"},"spacing":{"padding":{"top":"var:preset|spacing|xl","right":"var:preset|spacing|xl","bottom":"var:preset|spacing|xl","left":"var:preset|spacing|xl"}}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group alignwide has-surface-background-color has-background" style="border-radius:var(--wp--preset--border-radius--lg);padding-top:var(--wp--preset--spacing--xl);padding-right:var(--wp--preset--spacing--xl);padding-bottom:var(--wp--preset--spacing--xl);padding-left:var(--wp--preset--spacing--xl)">
	<!-- wp:paragraph {"fontSize":"sm"} -->
	<p class="has-sm-font-size"><?php esc_html_e( 'Author / expert', 'seo-geo-theme' ); ?></p>
	<!-- /wp:paragraph -->

	<!-- wp:heading {"level":3,"fontSize":"xl"} -->
	<h3 class="wp-block-heading has-xl-font-size"><?php esc_html_e( 'Add the author name', 'seo-geo-theme' ); ?></h3>
	<!-- /wp:heading -->

	<!-- wp:paragraph {"textColor":"muted"} -->
	<p class="has-muted-color has-text-color"><?php esc_html_e( 'Add the role, specialty or relevant qualification', 'seo-geo-theme' ); ?></p>
	<!-- /wp:paragraph -->

	<!-- wp:paragraph -->
	<p><?php esc_html_e( 'Write a concise bio focused on relevant experience, responsibilities and the perspective this person brings to the content.', 'seo-geo-theme' ); ?></p>
	<!-- /wp:paragraph -->

	<!-- wp:buttons -->
	<div class="wp-block-buttons">
		<!-- wp:button {"className":"is-style-outline"} -->
		<div class="wp-block-button is-style-outline"><a class="wp-block-button__link wp-element-button" href="#"><?php esc_html_e( 'View author profile', 'seo-geo-theme' ); ?></a></div>
		<!-- /wp:button -->
	</div>
	<!-- /wp:buttons -->
</div>
<!-- /wp:group -->
