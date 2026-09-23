<?php
/**
 * Elementor migration adapter.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge\Migration;

use RuntimeException;
use WP_Post;

/**
 * Converts a conservative allowlist of Elementor widgets into native blocks.
 */
final class ElementorMigrationAdapter implements BuilderMigrationAdapterInterface {
	/**
	 * Supported Elementor widget types.
	 *
	 * @var list<string>
	 */
	private const SUPPORTED_WIDGETS = array(
		'heading',
		'text-editor',
		'image',
		'button',
		'divider',
		'spacer',
	);

	/**
	 * Stable adapter identifier.
	 */
	public function id(): string {
		return 'elementor';
	}

	/**
	 * Produce a safe plan.
	 *
	 * @param WP_Post $post Resource to inspect.
	 * @return array{supported:bool,source:string,target:string,operations:list<string>,blockers:list<string>,warnings:list<string>,media_ids:list<int>}
	 */
	public function plan( WP_Post $post ): array {
		$tree = $this->tree( $post );
		if ( null === $tree ) {
			return $this->blocked_plan( 'elementor-data-missing-or-invalid' );
		}

		$widgets   = array();
		$media_ids = array();
		$this->collect_widgets( $tree, $widgets, $media_ids );

		$unsupported = array_values( array_diff( array_keys( $widgets ), self::SUPPORTED_WIDGETS ) );
		sort( $unsupported );
		$blockers = array_map(
			static fn( string $widget ): string => 'unsupported-elementor-widget:' . $widget,
			$unsupported
		);

		return array(
			'supported'  => array() === $blockers,
			'source'     => 'elementor',
			'target'     => 'wordpress-core-blocks',
			'operations' => array(
				'replace-post-content-with-native-blocks',
				'disable-elementor-page-mode',
				'retain-original-elementor-data-in-migration-backup',
			),
			'blockers'   => $blockers,
			'warnings'   => array( 'elementor-layout-and-style-settings-are-not-reproduced-exactly' ),
			'media_ids'  => $media_ids,
		);
	}

	/**
	 * Build the internal mutation payload.
	 *
	 * @param WP_Post $post Resource to transform.
	 * @return array{content:string,delete_meta:list<string>,update_meta:array<string,mixed>}
	 */
	public function transform( WP_Post $post ): array {
		$plan = $this->plan( $post );
		if ( ! $plan['supported'] ) {
			throw new RuntimeException( 'Elementor resource contains unsupported migration components.' );
		}

		$tree = $this->tree( $post );
		if ( null === $tree ) {
			throw new RuntimeException( 'Elementor migration data is unavailable.' );
		}

		$blocks = array();
		$this->render_nodes( $tree, $blocks );

		return array(
			'content'     => implode( "\n\n", $blocks ),
			'delete_meta' => array( '_elementor_edit_mode' ),
			'update_meta' => array(),
		);
	}

	/**
	 * Decode Elementor JSON tree.
	 *
	 * @return array<int,mixed>|null
	 */
	private function tree( WP_Post $post ): ?array {
		$raw = get_post_meta( $post->ID, '_elementor_data', true );
		if ( is_array( $raw ) ) {
			return $raw;
		}
		if ( ! is_string( $raw ) || '' === trim( $raw ) ) {
			return null;
		}

		$decoded = json_decode( $raw, true );
		return is_array( $decoded ) ? $decoded : null;
	}

	/**
	 * Collect widget types and media IDs recursively.
	 *
	 * @param array<int,mixed>   $nodes     Elementor nodes.
	 * @param array<string, int> $widgets   Widget counts by type.
	 * @param list<int>          $media_ids Collected media IDs.
	 */
	private function collect_widgets( array $nodes, array &$widgets, array &$media_ids ): void {
		foreach ( $nodes as $node ) {
			if ( ! is_array( $node ) ) {
				continue;
			}

			$widget_type = $node['widgetType'] ?? null;
			if ( is_string( $widget_type ) && '' !== $widget_type ) {
				$widgets[ $widget_type ] = ( $widgets[ $widget_type ] ?? 0 ) + 1;

				if ( 'image' === $widget_type ) {
					$settings = isset( $node['settings'] ) && is_array( $node['settings'] ) ? $node['settings'] : array();
					$image    = isset( $settings['image'] ) && is_array( $settings['image'] ) ? $settings['image'] : array();
					$id       = isset( $image['id'] ) ? (int) $image['id'] : 0;
					if ( 0 < $id && ! in_array( $id, $media_ids, true ) ) {
						$media_ids[] = $id;
					}
				}
			}

			$children = isset( $node['elements'] ) && is_array( $node['elements'] ) ? $node['elements'] : array();
			if ( array() !== $children ) {
				$this->collect_widgets( $children, $widgets, $media_ids );
			}
		}

		sort( $media_ids );
	}

	/**
	 * Convert Elementor nodes to serialized core blocks.
	 *
	 * @param array<int,mixed> $nodes  Elementor nodes.
	 * @param list<string>     $blocks Serialized blocks.
	 */
	private function render_nodes( array $nodes, array &$blocks ): void {
		foreach ( $nodes as $node ) {
			if ( ! is_array( $node ) ) {
				continue;
			}

			$widget_type = $node['widgetType'] ?? null;
			$settings    = isset( $node['settings'] ) && is_array( $node['settings'] ) ? $node['settings'] : array();

			if ( is_string( $widget_type ) && '' !== $widget_type ) {
				$blocks[] = $this->render_widget( $widget_type, $settings );
			}

			$children = isset( $node['elements'] ) && is_array( $node['elements'] ) ? $node['elements'] : array();
			if ( array() !== $children ) {
				$this->render_nodes( $children, $blocks );
			}
		}
	}

	/**
	 * Render one supported widget.
	 *
	 * @param string              $widget_type Elementor widget type.
	 * @param array<string,mixed> $settings    Elementor widget settings.
	 */
	private function render_widget( string $widget_type, array $settings ): string {
		return match ( $widget_type ) {
			'heading'     => $this->heading_block( $settings ),
			'text-editor' => $this->html_block( isset( $settings['editor'] ) && is_string( $settings['editor'] ) ? $settings['editor'] : '' ),
			'image'       => $this->image_block( $settings ),
			'button'      => $this->button_block( $settings ),
			'divider'     => '<!-- wp:separator --><hr class="wp-block-separator has-alpha-channel-opacity"/><!-- /wp:separator -->',
			'spacer'      => '<!-- wp:spacer {"height":"32px"} --><div style="height:32px" aria-hidden="true" class="wp-block-spacer"></div><!-- /wp:spacer -->',
			default       => throw new RuntimeException( 'Unsupported Elementor widget reached transform.' ),
		};
	}

	/**
	 * Render heading block.
	 *
	 * @param array<string,mixed> $settings Widget settings.
	 */
	private function heading_block( array $settings ): string {
		$text      = isset( $settings['title'] ) && is_string( $settings['title'] ) ? wp_strip_all_tags( $settings['title'] ) : '';
		$size      = isset( $settings['header_size'] ) && is_string( $settings['header_size'] ) ? strtolower( $settings['header_size'] ) : 'h2';
		$level     = in_array( $size, array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' ), true ) ? (int) substr( $size, 1 ) : 2;
		$attribute = 2 === $level ? '' : ' {"level":' . $level . '}';

		return '<!-- wp:heading' . $attribute . ' --><h' . $level . ' class="wp-block-heading">' . esc_html( $text ) . '</h' . $level . '><!-- /wp:heading -->';
	}

	/**
	 * Render HTML-preserving native block.
	 */
	private function html_block( string $html ): string {
		return '<!-- wp:html -->' . wp_kses_post( $html ) . '<!-- /wp:html -->';
	}

	/**
	 * Render image block while preserving attachment ID when known.
	 *
	 * @param array<string,mixed> $settings Widget settings.
	 */
	private function image_block( array $settings ): string {
		$image = isset( $settings['image'] ) && is_array( $settings['image'] ) ? $settings['image'] : array();
		$id    = isset( $image['id'] ) ? (int) $image['id'] : 0;
		$url   = isset( $image['url'] ) && is_string( $image['url'] ) ? esc_url_raw( $image['url'] ) : '';

		if ( 0 < $id ) {
			$attachment_url = wp_get_attachment_url( $id );
			if ( is_string( $attachment_url ) && '' !== $attachment_url ) {
				$url = $attachment_url;
			}
		}

		if ( '' === $url ) {
			throw new RuntimeException( 'Elementor image widget has no usable media URL.' );
		}

		$alt   = 0 < $id ? (string) get_post_meta( $id, '_wp_attachment_image_alt', true ) : '';
		$attrs = 0 < $id ? ' {"id":' . $id . ',"sizeSlug":"full","linkDestination":"none"}' : ' {"sizeSlug":"full","linkDestination":"none"}';
		$class = 0 < $id ? ' class="wp-image-' . $id . '"' : '';

		return '<!-- wp:image' . $attrs . ' --><figure class="wp-block-image size-full"><img src="' . esc_url( $url ) . '" alt="' . esc_attr( $alt ) . '"' . $class . '/></figure><!-- /wp:image -->';
	}

	/**
	 * Render button block.
	 *
	 * @param array<string,mixed> $settings Widget settings.
	 */
	private function button_block( array $settings ): string {
		$text = isset( $settings['text'] ) && is_string( $settings['text'] ) ? wp_strip_all_tags( $settings['text'] ) : '';
		$link = isset( $settings['link'] ) && is_array( $settings['link'] ) ? $settings['link'] : array();
		$url  = isset( $link['url'] ) && is_string( $link['url'] ) ? esc_url_raw( $link['url'] ) : '#';

		return '<!-- wp:buttons --><div class="wp-block-buttons"><!-- wp:button --><div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="' . esc_url( $url ) . '">' . esc_html( $text ) . '</a></div><!-- /wp:button --></div><!-- /wp:buttons -->';
	}

	/**
	 * Build a stable blocked plan.
	 *
	 * @return array{supported:bool,source:string,target:string,operations:list<string>,blockers:list<string>,warnings:list<string>,media_ids:list<int>}
	 */
	private function blocked_plan( string $blocker ): array {
		return array(
			'supported'  => false,
			'source'     => 'elementor',
			'target'     => 'wordpress-core-blocks',
			'operations' => array(),
			'blockers'   => array( $blocker ),
			'warnings'   => array(),
			'media_ids'  => array(),
		);
	}
}
