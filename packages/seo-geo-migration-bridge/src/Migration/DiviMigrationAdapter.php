<?php
/**
 * Divi migration adapter.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge\Migration;

use RuntimeException;
use WP_Post;

/**
 * Converts a conservative allowlist of Divi modules into native blocks.
 */
final class DiviMigrationAdapter implements BuilderMigrationAdapterInterface {
	/**
	 * Supported Divi module tags.
	 *
	 * @var list<string>
	 */
	private const SUPPORTED_MODULES = array(
		'et_pb_text',
		'et_pb_button',
		'et_pb_image',
		'et_pb_divider',
		'et_pb_spacer',
	);

	/**
	 * Structural tags that may be flattened safely into content order.
	 *
	 * @var list<string>
	 */
	private const STRUCTURAL_TAGS = array(
		'et_pb_section',
		'et_pb_row',
		'et_pb_column',
	);

	/**
	 * Stable adapter identifier.
	 */
	public function id(): string {
		return 'divi';
	}

	/**
	 * Produce a safe plan.
	 *
	 * @param WP_Post $post Resource to inspect.
	 * @return array{supported:bool,source:string,target:string,operations:list<string>,blockers:list<string>,warnings:list<string>,media_ids:list<int>}
	 */
	public function plan( WP_Post $post ): array {
		$tree = $this->parse_tree( $post->post_content );
		if ( null === $tree ) {
			return $this->blocked_plan( 'divi-shortcode-tree-invalid' );
		}

		$tags      = array();
		$media_ids = array();
		$this->collect_tags( $tree, $tags, $media_ids );

		$known       = array_merge( self::SUPPORTED_MODULES, self::STRUCTURAL_TAGS );
		$unsupported = array_values( array_diff( array_keys( $tags ), $known ) );
		sort( $unsupported );

		$blockers = array_map(
			static fn( string $tag ): string => 'unsupported-divi-module:' . $tag,
			$unsupported
		);

		return array(
			'supported'  => array() === $blockers,
			'source'     => 'divi',
			'target'     => 'wordpress-core-blocks',
			'operations' => array(
				'replace-divi-shortcodes-with-native-blocks',
				'disable-divi-page-mode',
				'retain-original-divi-content-in-migration-backup',
			),
			'blockers'   => $blockers,
			'warnings'   => array( 'divi-layout-and-style-settings-are-not-reproduced-exactly' ),
			'media_ids'  => $media_ids,
		);
	}

	/**
	 * Build internal mutation payload.
	 *
	 * @param WP_Post $post Resource to transform.
	 * @return array{content:string,delete_meta:list<string>,update_meta:array<string,mixed>}
	 */
	public function transform( WP_Post $post ): array {
		$plan = $this->plan( $post );
		if ( ! $plan['supported'] ) {
			throw new RuntimeException( 'Divi resource contains unsupported migration components.' );
		}

		$tree = $this->parse_tree( $post->post_content );
		if ( null === $tree ) {
			throw new RuntimeException( 'Divi shortcode tree is invalid.' );
		}

		$blocks = array();
		$this->render_nodes( $tree, $blocks );

		return array(
			'content'     => implode( "\n\n", $blocks ),
			'delete_meta' => array(),
			'update_meta' => array(
				'et_pb_use_builder' => 'off',
			),
		);
	}

	/**
	 * Parse nested Divi shortcodes into a minimal tree.
	 *
	 * @return list<array<string,mixed>>|null
	 */
	private function parse_tree( string $content ): ?array {
		$root = array(
			'tag'      => '__root__',
			'attrs'    => array(),
			'children' => array(),
		);
		$stack = array( &$root );
		$offset = 0;

		if ( false === preg_match_all( '/\[(\/?)(et_pb_[a-z0-9_]+)([^\]]*)\]/i', $content, $matches, PREG_OFFSET_CAPTURE ) ) {
			return null;
		}

		$count = count( $matches[0] );
		for ( $index = 0; $index < $count; ++$index ) {
			$token       = $matches[0][ $index ][0];
			$token_start = (int) $matches[0][ $index ][1];
			$prefix      = $matches[1][ $index ][0];
			$tag         = strtolower( $matches[2][ $index ][0] );
			$raw_attrs   = trim( $matches[3][ $index ][0] );

			if ( $token_start > $offset ) {
				$text = substr( $content, $offset, $token_start - $offset );
				if ( false !== $text && '' !== trim( $text ) ) {
					$current = count( $stack ) - 1;
					$stack[ $current ]['children'][] = array(
						'tag'      => '__text__',
						'attrs'    => array(),
						'children' => array(),
						'text'     => $text,
					);
				}
			}

			if ( '/' === $prefix ) {
				if ( count( $stack ) <= 1 ) {
					return null;
				}
				$current = count( $stack ) - 1;
				if ( $stack[ $current ]['tag'] !== $tag ) {
					return null;
				}
				array_pop( $stack );
			} else {
				$attrs = shortcode_parse_atts( $raw_attrs );
				if ( ! is_array( $attrs ) ) {
					$attrs = array();
				}

				$current = count( $stack ) - 1;
				$stack[ $current ]['children'][] = array(
					'tag'      => $tag,
					'attrs'    => $attrs,
					'children' => array(),
				);
				$child_index = count( $stack[ $current ]['children'] ) - 1;
				$stack[]     =& $stack[ $current ]['children'][ $child_index ];
			}

			$offset = $token_start + strlen( $token );
		}

		if ( $offset < strlen( $content ) ) {
			$text = substr( $content, $offset );
			if ( false !== $text && '' !== trim( $text ) ) {
				$current = count( $stack ) - 1;
				$stack[ $current ]['children'][] = array(
					'tag'      => '__text__',
					'attrs'    => array(),
					'children' => array(),
					'text'     => $text,
				);
			}
		}

		return 1 === count( $stack ) ? $root['children'] : null;
	}

	/**
	 * Collect Divi tags and media IDs.
	 *
	 * @param list<array<string,mixed>> $nodes     Parsed nodes.
	 * @param array<string,int>          $tags      Tag counts.
	 * @param list<int>                  $media_ids Media IDs.
	 */
	private function collect_tags( array $nodes, array &$tags, array &$media_ids ): void {
		foreach ( $nodes as $node ) {
			$tag = isset( $node['tag'] ) && is_string( $node['tag'] ) ? $node['tag'] : '';
			if ( '' === $tag ) {
				continue;
			}

			if ( '__text__' !== $tag ) {
				$tags[ $tag ] = ( $tags[ $tag ] ?? 0 ) + 1;
			}

			$attrs = isset( $node['attrs'] ) && is_array( $node['attrs'] ) ? $node['attrs'] : array();
			if ( 'et_pb_image' === $tag && isset( $attrs['attachment_id'] ) ) {
				$id = (int) $attrs['attachment_id'];
				if ( 0 < $id && ! in_array( $id, $media_ids, true ) ) {
					$media_ids[] = $id;
				}
			}

			$children = isset( $node['children'] ) && is_array( $node['children'] ) ? $node['children'] : array();
			if ( array() !== $children ) {
				$this->collect_tags( $children, $tags, $media_ids );
			}
		}

		sort( $media_ids );
	}

	/**
	 * Render parsed nodes to native blocks.
	 *
	 * @param list<array<string,mixed>> $nodes  Parsed nodes.
	 * @param list<string>              $blocks Serialized blocks.
	 */
	private function render_nodes( array $nodes, array &$blocks ): void {
		foreach ( $nodes as $node ) {
			$tag      = isset( $node['tag'] ) && is_string( $node['tag'] ) ? $node['tag'] : '';
			$attrs    = isset( $node['attrs'] ) && is_array( $node['attrs'] ) ? $node['attrs'] : array();
			$children = isset( $node['children'] ) && is_array( $node['children'] ) ? $node['children'] : array();

			if ( '__text__' === $tag ) {
				$text = isset( $node['text'] ) && is_string( $node['text'] ) ? $node['text'] : '';
				if ( '' !== trim( $text ) ) {
					$blocks[] = $this->html_block( $text );
				}
				continue;
			}

			if ( in_array( $tag, self::STRUCTURAL_TAGS, true ) ) {
				$this->render_nodes( $children, $blocks );
				continue;
			}

			$blocks[] = $this->render_module( $tag, $attrs, $children );
		}
	}

	/**
	 * Render one supported Divi module.
	 *
	 * @param string                    $tag      Module tag.
	 * @param array<string,mixed>       $attrs    Module attributes.
	 * @param list<array<string,mixed>> $children Module children.
	 */
	private function render_module( string $tag, array $attrs, array $children ): string {
		return match ( $tag ) {
			'et_pb_text'    => $this->html_block( $this->children_text( $children ) ),
			'et_pb_button'  => $this->button_block( $attrs ),
			'et_pb_image'   => $this->image_block( $attrs ),
			'et_pb_divider' => '<!-- wp:separator --><hr class="wp-block-separator has-alpha-channel-opacity"/><!-- /wp:separator -->',
			'et_pb_spacer'  => '<!-- wp:spacer {"height":"32px"} --><div style="height:32px" aria-hidden="true" class="wp-block-spacer"></div><!-- /wp:spacer -->',
			default         => throw new RuntimeException( 'Unsupported Divi module reached transform.' ),
		};
	}

	/**
	 * Return concatenated literal child text.
	 *
	 * @param list<array<string,mixed>> $children Child nodes.
	 */
	private function children_text( array $children ): string {
		$text = '';

		foreach ( $children as $child ) {
			$tag = isset( $child['tag'] ) && is_string( $child['tag'] ) ? $child['tag'] : '';
			if ( '__text__' === $tag ) {
				$text .= isset( $child['text'] ) && is_string( $child['text'] ) ? $child['text'] : '';
				continue;
			}

			throw new RuntimeException( 'Nested Divi module inside text module is not supported.' );
		}

		return $text;
	}

	/**
	 * Render HTML-preserving native block.
	 */
	private function html_block( string $html ): string {
		return '<!-- wp:html -->' . wp_kses_post( $html ) . '<!-- /wp:html -->';
	}

	/**
	 * Render Divi button.
	 *
	 * @param array<string,mixed> $attrs Module attributes.
	 */
	private function button_block( array $attrs ): string {
		$text = isset( $attrs['button_text'] ) && is_string( $attrs['button_text'] ) ? wp_strip_all_tags( $attrs['button_text'] ) : '';
		$url  = isset( $attrs['button_url'] ) && is_string( $attrs['button_url'] ) ? esc_url_raw( $attrs['button_url'] ) : '#';

		return '<!-- wp:buttons --><div class="wp-block-buttons"><!-- wp:button --><div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="' . esc_url( $url ) . '">' . esc_html( $text ) . '</a></div><!-- /wp:button --></div><!-- /wp:buttons -->';
	}

	/**
	 * Render Divi image preserving media ID where available.
	 *
	 * @param array<string,mixed> $attrs Module attributes.
	 */
	private function image_block( array $attrs ): string {
		$id  = isset( $attrs['attachment_id'] ) ? (int) $attrs['attachment_id'] : 0;
		$url = isset( $attrs['src'] ) && is_string( $attrs['src'] ) ? esc_url_raw( $attrs['src'] ) : '';

		if ( 0 < $id ) {
			$attachment_url = wp_get_attachment_url( $id );
			if ( is_string( $attachment_url ) && '' !== $attachment_url ) {
				$url = $attachment_url;
			}
		}

		if ( '' === $url ) {
			throw new RuntimeException( 'Divi image module has no usable media URL.' );
		}

		$alt   = isset( $attrs['alt'] ) && is_string( $attrs['alt'] ) ? $attrs['alt'] : '';
		$attrs_json = 0 < $id ? ' {"id":' . $id . ',"sizeSlug":"full","linkDestination":"none"}' : ' {"sizeSlug":"full","linkDestination":"none"}';
		$class = 0 < $id ? ' class="wp-image-' . $id . '"' : '';

		return '<!-- wp:image' . $attrs_json . ' --><figure class="wp-block-image size-full"><img src="' . esc_url( $url ) . '" alt="' . esc_attr( $alt ) . '"' . $class . '/></figure><!-- /wp:image -->';
	}

	/**
	 * Build a stable blocked plan.
	 *
	 * @return array{supported:bool,source:string,target:string,operations:list<string>,blockers:list<string>,warnings:list<string>,media_ids:list<int>}
	 */
	private function blocked_plan( string $blocker ): array {
		return array(
			'supported'  => false,
			'source'     => 'divi',
			'target'     => 'wordpress-core-blocks',
			'operations' => array(),
			'blockers'   => array( $blocker ),
			'warnings'   => array(),
			'media_ids'  => array(),
		);
	}
}
