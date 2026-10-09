<?php
/**
 * Research Theme-owned Home renderer.
 *
 * @package SeoGeoTheme
 */

declare(strict_types=1);

namespace SeoGeo\Theme\Strategic;

use WP_Post;
use WP_Query;

/**
 * Renders research-home-v1 as semantic server HTML without making Gutenberg
 * responsible for the public composition.
 */
final class ResearchHomeRenderer {
	/**
	 * Build the Research Home renderer.
	 *
	 * @param ResearchHomeModelResolver $resolver Structured model resolver.
	 */
	public function __construct( private readonly ResearchHomeModelResolver $resolver ) {}

	/**
	 * Determine whether this page has a complete Research Home model.
	 *
	 * @param WP_Post $post Source WordPress page.
	 */
	public function supports( WP_Post $post ): bool {
		return $this->resolver->supports( $post );
	}

	/**
	 * Render one Research Home.
	 *
	 * @param WP_Post $post Source WordPress page.
	 * @return string Theme-owned HTML. Model values are escaped at composition.
	 */
	public function render( WP_Post $post ): string {
		$model = $this->resolver->resolve( $post );
		if ( null === $model || ! isset( $model['slots'] ) || ! is_array( $model['slots'] ) ) {
			return '';
		}

		$slots = $model['slots'];
		$is_es = function_exists( 'seo_geo_theme_preset_locale' ) && 'es_ES' === seo_geo_theme_preset_locale();

		return '<main id="seo-geo-main" class="seo-geo-research-home" tabindex="-1">'
			. $this->render_hero( $slots, $model, $is_es )
			. $this->render_research_lines( $slots, $model, $is_es )
			. $this->render_outputs( $slots, $model, $is_es )
			. $this->render_identifiers( $slots, $model, $is_es )
			. $this->render_latest_insights( $slots, $is_es )
			. $this->render_final_cta( $slots )
			. '</main>';
	}

	/**
	 * Render the researcher identity hero.
	 *
	 * @param array<string,mixed> $slots Semantic slots.
	 * @param array<string,mixed> $model Structured model.
	 * @param bool                $is_es Whether the active locale is Spanish.
	 */
	private function render_hero( array $slots, array $model, bool $is_es ): string {
		$cta = $this->link( $slots, 'hero-primary-cta' );
		if ( null === $cta ) {
			return '';
		}

		$identifiers = isset( $model['academic_identifiers'] ) && is_array( $model['academic_identifiers'] ) ? $model['academic_identifiers'] : array();
		$verified    = '';
		foreach ( array_slice( $identifiers, 0, 3 ) as $identifier ) {
			if ( ! is_array( $identifier ) ) {
				continue;
			}

			$label = isset( $identifier['label'] ) && is_string( $identifier['label'] ) ? $identifier['label'] : '';
			$value = isset( $identifier['value'] ) && is_string( $identifier['value'] ) ? $identifier['value'] : '';
			$url   = isset( $identifier['url'] ) && is_string( $identifier['url'] ) ? $identifier['url'] : '';
			if ( '' === $label || '' === $value ) {
				continue;
			}

			$content = '<span>' . esc_html( $label ) . '</span><strong>' . esc_html( $value ) . '</strong>';
			if ( '' !== $url ) {
				$verified .= '<a class="seo-geo-research-verified-chip" href="' . esc_url( $url ) . '" rel="me">' . $content . '</a>';
			} else {
				$verified .= '<span class="seo-geo-research-verified-chip">' . $content . '</span>';
			}
		}

		$signal = '<div class="seo-geo-research-hero__signal" aria-hidden="true">'
			. '<span class="seo-geo-research-orbit seo-geo-research-orbit--one"></span>'
			. '<span class="seo-geo-research-orbit seo-geo-research-orbit--two"></span>'
			. '<span class="seo-geo-research-orbit seo-geo-research-orbit--three"></span>'
			. '<span class="seo-geo-research-signal__core">R</span>'
			. '</div>';

		return '<section class="seo-geo-research-hero" aria-labelledby="seo-geo-research-title">'
			. '<div class="seo-geo-research-shell seo-geo-research-hero__grid">'
			. '<div class="seo-geo-research-hero__copy">'
			. '<p class="seo-geo-research-eyebrow">' . esc_html( $this->text( $slots, 'hero-eyebrow' ) ) . '</p>'
			. '<h1 id="seo-geo-research-title">' . esc_html( $this->text( $slots, 'researcher-name' ) ) . '</h1>'
			. '<p class="seo-geo-research-headline">' . esc_html( $this->text( $slots, 'researcher-headline' ) ) . '</p>'
			. '<p class="seo-geo-research-lead">' . esc_html( $this->text( $slots, 'hero-lead' ) ) . '</p>'
			. '<p class="seo-geo-research-actions"><a class="seo-geo-research-button" href="' . esc_url( $cta['url'] ) . '">' . esc_html( $cta['label'] ) . '</a></p>'
			. ( '' === $verified ? '' : '<div class="seo-geo-research-verified" aria-label="' . esc_attr( $is_es ? 'Identificadores académicos verificados' : 'Verified academic identifiers' ) . '">' . $verified . '</div>' )
			. '</div>'
			. '<div class="seo-geo-research-hero__visual">' . $signal . '</div>'
			. '</div></section>';
	}

	/**
	 * Render authored research lines.
	 *
	 * @param array<string,mixed> $slots Semantic slots.
	 * @param array<string,mixed> $model Structured model.
	 * @param bool                $is_es Whether the active locale is Spanish.
	 */
	private function render_research_lines( array $slots, array $model, bool $is_es ): string {
		$items = isset( $model['research_lines'] ) && is_array( $model['research_lines'] ) ? $model['research_lines'] : array();
		$cards = '';
		$index = 0;
		foreach ( $items as $item ) {
			if ( ! is_array( $item ) || ! isset( $item['title'] ) || ! is_string( $item['title'] ) ) {
				continue;
			}
			++$index;
			$summary = isset( $item['summary'] ) && is_string( $item['summary'] ) ? $item['summary'] : '';
			$url     = isset( $item['url'] ) && is_string( $item['url'] ) ? $item['url'] : '';
			$title   = esc_html( $item['title'] );
			$title   = '' !== $url ? '<a href="' . esc_url( $url ) . '">' . $title . '</a>' : $title;

			$cards .= '<article class="seo-geo-research-line-card">'
				. '<p class="seo-geo-research-card-index">' . esc_html( str_pad( (string) $index, 2, '0', STR_PAD_LEFT ) ) . '</p>'
				. '<h3>' . $title . '</h3>'
				. ( '' === $summary ? '' : '<p>' . esc_html( $summary ) . '</p>' )
				. '</article>';
		}

		return '<section class="seo-geo-research-section seo-geo-research-section--lines" aria-labelledby="seo-geo-research-lines-title">'
			. '<div class="seo-geo-research-shell">'
			. $this->section_heading( $is_es ? 'AGENDA' : 'RESEARCH AGENDA', 'seo-geo-research-lines-title', $this->text( $slots, 'research-lines-heading' ), $this->text( $slots, 'research-lines-intro' ) )
			. ( '' === $cards ? '' : '<div class="seo-geo-research-line-grid">' . $cards . '</div>' )
			. '</div></section>';
	}

	/**
	 * Render selected real outputs with only explicitly supplied metadata.
	 *
	 * @param array<string,mixed> $slots Semantic slots.
	 * @param array<string,mixed> $model Structured model.
	 * @param bool                $is_es Whether the active locale is Spanish.
	 */
	private function render_outputs( array $slots, array $model, bool $is_es ): string {
		$items = isset( $model['selected_outputs'] ) && is_array( $model['selected_outputs'] ) ? $model['selected_outputs'] : array();
		$cards = '';
		foreach ( $items as $item ) {
			if ( ! is_array( $item ) || ! isset( $item['title'] ) || ! is_string( $item['title'] ) ) {
				continue;
			}

			$url   = isset( $item['url'] ) && is_string( $item['url'] ) ? $item['url'] : '';
			$title = esc_html( $item['title'] );
			$title = '' !== $url ? '<a href="' . esc_url( $url ) . '">' . $title . '</a>' : $title;
			$meta  = array();
			foreach ( array( 'type', 'status', 'year', 'venue', 'identifier' ) as $field ) {
				if ( isset( $item[ $field ] ) && is_string( $item[ $field ] ) && '' !== trim( $item[ $field ] ) ) {
					$meta[] = esc_html( $item[ $field ] );
				}
			}

			$cards .= '<article class="seo-geo-research-output-card">'
				. '<div class="seo-geo-research-output-card__mark" aria-hidden="true"></div>'
				. '<div><h3>' . $title . '</h3>'
				. ( array() === $meta ? '' : '<p class="seo-geo-research-output-meta">' . implode( '<span aria-hidden="true">·</span>', $meta ) . '</p>' )
				. '</div></article>';
		}

		return '<section class="seo-geo-research-section seo-geo-research-section--outputs" aria-labelledby="seo-geo-research-outputs-title">'
			. '<div class="seo-geo-research-shell">'
			. $this->section_heading( $is_es ? 'RESULTADOS' : 'SELECTED OUTPUTS', 'seo-geo-research-outputs-title', $this->text( $slots, 'selected-outputs-heading' ), $this->text( $slots, 'selected-outputs-intro' ) )
			. ( '' === $cards ? '' : '<div class="seo-geo-research-output-list">' . $cards . '</div>' )
			. '</div></section>';
	}

	/**
	 * Render verified identifiers only; unverified values never reach the public
	 * surface through this collection.
	 *
	 * @param array<string,mixed> $slots Semantic slots.
	 * @param array<string,mixed> $model Structured model.
	 * @param bool                $is_es Whether the active locale is Spanish.
	 */
	private function render_identifiers( array $slots, array $model, bool $is_es ): string {
		$items = isset( $model['academic_identifiers'] ) && is_array( $model['academic_identifiers'] ) ? $model['academic_identifiers'] : array();
		if ( array() === $items ) {
			return '';
		}

		$list = '';
		foreach ( $items as $item ) {
			if ( ! is_array( $item ) || ! isset( $item['label'], $item['value'] ) || ! is_string( $item['label'] ) || ! is_string( $item['value'] ) ) {
				continue;
			}
			$url   = isset( $item['url'] ) && is_string( $item['url'] ) ? $item['url'] : '';
			$value = '<span>' . esc_html( $item['label'] ) . '</span><strong>' . esc_html( $item['value'] ) . '</strong>';
			$list .= '<li>' . ( '' !== $url ? '<a href="' . esc_url( $url ) . '" rel="me">' . $value . '</a>' : $value ) . '</li>';
		}

		if ( '' === $list ) {
			return '';
		}

		return '<section class="seo-geo-research-section seo-geo-research-section--identifiers" aria-labelledby="seo-geo-research-identifiers-title">'
			. '<div class="seo-geo-research-shell seo-geo-research-identifiers-grid">'
			. '<div><p class="seo-geo-research-eyebrow">' . esc_html( $is_es ? 'IDENTIDAD ACADÉMICA' : 'ACADEMIC IDENTITY' ) . '</p>'
			. '<h2 id="seo-geo-research-identifiers-title">' . esc_html( $this->text( $slots, 'academic-identifiers-heading' ) ) . '</h2></div>'
			. '<ul class="seo-geo-research-identifier-list">' . $list . '</ul>'
			. '</div></section>';
	}

	/**
	 * Render the latest three published native WordPress posts.
	 *
	 * @param array<string,mixed> $slots Semantic slots.
	 * @param bool                $is_es Whether the active locale is Spanish.
	 */
	private function render_latest_insights( array $slots, bool $is_es ): string {
		$query = new WP_Query(
			array(
				'post_type'              => 'post',
				'post_status'            => 'publish',
				'posts_per_page'         => 3,
				'ignore_sticky_posts'    => true,
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);

		$items = '';
		foreach ( $query->posts as $article ) {
			if ( ! $article instanceof WP_Post ) {
				continue;
			}
			$url = get_permalink( $article );
			if ( '' === $url ) {
				continue;
			}

			$excerpt = wp_trim_words( trim( wp_strip_all_tags( get_the_excerpt( $article ), true ) ), 24, '…' );
			$items  .= '<article class="seo-geo-research-insight-card">'
				. '<time datetime="' . esc_attr( get_the_date( DATE_W3C, $article ) ) . '">' . esc_html( get_the_date( '', $article ) ) . '</time>'
				. '<h3><a href="' . esc_url( $url ) . '">' . esc_html( get_the_title( $article ) ) . '</a></h3>'
				. ( '' === $excerpt ? '' : '<p>' . esc_html( $excerpt ) . '</p>' )
				. '</article>';
		}
		wp_reset_postdata();

		return '<section class="seo-geo-research-section seo-geo-research-section--insights" aria-labelledby="seo-geo-research-insights-title">'
			. '<div class="seo-geo-research-shell">'
			. '<div class="seo-geo-research-section-heading"><p class="seo-geo-research-eyebrow">' . esc_html( $is_es ? 'NOTAS Y ANÁLISIS' : 'NOTES & ANALYSIS' ) . '</p>'
			. '<h2 id="seo-geo-research-insights-title">' . esc_html( $this->text( $slots, 'latest-insights-heading' ) ) . '</h2></div>'
			. ( '' === $items ? '' : '<div class="seo-geo-research-insight-grid">' . $items . '</div>' )
			. '</div></section>';
	}

	/**
	 * Render the closing contact/collaboration CTA.
	 *
	 * @param array<string,mixed> $slots Semantic slots.
	 */
	private function render_final_cta( array $slots ): string {
		$link = $this->link( $slots, 'final-cta-button' );
		if ( null === $link ) {
			return '';
		}

		return '<section class="seo-geo-research-final-cta" aria-labelledby="seo-geo-research-final-cta-title">'
			. '<div class="seo-geo-research-shell seo-geo-research-final-cta__inner">'
			. '<div><h2 id="seo-geo-research-final-cta-title">' . esc_html( $this->text( $slots, 'final-cta-heading' ) ) . '</h2>'
			. '<p>' . esc_html( $this->text( $slots, 'final-cta-body' ) ) . '</p></div>'
			. '<p><a class="seo-geo-research-button seo-geo-research-button--light" href="' . esc_url( $link['url'] ) . '">' . esc_html( $link['label'] ) . '</a></p>'
			. '</div></section>';
	}

	/**
	 * Render a reusable section heading.
	 *
	 * @param string $eyebrow Compact section label.
	 * @param string $id      Heading DOM identifier.
	 * @param string $heading Visible section heading.
	 * @param string $intro   Supporting introduction.
	 */
	private function section_heading( string $eyebrow, string $id, string $heading, string $intro ): string {
		return '<header class="seo-geo-research-section-heading">'
			. '<p class="seo-geo-research-eyebrow">' . esc_html( $eyebrow ) . '</p>'
			. '<h2 id="' . esc_attr( $id ) . '">' . esc_html( $heading ) . '</h2>'
			. '<p>' . esc_html( $intro ) . '</p>'
			. '</header>';
	}

	/**
	 * Read one validated text slot.
	 *
	 * @param array<string,mixed> $slots Semantic slots.
	 * @param string              $key   Slot key.
	 */
	private function text( array $slots, string $key ): string {
		$value = $slots[ $key ] ?? '';
		return is_string( $value ) ? $value : '';
	}

	/**
	 * Read one validated link slot.
	 *
	 * @param array<string,mixed> $slots Semantic slots.
	 * @param string              $key   Slot key.
	 * @return array{label:string,url:string}|null
	 */
	private function link( array $slots, string $key ): ?array {
		$value = $slots[ $key ] ?? null;
		if ( ! is_array( $value ) || ! isset( $value['label'], $value['url'] ) || ! is_string( $value['label'] ) || ! is_string( $value['url'] ) ) {
			return null;
		}

		return array(
			'label' => $value['label'],
			'url'   => $value['url'],
		);
	}
}
