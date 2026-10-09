<?php
/**
 * Read-only permalink structure inspector.
 *
 * @package SeoGeoManager
 */

declare(strict_types=1);

namespace SeoGeo\Manager\Support;

final class PermalinkInspector {
	private const PLACEHOLDER_PATTERN = '/(\{[a-f0-9]{32,64}\})(category|postname|year|monthnum|day|hour|minute|second|author|pagename)\1/i';

	/**
	 * Build a bounded, read-only permalink repair preview.
	 *
	 * The restored token sequence is a syntactic recovery candidate only. It is
	 * deliberately not treated as authoritative historical SEO structure: a
	 * migrated/cloned database may contain a corrupted option that no longer
	 * reflects the public URLs which search engines saw before the migration.
	 *
	 * @return array<string, mixed>
	 */
	public static function preview(): array {
		$current  = (string) get_option( 'permalink_structure', '' );
		$tokens   = array();
		$proposed = preg_replace_callback(
			self::PLACEHOLDER_PATTERN,
			static function ( array $matches ) use ( &$tokens ): string {
				$token = strtolower( (string) $matches[2] );
				if ( '' !== $token ) {
					$tokens[] = $token;
				}
				return '%' . $token . '%';
			},
			$current
		);
		$proposed = is_string( $proposed ) ? $proposed : $current;

		$malformed_fragments = array();
		if ( preg_match_all( '/\{[a-f0-9]{32,64}\}/i', $proposed, $matches ) ) {
			$malformed_fragments = array_values( array_unique( array_filter( $matches[0], 'is_string' ) ) );
		}

		$changed        = $proposed !== $current;
		$has_postname   = false !== strpos( $proposed, '%postname%' );
		$safe_candidate = $changed && $has_postname && array() === $malformed_fragments;
		$post_counts    = wp_count_posts( 'post' );
		$published      = isset( $post_counts->publish ) ? (int) $post_counts->publish : 0;

		return array(
			'mode'                   => 'preview',
			'write_performed'        => false,
			'current_structure'      => $current,
			'proposed_structure'     => $proposed,
			'candidate_kind'         => 'syntactic-placeholder-recovery',
			'seo_authority_verified' => false,
			'current_fingerprint'    => self::fingerprint( $current ),
			'restored_tokens'        => array_values( array_unique( $tokens ) ),
			'malformed_fragments'    => $malformed_fragments,
			'changed'                => $changed,
			'safe_candidate'         => $safe_candidate,
			'published_posts'        => $published,
			'redirect_plan_required' => 0 < $published,
			'rewrite_flush_required' => true,
			'apply_blocked'          => true,
			'next_action'            => $safe_candidate ? 'build-permalink-redirect-plan' : 'manual-permalink-review',
			'environment'            => EnvironmentPolicy::snapshot(),
			'policy'                 => array(
				'preview_only'                         => true,
				'no_option_write'                      => true,
				'no_rewrite_flush'                     => true,
				'redirect_plan_before_apply'           => true,
				'syntactic_candidate_not_seo_authority'=> true,
			),
		);
	}

	private static function fingerprint( string $structure ): string {
		return hash(
			'sha256',
			(string) wp_json_encode(
				array(
					'home_url'            => home_url( '/' ),
					'site_url'            => site_url( '/' ),
					'permalink_structure' => $structure,
				)
			)
		);
	}
}
