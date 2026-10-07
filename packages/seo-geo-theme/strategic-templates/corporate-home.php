<?php
/**
 * Corporate v5 Theme-owned strategic Home document.
 *
 * @package SeoGeoTheme
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
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
<?php if ( function_exists( 'block_template_part' ) ) : ?>
	<header class="seo-geo-strategic-header">
		<?php block_template_part( 'header' ); ?>
	</header>
<?php endif; ?>

<?php
if ( function_exists( 'seo_geo_theme_strategic_surface_runtime' ) ) {
	// The renderer escapes every model value before composing Theme-owned HTML.
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	echo seo_geo_theme_strategic_surface_runtime()->render_current();
}
?>

<?php if ( function_exists( 'block_template_part' ) ) : ?>
	<footer class="seo-geo-strategic-footer">
		<?php block_template_part( 'footer' ); ?>
	</footer>
<?php endif; ?>

<?php wp_footer(); ?>
</body>
</html>
