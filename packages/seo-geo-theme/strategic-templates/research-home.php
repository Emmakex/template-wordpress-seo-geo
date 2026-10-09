<?php
/**
 * Research Theme-owned strategic Home document.
 *
 * @package SeoGeoTheme
 */
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$site_name        = (string) get_bloginfo( 'name' );
$site_description = (string) get_bloginfo( 'description' );
$home_url         = home_url( '/' );
$primary_nav      = do_blocks( '<!-- wp:seo-geo/preset-navigation {"location":"primary"} /-->' );
$footer_nav       = do_blocks( '<!-- wp:seo-geo/preset-navigation {"location":"footer"} /-->' );
$custom_logo      = has_custom_logo() ? get_custom_logo() : '';
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<a class="screen-reader-text skip-link" href="#seo-geo-main"><?php esc_html_e( 'Skip to content', 'seo-geo-theme' ); ?></a>

<header class="seo-geo-strategic-header seo-geo-research-header" role="banner">
	<div class="seo-geo-strategic-header__inner seo-geo-research-shell">
		<div class="seo-geo-strategic-brand">
			<?php if ( '' !== $custom_logo ) : ?>
				<div class="seo-geo-strategic-brand__logo">
					<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core-generated custom logo markup. ?>
					<?php echo $custom_logo; ?>
				</div>
			<?php endif; ?>
			<a class="seo-geo-strategic-brand__name" href="<?php echo esc_url( $home_url ); ?>" rel="home"><?php echo esc_html( $site_name ); ?></a>
		</div>
		<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Server-rendered registered navigation block. ?>
		<?php echo $primary_nav; ?>
	</div>
</header>

<?php
if ( function_exists( 'seo_geo_theme_strategic_surface_runtime' ) ) {
	// The renderer escapes every structured model value before composition.
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	echo seo_geo_theme_strategic_surface_runtime()->render_current();
}
?>

<footer class="seo-geo-strategic-footer seo-geo-research-footer" role="contentinfo">
	<div class="seo-geo-strategic-footer__inner seo-geo-research-shell">
		<div class="seo-geo-strategic-footer__brand">
			<a href="<?php echo esc_url( $home_url ); ?>" rel="home"><?php echo esc_html( $site_name ); ?></a>
			<?php if ( '' !== trim( $site_description ) ) : ?>
				<p><?php echo esc_html( $site_description ); ?></p>
			<?php endif; ?>
		</div>
		<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Server-rendered registered navigation block. ?>
		<?php echo $footer_nav; ?>
		<p class="seo-geo-strategic-footer__meta">&copy; <?php echo esc_html( wp_date( 'Y' ) ); ?> <?php echo esc_html( $site_name ); ?></p>
	</div>
</footer>

<?php wp_footer(); ?>
</body>
</html>
