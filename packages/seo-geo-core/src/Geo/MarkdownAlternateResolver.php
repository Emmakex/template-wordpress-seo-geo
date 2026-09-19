<?php
/**
 * Optional localized Markdown alternate resolver.
 *
 * @package SeoGeoCore
 */

declare(strict_types=1);

namespace SeoGeo\Core\Geo;

use SeoGeo\Core\Language\NativeLanguageConfiguration;
use SeoGeo\Core\Language\NativeTranslationRegistry;
use SeoGeo\Core\Seo\IndexabilityResolver;
use SeoGeo\Core\Seo\LocalizedSeoResolver;
use WP_Post;

/**
 * Resolves safe Markdown alternates for authoritative public WordPress resources.
 */
final class MarkdownAlternateResolver {
	/**
	 * Server-side Markdown alternate configuration option.
	 */
	public const OPTION_NAME = 'seo_geo_markdown_alternates';

	/**
	 * Translation relationship authority.
	 *
	 * @var NativeTranslationRegistry
	 */
	private NativeTranslationRegistry $translations;

	/**
	 * Localized public-URL authority.
	 *
	 * @var LocalizedSeoResolver
	 */
	private LocalizedSeoResolver $localized_seo;

	/**
	 * Native language configuration.
	 *
	 * @var NativeLanguageConfiguration
	 */
	private NativeLanguageConfiguration $languages;

	/**
	 * Current-request indexability authority.
	 *
	 * @var IndexabilityResolver
	 */
	private IndexabilityResolver $indexability;

	/**
	 * Content provenance authority.
	 *
	 * @var ContentProvenanceResolver
	 */
	private ContentProvenanceResolver $provenance;

	/**
	 * Create the resolver.
	 *
	 * @param NativeTranslationRegistry   $translations  Translation relationship authority.
	 * @param LocalizedSeoResolver        $localized_seo Localized public-URL authority.
	 * @param NativeLanguageConfiguration $languages     Native language configuration.
	 * @param IndexabilityResolver        $indexability Current-request indexability authority.
	 * @param ContentProvenanceResolver   $provenance   Content provenance authority.
	 */
	public function __construct(
		NativeTranslationRegistry $translations,
		LocalizedSeoResolver $localized_seo,
		NativeLanguageConfiguration $languages,
		IndexabilityResolver $indexability,
		ContentProvenanceResolver $provenance
	) {
		$this->translations  = $translations;
		$this->localized_seo = $localized_seo;
		$this->languages     = $languages;
		$this->indexability  = $indexability;
		$this->provenance    = $provenance;
	}

	/**
	 * Return whether Markdown alternates are explicitly enabled on a public site.
	 */
	public function enabled(): bool {
		$configuration = get_option( self::OPTION_NAME, array() );

		return is_array( $configuration )
			&& true === ( $configuration['enabled'] ?? false )
			&& 1 === (int) get_option( 'blog_public', '1' );
	}

	/**
	 * Resolve the Markdown alternate URL for the current authoritative HTML page.
	 */
	public function current_alternate_url(): ?string {
		if (
			! $this->enabled()
			|| ! is_singular()
			|| IndexabilityResolver::INDEXABLE !== $this->indexability->resolve()
		) {
			return null;
		}

		$post_id = get_queried_object_id();
		if ( 0 >= $post_id ) {
			return null;
		}

		$relationship = $this->translations->for_post( $post_id );

		if ( null !== $relationship ) {
			$language = $this->localized_seo->current_language_code();

			if ( null === $language || $language !== $relationship->current_language_code() ) {
				return null;
			}

			return $this->url_for_post( $post_id, $language );
		}

		return $this->url_for_post( $post_id, null );
	}

	/**
	 * Resolve a Markdown alternate URL for one validated public resource.
	 *
	 * @param int         $post_id       WordPress resource ID.
	 * @param string|null $language_code Required relationship language, or null for unlocalized content.
	 */
	public function url_for_post( int $post_id, ?string $language_code ): ?string {
		$post = get_post( $post_id );
		if ( ! $this->is_public_resource( $post ) ) {
			return null;
		}

		$relationship = $this->translations->for_post( $post_id );

		if ( null !== $relationship ) {
			if (
				null === $language_code
				|| $language_code !== $relationship->current_language_code()
			) {
				return null;
			}

			$html_url = $this->localized_seo->url_for_translation( $post_id, $language_code );
		} else {
			if ( null !== $language_code ) {
				return null;
			}

			$html_url = get_permalink( $post );
		}

		if ( ! is_string( $html_url ) || ! $this->is_safe_site_url( $html_url ) ) {
			return null;
		}

		return $this->markdown_url_from_html( $html_url );
	}

	/**
	 * Resolve the current Markdown HTTP request into one safe public resource.
	 *
	 * @return array{post:WP_Post,html_url:string,markdown_url:string,language:string|null}|null
	 */
	public function current_markdown_request(): ?array {
		if ( ! $this->enabled() || ! $this->request_method_supported() ) {
			return null;
		}

		$request_path = $this->request_path();
		if ( null === $request_path ) {
			return null;
		}

		$html_path = $this->html_path_from_markdown( $request_path );
		if ( null === $html_path ) {
			return null;
		}

		$home_path = wp_parse_url( home_url( '/' ), PHP_URL_PATH );
		if ( ! is_string( $home_path ) ) {
			return null;
		}

		$normalized_home = '/' . trim( $home_path, '/' );
		if ( '/' !== $normalized_home ) {
			$normalized_home .= '/';
		}

		if ( ! str_starts_with( $html_path, $normalized_home ) ) {
			return null;
		}

		$relative = ltrim( substr( $html_path, strlen( $normalized_home ) ), '/' );
		$segments = '' !== $relative ? explode( '/', trim( $relative, '/' ) ) : array();
		$language = null;

		if ( array() !== $segments ) {
			$candidate = strtolower( rawurldecode( $segments[0] ) );

			if ( null !== $this->languages->locale_for( $candidate ) ) {
				$language = $candidate;
				array_shift( $segments );
			}
		}

		$markdown_resource_path = '/' . implode( '/', $segments );
		if ( '/' !== $markdown_resource_path ) {
			$markdown_resource_path = trailingslashit( $markdown_resource_path );
		}

		$post_id = url_to_postid( home_url( $markdown_resource_path ) );
		if ( 0 >= $post_id ) {
			return null;
		}

		$post = get_post( $post_id );
		if ( ! $this->is_public_resource( $post ) ) {
			return null;
		}

		$relationship = $this->translations->for_post( $post_id );

		if ( null !== $relationship ) {
			if ( null === $language || $language !== $relationship->current_language_code() ) {
				return null;
			}

			$html_url = $this->localized_seo->url_for_translation( $post_id, $language );
		} else {
			if ( null !== $language ) {
				return null;
			}

			$html_url = get_permalink( $post );
		}

		if ( ! is_string( $html_url ) || ! $this->is_safe_site_url( $html_url ) ) {
			return null;
		}

		$markdown_url = $this->markdown_url_from_html( $html_url );
		$expected     = wp_parse_url( $markdown_url, PHP_URL_PATH );

		if ( ! is_string( $expected ) || $expected !== $request_path ) {
			return null;
		}

		return array(
			'post'         => $post,
			'html_url'     => $html_url,
			'markdown_url' => $markdown_url,
			'language'     => $language,
		);
	}

	/**
	 * Build clean Markdown from one resolved public resource.
	 *
	 * @param array{post:WP_Post,html_url:string,markdown_url:string,language:string|null} $markdown_resource Resolved Markdown resource.
	 */
	public function render_markdown( array $markdown_resource ): string {
		$post  = $markdown_resource['post'];
		$title = $this->plain_text( get_the_title( $post ) );

		$lines = array(
			'# ' . $this->escape_markdown( $title ),
			'',
			'Source: [' . $this->escape_markdown( $markdown_resource['html_url'] ) . '](' . $markdown_resource['html_url'] . ')',
		);

		$language = $markdown_resource['language'];
		if ( null === $language ) {
			$language = get_bloginfo( 'language' );
		}

		if ( '' !== trim( $language ) ) {
			$lines[] = 'Language: ' . $this->escape_markdown( trim( $language ) );
		}

		$provenance = $this->provenance->for_post( (int) $post->ID );
		if ( null !== $provenance ) {
			if ( null !== $provenance['author'] ) {
				$author  = $provenance['author'];
				$lines[] = 'Author: [' . $this->escape_markdown( $author['name'] ) . '](' . $author['url'] . ')';
			}

			if ( null !== $provenance['publisher'] ) {
				$publisher = $provenance['publisher'];
				$lines[]    = 'Publisher: [' . $this->escape_markdown( $publisher['name'] ) . '](' . $publisher['url'] . ')';
			}

			$lines[] = 'Published: ' . $provenance['date_published'];
			$lines[] = 'Updated: ' . $provenance['date_modified'];
		}

		$excerpt = $this->plain_text( (string) $post->post_excerpt );
		if ( '' !== $excerpt ) {
			$lines[] = '';
			$lines[] = '> ' . $this->escape_markdown( $excerpt );
		}

		$body = $this->blocks_to_markdown( parse_blocks( (string) $post->post_content ) );
		if ( '' !== $body ) {
			$lines[] = '';
			$lines[] = $body;
		}

		return rtrim( implode( "\n", $lines ) ) . "\n";
	}

	/**
	 * Convert authored Gutenberg blocks to conservative Markdown without executing dynamic blocks or shortcodes.
	 *
	 * @param array<int, array<string, mixed>> $blocks Parsed WordPress blocks.
	 */
	private function blocks_to_markdown( array $blocks ): string {
		$parts = array();

		foreach ( $blocks as $block ) {
			$inner_blocks = $block['innerBlocks'] ?? array();
			$block_name   = $block['blockName'] ?? null;
			$inner_html   = $block['innerHTML'] ?? '';

			if ( is_array( $inner_blocks ) && array() !== $inner_blocks ) {
				$nested = $this->blocks_to_markdown( $inner_blocks );
				if ( '' !== $nested ) {
					$parts[] = $nested;
				}
				continue;
			}

			if ( ! is_string( $inner_html ) || '' === trim( $inner_html ) ) {
				continue;
			}

			$text = $this->plain_text( strip_shortcodes( $inner_html ) );
			if ( '' === $text ) {
				continue;
			}

			if ( 'core/heading' === $block_name ) {
				$level = 2;
				$attrs = $block['attrs'] ?? array();

				if ( is_array( $attrs ) && isset( $attrs['level'] ) && is_int( $attrs['level'] ) ) {
					$level = max( 2, min( 6, $attrs['level'] ) );
				}

				$parts[] = str_repeat( '#', $level ) . ' ' . $this->escape_markdown( $text );
				continue;
			}

			if ( 'core/quote' === $block_name ) {
				$parts[] = '> ' . $this->escape_markdown( $text );
				continue;
			}

			$parts[] = $this->escape_markdown( $text );
		}

		return implode( "\n\n", $parts );
	}

	/**
	 * Transform an authoritative HTML URL into the recommended Markdown alternate form.
	 *
	 * @param string $html_url Valid same-site HTML URL.
	 */
	private function markdown_url_from_html( string $html_url ): string {
		return str_ends_with( $html_url, '/' ) ? $html_url . 'index.md' : $html_url . '.md';
	}

	/**
	 * Transform a Markdown request path back to its HTML path form.
	 *
	 * @param string $request_path Current request path.
	 */
	private function html_path_from_markdown( string $request_path ): ?string {
		if ( str_ends_with( $request_path, '/index.md' ) ) {
			return substr( $request_path, 0, -strlen( 'index.md' ) );
		}

		if ( str_ends_with( $request_path, '.md' ) ) {
			$path = substr( $request_path, 0, -3 );

			return '' !== $path ? $path : null;
		}

		return null;
	}

	/**
	 * Return the current request path without query/fragment components.
	 */
	private function request_path(): ?string {
		if ( ! isset( $_SERVER['REQUEST_URI'] ) || ! is_string( $_SERVER['REQUEST_URI'] ) ) {
			return null;
		}

		$request_uri = sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) );
		$path        = wp_parse_url( $request_uri, PHP_URL_PATH );

		return is_string( $path ) ? $path : null;
	}

	/**
	 * Allow only GET and HEAD for Markdown alternates.
	 */
	private function request_method_supported(): bool {
		$method = isset( $_SERVER['REQUEST_METHOD'] ) && is_string( $_SERVER['REQUEST_METHOD'] )
			? strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) )
			: 'GET';

		return in_array( $method, array( 'GET', 'HEAD' ), true );
	}

	/**
	 * Report whether one resource is safe for public Markdown output.
	 *
	 * @param WP_Post|null $post Candidate resource.
	 */
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

	/**
	 * Require a same-host absolute HTTP(S) URL.
	 *
	 * @param string $url Candidate public URL.
	 */
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
	 * Normalize one-line authored text.
	 *
	 * @param string $value Raw text.
	 */
	private function plain_text( string $value ): string {
		$value = wp_strip_all_tags( $value, true );
		$value = preg_replace( '/\s+/u', ' ', $value );

		return is_string( $value ) ? trim( $value ) : '';
	}

	/**
	 * Escape Markdown label/content control characters.
	 *
	 * @param string $value Normalized text.
	 */
	private function escape_markdown( string $value ): string {
		return str_replace(
			array( '\\', '[', ']', '*', '_' ),
			array( '\\\\', '\[', '\]', '\*', '\_' ),
			$value
		);
	}
}
