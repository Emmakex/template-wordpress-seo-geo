<?php
/**
 * Clean Corporate Home rebuild for reset-first redesigns.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge\Reset;

use WP_Block_Patterns_Registry;
use WP_Error;
use WP_Post;

/**
 * Builds a private Home draft from Theme-owned Corporate patterns only.
 */
final class CleanHomeRebuilder {
	public const SOURCE_ID_META     = '_seo_geo_clean_home_source_id_v1';
	public const SOURCE_SHA_META    = '_seo_geo_clean_home_source_sha256_v1';
	public const SOURCE_PATH_META   = '_seo_geo_clean_home_source_path_v1';
	public const MANIFEST_SHA_META  = '_seo_geo_clean_home_manifest_sha256_v1';
	public const PLAN_SHA_META      = '_seo_geo_clean_home_plan_sha256_v1';
	public const PATTERNS_META      = '_seo_geo_clean_home_patterns_v1';
	public const CREATED_AT_META    = '_seo_geo_clean_home_created_at_v1';
	public const CONTENT_STATE_META = '_seo_geo_clean_home_content_state_v1';

	private const PAGE_KEY = 'home';
	private const PRESET   = 'corporate';

	/**
	 * Construct the clean Home builder.
	 *
	 * @param RescueManifest $manifest Rescue Manifest authority.
	 */
	public function __construct( private RescueManifest $manifest ) {
	}

	/**
	 * Build a read-only clean Home draft plan.
	 *
	 * @return array<string,mixed>
	 */
	public function plan(): array {
		$blockers  = array();
		$saved     = $this->manifest->saved();
		$bootstrap = get_option( CorporateThemeBootstrap::REPORT_OPTION, null );

		if (
			! is_array( $bootstrap )
			|| 'reset-rebuild-corporate-bootstrap' !== ( $bootstrap['mode'] ?? null )
			|| 'completed' !== ( $bootstrap['status'] ?? null )
			|| self::PRESET !== ( $bootstrap['preset'] ?? null )
		) {
			$blockers[] = 'corporate-bootstrap-required';
		}

		if ( CloneResetEngine::TARGET_THEME !== $this->active_stylesheet() ) {
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

		$source_id = is_array( $saved ) ? (int) ( $saved['site']['front_page_id'] ?? 0 ) : 0;
		$source    = 0 < $source_id ? get_post( $source_id ) : null;
		if ( ! $source instanceof WP_Post || 'page' !== $source->post_type ) {
			$blockers[] = 'front-page-source-required';
		}

		$manifest_resource = is_array( $saved ) ? $this->manifest_resource( $saved, $source_id ) : null;
		$expected_sha      = is_array( $manifest_resource ) ? (string) ( $manifest_resource['content_sha256'] ?? '' ) : '';
		$current_sha       = $source instanceof WP_Post ? hash( 'sha256', (string) $source->post_content ) : '';
		if ( '' === $expected_sha || '' === $current_sha || ! hash_equals( $expected_sha, $current_sha ) ) {
			$blockers[] = 'front-page-content-drift';
		}

		$page_definition = null;
		$patterns        = array();
		$composition     = '';
		if ( function_exists( 'seo_geo_theme_preset_document' ) && function_exists( 'seo_geo_theme_preset_locale' ) ) {
			$page_definition = $this->home_definition();
			if ( ! is_array( $page_definition ) ) {
				$blockers[] = 'corporate-home-definition-missing';
			} else {
				$patterns = is_array( $page_definition['patterns'] ?? null )
					? array_values( array_filter( $page_definition['patterns'], 'is_string' ) )
					: array();

				if ( array() === $patterns ) {
					$blockers[] = 'corporate-home-patterns-empty';
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
		}

		foreach ( array( '[et_pb_', 'et_pb_', 'elementor-', '[vc_', 'fusion-builder', 'seo-geo-remap-slot:' ) as $legacy_token ) {
			if ( '' !== $composition && str_contains( strtolower( $composition ), strtolower( $legacy_token ) ) ) {
				$blockers[] = 'legacy-token-in-native-composition:' . sanitize_key( $legacy_token );
			}
		}

		$blockers = array_values( array_unique( $blockers ) );
		sort( $blockers );

		$source_path   = $source instanceof WP_Post ? $this->post_path( $source ) : '';
		$plan_material = array(
			'preset'          => self::PRESET,
			'page_key'        => self::PAGE_KEY,
			'manifest_sha256' => is_array( $saved ) ? (string) ( $saved['manifest_sha256'] ?? '' ) : '',
			'source_id'       => $source_id,
			'source_sha256'   => $current_sha,
			'source_path'     => $source_path,
			'patterns'        => $patterns,
			'composition_sha' => hash( 'sha256', $composition ),
		);
		$plan_sha      = hash(
			'sha256',
			(string) wp_json_encode( $plan_material, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE )
		);

		return array(
			'schema_version'  => 1,
			'mode'            => 'reset-rebuild-clean-home-plan',
			'ready'           => array() === $blockers,
			'blockers'        => $blockers,
			'preset'          => self::PRESET,
			'page_key'        => self::PAGE_KEY,
			'source'          => $source instanceof WP_Post
				? array(
					'id'             => $source_id,
					'title'          => get_the_title( $source ),
					'path'           => $source_path,
					'content_sha256' => $current_sha,
				)
				: null,
			'manifest_sha256' => is_array( $saved ) ? (string) ( $saved['manifest_sha256'] ?? '' ) : '',
			'patterns'        => $patterns,
			'composition'     => $composition,
			'plan_sha256'     => $plan_sha,
			'existing_draft'  => $this->existing_draft_id( $source_id, $plan_sha ),
			'content_state'   => 'preset-scaffold',
			'safety'          => array(
				'legacy_layout_reused'         => false,
				'content_remap_required'       => false,
				'source_post_mutation'         => false,
				'front_page_assignment_change' => false,
				'draft_only'                   => true,
			),
		);
	}

	/**
	 * Create or reuse the clean Corporate Home draft.
	 *
	 * @return array<string,mixed>|WP_Error
	 */
	public function create_draft(): array|WP_Error {
		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error( 'seo_geo_clean_home_forbidden', 'Administrator capability is required.' );
		}

		$plan = $this->plan();
		if ( true !== ( $plan['ready'] ?? false ) ) {
			return new WP_Error(
				'seo_geo_clean_home_not_ready',
				'Clean Home rebuild is blocked: ' . implode( ', ', is_array( $plan['blockers'] ?? null ) ? $plan['blockers'] : array() )
			);
		}

		$existing = (int) ( $plan['existing_draft'] ?? 0 );
		if ( 0 < $existing ) {
			return array(
				'schema_version' => 1,
				'mode'           => 'reset-rebuild-clean-home-draft',
				'status'         => 'existing',
				'draft_id'       => $existing,
				'source_id'      => (int) ( $plan['source']['id'] ?? 0 ),
				'plan_sha256'    => (string) $plan['plan_sha256'],
			);
		}

		$source_id = (int) ( $plan['source']['id'] ?? 0 );
		$source    = get_post( $source_id );
		if ( ! $source instanceof WP_Post ) {
			return new WP_Error( 'seo_geo_clean_home_source_missing', 'The preserved front-page source is unavailable.' );
		}

		$draft_id = wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_status'  => 'draft',
				'post_title'   => get_the_title( $source ),
				'post_name'    => 'seo-geo-clean-home-' . $source_id,
				'post_content' => (string) $plan['composition'],
				'post_parent'  => 0,
			),
			true
		);
		if ( $draft_id instanceof WP_Error ) {
			return $draft_id;
		}

		$meta = array(
			self::SOURCE_ID_META     => $source_id,
			self::SOURCE_SHA_META    => (string) ( $plan['source']['content_sha256'] ?? '' ),
			self::SOURCE_PATH_META   => (string) ( $plan['source']['path'] ?? '' ),
			self::MANIFEST_SHA_META  => (string) $plan['manifest_sha256'],
			self::PLAN_SHA_META      => (string) $plan['plan_sha256'],
			self::PATTERNS_META      => array_values( is_array( $plan['patterns'] ?? null ) ? $plan['patterns'] : array() ),
			self::CREATED_AT_META    => gmdate( DATE_ATOM ),
			self::CONTENT_STATE_META => 'preset-scaffold',
		);
		foreach ( $meta as $key => $value ) {
			update_post_meta( $draft_id, $key, $value );
		}

		return array(
			'schema_version' => 1,
			'mode'           => 'reset-rebuild-clean-home-draft',
			'status'         => 'created',
			'draft_id'       => $draft_id,
			'source_id'      => $source_id,
			'plan_sha256'    => (string) $plan['plan_sha256'],
			'content_state'  => 'preset-scaffold',
			'safety'         => array(
				'source_unchanged'        => hash_equals(
					(string) ( $plan['source']['content_sha256'] ?? '' ),
					hash( 'sha256', (string) get_post_field( 'post_content', $source_id ) )
				),
				'front_page_id_unchanged' => (int) get_option( 'page_on_front', 0 ) === $source_id,
				'draft_only'              => 'draft' === get_post_status( $draft_id ),
			),
		);
	}

	/**
	 * Resolve the Corporate Home definition for the active locale.
	 *
	 * @return array<string,mixed>|null
	 */
	private function home_definition(): ?array {
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
			if ( is_array( $page ) && self::PAGE_KEY === ( $page['key'] ?? null ) ) {
				return $page;
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
	 * Find the front-page resource captured by the Rescue Manifest.
	 *
	 * @param array<string,mixed> $manifest Saved Rescue Manifest.
	 * @param int                 $post_id  Front-page post ID.
	 * @return array<string,mixed>|null
	 */
	private function manifest_resource( array $manifest, int $post_id ): ?array {
		foreach ( is_array( $manifest['resources'] ?? null ) ? $manifest['resources'] : array() as $resource ) {
			if ( is_array( $resource ) && (int) ( $resource['id'] ?? 0 ) === $post_id ) {
				return $resource;
			}
		}

		return null;
	}

	/**
	 * Resolve an equivalent clean Home draft.
	 *
	 * @param int    $source_id Preserved front-page post ID.
	 * @param string $plan_sha  Clean Home plan SHA-256.
	 */
	private function existing_draft_id( int $source_id, string $plan_sha ): int {
		if ( 0 >= $source_id ) {
			return 0;
		}

		$ids = get_posts(
			array(
				'post_type'              => 'page',
				'post_status'            => array( 'draft', 'pending', 'private' ),
				'posts_per_page'         => 20,
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
				(int) get_post_meta( $post_id, self::SOURCE_ID_META, true ) === $source_id
				&& (string) get_post_meta( $post_id, self::PLAN_SHA_META, true ) === $plan_sha
			) {
				return $post_id;
			}
		}

		return 0;
	}

	/**
	 * Return one post's public path.
	 *
	 * @param WP_Post $post Preserved front-page post.
	 */
	private function post_path( WP_Post $post ): string {
		$path = wp_parse_url( get_permalink( $post ), PHP_URL_PATH );

		return is_string( $path ) && '' !== $path ? $path : '/';
	}

	/**
	 * Return active Theme stylesheet.
	 */
	private function active_stylesheet(): string {
		$value = get_option( 'stylesheet', '' );

		return is_string( $value ) ? $value : '';
	}
}
