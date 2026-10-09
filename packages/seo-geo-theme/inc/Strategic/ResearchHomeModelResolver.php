<?php
/**
 * Research Home structured-model resolver.
 *
 * @package SeoGeoTheme
 */

declare(strict_types=1);

namespace SeoGeo\Theme\Strategic;

use WP_Post;

/**
 * Resolves research-home-v1 from versioned post meta or a trusted external
 * provider. Gutenberg content never becomes the strategic layout authority.
 */
final class ResearchHomeModelResolver {
	private const MODEL_ID = 'research-home-v1';
	private const META_KEY = '_seo_geo_theme_page_model';

	/**
	 * CTA slots that must resolve to an explicit label and URL.
	 *
	 * @var list<string>
	 */
	private const LINK_SLOTS = array( 'hero-primary-cta', 'final-cta-button' );

	/**
	 * Resolve one validated Research Home model.
	 *
	 * @param WP_Post $post Source WordPress page.
	 * @return array<string,mixed>|null
	 */
	public function resolve( WP_Post $post ): ?array {
		$external = apply_filters( 'seo_geo_theme_research_home_model', null, $post );
		if ( is_array( $external ) ) {
			$model = $this->normalize_model( $external, $post );
			if ( null !== $model ) {
				$model['source'] = isset( $external['source'] ) && is_string( $external['source'] )
					? sanitize_key( $external['source'] )
					: 'external';

				return $model;
			}
		}

		$stored = get_post_meta( $post->ID, self::META_KEY, true );
		if ( is_string( $stored ) && '' !== trim( $stored ) ) {
			try {
				$stored = json_decode( $stored, true, 512, JSON_THROW_ON_ERROR );
			} catch ( \JsonException $exception ) {
				unset( $exception );
				return null;
			}
		}

		if ( ! is_array( $stored ) ) {
			return null;
		}

		$model = $this->normalize_model( $stored, $post );
		if ( null !== $model ) {
			$model['source'] = 'post-meta';
		}

		return $model;
	}

	/**
	 * Determine whether this page carries a complete Research Home model.
	 *
	 * @param WP_Post $post Source WordPress page.
	 */
	public function supports( WP_Post $post ): bool {
		return null !== $this->resolve( $post );
	}

	/**
	 * Normalize one candidate model against the bundled page-model contract.
	 *
	 * @param array<string,mixed> $candidate Candidate model.
	 * @param WP_Post             $post      Source page.
	 * @return array<string,mixed>|null
	 */
	private function normalize_model( array $candidate, WP_Post $post ): ?array {
		if ( self::MODEL_ID !== ( $candidate['model_id'] ?? null ) ) {
			return null;
		}

		$contract = $this->contract();
		$slots    = $candidate['slots'] ?? null;
		if ( null === $contract || ! is_array( $slots ) ) {
			return null;
		}

		$normalized_slots = array();
		foreach ( $slots as $key => $value ) {
			if ( ! is_string( $key ) ) {
				continue;
			}

			$key = sanitize_key( $key );
			if ( in_array( $key, self::LINK_SLOTS, true ) ) {
				$link = $this->normalize_link( $value );
				if ( null !== $link ) {
					$normalized_slots[ $key ] = $link;
				}
				continue;
			}

			if ( is_string( $value ) ) {
				$text = trim( wp_strip_all_tags( $value, true ) );
				if ( '' !== $text ) {
					$normalized_slots[ $key ] = $text;
				}
			}
		}

		$required = $contract['required'] ?? null;
		if ( ! is_array( $required ) ) {
			return null;
		}

		foreach ( $required as $required_slot ) {
			if ( ! is_string( $required_slot ) ) {
				return null;
			}

			$required_slot = sanitize_key( $required_slot );
			$value         = $normalized_slots[ $required_slot ] ?? null;

			if ( in_array( $required_slot, self::LINK_SLOTS, true ) ) {
				if ( ! is_array( $value ) || ! isset( $value['label'], $value['url'] ) ) {
					return null;
				}
				continue;
			}

			if ( ! is_string( $value ) || '' === trim( $value ) ) {
				return null;
			}
		}

		return array(
			'model_id'             => self::MODEL_ID,
			'post_id'              => $post->ID,
			'slots'                => $normalized_slots,
			'research_lines'       => $this->normalize_research_lines( $candidate['research_lines'] ?? null ),
			'selected_outputs'     => $this->normalize_outputs( $candidate['selected_outputs'] ?? null ),
			'academic_identifiers' => $this->normalize_identifiers( $candidate['academic_identifiers'] ?? null ),
		);
	}

	/**
	 * Return the canonical Research Home model contract.
	 *
	 * @return array<string,mixed>|null
	 */
	private function contract(): ?array {
		if ( ! function_exists( 'seo_geo_theme_preset_document' ) ) {
			return null;
		}

		$document = seo_geo_theme_preset_document( 'research', 'page-models.json' );
		$model    = is_array( $document ) ? ( $document['models']['home'] ?? null ) : null;

		return is_array( $model ) && self::MODEL_ID === ( $model['model_id'] ?? null ) ? $model : null;
	}

	/**
	 * Normalize a CTA link without inventing a target.
	 *
	 * @param mixed $value Candidate link.
	 * @return array{label:string,url:string}|null
	 */
	private function normalize_link( $value ): ?array {
		if ( ! is_array( $value ) || ! isset( $value['label'], $value['url'] ) || ! is_string( $value['label'] ) || ! is_string( $value['url'] ) ) {
			return null;
		}

		$label = trim( wp_strip_all_tags( $value['label'], true ) );
		$url   = esc_url_raw( trim( $value['url'] ) );
		if ( '' === $label || '' === $url ) {
			return null;
		}

		return array(
			'label' => $label,
			'url'   => $url,
		);
	}

	/**
	 * Normalize authored research-line data.
	 *
	 * @param mixed $value Candidate collection.
	 * @return list<array{title:string,summary:string,url:string}>
	 */
	private function normalize_research_lines( $value ): array {
		if ( ! is_array( $value ) ) {
			return array();
		}

		$items = array();
		foreach ( array_slice( $value, 0, 6 ) as $item ) {
			if ( ! is_array( $item ) || ! isset( $item['title'] ) || ! is_string( $item['title'] ) ) {
				continue;
			}

			$title = trim( wp_strip_all_tags( $item['title'], true ) );
			if ( '' === $title ) {
				continue;
			}

			$items[] = array(
				'title'   => $title,
				'summary' => isset( $item['summary'] ) && is_string( $item['summary'] ) ? trim( wp_strip_all_tags( $item['summary'], true ) ) : '',
				'url'     => isset( $item['url'] ) && is_string( $item['url'] ) ? esc_url_raw( trim( $item['url'] ) ) : '',
			);
		}

		return $items;
	}

	/**
	 * Normalize real research outputs. Status, year and identifiers are preserved
	 * only when explicitly supplied; the Theme never infers peer review or IDs.
	 *
	 * @param mixed $value Candidate collection.
	 * @return list<array<string,string>>
	 */
	private function normalize_outputs( $value ): array {
		if ( ! is_array( $value ) ) {
			return array();
		}

		$items = array();
		foreach ( array_slice( $value, 0, 6 ) as $item ) {
			if ( ! is_array( $item ) || ! isset( $item['title'] ) || ! is_string( $item['title'] ) ) {
				continue;
			}

			$title = trim( wp_strip_all_tags( $item['title'], true ) );
			if ( '' === $title ) {
				continue;
			}

			$normalized = array( 'title' => $title );
			foreach ( array( 'type', 'status', 'year', 'venue', 'identifier' ) as $field ) {
				if ( isset( $item[ $field ] ) && is_string( $item[ $field ] ) ) {
					$text = trim( wp_strip_all_tags( $item[ $field ], true ) );
					if ( '' !== $text ) {
						$normalized[ $field ] = $text;
					}
				}
			}
			if ( isset( $item['url'] ) && is_string( $item['url'] ) ) {
				$url = esc_url_raw( trim( $item['url'] ) );
				if ( '' !== $url ) {
					$normalized['url'] = $url;
				}
			}

			$items[] = $normalized;
		}

		return $items;
	}

	/**
	 * Normalize only identifiers explicitly marked as verified.
	 *
	 * @param mixed $value Candidate collection.
	 * @return list<array{label:string,value:string,url:string}>
	 */
	private function normalize_identifiers( $value ): array {
		if ( ! is_array( $value ) ) {
			return array();
		}

		$items = array();
		foreach ( array_slice( $value, 0, 8 ) as $item ) {
			if ( ! is_array( $item ) || true !== ( $item['verified'] ?? false ) ) {
				continue;
			}

			$label = isset( $item['label'] ) && is_string( $item['label'] ) ? trim( wp_strip_all_tags( $item['label'], true ) ) : '';
			$id    = isset( $item['value'] ) && is_string( $item['value'] ) ? trim( wp_strip_all_tags( $item['value'], true ) ) : '';
			$url   = isset( $item['url'] ) && is_string( $item['url'] ) ? esc_url_raw( trim( $item['url'] ) ) : '';
			if ( '' === $label || '' === $id ) {
				continue;
			}

			$items[] = array(
				'label' => $label,
				'value' => $id,
				'url'   => $url,
			);
		}

		return $items;
	}
}
