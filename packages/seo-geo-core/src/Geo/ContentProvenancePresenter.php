<?php
/**
 * Native content provenance presenter.
 *
 * @package SeoGeoCore
 */

declare(strict_types=1);

namespace SeoGeo\Core\Geo;

use SeoGeo\Core\Seo\SeoOutputAuthority;

/**
 * Publishes standards-based author and article provenance metadata.
 */
final class ContentProvenancePresenter {
	/**
	 * Output authority.
	 *
	 * @var SeoOutputAuthority
	 */
	private SeoOutputAuthority $authority;

	/**
	 * Provenance authority.
	 *
	 * @var ContentProvenanceResolver
	 */
	private ContentProvenanceResolver $resolver;

	/**
	 * Create the presenter.
	 *
	 * @param SeoOutputAuthority        $authority Output authority.
	 * @param ContentProvenanceResolver $resolver  Provenance authority.
	 */
	public function __construct( SeoOutputAuthority $authority, ContentProvenanceResolver $resolver ) {
		$this->authority = $authority;
		$this->resolver  = $resolver;
	}

	/**
	 * Register provenance output when native Core owns SEO metadata.
	 */
	public function register(): void {
		if ( 'native' !== $this->authority->provider() ) {
			return;
		}

		add_action( 'wp_head', array( $this, 'render_head' ), 8 );
		add_filter( 'seo_geo_open_graph_metadata', array( $this, 'filter_open_graph' ), 20 );
	}

	/**
	 * Render standard HTML author metadata for the current article.
	 */
	public function render_head(): void {
		$provenance = $this->resolver->current();
		if ( null === $provenance || null === $provenance['author'] ) {
			return;
		}

		$author = $provenance['author'];

		echo '<meta name="author" content="' . esc_attr( $author['name'] ) . '" />' . "\n";
		echo '<link rel="author" href="' . esc_url( $author['url'] ) . '" />' . "\n";
	}

	/**
	 * Extend native Open Graph article metadata from the same provenance source.
	 *
	 * @param array<string, string> $metadata Native Open Graph property map.
	 * @return array<string, string>
	 */
	public function filter_open_graph( array $metadata ): array {
		$provenance = $this->resolver->current();
		if ( null === $provenance ) {
			return $metadata;
		}

		$metadata['article:published_time'] = $provenance['date_published'];
		$metadata['article:modified_time']  = $provenance['date_modified'];

		if ( null !== $provenance['author'] ) {
			$metadata['article:author'] = $provenance['author']['url'];
		}

		return $metadata;
	}
}
