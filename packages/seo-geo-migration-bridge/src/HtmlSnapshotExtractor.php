<?php
/**
 * Public HTML SEO/GEO snapshot extractor.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;

/**
 * Extracts comparison-safe public signals from one HTML response.
 */
final class HtmlSnapshotExtractor {
	/**
	 * Extract one page snapshot.
	 *
	 * @param array{content_type:string,location:string,x_robots_tag:string} $headers Response headers.
	 * @return array<string,mixed>
	 */
	public function extract( string $url, int $status, string $html, array $headers ): array {
		$result = array(
			'http'             => array(
				'status'         => $status,
				'content_type'   => $headers['content_type'],
				'location'       => $headers['location'],
				'x_robots_tag'   => $headers['x_robots_tag'],
			),
			'indexability'     => $this->indexability( $status, '', $headers['x_robots_tag'] ),
			'title'            => null,
			'meta_description' => null,
			'canonical'        => null,
			'robots'           => null,
			'html_lang'        => null,
			'hreflang'         => array(),
			'open_graph'       => array(),
			'schema'           => array(
				'block_count' => 0,
				'types'       => array(),
				'blocks'      => array(),
			),
			'headings'         => array(),
			'h1_count'         => 0,
			'breadcrumbs'      => array(),
			'internal_links'   => array(),
			'primary_content'  => array(
				'bytes'      => 0,
				'word_count' => 0,
				'sha256'     => null,
			),
		);

		if ( '' === trim( $html ) || ! class_exists( DOMDocument::class ) ) {
			return $this->fallback_extract( $result, $url, $status, $html, $headers );
		}

		$previous_errors = libxml_use_internal_errors( true );
		$document        = new DOMDocument();
		$loaded          = $document->loadHTML(
			'<?xml encoding="UTF-8">' . $html,
			LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING
		);
		libxml_clear_errors();
		libxml_use_internal_errors( $previous_errors );

		if ( false === $loaded ) {
			return $this->fallback_extract( $result, $url, $status, $html, $headers );
		}

		$xpath = new DOMXPath( $document );

		$result['title']            = $this->first_text( $xpath, '//title[1]' );
		$result['meta_description'] = $this->first_attribute( $xpath, '//meta[translate(@name,"ABCDEFGHIJKLMNOPQRSTUVWXYZ","abcdefghijklmnopqrstuvwxyz")="description"][1]', 'content' );
		$result['canonical']        = $this->first_attribute( $xpath, '//link[contains(concat(" ", normalize-space(translate(@rel,"ABCDEFGHIJKLMNOPQRSTUVWXYZ","abcdefghijklmnopqrstuvwxyz")), " "), " canonical ")][1]', 'href' );
		$result['robots']           = $this->first_attribute( $xpath, '//meta[translate(@name,"ABCDEFGHIJKLMNOPQRSTUVWXYZ","abcdefghijklmnopqrstuvwxyz")="robots"][1]', 'content' );
		$result['html_lang']        = $this->first_attribute( $xpath, '//html[1]', 'lang' );
		$result['indexability']     = $this->indexability( $status, (string) ( $result['robots'] ?? '' ), $headers['x_robots_tag'] );
		$result['hreflang']         = $this->hreflang( $xpath );
		$result['open_graph']       = $this->open_graph( $xpath );
		$result['schema']           = $this->schema( $xpath );
		$result['headings']         = $this->headings( $xpath );
		$result['h1_count']         = count(
			array_filter(
				$result['headings'],
				static fn( array $heading ): bool => 1 === ( $heading['level'] ?? 0 )
			)
		);
		$result['breadcrumbs']      = $this->breadcrumbs( $xpath, $url );
		$result['internal_links']   = $this->internal_links( $xpath, $url );
		$result['primary_content']  = $this->primary_content( $xpath );

		return $result;
	}

	/**
	 * Minimal extraction when DOM is unavailable or malformed input cannot load.
	 *
	 * @param array<string,mixed> $result Base result.
	 * @param array{content_type:string,location:string,x_robots_tag:string} $headers Response headers.
	 * @return array<string,mixed>
	 */
	private function fallback_extract( array $result, string $url, int $status, string $html, array $headers ): array {
		if ( 1 === preg_match( '/<title[^>]*>(.*?)<\/title>/is', $html, $match ) ) {
			$result['title'] = $this->normalize_text( wp_strip_all_tags( html_entity_decode( $match[1], ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ) );
		}

		if ( 1 === preg_match( '/<meta[^>]+name=["\']description["\'][^>]+content=["\']([^"\']*)["\'][^>]*>/i', $html, $match ) ) {
			$result['meta_description'] = html_entity_decode( $match[1], ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		}

		if ( 1 === preg_match( '/<link[^>]+rel=["\'][^"\']*canonical[^"\']*["\'][^>]+href=["\']([^"\']+)["\'][^>]*>/i', $html, $match ) ) {
			$result['canonical'] = html_entity_decode( $match[1], ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		}

		if ( 1 === preg_match( '/<meta[^>]+name=["\']robots["\'][^>]+content=["\']([^"\']*)["\'][^>]*>/i', $html, $match ) ) {
			$result['robots'] = html_entity_decode( $match[1], ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		}

		$result['indexability'] = $this->indexability( $status, (string) ( $result['robots'] ?? '' ), $headers['x_robots_tag'] );
		$text                   = $this->normalize_text( wp_strip_all_tags( $html ) );
		$result['primary_content'] = $this->fingerprint_text( $text );

		return $result;
	}

	/**
	 * Resolve one XPath text value.
	 */
	private function first_text( DOMXPath $xpath, string $query ): ?string {
		$nodes = $xpath->query( $query );
		if ( false === $nodes || 0 === $nodes->length ) {
			return null;
		}

		$text = $this->normalize_text( (string) $nodes->item( 0 )?->textContent );
		return '' !== $text ? $text : null;
	}

	/**
	 * Resolve one XPath attribute.
	 */
	private function first_attribute( DOMXPath $xpath, string $query, string $attribute ): ?string {
		$nodes = $xpath->query( $query );
		$node  = false !== $nodes ? $nodes->item( 0 ) : null;
		if ( ! $node instanceof DOMElement || ! $node->hasAttribute( $attribute ) ) {
			return null;
		}

		$value = trim( html_entity_decode( $node->getAttribute( $attribute ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
		return '' !== $value ? $value : null;
	}

	/**
	 * Extract reciprocal alternate language links.
	 *
	 * @return list<array{lang:string,url:string}>
	 */
	private function hreflang( DOMXPath $xpath ): array {
		$rows  = array();
		$nodes = $xpath->query( '//link[contains(concat(" ", normalize-space(translate(@rel,"ABCDEFGHIJKLMNOPQRSTUVWXYZ","abcdefghijklmnopqrstuvwxyz")), " "), " alternate ")][@hreflang][@href]' );
		if ( false === $nodes ) {
			return array();
		}

		foreach ( $nodes as $node ) {
			if ( ! $node instanceof DOMElement ) {
				continue;
			}

			$lang = strtolower( trim( $node->getAttribute( 'hreflang' ) ) );
			$url  = trim( html_entity_decode( $node->getAttribute( 'href' ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
			if ( '' !== $lang && '' !== $url ) {
				$rows[] = array(
					'lang' => $lang,
					'url'  => $url,
				);
			}
		}

		usort(
			$rows,
			static fn( array $left, array $right ): int => strcmp( $left['lang'] . '|' . $left['url'], $right['lang'] . '|' . $right['url'] )
		);

		return $rows;
	}

	/**
	 * Extract Open Graph properties.
	 *
	 * @return list<array{property:string,content:string}>
	 */
	private function open_graph( DOMXPath $xpath ): array {
		$rows  = array();
		$nodes = $xpath->query( '//meta[@property][@content]' );
		if ( false === $nodes ) {
			return array();
		}

		foreach ( $nodes as $node ) {
			if ( ! $node instanceof DOMElement ) {
				continue;
			}

			$property = strtolower( trim( $node->getAttribute( 'property' ) ) );
			if ( ! str_starts_with( $property, 'og:' ) ) {
				continue;
			}

			$rows[] = array(
				'property' => $property,
				'content'  => trim( html_entity_decode( $node->getAttribute( 'content' ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ),
			);
		}

		usort(
			$rows,
			static fn( array $left, array $right ): int => strcmp( $left['property'] . '|' . $left['content'], $right['property'] . '|' . $right['content'] )
		);

		return $rows;
	}

	/**
	 * Extract comparison-safe JSON-LD fingerprints and types.
	 *
	 * @return array{block_count:int,types:list<string>,blocks:list<array{valid_json:bool,types:list<string>,sha256:string}>}
	 */
	private function schema( DOMXPath $xpath ): array {
		$blocks    = array();
		$all_types = array();
		$nodes     = $xpath->query( '//script[translate(@type,"ABCDEFGHIJKLMNOPQRSTUVWXYZ","abcdefghijklmnopqrstuvwxyz")="application/ld+json"]' );

		if ( false === $nodes ) {
			return array(
				'block_count' => 0,
				'types'       => array(),
				'blocks'      => array(),
			);
		}

		foreach ( $nodes as $node ) {
			$raw   = trim( (string) $node->textContent );
			$value = json_decode( $raw, true );
			$types = array();

			if ( JSON_ERROR_NONE === json_last_error() ) {
				$this->collect_schema_types( $value, $types );
				$normalized = $this->normalize_json_value( $value );
				$encoded    = wp_json_encode( $normalized, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
				$hash_input = false === $encoded ? $raw : $encoded;
				$valid      = true;
			} else {
				$hash_input = $raw;
				$valid      = false;
			}

			$types = array_values( array_unique( $types ) );
			sort( $types );
			$all_types = array_merge( $all_types, $types );

			$blocks[] = array(
				'valid_json' => $valid,
				'types'      => $types,
				'sha256'     => hash( 'sha256', $hash_input ),
			);
		}

		$all_types = array_values( array_unique( $all_types ) );
		sort( $all_types );

		return array(
			'block_count' => count( $blocks ),
			'types'       => $all_types,
			'blocks'      => $blocks,
		);
	}

	/**
	 * Collect @type values recursively.
	 *
	 * @param mixed        $value Current decoded JSON value.
	 * @param list<string> $types Collected types.
	 */
	private function collect_schema_types( mixed $value, array &$types ): void {
		if ( ! is_array( $value ) ) {
			return;
		}

		if ( isset( $value['@type'] ) ) {
			$current = $value['@type'];
			if ( is_string( $current ) ) {
				$types[] = $current;
			} elseif ( is_array( $current ) ) {
				foreach ( $current as $type ) {
					if ( is_string( $type ) ) {
						$types[] = $type;
					}
				}
			}
		}

		foreach ( $value as $child ) {
			if ( is_array( $child ) ) {
				$this->collect_schema_types( $child, $types );
			}
		}
	}

	/**
	 * Recursively sort associative JSON objects before hashing.
	 *
	 * @param mixed $value Decoded JSON value.
	 * @return mixed
	 */
	private function normalize_json_value( mixed $value ): mixed {
		if ( ! is_array( $value ) ) {
			return $value;
		}

		foreach ( $value as $key => $child ) {
			$value[ $key ] = $this->normalize_json_value( $child );
		}

		if ( ! array_is_list( $value ) ) {
			ksort( $value );
		}

		return $value;
	}

	/**
	 * Extract H1-H6 outline.
	 *
	 * @return list<array{level:int,text:string}>
	 */
	private function headings( DOMXPath $xpath ): array {
		$rows  = array();
		$nodes = $xpath->query( '//h1|//h2|//h3|//h4|//h5|//h6' );
		if ( false === $nodes ) {
			return array();
		}

		foreach ( $nodes as $node ) {
			$tag = strtolower( $node->nodeName );
			$text = $this->normalize_text( (string) $node->textContent );
			if ( '' === $text || 1 !== preg_match( '/^h([1-6])$/', $tag, $match ) ) {
				continue;
			}

			$rows[] = array(
				'level' => (int) $match[1],
				'text'  => $text,
			);
		}

		return $rows;
	}

	/**
	 * Extract visible breadcrumb containers heuristically.
	 *
	 * @return list<array{text:string,links:list<string>}>
	 */
	private function breadcrumbs( DOMXPath $xpath, string $base_url ): array {
		$rows  = array();
		$query = '//*[contains(translate(@class,"ABCDEFGHIJKLMNOPQRSTUVWXYZ","abcdefghijklmnopqrstuvwxyz"),"breadcrumb") or contains(translate(@aria-label,"ABCDEFGHIJKLMNOPQRSTUVWXYZ","abcdefghijklmnopqrstuvwxyz"),"breadcrumb")]';
		$nodes = $xpath->query( $query );

		if ( false === $nodes ) {
			return array();
		}

		foreach ( $nodes as $node ) {
			$text = $this->normalize_text( (string) $node->textContent );
			if ( '' === $text ) {
				continue;
			}

			$links = array();
			$child_xpath = new DOMXPath( $node->ownerDocument );
			$anchors = $child_xpath->query( './/a[@href]', $node );
			if ( false !== $anchors ) {
				foreach ( $anchors as $anchor ) {
					if ( $anchor instanceof DOMElement ) {
						$normalized = $this->normalize_internal_url( $anchor->getAttribute( 'href' ), $base_url );
						if ( null !== $normalized ) {
							$links[] = $normalized;
						}
					}
				}
			}

			$links = array_values( array_unique( $links ) );
			sort( $links );
			$rows[] = array(
				'text'  => $text,
				'links' => $links,
			);
		}

		return $rows;
	}

	/**
	 * Extract same-origin internal links.
	 *
	 * @return list<string>
	 */
	private function internal_links( DOMXPath $xpath, string $base_url ): array {
		$links = array();
		$nodes = $xpath->query( '//a[@href]' );
		if ( false === $nodes ) {
			return array();
		}

		foreach ( $nodes as $node ) {
			if ( ! $node instanceof DOMElement ) {
				continue;
			}

			$normalized = $this->normalize_internal_url( $node->getAttribute( 'href' ), $base_url );
			if ( null !== $normalized ) {
				$links[] = $normalized;
			}
		}

		$links = array_values( array_unique( $links ) );
		sort( $links );
		return $links;
	}

	/**
	 * Hash primary visible content without persisting the body.
	 *
	 * @return array{bytes:int,word_count:int,sha256:string|null}
	 */
	private function primary_content( DOMXPath $xpath ): array {
		$nodes = $xpath->query( '//main[1]' );
		$node  = false !== $nodes ? $nodes->item( 0 ) : null;

		if ( ! $node instanceof DOMNode ) {
			$nodes = $xpath->query( '//body[1]' );
			$node  = false !== $nodes ? $nodes->item( 0 ) : null;
		}

		if ( ! $node instanceof DOMNode ) {
			return array(
				'bytes'      => 0,
				'word_count' => 0,
				'sha256'     => null,
			);
		}

		return $this->fingerprint_text( $this->normalize_text( (string) $node->textContent ) );
	}

	/**
	 * Convert text into non-reversible comparison metadata.
	 *
	 * @return array{bytes:int,word_count:int,sha256:string|null}
	 */
	private function fingerprint_text( string $text ): array {
		if ( '' === $text ) {
			return array(
				'bytes'      => 0,
				'word_count' => 0,
				'sha256'     => null,
			);
		}

		$words = preg_split( '/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY );

		return array(
			'bytes'      => strlen( $text ),
			'word_count' => is_array( $words ) ? count( $words ) : 0,
			'sha256'     => hash( 'sha256', $text ),
		);
	}

	/**
	 * Classify indexability from response and robots signals.
	 *
	 * @return array{state:string,indexable:bool|null,reasons:list<string>}
	 */
	private function indexability( int $status, string $robots, string $x_robots_tag ): array {
		$directives = strtolower( $robots . ',' . $x_robots_tag );
		$reasons    = array();

		if ( 0 === $status ) {
			return array(
				'state'     => 'unknown',
				'indexable' => null,
				'reasons'   => array( 'request-error' ),
			);
		}

		if ( $status >= 300 && $status < 400 ) {
			return array(
				'state'     => 'redirect',
				'indexable' => false,
				'reasons'   => array( 'http-redirect' ),
			);
		}

		if ( $status >= 400 ) {
			return array(
				'state'     => 404 === $status ? 'not-found' : 'http-error',
				'indexable' => false,
				'reasons'   => array( 'http-' . $status ),
			);
		}

		if ( str_contains( $directives, 'noindex' ) || str_contains( $directives, 'none' ) ) {
			$reasons[] = 'robots-noindex';
			return array(
				'state'     => 'noindex',
				'indexable' => false,
				'reasons'   => $reasons,
			);
		}

		return array(
			'state'     => 'indexable',
			'indexable' => true,
			'reasons'   => array(),
		);
	}

	/**
	 * Normalize human-readable text.
	 */
	private function normalize_text( string $text ): string {
		$normalized = preg_replace( '/\s+/u', ' ', trim( $text ) );
		return is_string( $normalized ) ? $normalized : '';
	}

	/**
	 * Resolve and normalize a same-origin link.
	 */
	private function normalize_internal_url( string $href, string $base_url ): ?string {
		$href = trim( html_entity_decode( $href, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
		if ( '' === $href || str_starts_with( $href, '#' ) || 1 === preg_match( '/^(?:mailto|tel|javascript|data):/i', $href ) ) {
			return null;
		}

		$base = wp_parse_url( $base_url );
		if ( ! is_array( $base ) || ! isset( $base['scheme'], $base['host'] ) ) {
			return null;
		}

		if ( str_starts_with( $href, '//' ) ) {
			$href = (string) $base['scheme'] . ':' . $href;
		} elseif ( ! preg_match( '#^https?://#i', $href ) ) {
			$origin = (string) $base['scheme'] . '://' . (string) $base['host'];
			if ( isset( $base['port'] ) ) {
				$origin .= ':' . (int) $base['port'];
			}

			if ( str_starts_with( $href, '/' ) ) {
				$href = $origin . $href;
			} else {
				$base_path = isset( $base['path'] ) ? (string) $base['path'] : '/';
				$directory = rtrim( str_replace( '\\', '/', dirname( $base_path ) ), '/' );
				$href      = $origin . ( '' === $directory ? '' : $directory ) . '/' . $href;
			}
		}

		$parts = wp_parse_url( $href );
		if ( ! is_array( $parts ) || ! isset( $parts['scheme'], $parts['host'] ) ) {
			return null;
		}

		$home = wp_parse_url( home_url( '/' ) );
		if ( ! is_array( $home ) || ! isset( $home['host'] ) || strtolower( (string) $home['host'] ) !== strtolower( (string) $parts['host'] ) ) {
			return null;
		}

		$scheme = strtolower( (string) $parts['scheme'] );
		if ( ! in_array( $scheme, array( 'http', 'https' ), true ) ) {
			return null;
		}

		$port      = isset( $parts['port'] ) ? (int) $parts['port'] : ( 'https' === $scheme ? 443 : 80 );
		$home_port = isset( $home['port'] )
			? (int) $home['port']
			: ( isset( $home['scheme'] ) && 'https' === strtolower( (string) $home['scheme'] ) ? 443 : 80 );
		if ( $port !== $home_port ) {
			return null;
		}

		$path = isset( $parts['path'] ) && '' !== (string) $parts['path'] ? (string) $parts['path'] : '/';
		$path = $this->normalize_path( $path );

		$port_fragment = in_array( $port, array( 80, 443 ), true ) ? '' : ':' . $port;
		$query         = isset( $parts['query'] ) && '' !== (string) $parts['query'] ? '?' . (string) $parts['query'] : '';

		return $scheme . '://' . strtolower( (string) $parts['host'] ) . $port_fragment . $path . $query;
	}

	/**
	 * Collapse dot segments in a URL path.
	 */
	private function normalize_path( string $path ): string {
		$segments = explode( '/', $path );
		$output   = array();

		foreach ( $segments as $segment ) {
			if ( '' === $segment || '.' === $segment ) {
				continue;
			}
			if ( '..' === $segment ) {
				array_pop( $output );
				continue;
			}
			$output[] = $segment;
		}

		return '/' . implode( '/', $output ) . ( str_ends_with( $path, '/' ) && array() !== $output ? '/' : '' );
	}
}
