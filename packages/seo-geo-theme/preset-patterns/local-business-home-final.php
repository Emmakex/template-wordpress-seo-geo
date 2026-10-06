<?php
/**
 * Local Business / Local Pro final Home mockup.
 *
 * The page-level H1 remains owned by front-page.html. This composition starts
 * below that title and supplies the finished local-business information system.
 *
 * @package SeoGeoTheme
 */

declare(strict_types=1);

$seo_geo_local_copy_path = seo_geo_theme_preset_root() . '/local-business/mockup-copy.json';
$seo_geo_local_copy_doc  = is_readable( $seo_geo_local_copy_path ) && function_exists( 'wp_json_file_decode' )
	? wp_json_file_decode( $seo_geo_local_copy_path, array( 'associative' => true ) )
	: null;

if ( ! is_array( $seo_geo_local_copy_doc ) || ! is_array( $seo_geo_local_copy_doc['en_US'] ?? null ) ) {
	return;
}

$seo_geo_local_locale   = seo_geo_theme_preset_locale();
$seo_geo_local_fallback = $seo_geo_local_copy_doc['en_US'];
$seo_geo_local_current  = $seo_geo_local_copy_doc[ $seo_geo_local_locale ] ?? null;
$seo_geo_local_copy     = is_array( $seo_geo_local_current )
	? array_merge( $seo_geo_local_fallback, $seo_geo_local_current )
	: $seo_geo_local_fallback;
?>
<!-- wp:group {"tagName":"section","align":"wide","className":"seo-geo-local-home seo-geo-local-hero seo-geo-local-shell","layout":{"type":"constrained"}} -->
<section class="wp-block-group alignwide seo-geo-local-home seo-geo-local-hero seo-geo-local-shell">
	<!-- wp:columns {"verticalAlignment":"center","className":"seo-geo-local-hero__grid"} -->
	<div class="wp-block-columns are-vertically-aligned-center seo-geo-local-hero__grid">
		<!-- wp:column {"verticalAlignment":"center"} -->
		<div class="wp-block-column is-vertically-aligned-center">
			<!-- wp:group {"className":"seo-geo-safe-copy","layout":{"type":"constrained"}} -->
			<div class="wp-block-group seo-geo-safe-copy">
				<!-- wp:paragraph {"className":"seo-geo-local-kicker seo-geo-content-slot--hero-eyebrow seo-geo-placeholder--copy"} -->
				<p class="seo-geo-local-kicker seo-geo-content-slot--hero-eyebrow seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_local_copy['kicker'] ); ?></p>
				<!-- /wp:paragraph -->
				<!-- wp:paragraph {"className":"seo-geo-local-lead seo-geo-content-slot--hero-lead seo-geo-placeholder--copy"} -->
				<p class="seo-geo-local-lead seo-geo-content-slot--hero-lead seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_local_copy['lead'] ); ?></p>
				<!-- /wp:paragraph -->
				<!-- wp:buttons {"className":"seo-geo-local-actions"} -->
				<div class="wp-block-buttons seo-geo-local-actions">
					<!-- wp:button {"className":"seo-geo-content-slot--hero-primary-cta seo-geo-placeholder--copy"} -->
					<div class="wp-block-button seo-geo-content-slot--hero-primary-cta seo-geo-placeholder--copy"><a class="wp-block-button__link wp-element-button" href="#contacto-local"><?php echo esc_html( $seo_geo_local_copy['primary'] ); ?></a></div>
					<!-- /wp:button -->
					<!-- wp:button {"className":"is-style-outline seo-geo-content-slot--hero-secondary-cta seo-geo-placeholder--copy"} -->
					<div class="wp-block-button is-style-outline seo-geo-content-slot--hero-secondary-cta seo-geo-placeholder--copy"><a class="wp-block-button__link wp-element-button" href="#servicios-locales"><?php echo esc_html( $seo_geo_local_copy['secondary'] ); ?></a></div>
					<!-- /wp:button -->
				</div>
				<!-- /wp:buttons -->
			</div>
			<!-- /wp:group -->
		</div>
		<!-- /wp:column -->

		<!-- wp:column {"verticalAlignment":"center"} -->
		<div class="wp-block-column is-vertically-aligned-center">
			<!-- wp:group {"className":"seo-geo-local-presence seo-geo-content-slot--local-presence seo-geo-placeholder--fact","layout":{"type":"constrained"}} -->
			<div class="wp-block-group seo-geo-local-presence seo-geo-content-slot--local-presence seo-geo-placeholder--fact">
				<!-- wp:paragraph {"className":"seo-geo-local-presence__status"} -->
				<p class="seo-geo-local-presence__status"><?php echo esc_html( $seo_geo_local_copy['presence_status'] ); ?></p>
				<!-- /wp:paragraph -->
				<!-- wp:group {"className":"seo-geo-local-fact-grid","layout":{"type":"default"}} -->
				<div class="wp-block-group seo-geo-local-fact-grid">
					<!-- wp:group {"className":"seo-geo-local-fact-card","layout":{"type":"constrained"}} -->
					<div class="wp-block-group seo-geo-local-fact-card"><strong><?php echo esc_html( $seo_geo_local_copy['fact_area_label'] ); ?></strong><p class="seo-geo-placeholder--fact"><?php echo esc_html( $seo_geo_local_copy['fact_area_value'] ); ?></p></div>
					<!-- /wp:group -->
					<!-- wp:group {"className":"seo-geo-local-fact-card","layout":{"type":"constrained"}} -->
					<div class="wp-block-group seo-geo-local-fact-card"><strong><?php echo esc_html( $seo_geo_local_copy['fact_contact_label'] ); ?></strong><p class="seo-geo-placeholder--fact"><?php echo esc_html( $seo_geo_local_copy['fact_contact_value'] ); ?></p></div>
					<!-- /wp:group -->
					<!-- wp:group {"className":"seo-geo-local-fact-card","layout":{"type":"constrained"}} -->
					<div class="wp-block-group seo-geo-local-fact-card"><strong><?php echo esc_html( $seo_geo_local_copy['fact_hours_label'] ); ?></strong><p class="seo-geo-placeholder--fact"><?php echo esc_html( $seo_geo_local_copy['fact_hours_value'] ); ?></p></div>
					<!-- /wp:group -->
				</div>
				<!-- /wp:group -->
			</div>
			<!-- /wp:group -->
		</div>
		<!-- /wp:column -->
	</div>
	<!-- /wp:columns -->
</section>
<!-- /wp:group -->

<!-- wp:group {"tagName":"section","align":"full","className":"seo-geo-local-section seo-geo-local-section--surface","layout":{"type":"constrained"}} -->
<section class="wp-block-group alignfull seo-geo-local-section seo-geo-local-section--surface">
	<!-- wp:group {"className":"seo-geo-local-shell","layout":{"type":"constrained"}} -->
	<div class="wp-block-group seo-geo-local-shell">
		<!-- wp:paragraph {"className":"seo-geo-local-kicker"} -->
		<p class="seo-geo-local-kicker"><?php echo esc_html( $seo_geo_local_copy['actions_label'] ); ?></p>
		<!-- /wp:paragraph -->
		<!-- wp:group {"className":"seo-geo-local-action-grid","layout":{"type":"default"}} -->
		<div class="wp-block-group seo-geo-local-action-grid">
			<!-- wp:group {"className":"seo-geo-local-action-card","layout":{"type":"constrained"}} -->
			<div class="wp-block-group seo-geo-local-action-card"><h3 class="wp-block-heading seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_local_copy['action_1_title'] ); ?></h3><p class="seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_local_copy['action_1_body'] ); ?></p></div>
			<!-- /wp:group -->
			<!-- wp:group {"className":"seo-geo-local-action-card","layout":{"type":"constrained"}} -->
			<div class="wp-block-group seo-geo-local-action-card"><h3 class="wp-block-heading seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_local_copy['action_2_title'] ); ?></h3><p class="seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_local_copy['action_2_body'] ); ?></p></div>
			<!-- /wp:group -->
			<!-- wp:group {"className":"seo-geo-local-action-card","layout":{"type":"constrained"}} -->
			<div class="wp-block-group seo-geo-local-action-card"><h3 class="wp-block-heading seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_local_copy['action_3_title'] ); ?></h3><p class="seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_local_copy['action_3_body'] ); ?></p></div>
			<!-- /wp:group -->
		</div>
		<!-- /wp:group -->
	</div>
	<!-- /wp:group -->
</section>
<!-- /wp:group -->

<!-- wp:group {"tagName":"section","align":"wide","anchor":"servicios-locales","className":"seo-geo-local-section seo-geo-local-shell","layout":{"type":"constrained"}} -->
<section id="servicios-locales" class="wp-block-group alignwide seo-geo-local-section seo-geo-local-shell">
	<!-- wp:group {"className":"seo-geo-local-section__heading seo-geo-safe-copy","layout":{"type":"constrained"}} -->
	<div class="wp-block-group seo-geo-local-section__heading seo-geo-safe-copy"><p class="seo-geo-local-kicker"><?php echo esc_html( $seo_geo_local_copy['services_label'] ); ?></p><h2 class="wp-block-heading seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_local_copy['services_title'] ); ?></h2><p class="seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_local_copy['services_intro'] ); ?></p></div>
	<!-- /wp:group -->
	<!-- wp:group {"className":"seo-geo-local-service-grid","layout":{"type":"default"}} -->
	<div class="wp-block-group seo-geo-local-service-grid">
		<!-- wp:group {"className":"seo-geo-local-service-card seo-geo-placeholder--copy","layout":{"type":"constrained"}} -->
		<div class="wp-block-group seo-geo-local-service-card seo-geo-placeholder--copy"><p class="seo-geo-local-service-card__index">01</p><h3 class="wp-block-heading"><?php echo esc_html( $seo_geo_local_copy['service_1_title'] ); ?></h3><p><?php echo esc_html( $seo_geo_local_copy['service_1_body'] ); ?></p></div>
		<!-- /wp:group -->
		<!-- wp:group {"className":"seo-geo-local-service-card seo-geo-placeholder--copy","layout":{"type":"constrained"}} -->
		<div class="wp-block-group seo-geo-local-service-card seo-geo-placeholder--copy"><p class="seo-geo-local-service-card__index">02</p><h3 class="wp-block-heading"><?php echo esc_html( $seo_geo_local_copy['service_2_title'] ); ?></h3><p><?php echo esc_html( $seo_geo_local_copy['service_2_body'] ); ?></p></div>
		<!-- /wp:group -->
		<!-- wp:group {"className":"seo-geo-local-service-card seo-geo-placeholder--copy","layout":{"type":"constrained"}} -->
		<div class="wp-block-group seo-geo-local-service-card seo-geo-placeholder--copy"><p class="seo-geo-local-service-card__index">03</p><h3 class="wp-block-heading"><?php echo esc_html( $seo_geo_local_copy['service_3_title'] ); ?></h3><p><?php echo esc_html( $seo_geo_local_copy['service_3_body'] ); ?></p></div>
		<!-- /wp:group -->
	</div>
	<!-- /wp:group -->
</section>
<!-- /wp:group -->

<!-- wp:group {"tagName":"section","align":"full","className":"seo-geo-local-section seo-geo-local-section--surface","layout":{"type":"constrained"}} -->
<section class="wp-block-group alignfull seo-geo-local-section seo-geo-local-section--surface">
	<!-- wp:columns {"verticalAlignment":"center","className":"seo-geo-local-shell seo-geo-local-split"} -->
	<div class="wp-block-columns are-vertically-aligned-center seo-geo-local-shell seo-geo-local-split">
		<!-- wp:column {"verticalAlignment":"center"} -->
		<div class="wp-block-column is-vertically-aligned-center">
			<!-- wp:group {"className":"seo-geo-local-area-visual seo-geo-content-slot--service-area-visual seo-geo-placeholder--fact","layout":{"type":"default"}} -->
			<div class="wp-block-group seo-geo-local-area-visual seo-geo-content-slot--service-area-visual seo-geo-placeholder--fact"><!-- wp:group {"className":"seo-geo-local-area-note","layout":{"type":"constrained"}} --><div class="wp-block-group seo-geo-local-area-note"><p><?php echo esc_html( $seo_geo_local_copy['area_note'] ); ?></p></div><!-- /wp:group --></div>
			<!-- /wp:group -->
		</div>
		<!-- /wp:column -->
		<!-- wp:column {"verticalAlignment":"center"} -->
		<div class="wp-block-column is-vertically-aligned-center"><p class="seo-geo-local-kicker"><?php echo esc_html( $seo_geo_local_copy['area_label'] ); ?></p><h2 class="wp-block-heading seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_local_copy['area_title'] ); ?></h2><p class="seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_local_copy['area_body'] ); ?></p><!-- wp:list {"className":"seo-geo-placeholder--fact"} --><ul class="wp-block-list seo-geo-placeholder--fact"><li><?php echo esc_html( $seo_geo_local_copy['area_point_1'] ); ?></li><li><?php echo esc_html( $seo_geo_local_copy['area_point_2'] ); ?></li><li><?php echo esc_html( $seo_geo_local_copy['area_point_3'] ); ?></li></ul><!-- /wp:list --></div>
		<!-- /wp:column -->
	</div>
	<!-- /wp:columns -->
</section>
<!-- /wp:group -->

<!-- wp:group {"tagName":"section","align":"wide","className":"seo-geo-local-section seo-geo-local-shell","layout":{"type":"constrained"}} -->
<section class="wp-block-group alignwide seo-geo-local-section seo-geo-local-shell">
	<!-- wp:group {"className":"seo-geo-local-section__heading","layout":{"type":"constrained"}} -->
	<div class="wp-block-group seo-geo-local-section__heading"><p class="seo-geo-local-kicker"><?php echo esc_html( $seo_geo_local_copy['process_label'] ); ?></p><h2 class="wp-block-heading seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_local_copy['process_title'] ); ?></h2></div>
	<!-- /wp:group -->
	<!-- wp:group {"className":"seo-geo-local-process","layout":{"type":"default"}} -->
	<div class="wp-block-group seo-geo-local-process">
		<!-- wp:group {"layout":{"type":"constrained"}} --><div class="wp-block-group"><h3 class="wp-block-heading seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_local_copy['step_1_title'] ); ?></h3><p class="seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_local_copy['step_1_body'] ); ?></p></div><!-- /wp:group -->
		<!-- wp:group {"layout":{"type":"constrained"}} --><div class="wp-block-group"><h3 class="wp-block-heading seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_local_copy['step_2_title'] ); ?></h3><p class="seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_local_copy['step_2_body'] ); ?></p></div><!-- /wp:group -->
		<!-- wp:group {"layout":{"type":"constrained"}} --><div class="wp-block-group"><h3 class="wp-block-heading seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_local_copy['step_3_title'] ); ?></h3><p class="seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_local_copy['step_3_body'] ); ?></p></div><!-- /wp:group -->
	</div>
	<!-- /wp:group -->
</section>
<!-- /wp:group -->

<!-- wp:group {"tagName":"section","align":"full","className":"seo-geo-local-section seo-geo-local-section--surface","layout":{"type":"constrained"}} -->
<section class="wp-block-group alignfull seo-geo-local-section seo-geo-local-section--surface">
	<!-- wp:group {"className":"seo-geo-local-shell","layout":{"type":"constrained"}} -->
	<div class="wp-block-group seo-geo-local-shell">
		<!-- wp:group {"className":"seo-geo-local-section__heading","layout":{"type":"constrained"}} -->
		<div class="wp-block-group seo-geo-local-section__heading"><p class="seo-geo-local-kicker"><?php echo esc_html( $seo_geo_local_copy['facts_label'] ); ?></p><h2 class="wp-block-heading seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_local_copy['facts_title'] ); ?></h2><p class="seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_local_copy['facts_intro'] ); ?></p></div>
		<!-- /wp:group -->
		<!-- wp:group {"className":"seo-geo-local-facts seo-geo-placeholder--fact","layout":{"type":"constrained"}} -->
		<div class="wp-block-group seo-geo-local-facts seo-geo-placeholder--fact">
			<!-- wp:group {"className":"seo-geo-local-fact-grid","layout":{"type":"default"}} -->
			<div class="wp-block-group seo-geo-local-fact-grid">
				<!-- wp:group {"className":"seo-geo-local-fact-card","layout":{"type":"constrained"}} --><div class="wp-block-group seo-geo-local-fact-card"><h3 class="wp-block-heading"><?php echo esc_html( $seo_geo_local_copy['facts_address_title'] ); ?></h3><p><?php echo esc_html( $seo_geo_local_copy['facts_address_body'] ); ?></p></div><!-- /wp:group -->
				<!-- wp:group {"className":"seo-geo-local-fact-card","layout":{"type":"constrained"}} --><div class="wp-block-group seo-geo-local-fact-card"><h3 class="wp-block-heading"><?php echo esc_html( $seo_geo_local_copy['facts_contact_title'] ); ?></h3><p><?php echo esc_html( $seo_geo_local_copy['facts_contact_body'] ); ?></p></div><!-- /wp:group -->
				<!-- wp:group {"className":"seo-geo-local-fact-card","layout":{"type":"constrained"}} --><div class="wp-block-group seo-geo-local-fact-card"><h3 class="wp-block-heading"><?php echo esc_html( $seo_geo_local_copy['facts_hours_title'] ); ?></h3><p><?php echo esc_html( $seo_geo_local_copy['facts_hours_body'] ); ?></p></div><!-- /wp:group -->
			</div>
			<!-- /wp:group -->
		</div>
		<!-- /wp:group -->
	</div>
	<!-- /wp:group -->
</section>
<!-- /wp:group -->

<!-- wp:group {"tagName":"section","align":"wide","className":"seo-geo-local-section seo-geo-local-shell seo-geo-local-faq","layout":{"type":"constrained"}} -->
<section class="wp-block-group alignwide seo-geo-local-section seo-geo-local-shell seo-geo-local-faq">
	<p class="seo-geo-local-kicker"><?php echo esc_html( $seo_geo_local_copy['faq_label'] ); ?></p>
	<h2 class="wp-block-heading seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_local_copy['faq_title'] ); ?></h2>
	<!-- wp:details {"className":"seo-geo-placeholder--copy"} --><details class="wp-block-details seo-geo-placeholder--copy"><summary><?php echo esc_html( $seo_geo_local_copy['faq_1_q'] ); ?></summary><!-- wp:paragraph --><p><?php echo esc_html( $seo_geo_local_copy['faq_1_a'] ); ?></p><!-- /wp:paragraph --></details><!-- /wp:details -->
	<!-- wp:details {"className":"seo-geo-placeholder--copy"} --><details class="wp-block-details seo-geo-placeholder--copy"><summary><?php echo esc_html( $seo_geo_local_copy['faq_2_q'] ); ?></summary><!-- wp:paragraph --><p><?php echo esc_html( $seo_geo_local_copy['faq_2_a'] ); ?></p><!-- /wp:paragraph --></details><!-- /wp:details -->
	<!-- wp:details {"className":"seo-geo-placeholder--copy"} --><details class="wp-block-details seo-geo-placeholder--copy"><summary><?php echo esc_html( $seo_geo_local_copy['faq_3_q'] ); ?></summary><!-- wp:paragraph --><p><?php echo esc_html( $seo_geo_local_copy['faq_3_a'] ); ?></p><!-- /wp:paragraph --></details><!-- /wp:details -->
</section>
<!-- /wp:group -->

<!-- wp:group {"tagName":"section","anchor":"contacto-local","className":"seo-geo-local-final-cta seo-geo-safe-copy","layout":{"type":"constrained"}} -->
<section id="contacto-local" class="wp-block-group seo-geo-local-final-cta seo-geo-safe-copy">
	<!-- wp:heading {"level":2,"className":"seo-geo-content-slot--final-cta-title seo-geo-placeholder--copy"} --><h2 class="wp-block-heading seo-geo-content-slot--final-cta-title seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_local_copy['cta_title'] ); ?></h2><!-- /wp:heading -->
	<!-- wp:paragraph {"className":"seo-geo-content-slot--final-cta-body seo-geo-placeholder--copy"} --><p class="seo-geo-content-slot--final-cta-body seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_local_copy['cta_body'] ); ?></p><!-- /wp:paragraph -->
	<!-- wp:buttons --><div class="wp-block-buttons"><!-- wp:button {"className":"seo-geo-placeholder--fact"} --><div class="wp-block-button seo-geo-placeholder--fact"><a class="wp-block-button__link wp-element-button" href="#"><?php echo esc_html( $seo_geo_local_copy['cta_button'] ); ?></a></div><!-- /wp:button --></div><!-- /wp:buttons -->
</section>
<!-- /wp:group -->
