<?php
/**
 * Guarded href-only mutation adapter for one existing contextual link.
 *
 * @package SeoGeoManager
 */

declare(strict_types=1);

namespace SeoGeo\Manager\Changes;

use SeoGeo\Manager\Support\ContentFingerprint;
use WP_Error;
use WP_Post;

final class ContextualLinkChangeAdapter {
	private const SCHEMA_VERSION = 1;
	private const ADAPTER        = 'contextual-link';

	/**
	 * Preview one href-only contextual-link correction.
	 *
	 * @param array<string, mixed> $payload Request payload.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function preview( array $payload ) {
		$prepared = self::prepare( $payload, false );
		if ( is_wp_error( $prepared ) ) {
			return $prepared;
		}

		return array(
			'schema_version' => self::SCHEMA_VERSION,
			'mode'           => 'preview',
			'adapter'        => self::ADAPTER,
			'source'         => self::source_summary( $prepared['source'] ),
			'edge'           => array(
				'edge_id'     => $prepared['edge_id'],
				'ordinal'     => $prepared['ordinal'],
				'anchor_text' => $prepared['anchor_text'],
				'before_href' => $prepared['before_href'],
				'after_href'  => $prepared['after_href'],
			),
			'target'         => self::target_summary( $prepared['target'], $prepared['after_href'] ),
			'has_changes'    => $prepared['before_href'] !== $prepared['after_href'],
			'policy'         => array(
				'href_only'                => true,
				'target_resource_id_only'  => true,
				'published_target_required' => true,
				'public_source_blocked'    => 'publish' === $prepared['source']->post_status && true !== ( $payload['allow_published_target'] ?? false ),
				'allow_published_target'   => true === ( $payload['allow_published_target'] ?? false ),
			),
		);
	}

	/**
	 * Apply one href-only contextual-link correction.
	 *
	 * @param array<string, mixed> $payload Request payload.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function apply( array $payload ) {
		$idempotency_key = isset( $payload['idempotency_key'] ) && is_string( $payload['idempotency_key'] ) ? trim( $payload['idempotency_key'] ) : '';
		if ( '' === $idempotency_key || 128 < strlen( $idempotency_key ) ) {
			return new WP_Error(
				'seo_geo_manager_contextual_link_idempotency_required',
				'Contextual-link apply requires an idempotency key of at most 128 characters.',
				array( 'status' => 400 )
			);
		}

		$payload_hash = self::payload_hash( $payload );
		$lookup       = OperationStore::lookup( $idempotency_key, $payload_hash );
		if ( is_wp_error( $lookup ) ) {
			return $lookup;
		}
		if ( true === $lookup['existing'] ) {
			return self::replay_operation( $lookup['operation_id'] );
		}

		$prepared = self::prepare( $payload, true );
		if ( is_wp_error( $prepared ) ) {
			return $prepared;
		}

		$operation_id = wp_generate_uuid4();
		$reservation  = OperationStore::reserve( $idempotency_key, $payload_hash, $operation_id );
		if ( is_wp_error( $reservation ) ) {
			return $reservation;
		}
		if ( true === $reservation['existing'] ) {
			return self::replay_operation( $reservation['operation_id'] );
		}

		$revision = wp_save_post_revision( $prepared['source']->ID );
		$result   = wp_update_post(
			array(
				'ID'           => $prepared['source']->ID,
				'post_content' => $prepared['after_content'],
			),
			true
		);
		if ( is_wp_error( $result ) ) {
			self::save_failed_operation( $operation_id, $prepared );
			return $result;
		}

		$after = get_post( $prepared['source']->ID );
		if ( ! $after instanceof WP_Post ) {
			self::compensate( $prepared['source']->ID, $prepared['before_content'] );
			self::save_failed_operation( $operation_id, $prepared );
			return new WP_Error( 'seo_geo_manager_contextual_link_apply_missing', 'Source content could not be reloaded after link apply; the adapter attempted compensation.', array( 'status' => 500 ) );
		}

		$after_fingerprint = ContentFingerprint::for_post( $after );
		if ( $after->post_content !== $prepared['after_content'] || hash_equals( $prepared['before_fingerprint'], $after_fingerprint ) ) {
			self::compensate( $prepared['source']->ID, $prepared['before_content'] );
			self::save_failed_operation( $operation_id, $prepared );
			return new WP_Error(
				'seo_geo_manager_contextual_link_verify_failed',
				'Contextual-link apply verification failed and the adapter attempted compensation.',
				array( 'status' => 409 )
			);
		}

		$operation = array(
			'operation_id'       => $operation_id,
			'operation_type'     => 'contextual-link-change',
			'adapter'            => self::ADAPTER,
			'status'             => 'applied',
			'target_id'          => (int) $prepared['source']->ID,
			'target_type'        => (string) $prepared['source']->post_type,
			'source_id'          => (int) $prepared['source']->ID,
			'edge_id'            => $prepared['edge_id'],
			'ordinal'            => $prepared['ordinal'],
			'target_resource_id' => (int) $prepared['target']->ID,
			'before_fingerprint' => $prepared['before_fingerprint'],
			'after_fingerprint'  => $after_fingerprint,
			'before_href'        => $prepared['before_href'],
			'after_href'         => $prepared['after_href'],
			'before_content'      => $prepared['before_content'],
			'after_content'       => $prepared['after_content'],
			'changes'             => array( 'contextual_href' => true ),
			'revision_id'         => is_numeric( $revision ) ? (int) $revision : 0,
			'created_at_gmt'      => gmdate( 'c' ),
			'idempotent_replay'   => false,
		);

		if ( ! OperationStore::save( $operation_id, $operation ) ) {
			self::compensate( $prepared['source']->ID, $prepared['before_content'] );
			return new WP_Error(
				'seo_geo_manager_contextual_link_operation_store_failed',
				'Contextual link changed but the operation record could not be persisted; the adapter attempted compensation.',
				array( 'status' => 500 )
			);
		}

		return $operation;
	}

	/**
	 * Roll back one applied contextual-link change when the source is unchanged.
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	public static function rollback( string $operation_id ) {
		$operation = OperationStore::get( $operation_id );
		if ( ! is_array( $operation ) || self::ADAPTER !== ( $operation['adapter'] ?? '' ) ) {
			return new WP_Error( 'seo_geo_manager_contextual_link_operation_not_found', 'Contextual-link operation not found.', array( 'status' => 404 ) );
		}
		if ( 'rolled-back' === ( $operation['status'] ?? '' ) ) {
			return $operation;
		}
		if ( 'applied' !== ( $operation['status'] ?? '' ) ) {
			return new WP_Error( 'seo_geo_manager_contextual_link_rollback_unavailable', 'Only applied contextual-link operations can be rolled back.', array( 'status' => 409 ) );
		}

		$source_id = isset( $operation['source_id'] ) ? absint( $operation['source_id'] ) : 0;
		$source    = get_post( $source_id );
		if ( ! $source instanceof WP_Post ) {
			return new WP_Error( 'seo_geo_manager_contextual_link_source_missing', 'Contextual-link rollback source no longer exists.', array( 'status' => 404 ) );
		}
		if ( ! current_user_can( 'edit_post', $source->ID ) ) {
			return new WP_Error( 'seo_geo_manager_forbidden', 'You cannot roll back this contextual link.', array( 'status' => 403 ) );
		}

		$after_fingerprint = isset( $operation['after_fingerprint'] ) && is_string( $operation['after_fingerprint'] ) ? $operation['after_fingerprint'] : '';
		$current           = ContentFingerprint::for_post( $source );
		if ( '' === $after_fingerprint || ! hash_equals( $after_fingerprint, $current ) ) {
			return new WP_Error(
				'seo_geo_manager_contextual_link_rollback_stale',
				'Source content changed after Apply; rollback is blocked to avoid overwriting newer edits.',
				array( 'status' => 409 )
			);
		}

		$before_content = isset( $operation['before_content'] ) && is_string( $operation['before_content'] ) ? $operation['before_content'] : '';
		$after_content  = isset( $operation['after_content'] ) && is_string( $operation['after_content'] ) ? $operation['after_content'] : '';
		if ( $source->post_content !== $after_content ) {
			return new WP_Error( 'seo_geo_manager_contextual_link_rollback_state_mismatch', 'Stored contextual-link state no longer matches the source.', array( 'status' => 409 ) );
		}

		wp_save_post_revision( $source->ID );
		$result = wp_update_post( array( 'ID' => $source->ID, 'post_content' => $before_content ), true );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$restored = get_post( $source->ID );
		$before   = isset( $operation['before_fingerprint'] ) && is_string( $operation['before_fingerprint'] ) ? $operation['before_fingerprint'] : '';
		if ( ! $restored instanceof WP_Post || '' === $before || ! hash_equals( $before, ContentFingerprint::for_post( $restored ) ) ) {
			self::compensate( $source->ID, $after_content );
			return new WP_Error(
				'seo_geo_manager_contextual_link_rollback_verify_failed',
				'Contextual-link rollback verification failed; the adapter attempted to restore the applied state.',
				array( 'status' => 409 )
			);
		}

		$operation['status']               = 'rolled-back';
		$operation['rolled_back_at_gmt']   = gmdate( 'c' );
		$operation['rollback_fingerprint'] = ContentFingerprint::for_post( $restored );
		OperationStore::save( $operation_id, $operation );

		return $operation;
	}

	/**
	 * @param array<string, mixed> $payload Request payload.
	 * @return array<string, mixed>|WP_Error
	 */
	private static function prepare( array $payload, bool $for_apply ) {
		$schema_version = isset( $payload['schema_version'] ) ? (int) $payload['schema_version'] : self::SCHEMA_VERSION;
		$source_id      = isset( $payload['source_id'] ) ? absint( $payload['source_id'] ) : 0;
		$edge_id        = isset( $payload['edge_id'] ) && is_string( $payload['edge_id'] ) ? strtolower( trim( $payload['edge_id'] ) ) : '';
		$expected       = isset( $payload['expected_fingerprint'] ) && is_string( $payload['expected_fingerprint'] ) ? trim( $payload['expected_fingerprint'] ) : '';
		$target_id      = isset( $payload['target_resource_id'] ) ? absint( $payload['target_resource_id'] ) : 0;

		if ( self::SCHEMA_VERSION !== $schema_version || 1 > $source_id || 64 !== strlen( $edge_id ) || ! ctype_xdigit( $edge_id ) || '' === $expected || 1 > $target_id ) {
			return new WP_Error(
				'seo_geo_manager_contextual_link_request_invalid',
				'Contextual-link requests require source_id, edge_id, expected_fingerprint and target_resource_id.',
				array( 'status' => 400 )
			);
		}

		$source = get_post( $source_id );
		if ( ! $source instanceof WP_Post || ! in_array( $source->post_type, array( 'page', 'post' ), true ) ) {
			return new WP_Error( 'seo_geo_manager_contextual_link_source_not_found', 'Contextual-link source page/post was not found.', array( 'status' => 404 ) );
		}
		if ( ! current_user_can( 'edit_post', $source->ID ) ) {
			return new WP_Error( 'seo_geo_manager_forbidden', 'You cannot modify this contextual-link source.', array( 'status' => 403 ) );
		}
		if ( $for_apply && 'publish' === $source->post_status && true !== ( $payload['allow_published_target'] ?? false ) ) {
			return new WP_Error(
				'seo_geo_manager_contextual_link_public_approval_required',
				'Published source writes require explicit approval after Preview.',
				array( 'status' => 409 )
			);
		}

		$current_fingerprint = ContentFingerprint::for_post( $source );
		if ( ! hash_equals( $expected, $current_fingerprint ) ) {
			return new WP_Error(
				'seo_geo_manager_contextual_link_fingerprint_mismatch',
				'Source content changed after link discovery; inspect the source again before applying.',
				array( 'status' => 409 )
			);
		}

		$target = get_post( $target_id );
		if ( ! $target instanceof WP_Post || ! in_array( $target->post_type, array( 'page', 'post' ), true ) ) {
			return new WP_Error( 'seo_geo_manager_contextual_link_target_not_found', 'Contextual-link target page/post was not found.', array( 'status' => 404 ) );
		}
		if ( 'publish' !== $target->post_status ) {
			return new WP_Error( 'seo_geo_manager_contextual_link_target_not_public', 'Contextual-link target must be currently published.', array( 'status' => 409 ) );
		}
		if ( $source->ID === $target->ID ) {
			return new WP_Error( 'seo_geo_manager_contextual_link_self_target', 'Contextual-link target cannot be the source resource itself.', array( 'status' => 409 ) );
		}

		$target_url = get_permalink( $target );
		if ( ! is_string( $target_url ) || '' === $target_url || ! self::inside_current_home( $target_url ) ) {
			return new WP_Error( 'seo_geo_manager_contextual_link_target_invalid', 'The published target does not resolve inside the current WordPress environment.', array( 'status' => 409 ) );
		}

		$edge = self::find_edge( $source, $edge_id );
		if ( is_wp_error( $edge ) ) {
			return $edge;
		}
		if ( $edge['before_href'] === $target_url ) {
			return new WP_Error( 'seo_geo_manager_contextual_link_no_changes', 'The contextual link already points to the selected published target.', array( 'status' => 400 ) );
		}

		$after_anchor = self::replace_anchor_href( $edge['anchor_html'], $target_url );
		if ( is_wp_error( $after_anchor ) ) {
			return $after_anchor;
		}

		$before_content = (string) $source->post_content;
		$after_content  = substr_replace( $before_content, $after_anchor, $edge['offset'], $edge['length'] );
		if ( $before_content === $after_content ) {
			return new WP_Error( 'seo_geo_manager_contextual_link_no_changes', 'The contextual-link mutation produced no content change.', array( 'status' => 400 ) );
		}

		return array(
			'source'             => $source,
			'target'             => $target,
			'edge_id'            => $edge_id,
			'ordinal'            => $edge['ordinal'],
			'anchor_text'        => $edge['anchor_text'],
			'before_href'        => $edge['before_href'],
			'after_href'         => $target_url,
			'before_fingerprint' => $current_fingerprint,
			'before_content'     => $before_content,
			'after_content'      => $after_content,
		);
	}

	/**
	 * @return array{ordinal:int,anchor_text:string,before_href:string,anchor_html:string,offset:int,length:int}|WP_Error
	 */
	private static function find_edge( WP_Post $source, string $edge_id ) {
		$html    = (string) $source->post_content;
		$matches = array();
		$count   = preg_match_all( '~<a\b([^>]*)>(.*?)</a\s*>~is', $html, $matches, PREG_SET_ORDER );
		if ( ! is_int( $count ) || 1 > $count ) {
			return new WP_Error( 'seo_geo_manager_contextual_link_edge_not_found', 'The requested contextual-link edge is no longer present.', array( 'status' => 409 ) );
		}

		$cursor  = 0;
		$ordinal = 0;
		$found   = array();
		foreach ( $matches as $match ) {
			$anchor_html = $match[0];
			$attrs       = $match[1];
			$inner       = $match[2];
			$offset      = strpos( $html, $anchor_html, $cursor );
			if ( false === $offset ) {
				return new WP_Error( 'seo_geo_manager_contextual_link_edge_position_invalid', 'Contextual-link position could not be resolved safely.', array( 'status' => 409 ) );
			}
			$cursor = $offset + strlen( $anchor_html );
			$href   = self::attribute_value( $attrs, 'href' );
			if ( self::skip_href( $href ) ) {
				continue;
			}

			$text    = self::clean_text( $inner );
			$current = hash( 'sha256', $source->ID . '|' . $ordinal . '|' . $href . '|' . $text );
			if ( hash_equals( $edge_id, $current ) ) {
				$found[] = array(
					'ordinal'     => $ordinal,
					'anchor_text' => $text,
					'before_href' => $href,
					'anchor_html' => $anchor_html,
					'offset'      => $offset,
					'length'      => strlen( $anchor_html ),
				);
			}
			++$ordinal;
		}

		if ( 1 !== count( $found ) ) {
			return new WP_Error(
				'seo_geo_manager_contextual_link_edge_mismatch',
				'Contextual-link edge identity no longer resolves uniquely; inspect the source again.',
				array( 'status' => 409, 'matches' => count( $found ) )
			);
		}

		return $found[0];
	}

	/** @return string|WP_Error */
	private static function replace_anchor_href( string $anchor_html, string $target_url ) {
		$count = preg_match_all( '~\bhref\s*=\s*(["\'])(.*?)\1~is', $anchor_html );
		if ( 1 !== $count ) {
			return new WP_Error( 'seo_geo_manager_contextual_link_href_ambiguous', 'The selected anchor must contain exactly one href attribute.', array( 'status' => 409 ) );
		}

		$replaced = preg_replace_callback(
			'~\bhref\s*=\s*(["\'])(.*?)\1~is',
			static function ( array $match ) use ( $target_url ): string {
				$quote = isset( $match[1] ) && is_string( $match[1] ) ? $match[1] : '"';
				return 'href=' . $quote . esc_attr( $target_url ) . $quote;
			},
			$anchor_html,
			1
		);

		return is_string( $replaced ) && $replaced !== $anchor_html
			? $replaced
			: new WP_Error( 'seo_geo_manager_contextual_link_href_replace_failed', 'The selected anchor href could not be replaced safely.', array( 'status' => 409 ) );
	}

	private static function compensate( int $source_id, string $content ): void {
		wp_update_post( array( 'ID' => $source_id, 'post_content' => $content ) );
	}

	/** @param array<string, mixed> $prepared Prepared mutation. */
	private static function save_failed_operation( string $operation_id, array $prepared ): void {
		OperationStore::save(
			$operation_id,
			array(
				'operation_id'       => $operation_id,
				'operation_type'     => 'contextual-link-change',
				'adapter'            => self::ADAPTER,
				'status'             => 'failed',
				'target_id'          => (int) $prepared['source']->ID,
				'target_type'        => (string) $prepared['source']->post_type,
				'source_id'          => (int) $prepared['source']->ID,
				'edge_id'            => $prepared['edge_id'],
				'target_resource_id' => (int) $prepared['target']->ID,
				'before_fingerprint' => $prepared['before_fingerprint'],
				'created_at_gmt'     => gmdate( 'c' ),
			)
		);
	}

	/** @return array<string, mixed>|WP_Error */
	private static function replay_operation( string $operation_id ) {
		$operation = OperationStore::get( $operation_id );
		if ( ! is_array( $operation ) || self::ADAPTER !== ( $operation['adapter'] ?? '' ) ) {
			return new WP_Error( 'seo_geo_manager_contextual_link_idempotency_unavailable', 'Idempotency key points to an incompatible operation.', array( 'status' => 409 ) );
		}
		if ( 'failed' === ( $operation['status'] ?? '' ) ) {
			return new WP_Error( 'seo_geo_manager_contextual_link_previous_attempt_failed', 'This idempotency key belongs to a failed link attempt.', array( 'status' => 409 ) );
		}
		$operation['idempotent_replay'] = true;
		return $operation;
	}

	/** @param array<string, mixed> $payload Request payload. */
	private static function payload_hash( array $payload ): string {
		$material = array(
			'schema_version'          => isset( $payload['schema_version'] ) ? (int) $payload['schema_version'] : self::SCHEMA_VERSION,
			'source_id'               => isset( $payload['source_id'] ) ? absint( $payload['source_id'] ) : 0,
			'edge_id'                 => isset( $payload['edge_id'] ) && is_string( $payload['edge_id'] ) ? strtolower( trim( $payload['edge_id'] ) ) : '',
			'expected_fingerprint'    => isset( $payload['expected_fingerprint'] ) && is_string( $payload['expected_fingerprint'] ) ? trim( $payload['expected_fingerprint'] ) : '',
			'target_resource_id'      => isset( $payload['target_resource_id'] ) ? absint( $payload['target_resource_id'] ) : 0,
			'allow_published_target'  => true === ( $payload['allow_published_target'] ?? false ),
		);
		return hash( 'sha256', (string) wp_json_encode( $material ) );
	}

	/** @return array<string, mixed> */
	private static function source_summary( WP_Post $source ): array {
		$permalink = get_permalink( $source );
		return array(
			'id'          => (int) $source->ID,
			'type'        => (string) $source->post_type,
			'status'      => (string) $source->post_status,
			'permalink'   => is_string( $permalink ) ? $permalink : '',
			'fingerprint' => ContentFingerprint::for_post( $source ),
		);
	}

	/** @return array<string, mixed> */
	private static function target_summary( WP_Post $target, string $permalink ): array {
		return array(
			'id'        => (int) $target->ID,
			'type'      => (string) $target->post_type,
			'status'    => (string) $target->post_status,
			'permalink' => $permalink,
		);
	}

	private static function inside_current_home( string $url ): bool {
		$target = wp_parse_url( $url );
		$home   = wp_parse_url( home_url( '/' ) );
		if ( ! is_array( $target ) || ! is_array( $home ) ) {
			return false;
		}
		if ( strtolower( (string) ( $target['host'] ?? '' ) ) !== strtolower( (string) ( $home['host'] ?? '' ) ) ) {
			return false;
		}
		$target_path = self::normalize_path( (string) ( $target['path'] ?? '/' ) );
		$home_path   = self::normalize_path( (string) ( $home['path'] ?? '/' ) );
		return '/' === $home_path || $target_path === $home_path || str_starts_with( $target_path, trailingslashit( $home_path ) );
	}

	private static function attribute_value( string $attributes, string $attribute ): string {
		$attribute = preg_quote( $attribute, '~' );
		if ( 1 === preg_match( '~\b' . $attribute . '\s*=\s*(["\'])(.*?)\1~is', $attributes, $match ) && isset( $match[2] ) && is_string( $match[2] ) ) {
			return trim( html_entity_decode( $match[2], ENT_QUOTES | ENT_HTML5, get_bloginfo( 'charset' ) ) );
		}
		return '';
	}

	private static function clean_text( string $value ): string {
		$text = html_entity_decode( wp_strip_all_tags( $value ), ENT_QUOTES | ENT_HTML5, get_bloginfo( 'charset' ) );
		$text = preg_replace( '/\s+/u', ' ', $text );
		return is_string( $text ) ? trim( $text ) : '';
	}

	private static function skip_href( string $href ): bool {
		if ( '' === $href || str_starts_with( $href, '#' ) ) {
			return true;
		}
		$lower = strtolower( $href );
		return str_starts_with( $lower, 'javascript:' ) || str_starts_with( $lower, 'data:' );
	}

	private static function normalize_path( string $path ): string {
		$path = '/' . ltrim( $path, '/' );
		return '/' === $path ? '/' : trailingslashit( $path );
	}
}
