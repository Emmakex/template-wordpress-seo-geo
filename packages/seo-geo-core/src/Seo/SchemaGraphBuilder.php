<?php
/**
 * Native Schema.org graph builder.
 *
 * @package SeoGeoCore
 */

declare(strict_types=1);

namespace SeoGeo\Core\Seo;

/**
 * Builds the minimal native Schema.org graph from existing SEO authorities.
 */
final class SchemaGraphBuilder {
	/**
	 * Indexability authority.
	 *
	 * @var IndexabilityResolver
	 */
	private IndexabilityResolver $indexability;

	/**
	 * Canonical URL authority.
	 *
	 * @var CanonicalResolver
	 */
	private CanonicalResolver $canonical;

	/**
	 * Reusable breadcrumb authority.
	 *
	 * @var BreadcrumbResolver
	 */
	private BreadcrumbResolver $breadcrumbs;

	/**
	 * Localized SEO authority.
	 *
	 * @var LocalizedSeoResolver
	 */
	private LocalizedSeoResolver $localized;

	/**
	 * Create the graph builder.
	 *
	 * @param IndexabilityResolver $indexability Native indexability resolver.
	 * @param CanonicalResolver    $canonical    Canonical URL resolver.
	 * @param BreadcrumbResolver   $breadcrumbs  Reusable breadcrumb resolver.
	 * @param LocalizedSeoResolver $localized    Localized SEO authority.
	 */
	public function __construct(
		IndexabilityResolver $indexability,
		CanonicalResolver $canonical,
		BreadcrumbResolver $breadcrumbs,
		LocalizedSeoResolver $localized
	) {
		$this->indexability = $indexability;
		$this->canonical    = $canonical;
		$this->breadcrumbs  = $breadcrumbs;
		$this->localized    = $localized;
	}

	/**
	 * Resolve the current request's native JSON-LD graph.
	 *
	 * Only indexable HTML receives native Schema output. The graph deliberately
	 * starts with WebSite, WebPage and BreadcrumbList only; entity-specific
	 * Organization/Person/Article/LocalBusiness nodes require later explicit
	 * data contracts.
	 *
	 * @return array{'@context': string, '@graph': array<int, array<string, mixed>>}|null
	 */
	public function resolve(): ?array {
		$state = $this->indexability->resolve();
		if ( IndexabilityResolver::INDEXABLE !== $state ) {
			return null;
		}

		$canonical = $this->canonical->resolve( $state );
		if ( null === $canonical || ! $this->is_absolute_http_url( $canonical ) ) {
			return null;
		}

		$website_id = home_url( '/#website' );
		if ( ! $this->is_absolute_http_url( $website_id ) ) {
			return null;
		}

		$website = array(
			'@type' => 'WebSite',
			'@id'   => $website_id,
			'url'   => home_url( '/' ),
		);

		$site_name = $this->clean_text( get_bloginfo( 'name' ) );
		if ( null !== $site_name ) {
			$website['name'] = $site_name;
		}

		$webpage_id = $canonical . '#webpage';
		$webpage    = array(
			'@type'    => 'WebPage',
			'@id'      => $webpage_id,
			'url'      => $canonical,
			'isPartOf' => array( '@id' => $website_id ),
		);

		$page_name = $this->current_page_name();
		if ( null !== $page_name ) {
			$webpage['name'] = $page_name;
		}

		$language = $this->current_language();
		if ( null !== $language ) {
			$webpage['inLanguage'] = $language;
		}

		$nodes      = array( $website );
		$breadcrumb = $this->breadcrumb_node( $canonical );

		if ( null !== $breadcrumb ) {
			$webpage['breadcrumb'] = array( '@id' => $breadcrumb['@id'] );
			$nodes[]                = $breadcrumb;
		}

		$nodes[] = $webpage;

		return array(
			'@context' => 'https://schema.org',
			'@graph'   => $nodes,
		);
	}

	/**
	 * Resolve the current document language as a conservative BCP 47 tag.
	 */
	private function current_language(): ?string {
		$locale = $this->localized->current_locale() ?? get_locale();
		$tag    = str_replace( '_', '-', trim( $locale ) );

		return 1 === preg_match( '/^[A-Za-z]{2,3}(?:-[A-Za-z0-9]{2,8})*$/', $tag )
			? $tag
			: null;
	}

	/**
	 * Resolve a concise page name without inventing content.
	 */
	private function current_page_name(): ?string {
		$name = '';

		if ( is_singular() ) {
			$object_id = get_queried_object_id();
			if ( 0 < $object_id ) {
				$name = get_the_title( $object_id );
			}
		} elseif ( is_front_page() ) {
			$name = get_bloginfo( 'name' );
		} else {
			$name = wp_get_document_title();
		}

		return $this->clean_text( $name );
	}

	/**
	 * Build a BreadcrumbList only when the reusable breadcrumb contract provides
	 * a complete absolute-URL chain. Missing URLs suppress the whole node rather
	 * than fabricating a structured-data destination.
	 *
	 * @param string $canonical Authoritative current canonical URL.
	 * @return array<string, mixed>|null
	 */
	private function breadcrumb_node( string $canonical ): ?array {
		$breadcrumbs = $this->breadcrumbs->resolve();
		if ( count( $breadcrumbs ) < 2 ) {
			return null;
		}

		$elements = array();
		$position = 1;

		foreach ( $breadcrumbs as $breadcrumb ) {
			$label = $this->clean_text( $breadcrumb['label'] );
			$url   = $breadcrumb['url'];

			if (
				null === $label
				|| null === $url
				|| ! $this->is_absolute_http_url( $url )
			) {
				return null;
			}

			$elements[] = array(
				'@type'    => 'ListItem',
				'position' => $position,
				'name'     => $label,
				'item'     => array(
					'@id'  => $url,
					'name' => $label,
				),
			);
			++$position;
		}

		return array(
			'@type'           => 'BreadcrumbList',
			'@id'             => $canonical . '#breadcrumb',
			'itemListElement' => $elements,
		);
	}

	/**
	 * Normalize visible text for structured data.
	 *
	 * @param string $value Raw visible text.
	 */
	private function clean_text( string $value ): ?string {
		$value = html_entity_decode( wp_strip_all_tags( $value, true ), ENT_QUOTES | ENT_HTML5, get_bloginfo( 'charset' ) );
		$value = preg_replace( '/\s+/u', ' ', $value );
		$value = is_string( $value ) ? trim( $value ) : '';

		return '' !== $value ? $value : null;
	}

	/**
	 * Accept only absolute HTTP(S) URLs.
	 *
	 * @param string $url Candidate URL.
	 */
	private function is_absolute_http_url( string $url ): bool {
		$scheme = wp_parse_url( $url, PHP_URL_SCHEME );
		$host   = wp_parse_url( $url, PHP_URL_HOST );

		return is_string( $scheme )
			&& is_string( $host )
			&& '' !== $host
			&& in_array( strtolower( $scheme ), array( 'http', 'https' ), true );
	}
}
