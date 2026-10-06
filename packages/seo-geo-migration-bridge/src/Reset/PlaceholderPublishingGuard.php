<?php
/**
 * Publishing guard for semantic migration placeholders.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge\Reset;

/**
 * Keeps generated drafts non-public until semantic placeholder slots are resolved.
 */
final class PlaceholderPublishingGuard {
	/** Register the publish-safety filter. */
	public static function boot(): void {
		add_filter( 'wp_insert_post_data', array( self::class, 'guard' ), 20, 4 );
	}

	/**
	 * Force an unfinished clean Home back to draft when a public status is requested.
	 *
	 * Both untouched preset scaffolds and hydrated drafts with unresolved semantic
	 * placeholders are non-publishable. This runs before the database write.
	 *
	 * @param array<string,mixed> $data                Sanitized post data.
	 * @param array<string,mixed> $postarr             Raw post payload.
	 * @param array<string,mixed> $unsanitized_postarr Unsanitized post payload.
	 * @param bool                $update              Whether an existing post is updated.
	 * @return array<string,mixed>
	 */
	public static function guard( array $data, array $postarr, array $unsanitized_postarr, bool $update ): array {
		unset( $unsanitized_postarr );

		if ( ! $update || 'page' !== (string) ( $data['post_type'] ?? '' ) ) {
			return $data;
		}

		$status = (string) ( $data['post_status'] ?? '' );
		if ( ! in_array( $status, array( 'publish', 'future' ), true ) ) {
			return $data;
		}

		$post_id = max( 0, (int) ( $postarr['ID'] ?? 0 ) );
		if ( 0 >= $post_id ) {
			return $data;
		}

		$content_state = (string) get_post_meta( $post_id, CleanHomeRebuilder::CONTENT_STATE_META, true );
		$placeholders  = get_post_meta( $post_id, AutomaticHomeContentStateKit::PLACEHOLDER_META, true );
		$has_prompts   = is_array( $placeholders ) && array() !== array_filter( $placeholders, 'is_string' );
		$is_scaffold   = NativeHomeHydrator::SCAFFOLD_STATE === $content_state;

		if ( ! $is_scaffold && ! $has_prompts ) {
			return $data;
		}

		$data['post_status'] = 'draft';
		set_transient( 'seo_geo_placeholder_publish_blocked_' . $post_id, 1, MINUTE_IN_SECONDS );

		return $data;
	}
}
