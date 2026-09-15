<?php
/**
 * Indexability resolver.
 *
 * @package SeoGeoCore
 */

declare(strict_types=1);

namespace SeoGeo\Core\Seo;

/**
 * Resolves the current request into one explicit indexability state.
 */
final class IndexabilityResolver {
	public const INDEXABLE        = 'indexable';
	public const NOINDEX_FOLLOW   = 'noindex-follow';
	public const NOINDEX_NOFOLLOW = 'noindex-nofollow';
	public const REDIRECT         = 'redirect';
	public const NOT_FOUND        = 'not-found';
	public const GONE             = '410-gone';

	/**
	 * Allowed resolver states.
	 *
	 * @var array<int, string>
	 */
	private const STATES = array(
		self::INDEXABLE,
		self::NOINDEX_FOLLOW,
		self::NOINDEX_NOFOLLOW,
		self::REDIRECT,
		self::NOT_FOUND,
		self::GONE,
	);

	/**
	 * Resolve the current WordPress request.
	 */
	public function resolve(): string {
		$state = self::INDEXABLE;

		if ( is_404() ) {
			$state = self::NOT_FOUND;
		} elseif ( ! get_option( 'blog_public' ) ) {
			$state = self::NOINDEX_NOFOLLOW;
		} elseif ( is_preview() ) {
			$state = self::NOINDEX_NOFOLLOW;
		} elseif ( is_search() || is_embed() ) {
			$state = self::NOINDEX_FOLLOW;
		} elseif ( is_singular() && post_password_required() ) {
			$state = self::NOINDEX_FOLLOW;
		}

		/**
		 * Filters the resolved indexability state.
		 *
		 * Consumers may use this to expose deliberate redirect/gone policies,
		 * but unsupported values are rejected and the native state is retained.
		 *
		 * @param string $state Resolved state.
		 */
		$filtered = apply_filters( 'seo_geo_indexability_state', $state );

		if ( is_string( $filtered ) && in_array( $filtered, self::STATES, true ) ) {
			return $filtered;
		}

		return $state;
	}
}
