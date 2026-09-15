<?php
/**
 * Title: Call to action — focused
 * Slug: seo-geo-theme/cta
 * Categories: call-to-action
 * Description: A focused call-to-action section using the semantic accent tokens.
 * Viewport Width: 1200
 *
 * @package SeoGeoTheme
 */

?>
<!-- wp:group {"align":"full","backgroundColor":"accent","textColor":"accent-contrast","style":{"spacing":{"padding":{"top":"var:preset|spacing|2xl","bottom":"var:preset|spacing|2xl","left":"var:preset|spacing|lg","right":"var:preset|spacing|lg"}}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull has-accent-contrast-color has-accent-background-color has-text-color has-background" style="padding-top:var(--wp--preset--spacing--2-xl);padding-right:var(--wp--preset--spacing--lg);padding-bottom:var(--wp--preset--spacing--2-xl);padding-left:var(--wp--preset--spacing--lg)">
	<!-- wp:heading {"textColor":"accent-contrast","fontSize":"2xl"} -->
	<h2 class="wp-block-heading has-accent-contrast-color has-text-color has-2-xl-font-size"><?php esc_html_e( 'State the next useful step clearly', 'seo-geo-theme' ); ?></h2>
	<!-- /wp:heading -->

	<!-- wp:paragraph {"textColor":"accent-contrast"} -->
	<p class="has-accent-contrast-color has-text-color"><?php esc_html_e( 'Add the minimum supporting context a visitor needs before taking action.', 'seo-geo-theme' ); ?></p>
	<!-- /wp:paragraph -->

	<!-- wp:buttons -->
	<div class="wp-block-buttons">
		<!-- wp:button {"backgroundColor":"base","textColor":"accent-strong"} -->
		<div class="wp-block-button"><a class="wp-block-button__link has-accent-strong-color has-base-background-color has-text-color has-background wp-element-button" href="#"><?php esc_html_e( 'Take the next step', 'seo-geo-theme' ); ?></a></div>
		<!-- /wp:button -->
	</div>
	<!-- /wp:buttons -->
</div>
<!-- /wp:group -->
