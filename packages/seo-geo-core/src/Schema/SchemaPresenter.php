<?php
/**
 * Native Schema graph presenter.
 *
 * @package SeoGeoCore
 */

declare(strict_types=1);

namespace SeoGeo\Core\Schema;

use SeoGeo\Core\Seo\SeoOutputAuthority;

/**
 * Renders exactly one native JSON-LD graph when Core owns Schema output.
 */
final class SchemaPresenter {
	/**
	 * SEO output authority.
	 *
	 * @var SeoOutputAuthority
	 */
	private SeoOutputAuthority $authority;

	/**
	 * Native graph builder.
	 *
	 * @var SchemaGraphBuilder
	 */
	private SchemaGraphBuilder $graph;

	/**
	 * Create the presenter.
	 *
	 * @param SeoOutputAuthority $authority SEO output authority.
	 * @param SchemaGraphBuilder $graph     Native graph builder.
	 */
	public function __construct( SeoOutputAuthority $authority, SchemaGraphBuilder $graph ) {
		$this->authority = $authority;
		$this->graph     = $graph;
	}

	/**
	 * Register JSON-LD output only when native Core owns Schema.
	 */
	public function register(): void {
		if ( $this->authority->native_owns( SeoOutputAuthority::SIGNAL_SCHEMA ) ) {
			add_action( 'wp_head', array( $this, 'render' ), 20 );
		}
	}

	/**
	 * Render one parseable JSON-LD graph.
	 */
	public function render(): void {
		$graph = $this->graph->resolve();

		if ( array() === $graph ) {
			return;
		}

		$encoded = wp_json_encode(
			$graph,
			JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE
		);

		if ( false === $encoded ) {
			return;
		}

		wp_print_inline_script_tag(
			$encoded,
			array(
				'id'   => 'seo-geo-schema-graph',
				'type' => 'application/ld+json',
			)
		);
	}
}
