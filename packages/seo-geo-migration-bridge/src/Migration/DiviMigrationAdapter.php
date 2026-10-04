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
	 * Theme-owned native contact-form metadata.
	 */
	private const CONTACT_FORMS_META = '_seo_geo_contact_forms_v1';

	/**
	 * Contact forms collected during one transform.
	 *
	 * @var array<string,array<string,mixed>>
	 */
	private array $contact_forms = array();

	/**
	 * Contact-form sequence for stable block IDs.
	 *
	 * @var int
	 */
	private int $contact_form_index = 0;

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
		'et_pb_fullwidth_header',
		'et_pb_blurb',
		'et_pb_accordion_item',
		'et_pb_circle_counter',
		'et_pb_counter',
		'et_pb_number_counter',
		'et_pb_testimonial',
		'et_pb_social_media_follow_network',
		'et_pb_blog',
		'et_pb_contact_form',
		'et_pb_contact_field',
	);

	/**
	 * Structural tags that may be flattened safely into content order.
	 *
	 * @var list<string>
	 */
	private const STRUCTURAL_TAGS = array(
		'et_pb_section',
		'et_pb_row',
		'et_pb_row_inner',
		'et_pb_column',
		'et_pb_column_inner',
		'et_pb_accordion',
		'et_pb_counters',
		'et_pb_social_media_follow',
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
	 * @throws RuntimeException When the Divi tree cannot be migrated safely.
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

		$this->contact_forms      = array();
		$this->contact_form_index = 0;

		$blocks = array();
		$this->render_nodes( $tree, $blocks );

		$update_meta = array(
			'et_pb_use_builder' => 'off',
		);
		if ( array() !== $this->contact_forms ) {
			$update_meta[ self::CONTACT_FORMS_META ] = $this->contact_forms;
		}

		return array(
			'content'     => implode( "\n\n", array_filter( $blocks ) ),
			'delete_meta' => array(),
			'update_meta' => $update_meta,
		);
	}

	/**
	 * Parse nested Divi shortcodes into a minimal tree.
	 *
	 * @param string $content Divi shortcode content.
	 * @return list<array<string,mixed>>|null
	 */
	private function parse_tree( string $content ): ?array {
		$root   = array(
			'tag'      => '__root__',
			'attrs'    => array(),
			'children' => array(),
		);
		$stack  = array( &$root );
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
				if ( '' !== trim( $text ) ) {
					$current                         = count( $stack ) - 1;
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

				$current                         = count( $stack ) - 1;
				$stack[ $current ]['children'][] = array(
					'tag'      => $tag,
					'attrs'    => $attrs,
					'children' => array(),
				);
				$child_index                     = count( $stack[ $current ]['children'] ) - 1;
				$stack[]                         =& $stack[ $current ]['children'][ $child_index ];
			}

			$offset = $token_start + strlen( $token );
		}

		if ( $offset < strlen( $content ) ) {
			$text = substr( $content, $offset );
			if ( '' !== trim( $text ) ) {
				$current                         = count( $stack ) - 1;
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
	 * @param array<string,int>         $tags      Tag counts.
	 * @param array                     $media_ids Media IDs.
	 * @phpstan-param list<int> $media_ids
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
	 * @param array                     $blocks Serialized blocks.
	 * @phpstan-param list<string> $blocks
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
	 * @throws RuntimeException When an unsupported module reaches transformation.
	 */
	private function render_module( string $tag, array $attrs, array $children ): string {
		return match ( $tag ) {
			'et_pb_text'    => $this->html_block( $this->children_text( $children ) ),
			'et_pb_button'  => $this->button_block( $attrs ),
			'et_pb_image'   => $this->image_block( $attrs ),
			'et_pb_divider' => '<!-- wp:separator --><hr class="wp-block-separator has-alpha-channel-opacity"/><!-- /wp:separator -->',
			'et_pb_spacer'           => '<!-- wp:spacer {"height":"32px"} --><div style="height:32px" aria-hidden="true" class="wp-block-spacer"></div><!-- /wp:spacer -->',
			'et_pb_fullwidth_header'          => $this->fullwidth_header_block( $attrs, $children ),
			'et_pb_blurb'                     => $this->blurb_block( $attrs, $children ),
			'et_pb_accordion_item'            => $this->accordion_item_block( $attrs, $children ),
			'et_pb_circle_counter'            => $this->counter_block( $attrs, 'circle' ),
			'et_pb_counter'                   => $this->counter_block( $attrs, 'bar' ),
			'et_pb_number_counter'            => $this->counter_block( $attrs, 'number' ),
			'et_pb_testimonial'               => $this->testimonial_block( $attrs, $children ),
			'et_pb_social_media_follow_network' => $this->social_network_block( $attrs ),
			'et_pb_blog'                      => $this->blog_block( $attrs ),
			'et_pb_contact_form'              => $this->contact_form_block( $attrs, $children ),
			'et_pb_contact_field'             => '',
			default                           => throw new RuntimeException( 'Unsupported Divi module reached transform.' ),
		};
	}

	/**
	 * Return concatenated literal child text.
	 *
	 * @param list<array<string,mixed>> $children Child nodes.
	 * @throws RuntimeException When a text module contains nested modules.
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

		return $this->normalize_legacy_divi_text( $text );
	}

	/**
	 * Render HTML-preserving native block.
	 *
	 * @param string $html Trusted legacy HTML after WordPress sanitization.
	 */
	private function html_block( string $html ): string {
		$html = $this->normalize_legacy_divi_text( $html );

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
	 * @throws RuntimeException When an image has no usable source.
	 */
	private function image_block( array $attrs ): string {
		$id  = isset( $attrs['attachment_id'] ) ? (int) $attrs['attachment_id'] : 0;
		$url = isset( $attrs['src'] ) && is_string( $attrs['src'] ) ? esc_url_raw( $attrs['src'] ) : '';

		if ( 0 < $id ) {
			$attachment_url = wp_get_attachment_url( $id );
			if ( is_string( $attachment_url ) && '' !== $attachment_url ) {
				$url = $attachment_url;
			}
		} elseif ( '' !== $url ) {
			$resolved_id = attachment_url_to_postid( $url );
			if ( 0 < $resolved_id ) {
				$id = $resolved_id;
			}
		}

		if ( '' === $url ) {
			throw new RuntimeException( 'Divi image module has no usable media URL.' );
		}

		$alt        = isset( $attrs['alt'] ) && is_string( $attrs['alt'] ) ? $attrs['alt'] : '';
		$attrs_json = 0 < $id ? ' {"id":' . $id . ',"sizeSlug":"full","linkDestination":"none"}' : ' {"sizeSlug":"full","linkDestination":"none"}';
		$image_html = $this->responsive_image_markup( $id, $url, $alt );

		return '<!-- wp:image' . $attrs_json . ' --><figure class="wp-block-image size-full">' . $image_html . '</figure><!-- /wp:image -->';
	}

	/**
	 * Build responsive image HTML while preserving WordPress runtime loading
	 * heuristics for likely LCP media.
	 */
	private function responsive_image_markup( int $attachment_id, string $url, string $alt ): string {
		if ( 0 < $attachment_id ) {
			$markup = wp_get_attachment_image(
				$attachment_id,
				'full',
				false,
				array(
					'alt'     => $alt,
					'loading' => false,
				)
			);

			if ( is_string( $markup ) && '' !== $markup ) {
				return $markup;
			}
		}

		return '<img src="' . esc_url( $url ) . '" alt="' . esc_attr( $alt ) . '" decoding="async"/>';
	}

	/**
	 * Render a Divi fullwidth header into conservative native blocks.
	 *
	 * The visual layout is intentionally not reproduced exactly. The migration
	 * preserves visible title/subhead/body/button/background-image facts so the
	 * resource no longer depends on Divi shortcode rendering.
	 *
	 * @param array<string,mixed>       $attrs    Module attributes.
	 * @param list<array<string,mixed>> $children Module children.
	 */
	private function fullwidth_header_block( array $attrs, array $children ): string {
		$title   = $this->first_string_attr( $attrs, array( 'title' ) );
		$subhead = $this->first_string_attr( $attrs, array( 'subhead' ) );
		$body    = trim( $this->children_text( $children ) );
		$image   = $this->first_string_attr( $attrs, array( 'background_image' ) );
		$blocks  = array();

		if ( '' !== $image ) {
			$blocks[] = '<!-- wp:image {"sizeSlug":"full","linkDestination":"none","className":"seo-geo-migrated-divi-header-image"} --><figure class="wp-block-image size-full seo-geo-migrated-divi-header-image">' . $this->responsive_image_markup( attachment_url_to_postid( $image ), $image, '' ) . '</figure><!-- /wp:image -->';
		}
		if ( '' !== $title ) {
			$blocks[] = '<!-- wp:heading {"level":2} --><h2 class="wp-block-heading">' . esc_html( wp_strip_all_tags( $title ) ) . '</h2><!-- /wp:heading -->';
		}
		if ( '' !== $subhead ) {
			$blocks[] = '<!-- wp:paragraph --><p>' . esc_html( wp_strip_all_tags( $subhead ) ) . '</p><!-- /wp:paragraph -->';
		}
		if ( '' !== $body ) {
			$blocks[] = $this->html_block( $body );
		}

		$button_one = $this->header_button_block( $attrs, 'one' );
		if ( '' !== $button_one ) {
			$blocks[] = $button_one;
		}
		$button_two = $this->header_button_block( $attrs, 'two' );
		if ( '' !== $button_two ) {
			$blocks[] = $button_two;
		}

		return '<!-- wp:group {"className":"seo-geo-migrated-divi-fullwidth-header"} --><div class="wp-block-group seo-geo-migrated-divi-fullwidth-header">' . implode( "\n", $blocks ) . '</div><!-- /wp:group -->';
	}

	/**
	 * Render one fullwidth-header button when configured.
	 *
	 * @param array<string,mixed> $attrs Module attributes.
	 * @param string              $slot  Button slot: one or two.
	 */
	private function header_button_block( array $attrs, string $slot ): string {
		$text = $this->first_string_attr( $attrs, array( 'button_' . $slot . '_text' ) );
		if ( '' === $text ) {
			return '';
		}

		$url = $this->first_string_attr( $attrs, array( 'button_' . $slot . '_url' ) );
		if ( '' === $url ) {
			$url = '#';
		}

		return '<!-- wp:buttons --><div class="wp-block-buttons"><!-- wp:button --><div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="' . esc_url( $url ) . '">' . esc_html( wp_strip_all_tags( $text ) ) . '</a></div><!-- /wp:button --></div><!-- /wp:buttons -->';
	}

	/**
	 * Return the first non-empty string attribute from an allowlist.
	 *
	 * @param array<string,mixed> $attrs Module attributes.
	 * @param array               $keys  Candidate keys.
	 * @phpstan-param list<string> $keys
	 */
	private function first_string_attr( array $attrs, array $keys ): string {
		foreach ( $keys as $key ) {
			if ( isset( $attrs[ $key ] ) && is_string( $attrs[ $key ] ) && '' !== trim( $attrs[ $key ] ) ) {
				return trim( $attrs[ $key ] );
			}
		}

		return '';
	}

	/**
	 * Render a Divi blurb as a native group.
	 *
	 * @param array<string,mixed>       $attrs    Module attributes.
	 * @param list<array<string,mixed>> $children Module children.
	 */
	private function blurb_block( array $attrs, array $children ): string {
		$title = $this->first_string_attr( $attrs, array( 'title' ) );
		$url   = $this->first_string_attr( $attrs, array( 'url' ) );
		$image = $this->first_string_attr( $attrs, array( 'image' ) );
		$body  = trim( $this->children_text( $children ) );
		$parts = array();

		if ( '' !== $image ) {
			$parts[] = '<!-- wp:image {"sizeSlug":"full","linkDestination":"none"} --><figure class="wp-block-image size-full">' . $this->responsive_image_markup( attachment_url_to_postid( $image ), $image, '' ) . '</figure><!-- /wp:image -->';
		}
		if ( '' !== $title ) {
			$title_html = esc_html( wp_strip_all_tags( $title ) );
			if ( '' !== $url ) {
				$title_html = '<a href="' . esc_url( $url ) . '">' . $title_html . '</a>';
			}
			$parts[] = '<!-- wp:heading {"level":3} --><h3 class="wp-block-heading">' . $title_html . '</h3><!-- /wp:heading -->';
		}
		if ( '' !== $body ) {
			$parts[] = $this->html_block( $body );
		}

		return '<!-- wp:group {"className":"seo-geo-migrated-divi-blurb"} --><div class="wp-block-group seo-geo-migrated-divi-blurb">' . implode( "\n", $parts ) . '</div><!-- /wp:group -->';
	}

	/**
	 * Render a Divi accordion item as the native Details block.
	 *
	 * @param array<string,mixed>       $attrs    Module attributes.
	 * @param list<array<string,mixed>> $children Module children.
	 */
	private function accordion_item_block( array $attrs, array $children ): string {
		$title = $this->first_string_attr( $attrs, array( 'title' ) );
		$body  = trim( $this->children_text( $children ) );
		if ( '' === $title ) {
			$title = 'Information';
		}

		return '<!-- wp:details --><details class="wp-block-details"><summary>' . esc_html( wp_strip_all_tags( $title ) ) . '</summary>' . wp_kses_post( $body ) . '</details><!-- /wp:details -->';
	}

	/**
	 * Render a visual Divi counter as durable native text content.
	 *
	 * @param array<string,mixed> $attrs Module attributes.
	 * @param string              $kind  Counter kind.
	 */
	private function counter_block( array $attrs, string $kind ): string {
		$title  = $this->first_string_attr( $attrs, array( 'title' ) );
		$number = $this->first_string_attr( $attrs, array( 'number', 'percent' ) );
		if ( '' === $number ) {
			$number = '0';
		}
		$suffix = in_array( $kind, array( 'bar', 'circle' ), true ) ? '%' : '';

		return '<!-- wp:group {"className":"seo-geo-migrated-divi-counter"} --><div class="wp-block-group seo-geo-migrated-divi-counter"><!-- wp:heading {"level":3} --><h3 class="wp-block-heading">' . esc_html( $number . $suffix ) . '</h3><!-- /wp:heading -->' . ( '' !== $title ? '<!-- wp:paragraph --><p>' . esc_html( wp_strip_all_tags( $title ) ) . '</p><!-- /wp:paragraph -->' : '' ) . '</div><!-- /wp:group -->';
	}

	/**
	 * Render a Divi testimonial with visible quote and attribution.
	 *
	 * @param array<string,mixed>       $attrs    Module attributes.
	 * @param list<array<string,mixed>> $children Module children.
	 */
	private function testimonial_block( array $attrs, array $children ): string {
		$quote    = trim( $this->children_text( $children ) );
		$author   = $this->first_string_attr( $attrs, array( 'author' ) );
		$job      = $this->first_string_attr( $attrs, array( 'job_title' ) );
		$company  = $this->first_string_attr( $attrs, array( 'company_name' ) );
		$portrait = $this->first_string_attr( $attrs, array( 'portrait_url' ) );
		$parts    = array();

		if ( '' !== $portrait ) {
			$portrait_markup = $this->responsive_image_markup( attachment_url_to_postid( $portrait ), $portrait, $author );
			$parts[]         = '<!-- wp:image {"width":"96px","height":"96px","scale":"cover","sizeSlug":"full","linkDestination":"none"} --><figure class="wp-block-image size-full is-resized">' . $portrait_markup . '</figure><!-- /wp:image -->';
		}
		if ( '' !== $quote ) {
			$parts[] = '<!-- wp:quote --><blockquote class="wp-block-quote">' . wp_kses_post( $quote ) . '</blockquote><!-- /wp:quote -->';
		}
		$credit = trim( implode( ' · ', array_filter( array( $author, $job, $company ) ) ) );
		if ( '' !== $credit ) {
			$parts[] = '<!-- wp:paragraph --><p><strong>' . esc_html( wp_strip_all_tags( $credit ) ) . '</strong></p><!-- /wp:paragraph -->';
		}

		return '<!-- wp:group {"className":"seo-geo-migrated-divi-testimonial"} --><div class="wp-block-group seo-geo-migrated-divi-testimonial">' . implode( "\n", $parts ) . '</div><!-- /wp:group -->';
	}

	/**
	 * Render a Divi social follow network as a normal durable link.
	 *
	 * @param array<string,mixed> $attrs Module attributes.
	 */
	private function social_network_block( array $attrs ): string {
		$network = $this->first_string_attr( $attrs, array( 'social_network' ) );
		$url     = $this->first_string_attr( $attrs, array( 'url' ) );
		if ( '' === $url ) {
			return '';
		}

		$label = '' !== $network ? ucfirst( str_replace( array( '_', '-' ), ' ', $network ) ) : 'Social network';

		return '<!-- wp:paragraph {"className":"seo-geo-migrated-divi-social-link"} --><p class="seo-geo-migrated-divi-social-link"><a href="' . esc_url( $url ) . '" rel="me noopener">' . esc_html( $label ) . '</a></p><!-- /wp:paragraph -->';
	}

	/**
	 * Render a Divi blog module using WordPress native Latest Posts.
	 *
	 * @param array<string,mixed> $attrs Module attributes.
	 */
	private function blog_block( array $attrs ): string {
		$count = absint( $this->first_string_attr( $attrs, array( 'posts_number' ) ) );
		if ( 1 > $count ) {
			$count = 6;
		}
		$count  = min( 20, $count );
		$config = array(
			'postsToShow'             => $count,
			'displayPostDate'         => 'off' !== strtolower( $this->first_string_attr( $attrs, array( 'show_date' ) ) ),
			'displayFeaturedImage'    => 'off' !== strtolower( $this->first_string_attr( $attrs, array( 'show_thumbnail' ) ) ),
			'displayPostContent'      => 'off' !== strtolower( $this->first_string_attr( $attrs, array( 'show_excerpt' ) ) ),
			'displayPostContentRadio' => 'excerpt',
		);
		$json   = wp_json_encode( $config, JSON_UNESCAPED_SLASHES );

		return '<!-- wp:latest-posts ' . ( is_string( $json ) ? $json : '{}' ) . ' /-->';
	}

	/**
	 * Convert one Divi contact form into the Theme-owned dynamic block.
	 *
	 * @param array<string,mixed>       $attrs    Form attributes.
	 * @param list<array<string,mixed>> $children Form field nodes.
	 * @throws RuntimeException When the legacy form cannot be represented safely.
	 */
	private function contact_form_block( array $attrs, array $children ): string {
		$recipient = sanitize_email( $this->first_string_attr( $attrs, array( 'email' ) ) );
		if ( ! is_email( $recipient ) ) {
			throw new RuntimeException( 'Divi contact form has no valid recipient.' );
		}

		$fields = array();
		foreach ( $children as $child ) {
			if ( 'et_pb_contact_field' !== ( $child['tag'] ?? '' ) ) {
				continue;
			}

			$field_attrs = isset( $child['attrs'] ) && is_array( $child['attrs'] ) ? $child['attrs'] : array();
			$field_id    = sanitize_key( $this->first_string_attr( $field_attrs, array( 'field_id' ) ) );
			$label       = $this->first_string_attr( $field_attrs, array( 'field_title' ) );
			$type        = $this->contact_field_type( $field_attrs );

			if ( '' === $field_id || '' === $label || null === $type ) {
				throw new RuntimeException( 'Divi contact form contains an unsupported field.' );
			}

			$fields[] = array(
				'id'       => $field_id,
				'label'    => wp_strip_all_tags( $label ),
				'type'     => $type,
				'required' => 'off' !== strtolower( $this->first_string_attr( $field_attrs, array( 'required_mark' ) ) ),
			);
		}

		if ( array() === $fields ) {
			throw new RuntimeException( 'Divi contact form has no migratable fields.' );
		}

		++$this->contact_form_index;
		$form_id                         = 'divi-form-' . $this->contact_form_index;
		$this->contact_forms[ $form_id ] = array(
			'schema_version'  => 1,
			'recipient'       => $recipient,
			'button_text'     => wp_strip_all_tags( $this->first_string_attr( $attrs, array( 'submit_button_text' ) ) ),
			'success_message' => wp_strip_all_tags( $this->first_string_attr( $attrs, array( 'success_message' ) ) ),
			'fields'          => $fields,
		);

		$block_attrs = wp_json_encode(
			array( 'formId' => $form_id ),
			JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
		);

		return '<!-- wp:seo-geo/contact-form ' . ( is_string( $block_attrs ) ? $block_attrs : '{}' ) . ' /-->';
	}

	/**
	 * Normalize supported Divi field types.
	 *
	 * @param array<string,mixed> $attrs Field attributes.
	 */
	private function contact_field_type( array $attrs ): ?string {
		$type = strtolower( $this->first_string_attr( $attrs, array( 'field_type' ) ) );
		$id   = strtolower( $this->first_string_attr( $attrs, array( 'field_id' ) ) );

		if ( 'email' === $type || str_contains( $id, 'email' ) || str_contains( $id, 'correo' ) ) {
			return 'email';
		}
		if ( 'text' === $type || str_contains( $id, 'message' ) || str_contains( $id, 'mensaje' ) ) {
			return 'textarea';
		}
		if ( 'input' === $type || '' === $type ) {
			return str_contains( $id, 'web' ) || str_contains( $id, 'url' ) ? 'url' : 'text';
		}

		return null;
	}

	/**
	 * Normalize one bounded legacy Divi text placeholder.
	 *
	 * @param string $value Legacy text or sanitized HTML.
	 */
	private function normalize_legacy_divi_text( string $value ): string {
		if ( '' === $value || ! str_contains( $value, '{' ) ) {
			return $value;
		}

		$normalized = preg_replace( '/\\{[a-f0-9]{64}\\}/i', '%', $value );

		return is_string( $normalized ) ? $normalized : $value;
	}

	/**
	 * Build a stable blocked plan.
	 *
	 * @param string $blocker Stable blocker code.
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
