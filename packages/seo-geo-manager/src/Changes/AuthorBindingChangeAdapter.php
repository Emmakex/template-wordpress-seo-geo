<?php
/**
 * Guarded author binding changes for existing WordPress users.
 *
 * @package SeoGeoManager
 */

declare(strict_types=1);

namespace SeoGeo\Manager\Changes;

use SeoGeo\Manager\Intelligence\AuthorBindingReader;
use WP_Error;
use WP_Post;
use WP_Post_Type;
use WP_User;

/** Change one post/page author relationship without creating user identities. */
final class AuthorBindingChangeAdapter {
	private const ADAPTER = 'author-binding';

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
			'resource'       => self::resource_summary( $state['post'] ),
			'change'         => array(
				'before_author_id' => $state['before_author_id'],
				'after_author_id'  => $state['after_author_id'],
				'after_author'     => self::author_summary( $state['after_author'] ),
			),
			'has_changes'    => true,
			'policy'         => array(
				'existing_users_only'         => true,
				'user_creation_supported'     => false,
				'published_approval_required' => 'publish' === $state['post']->post_status,
				'edit_others_capability'      => $state['edit_others_capability'],
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
				'seo_geo_manager_author_binding_idempotency_required',
				'Author binding Apply requires an idempotency key of at most 128 characters.',
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

		$result = wp_update_post(
			array(
				'ID'          => $state['post']->ID,
				'post_author' => $state['after_author_id'],
			),
			true
		);
		if ( is_wp_error( $result ) ) {
			self::save_failed( $operation_id, $state, $hash, $key, $result );

			return $result;
		}

		$after = AuthorBindingReader::resource( $state['post']->ID );
		if ( is_wp_error( $after ) || (int) $after->post_author !== $state['after_author_id'] ) {
			self::restore( $state['post']->ID, $state['before_author_id'] );

			return new WP_Error(
				'seo_geo_manager_author_binding_verify_failed',
				'Author binding verification failed; Manager attempted compensation.',
				array( 'status' => 409 )
			);
		}

		$after_fingerprint = AuthorBindingReader::fingerprint( $after->ID, (int) $after->post_author );
		if ( hash_equals( $state['before_fingerprint'], $after_fingerprint ) ) {
			self::restore( $state['post']->ID, $state['before_author_id'] );

			return new WP_Error(
				'seo_geo_manager_author_binding_fingerprint_unchanged',
				'Author binding did not produce a verifiable state change.',
				array( 'status' => 409 )
			);
		}

		$operation = array(
			'operation_id'       => $operation_id,
			'operation_type'     => 'author-binding-change',
			'adapter'            => self::ADAPTER,
			'schema_version'     => 1,
			'status'             => 'applied',
			'target_id'          => (int) $after->ID,
			'target_type'        => (string) $after->post_type,
			'resource_id'        => (int) $after->ID,
			'before_author_id'   => $state['before_author_id'],
			'after_author_id'    => $state['after_author_id'],
			'before_fingerprint' => $state['before_fingerprint'],
			'after_fingerprint'  => $after_fingerprint,
			'idempotency_key'    => $key,
			'payload_hash'       => $hash,
			'changes'            => array( 'author_binding' => true ),
			'created_at_gmt'     => gmdate( 'c' ),
			'idempotent_replay'  => false,
		);
		if ( ! OperationStore::save( $operation_id, $operation ) ) {
			self::restore( $after->ID, $state['before_author_id'] );

			return new WP_Error(
				'seo_geo_manager_author_binding_operation_store_failed',
				'Author binding changed but operation history could not be persisted; Manager attempted compensation.',
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
				'seo_geo_manager_author_binding_operation_not_found',
				'Author binding operation not found.',
				array( 'status' => 404 )
			);
		}
		if ( 'rolled-back' === ( $operation['status'] ?? '' ) ) {
			return $operation;
		}
		if ( 'applied' !== ( $operation['status'] ?? '' ) ) {
			return new WP_Error(
				'seo_geo_manager_author_binding_rollback_unavailable',
				'Only applied author bindings can be rolled back.',
				array( 'status' => 409 )
			);
		}

		$resource_id = isset( $operation['resource_id'] ) ? absint( $operation['resource_id'] ) : 0;
		$post        = AuthorBindingReader::resource( $resource_id );
		if ( is_wp_error( $post ) ) {
			return $post;
		}
		if ( ! current_user_can( 'edit_post', $post->ID ) ) {
			return new WP_Error(
				'seo_geo_manager_author_binding_forbidden',
				'You cannot roll back the author of this resource.',
				array( 'status' => 403 )
			);
		}

		$after_author_id = isset( $operation['after_author_id'] ) ? absint( $operation['after_author_id'] ) : 0;
		$after_fp        = isset( $operation['after_fingerprint'] ) && is_string( $operation['after_fingerprint'] )
			? $operation['after_fingerprint']
			: '';
		$current_fp      = AuthorBindingReader::fingerprint( $post->ID, (int) $post->post_author );
		if ( (int) $post->post_author !== $after_author_id || '' === $after_fp || ! hash_equals( $after_fp, $current_fp ) ) {
			return new WP_Error(
				'seo_geo_manager_author_binding_rollback_stale',
				'Author binding changed after Apply; rollback is blocked to avoid overwriting a newer edit.',
				array( 'status' => 409 )
			);
		}

		$before_author_id = isset( $operation['before_author_id'] ) ? absint( $operation['before_author_id'] ) : 0;
		$target_guard     = self::validate_target_author( $post, $before_author_id );
		if ( is_wp_error( $target_guard ) ) {
			return $target_guard;
		}
		$before_fp = isset( $operation['before_fingerprint'] ) && is_string( $operation['before_fingerprint'] )
			? $operation['before_fingerprint']
			: '';

		$restore = self::restore( $post->ID, $before_author_id );
		if ( is_wp_error( $restore ) ) {
			return $restore;
		}

		$restored = AuthorBindingReader::resource( $post->ID );
		if ( is_wp_error( $restored ) ) {
			self::restore( $post->ID, $after_author_id );

			return $restored;
		}
		$restored_fp = AuthorBindingReader::fingerprint( $restored->ID, (int) $restored->post_author );
		if ( (int) $restored->post_author !== $before_author_id || '' === $before_fp || ! hash_equals( $before_fp, $restored_fp ) ) {
			self::restore( $post->ID, $after_author_id );

			return new WP_Error(
				'seo_geo_manager_author_binding_rollback_verify_failed',
				'Author rollback verification failed; Manager attempted to restore the applied state.',
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
	 * @return array{post:WP_Post,before_author_id:int,after_author_id:int,after_author:WP_User,before_fingerprint:string,edit_others_capability:string}|WP_Error
	 */
	private static function prepare( array $payload, bool $for_apply ) {
		$resource_id = isset( $payload['resource_id'] ) ? absint( $payload['resource_id'] ) : 0;
		$author_id   = isset( $payload['author_id'] ) ? absint( $payload['author_id'] ) : 0;
		$expected    = isset( $payload['expected_fingerprint'] ) && is_string( $payload['expected_fingerprint'] )
			? trim( $payload['expected_fingerprint'] )
			: '';
		if ( 1 > $resource_id || 1 > $author_id || '' === $expected ) {
			return new WP_Error(
				'seo_geo_manager_author_binding_request_invalid',
				'Author binding requests require resource_id, author_id and expected_fingerprint.',
				array( 'status' => 400 )
			);
		}

		$inspection = AuthorBindingReader::read( $resource_id );
		if ( is_wp_error( $inspection ) ) {
			return $inspection;
		}
		$binding   = isset( $inspection['binding'] ) && is_array( $inspection['binding'] ) ? $inspection['binding'] : array();
		$before_id = isset( $binding['author_id'] ) ? absint( $binding['author_id'] ) : 0;
		$before_fp = isset( $binding['fingerprint'] ) && is_string( $binding['fingerprint'] ) ? $binding['fingerprint'] : '';
		if ( '' === $before_fp || ! hash_equals( $expected, $before_fp ) ) {
			return new WP_Error(
				'seo_geo_manager_author_binding_fingerprint_mismatch',
				'Author binding changed after inspection.',
				array( 'status' => 409 )
			);
		}
		if ( $before_id === $author_id ) {
			return new WP_Error(
				'seo_geo_manager_author_binding_no_changes',
				'The requested author is already bound to this resource.',
				array( 'status' => 400 )
			);
		}

		$post = AuthorBindingReader::resource( $resource_id );
		if ( is_wp_error( $post ) ) {
			return $post;
		}
		if ( $for_apply && 'publish' === $post->post_status && true !== ( $payload['allow_published_target'] ?? false ) ) {
			return new WP_Error(
				'seo_geo_manager_author_binding_public_approval_required',
				'Published resource author changes require explicit approval after Preview.',
				array( 'status' => 409 )
			);
		}

		$target = self::validate_target_author( $post, $author_id );
		if ( is_wp_error( $target ) ) {
			return $target;
		}

		$post_type = get_post_type_object( $post->post_type );
		$edit_others = $post_type instanceof WP_Post_Type && isset( $post_type->cap->edit_others_posts ) && is_string( $post_type->cap->edit_others_posts )
			? $post_type->cap->edit_others_posts
			: '';

		return array(
			'post'                   => $post,
			'before_author_id'       => $before_id,
			'after_author_id'        => $author_id,
			'after_author'           => $target,
			'before_fingerprint'     => $before_fp,
			'edit_others_capability' => $edit_others,
		);
	}

	/**
	 * Validate an existing WordPress user as an eligible author target.
	 *
	 * @return WP_User|WP_Error
	 */
	private static function validate_target_author( WP_Post $post, int $author_id ) {
		$author = get_userdata( $author_id );
		if ( ! $author instanceof WP_User ) {
			return new WP_Error(
				'seo_geo_manager_author_binding_user_not_found',
				'The requested WordPress author does not exist.',
				array( 'status' => 404 )
			);
		}

		$post_type = get_post_type_object( $post->post_type );
		if ( ! $post_type instanceof WP_Post_Type ) {
			return new WP_Error(
				'seo_geo_manager_author_binding_type_unavailable',
				'The resource post type is unavailable.',
				array( 'status' => 400 )
			);
		}

		$edit_cap = isset( $post_type->cap->edit_posts ) && is_string( $post_type->cap->edit_posts )
			? $post_type->cap->edit_posts
			: '';
		if ( '' === $edit_cap || ! user_can( $author, $edit_cap ) ) {
			return new WP_Error(
				'seo_geo_manager_author_binding_user_ineligible',
				'The requested WordPress user is not eligible to author this content type.',
				array( 'status' => 403 )
			);
		}

		if ( $author_id !== get_current_user_id() ) {
			$edit_others = isset( $post_type->cap->edit_others_posts ) && is_string( $post_type->cap->edit_others_posts )
				? $post_type->cap->edit_others_posts
				: '';
			if ( '' === $edit_others || ! current_user_can( $edit_others ) ) {
				return new WP_Error(
					'seo_geo_manager_author_binding_edit_others_required',
					'Assigning another WordPress author requires the post type edit-others capability.',
					array( 'status' => 403 )
				);
			}
		}

		return $author;
	}

	/**
	 * @return true|WP_Error
	 */
	private static function restore( int $resource_id, int $author_id ) {
		$result = wp_update_post(
			array(
				'ID'          => $resource_id,
				'post_author' => $author_id,
			),
			true
		);

		return is_wp_error( $result ) ? $result : true;
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
	 * @return array<string, mixed>
	 */
	private static function author_summary( WP_User $author ): array {
		return array(
			'id'           => (int) $author->ID,
			'display_name' => (string) $author->display_name,
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
					'author_id'              => isset( $payload['author_id'] ) ? absint( $payload['author_id'] ) : 0,
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
				'seo_geo_manager_author_binding_idempotency_unavailable',
				'Idempotency key is unavailable for replay.',
				array( 'status' => 409 )
			);
		}
		$operation['idempotent_replay'] = true;

		return $operation;
	}

	/**
	 * @param array{post:WP_Post,before_author_id:int,after_author_id:int,after_author:WP_User,before_fingerprint:string,edit_others_capability:string} $state Prepared state.
	 */
	private static function save_failed( string $operation_id, array $state, string $payload_hash, string $key, WP_Error $error ): void {
		OperationStore::save(
			$operation_id,
			array(
				'operation_id'       => $operation_id,
				'operation_type'     => 'author-binding-change',
				'adapter'            => self::ADAPTER,
				'schema_version'     => 1,
				'status'             => 'failed',
				'target_id'          => (int) $state['post']->ID,
				'target_type'        => (string) $state['post']->post_type,
				'resource_id'        => (int) $state['post']->ID,
				'before_author_id'   => $state['before_author_id'],
				'after_author_id'    => $state['after_author_id'],
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
