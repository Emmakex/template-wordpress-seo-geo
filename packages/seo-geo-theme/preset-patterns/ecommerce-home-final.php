<?php
/**
 * Ecommerce final Home composition.
 *
 * The page-level H1 remains owned by front-page.html. Commerce-facing facts
 * such as products, prices, stock, reviews and store policies stay explicitly
 * provisional until a supported provider or verified store source hydrates
 * them.
 *
 * @package SeoGeoTheme
 */

declare(strict_types=1);

$seo_geo_ecommerce_copy_path = seo_geo_theme_preset_root() . '/ecommerce/mockup-copy.json';
$seo_geo_ecommerce_copy_doc  = is_readable( $seo_geo_ecommerce_copy_path ) && function_exists( 'wp_json_file_decode' )
	? wp_json_file_decode( $seo_geo_ecommerce_copy_path, array( 'associative' => true ) )
	: null;

if ( ! is_array( $seo_geo_ecommerce_copy_doc ) || ! is_array( $seo_geo_ecommerce_copy_doc['en_US'] ?? null ) ) {
	return;
}

$seo_geo_ecommerce_locale   = seo_geo_theme_preset_locale();
$seo_geo_ecommerce_fallback = $seo_geo_ecommerce_copy_doc['en_US'];
$seo_geo_ecommerce_current  = $seo_geo_ecommerce_copy_doc[ $seo_geo_ecommerce_locale ] ?? null;
$seo_geo_ecommerce_copy     = is_array( $seo_geo_ecommerce_current )
	? array_merge( $seo_geo_ecommerce_fallback, $seo_geo_ecommerce_current )
	: $seo_geo_ecommerce_fallback;
?>
<!-- wp:group {"tagName":"section","align":"wide","className":"seo-geo-commerce-home seo-geo-commerce-hero seo-geo-layout-shell","layout":{"type":"constrained"}} -->
<section class="wp-block-group alignwide seo-geo-commerce-home seo-geo-commerce-hero seo-geo-layout-shell">
	<!-- wp:group {"className":"seo-geo-commerce-hero__grid","layout":{"type":"default"}} -->
	<div class="wp-block-group seo-geo-commerce-hero__grid">
		<!-- wp:group {"className":"seo-geo-safe-copy","layout":{"type":"constrained"}} -->
		<div class="wp-block-group seo-geo-safe-copy">
			<p class="seo-geo-commerce-kicker seo-geo-content-slot--hero-kicker seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_ecommerce_copy['kicker'] ); ?></p>
			<p class="seo-geo-commerce-lead seo-geo-content-slot--hero-lead seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_ecommerce_copy['lead'] ); ?></p>
			<!-- wp:buttons {"className":"seo-geo-commerce-actions"} -->
			<div class="wp-block-buttons seo-geo-commerce-actions">
				<!-- wp:button --><div class="wp-block-button"><a class="wp-block-button__link wp-element-button seo-geo-placeholder--copy" href="#catalog"><?php echo esc_html( $seo_geo_ecommerce_copy['primary'] ); ?></a></div><!-- /wp:button -->
				<!-- wp:button {"className":"is-style-outline"} --><div class="wp-block-button is-style-outline"><a class="wp-block-button__link wp-element-button seo-geo-placeholder--copy" href="#buying-guide"><?php echo esc_html( $seo_geo_ecommerce_copy['secondary'] ); ?></a></div><!-- /wp:button -->
			</div>
			<!-- /wp:buttons -->
		</div>
		<!-- /wp:group -->

		<!-- wp:group {"className":"seo-geo-commerce-hero__visual seo-geo-content-slot--hero-media seo-geo-placeholder--media","layout":{"type":"default"}} -->
		<div class="wp-block-group seo-geo-commerce-hero__visual seo-geo-content-slot--hero-media seo-geo-placeholder--media">
			<div class="seo-geo-commerce-product-visual" aria-hidden="true"></div>
			<div class="seo-geo-commerce-hero__rail seo-geo-safe-copy">
				<div class="seo-geo-commerce-hero__note seo-geo-placeholder--copy"><strong><?php echo esc_html( $seo_geo_ecommerce_copy['hero_visual_label'] ); ?></strong><p><?php echo esc_html( $seo_geo_ecommerce_copy['hero_visual_note'] ); ?></p></div>
				<div class="seo-geo-commerce-hero__note seo-geo-placeholder--commerce"><span class="seo-geo-commerce-kicker"><?php echo esc_html( $seo_geo_ecommerce_copy['provider_label'] ); ?></span><strong><?php echo esc_html( $seo_geo_ecommerce_copy['provider_note_title'] ); ?></strong><p><?php echo esc_html( $seo_geo_ecommerce_copy['provider_note_body'] ); ?></p></div>
			</div>
		</div>
		<!-- /wp:group -->
	</div>
	<!-- /wp:group -->
</section>
<!-- /wp:group -->

<!-- wp:group {"tagName":"section","align":"full","className":"seo-geo-commerce-section seo-geo-commerce-section--surface","layout":{"type":"constrained"}} -->
<section class="wp-block-group alignfull seo-geo-commerce-section seo-geo-commerce-section--surface">
	<!-- wp:group {"className":"seo-geo-layout-shell","layout":{"type":"constrained"}} -->
	<div class="wp-block-group seo-geo-layout-shell">
		<div class="seo-geo-commerce-section__heading seo-geo-safe-copy">
			<p class="seo-geo-commerce-kicker seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_ecommerce_copy['categories_label'] ); ?></p>
			<h2 class="wp-block-heading seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_ecommerce_copy['categories_title'] ); ?></h2>
			<p class="seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_ecommerce_copy['categories_intro'] ); ?></p>
		</div>
		<!-- wp:group {"className":"seo-geo-commerce-category-grid","layout":{"type":"default"}} -->
		<div class="wp-block-group seo-geo-commerce-category-grid">
			<div class="seo-geo-commerce-category-card seo-geo-placeholder--commerce"><span class="seo-geo-commerce-card-index">01</span><h3 class="wp-block-heading seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_ecommerce_copy['category_1_title'] ); ?></h3><p class="seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_ecommerce_copy['category_1_body'] ); ?></p></div>
			<div class="seo-geo-commerce-category-card seo-geo-placeholder--commerce"><span class="seo-geo-commerce-card-index">02</span><h3 class="wp-block-heading seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_ecommerce_copy['category_2_title'] ); ?></h3><p class="seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_ecommerce_copy['category_2_body'] ); ?></p></div>
			<div class="seo-geo-commerce-category-card seo-geo-placeholder--commerce"><span class="seo-geo-commerce-card-index">03</span><h3 class="wp-block-heading seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_ecommerce_copy['category_3_title'] ); ?></h3><p class="seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_ecommerce_copy['category_3_body'] ); ?></p></div>
		</div>
		<!-- /wp:group -->
	</div>
	<!-- /wp:group -->
</section>
<!-- /wp:group -->

<!-- wp:group {"tagName":"section","align":"wide","anchor":"catalog","className":"seo-geo-commerce-section seo-geo-layout-shell","layout":{"type":"constrained"}} -->
<section id="catalog" class="wp-block-group alignwide seo-geo-commerce-section seo-geo-layout-shell">
	<div class="seo-geo-commerce-section__heading seo-geo-safe-copy">
		<p class="seo-geo-commerce-kicker seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_ecommerce_copy['catalog_label'] ); ?></p>
		<h2 class="wp-block-heading seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_ecommerce_copy['catalog_title'] ); ?></h2>
		<p class="seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_ecommerce_copy['catalog_intro'] ); ?></p>
	</div>
	<!-- wp:group {"className":"seo-geo-commerce-catalog-grid","layout":{"type":"default"}} -->
	<div class="wp-block-group seo-geo-commerce-catalog-grid">
		<div class="seo-geo-commerce-product-card seo-geo-placeholder--commerce"><div class="seo-geo-commerce-product-card__visual seo-geo-placeholder--media" aria-hidden="true"></div><p class="seo-geo-commerce-kicker seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_ecommerce_copy['catalog_slot_label'] ); ?></p><h3 class="wp-block-heading seo-geo-content-slot--catalog-1-title seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_ecommerce_copy['product_1_title'] ); ?></h3><p class="seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_ecommerce_copy['product_1_body'] ); ?></p><p class="seo-geo-commerce-product-action seo-geo-placeholder--commerce"><a href="#"><?php echo esc_html( $seo_geo_ecommerce_copy['product_action'] ); ?></a></p></div>
		<div class="seo-geo-commerce-product-card seo-geo-placeholder--commerce"><div class="seo-geo-commerce-product-card__visual seo-geo-placeholder--media" aria-hidden="true"></div><p class="seo-geo-commerce-kicker seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_ecommerce_copy['catalog_slot_label'] ); ?></p><h3 class="wp-block-heading seo-geo-content-slot--catalog-2-title seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_ecommerce_copy['product_2_title'] ); ?></h3><p class="seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_ecommerce_copy['product_2_body'] ); ?></p><p class="seo-geo-commerce-product-action seo-geo-placeholder--commerce"><a href="#"><?php echo esc_html( $seo_geo_ecommerce_copy['product_action'] ); ?></a></p></div>
		<div class="seo-geo-commerce-product-card seo-geo-placeholder--commerce"><div class="seo-geo-commerce-product-card__visual seo-geo-placeholder--media" aria-hidden="true"></div><p class="seo-geo-commerce-kicker seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_ecommerce_copy['catalog_slot_label'] ); ?></p><h3 class="wp-block-heading seo-geo-content-slot--catalog-3-title seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_ecommerce_copy['product_3_title'] ); ?></h3><p class="seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_ecommerce_copy['product_3_body'] ); ?></p><p class="seo-geo-commerce-product-action seo-geo-placeholder--commerce"><a href="#"><?php echo esc_html( $seo_geo_ecommerce_copy['product_action'] ); ?></a></p></div>
	</div>
	<!-- /wp:group -->
</section>
<!-- /wp:group -->

<!-- wp:group {"tagName":"section","align":"full","className":"seo-geo-commerce-section seo-geo-commerce-section--ink","layout":{"type":"constrained"}} -->
<section class="wp-block-group alignfull seo-geo-commerce-section seo-geo-commerce-section--ink">
	<!-- wp:group {"className":"seo-geo-layout-shell","layout":{"type":"constrained"}} -->
	<div class="wp-block-group seo-geo-layout-shell">
		<div class="seo-geo-commerce-section__heading seo-geo-safe-copy"><p class="seo-geo-commerce-kicker seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_ecommerce_copy['confidence_label'] ); ?></p><h2 class="wp-block-heading seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_ecommerce_copy['confidence_title'] ); ?></h2><p class="seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_ecommerce_copy['confidence_intro'] ); ?></p></div>
		<div class="seo-geo-commerce-confidence-grid">
			<div class="seo-geo-commerce-confidence-card seo-geo-placeholder--commerce"><span class="seo-geo-commerce-card-index">01</span><h3 class="wp-block-heading seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_ecommerce_copy['confidence_1_title'] ); ?></h3><p class="seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_ecommerce_copy['confidence_1_body'] ); ?></p></div>
			<div class="seo-geo-commerce-confidence-card seo-geo-placeholder--commerce"><span class="seo-geo-commerce-card-index">02</span><h3 class="wp-block-heading seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_ecommerce_copy['confidence_2_title'] ); ?></h3><p class="seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_ecommerce_copy['confidence_2_body'] ); ?></p></div>
			<div class="seo-geo-commerce-confidence-card seo-geo-placeholder--commerce"><span class="seo-geo-commerce-card-index">03</span><h3 class="wp-block-heading seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_ecommerce_copy['confidence_3_title'] ); ?></h3><p class="seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_ecommerce_copy['confidence_3_body'] ); ?></p></div>
		</div>
	</div>
	<!-- /wp:group -->
</section>
<!-- /wp:group -->

<!-- wp:group {"tagName":"section","align":"wide","anchor":"buying-guide","className":"seo-geo-commerce-section seo-geo-layout-shell","layout":{"type":"constrained"}} -->
<section id="buying-guide" class="wp-block-group alignwide seo-geo-commerce-section seo-geo-layout-shell">
	<!-- wp:columns {"verticalAlignment":"center","className":"seo-geo-commerce-guide"} -->
	<div class="wp-block-columns are-vertically-aligned-center seo-geo-commerce-guide">
		<div class="wp-block-column is-vertically-aligned-center seo-geo-safe-copy"><p class="seo-geo-commerce-kicker seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_ecommerce_copy['guide_label'] ); ?></p><h2 class="wp-block-heading seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_ecommerce_copy['guide_title'] ); ?></h2><p class="seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_ecommerce_copy['guide_body'] ); ?></p></div>
		<div class="wp-block-column is-vertically-aligned-center"><div class="seo-geo-commerce-guide-list"><p class="seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_ecommerce_copy['guide_1'] ); ?></p><p class="seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_ecommerce_copy['guide_2'] ); ?></p><p class="seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_ecommerce_copy['guide_3'] ); ?></p></div></div>
	</div>
	<!-- /wp:columns -->
</section>
<!-- /wp:group -->

<!-- wp:group {"tagName":"section","align":"full","className":"seo-geo-commerce-section seo-geo-commerce-section--surface","layout":{"type":"constrained"}} -->
<section class="wp-block-group alignfull seo-geo-commerce-section seo-geo-commerce-section--surface">
	<!-- wp:columns {"verticalAlignment":"center","className":"seo-geo-layout-shell seo-geo-commerce-brand"} -->
	<div class="wp-block-columns are-vertically-aligned-center seo-geo-layout-shell seo-geo-commerce-brand">
		<div class="wp-block-column is-vertically-aligned-center"><div class="seo-geo-commerce-brand-visual seo-geo-placeholder--media" aria-hidden="true"></div></div>
		<div class="wp-block-column is-vertically-aligned-center seo-geo-safe-copy"><p class="seo-geo-commerce-kicker seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_ecommerce_copy['brand_label'] ); ?></p><h2 class="wp-block-heading seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_ecommerce_copy['brand_title'] ); ?></h2><p class="seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_ecommerce_copy['brand_body'] ); ?></p><p class="seo-geo-commerce-text-link seo-geo-placeholder--commerce"><a href="#"><?php echo esc_html( $seo_geo_ecommerce_copy['brand_action'] ); ?></a></p></div>
	</div>
	<!-- /wp:columns -->
</section>
<!-- /wp:group -->

<!-- wp:group {"tagName":"section","align":"wide","className":"seo-geo-commerce-section seo-geo-layout-shell","layout":{"type":"constrained"}} -->
<section class="wp-block-group alignwide seo-geo-commerce-section seo-geo-layout-shell">
	<div class="seo-geo-commerce-section__heading seo-geo-safe-copy"><p class="seo-geo-commerce-kicker seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_ecommerce_copy['policies_label'] ); ?></p><h2 class="wp-block-heading seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_ecommerce_copy['policies_title'] ); ?></h2></div>
	<!-- wp:group {"className":"seo-geo-commerce-policy-links","layout":{"type":"default"}} -->
	<div class="wp-block-group seo-geo-commerce-policy-links seo-geo-placeholder--commerce"><a href="#"><?php echo esc_html( $seo_geo_ecommerce_copy['policy_1'] ); ?></a><a href="#"><?php echo esc_html( $seo_geo_ecommerce_copy['policy_2'] ); ?></a><a href="#"><?php echo esc_html( $seo_geo_ecommerce_copy['policy_3'] ); ?></a><a href="#"><?php echo esc_html( $seo_geo_ecommerce_copy['policy_4'] ); ?></a></div>
	<!-- /wp:group -->
</section>
<!-- /wp:group -->

<!-- wp:group {"tagName":"section","align":"full","className":"seo-geo-commerce-final","layout":{"type":"constrained"}} -->
<section class="wp-block-group alignfull seo-geo-commerce-final">
	<!-- wp:group {"className":"seo-geo-layout-shell seo-geo-safe-copy","layout":{"type":"constrained"}} -->
	<div class="wp-block-group seo-geo-layout-shell seo-geo-safe-copy">
		<p class="seo-geo-commerce-kicker seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_ecommerce_copy['final_label'] ); ?></p>
		<h2 class="wp-block-heading seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_ecommerce_copy['final_title'] ); ?></h2>
		<p class="seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_ecommerce_copy['final_body'] ); ?></p>
		<!-- wp:buttons --><div class="wp-block-buttons"><!-- wp:button --><div class="wp-block-button seo-geo-placeholder--commerce"><a class="wp-block-button__link wp-element-button" href="#"><?php echo esc_html( $seo_geo_ecommerce_copy['final_action'] ); ?></a></div><!-- /wp:button --></div><!-- /wp:buttons -->
	</div>
	<!-- /wp:group -->
</section>
<!-- /wp:group -->
