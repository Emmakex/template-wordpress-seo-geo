<?php
/**
 * Clone runtime reset for reset-first rebuilds.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge\Reset;

use SeoGeo\MigrationBridge\Sandbox\SandboxGuard;
use WP_Error;

/**
 * Removes legacy runtime baggage while preserving the Rescue Manifest/content.
 */
final class CloneResetEngine {
	public const REPORT_OPTION = 'seo_geo_clone_reset_report_v1';
	public const TARGET_THEME  = 'seo-geo-theme';

	/**
	 * Construct the clone reset engine.
	 *
	 * @param RescueManifest $manifest Rescue Manifest authority.
	 */
	public function __construct( private RescueManifest $manifest ) {
	}

	/**
	 * Build a read-only reset plan.
	 *
	 * @param array<int,string> $requested_keep_plugins Plugin files explicitly kept for business functionality.
	 * @return array<string,mixed>
	 */
	public function plan( array $requested_keep_plugins = array() ): array {
		$this->load_admin_files();

		$blockers     = array();
		$saved        = $this->manifest->saved();
		$bridge       = $this->bridge_plugin_file();
		$keep_plugins = $this->normalize_keep_plugins( $requested_keep_plugins, $bridge );
		$plugins      = get_plugins();
		$themes       = wp_get_themes();
		$home_path    = wp_parse_url( home_url( '/' ), PHP_URL_PATH );
		$home_path    = is_string( $home_path ) && '' !== $home_path ? $home_path : '/';

		if ( ! SandboxGuard::enabled() ) {
			$blockers[] = 'sandbox-marker-required';
		}
		if ( 'subdirectory' === SandboxGuard::mode() ) {
			if ( ! SandboxGuard::storage_isolated() ) {
				$blockers[] = 'subdirectory-storage-isolation-required';
			}
			if ( '/' === untrailingslashit( $home_path ) || '' === untrailingslashit( $home_path ) ) {
				$blockers[] = 'subdirectory-home-path-required';
			}
		}
		if ( ! is_array( $saved ) || '' === (string) ( $saved['manifest_sha256'] ?? '' ) ) {
			$blockers[] = 'rescue-manifest-required';
		}
		if ( ! isset( $themes[ self::TARGET_THEME ] ) ) {
			$blockers[] = 'target-theme-not-installed';
		}

		$plugin_keep   = array();
		$plugin_remove = array();
		foreach ( $plugins as $plugin_file => $plugin_data ) {
			$row = array(
				'file'   => (string) $plugin_file,
				'name'   => (string) ( $plugin_data['Name'] ?? $plugin_file ),
				'active' => is_plugin_active( (string) $plugin_file ),
			);
			if ( in_array( (string) $plugin_file, $keep_plugins, true ) ) {
				$plugin_keep[] = $row;
			} else {
				$plugin_remove[] = $row;
			}
		}

		$theme_keep   = array();
		$theme_remove = array();
		foreach ( $themes as $stylesheet => $theme ) {
			$row = array(
				'stylesheet' => (string) $stylesheet,
				'name'       => (string) $theme->get( 'Name' ),
				'active'     => get_stylesheet() === (string) $stylesheet,
			);
			if ( self::TARGET_THEME === (string) $stylesheet ) {
				$theme_keep[] = $row;
			} else {
				$theme_remove[] = $row;
			}
		}

		$blockers = array_values( array_unique( $blockers ) );
		sort( $blockers );

		$material = array(
			'manifest_sha256' => is_array( $saved ) ? (string) ( $saved['manifest_sha256'] ?? '' ) : '',
			'home_url'        => home_url( '/' ),
			'keep_plugins'    => $keep_plugins,
			'remove_plugins'  => array_column( $plugin_remove, 'file' ),
			'remove_themes'   => array_column( $theme_remove, 'stylesheet' ),
			'target_theme'    => self::TARGET_THEME,
		);

		return array(
			'schema_version' => 1,
			'mode'           => 'reset-rebuild-clone-reset-plan',
			'ready'          => array() === $blockers,
			'blockers'       => $blockers,
			'manifest'       => array(
				'available' => is_array( $saved ),
				'sha256'    => is_array( $saved ) ? (string) ( $saved['manifest_sha256'] ?? '' ) : '',
				'resources' => is_array( $saved ) ? (int) ( $saved['counts']['resources'] ?? 0 ) : 0,
			),
			'plugins'        => array(
				'keep'   => $plugin_keep,
				'remove' => $plugin_remove,
			),
			'themes'         => array(
				'keep'   => $theme_keep,
				'remove' => $theme_remove,
			),
			'cleanup'        => array(
				'clear_widget_assignments' => true,
				'clear_removed_theme_mods' => true,
				'clear_rewrite_rules'      => true,
				'clear_object_cache'       => true,
				'generated_paths'          => array(
					'wp-content/et-cache',
					'uploads/elementor/css',
				),
			),
			'plan_sha256'    => hash(
				'sha256',
				(string) wp_json_encode( $material, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE )
			),
			'safety'         => array(
				'sandbox_only'             => true,
				'dependency_review_needed' => false,
				'content_delete_allowed'   => false,
				'manifest_delete_allowed'  => false,
				'bridge_delete_allowed'    => false,
			),
		);
	}

	/**
	 * Apply the runtime reset to the clone.
	 *
	 * @param array<int,string> $requested_keep_plugins Plugin files explicitly kept.
	 * @return array<string,mixed>|WP_Error
	 */
	public function apply( array $requested_keep_plugins = array() ): array|WP_Error {
		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error( 'seo_geo_clone_reset_forbidden', 'Administrator capability is required.' );
		}

		$plan = $this->plan( $requested_keep_plugins );
		if ( true !== ( $plan['ready'] ?? false ) ) {
			return new WP_Error(
				'seo_geo_clone_reset_not_ready',
				'Clone reset is blocked: ' . implode( ', ', is_array( $plan['blockers'] ?? null ) ? $plan['blockers'] : array() )
			);
		}

		$this->load_admin_files();

		$manifest_before = $this->manifest->saved();
		$manifest_sha    = is_array( $manifest_before ) ? (string) ( $manifest_before['manifest_sha256'] ?? '' ) : '';
		$removed_plugins = array_values(
			array_filter(
				array_map(
					static fn( mixed $row ): string => is_array( $row ) ? (string) ( $row['file'] ?? '' ) : '',
					is_array( $plan['plugins']['remove'] ?? null ) ? $plan['plugins']['remove'] : array()
				)
			)
		);
		$removed_themes  = array_values(
			array_filter(
				array_map(
					static fn( mixed $row ): string => is_array( $row ) ? (string) ( $row['stylesheet'] ?? '' ) : '',
					is_array( $plan['themes']['remove'] ?? null ) ? $plan['themes']['remove'] : array()
				)
			)
		);

		$before = array(
			'stylesheet'     => $this->active_stylesheet(),
			'active_plugins' => $this->active_plugins(),
		);

		if ( self::TARGET_THEME !== $this->active_stylesheet() ) {
			switch_theme( self::TARGET_THEME );
		}
		if ( self::TARGET_THEME !== $this->active_stylesheet() ) {
			return new WP_Error( 'seo_geo_clone_reset_theme_switch_failed', 'SEO/GEO Theme could not become the active theme.' );
		}

		$active_to_remove = array_values( array_filter( $removed_plugins, 'is_plugin_active' ) );
		if ( array() !== $active_to_remove ) {
			deactivate_plugins( $active_to_remove, true );
		}

		$plugin_delete_error = null;
		if ( array() !== $removed_plugins ) {
			$result = delete_plugins( $removed_plugins );
			if ( $result instanceof WP_Error ) {
				$plugin_delete_error = $result->get_error_message();
			}
		}

		$theme_delete_errors = array();
		foreach ( $removed_themes as $stylesheet ) {
			$result = delete_theme( $stylesheet );
			if ( $result instanceof WP_Error ) {
				$theme_delete_errors[ $stylesheet ] = $result->get_error_message();
				continue;
			}
			delete_option( 'theme_mods_' . $stylesheet );
		}

		update_option( 'sidebars_widgets', array( 'wp_inactive_widgets' => array() ), false );
		delete_option( 'rewrite_rules' );
		wp_cache_flush();

		$generated_cleanup  = $this->clear_generated_paths();
		$manifest_after     = $this->manifest->saved();
		$manifest_unchanged = is_array( $manifest_after )
			&& '' !== $manifest_sha
			&& hash_equals( $manifest_sha, (string) ( $manifest_after['manifest_sha256'] ?? '' ) );

		$content_check = $this->verify_rescued_content( $manifest_after );
		$errors        = array();
		if ( null !== $plugin_delete_error ) {
			$errors['plugins'] = $plugin_delete_error;
		}
		if ( array() !== $theme_delete_errors ) {
			$errors['themes'] = $theme_delete_errors;
		}
		if ( ! $manifest_unchanged ) {
			$errors['manifest'] = 'rescue-manifest-drift';
		}
		if ( ! $content_check['unchanged'] ) {
			$errors['content'] = $content_check['drifted_ids'];
		}

		$report = array(
			'schema_version'    => 1,
			'mode'              => 'reset-rebuild-clone-reset',
			'status'            => array() === $errors ? 'completed' : 'partial',
			'reset_at'          => gmdate( DATE_ATOM ),
			'plan_sha256'       => (string) ( $plan['plan_sha256'] ?? '' ),
			'manifest_sha256'   => $manifest_sha,
			'before'            => $before,
			'after'             => array(
				'stylesheet'     => $this->active_stylesheet(),
				'active_plugins' => $this->active_plugins(),
			),
			'removed'           => array(
				'plugins_requested' => $removed_plugins,
				'themes_requested'  => $removed_themes,
			),
			'generated_cleanup' => $generated_cleanup,
			'content_check'     => $content_check,
			'errors'            => $errors,
			'safety'            => array(
				'manifest_unchanged'  => $manifest_unchanged,
				'content_unchanged'   => $content_check['unchanged'],
				'target_theme_active' => true,
				'bridge_active'       => is_plugin_active( $this->bridge_plugin_file() ),
				'production_mutation' => false,
			),
		);

		update_option( self::REPORT_OPTION, $report, false );

		return $report;
	}

	/**
	 * Return the latest reset report.
	 *
	 * @return array<string,mixed>|null
	 */
	public function report(): ?array {
		$value = get_option( self::REPORT_OPTION, null );

		return is_array( $value ) ? $value : null;
	}

	/**
	 * Normalize explicit plugin keep-list and force Migration Bridge retention.
	 *
	 * @param array<int,string> $requested Requested plugin files.
	 * @param string            $bridge    Migration Bridge plugin file.
	 * @return list<string>
	 */
	private function normalize_keep_plugins( array $requested, string $bridge ): array {
		$keep = array( $bridge );
		foreach ( $requested as $plugin_file ) {
			if ( '' !== trim( $plugin_file ) ) {
				$keep[] = plugin_basename( $plugin_file );
			}
		}

		$keep = array_values( array_unique( $keep ) );
		sort( $keep );

		return $keep;
	}

	/**
	 * Confirm every rescued post still matches the captured content fingerprint.
	 *
	 * @param array<string,mixed>|null $manifest Saved Rescue Manifest.
	 * @return array{unchanged:bool,checked:int,drifted_ids:list<int>}
	 */
	private function verify_rescued_content( ?array $manifest ): array {
		$drifted = array();
		$checked = 0;

		foreach ( is_array( $manifest['resources'] ?? null ) ? $manifest['resources'] : array() as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}

			$post_id      = (int) ( $item['id'] ?? 0 );
			$expected_sha = (string) ( $item['content_sha256'] ?? '' );
			if ( 0 >= $post_id || '' === $expected_sha ) {
				continue;
			}

			++$checked;
			$current = (string) get_post_field( 'post_content', $post_id );
			if ( ! hash_equals( $expected_sha, hash( 'sha256', $current ) ) ) {
				$drifted[] = $post_id;
			}
		}

		return array(
			'unchanged'   => array() === $drifted,
			'checked'     => $checked,
			'drifted_ids' => $drifted,
		);
	}

	/**
	 * Return the normalized active plugin file list.
	 *
	 * @return list<string>
	 */
	private function active_plugins(): array {
		$value = get_option( 'active_plugins', array() );
		if ( ! is_array( $value ) ) {
			return array();
		}

		$plugins = array_values(
			array_filter(
				array_map(
					static fn( mixed $plugin ): string => is_string( $plugin ) ? $plugin : '',
					$value
				)
			)
		);
		sort( $plugins );

		return $plugins;
	}

	/**
	 * Delete known generated presentation caches only.
	 *
	 * @return array<string,bool>
	 */
	private function clear_generated_paths(): array {
		global $wp_filesystem;

		require_once ABSPATH . 'wp-admin/includes/file.php';
		WP_Filesystem();

		$uploads = wp_get_upload_dir();
		$paths   = array(
			'et-cache'      => WP_CONTENT_DIR . '/et-cache',
			'elementor-css' => trailingslashit( (string) $uploads['basedir'] ) . 'elementor/css',
		);
		$result  = array();

		foreach ( $paths as $key => $path ) {
			if ( ! $wp_filesystem ) {
				$result[ $key ] = false;
				continue;
			}
			if ( ! $wp_filesystem->exists( $path ) ) {
				$result[ $key ] = true;
				continue;
			}
			$result[ $key ] = $wp_filesystem->delete( $path, true );
		}

		return $result;
	}

	/**
	 * Return the active stylesheet slug without relying on mutable function narrowing.
	 */
	private function active_stylesheet(): string {
		$value = get_option( 'stylesheet', '' );

		return is_string( $value ) ? $value : '';
	}

	/**
	 * Return the Migration Bridge plugin file from this package path.
	 */
	private function bridge_plugin_file(): string {
		return plugin_basename( dirname( __DIR__, 2 ) . '/seo-geo-migration-bridge.php' );
	}

	/**
	 * Load WordPress admin plugin/theme helpers.
	 */
	private function load_admin_files(): void {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		require_once ABSPATH . 'wp-admin/includes/theme.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
	}
}
