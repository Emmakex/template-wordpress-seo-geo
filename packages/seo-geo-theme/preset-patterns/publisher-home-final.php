<?php
/**
 * Publisher / Editorial final Home mockup.
 *
 * The page-level H1 remains owned by front-page.html. This composition starts
 * below that title and provides a finished editorial discovery surface whose
 * provisional story/topic/author copy must be hydrated from real content.
 *
 * @package SeoGeoTheme
 */

declare(strict_types=1);

$seo_geo_publisher_copy_path = seo_geo_theme_preset_root() . '/publisher/mockup-copy.json';
$seo_geo_publisher_copy_doc  = is_readable( $seo_geo_publisher_copy_path ) && function_exists( 'wp_json_file_decode' )
	? wp_json_file_decode( $seo_geo_publisher_copy_path, array( 'associative' => true ) )
	: null;

if ( ! is_array( $seo_geo_publisher_copy_doc ) || ! is_array( $seo_geo_publisher_copy_doc['en_US'] ?? null ) ) {
	return;
}

$seo_geo_publisher_locale   = seo_geo_theme_preset_locale();
$seo_geo_publisher_fallback = $seo_geo_publisher_copy_doc['en_US'];
$seo_geo_publisher_current  = $seo_geo_publisher_copy_doc[ $seo_geo_publisher_locale ] ?? null;
$seo_geo_publisher_copy     = is_array( $seo_geo_publisher_current )
	? array_merge( $seo_geo_publisher_fallback, $seo_geo_publisher_current )
	: $seo_geo_publisher_fallback;
?>
<!-- wp:group {"tagName":"section","align":"wide","className":"seo-geo-publisher-home seo-geo-publisher-hero seo-geo-layout-shell","layout":{"type":"constrained"}} -->
<section class="wp-block-group alignwide seo-geo-publisher-home seo-geo-publisher-hero seo-geo-layout-shell">
	<!-- wp:group {"className":"seo-geo-publisher-hero__intro seo-geo-safe-copy","layout":{"type":"constrained"}} -->
	<div class="wp-block-group seo-geo-publisher-hero__intro seo-geo-safe-copy">
		<!-- wp:paragraph {"className":"seo-geo-publisher-kicker seo-geo-content-slot--hero-kicker seo-geo-placeholder--copy"} --><p class="seo-geo-publisher-kicker seo-geo-content-slot--hero-kicker seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_publisher_copy['kicker'] ); ?></p><!-- /wp:paragraph -->
		<!-- wp:paragraph {"className":"seo-geo-publisher-lead seo-geo-content-slot--hero-lead seo-geo-placeholder--copy"} --><p class="seo-geo-publisher-lead seo-geo-content-slot--hero-lead seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_publisher_copy['lead'] ); ?></p><!-- /wp:paragraph -->
		<!-- wp:buttons {"className":"seo-geo-publisher-actions"} --><div class="wp-block-buttons seo-geo-publisher-actions"><!-- wp:button --><div class="wp-block-button"><a class="wp-block-button__link wp-element-button seo-geo-placeholder--copy" href="#priority-reading"><?php echo esc_html( $seo_geo_publisher_copy['primary'] ); ?></a></div><!-- /wp:button --><!-- wp:button {"className":"is-style-outline"} --><div class="wp-block-button is-style-outline"><a class="wp-block-button__link wp-element-button seo-geo-placeholder--copy" href="#topics"><?php echo esc_html( $seo_geo_publisher_copy['secondary'] ); ?></a></div><!-- /wp:button --></div><!-- /wp:buttons -->
	</div>
	<!-- /wp:group -->

	<!-- wp:group {"className":"seo-geo-publisher-lead-story seo-geo-surface-card","layout":{"type":"default"}} -->
	<div class="wp-block-group seo-geo-publisher-lead-story seo-geo-surface-card">
		<!-- wp:group {"className":"seo-geo-publisher-lead-story__visual seo-geo-content-slot--lead-media seo-geo-placeholder--media","layout":{"type":"default"}} --><div class="wp-block-group seo-geo-publisher-lead-story__visual seo-geo-content-slot--lead-media seo-geo-placeholder--media" aria-hidden="true"></div><!-- /wp:group -->
		<!-- wp:group {"className":"seo-geo-publisher-lead-story__copy seo-geo-safe-copy","layout":{"type":"constrained"}} -->
		<div class="wp-block-group seo-geo-publisher-lead-story__copy seo-geo-safe-copy">
			<!-- wp:paragraph {"className":"seo-geo-publisher-kicker seo-geo-placeholder--copy"} --><p class="seo-geo-publisher-kicker seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_publisher_copy['lead_label'] ); ?></p><!-- /wp:paragraph -->
			<!-- wp:heading {"level":2,"className":"seo-geo-content-slot--lead-title seo-geo-placeholder--copy"} --><h2 class="wp-block-heading seo-geo-content-slot--lead-title seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_publisher_copy['lead_title'] ); ?></h2><!-- /wp:heading -->
			<!-- wp:paragraph {"className":"seo-geo-content-slot--lead-summary seo-geo-placeholder--copy"} --><p class="seo-geo-content-slot--lead-summary seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_publisher_copy['lead_summary'] ); ?></p><!-- /wp:paragraph -->
			<!-- wp:paragraph {"className":"seo-geo-publisher-text-link seo-geo-placeholder--copy"} --><p class="seo-geo-publisher-text-link seo-geo-placeholder--copy"><a href="#priority-reading"><?php echo esc_html( $seo_geo_publisher_copy['lead_action'] ); ?></a></p><!-- /wp:paragraph -->
		</div>
		<!-- /wp:group -->
	</div>
	<!-- /wp:group -->
</section>
<!-- /wp:group -->

<!-- wp:group {"tagName":"section","align":"full","anchor":"priority-reading","className":"seo-geo-publisher-section seo-geo-publisher-section--paper","layout":{"type":"constrained"}} -->
<section id="priority-reading" class="wp-block-group alignfull seo-geo-publisher-section seo-geo-publisher-section--paper"><!-- wp:group {"className":"seo-geo-layout-shell","layout":{"type":"constrained"}} --><div class="wp-block-group seo-geo-layout-shell">
	<!-- wp:group {"className":"seo-geo-section-intro seo-geo-safe-copy","layout":{"type":"default"}} --><div class="wp-block-group seo-geo-section-intro seo-geo-safe-copy"><p class="seo-geo-publisher-kicker seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_publisher_copy['priority_label'] ); ?></p><h2 class="wp-block-heading seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_publisher_copy['priority_title'] ); ?></h2></div><!-- /wp:group -->
	<!-- wp:group {"className":"seo-geo-editorial-grid seo-geo-publisher-story-grid","layout":{"type":"default"}} --><div class="wp-block-group seo-geo-editorial-grid seo-geo-publisher-story-grid">
		<!-- wp:group {"className":"seo-geo-publisher-story-card seo-geo-publisher-story-card--feature seo-geo-surface-card","layout":{"type":"constrained"}} --><div class="wp-block-group seo-geo-publisher-story-card seo-geo-publisher-story-card--feature seo-geo-surface-card"><div class="seo-geo-publisher-story-card__visual seo-geo-placeholder--media" aria-hidden="true"></div><p class="seo-geo-publisher-story-card__index">01</p><h3 class="wp-block-heading seo-geo-content-slot--priority-story-1-title seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_publisher_copy['story_1_title'] ); ?></h3><p class="seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_publisher_copy['story_1_body'] ); ?></p></div><!-- /wp:group -->
		<!-- wp:group {"className":"seo-geo-publisher-story-card seo-geo-surface-card","layout":{"type":"constrained"}} --><div class="wp-block-group seo-geo-publisher-story-card seo-geo-surface-card"><p class="seo-geo-publisher-story-card__index">02</p><h3 class="wp-block-heading seo-geo-content-slot--priority-story-2-title seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_publisher_copy['story_2_title'] ); ?></h3><p class="seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_publisher_copy['story_2_body'] ); ?></p></div><!-- /wp:group -->
		<!-- wp:group {"className":"seo-geo-publisher-story-card seo-geo-surface-card","layout":{"type":"constrained"}} --><div class="wp-block-group seo-geo-publisher-story-card seo-geo-surface-card"><p class="seo-geo-publisher-story-card__index">03</p><h3 class="wp-block-heading seo-geo-content-slot--priority-story-3-title seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_publisher_copy['story_3_title'] ); ?></h3><p class="seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_publisher_copy['story_3_body'] ); ?></p></div><!-- /wp:group -->
	</div><!-- /wp:group -->
</div><!-- /wp:group --></section>
<!-- /wp:group -->

<!-- wp:group {"tagName":"section","align":"wide","anchor":"topics","className":"seo-geo-publisher-section seo-geo-layout-shell","layout":{"type":"constrained"}} -->
<section id="topics" class="wp-block-group alignwide seo-geo-publisher-section seo-geo-layout-shell">
	<!-- wp:group {"className":"seo-geo-publisher-section__heading seo-geo-safe-copy","layout":{"type":"constrained"}} --><div class="wp-block-group seo-geo-publisher-section__heading seo-geo-safe-copy"><p class="seo-geo-publisher-kicker seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_publisher_copy['topics_label'] ); ?></p><h2 class="wp-block-heading seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_publisher_copy['topics_title'] ); ?></h2><p class="seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_publisher_copy['topics_intro'] ); ?></p></div><!-- /wp:group -->
	<!-- wp:group {"className":"seo-geo-publisher-topics","layout":{"type":"flex","flexWrap":"wrap"}} --><div class="wp-block-group seo-geo-publisher-topics"><a class="seo-geo-publisher-topic seo-geo-placeholder--copy" href="#priority-reading"><?php echo esc_html( $seo_geo_publisher_copy['topic_1'] ); ?></a><a class="seo-geo-publisher-topic seo-geo-placeholder--copy" href="#priority-reading"><?php echo esc_html( $seo_geo_publisher_copy['topic_2'] ); ?></a><a class="seo-geo-publisher-topic seo-geo-placeholder--copy" href="#priority-reading"><?php echo esc_html( $seo_geo_publisher_copy['topic_3'] ); ?></a><a class="seo-geo-publisher-topic seo-geo-placeholder--copy" href="#priority-reading"><?php echo esc_html( $seo_geo_publisher_copy['topic_4'] ); ?></a></div><!-- /wp:group -->
</section>
<!-- /wp:group -->

<!-- wp:group {"tagName":"section","align":"full","className":"seo-geo-publisher-section seo-geo-publisher-section--ink","layout":{"type":"constrained"}} -->
<section class="wp-block-group alignfull seo-geo-publisher-section seo-geo-publisher-section--ink"><!-- wp:group {"className":"seo-geo-layout-shell","layout":{"type":"constrained"}} --><div class="wp-block-group seo-geo-layout-shell">
	<!-- wp:group {"className":"seo-geo-publisher-section__heading seo-geo-safe-copy","layout":{"type":"constrained"}} --><div class="wp-block-group seo-geo-publisher-section__heading seo-geo-safe-copy"><p class="seo-geo-publisher-kicker seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_publisher_copy['standards_label'] ); ?></p><h2 class="wp-block-heading seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_publisher_copy['standards_title'] ); ?></h2><p class="seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_publisher_copy['standards_body'] ); ?></p></div><!-- /wp:group -->
	<!-- wp:group {"className":"seo-geo-publisher-standards","layout":{"type":"default"}} --><div class="wp-block-group seo-geo-publisher-standards"><div><span>01</span><h3 class="wp-block-heading seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_publisher_copy['standard_1_title'] ); ?></h3><p class="seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_publisher_copy['standard_1_body'] ); ?></p></div><div><span>02</span><h3 class="wp-block-heading seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_publisher_copy['standard_2_title'] ); ?></h3><p class="seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_publisher_copy['standard_2_body'] ); ?></p></div><div><span>03</span><h3 class="wp-block-heading seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_publisher_copy['standard_3_title'] ); ?></h3><p class="seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_publisher_copy['standard_3_body'] ); ?></p></div></div><!-- /wp:group -->
</div><!-- /wp:group --></section>
<!-- /wp:group -->

<!-- wp:group {"tagName":"section","align":"wide","className":"seo-geo-publisher-section seo-geo-layout-shell","layout":{"type":"constrained"}} -->
<section class="wp-block-group alignwide seo-geo-publisher-section seo-geo-layout-shell">
	<!-- wp:columns {"className":"seo-geo-publisher-resource-split"} --><div class="wp-block-columns seo-geo-publisher-resource-split"><!-- wp:column --><div class="wp-block-column"><p class="seo-geo-publisher-kicker seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_publisher_copy['resources_label'] ); ?></p><h2 class="wp-block-heading seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_publisher_copy['resources_title'] ); ?></h2><p class="seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_publisher_copy['resources_body'] ); ?></p></div><!-- /wp:column --><!-- wp:column --><div class="wp-block-column"><div class="seo-geo-publisher-resource-list"><p class="seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_publisher_copy['resource_1'] ); ?></p><p class="seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_publisher_copy['resource_2'] ); ?></p><p class="seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_publisher_copy['resource_3'] ); ?></p></div></div><!-- /wp:column --></div><!-- /wp:columns -->
</section>
<!-- /wp:group -->

<!-- wp:group {"tagName":"section","align":"full","className":"seo-geo-publisher-section seo-geo-publisher-section--paper","layout":{"type":"constrained"}} -->
<section class="wp-block-group alignfull seo-geo-publisher-section seo-geo-publisher-section--paper"><!-- wp:columns {"verticalAlignment":"center","className":"seo-geo-layout-shell seo-geo-publisher-authors"} --><div class="wp-block-columns are-vertically-aligned-center seo-geo-layout-shell seo-geo-publisher-authors"><!-- wp:column {"verticalAlignment":"center"} --><div class="wp-block-column is-vertically-aligned-center"><div class="seo-geo-publisher-author-visual seo-geo-placeholder--media" aria-hidden="true"><span></span><span></span><span></span></div></div><!-- /wp:column --><!-- wp:column {"verticalAlignment":"center"} --><div class="wp-block-column is-vertically-aligned-center"><p class="seo-geo-publisher-kicker seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_publisher_copy['authors_label'] ); ?></p><h2 class="wp-block-heading seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_publisher_copy['authors_title'] ); ?></h2><p class="seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_publisher_copy['authors_body'] ); ?></p><p class="seo-geo-publisher-text-link seo-geo-placeholder--copy"><a href="#topics"><?php echo esc_html( $seo_geo_publisher_copy['authors_action'] ); ?></a></p></div><!-- /wp:column --></div><!-- /wp:columns --></section>
<!-- /wp:group -->

<!-- wp:group {"tagName":"section","align":"wide","className":"seo-geo-publisher-follow seo-geo-final-cta seo-geo-safe-copy","layout":{"type":"constrained"}} -->
<section class="wp-block-group alignwide seo-geo-publisher-follow seo-geo-final-cta seo-geo-safe-copy">
	<!-- wp:paragraph {"className":"seo-geo-publisher-kicker seo-geo-placeholder--copy"} --><p class="seo-geo-publisher-kicker seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_publisher_copy['follow_label'] ); ?></p><!-- /wp:paragraph -->
	<!-- wp:heading {"level":2,"className":"seo-geo-placeholder--copy"} --><h2 class="wp-block-heading seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_publisher_copy['follow_title'] ); ?></h2><!-- /wp:heading -->
	<!-- wp:paragraph {"className":"seo-geo-placeholder--copy"} --><p class="seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_publisher_copy['follow_body'] ); ?></p><!-- /wp:paragraph -->
	<!-- wp:buttons --><div class="wp-block-buttons"><!-- wp:button --><div class="wp-block-button"><a class="wp-block-button__link wp-element-button seo-geo-placeholder--copy" href="#topics"><?php echo esc_html( $seo_geo_publisher_copy['follow_action'] ); ?></a></div><!-- /wp:button --></div><!-- /wp:buttons -->
</section>
<!-- /wp:group -->
