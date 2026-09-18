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
use WP_Post;

/**
 * Builds the baseline native WebSite + WebPage JSON-LD graph.
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
	 * Native identity resolver.
	 *
	 * @var SchemaIdentityResolver
	 */
	private SchemaIdentityResolver $identity;

	/**
	 * Create the graph builder.
	 *
	 * @param IndexabilityResolver   $indexability Native indexability authority.
	 * @param CanonicalResolver      $canonical    Canonical URL authority.
	 * @param LanguageManager        $language     Active language facade.
	 * @param SchemaNodeIds          $ids          Stable node-ID generator.
	 * @param SchemaIdentityResolver $identity     Native identity authority.
	 */
	public function __construct(
		IndexabilityResolver $indexability,
		CanonicalResolver $canonical,
		LanguageManager $language,
		SchemaNodeIds $ids,
		SchemaIdentityResolver $identity
	) {
		$this->indexability = $indexability;
		$this->canonical    = $canonical;
		$this->language     = $language;
		$this->ids          = $ids;
		$this->identity     = $identity;
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

		$web_page = array(
			'@type'      => 'WebPage',
			'@id'        => $this->ids->web_page( $canonical_url ),
			'url'        => $canonical_url,
			'isPartOf'   => array( '@id' => $website_id ),
			'inLanguage' => $this->bcp47( $this->language->current_locale() ),
		);

		$page_name = $this->text( wp_get_document_title() );
		if ( '' !== $page_name ) {
			$web_page['name'] = $page_name;
		}

		$graph = array( $website, $web_page );

		$organization = $this->identity->organization();
		if ( is_front_page() && null !== $organization ) {
			$organization_node = $this->organization_node( $organization );

			$website['publisher'] = array( '@id' => $organization['id'] );

			$graph[0] = $website;
			$graph[]  = $organization_node;
		}

		$author = $this->identity->current_author();
		if ( null !== $author ) {
			$web_page['@type']      = 'ProfilePage';
			$web_page['mainEntity'] = array( '@id' => $author['id'] );

			$person = $this->person_node( $author, $web_page['@id'] );

			$graph[1] = $web_page;
			$graph[]  = $person;
		}

		if ( is_singular( 'post' ) ) {
			$post = get_queried_object();

			if ( $post instanceof WP_Post ) {
				$post_author = $this->identity->person_for_user_id( (int) $post->post_author );
				$published   = get_post_datetime( $post );
				$modified    = get_post_datetime( $post, 'modified' );
				$headline    = $this->text( get_the_title( $post ) );

				if ( null !== $post_author && false !== $published && false !== $modified && '' !== $headline ) {
					$blog_posting = array(
						'@type'            => 'BlogPosting',
						'@id'              => $this->ids->blog_posting( $canonical_url ),
						'url'              => $canonical_url,
						'headline'         => $headline,
						'datePublished'    => $published->format( DATE_W3C ),
						'dateModified'     => $modified->format( DATE_W3C ),
						'inLanguage'       => $this->bcp47( $this->language->current_locale() ),
						'mainEntityOfPage' => array( '@id' => $web_page['@id'] ),
						'author'           => array( '@id' => $post_author['id'] ),
					);

					$image_url = get_the_post_thumbnail_url( $post, 'full' );
					if ( is_string( $image_url ) && '' !== $image_url ) {
						$blog_posting['image'] = $image_url;
					}

					$graph[] = $this->person_node( $post_author );

					if ( null !== $organization ) {
						$blog_posting['publisher'] = array( '@id' => $organization['id'] );
						$website['publisher']       = array( '@id' => $organization['id'] );

						$graph[0] = $website;
						$graph[]  = $this->organization_node( $organization );
					}

					$graph[] = $blog_posting;
				}
		}

		return array(
			'@context' => 'https://schema.org',
			'@graph'   => $graph,
		);
	}

	/**
	 * Build one shared Organization node.
	 *
	 * @param array{id:string,name:string,url:string} $organization Resolved Organization identity.
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
	 * Build one shared Person node.
	 *
	 * @param array{id:string,name:string,url:string,description:string} $person              Resolved Person identity.
	 * @param string|null                                               $main_entity_page_id Optional ProfilePage node ID.
	 * @return array<string, mixed>
	 */
	private function person_node( array $person, ?string $main_entity_page_id = null ): array {
		$node = array(
			'@type' => 'Person',
			'@id'   => $person['id'],
			'name'  => $person['name'],
			'url'   => $person['url'],
		);

		if ( '' !== $person['description'] ) {
			$node['description'] = $person['description'];
		}

		if ( null !== $main_entity_page_id ) {
			$node['mainEntityOfPage'] = array( '@id' => $main_entity_page_id );
		}

		return $node;
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
