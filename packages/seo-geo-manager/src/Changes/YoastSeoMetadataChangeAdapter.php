<?php
/**
 * Guarded SEO metadata changes through Yoast SEO's public WordPress Abilities.
 *
 * @package SeoGeoManager
 */

declare(strict_types=1);

namespace SeoGeo\Manager\Changes;

use SeoGeo\Manager\Intelligence\SeoAuthorityScanner;
use SeoGeo\Manager\Support\YoastSeoAbilityBridge;
use WP_Error;
use WP_Post;

/** Change the bounded Yoast SEO state without touching private provider metadata. */
final class YoastSeoMetadataChangeAdapter {
	private const ADAPTER = 'yoast-abilities';

	/**
	 * Inspect bounded Yoast state only when Yoast is the single writable authority.
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	public static function inspect( int $resource_id ) {
		$guard = self::authority_guard();
		if ( is_wp_error( $guard ) ) {
			return $guard;
		}
		$permission = self::permission_guard( $resource_id );
		if ( is_wp_error( $permission ) ) {
			return $permission;
		}

		$post = get_post( $resource_id );
		if ( ! $post instanceof WP_Post ) {
			return new WP_Error(
				'seo_geo_manager_yoast_resource_not_found',
				'The requested Yoast SEO resource was not found.',
				array( 'status' => 404 )
			);
		}

		$provider = YoastSeoAbilityBridge::inspect( $resource_id );
		if ( is_wp_error( $provider ) ) {
			return $provider;
		}
		$values = self::provider_values( $provider );
		if ( is_wp_error( $values ) ) {
			return $values;
		}

		return array(
			'schema_version' => 1,
			'adapter'        => self::ADAPTER,
			'provider'       => 'yoast',
			'resource'       => self::resource_summary( $post ),
			'values'         => $values,
			'fingerprint'    => self::fingerprint( $resource_id, $values ),
			'read_only'      => self::read_only_context( $provider ),
			'authority'      => $guard['authority'],
			'outputs'        => $guard['outputs'],
		);
	}

	/**
	 * @param array<string, mixed> $payload Request payload.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function preview( array $payload ) {
		$state = self::prepare( $payload, false );
		if ( is_wp_error( $state ) ) {
			return $state;
		}

		return array(
			'schema_version' => 1,
			'mode'           => 'preview',
			'adapter'        => self::ADAPTER,
			'provider'       => 'yoast',
			'authority'      => $state['authority'],
			'resource'       => self::resource_summary( $state['post'] ),
			'change'         => array(
				'before'             => $state['before_values'],
				'after'              => $state['after_values'],
				'before_fingerprint' => $state['before_fingerprint'],
				'after_fingerprint'  => $state['after_fingerprint'],
			),
			'has_changes'    => true,
			'policy'         => array(
				'provider_public_abilities_only' => true,
				'private_provider_meta_writes'   => false,
				'writable_signals'               => array( 'canonical', 'robots', 'schema' ),
				'read_only_signals'              => array( 'title-meta', 'open-graph', 'hreflang', 'sitemap' ),
				'published_approval_required'    => 'publish' === $state['post']->post_status,
				'stale_safe_rollback'            => true,
			),
		);
	}

	/**
	 * @param array<string, mixed> $payload Request payload.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function apply( array $payload ) {
		$key = isset( $payload['idempotency_key'] ) && is_string( $payload['idempotency_key'] )
			? trim( $payload['idempotency_key'] )
			: '';
		if ( '' === $key || 128 < strlen( $key ) ) {
			return new WP_Error(
				'seo_geo_manager_yoast_seo_idempotency_required',
				'Yoast SEO Apply requires an idempotency key of at most 128 characters.',
				array( 'status' => 400 )
			);
		}

		$hash   = self::payload_hash( $payload );
		$lookup = OperationStore::lookup( $key, $hash );
		if ( is_wp_error( $lookup ) ) {
			return $lookup;
		}
		if ( true === $lookup['existing'] ) {
			return self::replay( $lookup['operation_id'] );
		}

		$state = self::prepare( $payload, true );
		if ( is_wp_error( $state ) ) {
			return $state;
		}

		$operation_id = wp_generate_uuid4();
		$reservation  = OperationStore::reserve( $key, $hash, $operation_id );
		if ( is_wp_error( $reservation ) ) {
			return $reservation;
		}
		if ( true === $reservation['existing'] ) {
			return self::replay( $reservation['operation_id'] );
		}

		$result = YoastSeoAbilityBridge::update( $state['post']->ID, $state['after_values'] );
		if ( is_wp_error( $result ) ) {
			self::save_failed( $operation_id, $state, $hash, $key, $result );

			return $result;
		}
		$after_values = self::provider_values( $result );
		if ( is_wp_error( $after_values ) ) {
			YoastSeoAbilityBridge::update( $state['post']->ID, $state['before_values'] );

			return $after_values;
		}
		$after_fingerprint = self::fingerprint( $state['post']->ID, $after_values );
		if ( ! hash_equals( $state['after_fingerprint'], $after_fingerprint ) || $after_values !== $state['after_values'] ) {
			YoastSeoAbilityBridge::update( $state['post']->ID, $state['before_values'] );

			return new WP_Error(
				'seo_geo_manager_yoast_seo_verify_failed',
				'Yoast SEO verification failed; Manager attempted compensation through the provider ability.',
				array( 'status' => 409 )
			);
		}

		$operation = array(
			'operation_id'       => $operation_id,
			'operation_type'     => 'yoast-seo-metadata-change',
			'adapter'            => self::ADAPTER,
			'provider'           => 'yoast',
			'schema_version'     => 1,
			'status'             => 'applied',
			'target_id'          => (int) $state['post']->ID,
			'target_type'        => (string) $state['post']->post_type,
			'resource_id'        => (int) $state['post']->ID,
			'before_values'      => $state['before_values'],
			'after_values'       => $state['after_values'],
			'before_fingerprint' => $state['before_fingerprint'],
			'after_fingerprint'  => $after_fingerprint,
			'idempotency_key'    => $key,
			'payload_hash'       => $hash,
			'changes'            => array( 'yoast_seo_metadata' => true ),
			'created_at_gmt'     => gmdate( 'c' ),
			'idempotent_replay'  => false,
		);
		if ( ! OperationStore::save( $operation_id, $operation ) ) {
			YoastSeoAbilityBridge::update( $state['post']->ID, $state['before_values'] );

			return new WP_Error(
				'seo_geo_manager_yoast_seo_operation_store_failed',
				'Yoast SEO changed but operation history could not be persisted; Manager attempted compensation.',
				array( 'status' => 500 )
			);
		}

		return $operation;
	}

	/**
	 * @return array<string, mixed>|WP_Error
	 */
	public static function rollback( string $operation_id ) {
		$operation = OperationStore::get( $operation_id );
		if ( ! is_array( $operation ) || self::ADAPTER !== ( $operation['adapter'] ?? '' ) ) {
			return new WP_Error(
				'seo_geo_manager_yoast_seo_operation_not_found',
				'Yoast SEO metadata operation not found.',
				array( 'status' => 404 )
			);
		}
		if ( 'rolled-back' === ( $operation['status'] ?? '' ) ) {
			return $operation;
		}
		if ( 'applied' !== ( $operation['status'] ?? '' ) ) {
			return new WP_Error(
				'seo_geo_manager_yoast_seo_rollback_unavailable',
				'Only applied Yoast SEO metadata operations can be rolled back.',
				array( 'status' => 409 )
			);
		}

		$guard = self::authority_guard();
		if ( is_wp_error( $guard ) ) {
			return $guard;
		}

		$resource_id = isset( $operation['resource_id'] ) ? absint( $operation['resource_id'] ) : 0;
		$permission  = self::permission_guard( $resource_id );
		if ( is_wp_error( $permission ) ) {
			return $permission;
		}

		$current = YoastSeoAbilityBridge::inspect( $resource_id );
		if ( is_wp_error( $current ) ) {
			return $current;
		}
		$current_values = self::provider_values( $current );
		if ( is_wp_error( $current_values ) ) {
			return $current_values;
		}
		$current_fp = self::fingerprint( $resource_id, $current_values );
		$after_fp   = isset( $operation['after_fingerprint'] ) && is_string( $operation['after_fingerprint'] ) ? $operation['after_fingerprint'] : '';
		if ( '' === $after_fp || ! hash_equals( $after_fp, $current_fp ) ) {
			return new WP_Error(
				'seo_geo_manager_yoast_seo_rollback_stale',
				'Yoast SEO metadata changed after Apply; rollback is blocked to avoid overwriting a newer edit.',
				array( 'status' => 409 )
			);
		}

		$before_values = self::normalize_operation_values( $operation['before_values'] ?? null );
		$after_values  = self::normalize_operation_values( $operation['after_values'] ?? null );
		$before_fp     = isset( $operation['before_fingerprint'] ) && is_string( $operation['before_fingerprint'] ) ? $operation['before_fingerprint'] : '';
		if ( is_wp_error( $before_values ) || is_wp_error( $after_values ) || '' === $before_fp ) {
			return new WP_Error(
				'seo_geo_manager_yoast_seo_rollback_state_invalid',
				'Yoast SEO rollback state is incomplete.',
				array( 'status' => 409 )
			);
		}

		$restored = YoastSeoAbilityBridge::update( $resource_id, $before_values );
		if ( is_wp_error( $restored ) ) {
			return $restored;
		}
		$restored_values = self::provider_values( $restored );
		if ( is_wp_error( $restored_values ) ) {
			YoastSeoAbilityBridge::update( $resource_id, $after_values );

			return $restored_values;
		}
		$restored_fp = self::fingerprint( $resource_id, $restored_values );
		if ( ! hash_equals( $before_fp, $restored_fp ) || $before_values !== $restored_values ) {
			YoastSeoAbilityBridge::update( $resource_id, $after_values );

			return new WP_Error(
				'seo_geo_manager_yoast_seo_rollback_verify_failed',
				'Yoast SEO rollback verification failed; Manager attempted to restore the applied provider state.',
				array( 'status' => 409 )
			);
		}

		$operation['status']               = 'rolled-back';
		$operation['rolled_back_at_gmt']   = gmdate( 'c' );
		$operation['rollback_fingerprint'] = $restored_fp;
		OperationStore::save( $operation_id, $operation );

		return $operation;
	}

	/**
	 * @param array<string, mixed> $payload Request payload.
	 * @return array<string, mixed>|WP_Error
	 */
	private static function prepare( array $payload, bool $for_apply ) {
		$guard = self::authority_guard();
		if ( is_wp_error( $guard ) ) {
			return $guard;
		}

		$resource_id = isset( $payload['resource_id'] ) ? absint( $payload['resource_id'] ) : 0;
		$expected    = isset( $payload['expected_fingerprint'] ) && is_string( $payload['expected_fingerprint'] )
			? trim( $payload['expected_fingerprint'] )
			: '';
		if ( 1 > $resource_id || '' === $expected ) {
			return new WP_Error(
				'seo_geo_manager_yoast_seo_request_invalid',
				'Yoast SEO requests require resource_id, values and expected_fingerprint.',
				array( 'status' => 400 )
			);
		}
		$permission = self::permission_guard( $resource_id );
		if ( is_wp_error( $permission ) ) {
			return $permission;
		}

		$post = get_post( $resource_id );
		if ( ! $post instanceof WP_Post ) {
			return new WP_Error(
				'seo_geo_manager_yoast_resource_not_found',
				'The requested Yoast SEO resource was not found.',
				array( 'status' => 404 )
			);
		}

		$inspection = YoastSeoAbilityBridge::inspect( $resource_id );
		if ( is_wp_error( $inspection ) ) {
			return $inspection;
		}
		$before_values = self::provider_values( $inspection );
		if ( is_wp_error( $before_values ) ) {
			return $before_values;
		}
		$before_fp = self::fingerprint( $resource_id, $before_values );
		if ( ! hash_equals( $expected, $before_fp ) ) {
			return new WP_Error(
				'seo_geo_manager_yoast_seo_fingerprint_mismatch',
				'Yoast SEO metadata changed after inspection.',
				array( 'status' => 409 )
			);
		}

		$after_values = self::normalize_values( $payload['values'] ?? null );
		if ( is_wp_error( $after_values ) ) {
			return $after_values;
		}
		$ability_validation = YoastSeoAbilityBridge::validate_update( $resource_id, $after_values );
		if ( is_wp_error( $ability_validation ) ) {
			return $ability_validation;
		}
		if ( $before_values === $after_values ) {
			return new WP_Error(
				'seo_geo_manager_yoast_seo_no_changes',
				'The requested Yoast SEO state is already active.',
				array( 'status' => 400 )
			);
		}
		if ( $for_apply && 'publish' === $post->post_status && true !== ( $payload['allow_published_target'] ?? false ) ) {
			return new WP_Error(
				'seo_geo_manager_yoast_seo_public_approval_required',
				'Published Yoast SEO writes require explicit approval after Preview.',
				array( 'status' => 409 )
			);
		}

		return array(
			'post'               => $post,
			'authority'          => $guard['authority'],
			'before_values'      => $before_values,
			'after_values'       => $after_values,
			'before_fingerprint' => $before_fp,
			'after_fingerprint'  => self::fingerprint( $resource_id, $after_values ),
		);
	}

	/**
	 * @return array{authority:array<string,mixed>,outputs:array<string,mixed>}|WP_Error
	 */
	private static function authority_guard() {
		if ( ! YoastSeoAbilityBridge::available() ) {
			return new WP_Error(
				'seo_geo_manager_yoast_abilities_unavailable',
				'The accepted Yoast SEO WordPress Abilities are unavailable.',
				array( 'status' => 409 )
			);
		}

		$scan      = SeoAuthorityScanner::scan();
		$authority = isset( $scan['authority'] ) && is_array( $scan['authority'] ) ? $scan['authority'] : array();
		$outputs   = isset( $scan['outputs'] ) && is_array( $scan['outputs'] ) ? $scan['outputs'] : array();
		$required  = array( 'canonical', 'robots', 'schema' );
		$writable  = true;
		foreach ( $required as $signal ) {
			$writable = $writable && true === ( $outputs[ $signal ]['writable'] ?? false );
		}

		if (
			true !== ( $scan['authority_resolved'] ?? false ) ||
			true === ( $scan['conflict'] ?? false ) ||
			'yoast' !== ( $authority['id'] ?? '' ) ||
			self::ADAPTER !== ( $authority['adapter'] ?? '' ) ||
			! $writable
		) {
			return new WP_Error(
				'seo_geo_manager_yoast_seo_authority_blocked',
				'Yoast SEO writes require one verified Yoast authority backed by its public WordPress Abilities.',
				array( 'status' => 409 )
			);
		}

		return array(
			'authority' => $authority,
			'outputs'   => $outputs,
		);
	}

	/** @return true|WP_Error */
	private static function permission_guard( int $resource_id ) {
		if ( ! current_user_can( 'edit_post', $resource_id ) || ! current_user_can( 'wpseo_edit_advanced_metadata' ) ) {
			return new WP_Error(
				'seo_geo_manager_yoast_seo_forbidden',
				'You cannot operate advanced Yoast SEO metadata for this resource.',
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * @param mixed $raw Raw desired values.
	 * @return array<string, mixed>|WP_Error
	 */
	private static function normalize_values( $raw ) {
		if ( ! is_array( $raw ) ) {
			return new WP_Error( 'seo_geo_manager_yoast_seo_values_invalid', 'Yoast SEO values must be an object.', array( 'status' => 400 ) );
		}
		$expected = array( 'canonical', 'nofollow', 'noindex', 'schema_article_type', 'schema_page_type' );
		$keys     = array_keys( $raw );
		sort( $keys );
		if ( $expected !== $keys ) {
			return new WP_Error(
				'seo_geo_manager_yoast_seo_values_invalid',
				'Yoast SEO values require exactly canonical, noindex, nofollow, schema_page_type and schema_article_type.',
				array( 'status' => 400 )
			);
		}

		$canonical = self::normalize_canonical( $raw['canonical'] );
		if ( is_wp_error( $canonical ) ) {
			return $canonical;
		}
		$noindex = self::normalize_nullable_bool( $raw['noindex'], 'noindex' );
		if ( is_wp_error( $noindex ) ) {
			return $noindex;
		}
		if ( ! is_bool( $raw['nofollow'] ) ) {
			return new WP_Error( 'seo_geo_manager_yoast_seo_nofollow_invalid', 'Yoast nofollow must be boolean.', array( 'status' => 400 ) );
		}
		$page_type = self::normalize_schema_type( $raw['schema_page_type'], 'schema_page_type' );
		if ( is_wp_error( $page_type ) ) {
			return $page_type;
		}
		$article_type = self::normalize_schema_type( $raw['schema_article_type'], 'schema_article_type' );
		if ( is_wp_error( $article_type ) ) {
			return $article_type;
		}

		return array(
			'canonical'           => $canonical,
			'noindex'             => $noindex,
			'nofollow'            => $raw['nofollow'],
			'schema_page_type'    => $page_type,
			'schema_article_type' => $article_type,
		);
	}

	/**
	 * @param mixed $raw Stored operation values.
	 * @return array<string, mixed>|WP_Error
	 */
	private static function normalize_operation_values( $raw ) {
		return self::normalize_values( $raw );
	}

	/**
	 * @param array<string, mixed> $provider Provider response.
	 * @return array<string, mixed>|WP_Error
	 */
	private static function provider_values( array $provider ) {
		return self::normalize_values(
			array(
				'canonical'           => $provider['canonical'] ?? null,
				'noindex'             => $provider['noindex'] ?? null,
				'nofollow'            => $provider['nofollow'] ?? false,
				'schema_page_type'    => $provider['schema_page_type'] ?? null,
				'schema_article_type' => $provider['schema_article_type'] ?? null,
			)
		);
	}

	/**
	 * @param mixed $value Raw canonical value.
	 * @return string|null|WP_Error
	 */
	private static function normalize_canonical( $value ) {
		if ( null === $value || '' === $value ) {
			return null;
		}
		if ( ! is_string( $value ) ) {
			return new WP_Error( 'seo_geo_manager_yoast_seo_canonical_invalid', 'Yoast canonical must be an absolute HTTP(S) URL or null.', array( 'status' => 400 ) );
		}
		$normalized = esc_url_raw( trim( $value ), array( 'http', 'https' ) );
		$scheme     = strtolower( (string) wp_parse_url( $normalized, PHP_URL_SCHEME ) );
		$host       = (string) wp_parse_url( $normalized, PHP_URL_HOST );
		if ( '' === $normalized || ! in_array( $scheme, array( 'http', 'https' ), true ) || '' === $host ) {
			return new WP_Error( 'seo_geo_manager_yoast_seo_canonical_invalid', 'Yoast canonical must be an absolute HTTP(S) URL or null.', array( 'status' => 400 ) );
		}

		return $normalized;
	}

	/**
	 * @param mixed  $value Raw boolean value.
	 * @param string $field Field name.
	 * @return bool|null|WP_Error
	 */
	private static function normalize_nullable_bool( $value, string $field ) {
		if ( null === $value ) {
			return null;
		}
		if ( ! is_bool( $value ) ) {
			return new WP_Error( 'seo_geo_manager_yoast_seo_' . $field . '_invalid', 'Yoast ' . $field . ' must be boolean or null.', array( 'status' => 400 ) );
		}

		return $value;
	}

	/**
	 * @param mixed  $value Raw Schema type.
	 * @param string $field Field name.
	 * @return string|null|WP_Error
	 */
	private static function normalize_schema_type( $value, string $field ) {
		if ( null === $value || '' === $value ) {
			return null;
		}
		if ( ! is_string( $value ) ) {
			return new WP_Error( 'seo_geo_manager_yoast_seo_' . $field . '_invalid', 'Yoast Schema type must be a string or null.', array( 'status' => 400 ) );
		}
		$value = sanitize_text_field( $value );
		if ( '' === $value || 128 < strlen( $value ) ) {
			return new WP_Error( 'seo_geo_manager_yoast_seo_' . $field . '_invalid', 'Yoast Schema type is invalid.', array( 'status' => 400 ) );
		}

		return $value;
	}

	/**
	 * @param array<string, mixed> $provider Provider response.
	 * @return array<string, mixed>
	 */
	private static function read_only_context( array $provider ): array {
		return array(
			'title_meta' => array(
				'seo_title'                 => self::nullable_string( $provider['seo_title'] ?? null ),
				'seo_title_rendered'        => self::nullable_string( $provider['seo_title_rendered'] ?? null ),
				'meta_description'          => self::nullable_string( $provider['meta_description'] ?? null ),
				'meta_description_rendered' => self::nullable_string( $provider['meta_description_rendered'] ?? null ),
			),
			'open_graph' => array(
				'title'                => self::nullable_string( $provider['open_graph_title'] ?? null ),
				'title_rendered'       => self::nullable_string( $provider['open_graph_title_rendered'] ?? null ),
				'description'          => self::nullable_string( $provider['open_graph_description'] ?? null ),
				'description_rendered' => self::nullable_string( $provider['open_graph_description_rendered'] ?? null ),
			),
		);
	}

	/**
	 * @param mixed $value Provider value.
	 */
	private static function nullable_string( $value ): ?string {
		return is_string( $value ) ? $value : null;
	}

	/**
	 * @param array<string, mixed> $values Bounded provider values.
	 */
	private static function fingerprint( int $resource_id, array $values ): string {
		return hash(
			'sha256',
			self::ADAPTER . '|' . $resource_id . '|' . (string) wp_json_encode( $values )
		);
	}

	/** @return array<string, mixed> */
	private static function resource_summary( WP_Post $post ): array {
		return array(
			'id'        => (int) $post->ID,
			'post_type' => (string) $post->post_type,
			'status'    => (string) $post->post_status,
			'title'     => html_entity_decode( get_the_title( $post ), ENT_QUOTES | ENT_HTML5, self::charset() ),
		);
	}

	/**
	 * @param array<string, mixed> $payload Request payload.
	 */
	private static function payload_hash( array $payload ): string {
		return hash(
			'sha256',
			(string) wp_json_encode(
				array(
					'resource_id'            => isset( $payload['resource_id'] ) ? absint( $payload['resource_id'] ) : 0,
					'values'                 => $payload['values'] ?? null,
					'expected_fingerprint'   => isset( $payload['expected_fingerprint'] ) ? (string) $payload['expected_fingerprint'] : '',
					'allow_published_target' => true === ( $payload['allow_published_target'] ?? false ),
				)
			)
		);
	}

	/** @return array<string, mixed>|WP_Error */
	private static function replay( string $operation_id ) {
		$operation = OperationStore::get( $operation_id );
		if ( ! is_array( $operation ) || self::ADAPTER !== ( $operation['adapter'] ?? '' ) || 'failed' === ( $operation['status'] ?? '' ) ) {
			return new WP_Error(
				'seo_geo_manager_yoast_seo_idempotency_unavailable',
				'Idempotency key is unavailable for replay.',
				array( 'status' => 409 )
			);
		}
		$operation['idempotent_replay'] = true;

		return $operation;
	}

	/**
	 * @param array<string, mixed> $state Prepared state.
	 */
	private static function save_failed( string $operation_id, array $state, string $payload_hash, string $key, WP_Error $error ): void {
		$post = $state['post'] ?? null;
		if ( ! $post instanceof WP_Post ) {
			return;
		}

		OperationStore::save(
			$operation_id,
			array(
				'operation_id'       => $operation_id,
				'operation_type'     => 'yoast-seo-metadata-change',
				'adapter'            => self::ADAPTER,
				'provider'           => 'yoast',
				'schema_version'     => 1,
				'status'             => 'failed',
				'target_id'          => (int) $post->ID,
				'target_type'        => (string) $post->post_type,
				'resource_id'        => (int) $post->ID,
				'before_values'      => $state['before_values'] ?? array(),
				'after_values'       => $state['after_values'] ?? array(),
				'before_fingerprint' => $state['before_fingerprint'] ?? '',
				'idempotency_key'    => $key,
				'payload_hash'       => $payload_hash,
				'error_code'         => $error->get_error_code(),
				'created_at_gmt'     => gmdate( 'c' ),
			)
		);
	}

	private static function charset(): string {
		$charset = get_bloginfo( 'charset' );

		return is_string( $charset ) && '' !== $charset ? $charset : 'UTF-8';
	}
}
