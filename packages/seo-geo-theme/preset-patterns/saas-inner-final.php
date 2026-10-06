<?php
/**
 * SaaS / Digital Product final inner-page compositions.
 *
 * The page template owns the document H1. This preset-owned pattern starts
 * below that title and provides the finished visual/content architecture that
 * hydration later fills with verified client/product facts.
 *
 * @package SeoGeoTheme
 */

declare(strict_types=1);

$seo_geo_saas_inner_key  = isset( $seo_geo_pattern_key ) && is_string( $seo_geo_pattern_key ) ? $seo_geo_pattern_key : '';
$seo_geo_saas_inner_path = seo_geo_theme_preset_root() . '/saas-digital-product/mockup-inner-copy.json';
$seo_geo_saas_inner_doc  = is_readable( $seo_geo_saas_inner_path ) && function_exists( 'wp_json_file_decode' )
	? wp_json_file_decode( $seo_geo_saas_inner_path, array( 'associative' => true ) )
	: null;

if ( ! is_array( $seo_geo_saas_inner_doc ) || '' === $seo_geo_saas_inner_key ) {
	return;
}

$seo_geo_saas_inner_locale   = seo_geo_theme_preset_locale();
$seo_geo_saas_inner_fallback = $seo_geo_saas_inner_doc['en_US'] ?? null;
$seo_geo_saas_inner_current  = $seo_geo_saas_inner_doc[ $seo_geo_saas_inner_locale ] ?? null;

if ( ! is_array( $seo_geo_saas_inner_fallback ) ) {
	return;
}

$seo_geo_saas_inner_pages = is_array( $seo_geo_saas_inner_current )
	? array_replace_recursive( $seo_geo_saas_inner_fallback, $seo_geo_saas_inner_current )
	: $seo_geo_saas_inner_fallback;
$seo_geo_saas_inner_page  = $seo_geo_saas_inner_pages[ $seo_geo_saas_inner_key ] ?? null;

if ( ! is_array( $seo_geo_saas_inner_page ) ) {
	return;
}

$seo_geo_saas_inner_eyebrow  = $seo_geo_saas_inner_page['eyebrow'] ?? '';
$seo_geo_saas_inner_lead     = $seo_geo_saas_inner_page['lead'] ?? '';
$seo_geo_saas_inner_sections = $seo_geo_saas_inner_page['sections'] ?? array();
$seo_geo_saas_inner_cta      = $seo_geo_saas_inner_page['cta'] ?? array();

if ( ! is_string( $seo_geo_saas_inner_eyebrow ) || ! is_string( $seo_geo_saas_inner_lead ) || ! is_array( $seo_geo_saas_inner_sections ) || ! is_array( $seo_geo_saas_inner_cta ) ) {
	return;
}

$seo_geo_saas_inner_variant = sanitize_html_class( $seo_geo_saas_inner_key );
?>
<!-- wp:group {"tagName":"section","align":"wide","className":"seo-geo-saas-hero seo-geo-saas-inner-hero seo-geo-layout-shell","layout":{"type":"constrained"}} -->
<section class="wp-block-group alignwide seo-geo-saas-hero seo-geo-saas-inner-hero seo-geo-layout-shell seo-geo-saas-inner--<?php echo esc_attr( $seo_geo_saas_inner_variant ); ?>">
	<!-- wp:columns {"verticalAlignment":"center","className":"seo-geo-saas-hero__grid"} -->
	<div class="wp-block-columns are-vertically-aligned-center seo-geo-saas-hero__grid">
		<!-- wp:column {"verticalAlignment":"center"} -->
		<div class="wp-block-column is-vertically-aligned-center">
			<!-- wp:group {"className":"seo-geo-saas-hero__copy seo-geo-safe-copy","layout":{"type":"constrained"}} -->
			<div class="wp-block-group seo-geo-saas-hero__copy seo-geo-safe-copy">
				<!-- wp:paragraph {"className":"seo-geo-saas-kicker seo-geo-content-slot--page-eyebrow seo-geo-placeholder--copy"} --><p class="seo-geo-saas-kicker seo-geo-content-slot--page-eyebrow seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_saas_inner_eyebrow ); ?></p><!-- /wp:paragraph -->
				<!-- wp:paragraph {"className":"seo-geo-saas-lead seo-geo-content-slot--page-lead seo-geo-placeholder--copy"} --><p class="seo-geo-saas-lead seo-geo-content-slot--page-lead seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_saas_inner_lead ); ?></p><!-- /wp:paragraph -->
			</div>
			<!-- /wp:group -->
		</div>
		<!-- /wp:column -->
		<!-- wp:column {"verticalAlignment":"center"} -->
		<div class="wp-block-column is-vertically-aligned-center">
			<!-- wp:group {"className":"seo-geo-saas-product-frame seo-geo-content-slot--page-visual seo-geo-placeholder--media","layout":{"type":"default"}} -->
			<div class="wp-block-group seo-geo-saas-product-frame seo-geo-content-slot--page-visual seo-geo-placeholder--media">
				<!-- wp:group {"className":"seo-geo-saas-product-frame__rail","layout":{"type":"default"}} --><div class="wp-block-group seo-geo-saas-product-frame__rail"></div><!-- /wp:group -->
				<!-- wp:group {"className":"seo-geo-saas-product-frame__canvas","layout":{"type":"default"}} --><div class="wp-block-group seo-geo-saas-product-frame__canvas"><!-- wp:group {"className":"seo-geo-saas-product-frame__panel","layout":{"type":"default"}} --><div class="wp-block-group seo-geo-saas-product-frame__panel"></div><!-- /wp:group --><!-- wp:group {"className":"seo-geo-saas-product-frame__metrics","layout":{"type":"default"}} --><div class="wp-block-group seo-geo-saas-product-frame__metrics"><!-- wp:group {"className":"seo-geo-saas-product-frame__metric","layout":{"type":"default"}} --><div class="wp-block-group seo-geo-saas-product-frame__metric"></div><!-- /wp:group --><!-- wp:group {"className":"seo-geo-saas-product-frame__metric","layout":{"type":"default"}} --><div class="wp-block-group seo-geo-saas-product-frame__metric"></div><!-- /wp:group --></div><!-- /wp:group --></div><!-- /wp:group -->
			</div>
			<!-- /wp:group -->
		</div>
		<!-- /wp:column -->
	</div>
	<!-- /wp:columns -->
</section>
<!-- /wp:group -->

<?php foreach ( $seo_geo_saas_inner_sections as $seo_geo_saas_inner_index => $seo_geo_saas_inner_section ) : ?>
	<?php
	if ( ! is_array( $seo_geo_saas_inner_section ) ) {
		continue;
	}
	$seo_geo_saas_inner_label = $seo_geo_saas_inner_section['label'] ?? '';
	$seo_geo_saas_inner_title = $seo_geo_saas_inner_section['title'] ?? '';
	$seo_geo_saas_inner_body  = $seo_geo_saas_inner_section['body'] ?? '';
	$seo_geo_saas_inner_cards = $seo_geo_saas_inner_section['cards'] ?? array();
	if ( ! is_string( $seo_geo_saas_inner_label ) || ! is_string( $seo_geo_saas_inner_title ) || ! is_string( $seo_geo_saas_inner_body ) || ! is_array( $seo_geo_saas_inner_cards ) ) {
		continue;
	}
	$seo_geo_saas_inner_surface = 1 === ( (int) $seo_geo_saas_inner_index % 2 );
	?>
<!-- wp:group {"tagName":"section","align":"full","className":"seo-geo-saas-section","layout":{"type":"constrained"}} -->
<section class="wp-block-group alignfull seo-geo-saas-section<?php echo $seo_geo_saas_inner_surface ? ' seo-geo-saas-section--surface' : ''; ?>">
	<!-- wp:group {"className":"seo-geo-layout-shell","layout":{"type":"constrained"}} -->
	<div class="wp-block-group seo-geo-layout-shell">
		<!-- wp:group {"className":"seo-geo-saas-section__heading seo-geo-safe-copy","layout":{"type":"constrained"}} -->
		<div class="wp-block-group seo-geo-saas-section__heading seo-geo-safe-copy">
			<p class="seo-geo-saas-kicker seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_saas_inner_label ); ?></p>
			<h2 class="wp-block-heading seo-geo-content-slot--section-heading seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_saas_inner_title ); ?></h2>
			<p class="seo-geo-content-slot--section-intro seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_saas_inner_body ); ?></p>
		</div>
		<!-- /wp:group -->
		<!-- wp:group {"className":"seo-geo-saas-use-cases","layout":{"type":"default"}} -->
		<div class="wp-block-group seo-geo-saas-use-cases">
			<?php foreach ( $seo_geo_saas_inner_cards as $seo_geo_saas_inner_card_index => $seo_geo_saas_inner_card ) : ?>
				<?php
				if ( ! is_array( $seo_geo_saas_inner_card ) ) {
					continue;
				}
				$seo_geo_saas_inner_card_title = $seo_geo_saas_inner_card['title'] ?? '';
				$seo_geo_saas_inner_card_body  = $seo_geo_saas_inner_card['body'] ?? '';
				if ( ! is_string( $seo_geo_saas_inner_card_title ) || ! is_string( $seo_geo_saas_inner_card_body ) ) {
					continue;
				}
				?>
				<!-- wp:group {"className":"seo-geo-saas-card seo-geo-surface-card","layout":{"type":"constrained"}} -->
				<div class="wp-block-group seo-geo-saas-card seo-geo-surface-card">
					<p class="seo-geo-saas-card__label"><?php echo esc_html( sprintf( '%02d', (int) $seo_geo_saas_inner_card_index + 1 ) ); ?></p>
					<h3 class="wp-block-heading seo-geo-content-slot--card-title seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_saas_inner_card_title ); ?></h3>
					<p class="seo-geo-content-slot--card-body seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_saas_inner_card_body ); ?></p>
				</div>
				<!-- /wp:group -->
			<?php endforeach; ?>
		</div>
		<!-- /wp:group -->
	</div>
	<!-- /wp:group -->
</section>
<!-- /wp:group -->
<?php endforeach; ?>

<?php
$seo_geo_saas_inner_cta_title  = $seo_geo_saas_inner_cta['title'] ?? '';
$seo_geo_saas_inner_cta_body   = $seo_geo_saas_inner_cta['body'] ?? '';
$seo_geo_saas_inner_cta_button = $seo_geo_saas_inner_cta['button'] ?? '';
$seo_geo_saas_inner_cta_url    = home_url( '/#contact-demo' );
?>
<!-- wp:group {"tagName":"section","align":"full","anchor":"next-step","className":"seo-geo-saas-section seo-geo-saas-final-cta","layout":{"type":"constrained"}} -->
<section id="next-step" class="wp-block-group alignfull seo-geo-saas-section seo-geo-saas-final-cta">
	<!-- wp:group {"className":"seo-geo-layout-shell","layout":{"type":"constrained"}} -->
	<div class="wp-block-group seo-geo-layout-shell">
		<h2 class="wp-block-heading seo-geo-content-slot--cta-heading seo-geo-placeholder--copy"><?php echo esc_html( is_string( $seo_geo_saas_inner_cta_title ) ? $seo_geo_saas_inner_cta_title : '' ); ?></h2>
		<p class="seo-geo-content-slot--cta-body seo-geo-placeholder--copy"><?php echo esc_html( is_string( $seo_geo_saas_inner_cta_body ) ? $seo_geo_saas_inner_cta_body : '' ); ?></p>
		<!-- wp:buttons --><div class="wp-block-buttons"><!-- wp:button --><div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="<?php echo esc_url( $seo_geo_saas_inner_cta_url ); ?>"><?php echo esc_html( is_string( $seo_geo_saas_inner_cta_button ) ? $seo_geo_saas_inner_cta_button : '' ); ?></a></div><!-- /wp:button --></div><!-- /wp:buttons -->
	</div>
	<!-- /wp:group -->
</section>
<!-- /wp:group -->
