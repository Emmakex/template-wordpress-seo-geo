<?php
/**
 * Title: Hero — clear value proposition
 * Slug: seo-geo-theme/hero
 * Categories: banner, featured
 * Description: A lightweight hero with a heading, supporting copy and two actions.
 * Viewport Width: 1200
 */
?>
<!-- wp:group {"align":"full","backgroundColor":"surface","style":{"spacing":{"padding":{"top":"var:preset|spacing|3xl","bottom":"var:preset|spacing|3xl","left":"var:preset|spacing|lg","right":"var:preset|spacing|lg"}}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull has-surface-background-color has-background" style="padding-top:var(--wp--preset--spacing--3-xl);padding-right:var(--wp--preset--spacing--lg);padding-bottom:var(--wp--preset--spacing--3-xl);padding-left:var(--wp--preset--spacing--lg)">
	<!-- wp:group {"align":"wide","layout":{"type":"constrained"}} -->
	<div class="wp-block-group alignwide">
		<!-- wp:paragraph {"fontSize":"sm"} -->
		<p class="has-sm-font-size"><?php esc_html_e( 'Add a short category or trust signal', 'seo-geo-theme' ); ?></p>
		<!-- /wp:paragraph -->

		<!-- wp:heading {"level":2,"fontSize":"3xl"} -->
		<h2 class="wp-block-heading has-3-xl-font-size"><?php esc_html_e( 'Write a clear, specific value proposition', 'seo-geo-theme' ); ?></h2>
		<!-- /wp:heading -->

		<!-- wp:paragraph {"fontSize":"lg"} -->
		<p class="has-lg-font-size"><?php esc_html_e( 'Explain who this is for, what problem it solves and why the reader should continue.', 'seo-geo-theme' ); ?></p>
		<!-- /wp:paragraph -->

		<!-- wp:buttons -->
		<div class="wp-block-buttons">
			<!-- wp:button -->
			<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="#"><?php esc_html_e( 'Primary action', 'seo-geo-theme' ); ?></a></div>
			<!-- /wp:button -->

			<!-- wp:button {"className":"is-style-outline"} -->
			<div class="wp-block-button is-style-outline"><a class="wp-block-button__link wp-element-button" href="#"><?php esc_html_e( 'Secondary action', 'seo-geo-theme' ); ?></a></div>
			<!-- /wp:button -->
		</div>
		<!-- /wp:buttons -->
	</div>
	<!-- /wp:group -->
</div>
<!-- /wp:group -->
