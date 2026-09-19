<?php
/**
 * Native content provenance resolver.
 *
 * @package SeoGeoCore
 */

declare(strict_types=1);

namespace SeoGeo\Core\Geo;

use SeoGeo\Core\Schema\SchemaIdentityResolver;
use SeoGeo\Core\Seo\CanonicalResolver;
use SeoGeo\Core\Seo\IndexabilityResolver;
use WP_Post;

/**
 * Resolves authoritative author, date, publisher and source metadata for public posts.
 */
final class ContentProvenanceResolver {
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
	 * Identity authority.
	 *
	 * @var SchemaIdentityResolver
	 */
	private SchemaIdentityResolver $identity;

	/**
	 * Create the resolver.
	 *
	 * @param IndexabilityResolver    $indexability Indexability authority.
	 * @param CanonicalResolver       $canonical    Canonical URL authority.
	 * @param SchemaIdentityResolver  $identity     Identity authority.
	 */
	public function __construct(
		IndexabilityResolver $indexability,
		CanonicalResolver $canonical,
		SchemaIdentityResolver $identity
	) {
		$this->indexability = $indexability;
		$this->canonical    = $canonical;
		$this->identity     = $identity;
	}

	/**
	 * Resolve provenance for the current authoritative built-in post.
	 *
	 * @return array{
	 *     source_url:string,
	 *     author:array{id:string,name:string,url:string,description:string}|null,
	 *     publisher:array{id:string,name:string,url:string}|null,
	 *     date_published:string,
	 *     date_modified:string
	 * }|null
	 */
	public function current(): ?array {
		if ( ! is_singular( 'post' ) ) {
			return null;
		}

		$state = $this->indexability->resolve();
		if ( IndexabilityResolver::INDEXABLE !== $state ) {
			return null;
		}

		$source_url = $this->canonical->resolve( $state );
		if ( null === $source_url ) {
			return null;
		}

		$post_id = get_queried_object_id();
		$data    = $this->for_post( $post_id );

		if ( null === $data ) {
			return null;
		}

		return array(
			'source_url'     => $source_url,
			'author'         => $data['author'],
			'publisher'      => $data['publisher'],
			'date_published' => $data['date_published'],
			'date_modified'  => $data['date_modified'],
		);
	}

	/**
	 * Resolve provenance for one published built-in post.
	 *
	 * @param int $post_id WordPress post ID.
	 * @return array{
	 *     author:array{id:string,name:string,url:string,description:string}|null,
	 *     publisher:array{id:string,name:string,url:string}|null,
	 *     date_published:string,
	 *     date_modified:string
	 * }|null
	 */
	public function for_post( int $post_id ): ?array {
		$post = get_post( $post_id );

		if (
			! $post instanceof WP_Post
			|| 'post' !== $post->post_type
			|| 'publish' !== $post->post_status
			|| '' !== $post->post_password
		) {
			return null;
		}

		$published = get_post_datetime( $post, 'date' );
		$modified  = get_post_datetime( $post, 'modified' );

		if ( false === $published || false === $modified ) {
			return null;
		}

		return array(
			'author'         => $this->identity->author( (int) $post->post_author ),
			'publisher'      => $this->identity->organization(),
			'date_published' => $published->format( DATE_W3C ),
			'date_modified'  => $modified->format( DATE_W3C ),
		);
	}
}
