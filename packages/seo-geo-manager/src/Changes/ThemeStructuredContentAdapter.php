<?php
/**
 * Bounded adapter for Theme-owned structured content slots.
 *
 * @package SeoGeoManager
 */

declare(strict_types=1);

namespace SeoGeo\Manager\Changes;

use SeoGeo\Manager\Intelligence\ThemeContractScanner;
use WP_Error;
use WP_Post;

final class ThemeStructuredContentAdapter {
	private const SCHEMA_VERSION = 1;
	private const SLOT_PREFIX    = 'seo-geo-content-slot--';

	/**
	 * Preview a structured-slot mutation while preserving Theme layout.
	 *
	 * @param array<string, mixed> $payload Request payload.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function preview( array $payload ) {
		$prepared = self::prepare( $payload, false );
		if ( is_wp_error( $prepared ) ) {
			return $prepared;
		}

		$engine = ChangeSetEngine::preview( $prepared['change_set'] );
		if ( is_wp_error( $engine ) ) {
			return $engine;
		}

		return array(
			'schema_version' => self::SCHEMA_VERSION,
			'mode'           => 'preview',
			'target'         => $engine['target'] ?? array(),
			'preset'         => $prepared['preset'],
			'page_key'       => $prepared['page_key'],
			'model_id'       => $prepared['model_id'],
			'slots'          => $prepared['slot_diff'],
			'has_changes'    => true === ( $engine['has_changes'] ?? false ),
			'policy'         => array(
				'layout_preserved'         => true,
				'arbitrary_html_blocked'   => true,
				'verified_groups'          => $prepared['verified_groups'],
				'published_target_blocked' => $engine['policy']['published_target_blocked'] ?? false,
				'allow_published_target'   => $engine['policy']['allow_published_target'] ?? false,
			),
		);
	}

	/**
	 * Apply a structured-slot mutation through the existing M2 change engine.
	 *
	 * @param array<string, mixed> $payload Request payload.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function apply( array $payload ) {
		$prepared = self::prepare( $payload, true );
		if ( is_wp_error( $prepared ) ) {
			return $prepared;
		}

		$result = ChangeSetEngine::apply( $prepared['change_set'] );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$result['structured_model'] = array(
			'preset'           => $prepared['preset'],
			'page_key'         => $prepared['page_key'],
			'model_id'         => $prepared['model_id'],
			'slots'            => $prepared['slot_diff'],
			'verified_groups'  => $prepared['verified_groups'],
			'layout_preserved' => true,
		);

		return $result;
	}

	/**
	 * @param array<string, mixed> $payload Request payload.
	 * @return array<string, mixed>|WP_Error
	 */
	private static function prepare( array $payload, bool $require_idempotency ) {
		$schema_version = isset( $payload['schema_version'] ) ? (int) $payload['schema_version'] : self::SCHEMA_VERSION;
		if ( self::SCHEMA_VERSION !== $schema_version ) {
			return new WP_Error(
				'seo_geo_manager_structured_schema_unsupported',
				'Unsupported structured-content schema version.',
				array( 'status' => 400 )
			);
		}

		$target      = isset( $payload['target'] ) && is_array( $payload['target'] ) ? $payload['target'] : array();
		$target_id   = isset( $target['id'] ) ? absint( $target['id'] ) : 0;
		$fingerprint = isset( $target['expected_fingerprint'] ) && is_string( $target['expected_fingerprint'] ) ? trim( $target['expected_fingerprint'] ) : '';
		$model_id    = isset( $payload['model_id'] ) && is_string( $payload['model_id'] ) ? sanitize_key( $payload['model_id'] ) : '';
		$raw_slots   = isset( $payload['slots'] ) && is_array( $payload['slots'] ) ? $payload['slots'] : array();

		if ( 1 > $target_id || '' === $fingerprint || '' === $model_id || array() === $raw_slots ) {
			return new WP_Error(
				'seo_geo_manager_structured_request_invalid',
				'Target ID, expected fingerprint, model_id and at least one slot are required.',
				array( 'status' => 400 )
			);
		}

		$post = get_post( $target_id );
		if ( ! $post instanceof WP_Post || 'page' !== $post->post_type ) {
			return new WP_Error(
				'seo_geo_manager_structured_target_missing',
				'Theme structured-content targets must be existing WordPress pages.',
				array( 'status' => 404 )
			);
		}

		if ( ! current_user_can( 'edit_post', $post->ID ) ) {
			return new WP_Error(
				'seo_geo_manager_forbidden',
				'You cannot modify this structured page.',
				array( 'status' => 403 )
			);
		}

		$contract = self::target_contract( $post->ID, $model_id );
		if ( is_wp_error( $contract ) ) {
			return $contract;
		}

		$model = self::model_document( $contract['preset'], $model_id );
		if ( is_wp_error( $model ) ) {
			return $model;
		}

		$definitions = self::slot_definitions( $model );
		$verified     = self::verified_groups( $payload );
		$normalized   = self::normalize_slots( $raw_slots, $definitions, $verified );
		if ( is_wp_error( $normalized ) ) {
			return $normalized;
		}

		$mutation = self::mutate_content( (string) $post->post_content, $normalized, $definitions );
		if ( is_wp_error( $mutation ) ) {
			return $mutation;
		}

		$change_set = array(
			'schema_version' => self::SCHEMA_VERSION,
			'target'         => array(
				'id'                   => $target_id,
				'expected_fingerprint' => $fingerprint,
			),
			'changes'        => array(
				'content' => $mutation['content'],
			),
		);

		if ( true === ( $payload['allow_published_target'] ?? false ) ) {
			$change_set['allow_published_target'] = true;
		}

		if ( $require_idempotency ) {
			$idempotency_key = isset( $payload['idempotency_key'] ) && is_string( $payload['idempotency_key'] ) ? trim( $payload['idempotency_key'] ) : '';
			if ( '' === $idempotency_key || 128 < strlen( $idempotency_key ) ) {
				return new WP_Error(
					'seo_geo_manager_idempotency_required',
					'Apply requests require an idempotency key of at most 128 characters.',
					array( 'status' => 400 )
				);
			}
			$change_set['idempotency_key'] = $idempotency_key;
		}

		return array(
			'change_set'      => $change_set,
			'preset'          => $contract['preset'],
			'page_key'        => $contract['page_key'],
			'model_id'        => $model_id,
			'slot_diff'       => $mutation['slot_diff'],
			'verified_groups' => $verified,
		);
	}

	/**
	 * Resolve a model only when Theme intelligence maps it to the exact target.
	 *
	 * @return array{preset:string,page_key:string}|WP_Error
	 */
	private static function target_contract( int $target_id, string $model_id ) {
		$scan = ThemeContractScanner::scan();
		if ( true !== ( $scan['applicable'] ?? false ) ) {
			return new WP_Error(
				'seo_geo_manager_structured_theme_unavailable',
				'The active Theme does not expose an applicable structured preset contract.',
				array( 'status' => 409 )
			);
		}

		$preset = isset( $scan['preset'] ) && is_string( $scan['preset'] ) ? sanitize_key( $scan['preset'] ) : '';
		$pages  = isset( $scan['pages'] ) && is_array( $scan['pages'] ) ? $scan['pages'] : array();
		foreach ( $pages as $page ) {
			if ( ! is_array( $page ) ) {
				continue;
			}
			$resource = isset( $page['resource'] ) && is_array( $page['resource'] ) ? $page['resource'] : array();
			$model    = isset( $page['model'] ) && is_array( $page['model'] ) ? $page['model'] : array();
			if ( (int) ( $resource['id'] ?? 0 ) !== $target_id || ( $model['model_id'] ?? '' ) !== $model_id ) {
				continue;
			}

			$page_key = isset( $page['key'] ) && is_string( $page['key'] ) ? sanitize_key( $page['key'] ) : '';
			if ( '' !== $preset && '' !== $page_key ) {
				return array(
					'preset'   => $preset,
					'page_key' => $page_key,
				);
			}
		}

		return new WP_Error(
			'seo_geo_manager_structured_model_target_mismatch',
			'The requested Theme model is not mapped to the target page.',
			array( 'status' => 409 )
		);
	}

	/**
	 * @return array<string, mixed>|WP_Error
	 */
	private static function model_document( string $preset, string $model_id ) {
		$document = null;
		if ( is_callable( 'seo_geo_theme_preset_document' ) ) {
			$value = call_user_func( 'seo_geo_theme_preset_document', $preset, 'page-models.json' );
			if ( is_array( $value ) ) {
				$document = $value;
			}
		}

		if ( ! is_array( $document ) ) {
			$path = trailingslashit( get_template_directory() ) . 'presets/' . sanitize_key( $preset ) . '/page-models.json';
			if ( is_readable( $path ) && function_exists( 'wp_json_file_decode' ) ) {
				$value    = wp_json_file_decode( $path, array( 'associative' => true ) );
				$document = is_array( $value ) ? $value : null;
			}
		}

		$models = is_array( $document ) && isset( $document['native_content_models'] ) && is_array( $document['native_content_models'] )
			? $document['native_content_models']
			: array();
		$model  = $models[ $model_id ] ?? null;
		if ( ! is_array( $model ) ) {
			return new WP_Error(
				'seo_geo_manager_structured_model_unavailable',
				'The requested Theme structured model is unavailable.',
				array( 'status' => 409 )
			);
		}

		return $model;
	}

	/**
	 * @param array<string, mixed> $model Model contract.
	 * @return array<string, array<string, mixed>>
	 */
	private static function slot_definitions( array $model ): array {
		$result = array();
		$slots  = isset( $model['slots'] ) && is_array( $model['slots'] ) ? $model['slots'] : array();
		foreach ( $slots as $slot ) {
			if ( ! is_array( $slot ) || ! isset( $slot['id'] ) || ! is_string( $slot['id'] ) ) {
				continue;
			}
			$id = sanitize_key( $slot['id'] );
			if ( '' !== $id ) {
				$result[ $id ] = $slot;
			}
		}

		return $result;
	}

	/**
	 * @param array<string, mixed> $payload Request payload.
	 * @return list<string>
	 */
	private static function verified_groups( array $payload ): array {
		$groups = isset( $payload['verified_groups'] ) && is_array( $payload['verified_groups'] ) ? $payload['verified_groups'] : array();
		$result = array();
		foreach ( $groups as $group ) {
			if ( is_string( $group ) ) {
				$group = sanitize_key( $group );
				if ( '' !== $group ) {
					$result[] = $group;
				}
			}
		}

		return array_values( array_unique( $result ) );
	}

	/**
	 * @param array<string, mixed>                $raw_slots Raw slot changes.
	 * @param array<string, array<string, mixed>> $definitions Model slot definitions.
	 * @param list<string>                        $verified_groups Explicit verification attestations.
	 * @return array<string, array<string, mixed>>|WP_Error
	 */
	private static function normalize_slots( array $raw_slots, array $definitions, array $verified_groups ) {
		$result = array();
		foreach ( $raw_slots as $slot_id => $value ) {
			if ( ! is_string( $slot_id ) ) {
				return new WP_Error( 'seo_geo_manager_structured_slot_invalid', 'Structured slot IDs must be strings.', array( 'status' => 400 ) );
			}

			$slot_id    = sanitize_key( $slot_id );
			$definition = $definitions[ $slot_id ] ?? null;
			if ( ! is_array( $definition ) ) {
				return new WP_Error(
					'seo_geo_manager_structured_slot_unsupported',
					'The request contains a slot not defined by the active Theme model.',
					array( 'status' => 400, 'slot_id' => $slot_id )
				);
			}

			$verification_group = isset( $definition['verification_group'] ) && is_string( $definition['verification_group'] )
				? sanitize_key( $definition['verification_group'] )
				: '';
			if ( true === ( $definition['requires_verification'] ?? false ) && ( '' === $verification_group || ! in_array( $verification_group, $verified_groups, true ) ) ) {
				return new WP_Error(
					'seo_geo_manager_structured_verification_required',
					'This structured slot requires an explicit verified-group attestation.',
					array(
						'status'             => 409,
						'slot_id'            => $slot_id,
						'verification_group' => $verification_group,
					)
				);
			}

			$type = isset( $definition['type'] ) && is_string( $definition['type'] ) ? sanitize_key( $definition['type'] ) : 'text';
			if ( 'text' === $type ) {
				if ( ! is_string( $value ) || '' === trim( $value ) ) {
					return new WP_Error(
						'seo_geo_manager_structured_text_invalid',
						'Structured text slots require a non-empty string.',
						array( 'status' => 400, 'slot_id' => $slot_id )
					);
				}
				$result[ $slot_id ] = array(
					'type'  => 'text',
					'value' => sanitize_text_field( $value ),
				);
				continue;
			}

			if ( 'link' === $type ) {
				if ( ! is_array( $value ) ) {
					return new WP_Error(
						'seo_geo_manager_structured_link_invalid',
						'Structured link slots require text and url fields.',
						array( 'status' => 400, 'slot_id' => $slot_id )
					);
				}
				$text = isset( $value['text'] ) && is_string( $value['text'] ) ? sanitize_text_field( $value['text'] ) : '';
				$url  = isset( $value['url'] ) && is_string( $value['url'] ) ? self::normalize_url( $value['url'] ) : '';
				if ( '' === $text || '' === $url ) {
					return new WP_Error(
						'seo_geo_manager_structured_link_invalid',
						'Structured link slots require non-empty safe text and url fields.',
						array( 'status' => 400, 'slot_id' => $slot_id )
					);
				}
				$result[ $slot_id ] = array(
					'type' => 'link',
					'text' => $text,
					'url'  => $url,
				);
				continue;
			}

			return new WP_Error(
				'seo_geo_manager_structured_slot_type_unsupported',
				'The requested structured slot type is not writable in this Manager version.',
				array( 'status' => 400, 'slot_id' => $slot_id, 'type' => $type )
			);
		}

		return $result;
	}

	private static function normalize_url( string $value ): string {
		$value = trim( $value );
		if ( '' === $value || preg_match( '/^(?:javascript|data):/i', $value ) ) {
			return '';
		}

		if ( str_starts_with( $value, '/' ) || str_starts_with( $value, '#' ) ) {
			return esc_url_raw( $value );
		}

		return esc_url_raw( $value, array( 'http', 'https', 'mailto', 'tel' ) );
	}

	/**
	 * @param array<string, array<string, mixed>> $slots Normalized slot changes.
	 * @param array<string, array<string, mixed>> $definitions Model definitions.
	 * @return array{content:string,slot_diff:array<string, mixed>}|WP_Error
	 */
	private static function mutate_content( string $content, array $slots, array $definitions ) {
		$slot_diff = array();
		foreach ( $slots as $slot_id => $change ) {
			$marker  = self::SLOT_PREFIX . $slot_id;
			$pattern = self::slot_pattern( $marker );
			$count   = preg_match_all( $pattern, $content, $matches, PREG_SET_ORDER );
			if ( 1 !== $count || ! isset( $matches[0] ) || ! is_array( $matches[0] ) ) {
				return new WP_Error(
					'seo_geo_manager_structured_slot_cardinality',
					'The Theme slot marker must exist exactly once in stored page content.',
					array(
						'status'  => 409,
						'slot_id' => $slot_id,
						'found'   => is_int( $count ) ? $count : 0,
					)
				);
			}

			$match        = $matches[0];
			$type         = isset( $definitions[ $slot_id ]['type'] ) && is_string( $definitions[ $slot_id ]['type'] )
				? sanitize_key( $definitions[ $slot_id ]['type'] )
				: 'text';
			$before_inner = isset( $match['inner'] ) && is_string( $match['inner'] ) ? $match['inner'] : '';
			$open         = isset( $match['open'] ) && is_string( $match['open'] ) ? $match['open'] : '';
			$close        = isset( $match['close'] ) && is_string( $match['close'] ) ? $match['close'] : '';
			$tag          = isset( $match['tag'] ) && is_string( $match['tag'] ) ? strtolower( $match['tag'] ) : '';

			if ( 'text' === $type ) {
				$after_inner = esc_html( (string) $change['value'] );
				$replacement = $open . $after_inner . $close;
				$slot_diff[ $slot_id ] = array(
					'type' => 'text',
					'from' => html_entity_decode( wp_strip_all_tags( $before_inner ), ENT_QUOTES | ENT_HTML5, get_bloginfo( 'charset' ) ),
					'to'   => (string) $change['value'],
				);
			} elseif ( 'link' === $type ) {
				if ( 'a' !== $tag ) {
					return new WP_Error(
						'seo_geo_manager_structured_link_marker_invalid',
						'Structured link slots must mark an anchor element directly.',
						array( 'status' => 409, 'slot_id' => $slot_id )
					);
				}
				$before_url  = self::attribute_value( $open, 'href' );
				$after_open  = self::replace_attribute( $open, 'href', (string) $change['url'] );
				$after_inner = esc_html( (string) $change['text'] );
				$replacement = $after_open . $after_inner . $close;
				$slot_diff[ $slot_id ] = array(
					'type' => 'link',
					'from' => array(
						'text' => html_entity_decode( wp_strip_all_tags( $before_inner ), ENT_QUOTES | ENT_HTML5, get_bloginfo( 'charset' ) ),
						'url'  => $before_url,
					),
					'to'   => array(
						'text' => (string) $change['text'],
						'url'  => (string) $change['url'],
					),
				);
			} else {
				return new WP_Error( 'seo_geo_manager_structured_slot_type_unsupported', 'Unsupported structured slot type.', array( 'status' => 400 ) );
			}

			$updated = preg_replace_callback(
				$pattern,
				static fn ( array $ignored ): string => $replacement,
				$content,
				1
			);
			if ( ! is_string( $updated ) ) {
				return new WP_Error( 'seo_geo_manager_structured_replace_failed', 'Could not safely replace the Theme slot.', array( 'status' => 500 ) );
			}
			$content = $updated;
		}

		return array(
			'content'   => $content,
			'slot_diff' => $slot_diff,
		);
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

	private static function replace_attribute( string $opening_tag, string $attribute, string $value ): string {
		$escaped   = esc_attr( $value );
		$attribute = sanitize_key( $attribute );
		$pattern   = '~\\b' . preg_quote( $attribute, '~' ) . '\\s*=\\s*(["\']).*?\\1~is';
		if ( 1 === preg_match( $pattern, $opening_tag ) ) {
			$updated = preg_replace( $pattern, $attribute . '="' . $escaped . '"', $opening_tag, 1 );

			return is_string( $updated ) ? $updated : $opening_tag;
		}

		return preg_replace( '/>$/', ' ' . $attribute . '="' . $escaped . '">', $opening_tag, 1 ) ?: $opening_tag;
	}
}
