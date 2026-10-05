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
 * Refines automatic Home classification using rescued content quality signals.
 */
final class AutomaticHomeContentQualityKit {
	/**
	 * Base accepted automatic Home generator.
	 *
	 * @var AutomaticHomeContentKit
	 */
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
	 * Build a refined automatic proposal while preserving accepted safety contracts.
	 *
	 * @return array<string,mixed>
	 */
	public function plan(): array {
		$plan = $this->base->plan();
		if ( true !== ( $plan['ready'] ?? false ) ) {
			return $plan;
		}

		$home_plan = $this->builder->plan();
		$source_id = (int) ( $home_plan['source']['id'] ?? 0 );
		$source    = 0 < $source_id ? get_post( $source_id ) : null;
		if ( ! $source instanceof WP_Post ) {
			return $plan;
		}

		$values    = is_array( $plan['values'] ?? null ) ? $plan['values'] : array();
		$plain     = $this->plain_text( (string) $source->post_content );
		$sentences = $this->sentences( $plain );
		$lead      = $this->prepare_lead( (string) ( $values['hero-lead'] ?? '' ) );
		$services  = $this->refine_capabilities( $values, $lead, $plain, $sentences );

		if ( 3 > count( $services ) ) {
			$plan['ready']      = false;
			$plan['blockers'][] = 'quality-three-capabilities-not-detected';
			$plan['blockers']   = array_values( array_unique( $plan['blockers'] ) );
			return $plan;
		}

		$values['hero-lead']          = $lead;
		$values['capabilities-intro'] = $this->capabilities_intro( $services, $lead );

		$values = $this->apply_capabilities( $values, $services );
		$values = $this->improve_process( $values, $sentences, $lead );

		$plan['values']       = $values;
		$plan['home_heading'] = $this->home_heading( $source, $lead );
		$plan['quality_pass'] = 'content-classification-v2';
		$plan['detected']     = is_array( $plan['detected'] ?? null ) ? $plan['detected'] : array();

		$plan['detected']['capabilities']      = count( $services );
		$plan['detected']['capability_titles'] = array_values(
			array_map(
				static fn( array $service ): string => (string) $service['title'],
				$services
			)
		);

		return $plan;
	}

	/**
	 * Persist and hydrate the refined automatic Home proposal.
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
	 * Refine three capabilities from the base proposal and rescued Home vocabulary.
	 *
	 * @param array  $values    Base semantic values.
	 * @param string $lead      Prepared hero lead.
	 * @param string $plain     Plain rescued Home content.
	 * @param array  $sentences Rescued sentence units.
	 * @phpstan-param array<string,mixed> $values
	 * @phpstan-param list<string> $sentences
	 * @return list<array{title:string,body:string,path:string}>
	 */
	private function refine_capabilities( array $values, string $lead, string $plain, array $sentences ): array {
		$services = array();
		for ( $number = 1; $number <= 3; ++$number ) {
			$title = trim( (string) ( $values[ 'capability-' . $number . '-title' ] ?? '' ) );
			$body  = trim( (string) ( $values[ 'capability-' . $number . '-body' ] ?? '' ) );
			$path  = $this->link_url( $values[ 'capability-' . $number . '-link' ] ?? null );
			if ( '' === $title || '' === $body || $this->structural_capability( $title ) ) {
				continue;
			}
			$services[] = array(
				'title' => $title,
				'body'  => $body,
				'path'  => $path,
			);
		}
		$services = $this->unique_capabilities( $services );

		foreach ( $this->ranked_categories( $lead, $plain ) as $category ) {
			if ( 3 <= count( $services ) ) {
				break;
			}
			if ( ! $this->contains_any( strtolower( $plain ), $category['keywords'] ) ) {
				continue;
			}
			$body       = $this->best_sentence( $sentences, $category['keywords'] );
			$services[] = array(
				'title' => $this->localized( $category['es'], $category['en'] ),
				'body'  => '' !== $body ? $body : $lead,
				'path'  => '',
			);

			$services = $this->unique_capabilities( $services );
		}

		return array_slice( $services, 0, 3 );
	}

	/**
	 * Write refined capability values back into the semantic Content Kit.
	 *
	 * @param array $values   Current semantic values.
	 * @param array $services Refined capabilities.
	 * @phpstan-param array<string,mixed> $values
	 * @phpstan-param list<array{title:string,body:string,path:string}> $services
	 * @return array<string,mixed>
	 */
	private function apply_capabilities( array $values, array $services ): array {
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

		return $values;
	}

	/**
	 * Replace weak process copy with stronger stage-specific rescued statements.
	 *
	 * @param array  $values    Current semantic values.
	 * @param array  $sentences Rescued sentence units.
	 * @param string $lead      Hero summary fallback.
	 * @phpstan-param array<string,mixed> $values
	 * @phpstan-param list<string> $sentences
	 * @return array<string,mixed>
	 */
	private function improve_process( array $values, array $sentences, string $lead ): array {
		$sets = array(
			array(
				'title'     => $this->localized( 'Entender', 'Understand' ),
				'keywords'  => array( 'entender', 'diagn', 'analiz', 'contexto', 'objetivo', 'necesidad', 'understand', 'research' ),
				'penalties' => array( 'prospect', 'compra', 'comprar', 'contactar', 'productos y servicios' ),
			),
			array(
				'title'     => $this->localized( 'Definir', 'Define' ),
				'keywords'  => array( 'estrateg', 'defin', 'plan', 'diseñ', 'design', 'strategy' ),
				'penalties' => array(),
			),
			array(
				'title'     => $this->localized( 'Medir y mejorar', 'Measure and improve' ),
				'keywords'  => array( 'medir', 'resultado', 'mejor', 'optim', 'inspeccionar', 'limpiar', 'measure', 'result', 'improve' ),
				'penalties' => array(),
			),
		);

		$used = array();
		foreach ( $sets as $index => $set ) {
			$body = $this->best_sentence( $sentences, $set['keywords'], $set['penalties'], $used );
			if ( '' === $body ) {
				$body = $lead;
			}
			$number                                    = $index + 1;
			$values[ 'process-' . $number . '-title' ] = (string) $set['title'];
			$values[ 'process-' . $number . '-body' ]  = $body;
			$used[]                                    = $body;
		}

		$intro = $this->best_sentence(
			$sentences,
			array( 'proceso', 'metod', 'estrateg', 'analiz', 'process', 'method', 'strategy' )
		);

		$values['process-intro'] = '' !== $intro ? $intro : $lead;

		return $values;
	}

	/**
	 * Build capability introduction from selected capability identities.
	 *
	 * @param array  $services Refined capabilities.
	 * @param string $lead     Hero summary fallback.
	 * @phpstan-param list<array{title:string,body:string,path:string}> $services
	 */
	private function capabilities_intro( array $services, string $lead ): string {
		$titles = array_values(
			array_filter(
				array_map(
					static fn( array $service ): string => trim( (string) $service['title'] ),
					$services
				)
			)
		);
		if ( 3 > count( $titles ) ) {
			return $lead;
		}
		$last = (string) array_pop( $titles );
		$list = implode( ', ', $titles ) . $this->localized( ' y ', ' and ' ) . $last;

		return $this->localized( 'Trabajamos en ', 'We work across ' ) . $list . '.';
	}

	/**
	 * Resolve a descriptive generated Home title instead of a brand-only title.
	 *
	 * @param WP_Post $source Preserved source Home.
	 * @param string  $lead   Prepared hero summary.
	 */
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

	/**
	 * Clean a rescued Hero lead and remove a duplicated organization prefix.
	 *
	 * @param string $text Rescued lead copy.
	 */
	private function prepare_lead( string $text ): string {
		$text         = preg_replace( '/\s+/u', ' ', trim( sanitize_text_field( $text ) ) ) ?? $text;
		$organization = trim( (string) get_bloginfo( 'name' ) );
		if ( '' !== $organization ) {
			$text = preg_replace( '/^' . preg_quote( $organization, '/' ) . '\s+/iu', '', $text, 1 ) ?? $text;
		}

		return trim( $text );
	}

	/**
	 * Convert builder-heavy source content into plain authored text without executing it.
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

		return trim( preg_replace( '/\n{2,}/u', "\n", $content ) ?? $content );
	}

	/**
	 * Split rescued copy into sentence-sized retrieval units.
	 *
	 * @param string $plain Plain authored content.
	 * @return list<string>
	 */
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

	/**
	 * Choose the strongest rescued sentence for one semantic topic.
	 *
	 * @param array $sentences Source sentence units.
	 * @param array $keywords  Positive tokens.
	 * @param array $penalties Negative tokens.
	 * @param array $used      Already selected sentences.
	 * @phpstan-param list<string> $sentences
	 * @phpstan-param list<string> $keywords
	 * @phpstan-param list<string> $penalties
	 * @phpstan-param list<string> $used
	 */
	private function best_sentence( array $sentences, array $keywords, array $penalties = array(), array $used = array() ): string {
		$best       = '';
		$best_score = 0;
		foreach ( $sentences as $sentence ) {
			if ( in_array( $sentence, $used, true ) ) {
				continue;
			}
			$lower = strtolower( $sentence );

			$score = 10 * $this->match_count( $lower, $keywords );
			$score -= 15 * $this->match_count( $lower, $penalties );
			if ( $score > $best_score ) {
				$best       = $sentence;
				$best_score = $score;
			}
		}

		return $best;
	}

	/**
	 * Determine whether a capability title is structural rather than a service.
	 *
	 * @param string $title Candidate capability title.
	 */
	private function structural_capability( string $title ): bool {
		return $this->contains_any(
			strtolower( $title ),
			array(
				'nuestras marcas',
				'our brands',
				'trabaja con',
				'careers',
				'equipo',
				'team',
			)
		);
	}

	/**
	 * Rank fallback semantic categories by Hero and wider Home evidence.
	 *
	 * @param string $lead  Hero summary.
	 * @param string $plain Full plain Home copy.
	 * @return list<array{es:string,en:string,keywords:list<string>}>
	 */
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

		return array_values(
			array_map(
				static fn( array $item ): array => $item['category'],
				$ranked
			)
		);
	}

	/**
	 * Return bounded semantic capability categories.
	 *
	 * @return list<array{es:string,en:string,keywords:list<string>}>
	 */
	private function categories(): array {
		return array(
			array(
				'es'       => 'Marketing digital',
				'en'       => 'Digital marketing',
				'keywords' => array( 'marketing', 'publicidad', 'ads', 'campaña', 'campaign' ),
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
				'es'       => 'SEO y visibilidad digital',
				'en'       => 'SEO and digital visibility',
				'keywords' => array( 'seo', 'posicionamiento', 'search engine', 'visibilidad' ),
			),
			array(
				'es'       => 'Desarrollo web y tecnología',
				'en'       => 'Web development and technology',
				'keywords' => array( 'wordpress', 'web', 'desarrollo', 'development', 'software', 'tecnología', 'tecnologia', 'transformación digital', 'transformacion digital' ),
			),
		);
	}

	/**
	 * Count distinct keyword hits.
	 *
	 * @param string $text     Searchable text.
	 * @param array  $keywords Keywords to count.
	 * @phpstan-param list<string> $keywords
	 */
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
	 * Remove duplicate capability titles while preserving first occurrence.
	 *
	 * @param array $items Capability candidates.
	 * @phpstan-param list<array{title:string,body:string,path:string}> $items
	 * @return list<array{title:string,body:string,path:string}>
	 */
	private function unique_capabilities( array $items ): array {
		$seen = array();
		$out  = array();
		foreach ( $items as $item ) {
			$key = strtolower( trim( (string) $item['title'] ) );
			if ( '' === $key || isset( $seen[ $key ] ) ) {
				continue;
			}
			$seen[ $key ] = true;
			$out[]        = $item;
		}

		return $out;
	}

	/**
	 * Read one URL field from a Content Kit link value.
	 *
	 * @param mixed $link Semantic link value.
	 */
	private function link_url( mixed $link ): string {
		return is_array( $link ) && is_scalar( $link['url'] ?? null ) ? trim( (string) $link['url'] ) : '';
	}

	/**
	 * Resolve a short label in the active preset language.
	 *
	 * @param string $es Spanish text.
	 * @param string $en English text.
	 */
	private function localized( string $es, string $en ): string {
		$locale = function_exists( 'seo_geo_theme_preset_locale' ) ? (string) \seo_geo_theme_preset_locale() : get_locale();

		return str_starts_with( strtolower( $locale ), 'es' ) ? $es : $en;
	}
}
