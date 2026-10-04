<?php
/**
 * Preset-owned navigation runtime.
 *
 * @package SeoGeoTheme
 */

declare(strict_types=1);

namespace SeoGeo\Theme\Navigation;

use WP_Post;

/**
 * Renders a compact navigation surface for active presets.
 */
final class PresetNavigationRuntime {
	public const BLOCK_NAME = 'seo-geo/preset-navigation';

	/**
	 * Register menu locations and the dynamic block.
	 */
	public function register(): void {
		add_action( 'after_setup_theme', array( $this, 'register_menu_locations' ) );
		add_action( 'init', array( $this, 'register_block' ) );
	}

	/**
	 * Register conventional menu locations so future sites may assign them explicitly.
	 */
	public function register_menu_locations(): void {
		register_nav_menus(
			array(
				'primary' => __( 'Primary navigation', 'seo-geo-theme' ),
				'footer'  => __( 'Footer navigation', 'seo-geo-theme' ),
			)
		);
	}

	/**
	 * Register the server-rendered navigation block.
	 */
	public function register_block(): void {
		register_block_type(
			self::BLOCK_NAME,
			array(
				'api_version'     => '3',
				'attributes'      => array(
					'location' => array(
						'type'    => 'string',
						'default' => 'primary',
					),
				),
				'render_callback' => array( $this, 'render' ),
			)
		);
	}

	/**
	 * Render one preset navigation location.
	 *
	 * @param array<string,mixed> $attributes Block attributes.
	 */
	public function render( array $attributes ): string {
		$location = isset( $attributes['location'] ) && is_string( $attributes['location'] )
			? sanitize_key( $attributes['location'] )
			: 'primary';

		if ( ! in_array( $location, array( 'primary', 'footer' ), true ) ) {
			$location = 'primary';
		}

		$items = $this->resolved_items( $location );
		if ( array() === $items ) {
			return '';
		}

		$label = 'footer' === $location
			? __( 'Footer navigation', 'seo-geo-theme' )
			: __( 'Primary navigation', 'seo-geo-theme' );
		$class = 'seo-geo-preset-navigation seo-geo-preset-navigation--' . $location;

		$list = '<ul class="seo-geo-preset-navigation__list">';
		foreach ( $items as $item ) {
			$url  = $item['url'];
			$text = $item['label'];
			if ( '' === $url || '' === $text ) {
				continue;
			}

			$current = $this->is_current_url( $url ) ? ' aria-current="page"' : '';
			$list   .= '<li class="seo-geo-preset-navigation__item"><a href="' . esc_url( $url ) . '"' . $current . '>' . esc_html( $text ) . '</a></li>';
		}
		$list .= '</ul>';

		if ( 'primary' === $location ) {
			$mobile = '<details class="seo-geo-preset-navigation__mobile"><summary>' . esc_html__( 'Menu', 'seo-geo-theme' ) . '</summary>' . $list . '</details>';
			return '<nav class="' . esc_attr( $class ) . '" aria-label="' . esc_attr( $label ) . '"><div class="seo-geo-preset-navigation__desktop">' . $list . '</div>' . $mobile . '</nav>';
		}

		return '<nav class="' . esc_attr( $class ) . '" aria-label="' . esc_attr( $label ) . '">' . $list . '</nav>';
	}

	/**
	 * Resolve menu items from explicit theme location, a bounded legacy-menu
	 * heuristic, or the active preset navigation contract.
	 *
	 * @param string $location Navigation location.
	 * @return list<array{label:string,url:string}>
	 */
	private function resolved_items( string $location ): array {
		$assigned = $this->assigned_menu_items( $location );
		if ( array() !== $assigned ) {
			return $assigned;
		}

		if ( 'primary' === $location ) {
			$legacy = $this->legacy_primary_menu_items();
			if ( array() !== $legacy ) {
				return $legacy;
			}
		}

		return $this->preset_items( $location );
	}

	/**
	 * Resolve a menu explicitly assigned to the current theme location.
	 *
	 * @param string $location Navigation location.
	 * @return list<array{label:string,url:string}>
	 */
	private function assigned_menu_items( string $location ): array {
		$locations = get_nav_menu_locations();
		if ( ! isset( $locations[ $location ] ) || 0 >= (int) $locations[ $location ] ) {
			return array();
		}

		return $this->menu_items_from_term_id( (int) $locations[ $location ] );
	}

	/**
	 * Reuse a plausible pre-existing classic primary menu on migrated sites.
	 *
	 * This is read-only and bounded: only menus with 3-8 public items qualify.
	 * Named primary/header/main menus are preferred; otherwise the smallest
	 * suitable menu is selected.
	 *
	 * @return list<array{label:string,url:string}>
	 */
	private function legacy_primary_menu_items(): array {
		$menus = wp_get_nav_menus();
		if ( array() === $menus ) {
			return array();
		}

		$candidates = array();

		foreach ( $menus as $menu ) {
			$items = $this->menu_items_from_term_id( (int) $menu->term_id );
			$count = count( $items );
			if ( 3 > $count || 8 < $count ) {
				continue;
			}

			$name  = strtolower( remove_accents( (string) $menu->name ) );
			$score = 0;
			foreach ( array( 'primary', 'principal', 'main', 'header', 'top', 'menu' ) as $needle ) {
				if ( str_contains( $name, $needle ) ) {
					$score += 10;
				}
			}
			$score += max( 0, 8 - $count );

			$candidates[] = array(
				'score' => $score,
				'items' => $items,
			);
		}

		if ( array() === $candidates ) {
			return array();
		}

		usort(
			$candidates,
			static fn( array $a, array $b ): int => (int) $b['score'] <=> (int) $a['score']
		);

		return $candidates[0]['items'];
	}

	/**
	 * Convert classic menu items into the public render shape.
	 *
	 * @param int $term_id Menu term ID.
	 * @return list<array{label:string,url:string}>
	 */
	private function menu_items_from_term_id( int $term_id ): array {
		$raw = wp_get_nav_menu_items( $term_id );
		if ( ! is_array( $raw ) ) {
			return array();
		}

		$items = array();
		foreach ( $raw as $item ) {
			if ( ! $item instanceof WP_Post || 'publish' !== $item->post_status ) {
				continue;
			}

			$item_data = get_object_vars( $item );
			$url       = isset( $item_data['url'] ) && is_string( $item_data['url'] ) ? trim( $item_data['url'] ) : '';
			$label     = isset( $item_data['title'] ) && is_string( $item_data['title'] ) ? trim( wp_strip_all_tags( $item_data['title'] ) ) : '';
			if ( '' === $url || '' === $label ) {
				continue;
			}

			$items[] = array(
				'label' => $label,
				'url'   => $url,
			);
		}

		return $items;
	}

	/**
	 * Resolve the active preset navigation map against existing site content.
	 *
	 * @param string $location Navigation location.
	 * @return list<array{label:string,url:string}>
	 */
	private function preset_items( string $location ): array {
		if ( ! function_exists( 'seo_geo_theme_active_preset_id' ) || ! function_exists( 'seo_geo_theme_preset_document' ) ) {
			return array();
		}

		$preset_id = seo_geo_theme_active_preset_id();
		if ( null === $preset_id ) {
			return array();
		}

		$manifest = seo_geo_theme_preset_document( $preset_id, 'preset.json' );
		$content  = seo_geo_theme_preset_document( $preset_id, 'content-map.json' );
		if ( ! is_array( $manifest ) || ! is_array( $content ) ) {
			return array();
		}

		$keys   = $manifest['navigation'][ $location ] ?? null;
		$locale = function_exists( 'seo_geo_theme_preset_locale' ) ? seo_geo_theme_preset_locale() : 'en_US';
		$pages  = $content['locales'][ $locale ]['pages'] ?? $content['locales']['en_US']['pages'] ?? null;
		if ( ! is_array( $keys ) || ! is_array( $pages ) ) {
			return array();
		}

		$definitions = array();
		foreach ( $pages as $page ) {
			if ( is_array( $page ) && isset( $page['key'] ) && is_string( $page['key'] ) ) {
				$definitions[ $page['key'] ] = $page;
			}
		}

		$items = array();
		foreach ( $keys as $key ) {
			if ( ! is_string( $key ) || ! isset( $definitions[ $key ] ) ) {
				continue;
			}

			$resolved = $this->resolve_page_definition( $key, $definitions[ $key ] );
			if ( null !== $resolved ) {
				$items[] = $resolved;
			}
		}

		return $items;
	}

	/**
	 * Resolve one content-map page definition without creating or mutating content.
	 *
	 * @param string              $key        Preset page key.
	 * @param array<string,mixed> $definition Preset page definition.
	 * @return array{label:string,url:string}|null
	 */
	private function resolve_page_definition( string $key, array $definition ): ?array {
		$title = isset( $definition['title'] ) && is_string( $definition['title'] ) ? trim( $definition['title'] ) : '';
		$slug  = isset( $definition['slug'] ) && is_string( $definition['slug'] ) ? sanitize_title( $definition['slug'] ) : '';
		$role  = isset( $definition['role'] ) && is_string( $definition['role'] ) ? sanitize_key( $definition['role'] ) : '';

		if ( 'front-page' === $role || 'home' === $key ) {
			return array(
				'label' => '' !== $title ? $title : __( 'Home', 'seo-geo-theme' ),
				'url'   => home_url( '/' ),
			);
		}

		if ( 'posts-page' === $role ) {
			$posts_page = (int) get_option( 'page_for_posts', 0 );
			if ( 0 < $posts_page && 'publish' === get_post_status( $posts_page ) ) {
				$posts_url = get_permalink( $posts_page );
				if ( is_string( $posts_url ) && '' !== $posts_url ) {
					return array(
						'label' => get_the_title( $posts_page ),
						'url'   => $posts_url,
					);
				}
			}
		}

		if ( 'privacy-policy' === $key ) {
			$url = get_privacy_policy_url();
			if ( '' !== $url ) {
				return array(
					'label' => '' !== $title ? $title : __( 'Privacy policy', 'seo-geo-theme' ),
					'url'   => $url,
				);
			}
		}

		if ( '' !== $slug ) {
			$page = get_page_by_path( $slug, OBJECT, 'page' );
			if ( $page instanceof WP_Post && 'publish' === $page->post_status ) {
				$page_url = get_permalink( $page );
				if ( '' !== $page_url ) {
					return array(
						'label' => '' !== $title ? $title : get_the_title( $page ),
						'url'   => $page_url,
					);
				}
			}
		}

		if ( '' !== $title ) {
			$query = new \WP_Query(
				array(
					'post_type'              => 'page',
					'post_status'            => 'publish',
					'posts_per_page'         => 20,
					'orderby'                => 'menu_order title',
					'order'                  => 'ASC',
					'no_found_rows'          => true,
					'update_post_meta_cache' => false,
					'update_post_term_cache' => false,
				)
			);

			$needle = strtolower( remove_accents( $title ) );

			foreach ( $query->posts as $page ) {
				if ( ! $page instanceof WP_Post ) {
					continue;
				}
				$candidate = strtolower( remove_accents( get_the_title( $page ) ) );
				if ( str_contains( $candidate, $needle ) || str_contains( $needle, $candidate ) ) {
					$page_url = get_permalink( $page );
					if ( '' !== $page_url ) {
						return array(
							'label' => get_the_title( $page ),
							'url'   => $page_url,
						);
					}
				}
			}
		}

		return null;
	}

	/**
	 * Determine whether a navigation target represents the current request.
	 *
	 * @param string $url Navigation target URL.
	 */
	private function is_current_url( string $url ): bool {
		$request_uri  = isset( $_SERVER['REQUEST_URI'] )
			? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) )
			: '/';
		$current_path = wp_parse_url( $request_uri, PHP_URL_PATH );
		$target_path  = wp_parse_url( $url, PHP_URL_PATH );

		if ( ! is_string( $current_path ) || ! is_string( $target_path ) ) {
			return false;
		}

		return untrailingslashit( $current_path ) === untrailingslashit( $target_path );
	}
}
