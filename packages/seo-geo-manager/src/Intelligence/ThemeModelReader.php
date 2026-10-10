<?php
/**
 * Read-only semantic model discovery for the active SEO/GEO Theme preset.
 *
 * @package SeoGeoManager
 */

declare(strict_types=1);

namespace SeoGeo\Manager\Intelligence;

use SeoGeo\Manager\Support\ContentFingerprint;
use WP_Error;
use WP_Post;

final class ThemeModelReader {
	private const DEFAULT_SLOT_PREFIX = 'seo-geo-content-slot--';

	/**
	 * List semantic models exposed by the active preset contract.
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	public static function list_models() {
		$context = self::context();
		if ( is_wp_error( $context ) ) {
			return $context;
		}

		$models = array();
		foreach ( $context['models'] as $model_id => $model ) {
			if ( ! is_string( $model_id ) || ! is_array( $model ) ) {
				continue;
			}

			$models[] = self::model_summary(
				sanitize_key( $model_id ),
				$model,
				$context['scan']
			);
		}

		usort(
			$models,
			static fn ( array $left, array $right ): int => strcmp( (string) $left['model_id'], (string) $right['model_id'] )
		);

		return array(
			'schema_version' => 1,
			'applicable'     => true,
			'theme'          => $context['scan']['theme'] ?? array(),
			'preset'         => $context['preset'],
			'locale'         => $context['scan']['locale'] ?? null,
			'model_count'    => count( $models ),
			'models'         => $models,
			'policy'         => array(
				'contract_authority'       => 'active-theme-preset-page-models',
				'arbitrary_template_read' => false,
				'raw_post_content_returned'=> false,
			),
		);
	}

	/**
	 * Read one semantic model, its mapped page and current bounded slot values.
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	public static function read_model( string $model_id ) {
		$model_id = sanitize_key( $model_id );
		if ( '' === $model_id ) {
			return self::error( 'seo_geo_manager_theme_model_invalid', 'A valid Theme model ID is required.', 400 );
		}

		$context = self::context();
		if ( is_wp_error( $context ) ) {
			return $context;
		}

		$model = $context['models'][ $model_id ] ?? null;
		if ( ! is_array( $model ) ) {
			return self::error( 'seo_geo_manager_theme_model_not_found', 'The requested Theme semantic model is not defined by the active preset.', 404 );
		}

		$mapping = self::mapped_page( $model_id, $context['scan'] );
		$post    = null;
		if ( 0 < $mapping['resource_id'] ) {
			$candidate = get_post( $mapping['resource_id'] );
			if ( $candidate instanceof WP_Post && 'page' === $candidate->post_type ) {
				$post = $candidate;
			}
		}

		if ( $post instanceof WP_Post && ! current_user_can( 'edit_post', $post->ID ) ) {
			return self::error( 'seo_geo_manager_theme_model_forbidden', 'You cannot inspect the mapped Theme page.', 403 );
		}

		$slots       = self::slot_definitions( $model );
		$slot_values = array();
		foreach ( $slots as $slot ) {
			$slot_values[] = self::slot_state( $slot, $model, $post );
		}

		return array(
			'schema_version' => 1,
			'applicable'     => true,
			'theme'          => $context['scan']['theme'] ?? array(),
			'preset'         => $context['preset'],
			'locale'         => $context['scan']['locale'] ?? null,
			'model'          => array(
				'model_id'                 => $model_id,
				'schema_version'            => isset( $model['schema_version'] ) ? (int) $model['schema_version'] : 1,
				'page_key'                  => self::model_page_key( $model_id, $model, $context['document'] ),
				'slot_prefix'               => self::slot_prefix( $model ),
				'required_any_slots'        => self::string_groups( $model['required_any_slots'] ?? array() ),
				'required_verified_groups'  => self::string_list( $model['required_verified_groups'] ?? array() ),
				'section_policy'            => self::safe_array_list( $model['section_policy'] ?? array() ),
				'safety'                    => isset( $model['safety'] ) && is_array( $model['safety'] ) ? $model['safety'] : array(),
			),
			'mapping'        => array(
				'mapped'      => $post instanceof WP_Post,
				'page_key'    => $mapping['page_key'],
				'resource'    => self::resource_summary( $post ),
				'resolution'  => $mapping['resolution'],
			),
			'slots'          => $slot_values,
			'policy'         => array(
				'contract_authority'        => 'active-theme-preset-page-models',
				'current_values_bounded'    => true,
				'raw_post_content_returned' => false,
				'layout_mutation'            => false,
			),
		);
	}

	/**
	 * @return array{scan:array<string,mixed>,preset:string,document:array<string,mixed>,models:array<string,mixed>}|WP_Error
	 */
	private static function context() {
		$scan = ThemeContractScanner::scan();
		if ( true !== ( $scan['applicable'] ?? false ) ) {
			return self::error(
				'seo_geo_manager_theme_models_unavailable',
				'The active Theme does not expose an applicable semantic preset contract.',
				409
			);
		}

		$preset = isset( $scan['preset'] ) && is_string( $scan['preset'] ) ? sanitize_key( $scan['preset'] ) : '';
		if ( '' === $preset ) {
			return self::error( 'seo_geo_manager_theme_preset_missing', 'The active Theme preset could not be resolved.', 409 );
		}

		$document = self::preset_document( $preset, 'page-models.json' );
		if ( ! is_array( $document ) ) {
			return self::error( 'seo_geo_manager_theme_models_document_missing', 'The active preset page-models contract is unavailable.', 409 );
		}

		$models = isset( $document['native_content_models'] ) && is_array( $document['native_content_models'] )
			? $document['native_content_models']
			: array();
		if ( array() === $models ) {
			return self::error( 'seo_geo_manager_theme_models_empty', 'The active preset exposes no native content models.', 409 );
		}

		return array(
			'scan'     => $scan,
			'preset'   => $preset,
			'document' => $document,
			'models'   => $models,
		);
	}

	/**
	 * @return array<string, mixed>|null
	 */
	private static function preset_document( string $preset, string $filename ): ?array {
		if ( function_exists( 'seo_geo_theme_preset_document' ) ) {
			$value = call_user_func( 'seo_geo_theme_preset_document', $preset, $filename );
			if ( is_array( $value ) ) {
				return $value;
			}
		}

		$path = trailingslashit( get_template_directory() ) . 'presets/' . sanitize_key( $preset ) . '/' . basename( $filename );
		if ( ! is_readable( $path ) || ! function_exists( 'wp_json_file_decode' ) ) {
			return null;
		}

		$value = wp_json_file_decode( $path, array( 'associative' => true ) );

		return is_array( $value ) ? $value : null;
	}

	/**
	 * @param array<string, mixed> $model Model definition.
	 * @param array<string, mixed> $scan Scanner output.
	 * @return array<string, mixed>
	 */
	private static function model_summary( string $model_id, array $model, array $scan ): array {
		$mapping  = self::mapped_page( $model_id, $scan );
		$resource = null;
		if ( 0 < $mapping['resource_id'] ) {
			$post = get_post( $mapping['resource_id'] );
			if ( $post instanceof WP_Post && 'page' === $post->post_type && current_user_can( 'edit_post', $post->ID ) ) {
				$resource = self::resource_summary( $post );
			}
		}

		$slots    = self::slot_definitions( $model );
		$required = 0;
		$verified = array();
		foreach ( $slots as $slot ) {
			if ( true === ( $slot['required'] ?? false ) ) {
				++$required;
			}
			$group = isset( $slot['verification_group'] ) && is_string( $slot['verification_group'] ) ? sanitize_key( $slot['verification_group'] ) : '';
			if ( '' !== $group ) {
				$verified[] = $group;
			}
		}

		return array(
			'model_id'              => $model_id,
			'schema_version'         => isset( $model['schema_version'] ) ? (int) $model['schema_version'] : 1,
			'page_key'               => isset( $model['page_key'] ) && is_string( $model['page_key'] ) ? sanitize_key( $model['page_key'] ) : $mapping['page_key'],
			'slot_count'             => count( $slots ),
			'required_slot_count'    => $required,
			'verification_groups'    => array_values( array_unique( $verified ) ),
			'mapped'                 => null !== $resource,
			'resource'               => $resource,
		);
	}

	/**
	 * @param array<string, mixed> $scan Scanner output.
	 * @return array{page_key:string,resource_id:int,resolution:array<string,mixed>}
	 */
	private static function mapped_page( string $model_id, array $scan ): array {
		$pages = isset( $scan['pages'] ) && is_array( $scan['pages'] ) ? $scan['pages'] : array();
		foreach ( $pages as $page ) {
			if ( ! is_array( $page ) ) {
				continue;
			}
			$model = isset( $page['model'] ) && is_array( $page['model'] ) ? $page['model'] : array();
			if ( $model_id !== ( $model['model_id'] ?? '' ) ) {
				continue;
			}

			$resource = isset( $page['resource'] ) && is_array( $page['resource'] ) ? $page['resource'] : array();

			return array(
				'page_key'    => isset( $page['key'] ) && is_string( $page['key'] ) ? sanitize_key( $page['key'] ) : '',
				'resource_id' => isset( $resource['id'] ) ? absint( $resource['id'] ) : 0,
				'resolution'  => isset( $page['resolution'] ) && is_array( $page['resolution'] ) ? $page['resolution'] : array(),
			);
		}

		return array(
			'page_key'    => '',
			'resource_id' => 0,
			'resolution'  => array(),
		);
	}

	/**
	 * @param array<string, mixed> $model Model definition.
	 * @return list<array<string, mixed>>
	 */
	private static function slot_definitions( array $model ): array {
		$result = array();
		$slots  = isset( $model['slots'] ) && is_array( $model['slots'] ) ? $model['slots'] : array();
		foreach ( $slots as $slot ) {
			if ( ! is_array( $slot ) || ! isset( $slot['id'] ) || ! is_string( $slot['id'] ) ) {
				continue;
			}
			$id = sanitize_key( $slot['id'] );
			if ( '' === $id ) {
				continue;
			}

			$result[] = array(
				'id'                    => $id,
				'type'                  => isset( $slot['type'] ) && is_string( $slot['type'] ) ? sanitize_key( $slot['type'] ) : 'text',
				'required'              => true === ( $slot['required'] ?? false ),
				'requires_verification' => true === ( $slot['requires_verification'] ?? false ),
				'verification_group'    => isset( $slot['verification_group'] ) && is_string( $slot['verification_group'] ) ? sanitize_key( $slot['verification_group'] ) : '',
			);
		}

		return $result;
	}

	/**
	 * @param array<string, mixed> $slot Slot definition.
	 * @param array<string, mixed> $model Model definition.
	 * @return array<string, mixed>
	 */
	private static function slot_state( array $slot, array $model, ?WP_Post $post ): array {
		$state = $slot;
		$state['writable'] = in_array( $slot['type'], array( 'text', 'link' ), true );
		if ( ! $post instanceof WP_Post ) {
			$state['marker_count'] = 0;
			$state['value']        = null;

			return $state;
		}

		$prefix  = self::slot_prefix( $model );
		$pattern = self::slot_pattern( $prefix . $slot['id'] );
		$count   = preg_match_all( $pattern, (string) $post->post_content, $matches, PREG_SET_ORDER );
		$state['marker_count'] = is_int( $count ) ? $count : 0;
		$state['value']        = null;

		if ( 1 !== $count || ! isset( $matches[0] ) || ! is_array( $matches[0] ) ) {
			return $state;
		}

		$match = $matches[0];
		$inner = isset( $match['inner'] ) && is_string( $match['inner'] ) ? $match['inner'] : '';
		if ( 'link' === $slot['type'] ) {
			$tag = isset( $match['tag'] ) && is_string( $match['tag'] ) ? strtolower( $match['tag'] ) : '';
			if ( 'a' !== $tag ) {
				return $state;
			}
			$open = isset( $match['open'] ) && is_string( $match['open'] ) ? $match['open'] : '';
			$state['value'] = array(
				'text' => self::clean_text( $inner ),
				'url'  => self::attribute_value( $open, 'href' ),
			);

			return $state;
		}

		if ( 'text' === $slot['type'] ) {
			$state['value'] = self::clean_text( $inner );
		}

		return $state;
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
			'id'          => (int) $post->ID,
			'status'      => (string) $post->post_status,
			'slug'        => (string) $post->post_name,
			'title'       => html_entity_decode( get_the_title( $post ), ENT_QUOTES | ENT_HTML5, get_bloginfo( 'charset' ) ),
			'permalink'   => is_string( $permalink ) ? $permalink : '',
			'fingerprint' => ContentFingerprint::for_post( $post ),
		);
	}

	/**
	 * @param array<string, mixed> $model Model definition.
	 * @param array<string, mixed> $document page-models document.
	 */
	private static function model_page_key( string $model_id, array $model, array $document ): string {
		if ( isset( $model['page_key'] ) && is_string( $model['page_key'] ) ) {
			return sanitize_key( $model['page_key'] );
		}

		$map = isset( $document['models_by_page'] ) && is_array( $document['models_by_page'] ) ? $document['models_by_page'] : array();
		foreach ( $map as $page_key => $mapped_model_id ) {
			if ( is_string( $page_key ) && is_string( $mapped_model_id ) && $model_id === sanitize_key( $mapped_model_id ) ) {
				return sanitize_key( $page_key );
			}
		}

		return '';
	}

	/**
	 * @param array<string, mixed> $model Model definition.
	 */
	private static function slot_prefix( array $model ): string {
		$prefix = isset( $model['slot_prefix'] ) && is_string( $model['slot_prefix'] ) ? trim( $model['slot_prefix'] ) : '';

		return '' !== $prefix ? $prefix : self::DEFAULT_SLOT_PREFIX;
	}

	private static function slot_pattern( string $marker ): string {
		$marker = preg_quote( $marker, '~' );

		return '~(?P<open><(?P<tag>[a-z][a-z0-9]*)\\b(?=[^>]*\\bclass\\s*=\\s*(?:"[^"]*\\b' . $marker . '\\b[^"]*"|\'[^\']*\\b' . $marker . '\\b[^\']*\'))[^>]*>)(?P<inner>.*?)(?P<close></(?P=tag)\\s*>)~is';
	}

	private static function attribute_value( string $opening_tag, string $attribute ): string {
		$attribute = preg_quote( $attribute, '~' );
		if ( 1 === preg_match( '~\\b' . $attribute . '\\s*=\\s*(["\'])(.*?)\\1~is', $opening_tag, $match ) && isset( $match[2] ) && is_string( $match[2] ) ) {
			return html_entity_decode( $match[2], ENT_QUOTES | ENT_HTML5, get_bloginfo( 'charset' ) );
		}

		return '';
	}

	private static function clean_text( string $value ): string {
		return html_entity_decode( wp_strip_all_tags( $value ), ENT_QUOTES | ENT_HTML5, get_bloginfo( 'charset' ) );
	}

	/**
	 * @param mixed $value Value.
	 * @return list<string>
	 */
	private static function string_list( $value ): array {
		if ( ! is_array( $value ) ) {
			return array();
		}

		$result = array();
		foreach ( $value as $item ) {
			if ( is_string( $item ) && '' !== sanitize_key( $item ) ) {
				$result[] = sanitize_key( $item );
			}
		}

		return array_values( array_unique( $result ) );
	}

	/**
	 * @param mixed $value Value.
	 * @return list<list<string>>
	 */
	private static function string_groups( $value ): array {
		if ( ! is_array( $value ) ) {
			return array();
		}

		$result = array();
		foreach ( $value as $group ) {
			$items = self::string_list( $group );
			if ( array() !== $items ) {
				$result[] = $items;
			}
		}

		return $result;
	}

	/**
	 * @param mixed $value Value.
	 * @return list<array<string, mixed>>
	 */
	private static function safe_array_list( $value ): array {
		if ( ! is_array( $value ) ) {
			return array();
		}

		return array_values( array_filter( $value, 'is_array' ) );
	}

	private static function error( string $code, string $message, int $status ): WP_Error {
		return new WP_Error( $code, $message, array( 'status' => $status ) );
	}
}
