<?php
/**
 * Native Schema article resolver.
 *
 * @package SeoGeoCore
 */

declare(strict_types=1);

namespace SeoGeo\Core\Schema;

use WP_Post;

/**
 * Resolves truthful BlogPosting data for native WordPress posts.
 */
final class SchemaArticleResolver {
	/**
	 * Native identity authority.
	 *
	 * @var SchemaIdentityResolver
	 */
	private SchemaIdentityResolver $identity;

	/**
	 * Create the article resolver.
	 *
	 * @param SchemaIdentityResolver $identity Native identity authority.
	 */
	public function __construct( SchemaIdentityResolver $identity ) {
		$this->identity = $identity;
	}

	/**
	 * Resolve the current built-in WordPress post as a BlogPosting source.
	 *
	 * Pages and other post types are intentionally excluded until a future
	 * preset or explicit content contract declares them article-like.
	 *
	 * @return array{
	 *     headline:string,
	 *     date_published:string,
	 *     date_modified:string,
	 *     author:array{id:string,name:string,url:string,description:string}|null
	 * }|null
	 */
	public function current(): ?array {
		if ( ! is_singular( 'post' ) ) {
			return null;
		}

		$post = get_queried_object();
		if ( ! $post instanceof WP_Post || 'post' !== $post->post_type || 'publish' !== $post->post_status ) {
			return null;
		}

		$headline = $this->text( get_the_title( $post ) );
		if ( '' === $headline ) {
			return null;
		}

		$published = get_post_datetime( $post, 'date' );
		$modified  = get_post_datetime( $post, 'modified' );

		if ( false === $published || false === $modified ) {
			return null;
		}

		return array(
			'headline'       => $headline,
			'date_published' => $published->format( DATE_W3C ),
			'date_modified'  => $modified->format( DATE_W3C ),
			'author'         => $this->identity->author( (int) $post->post_author ),
		);
	}

	/**
	 * Normalize visible WordPress text for article use.
	 *
	 * @param string $value Raw visible text.
	 */
	private function text( string $value ): string {
		return trim( wp_strip_all_tags( $value, true ) );
	}
}
