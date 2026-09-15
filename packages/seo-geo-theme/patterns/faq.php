<?php
/**
 * Title: FAQ — native details
 * Slug: seo-geo-theme/faq
 * Categories: text
 * Description: An accessible FAQ layout using native Details blocks without adding Schema ownership.
 * Viewport Width: 900
 *
 * @package SeoGeoTheme
 */

?>
<!-- wp:group {"align":"wide","style":{"spacing":{"padding":{"top":"var:preset|spacing|2xl","bottom":"var:preset|spacing|2xl"}}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group alignwide" style="padding-top:var(--wp--preset--spacing--2-xl);padding-bottom:var(--wp--preset--spacing--2-xl)">
	<!-- wp:heading {"fontSize":"2xl"} -->
	<h2 class="wp-block-heading has-2-xl-font-size"><?php esc_html_e( 'Frequently asked questions', 'seo-geo-theme' ); ?></h2>
	<!-- /wp:heading -->

	<!-- wp:paragraph -->
	<p><?php esc_html_e( 'Answer real questions clearly. Keep each answer useful even when read outside the page context.', 'seo-geo-theme' ); ?></p>
	<!-- /wp:paragraph -->

	<!-- wp:details -->
	<details class="wp-block-details"><summary><?php esc_html_e( 'Replace this with a real customer question', 'seo-geo-theme' ); ?></summary><!-- wp:paragraph -->
	<p><?php esc_html_e( 'Give a direct answer first, then add only the context needed to make it accurate.', 'seo-geo-theme' ); ?></p>
	<!-- /wp:paragraph --></details>
	<!-- /wp:details -->

	<!-- wp:details -->
	<details class="wp-block-details"><summary><?php esc_html_e( 'Add a second high-value question', 'seo-geo-theme' ); ?></summary><!-- wp:paragraph -->
	<p><?php esc_html_e( 'Use factual wording and avoid promises that the visible content cannot support.', 'seo-geo-theme' ); ?></p>
	<!-- /wp:paragraph --></details>
	<!-- /wp:details -->

	<!-- wp:details -->
	<details class="wp-block-details"><summary><?php esc_html_e( 'Add a question that helps the visitor decide', 'seo-geo-theme' ); ?></summary><!-- wp:paragraph -->
	<p><?php esc_html_e( 'Clarify scope, process, requirements, timing or another decision-critical point.', 'seo-geo-theme' ); ?></p>
	<!-- /wp:paragraph --></details>
	<!-- /wp:details -->
</div>
<!-- /wp:group -->
