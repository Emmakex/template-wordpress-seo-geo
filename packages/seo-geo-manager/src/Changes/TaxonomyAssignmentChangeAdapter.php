<?php
/**
 * Guarded taxonomy-assignment changes for existing terms.
 *
 * @package SeoGeoManager
 */

declare(strict_types=1);

namespace SeoGeo\Manager\Changes;

use SeoGeo\Manager\Intelligence\TaxonomyAssignmentReader;
use WP_Error;
use WP_Post;

/** Replace an existing-term assignment without creating taxonomy terms. */
final class TaxonomyAssignmentChangeAdapter {
	private const ADAPTER = 'taxonomy-assignment';

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
			'resource'       => self::resource_summary( $state['resource'] ),
			'taxonomy'       => $state['taxonomy'],
			'change'         => array(
				'before_term_ids' => $state['before_term_ids'],
				'after_term_ids'  => $state['after_term_ids'],
			),
			'has_changes'    => true,
			'policy'         => array(
				'existing_terms_only'         => true,
				'term_creation_supported'     => false,
				'exact_replace_assignment'    => true,
				'published_approval_required' => 'publish' === $state['resource']->post_status,
				'stale_safe_rollback'         => true,
			),
		);
	}

	/**
	 * @param array<string, mixed> $payload Request payload.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function apply( array $payload ) {
		$key = isset( $payload['idempotency_key'] ) && is_string( $payload['idempotency_key'] ) ? trim( $payload['idempotency_key'] ) : '';
		if ( '' === $key || 128 < strlen( $key ) ) {
			return new WP_Error(
				'seo_geo_manager_taxonomy_idempotency_required',
				'Taxonomy Apply requires an idempotency key of at most 128 characters.',
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

		$result = wp_set_object_terms(
			$state['resource']->ID,
			$state['after_term_ids'],
			$state['taxonomy'],
			false
		);
		if ( is_wp_error( $result ) ) {
			self::save_failed( $operation_id, $state, $hash, $key, $result );
			return $result;
		}

		$after_ids = TaxonomyAssignmentReader::term_ids( $state['resource']->ID, $state['taxonomy'] );
		if ( is_wp_error( $after_ids ) || $after_ids !== $state['after_term_ids'] ) {
			self::restore( $state['resource']->ID, $state['taxonomy'], $state['before_term_ids'] );
			return new WP_Error(
				'seo_geo_manager_taxonomy_verify_failed',
				'Taxonomy assignment verification failed; Manager attempted compensation.',
				array( 'status' => 409 )
			);
		}

		$after_fingerprint = TaxonomyAssignmentReader::fingerprint_from_ids(
			$state['resource']->ID,
			$state['taxonomy'],
			$after_ids
		);
		if ( hash_equals( $state['before_fingerprint'], $after_fingerprint ) ) {
			self::restore( $state['resource']->ID, $state['taxonomy'], $state['before_term_ids'] );
			return new WP_Error(
				'seo_geo_manager_taxonomy_fingerprint_unchanged',
				'Taxonomy assignment did not produce a verifiable state change.',
				array( 'status' => 409 )
			);
		}

		$operation = array(
			'operation_id'       => $operation_id,
			'operation_type'     => 'taxonomy-assignment-change',
			'adapter'            => self::ADAPTER,
			'schema_version'     => 1,
			'status'             => 'applied',
			'target_id'          => (int) $state['resource']->ID,
			'target_type'        => (string) $state['resource']->post_type,
			'resource_id'        => (int) $state['resource']->ID,
			'taxonomy'           => $state['taxonomy'],
			'before_term_ids'     => $state['before_term_ids'],
			'after_term_ids'      => $after_ids,
			'before_fingerprint' => $state['before_fingerprint'],
			'after_fingerprint'  => $after_fingerprint,
			'idempotency_key'    => $key,
			'payload_hash'       => $hash,
			'changes'            => array( 'taxonomy_assignment' => true ),
			'created_at_gmt'     => gmdate( 'c' ),
			'idempotent_replay'  => false,
		);
		if ( ! OperationStore::save( $operation_id, $operation ) ) {
			self::restore( $state['resource']->ID, $state['taxonomy'], $state['before_term_ids'] );
			return new WP_Error(
				'seo_geo_manager_taxonomy_operation_store_failed',
				'Taxonomy assignment changed but operation history could not be persisted; Manager attempted compensation.',
				array( 'status' => 500 )
			);
		}

		return $operation;
	}

	/** @return array<string, mixed>|WP_Error */
	public static function rollback( string $operation_id ) {
		$operation = OperationStore::get( $operation_id );
		if ( ! is_array( $operation ) || self::ADAPTER !== ( $operation['adapter'] ?? '' ) ) {
			return new WP_Error( 'seo_geo_manager_taxonomy_operation_not_found', 'Taxonomy assignment operation not found.', array( 'status' => 404 ) );
		}
		if ( 'rolled-back' === ( $operation['status'] ?? '' ) ) {
			return $operation;
		}
		if ( 'applied' !== ( $operation['status'] ?? '' ) ) {
			return new WP_Error( 'seo_geo_manager_taxonomy_rollback_unavailable', 'Only applied taxonomy assignments can be rolled back.', array( 'status' => 409 ) );
		}

		$resource_id = isset( $operation['resource_id'] ) ? absint( $operation['resource_id'] ) : 0;
		$taxonomy    = isset( $operation['taxonomy'] ) && is_string( $operation['taxonomy'] ) ? sanitize_key( $operation['taxonomy'] ) : '';
		$current_ids = TaxonomyAssignmentReader::term_ids( $resource_id, $taxonomy );
		if ( is_wp_error( $current_ids ) ) {
			return $current_ids;
		}
		$after_ids = self::normalize_term_ids( $operation['after_term_ids'] ?? array() );
		$after_fp  = isset( $operation['after_fingerprint'] ) && is_string( $operation['after_fingerprint'] ) ? $operation['after_fingerprint'] : '';
		$current_fp = TaxonomyAssignmentReader::fingerprint_from_ids( $resource_id, $taxonomy, $current_ids );
		if ( $current_ids !== $after_ids || '' === $after_fp || ! hash_equals( $after_fp, $current_fp ) ) {
			return new WP_Error(
				'seo_geo_manager_taxonomy_rollback_stale',
				'Taxonomy assignment changed after Apply; rollback is blocked to avoid overwriting newer edits.',
				array( 'status' => 409 )
			);
		}

		$before_ids = self::normalize_term_ids( $operation['before_term_ids'] ?? array() );
		$before_fp  = isset( $operation['before_fingerprint'] ) && is_string( $operation['before_fingerprint'] ) ? $operation['before_fingerprint'] : '';
		$restore    = self::restore( $resource_id, $taxonomy, $before_ids );
		if ( is_wp_error( $restore ) ) {
			return $restore;
		}

		$restored_ids = TaxonomyAssignmentReader::term_ids( $resource_id, $taxonomy );
		if ( is_wp_error( $restored_ids ) ) {
			self::restore( $resource_id, $taxonomy, $after_ids );
			return $restored_ids;
		}
		$restored_fp = TaxonomyAssignmentReader::fingerprint_from_ids( $resource_id, $taxonomy, $restored_ids );
		if ( $restored_ids !== $before_ids || '' === $before_fp || ! hash_equals( $before_fp, $restored_fp ) ) {
			self::restore( $resource_id, $taxonomy, $after_ids );
			return new WP_Error(
				'seo_geo_manager_taxonomy_rollback_verify_failed',
				'Taxonomy rollback verification failed; Manager attempted to restore the applied state.',
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
	 * @return array{resource:WP_Post,taxonomy:string,before_term_ids:list<int>,after_term_ids:list<int>,before_fingerprint:string}|WP_Error
	 */
	private static function prepare( array $payload, bool $for_apply ) {
		$resource_id = isset( $payload['resource_id'] ) ? absint( $payload['resource_id'] ) : 0;
		$taxonomy    = isset( $payload['taxonomy'] ) && is_string( $payload['taxonomy'] ) ? sanitize_key( $payload['taxonomy'] ) : '';
		$after_ids   = self::normalize_term_ids( $payload['term_ids'] ?? array() );
		$expected    = isset( $payload['expected_fingerprint'] ) && is_string( $payload['expected_fingerprint'] ) ? trim( $payload['expected_fingerprint'] ) : '';
		if ( 1 > $resource_id || '' === $taxonomy || '' === $expected ) {
			return new WP_Error(
				'seo_geo_manager_taxonomy_request_invalid',
				'Taxonomy requests require resource_id, taxonomy, term_ids and expected_fingerprint.',
				array( 'status' => 400 )
			);
		}

		$inspection = TaxonomyAssignmentReader::read( $resource_id, $taxonomy );
		if ( is_wp_error( $inspection ) ) {
			return $inspection;
		}
		$assignment = isset( $inspection['assignment'] ) && is_array( $inspection['assignment'] ) ? $inspection['assignment'] : array();
		$before_ids = self::normalize_term_ids( $assignment['term_ids'] ?? array() );
		$before_fp  = isset( $assignment['fingerprint'] ) && is_string( $assignment['fingerprint'] ) ? $assignment['fingerprint'] : '';
		if ( '' === $before_fp || ! hash_equals( $expected, $before_fp ) ) {
			return new WP_Error( 'seo_geo_manager_taxonomy_fingerprint_mismatch', 'Taxonomy assignment changed after inspection.', array( 'status' => 409 ) );
		}
		if ( $before_ids === $after_ids ) {
			return new WP_Error( 'seo_geo_manager_taxonomy_no_changes', 'The requested taxonomy assignment is already active.', array( 'status' => 400 ) );
		}

		$resource = get_post( $resource_id );
		if ( ! $resource instanceof WP_Post ) {
			return new WP_Error( 'seo_geo_manager_taxonomy_resource_not_found', 'The requested post/page was not found.', array( 'status' => 404 ) );
		}
		if ( $for_apply && 'publish' === $resource->post_status && true !== ( $payload['allow_published_target'] ?? false ) ) {
			return new WP_Error(
				'seo_geo_manager_taxonomy_public_approval_required',
				'Published resource writes require explicit approval after Preview.',
				array( 'status' => 409 )
			);
		}

		$term_guard = self::validate_existing_terms( $taxonomy, $after_ids );
		if ( is_wp_error( $term_guard ) ) {
			return $term_guard;
		}

		return array(
			'resource'           => $resource,
			'taxonomy'           => $taxonomy,
			'before_term_ids'     => $before_ids,
			'after_term_ids'      => $after_ids,
			'before_fingerprint' => $before_fp,
		);
	}

	/** @param mixed $raw Raw term IDs. @return list<int> */
	private static function normalize_term_ids( $raw ): array {
		$values = is_array( $raw ) ? $raw : array();
		$ids    = array_map( 'absint', $values );
		$ids    = array_values( array_unique( array_filter( $ids ) ) );
		sort( $ids, SORT_NUMERIC );

		return $ids;
	}

	/** @param list<int> $term_ids Term IDs. @return true|WP_Error */
	private static function validate_existing_terms( string $taxonomy, array $term_ids ) {
		if ( array() === $term_ids ) {
			return true;
		}

		$found = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'include'    => $term_ids,
				'hide_empty' => false,
				'fields'     => 'ids',
			)
		);
		if ( is_wp_error( $found ) ) {
			return $found;
		}
		$found_ids = array_map( 'absint', $found );
		$found_ids = array_values( array_filter( $found_ids ) );
		sort( $found_ids, SORT_NUMERIC );
		if ( $found_ids !== $term_ids ) {
			return new WP_Error(
				'seo_geo_manager_taxonomy_term_not_found',
				'One or more requested taxonomy terms do not exist in the selected taxonomy.',
				array( 'status' => 404 )
			);
		}

		return true;
	}

	/** @param list<int> $term_ids Term IDs. @return array<int, int>|WP_Error */
	private static function restore( int $resource_id, string $taxonomy, array $term_ids ) {
		$result = wp_set_object_terms( $resource_id, $term_ids, $taxonomy, false );

		return is_wp_error( $result ) ? $result : array_map( 'absint', $result );
	}

	/** @return array<string, mixed> */
	private static function resource_summary( WP_Post $resource ): array {
		return array(
			'id'        => (int) $resource->ID,
			'post_type' => (string) $resource->post_type,
			'status'    => (string) $resource->post_status,
			'title'     => html_entity_decode( get_the_title( $resource ), ENT_QUOTES | ENT_HTML5, self::charset() ),
		);
	}

	/** @param array<string, mixed> $payload Request payload. */
	private static function payload_hash( array $payload ): string {
		return hash(
			'sha256',
			(string) wp_json_encode(
				array(
					'resource_id'            => isset( $payload['resource_id'] ) ? absint( $payload['resource_id'] ) : 0,
					'taxonomy'               => isset( $payload['taxonomy'] ) ? sanitize_key( (string) $payload['taxonomy'] ) : '',
					'term_ids'               => self::normalize_term_ids( $payload['term_ids'] ?? array() ),
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
			return new WP_Error( 'seo_geo_manager_taxonomy_idempotency_unavailable', 'Idempotency key is unavailable for replay.', array( 'status' => 409 ) );
		}
		$operation['idempotent_replay'] = true;

		return $operation;
	}

	/**
	 * @param array{resource:WP_Post,taxonomy:string,before_term_ids:list<int>,after_term_ids:list<int>,before_fingerprint:string} $state Prepared state.
	 */
	private static function save_failed( string $operation_id, array $state, string $payload_hash, string $key, WP_Error $error ): void {
		OperationStore::save(
			$operation_id,
			array(
				'operation_id'       => $operation_id,
				'operation_type'     => 'taxonomy-assignment-change',
				'adapter'            => self::ADAPTER,
				'schema_version'     => 1,
				'status'             => 'failed',
				'target_id'          => (int) $state['resource']->ID,
				'target_type'        => (string) $state['resource']->post_type,
				'resource_id'        => (int) $state['resource']->ID,
				'taxonomy'           => $state['taxonomy'],
				'before_term_ids'     => $state['before_term_ids'],
				'after_term_ids'      => $state['after_term_ids'],
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
