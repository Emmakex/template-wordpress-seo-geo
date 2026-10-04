<?php
/**
 * Clean Corporate inner-page SEO/GEO handoff.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge\Reset;

use SeoGeo\Core\Seo\IndexabilityResolver;
use SeoGeo\Core\Seo\NativeSeoMetadata;
use WP_Error;
use WP_Post;

/**
 * Translates safe rescued SEO signals into provider-neutral native metadata.
 */
final class CorporatePageSeoHandoff {
	public const REPORT_META = '_seo_geo_clean_page_seo_handoff_report_v1';

	/**
	 * Construct the generic Corporate page SEO handoff.
	 */
	public function __construct(
		private RescueManifest $manifest,
		private CleanCorporatePageRebuilder $builder,
		private NativeCorporatePageHydrator $hydrator
	) {
	}

	/**
	 * Build a read-only handoff plan.
	 *
	 * @param string $page_key Corporate page key.
	 * @return array<string,mixed>
	 */
	public function plan( string $page_key ): array {
		$page_key = sanitize_key( $page_key );
		$blockers = array();
		$page     = $this->builder->plan( $page_key );
		$manifest = $this->manifest->saved();
		$draft_id = (int) ( $page['existing_draft'] ?? 0 );
		$draft    = 0 < $draft_id ? get_post( $draft_id ) : null;

		if ( ! class_exists( NativeSeoMetadata::class ) ) {
			$blockers[] = 'native-seo-metadata-runtime-required';
		}
		if ( ! $draft instanceof WP_Post || 'page' !== $draft->post_type || 'draft' !== $draft->post_status ) {
			$blockers[] = 'clean-page-draft-required';
		}
		if ( $page_key !== (string) get_post_meta( $draft_id, CleanCorporatePageRebuilder::PAGE_KEY_META, true ) ) {
			$blockers[] = 'clean-page-key-mismatch';
		}
		if ( NativeCorporatePageHydrator::CONTENT_STATE !== (string) get_post_meta( $draft_id, CleanCorporatePageRebuilder::CONTENT_STATE_META, true ) ) {
			$blockers[] = 'hydrated-page-required';
		}

		$hydration_plan = $this->hydrator->plan( $page_key );
		if ( true !== ( $hydration_plan['ready'] ?? false ) ) {
			$blockers[] = 'hydrated-page-drift-or-plan-blocked';
		}

		$source_id = (int) get_post_meta( $draft_id, CleanCorporatePageRebuilder::SOURCE_ID_META, true );
		$resource  = is_array( $manifest ) ? $this->resource( $manifest, $source_id ) : null;
		if ( ! is_array( $resource ) ) {
			$blockers[] = 'rescued-page-resource-required';
		}

		$source_content = 0 < $source_id ? get_post_field( 'post_content', $source_id ) : null;
		$source_sha     = is_string( $source_content ) ? hash( 'sha256', $source_content ) : '';
		$rescued_sha    = is_array( $resource ) ? (string) ( $resource['content_sha256'] ?? '' ) : '';
		if ( '' === $source_sha || '' === $rescued_sha || ! hash_equals( $rescued_sha, $source_sha ) ) {
			$blockers[] = 'rescued-page-content-drift';
		}

		$source_path = is_array( $resource ) ? (string) ( $resource['path'] ?? '' ) : '';
		if ( ! $this->source_path_matches( $source_id, $source_path ) ) {
			$blockers[] = 'rescued-page-path-drift';
		}

		$seo          = is_array( $resource['seo'] ?? null ) ? $resource['seo'] : array();
		$provider     = $this->provider( $seo );
		$review_items = array();
		if ( 'ambiguous' === $provider ) {
			$review_items[] = 'multiple-legacy-seo-providers';
		}

		$title              = $this->plain_provider_value( $seo, $provider, 'title', $review_items );
		$description        = $this->plain_provider_value( $seo, $provider, 'description', $review_items );
		$canonical          = $this->provider_value( $seo, $provider, 'canonical' );
		$canonical_strategy = $this->canonical_strategy( $canonical, $source_path, $manifest, $review_items );
		$indexability       = $this->indexability( $seo, $provider );

		$blockers     = array_values( array_unique( $blockers ) );
		$review_items = array_values( array_unique( $review_items ) );
		sort( $blockers );
		sort( $review_items );

		$material = array(
			'schema_version'     => 1,
			'mode'               => 'clean-corporate-page-native-seo-handoff-plan',
			'page_key'           => $page_key,
			'draft_id'           => $draft_id,
			'source_id'          => $source_id,
			'provider'           => $provider,
			'overrides'          => array(
				'title'        => $title,
				'description'  => $description,
				'indexability' => $indexability,
			),
			'canonical_strategy' => $canonical_strategy,
			'review_items'       => $review_items,
			'source_path'        => $source_path,
			'source_sha256'      => $source_sha,
			'content_sha256'     => $draft instanceof WP_Post ? hash( 'sha256', (string) $draft->post_content ) : '',
		);
		$plan_sha = hash(
			'sha256',
			(string) wp_json_encode( $material, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE )
		);

		return array_merge(
			$material,
			array(
				'ready'           => array() === $blockers,
				'blockers'        => $blockers,
				'review_required' => array() !== $review_items,
				'plan_sha256'     => $plan_sha,
				'safety'          => array(
					'source_post_mutation'         => false,
					'source_url_change'            => false,
					'front_page_assignment_change' => false,
					'legacy_provider_required'     => false,
					'draft_only'                   => true,
				),
			)
		);
	}

	/**
	 * Apply safe native SEO metadata to one hydrated clean page draft.
	 *
	 * @param string $page_key Corporate page key.
	 * @return array<string,mixed>|WP_Error
	 */
	public function apply( string $page_key ): array|WP_Error {
		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error( 'seo_geo_page_seo_handoff_forbidden', 'Administrator capability is required.' );
		}

		$plan = $this->plan( $page_key );
		if ( true !== ( $plan['ready'] ?? false ) ) {
			return new WP_Error(
				'seo_geo_page_seo_handoff_not_ready',
				'Corporate page SEO handoff is blocked: ' . implode( ', ', is_array( $plan['blockers'] ?? null ) ? $plan['blockers'] : array() )
			);
		}

		$draft_id  = (int) ( $plan['draft_id'] ?? 0 );
		$overrides = is_array( $plan['overrides'] ?? null ) ? $plan['overrides'] : array();

		$this->write_or_delete( $draft_id, NativeSeoMetadata::META_TITLE, $overrides['title'] ?? null );
		$this->write_or_delete( $draft_id, NativeSeoMetadata::META_DESCRIPTION, $overrides['description'] ?? null );
		$this->write_or_delete( $draft_id, NativeSeoMetadata::META_INDEXABILITY, $overrides['indexability'] ?? null );
		delete_post_meta( $draft_id, NativeSeoMetadata::META_CANONICAL );

		$report_material = array(
			'schema_version'     => 1,
			'mode'               => 'clean-corporate-page-native-seo-handoff',
			'page_key'           => sanitize_key( $page_key ),
			'draft_id'           => $draft_id,
			'source_id'          => (int) ( $plan['source_id'] ?? 0 ),
			'provider'           => (string) ( $plan['provider'] ?? 'none' ),
			'overrides'          => $overrides,
			'canonical_strategy' => (string) ( $plan['canonical_strategy'] ?? 'native-self-canonical' ),
			'review_required'    => true === ( $plan['review_required'] ?? false ),
			'review_items'       => is_array( $plan['review_items'] ?? null ) ? $plan['review_items'] : array(),
			'plan_sha256'        => (string) ( $plan['plan_sha256'] ?? '' ),
		);
		$report_sha      = hash(
			'sha256',
			(string) wp_json_encode( $report_material, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE )
		);

		$existing = get_post_meta( $draft_id, self::REPORT_META, true );
		if ( is_array( $existing ) && hash_equals( (string) ( $existing['report_sha256'] ?? '' ), $report_sha ) ) {
			$existing['status'] = 'existing';

			return $existing;
		}

		$report                          = $report_material;
		$report['status']                = 'applied';
		$report['applied_at']            = gmdate( DATE_ATOM );
		$report['report_sha256']         = $report_sha;
		$report['cutover_seo_ready']     = true !== $report['review_required'];
		$report['source_unchanged']      = $this->source_unchanged( $draft_id );
		$report['source_path_unchanged'] = $this->source_path_matches(
			(int) $report['source_id'],
			(string) get_post_meta( $draft_id, CleanCorporatePageRebuilder::SOURCE_PATH_META, true )
		);
		$report['draft_only']            = 'draft' === get_post_status( $draft_id );

		update_post_meta( $draft_id, self::REPORT_META, $report );

		return $report;
	}

	/**
	 * Return the persisted handoff report.
	 *
	 * @param int $draft_id Clean page draft ID.
	 * @return array<string,mixed>|null
	 */
	public function report( int $draft_id ): ?array {
		$value = get_post_meta( $draft_id, self::REPORT_META, true );

		return is_array( $value ) ? $value : null;
	}

	/** Resolve captured SEO provider family. */
	private function provider( array $seo ): string {
		$yoast     = $this->has_any_key(
			$seo,
			array( '_yoast_wpseo_title', '_yoast_wpseo_metadesc', '_yoast_wpseo_canonical', '_yoast_wpseo_meta-robots-noindex', '_yoast_wpseo_meta-robots-nofollow' )
		);
		$rank_math = $this->has_any_key(
			$seo,
			array( 'rank_math_title', 'rank_math_description', 'rank_math_canonical_url', 'rank_math_robots' )
		);
		if ( $yoast && $rank_math ) {
			return 'ambiguous';
		}
		if ( $rank_math ) {
			return 'rank-math';
		}
		if ( $yoast ) {
			return 'yoast';
		}

		return 'none';
	}

	/** Resolve a safe plain legacy title/description. */
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

	/** Resolve one scalar legacy provider value. */
	private function provider_value( array $seo, string $provider, string $field ): ?string {
		$keys = array(
			'yoast'     => array(
				'title'       => '_yoast_wpseo_title',
				'description' => '_yoast_wpseo_metadesc',
				'canonical'   => '_yoast_wpseo_canonical',
			),
			'rank-math' => array(
				'title'       => 'rank_math_title',
				'description' => 'rank_math_description',
				'canonical'   => 'rank_math_canonical_url',
			),
		);
		if ( ! isset( $keys[ $provider ][ $field ] ) ) {
			return null;
		}

		$value = $seo[ $keys[ $provider ][ $field ] ] ?? null;

		return is_scalar( $value ) && '' !== trim( (string) $value ) ? trim( (string) $value ) : null;
	}

	/** Resolve canonical carryover policy without copying risky values. */
	private function canonical_strategy( ?string $canonical, string $source_path, ?array $manifest, array &$review_items ): string {
		if ( null === $canonical ) {
			return 'native-self-canonical';
		}

		$canonical_host = wp_parse_url( $canonical, PHP_URL_HOST );
		$canonical_path = wp_parse_url( $canonical, PHP_URL_PATH );
		$home_url       = is_array( $manifest ) ? (string) ( $manifest['site']['home_url'] ?? '' ) : '';
		$home_host      = wp_parse_url( $home_url, PHP_URL_HOST );
		if (
			is_string( $canonical_host )
			&& is_string( $home_host )
			&& 0 === strcasecmp( $canonical_host, $home_host )
			&& is_string( $canonical_path )
			&& $this->normalize_path( $canonical_path ) === $this->normalize_path( $source_path )
		) {
			return 'native-self-canonical';
		}

		$review_items[] = 'custom-canonical-review';

		return 'custom-canonical-review-required';
	}

	/** Resolve source indexability from legacy metadata. */
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

	/** Write one scalar override or delete it when absent. */
	private function write_or_delete( int $post_id, string $meta_key, mixed $value ): void {
		if ( is_scalar( $value ) && '' !== trim( (string) $value ) ) {
			update_post_meta( $post_id, $meta_key, (string) $value );

			return;
		}
		delete_post_meta( $post_id, $meta_key );
	}

	/** Whether one provider family has any captured value. */
	private function has_any_key( array $seo, array $keys ): bool {
		foreach ( $keys as $key ) {
			if ( array_key_exists( $key, $seo ) ) {
				return true;
			}
		}

		return false;
	}

	/** Find one rescued resource by source ID. */
	private function resource( array $manifest, int $source_id ): ?array {
		foreach ( is_array( $manifest['resources'] ?? null ) ? $manifest['resources'] : array() as $resource ) {
			if ( is_array( $resource ) && (int) ( $resource['id'] ?? 0 ) === $source_id ) {
				return $resource;
			}
		}

		return null;
	}

	/** Normalize one URL path. */
	private function normalize_path( string $path ): string {
		$path = '/' . trim( $path, '/' );

		return '/' === $path ? '/' : $path . '/';
	}

	/** Confirm rescued source content still matches provenance. */
	private function source_unchanged( int $draft_id ): bool {
		$source_id  = (int) get_post_meta( $draft_id, CleanCorporatePageRebuilder::SOURCE_ID_META, true );
		$source_sha = (string) get_post_meta( $draft_id, CleanCorporatePageRebuilder::SOURCE_SHA_META, true );
		$content    = 0 < $source_id ? get_post_field( 'post_content', $source_id ) : null;

		return is_string( $content ) && '' !== $source_sha && hash_equals( $source_sha, hash( 'sha256', $content ) );
	}

	/** Confirm source permalink still matches the rescued path. */
	private function source_path_matches( int $source_id, string $expected_path ): bool {
		if ( 0 >= $source_id || '' === $expected_path ) {
			return false;
		}

		$path = wp_parse_url( get_permalink( $source_id ), PHP_URL_PATH );

		return is_string( $path ) && $this->normalize_path( $path ) === $this->normalize_path( $expected_path );
	}
}
