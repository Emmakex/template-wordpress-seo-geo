<?php
/**
 * Seed representative browser-acceptance pages.
 *
 * Run with WP-CLI eval-file inside the disposable CI WordPress fixture.
 *
 * @package SeoGeoAcceptance
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$pattern_slugs = array(
	'seo-geo-theme/hero',
	'seo-geo-theme/services-features',
	'seo-geo-theme/faq',
	'seo-geo-theme/cta',
	'seo-geo-theme/contact',
);

$registry = WP_Block_Patterns_Registry::get_instance();
$content  = '';

foreach ( $pattern_slugs as $pattern_slug ) {
	$pattern = $registry->get_registered( $pattern_slug );

	if ( ! is_array( $pattern ) || ! isset( $pattern['content'] ) || ! is_string( $pattern['content'] ) ) {
		WP_CLI::error( sprintf( 'Required acceptance pattern is not registered: %s', $pattern_slug ) );
	}

	$content .= "\n" . $pattern['content'] . "\n";
}

$spanish_copy = array(
	'Add a short category or trust signal' => 'Añade una categoría breve o una señal de confianza verificable',
	'Write a clear, specific value proposition' => 'Escribe una propuesta de valor clara, específica y fácil de entender',
	'Explain who this is for, what problem it solves and why the reader should continue.' => 'Explica para quién es, qué problema resuelve y por qué merece la pena seguir leyendo.',
	'Primary action' => 'Acción principal',
	'Secondary action' => 'Acción secundaria',
	'Describe the main ways you help' => 'Describe las principales maneras en las que ayudas',
	'Keep each item distinct, concrete and easy to compare.' => 'Mantén cada elemento diferenciado, concreto y sencillo de comparar.',
	'Primary offering' => 'Servicio principal',
	'Explain the outcome, scope and who benefits from this offering.' => 'Explica el resultado, el alcance y quién se beneficia de este servicio.',
	'Supporting offering' => 'Servicio complementario',
	'Describe a second meaningful capability without repeating the first.' => 'Describe una segunda capacidad relevante sin repetir la propuesta anterior.',
	'Specialist offering' => 'Servicio especializado',
	'Use the third item for a specialist service, feature or differentiator.' => 'Utiliza el tercer elemento para un servicio especializado, una función o un diferenciador concreto.',
	'Frequently asked questions' => 'Preguntas frecuentes',
	'Answer real questions clearly. Keep each answer useful even when read outside the page context.' => 'Responde preguntas reales con claridad y procura que cada respuesta siga siendo útil incluso fuera del contexto de la página.',
	'Replace this with a real customer question' => 'Sustituye esto por una pregunta real de un cliente',
	'Give a direct answer first, then add only the context needed to make it accurate.' => 'Da primero una respuesta directa y añade después únicamente el contexto necesario para que sea precisa.',
	'Add a second high-value question' => 'Añade una segunda pregunta de alto valor',
	'Use factual wording and avoid promises that the visible content cannot support.' => 'Utiliza una redacción factual y evita promesas que el contenido visible no pueda demostrar.',
	'Add a question that helps the visitor decide' => 'Añade una pregunta que ayude al visitante a tomar una decisión',
	'Clarify scope, process, requirements, timing or another decision-critical point.' => 'Aclara el alcance, el proceso, los requisitos, los plazos u otro punto decisivo para el usuario.',
	'State the next useful step clearly' => 'Indica con claridad cuál es el siguiente paso útil',
	'Add the minimum supporting context a visitor needs before taking action.' => 'Añade solo el contexto mínimo que una persona necesita antes de realizar la acción.',
	'Take the next step' => 'Dar el siguiente paso',
	'Contact' => 'Contacto',
	'Explain the best way to get in touch and what information helps you respond effectively.' => 'Explica cuál es la mejor forma de contactar y qué información permite responder de manera eficaz.',
	'Email' => 'Correo electrónico',
	'Add the public contact email address.' => 'Añade la dirección pública de correo electrónico de contacto.',
	'Phone' => 'Teléfono',
	'Add the public phone number and, if relevant, service hours.' => 'Añade el número de teléfono público y, cuando corresponda, el horario de atención.',
	'Location or service area' => 'Ubicación o área de servicio',
	'Add a real address or describe the geographic area served.' => 'Añade una dirección real o describe con precisión el área geográfica atendida.',
	'Contact us' => 'Contactar',
);

$pages = array(
	array(
		'slug'    => 'acceptance-en',
		'title'   => 'Accessibility and responsive acceptance — English',
		'content' => $content,
	),
	array(
		'slug'    => 'acceptance-es',
		'title'   => 'Aceptación de accesibilidad y diseño responsive — Español',
		'content' => strtr( $content, $spanish_copy ),
	),
);

foreach ( $pages as $page ) {
	$existing = get_page_by_path( $page['slug'], OBJECT, 'page' );
	$post_id  = $existing instanceof WP_Post ? $existing->ID : 0;

	$result = wp_insert_post(
		wp_slash(
			array(
				'ID'           => $post_id,
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_name'    => $page['slug'],
				'post_title'   => $page['title'],
				'post_content' => $page['content'],
			)
		),
		true
	);

	if ( is_wp_error( $result ) ) {
		WP_CLI::error( sprintf( 'Could not seed %s: %s', $page['slug'], $result->get_error_message() ) );
	}

	update_post_meta( (int) $result, '_seo_geo_acceptance_fixture', '1' );
	WP_CLI::log( sprintf( 'Seeded %s as page %d.', $page['slug'], (int) $result ) );
}

WP_CLI::success( 'Representative EN/ES accessibility fixtures seeded.' );
