<?php
/**
 * Theme/preset contract intelligence for Build / Finish workflows.
 *
 * @package SeoGeoManager
 */

declare(strict_types=1);

namespace SeoGeo\Manager\Intelligence;

use WP_HTML_Tag_Processor;
use WP_Post;
use WP_Query;

final class ThemeContractScanner {
	private const MAX_FUZZY_PAGES = 100;

	/**
	 * Inspect the active SEO/GEO Theme preset and its expected strategic surfaces.
	 *
	 * @return array<string, mixed>
	 */
	public static function scan(): array {
		$theme       = wp_get_theme();
		$is_seo_geo  = self::is_seo_geo_theme(
			(string) $theme->get_stylesheet(),
			(string) $theme->get_template(),
			(string) $theme->get( 'TextDomain' )
		);
		$preset_id   = self::active_preset_id();
		$content_map = '' !== $preset_id ? self::preset_document( $preset_id, 'content-map.json' ) : null;
		$page_models = '' !== $preset_id ? self::preset_document( $preset_id, 'page-models.json' ) : null;

		if ( ! $is_seo_geo || '' === $preset_id || ! is_array( $content_map ) ) {
			return array(
				'applicable' => false,
				'theme'      => array(
					'name'       => (string) $theme->get( 'Name' ),
					'version'    => (string) $theme->get( 'Version' ),
					'stylesheet' => (string) $theme->get_stylesheet(),
				),
				'preset'     => '' !== $preset_id ? $preset_id : null,
				'reason'     => ! $is_seo_geo ? 'active-theme-is-not-seo-geo-theme' : 'preset-contract-unavailable',
			);
		}

		$locale_key = self::resolve_locale_key( $content_map );
		$locale     = $content_map['locales'][ $locale_key ] ?? null;
		$pages      = is_array( $locale ) && isset( $locale['pages'] ) && is_array( $locale['pages'] ) ? $locale['pages'] : array();
		$models     = self::models( $content_map, $page_models );
		$model_map  = self::models_by_page( $page_models );
		$items      = array();
		$summary    = array(
			'expected_pages'         => 0,
			'resolved_pages'         => 0,
			'missing_pages'          => 0,
			'model_pages'            => 0,
			'incomplete_model_pages' => 0,
			'missing_required_slots' => 0,
			'media_images'           => 0,
			'media_missing_alt'      => 0,
		);

		foreach ( $pages as $page_definition ) {
			if ( ! is_array( $page_definition ) ) {
				continue;
			}

			$key = isset( $page_definition['key'] ) && is_string( $page_definition['key'] ) ? sanitize_key( $page_definition['key'] ) : '';
			if ( '' === $key ) {
				continue;
			}

			++$summary['expected_pages'];
			$resolved = self::resolve_page( $key, $page_definition, $preset_id );
			$post     = $resolved['post'];
			$model_id = self::model_id_for_page( $key, $page_definition, $model_map );
			$model    = '' !== $model_id && isset( $models[ $model_id ] ) && is_array( $models[ $model_id ] ) ? $models[ $model_id ] : null;

			if ( $post instanceof WP_Post ) {
				++$summary['resolved_pages'];
			} else {
				++$summary['missing_pages'];
			}

			$slot_report = array(
				'model_id'                  => '' !== $model_id ? $model_id : null,
				'required_slots'            => array(),
				'present_required_slots'    => array(),
				'missing_required_slots'    => array(),
				'required_any_groups'       => array(),
				'missing_required_any'      => array(),
				'verification_groups'       => array(),
				'missing_verified_groups'   => array(),
				'provenance_review_required'=> array(),
			);

			if ( is_array( $model ) ) {
				++$summary['model_pages'];
				$slot_report = self::slot_report( $post, $model, $model_id );
				$missing     = count( $slot_report['missing_required_slots'] ) + count( $slot_report['missing_required_any'] ) + count( $slot_report['missing_verified_groups'] );
				$summary['missing_required_slots'] += $missing;
				if ( 0 < $missing ) {
					++$summary['incomplete_model_pages'];
				}
			}

			$media = self::page_media( $post );
			$summary['media_images']      += (int) $media['content_images'];
			$summary['media_missing_alt'] += (int) $media['missing_alt'];

			$items[] = array(
				'key'              => $key,
				'role'             => isset( $page_definition['role'] ) && is_string( $page_definition['role'] ) ? $page_definition['role'] : '',
				'expected_title'   => isset( $page_definition['title'] ) && is_string( $page_definition['title'] ) ? $page_definition['title'] : '',
				'expected_slug'    => isset( $page_definition['slug'] ) && is_string( $page_definition['slug'] ) ? $page_definition['slug'] : '',
				'resolved'         => $post instanceof WP_Post,
				'resolution'       => array(
					'method'     => $resolved['method'],
					'confidence' => $resolved['confidence'],
				),
				'resource'         => self::resource_summary( $post ),
				'content_contract' => array(
					'primary_intent'    => self::nested_string( $page_definition, array( 'content_contract', 'primary_intent' ) ),
					'required_sections' => self::nested_string_list( $page_definition, array( 'content_contract', 'required_sections' ) ),
					'internal_targets'  => self::nested_string_list( $page_definition, array( 'content_contract', 'internal_link_targets' ) ),
				),
				'model'            => $slot_report,
				'media'            => $media,
			);
		}

		return array(
			'applicable' => true,
			'theme'      => array(
				'name'       => (string) $theme->get( 'Name' ),
				'version'    => (string) $theme->get( 'Version' ),
				'stylesheet' => (string) $theme->get_stylesheet(),
			),
			'preset'     => $preset_id,
			'locale'     => $locale_key,
			'summary'    => $summary,
			'pages'      => $items,
		);
	}

	/**
	 * Resolve the active preset without making Manager depend on Theme internals.
	 */
	private static function active_preset_id(): string {
		if ( function_exists( 'seo_geo_theme_active_preset_id' ) ) {
			$value = call_user_func( 'seo_geo_theme_active_preset_id' );
			if ( is_string( $value ) ) {
				return sanitize_key( $value );
			}
		}

		$value = get_option( 'seo_geo_active_preset', '' );

		return is_string( $value ) ? sanitize_key( $value ) : '';
	}

	/**
	 * Load one preset contract through the Theme API or an installed-theme fallback.
	 *
	 * @return array<string, mixed>|null
	 */
	private static function preset_document( string $preset_id, string $filename ): ?array {
		if ( function_exists( 'seo_geo_theme_preset_document' ) ) {
			$value = call_user_func( 'seo_geo_theme_preset_document', $preset_id, $filename );
			if ( is_array( $value ) ) {
				return $value;
			}
		}

		$path = trailingslashit( get_template_directory() ) . 'presets/' . sanitize_key( $preset_id ) . '/' . basename( $filename );
		if ( ! is_readable( $path ) || ! function_exists( 'wp_json_file_decode' ) ) {
			return null;
		}

		$value = wp_json_file_decode( $path, array( 'associative' => true ) );

		return is_array( $value ) ? $value : null;
	}

	/**
	 * @param array<string, mixed> $content_map Content map.
	 */
	private static function resolve_locale_key( array $content_map ): string {
		$locales = isset( $content_map['locales'] ) && is_array( $content_map['locales'] ) ? $content_map['locales'] : array();
		$current = get_locale();
		if ( isset( $locales[ $current ] ) ) {
			return $current;
		}

		$fallback = str_starts_with( strtolower( $current ), 'es' ) ? 'es_ES' : 'en_US';
		if ( isset( $locales[ $fallback ] ) ) {
			return $fallback;
		}

		$keys = array_keys( $locales );

		return isset( $keys[0] ) && is_string( $keys[0] ) ? $keys[0] : $fallback;
	}

	/**
	 * @param array<string, mixed>      $content_map Content map.
	 * @param array<string, mixed>|null $page_models Page models.
	 * @return array<string, array<string, mixed>>
	 */
	private static function models( array $content_map, ?array $page_models ): array {
		$models = array();
		foreach ( array( $content_map, $page_models ) as $document ) {
			if ( ! is_array( $document ) || ! isset( $document['native_content_models'] ) || ! is_array( $document['native_content_models'] ) ) {
				continue;
			}
			foreach ( $document['native_content_models'] as $model_id => $model ) {
				if ( is_string( $model_id ) && is_array( $model ) ) {
					$models[ $model_id ] = $model;
				}
			}
		}

		return $models;
	}

	/**
	 * @param array<string, mixed>|null $page_models Page models.
	 * @return array<string, string>
	 */
	private static function models_by_page( ?array $page_models ): array {
		$result = array();
		if ( ! is_array( $page_models ) || ! isset( $page_models['models_by_page'] ) || ! is_array( $page_models['models_by_page'] ) ) {
			return $result;
		}

		foreach ( $page_models['models_by_page'] as $page_key => $model_id ) {
			if ( is_string( $page_key ) && is_string( $model_id ) ) {
				$result[ sanitize_key( $page_key ) ] = sanitize_key( $model_id );
			}
		}

		return $result;
	}

	/**
	 * @param array<string, mixed>  $page_definition Page definition.
	 * @param array<string, string> $model_map Model map.
	 */
	private static function model_id_for_page( string $key, array $page_definition, array $model_map ): string {
		$value = self::nested_string( $page_definition, array( 'content_contract', 'native_content_model' ) );
		if ( '' !== $value ) {
			return sanitize_key( $value );
		}

		return $model_map[ $key ] ?? '';
	}

	/**
	 * @param array<string, mixed> $page_definition Page definition.
	 * @return array{post:WP_Post|null,method:string,confidence:string}
	 */
	private static function resolve_page( string $key, array $page_definition, string $preset_id ): array {
		$filtered = apply_filters( 'seo_geo_manager_resolve_preset_page_id', 0, $key, $page_definition, $preset_id );
		if ( is_numeric( $filtered ) && 0 < (int) $filtered ) {
			$post = get_post( (int) $filtered );
			if ( $post instanceof WP_Post && 'page' === $post->post_type ) {
				return array( 'post' => $post, 'method' => 'filter', 'confidence' => 'high' );
			}
		}

		$role = isset( $page_definition['role'] ) && is_string( $page_definition['role'] ) ? $page_definition['role'] : '';
		if ( 'front-page' === $role && 'page' === get_option( 'show_on_front' ) ) {
			$post = get_post( (int) get_option( 'page_on_front', 0 ) );
			if ( $post instanceof WP_Post ) {
				return array( 'post' => $post, 'method' => 'front-page-option', 'confidence' => 'high' );
			}
		}

		if ( 'posts-page' === $role ) {
			$post = get_post( (int) get_option( 'page_for_posts', 0 ) );
			if ( $post instanceof WP_Post ) {
				return array( 'post' => $post, 'method' => 'posts-page-option', 'confidence' => 'high' );
			}
		}

		$slug = isset( $page_definition['slug'] ) && is_string( $page_definition['slug'] ) ? trim( $page_definition['slug'], '/' ) : '';
		if ( '' !== $slug ) {
			$post = get_page_by_path( $slug, OBJECT, 'page' );
			if ( $post instanceof WP_Post ) {
				return array( 'post' => $post, 'method' => 'expected-slug', 'confidence' => 'high' );
			}
		}

		$title = isset( $page_definition['title'] ) && is_string( $page_definition['title'] ) ? trim( $page_definition['title'] ) : '';
		if ( '' !== $title ) {
			$query = new WP_Query(
				array(
					'post_type'      => 'page',
					'post_status'    => array( 'publish', 'future', 'draft', 'pending', 'private' ),
					'posts_per_page' => 2,
					'title'          => $title,
					'no_found_rows'  => true,
				)
			);
			if ( 1 === count( $query->posts ) && $query->posts[0] instanceof WP_Post ) {
				return array( 'post' => $query->posts[0], 'method' => 'expected-title', 'confidence' => 'medium' );
			}
		}

		$fuzzy = self::fuzzy_page( $slug, $title );
		if ( $fuzzy instanceof WP_Post ) {
			return array( 'post' => $fuzzy, 'method' => 'fuzzy-title-slug', 'confidence' => 'medium' );
		}

		return array( 'post' => null, 'method' => 'unresolved', 'confidence' => 'none' );
	}

	private static function fuzzy_page( string $slug, string $title ): ?WP_Post {
		$needles = self::tokens( sanitize_title( $slug . ' ' . $title ) );
		if ( array() === $needles ) {
			return null;
		}

		$query = new WP_Query(
			array(
				'post_type'      => 'page',
				'post_status'    => array( 'publish', 'future', 'draft', 'pending', 'private' ),
				'posts_per_page' => self::MAX_FUZZY_PAGES,
				'orderby'        => 'ID',
				'order'          => 'ASC',
				'no_found_rows'  => true,
			)
		);
		$best       = null;
		$best_score = 0.0;
		$tied       = false;

		foreach ( $query->posts as $candidate ) {
			if ( ! $candidate instanceof WP_Post ) {
				continue;
			}
			$haystack = self::tokens( sanitize_title( $candidate->post_name . ' ' . $candidate->post_title ) );
			if ( array() === $haystack ) {
				continue;
			}
			$hits  = count( array_intersect( $needles, $haystack ) );
			$score = $hits / max( 1, count( $needles ) );
			if ( $score > $best_score ) {
				$best       = $candidate;
				$best_score = $score;
				$tied       = false;
			} elseif ( $score === $best_score && 0.0 < $score ) {
				$tied = true;
			}
		}

		return 0.75 <= $best_score && ! $tied && $best instanceof WP_Post ? $best : null;
	}

	/**
	 * @return list<string>
	 */
	private static function tokens( string $value ): array {
		$parts  = preg_split( '/-+/', $value );
		$result = array();
		if ( ! is_array( $parts ) ) {
			return $result;
		}

		foreach ( $parts as $part ) {
			if ( 3 <= strlen( $part ) ) {
				$result[] = $part;
			}
		}

		return array_values( array_unique( $result ) );
	}

	/**
	 * @param WP_Post|null         $post Post.
	 * @param array<string, mixed> $model Model contract.
	 * @return array<string, mixed>
	 */
	private static function slot_report( ?WP_Post $post, array $model, string $model_id ): array {
		$required      = array();
		$present       = array();
		$missing       = array();
		$slot_presence = array();
		$slots         = isset( $model['slots'] ) && is_array( $model['slots'] ) ? $model['slots'] : array();
		$content       = $post instanceof WP_Post ? (string) $post->post_content : '';

		foreach ( $slots as $slot ) {
			if ( ! is_array( $slot ) || ! isset( $slot['id'] ) || ! is_string( $slot['id'] ) ) {
				continue;
			}
			$id   = sanitize_key( $slot['id'] );
			$type = isset( $slot['type'] ) && is_string( $slot['type'] ) ? sanitize_key( $slot['type'] ) : 'text';
			$has  = '' !== $content && self::slot_has_value( $content, $id, $type );
			$slot_presence[ $id ] = $has;
			if ( true === ( $slot['required'] ?? false ) ) {
				$required[] = $id;
				if ( $has ) {
					$present[] = $id;
				} else {
					$missing[] = $id;
				}
			}
		}

		$required_any        = array();
		$missing_any         = array();
		$required_any_groups = isset( $model['required_any_slots'] ) && is_array( $model['required_any_slots'] ) ? $model['required_any_slots'] : array();
		foreach ( $required_any_groups as $group ) {
			if ( ! is_array( $group ) ) {
				continue;
			}
			$ids = array_values( array_filter( array_map( 'sanitize_key', array_filter( $group, 'is_string' ) ) ) );
			if ( array() === $ids ) {
				continue;
			}
			$required_any[] = $ids;
			$has_any        = false;
			foreach ( $ids as $id ) {
				if ( true === ( $slot_presence[ $id ] ?? false ) ) {
					$has_any = true;
					break;
				}
			}
			if ( ! $has_any ) {
				$missing_any[] = $ids;
			}
		}

		$verification_groups = array();
		foreach ( $slots as $slot ) {
			if ( ! is_array( $slot ) || ! isset( $slot['verification_group'] ) || ! is_string( $slot['verification_group'] ) || ! isset( $slot['id'] ) || ! is_string( $slot['id'] ) ) {
				continue;
			}
			$group = sanitize_key( $slot['verification_group'] );
			$id    = sanitize_key( $slot['id'] );
			if ( '' === $group ) {
				continue;
			}
			if ( ! isset( $verification_groups[ $group ] ) ) {
				$verification_groups[ $group ] = array();
			}
			if ( true === ( $slot_presence[ $id ] ?? false ) ) {
				$verification_groups[ $group ][] = $id;
			}
		}

		$missing_verified = array();
		$provenance       = array();
		$required_verified = isset( $model['required_verified_groups'] ) && is_array( $model['required_verified_groups'] ) ? $model['required_verified_groups'] : array();
		foreach ( $required_verified as $group ) {
			if ( ! is_string( $group ) ) {
				continue;
			}
			$group = sanitize_key( $group );
			if ( array() === ( $verification_groups[ $group ] ?? array() ) ) {
				$missing_verified[] = $group;
			} else {
				$provenance[] = $group;
			}
		}

		return array(
			'model_id'                   => $model_id,
			'required_slots'             => $required,
			'present_required_slots'     => $present,
			'missing_required_slots'     => $missing,
			'required_any_groups'        => $required_any,
			'missing_required_any'       => $missing_any,
			'verification_groups'        => $verification_groups,
			'missing_verified_groups'    => $missing_verified,
			'provenance_review_required' => $provenance,
		);
	}

	private static function slot_has_value( string $content, string $slot_id, string $type ): bool {
		$marker = 'seo-geo-content-slot--' . $slot_id;
		if ( ! str_contains( $content, $marker ) ) {
			return false;
		}

		$blocks = parse_blocks( $content );

		return self::blocks_have_slot_value( $blocks, $marker, $type );
	}

	/**
	 * @param array<int, array<string, mixed>> $blocks Parsed blocks.
	 */
	private static function blocks_have_slot_value( array $blocks, string $marker, string $type ): bool {
		foreach ( $blocks as $block ) {
			$attrs      = isset( $block['attrs'] ) && is_array( $block['attrs'] ) ? $block['attrs'] : array();
			$class_name = isset( $attrs['className'] ) && is_string( $attrs['className'] ) ? $attrs['className'] : '';
			$inner_html = isset( $block['innerHTML'] ) && is_string( $block['innerHTML'] ) ? $block['innerHTML'] : '';
			if ( str_contains( $class_name, $marker ) ) {
				$html = render_block( $block );
				if ( self::html_has_meaningful_value( $html, $type ) ) {
					return true;
				}
			}
			if ( str_contains( $inner_html, $marker ) && self::marked_html_has_value( $inner_html, $marker, $type ) ) {
				return true;
			}
			$inner_blocks = isset( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ? $block['innerBlocks'] : array();
			if ( array() !== $inner_blocks && self::blocks_have_slot_value( $inner_blocks, $marker, $type ) ) {
				return true;
			}
		}

		return false;
	}

	private static function marked_html_has_value( string $html, string $marker, string $type ): bool {
		$pattern = '/<([a-z0-9]+)\\b[^>]*class=(?:"|\\\')([^"\\\']*' . preg_quote( $marker, '/' ) . '[^"\\\']*)(?:"|\\\')[^>]*>(.*?)<\\/\\1>/is';
		if ( 1 > preg_match_all( $pattern, $html, $matches, PREG_SET_ORDER ) ) {
			return false;
		}
		foreach ( $matches as $match ) {
			if ( isset( $match[0] ) && is_string( $match[0] ) && self::html_has_meaningful_value( $match[0], $type ) ) {
				return true;
			}
		}

		return false;
	}

	private static function html_has_meaningful_value( string $html, string $type ): bool {
		if ( 'link' === $type ) {
			$processor = new WP_HTML_Tag_Processor( $html );
			while ( $processor->next_tag( 'a' ) ) {
				$href = $processor->get_attribute( 'href' );
				if ( is_string( $href ) && '' !== trim( $href ) && '' !== trim( wp_strip_all_tags( $html, true ) ) ) {
					return true;
				}
			}

			return false;
		}

		if ( 'list' === $type ) {
			return str_contains( strtolower( $html ), '<li' ) && '' !== trim( wp_strip_all_tags( $html, true ) );
		}

		return '' !== trim( wp_strip_all_tags( $html, true ) );
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function page_media( ?WP_Post $post ): array {
		if ( ! $post instanceof WP_Post ) {
			return array(
				'featured_media_id' => 0,
				'content_images'     => 0,
				'missing_alt'        => 0,
			);
		}

		$featured = get_post_thumbnail_id( $post );
		$processor = new WP_HTML_Tag_Processor( (string) $post->post_content );
		$images     = 0;
		$missing    = 0;
		while ( $processor->next_tag( 'img' ) ) {
			++$images;
			$alt = $processor->get_attribute( 'alt' );
			if ( ! is_string( $alt ) || '' === trim( $alt ) ) {
				++$missing;
			}
		}

		return array(
			'featured_media_id' => is_numeric( $featured ) ? (int) $featured : 0,
			'content_images'     => $images,
			'missing_alt'        => $missing,
		);
	}

	/**
	 * @return array<string, mixed>|null
	 */
	private static function resource_summary( ?WP_Post $post ): ?array {
		if ( ! $post instanceof WP_Post ) {
			return null;
		}
		$permalink = get_permalink( $post );

		return array(
			'id'        => (int) $post->ID,
			'status'    => (string) $post->post_status,
			'slug'      => (string) $post->post_name,
			'title'     => html_entity_decode( get_the_title( $post ), ENT_QUOTES | ENT_HTML5, get_bloginfo( 'charset' ) ),
			'permalink' => is_string( $permalink ) ? $permalink : '',
		);
	}

	/**
	 * @param array<string, mixed> $source Source.
	 * @param list<string>         $path Path.
	 */
	private static function nested_string( array $source, array $path ): string {
		$value = $source;
		foreach ( $path as $key ) {
			if ( ! isset( $value[ $key ] ) || ! is_array( $value ) ) {
				return '';
			}
			$value = $value[ $key ];
		}

		return is_string( $value ) ? $value : '';
	}

	/**
	 * @param array<string, mixed> $source Source.
	 * @param list<string>         $path Path.
	 * @return list<string>
	 */
	private static function nested_string_list( array $source, array $path ): array {
		$value = $source;
		foreach ( $path as $key ) {
			if ( ! is_array( $value ) || ! array_key_exists( $key, $value ) ) {
				return array();
			}
			$value = $value[ $key ];
		}
		if ( ! is_array( $value ) ) {
			return array();
		}

		return array_values( array_filter( $value, 'is_string' ) );
	}

	private static function is_seo_geo_theme( string $stylesheet, string $template, string $text_domain ): bool {
		$haystack = strtolower( implode( '|', array( $stylesheet, $template, $text_domain ) ) );

		return false !== strpos( $haystack, 'seo-geo' );
	}
}
