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

		$can_edit_posts          = current_user_can( 'edit_posts' );
		$can_publish_posts       = current_user_can( 'publish_posts' );
		$can_edit_pages          = current_user_can( 'edit_pages' );
		$can_publish_pages       = current_user_can( 'publish_pages' );
		$can_upload_files        = current_user_can( 'upload_files' );
		$can_edit_theme          = current_user_can( 'edit_theme_options' );
		$can_manage_categories   = current_user_can( 'manage_categories' );
		$can_manage_options      = current_user_can( 'manage_options' );
		$app_passwords_supported = function_exists( 'wp_is_application_passwords_supported' )
			? wp_is_application_passwords_supported()
			: false;
		$app_passwords_available = function_exists( 'wp_is_application_passwords_available_for_user' )
			? wp_is_application_passwords_available_for_user( $user )
			: false;

		return array(
			'schema_version' => 1,
			'manager'        => array(
				'service'       => 'seo-geo-manager',
				'version'       => SEO_GEO_MANAGER_VERSION,
				'api_namespace' => 'seo-geo-manager/v1',
			),
			'principal'      => array(
				'user_id'       => (int) $user->ID,
				'authenticated' => 0 < (int) $user->ID,
			),
			'authentication' => array(
				'remote_transport_requires_https' => true,
				'application_passwords_supported' => $app_passwords_supported,
				'application_passwords_available' => $app_passwords_available,
				'browser_session_nonce'           => true,
				'revocable_identity_required'     => true,
				'secrets_returned'                => false,
				'generic_remote_shell'            => false,
			),
			'environment'    => EnvironmentPolicy::snapshot(),
			'wordpress'      => array(
				'version'   => get_bloginfo( 'version' ),
				'multisite' => is_multisite(),
				'blog_id'   => get_current_blog_id(),
			),
			'capabilities'   => array(
				'site_intelligence'         => self::capability( $can_edit_posts, 'edit_posts' ),
				'content_read'              => self::capability( $can_edit_posts, 'edit_posts' ),
				'content_change_set'        => self::capability( $can_edit_posts, 'edit_posts' ),
				'post_create'               => self::capability( $can_edit_posts, 'edit_posts' ),
				'page_create'               => self::capability( $can_edit_pages, 'edit_pages' ),
				'theme_structured_content'  => self::capability( $can_edit_posts, 'edit_posts' ),
				'navigation_change_set'     => self::capability( $can_edit_theme, 'edit_theme_options' ),
				'media_write'               => self::capability( $can_upload_files, 'upload_files' ),
				'taxonomy_management'       => self::capability( $can_manage_categories, 'manage_categories' ),
				'post_publish'              => self::capability( $can_publish_posts, 'publish_posts' ),
				'page_write'                => self::capability( $can_edit_pages, 'edit_pages' ),
				'page_publish'              => self::capability( $can_publish_pages, 'publish_pages' ),
				'site_wide_operations'      => self::capability( $can_manage_options, 'manage_options' ),
				'permalink_administration'  => self::capability( $can_manage_options, 'manage_options' ),
				'operation_history_summary' => self::capability( $can_edit_posts, 'edit_posts' ),
			),
			'operations'     => self::operations(
				$can_edit_posts,
				$can_edit_pages,
				$can_publish_posts,
				$can_publish_pages,
				$can_edit_theme,
				$can_manage_options
			),
			'safety'         => array(
				'preview_before_mutation'         => true,
				'idempotency_supported'           => true,
				'expected_fingerprint_supported'  => true,
				'environment_binding_supported'   => true,
				'operation_history_supported'     => true,
				'stale_safe_rollback_supported'   => true,
				'rendered_verification_supported' => true,
				'bounded_responses'               => true,
				'client_specific_code_required'   => false,
			),
		);
	}

	/**
	 * @return array{available:bool,required_wordpress_capability:string}
	 */
	private static function capability( bool $available, string $required ): array {
		return array(
			'available'                     => $available,
			'required_wordpress_capability' => $required,
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
		bool $can_edit_theme,
		bool $can_manage_options
	): array {
		$can_create_content  = $can_edit_posts || $can_edit_pages;
		$can_publish_content = $can_publish_posts || $can_publish_pages;

		return array(
			'inspect.site' => array(
				'available' => $can_edit_posts,
				'method'    => 'GET',
				'path'      => '/site/intelligence',
				'risk'      => 'read-only',
			),
			'inspect.snapshot' => array(
				'available' => $can_edit_posts,
				'method'    => 'GET',
				'path'      => '/site/snapshot',
				'risk'      => 'read-only',
			),
			'content.preview' => array(
				'available' => $can_edit_posts,
				'method'    => 'POST',
				'path'      => '/changes/preview',
				'risk'      => 'read-only-preview',
			),
			'content.apply' => array(
				'available' => $can_edit_posts,
				'method'    => 'POST',
				'path'      => '/changes/apply',
				'risk'      => 'mutation',
			),
			'content.create.preview' => array(
				'available' => $can_create_content,
				'method'    => 'POST',
				'path'      => '/content/resources/preview',
				'risk'      => 'read-only-preview',
			),
			'content.create.apply' => array(
				'available' => $can_create_content,
				'method'    => 'POST',
				'path'      => '/content/resources/apply',
				'risk'      => 'mutation',
			),
			'content.publication.preview' => array(
				'available' => $can_publish_content,
				'method'    => 'POST',
				'path'      => '/content/{id}/publication/preview',
				'risk'      => 'read-only-preview',
			),
			'content.publication.apply' => array(
				'available' => $can_publish_content,
				'method'    => 'POST',
				'path'      => '/content/{id}/publication/apply',
				'risk'      => 'publication-mutation',
			),
			'theme.preview' => array(
				'available' => $can_edit_posts,
				'method'    => 'POST',
				'path'      => '/theme/structured/preview',
				'risk'      => 'read-only-preview',
			),
			'theme.apply' => array(
				'available' => $can_edit_posts,
				'method'    => 'POST',
				'path'      => '/theme/structured/apply',
				'risk'      => 'mutation',
			),
			'navigation.preview' => array(
				'available' => $can_edit_theme,
				'method'    => 'POST',
				'path'      => '/navigation/changes/preview',
				'risk'      => 'read-only-preview',
			),
			'navigation.apply' => array(
				'available' => $can_edit_theme,
				'method'    => 'POST',
				'path'      => '/navigation/changes/apply',
				'risk'      => 'mutation',
			),
			'operations.read' => array(
				'available' => $can_edit_posts,
				'method'    => 'GET',
				'path'      => '/operations',
				'risk'      => 'read-only',
			),
			'field_gate.inspect' => array(
				'available' => $can_manage_options,
				'method'    => 'GET',
				'path'      => '/field-gate/preflight',
				'risk'      => 'read-only',
			),
			'permalinks.admin' => array(
				'available' => $can_manage_options,
				'method'    => 'MIXED',
				'path'      => '/permalinks/*',
				'risk'      => 'site-wide-guarded',
			),
		);
	}
}
