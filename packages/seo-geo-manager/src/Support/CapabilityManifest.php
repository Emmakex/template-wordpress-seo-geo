<?php
/**
 * Authenticated capability manifest for remote Manager orchestration.
 *
 * @package SeoGeoManager
 */

declare(strict_types=1);

namespace SeoGeo\Manager\Support;

final class CapabilityManifest {
	/**
	 * Build a bounded capability manifest for the current authenticated user.
	 *
	 * The manifest describes what the caller may do through Manager. It never
	 * returns credentials, nonces, application passwords or private content.
	 *
	 * @return array<string, mixed>
	 */
	public static function build(): array {
		$user = wp_get_current_user();

		$can_edit_posts = current_user_can( 'edit_posts' );
		$can_publish_posts = current_user_can( 'publish_posts' );
		$can_edit_pages = current_user_can( 'edit_pages' );
		$can_publish_pages = current_user_can( 'publish_pages' );
		$can_upload_files = current_user_can( 'upload_files' );
		$can_edit_theme = current_user_can( 'edit_theme_options' );
		$can_manage_categories = current_user_can( 'manage_categories' );
		$can_manage_options = current_user_can( 'manage_options' );
		$can_read_links = $can_edit_posts || $can_edit_pages;

		$app_passwords_supported = function_exists( 'wp_is_application_passwords_supported' )
			? wp_is_application_passwords_supported()
			: false;
		$app_passwords_available = function_exists( 'wp_is_application_passwords_available_for_user' )
			? wp_is_application_passwords_available_for_user( $user )
			: false;

		$capabilities = array();
		$capabilities['site_intelligence'] = self::capability( $can_edit_posts, 'edit_posts' );
		$capabilities['content_read'] = self::capability( $can_edit_posts, 'edit_posts' );
		$capabilities['content_change_set'] = self::capability( $can_edit_posts, 'edit_posts' );
		$capabilities['post_create'] = self::capability( $can_edit_posts, 'edit_posts' );
		$capabilities['page_create'] = self::capability( $can_edit_pages, 'edit_pages' );
		$capabilities['theme_model_read'] = self::capability(
			$can_edit_pages || $can_edit_posts,
			'edit_pages|edit_posts'
		);
		$capabilities['theme_structured_content'] = self::capability( $can_edit_posts, 'edit_posts' );
		$capabilities['navigation_change_set'] = self::capability( $can_edit_theme, 'edit_theme_options' );
		$capabilities['contextual_link_read'] = self::capability( $can_read_links, 'edit_pages|edit_posts' );
		$capabilities['contextual_link_write'] = self::capability(
			$can_read_links,
			'edit_pages|edit_posts + edit_post(source)'
		);
		$capabilities['media_read'] = self::capability( $can_upload_files, 'upload_files' );
		$capabilities['media_write'] = self::capability( $can_upload_files, 'upload_files' );
		$capabilities['taxonomy_management'] = self::capability(
			$can_manage_categories,
			'manage_categories'
		);
		$capabilities['post_publish'] = self::capability( $can_publish_posts, 'publish_posts' );
		$capabilities['page_write'] = self::capability( $can_edit_pages, 'edit_pages' );
		$capabilities['page_publish'] = self::capability( $can_publish_pages, 'publish_pages' );
		$capabilities['site_wide_operations'] = self::capability( $can_manage_options, 'manage_options' );
		$capabilities['permalink_administration'] = self::capability( $can_manage_options, 'manage_options' );
		$capabilities['operation_history_summary'] = self::capability( $can_edit_posts, 'edit_posts' );

		return array(
			'schema_version' => 1,
			'manager' => array(
				'service' => 'seo-geo-manager',
				'version' => SEO_GEO_MANAGER_VERSION,
				'api_namespace' => 'seo-geo-manager/v1',
			),
			'principal' => array(
				'user_id' => (int) $user->ID,
				'authenticated' => 0 < (int) $user->ID,
			),
			'authentication' => array(
				'remote_transport_requires_https' => true,
				'application_passwords_supported' => $app_passwords_supported,
				'application_passwords_available' => $app_passwords_available,
				'browser_session_nonce' => true,
				'revocable_identity_required' => true,
				'secrets_returned' => false,
				'generic_remote_shell' => false,
			),
			'environment' => EnvironmentPolicy::snapshot(),
			'wordpress' => array(
				'version' => get_bloginfo( 'version' ),
				'multisite' => is_multisite(),
				'blog_id' => get_current_blog_id(),
			),
			'capabilities' => $capabilities,
			'operations' => self::operations(
				$can_edit_posts,
				$can_edit_pages,
				$can_publish_posts,
				$can_publish_pages,
				$can_upload_files,
				$can_edit_theme,
				$can_manage_options
			),
			'safety' => array(
				'preview_before_mutation' => true,
				'idempotency_supported' => true,
				'expected_fingerprint_supported' => true,
				'environment_binding_supported' => true,
				'operation_history_supported' => true,
				'stale_safe_rollback_supported' => true,
				'rendered_verification_supported' => true,
				'bounded_responses' => true,
				'client_specific_code_required' => false,
			),
		);
	}

	/**
	 * @return array{available:bool,required_wordpress_capability:string}
	 */
	private static function capability( bool $available, string $required ): array {
		return array(
			'available' => $available,
			'required_wordpress_capability' => $required,
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function operation( bool $available, string $method, string $path, string $risk ): array {
		return array(
			'available' => $available,
			'method' => $method,
			'path' => $path,
			'risk' => $risk,
		);
	}

	/**
	 * @return array<string, array<string, mixed>>
	 */
	private static function operations(
		bool $can_edit_posts,
		bool $can_edit_pages,
		bool $can_publish_posts,
		bool $can_publish_pages,
		bool $can_upload_files,
		bool $can_edit_theme,
		bool $can_manage_options
	): array {
		$can_create_content = $can_edit_posts || $can_edit_pages;
		$can_publish_content = $can_publish_posts || $can_publish_pages;
		$can_read_models = $can_edit_posts || $can_edit_pages;
		$can_read_links = $can_edit_posts || $can_edit_pages;

		$operations = array();
		$operations['inspect.site'] = self::operation( $can_edit_posts, 'GET', '/site/intelligence', 'read-only' );
		$operations['inspect.snapshot'] = self::operation( $can_edit_posts, 'GET', '/site/snapshot', 'read-only' );
		$operations['content.preview'] = self::operation( $can_edit_posts, 'POST', '/changes/preview', 'read-only-preview' );
		$operations['content.apply'] = self::operation( $can_edit_posts, 'POST', '/changes/apply', 'mutation' );
		$operations['content.create.preview'] = self::operation(
			$can_create_content,
			'POST',
			'/content/resources/preview',
			'read-only-preview'
		);
		$operations['content.create.apply'] = self::operation(
			$can_create_content,
			'POST',
			'/content/resources/apply',
			'mutation'
		);
		$operations['content.publication.preview'] = self::operation(
			$can_publish_content,
			'POST',
			'/content/{id}/publication/preview',
			'read-only-preview'
		);
		$operations['content.publication.apply'] = self::operation(
			$can_publish_content,
			'POST',
			'/content/{id}/publication/apply',
			'publication-mutation'
		);
		$operations['theme.models.list'] = self::operation( $can_read_models, 'GET', '/theme/models', 'read-only' );
		$operations['theme.models.read'] = self::operation(
			$can_read_models,
			'GET',
			'/theme/models/{model_id}',
			'read-only'
		);
		$operations['theme.preview'] = self::operation(
			$can_edit_posts,
			'POST',
			'/theme/structured/preview',
			'read-only-preview'
		);
		$operations['theme.apply'] = self::operation( $can_edit_posts, 'POST', '/theme/structured/apply', 'mutation' );
		$operations['navigation.preview'] = self::operation(
			$can_edit_theme,
			'POST',
			'/navigation/changes/preview',
			'read-only-preview'
		);
		$operations['navigation.apply'] = self::operation(
			$can_edit_theme,
			'POST',
			'/navigation/changes/apply',
			'mutation'
		);
		$operations['links.contextual.read'] = self::operation(
			$can_read_links,
			'GET',
			'/links/contextual',
			'read-only'
		);
		$operations['links.contextual.preview'] = self::operation(
			$can_read_links,
			'POST',
			'/links/contextual/changes/preview',
			'read-only-preview'
		);
		$operations['links.contextual.apply'] = self::operation(
			$can_read_links,
			'POST',
			'/links/contextual/changes/apply',
			'mutation'
		);
		$operations['links.contextual.rollback'] = self::operation(
			$can_read_links,
			'POST',
			'/links/contextual/changes/{operation_id}/rollback',
			'rollback-mutation'
		);
		$operations['media.list'] = self::operation( $can_upload_files, 'GET', '/media', 'read-only' );
		$operations['media.read'] = self::operation( $can_upload_files, 'GET', '/media/{media_id}', 'read-only' );
		$operations['operations.read'] = self::operation( $can_edit_posts, 'GET', '/operations', 'read-only' );
		$operations['field_gate.inspect'] = self::operation(
			$can_manage_options,
			'GET',
			'/field-gate/preflight',
			'read-only'
		);
		$operations['permalinks.admin'] = self::operation(
			$can_manage_options,
			'MIXED',
			'/permalinks/*',
			'site-wide-guarded'
		);

		return $operations;
	}
}
