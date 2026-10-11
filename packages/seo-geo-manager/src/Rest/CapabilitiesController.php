<?php
/**
 * Authenticated capability-discovery endpoint.
 *
 * @package SeoGeoManager
 */

declare(strict_types=1);

namespace SeoGeo\Manager\Rest;

use SeoGeo\Manager\Support\CapabilityManifest;
use WP_REST_Request;
use WP_REST_Response;

final class CapabilitiesController {
	private const NAMESPACE = 'seo-geo-manager/v1';

	public static function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/capabilities',
			array(
				'methods'             => 'GET',
				'callback'            => array( self::class, 'show' ),
				'permission_callback' => array( self::class, 'can_read' ),
			)
		);
	}

	public static function can_read(): bool {
		return current_user_can( 'edit_posts' ) || current_user_can( 'manage_options' );
	}

	public static function show( WP_REST_Request $request ): WP_REST_Response {
		unset( $request );

		$manifest         = CapabilityManifest::build();
		$write_contract   = function_exists( 'seo_geo_theme_set_strategic_media_slot' )
			&& function_exists( 'seo_geo_theme_remove_strategic_media_slot' )
			&& function_exists( 'seo_geo_theme_strategic_media_slot' )
			&& function_exists( 'seo_geo_theme_strategic_media_fingerprint' )
			&& function_exists( 'seo_geo_theme_strategic_media_contract' );
		$available        = $write_contract && current_user_can( 'upload_files' ) && current_user_can( 'edit_pages' );
		$can_assign_terms = current_user_can( 'edit_posts' ) || current_user_can( 'edit_pages' );
		$can_bind_author  = current_user_can( 'edit_posts' ) || current_user_can( 'edit_pages' );

		$manifest['theme_contracts']['strategic_media_write'] = array(
			'available'              => $write_contract,
			'declared_slots_only'    => true,
			'raw_theme_meta_exposed' => false,
		);
		$manifest['capabilities']['theme_media_binding_write'] = array(
			'available'                     => $available,
			'required_wordpress_capability' => 'upload_files + edit_pages + edit_post(resource) + edit_post(attachment)',
		);
		$manifest['operations']['theme.media_binding.preview'] = self::operation(
			$available,
			'POST',
			'/theme/media-bindings/{resource_id}/{model_id}/{slot_id}/changes/preview',
			'read-only-preview'
		);
		$manifest['operations']['theme.media_binding.apply'] = self::operation(
			$available,
			'POST',
			'/theme/media-bindings/{resource_id}/{model_id}/{slot_id}/changes/apply',
			'mutation'
		);
		$manifest['operations']['theme.media_binding.rollback'] = self::operation(
			$available,
			'POST',
			'/theme/media-bindings/changes/{operation_id}/rollback',
			'rollback-mutation'
		);

		$manifest['capabilities']['taxonomy_assignment'] = array(
			'available'                     => $can_assign_terms,
			'required_wordpress_capability' => 'edit_post(resource) + taxonomy.assign_terms',
			'existing_terms_only'           => true,
		);
		$manifest['operations']['content.taxonomy_assignment.read'] = self::operation(
			$can_assign_terms,
			'GET',
			'/content/{resource_id}/taxonomies/{taxonomy}',
			'read-only'
		);
		$manifest['operations']['content.taxonomy_assignment.preview'] = self::operation(
			$can_assign_terms,
			'POST',
			'/content/{resource_id}/taxonomies/{taxonomy}/changes/preview',
			'read-only-preview'
		);
		$manifest['operations']['content.taxonomy_assignment.apply'] = self::operation(
			$can_assign_terms,
			'POST',
			'/content/{resource_id}/taxonomies/{taxonomy}/changes/apply',
			'mutation'
		);
		$manifest['operations']['content.taxonomy_assignment.rollback'] = self::operation(
			$can_assign_terms,
			'POST',
			'/content/taxonomies/changes/{operation_id}/rollback',
			'rollback-mutation'
		);

		$manifest['capabilities']['author_binding'] = array(
			'available'                     => $can_bind_author,
			'required_wordpress_capability' => 'edit_post(resource); assigning another user also requires post_type.edit_others_posts',
			'existing_users_only'           => true,
			'public_profile_fields_only'    => true,
		);
		$manifest['operations']['content.author_binding.read'] = self::operation(
			$can_bind_author,
			'GET',
			'/content/{resource_id}/author',
			'read-only'
		);
		$manifest['operations']['content.author_binding.preview'] = self::operation(
			$can_bind_author,
			'POST',
			'/content/{resource_id}/author/changes/preview',
			'read-only-preview'
		);
		$manifest['operations']['content.author_binding.apply'] = self::operation(
			$can_bind_author,
			'POST',
			'/content/{resource_id}/author/changes/apply',
			'mutation'
		);
		$manifest['operations']['content.author_binding.rollback'] = self::operation(
			$can_bind_author,
			'POST',
			'/content/authors/changes/{operation_id}/rollback',
			'rollback-mutation'
		);

		return new WP_REST_Response( $manifest, 200 );
	}

	/** @return array<string, mixed> */
	private static function operation( bool $available, string $method, string $path, string $risk ): array {
		return array(
			'available' => $available,
			'method'    => $method,
			'path'      => $path,
			'risk'      => $risk,
		);
	}
}
