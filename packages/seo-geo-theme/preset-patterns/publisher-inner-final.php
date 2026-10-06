<?php
/**
 * Publisher / Editorial final inner-page renderer.
 *
 * The page template owns the document H1. This renderer begins below it and
 * keeps all provisional editorial content visibly marked for later hydration.
 *
 * @package SeoGeoTheme
 */

declare(strict_types=1);

$seo_geo_publisher_inner_allowed = array( 'articles', 'topics', 'authors', 'about', 'editorial-policy', 'contact' );
$seo_geo_publisher_inner_key     = isset( $seo_geo_pattern_key ) && is_string( $seo_geo_pattern_key )
	? sanitize_key( $seo_geo_pattern_key )
	: '';

if ( ! in_array( $seo_geo_publisher_inner_key, $seo_geo_publisher_inner_allowed, true ) ) {
	return;
}

$seo_geo_publisher_inner_copy_path = seo_geo_theme_preset_root() . '/publisher/mockup-inner-copy.json';
$seo_geo_publisher_inner_doc       = is_readable( $seo_geo_publisher_inner_copy_path ) && function_exists( 'wp_json_file_decode' )
	? wp_json_file_decode( $seo_geo_publisher_inner_copy_path, array( 'associative' => true ) )
	: null;

if ( ! is_array( $seo_geo_publisher_inner_doc ) || ! is_array( $seo_geo_publisher_inner_doc['en_US'] ?? null ) ) {
	return;
}

$seo_geo_publisher_inner_locale   = seo_geo_theme_preset_locale();
$seo_geo_publisher_inner_fallback = $seo_geo_publisher_inner_doc['en_US'][ $seo_geo_publisher_inner_key ] ?? null;
$seo_geo_publisher_inner_current  = $seo_geo_publisher_inner_doc[ $seo_geo_publisher_inner_locale ][ $seo_geo_publisher_inner_key ] ?? null;

if ( ! is_array( $seo_geo_publisher_inner_fallback ) ) {
	return;
}

$seo_geo_publisher_inner_page = is_array( $seo_geo_publisher_inner_current )
	? array_replace_recursive( $seo_geo_publisher_inner_fallback, $seo_geo_publisher_inner_current )
	: $seo_geo_publisher_inner_fallback;

$seo_geo_publisher_inner_eyebrow  = $seo_geo_publisher_inner_page['eyebrow'] ?? '';
$seo_geo_publisher_inner_lead     = $seo_geo_publisher_inner_page['lead'] ?? '';
$seo_geo_publisher_inner_sections = $seo_geo_publisher_inner_page['sections'] ?? array();
$seo_geo_publisher_inner_cta      = $seo_geo_publisher_inner_page['cta'] ?? array();

if (
	! is_string( $seo_geo_publisher_inner_eyebrow )
	|| ! is_string( $seo_geo_publisher_inner_lead )
	|| ! is_array( $seo_geo_publisher_inner_sections )
	|| ! is_array( $seo_geo_publisher_inner_cta )
) {
	return;
}
?>
<!-- wp:group {"tagName":"section","align":"wide","className":"seo-geo-publisher-section seo-geo-layout-shell seo-geo-safe-copy","layout":{"type":"constrained"}} -->
<section class="wp-block-group alignwide seo-geo-publisher-section seo-geo-layout-shell seo-geo-safe-copy">
	<!-- wp:paragraph {"className":"seo-geo-publisher-kicker seo-geo-content-slot--page-eyebrow seo-geo-placeholder--copy"} -->
	<p class="seo-geo-publisher-kicker seo-geo-content-slot--page-eyebrow seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_publisher_inner_eyebrow ); ?></p>
	<!-- /wp:paragraph -->
	<!-- wp:paragraph {"className":"seo-geo-publisher-lead seo-geo-content-slot--page-lead seo-geo-placeholder--copy"} -->
	<p class="seo-geo-publisher-lead seo-geo-content-slot--page-lead seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_publisher_inner_lead ); ?></p>
	<!-- /wp:paragraph -->
</section>
<!-- /wp:group -->

<?php foreach ( $seo_geo_publisher_inner_sections as $seo_geo_publisher_inner_index => $seo_geo_publisher_inner_section ) : ?>
	<?php
	if ( ! is_array( $seo_geo_publisher_inner_section ) ) {
		continue;
	}

	$seo_geo_publisher_inner_label = $seo_geo_publisher_inner_section['label'] ?? '';
	$seo_geo_publisher_inner_title = $seo_geo_publisher_inner_section['title'] ?? '';
	$seo_geo_publisher_inner_body  = $seo_geo_publisher_inner_section['body'] ?? '';
	$seo_geo_publisher_inner_cards = $seo_geo_publisher_inner_section['cards'] ?? array();

	if (
		! is_string( $seo_geo_publisher_inner_label )
		|| ! is_string( $seo_geo_publisher_inner_title )
		|| ! is_string( $seo_geo_publisher_inner_body )
		|| ! is_array( $seo_geo_publisher_inner_cards )
	) {
		continue;
	}

	$seo_geo_publisher_inner_section_class = 0 === (int) $seo_geo_publisher_inner_index
		? 'seo-geo-publisher-section seo-geo-publisher-section--paper'
		: 'seo-geo-publisher-section';
	?>
<!-- wp:group {"tagName":"section","align":"full","className":"<?php echo esc_attr( $seo_geo_publisher_inner_section_class ); ?>","layout":{"type":"constrained"}} -->
<section class="wp-block-group alignfull <?php echo esc_attr( $seo_geo_publisher_inner_section_class ); ?>">
	<!-- wp:group {"className":"seo-geo-layout-shell","layout":{"type":"constrained"}} -->
	<div class="wp-block-group seo-geo-layout-shell">
		<!-- wp:group {"className":"seo-geo-section-intro seo-geo-safe-copy","layout":{"type":"default"}} -->
		<div class="wp-block-group seo-geo-section-intro seo-geo-safe-copy">
			<p class="seo-geo-publisher-kicker seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_publisher_inner_label ); ?></p>
			<div>
				<h2 class="wp-block-heading seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_publisher_inner_title ); ?></h2>
				<p class="seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_publisher_inner_body ); ?></p>
			</div>
		</div>
		<!-- /wp:group -->

		<!-- wp:group {"className":"seo-geo-bento seo-geo-bento--balanced","layout":{"type":"default"}} -->
		<div class="wp-block-group seo-geo-bento seo-geo-bento--balanced">
			<?php foreach ( $seo_geo_publisher_inner_cards as $seo_geo_publisher_inner_card_index => $seo_geo_publisher_inner_card ) : ?>
				<?php
				if ( ! is_array( $seo_geo_publisher_inner_card ) ) {
					continue;
				}

				$seo_geo_publisher_inner_card_title = $seo_geo_publisher_inner_card['title'] ?? '';
				$seo_geo_publisher_inner_card_body  = $seo_geo_publisher_inner_card['body'] ?? '';

				if ( ! is_string( $seo_geo_publisher_inner_card_title ) || ! is_string( $seo_geo_publisher_inner_card_body ) ) {
					continue;
				}
				?>
				<!-- wp:group {"className":"seo-geo-publisher-story-card seo-geo-surface-card seo-geo-placeholder--copy","layout":{"type":"constrained"}} -->
				<div class="wp-block-group seo-geo-publisher-story-card seo-geo-surface-card seo-geo-placeholder--copy">
					<p class="seo-geo-publisher-story-card__index"><?php echo esc_html( sprintf( '%02d', (int) $seo_geo_publisher_inner_card_index + 1 ) ); ?></p>
					<h3 class="wp-block-heading"><?php echo esc_html( $seo_geo_publisher_inner_card_title ); ?></h3>
					<p><?php echo esc_html( $seo_geo_publisher_inner_card_body ); ?></p>
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
$seo_geo_publisher_inner_cta_title  = $seo_geo_publisher_inner_cta['title'] ?? '';
$seo_geo_publisher_inner_cta_body   = $seo_geo_publisher_inner_cta['body'] ?? '';
$seo_geo_publisher_inner_cta_button = $seo_geo_publisher_inner_cta['button'] ?? '';
?>
<?php if ( is_string( $seo_geo_publisher_inner_cta_title ) && is_string( $seo_geo_publisher_inner_cta_body ) && is_string( $seo_geo_publisher_inner_cta_button ) ) : ?>
<!-- wp:group {"tagName":"section","align":"wide","className":"seo-geo-publisher-follow seo-geo-final-cta seo-geo-safe-copy","layout":{"type":"constrained"}} -->
<section class="wp-block-group alignwide seo-geo-publisher-follow seo-geo-final-cta seo-geo-safe-copy">
	<p class="seo-geo-publisher-kicker seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_publisher_inner_eyebrow ); ?></p>
	<h2 class="wp-block-heading seo-geo-content-slot--final-cta-title seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_publisher_inner_cta_title ); ?></h2>
	<p class="seo-geo-content-slot--final-cta-body seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_publisher_inner_cta_body ); ?></p>
	<!-- wp:buttons -->
	<div class="wp-block-buttons">
		<!-- wp:button {"className":"seo-geo-content-slot--final-cta-route seo-geo-placeholder--copy"} -->
		<div class="wp-block-button seo-geo-content-slot--final-cta-route seo-geo-placeholder--copy"><a class="wp-block-button__link wp-element-button" href="#"><?php echo esc_html( $seo_geo_publisher_inner_cta_button ); ?></a></div>
		<!-- /wp:button -->
	</div>
	<!-- /wp:buttons -->
</section>
<!-- /wp:group -->
<?php endif; ?>
