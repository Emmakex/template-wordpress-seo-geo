<?php
/**
 * SaaS / Digital Product final Home mockup.
 *
 * The page-level H1 remains owned by front-page.html. This composition starts
 * below that title and supplies the finished product-facing information system.
 *
 * @package SeoGeoTheme
 */

declare(strict_types=1);

$seo_geo_saas_is_es = 'es_ES' === seo_geo_theme_preset_locale();
$seo_geo_saas_copy  = $seo_geo_saas_is_es ? array(
	'kicker'             => 'Producto digital · flujo · resultados verificables',
	'lead'               => 'Una propuesta de producto clara, con un flujo visual preparado para explicar qué hace, para quién sirve y cuál es la siguiente acción.',
	'primary'            => 'Solicitar una demo',
	'secondary'          => 'Ver cómo funciona',
	'preview_label'      => 'Vista previa conceptual',
	'preview_note'       => 'Este visual es provisional y no representa una interfaz ni métricas reales. Se sustituye durante la hidratación cuando exista material verificable.',
	'value_label'        => 'Qué cambia',
	'value_title'        => 'El producto se entiende antes de pedir una demo',
	'value_intro'        => 'La arquitectura ya está terminada. El contenido real sustituye estas guías sin cambiar la jerarquía, el responsive ni el sistema visual.',
	'problem'            => 'Problema',
	'problem_body'       => 'Explica la fricción concreta que hoy consume tiempo, coordinación o visibilidad al usuario.',
	'workflow'           => 'Flujo del producto',
	'workflow_body'      => 'Describe cómo entra la información, qué hace el producto y dónde recibe valor el usuario.',
	'outcome'            => 'Resultado observable',
	'outcome_body'       => 'Añade únicamente mejoras o resultados que puedan verificarse con datos, producto o evidencia real.',
	'flow_label'         => 'Cómo funciona',
	'flow_title'         => 'Un recorrido simple de principio a fin',
	'flow_intro'         => 'Tres pasos bastan para explicar el núcleo del producto sin convertir la Home en documentación técnica.',
	'step_1'             => 'Conectar',
	'step_1_body'        => 'Define la entrada real: datos, formulario, integración, evento o tarea que inicia el flujo.',
	'step_2'             => 'Organizar',
	'step_2_body'        => 'Explica el procesamiento o coordinación principal sin atribuir capacidades que el producto no tenga.',
	'step_3'             => 'Actuar',
	'step_3_body'        => 'Muestra la salida útil: decisión, automatización, seguimiento, entrega o siguiente acción.',
	'integration_label'  => 'Integraciones y confianza',
	'integration_title'  => 'La conectividad se muestra solo cuando existe',
	'integration_body'   => 'Este espacio está preparado para integraciones, seguridad y límites operativos reales. No se generan logos, certificaciones ni compatibilidades ficticias.',
	'integration_empty'  => 'Añade aquí integraciones o señales de confianza verificadas durante la hidratación.',
	'cases_label'        => 'Casos de uso',
	'cases_title'        => 'Una misma plataforma, tres contextos claros',
	'case_1'             => 'Para un equipo operativo',
	'case_1_body'        => 'Describe la tarea real, el desencadenante y la mejora de flujo que sí soporta el producto.',
	'case_2'             => 'Para responsables de negocio',
	'case_2_body'        => 'Explica qué visibilidad, control o coordinación aporta sin inventar porcentajes ni resultados.',
	'case_3'             => 'Para un flujo especializado',
	'case_3_body'        => 'Reserva este bloque para un escenario vertical o avanzado que pueda demostrarse.',
	'plans_label'        => 'Planes',
	'plans_title'        => 'Preparado para precios reales, nunca inferidos',
	'plans_body'         => 'La estructura comercial puede mostrar planes, límites y CTA cuando esos datos sean oficiales. Hasta entonces permanece como contenido provisional no publicable.',
	'plan_1'             => 'Plan principal',
	'plan_1_body'        => 'Nombre, alcance, límites y precio verificados.',
	'plan_2'             => 'Plan avanzado',
	'plan_2_body'        => 'Diferencias reales y criterio claro para elegirlo.',
	'faq_label'          => 'Preguntas frecuentes',
	'faq_title'          => 'Resuelve objeciones sin esconder la información importante',
	'faq_1'              => '¿Qué problema resuelve el producto?',
	'faq_1_body'         => 'Sustituye esta guía por una respuesta breve basada en el caso de uso y alcance reales.',
	'faq_2'              => '¿Cómo se integra con otros sistemas?',
	'faq_2_body'         => 'Indica únicamente conexiones soportadas, requisitos y límites operativos reales.',
	'faq_3'              => '¿Cómo se contrata o prueba?',
	'faq_3_body'         => 'Explica el proceso comercial real, la demo, prueba o alta cuando esté definido.',
	'cta_title'          => 'Convierte la comprensión en la siguiente acción',
	'cta_body'           => 'Cierra con una sola acción principal y el contexto suficiente para saber qué ocurrirá después.',
	'cta_button'         => 'Solicitar una demo',
) : array(
	'kicker'             => 'Digital product · workflow · verifiable outcomes',
	'lead'               => 'A clear product proposition with a visual flow ready to explain what it does, who it serves and what the next action should be.',
	'primary'            => 'Request a demo',
	'secondary'          => 'See how it works',
	'preview_label'      => 'Concept preview',
	'preview_note'       => 'This visual is provisional and does not represent a real interface or real metrics. Hydration replaces it when verified product material exists.',
	'value_label'        => 'What changes',
	'value_title'        => 'Understand the product before requesting a demo',
	'value_intro'        => 'The architecture is already finished. Real content replaces these guides without changing hierarchy, responsive behavior or the visual system.',
	'problem'            => 'Problem',
	'problem_body'       => 'Explain the concrete friction that currently consumes time, coordination or visibility for the user.',
	'workflow'           => 'Product workflow',
	'workflow_body'      => 'Describe how information enters, what the product does and where the user receives value.',
	'outcome'            => 'Observable outcome',
	'outcome_body'       => 'Add only improvements or outcomes that can be verified with real data, product behavior or evidence.',
	'flow_label'         => 'How it works',
	'flow_title'         => 'A simple journey from start to finish',
	'flow_intro'         => 'Three steps are enough to explain the product core without turning the Home into technical documentation.',
	'step_1'             => 'Connect',
	'step_1_body'        => 'Define the real input: data, form, integration, event or task that starts the flow.',
	'step_2'             => 'Organize',
	'step_2_body'        => 'Explain the main processing or coordination step without attributing unsupported capabilities.',
	'step_3'             => 'Act',
	'step_3_body'        => 'Show the useful output: decision, automation, follow-up, delivery or next action.',
	'integration_label'  => 'Integrations and trust',
	'integration_title'  => 'Connectivity appears only when it exists',
	'integration_body'   => 'This area is prepared for real integrations, security and operational boundaries. Logos, certifications and compatibility are never fabricated.',
	'integration_empty'  => 'Add verified integrations or trust signals here during hydration.',
	'cases_label'        => 'Use cases',
	'cases_title'        => 'One platform, three clear contexts',
	'case_1'             => 'For an operations team',
	'case_1_body'        => 'Describe the real job, trigger and workflow improvement that the product actually supports.',
	'case_2'             => 'For business owners',
	'case_2_body'        => 'Explain the visibility, control or coordination it provides without inventing percentages or outcomes.',
	'case_3'             => 'For a specialized workflow',
	'case_3_body'        => 'Reserve this block for a vertical or advanced scenario that can be demonstrated.',
	'plans_label'        => 'Plans',
	'plans_title'        => 'Ready for real pricing, never inferred pricing',
	'plans_body'         => 'The commercial structure can show plans, limits and CTAs when those facts are official. Until then it remains provisional, non-publishable content.',
	'plan_1'             => 'Primary plan',
	'plan_1_body'        => 'Verified name, scope, limits and price.',
	'plan_2'             => 'Advanced plan',
	'plan_2_body'        => 'Real differences and a clear reason to choose it.',
	'faq_label'          => 'Frequently asked questions',
	'faq_title'          => 'Resolve objections without hiding important information',
	'faq_1'              => 'What problem does the product solve?',
	'faq_1_body'         => 'Replace this guide with a concise answer based on the real use case and scope.',
	'faq_2'              => 'How does it connect with other systems?',
	'faq_2_body'         => 'State only supported connections, requirements and real operational boundaries.',
	'faq_3'              => 'How can someone buy or try it?',
	'faq_3_body'         => 'Explain the real commercial flow, demo, trial or onboarding process when defined.',
	'cta_title'          => 'Turn understanding into the next action',
	'cta_body'           => 'Close with one primary action and enough context to know what happens next.',
	'cta_button'         => 'Request a demo',
);
?>
<!-- wp:group {"tagName":"section","align":"wide","className":"seo-geo-saas-home seo-geo-saas-hero seo-geo-layout-shell","layout":{"type":"constrained"}} -->
<section class="wp-block-group alignwide seo-geo-saas-home seo-geo-saas-hero seo-geo-layout-shell">
	<!-- wp:columns {"verticalAlignment":"center","className":"seo-geo-saas-hero__grid"} -->
	<div class="wp-block-columns are-vertically-aligned-center seo-geo-saas-hero__grid">
		<!-- wp:column {"verticalAlignment":"center"} -->
		<div class="wp-block-column is-vertically-aligned-center"><!-- wp:group {"className":"seo-geo-saas-hero__copy seo-geo-safe-copy","layout":{"type":"constrained"}} --><div class="wp-block-group seo-geo-saas-hero__copy seo-geo-safe-copy">
			<!-- wp:paragraph {"className":"seo-geo-saas-kicker seo-geo-content-slot--hero-eyebrow seo-geo-placeholder--copy"} --><p class="seo-geo-saas-kicker seo-geo-content-slot--hero-eyebrow seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_saas_copy['kicker'] ); ?></p><!-- /wp:paragraph -->
			<!-- wp:paragraph {"className":"seo-geo-saas-lead seo-geo-content-slot--hero-lead seo-geo-placeholder--copy"} --><p class="seo-geo-saas-lead seo-geo-content-slot--hero-lead seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_saas_copy['lead'] ); ?></p><!-- /wp:paragraph -->
			<!-- wp:buttons {"className":"seo-geo-saas-actions"} --><div class="wp-block-buttons seo-geo-saas-actions"><!-- wp:button {"className":"seo-geo-content-slot--hero-primary-cta seo-geo-placeholder--copy"} --><div class="wp-block-button seo-geo-content-slot--hero-primary-cta seo-geo-placeholder--copy"><a class="wp-block-button__link wp-element-button" href="#contact-demo"><?php echo esc_html( $seo_geo_saas_copy['primary'] ); ?></a></div><!-- /wp:button --><!-- wp:button {"className":"is-style-outline seo-geo-content-slot--hero-secondary-cta seo-geo-placeholder--copy"} --><div class="wp-block-button is-style-outline seo-geo-content-slot--hero-secondary-cta seo-geo-placeholder--copy"><a class="wp-block-button__link wp-element-button" href="#como-funciona"><?php echo esc_html( $seo_geo_saas_copy['secondary'] ); ?></a></div><!-- /wp:button --></div><!-- /wp:buttons -->
		</div><!-- /wp:group --></div>
		<!-- /wp:column -->
		<!-- wp:column {"verticalAlignment":"center"} -->
		<div class="wp-block-column is-vertically-aligned-center"><!-- wp:group {"className":"seo-geo-saas-product-frame seo-geo-content-slot--product-visual seo-geo-placeholder--media","layout":{"type":"default"}} --><div class="wp-block-group seo-geo-saas-product-frame seo-geo-content-slot--product-visual seo-geo-placeholder--media">
			<!-- wp:group {"className":"seo-geo-saas-product-frame__rail","layout":{"type":"default"}} --><div class="wp-block-group seo-geo-saas-product-frame__rail"></div><!-- /wp:group -->
			<!-- wp:group {"className":"seo-geo-saas-product-frame__canvas","layout":{"type":"default"}} --><div class="wp-block-group seo-geo-saas-product-frame__canvas"><!-- wp:group {"className":"seo-geo-saas-product-frame__panel","layout":{"type":"default"}} --><div class="wp-block-group seo-geo-saas-product-frame__panel"></div><!-- /wp:group --><!-- wp:group {"className":"seo-geo-saas-product-frame__metrics","layout":{"type":"default"}} --><div class="wp-block-group seo-geo-saas-product-frame__metrics"><!-- wp:group {"className":"seo-geo-saas-product-frame__metric","layout":{"type":"default"}} --><div class="wp-block-group seo-geo-saas-product-frame__metric"></div><!-- /wp:group --><!-- wp:group {"className":"seo-geo-saas-product-frame__metric","layout":{"type":"default"}} --><div class="wp-block-group seo-geo-saas-product-frame__metric"></div><!-- /wp:group --></div><!-- /wp:group --></div><!-- /wp:group -->
		</div><!-- /wp:group --><!-- wp:paragraph {"className":"seo-geo-saas-kicker"} --><p class="seo-geo-saas-kicker"><?php echo esc_html( $seo_geo_saas_copy['preview_label'] ); ?></p><!-- /wp:paragraph --><!-- wp:paragraph {"fontSize":"xs","className":"seo-geo-placeholder--copy"} --><p class="has-xs-font-size seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_saas_copy['preview_note'] ); ?></p><!-- /wp:paragraph --></div>
		<!-- /wp:column -->
	</div>
	<!-- /wp:columns -->
</section>
<!-- /wp:group -->

<!-- wp:group {"tagName":"section","align":"full","className":"seo-geo-saas-section seo-geo-saas-section--surface","layout":{"type":"constrained"}} -->
<section class="wp-block-group alignfull seo-geo-saas-section seo-geo-saas-section--surface"><!-- wp:group {"className":"seo-geo-layout-shell","layout":{"type":"constrained"}} --><div class="wp-block-group seo-geo-layout-shell">
	<!-- wp:group {"className":"seo-geo-saas-section__heading seo-geo-safe-copy","layout":{"type":"constrained"}} --><div class="wp-block-group seo-geo-saas-section__heading seo-geo-safe-copy"><!-- wp:paragraph {"className":"seo-geo-saas-kicker"} --><p class="seo-geo-saas-kicker"><?php echo esc_html( $seo_geo_saas_copy['value_label'] ); ?></p><!-- /wp:paragraph --><!-- wp:heading {"level":2,"className":"seo-geo-content-slot--value-heading seo-geo-placeholder--copy"} --><h2 class="wp-block-heading seo-geo-content-slot--value-heading seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_saas_copy['value_title'] ); ?></h2><!-- /wp:heading --><!-- wp:paragraph {"className":"seo-geo-content-slot--value-intro seo-geo-placeholder--copy"} --><p class="seo-geo-content-slot--value-intro seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_saas_copy['value_intro'] ); ?></p><!-- /wp:paragraph --></div><!-- /wp:group -->
	<!-- wp:group {"className":"seo-geo-bento","layout":{"type":"default"}} --><div class="wp-block-group seo-geo-bento"><!-- wp:group {"className":"seo-geo-saas-card seo-geo-saas-card--primary seo-geo-surface-card","layout":{"type":"constrained"}} --><div class="wp-block-group seo-geo-saas-card seo-geo-saas-card--primary seo-geo-surface-card"><p class="seo-geo-saas-card__label">01</p><h3 class="wp-block-heading seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_saas_copy['problem'] ); ?></h3><p class="seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_saas_copy['problem_body'] ); ?></p></div><!-- /wp:group --><!-- wp:group {"className":"seo-geo-saas-card seo-geo-surface-card","layout":{"type":"constrained"}} --><div class="wp-block-group seo-geo-saas-card seo-geo-surface-card"><p class="seo-geo-saas-card__label">02</p><h3 class="wp-block-heading seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_saas_copy['workflow'] ); ?></h3><p class="seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_saas_copy['workflow_body'] ); ?></p></div><!-- /wp:group --><!-- wp:group {"className":"seo-geo-saas-card seo-geo-surface-card","layout":{"type":"constrained"}} --><div class="wp-block-group seo-geo-saas-card seo-geo-surface-card"><p class="seo-geo-saas-card__label">03</p><h3 class="wp-block-heading seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_saas_copy['outcome'] ); ?></h3><p class="seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_saas_copy['outcome_body'] ); ?></p></div><!-- /wp:group --></div><!-- /wp:group -->
</div><!-- /wp:group --></section>
<!-- /wp:group -->

<!-- wp:group {"tagName":"section","align":"wide","anchor":"como-funciona","className":"seo-geo-saas-section seo-geo-layout-shell","layout":{"type":"constrained"}} -->
<section id="como-funciona" class="wp-block-group alignwide seo-geo-saas-section seo-geo-layout-shell"><!-- wp:group {"className":"seo-geo-saas-section__heading","layout":{"type":"constrained"}} --><div class="wp-block-group seo-geo-saas-section__heading"><p class="seo-geo-saas-kicker"><?php echo esc_html( $seo_geo_saas_copy['flow_label'] ); ?></p><h2 class="wp-block-heading seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_saas_copy['flow_title'] ); ?></h2><p class="seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_saas_copy['flow_intro'] ); ?></p></div><!-- /wp:group --><!-- wp:group {"className":"seo-geo-process-sequence seo-geo-saas-flow","layout":{"type":"default"}} --><div class="wp-block-group seo-geo-process-sequence seo-geo-saas-flow"><!-- wp:group {"layout":{"type":"constrained"}} --><div class="wp-block-group"><h3 class="wp-block-heading seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_saas_copy['step_1'] ); ?></h3><p class="seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_saas_copy['step_1_body'] ); ?></p></div><!-- /wp:group --><!-- wp:group {"layout":{"type":"constrained"}} --><div class="wp-block-group"><h3 class="wp-block-heading seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_saas_copy['step_2'] ); ?></h3><p class="seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_saas_copy['step_2_body'] ); ?></p></div><!-- /wp:group --><!-- wp:group {"layout":{"type":"constrained"}} --><div class="wp-block-group"><h3 class="wp-block-heading seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_saas_copy['step_3'] ); ?></h3><p class="seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_saas_copy['step_3_body'] ); ?></p></div><!-- /wp:group --></div><!-- /wp:group --></section>
<!-- /wp:group -->

<!-- wp:group {"tagName":"section","align":"full","className":"seo-geo-saas-section seo-geo-saas-section--surface","layout":{"type":"constrained"}} -->
<section class="wp-block-group alignfull seo-geo-saas-section seo-geo-saas-section--surface"><!-- wp:columns {"verticalAlignment":"center","className":"seo-geo-layout-shell seo-geo-saas-split"} --><div class="wp-block-columns are-vertically-aligned-center seo-geo-layout-shell seo-geo-saas-split"><!-- wp:column {"verticalAlignment":"center"} --><div class="wp-block-column is-vertically-aligned-center"><p class="seo-geo-saas-kicker"><?php echo esc_html( $seo_geo_saas_copy['integration_label'] ); ?></p><h2 class="wp-block-heading seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_saas_copy['integration_title'] ); ?></h2><p class="seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_saas_copy['integration_body'] ); ?></p></div><!-- /wp:column --><!-- wp:column {"verticalAlignment":"center"} --><div class="wp-block-column is-vertically-aligned-center"><!-- wp:group {"className":"seo-geo-saas-proof-placeholder seo-geo-placeholder--copy","layout":{"type":"constrained"}} --><div class="wp-block-group seo-geo-saas-proof-placeholder seo-geo-placeholder--copy"><p><?php echo esc_html( $seo_geo_saas_copy['integration_empty'] ); ?></p></div><!-- /wp:group --></div><!-- /wp:column --></div><!-- /wp:columns --></section>
<!-- /wp:group -->

<!-- wp:group {"tagName":"section","align":"wide","className":"seo-geo-saas-section seo-geo-layout-shell","layout":{"type":"constrained"}} -->
<section class="wp-block-group alignwide seo-geo-saas-section seo-geo-layout-shell"><!-- wp:group {"className":"seo-geo-saas-section__heading","layout":{"type":"constrained"}} --><div class="wp-block-group seo-geo-saas-section__heading"><p class="seo-geo-saas-kicker"><?php echo esc_html( $seo_geo_saas_copy['cases_label'] ); ?></p><h2 class="wp-block-heading seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_saas_copy['cases_title'] ); ?></h2></div><!-- /wp:group --><!-- wp:group {"className":"seo-geo-saas-use-cases","layout":{"type":"default"}} --><div class="wp-block-group seo-geo-saas-use-cases"><!-- wp:group {"className":"seo-geo-saas-card seo-geo-surface-card","layout":{"type":"constrained"}} --><div class="wp-block-group seo-geo-saas-card seo-geo-surface-card"><h3 class="wp-block-heading seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_saas_copy['case_1'] ); ?></h3><p class="seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_saas_copy['case_1_body'] ); ?></p></div><!-- /wp:group --><!-- wp:group {"className":"seo-geo-saas-card seo-geo-surface-card","layout":{"type":"constrained"}} --><div class="wp-block-group seo-geo-saas-card seo-geo-surface-card"><h3 class="wp-block-heading seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_saas_copy['case_2'] ); ?></h3><p class="seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_saas_copy['case_2_body'] ); ?></p></div><!-- /wp:group --><!-- wp:group {"className":"seo-geo-saas-card seo-geo-surface-card","layout":{"type":"constrained"}} --><div class="wp-block-group seo-geo-saas-card seo-geo-surface-card"><h3 class="wp-block-heading seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_saas_copy['case_3'] ); ?></h3><p class="seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_saas_copy['case_3_body'] ); ?></p></div><!-- /wp:group --></div><!-- /wp:group --></section>
<!-- /wp:group -->

<!-- wp:group {"tagName":"section","align":"full","className":"seo-geo-saas-section seo-geo-saas-section--surface","layout":{"type":"constrained"}} -->
<section class="wp-block-group alignfull seo-geo-saas-section seo-geo-saas-section--surface"><!-- wp:group {"className":"seo-geo-layout-shell","layout":{"type":"constrained"}} --><div class="wp-block-group seo-geo-layout-shell"><!-- wp:group {"className":"seo-geo-saas-section__heading","layout":{"type":"constrained"}} --><div class="wp-block-group seo-geo-saas-section__heading"><p class="seo-geo-saas-kicker"><?php echo esc_html( $seo_geo_saas_copy['plans_label'] ); ?></p><h2 class="wp-block-heading seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_saas_copy['plans_title'] ); ?></h2><p class="seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_saas_copy['plans_body'] ); ?></p></div><!-- /wp:group --><!-- wp:columns {"className":"seo-geo-saas-split"} --><div class="wp-block-columns seo-geo-saas-split"><!-- wp:column {"className":"seo-geo-saas-card seo-geo-surface-card seo-geo-placeholder--copy"} --><div class="wp-block-column seo-geo-saas-card seo-geo-surface-card seo-geo-placeholder--copy"><h3 class="wp-block-heading"><?php echo esc_html( $seo_geo_saas_copy['plan_1'] ); ?></h3><p><?php echo esc_html( $seo_geo_saas_copy['plan_1_body'] ); ?></p></div><!-- /wp:column --><!-- wp:column {"className":"seo-geo-saas-card seo-geo-surface-card seo-geo-placeholder--copy"} --><div class="wp-block-column seo-geo-saas-card seo-geo-surface-card seo-geo-placeholder--copy"><h3 class="wp-block-heading"><?php echo esc_html( $seo_geo_saas_copy['plan_2'] ); ?></h3><p><?php echo esc_html( $seo_geo_saas_copy['plan_2_body'] ); ?></p></div><!-- /wp:column --></div><!-- /wp:columns --></div><!-- /wp:group --></section>
<!-- /wp:group -->

<!-- wp:group {"tagName":"section","align":"wide","className":"seo-geo-saas-section seo-geo-layout-shell seo-geo-saas-faq","layout":{"type":"constrained"}} -->
<section class="wp-block-group alignwide seo-geo-saas-section seo-geo-layout-shell seo-geo-saas-faq"><p class="seo-geo-saas-kicker"><?php echo esc_html( $seo_geo_saas_copy['faq_label'] ); ?></p><h2 class="wp-block-heading seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_saas_copy['faq_title'] ); ?></h2><!-- wp:details --><details class="wp-block-details"><summary><?php echo esc_html( $seo_geo_saas_copy['faq_1'] ); ?></summary><!-- wp:paragraph {"className":"seo-geo-placeholder--copy"} --><p class="seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_saas_copy['faq_1_body'] ); ?></p><!-- /wp:paragraph --></details><!-- /wp:details --><!-- wp:details --><details class="wp-block-details"><summary><?php echo esc_html( $seo_geo_saas_copy['faq_2'] ); ?></summary><!-- wp:paragraph {"className":"seo-geo-placeholder--copy"} --><p class="seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_saas_copy['faq_2_body'] ); ?></p><!-- /wp:paragraph --></details><!-- /wp:details --><!-- wp:details --><details class="wp-block-details"><summary><?php echo esc_html( $seo_geo_saas_copy['faq_3'] ); ?></summary><!-- wp:paragraph {"className":"seo-geo-placeholder--copy"} --><p class="seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_saas_copy['faq_3_body'] ); ?></p><!-- /wp:paragraph --></details><!-- /wp:details --></section>
<!-- /wp:group -->

<!-- wp:group {"tagName":"section","align":"wide","anchor":"contact-demo","className":"seo-geo-final-cta seo-geo-saas-final-cta seo-geo-safe-copy","layout":{"type":"constrained"}} -->
<section id="contact-demo" class="wp-block-group alignwide seo-geo-final-cta seo-geo-saas-final-cta seo-geo-safe-copy"><h2 class="wp-block-heading seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_saas_copy['cta_title'] ); ?></h2><p class="seo-geo-placeholder--copy"><?php echo esc_html( $seo_geo_saas_copy['cta_body'] ); ?></p><!-- wp:buttons --><div class="wp-block-buttons"><!-- wp:button {"className":"seo-geo-placeholder--copy"} --><div class="wp-block-button seo-geo-placeholder--copy"><a class="wp-block-button__link wp-element-button" href="#"><?php echo esc_html( $seo_geo_saas_copy['cta_button'] ); ?></a></div><!-- /wp:button --></div><!-- /wp:buttons --></section>
<!-- /wp:group -->
