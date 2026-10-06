<?php
/**
 * Ecommerce final inner-page renderer.
 *
 * The page template owns the document H1. This renderer starts below the page
 * title and keeps every commerce-sensitive surface provisional until hydrated
 * from a supported provider or verified store source.
 *
 * @package SeoGeoTheme
 */

declare(strict_types=1);

if ( ! isset( $seo_geo_ecommerce_pattern_key ) || ! is_string( $seo_geo_ecommerce_pattern_key ) ) {
	return;
}

$seo_geo_ecommerce_allowed_pages = array(
	'shop',
	'categories',
	'buying-guides',
	'about',
	'support',
	'contact',
);

if ( ! in_array( $seo_geo_ecommerce_pattern_key, $seo_geo_ecommerce_allowed_pages, true ) ) {
	return;
}

$seo_geo_ecommerce_copy_path = seo_geo_theme_preset_root() . '/ecommerce/inner-copy.json';
$seo_geo_ecommerce_copy_doc  = is_readable( $seo_geo_ecommerce_copy_path ) && function_exists( 'wp_json_file_decode' )
	? wp_json_file_decode( $seo_geo_ecommerce_copy_path, array( 'associative' => true ) )
	: null;

if ( ! is_array( $seo_geo_ecommerce_copy_doc ) || ! is_array( $seo_geo_ecommerce_copy_doc['en_US'] ?? null ) ) {
	return;
}

$seo_geo_ecommerce_locale        = seo_geo_theme_preset_locale();
$seo_geo_ecommerce_fallback_page = $seo_geo_ecommerce_copy_doc['en_US'][ $seo_geo_ecommerce_pattern_key ] ?? null;
$seo_geo_ecommerce_current_page  = $seo_geo_ecommerce_copy_doc[ $seo_geo_ecommerce_locale ][ $seo_geo_ecommerce_pattern_key ] ?? null;

if ( ! is_array( $seo_geo_ecommerce_fallback_page ) ) {
	return;
}

$seo_geo_ecommerce_copy = is_array( $seo_geo_ecommerce_current_page )
	? array_merge( $seo_geo_ecommerce_fallback_page, $seo_geo_ecommerce_current_page )
	: $seo_geo_ecommerce_fallback_page;

$seo_geo_ecommerce_is_catalog_surface = in_array( $seo_geo_ecommerce_pattern_key, array( 'shop', 'categories' ), true );
$seo_geo_ecommerce_card_class         = $seo_geo_ecommerce_is_catalog_surface
	? 'seo-geo-commerce-category-card seo-geo-placeholder--commerce'
	: 'seo-geo-commerce-category-card';
?>
<!-- wp:group {"tagName":"section","align":"wide","className":"seo-geo-commerce-section seo-geo-layout-shell seo-geo-commerce-inner","layout":{"type":"constrained"}} -->
<section class="wp-block-group alignwide seo-geo-commerce-section seo-geo-layout-shell seo-geo-commerce-inner">
	<div class="seo-geo-commerce-section__heading seo-geo-safe-copy">
		<p class="seo-geo-commerce-kicker seo-geo-content-slot--page-kicker seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_ecommerce_copy['kicker'] ); ?></p>
		<p class="seo-geo-commerce-lead seo-geo-content-slot--page-lead seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_ecommerce_copy['lead'] ); ?></p>
	</div>

	<div class="seo-geo-commerce-section__heading seo-geo-safe-copy">
		<p class="seo-geo-commerce-kicker seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_ecommerce_copy['section_label'] ); ?></p>
		<h2 class="wp-block-heading seo-geo-content-slot--section-title seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_ecommerce_copy['section_title'] ); ?></h2>
		<p class="seo-geo-content-slot--section-body seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_ecommerce_copy['section_body'] ); ?></p>
	</div>

	<!-- wp:group {"className":"seo-geo-commerce-category-grid","layout":{"type":"default"}} -->
	<div class="wp-block-group seo-geo-commerce-category-grid">
		<div class="<?php echo esc_attr( $seo_geo_ecommerce_card_class ); ?>"><span class="seo-geo-commerce-card-index">01</span><h3 class="wp-block-heading seo-geo-content-slot--card-1-title seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_ecommerce_copy['card_1_title'] ); ?></h3><p class="seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_ecommerce_copy['card_1_body'] ); ?></p></div>
		<div class="<?php echo esc_attr( $seo_geo_ecommerce_card_class ); ?>"><span class="seo-geo-commerce-card-index">02</span><h3 class="wp-block-heading seo-geo-content-slot--card-2-title seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_ecommerce_copy['card_2_title'] ); ?></h3><p class="seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_ecommerce_copy['card_2_body'] ); ?></p></div>
		<div class="<?php echo esc_attr( $seo_geo_ecommerce_card_class ); ?>"><span class="seo-geo-commerce-card-index">03</span><h3 class="wp-block-heading seo-geo-content-slot--card-3-title seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_ecommerce_copy['card_3_title'] ); ?></h3><p class="seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_ecommerce_copy['card_3_body'] ); ?></p></div>
	</div>
	<!-- /wp:group -->
</section>
<!-- /wp:group -->

<!-- wp:group {"tagName":"section","align":"full","className":"seo-geo-commerce-section seo-geo-commerce-section--ink","layout":{"type":"constrained"}} -->
<section class="wp-block-group alignfull seo-geo-commerce-section seo-geo-commerce-section--ink">
	<!-- wp:group {"className":"seo-geo-layout-shell","layout":{"type":"constrained"}} -->
	<div class="wp-block-group seo-geo-layout-shell">
		<div class="seo-geo-commerce-section__heading seo-geo-safe-copy">
			<p class="seo-geo-commerce-kicker seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_ecommerce_copy['guide_label'] ); ?></p>
			<h2 class="wp-block-heading seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_ecommerce_copy['guide_title'] ); ?></h2>
			<p class="seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_ecommerce_copy['guide_body'] ); ?></p>
		</div>
		<div class="seo-geo-commerce-confidence-grid">
			<div class="seo-geo-commerce-confidence-card"><span class="seo-geo-commerce-card-index">01</span><p class="seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_ecommerce_copy['guide_item_1'] ); ?></p></div>
			<div class="seo-geo-commerce-confidence-card"><span class="seo-geo-commerce-card-index">02</span><p class="seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_ecommerce_copy['guide_item_2'] ); ?></p></div>
			<div class="seo-geo-commerce-confidence-card"><span class="seo-geo-commerce-card-index">03</span><p class="seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_ecommerce_copy['guide_item_3'] ); ?></p></div>
		</div>
	</div>
	<!-- /wp:group -->
</section>
<!-- /wp:group -->

<!-- wp:group {"tagName":"section","align":"full","anchor":"commerce-next-step","className":"seo-geo-commerce-final","layout":{"type":"constrained"}} -->
<section id="commerce-next-step" class="wp-block-group alignfull seo-geo-commerce-final">
	<p class="seo-geo-commerce-kicker seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_ecommerce_copy['kicker'] ); ?></p>
	<h2 class="wp-block-heading seo-geo-content-slot--final-title seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_ecommerce_copy['final_title'] ); ?></h2>
	<p class="seo-geo-content-slot--final-body seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_ecommerce_copy['final_body'] ); ?></p>
	<p class="seo-geo-commerce-text-link seo-geo-placeholder--commerce"><span><?php echo esc_html( $seo_geo_ecommerce_copy['final_action'] ); ?></span></p>
</section>
<!-- /wp:group -->
