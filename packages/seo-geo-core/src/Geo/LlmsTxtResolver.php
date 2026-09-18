<?php
/**
 * Optional llms.txt resolver.
 *
 * @package SeoGeoCore
 */

declare(strict_types=1);

namespace SeoGeo\Core\Geo;

use SeoGeo\Core\Language\NativeTranslationRegistry;
use SeoGeo\Core\Seo\LocalizedSeoResolver;
use WP_Post;

/**
 * Builds one conservative llms.txt document from explicitly selected resources.
 */
final class LlmsTxtResolver {
	public const OPTION_NAME = 'seo_geo_llms_txt';

	private NativeTranslationRegistry $translations;
	private LocalizedSeoResolver $localized_seo;

	public function __construct( NativeTranslationRegistry $translations, LocalizedSeoResolver $localized_seo ) {
		$this->translations  = $translations;
		$this->localized_seo = $localized_seo;
	}

	public function enabled(): bool {
		$configuration = $this->configuration();

		return true === ( $configuration['enabled'] ?? false ) && 1 === (int) get_option( 'blog_public', '1' );
	}

	public function resolve(): ?string {
		if ( ! $this->enabled() ) {
			return null;
		}

		$title = $this->plain_text( get_bloginfo( 'name' ) );
		if ( '' === $title ) {
			return null;
		}

		$configuration = $this->configuration();
		$lines         = array( '# ' . $this->markdown_text( $title ) );
		$summary       = $this->plain_text_value( $configuration['summary'] ?? get_bloginfo( 'description' ) );

		if ( null !== $summary ) {
			$lines[] = '';
			$lines[] = '> ' . $this->markdown_text( $summary );
		}

		foreach ( $this->sections( $configuration['sections'] ?? null ) as $section ) {
			$lines[] = '';
			$lines[] = '## ' . $this->markdown_text( $section['title'] );

			foreach ( $section['links'] as $link ) {
				$line = '- [' . $this->markdown_text( $link['title'] ) . '](' . $this->markdown_url( $link['url'] ) . ')';

				if ( null !== $link['note'] ) {
					$line .= ': ' . $this->markdown_text( $link['note'] );
				}

				$lines[] = $line;
			}
		}

		return implode( "\n", $lines ) . "\n";
	}

	/**
	 * @param mixed $value Raw sections option.
	 * @return array<int, array{title:string,links:array<int,array{title:string,url:string,note:string|null}>}>
	 */
	private function sections( $value ): array {
		if ( ! is_array( $value ) ) {
			return array();
		}

		$sections = array();
		$seen     = array();

		foreach ( $value as $raw_section ) {
			if ( ! is_array( $raw_section ) ) {
				continue;
			}

			$title = $this->plain_text_value( $raw_section['title'] ?? null );
			$ids   = $raw_section['post_ids'] ?? null;

			if ( null === $title || ! is_array( $ids ) ) {
				continue;
			}

			$links = array();

			foreach ( $ids as $raw_id ) {
				$post_id = $this->post_id( $raw_id );

				if ( null === $post_id || isset( $seen[ $post_id ] ) ) {
					continue;
				}

				$link = $this->resource_link( $post_id );
				if ( null === $link ) {
					continue;
				}

				$seen[ $post_id ] = true;
				$links[]          = $link;
			}

			if ( array() === $links ) {
				continue;
			}

			$sections[] = array(
				'title' => $title,
				'links' => $links,
			);
		}

		return $sections;
	}

	/**
	 * @return array{title:string,url:string,note:string|null}|null
	 */
	private function resource_link( int $post_id ): ?array {
		$post = get_post( $post_id );

		if ( ! $this->is_public_resource( $post ) ) {
			return null;
		}

		$title = $this->plain_text( get_the_title( $post ) );
		if ( '' === $title ) {
			return null;
		}

		$relationship = $this->translations->for_post( $post_id );
		$language     = null;

		if ( null !== $relationship ) {
			$language = $relationship->current_language_code();
			$url      = $this->localized_seo->url_for_translation( $post_id, $language );
		} else {
			$url = get_permalink( $post );
		}

		if ( ! is_string( $url ) || ! $this->is_safe_site_url( $url ) ) {
			return null;
		}

		return array(
			'title' => $title,
			'url'   => $url,
			'note'  => null !== $language ? 'Language: ' . $language : null,
		);
	}

	private function is_public_resource( ?WP_Post $post ): bool {
		if (
			! $post instanceof WP_Post
			|| 'publish' !== $post->post_status
			|| 'attachment' === $post->post_type
			|| '' !== $post->post_password
		) {
			return false;
		}

		$post_type = get_post_type_object( $post->post_type );

		return null !== $post_type && is_post_type_viewable( $post_type );
	}

	private function is_safe_site_url( string $url ): bool {
		$scheme    = wp_parse_url( $url, PHP_URL_SCHEME );
		$host      = wp_parse_url( $url, PHP_URL_HOST );
		$home_host = wp_parse_url( home_url( '/' ), PHP_URL_HOST );

		return is_string( $scheme )
			&& in_array( strtolower( $scheme ), array( 'http', 'https' ), true )
			&& is_string( $host )
			&& is_string( $home_host )
			&& 0 === strcasecmp( $host, $home_host );
	}

	/**
	 * @param mixed $value Candidate post ID.
	 */
	private function post_id( $value ): ?int {
		if ( is_int( $value ) ) {
			$post_id = $value;
		} elseif ( is_string( $value ) && ctype_digit( $value ) ) {
			$post_id = (int) $value;
		} else {
			return null;
		}

		return 0 < $post_id ? $post_id : null;
	}

	/**
	 * @return array<string, mixed>
	 */
	private function configuration(): array {
		$value = get_option( self::OPTION_NAME, array() );

		return is_array( $value ) ? $value : array();
	}

	/**
	 * @param mixed $value Raw value.
	 */
	private function plain_text_value( $value ): ?string {
		if ( ! is_string( $value ) ) {
			return null;
		}

		$text = $this->plain_text( $value );

		return '' !== $text ? $text : null;
	}

	private function plain_text( string $value ): string {
		$value = wp_strip_all_tags( $value, true );
		$value = preg_replace( '/\s+/u', ' ', $value );

		return is_string( $value ) ? trim( $value ) : '';
	}

	private function markdown_text( string $value ): string {
		return str_replace(
			array( '\\', '[', ']', '*', '_' ),
			array( '\\\\', '\[', '\]', '\*', '\_' ),
			$value
		);
	}

	private function markdown_url( string $url ): string {
		return str_replace( array( '(', ')' ), array( '%28', '%29' ), $url );
	}
}
