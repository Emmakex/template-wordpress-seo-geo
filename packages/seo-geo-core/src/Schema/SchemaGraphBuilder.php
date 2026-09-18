<?php
/**
 * Native Schema graph builder.
 *
 * @package SeoGeoCore
 */

declare(strict_types=1);

namespace SeoGeo\Core\Schema;

use SeoGeo\Core\Language\LanguageManager;
use SeoGeo\Core\Seo\CanonicalResolver;
use SeoGeo\Core\Seo\IndexabilityResolver;

/**
 * Builds the native single-owner JSON-LD graph.
 */
final class SchemaGraphBuilder {
	/**
	 * Indexability resolver.
	 *
	 * @var IndexabilityResolver
	 */
	private IndexabilityResolver $indexability;

	/**
	 * Canonical resolver.
	 *
	 * @var CanonicalResolver
	 */
	private CanonicalResolver $canonical;

	/**
	 * Language facade.
	 *
	 * @var LanguageManager
	 */
	private LanguageManager $language;

	/**
	 * Stable node-ID generator.
	 *
	 * @var SchemaNodeIds
	 */
	private SchemaNodeIds $ids;

	/**
	 * Native BreadcrumbList resolver.
	 *
	 * @var SchemaBreadcrumbResolver
	 */
	private SchemaBreadcrumbResolver $breadcrumb;

	/**
	 * Native identity resolver.
	 *
	 * @var SchemaIdentityResolver
	 */
	private SchemaIdentityResolver $identity;

	/**
	 * Native LocalBusiness resolver.
	 *
	 * @var SchemaLocalBusinessResolver
	 */
	private SchemaLocalBusinessResolver $local_business;

	/**
	 * Native BlogPosting resolver.
	 *
	 * @var SchemaArticleResolver
	 */
	private SchemaArticleResolver $article;

	/**
	 * Create the graph builder.
	 *
	 * @param IndexabilityResolver        $indexability Native indexability authority.
	 * @param CanonicalResolver           $canonical    Canonical URL authority.
	 * @param LanguageManager             $language     Active language facade.
	 * @param SchemaNodeIds               $ids          Stable node-ID generator.
	 * @param SchemaBreadcrumbResolver    $breadcrumb      Native BreadcrumbList authority.
	 * @param SchemaIdentityResolver      $identity        Native identity authority.
	 * @param SchemaLocalBusinessResolver $local_business  Native LocalBusiness authority.
	 * @param SchemaArticleResolver       $article         Native BlogPosting data authority.
	 */
	public function __construct(
		IndexabilityResolver $indexability,
		CanonicalResolver $canonical,
		LanguageManager $language,
		SchemaNodeIds $ids,
		SchemaBreadcrumbResolver $breadcrumb,
		SchemaIdentityResolver $identity,
		SchemaLocalBusinessResolver $local_business,
		SchemaArticleResolver $article
	) {
		$this->indexability   = $indexability;
		$this->canonical      = $canonical;
		$this->language       = $language;
		$this->ids            = $ids;
		$this->breadcrumb     = $breadcrumb;
		$this->identity       = $identity;
		$this->local_business = $local_business;
		$this->article        = $article;
	}

	/**
	 * Resolve the current public Schema graph.
	 *
	 * @return array<string, mixed>
	 */
	public function resolve(): array {
		$state = $this->indexability->resolve();

		if ( IndexabilityResolver::INDEXABLE !== $state ) {
			return array();
		}

		$canonical_url = $this->canonical->resolve( $state );

		if ( null === $canonical_url ) {
			return array();
		}

		$website_id = $this->ids->website();
		$website    = array(
			'@type' => 'WebSite',
			'@id'   => $website_id,
			'url'   => home_url( '/' ),
		);

		$site_name = $this->text( get_bloginfo( 'name' ) );
		if ( '' !== $site_name ) {
			$website['name'] = $site_name;
		}

		$web_page_id = $this->ids->web_page( $canonical_url );
		$web_page    = array(
			'@type'      => 'WebPage',
			'@id'        => $web_page_id,
			'url'        => $canonical_url,
			'isPartOf'   => array( '@id' => $website_id ),
			'inLanguage' => $this->bcp47( $this->language->current_locale() ),
		);

		$page_name = $this->text( wp_get_document_title() );
		if ( '' !== $page_name ) {
			$web_page['name'] = $page_name;
		}

		$breadcrumb = $this->breadcrumb->current( $canonical_url );
		if ( null !== $breadcrumb ) {
			$web_page['breadcrumb'] = array( '@id' => $breadcrumb['@id'] );
		}

		$graph = array( $website, $web_page );
		if ( null !== $breadcrumb ) {
			$graph[] = $breadcrumb;
		}
		$organization   = $this->identity->organization();
		$local_business = $this->local_business->resolve();

		if ( is_front_page() && null !== $local_business ) {
			$website['publisher']   = array( '@id' => $local_business['@id'] );
			$web_page['mainEntity'] = array( '@id' => $local_business['@id'] );

			$graph[0] = $website;
			$graph[1] = $web_page;
			$graph[]  = $local_business;
		} elseif ( is_front_page() && null !== $organization ) {
			$website['publisher'] = array( '@id' => $organization['id'] );

			$graph[0] = $website;
			$graph[]  = $this->organization_node( $organization );
		}

		$author = $this->identity->current_author();
		if ( null !== $author ) {
			$web_page['@type']      = 'ProfilePage';
			$web_page['mainEntity'] = array( '@id' => $author['id'] );

			$graph[1] = $web_page;
			$graph[]  = $this->person_node( $author, $web_page_id );
		}

		$article = $this->article->current();
		if ( null !== $article ) {
			$article_id   = $this->ids->article( $canonical_url );
			$article_node = array(
				'@type'            => 'BlogPosting',
				'@id'              => $article_id,
				'url'              => $canonical_url,
				'mainEntityOfPage' => array( '@id' => $web_page_id ),
				'headline'         => $article['headline'],
				'datePublished'    => $article['date_published'],
				'dateModified'     => $article['date_modified'],
				'inLanguage'       => $this->bcp47( $this->language->current_locale() ),
			);

			$web_page['mainEntity'] = array( '@id' => $article_id );
			$graph[1]               = $web_page;

			$article_author = $article['author'];
			if ( null !== $article_author ) {
				$article_node['author'] = array( '@id' => $article_author['id'] );
			}

			if ( null !== $local_business ) {
				$article_node['publisher'] = array( '@id' => $local_business['@id'] );
			} elseif ( null !== $organization ) {
				$article_node['publisher'] = array( '@id' => $organization['id'] );
			}

			$graph[] = $article_node;

			if ( null !== $article_author ) {
				$profile_page_id = $this->ids->web_page( $article_author['url'] );
				$graph[]         = $this->person_node( $article_author, $profile_page_id );
			}

			if ( null !== $local_business ) {
				$graph[] = $local_business;
			} elseif ( null !== $organization ) {
				$graph[] = $this->organization_node( $organization );
			}
		}

		return array(
			'@context' => 'https://schema.org',
			'@graph'   => $graph,
		);
	}

	/**
	 * Build one stable Organization graph node.
	 *
	 * @param array{id:string,name:string,url:string} $organization Resolved organization identity.
	 * @return array<string, string>
	 */
	private function organization_node( array $organization ): array {
		return array(
			'@type' => 'Organization',
			'@id'   => $organization['id'],
			'name'  => $organization['name'],
			'url'   => $organization['url'],
		);
	}

	/**
	 * Build one stable Person graph node.
	 *
	 * @param array{id:string,name:string,url:string,description:string} $author  Resolved author identity.
	 * @param string                                                     $page_id Stable profile WebPage ID.
	 * @return array<string, mixed>
	 */
	private function person_node( array $author, string $page_id ): array {
		$person = array(
			'@type'            => 'Person',
			'@id'              => $author['id'],
			'name'             => $author['name'],
			'url'              => $author['url'],
			'mainEntityOfPage' => array( '@id' => $page_id ),
		);

		return $person;
	}

	/**
	 * Normalize visible WordPress text for graph use.
	 *
	 * @param string $value Raw visible text.
	 */
	private function text( string $value ): string {
		return trim( wp_strip_all_tags( $value, true ) );
	}

	/**
	 * Convert a WordPress locale into a conservative BCP 47 language tag.
	 *
	 * @param string $locale WordPress locale.
	 */
	private function bcp47( string $locale ): string {
		$parts = preg_split( '/[_-]/', trim( $locale ) );

		if ( ! is_array( $parts ) || array() === $parts ) {
			return 'en';
		}

		$tag = array();

		foreach ( $parts as $index => $part ) {
			if ( '' === $part ) {
				continue;
			}

			if ( 0 === $index ) {
				$tag[] = strtolower( $part );
			} elseif ( 4 === strlen( $part ) && ctype_alpha( $part ) ) {
				$tag[] = ucfirst( strtolower( $part ) );
			} elseif ( 2 === strlen( $part ) && ctype_alpha( $part ) ) {
				$tag[] = strtoupper( $part );
			} else {
				$tag[] = $part;
			}
		}

		return array() !== $tag ? implode( '-', $tag ) : 'en';
	}
}
