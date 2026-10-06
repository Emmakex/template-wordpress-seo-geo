<?php
/**
 * Title: Corporate — final Home mockup
 * Slug: seo-geo-theme/corporate-home-final
 * Categories: featured, call-to-action
 * Description: Complete Corporate Home composition with safe copy/media placeholders ready for later hydration.
 * Viewport Width: 1440
 *
 * @package SeoGeoTheme
 */

$locale = function_exists( 'seo_geo_theme_preset_locale' ) ? seo_geo_theme_preset_locale() : 'en_US';
$is_es  = 'es_ES' === $locale;
$asset  = trailingslashit( get_template_directory_uri() ) . 'assets/images/presets/corporate/';

$copy = $is_es ? array(
	'eyebrow'        => 'Estrategia · producto · crecimiento',
	'lead'           => 'Lorem ipsum dolor sit amet, una propuesta clara que explique qué cambia para el cliente y por qué merece la pena seguir leyendo.',
	'primary'        => 'Hablar del proyecto',
	'secondary'      => 'Ver capacidades',
	'cap_eyebrow'    => 'Capacidades',
	'cap_heading'    => 'Una oferta clara, visual y fácil de entender',
	'cap_intro'      => 'Lorem ipsum dolor sit amet. Esta maqueta define la jerarquía final; después sustituimos este contenido por el material rescatado y lo optimizamos.',
	'cap1'           => 'Estrategia digital',
	'cap1_body'      => 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Define el problema, la oportunidad y el camino.',
	'cap2'           => 'Producto y experiencia',
	'cap2_body'      => 'Lorem ipsum dolor sit amet. Diseña experiencias rápidas, útiles y coherentes en todos los dispositivos.',
	'cap3'           => 'Crecimiento medible',
	'cap3_body'      => 'Lorem ipsum dolor sit amet. Conecta adquisición, contenido, automatización y medición con objetivos reales.',
	'explore'        => 'Explorar capacidad',
	'work_eyebrow'   => 'Trabajo destacado',
	'work_heading'   => 'El proyecto se explica con una gran composición, no con otra tarjeta',
	'work_body'      => 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Aquí entrará un caso real, una imagen rescatada o un visual definitivo cuando exista evidencia verificable.',
	'work_link'      => 'Ver proyectos',
	'process_label'  => 'Método',
	'process_title'  => 'De una pregunta concreta a un resultado que se puede mejorar',
	'process_intro'  => 'Lorem ipsum dolor sit amet. El proceso debe ayudar a entender cómo trabajamos sin convertir la página en un manual.',
	'p1'             => 'Entender',
	'p1b'            => 'Contexto, audiencia, restricciones, señales y criterio de éxito.',
	'p2'             => 'Construir',
	'p2b'            => 'Diseño, contenido, tecnología y checkpoints en una única dirección.',
	'p3'             => 'Mejorar',
	'p3b'            => 'Medición, aprendizaje y evolución continua después de publicar.',
	'about_label'    => 'La empresa',
	'about_title'    => 'Un bloque editorial preparado para contar quién hay detrás',
	'about_body'     => 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Esta zona está preparada para historia, equipo, filosofía y señales de autoridad reales.',
	'about_link'     => 'Conocer la empresa',
	'insights_label' => 'Insights',
	'insights_title' => 'Contenido útil que demuestra experiencia y amplía el grafo temático',
	'insights_body'  => 'Los artículos recientes se integran en la dirección de arte y siguen siendo HTML rastreable, enlazado y rápido.',
	'empty'          => 'Publica el primer insight útil para completar esta sección.',
	'cta_title'      => 'La siguiente acción también forma parte del diseño',
	'cta_body'       => 'Lorem ipsum dolor sit amet. Un cierre claro, con contexto suficiente y una sola acción principal.',
	'cta_button'     => 'Empezar conversación',
) : array(
	'eyebrow'        => 'Strategy · product · growth',
	'lead'           => 'Lorem ipsum dolor sit amet, a clear proposition explaining what changes for the client and why the rest of the page is worth exploring.',
	'primary'        => 'Discuss the project',
	'secondary'      => 'Explore capabilities',
	'cap_eyebrow'    => 'Capabilities',
	'cap_heading'    => 'A clear, visual offer that is easy to understand',
	'cap_intro'      => 'Lorem ipsum dolor sit amet. This mockup defines the final hierarchy; rescued content can replace the placeholders without redesigning the page.',
	'cap1'           => 'Digital strategy',
	'cap1_body'      => 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Define the problem, opportunity and path forward.',
	'cap2'           => 'Product and experience',
	'cap2_body'      => 'Lorem ipsum dolor sit amet. Design fast, useful and coherent experiences across devices.',
	'cap3'           => 'Measurable growth',
	'cap3_body'      => 'Lorem ipsum dolor sit amet. Connect acquisition, content, automation and measurement to real objectives.',
	'explore'        => 'Explore capability',
	'work_eyebrow'   => 'Featured work',
	'work_heading'   => 'A project is explained with a major composition, not another generic card',
	'work_body'      => 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. A verified case, rescued image or final visual can replace this safe preview later.',
	'work_link'      => 'View work',
	'process_label'  => 'Method',
	'process_title'  => 'From a concrete question to an outcome that keeps improving',
	'process_intro'  => 'Lorem ipsum dolor sit amet. The process should explain how the work moves without turning the page into a manual.',
	'p1'             => 'Understand',
	'p1b'            => 'Context, audience, constraints, signals and success criteria.',
	'p2'             => 'Build',
	'p2b'            => 'Design, content, technology and checkpoints moving in one direction.',
	'p3'             => 'Improve',
	'p3b'            => 'Measurement, learning and continuous evolution after launch.',
	'about_label'    => 'Company',
	'about_title'    => 'An editorial block ready to explain who is behind the work',
	'about_body'     => 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. This area is prepared for verified history, team, philosophy and authority signals.',
	'about_link'     => 'About the company',
	'insights_label' => 'Insights',
	'insights_title' => 'Useful publishing that demonstrates expertise and expands topical depth',
	'insights_body'  => 'Recent articles belong to the art direction while remaining crawlable, linked and fast server-rendered HTML.',
	'empty'          => 'Publish the first useful insight to populate this section.',
	'cta_title'      => 'The next action is part of the design too',
	'cta_body'       => 'Lorem ipsum dolor sit amet. A clear close with enough context and one primary action.',
	'cta_button'     => 'Start a conversation',
);
?>

<!-- wp:group {"tagName":"section","align":"wide","className":"seo-geo-corporate-final-home seo-geo-corporate-native-hero","layout":{"type":"constrained"}} -->
<section class="wp-block-group alignwide seo-geo-corporate-final-home seo-geo-corporate-native-hero">
	<!-- wp:columns {"verticalAlignment":"center","className":"seo-geo-corporate-native-hero__grid"} -->
	<div class="wp-block-columns are-vertically-aligned-center seo-geo-corporate-native-hero__grid">
		<!-- wp:column {"verticalAlignment":"center"} -->
		<div class="wp-block-column is-vertically-aligned-center">
			<!-- wp:group {"className":"seo-geo-corporate-native-hero__copy","layout":{"type":"constrained"}} -->
			<div class="wp-block-group seo-geo-corporate-native-hero__copy">
				<!-- wp:paragraph {"className":"seo-geo-corporate-eyebrow seo-geo-content-slot--hero-eyebrow seo-geo-placeholder--copy"} -->
				<p class="seo-geo-corporate-eyebrow seo-geo-content-slot--hero-eyebrow seo-geo-placeholder--copy"><?php echo esc_html( $copy['eyebrow'] ); ?></p>
				<!-- /wp:paragraph -->
				<!-- wp:paragraph {"fontSize":"xl","className":"seo-geo-corporate-lead seo-geo-content-slot--hero-lead seo-geo-placeholder--copy"} -->
				<p class="has-xl-font-size seo-geo-corporate-lead seo-geo-content-slot--hero-lead seo-geo-placeholder--copy"><?php echo esc_html( $copy['lead'] ); ?></p>
				<!-- /wp:paragraph -->
				<!-- wp:buttons {"className":"seo-geo-corporate-actions"} -->
				<div class="wp-block-buttons seo-geo-corporate-actions">
					<!-- wp:button {"className":"seo-geo-content-slot--hero-primary-cta seo-geo-placeholder--copy"} -->
					<div class="wp-block-button seo-geo-content-slot--hero-primary-cta seo-geo-placeholder--copy"><a class="wp-block-button__link wp-element-button" href="#contacto"><?php echo esc_html( $copy['primary'] ); ?></a></div>
					<!-- /wp:button -->
					<!-- wp:button {"className":"is-style-outline seo-geo-content-slot--hero-secondary-cta seo-geo-placeholder--copy"} -->
					<div class="wp-block-button is-style-outline seo-geo-content-slot--hero-secondary-cta seo-geo-placeholder--copy"><a class="wp-block-button__link wp-element-button" href="#capacidades"><?php echo esc_html( $copy['secondary'] ); ?></a></div>
					<!-- /wp:button -->
				</div>
				<!-- /wp:buttons -->
			</div>
			<!-- /wp:group -->
		</div>
		<!-- /wp:column -->

		<!-- wp:column {"verticalAlignment":"center"} -->
		<div class="wp-block-column is-vertically-aligned-center">
			<!-- wp:image {"sizeSlug":"full","linkDestination":"none","className":"seo-geo-corporate-mockup-media seo-geo-content-slot--hero-media seo-geo-placeholder--media"} -->
			<figure class="wp-block-image size-full seo-geo-corporate-mockup-media seo-geo-content-slot--hero-media seo-geo-placeholder--media"><img src="<?php echo esc_url( $asset . 'placeholder-hero.svg' ); ?>" alt="" width="1200" height="760" decoding="async" fetchpriority="high"/></figure>
			<!-- /wp:image -->
		</div>
		<!-- /wp:column -->
	</div>
	<!-- /wp:columns -->
</section>
<!-- /wp:group -->

<!-- wp:group {"tagName":"section","align":"wide","className":"seo-geo-corporate-native-section seo-geo-corporate-native-capabilities","anchor":"capacidades","layout":{"type":"constrained"}} -->
<section id="capacidades" class="wp-block-group alignwide seo-geo-corporate-native-section seo-geo-corporate-native-capabilities">
	<!-- wp:group {"className":"seo-geo-corporate-section-heading","layout":{"type":"constrained"}} -->
	<div class="wp-block-group seo-geo-corporate-section-heading">
		<!-- wp:paragraph {"className":"seo-geo-corporate-eyebrow"} --><p class="seo-geo-corporate-eyebrow"><?php echo esc_html( $copy['cap_eyebrow'] ); ?></p><!-- /wp:paragraph -->
		<!-- wp:heading {"level":2,"className":"seo-geo-content-slot--capabilities-heading seo-geo-placeholder--copy"} --><h2 class="wp-block-heading seo-geo-content-slot--capabilities-heading seo-geo-placeholder--copy"><?php echo esc_html( $copy['cap_heading'] ); ?></h2><!-- /wp:heading -->
		<!-- wp:paragraph {"className":"seo-geo-content-slot--capabilities-intro seo-geo-placeholder--copy"} --><p class="seo-geo-content-slot--capabilities-intro seo-geo-placeholder--copy"><?php echo esc_html( $copy['cap_intro'] ); ?></p><!-- /wp:paragraph -->
	</div>
	<!-- /wp:group -->
	<!-- wp:columns {"className":"seo-geo-corporate-card-grid"} -->
	<div class="wp-block-columns seo-geo-corporate-card-grid">
		<!-- wp:column {"className":"seo-geo-corporate-card seo-geo-corporate-card--featured"} --><div class="wp-block-column seo-geo-corporate-card seo-geo-corporate-card--featured"><!-- wp:paragraph {"className":"seo-geo-corporate-card__index"} --><p class="seo-geo-corporate-card__index">01</p><!-- /wp:paragraph --><!-- wp:heading {"level":3,"className":"seo-geo-content-slot--capability-1-title seo-geo-placeholder--copy"} --><h3 class="wp-block-heading seo-geo-content-slot--capability-1-title seo-geo-placeholder--copy"><?php echo esc_html( $copy['cap1'] ); ?></h3><!-- /wp:heading --><!-- wp:paragraph {"className":"seo-geo-content-slot--capability-1-body seo-geo-placeholder--copy"} --><p class="seo-geo-content-slot--capability-1-body seo-geo-placeholder--copy"><?php echo esc_html( $copy['cap1_body'] ); ?></p><!-- /wp:paragraph --><!-- wp:paragraph {"className":"seo-geo-content-slot--capability-1-link seo-geo-placeholder--copy"} --><p class="seo-geo-content-slot--capability-1-link seo-geo-placeholder--copy"><a href="#"><?php echo esc_html( $copy['explore'] ); ?></a></p><!-- /wp:paragraph --></div><!-- /wp:column -->
		<!-- wp:column {"className":"seo-geo-corporate-card"} --><div class="wp-block-column seo-geo-corporate-card"><!-- wp:paragraph {"className":"seo-geo-corporate-card__index"} --><p class="seo-geo-corporate-card__index">02</p><!-- /wp:paragraph --><!-- wp:heading {"level":3,"className":"seo-geo-content-slot--capability-2-title seo-geo-placeholder--copy"} --><h3 class="wp-block-heading seo-geo-content-slot--capability-2-title seo-geo-placeholder--copy"><?php echo esc_html( $copy['cap2'] ); ?></h3><!-- /wp:heading --><!-- wp:paragraph {"className":"seo-geo-content-slot--capability-2-body seo-geo-placeholder--copy"} --><p class="seo-geo-content-slot--capability-2-body seo-geo-placeholder--copy"><?php echo esc_html( $copy['cap2_body'] ); ?></p><!-- /wp:paragraph --><!-- wp:paragraph {"className":"seo-geo-content-slot--capability-2-link seo-geo-placeholder--copy"} --><p class="seo-geo-content-slot--capability-2-link seo-geo-placeholder--copy"><a href="#"><?php echo esc_html( $copy['explore'] ); ?></a></p><!-- /wp:paragraph --></div><!-- /wp:column -->
		<!-- wp:column {"className":"seo-geo-corporate-card"} --><div class="wp-block-column seo-geo-corporate-card"><!-- wp:paragraph {"className":"seo-geo-corporate-card__index"} --><p class="seo-geo-corporate-card__index">03</p><!-- /wp:paragraph --><!-- wp:heading {"level":3,"className":"seo-geo-content-slot--capability-3-title seo-geo-placeholder--copy"} --><h3 class="wp-block-heading seo-geo-content-slot--capability-3-title seo-geo-placeholder--copy"><?php echo esc_html( $copy['cap3'] ); ?></h3><!-- /wp:heading --><!-- wp:paragraph {"className":"seo-geo-content-slot--capability-3-body seo-geo-placeholder--copy"} --><p class="seo-geo-content-slot--capability-3-body seo-geo-placeholder--copy"><?php echo esc_html( $copy['cap3_body'] ); ?></p><!-- /wp:paragraph --><!-- wp:paragraph {"className":"seo-geo-content-slot--capability-3-link seo-geo-placeholder--copy"} --><p class="seo-geo-content-slot--capability-3-link seo-geo-placeholder--copy"><a href="#"><?php echo esc_html( $copy['explore'] ); ?></a></p><!-- /wp:paragraph --></div><!-- /wp:column -->
	</div>
	<!-- /wp:columns -->
</section>
<!-- /wp:group -->

<!-- wp:group {"tagName":"section","align":"wide","className":"seo-geo-corporate-native-section seo-geo-corporate-featured-work","layout":{"type":"constrained"}} -->
<section class="wp-block-group alignwide seo-geo-corporate-native-section seo-geo-corporate-featured-work">
	<!-- wp:columns {"verticalAlignment":"center","className":"seo-geo-corporate-featured-work__grid"} -->
	<div class="wp-block-columns are-vertically-aligned-center seo-geo-corporate-featured-work__grid">
		<!-- wp:column {"verticalAlignment":"center","width":"58%"} --><div class="wp-block-column is-vertically-aligned-center" style="flex-basis:58%"><!-- wp:image {"sizeSlug":"full","linkDestination":"none","className":"seo-geo-corporate-mockup-media seo-geo-content-slot--case-media seo-geo-placeholder--media"} --><figure class="wp-block-image size-full seo-geo-corporate-mockup-media seo-geo-content-slot--case-media seo-geo-placeholder--media"><img src="<?php echo esc_url( $asset . 'placeholder-case.svg' ); ?>" alt="" width="1200" height="760" loading="lazy" decoding="async"/></figure><!-- /wp:image --></div><!-- /wp:column -->
		<!-- wp:column {"verticalAlignment":"center","width":"42%"} --><div class="wp-block-column is-vertically-aligned-center" style="flex-basis:42%"><!-- wp:paragraph {"className":"seo-geo-corporate-eyebrow"} --><p class="seo-geo-corporate-eyebrow"><?php echo esc_html( $copy['work_eyebrow'] ); ?></p><!-- /wp:paragraph --><!-- wp:heading {"level":2,"className":"seo-geo-content-slot--case-heading seo-geo-placeholder--copy"} --><h2 class="wp-block-heading seo-geo-content-slot--case-heading seo-geo-placeholder--copy"><?php echo esc_html( $copy['work_heading'] ); ?></h2><!-- /wp:heading --><!-- wp:paragraph {"className":"seo-geo-content-slot--case-intro seo-geo-placeholder--copy"} --><p class="seo-geo-content-slot--case-intro seo-geo-placeholder--copy"><?php echo esc_html( $copy['work_body'] ); ?></p><!-- /wp:paragraph --><!-- wp:paragraph {"className":"seo-geo-content-slot--case-link seo-geo-placeholder--copy"} --><p class="seo-geo-content-slot--case-link seo-geo-placeholder--copy"><a href="#"><?php echo esc_html( $copy['work_link'] ); ?></a></p><!-- /wp:paragraph --></div><!-- /wp:column -->
	</div>
	<!-- /wp:columns -->
</section>
<!-- /wp:group -->

<!-- wp:group {"tagName":"section","align":"full","className":"seo-geo-corporate-native-section seo-geo-corporate-native-process","layout":{"type":"constrained"}} -->
<section class="wp-block-group alignfull seo-geo-corporate-native-section seo-geo-corporate-native-process">
	<!-- wp:group {"className":"seo-geo-corporate-section-heading","layout":{"type":"constrained"}} --><div class="wp-block-group seo-geo-corporate-section-heading"><!-- wp:paragraph {"className":"seo-geo-corporate-eyebrow"} --><p class="seo-geo-corporate-eyebrow"><?php echo esc_html( $copy['process_label'] ); ?></p><!-- /wp:paragraph --><!-- wp:heading {"level":2,"className":"seo-geo-content-slot--process-heading seo-geo-placeholder--copy"} --><h2 class="wp-block-heading seo-geo-content-slot--process-heading seo-geo-placeholder--copy"><?php echo esc_html( $copy['process_title'] ); ?></h2><!-- /wp:heading --><!-- wp:paragraph {"className":"seo-geo-content-slot--process-intro seo-geo-placeholder--copy"} --><p class="seo-geo-content-slot--process-intro seo-geo-placeholder--copy"><?php echo esc_html( $copy['process_intro'] ); ?></p><!-- /wp:paragraph --></div><!-- /wp:group -->
	<!-- wp:columns {"className":"seo-geo-corporate-process-grid"} --><div class="wp-block-columns seo-geo-corporate-process-grid"><!-- wp:column {"className":"seo-geo-corporate-process-step"} --><div class="wp-block-column seo-geo-corporate-process-step"><p class="seo-geo-corporate-process-step__number">01</p><h3 class="wp-block-heading seo-geo-content-slot--process-1-title seo-geo-placeholder--copy"><?php echo esc_html( $copy['p1'] ); ?></h3><p class="seo-geo-content-slot--process-1-body seo-geo-placeholder--copy"><?php echo esc_html( $copy['p1b'] ); ?></p></div><!-- /wp:column --><!-- wp:column {"className":"seo-geo-corporate-process-step"} --><div class="wp-block-column seo-geo-corporate-process-step"><p class="seo-geo-corporate-process-step__number">02</p><h3 class="wp-block-heading seo-geo-content-slot--process-2-title seo-geo-placeholder--copy"><?php echo esc_html( $copy['p2'] ); ?></h3><p class="seo-geo-content-slot--process-2-body seo-geo-placeholder--copy"><?php echo esc_html( $copy['p2b'] ); ?></p></div><!-- /wp:column --><!-- wp:column {"className":"seo-geo-corporate-process-step"} --><div class="wp-block-column seo-geo-corporate-process-step"><p class="seo-geo-corporate-process-step__number">03</p><h3 class="wp-block-heading seo-geo-content-slot--process-3-title seo-geo-placeholder--copy"><?php echo esc_html( $copy['p3'] ); ?></h3><p class="seo-geo-content-slot--process-3-body seo-geo-placeholder--copy"><?php echo esc_html( $copy['p3b'] ); ?></p></div><!-- /wp:column --></div><!-- /wp:columns -->
</section>
<!-- /wp:group -->

<!-- wp:group {"tagName":"section","align":"wide","className":"seo-geo-corporate-native-section seo-geo-corporate-about-feature","layout":{"type":"constrained"}} -->
<section class="wp-block-group alignwide seo-geo-corporate-native-section seo-geo-corporate-about-feature">
	<!-- wp:columns {"verticalAlignment":"center","className":"seo-geo-corporate-about-feature__grid"} --><div class="wp-block-columns are-vertically-aligned-center seo-geo-corporate-about-feature__grid"><!-- wp:column {"verticalAlignment":"center"} --><div class="wp-block-column is-vertically-aligned-center"><p class="seo-geo-corporate-eyebrow"><?php echo esc_html( $copy['about_label'] ); ?></p><h2 class="wp-block-heading seo-geo-content-slot--about-heading seo-geo-placeholder--copy"><?php echo esc_html( $copy['about_title'] ); ?></h2><p class="seo-geo-content-slot--about-body seo-geo-placeholder--copy"><?php echo esc_html( $copy['about_body'] ); ?></p><p class="seo-geo-content-slot--about-link seo-geo-placeholder--copy"><a href="#"><?php echo esc_html( $copy['about_link'] ); ?></a></p></div><!-- /wp:column --><!-- wp:column {"verticalAlignment":"center"} --><div class="wp-block-column is-vertically-aligned-center"><!-- wp:image {"sizeSlug":"full","linkDestination":"none","className":"seo-geo-corporate-mockup-media seo-geo-content-slot--about-media seo-geo-placeholder--media"} --><figure class="wp-block-image size-full seo-geo-corporate-mockup-media seo-geo-content-slot--about-media seo-geo-placeholder--media"><img src="<?php echo esc_url( $asset . 'placeholder-about.svg' ); ?>" alt="" width="1200" height="760" loading="lazy" decoding="async"/></figure><!-- /wp:image --></div><!-- /wp:column --></div><!-- /wp:columns -->
</section>
<!-- /wp:group -->

<!-- wp:group {"tagName":"section","align":"wide","className":"seo-geo-corporate-native-section seo-geo-corporate-native-insights","layout":{"type":"constrained"}} -->
<section class="wp-block-group alignwide seo-geo-corporate-native-section seo-geo-corporate-native-insights">
	<!-- wp:group {"className":"seo-geo-corporate-section-heading","layout":{"type":"constrained"}} --><div class="wp-block-group seo-geo-corporate-section-heading"><p class="seo-geo-corporate-eyebrow"><?php echo esc_html( $copy['insights_label'] ); ?></p><h2 class="wp-block-heading seo-geo-content-slot--insights-heading seo-geo-placeholder--copy"><?php echo esc_html( $copy['insights_title'] ); ?></h2><p class="seo-geo-content-slot--insights-intro seo-geo-placeholder--copy"><?php echo esc_html( $copy['insights_body'] ); ?></p></div><!-- /wp:group -->
	<!-- wp:query {"query":{"perPage":3,"pages":0,"offset":0,"postType":"post","order":"desc","orderBy":"date","author":"","search":"","exclude":[],"sticky":"","inherit":false},"className":"seo-geo-corporate-insights-query"} --><div class="wp-block-query seo-geo-corporate-insights-query"><!-- wp:post-template {"layout":{"type":"grid","columnCount":3}} --><!-- wp:group {"className":"seo-geo-corporate-insight-card","layout":{"type":"constrained"}} --><div class="wp-block-group seo-geo-corporate-insight-card"><!-- wp:post-date {"fontSize":"sm"} /--><!-- wp:post-title {"isLink":true,"level":3} /--><!-- wp:post-excerpt {"showMoreOnNewLine":false,"excerptLength":24} /--></div><!-- /wp:group --><!-- /wp:post-template --><!-- wp:query-no-results --><p><?php echo esc_html( $copy['empty'] ); ?></p><!-- /wp:query-no-results --></div><!-- /wp:query -->
</section>
<!-- /wp:group -->

<!-- wp:group {"tagName":"section","align":"full","className":"seo-geo-final-cta seo-geo-corporate-final-cta","anchor":"contacto","layout":{"type":"constrained"}} -->
<section id="contacto" class="wp-block-group alignfull seo-geo-final-cta seo-geo-corporate-final-cta">
	<!-- wp:heading {"level":2,"className":"seo-geo-content-slot--final-cta-heading seo-geo-placeholder--copy"} --><h2 class="wp-block-heading seo-geo-content-slot--final-cta-heading seo-geo-placeholder--copy"><?php echo esc_html( $copy['cta_title'] ); ?></h2><!-- /wp:heading -->
	<!-- wp:paragraph {"className":"seo-geo-content-slot--final-cta-body seo-geo-placeholder--copy"} --><p class="seo-geo-content-slot--final-cta-body seo-geo-placeholder--copy"><?php echo esc_html( $copy['cta_body'] ); ?></p><!-- /wp:paragraph -->
	<!-- wp:buttons --><div class="wp-block-buttons"><!-- wp:button {"className":"seo-geo-content-slot--final-cta-button seo-geo-placeholder--copy"} --><div class="wp-block-button seo-geo-content-slot--final-cta-button seo-geo-placeholder--copy"><a class="wp-block-button__link wp-element-button" href="#"><?php echo esc_html( $copy['cta_button'] ); ?></a></div><!-- /wp:button --></div><!-- /wp:buttons -->
</section>
<!-- /wp:group -->
