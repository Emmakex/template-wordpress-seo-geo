<?php
/**
 * Automatic Corporate Home content extraction for reset-first rebuilds.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge\Reset;

use WP_Error;
use WP_Post;

/**
 * Builds a safe Home Content Kit from rescued WordPress content without legacy layout reuse.
 */
final class AutomaticHomeContentKit {
	/**
	 * Construct the automatic Home content service.
	 *
	 * @param RescueManifest          $manifest Rescue Manifest authority.
	 * @param CleanHomeRebuilder      $builder  Clean Corporate Home builder.
	 * @param CorporateHomeContentKit $kit      Structured Home Content Kit service.
	 * @param NativeHomeHydrator      $hydrator Native Home hydrator.
	 */
	public function __construct(
		private RescueManifest $manifest,
		private CleanHomeRebuilder $builder,
		private CorporateHomeContentKit $kit,
		private NativeHomeHydrator $hydrator
	) {
	}

	/**
	 * Build a read-only automatic proposal from the rescued Home and related pages.
	 *
	 * @return array<string,mixed>
	 */
	public function plan(): array {
		$blockers  = array();
		$manifest  = $this->manifest->saved();
		$home_plan = $this->builder->plan();
		$draft_id  = (int) ( $home_plan['existing_draft'] ?? 0 );
		$source_id = (int) ( $home_plan['source']['id'] ?? 0 );
		$source    = 0 < $source_id ? get_post( $source_id ) : null;

		if ( ! is_array( $manifest ) ) {
			$blockers[] = 'rescue-manifest-required';
		}
		if ( true !== ( $home_plan['ready'] ?? false ) || 0 >= $draft_id ) {
			$blockers[] = 'clean-home-draft-required';
		}
		if ( ! $source instanceof WP_Post || 'page' !== $source->post_type ) {
			$blockers[] = 'front-page-source-required';
		}

		$plain     = $source instanceof WP_Post ? $this->plain_text( (string) $source->post_content ) : '';
		$sentences = $this->sentences( $plain );
		$lead      = $source instanceof WP_Post ? $this->lead( $source, $manifest ) : '';
		$contact   = is_array( $manifest ) ? $this->contact_target( $manifest ) : null;
		$about     = is_array( $manifest ) ? $this->about_target( $manifest ) : null;
		$services  = is_array( $manifest ) ? $this->capabilities( $manifest, $source_id, $lead, $plain, $sentences ) : array();
		$process   = $this->process_steps( $sentences, $lead );

		if ( '' === $lead ) {
			$blockers[] = 'source-summary-not-detected';
		}
		if ( ! is_array( $contact ) || '' === (string) ( $contact['path'] ?? '' ) ) {
			$blockers[] = 'contact-target-not-detected';
		}
		if ( 3 > count( $services ) ) {
			$blockers[] = 'three-capabilities-not-detected';
		}
		if ( 3 > count( $process ) ) {
			$blockers[] = 'three-process-statements-not-detected';
		}

		$blockers = array_values( array_unique( $blockers ) );
		sort( $blockers );

		$values = array();
		if ( array() === $blockers && is_array( $contact ) ) {
			$values = $this->build_values(
				$source,
				$manifest,
				$contact,
				$about,
				$services,
				$process,
				$plain,
				$lead
			);
		}

		return array(
			'schema_version'  => 1,
			'mode'            => 'automatic-corporate-home-content-plan',
			'ready'           => array() === $blockers,
			'blockers'        => $blockers,
			'draft_id'        => $draft_id,
			'source_id'       => $source_id,
			'plan_sha256'     => (string) ( $home_plan['plan_sha256'] ?? '' ),
			'values'          => $values,
			'verified_groups' => array(
				'hero-proof' => false,
				'proof'      => false,
				'case-study' => false,
			),
			'detected'        => array(
				'plain_text_bytes' => strlen( $plain ),
				'sentences'        => count( $sentences ),
				'capabilities'     => count( $services ),
				'contact_path'     => is_array( $contact ) ? (string) ( $contact['path'] ?? '' ) : '',
				'about_path'       => is_array( $about ) ? (string) ( $about['path'] ?? '' ) : '',
			),
			'safety'          => array(
				'legacy_layout_reused'         => false,
				'legacy_runtime_executed'      => false,
				'evidence_auto_verified'       => false,
				'source_post_mutation'         => false,
				'front_page_assignment_change' => false,
			),
		);
	}

	/**
	 * Generate, validate, persist and hydrate the clean Home in one operator action.
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
				'seo_geo_auto_home_not_ready',
				'Automatic Home content is blocked: ' . implode( ', ', is_array( $plan['blockers'] ?? null ) ? $plan['blockers'] : array() )
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

		$hydration = $this->hydrator->apply();
		if ( $hydration instanceof WP_Error ) {
			return $hydration;
		}

		return array(
			'schema_version' => 1,
			'mode'           => 'automatic-corporate-home-content',
			'status'         => 'applied',
			'draft_id'       => (int) $plan['draft_id'],
			'source_id'      => (int) $plan['source_id'],
			'kit_sha256'     => (string) ( $kit['kit_sha256'] ?? '' ),
			'content_sha256' => (string) ( $hydration['content_sha256'] ?? '' ),
			'detected'       => is_array( $plan['detected'] ?? null ) ? $plan['detected'] : array(),
			'safety'         => is_array( $plan['safety'] ?? null ) ? $plan['safety'] : array(),
		);
	}

	/**
	 * Build all required semantic values from detected source material.
	 *
	 * @param WP_Post|null $source   Preserved front-page source.
	 * @param array        $manifest Saved Rescue Manifest.
	 * @param array        $contact  Detected contact page resource.
	 * @param array|null   $about    Detected about page resource.
	 * @param array        $services Detected capability candidates.
	 * @param array        $process  Detected process statements.
	 * @param string       $plain    Plain authored source text.
	 * @param string       $lead     Source summary.
	 * @phpstan-param array<string,mixed> $manifest
	 * @phpstan-param array<string,mixed> $contact
	 * @phpstan-param array<string,mixed>|null $about
	 * @phpstan-param list<array{title:string,body:string,path:string}> $services
	 * @phpstan-param list<array{title:string,body:string}> $process
	 * @return array<string,mixed>
	 */
	private function build_values(
		?WP_Post $source,
		array $manifest,
		array $contact,
		?array $about,
		array $services,
		array $process,
		string $plain,
		string $lead
	): array {
		$organization = trim( (string) get_bloginfo( 'name' ) );
		if ( '' === $organization && $source instanceof WP_Post ) {
			$organization = trim( get_the_title( $source ) );
		}
		if ( '' === $organization ) {
			$organization = $this->localized( 'Organización', 'Organization' );
		}

		$contact_path = (string) ( $contact['path'] ?? '' );
		$values       = array(
			'hero-eyebrow'         => $organization,
			'hero-lead'            => $lead,
			'hero-primary-cta'     => array(
				'label' => $this->localized( 'Contactar', 'Contact us' ),
				'url'   => $contact_path,
			),
			'capabilities-heading' => $this->localized( 'Servicios y capacidades', 'Services and capabilities' ),
			'capabilities-intro'   => $lead,
			'process-heading'      => $this->localized( 'Cómo trabajamos', 'How we work' ),
			'process-intro'        => $this->process_intro( $plain, $lead ),
			'insights-heading'     => $this->localized( 'Actualidad e ideas', 'Insights and ideas' ),
			'insights-intro'       => $this->insights_intro( $manifest, $organization ),
			'final-cta-heading'    => $this->localized( '¿Hablamos?', 'Let’s talk' ),
			'final-cta-body'       => $this->localized(
				'Cuéntanos qué necesitas y revisaremos el contexto antes de definir los siguientes pasos.',
				'Tell us what you need and we will review the context before defining the next steps.'
			),
			'final-cta-button'     => array(
				'label' => $this->localized( 'Contactar', 'Contact us' ),
				'url'   => $contact_path,
			),
		);

		if ( is_array( $about ) && '' !== (string) ( $about['path'] ?? '' ) ) {
			$values['hero-secondary-cta'] = array(
				'label' => $this->localized( 'Conócenos', 'About us' ),
				'url'   => (string) $about['path'],
			);
		}

		foreach ( array_slice( $services, 0, 3 ) as $index => $service ) {
			$number       = $index + 1;
			$service_path = trim( (string) ( $service['path'] ?? '' ) );
			if ( '' === $service_path ) {
				$service_path = $contact_path;
			}
			$values[ 'capability-' . $number . '-title' ] = (string) $service['title'];
			$values[ 'capability-' . $number . '-body' ]  = (string) $service['body'];
			$values[ 'capability-' . $number . '-link' ]  = array(
				'label' => $this->localized( 'Más información', 'Learn more' ),
				'url'   => $service_path,
			);
		}

		foreach ( array_slice( $process, 0, 3 ) as $index => $step ) {
			$number                                    = $index + 1;
			$values[ 'process-' . $number . '-title' ] = (string) $step['title'];
			$values[ 'process-' . $number . '-body' ]  = (string) $step['body'];
		}

		return $values;
	}

	/**
	 * Convert builder-heavy stored content into plain authored text without executing legacy shortcodes.
	 *
	 * @param string $content Stored WordPress content.
	 */
	private function plain_text( string $content ): string {
		$content = preg_replace( '#<(script|style)\b[^>]*>.*?</\1>#is', ' ', $content ) ?? $content;
		$content = preg_replace( '/\[(?:\/)?[A-Za-z0-9_-]+(?:\s[^\]]*)?\]/u', ' ', $content ) ?? $content;
		$content = preg_replace( '/<(?:br|\/p|\/div|\/li|\/h[1-6])\b[^>]*>/i', "\n", $content ) ?? $content;
		$content = html_entity_decode( wp_strip_all_tags( $content ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$content = preg_replace( '/[\t ]+/u', ' ', $content ) ?? $content;
		$content = preg_replace( '/\s*\R\s*/u', "\n", $content ) ?? $content;
		$content = preg_replace( '/\n{2,}/u', "\n", $content ) ?? $content;

		return trim( $content );
	}

	/**
	 * Split plain authored content into useful sentence-sized retrieval units.
	 *
	 * @param string $plain Plain authored content.
	 * @return list<string>
	 */
	private function sentences( string $plain ): array {
		if ( '' === $plain ) {
			return array();
		}

		$split = preg_split( '/(?<=[.!?])\s+|\R+/u', $plain );
		$parts = is_array( $split ) ? $split : array();
		$out   = array();
		foreach ( $parts as $part ) {
			$part = trim( sanitize_text_field( $part ) );
			if ( 35 > strlen( $part ) ) {
				continue;
			}
			$out[] = wp_trim_words( $part, 42, '…' );
		}

		return array_values( array_unique( $out ) );
	}

	/**
	 * Resolve a concise source summary from rescued SEO, excerpt or authored content.
	 *
	 * @param WP_Post    $source   Preserved WordPress source page.
	 * @param array|null $manifest Saved Rescue Manifest.
	 * @phpstan-param array<string,mixed>|null $manifest
	 */
	private function lead( WP_Post $source, ?array $manifest ): string {
		$item = is_array( $manifest ) ? $this->resource_by_id( $manifest, $source->ID ) : null;
		$seo  = is_array( $item['seo'] ?? null ) ? $item['seo'] : array();
		foreach ( array( '_yoast_wpseo_metadesc', 'rank_math_description' ) as $key ) {
			$value = is_scalar( $seo[ $key ] ?? null ) ? trim( (string) $seo[ $key ] ) : '';
			if ( '' !== $value && ! str_contains( $value, '%%' ) ) {
				return wp_trim_words( sanitize_text_field( $value ), 42, '…' );
			}
		}

		$excerpt = trim( wp_strip_all_tags( (string) $source->post_excerpt ) );
		if ( '' !== $excerpt ) {
			return wp_trim_words( $excerpt, 42, '…' );
		}

		$sentences = $this->sentences( $this->plain_text( (string) $source->post_content ) );

		return (string) ( $sentences[0] ?? '' );
	}

	/**
	 * Detect service/capability candidates from related pages and Home vocabulary.
	 *
	 * @param array  $manifest  Saved Rescue Manifest.
	 * @param int    $source_id Preserved Home source ID.
	 * @param string $lead      Source summary.
	 * @param string $plain     Plain source content.
	 * @param array  $sentences Source retrieval units.
	 * @phpstan-param array<string,mixed> $manifest
	 * @phpstan-param list<string> $sentences
	 * @return list<array{title:string,body:string,path:string}>
	 */
	private function capabilities( array $manifest, int $source_id, string $lead, string $plain, array $sentences ): array {
		$candidates = array();
		$resources  = is_array( $manifest['resources'] ?? null ) ? $manifest['resources'] : array();
		foreach ( $resources as $item ) {
			if ( ! is_array( $item ) || 'page' !== ( $item['post_type'] ?? null ) || 'publish' !== ( $item['status'] ?? null ) ) {
				continue;
			}

			$id = (int) ( $item['id'] ?? 0 );
			if ( $source_id === $id || $this->generic_page( $item ) ) {
				continue;
			}

			$post = get_post( $id );
			if ( ! $post instanceof WP_Post ) {
				continue;
			}

			$title = trim( get_the_title( $post ) );
			$path  = (string) ( $item['path'] ?? '' );
			$body  = $this->lead( $post, $manifest );
			if ( '' === $title || '' === $path || '' === $body ) {
				continue;
			}

			$score = $this->service_score( $title . ' ' . $body );
			if ( 0 >= $score ) {
				continue;
			}

			$candidates[] = array(
				'title' => $title,
				'body'  => $body,
				'path'  => $path,
				'score' => $score,
			);
		}

		usort(
			$candidates,
			static fn( array $a, array $b ): int => (int) $b['score'] <=> (int) $a['score']
		);

		$result = array();
		foreach ( $candidates as $candidate ) {
			$result[] = array(
				'title' => (string) $candidate['title'],
				'body'  => (string) $candidate['body'],
				'path'  => (string) $candidate['path'],
			);
			if ( 3 <= count( $result ) ) {
				return $result;
			}
		}

		$haystack = strtolower( $plain );
		foreach ( $this->keyword_categories() as $category ) {
			if ( ! $this->contains_any( $haystack, $category['keywords'] ) ) {
				continue;
			}

			$body     = $this->sentence_for_keywords( $sentences, $category['keywords'] );
			$result[] = array(
				'title' => $this->localized( $category['es'], $category['en'] ),
				'body'  => '' !== $body ? $body : $lead,
				'path'  => '',
			);
			$result   = $this->unique_capabilities( $result );
			if ( 3 <= count( $result ) ) {
				break;
			}
		}

		return array_slice( $result, 0, 3 );
	}

	/**
	 * Build three process statements from source language without inventing evidence.
	 *
	 * @param array  $sentences Source retrieval units.
	 * @param string $lead      Source summary fallback.
	 * @phpstan-param list<string> $sentences
	 * @return list<array{title:string,body:string}>
	 */
	private function process_steps( array $sentences, string $lead ): array {
		$sets  = array(
			array(
				'es'       => 'Entender',
				'en'       => 'Understand',
				'keywords' => array( 'conocer', 'entender', 'analiz', 'diagn', 'understand', 'discover', 'research' ),
			),
			array(
				'es'       => 'Definir',
				'en'       => 'Define',
				'keywords' => array( 'estrateg', 'defin', 'plan', 'diseñ', 'design', 'strategy' ),
			),
			array(
				'es'       => 'Medir y mejorar',
				'en'       => 'Measure and improve',
				'keywords' => array( 'medir', 'resultado', 'mejor', 'optim', 'measure', 'result', 'improve' ),
			),
		);
		$steps = array();
		foreach ( $sets as $set ) {
			$body = $this->sentence_for_keywords( $sentences, $set['keywords'] );
			if ( '' === $body ) {
				continue;
			}
			$steps[] = array(
				'title' => $this->localized( $set['es'], $set['en'] ),
				'body'  => $body,
			);
		}

		foreach ( $sentences as $sentence ) {
			if ( 3 <= count( $steps ) ) {
				break;
			}
			if ( $this->step_body_exists( $steps, $sentence ) ) {
				continue;
			}
			$steps[] = array(
				'title' => $this->localized( 'Paso ' . ( count( $steps ) + 1 ), 'Step ' . ( count( $steps ) + 1 ) ),
				'body'  => $sentence,
			);
		}

		$fallback_titles = array(
			$this->localized( 'Entender', 'Understand' ),
			$this->localized( 'Definir', 'Define' ),
			$this->localized( 'Medir y mejorar', 'Measure and improve' ),
		);
		for ( $index = count( $steps ); $index < 3; ++$index ) {
			if ( '' === $lead ) {
				break;
			}
			$steps[] = array(
				'title' => $fallback_titles[ $index ],
				'body'  => $lead,
			);
		}

		return array_slice( $steps, 0, 3 );
	}

	/**
	 * Resolve a process introduction from source language.
	 *
	 * @param string $plain Plain source content.
	 * @param string $lead  Source summary fallback.
	 */
	private function process_intro( string $plain, string $lead ): string {
		$sentences = $this->sentences( $plain );
		$matched   = $this->sentence_for_keywords(
			$sentences,
			array( 'proceso', 'metod', 'estrateg', 'analiz', 'process', 'method', 'strategy' )
		);

		return '' !== $matched ? $matched : $lead;
	}

	/**
	 * Build a neutral Insights introduction from rescued post inventory.
	 *
	 * @param array  $manifest     Saved Rescue Manifest.
	 * @param string $organization Organization display name.
	 * @phpstan-param array<string,mixed> $manifest
	 */
	private function insights_intro( array $manifest, string $organization ): string {
		$posts = (int) ( $manifest['counts']['posts'] ?? 0 );
		if ( 0 < $posts ) {
			return $this->localized(
				'Consulta los contenidos publicados por ' . $organization . ' sobre sus áreas de trabajo y conocimiento.',
				'Explore content published by ' . $organization . ' about its areas of work and expertise.'
			);
		}

		return $this->localized(
			'Ideas y recursos relacionados con nuestras áreas de trabajo.',
			'Ideas and resources related to our areas of work.'
		);
	}

	/**
	 * Find the preserved Contact page.
	 *
	 * @param array $manifest Saved Rescue Manifest.
	 * @phpstan-param array<string,mixed> $manifest
	 * @return array<string,mixed>|null
	 */
	private function contact_target( array $manifest ): ?array {
		return $this->target_page( $manifest, array( 'contact', 'contacto' ) );
	}

	/**
	 * Find the preserved About page when one exists.
	 *
	 * @param array $manifest Saved Rescue Manifest.
	 * @phpstan-param array<string,mixed> $manifest
	 * @return array<string,mixed>|null
	 */
	private function about_target( array $manifest ): ?array {
		return $this->target_page(
			$manifest,
			array( 'about', 'nosotros', 'empresa', 'quienes-somos', 'quienes somos' )
		);
	}

	/**
	 * Find one published page by title/slug tokens.
	 *
	 * @param array $manifest Saved Rescue Manifest.
	 * @param array $tokens   Matching title/slug tokens.
	 * @phpstan-param array<string,mixed> $manifest
	 * @phpstan-param list<string> $tokens
	 * @return array<string,mixed>|null
	 */
	private function target_page( array $manifest, array $tokens ): ?array {
		$resources = is_array( $manifest['resources'] ?? null ) ? $manifest['resources'] : array();
		foreach ( $resources as $item ) {
			if ( ! is_array( $item ) || 'page' !== ( $item['post_type'] ?? null ) || 'publish' !== ( $item['status'] ?? null ) ) {
				continue;
			}

			$identity = strtolower( (string) ( $item['slug'] ?? '' ) . ' ' . (string) ( $item['title'] ?? '' ) );
			foreach ( $tokens as $token ) {
				if ( str_contains( $identity, $token ) && '' !== (string) ( $item['path'] ?? '' ) ) {
					return $item;
				}
			}
		}

		return null;
	}

	/**
	 * Determine whether a page is generic rather than a capability candidate.
	 *
	 * @param array $item Rescue Manifest resource.
	 * @phpstan-param array<string,mixed> $item
	 */
	private function generic_page( array $item ): bool {
		$identity = strtolower( (string) ( $item['slug'] ?? '' ) . ' ' . (string) ( $item['title'] ?? '' ) );

		return $this->contains_any( $identity, $this->generic_page_tokens() );
	}

	/**
	 * Return title/slug tokens for non-service pages.
	 *
	 * @return list<string>
	 */
	private function generic_page_tokens(): array {
		return array(
			'contact',
			'contacto',
			'about',
			'nosotros',
			'empresa',
			'privacy',
			'privacidad',
			'legal',
			'aviso',
			'cookies',
			'cookie',
			'terms',
			'terminos',
			'actualidad',
			'blog',
			'insights',
		);
	}

	/**
	 * Score candidate content for service/capability vocabulary.
	 *
	 * @param string $text Candidate title and summary.
	 */
	private function service_score( string $text ): int {
		$text  = strtolower( $text );
		$score = 0;
		foreach ( $this->keyword_categories() as $category ) {
			foreach ( $category['keywords'] as $keyword ) {
				if ( str_contains( $text, $keyword ) ) {
					$score += 10;
				}
			}
		}

		return $score;
	}

	/**
	 * Return bounded semantic capability categories used only when source pages are insufficient.
	 *
	 * @return list<array{es:string,en:string,keywords:list<string>}>
	 */
	private function keyword_categories(): array {
		return array(
			array(
				'es'       => 'Marketing digital',
				'en'       => 'Digital marketing',
				'keywords' => array( 'marketing', 'publicidad', 'ads', 'campaña', 'campaign' ),
			),
			array(
				'es'       => 'SEO y visibilidad digital',
				'en'       => 'SEO and digital visibility',
				'keywords' => array( 'seo', 'posicionamiento', 'search engine', 'visibilidad' ),
			),
			array(
				'es'       => 'Investigación de mercado',
				'en'       => 'Market research',
				'keywords' => array( 'investigación', 'investigacion', 'mercado', 'market research', 'competencia' ),
			),
			array(
				'es'       => 'Datos e inteligencia de negocio',
				'en'       => 'Data and business intelligence',
				'keywords' => array( 'datos', 'data', 'business intelligence', 'inteligencia de negocio', 'analítica', 'analitica', 'analytics' ),
			),
			array(
				'es'       => 'Inteligencia artificial y automatización',
				'en'       => 'AI and automation',
				'keywords' => array( 'inteligencia artificial', ' ia ', ' ai ', 'automatización', 'automatizacion', 'automation' ),
			),
			array(
				'es'       => 'Desarrollo web y tecnología',
				'en'       => 'Web development and technology',
				'keywords' => array( 'wordpress', 'web', 'desarrollo', 'development', 'software', 'tecnología', 'tecnologia' ),
			),
		);
	}

	/**
	 * Find the first source sentence that contains any requested keyword.
	 *
	 * @param array $sentences Source retrieval units.
	 * @param array $keywords  Search keywords.
	 * @phpstan-param list<string> $sentences
	 * @phpstan-param list<string> $keywords
	 */
	private function sentence_for_keywords( array $sentences, array $keywords ): string {
		foreach ( $sentences as $sentence ) {
			$lower = strtolower( $sentence );
			if ( $this->contains_any( $lower, $keywords ) ) {
				return $sentence;
			}
		}

		return '';
	}

	/**
	 * Determine whether a text contains any token.
	 *
	 * @param string $haystack Searchable text.
	 * @param array  $needles  Tokens to detect.
	 * @phpstan-param list<string> $needles
	 */
	private function contains_any( string $haystack, array $needles ): bool {
		foreach ( $needles as $needle ) {
			if ( str_contains( $haystack, $needle ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Remove duplicate capability titles while preserving their first occurrence.
	 *
	 * @param array $items Capability candidates.
	 * @phpstan-param list<array{title:string,body:string,path:string}> $items
	 * @return list<array{title:string,body:string,path:string}>
	 */
	private function unique_capabilities( array $items ): array {
		$seen = array();
		$out  = array();
		foreach ( $items as $item ) {
			$key = strtolower( trim( $item['title'] ) );
			if ( '' === $key || isset( $seen[ $key ] ) ) {
				continue;
			}
			$seen[ $key ] = true;
			$out[]        = $item;
		}

		return $out;
	}

	/**
	 * Determine whether one process body is already present.
	 *
	 * @param array  $steps    Existing process statements.
	 * @param string $sentence Candidate source sentence.
	 * @phpstan-param list<array{title:string,body:string}> $steps
	 */
	private function step_body_exists( array $steps, string $sentence ): bool {
		foreach ( $steps as $step ) {
			if ( $sentence === $step['body'] ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Find a Rescue Manifest resource by WordPress post ID.
	 *
	 * @param array $manifest Saved Rescue Manifest.
	 * @param int   $id       WordPress post ID.
	 * @phpstan-param array<string,mixed> $manifest
	 * @return array<string,mixed>|null
	 */
	private function resource_by_id( array $manifest, int $id ): ?array {
		$resources = is_array( $manifest['resources'] ?? null ) ? $manifest['resources'] : array();
		foreach ( $resources as $item ) {
			if ( is_array( $item ) && (int) ( $item['id'] ?? 0 ) === $id ) {
				return $item;
			}
		}

		return null;
	}

	/**
	 * Resolve one short operator-owned label in the active preset language.
	 *
	 * @param string $es Spanish text.
	 * @param string $en English text.
	 */
	private function localized( string $es, string $en ): string {
		$locale = function_exists( 'seo_geo_theme_preset_locale' ) ? (string) \seo_geo_theme_preset_locale() : get_locale();

		return str_starts_with( strtolower( $locale ), 'es' ) ? $es : $en;
	}
}
