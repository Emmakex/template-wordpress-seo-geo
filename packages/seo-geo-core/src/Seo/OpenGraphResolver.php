<?php
/**
 * Native Open Graph metadata resolver.
 *
 * @package SeoGeoCore
 */

declare(strict_types=1);

namespace SeoGeo\Core\Seo;

/**
 * Resolves a conservative Open Graph baseline from WordPress-native data.
 */
final class OpenGraphResolver {
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
	 * Description resolver.
	 *
	 * @var MetaDescriptionResolver
	 */
	private MetaDescriptionResolver $description;

	/**
	 * Create the resolver.
	 *
	 * @param IndexabilityResolver    $indexability Indexability resolver.
	 * @param CanonicalResolver       $canonical    Canonical resolver.
	 * @param MetaDescriptionResolver $description  Description resolver.
	 */
	public function __construct(
		IndexabilityResolver $indexability,
		CanonicalResolver $canonical,
		MetaDescriptionResolver $description
	) {
		$this->indexability = $indexability;
		$this->canonical    = $canonical;
		$this->description  = $description;
	}

	/**
	 * Resolve Open Graph properties for the current public request.
	 *
	 * @return array<string, string>
	 */
	public function resolve(): array {
		$state = $this->indexability->resolve();

		if ( IndexabilityResolver::INDEXABLE !== $state ) {
			return array();
		}

		$url   = $this->canonical->resolve( $state );
		$title = $this->normalize_text( wp_get_document_title() );

		if ( null === $url || '' === $title ) {
			return array();
		}

		$metadata = array(
			'og:title' => $title,
			'og:type'  => is_singular( 'post' ) ? 'article' : 'website',
			'og:url'   => $url,
		);

		$site_name = $this->normalize_text( get_bloginfo( 'name' ) );
		if ( '' !== $site_name ) {
			$metadata['og:site_name'] = $site_name;
		}

		$description = $this->description->resolve();
		if ( null !== $description ) {
			$metadata['og:description'] = $description;
		}

		$locale = trim( get_locale() );
		if ( '' !== $locale ) {
			$metadata['og:locale'] = str_replace( '-', '_', $locale );
		}

		$image = $this->resolve_image_url();
		if ( null !== $image ) {
			$metadata['og:image'] = $image;
		}

		/**
		 * Filters the native Open Graph property map.
		 *
		 * Callbacks must preserve the array<string, string> contract. Property names
		 * outside the Open Graph/article namespaces and empty values are discarded.
		 *
		 * @param array<string, string> $metadata Open Graph property map.
		 */
		$filtered = apply_filters( 'seo_geo_open_graph_metadata', $metadata );
		$output   = array();

		foreach ( $filtered as $property => $content ) {
			if ( 1 !== preg_match( '/^(?:og|article):[a-z0-9:_-]+$/', $property ) ) {
				continue;
			}

			$content = trim( $content );
			if ( '' !== $content ) {
				$output[ $property ] = $content;
			}
		}

		return $output;
	}

	/**
	 * Resolve an explicitly configured social image source.
	 */
	private function resolve_image_url(): ?string {
		if ( is_singular() ) {
			$image = get_the_post_thumbnail_url( get_queried_object_id(), 'full' );
			if ( is_string( $image ) && '' !== trim( $image ) ) {
				return $image;
			}
		}

		$site_icon = get_site_icon_url( 512 );

		return '' !== $site_icon ? $site_icon : null;
	}

	/**
	 * Normalize display text for metadata attributes.
	 *
	 * @param string $value Raw display text.
	 */
	private function normalize_text( string $value ): string {
		$charset = get_bloginfo( 'charset' );
		if ( '' === $charset ) {
			$charset = 'UTF-8';
		}

		$value = wp_strip_all_tags( $value, true );
		$value = html_entity_decode( $value, ENT_QUOTES | ENT_HTML5, $charset );
		$value = preg_replace( '/\s+/u', ' ', $value ) ?? '';

		return trim( $value );
	}
}
