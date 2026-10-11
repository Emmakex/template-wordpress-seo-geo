<?php
/**
 * Bounded WordPress author binding reader.
 *
 * @package SeoGeoManager
 */

declare(strict_types=1);

namespace SeoGeo\Manager\Intelligence;

use WP_Error;
use WP_Post;
use WP_User;

/** Read the current author relationship for one normal post/page. */
final class AuthorBindingReader {
	/**
	 * Read one author binding without mutating the resource.
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	public static function read( int $resource_id ) {
		$post = self::resource( $resource_id );
		if ( is_wp_error( $post ) ) {
			return $post;
		}
		if ( ! current_user_can( 'edit_post', $post->ID ) ) {
			return new WP_Error(
				'seo_geo_manager_author_binding_forbidden',
				'You cannot inspect or change the author of this resource.',
				array( 'status' => 403 )
			);
		}

		$author_id = (int) $post->post_author;
		$author    = get_userdata( $author_id );

		return array(
			'schema_version' => 1,
			'resource'       => array(
				'id'        => (int) $post->ID,
				'post_type' => (string) $post->post_type,
				'status'    => (string) $post->post_status,
				'title'     => html_entity_decode( get_the_title( $post ), ENT_QUOTES | ENT_HTML5, self::charset() ),
			),
			'author'         => self::author_summary( $author_id, $author ),
			'binding'        => array(
				'author_id'   => $author_id,
				'fingerprint' => self::fingerprint( $post->ID, $author_id ),
			),
			'policy'         => array(
				'posts_pages_only'            => true,
				'existing_users_only'         => true,
				'user_creation_supported'     => false,
				'preview_before_mutation'     => true,
				'expected_fingerprint_source' => 'binding.fingerprint',
				'public_profile_fields_only'  => true,
			),
		);
	}

	/**
	 * Return one supported normal WordPress post/page.
	 *
	 * @return WP_Post|WP_Error
	 */
	public static function resource( int $resource_id ) {
		$post = get_post( $resource_id );
		if (
			! $post instanceof WP_Post
			|| ! in_array( $post->post_type, array( 'post', 'page' ), true )
			|| in_array( $post->post_status, array( 'trash', 'auto-draft' ), true )
		) {
			return new WP_Error(
				'seo_geo_manager_author_binding_resource_not_found',
				'The requested post/page was not found.',
				array( 'status' => 404 )
			);
		}

		return $post;
	}

	/** Build a deterministic fingerprint for the author relationship. */
	public static function fingerprint( int $resource_id, int $author_id ): string {
		return hash(
			'sha256',
			(string) wp_json_encode(
				array(
					'resource_id' => $resource_id,
					'author_id'   => $author_id,
				)
			)
		);
	}

	/**
	 * @param WP_User|false $author Resolved WordPress author, when present.
	 * @return array<string, mixed>
	 */
	private static function author_summary( int $author_id, $author ): array {
		if ( ! $author instanceof WP_User ) {
			return array(
				'id'           => $author_id,
				'exists'       => false,
				'display_name' => '',
			);
		}

		return array(
			'id'           => (int) $author->ID,
			'exists'       => true,
			'display_name' => (string) $author->display_name,
		);
	}

	private static function charset(): string {
		$charset = get_bloginfo( 'charset' );

		return is_string( $charset ) && '' !== $charset ? $charset : 'UTF-8';
	}
}
