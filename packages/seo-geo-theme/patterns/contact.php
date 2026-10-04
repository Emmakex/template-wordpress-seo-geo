<?php
/**
 * Title: Contact — essential methods
 * Slug: seo-geo-theme/contact
 * Categories: text
 * Description: A dependency-free contact section with editable email, phone and location prompts.
 * Viewport Width: 1200
 *
 * @package SeoGeoTheme
 */

?>
<!-- wp:group {"align":"wide","className":"seo-geo-native-contact","style":{"spacing":{"padding":{"top":"var:preset|spacing|2xl","bottom":"var:preset|spacing|2xl"}}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group alignwide seo-geo-native-contact" style="padding-top:var(--wp--preset--spacing--2-xl);padding-bottom:var(--wp--preset--spacing--2-xl)">
	<!-- wp:heading {"fontSize":"2xl","className":"seo-geo-content-slot--contact-heading"} -->
	<h2 class="wp-block-heading has-2-xl-font-size seo-geo-content-slot--contact-heading"><?php esc_html_e( 'Contact', 'seo-geo-theme' ); ?></h2>
	<!-- /wp:heading -->

	<!-- wp:paragraph {"className":"seo-geo-content-slot--contact-intro"} -->
	<p class="seo-geo-content-slot--contact-intro"><?php esc_html_e( 'Explain the best way to get in touch and what information helps you respond effectively.', 'seo-geo-theme' ); ?></p>
	<!-- /wp:paragraph -->

	<!-- wp:columns -->
	<div class="wp-block-columns">
		<!-- wp:column -->
		<div class="wp-block-column">
			<!-- wp:heading {"level":3,"fontSize":"lg"} -->
			<h3 class="wp-block-heading has-lg-font-size"><?php esc_html_e( 'Email', 'seo-geo-theme' ); ?></h3>
			<!-- /wp:heading -->
			<!-- wp:paragraph {"className":"seo-geo-content-slot--contact-email"} -->
			<p class="seo-geo-content-slot--contact-email"><?php esc_html_e( 'Add the public contact email address.', 'seo-geo-theme' ); ?></p>
			<!-- /wp:paragraph -->
		</div>
		<!-- /wp:column -->

		<!-- wp:column -->
		<div class="wp-block-column">
			<!-- wp:heading {"level":3,"fontSize":"lg"} -->
			<h3 class="wp-block-heading has-lg-font-size"><?php esc_html_e( 'Phone', 'seo-geo-theme' ); ?></h3>
			<!-- /wp:heading -->
			<!-- wp:paragraph {"className":"seo-geo-content-slot--contact-phone"} -->
			<p class="seo-geo-content-slot--contact-phone"><?php esc_html_e( 'Add the public phone number and, if relevant, service hours.', 'seo-geo-theme' ); ?></p>
			<!-- /wp:paragraph -->
		</div>
		<!-- /wp:column -->

		<!-- wp:column -->
		<div class="wp-block-column">
			<!-- wp:heading {"level":3,"fontSize":"lg"} -->
			<h3 class="wp-block-heading has-lg-font-size"><?php esc_html_e( 'Location or service area', 'seo-geo-theme' ); ?></h3>
			<!-- /wp:heading -->
			<!-- wp:paragraph {"className":"seo-geo-content-slot--contact-area"} -->
			<p class="seo-geo-content-slot--contact-area"><?php esc_html_e( 'Add a real address or describe the geographic area served.', 'seo-geo-theme' ); ?></p>
			<!-- /wp:paragraph -->
		</div>
		<!-- /wp:column -->
	</div>
	<!-- /wp:columns -->

	<!-- wp:buttons -->
	<div class="wp-block-buttons">
		<!-- wp:button {"className":"seo-geo-content-slot--contact-primary-cta"} -->
		<div class="wp-block-button seo-geo-content-slot--contact-primary-cta"><a class="wp-block-button__link wp-element-button" href="#"><?php esc_html_e( 'Contact us', 'seo-geo-theme' ); ?></a></div>
		<!-- /wp:button -->
	</div>
	<!-- /wp:buttons -->
</div>
<!-- /wp:group -->
