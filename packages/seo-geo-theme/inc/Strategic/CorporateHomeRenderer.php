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
 * Render the strategic Corporate Home from corporate-home-v1 data.
 *
 * Gutenberg remains an editorial source for semantic values only. The Theme
 * owns the public hierarchy, layout and strategic-media placement.
 */
final class CorporateHomeRenderer {
	/**
	 * Create the Corporate Home renderer.
	 *
	 * @param CorporateHomeModelResolver $resolver Corporate Home model resolver.
	 */
	public function __construct( private readonly CorporateHomeModelResolver $resolver ) {}

	/**
	 * Check whether the supplied page supports Theme-owned Corporate rendering.
	 *
	 * @param WP_Post $post Source WordPress page.
	 * @return bool Whether the page is supported.
	 */
	public function supports( WP_Post $post ): bool {
		return $this->resolver->supports( $post );
	}

	/**
	 * Render trusted Theme HTML with authored values escaped at composition time.
	 *
	 * @param WP_Post $post Source WordPress page.
	 * @return string Rendered Corporate Home HTML.
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
	 * Render the Corporate hero.
	 *
	 * @param array<string, mixed> $slots Resolved semantic and media slots.
	 * @return string Hero HTML.
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

		$visual        = $this->hero_visual( $slots );
		$visual_hidden = $visual['decorative'] ? ' aria-hidden="true"' : '';

		return '<section class="seo-geo-corporate-native-hero" aria-labelledby="seo-geo-corporate-v5-title">'
			. '<div class="wp-block-columns seo-geo-corporate-native-hero__grid">'
			. '<div class="wp-block-column"><div class="seo-geo-corporate-native-hero__copy">'
			. '<p class="seo-geo-corporate-eyebrow">' . esc_html( $eyebrow ) . '</p>'
			. '<h1 id="seo-geo-corporate-v5-title" class="seo-geo-corporate-lead">' . esc_html( $lead ) . '</h1>'
			. $actions
			. '</div></div>'
			. '<div class="wp-block-column seo-geo-corporate-native-hero__visual-column"' . $visual_hidden . '>' . $visual['html'] . '</div>'
			. '</div></section>';
	}

	/**
	 * Resolve either the bound hero image or the local decorative fallback.
	 *
	 * @param array<string, mixed> $slots Resolved semantic and media slots.
	 * @return array{html:string,decorative:bool} Hero visual state.
	 */
	private function hero_visual( array $slots ): array {
		$image = $this->bound_image(
			$this->media( $slots, 'hero-media' ),
			'large',
			'seo-geo-corporate-hero-visual__image',
			false,
			'(max-width: 781px) calc(100vw - 2rem), 46vw',
			true
		);
		if ( '' !== $image ) {
			return array(
				'html'       => $image,
				'decorative' => false,
			);
		}

		return array(
			'html'       => $this->decorative_image( 'hero-intelligence.svg', 'seo-geo-corporate-hero-visual__image', 720, 620, false ),
			'decorative' => true,
		);
	}

	/**
	 * Render the three Corporate capability cards.
	 *
	 * @param array<string, mixed> $slots Resolved semantic and media slots.
	 * @param bool                 $is_es Whether the active locale is Spanish.
	 * @return string Capabilities section HTML.
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

			$media       = $this->bound_image(
				$this->media( $slots, 'capability-' . $index . '-media' ),
				'medium_large',
				'seo-geo-corporate-card__image',
				true,
				'(max-width: 820px) calc(100vw - 2rem), 31vw'
			);
			$media_html  = '' !== $media ? '<div class="seo-geo-corporate-card__media">' . $media . '</div>' : '';
			$media_class = '' !== $media ? ' has-media' : '';

			$cards .= '<article class="seo-geo-corporate-card seo-geo-corporate-card--' . $index . $media_class . '">'
				. $media_html
				. '<div class="seo-geo-corporate-card__body">'
				. '<p class="seo-geo-corporate-card__index">0' . $index . '</p>'
				. '<h3>' . esc_html( $title ) . '</h3>'
				. '<p>' . esc_html( $body ) . '</p>'
				. '<p class="seo-geo-corporate-card__link"><a href="' . esc_url( $link['url'] ) . '">' . esc_html( $link['label'] ) . '</a></p>'
				. '</div></article>';
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
	 * Render the Corporate method and process section.
	 *
	 * @param array<string, mixed> $slots Resolved semantic and media slots.
	 * @param bool                 $is_es Whether the active locale is Spanish.
	 * @return string Process section HTML.
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

		$heading = $this->section_heading(
			$is_es ? 'MÉTODO' : 'METHOD',
			'seo-geo-process-title',
			$this->text( $slots, 'process-heading' ),
			$this->text( $slots, 'process-intro' )
		);
		$media   = $this->bound_image(
			$this->media( $slots, 'process-media' ),
			'large',
			'seo-geo-corporate-process-visual__image',
			true,
			'(max-width: 820px) calc(100vw - 2rem), 42vw'
		);
		$intro   = '' !== $media
			? '<div class="seo-geo-corporate-process-intro-grid">' . $heading . '<figure class="seo-geo-corporate-process-visual">' . $media . '</figure></div>'
			: $heading;

		return '<section class="seo-geo-corporate-native-process" aria-labelledby="seo-geo-process-title">'
			. $intro
			. '<div class="seo-geo-corporate-process-grid">' . $steps . '</div>'
			. '</section>';
	}

	/**
	 * Render the latest Corporate Insights cards.
	 *
	 * @param array<string, mixed> $slots Resolved semantic and media slots.
	 * @return string Insights section HTML.
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
			$excerpt     = trim( wp_strip_all_tags( get_the_excerpt( $article ), true ) );
			$excerpt     = wp_trim_words( $excerpt, 30, '…' );
			$media       = $this->insight_media( $article, $position, $slots );
			$media_class = '' !== $media ? ' has-media' : '';

			$items .= '<li><article class="seo-geo-corporate-insight-card seo-geo-corporate-insight-card--' . $position . $media_class . '">'
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
	 * Resolve the visual for one Insight card.
	 *
	 * Featured Image remains authoritative. Strategic media acts as a safe
	 * fallback, followed by the existing local decorative asset for card one.
	 *
	 * @param WP_Post              $article Article being rendered.
	 * @param int                  $position One-based card position.
	 * @param array<string, mixed> $slots Resolved semantic and media slots.
	 * @return string Insight media HTML.
	 */
	private function insight_media( WP_Post $article, int $position, array $slots ): string {
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

		$image = $this->bound_image(
			$this->media( $slots, 'insights-' . $position . '-media' ),
			'large',
			'seo-geo-corporate-insight-card__image',
			true,
			'(max-width: 820px) calc(100vw - 2rem), 46vw'
		);
		if ( '' !== $image ) {
			return '<div class="seo-geo-corporate-insight-card__media">' . $image . '</div>';
		}

		if ( 1 !== $position ) {
			return '';
		}

		return '<div class="seo-geo-corporate-insight-card__media seo-geo-corporate-insight-card__media--fallback" aria-hidden="true">'
			. $this->decorative_image( 'insights-featured.svg', 'seo-geo-corporate-insight-card__image', 720, 420 )
			. '</div>';
	}

	/**
	 * Render the final Corporate call to action.
	 *
	 * @param array<string, mixed> $slots Resolved semantic and media slots.
	 * @return string CTA section HTML.
	 */
	private function render_final_cta( array $slots ): string {
		$link = $this->link( $slots, 'final-cta-button' );
		if ( null === $link ) {
			return '';
		}

		$copy  = '<div class="seo-geo-final-cta__copy">'
			. '<h2 id="seo-geo-final-cta-title">' . esc_html( $this->text( $slots, 'final-cta-heading' ) ) . '</h2>'
			. '<p>' . esc_html( $this->text( $slots, 'final-cta-body' ) ) . '</p>'
			. '<p><a class="wp-element-button" href="' . esc_url( $link['url'] ) . '">' . esc_html( $link['label'] ) . '</a></p>'
			. '</div>';
		$media = $this->bound_image(
			$this->media( $slots, 'final-cta-media' ),
			'large',
			'seo-geo-final-cta__image',
			true,
			'(max-width: 820px) calc(100vw - 2rem), 40vw'
		);

		return '<section class="seo-geo-final-cta' . ( '' !== $media ? ' has-media' : '' ) . '" aria-labelledby="seo-geo-final-cta-title">'
			. $copy
			. ( '' !== $media ? '<figure class="seo-geo-final-cta__media">' . $media . '</figure>' : '' )
			. '</section>';
	}

	/**
	 * Render a shared section heading.
	 *
	 * @param string $eyebrow Eyebrow label.
	 * @param string $id Heading element ID.
	 * @param string $heading Section heading.
	 * @param string $intro Section introduction.
	 * @return string Section heading HTML.
	 */
	private function section_heading( string $eyebrow, string $id, string $heading, string $intro ): string {
		return '<header class="seo-geo-corporate-section-heading">'
			. '<p class="seo-geo-corporate-eyebrow">' . esc_html( $eyebrow ) . '</p>'
			. '<h2 id="' . esc_attr( $id ) . '">' . esc_html( $heading ) . '</h2>'
			. '<p>' . esc_html( $intro ) . '</p>'
			. '</header>';
	}

	/**
	 * Render one Theme-bound image attachment.
	 *
	 * @param int    $media_id Attachment ID.
	 * @param string $size WordPress image size.
	 * @param string $css_class CSS class for the image.
	 * @param bool   $lazy Whether lazy loading is enabled.
	 * @param string $sizes Responsive image sizes expression.
	 * @param bool   $priority Whether the image receives high fetch priority.
	 * @return string Image HTML or an empty string.
	 */
	private function bound_image( int $media_id, string $size, string $css_class, bool $lazy, string $sizes, bool $priority = false ): string {
		if ( 1 > $media_id || ! wp_attachment_is_image( $media_id ) ) {
			return '';
		}

		$attributes = array(
			'class'    => $css_class,
			'loading'  => $lazy ? 'lazy' : 'eager',
			'decoding' => 'async',
			'sizes'    => $sizes,
		);
		if ( $priority ) {
			$attributes['fetchpriority'] = 'high';
		}

		return wp_get_attachment_image( $media_id, $size, false, $attributes );
	}

	/**
	 * Render one local decorative image.
	 *
	 * @param string $filename Relative asset filename.
	 * @param string $css_class CSS class for the image.
	 * @param int    $width Intrinsic image width.
	 * @param int    $height Intrinsic image height.
	 * @param bool   $lazy Whether lazy loading is enabled.
	 * @return string Decorative image HTML.
	 */
	private function decorative_image( string $filename, string $css_class, int $width, int $height, bool $lazy = true ): string {
		$loading = $lazy ? ' loading="lazy"' : '';
		return '<img class="' . esc_attr( $css_class ) . '" src="' . esc_url( $this->asset_uri( $filename ) ) . '" alt="" width="' . esc_attr( (string) $width ) . '" height="' . esc_attr( (string) $height ) . '" decoding="async"' . $loading . '>';
	}

	/**
	 * Return the public URI for one Corporate v5 asset.
	 *
	 * @param string $filename Relative asset filename.
	 * @return string Asset URI.
	 */
	private function asset_uri( string $filename ): string {
		return trailingslashit( get_template_directory_uri() ) . 'assets/images/presets/corporate/v5/' . ltrim( $filename, '/' );
	}

	/**
	 * Render one CTA button.
	 *
	 * @param array{label:string,url:string} $link Link definition.
	 * @param bool                           $outline Whether to use the outline style.
	 * @return string Button HTML.
	 */
	private function button( array $link, bool $outline ): string {
		$class = $outline ? 'wp-block-button is-style-outline' : 'wp-block-button';
		return '<div class="' . esc_attr( $class ) . '"><a class="wp-block-button__link wp-element-button" href="' . esc_url( $link['url'] ) . '">' . esc_html( $link['label'] ) . '</a></div>';
	}

	/**
	 * Read one text slot.
	 *
	 * @param array<string, mixed> $slots Resolved semantic and media slots.
	 * @param string               $id Slot ID.
	 * @return string Slot value.
	 */
	private function text( array $slots, string $id ): string {
		$value = $slots[ $id ] ?? '';
		return is_string( $value ) ? $value : '';
	}

	/**
	 * Read one media attachment slot.
	 *
	 * @param array<string, mixed> $slots Resolved semantic and media slots.
	 * @param string               $id Slot ID.
	 * @return int Attachment ID or zero.
	 */
	private function media( array $slots, string $id ): int {
		$value = $slots[ $id ] ?? 0;
		return is_int( $value ) && 0 < $value ? $value : 0;
	}

	/**
	 * Read one link slot.
	 *
	 * @param array<string, mixed> $slots Resolved semantic and media slots.
	 * @param string               $id Slot ID.
	 * @return array{label:string,url:string}|null Normalized link or null.
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
