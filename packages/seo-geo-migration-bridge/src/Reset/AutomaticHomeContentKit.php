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
	/** @var list<string> */
	private const GENERIC_PAGE_TOKENS = array(
		'contact', 'contacto', 'about', 'nosotros', 'empresa', 'privacy', 'privacidad',
		'legal', 'aviso', 'cookies', 'cookie', 'terms', 'terminos', 'actualidad', 'blog', 'insights',
	);

	/**
	 * Construct the automatic content service.
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
		$process   = $this->process_steps( $plain, $sentences, $lead );

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
			$organization = trim( (string) get_bloginfo( 'name' ) );
			if ( '' === $organization && $source instanceof WP_Post ) {
				$organization = trim( get_the_title( $source ) );
			}

			$values = array(
				'hero-eyebrow'        => '' !== $organization ? $organization : $this->text( 'Soluciones digitales', 'Digital solutions' ),
				'hero-lead'           => $lead,
				'hero-primary-cta'    => array(
					'label' => $this->text( 'Contactar', 'Contact us' ),
					'url'   => (string) $contact['path'],
				),
				'capabilities-heading' => $this->text( 'Servicios y capacidades', 'Services and capabilities' ),
				'capabilities-intro'   => $lead,
				'process-heading'      => $this->text( 'Cómo trabajamos', 'How we work' ),
				'process-intro'        => $this->process_intro( $plain, $lead ),
				'insights-heading'     => $this->text( 'Actualidad e ideas', 'Insights and ideas' ),
				'insights-intro'       => $this->insights_intro( $manifest, $organization ),
				'final-cta-heading'    => $this->text( '¿Hablamos?', 'Let’s talk' ),
				'final-cta-body'       => $this->text(
					'Cuéntanos qué necesitas y revisaremos el contexto antes de definir los siguientes pasos.',
					'Tell us what you need and we will review the context before defining the next steps.'
				),
				'final-cta-button'     => array(
					'label' => $this->text( 'Contactar', 'Contact us' ),
					'url'   => (string) $contact['path'],
				),
			);

			if ( is_array( $about ) && '' !== (string) ( $about['path'] ?? '' ) ) {
				$values['hero-secondary-cta'] = array(
					'label' => $this->text( 'Conócenos', 'About us' ),
					'url'   => (string) $about['path'],
				);
			}

			foreach ( array_slice( $services, 0, 3 ) as $index => $service ) {
				$number = $index + 1;
				$values[ 'capability-' . $number . '-title' ] = (string) $service['title'];
				$values[ 'capability-' . $number . '-body' ]  = (string) $service['body'];
				$values[ 'capability-' . $number . '-link' ]  = array(
					'label' => $this->text( 'Más información', 'Learn more' ),
					'url'   => (string) ( $service['path'] ?? $contact['path'] ),
				);
			}

			foreach ( array_slice( $process, 0, 3 ) as $index => $step ) {
				$number = $index + 1;
				$values[ 'process-' . $number . '-title' ] = (string) $step['title'];
				$values[ 'process-' . $number . '-body' ]  = (string) $step['body'];
			}
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
				'sentences'       => count( $sentences ),
				'capabilities'    => count( $services ),
				'contact_path'    => is_array( $contact ) ? (string) ( $contact['path'] ?? '' ) : '',
				'about_path'      => is_array( $about ) ? (string) ( $about['path'] ?? '' ) : '',
			),
			'safety'          => array(
				'legacy_layout_reused'       => false,
				'legacy_runtime_executed'    => false,
				'evidence_auto_verified'     => false,
				'source_post_mutation'       => false,
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
	 * Convert builder-heavy stored content into plain authored text without executing legacy shortcodes.
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

	/** @return list<string> */
	private function sentences( string $plain ): array {
		if ( '' === $plain ) {
			return array();
		}
		$parts = preg_split( '/(?<=[.!?])\s+|\R+/u', $plain ) ?: array();
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
	 * @param array<string,mixed>|null $manifest Saved rescue manifest.
	 */
	private function lead( WP_Post $source, ?array $manifest ): string {
		$resource = is_array( $manifest ) ? $this->resource_by_id( $manifest, $source->ID ) : null;
		$seo      = is_array( $resource['seo'] ?? null ) ? $resource['seo'] : array();
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
	 * Detect three service/capability candidates from related pages and source vocabulary.
	 *
	 * @param array<string,mixed> $manifest Saved rescue manifest.
	 * @param list<string>        $sentences Source sentences.
	 * @return list<array{title:string,body:string,path:string}>
	 */
	private function capabilities( array $manifest, int $source_id, string $lead, string $plain, array $sentences ): array {
		$candidates = array();
		$resources  = is_array( $manifest['resources'] ?? null ) ? $manifest['resources'] : array();
		foreach ( $resources as $resource ) {
			if ( ! is_array( $resource ) || 'page' !== ( $resource['post_type'] ?? null ) || 'publish' !== ( $resource['status'] ?? null ) ) {
				continue;
			}
			$id = (int) ( $resource['id'] ?? 0 );
			if ( $id === $source_id || $this->generic_page( $resource ) ) {
				continue;
			}
			$post = get_post( $id );
			if ( ! $post instanceof WP_Post ) {
				continue;
			}
			$title = trim( get_the_title( $post ) );
			$path  = (string) ( $resource['path'] ?? '' );
			$body  = $this->lead( $post, $manifest );
			if ( '' === $title || '' === $path || '' === $body ) {
				continue;
			}
			$score = $this->service_score( $title . ' ' . $body );
			if ( 0 >= $score ) {
				continue;
			}
			$candidates[] = array( 'title' => $title, 'body' => $body, 'path' => $path, 'score' => $score );
		}
		usort( $candidates, static fn( array $a, array $b ): int => (int) $b['score'] <=> (int) $a['score'] );

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

		$categories = $this->keyword_categories();
		$haystack   = strtolower( $plain );
		foreach ( $categories as $category ) {
			$matched = false;
			foreach ( $category['keywords'] as $keyword ) {
				if ( str_contains( $haystack, $keyword ) ) {
					$matched = true;
					break;
				}
			}
			if ( ! $matched ) {
				continue;
			}
			$body = $this->sentence_for_keywords( $sentences, $category['keywords'] );
			$result[] = array(
				'title' => $this->text( $category['es'], $category['en'] ),
				'body'  => '' !== $body ? $body : $lead,
				'path'  => '',
			);
			$result = $this->unique_capabilities( $result );
			if ( 3 <= count( $result ) ) {
				break;
			}
		}

		return array_slice( $result, 0, 3 );
	}

	/** @return list<array{title:string,body:string}> */
	private function process_steps( string $plain, array $sentences, string $lead ): array {
		$sets = array(
			array( 'es' => 'Entender', 'en' => 'Understand', 'keywords' => array( 'conocer', 'entender', 'analiz', 'diagn', 'understand', 'discover', 'research' ) ),
			array( 'es' => 'Definir', 'en' => 'Define', 'keywords' => array( 'estrateg', 'defin', 'plan', 'diseñ', 'design', 'strategy' ) ),
			array( 'es' => 'Medir y mejorar', 'en' => 'Measure and improve', 'keywords' => array( 'medir', 'resultado', 'mejor', 'optim', 'measure', 'result', 'improve' ) ),
		);
		$steps = array();
		foreach ( $sets as $set ) {
			$body = $this->sentence_for_keywords( $sentences, $set['keywords'] );
			if ( '' !== $body ) {
				$steps[] = array( 'title' => $this->text( $set['es'], $set['en'] ), 'body' => $body );
			}
		}
		foreach ( $sentences as $sentence ) {
			if ( 3 <= count( $steps ) ) {
				break;
			}
			$exists = false;
			foreach ( $steps as $step ) {
				if ( $step['body'] === $sentence ) {
					$exists = true;
					break;
				}
			}
			if ( ! $exists ) {
				$steps[] = array(
					'title' => $this->text( 'Paso ' . ( count( $steps ) + 1 ), 'Step ' . ( count( $steps ) + 1 ) ),
					'body'  => $sentence,
				);
			}
		}
		if ( 3 > count( $steps ) && '' !== $lead && '' !== $plain ) {
			while ( 3 > count( $steps ) ) {
				$steps[] = array(
					'title' => $this->text( 'Paso ' . ( count( $steps ) + 1 ), 'Step ' . ( count( $steps ) + 1 ) ),
					'body'  => $lead,
				);
			}
		}

		return array_slice( $steps, 0, 3 );
	}

	private function process_intro( string $plain, string $lead ): string {
		$sentences = $this->sentences( $plain );
		$matched   = $this->sentence_for_keywords( $sentences, array( 'proceso', 'metod', 'estrateg', 'analiz', 'process', 'method', 'strategy' ) );

		return '' !== $matched ? $matched : $lead;
	}

	private function insights_intro( array $manifest, string $organization ): string {
		$posts = (int) ( $manifest['counts']['posts'] ?? 0 );
		$name  = '' !== $organization ? $organization : $this->text( 'la organización', 'the organization' );
		if ( 0 < $posts ) {
			return $this->text(
				'Consulta los contenidos publicados por ' . $name . ' sobre sus áreas de trabajo y conocimiento.',
				'Explore content published by ' . $name . ' about its areas of work and expertise.'
			);
		}

		return $this->text( 'Ideas y recursos relacionados con nuestras áreas de trabajo.', 'Ideas and resources related to our areas of work.' );
	}

	/** @return array<string,mixed>|null */
	private function contact_target( array $manifest ): ?array {
		return $this->target_page( $manifest, array( 'contact', 'contacto' ) );
	}

	/** @return array<string,mixed>|null */
	private function about_target( array $manifest ): ?array {
		return $this->target_page( $manifest, array( 'about', 'nosotros', 'empresa', 'quienes-somos', 'quienes somos' ) );
	}

	/**
	 * @param array<string,mixed> $manifest Saved rescue manifest.
	 * @param list<string>        $tokens   Matching title/slug tokens.
	 * @return array<string,mixed>|null
	 */
	private function target_page( array $manifest, array $tokens ): ?array {
		$resources = is_array( $manifest['resources'] ?? null ) ? $manifest['resources'] : array();
		foreach ( $resources as $resource ) {
			if ( ! is_array( $resource ) || 'page' !== ( $resource['post_type'] ?? null ) || 'publish' !== ( $resource['status'] ?? null ) ) {
				continue;
			}
			$identity = strtolower( (string) ( $resource['slug'] ?? '' ) . ' ' . (string) ( $resource['title'] ?? '' ) );
			foreach ( $tokens as $token ) {
				if ( str_contains( $identity, $token ) && '' !== (string) ( $resource['path'] ?? '' ) ) {
					return $resource;
				}
			}
		}

		return null;
	}

	/** @param array<string,mixed> $resource */
	private function generic_page( array $resource ): bool {
		$identity = strtolower( (string) ( $resource['slug'] ?? '' ) . ' ' . (string) ( $resource['title'] ?? '' ) );
		foreach ( self::GENERIC_PAGE_TOKENS as $token ) {
			if ( str_contains( $identity, $token ) ) {
				return true;
			}
		}

		return false;
	}

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

	/** @return list<array{es:string,en:string,keywords:list<string>}> */
	private function keyword_categories(): array {
		return array(
			array( 'es' => 'Marketing digital', 'en' => 'Digital marketing', 'keywords' => array( 'marketing', 'publicidad', 'ads', 'campaña', 'campaign' ) ),
			array( 'es' => 'SEO y visibilidad digital', 'en' => 'SEO and digital visibility', 'keywords' => array( 'seo', 'posicionamiento', 'search engine', 'visibilidad' ) ),
			array( 'es' => 'Investigación de mercado', 'en' => 'Market research', 'keywords' => array( 'investigación', 'investigacion', 'mercado', 'market research', 'competencia' ) ),
			array( 'es' => 'Datos e inteligencia de negocio', 'en' => 'Data and business intelligence', 'keywords' => array( 'datos', 'data', 'business intelligence', 'inteligencia de negocio', 'analítica', 'analitica', 'analytics' ) ),
			array( 'es' => 'Inteligencia artificial y automatización', 'en' => 'AI and automation', 'keywords' => array( 'inteligencia artificial', ' ia ', ' ai ', 'automatización', 'automatizacion', 'automation' ) ),
			array( 'es' => 'Desarrollo web y tecnología', 'en' => 'Web development and technology', 'keywords' => array( 'wordpress', 'web', 'desarrollo', 'development', 'software', 'tecnología', 'tecnologia' ) ),
		);
	}

	/**
	 * @param list<string> $sentences Source sentences.
	 * @param list<string> $keywords  Search keywords.
	 */
	private function sentence_for_keywords( array $sentences, array $keywords ): string {
		foreach ( $sentences as $sentence ) {
			$lower = strtolower( $sentence );
			foreach ( $keywords as $keyword ) {
				if ( str_contains( $lower, $keyword ) ) {
					return $sentence;
				}
			}
		}

		return '';
	}

	/**
	 * @param list<array{title:string,body:string,path:string}> $items Capability candidates.
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

	/** @return array<string,mixed>|null */
	private function resource_by_id( array $manifest, int $id ): ?array {
		$resources = is_array( $manifest['resources'] ?? null ) ? $manifest['resources'] : array();
		foreach ( $resources as $resource ) {
			if ( is_array( $resource ) && $id === (int) ( $resource['id'] ?? 0 ) ) {
				return $resource;
			}
		}

		return null;
	}

	private function text( string $es, string $en ): string {
		$locale = function_exists( 'seo_geo_theme_preset_locale' ) ? (string) \seo_geo_theme_preset_locale() : get_locale();

		return str_starts_with( strtolower( $locale ), 'es' ) ? $es : $en;
	}
}
