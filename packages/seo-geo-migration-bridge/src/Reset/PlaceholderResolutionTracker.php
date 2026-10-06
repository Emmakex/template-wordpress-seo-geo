<?php
/**
 * Placeholder resolution tracking for reviewed Corporate Home content.
 *
 * @package SeoGeoMigrationBridge
 */

declare(strict_types=1);

namespace SeoGeo\MigrationBridge\Reset;

/**
 * Tracks reviewed replacements without unlocking publication until those values
 * have been successfully hydrated into the clean Home draft.
 */
final class PlaceholderResolutionTracker {
	public const PENDING_META = '_seo_geo_home_pending_placeholder_resolution_v1';

	/** Register Content Kit change tracking. */
	public static function boot(): void {
		add_action( 'updated_option', array( self::class, 'after_option_update' ), 20, 3 );
	}

	/**
	 * Record which unresolved placeholders differ from their generated baseline.
	 *
	 * Saving reviewed values does not itself make the draft publishable. The pending
	 * set is committed only after NativeHomeHydrator successfully applies that kit.
	 * Reverting a field back to its generated prompt removes it from the pending set.
	 *
	 * @param string $option    Updated option name.
	 * @param mixed  $old_value Previous option value.
	 * @param mixed  $value     New option value.
	 */
	public static function after_option_update( string $option, mixed $old_value, mixed $value ): void {
		unset( $old_value );

		if ( CorporateHomeContentKit::OPTION !== $option || ! is_array( $value ) ) {
			return;
		}

		$draft_id = max( 0, (int) ( $value['draft_id'] ?? 0 ) );
		if ( 0 >= $draft_id || 'page' !== get_post_type( $draft_id ) ) {
			return;
		}

		$placeholder_slots = get_post_meta( $draft_id, AutomaticHomeContentStateKit::PLACEHOLDER_META, true );
		$baseline          = get_post_meta( $draft_id, AutomaticHomeContentStateKit::PLACEHOLDER_BASELINE_META, true );
		if ( ! is_array( $placeholder_slots ) || array() === $placeholder_slots || ! is_array( $baseline ) ) {
			delete_post_meta( $draft_id, self::PENDING_META );
			return;
		}

		$new_values = is_array( $value['values'] ?? null ) ? $value['values'] : array();
		$pending    = array();

		foreach ( $placeholder_slots as $slot_id ) {
			if ( ! is_string( $slot_id ) || '' === $slot_id || ! isset( $baseline[ $slot_id ] ) ) {
				continue;
		}
			if ( ! array_key_exists( $slot_id, $new_values ) ) {
				continue;
			}

			$baseline_hash = is_string( $baseline[ $slot_id ] ) ? $baseline[ $slot_id ] : '';
			$current_hash  = self::fingerprint_value( $new_values[ $slot_id ] );
			if ( '' !== $baseline_hash && ! hash_equals( $baseline_hash, $current_hash ) ) {
				$pending[] = $slot_id;
			}
		}

		update_post_meta( $draft_id, self::PENDING_META, array_values( array_unique( $pending ) ) );
	}

	/**
	 * Commit pending reviewed replacements after successful Home hydration.
	 *
	 * @param int $draft_id Hydrated clean Home draft ID.
	 */
	public static function commit_after_hydration( int $draft_id ): void {
		if ( 0 >= $draft_id ) {
			return;
		}

		$pending      = get_post_meta( $draft_id, self::PENDING_META, true );
		$placeholders = get_post_meta( $draft_id, AutomaticHomeContentStateKit::PLACEHOLDER_META, true );
		if ( ! is_array( $pending ) || array() === $pending ) {
			return;
		}
		if ( ! is_array( $placeholders ) || array() === $placeholders ) {
			delete_post_meta( $draft_id, self::PENDING_META );
			return;
		}

		$pending      = array_values( array_filter( $pending, 'is_string' ) );
		$placeholders = array_values( array_filter( $placeholders, 'is_string' ) );
		$resolved     = array_values( array_intersect( $placeholders, $pending ) );
		$remaining    = array_values( array_diff( $placeholders, $resolved ) );

		if ( array() === $resolved ) {
			delete_post_meta( $draft_id, self::PENDING_META );
			return;
		}

		update_post_meta( $draft_id, AutomaticHomeContentStateKit::PLACEHOLDER_META, $remaining );

		$state = get_post_meta( $draft_id, AutomaticHomeContentStateKit::STATE_META, true );
		if ( is_array( $state ) ) {
			$slot_states = is_array( $state['slot_states'] ?? null ) ? $state['slot_states'] : array();
			foreach ( $resolved as $slot_id ) {
				$slot_states[ $slot_id ] = 'authored';
			}

			$state['slot_states']       = $slot_states;
			$state['placeholder_slots'] = $remaining;
			$state['publishable']       = array() === $remaining;
			if ( array() === $remaining ) {
				$state['mode'] = 'reviewed-content';
			}

			update_post_meta( $draft_id, AutomaticHomeContentStateKit::STATE_META, $state );
		}

		delete_post_meta( $draft_id, self::PENDING_META );
	}

	/**
	 * Fingerprint one semantic slot value for baseline comparison.
	 *
	 * @param mixed $value Slot value.
	 */
	public static function fingerprint_value( mixed $value ): string {
		if ( is_array( $value ) ) {
			$material = (string) wp_json_encode( $value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		} else {
			$material = is_scalar( $value ) ? trim( (string) $value ) : '';
		}

		return hash( 'sha256', $material );
	}
}
