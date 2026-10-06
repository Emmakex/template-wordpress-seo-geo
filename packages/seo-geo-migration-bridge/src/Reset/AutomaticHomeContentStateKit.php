<?php
/**
 * Semantic content-state layer for automatic Corporate Home reconstruction.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge\Reset;

use WP_Error;
use WP_Post;

/**
 * Preserves automatic content when available and supplies safe semantic placeholders
 * when a legacy Home does not contain enough authored copy to satisfy the native model.
 */
final class AutomaticHomeContentStateKit {
	public const STATE_META       = '_seo_geo_home_semantic_content_state_v1';
	public const PLACEHOLDER_META = '_seo_geo_home_placeholder_slots_v1';

	/**
	 * Content-only blockers that may be recovered with a semantic placeholder scaffold.
	 *
	 * Structural, destination, sandbox and link-target blockers are deliberately absent.
	 *
	 * @var list<string>
	 */
	private const RECOVERABLE_BLOCKERS = array(
		'source-summary-not-detected',
		'three-capabilities-not-detected',
		'three-process-statements-not-detected',
		'quality-three-capabilities-not-detected',
	);

	/**
	 * Construct the semantic content-state layer.
	 *
	 * @param AutomaticHomeContentQualityKit $quality  Automatic quality/content resolver.
	 * @param CorporateHomeContentKit        $kit      Corporate semantic content authority.
	 * @param NativeHomeHydrator             $hydrator Native Home hydration authority.
	 */
	public function __construct(
		private AutomaticHomeContentQualityKit $quality,
		private CorporateHomeContentKit $kit,
		private NativeHomeHydrator $hydrator
	) {
	}

	/**
	 * Build a publish-safety-aware automatic content plan.
	 *
	 * @return array<string,mixed>
	 */
	public function plan(): array {
		$plan = $this->quality->plan();

		if ( true === ( $plan['ready'] ?? false ) ) {
			return $this->decorate_resolved_plan( $plan );
		}

		$blockers      = is_array( $plan['blockers'] ?? null )
			? array_values( array_filter( array_map( 'strval', $plan['blockers'] ) ) )
			: array();
		$hard_blockers = array_values( array_diff( $blockers, self::RECOVERABLE_BLOCKERS ) );

		if ( array() !== $hard_blockers ) {
			$plan['content_state'] = array(
				'mode'              => 'blocked',
				'publishable'       => false,
				'placeholder_slots' => array(),
				'slot_states'       => array(),
			);
			return $plan;
		}

		$draft_id  = (int) ( $plan['draft_id'] ?? 0 );
		$source_id = (int) ( $plan['source_id'] ?? 0 );
		$source    = 0 < $source_id ? get_post( $source_id ) : null;
		$detected  = is_array( $plan['detected'] ?? null ) ? $plan['detected'] : array();
		$contact   = trim( (string) ( $detected['contact_path'] ?? '' ) );

		if ( 0 >= $draft_id || ! $source instanceof WP_Post || '' === $contact ) {
			$plan['content_state'] = array(
				'mode'              => 'blocked',
				'publishable'       => false,
				'placeholder_slots' => array(),
				'slot_states'       => array(),
			);
			return $plan;
		}

		$lead         = $this->source_summary( $source );
		$about        = trim( (string) ( $detected['about_path'] ?? '' ) );
		$organization = trim( (string) get_bloginfo( 'name' ) );
		if ( '' === $organization ) {
			$organization = trim( get_the_title( $source ) );
		}
		if ( '' === $organization ) {
			$organization = $this->localized( 'Organización', 'Organization' );
		}

		$values            = $this->placeholder_values( $organization, $contact, $about, $lead );
		$placeholder_slots = $this->placeholder_slots( '' !== $lead );
		$slot_states       = array();

		foreach ( array_keys( $values ) as $slot_id ) {
			$slot_states[ $slot_id ] = in_array( $slot_id, $placeholder_slots, true ) ? 'placeholder' : 'derived';
		}
		if ( '' !== $lead ) {
			$slot_states['hero-lead'] = 'source';
		}

		$plan['ready']                                 = true;
		$plan['blockers']                              = array();
		$plan['values']                                = $values;
		$plan['home_heading']                          = $this->fallback_heading( $source, $lead );
		$plan['quality_pass']                          = 'semantic-placeholder-fallback-v1';
		$plan['fallback_from']                         = $blockers;
		$plan['content_state']                         = array(
			'mode'              => 'semantic-placeholder-scaffold',
			'publishable'       => false,
			'placeholder_slots' => $placeholder_slots,
			'slot_states'       => $slot_states,
		);
		$plan['safety']                                = is_array( $plan['safety'] ?? null ) ? $plan['safety'] : array();
		$plan['safety']['placeholder_content_present'] = true;
		$plan['detected']                              = $detected;
		$plan['detected']['placeholder_slots']         = count( $placeholder_slots );

		return $plan;
	}

	/**
	 * Persist the plan, hydrate the draft and record its content provenance state.
	 *
	 * @return array<string,mixed>|WP_Error
	 */
	public function apply(): array|WP_Error {
		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error( 'seo_geo_auto_home_forbidden', 'Administrator capability is required.' );
		}

		$plan = $this->plan();
		if ( true !== ( $plan['ready'] ?? false ) ) {
			return new WP_Error(
				'seo_geo_auto_home_state_not_ready',
				'Automatic Home content-state plan is blocked: ' . implode( ', ', is_array( $plan['blockers'] ?? null ) ? $plan['blockers'] : array() )
			);
		}

		$kit = $this->kit->save(
			array(
				'draft_id'        => (int) $plan['draft_id'],
				'plan_sha256'     => (string) $plan['plan_sha256'],
				'values'          => is_array( $plan['values'] ?? null ) ? $plan['values'] : array(),
				'verified_groups' => is_array( $plan['verified_groups'] ?? null ) ? $plan['verified_groups'] : array(),
			)
		);
		if ( $kit instanceof WP_Error ) {
			return $kit;
		}

		$heading = is_scalar( $plan['home_heading'] ?? null ) ? trim( (string) $plan['home_heading'] ) : '';
		if ( '' !== $heading ) {
			$updated = wp_update_post(
				array(
					'ID'         => (int) $plan['draft_id'],
					'post_title' => $heading,
				),
				true
			);
			if ( $updated instanceof WP_Error ) {
				return $updated;
			}
		}

		$hydration = $this->hydrator->apply();
		if ( $hydration instanceof WP_Error ) {
			return $hydration;
		}

		$state = is_array( $plan['content_state'] ?? null ) ? $plan['content_state'] : array();
		update_post_meta( (int) $plan['draft_id'], self::STATE_META, $state );
		update_post_meta(
			(int) $plan['draft_id'],
			self::PLACEHOLDER_META,
			is_array( $state['placeholder_slots'] ?? null ) ? array_values( $state['placeholder_slots'] ) : array()
		);

		return array(
			'schema_version' => 3,
			'mode'           => 'automatic-corporate-home-content-state',
			'status'         => 'applied',
			'draft_id'       => (int) $plan['draft_id'],
			'source_id'      => (int) $plan['source_id'],
			'kit_sha256'     => (string) ( $kit['kit_sha256'] ?? '' ),
			'content_sha256' => (string) ( $hydration['content_sha256'] ?? '' ),
			'content_state'  => $state,
			'detected'       => is_array( $plan['detected'] ?? null ) ? $plan['detected'] : array(),
			'safety'         => is_array( $plan['safety'] ?? null ) ? $plan['safety'] : array(),
		);
	}

	/**
	 * Attach explicit source/derived state to an already complete automatic plan.
	 *
	 * @param array<string,mixed> $plan Complete quality plan.
	 * @return array<string,mixed>
	 */
	private function decorate_resolved_plan( array $plan ): array {
		$values      = is_array( $plan['values'] ?? null ) ? $plan['values'] : array();
		$slot_states = array();
		$derived     = array(
			'hero-eyebrow',
			'hero-primary-cta',
			'hero-secondary-cta',
			'capabilities-heading',
			'capabilities-intro',
			'process-heading',
			'process-intro',
			'insights-heading',
			'insights-intro',
			'final-cta-heading',
			'final-cta-body',
			'final-cta-button',
		);

		foreach ( array_keys( $values ) as $slot_id ) {
			$slot_states[ $slot_id ] = in_array( $slot_id, $derived, true ) ? 'derived' : 'source';
		}

		$plan['content_state']                         = array(
			'mode'              => 'rescued-content',
			'publishable'       => true,
			'placeholder_slots' => array(),
			'slot_states'       => $slot_states,
		);
		$plan['safety']                                = is_array( $plan['safety'] ?? null ) ? $plan['safety'] : array();
		$plan['safety']['placeholder_content_present'] = false;

		return $plan;
	}

	/**
	 * Return a safe draft summary from authored source content without executing it.
	 *
	 * @param WP_Post $source Preserved front-page source.
	 */
	private function source_summary( WP_Post $source ): string {
		$excerpt = trim( sanitize_text_field( (string) $source->post_excerpt ) );
		if ( '' !== $excerpt ) {
			return wp_trim_words( $excerpt, 34, '…' );
		}

		$content = preg_replace( '#<(script|style)\b[^>]*>.*?</\1>#is', ' ', (string) $source->post_content ) ?? (string) $source->post_content;
		$content = preg_replace( '/\[(?:\/)?[A-Za-z0-9_-]+(?:\s[^\]]*)?\]/u', ' ', $content ) ?? $content;
		$content = trim( sanitize_text_field( wp_strip_all_tags( $content ) ) );

		return '' !== $content ? wp_trim_words( $content, 34, '…' ) : '';
	}

	/**
	 * Build the minimum complete semantic Home model using explicit draft prompts.
	 *
	 * @param string $organization Organization/site name.
	 * @param string $contact      Known safe contact path.
	 * @param string $about        Optional known About path.
	 * @param string $lead         Optional rescued source summary.
	 * @return array<string,mixed>
	 */
	private function placeholder_values( string $organization, string $contact, string $about, string $lead ): array {
		$hero_lead = '' !== $lead
			? $lead
			: $this->localized(
				'Describe aquí, en una frase clara, el principal resultado que esta organización ofrece a sus clientes.',
				'Describe here, in one clear sentence, the primary outcome this organization delivers for its customers.'
			);

		$values = array(
			'hero-eyebrow'         => $organization,
			'hero-lead'            => $hero_lead,
			'hero-primary-cta'     => array(
				'label' => $this->localized( 'Contactar', 'Contact us' ),
				'url'   => $contact,
			),
			'capabilities-heading' => $this->localized( 'Servicios y capacidades', 'Services and capabilities' ),
			'capabilities-intro'   => $this->localized(
				'Resume aquí las áreas principales en las que la organización aporta valor.',
				'Summarize here the main areas where the organization creates value.'
			),
			'capability-1-title'   => $this->localized( 'Servicio principal', 'Primary service' ),
			'capability-1-body'    => $this->localized( 'Explica el problema que resuelve, para quién y qué resultado debería esperar el cliente.', 'Explain the problem it solves, who it is for and the outcome the customer should expect.' ),
			'capability-1-link'    => array(
				'label' => $this->localized( 'Más información', 'Learn more' ),
				'url'   => $contact,
			),
			'capability-2-title'   => $this->localized( 'Servicio complementario', 'Supporting service' ),
			'capability-2-body'    => $this->localized( 'Describe una segunda capacidad real y diferenciada que complete la propuesta principal.', 'Describe a second real and distinct capability that complements the primary offer.' ),
			'capability-2-link'    => array(
				'label' => $this->localized( 'Más información', 'Learn more' ),
				'url'   => $contact,
			),
			'capability-3-title'   => $this->localized( 'Capacidad especializada', 'Specialist capability' ),
			'capability-3-body'    => $this->localized( 'Añade una capacidad especializada, metodología o ventaja concreta que tenga sentido para este negocio.', 'Add a specialist capability, method or concrete advantage that is meaningful for this business.' ),
			'capability-3-link'    => array(
				'label' => $this->localized( 'Más información', 'Learn more' ),
				'url'   => $contact,
			),
			'process-heading'      => $this->localized( 'Cómo trabajamos', 'How we work' ),
			'process-intro'        => $this->localized( 'Sustituye este texto por el método real de trabajo de la organización.', 'Replace this copy with the organization’s real working method.' ),
			'process-1-title'      => $this->localized( 'Entender', 'Understand' ),
			'process-1-body'       => $this->localized( 'Describe cómo se entiende el contexto, el problema y los objetivos antes de empezar.', 'Describe how context, the problem and objectives are understood before work begins.' ),
			'process-2-title'      => $this->localized( 'Diseñar y ejecutar', 'Design and deliver' ),
			'process-2-body'       => $this->localized( 'Describe cómo se convierte el diagnóstico en una solución, plan o ejecución concreta.', 'Describe how the diagnosis becomes a concrete solution, plan or delivery.' ),
			'process-3-title'      => $this->localized( 'Medir y mejorar', 'Measure and improve' ),
			'process-3-body'       => $this->localized( 'Describe cómo se revisan los resultados y qué señales se utilizan para mejorar.', 'Describe how outcomes are reviewed and which signals are used to improve them.' ),
			'insights-heading'     => $this->localized( 'Actualidad e ideas', 'Insights and ideas' ),
			'insights-intro'       => $this->localized( 'Contenido publicado por la organización sobre sus áreas de trabajo y conocimiento.', 'Published content from the organization about its areas of work and expertise.' ),
			'final-cta-heading'    => $this->localized( '¿Hablamos?', 'Let’s talk' ),
			'final-cta-body'       => $this->localized( 'Cuéntanos qué necesitas y revisaremos el contexto antes de definir los siguientes pasos.', 'Tell us what you need and we will review the context before defining the next steps.' ),
			'final-cta-button'     => array(
				'label' => $this->localized( 'Contactar', 'Contact us' ),
				'url'   => $contact,
			),
		);

		if ( '' !== $about ) {
			$values['hero-secondary-cta'] = array(
				'label' => $this->localized( 'Conócenos', 'About us' ),
				'url'   => $about,
			);
		}

		return $values;
	}

	/**
	 * Return slots that remain editorial placeholders in the fallback scaffold.
	 *
	 * @param bool $has_source_lead Whether a rescued Hero lead is available.
	 * @return list<string>
	 */
	private function placeholder_slots( bool $has_source_lead ): array {
		$slots = array(
			'capabilities-intro',
			'capability-1-title',
			'capability-1-body',
			'capability-2-title',
			'capability-2-body',
			'capability-3-title',
			'capability-3-body',
			'process-intro',
			'process-1-body',
			'process-2-body',
			'process-3-body',
		);
		if ( ! $has_source_lead ) {
			array_unshift( $slots, 'hero-lead' );
		}

		return $slots;
	}

	/**
	 * Resolve a safe fallback document heading.
	 *
	 * @param WP_Post $source Preserved front-page source.
	 * @param string  $lead   Optional rescued Hero lead.
	 */
	private function fallback_heading( WP_Post $source, string $lead ): string {
		$title = trim( get_the_title( $source ) );
		if ( '' !== $title && ! in_array( strtolower( $title ), array( 'home', 'inicio', 'portada' ), true ) ) {
			return wp_trim_words( $title, 16, '' );
		}
		if ( '' !== $lead ) {
			return wp_trim_words( rtrim( $lead, ' .!?' ), 16, '' );
		}

		return $this->localized( 'Página principal en revisión', 'Home page under review' );
	}

	/**
	 * Localize one internal fallback string without external translation calls.
	 *
	 * @param string $spanish Spanish fallback copy.
	 * @param string $english English fallback copy.
	 */
	private function localized( string $spanish, string $english ): string {
		return str_starts_with( strtolower( get_locale() ), 'es' ) ? $spanish : $english;
	}
}
