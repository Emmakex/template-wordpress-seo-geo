<?php
/**
 * Quality pass for automatic Corporate Home reconstruction.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge\Reset;

use WP_Error;
use WP_Post;

/**
 * Refines automatic Home classification using service identity, relevance and copy-quality signals.
 */
final class AutomaticHomeContentQualityKit {
	private AutomaticHomeContentKit $base;

	/**
	 * Construct the quality-aware automatic Home service.
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
		$this->base = new AutomaticHomeContentKit( $manifest, $builder, $kit, $hydrator );
	}

	/**
	 * Build a refined automatic proposal while preserving all accepted safety contracts.
	 *
	 * @return array<string,mixed>
	 */
	public function plan(): array {
		$plan = $this->base->plan();
		if ( true !== ( $plan['ready'] ?? false ) ) {
			return $plan;
		}

		$manifest  = $this->manifest->saved();
		$home      = $this->builder->plan();
		$source_id = (int) ( $home['source']['id'] ?? 0 );
		$source    = 0 < $source_id ? get_post( $source_id ) : null;
		if ( ! is_array( $manifest ) || ! $source instanceof WP_Post ) {
			return $plan;
		}

		$values    = is_array( $plan['values'] ?? null ) ? $plan['values'] : array();
		$plain     = $this->plain_text( (string) $source->post_content );
		$sentences = $this->sentences( $plain );
		$lead      = $this->prepare_lead( (string) ( $values['hero-lead'] ?? '' ) );
		$services  = $this->capabilities( $manifest, $source_id, $lead, $plain, $sentences );
		$process   = $this->process_steps( $sentences, $lead );

		$blockers = is_array( $plan['blockers'] ?? null ) ? $plan['blockers'] : array();
		if ( 3 > count( $services ) ) {
			$blockers[] = 'quality-three-capabilities-not-detected';
		}
		if ( 3 > count( $process ) ) {
			$blockers[] = 'quality-three-process-statements-not-detected';
		}
		$blockers = array_values( array_unique( $blockers ) );
		sort( $blockers );
		if ( array() !== $blockers ) {
			$plan['ready']    = false;
			$plan['blockers'] = $blockers;
			return $plan;
		}

		$values['hero-lead']          = $lead;
		$values['capabilities-intro'] = $this->capabilities_intro( $services, $lead );

		$contact_path = $this->link_url( $values['hero-primary-cta'] ?? null );
		foreach ( array_slice( $services, 0, 3 ) as $index => $service ) {
			$number = $index + 1;
			$path   = trim( (string) $service['path'] );
			if ( '' === $path ) {
				$path = $contact_path;
			}
			$values[ 'capability-' . $number . '-title' ] = (string) $service['title'];
			$values[ 'capability-' . $number . '-body' ]  = (string) $service['body'];
			$values[ 'capability-' . $number . '-link' ]  = array(
				'label' => $this->localized( 'Más información', 'Learn more' ),
				'url'   => $path,
			);
		}

		foreach ( array_slice( $process, 0, 3 ) as $index => $step ) {
			$number = $index + 1;
			$values[ 'process-' . $number . '-title' ] = (string) $step['title'];
			$values[ 'process-' . $number . '-body' ]  = (string) $step['body'];
		}
		$values['process-intro'] = $this->process_intro( $sentences, $lead );

		$plan['values']       = $values;
		$plan['home_heading'] = $this->home_heading( $source, $lead );
		$plan['quality_pass'] = 'content-classification-v2';
		$plan['detected']     = is_array( $plan['detected'] ?? null ) ? $plan['detected'] : array();
		$plan['detected']['capabilities'] = count( $services );
		$plan['detected']['capability_titles'] = array_values(
			array_map( static fn( array $service ): string => (string) $service['title'], $services )
		);

		return $plan;
	}

	/**
	 * Generate, persist and hydrate the refined Home proposal.
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
				'seo_geo_auto_home_quality_not_ready',
				'Automatic Home quality pass is blocked: ' . implode( ', ', is_array( $plan['blockers'] ?? null ) ? $plan['blockers'] : array() )
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

		return array(
			'schema_version' => 2,
			'mode'           => 'automatic-corporate-home-content-quality',
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
	 * Detect capability pages by title/path intent before considering their body copy.
	 *
	 * @param array  $manifest  Saved Rescue Manifest.
	 * @param int    $source_id Preserved front-page source ID.
	 * @param string $lead      Prepared hero summary.
	 * @param string $plain     Plain Home copy.
	 * @param array  $sentences Home sentence units.
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
			$path  = trim( (string) ( $item['path'] ?? '' ) );
			$body  = $this->resource_lead( $post, $manifest );
			$score = $this->capability_page_score( $title, $path, $body );
			if ( '' === $title || '' === $path || '' === $body || 0 >= $score ) {
				continue;
			}
			$candidates[] = array(
				'title' => $title,
				'body'  => $body,
				'path'  => $path,
				'score' => $score,
			);
		}

		usort( $candidates, static fn( array $a, array $b ): int => (int) $b['score'] <=> (int) $a['score'] );
		$result = array();
		foreach ( $candidates as $candidate ) {
			$result[] = array(
				'title' => (string) $candidate['title'],
				'body'  => (string) $candidate['body'],
				'path'  => (string) $candidate['path'],
			);
			$result = $this->unique_capabilities( $result );
			if ( 3 <= count( $result ) ) {
				return $result;
			}
		}

		$haystack = strtolower( $plain );
		foreach ( $this->ranked_categories( $lead, $plain ) as $category ) {
			if ( ! $this->contains_any( $haystack, $category['keywords'] ) ) {
				continue;
			}
			$body = $this->best_sentence( $sentences, $category['keywords'] );
			$result[] = array(
				'title' => $this->localized( $category['es'], $category['en'] ),
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

	/** Score a published page as a capability only when its identity itself carries service intent. */
	private function capability_page_score( string $title, string $path, string $body ): int {
		$identity_score = $this->service_score( $title . ' ' . $path );
		if ( 0 >= $identity_score ) {
			return 0;
		}

		return ( 4 * $identity_score ) + min( 30, $this->service_score( $body ) );
	}

	/**
	 * Build process steps from the strongest stage-specific statements, not the first keyword hit.
	 *
	 * @param array  $sentences Source sentence units.
	 * @param string $lead      Hero summary fallback.
	 * @phpstan-param list<string> $sentences
	 * @return list<array{title:string,body:string}>
	 */
	private function process_steps( array $sentences, string $lead ): array {
		$sets = array(
			array(
				'es'        => 'Entender',
				'en'        => 'Understand',
				'keywords'  => array( 'entender', 'diagn', 'analiz', 'contexto', 'objetivo', 'necesidad', 'understand', 'diagnose', 'research' ),
				'penalties' => array( 'prospect', 'compra', 'comprar', 'contactar', 'productos y servicios' ),
			),
			array(
				'es'        => 'Definir',
				'en'        => 'Define',
				'keywords'  => array( 'estrateg', 'defin', 'plan', 'diseñ', 'prioridad', 'design', 'strategy' ),
				'penalties' => array(),
			),
			array(
				'es'        => 'Medir y mejorar',
				'en'        => 'Measure and improve',
				'keywords'  => array( 'medir', 'resultado', 'mejor', 'optim', 'inspeccionar', 'limpiar', 'measure', 'result', 'improve' ),
				'penalties' => array(),
			),
		);

		$steps = array();
		foreach ( $sets as $set ) {
			$body = $this->best_sentence( $sentences, $set['keywords'], $set['penalties'], $steps );
			if ( '' === $body ) {
				continue;
			}
			$steps[] = array(
				'title' => $this->localized( $set['es'], $set['en'] ),
				'body'  => $body,
			);
		}

		$fallback = array(
			$this->localized( 'Entender', 'Understand' ),
			$this->localized( 'Definir', 'Define' ),
			$this->localized( 'Medir y mejorar', 'Measure and improve' ),
		);
		for ( $index = count( $steps ); $index < 3; ++$index ) {
			if ( '' === $lead ) {
				break;
			}
			$steps[] = array( 'title' => $fallback[ $index ], 'body' => $lead );
		}

		return array_slice( $steps, 0, 3 );
	}

	/**
	 * Choose the strongest rescued sentence for a semantic topic.
	 *
	 * @param array $sentences Source sentence units.
	 * @param array $keywords  Positive tokens.
	 * @param array $penalties Negative tokens.
	 * @param array $existing  Already selected process steps.
	 * @phpstan-param list<string> $sentences
	 * @phpstan-param list<string> $keywords
	 * @phpstan-param list<string> $penalties
	 * @phpstan-param list<array{title:string,body:string}> $existing
	 */
	private function best_sentence( array $sentences, array $keywords, array $penalties = array(), array $existing = array() ): string {
		$best       = '';
		$best_score = 0;
		foreach ( $sentences as $sentence ) {
			if ( $this->step_body_exists( $existing, $sentence ) ) {
				continue;
			}
			$lower = strtolower( $sentence );
			$score = 10 * $this->match_count( $lower, $keywords );
			$score -= 15 * $this->match_count( $lower, $penalties );
			if ( str_contains( $lower, 'proceso' ) || str_contains( $lower, 'método' ) || str_contains( $lower, 'metodo' ) ) {
				$score += 4;
			}
			if ( $score > $best_score ) {
				$best       = $sentence;
				$best_score = $score;
			}
		}

		return $best;
	}

	/** Build a stronger process introduction from method/process statements. */
	private function process_intro( array $sentences, string $lead ): string {
		$matched = $this->best_sentence( $sentences, array( 'proceso', 'metod', 'estrateg', 'analiz', 'process', 'method', 'strategy' ) );

		return '' !== $matched ? $matched : $lead;
	}

	/** Build capability introduction from selected capability identities instead of duplicating the Hero. */
	private function capabilities_intro( array $services, string $lead ): string {
		$titles = array_values( array_filter( array_map( static fn( array $service ): string => trim( (string) $service['title'] ), $services ) ) );
		if ( 3 > count( $titles ) ) {
			return $lead;
		}
		$last = array_pop( $titles );
		$list = implode( ', ', $titles ) . $this->localized( ' y ', ' and ' ) . $last;

		return $this->localized( 'Trabajamos en ', 'We work across ' ) . $list . '.';
	}

	/** Resolve a descriptive H1 for the generated draft. */
	private function home_heading( WP_Post $source, string $lead ): string {
		$source_title = trim( get_the_title( $source ) );
		$organization = trim( (string) get_bloginfo( 'name' ) );
		$generic      = array( 'home', 'inicio', 'portada', strtolower( $organization ) );
		if ( '' !== $source_title && ! in_array( strtolower( $source_title ), $generic, true ) ) {
			return wp_trim_words( $source_title, 16, '' );
		}
		$parts = preg_split( '/(?<=[.!?])\s+/u', $lead );
		$first = is_array( $parts ) ? trim( (string) ( $parts[0] ?? '' ) ) : trim( $lead );

		return '' !== $first ? wp_trim_words( rtrim( $first, ' .!?' ), 16, '' ) : $source_title;
	}

	/** Clean a rescued Hero lead and remove the duplicated organization prefix. */
	private function prepare_lead( string $text ): string {
		$text = preg_replace( '/\s+/u', ' ', trim( sanitize_text_field( $text ) ) ) ?? $text;
		$organization = trim( (string) get_bloginfo( 'name' ) );
		if ( '' !== $organization ) {
			$text = preg_replace( '/^' . preg_quote( $organization, '/' ) . '\s+/iu', '', $text, 1 ) ?? $text;
		}
		$text = preg_replace_callback(
			'/(^|[.!?]\s+)([\p{Ll}])/u',
			static fn( array $match ): string => $match[1] . mb_strtoupper( $match[2], 'UTF-8' ),
			$text
		) ?? $text;

		return trim( $text );
	}

	/** Resolve a concise page summary from rescued SEO, excerpt or authored copy. */
	private function resource_lead( WP_Post $post, array $manifest ): string {
		$item = $this->resource_by_id( $manifest, $post->ID );
		$seo  = is_array( $item['seo'] ?? null ) ? $item['seo'] : array();
		foreach ( array( '_yoast_wpseo_metadesc', 'rank_math_description' ) as $key ) {
			$value = is_scalar( $seo[ $key ] ?? null ) ? trim( (string) $seo[ $key ] ) : '';
			if ( '' !== $value && ! str_contains( $value, '%%' ) ) {
				return $this->prepare_lead( wp_trim_words( sanitize_text_field( $value ), 42, '…' ) );
			}
		}
		$excerpt = trim( wp_strip_all_tags( (string) $post->post_excerpt ) );
		if ( '' !== $excerpt ) {
			return $this->prepare_lead( wp_trim_words( $excerpt, 42, '…' ) );
		}
		$sentences = $this->sentences( $this->plain_text( (string) $post->post_content ) );

		return $this->prepare_lead( (string) ( $sentences[0] ?? '' ) );
	}

	/** Convert builder-heavy source content into authored plain text without executing it. */
	private function plain_text( string $content ): string {
		$content = preg_replace( '#<(script|style)\b[^>]*>.*?</\1>#is', ' ', $content ) ?? $content;
		$content = preg_replace( '/\[(?:\/)?[A-Za-z0-9_-]+(?:\s[^\]]*)?\]/u', ' ', $content ) ?? $content;
		$content = preg_replace( '/<(?:br|\/p|\/div|\/li|\/h[1-6])\b[^>]*>/i', "\n", $content ) ?? $content;
		$content = html_entity_decode( wp_strip_all_tags( $content ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$content = preg_replace( '/[\t ]+/u', ' ', $content ) ?? $content;
		$content = preg_replace( '/\s*\R\s*/u', "\n", $content ) ?? $content;

		return trim( preg_replace( '/\n{2,}/u', "\n", $content ) ?? $content );
	}

	/** Split rescued copy into sentence-sized retrieval units. */
	private function sentences( string $plain ): array {
		$split = preg_split( '/(?<=[.!?])\s+|\R+/u', $plain );
		$out   = array();
		foreach ( is_array( $split ) ? $split : array() as $part ) {
			$part = trim( sanitize_text_field( $part ) );
			if ( 35 <= strlen( $part ) ) {
				$out[] = wp_trim_words( $part, 42, '…' );
			}
		}

		return array_values( array_unique( $out ) );
	}

	/** Determine whether a page is structural rather than a capability page. */
	private function generic_page( array $item ): bool {
		$identity = strtolower( (string) ( $item['slug'] ?? '' ) . ' ' . (string) ( $item['title'] ?? '' ) );
		$tokens   = array(
			'contact', 'contacto', 'about', 'nosotros', 'empresa', 'privacy', 'privacidad', 'legal', 'aviso',
			'cookies', 'cookie', 'terms', 'terminos', 'actualidad', 'blog', 'insights', 'nuestras marcas',
			'our brands', 'trabaja con', 'trabaja-con', 'careers', 'empleo', 'equipo', 'team',
		);

		return $this->contains_any( $identity, $tokens );
	}

	/** Score service vocabulary. */
	private function service_score( string $text ): int {
		$score = 0;
		foreach ( $this->categories() as $category ) {
			$score += 10 * $this->match_count( $text, $category['keywords'] );
		}

		return $score;
	}

	/** Rank fallback capability categories by Hero evidence first, then wider Home evidence. */
	private function ranked_categories( string $lead, string $plain ): array {
		$ranked = array();
		foreach ( $this->categories() as $order => $category ) {
			$ranked[] = array(
				'category' => $category,
				'score'    => ( 30 * $this->match_count( $lead, $category['keywords'] ) ) + ( 5 * $this->match_count( $plain, $category['keywords'] ) ),
				'order'    => $order,
			);
		}
		usort(
			$ranked,
			static function ( array $a, array $b ): int {
				$score = (int) $b['score'] <=> (int) $a['score'];
				return 0 !== $score ? $score : (int) $a['order'] <=> (int) $b['order'];
			}
		);

		return array_values( array_map( static fn( array $item ): array => $item['category'], $ranked ) );
	}

	/** Return bounded semantic capability categories. */
	private function categories(): array {
		return array(
			array( 'es' => 'Marketing digital', 'en' => 'Digital marketing', 'keywords' => array( 'marketing', 'publicidad', 'ads', 'campaña', 'campaign' ) ),
			array( 'es' => 'SEO y visibilidad digital', 'en' => 'SEO and digital visibility', 'keywords' => array( 'seo', 'posicionamiento', 'search engine', 'visibilidad' ) ),
			array( 'es' => 'Investigación de mercado', 'en' => 'Market research', 'keywords' => array( 'investigación', 'investigacion', 'mercado', 'market research', 'competencia' ) ),
			array( 'es' => 'Datos e inteligencia de negocio', 'en' => 'Data and business intelligence', 'keywords' => array( 'datos', 'data', 'business intelligence', 'inteligencia de negocio', 'analítica', 'analitica', 'analytics' ) ),
			array( 'es' => 'Inteligencia artificial y automatización', 'en' => 'AI and automation', 'keywords' => array( 'inteligencia artificial', ' ia ', ' ai ', 'automatización', 'automatizacion', 'automation' ) ),
			array( 'es' => 'Desarrollo web y tecnología', 'en' => 'Web development and technology', 'keywords' => array( 'wordpress', 'web', 'desarrollo', 'development', 'software', 'tecnología', 'tecnologia', 'transformación digital', 'transformacion digital', 'digital transformation' ) ),
		);
	}

	/** Count distinct keyword hits. */
	private function match_count( string $text, array $keywords ): int {
		$text  = strtolower( $text );
		$count = 0;
		foreach ( $keywords as $keyword ) {
			if ( str_contains( $text, $keyword ) ) {
				++$count;
			}
		}

		return $count;
	}

	/** Determine whether a text contains any token. */
	private function contains_any( string $haystack, array $needles ): bool {
		foreach ( $needles as $needle ) {
			if ( str_contains( $haystack, $needle ) ) {
				return true;
			}
		}

		return false;
	}

	/** Remove duplicate capability titles. */
	private function unique_capabilities( array $items ): array {
		$seen = array();
		$out  = array();
		foreach ( $items as $item ) {
			$key = strtolower( trim( (string) $item['title'] ) );
			if ( '' === $key || isset( $seen[ $key ] ) ) {
				continue;
			}
			$seen[ $key ] = true;
			$out[] = $item;
		}

		return $out;
	}

	/** Determine whether one sentence was already used by a process step. */
	private function step_body_exists( array $steps, string $sentence ): bool {
		foreach ( $steps as $step ) {
			if ( $sentence === (string) $step['body'] ) {
				return true;
			}
		}

		return false;
	}

	/** Find one manifest resource by post ID. */
	private function resource_by_id( array $manifest, int $id ): ?array {
		$resources = is_array( $manifest['resources'] ?? null ) ? $manifest['resources'] : array();
		foreach ( $resources as $item ) {
			if ( is_array( $item ) && $id === (int) ( $item['id'] ?? 0 ) ) {
				return $item;
			}
		}

		return null;
	}

	/** Read one URL field from a Content Kit link value. */
	private function link_url( mixed $link ): string {
		return is_array( $link ) && is_scalar( $link['url'] ?? null ) ? trim( (string) $link['url'] ) : '';
	}

	/** Resolve a short label in the active preset language. */
	private function localized( string $es, string $en ): string {
		$locale = function_exists( 'seo_geo_theme_preset_locale' ) ? (string) \seo_geo_theme_preset_locale() : get_locale();

		return str_starts_with( strtolower( $locale ), 'es' ) ? $es : $en;
	}
}
