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
	 * Open Graph resolver.
	 *
	 * @var OpenGraphResolver
	 */
	private OpenGraphResolver $open_graph;

	/**
	 * Hreflang resolver.
	 *
	 * @var HreflangResolver
	 */
	private HreflangResolver $hreflang;

	/**
	 * Create the native presenter.
	 *
	 * @param SeoOutputAuthority      $authority    Output authority.
	 * @param IndexabilityResolver    $indexability Indexability resolver.
	 * @param CanonicalResolver       $canonical    Canonical resolver.
	 * @param MetaDescriptionResolver $description  Description resolver.
	 * @param OpenGraphResolver       $open_graph   Open Graph resolver.
	 * @param HreflangResolver         $hreflang     Hreflang resolver.
	 */
	public function __construct(
		SeoOutputAuthority $authority,
		IndexabilityResolver $indexability,
		CanonicalResolver $canonical,
		MetaDescriptionResolver $description,
		OpenGraphResolver $open_graph,
		HreflangResolver $hreflang
	) {
		$this->authority    = $authority;
		$this->indexability = $indexability;
		$this->canonical    = $canonical;
		$this->description  = $description;
		$this->open_graph   = $open_graph;
		$this->hreflang     = $hreflang;
	}

	/**
	 * Register native frontend output only for signals owned by Core.
	 */
	public function register(): void {
		$owns_head = $this->authority->native_owns( SeoOutputAuthority::SIGNAL_CANONICAL )
			|| $this->authority->native_owns( SeoOutputAuthority::SIGNAL_META_DESCRIPTION )
			|| $this->authority->native_owns( SeoOutputAuthority::SIGNAL_OPEN_GRAPH )
			|| $this->authority->native_owns( SeoOutputAuthority::SIGNAL_HREFLANG );

		if ( $this->authority->native_owns( SeoOutputAuthority::SIGNAL_CANONICAL ) ) {
			remove_action( 'wp_head', 'rel_canonical' );
		}

		if ( $owns_head ) {
			add_action( 'wp_head', array( $this, 'render_head' ), 9 );
		}

		if ( $this->authority->native_owns( SeoOutputAuthority::SIGNAL_ROBOTS ) ) {
			add_filter( 'wp_robots', array( $this, 'filter_robots' ), 99 );
		}
	}

	/**
	 * Render native metadata owned by Core.
	 */
	public function render_head(): void {
		if ( $this->authority->native_owns( SeoOutputAuthority::SIGNAL_CANONICAL ) ) {
			$state = $this->indexability->resolve();
			$url   = $this->canonical->resolve( $state );

			if ( null !== $url ) {
				echo '<link rel="canonical" href="' . esc_url( $url ) . '" />' . "\n";
			}
		}

		if ( $this->authority->native_owns( SeoOutputAuthority::SIGNAL_META_DESCRIPTION ) ) {
			$description = $this->description->resolve();

			if ( null !== $description ) {
				echo '<meta name="description" content="' . esc_attr( $description ) . '" />' . "\n";
			}
		}

		if ( $this->authority->native_owns( SeoOutputAuthority::SIGNAL_OPEN_GRAPH ) ) {
			foreach ( $this->open_graph->resolve() as $property => $content ) {
				echo '<meta property="' . esc_attr( $property ) . '" content="' . esc_attr( $content ) . '" />' . "\n";
			}
		}

		if ( $this->authority->native_owns( SeoOutputAuthority::SIGNAL_HREFLANG ) ) {
			foreach ( $this->hreflang->resolve() as $language_code => $url ) {
				echo '<link rel="alternate" hreflang="' . esc_attr( $language_code ) . '" href="' . esc_url( $url ) . '" />' . "\n";
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
