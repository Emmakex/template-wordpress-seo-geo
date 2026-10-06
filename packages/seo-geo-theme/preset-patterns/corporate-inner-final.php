<?php
/**
 * Corporate final inner-page mockups owned by the Corporate preset.
 *
 * The file is intentionally included once per registered page pattern. It does
 * not declare functions or classes so repeated inclusion remains safe.
 *
 * @package SeoGeoTheme
 */

declare(strict_types=1);

$seo_geo_pattern_key = isset( $seo_geo_pattern_key ) && is_string( $seo_geo_pattern_key ) ? $seo_geo_pattern_key : '';
$seo_geo_is_es       = 'es_ES' === seo_geo_theme_preset_locale();
$seo_geo_asset       = trailingslashit( get_template_directory_uri() ) . 'assets/images/presets/corporate/';

$seo_geo_copy = array(
	'services' => $seo_geo_is_es ? array(
		'eyebrow'       => 'Servicios',
		'lead'          => 'Lorem ipsum dolor sit amet. Una página de servicios ya diseñada para explicar la oferta con claridad, jerarquía y una siguiente acción evidente.',
		'intro_label'   => 'La oferta',
		'intro_title'   => 'Tres líneas claras para convertir una necesidad en una decisión',
		'intro_body'    => 'Lorem ipsum dolor sit amet. Sustituiremos estos bloques por los servicios reales rescatados, manteniendo la composición y el orden semántico.',
		'service_1'     => 'Estrategia y dirección',
		'service_1_body'=> 'Lorem ipsum dolor sit amet. Un espacio preparado para explicar problema, alcance, entregables y resultado esperado.',
		'service_2'     => 'Diseño y construcción',
		'service_2_body'=> 'Lorem ipsum dolor sit amet. Presenta cómo se transforma la estrategia en una experiencia rápida, usable y medible.',
		'service_3'     => 'Crecimiento y mejora',
		'service_3_body'=> 'Lorem ipsum dolor sit amet. Explica cómo contenido, adquisición, automatización y datos siguen mejorando después del lanzamiento.',
		'process_label' => 'Cómo trabajamos',
		'process_title' => 'Un proceso entendible antes de empezar',
		'process_body'  => 'La estructura está cerrada. El contenido real sustituye los placeholders sin cambiar la experiencia.',
		'process_1'     => 'Descubrir',
		'process_2'     => 'Construir',
		'process_3'     => 'Optimizar',
		'cta_title'     => 'Una página de servicios debe terminar en una decisión sencilla',
		'cta_body'      => 'Lorem ipsum dolor sit amet. Aquí entrará la propuesta de siguiente paso real del cliente.',
		'cta_button'    => 'Hablar del proyecto',
	) : array(
		'eyebrow'       => 'Services',
		'lead'          => 'Lorem ipsum dolor sit amet. A finished services page designed to explain the offer with clarity, hierarchy and an obvious next action.',
		'intro_label'   => 'The offer',
		'intro_title'   => 'Three clear lines that turn a need into a decision',
		'intro_body'    => 'Lorem ipsum dolor sit amet. Real rescued services replace these blocks while the composition and semantic order stay intact.',
		'service_1'     => 'Strategy and direction',
		'service_1_body'=> 'Lorem ipsum dolor sit amet. A prepared space for the problem, scope, deliverables and expected outcome.',
		'service_2'     => 'Design and build',
		'service_2_body'=> 'Lorem ipsum dolor sit amet. Explain how strategy becomes a fast, useful and measurable experience.',
		'service_3'     => 'Growth and improvement',
		'service_3_body'=> 'Lorem ipsum dolor sit amet. Show how content, acquisition, automation and data keep improving after launch.',
		'process_label' => 'How we work',
		'process_title' => 'A process people can understand before starting',
		'process_body'  => 'The structure is final. Real content replaces placeholders without changing the experience.',
		'process_1'     => 'Discover',
		'process_2'     => 'Build',
		'process_3'     => 'Improve',
		'cta_title'     => 'A services page should end with a simple decision',
		'cta_body'      => 'Lorem ipsum dolor sit amet. The client’s real next-step proposition is hydrated here.',
		'cta_button'    => 'Discuss the project',
	),
	'work' => $seo_geo_is_es ? array(
		'eyebrow'       => 'Proyectos',
		'lead'          => 'Lorem ipsum dolor sit amet. Una vitrina editorial preparada para mostrar trabajo real sin inventar clientes, cifras ni resultados.',
		'intro_label'   => 'Trabajo seleccionado',
		'intro_title'   => 'Los casos reales merecen contexto, no una cuadrícula genérica',
		'intro_body'    => 'La imagen y los textos son provisionales. Durante la hidratación entrarán proyectos, capturas y evidencia que realmente existan.',
		'proof_label'   => 'Evidencia',
		'proof_title'   => 'Qué debe demostrar cada caso antes de publicarse',
		'proof_1'       => 'Reto verificable',
		'proof_2'       => 'Enfoque concreto',
		'proof_3'       => 'Resultado demostrable',
		'case_label'    => 'Caso destacado',
		'case_title'    => 'Una composición lista para el primer caso real',
		'case_intro'    => 'Lorem ipsum dolor sit amet. No se publicará como prueba hasta que exista procedencia verificable.',
		'challenge'     => 'Reto',
		'approach'      => 'Enfoque',
		'result'        => 'Resultado',
		'case_prompt'   => 'Sustituir por información real y revisada antes de publicar.',
		'cta_title'     => 'El portfolio debe ayudar a imaginar el siguiente proyecto',
		'cta_body'      => 'Lorem ipsum dolor sit amet. Un cierre preparado para enlazar el trabajo mostrado con una conversación real.',
		'cta_button'    => 'Plantear un proyecto',
	) : array(
		'eyebrow'       => 'Work',
		'lead'          => 'Lorem ipsum dolor sit amet. An editorial showcase prepared for real work without inventing clients, figures or outcomes.',
		'intro_label'   => 'Selected work',
		'intro_title'   => 'Real cases deserve context, not a generic grid',
		'intro_body'    => 'The image and copy are provisional. Hydration brings in projects, captures and evidence that actually exist.',
		'proof_label'   => 'Evidence',
		'proof_title'   => 'What every case should demonstrate before publication',
		'proof_1'       => 'Verifiable challenge',
		'proof_2'       => 'Concrete approach',
		'proof_3'       => 'Demonstrable outcome',
		'case_label'    => 'Featured case',
		'case_title'    => 'A composition ready for the first real case',
		'case_intro'    => 'Lorem ipsum dolor sit amet. It is not treated as proof until verifiable provenance exists.',
		'challenge'     => 'Challenge',
		'approach'      => 'Approach',
		'result'        => 'Outcome',
		'case_prompt'   => 'Replace with reviewed real information before publication.',
		'cta_title'     => 'The portfolio should make the next project easier to imagine',
		'cta_body'      => 'Lorem ipsum dolor sit amet. A close prepared to connect demonstrated work to a real conversation.',
		'cta_button'    => 'Discuss a project',
	),
	'about' => $seo_geo_is_es ? array(
		'eyebrow'       => 'La empresa',
		'lead'          => 'Lorem ipsum dolor sit amet. Una página editorial terminada para explicar historia, criterio, personas y forma de trabajar con autoridad real.',
		'story_label'   => 'Historia y criterio',
		'story_title'   => 'Una empresa se entiende mejor cuando explica por qué trabaja como trabaja',
		'story_body'    => 'Lorem ipsum dolor sit amet. Aquí entrará la historia real rescatada, reducida a lo que aporta contexto, diferenciación y confianza.',
		'author_label'  => 'Quién hay detrás',
		'author_name'   => 'Nombre real pendiente de hidratar',
		'author_role'   => 'Rol real pendiente de hidratar',
		'author_bio'    => 'Lorem ipsum dolor sit amet. Biografía breve preparada para experiencia, especialidad y señales de autoridad verificables.',
		'process_label' => 'Método',
		'process_title' => 'La forma de trabajar también es parte de la propuesta',
		'process_1'     => 'Escuchar',
		'process_2'     => 'Priorizar',
		'process_3'     => 'Evolucionar',
		'cta_title'     => 'La confianza necesita contexto y una salida clara',
		'cta_body'      => 'Lorem ipsum dolor sit amet. El siguiente paso real se hidrata aquí sin rehacer la página.',
		'cta_button'    => 'Conocer cómo podemos ayudar',
	) : array(
		'eyebrow'       => 'Company',
		'lead'          => 'Lorem ipsum dolor sit amet. A finished editorial page for story, judgement, people and ways of working backed by real authority.',
		'story_label'   => 'Story and judgement',
		'story_title'   => 'A company is easier to understand when it explains why it works the way it does',
		'story_body'    => 'Lorem ipsum dolor sit amet. The rescued real story is distilled here to context, differentiation and trust.',
		'author_label'  => 'Who is behind the work',
		'author_name'   => 'Real name pending hydration',
		'author_role'   => 'Real role pending hydration',
		'author_bio'    => 'Lorem ipsum dolor sit amet. A concise bio prepared for verifiable experience, specialism and authority signals.',
		'process_label' => 'Method',
		'process_title' => 'How the work gets done is part of the proposition',
		'process_1'     => 'Listen',
		'process_2'     => 'Prioritize',
		'process_3'     => 'Evolve',
		'cta_title'     => 'Trust needs context and a clear way forward',
		'cta_body'      => 'Lorem ipsum dolor sit amet. The real next step is hydrated here without rebuilding the page.',
		'cta_button'    => 'See how we can help',
	),
	'contact' => $seo_geo_is_es ? array(
		'eyebrow'       => 'Contacto',
		'lead'          => 'Lorem ipsum dolor sit amet. Una página de contacto que reduce fricción y deja claro qué ocurrirá después de enviar una consulta.',
		'contact_label' => 'Empezar conversación',
		'contact_title' => 'Pocas opciones, todas claras',
		'contact_body'  => 'Los canales son placeholders seguros. La hidratación incorpora email, teléfono, zona de servicio y configuración real del formulario.',
		'email_title'   => 'Email',
		'email_body'    => 'Dirección real pendiente de hidratar',
		'phone_title'   => 'Teléfono',
		'phone_body'    => 'Número real pendiente de hidratar',
		'area_title'    => 'Área de servicio',
		'area_body'     => 'Cobertura real pendiente de hidratar',
		'expect_label'  => 'Qué esperar',
		'expect_title'  => 'El contacto también necesita una experiencia diseñada',
		'expect_body'   => 'Lorem ipsum dolor sit amet. Explica tiempos de respuesta, información útil para empezar y qué sucede en la primera conversación.',
		'cta_title'     => 'Listo para conectar el canal real',
		'cta_body'      => 'El diseño está terminado. Solo faltan los datos y destino reales del cliente.',
		'cta_button'    => 'Iniciar contacto',
	) : array(
		'eyebrow'       => 'Contact',
		'lead'          => 'Lorem ipsum dolor sit amet. A contact page that reduces friction and makes clear what happens after an enquiry.',
		'contact_label' => 'Start a conversation',
		'contact_title' => 'Few options, all of them clear',
		'contact_body'  => 'Channels are safe placeholders. Hydration adds the real email, phone, service area and form configuration.',
		'email_title'   => 'Email',
		'email_body'    => 'Real address pending hydration',
		'phone_title'   => 'Phone',
		'phone_body'    => 'Real number pending hydration',
		'area_title'    => 'Service area',
		'area_body'     => 'Real coverage pending hydration',
		'expect_label'  => 'What to expect',
		'expect_title'  => 'Contact deserves a designed experience too',
		'expect_body'   => 'Lorem ipsum dolor sit amet. Explain response times, useful starting information and what happens in the first conversation.',
		'cta_title'     => 'Ready to connect the real channel',
		'cta_body'      => 'The design is finished. Only the client’s real contact data and destination remain.',
		'cta_button'    => 'Start contact',
	),
	'insights' => $seo_geo_is_es ? array(
		'eyebrow'       => 'Insights',
		'lead'          => 'Ideas, guías y análisis que demuestran experiencia, responden preguntas reales y amplían el grafo temático del sitio.',
		'feed_label'    => 'Últimas publicaciones',
		'feed_title'    => 'Contenido útil, rastreable y conectado con la oferta',
		'feed_body'     => 'El índice se alimenta de WordPress. La dirección de arte está lista aunque todavía no existan artículos.',
		'empty'         => 'Todavía no hay publicaciones. El diseño ya está preparado para recibirlas.',
		'cta_title'     => 'El contenido también debe conducir a una siguiente acción',
		'cta_body'      => 'Las publicaciones alimentarán autoridad y descubrimiento; la arquitectura ya deja preparado el siguiente paso.',
		'cta_button'    => 'Hablar del proyecto',
	) : array(
		'eyebrow'       => 'Insights',
		'lead'          => 'Ideas, guides and analysis that demonstrate expertise, answer real questions and expand the site’s topical graph.',
		'feed_label'    => 'Latest publishing',
		'feed_title'    => 'Useful, crawlable content connected to the offer',
		'feed_body'     => 'The index is powered by WordPress. The art direction is ready even before the first article exists.',
		'empty'         => 'There are no published insights yet. The design is already prepared for them.',
		'cta_title'     => 'Content should lead to a useful next action too',
		'cta_body'      => 'Publishing builds authority and discovery; the architecture already prepares the next step.',
		'cta_button'    => 'Discuss the project',
	),
);

if ( ! isset( $seo_geo_copy[ $seo_geo_pattern_key ] ) ) {
	return;
}

$seo_geo_page = $seo_geo_copy[ $seo_geo_pattern_key ];
?>
<!-- wp:group {"tagName":"section","align":"wide","className":"seo-geo-corporate-native-hero seo-geo-corporate-inner-hero","layout":{"type":"constrained"}} -->
<section class="wp-block-group alignwide seo-geo-corporate-native-hero seo-geo-corporate-inner-hero">
	<!-- wp:columns {"verticalAlignment":"center","className":"seo-geo-corporate-native-hero__grid"} -->
	<div class="wp-block-columns are-vertically-aligned-center seo-geo-corporate-native-hero__grid">
		<!-- wp:column {"verticalAlignment":"center"} --><div class="wp-block-column is-vertically-aligned-center"><!-- wp:group {"className":"seo-geo-corporate-native-hero__copy","layout":{"type":"constrained"}} --><div class="wp-block-group seo-geo-corporate-native-hero__copy"><!-- wp:paragraph {"className":"seo-geo-corporate-eyebrow"} --><p class="seo-geo-corporate-eyebrow"><?php echo esc_html( $seo_geo_page['eyebrow'] ); ?></p><!-- /wp:paragraph --><!-- wp:paragraph {"fontSize":"xl","className":"seo-geo-corporate-lead seo-geo-content-slot--page-lead seo-geo-placeholder--copy"} --><p class="has-xl-font-size seo-geo-corporate-lead seo-geo-content-slot--page-lead seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_page['lead'] ); ?></p><!-- /wp:paragraph --></div><!-- /wp:group --></div><!-- /wp:column -->
		<!-- wp:column {"verticalAlignment":"center"} --><div class="wp-block-column is-vertically-aligned-center"><!-- wp:image {"sizeSlug":"full","linkDestination":"none","className":"seo-geo-corporate-mockup-media seo-geo-content-slot--page-media seo-geo-placeholder--media"} --><figure class="wp-block-image size-full seo-geo-corporate-mockup-media seo-geo-content-slot--page-media seo-geo-placeholder--media"><img src="<?php echo esc_url( $seo_geo_asset . ( 'about' === $seo_geo_pattern_key ? 'placeholder-about.svg' : ( 'work' === $seo_geo_pattern_key ? 'placeholder-case.svg' : 'placeholder-hero.svg' ) ) ); ?>" alt="" width="1200" height="760" loading="lazy" decoding="async"/></figure><!-- /wp:image --></div><!-- /wp:column -->
	</div><!-- /wp:columns -->
</section>
<!-- /wp:group -->

<?php if ( 'services' === $seo_geo_pattern_key ) : ?>
<!-- wp:group {"tagName":"section","align":"wide","className":"seo-geo-corporate-native-section","layout":{"type":"constrained"}} -->
<section class="wp-block-group alignwide seo-geo-corporate-native-section"><div class="wp-block-group seo-geo-corporate-section-heading"><p class="seo-geo-corporate-eyebrow"><?php echo esc_html( $seo_geo_page['intro_label'] ); ?></p><h2 class="wp-block-heading seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_page['intro_title'] ); ?></h2><p class="seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_page['intro_body'] ); ?></p></div>
<div class="wp-block-columns seo-geo-corporate-card-grid"><div class="wp-block-column seo-geo-corporate-card seo-geo-corporate-card--featured"><p class="seo-geo-corporate-card__index">01</p><h3 class="wp-block-heading seo-geo-content-slot--service-1-title seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_page['service_1'] ); ?></h3><p class="seo-geo-content-slot--service-1-body seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_page['service_1_body'] ); ?></p></div><div class="wp-block-column seo-geo-corporate-card"><p class="seo-geo-corporate-card__index">02</p><h3 class="wp-block-heading seo-geo-content-slot--service-2-title seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_page['service_2'] ); ?></h3><p class="seo-geo-content-slot--service-2-body seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_page['service_2_body'] ); ?></p></div><div class="wp-block-column seo-geo-corporate-card"><p class="seo-geo-corporate-card__index">03</p><h3 class="wp-block-heading seo-geo-content-slot--service-3-title seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_page['service_3'] ); ?></h3><p class="seo-geo-content-slot--service-3-body seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_page['service_3_body'] ); ?></p></div></div></section>
<!-- /wp:group -->
<!-- wp:group {"tagName":"section","align":"full","className":"seo-geo-corporate-native-process","layout":{"type":"constrained"}} --><section class="wp-block-group alignfull seo-geo-corporate-native-process"><div class="wp-block-group seo-geo-corporate-section-heading"><p class="seo-geo-corporate-eyebrow"><?php echo esc_html( $seo_geo_page['process_label'] ); ?></p><h2 class="wp-block-heading seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_page['process_title'] ); ?></h2><p class="seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_page['process_body'] ); ?></p></div><div class="wp-block-columns seo-geo-corporate-process-grid"><div class="wp-block-column seo-geo-corporate-process-step"><p class="seo-geo-corporate-process-step__number">01</p><h3 class="wp-block-heading seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_page['process_1'] ); ?></h3></div><div class="wp-block-column seo-geo-corporate-process-step"><p class="seo-geo-corporate-process-step__number">02</p><h3 class="wp-block-heading seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_page['process_2'] ); ?></h3></div><div class="wp-block-column seo-geo-corporate-process-step"><p class="seo-geo-corporate-process-step__number">03</p><h3 class="wp-block-heading seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_page['process_3'] ); ?></h3></div></div></section><!-- /wp:group -->
<?php elseif ( 'work' === $seo_geo_pattern_key ) : ?>
<!-- wp:group {"tagName":"section","align":"wide","className":"seo-geo-corporate-native-section seo-geo-corporate-featured-work","layout":{"type":"constrained"}} --><section class="wp-block-group alignwide seo-geo-corporate-native-section seo-geo-corporate-featured-work"><div class="wp-block-columns are-vertically-aligned-center seo-geo-corporate-featured-work__grid"><div class="wp-block-column is-vertically-aligned-center" style="flex-basis:58%"><figure class="wp-block-image size-full seo-geo-corporate-mockup-media seo-geo-placeholder--media"><img src="<?php echo esc_url( $seo_geo_asset . 'placeholder-case.svg' ); ?>" alt="" width="1200" height="760" loading="lazy" decoding="async"/></figure></div><div class="wp-block-column is-vertically-aligned-center" style="flex-basis:42%"><p class="seo-geo-corporate-eyebrow"><?php echo esc_html( $seo_geo_page['intro_label'] ); ?></p><h2 class="wp-block-heading seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_page['intro_title'] ); ?></h2><p class="seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_page['intro_body'] ); ?></p></div></div></section><!-- /wp:group -->
<!-- wp:group {"tagName":"section","align":"wide","className":"seo-geo-corporate-native-section seo-geo-corporate-native-proof","layout":{"type":"constrained"}} --><section class="wp-block-group alignwide seo-geo-corporate-native-section seo-geo-corporate-native-proof"><div class="wp-block-group seo-geo-corporate-section-heading"><p class="seo-geo-corporate-eyebrow"><?php echo esc_html( $seo_geo_page['proof_label'] ); ?></p><h2 class="wp-block-heading seo-geo-content-slot--proof-heading seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_page['proof_title'] ); ?></h2></div><div class="wp-block-columns seo-geo-corporate-card-grid"><div class="wp-block-column seo-geo-corporate-card"><p class="seo-geo-corporate-card__index">01</p><h3 class="wp-block-heading seo-geo-content-slot--proof-1-title seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_page['proof_1'] ); ?></h3></div><div class="wp-block-column seo-geo-corporate-card"><p class="seo-geo-corporate-card__index">02</p><h3 class="wp-block-heading seo-geo-content-slot--proof-2-title seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_page['proof_2'] ); ?></h3></div><div class="wp-block-column seo-geo-corporate-card"><p class="seo-geo-corporate-card__index">03</p><h3 class="wp-block-heading seo-geo-content-slot--proof-3-title seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_page['proof_3'] ); ?></h3></div></div></section><!-- /wp:group -->
<!-- wp:group {"tagName":"section","align":"full","className":"seo-geo-corporate-native-process seo-geo-corporate-case-study","layout":{"type":"constrained"}} --><section class="wp-block-group alignfull seo-geo-corporate-native-process seo-geo-corporate-case-study"><p class="seo-geo-corporate-eyebrow"><?php echo esc_html( $seo_geo_page['case_label'] ); ?></p><h2 class="wp-block-heading seo-geo-content-slot--case-heading seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_page['case_title'] ); ?></h2><p class="seo-geo-content-slot--case-intro seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_page['case_intro'] ); ?></p><div class="wp-block-columns seo-geo-corporate-process-grid"><div class="wp-block-column seo-geo-corporate-process-step"><h3 class="wp-block-heading"><?php echo esc_html( $seo_geo_page['challenge'] ); ?></h3><p class="seo-geo-content-slot--case-challenge-body seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_page['case_prompt'] ); ?></p></div><div class="wp-block-column seo-geo-corporate-process-step"><h3 class="wp-block-heading"><?php echo esc_html( $seo_geo_page['approach'] ); ?></h3><p class="seo-geo-content-slot--case-approach-body seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_page['case_prompt'] ); ?></p></div><div class="wp-block-column seo-geo-corporate-process-step"><h3 class="wp-block-heading"><?php echo esc_html( $seo_geo_page['result'] ); ?></h3><p class="seo-geo-content-slot--case-result-body seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_page['case_prompt'] ); ?></p></div></div></section><!-- /wp:group -->
<?php elseif ( 'about' === $seo_geo_pattern_key ) : ?>
<!-- wp:group {"tagName":"section","align":"wide","className":"seo-geo-corporate-native-section seo-geo-corporate-about-feature","layout":{"type":"constrained"}} --><section class="wp-block-group alignwide seo-geo-corporate-native-section seo-geo-corporate-about-feature"><div class="wp-block-columns are-vertically-aligned-center seo-geo-corporate-about-feature__grid"><div class="wp-block-column is-vertically-aligned-center"><p class="seo-geo-corporate-eyebrow"><?php echo esc_html( $seo_geo_page['story_label'] ); ?></p><h2 class="wp-block-heading seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_page['story_title'] ); ?></h2><p class="seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_page['story_body'] ); ?></p></div><div class="wp-block-column is-vertically-aligned-center"><figure class="wp-block-image size-full seo-geo-corporate-mockup-media seo-geo-placeholder--media"><img src="<?php echo esc_url( $seo_geo_asset . 'placeholder-about.svg' ); ?>" alt="" width="1200" height="760" loading="lazy" decoding="async"/></figure></div></div></section><!-- /wp:group -->
<!-- wp:group {"tagName":"section","align":"wide","className":"seo-geo-corporate-native-section","layout":{"type":"constrained"}} --><section class="wp-block-group alignwide seo-geo-corporate-native-section"><div class="wp-block-group seo-geo-corporate-section-heading"><p class="seo-geo-corporate-eyebrow"><?php echo esc_html( $seo_geo_page['author_label'] ); ?></p><h2 class="wp-block-heading seo-geo-content-slot--author-name seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_page['author_name'] ); ?></h2><p class="seo-geo-content-slot--author-role seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_page['author_role'] ); ?></p></div><div class="wp-block-columns"><div class="wp-block-column"><p class="seo-geo-content-slot--author-bio seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_page['author_bio'] ); ?></p></div></div></section><!-- /wp:group -->
<!-- wp:group {"tagName":"section","align":"full","className":"seo-geo-corporate-native-process","layout":{"type":"constrained"}} --><section class="wp-block-group alignfull seo-geo-corporate-native-process"><p class="seo-geo-corporate-eyebrow"><?php echo esc_html( $seo_geo_page['process_label'] ); ?></p><h2 class="wp-block-heading seo-geo-content-slot--process-heading seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_page['process_title'] ); ?></h2><div class="wp-block-columns seo-geo-corporate-process-grid"><div class="wp-block-column seo-geo-corporate-process-step"><p class="seo-geo-corporate-process-step__number">01</p><h3 class="wp-block-heading seo-geo-content-slot--process-1-title seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_page['process_1'] ); ?></h3></div><div class="wp-block-column seo-geo-corporate-process-step"><p class="seo-geo-corporate-process-step__number">02</p><h3 class="wp-block-heading seo-geo-content-slot--process-2-title seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_page['process_2'] ); ?></h3></div><div class="wp-block-column seo-geo-corporate-process-step"><p class="seo-geo-corporate-process-step__number">03</p><h3 class="wp-block-heading seo-geo-content-slot--process-3-title seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_page['process_3'] ); ?></h3></div></div></section><!-- /wp:group -->
<?php elseif ( 'contact' === $seo_geo_pattern_key ) : ?>
<!-- wp:group {"tagName":"section","align":"wide","className":"seo-geo-corporate-native-section","layout":{"type":"constrained"}} --><section class="wp-block-group alignwide seo-geo-corporate-native-section"><div class="wp-block-group seo-geo-corporate-section-heading"><p class="seo-geo-corporate-eyebrow"><?php echo esc_html( $seo_geo_page['contact_label'] ); ?></p><h2 class="wp-block-heading seo-geo-content-slot--contact-heading seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_page['contact_title'] ); ?></h2><p class="seo-geo-content-slot--contact-intro seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_page['contact_body'] ); ?></p></div><div class="wp-block-columns seo-geo-corporate-card-grid"><div class="wp-block-column seo-geo-corporate-card seo-geo-corporate-card--featured"><p class="seo-geo-corporate-card__index">01</p><h3 class="wp-block-heading"><?php echo esc_html( $seo_geo_page['email_title'] ); ?></h3><p class="seo-geo-content-slot--contact-email seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_page['email_body'] ); ?></p></div><div class="wp-block-column seo-geo-corporate-card"><p class="seo-geo-corporate-card__index">02</p><h3 class="wp-block-heading"><?php echo esc_html( $seo_geo_page['phone_title'] ); ?></h3><p class="seo-geo-content-slot--contact-phone seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_page['phone_body'] ); ?></p></div><div class="wp-block-column seo-geo-corporate-card"><p class="seo-geo-corporate-card__index">03</p><h3 class="wp-block-heading"><?php echo esc_html( $seo_geo_page['area_title'] ); ?></h3><p class="seo-geo-content-slot--contact-area seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_page['area_body'] ); ?></p></div></div></section><!-- /wp:group -->
<!-- wp:group {"tagName":"section","align":"wide","className":"seo-geo-corporate-native-section seo-geo-corporate-about-feature","layout":{"type":"constrained"}} --><section class="wp-block-group alignwide seo-geo-corporate-native-section seo-geo-corporate-about-feature"><div class="wp-block-columns are-vertically-aligned-center seo-geo-corporate-about-feature__grid"><div class="wp-block-column is-vertically-aligned-center"><p class="seo-geo-corporate-eyebrow"><?php echo esc_html( $seo_geo_page['expect_label'] ); ?></p><h2 class="wp-block-heading seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_page['expect_title'] ); ?></h2><p class="seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_page['expect_body'] ); ?></p></div><div class="wp-block-column is-vertically-aligned-center"><figure class="wp-block-image size-full seo-geo-corporate-mockup-media seo-geo-placeholder--media"><img src="<?php echo esc_url( $seo_geo_asset . 'placeholder-about.svg' ); ?>" alt="" width="1200" height="760" loading="lazy" decoding="async"/></figure></div></div></section><!-- /wp:group -->
<?php elseif ( 'insights' === $seo_geo_pattern_key ) : ?>
<!-- wp:group {"tagName":"section","align":"wide","className":"seo-geo-corporate-native-section","layout":{"type":"constrained"}} --><section class="wp-block-group alignwide seo-geo-corporate-native-section"><div class="wp-block-group seo-geo-corporate-section-heading"><p class="seo-geo-corporate-eyebrow"><?php echo esc_html( $seo_geo_page['feed_label'] ); ?></p><h2 class="wp-block-heading"><?php echo esc_html( $seo_geo_page['feed_title'] ); ?></h2><p><?php echo esc_html( $seo_geo_page['feed_body'] ); ?></p></div><!-- wp:query {"query":{"perPage":6,"pages":0,"offset":0,"postType":"post","order":"desc","orderBy":"date","author":"","search":"","exclude":[],"sticky":"","inherit":false},"align":"wide"} --><div class="wp-block-query alignwide"><!-- wp:post-template {"layout":{"type":"grid","columnCount":3}} --><!-- wp:group {"className":"seo-geo-corporate-card","layout":{"type":"constrained"}} --><div class="wp-block-group seo-geo-corporate-card"><!-- wp:post-featured-image {"isLink":true,"aspectRatio":"16/9"} /--><!-- wp:post-date {"fontSize":"sm"} /--><!-- wp:post-title {"isLink":true,"level":3} /--><!-- wp:post-excerpt {"moreText":""} /--></div><!-- /wp:group --><!-- /wp:post-template --><!-- wp:query-no-results --><p><?php echo esc_html( $seo_geo_page['empty'] ); ?></p><!-- /wp:query-no-results --><!-- wp:query-pagination {"layout":{"type":"flex","justifyContent":"space-between"}} --><!-- wp:query-pagination-previous /--><!-- wp:query-pagination-numbers /--><!-- wp:query-pagination-next /--><!-- /wp:query-pagination --></div><!-- /wp:query --></section><!-- /wp:group -->
<?php endif; ?>

<!-- wp:group {"tagName":"section","align":"full","className":"seo-geo-final-cta","layout":{"type":"constrained"}} -->
<section class="wp-block-group alignfull seo-geo-final-cta"><h2 class="wp-block-heading seo-geo-content-slot--final-cta-heading seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_page['cta_title'] ); ?></h2><p class="seo-geo-content-slot--final-cta-body seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_page['cta_body'] ); ?></p><div class="wp-block-buttons"><div class="wp-block-button seo-geo-content-slot--final-cta-button seo-geo-placeholder--copy"><a class="wp-block-button__link wp-element-button" href="#contacto"><?php echo esc_html( $seo_geo_page['cta_button'] ); ?></a></div></div></section>
<!-- /wp:group -->
