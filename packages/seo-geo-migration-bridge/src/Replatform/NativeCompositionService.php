<?php
/**
 * Non-destructive native replatform composition service.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge\Replatform;

use WP_Block_Patterns_Registry;
use WP_Error;
use WP_Post;

/**
 * Builds draft-only Theme/preset-native replacement pages from preserved sources.
 */
final class NativeCompositionService {
	/**
	 * Draft metadata keys.
	 */
	public const SOURCE_ID_META   = '_seo_geo_replatform_source_id_v1';
	public const SOURCE_SHA_META  = '_seo_geo_replatform_source_sha256_v1';
	public const SOURCE_PATH_META = '_seo_geo_replatform_source_path_v1';
	public const PRESET_META      = '_seo_geo_replatform_preset_v1';
	public const PAGE_KEY_META    = '_seo_geo_replatform_page_key_v1';
	public const PLAN_SHA_META    = '_seo_geo_replatform_plan_sha256_v1';
	public const CREATED_AT_META     = '_seo_geo_replatform_created_at_v1';
	public const COMPOSITION_META    = '_seo_geo_replatform_composition_v1';
	public const REMAP_PLAN_SHA_META = '_seo_geo_replatform_remap_plan_sha256_v1';
	public const REMAP_SUMMARY_META  = '_seo_geo_replatform_remap_summary_v1';

	/**
	 * Read-only content remap planner.
	 *
	 * @var ContentRemapPlanner
	 */
	private ContentRemapPlanner $content_remap_planner;

	/**
	 * Construct the native composer.
	 *
	 * @param ContentRemapPlanner|null $content_remap_planner Optional remap planner override.
	 */
	public function __construct( ?ContentRemapPlanner $content_remap_planner = null ) {
		$this->content_remap_planner = $content_remap_planner ?? new ContentRemapPlanner();
	}

	/**
	 * Build a read-only plan for one preset page key.
	 *
	 * @param string      $page_key  Preset page key.
	 * @param string|null $preset_id Optional preset override.
	 * @return array<string,mixed>
	 */
	public function plan( string $page_key, ?string $preset_id = null ): array {
		$page_key  = sanitize_key( $page_key );
		$preset_id = null === $preset_id ? $this->active_preset_id() : sanitize_key( $preset_id );
		$blockers  = array();

		if ( '' === $page_key ) {
			$blockers[] = 'page-key-empty';
		}
		if ( null === $preset_id ) {
			$blockers[] = 'preset-unavailable';
		}
		if ( ! function_exists( 'seo_geo_theme_preset_document' ) ) {
			$blockers[] = 'theme-preset-runtime-unavailable';
		}

		$page = null;
		if ( null !== $preset_id && function_exists( 'seo_geo_theme_preset_document' ) ) {
			$page = $this->page_definition( $preset_id, $page_key );
			if ( null === $page ) {
				$blockers[] = 'page-key-not-in-preset';
			}
		}

		$source = null;
		if ( is_array( $page ) ) {
			$source = $this->resolve_source_post( $page );
			if ( ! $source instanceof WP_Post ) {
				$blockers[] = 'source-page-not-found';
			}
		}

		$patterns = is_array( $page['patterns'] ?? null ) ? array_values( $page['patterns'] ) : array();
		if ( array() === $patterns ) {
			$blockers[] = 'native-composition-empty';
		}

		$pattern_contents = array();
		foreach ( $patterns as $pattern_slug ) {
			if ( ! is_string( $pattern_slug ) || '' === $pattern_slug ) {
				$blockers[] = 'pattern-slug-invalid';
				continue;
			}

			$content = $this->registered_pattern_content( $pattern_slug );
			if ( null === $content ) {
				$blockers[] = 'pattern-unavailable:' . $pattern_slug;
				continue;
			}

			$pattern_contents[ $pattern_slug ] = $content;
		}

		$blockers = array_values( array_unique( $blockers ) );
		sort( $blockers );

		$source_id      = $source instanceof WP_Post ? (int) $source->ID : 0;
		$source_content = $source instanceof WP_Post ? (string) $source->post_content : '';
		$source_sha     = 0 < $source_id ? hash( 'sha256', $source_content ) : null;
		$source_path    = $source instanceof WP_Post ? $this->post_path( $source ) : null;
		$content_remap  = $source instanceof WP_Post && is_array( $page )
			? $this->content_remap_planner->plan( $source, $page )
			: null;
		$composition    = implode( "\n\n", array_values( $pattern_contents ) );
		$plan_material  = array(
			'preset'        => $preset_id,
			'page_key'      => $page_key,
			'source_id'     => $source_id,
			'source_sha256' => $source_sha,
			'source_path'   => $source_path,
			'patterns'               => array_keys( $pattern_contents ),
			'composition'            => hash( 'sha256', $composition ),
			'content_remap_plan_sha' => is_array( $content_remap ) ? ( $content_remap['plan_sha256'] ?? null ) : null,
		);
		$plan_sha       = hash( 'sha256', (string) wp_json_encode( $plan_material ) );

		return array(
			'schema_version' => 1,
			'mode'           => 'native-replatform-plan',
			'ready'          => array() === $blockers,
			'preset'         => $preset_id,
			'page_key'       => $page_key,
			'page'           => $page,
			'source'         => $source instanceof WP_Post
				? array(
					'id'             => $source_id,
					'title'          => get_the_title( $source ),
					'post_name'      => (string) $source->post_name,
					'path'           => $source_path,
					'content_sha256' => $source_sha,
				)
				: null,
			'patterns'       => array_keys( $pattern_contents ),
			'composition'    => $composition,
			'content_remap'  => $content_remap,
			'plan_sha256'    => $plan_sha,
			'existing_draft' => $this->existing_draft_id( $source_id, $page_key, $plan_sha ),
			'blockers'       => $blockers,
			'safety'         => array(
				'sandbox_only'             => true,
				'source_post_mutation'     => false,
				'public_post_creation'     => false,
				'legacy_visual_parity'     => false,
				'native_draft_only'        => true,
				'canonical_change_allowed' => false,
			),
		);
	}

	/**
	 * Return plans for all singleton pages in the active preset.
	 *
	 * @param string|null $preset_id Optional preset override.
	 * @return list<array<string,mixed>>
	 */
	public function plans( ?string $preset_id = null ): array {
		$preset_id = null === $preset_id ? $this->active_preset_id() : sanitize_key( $preset_id );
		if ( null === $preset_id || ! function_exists( 'seo_geo_theme_preset_document' ) ) {
			return array();
		}

		$document = seo_geo_theme_preset_document( $preset_id, 'content-map.json' );
		if ( ! is_array( $document ) ) {
			return array();
		}

		$locale = function_exists( 'seo_geo_theme_preset_locale' ) ? seo_geo_theme_preset_locale() : 'en_US';
		$pages  = $document['locales'][ $locale ]['pages'] ?? $document['locales']['en_US']['pages'] ?? null;
		if ( ! is_array( $pages ) ) {
			return array();
		}

		$plans = array();
		foreach ( $pages as $page ) {
			if ( is_array( $page ) && isset( $page['key'] ) && is_string( $page['key'] ) ) {
				$plans[] = $this->plan( $page['key'], $preset_id );
			}
		}

		return $plans;
	}

	/**
	 * Create or reuse one private draft containing the native composition.
	 *
	 * @param string      $page_key  Preset page key.
	 * @param string|null $preset_id Optional preset override.
	 * @return array<string,mixed>|WP_Error
	 */
	public function create_draft( string $page_key, ?string $preset_id = null ): array|WP_Error {
		if ( ! $this->sandbox_active() ) {
			return new WP_Error( 'seo_geo_replatform_sandbox_required', 'Native replatform drafts may only be created inside an accepted sandbox.' );
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error( 'seo_geo_replatform_forbidden', 'Administrator capability is required.' );
		}

		$plan = $this->plan( $page_key, $preset_id );
		if ( true !== ( $plan['ready'] ?? false ) ) {
			return new WP_Error(
				'seo_geo_replatform_not_ready',
				'Native replatform plan is blocked: ' . implode( ', ', is_array( $plan['blockers'] ?? null ) ? $plan['blockers'] : array() )
			);
		}

		$existing = (int) ( $plan['existing_draft'] ?? 0 );
		if ( 0 < $existing ) {
			return array(
				'schema_version' => 1,
				'mode'           => 'native-replatform-draft',
				'status'         => 'existing',
				'draft_id'       => $existing,
				'source_id'      => (int) $plan['source']['id'],
				'page_key'       => (string) $plan['page_key'],
				'preset'                    => (string) $plan['preset'],
				'plan_sha256'               => (string) $plan['plan_sha256'],
				'content_remap_plan_sha256' => is_array( $plan['content_remap'] ?? null )
					? (string) ( $plan['content_remap']['plan_sha256'] ?? '' )
					: '',
			);
		}

		$source_id = (int) $plan['source']['id'];
		$source    = get_post( $source_id );
		if ( ! $source instanceof WP_Post ) {
			return new WP_Error( 'seo_geo_replatform_source_drift', 'The source page became unavailable before draft creation.' );
		}

		$draft_id = wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_status'  => 'draft',
				'post_title'   => sprintf(
					/* translators: %s: preserved source page title. */
					__( 'Native draft — %s', 'seo-geo-migration-bridge' ),
					get_the_title( $source )
				),
				'post_name'    => 'seo-geo-native-' . sanitize_title( (string) $plan['page_key'] ) . '-' . $source_id,
				'post_content' => (string) $plan['composition'],
				'post_parent'  => 0,
			),
			true
		);

		if ( $draft_id instanceof WP_Error ) {
			return $draft_id;
		}

		$meta = array(
			self::SOURCE_ID_META   => $source_id,
			self::SOURCE_SHA_META  => (string) $plan['source']['content_sha256'],
			self::SOURCE_PATH_META => (string) $plan['source']['path'],
			self::PRESET_META      => (string) $plan['preset'],
			self::PAGE_KEY_META    => (string) $plan['page_key'],
			self::PLAN_SHA_META       => (string) $plan['plan_sha256'],
			self::CREATED_AT_META     => gmdate( DATE_ATOM ),
			self::COMPOSITION_META    => array_values( $plan['patterns'] ),
			self::REMAP_PLAN_SHA_META => is_array( $plan['content_remap'] ?? null )
				? (string) ( $plan['content_remap']['plan_sha256'] ?? '' )
				: '',
			self::REMAP_SUMMARY_META  => is_array( $plan['content_remap'] ?? null )
				? array(
					'counts'        => $plan['content_remap']['counts'] ?? array(),
					'manual_review' => $plan['content_remap']['manual_review'] ?? array(),
				)
				: array(),
		);

		foreach ( $meta as $key => $value ) {
			update_post_meta( $draft_id, $key, $value );
		}

		return array(
			'schema_version' => 1,
			'mode'           => 'native-replatform-draft',
			'status'         => 'created',
			'draft_id'       => $draft_id,
			'source_id'      => $source_id,
			'page_key'       => (string) $plan['page_key'],
			'preset'                    => (string) $plan['preset'],
			'plan_sha256'               => (string) $plan['plan_sha256'],
			'content_remap_plan_sha256' => is_array( $plan['content_remap'] ?? null )
				? (string) ( $plan['content_remap']['plan_sha256'] ?? '' )
				: '',
			'safety'                    => array(
				'source_unchanged' => hash_equals(
					(string) $plan['source']['content_sha256'],
					hash( 'sha256', (string) get_post_field( 'post_content', $source_id ) )
				),
				'draft_only'       => 'draft' === get_post_status( $draft_id ),
			),
		);
	}

	/**
	 * Resolve active preset.
	 */
	private function active_preset_id(): ?string {
		if ( ! function_exists( 'seo_geo_theme_active_preset_id' ) ) {
			return null;
		}

		$value = seo_geo_theme_active_preset_id();

		return is_string( $value ) && '' !== $value ? $value : null;
	}

	/**
	 * Resolve one localized page definition.
	 *
	 * @param string $preset_id Preset identifier.
	 * @param string $page_key  Preset page key.
	 * @return array<string,mixed>|null
	 */
	private function page_definition( string $preset_id, string $page_key ): ?array {
		$document = seo_geo_theme_preset_document( $preset_id, 'content-map.json' );
		if ( ! is_array( $document ) ) {
			return null;
		}

		$locale = function_exists( 'seo_geo_theme_preset_locale' ) ? seo_geo_theme_preset_locale() : 'en_US';
		$pages  = $document['locales'][ $locale ]['pages'] ?? $document['locales']['en_US']['pages'] ?? null;
		if ( ! is_array( $pages ) ) {
			return null;
		}

		foreach ( $pages as $page ) {
			if ( is_array( $page ) && ( $page['key'] ?? null ) === $page_key ) {
				return $page;
			}
		}

		return null;
	}

	/**
	 * Resolve the preserved source singleton without mutation.
	 *
	 * @param array<string,mixed> $page Preset page definition.
	 */
	private function resolve_source_post( array $page ): ?WP_Post {
		$role = isset( $page['role'] ) && is_string( $page['role'] ) ? sanitize_key( $page['role'] ) : '';
		$key  = isset( $page['key'] ) && is_string( $page['key'] ) ? sanitize_key( $page['key'] ) : '';
		$slug = isset( $page['slug'] ) && is_string( $page['slug'] ) ? sanitize_title( $page['slug'] ) : '';

		$source_id = match ( $role ) {
			'front-page' => (int) get_option( 'page_on_front', 0 ),
			'posts-page' => (int) get_option( 'page_for_posts', 0 ),
			default      => 'privacy-policy' === $key ? (int) get_option( 'wp_page_for_privacy_policy', 0 ) : 0,
		};

		if ( 0 < $source_id ) {
			$post = get_post( $source_id );
			if ( $post instanceof WP_Post && 'page' === $post->post_type ) {
				return $post;
			}
		}

		if ( '' !== $slug ) {
			$post = get_page_by_path( $slug, OBJECT, 'page' );
			if ( $post instanceof WP_Post ) {
				return $post;
			}
		}

		return null;
	}

	/**
	 * Resolve native pattern content from WordPress' registered pattern authority.
	 *
	 * @param string $slug Registered block-pattern slug.
	 */
	private function registered_pattern_content( string $slug ): ?string {
		$pattern = WP_Block_Patterns_Registry::get_instance()->get_registered( $slug );
		if ( ! is_array( $pattern ) || ! isset( $pattern['content'] ) || ! is_string( $pattern['content'] ) ) {
			return null;
		}

		$content = trim( $pattern['content'] );

		return '' !== $content ? $content : null;
	}

	/**
	 * Resolve a prior equivalent native draft.
	 *
	 * The candidate list is deliberately bounded and filtered in PHP to avoid
	 * introducing an unbounded meta query into an administrator workflow.
	 *
	 * @param int    $source_id Preserved source post ID.
	 * @param string $page_key  Preset page key.
	 * @param string $plan_sha  Deterministic composition plan hash.
	 */
	private function existing_draft_id( int $source_id, string $page_key, string $plan_sha ): int {
		if ( 0 >= $source_id ) {
			return 0;
		}

		$posts = get_posts(
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

		foreach ( $posts as $post_id ) {
			$post_id = (int) $post_id;
			if (
				(int) get_post_meta( $post_id, self::SOURCE_ID_META, true ) === $source_id
				&& (string) get_post_meta( $post_id, self::PAGE_KEY_META, true ) === $page_key
				&& (string) get_post_meta( $post_id, self::PLAN_SHA_META, true ) === $plan_sha
			) {
				return $post_id;
			}
		}

		return 0;
	}

	/**
	 * Return the public path of one preserved source.
	 *
	 * @param WP_Post $post Preserved source post.
	 */
	private function post_path( WP_Post $post ): string {
		$url  = get_permalink( $post );
		$path = wp_parse_url( $url, PHP_URL_PATH );

		return is_string( $path ) && '' !== $path ? $path : '/';
	}

	/**
	 * Confirm that the accepted isolated sandbox authority is active.
	 */
	private function sandbox_active(): bool {
		return defined( 'SEO_GEO_MIGRATION_SANDBOX' ) && true === SEO_GEO_MIGRATION_SANDBOX;
	}
}
