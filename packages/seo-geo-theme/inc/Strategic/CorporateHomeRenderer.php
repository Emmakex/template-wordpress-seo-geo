<?php
/**
 * Corporate v5 Theme-owned Home renderer.
 *
 * @package SeoGeoTheme
 */

declare(strict_types=1);

namespace SeoGeo\Theme\Strategic;

use WP_Post;
use WP_Query;

/**
 * Renders the strategic Corporate Home from corporate-home-v1 data.
 *
 * Gutenberg may remain the current transition/editorial source of the semantic
 * values, but it does not own the public layout, wrapper widths or hierarchy.
 */
final class CorporateHomeRenderer {
	/**
	 * Build the Corporate Home renderer.
	 *
	 * @param CorporateHomeModelResolver $resolver Semantic model resolver.
	 */
	public function __construct( private readonly CorporateHomeModelResolver $resolver ) {}

	/**
	 * Check whether this page has a complete v5-compatible Home model.
	 *
	 * @param WP_Post $post Source WordPress page.
	 */
	public function supports( WP_Post $post ): bool {
		return $this->resolver->supports( $post );
	}

	/**
	 * Render one Corporate Home.
	 *
	 * @param WP_Post $post Source WordPress page.
	 * @return string Trusted Theme HTML with all content values escaped here.
	 */
	public function render( WP_Post $post ): string {
		$model = $this->resolver->resolve( $post );
		if ( null === $model || ! isset( $model['slots'] ) || ! is_array( $model['slots'] ) ) {
			return '';
		}

		$slots  = $model['slots'];
		$locale = function_exists( 'seo_geo_theme_preset_locale' ) ? seo_geo_theme_preset_locale() : 'en_US';
		$is_es  = 'es_ES' === $locale;

		return '<main id="seo-geo-main" class="seo-geo-corporate-v5-home" tabindex="-1">'
			. $this->render_hero( $slots )
			. $this->render_capabilities( $slots, $is_es )
			. $this->render_process( $slots, $is_es )
			. $this->render_insights( $slots )
			. $this->render_final_cta( $slots )
			. '</main>';
	}

	/**
	 * Render the strategic hero and primary actions.
	 *
	 * @param array<string, mixed> $slots Semantic slots.
	 */
	private function render_hero( array $slots ): string {
		$eyebrow   = $this->text( $slots, 'hero-eyebrow' );
		$lead      = $this->text( $slots, 'hero-lead' );
		$primary   = $this->link( $slots, 'hero-primary-cta' );
		$secondary = $this->link( $slots, 'hero-secondary-cta' );

		if ( null === $primary ) {
			return '';
		}

		$actions = '<div class="wp-block-buttons seo-geo-corporate-actions">' . $this->button( $primary, false );
		if ( null !== $secondary ) {
			$actions .= $this->button( $secondary, true );
		}
		$actions .= '</div>';

		$visual = $this->decorative_image( 'hero-intelligence.svg', 'seo-geo-corporate-hero-visual__image', 720, 620, false );

		return '<section class="seo-geo-corporate-native-hero" aria-labelledby="seo-geo-corporate-v5-title">'
			. '<div class="wp-block-columns seo-geo-corporate-native-hero__grid">'
			. '<div class="wp-block-column"><div class="seo-geo-corporate-native-hero__copy">'
			. '<p class="seo-geo-corporate-eyebrow">' . esc_html( $eyebrow ) . '</p>'
			. '<h1 id="seo-geo-corporate-v5-title" class="seo-geo-corporate-lead">' . esc_html( $lead ) . '</h1>'
			. $actions
			. '</div></div>'
			. '<div class="wp-block-column seo-geo-corporate-native-hero__visual-column" aria-hidden="true">' . $visual . '</div>'
			. '</div></section>';
	}

	/**
	 * Render the three capability cards.
	 *
	 * @param array<string, mixed> $slots Semantic slots.
	 * @param bool                 $is_es Whether Spanish preset copy is active.
	 */
	private function render_capabilities( array $slots, bool $is_es ): string {
		$cards = '';
		for ( $index = 1; $index <= 3; ++$index ) {
			$title = $this->text( $slots, 'capability-' . $index . '-title' );
			$body  = $this->text( $slots, 'capability-' . $index . '-body' );
			$link  = $this->link( $slots, 'capability-' . $index . '-link' );
			if ( null === $link ) {
				continue;
			}

			$cards .= '<article class="seo-geo-corporate-card seo-geo-corporate-card--' . $index . '">'
				. '<p class="seo-geo-corporate-card__index">0' . $index . '</p>'
				. '<h3>' . esc_html( $title ) . '</h3>'
				. '<p>' . esc_html( $body ) . '</p>'
				. '<p class="seo-geo-corporate-card__link"><a href="' . esc_url( $link['url'] ) . '">' . esc_html( $link['label'] ) . '</a></p>'
				. '</article>';
		}

		return '<section class="seo-geo-corporate-native-section seo-geo-corporate-native-capabilities" aria-labelledby="seo-geo-capabilities-title">'
			. $this->section_heading(
				$is_es ? 'CAPACIDADES' : 'CAPABILITIES',
				'seo-geo-capabilities-title',
				$this->text( $slots, 'capabilities-heading' ),
				$this->text( $slots, 'capabilities-intro' )
			)
			. '<div class="seo-geo-corporate-card-grid">' . $cards . '</div>'
			. '</section>';
	}

	/**
	 * Render the three-step working method.
	 *
	 * @param array<string, mixed> $slots Semantic slots.
	 * @param bool                 $is_es Whether Spanish preset copy is active.
	 */
	private function render_process( array $slots, bool $is_es ): string {
		$steps = '';
		for ( $index = 1; $index <= 3; ++$index ) {
			$steps .= '<article class="seo-geo-corporate-process-step seo-geo-corporate-process-step--' . $index . '" data-step="0' . $index . '">'
				. '<p class="seo-geo-corporate-process-step__number">0' . $index . '</p>'
				. '<h3>' . esc_html( $this->text( $slots, 'process-' . $index . '-title' ) ) . '</h3>'
				. '<p>' . esc_html( $this->text( $slots, 'process-' . $index . '-body' ) ) . '</p>'
				. '</article>';
		}

		return '<section class="seo-geo-corporate-native-process" aria-labelledby="seo-geo-process-title">'
			. $this->section_heading(
				$is_es ? 'MÉTODO' : 'METHOD',
				'seo-geo-process-title',
				$this->text( $slots, 'process-heading' ),
				$this->text( $slots, 'process-intro' )
			)
			. '<div class="seo-geo-corporate-process-grid">' . $steps . '</div>'
			. '</section>';
	}

	/**
	 * Render the latest three published WordPress posts.
	 *
	 * @param array<string, mixed> $slots Semantic slots.
	 */
	private function render_insights( array $slots ): string {
		$query = new WP_Query(
			array(
				'post_type'           => 'post',
				'post_status'         => 'publish',
				'posts_per_page'      => 3,
				'ignore_sticky_posts' => true,
				'no_found_rows'       => true,
			)
		);

		$items    = '';
		$position = 0;
		foreach ( $query->posts as $article ) {
			if ( ! $article instanceof WP_Post ) {
				continue;
			}

			$permalink = get_permalink( $article );
			if ( '' === $permalink ) {
				continue;
			}

			++$position;
			$excerpt = trim( wp_strip_all_tags( get_the_excerpt( $article ), true ) );
			$excerpt = wp_trim_words( $excerpt, 30, '…' );
			$media   = 1 === $position ? $this->insight_media( $article ) : '';

			$items .= '<li><article class="seo-geo-corporate-insight-card">'
				. $media
				. '<div class="seo-geo-corporate-insight-card__content">'
				. '<time class="wp-block-post-date" datetime="' . esc_attr( get_the_date( DATE_W3C, $article ) ) . '">' . esc_html( get_the_date( '', $article ) ) . '</time>'
				. '<h3 class="wp-block-post-title"><a href="' . esc_url( $permalink ) . '">' . esc_html( get_the_title( $article ) ) . '</a></h3>'
				. '<div class="wp-block-post-excerpt"><p>' . esc_html( $excerpt ) . '</p></div>'
				. '</div></article></li>';
		}

		wp_reset_postdata();

		return '<section class="seo-geo-corporate-native-section seo-geo-corporate-native-insights" aria-labelledby="seo-geo-insights-title">'
			. $this->section_heading(
				'INSIGHTS',
				'seo-geo-insights-title',
				$this->text( $slots, 'insights-heading' ),
				$this->text( $slots, 'insights-intro' )
			)
			. ( '' === $items ? '' : '<ul class="wp-block-post-template">' . $items . '</ul>' )
			. '</section>';
	}

	/**
	 * Render featured editorial media, preferring WordPress-authored media.
	 *
	 * @param WP_Post $article Article resource.
	 */
	private function insight_media( WP_Post $article ): string {
		if ( has_post_thumbnail( $article ) ) {
			$image = get_the_post_thumbnail(
				$article,
				'large',
				array(
					'class'    => 'seo-geo-corporate-insight-card__image',
					'loading'  => 'lazy',
					'decoding' => 'async',
					'sizes'    => '(max-width: 820px) calc(100vw - 2rem), 46vw',
				)
			);
			if ( '' !== $image ) {
				return '<div class="seo-geo-corporate-insight-card__media">' . $image . '</div>';
			}
		}

		return '<div class="seo-geo-corporate-insight-card__media seo-geo-corporate-insight-card__media--fallback" aria-hidden="true">'
			. $this->decorative_image( 'insights-featured.svg', 'seo-geo-corporate-insight-card__image', 720, 420 )
			. '</div>';
	}

	/**
	 * Render the closing call to action.
	 *
	 * @param array<string, mixed> $slots Semantic slots.
	 */
	private function render_final_cta( array $slots ): string {
		$link = $this->link( $slots, 'final-cta-button' );
		if ( null === $link ) {
			return '';
		}

		return '<section class="seo-geo-final-cta" aria-labelledby="seo-geo-final-cta-title">'
			. '<h2 id="seo-geo-final-cta-title">' . esc_html( $this->text( $slots, 'final-cta-heading' ) ) . '</h2>'
			. '<p>' . esc_html( $this->text( $slots, 'final-cta-body' ) ) . '</p>'
			. '<p><a class="wp-element-button" href="' . esc_url( $link['url'] ) . '">' . esc_html( $link['label'] ) . '</a></p>'
			. '</section>';
	}

	/**
	 * Render one reusable section header.
	 *
	 * @param string $eyebrow Compact section label.
	 * @param string $id      Heading element ID.
	 * @param string $heading Visible section heading.
	 * @param string $intro   Supporting copy.
	 */
	private function section_heading( string $eyebrow, string $id, string $heading, string $intro ): string {
		return '<header class="seo-geo-corporate-section-heading">'
			. '<p class="seo-geo-corporate-eyebrow">' . esc_html( $eyebrow ) . '</p>'
			. '<h2 id="' . esc_attr( $id ) . '">' . esc_html( $heading ) . '</h2>'
			. '<p>' . esc_html( $intro ) . '</p>'
			. '</header>';
	}

	/**
	 * Render a local decorative image with intrinsic dimensions.
	 *
	 * @param string $filename  Asset filename.
	 * @param string $css_class CSS class.
	 * @param int    $width     Intrinsic width.
	 * @param int    $height    Intrinsic height.
	 * @param bool   $lazy      Whether to lazy-load the asset.
	 */
	private function decorative_image( string $filename, string $css_class, int $width, int $height, bool $lazy = true ): string {
		$loading = $lazy ? ' loading="lazy"' : '';

		return '<img class="' . esc_attr( $css_class ) . '" src="' . esc_url( $this->asset_uri( $filename ) ) . '" alt="" width="' . esc_attr( (string) $width ) . '" height="' . esc_attr( (string) $height ) . '" decoding="async"' . $loading . '>';
	}

	/**
	 * Resolve one Corporate v5 local asset URL.
	 *
	 * @param string $filename Asset filename.
	 */
	private function asset_uri( string $filename ): string {
		return trailingslashit( get_template_directory_uri() ) . 'assets/images/presets/corporate/v5/' . ltrim( $filename, '/' );
	}

	/**
	 * Render one CTA button.
	 *
	 * @param array{label:string,url:string} $link    Link value.
	 * @param bool                           $outline Whether to use outline styling.
	 */
	private function button( array $link, bool $outline ): string {
		$class = $outline ? 'wp-block-button is-style-outline' : 'wp-block-button';

		return '<div class="' . esc_attr( $class ) . '"><a class="wp-block-button__link wp-element-button" href="' . esc_url( $link['url'] ) . '">' . esc_html( $link['label'] ) . '</a></div>';
	}

	/**
	 * Resolve one text slot.
	 *
	 * @param array<string, mixed> $slots Semantic slots.
	 * @param string               $id    Slot identifier.
	 */
	private function text( array $slots, string $id ): string {
		$value = $slots[ $id ] ?? '';

		return is_string( $value ) ? $value : '';
	}

	/**
	 * Resolve one link slot.
	 *
	 * @param array<string, mixed> $slots Semantic slots.
	 * @param string               $id    Slot identifier.
	 * @return array{label:string,url:string}|null
	 */
	private function link( array $slots, string $id ): ?array {
		$value = $slots[ $id ] ?? null;
		if ( ! is_array( $value ) || ! isset( $value['label'], $value['url'] ) || ! is_string( $value['label'] ) || ! is_string( $value['url'] ) ) {
			return null;
		}

		return array(
			'label' => $value['label'],
			'url'   => $value['url'],
		);
	}
}
