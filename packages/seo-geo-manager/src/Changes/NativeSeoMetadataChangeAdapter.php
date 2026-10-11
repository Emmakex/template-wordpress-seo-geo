<?php
/**
 * Guarded native SEO metadata changes for the SEO/GEO Theme authority.
 *
 * @package SeoGeoManager
 */

declare(strict_types=1);

namespace SeoGeo\Manager\Changes;

use SeoGeo\Core\Seo\IndexabilityResolver;
use SeoGeo\Core\Seo\NativeSeoMetadataWriter;
use SeoGeo\Manager\Intelligence\SeoAuthorityScanner;
use WP_Error;
use WP_Post;

/** Change the bounded native SEO override state through the Core authority. */
final class NativeSeoMetadataChangeAdapter {
	private const ADAPTER                = 'seo-geo-theme-native';
	private const MAX_TITLE_LENGTH       = 320;
	private const MAX_DESCRIPTION_LENGTH = 1024;

	/**
	 * Inspect the native SEO state only when one writable Theme authority exists.
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	public static function inspect( int $resource_id ) {
		$guard = self::authority_guard();
		if ( is_wp_error( $guard ) ) {
			return $guard;
		}
		if ( ! current_user_can( 'edit_post', $resource_id ) ) {
			return new WP_Error(
				'seo_geo_manager_native_seo_forbidden',
				'You cannot inspect SEO metadata for this resource.',
				array( 'status' => 403 )
			);
		}

		$inspection = ( new NativeSeoMetadataWriter() )->inspect( $resource_id );
		if ( is_wp_error( $inspection ) ) {
			return $inspection;
		}
		$inspection['authority'] = $guard['authority'];
		$inspection['outputs']   = $guard['outputs'];

		return $inspection;
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
				'native_core_authority_only' => true,
				'provider_meta_writes'       => false,
				'writable_signals'           => array( 'title-meta', 'canonical', 'robots' ),
				'read_only_signals'          => array( 'open-graph', 'schema', 'hreflang', 'sitemap' ),
				'published_approval_required' => 'publish' === $state['post']->post_status,
				'stale_safe_rollback'         => true,
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
				'seo_geo_manager_native_seo_idempotency_required',
				'Native SEO Apply requires an idempotency key of at most 128 characters.',
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

		$writer = new NativeSeoMetadataWriter();
		$result = $writer->replace( $state['post']->ID, $state['after_values'] );
		if ( is_wp_error( $result ) ) {
			self::save_failed( $operation_id, $state, $hash, $key, $result );

			return $result;
		}

		$after_fingerprint = isset( $result['fingerprint'] ) && is_string( $result['fingerprint'] ) ? $result['fingerprint'] : '';
		$after_values      = isset( $result['values'] ) && is_array( $result['values'] ) ? $result['values'] : array();
		if ( ! hash_equals( $state['after_fingerprint'], $after_fingerprint ) || $after_values !== $state['after_values'] ) {
			$writer->replace( $state['post']->ID, $state['before_values'] );

			return new WP_Error(
				'seo_geo_manager_native_seo_verify_failed',
				'Native SEO metadata verification failed; Manager attempted compensation.',
				array( 'status' => 409 )
			);
		}

		$operation = array(
			'operation_id'       => $operation_id,
			'operation_type'     => 'native-seo-metadata-change',
			'adapter'            => self::ADAPTER,
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
			'changes'            => array( 'native_seo_metadata' => true ),
			'created_at_gmt'     => gmdate( 'c' ),
			'idempotent_replay'  => false,
		);
		if ( ! OperationStore::save( $operation_id, $operation ) ) {
			$writer->replace( $state['post']->ID, $state['before_values'] );

			return new WP_Error(
				'seo_geo_manager_native_seo_operation_store_failed',
				'Native SEO metadata changed but operation history could not be persisted; Manager attempted compensation.',
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
				'seo_geo_manager_native_seo_operation_not_found',
				'Native SEO metadata operation not found.',
				array( 'status' => 404 )
			);
		}
		if ( 'rolled-back' === ( $operation['status'] ?? '' ) ) {
			return $operation;
		}
		if ( 'applied' !== ( $operation['status'] ?? '' ) ) {
			return new WP_Error(
				'seo_geo_manager_native_seo_rollback_unavailable',
				'Only applied native SEO metadata operations can be rolled back.',
				array( 'status' => 409 )
			);
		}

		$guard = self::authority_guard();
		if ( is_wp_error( $guard ) ) {
			return $guard;
		}

		$resource_id = isset( $operation['resource_id'] ) ? absint( $operation['resource_id'] ) : 0;
		if ( ! current_user_can( 'edit_post', $resource_id ) ) {
			return new WP_Error(
				'seo_geo_manager_native_seo_forbidden',
				'You cannot roll back SEO metadata for this resource.',
				array( 'status' => 403 )
			);
		}

		$writer  = new NativeSeoMetadataWriter();
		$current = $writer->inspect( $resource_id );
		if ( is_wp_error( $current ) ) {
			return $current;
		}
		$current_fp = isset( $current['fingerprint'] ) && is_string( $current['fingerprint'] ) ? $current['fingerprint'] : '';
		$after_fp   = isset( $operation['after_fingerprint'] ) && is_string( $operation['after_fingerprint'] ) ? $operation['after_fingerprint'] : '';
		if ( '' === $after_fp || ! hash_equals( $after_fp, $current_fp ) ) {
			return new WP_Error(
				'seo_geo_manager_native_seo_rollback_stale',
				'Native SEO metadata changed after Apply; rollback is blocked to avoid overwriting a newer edit.',
				array( 'status' => 409 )
			);
		}

		$before_values = self::normalize_operation_values( $operation['before_values'] ?? null );
		$after_values  = self::normalize_operation_values( $operation['after_values'] ?? null );
		$before_fp     = isset( $operation['before_fingerprint'] ) && is_string( $operation['before_fingerprint'] ) ? $operation['before_fingerprint'] : '';
		if ( is_wp_error( $before_values ) || is_wp_error( $after_values ) || '' === $before_fp ) {
			return new WP_Error(
				'seo_geo_manager_native_seo_rollback_state_invalid',
				'Native SEO rollback state is incomplete.',
				array( 'status' => 409 )
			);
		}

		$restored = $writer->replace( $resource_id, $before_values );
		if ( is_wp_error( $restored ) ) {
			return $restored;
		}
		$restored_fp = isset( $restored['fingerprint'] ) && is_string( $restored['fingerprint'] ) ? $restored['fingerprint'] : '';
		if ( ! hash_equals( $before_fp, $restored_fp ) ) {
			$writer->replace( $resource_id, $after_values );

			return new WP_Error(
				'seo_geo_manager_native_seo_rollback_verify_failed',
				'Native SEO rollback verification failed; Manager attempted to restore the applied state.',
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
	 * @return array{post:WP_Post,authority:array<string,mixed>,before_values:array<string,string|null>,after_values:array<string,string|null>,before_fingerprint:string,after_fingerprint:string}|WP_Error
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
				'seo_geo_manager_native_seo_request_invalid',
				'Native SEO requests require resource_id, values and expected_fingerprint.',
				array( 'status' => 400 )
			);
		}
		if ( ! current_user_can( 'edit_post', $resource_id ) ) {
			return new WP_Error(
				'seo_geo_manager_native_seo_forbidden',
				'You cannot change SEO metadata for this resource.',
				array( 'status' => 403 )
			);
		}

		$post = get_post( $resource_id );
		if ( ! $post instanceof WP_Post ) {
			return new WP_Error(
				'seo_geo_manager_native_seo_resource_not_found',
				'The requested SEO resource was not found.',
				array( 'status' => 404 )
			);
		}

		$writer     = new NativeSeoMetadataWriter();
		$inspection = $writer->inspect( $resource_id );
		if ( is_wp_error( $inspection ) ) {
			return $inspection;
		}
		$before_values = self::normalize_operation_values( $inspection['values'] ?? null );
		$before_fp     = isset( $inspection['fingerprint'] ) && is_string( $inspection['fingerprint'] ) ? $inspection['fingerprint'] : '';
		if ( is_wp_error( $before_values ) || '' === $before_fp || ! hash_equals( $expected, $before_fp ) ) {
			return new WP_Error(
				'seo_geo_manager_native_seo_fingerprint_mismatch',
				'Native SEO metadata changed after inspection.',
				array( 'status' => 409 )
			);
		}

		$after_values = self::normalize_values( $payload['values'] ?? null );
		if ( is_wp_error( $after_values ) ) {
			return $after_values;
		}
		if ( $before_values === $after_values ) {
			return new WP_Error(
				'seo_geo_manager_native_seo_no_changes',
				'The requested native SEO metadata is already active.',
				array( 'status' => 400 )
			);
		}
		if ( $for_apply && 'publish' === $post->post_status && true !== ( $payload['allow_published_target'] ?? false ) ) {
			return new WP_Error(
				'seo_geo_manager_native_seo_public_approval_required',
				'Published SEO metadata writes require explicit approval after Preview.',
				array( 'status' => 409 )
			);
		}

		return array(
			'post'               => $post,
			'authority'          => $guard['authority'],
			'before_values'      => $before_values,
			'after_values'       => $after_values,
			'before_fingerprint' => $before_fp,
			'after_fingerprint'  => NativeSeoMetadataWriter::fingerprint( $resource_id, $after_values ),
		);
	}

	/**
	 * @return array{authority:array<string,mixed>,outputs:array<string,mixed>}|WP_Error
	 */
	private static function authority_guard() {
		if ( ! class_exists( NativeSeoMetadataWriter::class ) ) {
			return new WP_Error(
				'seo_geo_manager_native_seo_writer_unavailable',
				'The SEO/GEO Core native metadata writer is unavailable.',
				array( 'status' => 409 )
			);
		}

		$scan      = SeoAuthorityScanner::scan();
		$authority = isset( $scan['authority'] ) && is_array( $scan['authority'] ) ? $scan['authority'] : array();
		$outputs   = isset( $scan['outputs'] ) && is_array( $scan['outputs'] ) ? $scan['outputs'] : array();
		$required  = array( 'title-meta', 'canonical', 'robots' );
		$writable  = true;
		foreach ( $required as $signal ) {
			$writable = $writable && true === ( $outputs[ $signal ]['writable'] ?? false );
		}

		if (
			true !== ( $scan['authority_resolved'] ?? false ) ||
			true === ( $scan['conflict'] ?? false ) ||
			'seo-geo-theme' !== ( $authority['id'] ?? '' ) ||
			! $writable
		) {
			return new WP_Error(
				'seo_geo_manager_native_seo_authority_blocked',
				'Native SEO writes require the verified SEO/GEO Theme authority with no competing SEO provider.',
				array( 'status' => 409 )
			);
		}

		return array(
			'authority' => $authority,
			'outputs'   => $outputs,
		);
	}

	/**
	 * Normalize a complete desired native SEO state without persisting it.
	 *
	 * @param mixed $raw Raw values payload.
	 * @return array<string, string|null>|WP_Error
	 */
	private static function normalize_values( $raw ) {
		if ( ! is_array( $raw ) ) {
			return new WP_Error( 'seo_geo_manager_native_seo_values_invalid', 'Native SEO values must be an object.', array( 'status' => 400 ) );
		}
		$expected = array( 'canonical', 'description', 'indexability', 'title' );
		$keys     = array_keys( $raw );
		sort( $keys );
		if ( $expected !== $keys ) {
			return new WP_Error(
				'seo_geo_manager_native_seo_values_invalid',
				'Native SEO values require exactly title, description, canonical and indexability.',
				array( 'status' => 400 )
			);
		}

		$title = self::normalize_text( $raw['title'], self::MAX_TITLE_LENGTH, 'title' );
		if ( is_wp_error( $title ) ) {
			return $title;
		}
		$description = self::normalize_text( $raw['description'], self::MAX_DESCRIPTION_LENGTH, 'description' );
		if ( is_wp_error( $description ) ) {
			return $description;
		}
		$canonical = self::normalize_canonical( $raw['canonical'] );
		if ( is_wp_error( $canonical ) ) {
			return $canonical;
		}
		$indexability = self::normalize_indexability( $raw['indexability'] );
		if ( is_wp_error( $indexability ) ) {
			return $indexability;
		}

		return array(
			'title'        => $title,
			'description'  => $description,
			'canonical'    => $canonical,
			'indexability' => $indexability,
		);
	}

	/**
	 * @param mixed $raw Stored operation values.
	 * @return array<string, string|null>|WP_Error
	 */
	private static function normalize_operation_values( $raw ) {
		return self::normalize_values( $raw );
	}

	/**
	 * @param mixed  $value     Raw text value.
	 * @param int    $max_bytes Maximum bytes.
	 * @param string $field     Field name.
	 * @return string|null|WP_Error
	 */
	private static function normalize_text( $value, int $max_bytes, string $field ) {
		if ( null === $value || '' === $value ) {
			return null;
		}
		if ( ! is_string( $value ) ) {
			return new WP_Error( 'seo_geo_manager_native_seo_' . $field . '_invalid', 'Native SEO text values must be strings or null.', array( 'status' => 400 ) );
		}
		$value = sanitize_text_field( $value );
		if ( '' === $value ) {
			return null;
		}
		if ( $max_bytes < strlen( $value ) ) {
			return new WP_Error( 'seo_geo_manager_native_seo_' . $field . '_too_long', 'Native SEO text value exceeds the supported length bound.', array( 'status' => 400 ) );
		}

		return $value;
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
			return new WP_Error( 'seo_geo_manager_native_seo_canonical_invalid', 'Canonical must be an absolute HTTP(S) URL or null.', array( 'status' => 400 ) );
		}
		$normalized = esc_url_raw( trim( $value ), array( 'http', 'https' ) );
		$scheme     = is_string( $normalized ) ? strtolower( (string) wp_parse_url( $normalized, PHP_URL_SCHEME ) ) : '';
		$host       = is_string( $normalized ) ? (string) wp_parse_url( $normalized, PHP_URL_HOST ) : '';
		if ( '' === $normalized || ! in_array( $scheme, array( 'http', 'https' ), true ) || '' === $host ) {
			return new WP_Error( 'seo_geo_manager_native_seo_canonical_invalid', 'Canonical must be an absolute HTTP(S) URL or null.', array( 'status' => 400 ) );
		}

		return $normalized;
	}

	/**
	 * @param mixed $value Raw indexability value.
	 * @return string|null|WP_Error
	 */
	private static function normalize_indexability( $value ) {
		if ( null === $value || '' === $value ) {
			return null;
		}
		if ( ! is_string( $value ) ) {
			return new WP_Error( 'seo_geo_manager_native_seo_indexability_invalid', 'Indexability value is invalid.', array( 'status' => 400 ) );
		}
		$value   = sanitize_key( $value );
		$allowed = array(
			IndexabilityResolver::INDEXABLE,
			IndexabilityResolver::NOINDEX_FOLLOW,
			IndexabilityResolver::NOINDEX_NOFOLLOW,
		);
		if ( ! in_array( $value, $allowed, true ) ) {
			return new WP_Error( 'seo_geo_manager_native_seo_indexability_invalid', 'Indexability value is invalid.', array( 'status' => 400 ) );
		}

		return $value;
	}

	/**
	 * @return array<string, mixed>
	 */
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

	/**
	 * @return array<string, mixed>|WP_Error
	 */
	private static function replay( string $operation_id ) {
		$operation = OperationStore::get( $operation_id );
		if ( ! is_array( $operation ) || self::ADAPTER !== ( $operation['adapter'] ?? '' ) || 'failed' === ( $operation['status'] ?? '' ) ) {
			return new WP_Error(
				'seo_geo_manager_native_seo_idempotency_unavailable',
				'Idempotency key is unavailable for replay.',
				array( 'status' => 409 )
			);
		}
		$operation['idempotent_replay'] = true;

		return $operation;
	}

	/**
	 * @param array{post:WP_Post,authority:array<string,mixed>,before_values:array<string,string|null>,after_values:array<string,string|null>,before_fingerprint:string,after_fingerprint:string} $state Prepared state.
	 */
	private static function save_failed( string $operation_id, array $state, string $payload_hash, string $key, WP_Error $error ): void {
		OperationStore::save(
			$operation_id,
			array(
				'operation_id'       => $operation_id,
				'operation_type'     => 'native-seo-metadata-change',
				'adapter'            => self::ADAPTER,
				'schema_version'     => 1,
				'status'             => 'failed',
				'target_id'          => (int) $state['post']->ID,
				'target_type'        => (string) $state['post']->post_type,
				'resource_id'        => (int) $state['post']->ID,
				'before_values'      => $state['before_values'],
				'after_values'       => $state['after_values'],
				'before_fingerprint' => $state['before_fingerprint'],
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
