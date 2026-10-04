<?php
/**
 * Native Corporate Insights/posts-index manager.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge\Reset;

use SeoGeo\Core\Seo\IndexabilityResolver;
use SeoGeo\Core\Seo\NativeSeoMetadata;
use SeoGeo\MigrationBridge\Sandbox\SandboxGuard;
use WP_Block_Type_Registry;
use WP_Error;
use WP_Post;

/**
 * Reuses a rescued page URL as WordPress's dynamic posts index without legacy layout rendering.
 */
final class CorporateInsightsManager {
	public const SOURCE_OPTION = 'seo_geo_corporate_insights_source_v1';
	public const BACKUP_OPTION = 'seo_geo_corporate_insights_backup_v1';
	public const REPORT_OPTION = 'seo_geo_corporate_insights_report_v1';

	/** Construct the Insights manager. */
	public function __construct( private RescueManifest $manifest ) {
	}

	/**
	 * Build a non-destructive Insights plan.
	 *
	 * @param int $source_id Explicit rescued source page ID; zero reuses binding.
	 * @return array<string,mixed>
	 */
	public function plan( int $source_id = 0 ): array {
		$blockers = array();
		$manifest = $this->manifest->saved();
		$bound    = (int) get_option( self::SOURCE_OPTION, 0 );

		if ( 0 < $source_id && 0 < $bound && $source_id !== $bound ) {
			$blockers[] = 'insights-source-binding-conflict';
		}
		$source_id = 0 < $source_id ? $source_id : $bound;
		if ( 0 >= $source_id ) {
			$blockers[] = 'insights-source-selection-required';
		}

		if ( CloneResetEngine::TARGET_THEME !== get_stylesheet() ) {
			$blockers[] = 'seo-geo-theme-not-active';
		}
		if ( ! function_exists( 'seo_geo_theme_active_preset_id' ) || 'corporate' !== \seo_geo_theme_active_preset_id() ) {
			$blockers[] = 'corporate-preset-not-active';
		}
		if ( 'page' !== (string) get_option( 'show_on_front', 'posts' ) || 0 >= (int) get_option( 'page_on_front', 0 ) ) {
			$blockers[] = 'static-front-page-required';
		}
		if ( 0 < $source_id && $source_id === (int) get_option( 'page_on_front', 0 ) ) {
			$blockers[] = 'insights-source-cannot-be-front-page';
		}

		$resource = is_array( $manifest ) ? $this->resource( $manifest, $source_id ) : null;
		$source   = 0 < $source_id ? get_post( $source_id ) : null;
		if (
			! is_array( $resource )
			|| ! $source instanceof WP_Post
			|| 'page' !== $source->post_type
			|| 'publish' !== $source->post_status
		) {
			$blockers[] = 'rescued-published-insights-page-required';
		}

		$expected_sha = is_array( $resource ) ? (string) ( $resource['content_sha256'] ?? '' ) : '';
		$current_sha  = $source instanceof WP_Post ? hash( 'sha256', (string) $source->post_content ) : '';
		if ( '' === $expected_sha || '' === $current_sha || ! hash_equals( $expected_sha, $current_sha ) ) {
			$blockers[] = 'insights-source-content-drift';
		}
		$source_path = is_array( $resource ) ? (string) ( $resource['path'] ?? '' ) : '';
		if ( ! $this->source_path_matches( $source_id, $source_path ) ) {
			$blockers[] = 'insights-source-path-drift';
		}

		$posts_page = (int) get_option( 'page_for_posts', 0 );
		if ( 0 < $posts_page && 0 < $source_id && $posts_page !== $source_id ) {
			$blockers[] = 'existing-posts-page-conflict';
		}

		$template = get_template_directory() . '/templates/home.html';
		if ( ! is_readable( $template ) ) {
			$blockers[] = 'native-insights-template-required';
		}

		$seo          = is_array( $resource['seo'] ?? null ) ? $resource['seo'] : array();
		$provider     = $this->provider( $seo );
		$review_items = array();
		if ( 'ambiguous' === $provider ) {
			$review_items[] = 'multiple-legacy-seo-providers';
		}
		$title       = $this->plain_provider_value( $seo, $provider, 'title', $review_items );
		$description = $this->plain_provider_value( $seo, $provider, 'description', $review_items );
		$canonical   = $this->provider_value( $seo, $provider, 'canonical' );
		if ( null !== $canonical && ! $this->is_self_canonical( $canonical, $source_path, $manifest ) ) {
			$review_items[] = 'custom-canonical-review';
		}
		$indexability = $this->indexability( $seo, $provider );

		$blockers     = array_values( array_unique( $blockers ) );
		$review_items = array_values( array_unique( $review_items ) );
		sort( $blockers );
		sort( $review_items );

		$material = array(
			'schema_version' => 1,
			'mode'           => 'corporate-insights-native-index-plan',
			'source_id'      => $source_id,
			'source_path'    => $source_path,
			'source_sha256'  => $current_sha,
			'provider'       => $provider,
			'overrides'      => array(
				'title'        => $title,
				'description'  => $description,
				'indexability' => $indexability,
			),
			'review_items'   => $review_items,
		);

		return array_merge(
			$material,
			array(
				'ready'           => array() === $blockers,
				'blockers'        => $blockers,
				'review_required' => array() !== $review_items,
				'plan_sha256'     => hash(
					'sha256',
					(string) wp_json_encode( $material, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE )
				),
			)
		);
	}

	/**
	 * Assign the rescued page as dynamic posts page and translate safe SEO metadata.
	 *
	 * @param int $source_id Explicit rescued source page ID.
	 * @return array<string,mixed>|WP_Error
	 */
	public function apply( int $source_id ): array|WP_Error {
		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error( 'seo_geo_insights_forbidden', 'Administrator capability is required.' );
		}
		if ( ! SandboxGuard::enabled() ) {
			return new WP_Error( 'seo_geo_insights_sandbox_required', 'Insights assignment is allowed only in the marked sandbox clone.' );
		}

		$plan = $this->plan( $source_id );
		if ( true !== ( $plan['ready'] ?? false ) ) {
			return new WP_Error(
				'seo_geo_insights_not_ready',
				'Insights native index is blocked: ' . implode( ', ', is_array( $plan['blockers'] ?? null ) ? $plan['blockers'] : array() )
			);
		}

		$existing = get_option( self::REPORT_OPTION, null );
		if ( is_array( $existing ) && hash_equals( (string) ( $existing['plan_sha256'] ?? '' ), (string) $plan['plan_sha256'] ) ) {
			$existing['status'] = 'existing';

			return $existing;
		}

		if ( ! is_array( get_option( self::BACKUP_OPTION, null ) ) ) {
			update_option(
				self::BACKUP_OPTION,
				array(
					'page_for_posts' => (int) get_option( 'page_for_posts', 0 ),
					'meta'           => $this->native_meta_backup( $source_id ),
				),
				false
			);
		}

		update_option( self::SOURCE_OPTION, $source_id, false );
		update_option( 'page_for_posts', $source_id );
		$overrides = is_array( $plan['overrides'] ?? null ) ? $plan['overrides'] : array();
		$this->write_or_delete( $source_id, NativeSeoMetadata::META_TITLE, $overrides['title'] ?? null );
		$this->write_or_delete( $source_id, NativeSeoMetadata::META_DESCRIPTION, $overrides['description'] ?? null );
		$this->write_or_delete( $source_id, NativeSeoMetadata::META_INDEXABILITY, $overrides['indexability'] ?? null );
		delete_post_meta( $source_id, NativeSeoMetadata::META_CANONICAL );

		$report = array(
			'schema_version'     => 1,
			'mode'               => 'corporate-insights-native-index',
			'status'             => 'applied',
			'source_id'          => $source_id,
			'source_path'        => (string) ( $plan['source_path'] ?? '' ),
			'plan_sha256'        => (string) ( $plan['plan_sha256'] ?? '' ),
			'provider'           => (string) ( $plan['provider'] ?? 'none' ),
			'overrides'          => $overrides,
			'review_items'       => is_array( $plan['review_items'] ?? null ) ? $plan['review_items'] : array(),
			'cutover_seo_ready'  => true !== ( $plan['review_required'] ?? false ),
			'source_unchanged'   => $this->source_matches_manifest( $source_id ),
			'posts_page_applied' => $source_id === (int) get_option( 'page_for_posts', 0 ),
			'applied_at'         => gmdate( DATE_ATOM ),
		);
		$report['report_sha256'] = hash(
			'sha256',
			(string) wp_json_encode( $report, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE )
		);
		update_option( self::REPORT_OPTION, $report, false );

		return $report;
	}

	/**
	 * Restore the previous posts-page/native metadata state.
	 *
	 * @return array<string,mixed>|WP_Error
	 */
	public function rollback(): array|WP_Error {
		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error( 'seo_geo_insights_forbidden', 'Administrator capability is required.' );
		}
		if ( ! SandboxGuard::enabled() ) {
			return new WP_Error( 'seo_geo_insights_sandbox_required', 'Insights rollback is allowed only in the marked sandbox clone.' );
		}

		$backup    = get_option( self::BACKUP_OPTION, null );
		$source_id = (int) get_option( self::SOURCE_OPTION, 0 );
		if ( ! is_array( $backup ) || 0 >= $source_id ) {
			return new WP_Error( 'seo_geo_insights_backup_missing', 'No Insights assignment backup is available.' );
		}

		update_option( 'page_for_posts', (int) ( $backup['page_for_posts'] ?? 0 ) );
		$this->restore_native_meta( $source_id, is_array( $backup['meta'] ?? null ) ? $backup['meta'] : array() );
		delete_option( self::REPORT_OPTION );
		delete_option( self::SOURCE_OPTION );
		delete_option( self::BACKUP_OPTION );

		return array(
			'schema_version' => 1,
			'mode'           => 'corporate-insights-native-index',
			'status'         => 'rolled-back',
			'page_for_posts' => (int) get_option( 'page_for_posts', 0 ),
		);
	}

	/**
	 * Return read-only readiness after apply.
	 *
	 * @return array<string,mixed>
	 */
	public function readiness(): array {
		$source_id = (int) get_option( self::SOURCE_OPTION, 0 );
		$report    = get_option( self::REPORT_OPTION, null );
		$blockers  = array();
		$template  = get_template_directory() . '/templates/home.html';
		$content   = is_readable( $template ) ? (string) file_get_contents( $template ) : '';

		if ( ! SandboxGuard::enabled() ) {
			$blockers[] = 'sandbox-marker-required';
		}
		if ( 0 >= $source_id || $source_id !== (int) get_option( 'page_for_posts', 0 ) ) {
			$blockers[] = 'native-posts-page-assignment-required';
		}
		if ( ! $this->source_matches_manifest( $source_id ) || 'publish' !== get_post_status( $source_id ) ) {
			$blockers[] = 'rescued-insights-source-drift';
		}
		if ( ! is_array( $report ) || true !== ( $report['cutover_seo_ready'] ?? false ) ) {
			$blockers[] = 'insights-seo-review-required';
		}
		if (
			'' === $content
			|| ! str_contains( $content, 'seo-geo/insights-title' )
			|| ! str_contains( $content, 'wp:query' )
			|| ! str_contains( $content, 'wp:post-title' )
			|| ! str_contains( $content, 'wp:post-excerpt' )
			|| ! str_contains( $content, 'wp:query-pagination' )
		) {
			$blockers[] = 'native-insights-template-incomplete';
		}
		$registry = WP_Block_Type_Registry::get_instance();
		if ( ! $registry->is_registered( 'seo-geo/insights-title' ) ) {
			$blockers[] = 'native-insights-title-block-required';
		}

		$blockers = array_values( array_unique( $blockers ) );
		sort( $blockers );
		$material = array(
			'schema_version'        => 1,
			'mode'                  => 'corporate-insights-readiness',
			'source_id'             => $source_id,
			'posts_page_id'         => (int) get_option( 'page_for_posts', 0 ),
			'blockers'              => $blockers,
			'manual_browser_checks' => array(
				'visual-layout',
				'responsive-behavior',
				'accessibility',
				'seo-geo-rendered-output',
				'performance',
			),
		);

		return array_merge(
			$material,
			array(
				'ready_for_browser_qa' => array() === $blockers,
				'report_sha256'        => hash( 'sha256', (string) wp_json_encode( $material, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) ),
			)
		);
	}

	/** Back up provider-neutral native metadata exactly. */
	private function native_meta_backup( int $post_id ): array {
		$keys = array(
			NativeSeoMetadata::META_TITLE,
			NativeSeoMetadata::META_DESCRIPTION,
			NativeSeoMetadata::META_CANONICAL,
			NativeSeoMetadata::META_INDEXABILITY,
		);
		$backup = array();
		foreach ( $keys as $key ) {
			$backup[ $key ] = array(
				'exists' => metadata_exists( 'post', $post_id, $key ),
				'value'  => get_post_meta( $post_id, $key, true ),
			);
		}

		return $backup;
	}

	/** Restore provider-neutral native metadata exactly. */
	private function restore_native_meta( int $post_id, array $backup ): void {
		foreach ( $backup as $key => $state ) {
			if ( ! is_string( $key ) || ! is_array( $state ) ) {
				continue;
			}
			if ( true === ( $state['exists'] ?? false ) ) {
				update_post_meta( $post_id, $key, $state['value'] ?? '' );
			} else {
				delete_post_meta( $post_id, $key );
			}
		}
	}

	/** Resolve captured SEO provider family. */
	private function provider( array $seo ): string {
		$yoast = $this->has_any_key( $seo, array( '_yoast_wpseo_title', '_yoast_wpseo_metadesc', '_yoast_wpseo_canonical', '_yoast_wpseo_meta-robots-noindex', '_yoast_wpseo_meta-robots-nofollow' ) );
		$rank  = $this->has_any_key( $seo, array( 'rank_math_title', 'rank_math_description', 'rank_math_canonical_url', 'rank_math_robots' ) );
		if ( $yoast && $rank ) {
			return 'ambiguous';
		}

		return $rank ? 'rank-math' : ( $yoast ? 'yoast' : 'none' );
	}

	/** Resolve safe plain legacy title/description. */
	private function plain_provider_value( array $seo, string $provider, string $field, array &$review_items ): ?string {
		$value = $this->provider_value( $seo, $provider, $field );
		if ( null === $value ) {
			return null;
		}
		if ( 1 === preg_match( '/%%[^%]+%%|%[a-z0-9_-]+%/i', $value ) ) {
			$review_items[] = 'legacy-' . $field . '-template-review';

			return null;
		}
		$value = sanitize_text_field( $value );

		return '' !== $value ? $value : null;
	}

	/** Resolve one scalar provider value. */
	private function provider_value( array $seo, string $provider, string $field ): ?string {
		$keys = array(
			'yoast'     => array( 'title' => '_yoast_wpseo_title', 'description' => '_yoast_wpseo_metadesc', 'canonical' => '_yoast_wpseo_canonical' ),
			'rank-math' => array( 'title' => 'rank_math_title', 'description' => 'rank_math_description', 'canonical' => 'rank_math_canonical_url' ),
		);
		if ( ! isset( $keys[ $provider ][ $field ] ) ) {
			return null;
		}
		$value = $seo[ $keys[ $provider ][ $field ] ] ?? null;

		return is_scalar( $value ) && '' !== trim( (string) $value ) ? trim( (string) $value ) : null;
	}

	/** Resolve legacy indexability. */
	private function indexability( array $seo, string $provider ): string {
		if ( 'yoast' === $provider ) {
			$noindex  = (string) ( $seo['_yoast_wpseo_meta-robots-noindex'] ?? '' );
			$nofollow = (string) ( $seo['_yoast_wpseo_meta-robots-nofollow'] ?? '' );
			if ( '1' === $noindex && '1' === $nofollow ) {
				return IndexabilityResolver::NOINDEX_NOFOLLOW;
			}
			if ( '1' === $noindex ) {
				return IndexabilityResolver::NOINDEX_FOLLOW;
			}
		}
		if ( 'rank-math' === $provider ) {
			$robots = $seo['rank_math_robots'] ?? array();
			$robots = is_array( $robots ) ? array_map( 'strval', $robots ) : array( (string) $robots );
			if ( in_array( 'noindex', $robots, true ) && in_array( 'nofollow', $robots, true ) ) {
				return IndexabilityResolver::NOINDEX_NOFOLLOW;
			}
			if ( in_array( 'noindex', $robots, true ) ) {
				return IndexabilityResolver::NOINDEX_FOLLOW;
			}
		}

		return IndexabilityResolver::INDEXABLE;
	}

	/** Whether one canonical is the rescued self-canonical. */
	private function is_self_canonical( string $canonical, string $source_path, ?array $manifest ): bool {
		$canonical_host = wp_parse_url( $canonical, PHP_URL_HOST );
		$canonical_path = wp_parse_url( $canonical, PHP_URL_PATH );
		$home_url       = is_array( $manifest ) ? (string) ( $manifest['site']['home_url'] ?? '' ) : '';
		$home_host      = wp_parse_url( $home_url, PHP_URL_HOST );

		return is_string( $canonical_host )
			&& is_string( $home_host )
			&& 0 === strcasecmp( $canonical_host, $home_host )
			&& is_string( $canonical_path )
			&& $this->normalize_path( $canonical_path ) === $this->normalize_path( $source_path );
	}

	/** Write one scalar override or remove it when absent. */
	private function write_or_delete( int $post_id, string $meta_key, mixed $value ): void {
		if ( is_scalar( $value ) && '' !== trim( (string) $value ) ) {
			update_post_meta( $post_id, $meta_key, (string) $value );

			return;
		}
		delete_post_meta( $post_id, $meta_key );
	}

	/** Whether any known provider key exists. */
	private function has_any_key( array $seo, array $keys ): bool {
		foreach ( $keys as $key ) {
			if ( array_key_exists( $key, $seo ) ) {
				return true;
			}
		}

		return false;
	}

	/** Find one resource captured by the Rescue Manifest. */
	private function resource( array $manifest, int $source_id ): ?array {
		foreach ( is_array( $manifest['resources'] ?? null ) ? $manifest['resources'] : array() as $resource ) {
			if ( is_array( $resource ) && (int) ( $resource['id'] ?? 0 ) === $source_id ) {
				return $resource;
			}
		}

		return null;
	}

	/** Confirm source content still matches Rescue Manifest. */
	private function source_matches_manifest( int $source_id ): bool {
		$manifest = $this->manifest->saved();
		$resource = is_array( $manifest ) ? $this->resource( $manifest, $source_id ) : null;
		$content  = 0 < $source_id ? get_post_field( 'post_content', $source_id ) : null;
		$expected = is_array( $resource ) ? (string) ( $resource['content_sha256'] ?? '' ) : '';

		return is_string( $content ) && '' !== $expected && hash_equals( $expected, hash( 'sha256', $content ) );
	}

	/** Confirm source permalink still matches the rescued path. */
	private function source_path_matches( int $source_id, string $expected_path ): bool {
		if ( 0 >= $source_id || '' === $expected_path ) {
			return false;
		}
		$path = wp_parse_url( get_permalink( $source_id ), PHP_URL_PATH );

		return is_string( $path ) && $this->normalize_path( $path ) === $this->normalize_path( $expected_path );
	}

	/** Normalize one URL path. */
	private function normalize_path( string $path ): string {
		$path = '/' . trim( $path, '/' );

		return '/' === $path ? '/' : $path . '/';
	}
}
