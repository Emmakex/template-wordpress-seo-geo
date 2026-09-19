<?php
/**
 * Read-only existing-site analyzer.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge;

use SeoGeo\MigrationBridge\Builders\BuilderDetectorInterface;
use SeoGeo\MigrationBridge\Builders\DiviDetector;
use SeoGeo\MigrationBridge\Builders\ElementorDetector;
use SeoGeo\MigrationBridge\Builders\NativeBlocksDetector;

/**
 * Builds a machine-readable migration inventory without mutating WordPress.
 */
final class SiteAnalyzer {
	/**
	 * Builder detectors.
	 *
	 * @var list<BuilderDetectorInterface>
	 */
	private array $builder_detectors;

	/**
	 * Construct the analyzer.
	 *
	 * @param list<BuilderDetectorInterface>|null $builder_detectors Optional detector override.
	 */
	public function __construct( ?array $builder_detectors = null ) {
		$this->builder_detectors = $builder_detectors ?? array(
			new NativeBlocksDetector(),
			new ElementorDetector(),
			new DiviDetector(),
		);
	}

	/**
	 * Analyze the current installation.
	 *
	 * The report intentionally returns metadata/signals rather than copying
	 * private settings, credentials or arbitrary option values.
	 *
	 * @return array<string, mixed>
	 */
	public function analyze(): array {
		$themes  = $this->themes();
		$plugins = $this->plugins();

		return array(
			'schema_version' => 1,
			'mode'           => 'read-only',
			'generated_at'   => gmdate( DATE_ATOM ),
			'site'           => $this->site(),
			'themes'         => $themes,
			'plugins'        => $plugins,
			'builders'       => $this->builders( $plugins, $themes ),
			'providers'      => $this->providers( $plugins ),
			'content_model'  => array(
				'post_types' => $this->post_types(),
				'taxonomies' => $this->taxonomies(),
				'shortcodes' => $this->shortcodes(),
				'widgets'    => $this->widgets(),
				'menus'      => $this->menus(),
				'templates'  => $this->templates(),
			),
			'customization'  => $this->customization_signals(),
			'safety'         => array(
				'mutations_performed'    => false,
				'content_scan_performed' => false,
				'credentials_collected'  => false,
				'option_values_exported' => false,
			),
		);
	}

	/**
	 * Return basic runtime/site metadata.
	 *
	 * @return array<string, mixed>
	 */
	private function site(): array {
		global $wp_version;

		return array(
			'home_url'          => home_url( '/' ),
			'site_url'          => site_url( '/' ),
			'locale'            => get_locale(),
			'wordpress_version' => (string) $wp_version,
			'php_version'       => PHP_VERSION,
			'multisite'         => is_multisite(),
		);
	}

	/**
	 * Return installed theme inventory.
	 *
	 * @return list<array<string, mixed>>
	 */
	private function themes(): array {
		$active_stylesheet = get_stylesheet();
		$themes            = array();

		foreach ( wp_get_themes() as $stylesheet => $theme ) {
			$template = (string) $theme->get( 'Template' );
			$themes[] = array(
				'stylesheet'     => (string) $stylesheet,
				'template'       => '' !== $template ? $template : (string) $stylesheet,
				'name'           => (string) $theme->get( 'Name' ),
				'version'        => (string) $theme->get( 'Version' ),
				'status'         => $active_stylesheet === $stylesheet ? 'active' : 'inactive',
				'is_child_theme' => '' !== $template,
			);
		}

		usort(
			$themes,
			static fn( array $left, array $right ): int => strcmp( (string) $left['stylesheet'], (string) $right['stylesheet'] )
		);

		return $themes;
	}

	/**
	 * Return installed plugin inventory.
	 *
	 * @return list<array<string, mixed>>
	 */
	private function plugins(): array {
		if ( ! function_exists( 'get_plugins' ) || ! function_exists( 'get_mu_plugins' ) || ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$plugins = array();

		foreach ( get_plugins() as $basename => $data ) {
			$plugins[] = array(
				'basename'       => (string) $basename,
				'name'           => isset( $data['Name'] ) ? (string) $data['Name'] : '',
				'version'        => isset( $data['Version'] ) ? (string) $data['Version'] : '',
				'active'         => is_plugin_active( (string) $basename ),
				'network_active' => is_multisite() && is_plugin_active_for_network( (string) $basename ),
				'must_use'       => false,
			);
		}

		foreach ( get_mu_plugins() as $basename => $data ) {
			$plugins[] = array(
				'basename'       => (string) $basename,
				'name'           => isset( $data['Name'] ) ? (string) $data['Name'] : '',
				'version'        => isset( $data['Version'] ) ? (string) $data['Version'] : '',
				'active'         => true,
				'network_active' => is_multisite(),
				'must_use'       => true,
			);
		}

		usort(
			$plugins,
			static fn( array $left, array $right ): int => strcmp( (string) $left['basename'], (string) $right['basename'] )
		);

		return $plugins;
	}

	/**
	 * Run all configured builder detectors.
	 *
	 * @param list<array<string, mixed>> $plugins Plugin inventory.
	 * @param list<array<string, mixed>> $themes  Theme inventory.
	 * @return list<array<string, mixed>>
	 */
	private function builders( array $plugins, array $themes ): array {
		$results = array();

		foreach ( $this->builder_detectors as $detector ) {
			$results[] = $detector->detect( $plugins, $themes );
		}

		usort(
			$results,
			static fn( array $left, array $right ): int => strcmp( (string) $left['id'], (string) $right['id'] )
		);

		return $results;
	}

	/**
	 * Detect known provider families from plugin basenames.
	 *
	 * @param list<array<string, mixed>> $plugins Plugin inventory.
	 * @return array<string, list<array<string, mixed>>>
	 */
	private function providers( array $plugins ): array {
		$catalog = array(
			'business_systems' => array(
				'woocommerce'            => array( 'woocommerce/' ),
				'easy-digital-downloads' => array( 'easy-digital-downloads/' ),
			),
			'seo'              => array(
				'yoast'     => array( 'wordpress-seo/' ),
				'rank-math' => array( 'seo-by-rank-math/' ),
				'aioseo'    => array( 'all-in-one-seo-pack/' ),
			),
			'schema'           => array(
				'schema-pro' => array( 'schema-pro/', 'wp-schema-pro/' ),
				'yoast'      => array( 'wordpress-seo/' ),
				'rank-math'  => array( 'seo-by-rank-math/' ),
				'aioseo'     => array( 'all-in-one-seo-pack/' ),
			),
			'multilingual'     => array(
				'wpml'           => array( 'sitepress-multilingual-cms/' ),
				'polylang'       => array( 'polylang/', 'polylang-pro/' ),
				'translatepress' => array( 'translatepress-multilingual/' ),
			),
			'redirects'        => array(
				'redirection' => array( 'redirection/' ),
			),
			'analytics'        => array(
				'site-kit'        => array( 'google-site-kit/' ),
				'monsterinsights' => array( 'google-analytics-for-wordpress/' ),
			),
			'forms'            => array(
				'contact-form-7' => array( 'contact-form-7/' ),
				'gravity-forms'  => array( 'gravityforms/' ),
				'wpforms'        => array( 'wpforms/', 'wpforms-lite/' ),
			),
			'cache'            => array(
				'wp-rocket'       => array( 'wp-rocket/' ),
				'w3-total-cache'  => array( 'w3-total-cache/' ),
				'litespeed-cache' => array( 'litespeed-cache/' ),
			),
			'security'         => array(
				'wordfence' => array( 'wordfence/' ),
				'sucuri'    => array( 'sucuri-scanner/' ),
			),
		);

		$result = array();

		foreach ( $catalog as $category => $providers ) {
			$result[ $category ] = array();

			foreach ( $providers as $provider_id => $prefixes ) {
				$matches = array();
				$active  = false;

				foreach ( $plugins as $plugin ) {
					$basename = $plugin['basename'] ?? null;
					if ( ! is_string( $basename ) || ! $this->matches_any_prefix( $basename, $prefixes ) ) {
						continue;
					}

					$matches[] = $basename;
					$active    = $active || true === ( $plugin['active'] ?? false );
				}

				if ( array() === $matches ) {
					continue;
				}

				sort( $matches );
				$result[ $category ][] = array(
					'id'              => $provider_id,
					'installed'       => true,
					'active'          => $active,
					'matched_plugins' => $matches,
				);
			}
		}

		return $result;
	}

	/**
	 * Check whether a plugin basename matches a provider prefix.
	 *
	 * @param string             $basename Plugin basename.
	 * @param array<int, string> $prefixes Provider plugin prefixes.
	 */
	private function matches_any_prefix( string $basename, array $prefixes ): bool {
		foreach ( $prefixes as $prefix ) {
			if ( str_starts_with( $basename, $prefix ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Return registered post types.
	 *
	 * @return list<array<string, mixed>>
	 */
	private function post_types(): array {
		$results = array();

		foreach ( get_post_types( array(), 'objects' ) as $post_type ) {
			$results[] = array(
				'name'     => (string) $post_type->name,
				'label'    => (string) $post_type->label,
				'public'   => true === $post_type->public,
				'show_ui'  => true === $post_type->show_ui,
				'built_in' => true === $post_type->_builtin,
			);
		}

		usort(
			$results,
			static fn( array $left, array $right ): int => strcmp( (string) $left['name'], (string) $right['name'] )
		);

		return $results;
	}

	/**
	 * Return registered taxonomies.
	 *
	 * @return list<array<string, mixed>>
	 */
	private function taxonomies(): array {
		$results = array();

		foreach ( get_taxonomies( array(), 'objects' ) as $taxonomy ) {
			$results[] = array(
				'name'        => (string) $taxonomy->name,
				'label'       => (string) $taxonomy->label,
				'public'      => true === $taxonomy->public,
				'show_ui'     => true === $taxonomy->show_ui,
				'built_in'    => true === $taxonomy->_builtin,
				'object_type' => array_values( array_map( 'strval', $taxonomy->object_type ) ),
			);
		}

		usort(
			$results,
			static fn( array $left, array $right ): int => strcmp( (string) $left['name'], (string) $right['name'] )
		);

		return $results;
	}

	/**
	 * Return registered shortcode identifiers.
	 *
	 * @return list<string>
	 */
	private function shortcodes(): array {
		global $shortcode_tags;

		$names = is_array( $shortcode_tags ) ? array_map( 'strval', array_keys( $shortcode_tags ) ) : array();
		sort( $names );

		return array_values( $names );
	}

	/**
	 * Return registered widget class identifiers.
	 *
	 * @return list<string>
	 */
	private function widgets(): array {
		global $wp_widget_factory;

		if ( ! isset( $wp_widget_factory ) || ! is_object( $wp_widget_factory ) || ! isset( $wp_widget_factory->widgets ) || ! is_array( $wp_widget_factory->widgets ) ) {
			return array();
		}

		$classes = array_map( 'strval', array_keys( $wp_widget_factory->widgets ) );
		sort( $classes );

		return array_values( $classes );
	}

	/**
	 * Return navigation menu inventory.
	 *
	 * @return list<array<string, mixed>>
	 */
	private function menus(): array {
		$results = array();
		$menus   = wp_get_nav_menus();

		if ( is_wp_error( $menus ) ) {
			return $results;
		}

		foreach ( $menus as $menu ) {
			$results[] = array(
				'term_id' => (int) $menu->term_id,
				'name'    => (string) $menu->name,
				'slug'    => (string) $menu->slug,
				'count'   => (int) $menu->count,
			);
		}

		usort(
			$results,
			static fn( array $left, array $right ): int => (int) $left['term_id'] <=> (int) $right['term_id']
		);

		return $results;
	}

	/**
	 * Return active-theme template file signals.
	 *
	 * @return array<string, mixed>
	 */
	private function templates(): array {
		$theme_root = get_stylesheet_directory();

		return array(
			'active_stylesheet' => get_stylesheet(),
			'active_template'   => get_template(),
			'block_templates'   => $this->relative_files( $theme_root . '/templates', '*.html' ),
			'template_parts'    => $this->relative_files( $theme_root . '/parts', '*.html' ),
			'php_templates'     => $this->relative_files( $theme_root, '*.php' ),
		);
	}

	/**
	 * Return non-content customization signals.
	 *
	 * @return array<string, mixed>
	 */
	private function customization_signals(): array {
		$custom_css = function_exists( 'wp_get_custom_css' ) ? wp_get_custom_css() : '';
		$functions  = get_stylesheet_directory() . '/functions.php';

		return array(
			'child_theme_active' => is_child_theme(),
			'custom_css'         => array(
				'present' => '' !== trim( $custom_css ),
				'bytes'   => strlen( $custom_css ),
				'sha256'  => '' !== $custom_css ? hash( 'sha256', $custom_css ) : null,
			),
			'functions_php'      => $this->file_signal( $functions ),
		);
	}

	/**
	 * Return presence, size and hash for a readable file.
	 *
	 * @param string $path File path.
	 * @return array<string, mixed>
	 */
	private function file_signal( string $path ): array {
		if ( ! is_readable( $path ) ) {
			return array(
				'present' => false,
				'bytes'   => 0,
				'sha256'  => null,
			);
		}

		$size = filesize( $path );
		$hash = hash_file( 'sha256', $path );

		return array(
			'present' => true,
			'bytes'   => false === $size ? 0 : $size,
			'sha256'  => false === $hash ? null : $hash,
		);
	}

	/**
	 * Return sorted basenames matching one directory glob.
	 *
	 * @param string $directory Directory path.
	 * @param string $pattern   Glob pattern.
	 * @return list<string>
	 */
	private function relative_files( string $directory, string $pattern ): array {
		if ( ! is_dir( $directory ) ) {
			return array();
		}

		$files = glob( trailingslashit( $directory ) . $pattern );
		if ( false === $files ) {
			return array();
		}

		$results = array_map( 'basename', $files );
		sort( $results );

		return array_values( $results );
	}
}
