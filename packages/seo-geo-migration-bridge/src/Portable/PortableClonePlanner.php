<?php
/**
 * Provider-neutral portable clone planning.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge\Portable;

use SeoGeo\MigrationBridge\BaselineSnapshotStore;
use SeoGeo\MigrationBridge\DependencyGraphBuilder;
use SeoGeo\MigrationBridge\Review\DependencyReviewStore;
use SeoGeo\MigrationBridge\SiteAnalyzer;
use wpdb;

/**
 * Builds a non-mutating plan for a same-host subdirectory clone.
 */
final class PortableClonePlanner {
	/**
	 * WordPress database connection.
	 *
	 * @var wpdb
	 */
	private wpdb $db;

	/**
	 * Construct the planner.
	 *
	 * @param wpdb|null $db Optional database connection.
	 */
	public function __construct( ?wpdb $db = null ) {
		global $wpdb;

		$this->db = $db ?? $wpdb;
	}

	/**
	 * Build a safe local-subdirectory clone plan without creating files/tables.
	 *
	 * @param string $directory Target directory name relative to ABSPATH.
	 * @return array<string,mixed>
	 */
	public function local_subdirectory( string $directory ): array {
		$directory    = trim( $directory );
		$source_root  = wp_normalize_path( untrailingslashit( ABSPATH ) );
		$target_path  = '' !== $directory ? wp_normalize_path( $source_root . '/' . $directory ) : '';
		$target_home  = '' !== $directory ? trailingslashit( home_url( '/' . $directory . '/' ) ) : '';
		$table_prefix = $this->target_table_prefix( $directory );
		$blockers     = array();

		if ( ! $this->valid_directory( $directory ) ) {
			$blockers[] = 'target-directory-invalid';
		}

		if ( in_array( strtolower( $directory ), array( 'wp-admin', 'wp-content', 'wp-includes' ), true ) ) {
			$blockers[] = 'target-directory-reserved';
		}

		if ( '' !== $target_path && $source_root === $target_path ) {
			$blockers[] = 'target-path-equals-source';
		}

		if ( '' !== $target_path && is_link( $target_path ) ) {
			$blockers[] = 'target-path-symlink-not-supported';
		}

		$target_exists = '' !== $target_path && file_exists( $target_path );
		$target_empty  = ! $target_exists || $this->directory_empty( $target_path );

		if ( $target_exists && ! is_dir( $target_path ) ) {
			$blockers[] = 'target-path-not-directory';
		} elseif ( $target_exists && ! $target_empty ) {
			$blockers[] = 'target-directory-not-empty';
		}

		if ( ! wp_is_writable( $source_root ) ) {
			$blockers[] = 'source-root-not-writable';
		}

		if ( '' !== $table_prefix && $this->table_prefix_exists( $table_prefix ) ) {
			$blockers[] = 'target-table-prefix-exists';
		}

		$baseline = ( new BaselineSnapshotStore() )->latest();
		if ( null === $baseline ) {
			$blockers[] = 'baseline-missing';
		}

		$review = $this->review_status( $baseline );
		if ( ! $review['complete'] ) {
			$blockers[] = 'dependency-review-incomplete';
		}

		return array(
			'schema_version' => 1,
			'mode'           => 'local-subdirectory',
			'ready'          => array() === $blockers,
			'blockers'       => array_values( array_unique( $blockers ) ),
			'source'         => array(
				'home_url'     => trailingslashit( home_url( '/' ) ),
				'root_path'    => $source_root,
				'table_prefix' => $this->db->prefix,
			),
			'target'         => array(
				'directory'    => $directory,
				'home_url'     => $target_home,
				'root_path'    => $target_path,
				'table_prefix' => $table_prefix,
				'exists'       => $target_exists,
				'empty'        => $target_empty,
			),
			'review' => $review,
			'safety' => array(
				'production_mutation_allowed'   => false,
				'destructive_overwrite_allowed' => false,
				'target_must_be_empty'          => true,
				'separate_table_prefix'         => $table_prefix !== $this->db->prefix,
				'credentials_exported'          => false,
			),
		);
	}

	/**
	 * Validate one simple directory name.
	 *
	 * @param string $directory Directory name.
	 */
	private function valid_directory( string $directory ): bool {
		return 1 === preg_match( '/^[A-Za-z0-9][A-Za-z0-9_-]{0,47}$/', $directory );
	}

	/**
	 * Return whether a directory contains no entries.
	 *
	 * @param string $path Absolute directory path.
	 */
	private function directory_empty( string $path ): bool {
		if ( ! is_dir( $path ) ) {
			return false;
		}

		$entries = scandir( $path );
		if ( false === $entries ) {
			return false;
		}

		return array() === array_values( array_diff( $entries, array( '.', '..' ) ) );
	}

	/**
	 * Build a deterministic isolated table-prefix candidate.
	 *
	 * @param string $directory Target directory.
	 */
	private function target_table_prefix( string $directory ): string {
		$seed   = trailingslashit( home_url( '/' ) ) . '|' . $directory;
		$suffix = substr( hash( 'sha256', $seed ), 0, 8 );
		$base   = preg_replace( '/[^A-Za-z0-9_]/', '_', $this->db->prefix );
		$base   = is_string( $base ) ? substr( $base, 0, 24 ) : 'wp_';

		return $base . 'sg_' . $suffix . '_';
	}

	/**
	 * Return whether target tables already exist for a prefix.
	 *
	 * @param string $prefix Target table prefix.
	 */
	private function table_prefix_exists( string $prefix ): bool {
		if ( '' === $prefix ) {
			return true;
		}

		$like   = $this->db->esc_like( $prefix ) . '%';
		$query  = $this->db->prepare( 'SHOW TABLES LIKE %s', $like );
		$result = $this->db->get_var( $query );

		return is_string( $result ) && '' !== $result;
	}

	/**
	 * Return bounded review completeness for current UNKNOWN components.
	 *
	 * @param array<string,mixed>|null $baseline Baseline envelope.
	 * @return array{reviewed_unknown:int,unreviewed_unknown:int,complete:bool}
	 */
	private function review_status( ?array $baseline ): array {
		if ( null === $baseline ) {
			return array(
				'reviewed_unknown'   => 0,
				'unreviewed_unknown' => 0,
				'complete'           => false,
			);
		}

		$snapshot   = is_array( $baseline['snapshot'] ?? null ) ? $baseline['snapshot'] : null;
		$analysis   = ( new SiteAnalyzer() )->analyze();
		$graph      = ( new DependencyGraphBuilder() )->build( $analysis, $snapshot );
		$components = is_array( $graph['components'] ?? null ) ? $graph['components'] : array();
		$reviews    = new DependencyReviewStore();
		$reviewed   = 0;
		$unreviewed = 0;

		foreach ( $components as $component ) {
			if ( ! is_array( $component ) || 'UNKNOWN' !== ( $component['classification'] ?? null ) ) {
				continue;
			}

			$component_id = $component['component_id'] ?? null;
			if ( is_string( $component_id ) && is_array( $reviews->decision_for( $component_id ) ) ) {
				++$reviewed;
			} else {
				++$unreviewed;
			}
		}

		return array(
			'reviewed_unknown'   => $reviewed,
			'unreviewed_unknown' => $unreviewed,
			'complete'           => 0 === $unreviewed,
		);
	}
}
