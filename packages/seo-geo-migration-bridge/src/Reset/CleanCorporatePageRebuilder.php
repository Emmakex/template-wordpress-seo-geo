<?php
/**
 * Clean Corporate inner-page rebuild for reset-first redesigns.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge\Reset;

use WP_Block_Patterns_Registry;
use WP_Error;
use WP_Post;

/**
 * Builds private native drafts for Corporate inner pages without reusing legacy layout.
 */
final class CleanCorporatePageRebuilder {
	public const SOURCE_MAP_OPTION = 'seo_geo_clean_page_source_map_v1';
	public const PAGE_KEY_META     = '_seo_geo_clean_page_key_v1';
	public const SOURCE_ID_META    = '_seo_geo_clean_page_source_id_v1';
	public const SOURCE_SHA_META   = '_seo_geo_clean_page_source_sha256_v1';
	public const SOURCE_PATH_META  = '_seo_geo_clean_page_source_path_v1';
	public const MANIFEST_SHA_META = '_seo_geo_clean_page_manifest_sha256_v1';
	public const PLAN_SHA_META     = '_seo_geo_clean_page_plan_sha256_v1';
	public const PATTERNS_META     = '_seo_geo_clean_page_patterns_v1';
	public const CREATED_AT_META   = '_seo_geo_clean_page_created_at_v1';
	public const CONTENT_STATE_META = '_seo_geo_clean_page_content_state_v1';
	public const CONTENT_STATE      = 'preset-scaffold';

	private const PRESET = 'corporate';

	/**
	 * Construct the clean Corporate inner-page builder.
	 *
	 * @param RescueManifest $manifest Rescue Manifest authority.
	 */
	public function __construct( private RescueManifest $manifest ) {
	}

	/**
	 * Build a read-only clean inner-page plan.
	 *
	 * @param string $page_key  Corporate page key.
	 * @param int    $source_id Explicit rescued source page ID. Zero uses the saved binding.
	 * @return array<string,mixed>
	 */
	public function plan( string $page_key, int $source_id = 0 ): array {
		$page_key = sanitize_key( $page_key );
		$blockers = array();
		$saved    = $this->manifest->saved();

		if ( 'home' === $page_key ) {
			$blockers[] = 'home-has-dedicated-rebuilder';
		}
		if ( '' === $page_key ) {
			$blockers[] = 'page-key-required';
		}

		$bootstrap = get_option( CorporateThemeBootstrap::REPORT_OPTION, null );
		if (
			! is_array( $bootstrap )
			|| 'reset-rebuild-corporate-bootstrap' !== ( $bootstrap['mode'] ?? null )
			|| 'completed' !== ( $bootstrap['status'] ?? null )
			|| self::PRESET !== ( $bootstrap['preset'] ?? null )
		) {
			$blockers[] = 'corporate-bootstrap-required';
		}

		if ( CloneResetEngine::TARGET_THEME !== get_stylesheet() ) {
			$blockers[] = 'seo-geo-theme-not-active';
		}
		if (
			! function_exists( 'seo_geo_theme_active_preset_id' )
			|| self::PRESET !== \seo_geo_theme_active_preset_id()
		) {
			$blockers[] = 'corporate-preset-not-active';
		}
		if ( ! function_exists( 'seo_geo_theme_preset_document' ) || ! function_exists( 'seo_geo_theme_preset_locale' ) ) {
			$blockers[] = 'theme-preset-runtime-unavailable';
		}
		if ( ! is_array( $saved ) || '' === (string) ( $saved['manifest_sha256'] ?? '' ) ) {
			$blockers[] = 'rescue-manifest-required';
		}

		$source_id = 0 < $source_id ? $source_id : $this->bound_source_id( $page_key );
		if ( 0 >= $source_id ) {
			$blockers[] = 'source-page-selection-required';
		}

		$front_page_id = is_array( $saved ) ? (int) ( $saved['site']['front_page_id'] ?? 0 ) : 0;
		if ( 0 < $source_id && $source_id === $front_page_id ) {
			$blockers[] = 'inner-page-source-cannot-be-front-page';
		}

		$resource = is_array( $saved ) ? $this->manifest_resource( $saved, $source_id ) : null;
		if (
			! is_array( $resource )
			|| 'page' !== (string) ( $resource['post_type'] ?? '' )
			|| 'publish' !== (string) ( $resource['status'] ?? '' )
		) {
			$blockers[] = 'rescued-published-page-required';
		}

		$source = 0 < $source_id ? get_post( $source_id ) : null;
		if ( ! $source instanceof WP_Post || 'page' !== $source->post_type || 'publish' !== $source->post_status ) {
			$blockers[] = 'source-page-required';
		}

		$expected_sha = is_array( $resource ) ? (string) ( $resource['content_sha256'] ?? '' ) : '';
		$current_sha  = $source instanceof WP_Post ? hash( 'sha256', (string) $source->post_content ) : '';
		if ( '' === $expected_sha || '' === $current_sha || ! hash_equals( $expected_sha, $current_sha ) ) {
			$blockers[] = 'source-page-content-drift';
		}

		$page_definition = $this->page_definition( $page_key );
		$patterns        = array();
		$composition     = '';
		if ( ! is_array( $page_definition ) ) {
			$blockers[] = 'corporate-page-definition-missing';
		} else {
			$patterns = is_array( $page_definition['patterns'] ?? null )
				? array_values( array_filter( $page_definition['patterns'], 'is_string' ) )
				: array();

			if ( array() === $patterns ) {
				$blockers[] = 'corporate-page-patterns-empty';
			}

			$parts = array();
			foreach ( $patterns as $pattern_slug ) {
				$pattern_content = $this->registered_pattern_content( $pattern_slug );
				if ( null === $pattern_content ) {
					$blockers[] = 'pattern-unavailable:' . $pattern_slug;
					continue;
				}
				$parts[] = $pattern_content;
			}
			$composition = implode( "\n\n", $parts );
		}

		foreach ( array( '[et_pb_', 'et_pb_', 'elementor-', '[vc_', 'fusion-builder', 'seo-geo-remap-slot:' ) as $legacy_token ) {
			if ( '' !== $composition && str_contains( strtolower( $composition ), strtolower( $legacy_token ) ) ) {
				$blockers[] = 'legacy-token-in-native-composition:' . sanitize_key( $legacy_token );
			}
		}

		$blockers = array_values( array_unique( $blockers ) );
		sort( $blockers );

		$source_path = is_array( $resource ) ? (string) ( $resource['path'] ?? '' ) : '';
		$preset_slug = is_array( $page_definition ) ? (string) ( $page_definition['slug'] ?? '' ) : '';
		$material    = array(
			'preset'          => self::PRESET,
			'page_key'        => $page_key,
			'manifest_sha256' => is_array( $saved ) ? (string) ( $saved['manifest_sha256'] ?? '' ) : '',
			'source_id'       => $source_id,
			'source_sha256'   => $current_sha,
			'source_path'     => $source_path,
			'preset_slug'     => $preset_slug,
			'patterns'        => $patterns,
			'composition_sha' => hash( 'sha256', $composition ),
		);
		$plan_sha    = hash(
			'sha256',
			(string) wp_json_encode( $material, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE )
		);

		return array(
			'schema_version'  => 1,
			'mode'            => 'reset-rebuild-clean-corporate-page-plan',
			'ready'           => array() === $blockers,
			'blockers'        => $blockers,
			'preset'          => self::PRESET,
			'page_key'        => $page_key,
			'definition'      => is_array( $page_definition )
				? array(
					'title' => (string) ( $page_definition['title'] ?? '' ),
					'slug'  => $preset_slug,
					'role'  => (string) ( $page_definition['role'] ?? '' ),
				)
				: null,
			'source'          => $source instanceof WP_Post
				? array(
					'id'             => $source_id,
					'title'          => get_the_title( $source ),
					'slug'           => (string) $source->post_name,
					'path'           => $source_path,
					'content_sha256' => $current_sha,
				)
				: null,
			'manifest_sha256' => is_array( $saved ) ? (string) ( $saved['manifest_sha256'] ?? '' ) : '',
			'patterns'        => $patterns,
			'composition'     => $composition,
			'plan_sha256'     => $plan_sha,
			'existing_draft'  => $this->existing_draft_id( $page_key, $source_id, $plan_sha ),
			'content_state'   => self::CONTENT_STATE,
			'safety'          => array(
				'legacy_layout_reused'       => false,
				'content_remap_required'     => false,
				'source_post_mutation'       => false,
				'source_url_change'          => false,
				'front_page_assignment_change' => false,
				'draft_only'                 => true,
			),
		);
	}

	/**
	 * Create or reuse one clean Corporate inner-page draft.
	 *
	 * @param string $page_key  Corporate page key.
	 * @param int    $source_id Explicit rescued source page ID.
	 * @return array<string,mixed>|WP_Error
	 */
	public function create_draft( string $page_key, int $source_id ): array|WP_Error {
		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error( 'seo_geo_clean_page_forbidden', 'Administrator capability is required.' );
		}

		$page_key = sanitize_key( $page_key );
		$plan     = $this->plan( $page_key, $source_id );
		if ( true !== ( $plan['ready'] ?? false ) ) {
			return new WP_Error(
				'seo_geo_clean_page_not_ready',
				'Clean Corporate page rebuild is blocked: ' . implode( ', ', is_array( $plan['blockers'] ?? null ) ? $plan['blockers'] : array() )
			);
		}

		$existing = (int) ( $plan['existing_draft'] ?? 0 );
		if ( 0 < $existing ) {
			$this->save_source_binding( $page_key, $source_id );

			return array(
				'schema_version' => 1,
				'mode'           => 'reset-rebuild-clean-corporate-page-draft',
				'status'         => 'existing',
				'page_key'       => $page_key,
				'draft_id'       => $existing,
				'source_id'      => $source_id,
				'plan_sha256'    => (string) $plan['plan_sha256'],
			);
		}

		$source = get_post( $source_id );
		if ( ! $source instanceof WP_Post ) {
			return new WP_Error( 'seo_geo_clean_page_source_missing', 'The preserved source page is unavailable.' );
		}

		$front_before   = (int) get_option( 'page_on_front', 0 );
		$plugins_before = $this->active_plugins();
		$source_before  = (string) $source->post_content;
		$path_before    = $this->post_path( $source );

		$draft_id = wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_status'  => 'draft',
				'post_title'   => get_the_title( $source ),
				'post_name'    => 'seo-geo-clean-' . $page_key . '-' . $source_id,
				'post_content' => (string) $plan['composition'],
				'post_parent'  => 0,
			),
			true
		);
		if ( $draft_id instanceof WP_Error ) {
			return $draft_id;
		}

		$meta = array(
			self::PAGE_KEY_META      => $page_key,
			self::SOURCE_ID_META     => $source_id,
			self::SOURCE_SHA_META    => (string) ( $plan['source']['content_sha256'] ?? '' ),
			self::SOURCE_PATH_META   => (string) ( $plan['source']['path'] ?? '' ),
			self::MANIFEST_SHA_META  => (string) $plan['manifest_sha256'],
			self::PLAN_SHA_META      => (string) $plan['plan_sha256'],
			self::PATTERNS_META      => array_values( is_array( $plan['patterns'] ?? null ) ? $plan['patterns'] : array() ),
			self::CREATED_AT_META    => gmdate( DATE_ATOM ),
			self::CONTENT_STATE_META => self::CONTENT_STATE,
		);
		foreach ( $meta as $key => $value ) {
			update_post_meta( $draft_id, $key, $value );
		}

		$this->save_source_binding( $page_key, $source_id );

		$source_after = get_post( $source_id );

		return array(
			'schema_version' => 1,
			'mode'           => 'reset-rebuild-clean-corporate-page-draft',
			'status'         => 'created',
			'page_key'       => $page_key,
			'draft_id'       => $draft_id,
			'source_id'      => $source_id,
			'plan_sha256'    => (string) $plan['plan_sha256'],
			'content_state'  => self::CONTENT_STATE,
			'safety'         => array(
				'source_unchanged'        => $source_after instanceof WP_Post
					&& hash_equals( hash( 'sha256', $source_before ), hash( 'sha256', (string) $source_after->post_content ) ),
				'source_path_unchanged'   => $source_after instanceof WP_Post
					&& $path_before === $this->post_path( $source_after ),
				'front_page_id_unchanged' => $front_before === (int) get_option( 'page_on_front', 0 ),
				'plugins_unchanged'       => $plugins_before === $this->active_plugins(),
				'draft_only'              => 'draft' === get_post_status( $draft_id ),
			),
		);
	}

	/**
	 * Return rescued published page candidates for explicit inner-page mapping.
	 *
	 * @return array
	 * @phpstan-return list<array{id:int,title:string,slug:string,path:string}>
	 */
	public function source_candidates(): array {
		$manifest = $this->manifest->saved();
		if ( ! is_array( $manifest ) ) {
			return array();
		}

		$front_page_id = (int) ( $manifest['site']['front_page_id'] ?? 0 );
		$candidates    = array();

		foreach ( is_array( $manifest['resources'] ?? null ) ? $manifest['resources'] : array() as $resource ) {
			if (
				! is_array( $resource )
				|| 'page' !== (string) ( $resource['post_type'] ?? '' )
				|| 'publish' !== (string) ( $resource['status'] ?? '' )
				|| $front_page_id === (int) ( $resource['id'] ?? 0 )
			) {
				continue;
			}

			$candidates[] = array(
				'id'    => (int) ( $resource['id'] ?? 0 ),
				'title' => (string) ( $resource['title'] ?? '' ),
				'slug'  => (string) ( $resource['slug'] ?? '' ),
				'path'  => (string) ( $resource['path'] ?? '' ),
			);
		}

		usort(
			$candidates,
			static fn( array $left, array $right ): int => strcmp( (string) $left['path'], (string) $right['path'] )
		);

		return $candidates;
	}

	/**
	 * Return the saved source binding for one Corporate page key.
	 *
	 * @param string $page_key Corporate page key.
	 */
	public function bound_source_id( string $page_key ): int {
		$map = get_option( self::SOURCE_MAP_OPTION, array() );
		if ( ! is_array( $map ) ) {
			return 0;
		}

		return (int) ( $map[ sanitize_key( $page_key ) ] ?? 0 );
	}

	/**
	 * Resolve one locale-aware Corporate page definition.
	 *
	 * @param string $page_key Corporate page key.
	 * @return array<string,mixed>|null
	 */
	private function page_definition( string $page_key ): ?array {
		if ( ! function_exists( 'seo_geo_theme_preset_document' ) || ! function_exists( 'seo_geo_theme_preset_locale' ) ) {
			return null;
		}

		$document = \seo_geo_theme_preset_document( self::PRESET, 'content-map.json' );
		if ( ! is_array( $document ) ) {
			return null;
		}

		$locale = \seo_geo_theme_preset_locale();
		$pages  = $document['locales'][ $locale ]['pages'] ?? $document['locales']['en_US']['pages'] ?? null;
		if ( ! is_array( $pages ) ) {
			return null;
		}

		foreach ( $pages as $page ) {
			if ( is_array( $page ) && $page_key === (string) ( $page['key'] ?? '' ) ) {
				return $page;
			}
		}

		return null;
	}

	/**
	 * Find one resource captured by the Rescue Manifest.
	 *
	 * @param array<string,mixed> $manifest Saved Rescue Manifest.
	 * @param int                 $post_id  Source post ID.
	 * @return array<string,mixed>|null
	 */
	private function manifest_resource( array $manifest, int $post_id ): ?array {
		foreach ( is_array( $manifest['resources'] ?? null ) ? $manifest['resources'] : array() as $resource ) {
			if ( is_array( $resource ) && $post_id === (int) ( $resource['id'] ?? 0 ) ) {
				return $resource;
			}
		}

		return null;
	}

	/**
	 * Resolve one Theme-registered block pattern.
	 *
	 * @param string $slug Pattern slug.
	 */
	private function registered_pattern_content( string $slug ): ?string {
		$pattern = WP_Block_Patterns_Registry::get_instance()->get_registered( $slug );
		if ( ! is_array( $pattern ) || ! is_string( $pattern['content'] ?? null ) ) {
			return null;
		}

		$content = trim( $pattern['content'] );

		return '' !== $content ? $content : null;
	}

	/**
	 * Find an equivalent private draft.
	 *
	 * @param string $page_key  Corporate page key.
	 * @param int    $source_id Preserved source page ID.
	 * @param string $plan_sha  Clean page plan SHA-256.
	 */
	private function existing_draft_id( string $page_key, int $source_id, string $plan_sha ): int {
		if ( 0 >= $source_id || '' === $page_key ) {
			return 0;
		}

		$ids = get_posts(
			array(
				'post_type'              => 'page',
				'post_status'            => array( 'draft', 'pending', 'private' ),
				'posts_per_page'         => 50,
				'fields'                 => 'ids',
				'orderby'                => 'ID',
				'order'                  => 'DESC',
				'no_found_rows'          => true,
				'update_post_meta_cache' => true,
				'update_post_term_cache' => false,
			)
		);

		foreach ( $ids as $post_id ) {
			$post_id = (int) $post_id;
			if (
				(string) get_post_meta( $post_id, self::PAGE_KEY_META, true ) === $page_key
				&& (int) get_post_meta( $post_id, self::SOURCE_ID_META, true ) === $source_id
				&& (string) get_post_meta( $post_id, self::PLAN_SHA_META, true ) === $plan_sha
			) {
				return $post_id;
			}
		}

		return 0;
	}

	/**
	 * Persist an explicit page-key to rescued-source binding.
	 *
	 * @param string $page_key  Corporate page key.
	 * @param int    $source_id Rescued source page ID.
	 */
	private function save_source_binding( string $page_key, int $source_id ): void {
		$map = get_option( self::SOURCE_MAP_OPTION, array() );
		$map = is_array( $map ) ? $map : array();

		$map[ $page_key ] = $source_id;
		ksort( $map );

		update_option( self::SOURCE_MAP_OPTION, $map, false );
	}

	/**
	 * Return one post's public path.
	 *
	 * @param WP_Post $post Preserved source page.
	 */
	private function post_path( WP_Post $post ): string {
		$path = wp_parse_url( get_permalink( $post ), PHP_URL_PATH );

		return is_string( $path ) && '' !== $path ? $path : '/';
	}

	/**
	 * Return the active plugin set in deterministic order.
	 *
	 * @return array
	 * @phpstan-return list<string>
	 */
	private function active_plugins(): array {
		$plugins = array_values( array_map( 'strval', get_option( 'active_plugins', array() ) ) );
		sort( $plugins );

		return $plugins;
	}
}
