<?php
/**
 * Native SEO presenter.
 *
 * @package SeoGeoCore
 */

declare(strict_types=1);

namespace SeoGeo\Core\Seo;

/**
 * Connects native SEO policy to WordPress frontend output.
 */
final class NativeSeoPresenter {
	/**
	 * SEO output authority.
	 *
	 * @var SeoOutputAuthority
	 */
	private SeoOutputAuthority $authority;

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
	 * Meta description resolver.
	 *
	 * @var MetaDescriptionResolver
	 */
	private MetaDescriptionResolver $description;

	/**
	 * Create the native presenter.
	 *
	 * @param SeoOutputAuthority      $authority    Output authority.
	 * @param IndexabilityResolver    $indexability Indexability resolver.
	 * @param CanonicalResolver       $canonical    Canonical resolver.
	 * @param MetaDescriptionResolver $description  Description resolver.
	 */
	public function __construct(
		SeoOutputAuthority $authority,
		IndexabilityResolver $indexability,
		CanonicalResolver $canonical,
		MetaDescriptionResolver $description
	) {
		$this->authority    = $authority;
		$this->indexability = $indexability;
		$this->canonical    = $canonical;
		$this->description  = $description;
	}

	/**
	 * Register native frontend output only when Core is authoritative.
	 */
	public function register(): void {
		if ( ! $this->authority->native_owns( SeoOutputAuthority::SIGNAL_CANONICAL ) ) {
			return;
		}

		remove_action( 'wp_head', 'rel_canonical' );
		add_action( 'wp_head', array( $this, 'render_head' ), 9 );
		add_filter( 'wp_robots', array( $this, 'filter_robots' ), 99 );
	}

	/**
	 * Render canonical and meta description output.
	 */
	public function render_head(): void {
		$state = $this->indexability->resolve();
		$url   = $this->canonical->resolve( $state );

		if ( null !== $url ) {
			echo '<link rel="canonical" href="' . esc_url( $url ) . '" />' . "\n";
		}

		if ( $this->authority->native_owns( SeoOutputAuthority::SIGNAL_META_DESCRIPTION ) ) {
			$description = $this->description->resolve();

			if ( null !== $description ) {
				echo '<meta name="description" content="' . esc_attr( $description ) . '" />' . "\n";
			}
		}
	}

	/**
	 * Apply the resolved indexability state through WordPress robots output.
	 *
	 * @param array<string, bool|string> $robots Existing robots directives.
	 * @return array<string, bool|string>
	 */
	public function filter_robots( array $robots ): array {
		if ( ! $this->authority->native_owns( SeoOutputAuthority::SIGNAL_ROBOTS ) ) {
			return $robots;
		}

		$state = $this->indexability->resolve();

		if ( IndexabilityResolver::INDEXABLE === $state ) {
			return $robots;
		}

		unset( $robots['index'], $robots['follow'], $robots['nofollow'] );
		$robots['noindex'] = true;

		if ( IndexabilityResolver::NOINDEX_NOFOLLOW === $state ) {
			$robots['nofollow'] = true;
		} else {
			$robots['follow'] = true;
		}

		return $robots;
	}
}
