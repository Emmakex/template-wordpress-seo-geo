<?php
/**
 * Corporate Home semantic-model resolver.
 *
 * @package SeoGeoTheme
 */

declare(strict_types=1);

namespace SeoGeo\Theme\Strategic;

use WP_HTML_Tag_Processor;
use WP_Post;

/**
 * Resolves the versioned corporate-home-v1 model without making Gutenberg the
 * frontend layout authority.
 *
 * The current transition source is the already-hydrated semantic block draft.
 * A future SEO/GEO Manager can provide the same model through the public filter
 * without changing the renderer or public HTML contract.
 */
final class CorporateHomeModelResolver {
	private const MODEL_ID    = 'corporate-home-v1';
	private const SLOT_PREFIX = 'seo-geo-content-slot--';

	/**
	 * Resolve a valid Corporate Home model for one WordPress page.
	 *
	 * @param WP_Post $post Source WordPress page.
	 * @return array<string, mixed>|null
	 */
	public function resolve( WP_Post $post ): ?array {
		$external = apply_filters( 'seo_geo_theme_corporate_home_model', null, $post );
		if ( is_array( $external ) && $this->is_valid_model( $external ) ) {
			$external['source']  = isset( $external['source'] ) && is_string( $external['source'] ) ? $external['source'] : 'external';
			$external['post_id'] = $post->ID;

			return $external;
		}

		$contract = $this->contract();
		if ( null === $contract || ! str_contains( (string) $post->post_content, 'seo-geo-corporate-native-hero' ) ) {
			return null;
		}

		$definitions = $this->slot_definitions( $contract );
		$slots       = array();
		$blocks      = parse_blocks( (string) $post->post_content );

		$this->collect_slots( $blocks, $definitions, $slots );

		$model = array(
			'model_id' => self::MODEL_ID,
			'post_id'  => $post->ID,
			'source'   => 'hydrated-semantic-blocks',
			'slots'    => $slots,
		);

		return $this->is_valid_model( $model ) ? $model : null;
	}

	/**
	 * Check whether the page has enough semantic data for Theme-owned rendering.
	 *
	 * @param WP_Post $post Source WordPress page.
	 */
	public function supports( WP_Post $post ): bool {
		return null !== $this->resolve( $post );
	}

	/**
	 * Return the Theme-owned corporate-home-v1 contract.
	 *
	 * @return array<string, mixed>|null
	 */
	private function contract(): ?array {
		if ( ! function_exists( 'seo_geo_theme_preset_document' ) ) {
			return null;
		}

		$document = seo_geo_theme_preset_document( 'corporate', 'content-map.json' );
		if ( ! is_array( $document ) ) {
			return null;
		}

		$models = $document['native_content_models'] ?? null;
		$model  = is_array( $models ) ? ( $models[ self::MODEL_ID ] ?? null ) : null;

		return is_array( $model ) ? $model : null;
	}

	/**
	 * Build the slot definition map from the canonical preset document.
	 *
	 * @param array<string, mixed> $contract Model contract.
	 * @return array<string, array{type:string,required:bool}>
	 */
	private function slot_definitions( array $contract ): array {
		$definitions = array();
		$slots       = $contract['slots'] ?? null;

		if ( ! is_array( $slots ) ) {
			return $definitions;
		}

		foreach ( $slots as $slot ) {
			if ( ! is_array( $slot ) ) {
				continue;
			}

			$id   = isset( $slot['id'] ) && is_string( $slot['id'] ) ? sanitize_key( $slot['id'] ) : '';
			$type = isset( $slot['type'] ) && is_string( $slot['type'] ) ? sanitize_key( $slot['type'] ) : '';
			if ( '' === $id || ! in_array( $type, array( 'text', 'link', 'list' ), true ) ) {
				continue;
			}

			$definitions[ $id ] = array(
				'type'     => $type,
				'required' => true === ( $slot['required'] ?? false ),
			);
		}

		return $definitions;
	}

	/**
	 * Traverse parsed blocks and collect semantic slot values.
	 *
	 * @param array<int, array<string, mixed>>                $blocks      Parsed blocks.
	 * @param array<string, array{type:string,required:bool}> $definitions Slot contract.
	 * @param array<string, mixed>                            $slots       Collected values.
	 */
	private function collect_slots( array $blocks, array $definitions, array &$slots ): void {
		foreach ( $blocks as $block ) {
			if ( ! is_array( $block ) ) {
				continue;
			}

			$class_name = '';
			$attrs      = $block['attrs'] ?? null;
			if ( is_array( $attrs ) && isset( $attrs['className'] ) && is_string( $attrs['className'] ) ) {
				$class_name = $attrs['className'];
			}

			$classes = preg_split( '/\s+/', trim( $class_name ) );
			if ( ! is_array( $classes ) ) {
				$classes = array();
			}

			foreach ( $classes as $class ) {
				if ( ! str_starts_with( $class, self::SLOT_PREFIX ) ) {
					continue;
				}

				$slot_id = sanitize_key( substr( $class, strlen( self::SLOT_PREFIX ) ) );
				if ( '' === $slot_id || ! isset( $definitions[ $slot_id ] ) || array_key_exists( $slot_id, $slots ) ) {
					continue;
				}

				$value = $this->extract_value( $block, $definitions[ $slot_id ]['type'] );
				if ( null !== $value ) {
					$slots[ $slot_id ] = $value;
				}
			}

			$inner_blocks = $block['innerBlocks'] ?? null;
			if ( is_array( $inner_blocks ) && array() !== $inner_blocks ) {
				$this->collect_slots( $inner_blocks, $definitions, $slots );
			}
		}
	}

	/**
	 * Extract one bounded slot value.
	 *
	 * @param array<string, mixed> $block Parsed block.
	 * @param string               $type  Contract slot type.
	 * @return string|array<string, string>|list<string>|null
	 */
	private function extract_value( array $block, string $type ) {
		$rendered = render_block( $block );
		if ( '' === trim( $rendered ) ) {
			return null;
		}

		if ( 'text' === $type ) {
			$value = trim( wp_strip_all_tags( $rendered, true ) );

			return '' === $value ? null : html_entity_decode( $value, ENT_QUOTES | ENT_HTML5, $this->charset() );
		}

		if ( 'link' === $type ) {
			return $this->extract_link( $rendered );
		}

		if ( 'list' === $type ) {
			return $this->extract_list( $rendered );
		}

		return null;
	}

	/**
	 * Extract the first authored anchor from a semantic link slot.
	 *
	 * @param string $html Rendered semantic-slot HTML.
	 * @return array{label:string,url:string}|null
	 */
	private function extract_link( string $html ): ?array {
		if ( class_exists( WP_HTML_Tag_Processor::class ) ) {
			$processor = new WP_HTML_Tag_Processor( $html );
			if ( $processor->next_tag( array( 'tag_name' => 'A' ) ) ) {
				$url = $processor->get_attribute( 'href' );
				if ( is_string( $url ) && '' !== trim( $url ) ) {
					$label = trim( wp_strip_all_tags( $html, true ) );
					if ( '' !== $label ) {
						return array(
							'label' => html_entity_decode( $label, ENT_QUOTES | ENT_HTML5, $this->charset() ),
							'url'   => $url,
						);
					}
				}
			}
		}

		if ( 1 === preg_match( '/<a\b[^>]*href=(?:"|\')([^"\']+)(?:"|\')[^>]*>(.*?)<\/a>/is', $html, $matches ) ) {
			$label = trim( wp_strip_all_tags( $matches[2], true ) );
			if ( '' !== $label && '' !== trim( $matches[1] ) ) {
				return array(
					'label' => html_entity_decode( $label, ENT_QUOTES | ENT_HTML5, $this->charset() ),
					'url'   => html_entity_decode( $matches[1], ENT_QUOTES | ENT_HTML5, $this->charset() ),
				);
			}
		}

		return null;
	}

	/**
	 * Extract list text without carrying block/layout markup into the model.
	 *
	 * @param string $html Rendered list slot HTML.
	 * @return list<string>|null
	 */
	private function extract_list( string $html ): ?array {
		if ( 1 > preg_match_all( '/<li\b[^>]*>(.*?)<\/li>/is', $html, $matches ) ) {
			return null;
		}

		$items = array();
		foreach ( $matches[1] as $item ) {
			if ( ! is_string( $item ) ) {
				continue;
			}

			$value = trim( wp_strip_all_tags( $item, true ) );
			if ( '' !== $value ) {
				$items[] = html_entity_decode( $value, ENT_QUOTES | ENT_HTML5, $this->charset() );
			}
		}

		return array() === $items ? null : array_values( $items );
	}

	/**
	 * Validate model identity and every required slot from the canonical contract.
	 *
	 * @param array<string, mixed> $model Candidate model.
	 */
	private function is_valid_model( array $model ): bool {
		if ( self::MODEL_ID !== ( $model['model_id'] ?? null ) || ! isset( $model['slots'] ) || ! is_array( $model['slots'] ) ) {
			return false;
		}

		$contract = $this->contract();
		if ( null === $contract ) {
			return false;
		}

		foreach ( $this->slot_definitions( $contract ) as $id => $definition ) {
			if ( ! $definition['required'] ) {
				continue;
			}

			$value = $model['slots'][ $id ] ?? null;
			if ( is_string( $value ) && '' !== trim( $value ) ) {
				continue;
			}

			if ( 'link' === $definition['type'] && is_array( $value ) && isset( $value['label'], $value['url'] ) && is_string( $value['label'] ) && is_string( $value['url'] ) && '' !== trim( $value['label'] ) && '' !== trim( $value['url'] ) ) {
				continue;
			}

			if ( 'list' === $definition['type'] && is_array( $value ) && array() !== $value ) {
				continue;
			}

			return false;
		}

		return true;
	}

	/** Resolve the WordPress document charset with a safe HTML default. */
	private function charset(): string {
		$charset = get_bloginfo( 'charset' );

		return is_string( $charset ) && '' !== $charset ? $charset : 'UTF-8';
	}
}
