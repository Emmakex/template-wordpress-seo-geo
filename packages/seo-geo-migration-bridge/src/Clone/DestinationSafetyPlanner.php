<?php
/**
 * Portable Clone Engine local destination safety planning.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge\Clone;

use wpdb;

/**
 * Produces a read-only safety plan for a future same-server clone target.
 */
final class DestinationSafetyPlanner {
	/**
	 * Plan one local-clone destination without creating or modifying it.
	 *
	 * @param string   $target_path         Absolute target path.
	 * @param string   $target_url          Target WordPress URL.
	 * @param string   $target_table_prefix Target table prefix.
	 * @param bool     $same_database       Whether source and target share one database.
	 * @param int|null $required_bytes      Optional inventory byte estimate.
	 * @return array<string,mixed>
	 */
	public function plan(
		string $target_path,
		string $target_url,
		string $target_table_prefix,
		bool $same_database = true,
		?int $required_bytes = null
	): array {
		global $wpdb;

		$source_path = wp_normalize_path( ABSPATH );
		$target_path = $this->normalize_target_path( $target_path );
		$source_url  = home_url( '/' );
		$target_url  = esc_url_raw( $target_url );
		$blockers    = array();
		$advisories  = array();

		if ( '' === $target_path || ! $this->absolute_path( $target_path ) ) {
			$blockers[] = 'target-path-invalid';
		}

		if ( ! in_array( wp_parse_url( $target_url, PHP_URL_SCHEME ), array( 'http', 'https' ), true ) ) {
			$blockers[] = 'target-url-invalid';
		} else {
			$target_url = trailingslashit( $target_url );
		}

		if ( $this->same_path( $source_path, $target_path ) ) {
			$blockers[] = 'target-path-is-production';
		}

		$payload_roots = $this->payload_roots();
		foreach ( $payload_roots as $root_id => $root_path ) {
			if ( $this->same_path( $root_path, $target_path ) || $this->inside_path( $target_path, $root_path ) ) {
				$blockers[] = 'target-inside-source-' . $root_id;
			}
		}

		$nested_under_wordpress_root = $this->inside_path( $target_path, $source_path );
		if ( $nested_under_wordpress_root && ! in_array( 'target-path-is-production', $blockers, true ) ) {
			$advisories[] = 'target-nested-under-wordpress-root';
		}

		$source_origin = $this->origin_key( $source_url );
		$target_origin = $this->origin_key( $target_url );
		$same_origin   = '' !== $source_origin && $source_origin === $target_origin;
		$source_base   = $this->url_base_path( $source_url );
		$target_base   = $this->url_base_path( $target_url );

		if ( untrailingslashit( $source_url ) === untrailingslashit( $target_url ) ) {
			$blockers[] = 'target-url-is-production';
		}

		if ( $same_origin && ( '/' === $target_base || $target_base === $source_base ) ) {
			$blockers[] = 'same-origin-target-path-not-isolated';
		}

		$source_prefix = $wpdb instanceof wpdb ? $wpdb->prefix : '';
		if ( 1 !== preg_match( '/^[A-Za-z0-9_]+$/', $target_table_prefix ) ) {
			$blockers[] = 'target-table-prefix-invalid';
		} elseif ( $same_database && $source_prefix === $target_table_prefix ) {
			$blockers[] = 'target-table-prefix-not-isolated';
		}

		$target_exists = '' !== $target_path && is_dir( $target_path );
		$target_empty  = $target_exists ? $this->directory_empty( $target_path ) : null;
		if ( true === $target_exists && false === $target_empty ) {
			$advisories[] = 'target-directory-not-empty-requires-explicit-ownership';
		}

		$probe_path = $target_exists ? $target_path : dirname( $target_path );
		$free_bytes = is_dir( $probe_path ) && is_readable( $probe_path ) ? disk_free_space( $probe_path ) : false;
		if ( null !== $required_bytes && false !== $free_bytes && $free_bytes < $required_bytes ) {
			$blockers[] = 'target-free-space-insufficient';
		}

		$blockers   = array_values( array_unique( $blockers ) );
		$advisories = array_values( array_unique( $advisories ) );

		return array(
			'mode'                         => 'read-only-destination-plan',
			'ready'                        => array() === $blockers,
			'source_path'                  => $source_path,
			'target_path'                  => $target_path,
			'source_url'                   => $source_url,
			'target_url'                   => $target_url,
			'same_origin'                  => $same_origin,
			'source_base_path'             => $source_base,
			'target_base_path'             => $target_base,
			'nested_under_wordpress_root'  => $nested_under_wordpress_root,
			'payload_roots_exclude_target' => ! $this->inside_any_payload_root( $target_path, $payload_roots ),
			'same_database'                => $same_database,
			'source_table_prefix'          => $source_prefix,
			'target_table_prefix'          => $target_table_prefix,
			'target_exists'                => $target_exists,
			'target_empty'                 => $target_empty,
			'free_bytes'                   => false === $free_bytes ? null : (int) $free_bytes,
			'required_bytes'               => null === $required_bytes ? null : max( 0, $required_bytes ),
			'blockers'                     => $blockers,
			'advisories'                   => $advisories,
			'mutations_performed'          => false,
		);
	}

	/**
	 * Return source payload roots keyed by class.
	 *
	 * @return array<string,string>
	 */
	private function payload_roots(): array {
		$uploads = wp_get_upload_dir();

		return array(
			'uploads' => wp_normalize_path( $uploads['basedir'] ),
			'plugins' => defined( 'WP_PLUGIN_DIR' ) ? wp_normalize_path( WP_PLUGIN_DIR ) : '',
			'themes'  => wp_normalize_path( get_theme_root() ),
		);
	}

	/**
	 * Normalize one requested absolute destination path without creating it.
	 *
	 * @param string $path Requested target path.
	 */
	private function normalize_target_path( string $path ): string {
		$path = trim( wp_normalize_path( $path ) );
		if ( '' === $path ) {
			return '';
		}

		$real = realpath( $path );
		if ( false !== $real ) {
			return trailingslashit( wp_normalize_path( $real ) );
		}

		$parent_path = realpath( dirname( $path ) );
		if ( false === $parent_path ) {
			return trailingslashit( $path );
		}

		return trailingslashit( wp_normalize_path( $parent_path ) . '/' . basename( $path ) );
	}

	/**
	 * Determine whether a normalized path is absolute.
	 *
	 * @param string $path Normalized path.
	 */
	private function absolute_path( string $path ): bool {
		return 1 === preg_match( '#^(?:[A-Za-z]:/|/)#', $path );
	}

	/**
	 * Compare normalized paths.
	 *
	 * @param string $left  First path.
	 * @param string $right Second path.
	 */
	private function same_path( string $left, string $right ): bool {
		if ( '' === $left || '' === $right ) {
			return false;
		}

		return untrailingslashit( wp_normalize_path( $left ) ) === untrailingslashit( wp_normalize_path( $right ) );
	}

	/**
	 * Determine whether candidate is strictly inside a parent path.
	 *
	 * @param string $candidate   Candidate path.
	 * @param string $parent_path Parent path.
	 */
	private function inside_path( string $candidate, string $parent_path ): bool {
		if ( '' === $candidate || '' === $parent_path || $this->same_path( $candidate, $parent_path ) ) {
			return false;
		}

		$candidate   = trailingslashit( wp_normalize_path( $candidate ) );
		$parent_path = trailingslashit( wp_normalize_path( $parent_path ) );

		return str_starts_with( $candidate, $parent_path );
	}

	/**
	 * Determine whether target is inside any source payload root.
	 *
	 * @param string               $target Target path.
	 * @param array<string,string> $roots  Payload roots.
	 */
	private function inside_any_payload_root( string $target, array $roots ): bool {
		foreach ( $roots as $root ) {
			if ( $this->same_path( $target, $root ) || $this->inside_path( $target, $root ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Return scheme/host/port origin key.
	 *
	 * @param string $url URL to normalize.
	 */
	private function origin_key( string $url ): string {
		$parts = wp_parse_url( $url );
		if ( ! is_array( $parts ) || ! is_string( $parts['scheme'] ?? null ) || ! is_string( $parts['host'] ?? null ) ) {
			return '';
		}

		$origin = strtolower( $parts['scheme'] ) . '://' . strtolower( $parts['host'] );
		if ( isset( $parts['port'] ) ) {
			$origin .= ':' . (string) (int) $parts['port'];
		}

		return $origin;
	}

	/**
	 * Return normalized URL base path.
	 *
	 * @param string $url URL to inspect.
	 */
	private function url_base_path( string $url ): string {
		$path = wp_parse_url( $url, PHP_URL_PATH );
		if ( ! is_string( $path ) || '' === $path ) {
			return '/';
		}

		return trailingslashit( '/' . trim( $path, '/' ) );
	}

	/**
	 * Check whether an existing target directory is empty.
	 *
	 * @param string $path Existing target directory.
	 */
	private function directory_empty( string $path ): ?bool {
		$entries = scandir( $path );
		if ( false === $entries ) {
			return null;
		}

		return array() === array_values( array_diff( $entries, array( '.', '..' ) ) );
	}
}
