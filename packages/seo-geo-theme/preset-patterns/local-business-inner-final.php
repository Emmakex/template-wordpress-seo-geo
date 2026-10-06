<?php
/**
 * Local Business / Local Pro final inner-page renderer.
 *
 * The page template owns the document H1. This renderer begins below it and
 * keeps verified local facts separate from provisional editorial copy.
 *
 * @package SeoGeoTheme
 */

declare(strict_types=1);

$seo_geo_local_inner_allowed = array( 'services', 'locations', 'about', 'faq', 'contact' );
$seo_geo_local_inner_key     = isset( $seo_geo_pattern_key ) && is_string( $seo_geo_pattern_key )
	? sanitize_key( $seo_geo_pattern_key )
	: '';

if ( ! in_array( $seo_geo_local_inner_key, $seo_geo_local_inner_allowed, true ) ) {
	return;
}

$seo_geo_local_inner_copy_path = seo_geo_theme_preset_root() . '/local-business/mockup-inner-copy.json';
$seo_geo_local_inner_doc       = is_readable( $seo_geo_local_inner_copy_path ) && function_exists( 'wp_json_file_decode' )
	? wp_json_file_decode( $seo_geo_local_inner_copy_path, array( 'associative' => true ) )
	: null;

if ( ! is_array( $seo_geo_local_inner_doc ) || ! is_array( $seo_geo_local_inner_doc['en_US'] ?? null ) ) {
	return;
}

$seo_geo_local_inner_locale   = seo_geo_theme_preset_locale();
$seo_geo_local_inner_fallback = $seo_geo_local_inner_doc['en_US'][ $seo_geo_local_inner_key ] ?? null;
$seo_geo_local_inner_current  = $seo_geo_local_inner_doc[ $seo_geo_local_inner_locale ][ $seo_geo_local_inner_key ] ?? null;

if ( ! is_array( $seo_geo_local_inner_fallback ) ) {
	return;
}

$seo_geo_local_inner_page = is_array( $seo_geo_local_inner_current )
	? array_replace_recursive( $seo_geo_local_inner_fallback, $seo_geo_local_inner_current )
	: $seo_geo_local_inner_fallback;

$seo_geo_local_inner_eyebrow  = $seo_geo_local_inner_page['eyebrow'] ?? '';
$seo_geo_local_inner_lead     = $seo_geo_local_inner_page['lead'] ?? '';
$seo_geo_local_inner_sections = $seo_geo_local_inner_page['sections'] ?? array();
$seo_geo_local_inner_cta      = $seo_geo_local_inner_page['cta'] ?? array();
$seo_geo_local_inner_faq      = $seo_geo_local_inner_page['faq'] ?? array();

if (
	! is_string( $seo_geo_local_inner_eyebrow )
	|| ! is_string( $seo_geo_local_inner_lead )
	|| ! is_array( $seo_geo_local_inner_sections )
	|| ! is_array( $seo_geo_local_inner_cta )
	|| ! is_array( $seo_geo_local_inner_faq )
) {
	return;
}
?>
<!-- wp:group {"tagName":"section","align":"wide","className":"seo-geo-local-section seo-geo-local-shell seo-geo-local-inner-hero","layout":{"type":"constrained"}} -->
<section class="wp-block-group alignwide seo-geo-local-section seo-geo-local-shell seo-geo-local-inner-hero">
	<!-- wp:paragraph {"className":"seo-geo-local-kicker seo-geo-content-slot--page-eyebrow seo-geo-placeholder--copy"} -->
	<p class="seo-geo-local-kicker seo-geo-content-slot--page-eyebrow seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_local_inner_eyebrow ); ?></p>
	<!-- /wp:paragraph -->
	<!-- wp:paragraph {"className":"seo-geo-local-lead seo-geo-content-slot--page-lead seo-geo-placeholder--copy"} -->
	<p class="seo-geo-local-lead seo-geo-content-slot--page-lead seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_local_inner_lead ); ?></p>
	<!-- /wp:paragraph -->
</section>
<!-- /wp:group -->

<?php if ( 'locations' === $seo_geo_local_inner_key ) : ?>
<!-- wp:group {"tagName":"section","align":"wide","className":"seo-geo-local-section seo-geo-local-shell","layout":{"type":"constrained"}} -->
<section class="wp-block-group alignwide seo-geo-local-section seo-geo-local-shell">
	<!-- wp:group {"className":"seo-geo-local-area-visual seo-geo-content-slot--locations-visual seo-geo-placeholder--fact","layout":{"type":"default"}} -->
	<div class="wp-block-group seo-geo-local-area-visual seo-geo-content-slot--locations-visual seo-geo-placeholder--fact">
		<!-- wp:group {"className":"seo-geo-local-area-note","layout":{"type":"constrained"}} -->
		<div class="wp-block-group seo-geo-local-area-note"><p><?php echo esc_html( $seo_geo_local_inner_lead ); ?></p></div>
		<!-- /wp:group -->
	</div>
	<!-- /wp:group -->
</section>
<!-- /wp:group -->
<?php endif; ?>

<?php if ( 'faq' === $seo_geo_local_inner_key && array() !== $seo_geo_local_inner_faq ) : ?>
<!-- wp:group {"tagName":"section","align":"wide","className":"seo-geo-local-section seo-geo-local-shell seo-geo-local-faq","layout":{"type":"constrained"}} -->
<section class="wp-block-group alignwide seo-geo-local-section seo-geo-local-shell seo-geo-local-faq">
	<?php foreach ( $seo_geo_local_inner_faq as $seo_geo_local_inner_faq_item ) : ?>
		<?php
		if ( ! is_array( $seo_geo_local_inner_faq_item ) ) {
			continue;
		}
		$seo_geo_local_inner_question = $seo_geo_local_inner_faq_item['q'] ?? '';
		$seo_geo_local_inner_answer   = $seo_geo_local_inner_faq_item['a'] ?? '';
		if ( ! is_string( $seo_geo_local_inner_question ) || ! is_string( $seo_geo_local_inner_answer ) ) {
			continue;
		}
		?>
		<!-- wp:details {"className":"seo-geo-placeholder--copy"} -->
		<details class="wp-block-details seo-geo-placeholder--copy"><summary><?php echo esc_html( $seo_geo_local_inner_question ); ?></summary><!-- wp:paragraph --><p><?php echo esc_html( $seo_geo_local_inner_answer ); ?></p><!-- /wp:paragraph --></details>
		<!-- /wp:details -->
	<?php endforeach; ?>
</section>
<!-- /wp:group -->
<?php endif; ?>

<?php foreach ( $seo_geo_local_inner_sections as $seo_geo_local_inner_index => $seo_geo_local_inner_section ) : ?>
	<?php
	if ( ! is_array( $seo_geo_local_inner_section ) ) {
		continue;
	}

	$seo_geo_local_inner_label      = $seo_geo_local_inner_section['label'] ?? '';
	$seo_geo_local_inner_title      = $seo_geo_local_inner_section['title'] ?? '';
	$seo_geo_local_inner_body       = $seo_geo_local_inner_section['body'] ?? '';
	$seo_geo_local_inner_cards      = $seo_geo_local_inner_section['cards'] ?? array();
	$seo_geo_local_inner_fact_gated = true === ( $seo_geo_local_inner_section['fact_gated'] ?? false );

	if (
		! is_string( $seo_geo_local_inner_label )
		|| ! is_string( $seo_geo_local_inner_title )
		|| ! is_string( $seo_geo_local_inner_body )
		|| ! is_array( $seo_geo_local_inner_cards )
	) {
		continue;
	}

	$seo_geo_local_inner_section_class = 0 === (int) $seo_geo_local_inner_index
		? 'seo-geo-local-section seo-geo-local-section--surface'
		: 'seo-geo-local-section';
	$seo_geo_local_inner_card_class    = $seo_geo_local_inner_fact_gated
		? 'seo-geo-local-service-card seo-geo-placeholder--fact'
		: 'seo-geo-local-service-card seo-geo-placeholder--copy';
	?>
<!-- wp:group {"tagName":"section","align":"full","className":"<?php echo esc_attr( $seo_geo_local_inner_section_class ); ?>","layout":{"type":"constrained"}} -->
<section class="wp-block-group alignfull <?php echo esc_attr( $seo_geo_local_inner_section_class ); ?>">
	<!-- wp:group {"className":"seo-geo-local-shell","layout":{"type":"constrained"}} -->
	<div class="wp-block-group seo-geo-local-shell">
		<!-- wp:group {"className":"seo-geo-local-section__heading","layout":{"type":"constrained"}} -->
		<div class="wp-block-group seo-geo-local-section__heading">
			<p class="seo-geo-local-kicker seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_local_inner_label ); ?></p>
			<h2 class="wp-block-heading seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_local_inner_title ); ?></h2>
			<p class="seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_local_inner_body ); ?></p>
		</div>
		<!-- /wp:group -->
		<!-- wp:group {"className":"seo-geo-local-service-grid","layout":{"type":"default"}} -->
		<div class="wp-block-group seo-geo-local-service-grid">
			<?php foreach ( $seo_geo_local_inner_cards as $seo_geo_local_inner_card_index => $seo_geo_local_inner_card ) : ?>
				<?php
				if ( ! is_array( $seo_geo_local_inner_card ) ) {
					continue;
				}
				$seo_geo_local_inner_card_title = $seo_geo_local_inner_card['title'] ?? '';
				$seo_geo_local_inner_card_body  = $seo_geo_local_inner_card['body'] ?? '';
				if ( ! is_string( $seo_geo_local_inner_card_title ) || ! is_string( $seo_geo_local_inner_card_body ) ) {
					continue;
				}
				?>
				<!-- wp:group {"className":"<?php echo esc_attr( $seo_geo_local_inner_card_class ); ?>","layout":{"type":"constrained"}} -->
				<div class="wp-block-group <?php echo esc_attr( $seo_geo_local_inner_card_class ); ?>">
					<p class="seo-geo-local-service-card__index"><?php echo esc_html( sprintf( '%02d', (int) $seo_geo_local_inner_card_index + 1 ) ); ?></p>
					<h3 class="wp-block-heading"><?php echo esc_html( $seo_geo_local_inner_card_title ); ?></h3>
					<p><?php echo esc_html( $seo_geo_local_inner_card_body ); ?></p>
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
$seo_geo_local_inner_cta_title  = $seo_geo_local_inner_cta['title'] ?? '';
$seo_geo_local_inner_cta_body   = $seo_geo_local_inner_cta['body'] ?? '';
$seo_geo_local_inner_cta_button = $seo_geo_local_inner_cta['button'] ?? '';
?>
<?php if ( is_string( $seo_geo_local_inner_cta_title ) && is_string( $seo_geo_local_inner_cta_body ) && is_string( $seo_geo_local_inner_cta_button ) ) : ?>
<!-- wp:group {"tagName":"section","className":"seo-geo-local-final-cta seo-geo-safe-copy","layout":{"type":"constrained"}} -->
<section class="wp-block-group seo-geo-local-final-cta seo-geo-safe-copy">
	<h2 class="wp-block-heading seo-geo-content-slot--final-cta-title seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_local_inner_cta_title ); ?></h2>
	<p class="seo-geo-content-slot--final-cta-body seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_local_inner_cta_body ); ?></p>
	<!-- wp:buttons --><div class="wp-block-buttons"><!-- wp:button {"className":"seo-geo-content-slot--final-cta-route seo-geo-placeholder--fact"} --><div class="wp-block-button seo-geo-content-slot--final-cta-route seo-geo-placeholder--fact"><a class="wp-block-button__link wp-element-button" href="#"><?php echo esc_html( $seo_geo_local_inner_cta_button ); ?></a></div><!-- /wp:button --></div><!-- /wp:buttons -->
</section>
<!-- /wp:group -->
<?php endif; ?>
